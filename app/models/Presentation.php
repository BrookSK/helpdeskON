<?php

/**
 * Apresentação comercial (etapa anterior à Proposta). Model fino sobre Database.
 * O conteúdo (seções) é guardado como JSON e sempre passa por PresentationRules.
 */
class Presentation
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function generateToken(): string
    {
        return bin2hex(random_bytes(16));
    }

    public function findById($id)
    {
        return $this->db->fetch(
            "SELECT p.*, wc.contact_name AS crm_contact_name, u.name AS created_by_name
             FROM presentations p
             LEFT JOIN whatsapp_contacts wc ON p.contact_id = wc.id
             LEFT JOIN users u ON p.created_by = u.id
             WHERE p.id = ?",
            [$id]
        );
    }

    public function findByToken($token)
    {
        $token = trim((string)$token);
        if ($token === '') return null;
        return $this->db->fetch("SELECT * FROM presentations WHERE public_token = ? LIMIT 1", [$token]);
    }

    public function getAll(array $filters = [])
    {
        $sql = "SELECT p.*, wc.contact_name AS crm_contact_name, u.name AS created_by_name
                FROM presentations p
                LEFT JOIN whatsapp_contacts wc ON p.contact_id = wc.id
                LEFT JOIN users u ON p.created_by = u.id
                WHERE 1=1";
        $params = [];
        if (!empty($filters['status'])) { $sql .= " AND p.status = ?"; $params[] = $filters['status']; }
        $sql .= " ORDER BY p.id DESC";
        try { return $this->db->fetchAll($sql, $params); } catch (\Throwable $e) { return []; }
    }

    public function create($data)
    {
        if (empty($data['public_token'])) $data['public_token'] = $this->generateToken();
        if (!isset($data['content'])) $data['content'] = PresentationRules::encode($this->templateSections());
        return $this->db->insert('presentations', $data);
    }

    public function update($id, $data)
    {
        return $this->db->update('presentations', $data, 'id = ?', [$id]);
    }

    public function delete($id)
    {
        return $this->db->delete('presentations', 'id = ?', [$id]);
    }

    public function sections(array $presentation): array
    {
        return PresentationRules::decode($presentation['content'] ?? null);
    }

    public function registerView($id): void
    {
        $this->db->query(
            "UPDATE presentations SET view_count = view_count + 1, last_viewed_at = NOW() WHERE id = ?",
            [$id]
        );
    }

    /**
     * Modelo usado ao criar uma apresentação: o salvo em Configurações
     * ('presentation_template') ou, se não houver, o modelo curto ao cliente.
     */
    public function templateSections(): array
    {
        $saved = (string) Config::get('presentation_template', '');
        return $saved !== '' ? PresentationRules::decode($saved) : PresentationRules::defaultSections();
    }

    public function saveTemplate(array $sections): void
    {
        Config::set('presentation_template', PresentationRules::encode($sections));
    }
}
