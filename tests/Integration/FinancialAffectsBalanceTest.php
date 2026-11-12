<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use FinancialBankAccount;
use FinancialTransaction;
use FinancialAccountRules;
use Database;

/**
 * Pagamento que mexe ou não no saldo (migration 171), contra helpdesk_on_test:
 *  - lançado já pago "sem mexer no saldo" não altera o saldo da conta;
 *  - pagar depois pelo botão mexe no saldo;
 *  - parcelado lançado como pago: só a 1ª parcela nasce paga;
 *  - alternar o efeito no saldo de um pagamento.
 */
final class FinancialAffectsBalanceTest extends TestCase
{
    private Database $db;
    private FinancialTransaction $tx;
    private int $userId;
    private int $accountId;

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste não configurado.');
        }
        $this->db = Database::getInstance();
        $this->tx = new FinancialTransaction();
        $u = uniqid();
        $this->userId = (int) $this->db->insert('users', [
            'name' => "FinSaldo {$u}", 'email' => "fs_{$u}@example.test",
            'password' => password_hash('x', PASSWORD_BCRYPT), 'role' => 'super_admin',
        ]);
        $this->accountId = (new FinancialBankAccount())->create([
            'name' => 'Conta ' . $u, 'opening_balance' => 1000, 'active' => 1, 'created_by' => $this->userId,
        ]);
    }

    protected function tearDown(): void
    {
        try { $this->db->delete('fin_transactions', 'created_by = ?', [$this->userId]); } catch (\Throwable $e) {}
        try { $this->db->delete('fin_bank_accounts', 'id = ?', [$this->accountId]); } catch (\Throwable $e) {}
        try { $this->db->delete('users', 'id = ?', [$this->userId]); } catch (\Throwable $e) {}
    }

    private function saldo(): float
    {
        return FinancialAccountRules::accountBalance(1000.0, $this->tx->movementsByAccount()[$this->accountId] ?? []);
    }

    private function lanca(array $extra, ?int $firstPaid = null): int
    {
        return $this->tx->createWithInstallments($extra + [
            'kind' => 'ganho', 'direction' => 'in', 'amount' => 300, 'installments' => 1,
            'status' => 'pending', 'due_date' => '2026-11-02', 'account_id' => $this->accountId,
            'source' => 'manual', 'created_by' => $this->userId,
        ], null, $firstPaid);
    }

    public function testJaPagoSemMexerNoSaldo(): void
    {
        $this->lanca(['status' => 'paid', 'paid_at' => '2026-11-02 10:00:00', 'affects_balance' => 0]);
        $this->assertSame(1000.0, $this->saldo());
    }

    public function testJaPagoMexendoNoSaldo(): void
    {
        $this->lanca(['status' => 'paid', 'paid_at' => '2026-11-02 10:00:00', 'affects_balance' => 1]);
        $this->assertSame(1300.0, $this->saldo());
    }

    public function testPagarDepoisMexeNoSaldo(): void
    {
        $id = $this->lanca([]);
        $this->assertSame(1000.0, $this->saldo());
        $this->tx->markPaid($id);
        $this->assertSame(1300.0, $this->saldo());
        $this->assertSame(1, (int)$this->tx->findById($id)['affects_balance']);
    }

    public function testParceladoJaPagoSoAPrimeiraParcela(): void
    {
        $id = $this->lanca(['amount' => 900, 'installments' => 3], 0);
        $status = array_column($this->tx->installmentProgress($id), 'status');
        $this->assertSame(['paid', 'pending', 'pending'], $status);
        $this->assertSame('pending', $this->tx->findById($id)['status']);
        $this->assertSame(1000.0, $this->saldo()); // 1ª paga sem mexer

        // Pagar a 2ª depois mexe.
        $this->tx->markPaid($id);
        $this->assertSame(1300.0, $this->saldo());
    }

    public function testAlternarEfeitoNoSaldo(): void
    {
        $id = $this->lanca(['status' => 'paid', 'paid_at' => '2026-11-02 10:00:00', 'affects_balance' => 0]);
        $this->assertTrue($this->tx->toggleAffectsBalance($id));
        $this->assertSame(1300.0, $this->saldo());
        $this->assertFalse($this->tx->toggleAffectsBalance($id));
        $this->assertSame(1000.0, $this->saldo());
    }
}
