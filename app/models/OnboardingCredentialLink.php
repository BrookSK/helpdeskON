<?php

/**
 * Link para o cliente enviar credenciais direto ao cofre (change 02, D6).
 *
 * O token em claro só existe na resposta da geração (vai no link). No banco
 * fica o SHA-256. O link vale 7 dias, pode ser revogado e não é gerado sem
 * credentials_key, para não gravar dado de cliente com a chave fixa de reserva.
 */
class OnboardingCredentialLink
{
    public const VALID_DAYS = 7;

    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** O cofre tem chave própria configurada? */
    public static function vaultReady(): bool
    {
        return trim((string)Config::get('credentials_key')) !== '';
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Gera o link. Devolve ['ok', 'token', 'expires_at', 'id'] ou ['ok'=>false, 'error'].
     */
    public function generate(int $onboardingId, ?int $userId): array
    {
        if (!self::vaultReady()) {
            return ['ok' => false, 'code' => 409, 'error' => 'Configure a chave do cofre (credentials_key) em Configurações antes de gerar o link.'];
        }
        $onb = $this->db->fetch("SELECT id FROM onboardings WHERE id = ?", [$onboardingId]);
        if (!$onb) return ['ok' => false, 'code' => 404, 'error' => 'Onboarding não encontrado.'];

        $token = bin2hex(random_bytes(24));
        $expires = date('Y-m-d H:i:s', time() + self::VALID_DAYS * 86400);
        $id = (int)$this->db->insert('onboarding_credential_links', [
            'onboarding_id' => $onboardingId, 'token_hash' => self::hashToken($token),
            'expires_at' => $expires, 'created_by' => $userId,
        ]);
        (new Onboarding())->addEvent($onboardingId, $userId, 'credential_link', 'Link de credenciais gerado (vale até ' . date('d/m/Y H:i', strtotime($expires)) . ')');
        return ['ok' => true, 'code' => 200, 'id' => $id, 'token' => $token, 'expires_at' => $expires];
    }

    public function revoke(int $linkId, ?int $userId): bool
    {
        $link = $this->db->fetch("SELECT * FROM onboarding_credential_links WHERE id = ?", [$linkId]);
        if (!$link || $link['revoked_at']) return false;
        $this->db->update('onboarding_credential_links', ['revoked_at' => date('Y-m-d H:i:s')], 'id = ?', [$linkId]);
        (new Onboarding())->addEvent((int)$link['onboarding_id'], $userId, 'credential_link', 'Link de credenciais #' . $linkId . ' revogado');
        return true;
    }

    public function listForOnboarding(int $onboardingId): array
    {
        return $this->db->fetchAll(
            "SELECT l.*, u.name AS created_by_name FROM onboarding_credential_links l
             LEFT JOIN users u ON l.created_by = u.id WHERE l.onboarding_id = ? ORDER BY l.id DESC",
            [$onboardingId]
        );
    }

    /**
     * Confere o token do link. Devolve ['ok'=>true, 'link', 'onboarding'] ou
     * ['ok'=>false, 'reason' => invalid|expired|revoked|no_key, 'error'].
     */
    public function check(string $token): array
    {
        $token = trim($token);
        $link = $token !== '' ? $this->db->fetch("SELECT * FROM onboarding_credential_links WHERE token_hash = ?", [self::hashToken($token)]) : null;
        if (!$link) return ['ok' => false, 'reason' => 'invalid', 'error' => 'Este link não é válido.'];
        if (!empty($link['revoked_at'])) return ['ok' => false, 'reason' => 'revoked', 'error' => 'Este link foi cancelado pela equipe. Peça um novo.'];
        if (strtotime($link['expires_at']) < time()) return ['ok' => false, 'reason' => 'expired', 'error' => 'Este link venceu. Peça um novo à equipe.'];
        if (!self::vaultReady()) return ['ok' => false, 'reason' => 'no_key', 'error' => 'O envio de credenciais está indisponível no momento. Fale com a equipe.'];
        $onb = (new Onboarding())->findById((int)$link['onboarding_id']);
        if (!$onb) return ['ok' => false, 'reason' => 'invalid', 'error' => 'Este link não é válido.'];
        return ['ok' => true, 'link' => $link, 'onboarding' => $onb];
    }

    /**
     * Grava no cofre o que o cliente enviou. A senha vai criptografada e não
     * volta na resposta. Registra data, IP e a quantidade de envios.
     *
     * @param array $input service_label, username, secret, url, notes
     */
    public function submit(string $token, array $input, string $ip): array
    {
        $check = $this->check($token);
        if (!$check['ok']) return ['ok' => false, 'code' => 410, 'error' => $check['error']];
        $label = mb_substr(trim((string)($input['service_label'] ?? '')), 0, 160);
        $secret = (string)($input['secret'] ?? '');
        if ($label === '') return ['ok' => false, 'code' => 400, 'error' => 'Informe o serviço (ex.: hospedagem, domínio, e-mail).'];
        if (trim($secret) === '' && trim((string)($input['username'] ?? '')) === '') {
            return ['ok' => false, 'code' => 400, 'error' => 'Informe o usuário ou a senha.'];
        }
        $link = $check['link'];
        $onb = $check['onboarding'];
        $ip = mb_substr($ip, 0, 45);

        $credId = (new ClientCredential())->create([
            'company_id' => $onb['company_id'] ?: null,
            'service_label' => $label,
            'username' => mb_substr(trim((string)($input['username'] ?? '')), 0, 255) ?: null,
            'url' => mb_substr(trim((string)($input['url'] ?? '')), 0, 500) ?: null,
            'notes' => trim((string)($input['notes'] ?? '')) ?: null,
            'secret' => $secret,
            'created_by' => null,
        ]);
        $this->db->update('client_credentials', ['onboarding_id' => (int)$onb['id']], 'id = ?', [$credId]);
        $this->db->query(
            "UPDATE onboarding_credential_links SET last_used_at = NOW(), last_used_ip = ?, use_count = use_count + 1 WHERE id = ?",
            [$ip, (int)$link['id']]
        );
        (new Onboarding())->addEvent((int)$onb['id'], null, 'credential_received',
            'Cliente enviou a credencial "' . $label . '" pelo link (IP ' . ($ip !== '' ? $ip : '?') . ')');
        if (!empty($link['created_by'])) {
            try {
                $this->db->insert('notifications', [
                    'user_id' => (int)$link['created_by'], 'title' => 'Credencial recebida',
                    'message' => 'O cliente enviou a credencial "' . $label . '" no onboarding "' . $onb['title'] . '".', 'type' => 'system',
                ]);
            } catch (\Throwable $e) { /* não interrompe o envio do cliente */ }
        }
        return ['ok' => true, 'code' => 200, 'credential_id' => $credId];
    }
}
