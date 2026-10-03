<?php

/**
 * Contas Asaas (Fase 5). As 3 contas selecionáveis por cobrança.
 */
class FinanceAccount
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById($id)
    {
        return $this->db->fetch("SELECT * FROM finance_accounts WHERE id = ?", [$id]);
    }

    public function getAll($onlyActive = false)
    {
        $sql = "SELECT * FROM finance_accounts";
        if ($onlyActive) $sql .= " WHERE active = 1";
        $sql .= " ORDER BY name";
        return $this->db->fetchAll($sql);
    }

    public function create($data)
    {
        return $this->db->insert('finance_accounts', $data);
    }

    public function update($id, $data)
    {
        return $this->db->update('finance_accounts', $data, 'id = ?', [$id]);
    }

    public function toggleActive($id)
    {
        $a = $this->findById($id);
        if (!$a) return false;
        return $this->db->update('finance_accounts', ['active' => ((int)$a['active'] === 1) ? 0 : 1], 'id = ?', [$id]);
    }
}
