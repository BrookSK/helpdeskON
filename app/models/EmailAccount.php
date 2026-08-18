<?php

class EmailAccount
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById($id)
    {
        return $this->db->fetch("SELECT * FROM email_accounts WHERE id = ?", [$id]);
    }

    /**
     * Todas as contas ativas (para admin).
     */
    public function getAll()
    {
        return $this->db->fetchAll(
            "SELECT ea.*, u.name as created_by_name
             FROM email_accounts ea
             LEFT JOIN users u ON ea.created_by = u.id
             ORDER BY ea.email ASC"
        );
    }

    /**
     * Contas vinculadas a um usuário específico (para o dropdown de envio).
     */
    public function getByUser($userId)
    {
        return $this->db->fetchAll(
            "SELECT ea.*
             FROM email_accounts ea
             JOIN email_account_users eau ON ea.id = eau.email_account_id
             WHERE eau.user_id = ? AND ea.is_active = 1
             ORDER BY ea.email ASC",
            [$userId]
        );
    }

    public function create($data)
    {
        if (!empty($data['smtp_password'])) {
            $data['smtp_password'] = self::encryptPassword($data['smtp_password']);
        }
        return $this->db->insert('email_accounts', $data);
    }

    public function update($id, $data)
    {
        // Só recriptografa se a senha foi alterada
        if (!empty($data['smtp_password'])) {
            $data['smtp_password'] = self::encryptPassword($data['smtp_password']);
        } else {
            unset($data['smtp_password']);
        }
        return $this->db->update('email_accounts', $data, 'id = ?', [$id]);
    }

    public function delete($id)
    {
        return $this->db->delete('email_accounts', 'id = ?', [$id]);
    }

    public function toggleActive($id)
    {
        $account = $this->findById($id);
        $newStatus = $account['is_active'] ? 0 : 1;
        return $this->db->update('email_accounts', ['is_active' => $newStatus], 'id = ?', [$id]);
    }

    // --- Vinculação com usuários ---

    /**
     * Retorna os IDs dos usuários vinculados a uma conta.
     */
    public function getLinkedUserIds($accountId)
    {
        $rows = $this->db->fetchAll(
            "SELECT user_id FROM email_account_users WHERE email_account_id = ?",
            [$accountId]
        );
        return array_column($rows, 'user_id');
    }

    /**
     * Retorna os usuários vinculados a uma conta (com dados).
     */
    public function getLinkedUsers($accountId)
    {
        return $this->db->fetchAll(
            "SELECT u.id, u.name, u.email, u.role
             FROM email_account_users eau
             JOIN users u ON eau.user_id = u.id
             WHERE eau.email_account_id = ?
             ORDER BY u.name",
            [$accountId]
        );
    }

    /**
     * Define os usuários vinculados a uma conta (substitui os existentes).
     */
    public function setLinkedUsers($accountId, array $userIds)
    {
        $this->db->delete('email_account_users', 'email_account_id = ?', [$accountId]);
        foreach ($userIds as $uid) {
            if (!$uid) continue;
            $this->db->insert('email_account_users', [
                'email_account_id' => (int)$accountId,
                'user_id' => (int)$uid,
            ]);
        }
    }

    // --- Criptografia de senha ---

    private static function encryptPassword($password)
    {
        $key = self::getEncryptionKey();
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($password, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . '::' . $encrypted);
    }

    public static function decryptPassword($encrypted)
    {
        $key = self::getEncryptionKey();
        $parts = explode('::', base64_decode($encrypted), 2);
        if (count($parts) !== 2) return $encrypted; // fallback: não criptografado
        [$iv, $data] = $parts;
        return openssl_decrypt($data, 'AES-256-CBC', $key, 0, $iv);
    }

    private static function getEncryptionKey()
    {
        // Usa uma chave derivada do app_name + salt fixo
        $appName = Config::get('app_name', 'helpdeskON');
        return hash('sha256', $appName . '_email_encryption_key_2024', true);
    }
}
