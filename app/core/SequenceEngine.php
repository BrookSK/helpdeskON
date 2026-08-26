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
        return ['success' => true, 'participant_id' => $participantId];
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
        }
        return count($parts);
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

    /** Executa um passo do participante (um nó). */
    private function step($participant, &$sentByAccount)
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
                // Respeita janela de horário e limite diário
                if (!$this->withinWindow($seq)) { $this->reschedule($participant, $this->nextWindowStart($seq)); return 'skipped'; }
                $key = $seq['id'];
                $sentByAccount[$key] = ($sentByAccount[$key] ?? 0);
                if ($this->sentToday($seq['id']) + $sentByAccount[$key] >= (int) $seq['daily_limit']) {
                    $this->reschedule($participant, date('Y-m-d H:i:s', strtotime('+1 hour')));
                    return 'skipped';
                }
                $this->doSend($participant, $seq, $node);
                $sentByAccount[$key]++;
                $this->advance($participant, $node['next'] ?? null, $nodes);
                $this->logExec($participant['id'], $nodeId, $type, 'done');
                return 'sent';

            case 'whatsapp':
                $this->doWhatsapp($participant, $node);
                $this->advance($participant, $node['next'] ?? null, $nodes);
                $this->logExec($participant['id'], $nodeId, $type, 'done');
                return 'sent';

            case 'wait':
                $secs = $this->waitSeconds($node['data'] ?? []);
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
                $label = $node['data']['label'] ?? '';
                if ($label) (new LeadTimelineService())->add($contactId, 'tag', 'Tag: ' . $label, ['tag' => $label]);
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

    private function doSend($participant, $seq, $node)
    {
        $contactId = $participant['contact_id'];
        $contact = $this->db->fetch("SELECT lead_email, contact_name FROM whatsapp_contacts WHERE id = ?", [$contactId]);
        if (empty($contact['lead_email'])) { $this->finish($participant, 'no_email'); return; }

        // Conta de envio: da sequência ou a primeira ativa
        $account = $this->resolveAccount($seq['email_account_id']);
        if (!$account) { $this->finish($participant, 'no_account'); return; }

        $data = $node['data'] ?? [];
        $subject = $this->render($data['subject'] ?? '(sem assunto)', $contact);
        $body = $this->render($data['body'] ?? '', $contact);

        (new EmailMessageService())->send([
            'contact_id' => $contactId,
            'account' => $account,
            'to' => $contact['lead_email'],
            'subject' => $subject,
            'body_html' => $body,
            'origin' => 'sequence',
            'sequence_participant_id' => $participant['id'],
            'node_id' => $node['id'],
        ]);
    }

    private function doWhatsapp($participant, $node)
    {
        $contactId = $participant['contact_id'];
        $contact = $this->db->fetch("SELECT phone, contact_name, push_name, lead_email FROM whatsapp_contacts WHERE id = ?", [$contactId]);
        if (empty($contact['phone'])) {
            (new LeadTimelineService())->add($contactId, 'note', 'WhatsApp da sequência não enviado: lead sem telefone.');
            return;
        }
        $msg = $this->render($node['data']['body'] ?? '', $contact);
        if (trim($msg) === '') return;

        try {
            WhatsappNotifier::sendToPhone($contact['phone'], $msg, $contact['contact_name'] ?? null);
            (new LeadTimelineService())->add($contactId, 'note', 'WhatsApp enviado pela sequência.', ['channel' => 'whatsapp']);
        } catch (\Throwable $e) {
            Logger::error('SequenceEngine whatsapp', ['contact' => $contactId, 'error' => $e->getMessage()]);
        }
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

    private function logExec($participantId, $nodeId, $type, $result)
    {
        // Idempotência: attempt incremental por (participant, node)
        $prev = $this->db->fetch("SELECT MAX(attempt) a FROM sequence_executions WHERE participant_id = ? AND node_id = ?", [$participantId, $nodeId]);
        $attempt = (int) ($prev['a'] ?? 0) + 1;
        try {
            $this->db->insert('sequence_executions', [
                'participant_id' => $participantId, 'node_id' => $nodeId,
                'node_type' => $type, 'attempt' => $attempt, 'result' => $result,
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
