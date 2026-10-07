<?php

/**
 * Contas a pagar do prestador (Guia de Contratação de Prestadores).
 * Model fino sobre Database, no padrão do projeto. A lógica de QUAIS lançamentos
 * gerar vive em PayableRules (regra pura); aqui só persistimos.
 */
class Payable
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById($id)
    {
        return $this->db->fetch("SELECT * FROM payables WHERE id = ?", [$id]);
    }

    /** Lançamentos de um prestador (mais recentes primeiro). */
    public function getByProvider($providerId)
    {
        try {
            return $this->db->fetchAll(
                "SELECT * FROM payables WHERE provider_id = ? ORDER BY id DESC",
                [(int)$providerId]
            );
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function create(array $data)
    {
        return $this->db->insert('payables', $data);
    }

    /**
     * (Re)gera os lançamentos de um prestador a partir de um plano
     * (PayableRules::buildPlanForProvider). IDEMPOTENTE: remove os lançamentos
     * AINDA PENDENTES deste prestador e recria pelo plano, preservando os que já
     * foram pagos/cancelados (não mexe no histórico). Assim o reprocessamento do
     * webhook de assinatura não duplica nem apaga pagamentos já feitos.
     *
     * @return int quantidade de lançamentos criados
     */
    public function replaceForProvider($providerId, array $plan, $createdBy = null): int
    {
        $providerId = (int)$providerId;
        // Remove apenas os pendentes (não toca em paid/cancelled — histórico).
        try {
            $this->db->delete('payables', 'provider_id = ? AND status = ?', [$providerId, PayableRules::STATUS_PENDING]);
        } catch (\Throwable $e) { /* se a tabela não existe, deixa estourar na create abaixo */ }

        $count = 0;
        foreach ($plan as $item) {
            $kind = PayableRules::normalizeKind($item['kind'] ?? null);
            if ($kind === null) continue;
            $this->create([
                'provider_id'       => $providerId,
                'kind'              => $kind,
                'description'       => isset($item['description']) ? mb_substr((string)$item['description'], 0, 255) : null,
                'amount'            => (float)($item['amount'] ?? 0),
                'due_date'          => $item['due_date'] ?? null,
                'recurring_cycle'   => $item['recurring_cycle'] ?? null,
                'installment_no'    => $item['installment_no'] ?? null,
                'installment_total' => $item['installment_total'] ?? null,
                'status'            => PayableRules::STATUS_PENDING,
                'created_by'        => $createdBy !== null ? (int)$createdBy : null,
            ]);
            $count++;
        }
        return $count;
    }

    /** Marca um lançamento como pago. */
    public function markPaid($id): bool
    {
        $p = $this->findById($id);
        if (!$p) return false;
        $this->db->update('payables', [
            'status'  => PayableRules::STATUS_PAID,
            'paid_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [(int)$id]);
        return true;
    }

    /** Cancela um lançamento. */
    public function cancel($id): bool
    {
        $p = $this->findById($id);
        if (!$p) return false;
        $this->db->update('payables', [
            'status' => PayableRules::STATUS_CANCELLED,
        ], 'id = ?', [(int)$id]);
        return true;
    }

    /** Total pendente de um prestador. */
    public function pendingTotal($providerId): float
    {
        $row = $this->db->fetch(
            "SELECT COALESCE(SUM(amount),0) AS t FROM payables WHERE provider_id = ? AND status = ?",
            [(int)$providerId, PayableRules::STATUS_PENDING]
        );
        return (float)($row['t'] ?? 0);
    }
}
