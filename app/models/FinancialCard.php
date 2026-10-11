<?php

/**
 * Cartões de crédito do Controle Financeiro (super_admin).
 */
class FinancialCard
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById($id)
    {
        return $this->db->fetch("SELECT * FROM fin_credit_cards WHERE id = ?", [$id]);
    }

    public function getAll($onlyActive = false): array
    {
        $sql = "SELECT * FROM fin_credit_cards";
        if ($onlyActive) $sql .= " WHERE active = 1";
        $sql .= " ORDER BY name";
        try {
            return $this->db->fetchAll($sql);
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function create(array $data)
    {
        return (int)$this->db->insert('fin_credit_cards', $data);
    }

    public function update($id, array $data): void
    {
        $this->db->update('fin_credit_cards', $data, 'id = ?', [$id]);
    }

    public function toggleActive($id): bool
    {
        $c = $this->findById($id);
        if (!$c) return false;
        $new = ((int)$c['active'] === 1) ? 0 : 1;
        $this->db->update('fin_credit_cards', ['active' => $new], 'id = ?', [$id]);
        return $new === 1;
    }
}