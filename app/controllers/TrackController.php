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
        if ($token) {
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
            $db = Database::getInstance();
            $msg = $db->fetch("SELECT contact_id FROM email_messages WHERE track_token = ?", [$token]);
            if ($msg) {
                $db->update('whatsapp_contacts', ['unsubscribed' => 1], 'id = ?', [$msg['contact_id']]);
                $db->insert('email_events', [
                    'message_id' => 0, 'contact_id' => $msg['contact_id'], 'event_type' => 'unsubscribe',
                ]);
                (new LeadTimelineService())->add($msg['contact_id'], 'note', 'Lead solicitou descadastro (unsubscribe).');
                (new SequenceEngine())->stopForContact($msg['contact_id'], 'unsubscribed');
                $done = true;
            }
        }
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title>Descadastro</title>'
            . '<style>body{font-family:Arial,sans-serif;text-align:center;padding:60px;color:#333}</style></head><body>'
            . '<h2>' . ($done ? 'Você foi descadastrado' : 'Link inválido') . '</h2>'
            . '<p>' . ($done ? 'Não enviaremos mais e-mails para você.' : 'Não foi possível processar o descadastro.') . '</p>'
            . '</body></html>';
        exit;
    }

    private function ip()
    {
        return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? null);
    }
}
