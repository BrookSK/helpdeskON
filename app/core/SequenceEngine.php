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

        $contact = $this->db->fetch("SELECT unsubscribed, email_bounced, lead_email FROM whatsapp_contacts WHERE id = ?", [$contactId]);
        if (!$contact) return ['success' => false, 'error' => 'Lead não encontrado.'];
        if (empty($contact['lead_email'])) return ['success' => false, 'error' => 'Lead sem e-mail cadastrado.'];
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

        foreach ($due as $p) {
            try {
                $r = $this->step($p, $sentByAccount);
                $stats['processed']++;
                if ($r === 'sent') $stats['sent']++;
                elseif ($r === 'finished') $stats['finished']++;
                elseif ($r === 'skipped') $stats['skipped']++;
            } catch (\Throwable $e) {
                $stats['errors']++;
                Logger::error('SequenceEngine step', ['participant' => $p['id'], 'error' => $e->getMessage()]);
                $this->db->update('sequence_participants', ['status' => 'failed', 'stop_reason' => 'error'], 'id = ?', [$p['id']]);
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

        $steps = [];
        $sentByAccount = [];
        for ($i = 0; $i < $maxSteps; $i++) {
            $p = $this->db->fetch("SELECT * FROM sequence_participants WHERE id = ?", [$participantId]);
            if (!$p || $p['status'] !== 'active') break;
            try {
                $r = $this->step($p, $sentByAccount, true); // testMode = true
                $steps[] = ['node' => $p['current_node'], 'result' => $r];
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

        // Descobre a instância do próprio contato do lead (mesma do chat dele)
        $ctxRow = $this->db->fetch("SELECT instance_id, remote_jid FROM whatsapp_contacts WHERE id = ?", [$contactId]);
        $instanceId = $ctxRow['instance_id'] ?? null;
        if (!$instanceId) {
            $anyInst = $this->db->fetch("SELECT id FROM whatsapp_instances WHERE is_default = 1 LIMIT 1")
                ?: $this->db->fetch("SELECT id FROM whatsapp_instances LIMIT 1");
            $instanceId = $anyInst['id'] ?? null;
        }
        if (!$instanceId) return 'Nenhuma instância de WhatsApp cadastrada';

        try {
            $api = EvolutionApi::fromInstance($instanceId);
            if (!$api) $api = EvolutionApi::getDefault();
            if (!$api) return 'Instância de WhatsApp indisponível';

            // Envia usando o número do lead
            $jid = $api->normalizeJid($api->normalizeNumber($contact['phone']));
            $result = $api->sendText($jid, $msg);
            if (is_array($result) && !empty($result['error'])) {
                return 'Falha ao enviar via Evolution: ' . (is_string($result['error']) ? $result['error'] : 'erro');
            }

            // Grava a mensagem NO PRÓPRIO contato do lead (para aparecer no chat dele)
            $this->db->insert('whatsapp_messages', [
                'instance_id' => $instanceId,
                'contact_id' => $contactId,
                'remote_jid' => $ctxRow['remote_jid'] ?: $jid,
                'message_id' => $result['key']['id'] ?? uniqid('seq_'),
                'from_me' => 1,
                'message_type' => 'text',
                'message_text' => $msg,
                'sender_name' => 'Prospecção',
                'timestamp' => date('Y-m-d H:i:s'),
                'is_read' => 1,
            ]);
            // Desarquiva e atualiza o "última mensagem" para subir no chat
            $this->db->update('whatsapp_contacts', [
                'is_archived' => 0,
                'last_message_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$contactId]);

            (new LeadTimelineService())->add($contactId, 'note', 'WhatsApp enviado pela sequência.', ['channel' => 'whatsapp']);
            return true;
        } catch (\Throwable $e) {
            Logger::error('SequenceEngine whatsapp', ['contact' => $contactId, 'error' => $e->getMessage()]);
            return 'Erro: ' . $e->getMessage();
        }
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
}
