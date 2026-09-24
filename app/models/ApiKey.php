<?php

/**
 * Modelo de API Keys para integração externa (API v1 de criação de chamados).
 *
 * Regras de segurança:
 *  - A chave em claro é gerada UMA vez (generate()) e devolvida ao chamador para
 *    exibição única. NUNCA é persistida em claro.
 *  - Persistimos apenas o hash SHA-256 (key_hash) e um prefixo curto (key_prefix)
 *    usado só para identificação na interface.
 *  - A resolução de uma requisição (resolveByPlainKey) compara o hash com
 *    hash_equals (timing-safe).
 *
 * Modelo de tenancy (Alternativa A):
 *  - Cada chave pertence a uma empresa (company_id) e aponta para um usuário de
 *    integração (integration_user_id, role 'client') dessa empresa. O ticket
 *    criado via API grava client_id = integration_user_id e a empresa é derivada
 *    por users.company_id — preservando todo o relacionamento atual.
 */
class ApiKey
{
    /** Prefixo textual das chaves emitidas. */
    public const KEY_PREFIX = 'hk_live_';

    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Gera uma nova chave em claro (não persiste nada).
     * Retorna ['plain' => string, 'hash' => string, 'prefix' => string].
     *
     * Método puro (sem banco) para permitir teste unitário.
     */
    public static function generatePlainKey(): array
    {
        $secret = bin2hex(random_bytes(24)); // 48 hex chars
        $plain = self::KEY_PREFIX . $secret;
        return [
            'plain'  => $plain,
            'hash'   => self::hashKey($plain),
            // Prefixo exibível: o prefixo textual + os primeiros 4 chars do segredo.
            'prefix' => substr($plain, 0, strlen(self::KEY_PREFIX) + 4),
        ];
    }

    /** Hash determinístico da chave (nunca guardamos o valor puro). */
    public static function hashKey(string $plain): string
    {
        return hash('sha256', $plain);
    }

    /**
     * Cria uma nova API Key para uma empresa.
     *  - Garante/cria o usuário de integração da empresa.
     *  - Persiste apenas o hash + prefixo.
     *
     * Retorna ['id' => int, 'plain' => string, 'prefix' => string], onde `plain`
     * é a única oportunidade de exibir a chave completa.
     */
    public function createForCompany(int $companyId, string $name): array
    {
        $integrationUserId = $this->ensureIntegrationUser($companyId);
        $gen = self::generatePlainKey();

        $id = $this->db->insert('api_keys', [
            'company_id'          => $companyId,
            'integration_user_id' => $integrationUserId,
            'name'                => $name,
            'key_prefix'          => $gen['prefix'],
            'key_hash'            => $gen['hash'],
            'is_active'           => 1,
        ]);

        return [
            'id'     => (int)$id,
            'plain'  => $gen['plain'],
            'prefix' => $gen['prefix'],
        ];
    }

    /**
     * Renova (rotaciona) a chave de um registro existente, NA MESMA LINHA.
     * Gera uma nova chave em claro, sobrescreve hash/prefixo, reativa o registro
     * e zera last_used_at. A chave anterior deixa de valer no mesmo instante
     * (o hash é substituído). Não cria linha nova nem deixa registros revogados.
     *
     * Retorna ['id' => int, 'plain' => string, 'prefix' => string], onde `plain`
     * é a única oportunidade de exibir a chave completa.
     */
    public function rotateKey(int $id): array
    {
        $gen = self::generatePlainKey();

        $this->db->update('api_keys', [
            'key_prefix'   => $gen['prefix'],
            'key_hash'     => $gen['hash'],
            'is_active'    => 1,
            'revoked_at'   => null,
            'last_used_at' => null,
        ], 'id = ?', [$id]);

        return [
            'id'     => $id,
            'plain'  => $gen['plain'],
            'prefix' => $gen['prefix'],
        ];
    }

    /**
     * Garante que exista um usuário de integração (role 'client') para a empresa
     * e retorna seu id. Reaproveita um já existente se houver.
     *
     * O usuário de integração é técnico: e-mail determinístico interno e senha
     * aleatória forte (nunca usada para login humano). is_active = 1 para que as
     * notificações/joins funcionem normalmente.
     */
    public function ensureIntegrationUser(int $companyId): int
    {
        $email = 'api+company' . $companyId . '@integracao.local';

        $existing = $this->db->fetch("SELECT id FROM users WHERE email = ?", [$email]);
        if ($existing) {
            return (int)$existing['id'];
        }

        $company = $this->db->fetch("SELECT name FROM companies WHERE id = ?", [$companyId]);
        $companyName = $company['name'] ?? ('Empresa #' . $companyId);

        $randomPassword = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);

        return (int)$this->db->insert('users', [
            'name'             => 'Integração API - ' . $companyName,
            'email'            => $email,
            'password'         => $randomPassword,
            'role'             => 'client',
            'is_active'        => 1,
            'company_id'       => $companyId,
            'is_company_owner' => 0,
        ]);
    }

    /**
     * Resolve uma chave em claro recebida numa requisição.
     * Retorna a linha da api_keys (com company_id/integration_user_id) ou null se
     * não existir. Não filtra por is_active aqui — o chamador decide o status
     * code (401 inválida vs 403 revogada).
     */
    public function resolveByPlainKey(string $plain): ?array
    {
        $plain = trim($plain);
        if ($plain === '') {
            return null;
        }
        $hash = self::hashKey($plain);
        $row = $this->db->fetch("SELECT * FROM api_keys WHERE key_hash = ? LIMIT 1", [$hash]);
        if (!$row) {
            return null;
        }
        // Defesa extra contra timing (o lookup por hash já é indexado/constante):
        if (!hash_equals($row['key_hash'], $hash)) {
            return null;
        }
        return $row;
    }

    /** Marca o último uso da chave (auditoria). Best-effort. */
    public function touchLastUsed(int $id): void
    {
        try {
            $this->db->update('api_keys', ['last_used_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        } catch (\Throwable $e) { /* não bloqueia a requisição */ }
    }

    /** Revoga/desativa uma chave. */
    public function revoke(int $id): void
    {
        $this->db->update('api_keys', [
            'is_active'  => 0,
            'revoked_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);
    }

    /** Lista todas as chaves com o nome da empresa, para a UI. */
    public function allWithCompany(): array
    {
        return $this->db->fetchAll(
            "SELECT k.*, c.name AS company_name
             FROM api_keys k
             LEFT JOIN companies c ON k.company_id = c.id
             ORDER BY k.is_active DESC, k.created_at DESC"
        );
    }

    public function findById(int $id): ?array
    {
        $row = $this->db->fetch("SELECT * FROM api_keys WHERE id = ?", [$id]);
        return $row ?: null;
    }
}
