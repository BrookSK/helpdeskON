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
 *   linkedin (data: action_type, objective, tone, cta, max_length, template_id, body)
 *          → cria uma tarefa MANUAL (linkedin_tasks) com a mensagem preparada pela IA
 *            e PAUSA o participante. Retomada por resumeAfterLinkedinTask ao "ENVIEI".
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

        $contact = $this->db->fetch("SELECT unsubscribed, email_bounced, lead_email, phone, linkedin_url FROM whatsapp_contacts WHERE id = ?", [$contactId]);
        if (!$contact) return ['success' => false, 'error' => 'Lead não encontrado.'];
        // Prospecção HÍBRIDA: basta o lead ter ao menos um canal utilizável
        // (e-mail, telefone OU LinkedIn). As etapas que exigem um canal específico
        // resolvem/pulam por conta própria no doSend/doWhatsapp/case 'linkedin'.
        if (empty($contact['lead_email']) && empty($contact['phone']) && empty($contact['linkedin_url'])) {
            return ['success' => false, 'error' => 'Lead sem e-mail, telefone ou LinkedIn cadastrado.'];
        }
        if (!empty($contact['unsubscribed'])) return ['success' => false, 'error' => 'Lead descadastrado.'];

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

        // Sequências de prospecção Apollo: garante que o lead tenha um card no board
        // "Prospecção Automática" (coluna "Novo"), se ainda não tiver nenhum card.
        if (stripos($seq['name'] ?? '', 'Apollo') !== false) {
            $this->ensureProspectingCard($contactId, $userId);
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
     * @param bool $manualForce Disparo MANUAL (botão "Executar campanha"): ignora
     *        APENAS a janela de horário/fim de semana do envio, para que a ação
     *        intencional do usuário execute o bloco agora. O limite diário e demais
     *        regras permanecem. Default false = comportamento idêntico ao do cron.
     * @param array|null $details Se um array for passado por referência, recebe uma
     *        linha LEGÍVEL por participante processado (nome, o que aconteceu, etapa
     *        atual, status e "aguardar até"). Só é usado no disparo manual — quando
     *        null (cron), o comportamento é idêntico ao de antes.
     * @return array métricas da execução
     */
    public function processDue($maxBatch = 200, $sequenceId = null, $manualForce = false, &$details = null)
    {
        $collect = is_array($details); // coletor legível por participante (disparo manual)
        $now = date('Y-m-d H:i:s');
        // Filtro opcional por sequência: sem $sequenceId (default) o comportamento é
        // idêntico ao do cron (todas as sequências ativas). Com $sequenceId, processa
        // SOMENTE os participantes elegíveis daquela sequência — mesmo motor/step().
        $params = [$now];
        $seqFilter = '';
        if ($sequenceId !== null) {
            $seqFilter = ' AND sp.sequence_id = ?';
            $params[] = (int) $sequenceId;
        }
        $due = $this->db->fetchAll(
            "SELECT sp.* FROM sequence_participants sp
             JOIN email_sequences s ON s.id = sp.sequence_id
             WHERE sp.status = 'active' AND s.is_active = 1
               AND sp.next_run_at IS NOT NULL AND sp.next_run_at <= ?" . $seqFilter . "
             ORDER BY sp.next_run_at ASC
             LIMIT " . (int) $maxBatch,
            $params
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
            $stepResults = []; // resultados dos passos deste participante (para o coletor legível)
            for ($i = 0; $i < $maxStepsPerParticipant; $i++) {
                try {
                    $r = $this->step($current, $sentByAccount, false, $manualForce);
                    $stats['processed']++;
                    if ($r === 'sent') $stats['sent']++;
                    elseif ($r === 'finished') $stats['finished']++;
                    elseif ($r === 'skipped') $stats['skipped']++;
                    $stepResults[] = $r;
                } catch (\Throwable $e) {
                    $stats['errors']++;
                    $stepResults[] = 'error';
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

            // Coletor legível (disparo manual): registra o que aconteceu com este lead.
            if ($collect) {
                $details[] = $this->summarizeRun($p['id'], $stepResults);
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
                    $ok = $this->evalCondition($node['data']['kind'] ?? 'replied', $contactId);
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
                case 'move':
                    $columnId = (int) ($node['data']['column_id'] ?? 0);
                    if ($columnId) $this->moveCard($contactId, $columnId);
                    $detail = 'Card movido.';
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

    /**
     * Executa um passo do participante (um nó). $testMode pula esperas/janela.
     * $manualForce (disparo manual) ignora SOMENTE a janela de horário/fim de semana
     * do envio — o limite diário e as demais regras seguem valendo.
     */
    private function step($participant, &$sentByAccount, $testMode = false, $manualForce = false)
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
                // Respeita janela de horário e limite diário (ignorado no modo teste)
                if (!$testMode) {
                    // Disparo MANUAL ("Executar campanha") ignora APENAS a janela de
                    // horário/fim de semana — a ação é intencional do usuário. O cron
                    // (manualForce=false) mantém a janela como sempre.
                    if (!$manualForce && !$this->withinWindow($seq)) { $this->reschedule($participant, $this->nextWindowStart($seq)); return 'skipped'; }
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
                $branch = $this->evalCondition($node['data']['kind'] ?? 'replied', $contactId) ? ($node['nextYes'] ?? null) : ($node['nextNo'] ?? null);
                $this->advance($participant, $branch, $nodes);
                $this->logExec($participant['id'], $nodeId, $type, 'done');
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
                $columnId = (int) ($node['data']['column_id'] ?? 0);
                if ($columnId) $this->moveCard($contactId, $columnId);
                $this->advance($participant, $node['next'] ?? null, $nodes);
                $this->logExec($participant['id'], $nodeId, $type, 'done');
                return 'skipped';

            case 'linkedin':
                // Etapa MANUAL assistida. NÃO envia nada: gera uma tarefa na fila
                // "Minhas Ações" (com a mensagem preparada pela IA) e PAUSA o
                // participante NESTE nó, aguardando o vendedor confirmar "ENVIEI".
                // No modo teste, apenas registra e segue (não trava o teste).
                $this->doLinkedin($participant, $seq, $node);
                if ($testMode) {
                    $this->advance($participant, $node['next'] ?? null, $nodes);
                    $this->logExec($participant['id'], $nodeId, $type, 'done', 'Tarefa LinkedIn (teste): seguiria pausada em produção.');
                    return 'skipped';
                }
                // Pausa: fixa o current_node neste nó e zera o next_run_at.
                $this->db->update('sequence_participants', [
                    'status' => 'paused', 'current_node' => $nodeId, 'next_run_at' => null,
                ], 'id = ?', [$participant['id']]);
                $this->logExec($participant['id'], $nodeId, $type, 'waiting', 'Tarefa LinkedIn criada — aguardando ação do vendedor.');
                return 'skipped';

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
            try {
                $phoneOnly = $api->extractPhone($jid);
                $check = $api->checkIsWhatsapp([$phoneOnly]);
                if (is_array($check)) {
                    foreach ($check as $item) {
                        if (!empty($item['exists']) && !empty($item['jid'])) {
                            $jid = $api->normalizeJid($item['jid']);
                            break;
                        }
                    }
                }
            } catch (\Throwable $e) { /* segue com o jid normalizado */ }

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

    private function evalCondition($kind, $contactId)
    {
        // Considera a última mensagem enviada ao lead
        $msg = $this->db->fetch(
            "SELECT open_count, click_count, replied_at FROM email_messages
             WHERE contact_id = ? AND direction='outbound' ORDER BY sent_at DESC LIMIT 1",
            [$contactId]
        );
        if (!$msg) return false;
        switch ($kind) {
            case 'opened': return (int) $msg['open_count'] > 0;
            case 'clicked': return (int) $msg['click_count'] > 0;
            case 'replied':
            default: return !empty($msg['replied_at']);
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
     * Etapa LinkedIn (MANUAL): cria uma tarefa na fila "Minhas Ações" com a mensagem
     * já preparada pela IA e registra na timeline. Idempotente por (participant, node):
     * se já existe uma tarefa aberta desta etapa, não recria.
     *
     * NÃO envia nada ao LinkedIn. Nenhuma automação/scraping. O envio é feito à mão
     * pelo vendedor, que depois confirma com "ENVIEI" (resumeAfterLinkedinTask).
     */
    /** A tabela linkedin_tasks existe? (migration 080 pode não ter rodado ainda.) */
    private function linkedinTasksReady()
    {
        static $ready = null;
        if ($ready !== null) return $ready;
        try {
            $r = $this->db->fetch(
                "SELECT 1 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'linkedin_tasks'"
            );
            $ready = (bool) $r;
        } catch (\Throwable $e) {
            $ready = false;
        }
        return $ready;
    }

    private function doLinkedin($participant, $seq, $node)
    {
        $contactId = $participant['contact_id'];
        $data = $node['data'] ?? [];

        // Compatibilidade: se a migration 080 (tabela linkedin_tasks) ainda não foi
        // aplicada, não quebra a sequência — apenas registra e segue.
        if (!$this->linkedinTasksReady()) {
            (new LeadTimelineService())->add(
                $contactId,
                'linkedin_task',
                'Etapa LinkedIn ignorada: tabela linkedin_tasks ausente (aplique a migration 080).',
                ['node_id' => $node['id']]
            );
            return true;
        }

        $taskModel = new LinkedinTask();
        // Idempotência: não recria tarefa já aberta desta etapa.
        $existing = $taskModel->findOpenByParticipantNode($participant['id'], $node['id']);
        if ($existing) return true;

        // SELECT resiliente: linkedin_url pode não existir antes da migration 080.
        $contact = $this->db->fetch("SELECT * FROM whatsapp_contacts WHERE id = ?", [$contactId]);

        // Mensagem preparada ANTES de a tarefa aparecer (só dados reais; sem alucinação).
        $gen = (new LinkedinMessageService())->generate($contactId, $node);

        $actionType = $data['action_type'] ?? 'message';
        $taskId = $taskModel->createIdempotent([
            'contact_id' => $contactId,
            'sequence_id' => $seq['id'] ?? null,
            'participant_id' => $participant['id'],
            'node_id' => $node['id'],
            'assigned_to' => $contact['assigned_to'] ?? ($participant['added_by'] ?? null),
            'action_type' => $actionType,
            'objective' => $data['objective'] ?? null,
            'linkedin_url' => $contact['linkedin_url'] ?? null,
            'template_id' => !empty($data['template_id']) ? (int) $data['template_id'] : null,
            'generated_message' => $gen['message'] ?? null,
            'status' => LinkedinTask::S_READY,
            'due_at' => date('Y-m-d H:i:s'),
        ]);

        (new LeadTimelineService())->add(
            $contactId,
            'linkedin_task',
            'Tarefa LinkedIn criada (' . $actionType . ') — aguardando envio manual.',
            ['task_id' => $taskId, 'sequence_id' => $seq['id'] ?? null, 'node_id' => $node['id']]
        );

        return true;
    }

    /**
     * Retoma a sequência após o vendedor confirmar o envio (ou pular) da tarefa
     * LinkedIn. Reativa o participante PAUSADO neste nó e avança para o próximo.
     * Chamado pelo LinkedinController ao "ENVIEI"/"PULAR".
     *
     * @return bool true se retomou; false se não havia nada a retomar.
     */
    public function resumeAfterLinkedinTask($participantId, $nodeId)
    {
        $participant = $this->db->fetch("SELECT * FROM sequence_participants WHERE id = ?", [$participantId]);
        if (!$participant) return false;
        // Só retoma se estava pausado exatamente neste nó (evita corridas).
        if ($participant['status'] !== 'paused' || (string) $participant['current_node'] !== (string) $nodeId) {
            return false;
        }

        $seq = $this->db->fetch("SELECT graph FROM email_sequences WHERE id = ?", [$participant['sequence_id']]);
        $graph = json_decode($seq['graph'] ?? '{}', true);
        $nodes = [];
        foreach (($graph['nodes'] ?? []) as $n) $nodes[$n['id']] = $n;
        $node = $nodes[$nodeId] ?? null;

        // Reativa e avança para o próximo nó (respeita os delays: o próximo 'wait'
        // reagenda normalmente no ciclo seguinte do cron).
        $this->db->update('sequence_participants', ['status' => 'active'], 'id = ?', [$participantId]);
        $participant['status'] = 'active';
        $this->advance($participant, $node['next'] ?? null, $nodes);
        return true;
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

    // ============ Acompanhamento (visão legível do estado) ============

    /**
     * Rótulos humanos por tipo de nó (espelham os NODE_LABELS do editor visual).
     */
    public static function nodeTypeLabel($type)
    {
        $map = [
            'send' => 'Enviar e-mail',
            'whatsapp' => 'Enviar WhatsApp',
            'linkedin' => 'LinkedIn (tarefa)',
            'wait' => 'Aguardar',
            'condition' => 'Condição',
            'tag' => 'Etiqueta',
            'score' => 'Score',
            'move' => 'Mover card',
            'reveal_phone' => 'Revelar telefone',
            'end' => 'Encerrar',
        ];
        return $map[$type] ?? ($type ?: '—');
    }

    /**
     * Descrição legível de um nó específico do grafo (ex.: "Aguardar 4 min",
     * "Condição — Se respondeu?", "Enviar e-mail — Assunto: ...").
     */
    public static function nodeDescribe($node)
    {
        if (!$node || empty($node['type'])) return '—';
        $type = $node['type'];
        $d = $node['data'] ?? [];
        $label = self::nodeTypeLabel($type);
        switch ($type) {
            case 'send':
                $subj = trim($d['subject'] ?? '');
                return $label . ($subj !== '' ? ' — ' . $subj : '');
            case 'wait':
                $amount = (int) ($d['amount'] ?? 0);
                $unitMap = ['minutes' => 'min', 'hours' => 'h', 'days' => 'dias'];
                $unit = $unitMap[$d['unit'] ?? 'days'] ?? 'dias';
                return $label . ' ' . $amount . ' ' . $unit;
            case 'condition':
                $kindMap = ['replied' => 'Se respondeu?', 'opened' => 'Se abriu?', 'clicked' => 'Se clicou?'];
                return $label . ' — ' . ($kindMap[$d['kind'] ?? 'replied'] ?? '?');
            case 'tag':
                return $label . (trim($d['label'] ?? '') !== '' ? ' — ' . $d['label'] : '');
            case 'score':
                $delta = (int) ($d['delta'] ?? 0);
                return $label . ' ' . ($delta > 0 ? '+' : '') . $delta;
            case 'linkedin':
                $actMap = ['connect' => 'Solicitar conexão', 'message' => '1ª mensagem', 'followup' => 'Follow-up', 'final' => 'Mensagem final'];
                return $label . ' — ' . ($actMap[$d['action_type'] ?? 'message'] ?? 'Ação');
            default:
                return $label;
        }
    }

    /**
     * Rótulo legível do RESULTADO de um passo no histórico, conforme o tipo do nó.
     * Ex.: send/done => "E-mail enviado"; send/failed => "Falha ao enviar";
     * wait/waiting => "Aguardando"; linkedin/waiting => "Tarefa criada (aguardando)".
     */
    public static function resultLabel($type, $result)
    {
        if ($type === 'send') {
            if ($result === 'done') return 'E-mail enviado';
            if ($result === 'failed') return 'Falha ao enviar e-mail';
        }
        if ($type === 'whatsapp') {
            if ($result === 'done') return 'WhatsApp enviado';
            if ($result === 'failed') return 'Falha ao enviar WhatsApp';
        }
        if ($type === 'wait' && $result === 'waiting') return 'Aguardando';
        if ($type === 'linkedin' && $result === 'waiting') return 'Tarefa criada (aguardando ação)';
        if ($type === 'condition') return 'Condição avaliada';
        $map = ['done' => 'Executado', 'failed' => 'Falhou', 'waiting' => 'Aguardando', 'skipped' => 'Pulado'];
        return $map[$result] ?? ($result ?: '—');
    }

    /**
     * Monta avisos LEGÍVEIS (impedido / pausado / pulado / aguardando) com o motivo
     * real, para o painel de acompanhamento. Cada aviso: {level, text}.
     * level: 'danger' (impediu/falhou), 'warning' (pausado/aguardando ação),
     *        'info' (aguardando tempo / andamento normal).
     */
    private function buildAlerts($participant, $curNode, array $history, $waitUntil)
    {
        $alerts = [];
        $status = $participant['status'] ?? '';
        $reason = $participant['stop_reason'] ?? '';

        // Falhas registradas no histórico (ex.: envio de e-mail/WhatsApp falhou).
        foreach ($history as $h) {
            if (($h['result'] ?? '') === 'failed') {
                $why = !empty($h['detail']) ? (' — ' . $h['detail']) : '';
                $alerts[] = ['level' => 'danger', 'text' => 'Etapa "' . $h['step'] . '" não foi concluída' . $why];
            }
        }

        // Estado final/interrompido com motivo.
        if ($status === 'finished') {
            $reasonMap = [
                'completed' => 'Sequência concluída normalmente.',
                'replied' => 'Interrompida porque o lead respondeu.',
                'unsubscribed' => 'Interrompida: lead descadastrado.',
                'bounce' => 'Interrompida: e-mail inválido (bounce).',
                'no_email' => 'Impedida: o lead não tem e-mail cadastrado.',
                'no_account' => 'Impedida: nenhuma conta de e-mail ativa configurada para envio.',
            ];
            $lvl = in_array($reason, ['no_email', 'no_account', 'bounce'], true) ? 'danger' : 'info';
            $alerts[] = ['level' => $lvl, 'text' => $reasonMap[$reason] ?? ('Finalizada (' . ($reason ?: 'concluída') . ').')];
        } elseif ($status === 'stopped') {
            $alerts[] = ['level' => 'warning', 'text' => 'Execução interrompida' . ($reason ? ' — ' . $reason : '') . '.'];
        } elseif ($status === 'failed') {
            $alerts[] = ['level' => 'danger', 'text' => 'Execução falhou' . ($reason ? ' — ' . $reason : '') . '.'];
        } elseif ($status === 'paused') {
            if (($curNode['type'] ?? '') === 'linkedin') {
                $alerts[] = ['level' => 'warning', 'text' => 'Pausado no LinkedIn: uma tarefa manual foi criada em CRM → Minhas Ações. A sequência só avança quando o vendedor confirmar o envio.'];
            } else {
                $alerts[] = ['level' => 'warning', 'text' => 'Participante pausado.'];
            }
        } else { // active
            $type = $curNode['type'] ?? null;
            if ($type === 'wait' && $waitUntil) {
                $alerts[] = ['level' => 'info', 'text' => 'Aguardando o tempo configurado. Próxima execução prevista para ' . $waitUntil . '.'];
            } elseif ($type === 'condition') {
                $alerts[] = ['level' => 'info', 'text' => 'Aguardando resposta para avaliar a condição. Enquanto não responder, seguirá pelo caminho "Não".'];
            } elseif ($type === 'send') {
                $alerts[] = ['level' => 'info', 'text' => 'Pronto para enviar e-mail na próxima execução.'];
            } elseif ($type === 'linkedin') {
                $alerts[] = ['level' => 'info', 'text' => 'Pronto para gerar a tarefa de LinkedIn na próxima execução.'];
            }
        }

        return $alerts;
    }

    /**
     * Status legível do participante para a tela de acompanhamento.
     * Considera o status do registro e o tipo do nó atual.
     */
    private function participantStatusText($participant, $currentNode)
    {
        $status = $participant['status'] ?? '';
        $type = $currentNode['type'] ?? null;

        if ($status === 'finished') {
            $reasonMap = [
                'completed' => 'Concluída', 'replied' => 'Respondeu', 'unsubscribed' => 'Descadastrado',
                'bounce' => 'E-mail inválido (bounce)', 'no_email' => 'Sem e-mail', 'no_account' => 'Sem conta de envio',
            ];
            return 'Finalizado — ' . ($reasonMap[$participant['stop_reason'] ?? ''] ?? ($participant['stop_reason'] ?: 'concluída'));
        }
        if ($status === 'stopped') return 'Interrompido' . (!empty($participant['stop_reason']) ? ' — ' . $participant['stop_reason'] : '');
        if ($status === 'failed') return 'Falhou' . (!empty($participant['stop_reason']) ? ' — ' . $participant['stop_reason'] : '');
        if ($status === 'paused') {
            if ($type === 'linkedin') return 'Aguardando ação no LinkedIn (Minhas Ações)';
            return 'Pausado';
        }
        // Ativo: detalha pelo tipo do nó atual
        if ($type === 'wait') return 'Aguardando';
        if ($type === 'condition') return 'Aguardando resposta';
        if ($type === 'send') return 'Pronto para enviar e-mail';
        if ($type === 'linkedin') return 'Pronto para tarefa de LinkedIn';
        return 'Ativo';
    }

    /**
     * Monta a visão legível de acompanhamento de TODOS os participantes de uma
     * sequência: etapa atual, última etapa executada, próxima etapa, status e,
     * quando em "Aguardar", até quando deve esperar (next_run_at).
     * Somente leitura — não altera estado nem executa nada.
     * @return array {sequence, participants:[...]}
     */
    public function progress($sequenceId)
    {
        $seq = $this->db->fetch("SELECT id, name, graph FROM email_sequences WHERE id = ?", [$sequenceId]);
        if (!$seq) return ['error' => 'Sequência não encontrada.'];

        $graph = json_decode($seq['graph'] ?? '{}', true);
        $nodes = [];
        foreach (($graph['nodes'] ?? []) as $n) $nodes[$n['id']] = $n;
        $startId = $graph['start'] ?? ($graph['nodes'][0]['id'] ?? null);

        $rows = $this->db->fetchAll(
            "SELECT sp.*, COALESCE(wc.contact_name, wc.push_name, wc.lead_email) AS lead_name, wc.lead_email
             FROM sequence_participants sp
             JOIN whatsapp_contacts wc ON sp.contact_id = wc.id
             WHERE sp.sequence_id = ?
             ORDER BY sp.status = 'active' DESC, sp.next_run_at ASC, sp.id ASC",
            [$sequenceId]
        );

        $out = [];
        foreach ($rows as $p) {
            // Nó atual: se null, o próximo a rodar é o start.
            $curId = $p['current_node'] ?: $startId;
            $curNode = $curId && isset($nodes[$curId]) ? $nodes[$curId] : null;

            // Próxima etapa: para nós lineares é o "next"; para condição depende do
            // ramo (mostramos ambos de forma resumida). Só informativo.
            $nextLabel = null;
            if ($curNode) {
                if (($curNode['type'] ?? '') === 'condition') {
                    $yes = isset($nodes[$curNode['nextYes'] ?? '']) ? self::nodeDescribe($nodes[$curNode['nextYes']]) : '—';
                    $no = isset($nodes[$curNode['nextNo'] ?? '']) ? self::nodeDescribe($nodes[$curNode['nextNo']]) : '—';
                    $nextLabel = 'Sim → ' . $yes . ' | Não → ' . $no;
                } else {
                    $nx = $curNode['next'] ?? null;
                    $nextLabel = ($nx && isset($nodes[$nx])) ? self::nodeDescribe($nodes[$nx]) : ('end' === ($curNode['type'] ?? '') ? '—' : 'Fim da sequência');
                }
            }

            // Histórico COMPLETO de etapas executadas (mostra que nada foi "pulado":
            // cada nó por onde o lead passou fica registrado, com resultado e motivo).
            $execRows = $this->db->fetchAll(
                "SELECT node_id, node_type, result, detail, executed_at
                 FROM sequence_executions WHERE participant_id = ?
                 ORDER BY executed_at ASC, id ASC",
                [$p['id']]
            );
            $history = [];
            foreach ($execRows as $ex) {
                $exNode = isset($nodes[$ex['node_id']]) ? $nodes[$ex['node_id']] : ['type' => $ex['node_type']];
                $history[] = [
                    'step' => self::nodeDescribe($exNode),
                    'type' => $ex['node_type'],
                    'result' => $ex['result'],
                    'result_label' => self::resultLabel($ex['node_type'], $ex['result']),
                    'detail' => $ex['detail'] ?: null,
                    'at' => $ex['executed_at'],
                ];
            }
            $last = !empty($execRows) ? end($execRows) : null;
            $lastLabel = null;
            if ($last) {
                $lastNode = isset($nodes[$last['node_id']]) ? $nodes[$last['node_id']] : ['type' => $last['node_type']];
                $lastLabel = self::nodeDescribe($lastNode);
            }

            // "Aguardar até": só relevante quando o nó atual é 'wait' e há next_run_at futuro.
            $waitUntil = null;
            if ($curNode && ($curNode['type'] ?? '') === 'wait' && !empty($p['next_run_at'])) {
                $waitUntil = $p['next_run_at'];
            }

            // Avisos: impedido / pausado / pulado / aguardando — com o motivo real.
            $alerts = $this->buildAlerts($p, $curNode, $history, $waitUntil);

            $out[] = [
                'participant_id' => (int) $p['id'],
                'lead_name' => $p['lead_name'] ?: ('Lead #' . $p['contact_id']),
                'lead_email' => $p['lead_email'] ?? null,
                'status' => $p['status'],
                'status_text' => $this->participantStatusText($p, $curNode),
                'current_step' => $curNode ? self::nodeDescribe($curNode) : '—',
                'current_type' => $curNode['type'] ?? null,
                'last_step' => $lastLabel ?: '—',
                'last_result' => $last['result'] ?? null,
                'last_at' => $last['executed_at'] ?? null,
                'next_step' => $nextLabel ?: '—',
                'next_run_at' => $p['next_run_at'] ?? null,
                'wait_until' => $waitUntil,
                'history' => $history,
                'alerts' => $alerts,
            ];
        }

        return ['sequence' => ['id' => (int) $seq['id'], 'name' => $seq['name']], 'participants' => $out];
    }

    /**
     * Resume, de forma LEGÍVEL, o que aconteceu com um participante durante uma
     * passada do disparo manual: nome do lead, o que foi executado, etapa atual,
     * status e "aguardar até" (quando aplicável). Usado só pelo coletor do runNow.
     * @param int   $participantId
     * @param array $stepResults resultados brutos dos passos ('sent','skipped',...)
     * @return array linha legível
     */
    private function summarizeRun($participantId, array $stepResults)
    {
        $p = $this->db->fetch(
            "SELECT sp.*, COALESCE(wc.contact_name, wc.push_name, wc.lead_email) AS lead_name
             FROM sequence_participants sp
             JOIN whatsapp_contacts wc ON sp.contact_id = wc.id
             WHERE sp.id = ?",
            [$participantId]
        );
        $name = $p['lead_name'] ?? ('Lead #' . $participantId);

        // Grafo para descrever o nó atual.
        $seq = $this->db->fetch("SELECT graph FROM email_sequences WHERE id = ?", [$p['sequence_id'] ?? 0]);
        $graph = json_decode($seq['graph'] ?? '{}', true);
        $nodes = [];
        foreach (($graph['nodes'] ?? []) as $n) $nodes[$n['id']] = $n;
        $startId = $graph['start'] ?? ($graph['nodes'][0]['id'] ?? null);
        $curId = $p['current_node'] ?: $startId;
        $curNode = ($curId && isset($nodes[$curId])) ? $nodes[$curId] : null;

        // O que foi feito nesta passada, com base nos resultados dos passos.
        $sent = count(array_filter($stepResults, fn($r) => $r === 'sent'));
        $hadError = in_array('error', $stepResults, true);
        $actions = [];
        if ($sent > 0) $actions[] = ($sent === 1 ? 'Envio realizado' : ($sent . ' envios realizados'));
        if ($hadError) $actions[] = 'erro durante a execução';
        if (empty($actions)) $actions[] = 'nenhuma ação executável nesta passada';

        $line = [
            'participant_id' => (int) $participantId,
            'lead_name' => $name,
            'did' => implode('; ', $actions),
            'status' => $p['status'] ?? '',
            'status_text' => $this->participantStatusText($p, $curNode),
            'current_step' => $curNode ? self::nodeDescribe($curNode) : '—',
            'current_type' => $curNode['type'] ?? null,
            'wait_until' => ($curNode && ($curNode['type'] ?? '') === 'wait' && !empty($p['next_run_at'])) ? $p['next_run_at'] : null,
            'next_run_at' => $p['next_run_at'] ?? null,
        ];
        return $line;
    }
}
