<?php

/**
 * Comissão automática: card do CRM fechado -> lançamento "a pagar" no
 * Controle Financeiro (fin_transactions com crm_card_id/commission_role,
 * migration 173). Regras em CrmCommissionRules.
 *
 * Chamado pelo CrmBoard sempre que o desfecho, o valor ou os responsáveis
 * (quem fechou/prospectou) de um card mudam, e antes de excluir cards.
 * Nunca deve derrubar a ação do CRM: quem chama trata exceções.
 */
class CrmCommissionSync
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Acerta os lançamentos de comissão do card.
     *
     * @param bool        $allowCreate     true quando o card acabou de ser fechado.
     * @param string|null $previousOutcome desfecho antes da mudança (para o aviso
     *                                     de comissão já paga em card reaberto).
     * @return array{created:int[],updated:int[],cancelled:int[],paidOrphans:int[]}
     */
    public function syncCard(int $cardId, bool $allowCreate, ?string $previousOutcome = null, ?int $actorId = null): array
    {
        $result = ['created' => [], 'updated' => [], 'cancelled' => [], 'paidOrphans' => []];
        $card = $this->db->fetch(
            "SELECT c.*, wc.contact_name AS lead_name
             FROM crm_cards c
             LEFT JOIN whatsapp_contacts wc ON wc.id = c.contact_id
             WHERE c.id = ?",
            [$cardId]
        );
        if (!$card) return $result;

        $closer = $this->findUser($card['converted_by'] ?? null);
        $prospector = $this->findUser($card['prospected_by'] ?? null);
        $desired = CrmCommissionRules::desired($card, $closer, $prospector);

        $existing = $this->db->fetchAll(
            "SELECT id, commission_role, user_id, amount, status, description
             FROM fin_transactions
             WHERE crm_card_id = ? AND status <> 'cancelled'
             ORDER BY id",
            [$cardId]
        );

        $plan = CrmCommissionRules::plan($desired, $existing, $allowCreate);
        if (empty($plan['create']) && empty($plan['update']) && empty($plan['cancel']) && empty($plan['paidOrphans'])) {
            return $result;
        }

        $closingDate = !empty($card['outcome_at']) ? substr((string)$card['outcome_at'], 0, 10) : date('Y-m-d');
        $categoryId = (!empty($plan['create']) || !empty($plan['update'])) ? $this->commissionCategoryId() : null;

        foreach ($plan['create'] as $want) {
            $salary = $this->salaryInfo($want['user_id']);
            $row = [
                'kind'            => $salary['kind'],
                'direction'       => 'out',
                'category'        => 'Comissão',
                'category_id'     => $categoryId,
                'description'     => $want['description'],
                'amount'          => $want['amount'],
                'installments'    => 1,
                'status'          => 'pending',
                'due_date'        => CrmCommissionRules::dueDate($closingDate, $salary['rows']),
                'account_id'      => $salary['account_id'],
                'user_id'         => $want['user_id'],
                'source'          => 'manual',
                'created_by'      => $actorId,
                'crm_card_id'     => $cardId,
                'commission_role' => $want['role'],
            ];
            $result['created'][] = (int)$this->db->insert('fin_transactions', $row);
        }

        foreach ($plan['update'] as [$id, $changes, $want]) {
            if (isset($changes['user_id'])) {
                // Outra pessoa: vencimento e conta passam a ser os do salário dela.
                $salary = $this->salaryInfo($want['user_id']);
                $changes['due_date'] = CrmCommissionRules::dueDate($closingDate, $salary['rows']);
                $changes['account_id'] = $salary['account_id'];
                $changes['kind'] = $salary['kind'];
            }
            if ($this->db->update('fin_transactions', $changes, "id = ? AND status = 'pending'", [$id]) > 0) {
                $result['updated'][] = $id;
            }
        }

        foreach ($plan['cancel'] as $id) {
            if ($this->db->update('fin_transactions', ['status' => 'cancelled'], "id = ? AND status = 'pending'", [$id]) > 0) {
                $result['cancelled'][] = $id;
            }
        }

        $result['paidOrphans'] = $plan['paidOrphans'];
        if (!empty($plan['paidOrphans']) && $previousOutcome === 'converted'
            && ($card['lead_outcome'] ?? 'open') !== 'converted') {
            // Sem valores: as atividades do card são vistas por toda a equipe comercial.
            $this->db->insert('crm_card_activities', [
                'card_id'       => $cardId,
                'user_id'       => $actorId,
                'activity_type' => 'note',
                'description'   => '⚠️ Este card tem comissão já paga no Controle Financeiro. Ela não foi estornada; confira lá.',
            ]);
        }
        return $result;
    }

    /** Cancela as comissões pendentes de cards que vão ser excluídos. */
    public function cancelPendingForCards(array $cardIds): int
    {
        $ids = array_values(array_filter(array_map('intval', $cardIds)));
        if (empty($ids)) return 0;
        $ph = implode(',', array_fill(0, count($ids), '?'));
        return (int)$this->db->query(
            "UPDATE fin_transactions SET status = 'cancelled'
             WHERE status = 'pending' AND crm_card_id IN ($ph)",
            $ids
        )->rowCount();
    }

    private function findUser($id): ?array
    {
        if (empty($id)) return null;
        $u = $this->db->fetch(
            "SELECT id, name, commission_percent, commission_prospection_percent, commission_closing_percent
             FROM users WHERE id = ?",
            [(int)$id]
        );
        return $u ?: null;
    }

    /**
     * Salários lançados da pessoa (tipo Salário ou categoria "Salário"), sem as
     * próprias comissões. Dão o vencimento, a conta e o tipo do lançamento.
     *
     * @return array{rows:array,account_id:?int,kind:string}
     */
    private function salaryInfo(int $userId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT t.id, t.kind, t.due_date, t.status, t.account_id
             FROM fin_transactions t
             LEFT JOIN fin_categories cat ON cat.id = t.category_id
             WHERE t.user_id = ? AND t.status <> 'cancelled' AND t.crm_card_id IS NULL
               AND t.direction = 'out' AND t.installments <= 1
               AND (t.kind = 'salario' OR LOWER(COALESCE(cat.name, t.category, '')) LIKE 'sal_rio%')
             ORDER BY t.due_date DESC, t.id DESC
             LIMIT 24",
            [$userId]
        );
        $latest = $rows[0] ?? null;
        $kind = ($latest && in_array($latest['kind'], ['salario', 'gasto'], true)) ? $latest['kind'] : 'salario';
        return [
            'rows'       => $rows,
            'account_id' => ($latest && !empty($latest['account_id'])) ? (int)$latest['account_id'] : null,
            'kind'       => $kind,
        ];
    }

    /** Categoria "Comissão" (cria como 'ambos' para aparecer também no tipo Salário). */
    private function commissionCategoryId(): ?int
    {
        try {
            $cat = $this->db->fetch(
                "SELECT id FROM fin_categories
                 WHERE LOWER(name) IN ('comissão', 'comissao', 'comissões', 'comissoes')
                 ORDER BY active DESC, id LIMIT 1"
            );
            if ($cat) return (int)$cat['id'];
            return (int)$this->db->insert('fin_categories', ['name' => 'Comissão', 'kind' => 'ambos', 'active' => 1]);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
