<?php

/**
 * Contas bancárias do Controle Financeiro (super_admin).
 * Cada conta tem um saldo inicial (opening_balance); o saldo atual é derivado
 * dos lançamentos pagos vinculados (ver FinancialAccountRules::accountBalance).
 */
class FinancialBankAccount
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById($id)
    {
        return $this->db->fetch("SELECT * FROM fin_bank_accounts WHERE id = ?", [$id]);
    }

    public function getAll($onlyActive = false): array
    {
        $sql = "SELECT * FROM fin_bank_accounts";
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
        return (int)$this->db->insert('fin_bank_accounts', $data);
    }

    public function update($id, array $data): void
    {
        $this->db->update('fin_bank_accounts', $data, 'id = ?', [$id]);
    }

    public function toggleActive($id): bool
    {
        $a = $this->findById($id);
        if (!$a) return false;
        $new = ((int)$a['active'] === 1) ? 0 : 1;
        $this->db->update('fin_bank_accounts', ['active' => $new], 'id = ?', [$id]);
        return $new === 1;
    }

    public function remove(int $id): void
    {
        // FKs em fin_transactions/fin_credit_cards têm ON DELETE SET NULL:
        // apagar a conta não apaga lançamentos, apenas desvincula.
        $this->db->delete('fin_bank_accounts', 'id = ?', [$id]);
    }
}
