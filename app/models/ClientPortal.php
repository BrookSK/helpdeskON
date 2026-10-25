<?php

/**
 * Dados do portal do cliente (change 02, D7): contratos assinados, cobranças e
 * projetos. Toda consulta exige o company_id do usuário logado, para que um
 * cliente nunca veja o que é de outra empresa.
 */
class ClientPortal
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function contracts(int $companyId): array
    {
        return $this->db->fetchAll(
            "SELECT id, title, status, signed_at, signed_file_status FROM contracts
             WHERE company_id = ? AND status = 'signed' ORDER BY signed_at DESC, id DESC",
            [$companyId]
        );
    }

    /** Contrato assinado da empresa, ou null (inclusive quando é de outra empresa). */
    public function findContract(int $companyId, int $contractId): ?array
    {
        $c = $this->db->fetch(
            "SELECT * FROM contracts WHERE id = ? AND company_id = ? AND status = 'signed'",
            [$contractId, $companyId]
        );
        return $c ?: null;
    }

    public function charges(int $companyId): array
    {
        return $this->db->fetchAll(
            "SELECT c.id, c.kind, c.description, c.amount, c.due_date, c.status, c.invoice_url, c.paid_at,
                    c.asaas_charge_id, c.asaas_subscription_id, fp.title AS project_title
             FROM finance_charges c
             JOIN finance_projects fp ON fp.id = c.project_id
             WHERE fp.company_id = ? AND c.status <> 'cancelled'
             ORDER BY (c.status = 'paid') ASC, c.due_date IS NULL, c.due_date ASC, c.id ASC",
            [$companyId]
        );
    }

    public function projects(int $companyId): array
    {
        return $this->db->fetchAll(
            "SELECT id, name, status, contract_type, delivered_at, warranty_ends_at, support_contract FROM projects
             WHERE company_id = ? AND status <> 'cancelled' ORDER BY id DESC",
            [$companyId]
        );
    }
}
