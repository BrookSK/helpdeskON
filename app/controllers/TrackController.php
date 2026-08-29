<?php

/**
 * Endpoints públicos de rastreamento de e-mail (sem autenticação).
 *   GET track/open/{token}        → pixel 1x1 (abertura)
 *   GET track/click/{token}?u=... → redireciona para a URL registrando o clique
 *   GET track/unsub/{token}       → descadastra o lead
 */
class TrackController extends Controller
{
    public function open($token = null)
    {
        // Aceita token com extensão de imagem (ex.: /track/open/TOKEN.png) — vários
        // clientes/proxies só carregam URLs que "parecem" imagem.
        if ($token) {
            $token = preg_replace('/\.(png|gif|jpg|jpeg)$/i', '', $token);
            try {
                (new EmailMessageService())->registerOpen($token, $this->ip(), $_SERVER['HTTP_USER_AGENT'] ?? null);
            } catch (\Throwable $e) { /* nunca quebra o pixel */ }
        }
        // GIF transparente 1x1
        header('Content-Type: image/gif');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        exit;
    }

    public function click($token = null)
    {
        $url = $_GET['u'] ?? '';
        $dest = null;
        if ($token && $url) {
            try {
                $dest = (new EmailMessageService())->registerClick($token, $url, $this->ip(), $_SERVER['HTTP_USER_AGENT'] ?? null);
            } catch (\Throwable $e) { $dest = $url; }
        }
        $dest = $dest ?: $url;

        // Só redireciona para http(s) — evita open redirect para esquemas perigosos
        if (!preg_match('#^https?://#i', (string) $dest)) {
            http_response_code(400);
            echo 'Link inválido.';
            exit;
        }
        header('Location: ' . $dest, true, 302);
        exit;
    }

    public function unsub($token = null)
    {
        $done = false;
        if ($token) {
            try {
                $db = Database::getInstance();
                // Traz o id da mensagem também (FK de email_events exige message_id válido)
                $msg = $db->fetch("SELECT id, contact_id FROM email_messages WHERE track_token = ?", [$token]);
                if ($msg && !empty($msg['contact_id'])) {
                    $contactId = (int) $msg['contact_id'];
                    $db->update('whatsapp_contacts', ['unsubscribed' => 1], 'id = ?', [$contactId]);
                    // Registra o evento com o message_id real (evita violar a foreign key)
                    try {
                        $db->insert('email_events', [
                            'message_id' => (int) $msg['id'],
                            'contact_id' => $contactId,
                            'event_type' => 'unsubscribe',
                        ]);
                    } catch (\Throwable $e) { /* evento é secundário */ }
                    try { (new LeadTimelineService())->add($contactId, 'note', 'Lead solicitou descadastro (unsubscribe).'); } catch (\Throwable $e) {}
                    // Remove o lead de TODAS as sequências ativas (para de receber)
                    try { (new SequenceEngine())->stopForContact($contactId, 'unsubscribed'); } catch (\Throwable $e) {}
                    $done = true;
                }
            } catch (\Throwable $e) {
                Logger::error('unsub', ['token' => $token, 'error' => $e->getMessage()]);
            }
        }
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>Descadastro</title>'
            . '<style>body{font-family:Arial,Helvetica,sans-serif;background:#f5f7fa;margin:0;padding:0;color:#333}'
            . '.card{max-width:480px;margin:60px auto;background:#fff;border-radius:14px;box-shadow:0 6px 24px rgba(0,0,0,.08);padding:40px 32px;text-align:center}'
            . '.ico{width:64px;height:64px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 18px;font-size:32px}'
            . '.ok{background:#e6f7f2;color:#00a884}.err{background:#fdECEC;color:#d32f2f}'
            . 'h2{margin:0 0 8px;font-size:1.3rem}p{color:#667;line-height:1.5}</style></head><body>'
            . '<div class="card">'
            . ($done
                ? '<div class="ico ok">&#10003;</div><h2>Descadastro confirmado</h2><p>Você foi removido da nossa lista e <strong>não receberá mais e-mails</strong>.</p><p style="font-size:0.82rem;color:#999;">Se foi um engano, entre em contato conosco para voltar a receber.</p>'
                : '<div class="ico err">&times;</div><h2>Link inválido ou expirado</h2><p>Não foi possível processar o descadastro. O link pode ter expirado.</p>')
            . '</div></body></html>';
        exit;
    }

    private function ip()
    {
        return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? null);
    }
}
