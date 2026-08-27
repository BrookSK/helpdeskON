<?php

/**
 * Serviço único de mensagens de e-mail vinculadas ao Lead.
 * Envio manual e automático (sequência) usam a MESMA estrutura (email_messages).
 * Injeta tracking (pixel de abertura + redirect de clique), persiste, e alimenta
 * timeline + score.
 */
class EmailMessageService
{
    private $db;
    private $prospection;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->prospection = new EmailProspection();
    }

    /**
     * Envia um e-mail para o Lead e registra tudo.
     *
     * @param array $params {
     *   contact_id (req), account (array da conta, req), to (req), subject (req),
     *   body_html (req), origin ('manual'|'sequence'), sequence_participant_id, node_id,
     *   sent_by (user id), cc, bcc, add_signature (bool)
     * }
     * @return array {success, message_id (local), error}
     */
    public function send(array $params)
    {
        $contactId = (int) $params['contact_id'];
        $account = $params['account'];
        $to = trim($params['to']);
        $subject = trim($params['subject']);
        $body = $params['body_html'];
        $origin = $params['origin'] ?? 'manual';

        // Bloqueia envio a leads descadastrados / com bounce definitivo
        $contact = $this->db->fetch("SELECT unsubscribed, email_bounced FROM whatsapp_contacts WHERE id = ?", [$contactId]);
        if ($contact && !empty($contact['unsubscribed'])) {
            return ['success' => false, 'error' => 'Lead descadastrado (unsubscribe). Envio bloqueado.'];
        }
        if ($contact && !empty($contact['email_bounced'])) {
            return ['success' => false, 'error' => 'E-mail deste lead retornou bounce definitivo. Envio bloqueado.'];
        }

        // Cria o registro (queued) para obter o token de tracking
        $token = bin2hex(random_bytes(16));
        $messageId = $this->db->insert('email_messages', [
            'contact_id' => $contactId,
            'email_account_id' => $account['id'] ?? null,
            'direction' => 'outbound',
            'origin' => $origin,
            'sequence_participant_id' => $params['sequence_participant_id'] ?? null,
            'node_id' => $params['node_id'] ?? null,
            'ab_variant' => $params['ab_variant'] ?? null,
            'thread_key' => $this->normalize($to),
            'recipient_email' => $to,
            'subject' => $subject,
            'body' => $body,
            'track_token' => $token,
            'status' => 'queued',
            'sent_by' => $params['sent_by'] ?? null,
        ]);

        // Injeta tracking no corpo (pixel + reescrita de links)
        $trackedBody = $this->injectTracking($body, $token);

        // Envia via SMTP (reusa a engine existente)
        $result = $this->prospection->sendEmail(
            $account, $to, $subject, $trackedBody,
            $params['cc'] ?? null, $params['bcc'] ?? null, []
        );

        if ($result === true) {
            $this->db->update('email_messages', [
                'status' => 'sent',
                'sent_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$messageId]);

            (new LeadTimelineService())->add($contactId, 'email_sent',
                ($origin === 'sequence' ? 'Follow-up enviado' : 'E-mail manual enviado') . ': ' . $subject,
                ['message_id' => $messageId, 'to' => $to, 'origin' => $origin],
                $params['sent_by'] ?? null);

            // Espelha na caixa de enviados (email_prospections) para aparecer no
            // Histórico de Prospecção — inclusive envios automáticos das sequências.
            // user_id e email_account_id são NOT NULL: usa fallbacks quando o envio
            // veio de uma sequência (sem usuário logado).
            try {
                $cName = $this->db->fetch("SELECT contact_name FROM whatsapp_contacts WHERE id = ?", [$contactId]);
                $uid = $params['sent_by'] ?? null;
                if (!$uid) {
                    $adm = $this->db->fetch("SELECT id FROM users WHERE role='super_admin' AND is_active=1 ORDER BY id ASC LIMIT 1");
                    $uid = $adm['id'] ?? null;
                }
                $accId = $account['id'] ?? null;
                if ($uid && $accId) {
                    $this->prospection->create([
                        'user_id' => $uid,
                        'email_account_id' => $accId,
                        'contact_id' => $contactId,
                        'recipient_email' => $to,
                        'recipient_name' => $cName['contact_name'] ?? null,
                        'subject' => $subject,
                        'body' => $body,
                        'status' => 'sent',
                        'sent_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            } catch (\Throwable $e) { /* não bloqueia o envio se o espelho falhar */ }

            return ['success' => true, 'message_id' => $messageId];
        }

        $this->db->update('email_messages', [
            'status' => 'failed',
            'error_message' => is_string($result) ? $result : 'Falha no envio',
        ], 'id = ?', [$messageId]);
        return ['success' => false, 'error' => is_string($result) ? $result : 'Falha no envio', 'message_id' => $messageId];
    }

    /** Registra uma abertura (pixel). Sinal fraco. */
    public function registerOpen($token, $ip = null, $ua = null)
    {
        $msg = $this->db->fetch("SELECT * FROM email_messages WHERE track_token = ?", [$token]);
        if (!$msg) return false;

        $now = date('Y-m-d H:i:s');
        $this->db->update('email_messages', [
            'open_count' => (int) $msg['open_count'] + 1,
            'first_open_at' => $msg['first_open_at'] ?: $now,
            'last_open_at' => $now,
        ], 'id = ?', [$msg['id']]);

        $this->db->insert('email_events', [
            'message_id' => $msg['id'], 'contact_id' => $msg['contact_id'],
            'event_type' => 'open', 'ip' => $ip, 'user_agent' => mb_substr((string)$ua, 0, 300),
        ]);

        // Só pontua/registra timeline na PRIMEIRA abertura (evita ruído)
        if (empty($msg['first_open_at'])) {
            (new LeadTimelineService())->add($msg['contact_id'], 'email_open', 'E-mail aberto: ' . $msg['subject'], ['message_id' => $msg['id']]);
            (new LeadScoreService())->add($msg['contact_id'], LeadScoreService::W_OPEN, 'abertura de e-mail');
        }
        return true;
    }

    /** Registra um clique e devolve a URL de destino. */
    public function registerClick($token, $url, $ip = null, $ua = null)
    {
        $msg = $this->db->fetch("SELECT * FROM email_messages WHERE track_token = ?", [$token]);
        if (!$msg) return null;

        $now = date('Y-m-d H:i:s');
        $this->db->update('email_messages', [
            'click_count' => (int) $msg['click_count'] + 1,
            'first_click_at' => $msg['first_click_at'] ?: $now,
        ], 'id = ?', [$msg['id']]);

        $this->db->insert('email_events', [
            'message_id' => $msg['id'], 'contact_id' => $msg['contact_id'],
            'event_type' => 'click', 'link_url' => mb_substr((string)$url, 0, 1000),
            'ip' => $ip, 'user_agent' => mb_substr((string)$ua, 0, 300),
        ]);

        if (empty($msg['first_click_at'])) {
            (new LeadTimelineService())->add($msg['contact_id'], 'email_click', 'Link clicado: ' . $msg['subject'], ['message_id' => $msg['id'], 'url' => $url]);
            (new LeadScoreService())->add($msg['contact_id'], LeadScoreService::W_CLICK, 'clique em link');
        }
        return $url;
    }

    /**
     * Registra uma resposta recebida do lead (via IMAP) e interrompe follow-ups.
     */
    public function registerReply($contactId, $subject = null, $userId = null)
    {
        // Evita processar a mesma resposta várias vezes: marca a última mensagem enviada
        $last = $this->db->fetch(
            "SELECT id FROM email_messages WHERE contact_id = ? AND direction='outbound' AND replied_at IS NULL ORDER BY sent_at DESC LIMIT 1",
            [$contactId]
        );
        if ($last) {
            $this->db->update('email_messages', ['replied_at' => date('Y-m-d H:i:s')], 'id = ?', [$last['id']]);
            $this->db->insert('email_events', [
                'message_id' => $last['id'], 'contact_id' => $contactId, 'event_type' => 'reply',
            ]);
        }

        (new LeadTimelineService())->add($contactId, 'email_reply', 'Lead respondeu' . ($subject ? ': ' . $subject : ''), null, $userId);
        (new LeadScoreService())->add($contactId, LeadScoreService::W_REPLY, 'resposta recebida');

        // Interrompe sequências ativas do lead
        (new SequenceEngine())->stopForContact($contactId, 'replied');

        return true;
    }

    /** Marca bounce e bloqueia. */
    public function registerBounce($contactId, $hard = true)
    {
        if ($hard) {
            $this->db->update('whatsapp_contacts', ['email_bounced' => 1], 'id = ?', [$contactId]);
            (new SequenceEngine())->stopForContact($contactId, 'bounce');
        }
        (new LeadTimelineService())->add($contactId, 'bounce', $hard ? 'Bounce definitivo (hard)' : 'Bounce temporário (soft)');
        (new LeadScoreService())->add($contactId, LeadScoreService::W_BOUNCE, 'bounce');
        return true;
    }

    // ---- tracking ----

    private function injectTracking($html, $token)
    {
        $base = rtrim(baseUrl(''), '/');
        // 1) Reescreve links http(s) para passar pelo redirect de rastreio
        $html = preg_replace_callback('/href="(https?:\/\/[^"]+)"/i', function ($m) use ($base, $token) {
            $target = $m[1];
            // Não reescreve o próprio link de descadastro
            if (strpos($target, '/track/unsub/') !== false) return $m[0];
            $url = $base . '/track/click/' . $token . '?u=' . urlencode($target);
            return 'href="' . $url . '"';
        }, $html);

        // 2) Pixel de abertura + link de descadastro no rodapé.
        // Evita display:none (Gmail/Outlook costumam não carregar imagens ocultas).
        // Usa 1x1 visível com cache-buster para forçar o carregamento a cada abertura.
        $pixelUrl = $base . '/track/open/' . $token . '?t=' . time();
        $pixel = '<img src="' . $pixelUrl . '" width="1" height="1" border="0" alt="" style="width:1px;height:1px;border:0;margin:0;padding:0;">';
        $unsub = '<div style="margin-top:16px;font-size:11px;color:#999;">Se não deseja mais receber e-mails, <a href="' . $base . '/track/unsub/' . $token . '">clique aqui para descadastrar</a>.</div>';
        return $html . $unsub . $pixel;
    }

    private function normalize($email)
    {
        return mb_strtolower(trim((string) $email));
    }
}
