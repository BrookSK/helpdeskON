<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use CrmBoard;
use Database;
use FinancialBankAccount;
use FinancialTransaction;
use ProposalResponse;

/**
 * Comissão automática do CRM no Controle Financeiro (migration 173), contra
 * helpdesk_on_test. Passa pelo mesmo caminho do sistema: CrmBoard::updateCard
 * (usado ao arrastar para "Fechado", "Marcar como convertido", editar o
 * card...) e CrmBoard::deleteCard.
 */
final class CrmCommissionSyncTest extends TestCase
{
    private Database $db;
    private CrmBoard $board;
    private int $admin;
    private int $evelyn;
    private int $bruno;
    private int $accountId;
    private int $boardId;
    private int $colNovo;
    private int $colFechado;
    private ?int $salaryId = null;
    /** @var int[] */
    private array $cardIds = [];
    private ?int $createdCategoryId = null;

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste não configurado.');
        }
        $this->db = Database::getInstance();
        $cols = array_column($this->db->fetchAll("SHOW COLUMNS FROM fin_transactions"), 'Field');
        if (!in_array('crm_card_id', $cols, true)) {
            $this->markTestSkipped('Migration 173 não aplicada no banco de teste.');
        }
        $hadCategory = $this->db->fetch("SELECT id FROM fin_categories WHERE LOWER(name) IN ('comissão','comissao','comissões','comissoes') LIMIT 1");

        $this->board = new CrmBoard();
        $u = uniqid();
        $mk = fn(string $name, string $role, float $closing, float $prosp) => (int)$this->db->insert('users', [
            'name' => "{$name} {$u}", 'email' => strtolower($name) . "_{$u}@example.test",
            'password' => password_hash('x', PASSWORD_BCRYPT), 'role' => $role,
            'commission_closing_percent' => $closing, 'commission_prospection_percent' => $prosp,
        ]);
        $this->admin = $mk('Admin', 'super_admin', 0, 0);
        $this->evelyn = $mk('Evelyn', 'comercial', 5, 0);
        $this->bruno = $mk('Bruno', 'comercial', 10, 2);

        $this->accountId = (new FinancialBankAccount())->create([
            'name' => 'Conta ' . $u, 'opening_balance' => 10000, 'active' => 1, 'created_by' => $this->admin,
        ]);

        $this->boardId = (int)$this->board->create(['name' => "Board {$u}", 'created_by' => $this->admin]);
        $this->colNovo = (int)$this->board->createColumn(['board_id' => $this->boardId, 'name' => 'Novo']);
        $this->colFechado = (int)$this->board->createColumn(['board_id' => $this->boardId, 'name' => 'Fechado']);

        if (!$hadCategory) {
            // A categoria é criada pelo sistema na 1ª comissão; o teste a remove no fim.
            $this->createdCategoryId = -1;
        }
    }

    protected function tearDown(): void
    {
        if (!isset($this->db)) return;
        foreach ($this->cardIds as $id) {
            try { $this->db->delete('fin_transactions', 'crm_card_id = ?', [$id]); } catch (\Throwable $e) {}
            try { $this->db->delete('crm_card_activities', 'card_id = ?', [$id]); } catch (\Throwable $e) {}
        }
        try { $this->db->delete('fin_transactions', 'created_by = ?', [$this->admin]); } catch (\Throwable $e) {}
        foreach ([$this->evelyn, $this->bruno] as $uid) {
            try { $this->db->delete('fin_transactions', 'user_id = ?', [$uid]); } catch (\Throwable $e) {}
        }
        try { $this->board->delete($this->boardId); } catch (\Throwable $e) {}
        try { $this->db->delete('fin_bank_accounts', 'id = ?', [$this->accountId]); } catch (\Throwable $e) {}
        if ($this->createdCategoryId === -1) {
            try { $this->db->query("DELETE FROM fin_categories WHERE name = 'Comissão' AND id NOT IN (SELECT category_id FROM (SELECT DISTINCT category_id FROM fin_transactions WHERE category_id IS NOT NULL) x)"); } catch (\Throwable $e) {}
        }
        foreach ([$this->evelyn, $this->bruno, $this->admin] as $uid) {
            try { $this->db->delete('users', 'id = ?', [$uid]); } catch (\Throwable $e) {}
        }
    }

    private function salario(int $userId, string $due, string $status = 'pending'): int
    {
        return (new FinancialTransaction())->create([
            'kind' => 'salario', 'direction' => 'out', 'description' => 'Salário', 'amount' => 2000,
            'installments' => 1, 'status' => $status, 'due_date' => $due,
            'paid_at' => $status === 'paid' ? $due . ' 10:00:00' : null,
            'recurring_cycle' => 'monthly', 'account_id' => $this->accountId, 'user_id' => $userId,
            'source' => 'manual', 'created_by' => $this->admin,
        ]);
    }

    private function card(float $value = 2000, array $o = []): int
    {
        $id = (int)$this->board->createCard($o + [
            'column_id' => $this->colNovo, 'title' => 'Padaria Sol', 'value' => $value,
            'assigned_to' => $this->evelyn, 'created_by' => $this->admin,
        ]);
        $this->cardIds[] = $id;
        return $id;
    }

    /** Mesmo update que o CrmController faz ao arrastar para "Fechado". */
    private function fechar(int $cardId, int $closedBy, ?int $prospectedBy = null, string $when = '2026-11-20 15:00:00'): void
    {
        $this->board->moveCard($cardId, $this->colFechado, 0);
        $this->board->updateCard($cardId, [
            'lead_outcome' => 'converted', 'outcome_at' => $when,
            'converted_by' => $closedBy, 'prospected_by' => $prospectedBy ?? $closedBy,
        ]);
    }

    private function comissoes(int $cardId, bool $withCancelled = false): array
    {
        $sql = "SELECT t.*, cat.name AS category_name FROM fin_transactions t
                LEFT JOIN fin_categories cat ON cat.id = t.category_id
                WHERE t.crm_card_id = ?" . ($withCancelled ? '' : " AND t.status <> 'cancelled'") . " ORDER BY t.id";
        return $this->db->fetchAll($sql, [$cardId]);
    }

    public function testFecharLancaComissaoAPagarJuntoComOSalario(): void
    {
        $this->salario($this->evelyn, '2026-12-05');
        $card = $this->card(2000);
        $this->fechar($card, $this->evelyn);

        $rows = $this->comissoes($card);
        $this->assertCount(1, $rows);
        $c = $rows[0];
        $this->assertSame('closing', $c['commission_role']);
        $this->assertSame($this->evelyn, (int)$c['user_id']);
        $this->assertEqualsWithDelta(100.0, (float)$c['amount'], 0.001);
        $this->assertSame('pending', $c['status']);
        $this->assertSame('out', $c['direction']);
        $this->assertSame('salario', $c['kind']);
        $this->assertSame('2026-12-05', $c['due_date']);
        $this->assertSame($this->accountId, (int)$c['account_id']);
        $this->assertSame('Comissão', $c['category_name']);
        $this->assertNull($c['recurring_cycle']);
        $this->assertSame('Comissão de fechamento – Padaria Sol (5% de R$ 2.000,00)', $c['description']);
    }

    public function testComissaoApareceNoAPagarDoMesESaiDoSaldoAoPagar(): void
    {
        $this->salario($this->evelyn, '2026-12-05');
        $card = $this->card(2000);
        $this->fechar($card, $this->evelyn);
        $id = (int)$this->comissoes($card)[0]['id'];

        $tx = new FinancialTransaction();
        $dezembro = array_filter($tx->getAll(['month' => '2026-12', 'user_id' => $this->evelyn]), fn($r) => (int)$r['id'] === $id);
        $this->assertCount(1, $dezembro);

        $tx->markPaid($id);
        $mov = $tx->movementsByAccount()[$this->accountId] ?? [];
        $this->assertEqualsWithDelta(9900.0, \FinancialAccountRules::accountBalance(10000.0, $mov), 0.001);
        // Pagar a comissão não cria "próxima ocorrência".
        $this->assertCount(1, $this->comissoes($card));
    }

    public function testSemSalarioLancadoVenceDia5DoMesSeguinte(): void
    {
        $card = $this->card(1000);
        $this->fechar($card, $this->evelyn, null, '2026-11-20 10:00:00');
        $c = $this->comissoes($card)[0];
        $this->assertSame('2026-12-05', $c['due_date']);
        $this->assertNull($c['account_id']);
        $this->assertSame('salario', $c['kind']);
    }

    public function testFecharDeNovoNaoDuplica(): void
    {
        $card = $this->card(2000);
        $this->fechar($card, $this->evelyn);
        $this->fechar($card, $this->evelyn);
        $this->assertCount(1, $this->comissoes($card));
    }

    public function testOutroFechouGeraAsDuasComissoes(): void
    {
        // Bruno trouxe (2% prospecção) e Evelyn fechou (5% fechamento).
        $card = $this->card(4000);
        $this->fechar($card, $this->evelyn, $this->bruno);
        $rows = $this->comissoes($card);
        $this->assertCount(2, $rows);
        $byRole = array_column($rows, null, 'commission_role');
        $this->assertSame($this->evelyn, (int)$byRole['closing']['user_id']);
        $this->assertEqualsWithDelta(200.0, (float)$byRole['closing']['amount'], 0.001);
        $this->assertSame($this->bruno, (int)$byRole['prospection']['user_id']);
        $this->assertEqualsWithDelta(80.0, (float)$byRole['prospection']['amount'], 0.001);
    }

    public function testPercentualZeradoNaoLanca(): void
    {
        // Evelyn trouxe (prospecção 0%) e Bruno fechou: só o fechamento do Bruno.
        $card = $this->card(1000);
        $this->fechar($card, $this->bruno, $this->evelyn);
        $rows = $this->comissoes($card);
        $this->assertCount(1, $rows);
        $this->assertSame('closing', $rows[0]['commission_role']);
        $this->assertSame($this->bruno, (int)$rows[0]['user_id']);

        // Admin fechando (sem %): nada.
        $card2 = $this->card(1000);
        $this->fechar($card2, $this->admin);
        $this->assertSame([], $this->comissoes($card2));
    }

    public function testMudarValorDoCardFechadoAtualizaPendente(): void
    {
        $card = $this->card(2000);
        $this->fechar($card, $this->evelyn);
        $this->board->updateCard($card, ['value' => 3000]);
        $c = $this->comissoes($card)[0];
        $this->assertEqualsWithDelta(150.0, (float)$c['amount'], 0.001);
        $this->assertStringContainsString('R$ 3.000,00', $c['description']);
        $this->assertCount(1, $this->comissoes($card));
    }

    public function testValorDeCardAbertoNaoLanca(): void
    {
        $card = $this->card(2000);
        $this->board->updateCard($card, ['value' => 5000]);
        $this->assertSame([], $this->comissoes($card, true));
    }

    public function testReabrirCancelaEFecharDeNovoLancaOutra(): void
    {
        $card = $this->card(2000);
        $this->fechar($card, $this->evelyn);
        $first = (int)$this->comissoes($card)[0]['id'];

        // Voltou ao funil (mesmo update do CrmController::moveCard).
        $this->board->updateCard($card, ['lead_outcome' => 'open', 'outcome_at' => null]);
        $this->assertSame([], $this->comissoes($card));
        $this->assertSame('cancelled', $this->db->fetch("SELECT status FROM fin_transactions WHERE id = ?", [$first])['status']);

        $this->fechar($card, $this->evelyn);
        $rows = $this->comissoes($card);
        $this->assertCount(1, $rows);
        $this->assertNotSame($first, (int)$rows[0]['id']);
    }

    public function testPerdidoCancelaPendente(): void
    {
        $card = $this->card(2000);
        $this->fechar($card, $this->evelyn);
        $this->board->updateCard($card, ['lead_outcome' => 'lost', 'outcome_at' => date('Y-m-d H:i:s')]);
        $this->assertSame([], $this->comissoes($card));
    }

    public function testComissaoPagaNaoEstornaEAvisaNoCard(): void
    {
        $card = $this->card(2000);
        $this->fechar($card, $this->evelyn);
        $id = (int)$this->comissoes($card)[0]['id'];
        (new FinancialTransaction())->markPaid($id);

        $this->board->updateCard($card, ['lead_outcome' => 'open', 'outcome_at' => null]);
        $this->assertSame('paid', $this->db->fetch("SELECT status FROM fin_transactions WHERE id = ?", [$id])['status']);
        $notes = array_column($this->board->getActivities($card), 'description');
        $this->assertNotEmpty(array_filter($notes, fn($n) => str_contains((string)$n, 'comissão já paga')));
        $this->assertTrue(empty(array_filter($notes, fn($n) => str_contains((string)$n, 'R$'))), 'Aviso não pode expor valores.');

        // Fechar de novo não paga duas vezes.
        $this->fechar($card, $this->evelyn);
        $this->assertCount(1, $this->comissoes($card));
    }

    public function testExcluirCardCancelaPendente(): void
    {
        $card = $this->card(2000);
        $this->fechar($card, $this->evelyn);
        $id = (int)$this->comissoes($card)[0]['id'];
        $this->board->deleteCard($card);
        $row = $this->db->fetch("SELECT status, crm_card_id FROM fin_transactions WHERE id = ?", [$id]);
        $this->assertSame('cancelled', $row['status']);
        $this->assertNull($row['crm_card_id']);
        $this->db->delete('fin_transactions', 'id = ?', [$id]);
    }

    public function testAceiteDaPropostaUsaOResponsavelDoCard(): void
    {
        $card = $this->card(2000);
        $stub = new class extends \Proposal {
            public function __construct() {}
            public function addEvent($proposalId, $userId, $type, $description = null) { return 0; }
        };
        $out = (new ProposalResponse($stub))->moveCardToWon(['id' => 1, 'card_id' => $card, 'revision_no' => 1]);
        $this->assertTrue($out['moved']);

        $row = $this->db->fetch("SELECT lead_outcome, converted_by, prospected_by FROM crm_cards WHERE id = ?", [$card]);
        $this->assertSame('converted', $row['lead_outcome']);
        $this->assertSame($this->evelyn, (int)$row['converted_by']);
        $rows = $this->comissoes($card);
        $this->assertCount(1, $rows);
        $this->assertSame($this->evelyn, (int)$rows[0]['user_id']);
    }
}
