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

    /**
     * Cria (ou reaproveita por e-mail) um usuário de acesso para um PRESTADOR,
     * com o papel derivado da função (developer/marketing/attendant/analyst).
     * Senha aleatória — o acesso real se dá por convite de 1º acesso / PIN.
     * Retorna o id do usuário (novo ou existente), ou null se faltar e-mail.
     */
    public function createForProvider(string $name, ?string $email, ?string $phone, string $role)
    {
        $email = trim((string)$email);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) return null;
        $existing = $this->findByEmail($email);
        if ($existing) return (int)$existing['id'];

        $allowed = ['developer', 'marketing', 'attendant', 'analyst'];
        $role = in_array($role, $allowed, true) ? $role : 'developer';

        return (int) $this->db->insert('users', [
            'name' => trim($name) !== '' ? trim($name) : 'Prestador',
            'email' => $email,
            'password' => password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT),
            'phone' => trim((string)$phone) ?: null,
            'role' => $role,
            'is_active' => 1,
        ]);
    }

    public function getAll($role = null)
    {
        // Subquery: nº de empresas ADICIONAIS (Multi-Empresas), sem contar a principal.
        $extraCountSql = "(SELECT COUNT(*) FROM user_company_access uca
                           WHERE uca.user_id = u.id
                             AND (u.company_id IS NULL OR uca.company_id <> u.company_id))";

        if ($role) {
            return $this->db->fetchAll(
                "SELECT u.*, comp.name as company_name, $extraCountSql AS extra_companies_count
                 FROM users u
                 LEFT JOIN companies comp ON u.company_id = comp.id
                 WHERE u.role = ? ORDER BY comp.name IS NULL, comp.name, u.name",
                [$role]
            );
        }
        return $this->db->fetchAll(
            "SELECT u.*, comp.name as company_name, $extraCountSql AS extra_companies_count
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
     * Verifica se um PIN de login (4 dígitos, coluna client_pin) já está em uso
     * por outro usuário. $exceptId ignora o próprio usuário ao editar.
     */
    public function clientPinExists($pin, $exceptId = null)
    {
        $pin = trim((string)$pin);
        if ($pin === '') return false;
        if ($exceptId) {
            return (bool) $this->db->fetch(
                "SELECT id FROM users WHERE client_pin = ? AND id <> ? LIMIT 1",
                [$pin, $exceptId]
            );
        }
        return (bool) $this->db->fetch("SELECT id FROM users WHERE client_pin = ? LIMIT 1", [$pin]);
    }

    /**
     * Define (ou gera) o PIN de cliente de um usuário, garantindo unicidade.
     * Só faz sentido para usuários com role 'client' (validado no controller).
     * Retorna o PIN definido, ou null em falha.
     */
    public function setClientPin($userId, ?string $pin = null): ?string
    {
        if ($pin === null) {
            // Gera um PIN único (algumas tentativas para evitar colisão).
            for ($i = 0; $i < 10; $i++) {
                $candidate = ClientPinRules::generate();
                if (!$this->clientPinExists($candidate, $userId)) { $pin = $candidate; break; }
            }
            if ($pin === null) return null;
        } else {
            $pin = ClientPinRules::normalize($pin);
            if ($pin === '' || $this->clientPinExists($pin, $userId)) return null;
        }
        $this->db->update('users', ['client_pin' => $pin], 'id = ?', [$userId]);
        return $pin;
    }

    /** Remove o PIN de cliente de um usuário. */
    public function clearClientPin($userId)
    {
        return $this->db->update('users', ['client_pin' => null], 'id = ?', [$userId]);
    }

    /**
     * Retorna o usuário ATIVO dono do PIN informado (login por PIN, /clientpin).
     * O PIN é por usuário: vale para qualquer papel.
     */
    public function findByClientPin($pin)
    {
        $pin = ClientPinRules::normalize($pin);
        if ($pin === '') return null;
        $user = $this->db->fetch(
            "SELECT * FROM users WHERE client_pin = ? AND is_active = 1 LIMIT 1",
            [$pin]
        );
        return $user ?: null;
    }

    /** Quantos super_admins ATIVOS existem no sistema. */
    public function countActiveSuperAdmins()
    {
        $row = $this->db->fetch(
            "SELECT COUNT(*) AS t FROM users WHERE role = 'super_admin' AND is_active = 1"
        );
        return (int) ($row['t'] ?? 0);
    }

    /**
     * O usuário informado é o ÚNICO super_admin ativo? Usado para impedir que o
     * sistema fique sem nenhum administrador (auto-exclusão/desativação/rebaixamento).
     */
    public function isLastActiveSuperAdmin($userId)
    {
        $user = $this->findById($userId);
        if (!$user || $user['role'] !== 'super_admin' || empty($user['is_active'])) {
            return false;
        }
        return $this->countActiveSuperAdmins() <= 1;
    }

    /**
     * Retorna as empresas vinculadas a um usuário (Multi-Empresas).
     * Combina a empresa principal (users.company_id) com os vínculos extras
     * registrados em user_company_access. Sem duplicatas, ordenado por nome.
     *
     * @return array Lista de empresas [id, name, is_primary]
     */
    public function getLinkedCompanies($userId)
    {
        $user = $this->findById($userId);
        if (!$user) {
            return [];
        }

        $companies = [];

        // 1) Empresa principal
        if (!empty($user['company_id'])) {
            $primary = $this->db->fetch("SELECT id, name FROM companies WHERE id = ?", [$user['company_id']]);
            if ($primary) {
                $companies[(int)$primary['id']] = [
                    'id' => (int)$primary['id'],
                    'name' => $primary['name'],
                    'is_primary' => true,
                ];
            }
        }

        // 2) Vínculos adicionais (user_company_access)
        $rows = $this->db->fetchAll(
            "SELECT c.id, c.name
             FROM user_company_access uca
             INNER JOIN companies c ON c.id = uca.company_id
             WHERE uca.user_id = ?",
            [$userId]
        );
        foreach ($rows as $r) {
            $id = (int)$r['id'];
            if (!isset($companies[$id])) {
                $companies[$id] = [
                    'id' => $id,
                    'name' => $r['name'],
                    'is_primary' => false,
                ];
            }
        }

        $list = array_values($companies);
        usort($list, function ($a, $b) {
            // Empresa principal primeiro, depois por nome
            if ($a['is_primary'] !== $b['is_primary']) {
                return $a['is_primary'] ? -1 : 1;
            }
            return strcasecmp($a['name'], $b['name']);
        });

        return $list;
    }

    /**
     * Verifica se um usuário está vinculado a uma empresa específica
     * (seja como empresa principal ou via user_company_access).
     */
    public function isLinkedToCompany($userId, $companyId)
    {
        $companyId = (int)$companyId;
        if ($companyId <= 0) {
            return false;
        }
        foreach ($this->getLinkedCompanies($userId) as $c) {
            if ((int)$c['id'] === $companyId) {
                return true;
            }
        }
        return false;
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

    /**
     * Gera um token de redefinição de PIN de acesso (client_pin) e envia email
     * com o link. Usa a mesma tabela password_resets com kind = 'pin', de modo
     * que o próprio usuário define o novo PIN pela tela segura — sem que o admin
     * veja ou escolha o valor. O link expira em 24 horas.
     *
     * @return bool true se o email foi enviado.
     */
    public function sendPinResetInvite($userId)
    {
        $user = $this->findById($userId);
        if (!$user || empty($user['email'])) {
            return false;
        }

        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

        // Invalida tokens de PIN anteriores (não mexe nos de senha).
        $this->db->query(
            "UPDATE password_resets SET used_at = NOW()
             WHERE user_id = ? AND kind = 'pin' AND used_at IS NULL",
            [$userId]
        );

        $this->db->insert('password_resets', [
            'user_id' => $user['id'],
            'token' => $token,
            'is_first_access' => 0,
            'kind' => 'pin',
            'expires_at' => $expiresAt,
        ]);

        $link = baseUrl('clientpin/resetPin/' . $token);
        $htmlBody = Mailer::template(
            'Redefinição de PIN de acesso',
            "<p>Olá, <strong>" . htmlspecialchars($user['name']) . "</strong>!</p>
            <p>Recebemos uma solicitação para redefinir o seu <strong>PIN de acesso</strong> (login por PIN).</p>
            <p style='text-align:center;margin:25px 0;'>
                <a href='{$link}' style='background:#00BFA6;color:#fff;padding:12px 30px;border-radius:8px;text-decoration:none;font-weight:600;font-size:0.9rem;display:inline-block;'>
                    Definir Novo PIN
                </a>
            </p>
            <p>Este link expira em <strong>24 horas</strong>. Por segurança, apenas você verá e definirá o novo PIN.</p>
            <p style='font-size:0.82rem;color:#999;'>Se você não solicitou, ignore este email: seu PIN atual continua válido.</p>
            <p style='font-size:0.78rem;color:#bbb;word-break:break-all;'>Link direto: {$link}</p>"
        );

        return Mailer::send($user['email'], 'Redefinição de PIN de acesso - ON Solutions Helpdesk', $htmlBody);
    }
}
