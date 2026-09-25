<?php

/**
 * Modelo de API Keys para integração externa (API v1 de criação de chamados).
 *
 * Modelo simplificado: UMA chave por empresa.
 *  - A chave é gravada inteira (coluna api_key), para poder ser reexibida e
 *    copiada na tela de Configurações. Trade-off consciente: ferramenta interna
 *    (só super_admin), priorizando praticidade.
 *  - Cada chave pertence a uma empresa (company_id) e aponta para um usuário de
 *    integração (integration_user_id, role 'client') dessa empresa. O ticket
 *    criado via API grava client_id = integration_user_id e a empresa é derivada
 *    por users.company_id — preservando todo o relacionamento atual.
 *  - Troca de chave (caso raro, ex.: vazamento) é feita diretamente no banco.
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
     * Gera uma nova chave em claro (não persiste nada). Método puro (sem banco)
     * para permitir teste unitário.
     */
    public static function generatePlainKey(): string
    {
        return self::KEY_PREFIX . bin2hex(random_bytes(24)); // 48 hex chars
    }

    /**
     * Retorna a chave existente da empresa ou cria uma nova (uma por empresa).
     * Garante também o usuário de integração da empresa.
     *
     * Retorna a linha completa de api_keys (com api_key, company_id,
     * integration_user_id).
     */
    public function getOrCreateForCompany(int $companyId): array
    {
        $existing = $this->findByCompany($companyId);
        if ($existing) {
            return $existing;
        }

        $integrationUserId = $this->ensureIntegrationUser($companyId);
        $id = (int)$this->db->insert('api_keys', [
            'company_id'          => $companyId,
            'integration_user_id' => $integrationUserId,
            'api_key'             => self::generatePlainKey(),
        ]);

        return $this->findById($id);
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
     * Retorna a linha de api_keys (com company_id/integration_user_id) ou null.
     * A comparação usa hash_equals para não vazar tempo.
     */
    public function resolveByPlainKey(string $plain): ?array
    {
        $plain = trim($plain);
        if ($plain === '') {
            return null;
        }
        $row = $this->db->fetch("SELECT * FROM api_keys WHERE api_key = ? LIMIT 1", [$plain]);
        if (!$row) {
            return null;
        }
        if (!hash_equals($row['api_key'], $plain)) {
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

    /** Chave de uma empresa (ou null se não houver). */
    public function findByCompany(int $companyId): ?array
    {
        $row = $this->db->fetch("SELECT * FROM api_keys WHERE company_id = ? LIMIT 1", [$companyId]);
        return $row ?: null;
    }

    public function findById(int $id): ?array
    {
        $row = $this->db->fetch("SELECT * FROM api_keys WHERE id = ?", [$id]);
        return $row ?: null;
    }

    /**
     * Mapa company_id => linha da chave, para a UI listar cada empresa com a sua
     * chave (quando houver).
     */
    public function keysByCompany(): array
    {
        $rows = $this->db->fetchAll("SELECT * FROM api_keys");
        $map = [];
        foreach ($rows as $r) {
            $map[(int)$r['company_id']] = $r;
        }
        return $map;
    }

    /**
     * Atualiza a URL de callback e o liga/desliga de uma empresa (recurso de
     * callback de status da API v1 — mão dupla).
     *
     * Só é possível cadastrar callback para empresas que já têm chave de API
     * (a chave é a identidade da integração). Retorna false se a empresa não
     * tiver chave. A URL é normalizada (trim); string vazia grava NULL.
     *
     * Não valida o formato da URL aqui — a validação/decisão de disparo fica em
     * ApiCallbackService::isValidCallbackUrl, usada no momento de enfileirar.
     */
    public function updateCallback(int $companyId, string $callbackUrl, bool $enabled): bool
    {
        $existing = $this->findByCompany($companyId);
        if (!$existing) {
            return false;
        }
        $url = trim($callbackUrl);
        $this->db->update('api_keys', [
            'callback_url'     => $url !== '' ? $url : null,
            'callback_enabled' => $enabled ? 1 : 0,
        ], 'company_id = ?', [$companyId]);
        return true;
    }
}
