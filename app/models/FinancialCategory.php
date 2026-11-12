<?php

/**
 * Categorias cadastráveis do Controle Financeiro (super_admin).
 * kind = 'ganho' | 'gasto' | 'ambos'. Fonte única para o select de categoria
 * no lançamento (substitui o campo de texto livre).
 */
class FinancialCategory
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById($id)
    {
        return $this->db->fetch("SELECT * FROM fin_categories WHERE id = ?", [$id]);
    }

    public function getAll($onlyActive = false): array
    {
        $sql = "SELECT * FROM fin_categories";
        if ($onlyActive) $sql .= " WHERE active = 1";
        $sql .= " ORDER BY name";
        try {
            return $this->db->fetchAll($sql);
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function create(array $data): int
    {
        return (int)$this->db->insert('fin_categories', $data);
    }

    public function toggleActive($id): bool
    {
        $c = $this->findById($id);
        if (!$c) return false;
        $new = ((int)$c['active'] === 1) ? 0 : 1;
        $this->db->update('fin_categories', ['active' => $new], 'id = ?', [$id]);
        return $new === 1;
    }

    public function remove(int $id): void
    {
        // FK em fin_transactions.category_id tem ON DELETE SET NULL.
        $this->db->delete('fin_categories', 'id = ?', [$id]);
    }
}
