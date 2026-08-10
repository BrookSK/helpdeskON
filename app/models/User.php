<?php

class User
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById($id)
    {
        return $this->db->fetch("SELECT * FROM users WHERE id = ?", [$id]);
    }

    public function findByEmail($email)
    {
        return $this->db->fetch("SELECT * FROM users WHERE email = ?", [$email]);
    }

    public function getAll($role = null)
    {
        if ($role) {
            return $this->db->fetchAll(
                "SELECT u.*, comp.name as company_name
                 FROM users u
                 LEFT JOIN companies comp ON u.company_id = comp.id
                 WHERE u.role = ? ORDER BY comp.name IS NULL, comp.name, u.name",
                [$role]
            );
        }
        return $this->db->fetchAll(
            "SELECT u.*, comp.name as company_name
             FROM users u
             LEFT JOIN companies comp ON u.company_id = comp.id
             ORDER BY u.name ASC"
        );
    }

    public function getClients()
    {
        return $this->db->fetchAll("SELECT * FROM users WHERE role = 'client' ORDER BY name");
    }

    public function getAttendants()
    {
        return $this->db->fetchAll("SELECT * FROM users WHERE role IN ('attendant', 'whatsapp_agent') AND is_active = 1 ORDER BY name");
    }

    /**
     * Usuários ativos agrupados por papel, para seleção hierárquica (Papel > Usuários).
     * $roles: lista de papéis a incluir.
     */
    public function getGroupedByRole($roles)
    {
        if (empty($roles)) return [];
        $placeholders = implode(',', array_fill(0, count($roles), '?'));
        $rows = $this->db->fetchAll(
            "SELECT id, name, email, role FROM users
             WHERE role IN ($placeholders) AND is_active = 1
             ORDER BY role, name",
            $roles
        );
        $grouped = [];
        foreach ($rows as $r) {
            $grouped[$r['role']][] = $r;
        }
        return $grouped;
    }

    /**
     * Usuários ativos de um conjunto de papéis (lista simples).
     */
    public function getByRoles($roles)
    {
        if (empty($roles)) return [];
        $placeholders = implode(',', array_fill(0, count($roles), '?'));
        return $this->db->fetchAll(
            "SELECT * FROM users WHERE role IN ($placeholders) AND is_active = 1 ORDER BY name",
            $roles
        );
    }

    public function create($data)
    {
        $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        return $this->db->insert('users', $data);
    }

    public function update($id, $data)
    {
        if (isset($data['password']) && !empty($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        } else {
            unset($data['password']);
        }
        return $this->db->update('users', $data, 'id = ?', [$id]);
    }

    public function delete($id)
    {
        return $this->db->delete('users', 'id = ?', [$id]);
    }

    public function authenticate($email, $password)
    {
        $user = $this->findByEmail($email);
        if ($user && password_verify($password, $user['password']) && $user['is_active']) {
            return $user;
        }
        return false;
    }

    public function toggleActive($id)
    {
        $user = $this->findById($id);
        $newStatus = $user['is_active'] ? 0 : 1;
        return $this->db->update('users', ['is_active' => $newStatus], 'id = ?', [$id]);
    }

    /**
     * Gera um token de definição de senha (primeiro acesso) e envia email com o link.
     * Após definir a senha, o usuário é logado automaticamente.
     */
    public function sendFirstAccessInvite($userId)
    {
        $user = $this->findById($userId);
        if (!$user || empty($user['email'])) {
            return false;
        }

        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));

        // Invalidar tokens anteriores
        $this->db->query(
            "UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL",
            [$userId]
        );

        $this->db->insert('password_resets', [
            'user_id' => $user['id'],
            'token' => $token,
            'is_first_access' => 1,
            'expires_at' => $expiresAt,
        ]);

        $link = baseUrl('password/reset/' . $token);
        $htmlBody = Mailer::template(
            'Bem-vindo ao Helpdesk!',
            "<p>Olá, <strong>" . htmlspecialchars($user['name']) . "</strong>!</p>
            <p>Seu acesso ao sistema de Helpdesk foi criado. Para começar, defina sua senha de acesso clicando no botão abaixo:</p>
            <p style='text-align:center;margin:25px 0;'>
                <a href='{$link}' style='background:#00BFA6;color:#fff;padding:12px 30px;border-radius:8px;text-decoration:none;font-weight:600;font-size:0.9rem;display:inline-block;'>
                    Definir Minha Senha
                </a>
            </p>
            <p style='margin:5px 0;'><strong>Email de acesso:</strong> {$user['email']}</p>
            <p>Este link expira em <strong>7 dias</strong>. Após definir sua senha, você entrará automaticamente no sistema.</p>
            <p style='font-size:0.78rem;color:#bbb;word-break:break-all;'>Link direto: {$link}</p>"
        );

        return Mailer::send($user['email'], 'Defina sua senha - ON Solutions Helpdesk', $htmlBody);
    }
}
