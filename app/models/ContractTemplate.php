<?php

/**
 * Modelos de contrato reutilizáveis (Fase 4). Model fino sobre Database.
 */
class ContractTemplate
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById($id)
    {
        return $this->db->fetch("SELECT * FROM contract_templates WHERE id = ?", [$id]);
    }

    public function getAll($onlyActive = false)
    {
        $sql = "SELECT * FROM contract_templates";
        if ($onlyActive) $sql .= " WHERE active = 1";
        $sql .= " ORDER BY name";
        try { return $this->db->fetchAll($sql); } catch (\Throwable $e) { return []; }
    }

    public function create($data)
    {
        return $this->db->insert('contract_templates', $data);
    }

    public function update($id, $data)
    {
        return $this->db->update('contract_templates', $data, 'id = ?', [$id]);
    }

    public function toggleActive($id)
    {
        $t = $this->findById($id);
        if (!$t) return false;
        return $this->db->update('contract_templates', ['active' => ((int)$t['active'] === 1) ? 0 : 1], 'id = ?', [$id]);
    }
}
