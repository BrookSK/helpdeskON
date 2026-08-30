<?php

/**
 * Engine ÚNICA de execução de sequências de follow-up.
 * Usada por: sequências completas (editor visual) E follow-ups simples pós-envio
 * manual (que geram uma sequência mínima). Não há engine duplicada.
 *
 * O grafo (graph JSON) tem o formato:
 *   { "nodes": [ {id, type, data:{...}, next, nextYes, nextNo} ], "start": "<nodeId>" }
 *
 * Tipos de nó:
 *   send   (data: subject, body)        → envia e-mail, avança para next
 *   wait   (data: amount, unit)         → agenda next_run_at, avança para next
 *   condition (data: kind: replied|opened|clicked) → ramifica nextYes/nextNo
 *   tag    (data: label)                → adiciona tag, avança
 *   score  (data: delta)                → altera score, avança
 *   move   (data: column_id)            → move card do lead, avança
 *   end                                 → finaliza participante
 *
 * Execução dirigida por cron (cron/runSequences): processa participantes com
 * status=active e next_run_at <= agora, respeitando limites da sequência.
 */
class SequenceEngine
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ============ Inscrição ============

    /**
     * Adiciona um Lead a uma sequência (idempotente por (sequence, contact)).
     * @return array {success, participant_id, error}
     */
    public function enroll($sequenceId, $contactId, $userId = null)
    {
        $seq = $this->db->fetch("SELECT * FROM email_sequences WHERE id = ?", [$sequenceId]);
        if (!$seq || !$seq['is_active']) return ['success' => false, 'error' => 'Sequência inválida ou inativa.'];

        $contact = $this->db->fetch("SELECT unsubscribed, email_bounced, lead_email, phone FROM whatsapp_contacts WHERE id = ?", [$contactId]);
        if (!$contact) return ['success' => false, 'error' => 'Lead não encontrado.'];
        if (!empty($contact['unsubscribed'])) return ['success' => false, 'error' => 'Lead descadastrado.'];

        // Elegibilidade por CANAL da sequência (email / whatsapp / mixed).
        // - email:    exige e-mail
        // - whatsapp: exige telefone
        // - mixed:    exige e-mail OU telefone
        $channel = $seq['channel_type'] ?? 'email';
        $hasEmail = !empty($contact['lead_email']);
        $hasPhone = !empty($contact['phone']);
        // A própria sequência pode revelar o telefone depois (bloco reveal_phone),
        // então um lead sem telefone imediato ainda é elegível em whatsapp/mixed.
        $willRevealPhone = $this->graphHasPhoneReveal($seq);
        if ($channel === 'whatsapp' && !$hasPhone && !$willRevealPhone) {
            return ['success' => false, 'error' => 'Lead sem telefone para sequência de WhatsApp.'];
        }
        if ($channel === 'mixed' && !$hasEmail && !$hasPhone && !$willRevealPhone) {
            return ['success' => false, 'error' => 'Lead sem e-mail nem telefone.'];
        }
        if ($channel === 'email' && !$hasEmail) {
            return ['success' => false, 'error' => 'Lead sem e-mail cadastrado.'];
        }

        $existing = $this->db->fetch("SELECT * FROM sequence_participants WHERE sequence_id = ? AND contact_id = ?", [$sequenceId, $contactId]);
        if ($existing) {
            if (in_array($existing['status'], ['active', 'paused'])) {
                return ['success' => false, 'error' => 'Lead já está nesta sequência.'];
            }
            // Reativa participante finalizado/parado
            $this->db->update('sequence_participants', [
                'status' => 'active', 'current_node' => null, 'next_run_at' => date('Y-m-d H:i:s'),
                'stop_reason' => null, 'finished_at' => null,
            ], 'id = ?', [$existing['id']]);
            $participantId = $existing['id'];
        } else {
            $participantId = $this->db->insert('sequence_participants', [
                'sequence_id' => $sequenceId,
                'contact_id' => $contactId,
                'status' => 'active',
                'current_node' => null,
                'next_run_at' => date('Y-m-d H:i:s'), // pronto para rodar já
                'added_by' => $userId,
            ]);
        }

        (new LeadTimelineService())->add($contactId, 'sequence_start', 'Adicionado à sequência: ' . $seq['name'], ['sequence_id' => $sequenceId], $userId);

        // Sequências de prospecção: garante o card no board "Prospecção Automática"
        // e move o lead para "Em prospecção" ao iniciar a sequência.
        if (stripos($seq['name'] ?? '', 'Apollo') !== false || stripos($seq['name'] ?? '', 'ON Solu') !== false) {
            $this->ensureProspectingCard($contactId, $userId);
            $col = $this->resolveMoveColumn($contactId, ['column_name' => 'Em prospecção']);
            if ($col) $this->moveCard($contactId, $col);
        }

        return ['success' => true, 'participant_id' => $participantId];
    }

    /** Cria o card do lead na coluna "Novo" do board de Prospecção Automática, se faltar. */
    private function ensureProspectingCard($contactId, $userId = null)
    {
        try {
            $exists = $this->db->fetch("SELECT id FROM crm_cards WHERE contact_id = ? LIMIT 1", [$contactId]);
            if ($exists) return;
            $col = $this->db->fetch(
                "SELECT col.id FROM crm_columns col
                 JOIN crm_boards b ON col.board_id = b.id
                 WHERE b.name = 'Prospecção Automática' AND col.name = 'Novo'
                 ORDER BY col.position ASC LIMIT 1"
            );
            if (!$col) return;
            $c = $this->db->fetch("SELECT contact_name, assigned_to FROM whatsapp_contacts WHERE id = ?", [$contactId]);
            (new CrmBoard())->createCard([
                'column_id' => (int)$col['id'],
                'title' => $c['contact_name'] ?: ('Lead #' . $contactId),
                'contact_id' => $contactId,
                'created_by' => $userId,
                'assigned_to' => $c['assigned_to'] ?? $userId,
            ]);
        } catch (\Throwable $e) { /* silencioso */ }
    }

    /**
     * Ao detectar RESPOSTA do lead (e-mail ou WhatsApp), em vez de simplesmente
     * encerrar, redireciona cada sequência ativa para o nó de TRIAGEM POR IA
     * (type=ai), pulando as esperas restantes. A IA então decide interesse →
     * agendamento, ou sem interesse → unsubscribe/encerramento.
     *
     * Se a sequência não tiver nó de IA, cai no comportamento antigo (encerra).
     * Idempotente: se já está no nó de IA (ou depois dele), não reprocessa.
     *
     * @return int nº de participantes roteados para triagem
     */
    public function routeReplyToTriage($contactId, $reason = 'replied')
    {
        $parts = $this->db->fetchAll(
            "SELECT sp.*, s.graph FROM sequence_participants sp
             JOIN email_sequences s ON s.id = sp.sequence_id
             WHERE sp.contact_id = ? AND sp.status IN ('active','paused')",
            [$contactId]
        );
        if (empty($parts)) return 0;

        $routed = 0;
        $stopIds = [];
        foreach ($parts as $p) {
            $graph = json_decode($p['graph'] ?? '{}', true);
            $nodesById = [];
            foreach ($graph['nodes'] ?? [] as $n) $nodesById[$n['id']] = $n;

            // 1) Destino preferido: a saída "Resposta recebida" (nextReply) do nó
            //    atual do participante — respeita o que o operador desenhou.
            $target = null;
            $curId = $p['current_node'] ?? ($graph['start'] ?? null);
            if ($curId && isset($nodesById[$curId]) && !empty($nodesById[$curId]['nextReply'])) {
                $target = $nodesById[$curId]['nextReply'];
            }
            // 2) Senão, qualquer nextReply definido no grafo (primeiro encontrado).
            if (!$target) {
                foreach ($nodesById as $n) {
                    if (!empty($n['nextReply'])) { $target = $n['nextReply']; break; }
                }
            }
            // 3) Fallback: primeiro nó de IA (triagem embutida).
            if (!$target) {
                foreach ($nodesById as $n) {
                    if (($n['type'] ?? '') === 'ai') { $target = $n['id']; break; }
                }
            }
            if (!$target || !isset($nodesById[$target])) { $stopIds[] = $p; continue; }

            // Evita reprocessar se já está no destino
            if (($p['current_node'] ?? null) === $target) { continue; }

            // Salta para o destino agora (ignora esperas restantes)
            $this->db->update('sequence_participants', [
                'status' => 'active',
                'current_node' => $target,
                'next_run_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$p['id']]);
            $routed++;
        }

        if ($routed > 0) {
            (new LeadTimelineService())->add($contactId, 'note', 'Resposta detectada — encaminhado para triagem por IA.', ['reason' => $reason]);
            // Move para "Respondeu" e etiqueta (mesmo comportamento anterior de reply)
            if ($reason === 'replied') $this->onReplyMoveCard($contactId);
        }

        // Participantes sem nó de IA: mantém o comportamento antigo (encerra).
        foreach ($stopIds as $p) {
            $this->db->update('sequence_participants', [
                'status' => 'stopped', 'stop_reason' => $reason, 'finished_at' => date('Y-m-d H:i:s'), 'next_run_at' => null,
            ], 'id = ?', [$p['id']]);
        }
        if (!empty($stopIds) && $reason === 'replied') {
            $this->onReplyMoveCard($contactId);
        }

        return $routed;
    }

    /** Interrompe todas as sequências ativas de um lead (resposta, bounce, unsub, manual). */
    public function stopForContact($contactId, $reason = 'manual')
    {
        $parts = $this->db->fetchAll(
            "SELECT id, sequence_id FROM sequence_participants WHERE contact_id = ? AND status IN ('active','paused')",
            [$contactId]
        );
        foreach ($parts as $p) {
            $this->db->update('sequence_participants', [
                'status' => 'stopped', 'stop_reason' => $reason, 'finished_at' => date('Y-m-d H:i:s'), 'next_run_at' => null,
            ], 'id = ?', [$p['id']]);
        }
        if (!empty($parts)) {
            (new LeadTimelineService())->add($contactId, 'sequence_stop', 'Sequência(s) interrompida(s) — ' . $reason, ['reason' => $reason]);
            // Ao RESPONDER, move o card para a coluna "Respondeu" (quando definida na
            // campanha de prospecção que originou este lead). Aplica também etiqueta.
            if ($reason === 'replied') {
                $this->onReplyMoveCard($contactId);
            }
        }
        return count($parts);
    }

    /**
     * Move o card do lead para a coluna "Respondeu" da campanha de prospecção
     * associada (se houver) e aplica a etiqueta correspondente. Silencioso se não
     * houver campanha/coluna configurada.
     */
    private function onReplyMoveCard($contactId)
    {
        try {
            // Coluna "Respondeu" do board da campanha que captou este lead.
            $col = $this->db->fetch(
                "SELECT col.id
                 FROM apollo_leads al
                 JOIN apollo_campaigns c ON c.id = (
                     SELECT campaign_id FROM apollo_prospecting_log l
                     WHERE l.contact_id = ? AND l.campaign_id IS NOT NULL
                     ORDER BY l.id DESC LIMIT 1
                 )
                 JOIN crm_columns col ON col.board_id = c.board_id AND col.name = 'Respondeu'
                 WHERE al.contact_id = ?
                 LIMIT 1",
                [$contactId, $contactId]
            );
            // Fallback: qualquer coluna 'Respondeu' de board de prospecção do lead
            if (!$col) {
                $col = $this->db->fetch(
                    "SELECT col.id
                     FROM crm_cards cc
                     JOIN crm_columns cur ON cc.column_id = cur.id
                     JOIN crm_columns col ON col.board_id = cur.board_id AND col.name = 'Respondeu'
                     WHERE cc.contact_id = ?
                     ORDER BY cc.id DESC LIMIT 1",
                    [$contactId]
                );
            }
            if ($col && !empty($col['id'])) {
                $this->moveCard($contactId, (int)$col['id']);
            }
        } catch (\Throwable $e) {
            Logger::error('onReplyMoveCard', ['contact' => $contactId, 'error' => $e->getMessage()]);
        }
    }

    // ============ Execução (chamada pelo cron) ============

    /**
     * Processa os participantes prontos (next_run_at <= agora), respeitando limites.
     * @return array métricas da execução
     */
    public function processDue($maxBatch = 200)
    {
        $now = date('Y-m-d H:i:s');
        $due = $this->db->fetchAll(
            "SELECT sp.* FROM sequence_participants sp
             JOIN email_sequences s ON s.id = sp.sequence_id
             WHERE sp.status = 'active' AND s.is_active = 1
               AND sp.next_run_at IS NOT NULL AND sp.next_run_at <= ?
             ORDER BY sp.next_run_at ASC
             LIMIT " . (int) $maxBatch,
            [$now]
        );

        $stats = ['processed' => 0, 'sent' => 0, 'finished' => 0, 'skipped' => 0, 'errors' => 0];
        $sentByAccount = []; // controle de limite diário por sequência
        $maxStepsPerParticipant = 50; // trava de segurança contra loops no grafo

        foreach ($due as $p) {
            // Drena os nós INSTANTÂNEOS do participante numa mesma passada:
            // reveal/condição/tag/score/move/whatsapp/send avançam para "agora",
            // então continuamos executando até bater num 'wait' (agenda futuro),
            // finalizar, pular por janela/limite, ou atingir a trava de segurança.
            $current = $p;
            for ($i = 0; $i < $maxStepsPerParticipant; $i++) {
                try {
                    $r = $this->step($current, $sentByAccount);
                    $stats['processed']++;
                    if ($r === 'sent') $stats['sent']++;
                    elseif ($r === 'finished') $stats['finished']++;
                    elseif ($r === 'skipped') $stats['skipped']++;
                } catch (\Throwable $e) {
                    $stats['errors']++;
                    Logger::error('SequenceEngine step', ['participant' => $current['id'], 'error' => $e->getMessage()]);
                    $this->db->update('sequence_participants', ['status' => 'failed', 'stop_reason' => 'error'], 'id = ?', [$current['id']]);
                    break;
                }

                if ($r === 'finished') break;

                // Recarrega o participante para ver o estado após o passo
                $current = $this->db->fetch("SELECT * FROM sequence_participants WHERE id = ?", [$p['id']]);
                if (!$current || $current['status'] !== 'active') break;
                // Se o próximo passo está agendado para o futuro (wait) ou foi
                // reagendado (fora de janela / limite diário), para por aqui.
                if (empty($current['next_run_at']) || strtotime($current['next_run_at']) > time()) break;
            }
        }
        return $stats;
    }

    /**
     * MODO TESTE: executa o fluxo inteiro do participante de uma vez, pulando as
     * esperas (wait) e ignorando janela/limite de envio. Registra cada etapa nos
     * logs (sequence_executions). Retorna as etapas executadas.
     */
    public function runTest($participantId, $maxSteps = 40)
    {
        $p = $this->db->fetch("SELECT * FROM sequence_participants WHERE id = ?", [$participantId]);
        if (!$p) return ['error' => 'Participante não encontrado.'];

        // Reativa e volta ao início para um teste limpo
        $this->db->update('sequence_participants', [
            'status' => 'active', 'current_node' => null, 'next_run_at' => date('Y-m-d H:i:s'),
            'stop_reason' => null, 'finished_at' => null, 'ab_variant' => null,
        ], 'id = ?', [$participantId]);

        // Modo teste: remove bloqueios de descadastro/bounce do contato para não
        // interromper o teste logo no início (dado real de opt-out é preservado em produção).
        $this->db->update('whatsapp_contacts', ['unsubscribed' => 0, 'email_bounced' => 0], 'id = ?', [$p['contact_id']]);

        $steps = [];
        $sentByAccount = [];
        // Resolve o start do grafo para rotular o primeiro passo corretamente
        $seqRow = $this->db->fetch("SELECT graph FROM email_sequences WHERE id = ?", [$p['sequence_id']]);
        $graph0 = json_decode($seqRow['graph'] ?? '{}', true);
        $startNode = $graph0['start'] ?? ($graph0['nodes'][0]['id'] ?? null);

        for ($i = 0; $i < $maxSteps; $i++) {
            $p = $this->db->fetch("SELECT * FROM sequence_participants WHERE id = ?", [$participantId]);
            if (!$p || $p['status'] !== 'active') break;
            $nodeBefore = $p['current_node'] ?: $startNode;
            try {
                $r = $this->step($p, $sentByAccount, true); // testMode = true
                $steps[] = ['node' => $nodeBefore, 'result' => $r];
                if ($r === 'finished') break;
            } catch (\Throwable $e) {
                Logger::error('SequenceEngine runTest', ['participant' => $participantId, 'error' => $e->getMessage()]);
                $steps[] = ['error' => $e->getMessage()];
                break;
            }
        }
        $final = $this->db->fetch("SELECT status, stop_reason, ab_variant FROM sequence_participants WHERE id = ?", [$participantId]);
        return ['success' => true, 'steps' => $steps, 'final' => $final];
    }

    /**
     * Reexecuta APENAS um nó específico de um participante, isoladamente, sem
     * alterar o current_node/next_run_at nem avançar o fluxo. Serve para
     * testar/forçar uma etapa (ex: reenviar o WhatsApp que falhou) sem refazer
     * a sequência inteira. Ignora janela/limite diário.
     * @return array {success, result, detail}
     */
    public function runSingleNode($participantId, $nodeId)
    {
        $participant = $this->db->fetch("SELECT * FROM sequence_participants WHERE id = ?", [$participantId]);
        if (!$participant) return ['success' => false, 'error' => 'Participante não encontrado.'];

        $seq = $this->db->fetch("SELECT * FROM email_sequences WHERE id = ?", [$participant['sequence_id']]);
        $graph = json_decode($seq['graph'] ?? '{}', true);
        $nodes = [];
        foreach (($graph['nodes'] ?? []) as $n) $nodes[$n['id']] = $n;
        if (!isset($nodes[$nodeId])) return ['success' => false, 'error' => 'Etapa não existe mais no fluxo.'];

        $node = $nodes[$nodeId];
        $type = $node['type'];
        $contactId = $participant['contact_id'];
        $result = 'done';
        $detail = null;

        try {
            switch ($type) {
                case 'send':
                    $r = $this->doSend($participant, $seq, $node, true); // testMode: ignora janela/limite
                    $result = ($r === true) ? 'done' : 'failed';
                    $detail = is_string($r) ? $r : null;
                    break;
                case 'whatsapp':
                    $r = $this->doWhatsapp($participant, $node);
                    $result = ($r === true) ? 'done' : 'failed';
                    $detail = is_string($r) ? $r : null;
                    break;
                case 'reveal_phone':
                    $this->doReveal($participant, $node['data'] ?? []);
                    $detail = 'Reveal solicitado/verificado.';
                    break;
                case 'condition':
                    $ok = $this->evalCondition($node['data']['kind'] ?? 'replied', $contactId, $participant);
                    $detail = 'Condição avaliada: ' . ($ok ? 'SIM' : 'NÃO');
                    break;
                case 'tag':
                    $label = trim($node['data']['label'] ?? '');
                    if ($label) { $this->applyLabel($contactId, $label, $node['data']['color'] ?? null); $detail = 'Etiqueta: ' . $label; }
                    break;
                case 'score':
                    $delta = (int) ($node['data']['delta'] ?? 0);
                    if ($delta) (new LeadScoreService())->add($contactId, $delta, 'sequência (teste)');
                    $detail = 'Score ' . ($delta > 0 ? '+' : '') . $delta;
                    break;
                case 'ai':
                    $ai = $this->doAi($participant, $node);
                    if (($node['data']['mode'] ?? 'simple') === 'decision') {
                        $detail = 'IA decidiu: ' . (!empty($ai['decision']) ? 'SIM' : 'NÃO') . '. ' . ($ai['detail'] ?? '');
                    } else {
                        $detail = 'IA: ' . ($ai['detail'] ?? '');
                    }
                    if (!empty($ai['error'])) $result = 'failed';
                    break;
                case 'move':
                    $columnId = $this->resolveMoveColumn($contactId, $node['data'] ?? []);
                    if ($columnId) $this->moveCard($contactId, $columnId);
                    $detail = 'Card movido.';
                    break;
                case 'unsubscribe':
                    $this->unsubscribeContact($contactId, $node['data']['reason'] ?? 'Sem interesse (sequência)');
                    $detail = 'Lead removido da lista (descadastrado).';
                    break;
                case 'schedule':
                    $sch = $this->doSchedule($participant, $node);
                    $detail = $sch['detail'] ?? 'Link de agendamento enviado.';
                    if (!empty($sch['error'])) $result = 'failed';
                    break;
                case 'connect':
                    $conn = $this->doConnect($participant, $node);
                    $detail = $conn['detail'] ?? 'Conectado à sequência.';
                    if (!empty($conn['error'])) $result = 'failed';
                    break;
                case 'reply':
                    $rep = $this->doReply($participant, $node);
                    $detail = $rep['detail'] ?? 'Resposta enviada.';
                    if (!empty($rep['error'])) $result = 'failed';
                    break;
                case 'wait':
                    $detail = 'Aguardar (sem efeito no teste isolado).';
                    break;
                case 'end':
                    $detail = 'Encerrar (sem efeito no teste isolado).';
                    break;
                default:
                    $detail = 'Tipo sem ação de teste.';
            }
        } catch (\Throwable $e) {
            $result = 'failed';
            $detail = 'Erro: ' . $e->getMessage();
            Logger::error('SequenceEngine runSingleNode', ['participant' => $participantId, 'node' => $nodeId, 'error' => $e->getMessage()]);
        }

        // Registra a reexecução no log (aparece na aba "Etapas executadas")
        $this->logExec($participantId, $nodeId, $type, $result, $detail);
        return ['success' => true, 'result' => $result, 'detail' => $detail, 'node_type' => $type];
    }

    /** Executa um passo do participante (um nó). $testMode pula esperas/janela. */
    private function step($participant, &$sentByAccount, $testMode = false)
    {
        $seq = $this->db->fetch("SELECT * FROM email_sequences WHERE id = ?", [$participant['sequence_id']]);
        $graph = json_decode($seq['graph'] ?? '{}', true);
        if (empty($graph['nodes'])) { $this->finish($participant, 'completed'); return 'finished'; }

        $nodes = [];
        foreach ($graph['nodes'] as $n) $nodes[$n['id']] = $n;

        // Nó atual: se null, começa pelo start
        $nodeId = $participant['current_node'] ?: ($graph['start'] ?? ($graph['nodes'][0]['id'] ?? null));
        if (!$nodeId || !isset($nodes[$nodeId])) { $this->finish($participant, 'completed'); return 'finished'; }

        // Revalida: lead respondeu/descadastrou/bounce? interrompe
        $contact = $this->db->fetch("SELECT unsubscribed, email_bounced FROM whatsapp_contacts WHERE id = ?", [$participant['contact_id']]);
        if ($contact && (!empty($contact['unsubscribed']) || !empty($contact['email_bounced']))) {
            $this->finish($participant, !empty($contact['unsubscribed']) ? 'unsubscribed' : 'bounce');
            return 'skipped';
        }

        $node = $nodes[$nodeId];
        $type = $node['type'];
        $contactId = $participant['contact_id'];

        switch ($type) {
            case 'send':
                // Canal ausente no lead: pula o bloco (não finaliza a sequência).
                // Ex.: sequência mista onde este lead só tem telefone → pula o e-mail.
                if (!$this->contactHasChannel($contactId, 'email')) {
                    $this->advance($participant, $node['next'] ?? null, $nodes);
                    $this->logExec($participant['id'], $nodeId, $type, 'skipped', 'Lead sem e-mail: bloco de e-mail pulado.');
                    return 'skipped';
                }
                // Respeita janela de horário e limite diário (ignorado no modo teste)
                if (!$testMode) {
                    if (!$this->withinWindow($seq)) { $this->reschedule($participant, $this->nextWindowStart($seq)); return 'skipped'; }
                    $key = $seq['id'];
                    $sentByAccount[$key] = ($sentByAccount[$key] ?? 0);
                    if ($this->sentToday($seq['id']) + $sentByAccount[$key] >= (int) $seq['daily_limit']) {
                        $this->reschedule($participant, date('Y-m-d H:i:s', strtotime('+1 hour')));
                        return 'skipped';
                    }
                    $sentByAccount[$key] = ($sentByAccount[$key] ?? 0) + 1;
                }
                $sendResult = $this->doSend($participant, $seq, $node, $testMode);
                // Se o envio abortou por config (sem conta/e-mail) fora do teste, o
                // participante já foi finalizado; no teste seguimos para ver o fluxo.
                $this->advance($participant, $node['next'] ?? null, $nodes);
                $this->logExec($participant['id'], $nodeId, $type, $sendResult === true ? 'done' : 'failed', is_string($sendResult) ? $sendResult : null);
                return 'sent';

            case 'reveal_phone':
                // Reveal PROGRESSIVO: revela só os dados marcados (telefone e/ou e-mail)
                // que ainda faltam no lead. Economiza créditos.
                $this->doReveal($participant, $node['data'] ?? []);
                $this->advance($participant, $node['next'] ?? null, $nodes);
                $this->logExec($participant['id'], $nodeId, $type, 'done');
                return 'skipped';

            case 'whatsapp':
                // Canal ausente no lead: pula o bloco (não finaliza a sequência).
                if (!$this->contactHasChannel($contactId, 'whatsapp')) {
                    $this->advance($participant, $node['next'] ?? null, $nodes);
                    $this->logExec($participant['id'], $nodeId, $type, 'skipped', 'Lead sem telefone: bloco de WhatsApp pulado.');
                    return 'skipped';
                }
                $waResult = $this->doWhatsapp($participant, $node);
                $this->advance($participant, $node['next'] ?? null, $nodes);
                $this->logExec($participant['id'], $nodeId, $type, $waResult === true ? 'done' : 'failed', is_string($waResult) ? $waResult : null);
                return 'sent';

            case 'wait':
                // Modo teste: pula a espera e segue imediatamente
                $secs = $testMode ? 0 : $this->waitSeconds($node['data'] ?? []);
                $this->db->update('sequence_participants', [
                    'current_node' => $node['next'] ?? null,
                    'next_run_at' => date('Y-m-d H:i:s', time() + $secs),
                ], 'id = ?', [$participant['id']]);
                if (empty($node['next'])) $this->finish($participant, 'completed');
                $this->logExec($participant['id'], $nodeId, $type, 'waiting');
                return 'skipped';

            case 'condition':
                $branch = $this->evalCondition($node['data']['kind'] ?? 'replied', $contactId, $participant) ? ($node['nextYes'] ?? null) : ($node['nextNo'] ?? null);
                $this->advance($participant, $branch, $nodes);
                $this->logExec($participant['id'], $nodeId, $type, 'done');
                return 'skipped';

            case 'ai':
                $ai = $this->doAi($participant, $node);
                if (($node['data']['mode'] ?? 'simple') === 'decision') {
                    // ramifica conforme a decisão SIM/NÃO da IA
                    $branch = !empty($ai['decision']) ? ($node['nextYes'] ?? null) : ($node['nextNo'] ?? null);
                    $this->advance($participant, $branch, $nodes);
                } else {
                    $this->advance($participant, $node['next'] ?? null, $nodes);
                }
                $this->logExec($participant['id'], $nodeId, $type, empty($ai['error']) ? 'done' : 'failed', $ai['detail'] ?? null);
                return 'skipped';

            case 'tag':
                $label = trim($node['data']['label'] ?? '');
                if ($label) {
                    $this->applyLabel($contactId, $label, $node['data']['color'] ?? null);
                    (new LeadTimelineService())->add($contactId, 'tag', 'Etiqueta aplicada: ' . $label, ['tag' => $label]);
                }
                $this->advance($participant, $node['next'] ?? null, $nodes);
                $this->logExec($participant['id'], $nodeId, $type, 'done');
                return 'skipped';

            case 'score':
                $delta = (int) ($node['data']['delta'] ?? 0);
                if ($delta) (new LeadScoreService())->add($contactId, $delta, 'sequência');
                $this->advance($participant, $node['next'] ?? null, $nodes);
                $this->logExec($participant['id'], $nodeId, $type, 'done');
                return 'skipped';

            case 'move':
                $columnId = $this->resolveMoveColumn($contactId, $node['data'] ?? []);
                if ($columnId) $this->moveCard($contactId, $columnId);
                $this->advance($participant, $node['next'] ?? null, $nodes);
                $this->logExec($participant['id'], $nodeId, $type, 'done');
                return 'skipped';

            case 'unsubscribe':
                $this->unsubscribeContact($contactId, $node['data']['reason'] ?? 'Sem interesse (sequência)');
                $this->advance($participant, $node['next'] ?? null, $nodes);
                $this->logExec($participant['id'], $nodeId, $type, 'done');
                return 'skipped';

            case 'schedule':
                $sch = $this->doSchedule($participant, $node);
                $this->advance($participant, $node['next'] ?? null, $nodes);
                $this->logExec($participant['id'], $nodeId, $type, empty($sch['error']) ? 'done' : 'failed', $sch['detail'] ?? null);
                return 'sent';

            case 'connect':
                $conn = $this->doConnect($participant, $node);
                $this->logExec($participant['id'], $nodeId, $type, empty($conn['error']) ? 'done' : 'failed', $conn['detail'] ?? null);
                // Se configurado para encerrar a atual, finaliza aqui; senão avança.
                if (!empty($node['data']['stop_current'])) {
                    $this->finish($participant, 'connected');
                    return 'finished';
                }
                $this->advance($participant, $node['next'] ?? null, $nodes);
                return 'skipped';

            case 'reply':
                $rep = $this->doReply($participant, $node);
                $this->advance($participant, $node['next'] ?? null, $nodes);
                $this->logExec($participant['id'], $nodeId, $type, empty($rep['error']) ? 'done' : 'failed', $rep['detail'] ?? null);
                return 'sent';

            case 'end':
            default:
                $this->finish($participant, 'completed');
                $this->logExec($participant['id'], $nodeId, $type, 'done');
                return 'finished';
        }
    }

    // ---- Ações de nó ----

    private function doSend($participant, $seq, $node, $testMode = false)
    {
        $contactId = $participant['contact_id'];
        $contact = $this->db->fetch("SELECT id, lead_email, contact_name, push_name, phone FROM whatsapp_contacts WHERE id = ?", [$contactId]);
        if (empty($contact['lead_email'])) {
            if (!$testMode) $this->finish($participant, 'no_email');
            return 'Lead sem e-mail cadastrado';
        }

        // Conta de envio: da sequência ou a primeira ativa
        $account = $this->resolveAccount($seq['email_account_id']);
        if (!$account) {
            if (!$testMode) $this->finish($participant, 'no_account');
            return 'Nenhuma conta de e-mail ativa configurada';
        }

        $data = $node['data'] ?? [];

        // Fontes de conteúdo: texto inline OU template cadastrado (template_id / template_id_b).
        $subjectSrc = $data['subject'] ?? '(sem assunto)';
        $bodySrc = $data['body'] ?? '';
        if (!empty($data['template_id'])) {
            $tpl = $this->db->fetch("SELECT subject, body FROM message_templates WHERE id = ?", [(int)$data['template_id']]);
            if ($tpl) { $subjectSrc = $tpl['subject'] ?: $subjectSrc; $bodySrc = $tpl['body']; }
        }

        // Teste A/B PERSISTENTE por participante: a variante é sorteada uma única vez
        // (na primeira mensagem A/B) e gravada em sequence_participants.ab_variant.
        $variant = null;
        $abEnabled = !empty($data['ab_enabled']) || !empty($data['template_id_b']) || !empty($data['body_b']) || !empty($data['subject_b']);
        if ($abEnabled) {
            $variant = $participant['ab_variant'] ?? null;
            if (!$variant) {
                $variant = (random_int(0, 1) === 1) ? 'B' : 'A';
                $this->db->update('sequence_participants', ['ab_variant' => $variant], 'id = ?', [$participant['id']]);
                (new LeadTimelineService())->add($contactId, 'tag', 'Variante A/B atribuída: ' . $variant, ['ab_variant' => $variant]);
            }
            if ($variant === 'B') {
                if (!empty($data['template_id_b'])) {
                    $tplB = $this->db->fetch("SELECT subject, body FROM message_templates WHERE id = ?", [(int)$data['template_id_b']]);
                    if ($tplB) { $subjectSrc = $tplB['subject'] ?: $subjectSrc; $bodySrc = $tplB['body']; }
                } else {
                    if (!empty($data['subject_b'])) $subjectSrc = $data['subject_b'];
                    if (!empty($data['body_b'])) $bodySrc = $data['body_b'];
                }
            }
        }

        $subject = $this->render($subjectSrc, $contact);
        $body = $this->render($bodySrc, $contact);

        $res = (new EmailMessageService())->send([
            'contact_id' => $contactId,
            'account' => $account,
            'to' => $contact['lead_email'],
            'subject' => $subject,
            'body_html' => $body,
            'origin' => 'sequence',
            'sequence_participant_id' => $participant['id'],
            'node_id' => $node['id'],
            'ab_variant' => $variant,
            // Todos os e-mails de sequência recebem a assinatura padrão da empresa.
            'add_signature' => true,
        ]);
        return !empty($res['success']) ? true : ('Falha no envio: ' . ($res['error'] ?? 'desconhecida'));
    }

    private function doWhatsapp($participant, $node)
    {
        $contactId = $participant['contact_id'];
        $contact = $this->db->fetch("SELECT id, phone, contact_name, push_name, lead_email FROM whatsapp_contacts WHERE id = ?", [$contactId]);
        if (empty($contact['phone'])) {
            (new LeadTimelineService())->add($contactId, 'note', 'WhatsApp da sequência não enviado: lead sem telefone.');
            return 'Lead sem telefone';
        }
        $data = $node['data'] ?? [];
        $bodySrc = $data['body'] ?? '';
        if (!empty($data['template_id'])) {
            $tpl = $this->db->fetch("SELECT body FROM message_templates WHERE id = ?", [(int)$data['template_id']]);
            if ($tpl) $bodySrc = $tpl['body'];
        }
        $msg = $this->render($bodySrc, $contact);
        if (trim($msg) === '') return 'Mensagem vazia';

        // SEMPRE usa a instância PADRÃO para envios de sequência.
        $ctxRow = $this->db->fetch("SELECT remote_jid FROM whatsapp_contacts WHERE id = ?", [$contactId]);
        $default = $this->db->fetch("SELECT id, connection_status FROM whatsapp_instances WHERE is_default = 1 LIMIT 1");
        if (!$default) return 'Nenhuma instância padrão de WhatsApp definida. Defina uma instância como padrão em WhatsApp.';
        $instanceId = (int)$default['id'];

        // A instância padrão precisa estar conectada (senão a Evolution retorna "Connection Closed").
        if (!$this->isInstanceConnected($instanceId)) {
            return 'A instância padrão de WhatsApp não está conectada. Conecte-a em WhatsApp.';
        }

        try {
            $api = EvolutionApi::fromInstance($instanceId);
            if (!$api) return 'Instância padrão de WhatsApp indisponível';

            // Mesmo caminho do envio manual (que funciona): usa o remote_jid do lead
            // quando for um JID válido; caso contrário monta a partir do telefone.
            $existingJid = $ctxRow['remote_jid'] ?? '';
            $isRealJid = $existingJid && stripos($existingJid, 'lead_') === false && strpos($existingJid, '@') !== false;
            $jid = $isRealJid ? $existingJid : $api->normalizeJid($api->normalizeNumber($contact['phone']));

            // Resolve o JID REAL no WhatsApp (corrige o 9º dígito de números BR e
            // evita HTTP 400 ao enviar para um JID que o WhatsApp não reconhece).
            // Igual ao fluxo de "nova conversa" que funciona.
            $checkedExists = null; // null = não foi possível checar; true/false = resultado
            try {
                $phoneOnly = $api->extractPhone($jid);
                $check = $api->checkIsWhatsapp([$phoneOnly]);
                if (is_array($check)) {
                    foreach ($check as $item) {
                        // Casa o retorno com o número consultado (quando informado)
                        if (array_key_exists('exists', $item)) $checkedExists = !empty($item['exists']);
                        if (!empty($item['exists']) && !empty($item['jid'])) {
                            $jid = $api->normalizeJid($item['jid']);
                            $checkedExists = true;
                            break;
                        }
                    }
                }
            } catch (\Throwable $e) { /* segue com o jid normalizado */ }

            // Número não tem WhatsApp: não adianta enviar (a Evolution devolve HTTP 400).
            // Registra e falha com mensagem clara, sem gerar erro cru de API.
            if ($checkedExists === false) {
                $onlyDigits = preg_replace('/\D/', '', (string) $contact['phone']);
                $msgFail = 'Número sem WhatsApp: ' . $onlyDigits . ' não possui conta no WhatsApp.';
                (new LeadTimelineService())->add($contactId, 'note', 'WhatsApp da sequência não enviado: ' . $msgFail, ['channel' => 'whatsapp']);
                return $msgFail;
            }

            $result = $api->sendText($jid, $msg);
            // A Evolution retorna erro tanto em ['error'=>true] quanto em HTTP >= 400.
            if (is_array($result) && !empty($result['error'])) {
                $detail = $result['message'] ?? (is_string($result['error']) ? $result['error'] : 'erro');
                $httpCode = $result['http_code'] ?? '';
                // Inclui o corpo da resposta da Evolution (motivo real do 400)
                $bodyDetail = '';
                if (!empty($result['response'])) {
                    $resp = $result['response'];
                    if (is_array($resp)) {
                        $inner = $resp['response']['message'] ?? ($resp['message'] ?? null);
                        $bodyDetail = is_array($inner) ? json_encode($inner, JSON_UNESCAPED_UNICODE) : (string)($inner ?? json_encode($resp, JSON_UNESCAPED_UNICODE));
                    } else {
                        $bodyDetail = (string)$resp;
                    }
                }
                $full = 'Falha ao enviar via Evolution: ' . $detail . ($httpCode ? " (HTTP $httpCode)" : '') . ($bodyDetail !== '' ? ' — ' . mb_substr($bodyDetail, 0, 500) : '') . ' [jid: ' . $jid . ']';
                (new LeadTimelineService())->add($contactId, 'note', 'WhatsApp da sequência falhou: ' . $full, ['channel' => 'whatsapp']);
                Logger::error('SequenceEngine whatsapp 400', ['contact' => $contactId, 'jid' => $jid, 'response' => $result['response'] ?? null]);
                return $full;
            }

            // Grava a mensagem NO PRÓPRIO contato do lead (para aparecer no chat dele)
            $this->db->insert('whatsapp_messages', [
                'instance_id' => $instanceId,
                'contact_id' => $contactId,
                'remote_jid' => $isRealJid ? $ctxRow['remote_jid'] : $jid,
                'message_id' => $result['key']['id'] ?? uniqid('seq_'),
                'from_me' => 1,
                'message_type' => 'text',
                'message_text' => $msg,
                'sender_name' => 'Prospecção',
                'timestamp' => date('Y-m-d H:i:s'),
                'is_read' => 1,
            ]);
            // Desarquiva, atualiza "última mensagem" e corrige o JID do lead se estava
            // com placeholder/errado (para o chat e futuros envios usarem o JID real).
            $contactUpdate = ['is_archived' => 0, 'last_message_at' => date('Y-m-d H:i:s')];
            if (!$isRealJid) $contactUpdate['remote_jid'] = $jid;
            $this->db->update('whatsapp_contacts', $contactUpdate, 'id = ?', [$contactId]);

            (new LeadTimelineService())->add($contactId, 'note', 'WhatsApp enviado pela sequência.', ['channel' => 'whatsapp']);
            return true;
        } catch (\Throwable $e) {
            Logger::error('SequenceEngine whatsapp', ['contact' => $contactId, 'error' => $e->getMessage()]);
            return 'Erro: ' . $e->getMessage();
        }
    }

    /** Confirma (ao vivo) se a instância está conectada; atualiza o cache. */
    private function isInstanceConnected($instanceId)
    {
        $inst = $this->db->fetch("SELECT * FROM whatsapp_instances WHERE id = ?", [$instanceId]);
        if (!$inst) return false;
        try {
            $api = EvolutionApi::fromInstance($instanceId);
            if (!$api) return false;
            $state = $api->connectionState();
            // Formato Evolution: { instance: { state: 'open'|'connecting'|'close' } }
            $st = $state['instance']['state'] ?? ($state['state'] ?? null);
            if ($st) {
                $this->db->update('whatsapp_instances', ['connection_status' => $st], 'id = ?', [$instanceId]);
                return in_array($st, ['open', 'connected'], true);
            }
        } catch (\Throwable $e) { /* rede/instância indisponível */ }
        // Sem resposta ao vivo: confia no cache
        return in_array($inst['connection_status'] ?? '', ['open', 'connected'], true);
    }

    /**
     * Reveal progressivo: revela ao Apollo apenas os dados marcados no bloco
     * (telefone e/ou e-mail) que AINDA FALTAM no lead. Não gasta crédito com o que
     * já existe. Telefone chega de forma assíncrona via webhook.
     * $data: ['reveal_phone'=>0/1 (default 1), 'reveal_email'=>0/1]
     */
    private function doReveal($participant, $data = [])
    {
        $contactId = $participant['contact_id'];
        // Por padrão o bloco revela telefone; e-mail só se marcado.
        $wantPhone = array_key_exists('reveal_phone', $data) ? !empty($data['reveal_phone']) : true;
        $wantEmail = !empty($data['reveal_email']);

        $contact = $this->db->fetch("SELECT phone, lead_email FROM whatsapp_contacts WHERE id = ?", [$contactId]);
        $hasPhone = $contact && !empty($contact['phone']);
        $hasEmail = $contact && !empty($contact['lead_email']);

        // Nada a revelar (já tem tudo o que foi pedido)? sai sem gastar crédito.
        $needPhone = $wantPhone && !$hasPhone;
        $needEmail = $wantEmail && !$hasEmail;
        if (!$needPhone && !$needEmail) return;

        // Localiza o staging Apollo vinculado a este lead
        $lead = $this->db->fetch("SELECT * FROM apollo_leads WHERE contact_id = ? ORDER BY id DESC LIMIT 1", [$contactId]);
        if (!$lead || empty($lead['apollo_id'])) return;

        try {
            $apollo = new ApolloApi();
            if (!$apollo->isConfigured()) return;

            $params = ['id' => $lead['apollo_id']];

            // E-mail: síncrono. Revela e grava se ainda faltava.
            if ($needEmail) {
                $params['reveal_personal_emails'] = true;
            }
            // Telefone: assíncrono via webhook (evita duplicar se já pendente).
            $phonePending = ($lead['phone_status'] ?? null) === 'pending';
            $doPhone = $needPhone && !$phonePending;
            if ($doPhone) {
                $webhookToken = trim((string) Config::get('apollo_webhook_token'));
                $base = rtrim(baseUrl(''), '/');
                if ($base !== '' && stripos($base, 'http') === 0) {
                    $params['reveal_phone_number'] = true;
                    $params['webhook_url'] = $base . '/crm/apolloPhoneWebhook' . ($webhookToken !== '' ? '?token=' . rawurlencode($webhookToken) : '');
                } else {
                    $doPhone = false; // sem URL pública não há como receber o telefone
                }
            }

            if (empty($params['reveal_personal_emails']) && empty($params['reveal_phone_number'])) return;

            $res = $apollo->enrichPerson($params);
            if (empty($res['success'])) return;

            $person = $res['data']['person'] ?? null;

            // Grava e-mail revelado (síncrono)
            if ($needEmail && $person) {
                $email = $this->extractRevealedEmail($person);
                if ($email) {
                    $this->db->update('whatsapp_contacts', ['lead_email' => $email], 'id = ?', [$contactId]);
                    $this->db->update('apollo_leads', ['email' => $email, 'is_enriched' => 1], 'id = ?', [$lead['id']]);
                    (new LeadTimelineService())->add($contactId, 'note', 'E-mail revelado pelo Apollo (progressivo).', ['channel' => 'apollo']);
                }
            }

            // Marca telefone como pendente (chega via webhook)
            if ($doPhone) {
                $requestId = $person['id'] ?? ($res['data']['request_id'] ?? null);
                $this->db->update('apollo_leads', [
                    'phone_status' => 'pending',
                    'phone_request_id' => $requestId,
                    'phone_requested_by' => $participant['added_by'] ?? null,
                ], 'id = ?', [$lead['id']]);
                (new LeadTimelineService())->add($contactId, 'note', 'Reveal de telefone solicitado ao Apollo (progressivo).', ['channel' => 'apollo']);
            }
        } catch (\Throwable $e) {
            Logger::error('SequenceEngine reveal', ['contact' => $contactId, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Bloco IA (ChatGPT): monta o prompt do operador + contexto do lead (dados +
     * histórico recente de mensagens) e consulta a OpenAI.
     *  - mode='decision' → pede uma decisão SIM/NÃO; retorna ['decision'=>bool].
     *  - mode='simple'   → retorna o texto e, se configurado, grava como nota.
     * @return array ['decision'=>bool, 'text'=>string, 'detail'=>string, 'error'=>?string]
     */
    private function doAi($participant, $node)
    {
        $contactId = $participant['contact_id'];
        $data = $node['data'] ?? [];
        $mode = ($data['mode'] ?? 'simple') === 'decision' ? 'decision' : 'simple';
        $model = trim((string)($data['model'] ?? 'gpt-4o-mini')) ?: 'gpt-4o-mini';
        $promptTpl = (string)($data['prompt'] ?? '');

        $apiKey = trim((string) Config::get('openai_api_key'));
        if ($apiKey === '') {
            $msg = 'Chave da OpenAI não configurada em Configurações.';
            (new LeadTimelineService())->add($contactId, 'note', 'Bloco IA não executado: ' . $msg, ['channel' => 'ai']);
            return ['decision' => false, 'text' => '', 'detail' => $msg, 'error' => $msg];
        }

        $contact = $this->db->fetch("SELECT id, contact_name, push_name, lead_email, phone FROM whatsapp_contacts WHERE id = ?", [$contactId]);
        if (!$contact) return ['decision' => false, 'text' => '', 'detail' => 'Lead não encontrado', 'error' => 'no_contact'];

        // Renderiza variáveis do prompt ({{primeiro_nome}}, {{empresa}}, etc.)
        $prompt = $this->render($promptTpl, $contact);

        // Contexto automático: dados do lead + histórico recente (e-mail + WhatsApp).
        $context = $this->buildAiContext($contactId, $contact);

        // Instrução de sistema conforme o modo
        if ($mode === 'decision') {
            $system = 'Você é um assistente de qualificação comercial. Analise o contexto do lead e a instrução. '
                . 'Responda SOMENTE com JSON válido no formato {"decision": true|false, "reason": "curto"}. '
                . 'decision=true significa SIM; decision=false significa NÃO.';
            $responseFormat = ['type' => 'json_object'];
        } else {
            $system = 'Você é um assistente comercial da ON Solutions Brasil. Responda de forma objetiva e profissional, '
                . 'em português do Brasil, apenas com o texto solicitado (sem markdown).';
            $responseFormat = null;
        }

        $userContent = $prompt . "\n\n---\nCONTEXTO DO LEAD:\n" . $context;

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $userContent],
            ],
            'temperature' => $mode === 'decision' ? 0.0 : 0.4,
            'max_tokens' => 800,
        ];
        if ($responseFormat) $payload['response_format'] = $responseFormat;

        try {
            $ch = curl_init('https://api.openai.com/v1/chat/completions');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $apiKey, 'Content-Type: application/json'],
                CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 60,
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr = curl_error($ch);
            curl_close($ch);

            if ($httpCode >= 400 || !$response) {
                $msg = 'Falha ao consultar a IA (HTTP ' . $httpCode . ')' . ($curlErr ? ': ' . $curlErr : '');
                Logger::error('SequenceEngine ai', ['contact' => $contactId, 'http' => $httpCode, 'err' => $curlErr, 'body' => is_string($response) ? substr($response, 0, 300) : null]);
                (new LeadTimelineService())->add($contactId, 'note', 'Bloco IA falhou: ' . $msg, ['channel' => 'ai']);
                return ['decision' => false, 'text' => '', 'detail' => $msg, 'error' => $msg];
            }

            $body = json_decode($response, true);
            $content = trim((string)($body['choices'][0]['message']['content'] ?? ''));

            if ($mode === 'decision') {
                $parsed = json_decode($content, true);
                $decision = is_array($parsed) ? (bool)($parsed['decision'] ?? false) : (stripos($content, 'true') !== false);
                $reason = is_array($parsed) ? (string)($parsed['reason'] ?? '') : $content;
                (new LeadTimelineService())->add($contactId, 'note',
                    'IA (decisão): ' . ($decision ? 'SIM' : 'NÃO') . ($reason !== '' ? ' — ' . $reason : ''),
                    ['channel' => 'ai', 'model' => $model, 'decision' => $decision]);
                return ['decision' => $decision, 'text' => $content, 'detail' => ($decision ? 'SIM' : 'NÃO') . ($reason ? ' — ' . mb_substr($reason, 0, 200) : ''), 'error' => null];
            }

            // Modo simples: registra a resposta (opcional) como nota do lead.
            if (!empty($data['save_note']) || !isset($data['save_note'])) {
                (new LeadTimelineService())->add($contactId, 'note', 'IA (resposta): ' . mb_substr($content, 0, 1500), ['channel' => 'ai', 'model' => $model]);
            }
            return ['decision' => false, 'text' => $content, 'detail' => mb_substr($content, 0, 200), 'error' => null];
        } catch (\Throwable $e) {
            Logger::error('SequenceEngine ai exception', ['contact' => $contactId, 'error' => $e->getMessage()]);
            return ['decision' => false, 'text' => '', 'detail' => 'Erro: ' . $e->getMessage(), 'error' => $e->getMessage()];
        }
    }

    /**
     * Remove o lead da lista de prospecção: marca unsubscribed=1 (bloqueia envios
     * futuros), aplica etiqueta e registra na timeline. Também interrompe outras
     * sequências ativas do contato.
     */
    private function unsubscribeContact($contactId, $reason = 'Sem interesse')
    {
        try {
            $this->db->update('whatsapp_contacts', ['unsubscribed' => 1], 'id = ?', [$contactId]);
            (new LeadTimelineService())->add($contactId, 'note', 'Lead removido da lista: ' . $reason, ['channel' => 'sequence', 'action' => 'unsubscribe']);
            $this->applyLabel($contactId, 'sem interesse', '#dc3545');
        } catch (\Throwable $e) {
            Logger::error('SequenceEngine unsubscribe', ['contact' => $contactId, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Bloco AGENDAMENTO: cria um link público de agendamento (token) com os dados
     * do lead pré-preenchidos e envia o convite por e-mail e/ou WhatsApp. Ao agendar,
     * o BookingController cria o evento no Google Meet e notifica as partes.
     * @return array ['detail'=>string, 'error'=>?string]
     */
    private function doSchedule($participant, $node)
    {
        $contactId = $participant['contact_id'];
        $data = $node['data'] ?? [];
        $channel = in_array($data['channel'] ?? 'auto', ['auto', 'email', 'whatsapp', 'reply'], true) ? $data['channel'] : 'auto';
        // 'reply' = usa o mesmo canal em que o lead respondeu por último.
        if ($channel === 'reply') {
            $contactForCh = $this->db->fetch("SELECT lead_email, phone FROM whatsapp_contacts WHERE id = ?", [$contactId]);
            $channel = $this->lastReplyChannel($contactId, $contactForCh);
        }
        $duration = (int)($data['duration'] ?? 0) ?: max(15, (int)(Config::get('booking_duration_min') ?? 45));
        $expiryDays = max(1, (int)(Config::get('booking_link_expiry_days') ?? 30));
        $title = trim((string)($data['title'] ?? '')) ?: 'Reunião com a ON Solutions Brasil';

        $contact = $this->db->fetch("SELECT id, contact_name, push_name, lead_email, phone, assigned_to FROM whatsapp_contacts WHERE id = ?", [$contactId]);
        if (!$contact) return ['detail' => 'Lead não encontrado', 'error' => 'no_contact'];

        // Reaproveita um link pendente do mesmo lead, se existir; senão cria um novo.
        $existing = $this->db->fetch(
            "SELECT token FROM agenda_booking_links WHERE contact_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1",
            [$contactId]);
        if ($existing) {
            $token = $existing['token'];
        } else {
            $token = bin2hex(random_bytes(16));
            $this->db->insert('agenda_booking_links', [
                'token' => $token,
                'contact_id' => $contactId,
                'assigned_to' => $contact['assigned_to'] ?: ($participant['added_by'] ?? null),
                'sequence_participant_id' => $participant['id'],
                'title' => $title,
                'duration_min' => $duration,
                'status' => 'pending',
                'expires_at' => date('Y-m-d H:i:s', strtotime('+' . $expiryDays . ' days')),
            ]);
        }

        $base = rtrim((string) Config::get('app_public_url'), '/') ?: rtrim(baseUrl(''), '/');
        $link = $base . '/booking/' . $token;

        // Mensagem do convite (com variáveis do lead + {{link_agendamento}})
        $msgTpl = (string)($data['message'] ?? '');
        if (trim($msgTpl) === '') {
            $msgTpl = '{{primeiro_nome}}, para avançarmos, escolha o melhor dia e horário para uma conversa rápida (online). É só clicar no link: {{link_agendamento}}';
        }
        $rendered = $this->render($msgTpl, $contact);
        $rendered = str_replace(['{{link_agendamento}}', '{{link}}'], $link, $rendered);

        $hasEmail = !empty($contact['lead_email']);
        $hasPhone = !empty($contact['phone']);
        $sent = [];

        // E-mail
        if (($channel === 'auto' || $channel === 'email') && $hasEmail) {
            $account = $this->resolveAccount($this->db->fetch("SELECT email_account_id FROM email_sequences WHERE id = ?", [$participant['sequence_id']])['email_account_id'] ?? null);
            if ($account) {
                $bodyHtml = '<p>' . nl2br(htmlspecialchars($rendered)) . '</p>'
                    . '<p style="text-align:center;margin:24px 0;"><a href="' . htmlspecialchars($link, ENT_QUOTES) . '" style="background:#00BFA6;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:600;display:inline-block;">Escolher data e horário</a></p>';
                $res = (new EmailMessageService())->send([
                    'contact_id' => $contactId,
                    'account' => $account,
                    'to' => $contact['lead_email'],
                    'subject' => $title,
                    'body_html' => $bodyHtml,
                    'origin' => 'sequence',
                    'sequence_participant_id' => $participant['id'],
                    'node_id' => $node['id'],
                    'add_signature' => true,
                ]);
                if (!empty($res['success'])) $sent[] = 'e-mail';
            }
        }

        // WhatsApp
        if (($channel === 'auto' || $channel === 'whatsapp') && $hasPhone) {
            $ok = $this->sendWhatsappRaw($contactId, $contact, $rendered);
            if ($ok === true) $sent[] = 'WhatsApp';
        }

        (new LeadTimelineService())->add($contactId, 'note', 'Link de agendamento enviado (' . (implode(' + ', $sent) ?: 'nenhum canal disponível') . '): ' . $link, ['channel' => 'schedule']);

        if (empty($sent)) {
            return ['detail' => 'Nenhum canal disponível para enviar o link (sem e-mail/telefone).', 'error' => 'no_channel'];
        }
        return ['detail' => 'Convite de agendamento enviado por ' . implode(' + ', $sent) . '.', 'error' => null];
    }

    /**
     * Descobre o canal da ÚLTIMA resposta do lead: compara a mensagem recebida
     * mais recente por e-mail (email_messages inbound / replied_at) com a mais
     * recente por WhatsApp (whatsapp_messages from_me=0). Retorna 'email' ou
     * 'whatsapp'. Fallback: e-mail se houver e-mail; senão WhatsApp.
     */
    private function lastReplyChannel($contactId, $contact = null)
    {
        $emailTs = 0; $waTs = 0;

        // Última evidência de resposta por e-mail (reply registrado)
        $em = $this->db->fetch(
            "SELECT COALESCE(replied_at, created_at) AS t FROM email_messages
             WHERE contact_id = ? AND (direction='inbound' OR replied_at IS NOT NULL)
             ORDER BY t DESC LIMIT 1", [$contactId]);
        if ($em && !empty($em['t'])) $emailTs = strtotime($em['t']);

        // Última mensagem recebida no WhatsApp
        $wa = $this->db->fetch(
            "SELECT timestamp AS t FROM whatsapp_messages
             WHERE contact_id = ? AND from_me = 0 ORDER BY id DESC LIMIT 1", [$contactId]);
        if ($wa && !empty($wa['t'])) $waTs = strtotime($wa['t']);

        if ($emailTs === 0 && $waTs === 0) {
            if (!$contact) $contact = $this->db->fetch("SELECT lead_email, phone FROM whatsapp_contacts WHERE id = ?", [$contactId]);
            if (!empty($contact['lead_email'])) return 'email';
            if (!empty($contact['phone'])) return 'whatsapp';
            return 'email';
        }
        return ($waTs >= $emailTs) ? 'whatsapp' : 'email';
    }

    /**
     * Bloco "Responder ao lead": envia uma mensagem pelo MESMO canal em que o
     * lead respondeu por último (e-mail ou WhatsApp). O conteúdo é o mesmo texto;
     * no e-mail usa o assunto informado. Assim a resposta nunca sai por um canal
     * aleatório.
     * @return array ['detail'=>string, 'error'=>?string, 'channel'=>string]
     */
    private function doReply($participant, $node)
    {
        $contactId = $participant['contact_id'];
        $data = $node['data'] ?? [];
        $contact = $this->db->fetch("SELECT id, contact_name, push_name, lead_email, phone FROM whatsapp_contacts WHERE id = ?", [$contactId]);
        if (!$contact) return ['detail' => 'Lead não encontrado', 'error' => 'no_contact', 'channel' => null];

        $channel = $this->lastReplyChannel($contactId, $contact);
        $subject = $this->render((string)($data['subject'] ?? 'ON Solutions Brasil'), $contact);
        $bodyRaw = (string)($data['body'] ?? '');
        $body = $this->render($bodyRaw, $contact);
        if (trim($body) === '') return ['detail' => 'Mensagem vazia.', 'error' => 'empty', 'channel' => $channel];

        // Canal e-mail: precisa de e-mail; se não tiver, tenta WhatsApp como alternativa.
        if ($channel === 'email' && empty($contact['lead_email'])) $channel = 'whatsapp';
        if ($channel === 'whatsapp' && empty($contact['phone'])) $channel = 'email';

        if ($channel === 'email') {
            if (empty($contact['lead_email'])) return ['detail' => 'Sem e-mail para responder.', 'error' => 'no_email', 'channel' => $channel];
            $bodyHtml = '<p>' . nl2br(htmlspecialchars($body)) . '</p>';
            $account = $this->resolveAccount($this->db->fetch("SELECT email_account_id FROM email_sequences WHERE id = ?", [$participant['sequence_id']])['email_account_id'] ?? null);
            if (!$account) return ['detail' => 'Sem conta de e-mail ativa.', 'error' => 'no_account', 'channel' => $channel];
            $res = (new EmailMessageService())->send([
                'contact_id' => $contactId, 'account' => $account, 'to' => $contact['lead_email'],
                'subject' => $subject, 'body_html' => $bodyHtml, 'origin' => 'sequence',
                'sequence_participant_id' => $participant['id'], 'node_id' => $node['id'], 'add_signature' => true,
            ]);
            return !empty($res['success'])
                ? ['detail' => 'Resposta enviada por e-mail.', 'error' => null, 'channel' => 'email']
                : ['detail' => 'Falha no e-mail: ' . ($res['error'] ?? ''), 'error' => $res['error'] ?? 'send', 'channel' => 'email'];
        }

        // Canal WhatsApp
        if (empty($contact['phone'])) return ['detail' => 'Sem telefone para responder.', 'error' => 'no_phone', 'channel' => $channel];
        $ok = $this->sendWhatsappRaw($contactId, $contact, $body);
        return ($ok === true)
            ? ['detail' => 'Resposta enviada por WhatsApp.', 'error' => null, 'channel' => 'whatsapp']
            : ['detail' => 'Falha no WhatsApp: ' . (is_string($ok) ? $ok : ''), 'error' => 'send', 'channel' => 'whatsapp'];
    }

    /**
     * Bloco CONEXÃO DE SEQUÊNCIA: inscreve o lead na sequência de destino.
     * Usa o próprio enroll (respeita canal/elegibilidade). Não encerra a atual
     * aqui — o step decide encerrar/seguir conforme data.stop_current.
     * @return array ['detail'=>string, 'error'=>?string]
     */
    private function doConnect($participant, $node)
    {
        $contactId = $participant['contact_id'];
        $targetSeqId = (int)($node['data']['sequence_id'] ?? 0);
        if (!$targetSeqId) return ['detail' => 'Sequência de destino não configurada.', 'error' => 'no_target'];

        $seq = $this->db->fetch("SELECT id, name FROM email_sequences WHERE id = ?", [$targetSeqId]);
        if (!$seq) return ['detail' => 'Sequência de destino não encontrada.', 'error' => 'not_found'];

        $r = $this->enroll($targetSeqId, $contactId, $participant['added_by'] ?? null);
        if (empty($r['success'])) {
            (new LeadTimelineService())->add($contactId, 'note', 'Conexão de sequência falhou (' . $seq['name'] . '): ' . ($r['error'] ?? ''), ['channel' => 'sequence']);
            return ['detail' => 'Falha ao conectar: ' . ($r['error'] ?? ''), 'error' => $r['error'] ?? 'enroll_failed'];
        }
        (new LeadTimelineService())->add($contactId, 'note', 'Conectado à sequência: ' . $seq['name'], ['channel' => 'sequence', 'sequence_id' => $targetSeqId]);
        return ['detail' => 'Lead conectado à sequência "' . $seq['name'] . '".', 'error' => null];
    }

    /**
     * Envia uma mensagem de texto simples ao lead pelo WhatsApp (instância padrão),
     * reusando a resolução de JID/checagem do doWhatsapp. Retorna true em sucesso.
     */
    private function sendWhatsappRaw($contactId, $contact, $text)
    {
        try {
            $default = $this->db->fetch("SELECT id FROM whatsapp_instances WHERE is_default = 1 LIMIT 1");
            if (!$default) return 'Sem instância padrão de WhatsApp.';
            $instanceId = (int)$default['id'];
            if (!$this->isInstanceConnected($instanceId)) return 'Instância padrão não conectada.';
            $api = EvolutionApi::fromInstance($instanceId);
            if (!$api) return 'Instância indisponível.';

            $ctxRow = $this->db->fetch("SELECT remote_jid FROM whatsapp_contacts WHERE id = ?", [$contactId]);
            $existingJid = $ctxRow['remote_jid'] ?? '';
            $isRealJid = $existingJid && stripos($existingJid, 'lead_') === false && strpos($existingJid, '@') !== false;
            $jid = $isRealJid ? $existingJid : $api->normalizeJid($api->normalizeNumber($contact['phone']));

            $checkedExists = null;
            try {
                $check = $api->checkIsWhatsapp([$api->extractPhone($jid)]);
                if (is_array($check)) {
                    foreach ($check as $item) {
                        if (array_key_exists('exists', $item)) $checkedExists = !empty($item['exists']);
                        if (!empty($item['exists']) && !empty($item['jid'])) { $jid = $api->normalizeJid($item['jid']); $checkedExists = true; break; }
                    }
                }
            } catch (\Throwable $e) {}
            if ($checkedExists === false) return 'Número sem WhatsApp.';

            $result = $api->sendText($jid, $text);
            if (is_array($result) && !empty($result['error'])) return 'Falha no envio do WhatsApp.';

            $this->db->insert('whatsapp_messages', [
                'instance_id' => $instanceId,
                'contact_id' => $contactId,
                'remote_jid' => $isRealJid ? $ctxRow['remote_jid'] : $jid,
                'message_id' => $result['key']['id'] ?? uniqid('seq_'),
                'from_me' => 1,
                'message_type' => 'text',
                'message_text' => $text,
                'timestamp' => date('Y-m-d H:i:s'),
            ]);
            $this->db->update('whatsapp_contacts', ['last_message_at' => date('Y-m-d H:i:s')], 'id = ?', [$contactId]);
            return true;
        } catch (\Throwable $e) {
            Logger::error('SequenceEngine sendWhatsappRaw', ['contact' => $contactId, 'error' => $e->getMessage()]);
            return 'Erro: ' . $e->getMessage();
        }
    }

    /**
     * Monta o contexto textual do lead para a IA: dados básicos, briefing e as
     * últimas mensagens trocadas (e-mail e WhatsApp), do mais recente ao mais antigo.
     */
    private function buildAiContext($contactId, $contact)
    {
        $lines = [];
        $name = $contact['contact_name'] ?: ($contact['push_name'] ?? '');
        if ($name) $lines[] = 'Nome: ' . $name;
        if (!empty($contact['lead_email'])) $lines[] = 'E-mail: ' . $contact['lead_email'];
        if (!empty($contact['phone'])) $lines[] = 'Telefone: ' . $contact['phone'];

        // Briefing comercial (empresa/cargo/necessidade), se houver
        try {
            $bf = $this->db->fetch("SELECT need, notes, main_pain, lead_temperature FROM commercial_briefings WHERE contact_id = ? LIMIT 1", [$contactId]);
            if ($bf) {
                if (!empty($bf['notes'])) $lines[] = 'Notas: ' . $bf['notes'];
                if (!empty($bf['need'])) $lines[] = 'Necessidade: ' . $bf['need'];
                if (!empty($bf['main_pain'])) $lines[] = 'Dor principal: ' . $bf['main_pain'];
                if (!empty($bf['lead_temperature'])) $lines[] = 'Temperatura: ' . $bf['lead_temperature'];
            }
        } catch (\Throwable $e) { /* ignore */ }

        // Últimos e-mails (assunto + status de resposta)
        try {
            $emails = $this->db->fetchAll(
                "SELECT subject, direction, replied_at, sent_at FROM email_messages
                 WHERE contact_id = ? ORDER BY id DESC LIMIT 5", [$contactId]);
            foreach ($emails as $m) {
                $dir = $m['direction'] === 'inbound' ? 'recebido' : 'enviado';
                $lines[] = 'E-mail (' . $dir . '): ' . ($m['subject'] ?? '') . ($m['replied_at'] ? ' [respondido]' : '');
            }
        } catch (\Throwable $e) { /* ignore */ }

        // Últimas mensagens de WhatsApp (texto + direção)
        try {
            $msgs = $this->db->fetchAll(
                "SELECT from_me, message_text FROM whatsapp_messages
                 WHERE contact_id = ? AND message_text IS NOT NULL AND message_text <> ''
                 ORDER BY id DESC LIMIT 10", [$contactId]);
            $msgs = array_reverse($msgs);
            foreach ($msgs as $m) {
                $who = $m['from_me'] ? 'Nós' : 'Lead';
                $lines[] = 'WhatsApp ' . $who . ': ' . mb_substr($m['message_text'], 0, 300);
            }
        } catch (\Throwable $e) { /* ignore */ }

        if (empty($lines)) return '(sem histórico registrado para este lead)';
        return implode("\n", $lines);
    }

    /** Extrai o e-mail real do payload do Apollo (ignora placeholders bloqueados). */
    private function extractRevealedEmail($person)
    {
        $isReal = fn($e) => !empty($e) && stripos($e, 'email_not_unlocked') === false && stripos($e, 'domain.com') === false && filter_var($e, FILTER_VALIDATE_EMAIL);
        if ($isReal($person['email'] ?? null)) return $person['email'];
        foreach (['personal_emails', 'contact_emails'] as $k) {
            if (!empty($person[$k]) && is_array($person[$k])) {
                foreach ($person[$k] as $item) {
                    $v = is_array($item) ? ($item['email'] ?? null) : $item;
                    if ($isReal($v)) return $v;
                }
            }
        }
        return null;
    }

    private function evalCondition($kind, $contactId, $participant = null)
    {
        // Considera a última mensagem enviada ao lead
        $msg = $this->db->fetch(
            "SELECT open_count, click_count, replied_at FROM email_messages
             WHERE contact_id = ? AND direction='outbound' ORDER BY sent_at DESC LIMIT 1",
            [$contactId]
        );
        switch ($kind) {
            case 'opened': return $msg ? (int) $msg['open_count'] > 0 : false;
            case 'clicked': return $msg ? (int) $msg['click_count'] > 0 : false;
            case 'replied':
            default:
                // Respondeu por e-mail?
                if ($msg && !empty($msg['replied_at'])) return true;
                // Respondeu por WhatsApp? Qualquer mensagem recebida do lead
                // (from_me=0) após o início da participação conta como resposta.
                $since = $participant['started_at'] ?? null;
                if ($since) {
                    $wa = $this->db->fetch(
                        "SELECT id FROM whatsapp_messages
                         WHERE contact_id = ? AND from_me = 0 AND timestamp >= ? LIMIT 1",
                        [$contactId, $since]
                    );
                } else {
                    $wa = $this->db->fetch(
                        "SELECT id FROM whatsapp_messages WHERE contact_id = ? AND from_me = 0 LIMIT 1",
                        [$contactId]
                    );
                }
                return (bool) $wa;
        }
    }

    /**
     * Cria (se necessário) uma etiqueta no CRM e a vincula ao contato.
     * Usa as tabelas reais whatsapp_labels + whatsapp_contact_labels.
     */
    private function applyLabel($contactId, $label, $color = null)
    {
        $color = $color ?: '#00BFA6';
        $row = $this->db->fetch("SELECT id FROM whatsapp_labels WHERE name = ? LIMIT 1", [$label]);
        if ($row) {
            $labelId = (int) $row['id'];
        } else {
            $labelId = $this->db->insert('whatsapp_labels', ['name' => $label, 'color' => $color]);
        }
        if (!$labelId) return;
        try {
            // UNIQUE (contact_id, label_id) evita duplicar
            $this->db->query(
                "INSERT IGNORE INTO whatsapp_contact_labels (contact_id, label_id) VALUES (?, ?)",
                [$contactId, $labelId]
            );
        } catch (\Throwable $e) { /* silencioso */ }
    }

    private function moveCard($contactId, $columnId)
    {
        $board = new CrmBoard();
        $card = $this->db->fetch("SELECT id FROM crm_cards WHERE contact_id = ? ORDER BY id DESC LIMIT 1", [$contactId]);
        if ($card) {
            $board->moveCard($card['id'], $columnId, 0);
            (new LeadTimelineService())->add($contactId, 'board_move', 'Card movido pela sequência', ['column_id' => $columnId]);
        }
    }

    /**
     * Resolve o destino de um bloco "move": aceita column_id fixo OU column_name
     * (resolvido no board do card atual do lead — robusto entre instalações).
     * Retorna o column_id destino, ou null se não encontrado.
     */
    private function resolveMoveColumn($contactId, $data)
    {
        $columnId = (int) ($data['column_id'] ?? 0);
        if ($columnId) return $columnId;

        $name = trim((string) ($data['column_name'] ?? ''));
        if ($name === '') return null;

        // Descobre o board a partir do card atual do lead; se não houver card,
        // usa o board "Prospecção Automática" como padrão.
        $card = $this->db->fetch(
            "SELECT col.board_id FROM crm_cards cc
             JOIN crm_columns col ON cc.column_id = col.id
             WHERE cc.contact_id = ? ORDER BY cc.id DESC LIMIT 1",
            [$contactId]
        );
        $boardId = $card['board_id'] ?? null;
        if ($boardId) {
            $col = $this->db->fetch(
                "SELECT id FROM crm_columns WHERE board_id = ? AND name = ? ORDER BY position ASC LIMIT 1",
                [$boardId, $name]
            );
        } else {
            $col = $this->db->fetch(
                "SELECT col.id FROM crm_columns col
                 JOIN crm_boards b ON col.board_id = b.id
                 WHERE b.name = 'Prospecção Automática' AND col.name = ?
                 ORDER BY col.position ASC LIMIT 1",
                [$name]
            );
        }
        return $col['id'] ?? null;
    }

    // ---- helpers de fluxo ----

    private function advance($participant, $nextNodeId, $nodes)
    {
        if (!$nextNodeId || !isset($nodes[$nextNodeId])) {
            $this->finish($participant, 'completed');
            return;
        }
        // Próximo nó roda na sequência imediatamente (mesma passada do cron não reprocessa
        // para evitar loop; fica pronto para o próximo ciclo)
        $this->db->update('sequence_participants', [
            'current_node' => $nextNodeId,
            'next_run_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$participant['id']]);
    }

    private function reschedule($participant, $when)
    {
        $this->db->update('sequence_participants', ['next_run_at' => $when], 'id = ?', [$participant['id']]);
    }

    /** Verifica se o grafo da sequência tem um bloco de reveal de telefone ativo. */
    private function graphHasPhoneReveal($seq)
    {
        if (empty($seq['graph'])) return false;
        $graph = json_decode($seq['graph'], true);
        foreach ($graph['nodes'] ?? [] as $n) {
            if (($n['type'] ?? '') === 'reveal_phone') {
                $rp = $n['data']['reveal_phone'] ?? 1;
                if (!empty($rp)) return true;
            }
        }
        return false;
    }

    /**
     * Verifica se o lead tem o canal necessário para um bloco:
     *   'email'    → possui lead_email
     *   'whatsapp' → possui telefone
     * Usado para pular blocos cujo canal o lead não possui (sequências mistas).
     */
    private function contactHasChannel($contactId, $channel)
    {
        $c = $this->db->fetch("SELECT lead_email, phone FROM whatsapp_contacts WHERE id = ?", [$contactId]);
        if (!$c) return false;
        if ($channel === 'whatsapp') return !empty($c['phone']);
        return !empty($c['lead_email']); // email (padrão)
    }

    private function finish($participant, $reason)
    {
        $this->db->update('sequence_participants', [
            'status' => 'finished', 'stop_reason' => $reason,
            'finished_at' => date('Y-m-d H:i:s'), 'next_run_at' => null,
        ], 'id = ?', [$participant['id']]);
    }

    private function logExec($participantId, $nodeId, $type, $result, $detail = null)
    {
        // Idempotência: attempt incremental por (participant, node)
        $prev = $this->db->fetch("SELECT MAX(attempt) a FROM sequence_executions WHERE participant_id = ? AND node_id = ?", [$participantId, $nodeId]);
        $attempt = (int) ($prev['a'] ?? 0) + 1;
        try {
            $this->db->insert('sequence_executions', [
                'participant_id' => $participantId, 'node_id' => $nodeId,
                'node_type' => $type, 'attempt' => $attempt, 'result' => $result, 'detail' => $detail,
            ]);
        } catch (\Throwable $e) { /* corrida — ignora duplicado */ }
    }

    private function waitSeconds($data)
    {
        $amount = max(0, (int) ($data['amount'] ?? 0));
        $unit = $data['unit'] ?? 'days';
        $map = ['minutes' => 60, 'hours' => 3600, 'days' => 86400];
        return $amount * ($map[$unit] ?? 86400);
    }

    private function withinWindow($seq)
    {
        if (!$seq['send_weekends'] && in_array(date('N'), ['6', '7'])) return false;
        $now = date('H:i:s');
        return $now >= $seq['window_start'] && $now <= $seq['window_end'];
    }

    private function nextWindowStart($seq)
    {
        $today = date('Y-m-d') . ' ' . $seq['window_start'];
        if (strtotime($today) > time()) return $today;
        return date('Y-m-d', strtotime('+1 day')) . ' ' . $seq['window_start'];
    }

    private function sentToday($sequenceId)
    {
        $r = $this->db->fetch(
            "SELECT COUNT(*) t FROM email_messages m
             JOIN sequence_participants sp ON sp.id = m.sequence_participant_id
             WHERE sp.sequence_id = ? AND m.direction='outbound' AND DATE(m.sent_at) = CURDATE()",
            [$sequenceId]
        );
        return (int) ($r['t'] ?? 0);
    }

    private function resolveAccount($accountId)
    {
        $am = new EmailAccount();
        if ($accountId) {
            $acc = $am->findById($accountId);
            if ($acc && $acc['is_active']) return $acc;
        }
        // primeira conta ativa
        $all = Database::getInstance()->fetch("SELECT * FROM email_accounts WHERE is_active = 1 ORDER BY id ASC LIMIT 1");
        return $all ?: null;
    }

    private function render($text, $contact)
    {
        // Usa o renderizador único de templates (mesmas variáveis em toda a plataforma)
        return MessageTemplate::render($text, $contact);
    }

    /**
     * Cria/roda um follow-up simples pós-envio manual, sem editor:
     * gera uma sequência mínima "espera N + condição respondeu + envio".
     */
    public function createSimpleFollowUp($contactId, $waitAmount, $waitUnit, $subject, $body, $userId = null)
    {
        $graph = [
            'start' => 'w1',
            'nodes' => [
                ['id' => 'w1', 'type' => 'wait', 'data' => ['amount' => $waitAmount, 'unit' => $waitUnit], 'next' => 'c1'],
                ['id' => 'c1', 'type' => 'condition', 'data' => ['kind' => 'replied'], 'nextYes' => 'e1', 'nextNo' => 's1'],
                ['id' => 's1', 'type' => 'send', 'data' => ['subject' => $subject, 'body' => $body], 'next' => 'e1'],
                ['id' => 'e1', 'type' => 'end'],
            ],
        ];
        $seqId = $this->db->insert('email_sequences', [
            'name' => 'Follow-up simples · ' . date('d/m H:i'),
            'description' => 'Gerado automaticamente após envio manual.',
            'graph' => json_encode($graph, JSON_UNESCAPED_UNICODE),
            'is_active' => 1,
            'created_by' => $userId,
        ]);
        return $this->enroll($seqId, $contactId, $userId);
    }
}
