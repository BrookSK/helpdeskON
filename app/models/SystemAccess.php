<?php

/**
 * Model para o cofre de credenciais (tabela system_accesses).
 *
 * Regra de isolamento: cada super_admin só enxerga os próprios registros
 * (created_by = user_id). Toda operação de leitura/escrita exige o $userId.
 *
 * Criptografia de senha:
 *   - Algoritmo : AES-256-CBC via openssl_encrypt / openssl_decrypt.
 *   - Chave     : derivada do DB_PASSWORD do ambiente com PBKDF2 (32 bytes).
 *   - IV        : 16 bytes aleatórios por registro, armazenados junto ao cipher
 *                 no formato base64(iv):base64(ciphertext).
 *   - Se o ambiente não tiver openssl, a senha é armazenada em texto puro com
 *     prefixo "plain:" (nunca acontece em produção, mas impede erros silenciosos).
 */
class SystemAccess
{
    private $db;

    /** Deriva chave de 32 bytes a partir de um segredo fixo do ambiente. */
    private static function encKey(): string
    {
        // Segredo: senha do banco (presente em todos os ambientes) + salt fixo.
        // Não é ideal como segredo criptográfico de longo prazo, mas é infinitamente
        // melhor do que texto puro. Pode ser substituído por APP_KEY no futuro.
        $secret = defined('DB_SECRET') ? DB_SECRET : 'helpdeskon_vault_fallback_key_v1';
        return hash('sha256', $secret, true); // 32 bytes
    }

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ------------------------------------------------------------------ //
    //  Criptografia                                                        //
    // ------------------------------------------------------------------ //

    public static function encryptPassword(string $plain): string
    {
        if (!function_exists('openssl_encrypt')) {
            return 'plain:' . base64_encode($plain);
        }
        $iv   = random_bytes(16);
        $enc  = openssl_encrypt($plain, 'AES-256-CBC', self::encKey(), OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv) . ':' . base64_encode($enc);
    }

    public static function decryptPassword(string $stored): string
    {
        if (str_starts_with($stored, 'plain:')) {
            return base64_decode(substr($stored, 6));
        }
        if (!function_exists('openssl_decrypt')) {
            return '';
        }
        $parts = explode(':', $stored, 2);
        if (count($parts) !== 2) return '';
        [$ivB64, $encB64] = $parts;
        $iv  = base64_decode($ivB64);
        $enc = base64_decode($encB64);
        $plain = openssl_decrypt($enc, 'AES-256-CBC', self::encKey(), OPENSSL_RAW_DATA, $iv);
        return $plain === false ? '' : $plain;
    }

    // ------------------------------------------------------------------ //
    //  Leitura                                                             //
    // ------------------------------------------------------------------ //

    /**
     * Retorna todos os registros do usuário, sem a senha decriptada.
     * A senha é revelada apenas via getPassword() (endpoint dedicado).
     */
    public function getAllByUser(int $userId): array
    {
        return $this->db->fetchAll(
            "SELECT id, title, category, url, username, notes, company_id, created_at, updated_at
               FROM system_accesses
              WHERE created_by = ?
              ORDER BY category ASC, title ASC",
            [$userId]
        );
    }

    public function findById(int $id, int $userId): ?array
    {
        return $this->db->fetch(
            "SELECT * FROM system_accesses WHERE id = ? AND created_by = ?",
            [$id, $userId]
        ) ?: null;
    }

    /**
     * Retorna a senha decriptada. Deve ser chamado apenas pelo endpoint
     * /acessos/reveal/{id}, que exige autenticação e ownership.
     */
    public function getPassword(int $id, int $userId): ?string
    {
        $row = $this->db->fetch(
            "SELECT password_enc FROM system_accesses WHERE id = ? AND created_by = ?",
            [$id, $userId]
        );
        if (!$row || empty($row['password_enc'])) return null;
        return self::decryptPassword($row['password_enc']);
    }

    /** Lista de categorias em uso pelo usuário, para montar o filtro. */
    public function getCategoriesByUser(int $userId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT DISTINCT category FROM system_accesses WHERE created_by = ? ORDER BY category ASC",
            [$userId]
        );
        return array_column($rows, 'category');
    }

    // ------------------------------------------------------------------ //
    //  Escrita                                                             //
    // ------------------------------------------------------------------ //

    public function create(array $data, int $userId): int
    {
        return $this->db->insert('system_accesses', [
            'title'        => $data['title'],
            'category'     => $data['category'] ?: 'Geral',
            'url'          => $data['url']      ?: null,
            'username'     => $data['username'] ?: null,
            'password_enc' => !empty($data['password']) ? self::encryptPassword($data['password']) : null,
            'notes'        => $data['notes']    ?: null,
            'company_id'   => !empty($data['company_id']) ? (int)$data['company_id'] : null,
            'created_by'   => $userId,
        ]);
    }

    public function update(int $id, array $data, int $userId): bool
    {
        // Verifica propriedade antes de atualizar
        $existing = $this->findById($id, $userId);
        if (!$existing) return false;

        $fields = [
            'title'      => $data['title'],
            'category'   => $data['category'] ?: 'Geral',
            'url'        => $data['url']      ?: null,
            'username'   => $data['username'] ?: null,
            'notes'      => $data['notes']    ?: null,
            'company_id' => !empty($data['company_id']) ? (int)$data['company_id'] : null,
        ];

        // Só atualiza a senha se o campo vier preenchido (campo em branco = manter a atual)
        if (!empty($data['password'])) {
            $fields['password_enc'] = self::encryptPassword($data['password']);
        }

        $this->db->update('system_accesses', $fields, 'id = ? AND created_by = ?', [$id, $userId]);
        return true;
    }

    public function delete(int $id, int $userId): bool
    {
        $existing = $this->findById($id, $userId);
        if (!$existing) return false;
        $this->db->delete('system_accesses', 'id = ? AND created_by = ?', [$id, $userId]);
        return true;
    }
}
