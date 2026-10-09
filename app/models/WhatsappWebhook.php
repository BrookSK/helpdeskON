<?php

/**
 * Webhook de ENTRADA do WhatsApp (config por empresa). Model fino sobre
 * Database, no padrão do projeto. Cada webhook tem um token único usado na URL
 * pública que o sistema externo chama.
 */
class WhatsappWebhook
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById($id)
    {
        return $this->db->fetch("SELECT * FROM whatsapp_webhooks WHERE id = ?", [$id]);
    }

    /** Resolve um webhook ATIVO pelo token da URL pública. */
    public function findActiveByToken($token)
    {
        try {
            return $this->db->fetch(
                "SELECT * FROM whatsapp_webhooks WHERE token = ? AND active = 1 LIMIT 1",
                [$token]
            );
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Lista webhooks de uma empresa (ou todos, se $companyId null), já com o
     * nome da empresa e da instância para exibição.
     */
    public function getByCompany($companyId = null)
    {
        $sql = "SELECT w.*, c.name AS company_name,
                       i.display_name AS instance_display, i.instance_name
                  FROM whatsapp_webhooks w
                  LEFT JOIN companies c ON c.id = w.company_id
                  LEFT JOIN whatsapp_instances i ON i.id = w.instance_id";
        $params = [];
        if ($companyId !== null) {
            $sql .= " WHERE w.company_id = ?";
            $params[] = $companyId;
        }
        $sql .= " ORDER BY c.name IS NULL, c.name, w.name";
        try {
            return $this->db->fetchAll($sql, $params);
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function create($data)
    {
        return $this->db->insert('whatsapp_webhooks', $data);
    }

    public function update($id, $data)
    {
        return $this->db->update('whatsapp_webhooks', $data, 'id = ?', [$id]);
    }

    /** Alterna ativo/inativo. Retorna o novo valor (0/1) ou false se não existir. */
    public function toggleActive($id)
    {
        $w = $this->findById($id);
        if (!$w) return false;
        $new = ((int)$w['active'] === 1) ? 0 : 1;
        $this->db->update('whatsapp_webhooks', ['active' => $new], 'id = ?', [$id]);
        return $new;
    }

    public function delete($id)
    {
        return $this->db->delete('whatsapp_webhooks', 'id = ?', [$id]);
    }

    /**
     * Gera um token único para a URL pública. Garante unicidade consultando o
     * banco (colisão em 32 hex é improvável, mas a checagem é barata).
     */
    public function generateUniqueToken(): string
    {
        do {
            $token = bin2hex(random_bytes(16)); // 32 chars hex
            $exists = $this->db->fetch("SELECT id FROM whatsapp_webhooks WHERE token = ?", [$token]);
        } while ($exists);
        return $token;
    }
}
