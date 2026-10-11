<?php

/**
 * Comissões do Controle Financeiro (super_admin).
 * Vinculadas a um ganho (projeto/venda fechada): pra quem, quanto, em quantas
 * parcelas. Quitar "paga a comissão" abate UMA parcela e empurra o próximo
 * vencimento; a comissão fecha sozinha na última.
 */
class FinancialCommission
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById($id)
    {
        return $this->db->fetch("SELECT * FROM fin_commissions WHERE id = ?", [$id]);
    }

    /** Lista comissões + dados do ganho de origem e do recebedor. */
    public function getAll(): array
    {
        $sql = "SELECT c.*, t.description AS ganho_desc, t.amount AS ganho_amount,
                       t.due_date AS ganho_due, u.name AS user_name
                FROM fin_commissions c
                LEFT JOIN fin_transactions t ON t.id = c.transaction_id
                LEFT JOIN users u ON u.id = c.user_id
                ORDER BY c.status = 'paid', c.next_due_date, c.id DESC
                LIMIT 500";
        try {
            return $this->db->fetchAll($sql);
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function create(array $data): int
    {
        return (int)$this->db->insert('fin_commissions', $data);
    }

    /**
     * Quita a próxima parcela da comissão. Retorna true quando fechou
     * inteira (última parcela paga).
     */
    public function markPaid(int $id): bool
    {
        $c = $this->findById($id);
        if (!$c || $c['status'] !== 'pending') return (bool)($c && $c['status'] === 'paid');

        $paid = (int)$c['installments_paid'] + 1;
        $total = max(1, (int)$c['installments']);
        $done = $paid >= $total;
        $nextDue = FinancialRules::commissionNextDue((string)$c['first_due_date'], $paid);

        $this->db->update('fin_commissions', [
            'installments_paid' => $paid,
            'next_due_date'     => $done ? null : $nextDue,
            'status'            => $done ? 'paid' : 'pending',
            'paid_at'           => $done ? date('Y-m-d H:i:s') : null,
        ], 'id = ?', [$id]);
        return $done;
    }

    public function remove(int $id): void
    {
        $this->db->delete('fin_commissions', 'id = ?', [$id]);
    }
}