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
    /**
     * Assinatura HTML padrão dos e-mails da ON Solutions Brasil.
     * Mesma identidade usada nos envios manuais (logo, nome, contatos).
     * Fonte única para e-mails manuais e de sequência.
     */
    /**
     * Assinatura HTML específica de uma CONTA de e-mail (por domínio). Usa os
     * campos signature_* da conta. Se a conta não tiver assinatura configurada
     * (signature_enabled=0 ou sem dados), retorna a assinatura padrão do sistema.
     *
     * @param array|null $account linha de email_accounts
     * @return string HTML com o marcador data-onsolu-signature
     */
    public static function signatureForAccount($account, $userName = null)
    {
        if (!is_array($account)) return self::signatureHtml($userName);

        // Conta com assinatura desativada explicitamente → sem assinatura.
        if (array_key_exists('signature_enabled', $account) && (int)$account['signature_enabled'] === 0) {
            return '<div data-onsolu-signature="1"></div>';
        }

        // Se a conta não tem NENHUM campo de assinatura preenchido, usa o padrão.
        $hasCustom = false;
        foreach (['signature_name','signature_company','signature_site','signature_email','signature_phone','signature_tagline','signature_logo','signature_role'] as $k) {
            if (!empty($account[$k])) { $hasCustom = true; break; }
        }
        if (!$hasCustom) return self::signatureHtml($userName ?? ($account['display_name'] ?? null));

        $esc = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
        $color = $esc($account['signature_color'] ?? '#00997D');

        // Logo (upload da conta). Aceita caminho relativo (baseUrl) ou URL absoluta.
        $logoHtml = '';
        if (!empty($account['signature_logo'])) {
            $logo = $account['signature_logo'];
            $logoUrl = (stripos($logo, 'http') === 0) ? $logo : baseUrl($logo);
            $logoHtml = '<img src="' . $esc($logoUrl) . '" alt="' . $esc($account['signature_company'] ?? '') . '" style="max-height:56px;margin-bottom:8px;">';
        }

        $name = trim((string)($account['signature_name'] ?? $userName ?? ''));
        $role = trim((string)($account['signature_role'] ?? ''));
        $company = trim((string)($account['signature_company'] ?? ''));
        $site = trim((string)($account['signature_site'] ?? ''));
        $email = trim((string)($account['signature_email'] ?? $account['email'] ?? ''));
        $phone = trim((string)($account['signature_phone'] ?? ''));
        $tagline = trim((string)($account['signature_tagline'] ?? ''));

        $nameBlock = $name !== '' ? '<div style="font-weight:600;color:#111;">' . $esc($name) . ($role !== '' ? ' <span style="font-weight:400;color:#666;">· ' . $esc($role) . '</span>' : '') . '</div>' : '';
        $companyBlock = $company !== '' ? '<div style="margin-top:6px;"><strong>' . $esc($company) . '</strong></div>' : '';

        $contactLines = [];
        if ($phone !== '') $contactLines[] = '📞 ' . $esc($phone);
        if ($email !== '') $contactLines[] = '📧 <a href="mailto:' . $esc($email) . '" style="color:' . $color . ';text-decoration:none;">' . $esc($email) . '</a>';
        if ($site !== '') {
            $siteUrl = (stripos($site, 'http') === 0) ? $site : ('https://' . $site);
            $contactLines[] = '🌐 <a href="' . $esc($siteUrl) . '" style="color:' . $color . ';text-decoration:none;">' . $esc($site) . '</a>';
        }
        $contactBlock = !empty($contactLines) ? '<div style="margin-top:8px;">' . implode('<br>', $contactLines) . '</div>' : '';
        $taglineBlock = $tagline !== '' ? '<div style="margin-top:8px;color:#888;font-size:12px;">' . $esc($tagline) . '</div>' : '';

        return '
<div data-onsolu-signature="1" style="margin-top:28px;padding-top:16px;border-top:1px solid #e5e7eb;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#333;line-height:1.5;">
    ' . $logoHtml . '
    ' . $nameBlock . '
    <div style="margin-top:6px;">Atenciosamente,</div>
    ' . $companyBlock . '
    ' . $contactBlock . '
    ' . $taglineBlock . '
</div>';
    }

    public static function signatureHtml($userName = null)
    {
        $name = htmlspecialchars((string) ($userName ?? ''), ENT_QUOTES, 'UTF-8');

        $logoHtml = '';
        try {
            $logoPath = Config::get('app_logo');
            if (!empty($logoPath)) {
                $logoUrl = baseUrl($logoPath);
                $logoHtml = '<img src="' . htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') . '" alt="ON Solutions Brasil" style="max-height:56px;margin-bottom:8px;">';
            }
        } catch (\Throwable $e) { $logoHtml = ''; }

        $nameBlock = $name !== '' ? '<div style="font-weight:600;color:#111;">' . $name . '</div>' : '';

        // Marcador de idempotência: usado pelo EmailProspection::sendEmail para
        // detectar que a assinatura já está presente e não duplicá-la.
        return '
<div data-onsolu-signature="1" style="margin-top:28px;padding-top:16px;border-top:1px solid #e5e7eb;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#333;line-height:1.5;">
    ' . $logoHtml . '
    ' . $nameBlock . '
    <div style="margin-top:6px;">Atenciosamente,<br><strong>Equipe ON Solutions Brasil</strong></div>
    <div style="color:#666;margin-top:2px;">Tecnologia • Desenvolvimento • Automação</div>
    <div style="margin-top:8px;">
        📧 <a href="mailto:contato@onsolutionsbrasil.com.br" style="color:#00997D;text-decoration:none;">contato@onsolutionsbrasil.com.br</a><br>
        🌐 <a href="https://www.onsolutionsbrasil.com.br" style="color:#00997D;text-decoration:none;">www.onsolutionsbrasil.com.br</a>
    </div>
    <div style="margin-top:8px;color:#888;font-size:12px;">
        <strong>ON Solutions Brasil</strong><br>
        Soluções inteligentes para transformar processos e negócios.
    </div>
</div>';
    }

    public function send(array $params)
    {
        $contactId = (int) $params['contact_id'];
        $account = $params['account'];
        $to = trim($params['to']);
        $subject = trim($params['subject']);
        $body = $params['body_html'];
        $origin = $params['origin'] ?? 'manual';

        // Assinatura padrão: anexada quando add_signature=true (ex.: e-mails de
        // sequência). O envio manual já concatena a assinatura antes de chamar aqui,
        // então não usa esse flag para evitar duplicidade.
        if (!empty($params['add_signature'])) {
            // Assinatura da CONTA que está enviando (por domínio); fallback padrão.
            $body .= self::signatureForAccount($account, $params['signature_name'] ?? null);
        }

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

        // Resposta do lead: encaminha para triagem por IA (se a sequência tiver o
        // bloco de IA); senão, encerra. Antes o comportamento era sempre encerrar.
        (new SequenceEngine())->routeReplyToTriage($contactId, 'replied');

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
        // Usa a URL pública configurada (confiável mesmo quando roda via cron/CLI,
        // onde não há HTTP_HOST). Fallback para baseUrl() no contexto HTTP.
        $configured = trim((string) Config::get('app_public_url'));
        $base = $configured !== '' ? rtrim($configured, '/') : rtrim(baseUrl(''), '/');
        // Garante https (clientes de e-mail bloqueiam pixel http/misto)
        $base = preg_replace('#^http://#i', 'https://', $base);
        // 1) Reescreve links http(s) para passar pelo redirect de rastreio
        $html = preg_replace_callback('/href="(https?:\/\/[^"]+)"/i', function ($m) use ($base, $token) {
            $target = $m[1];
            // Não reescreve o próprio link de descadastro
            if (strpos($target, '/track/unsub/') !== false) return $m[0];
            $url = $base . '/track/click/' . $token . '?u=' . urlencode($target);
            return 'href="' . $url . '"';
        }, $html);

        // 2) Pixel de abertura + link de descadastro no rodapé.
        // Extensão .png no fim ajuda clientes/proxies a tratarem como imagem e
        // carregarem. Sem display:none (Gmail/Outlook ignoram imagens ocultas).
        $pixelUrl = $base . '/track/open/' . $token . '.png';
        $pixel = '<img src="' . $pixelUrl . '" width="1" height="1" border="0" alt="" style="width:1px;height:1px;max-height:1px;max-width:1px;border:0;overflow:hidden;">';
        $unsub = '<div style="margin-top:16px;font-size:11px;color:#999;">Se não deseja mais receber e-mails, <a href="' . $base . '/track/unsub/' . $token . '">clique aqui para descadastrar</a>.</div>';
        // Insere o pixel no topo E no rodapé (alguns clientes só carregam o início do corpo).
        return $pixel . $html . $unsub . $pixel;
    }

    private function normalize($email)
    {
        return mb_strtolower(trim((string) $email));
    }
}
