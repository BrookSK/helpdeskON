<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use FinancialCard;
use FinancialTransaction;
use CardInvoiceRules;
use Database;

/**
 * Editar o fechamento do cartão recalcula a fatura das compras EM ABERTO
 * (caso real: cartão XP cadastrado fechando dia 14, quando fecha dia 24).
 * As pagas não mudam. Banco helpdesk_on_test.
 */
final class FinancialCardRecalcTest extends TestCase
{
    private Database $db;
    private FinancialTransaction $tx;
    private FinancialCard $cards;
    private int $userId;
    private int $cardId;

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste não configurado.');
        }
        $this->db = Database::getInstance();
        $col = $this->db->fetch("SELECT COUNT(*) AS c FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fin_transactions' AND COLUMN_NAME = 'purchase_date'");
        if ((int)($col['c'] ?? 0) === 0) {
            $this->markTestSkipped('Banco de teste sem fin_transactions.purchase_date (migration 168).');
        }
        $this->tx = new FinancialTransaction();
        $this->cards = new FinancialCard();
        $u = uniqid();
        $this->userId = (int) $this->db->insert('users', [
            'name' => "FinCard {$u}", 'email' => "fc_{$u}@example.test",
            'password' => password_hash('x', PASSWORD_BCRYPT), 'role' => 'super_admin',
        ]);
        $this->cardId = $this->cards->create([
            'name' => 'XP ' . $u, 'limit' => 5710.98, 'closing_day' => 14, 'due_day' => 1,
            'active' => 1, 'created_by' => $this->userId,
        ]);
    }

    protected function tearDown(): void
    {
        try { $this->db->delete('fin_transactions', 'created_by = ?', [$this->userId]); } catch (\Throwable $e) {}
        try { $this->db->delete('fin_credit_cards', 'id = ?', [$this->cardId]); } catch (\Throwable $e) {}
        try { $this->db->delete('users', 'id = ?', [$this->userId]); } catch (\Throwable $e) {}
    }

    /** Compra no cartão com o ciclo antigo (fecha 14, vence 1). */
    private function compra(string $purchase, array $extra = []): int
    {
        $installments = (int)($extra['installments'] ?? 1);
        $first = CardInvoiceRules::invoiceFor($purchase, 14, 1)['due_date'];
        $dates = $installments > 1 ? CardInvoiceRules::installmentDueDates($purchase, 14, 1, $installments) : null;
        return $this->tx->createWithInstallments($extra + [
            'kind' => 'gasto', 'direction' => 'out', 'amount' => 300, 'installments' => 1,
            'status' => 'pending', 'purchase_date' => $purchase, 'due_date' => $first,
            'card_id' => $this->cardId, 'source' => 'manual', 'created_by' => $this->userId,
        ], $dates);
    }

    private function due(int $id): string
    {
        return substr((string)$this->tx->findById($id)['due_date'], 0, 10);
    }

    public function testCompraEntreOsDias14E23VoltaUmaFatura(): void
    {
        $dia20 = $this->compra('2026-11-20');
        $dia10 = $this->compra('2026-11-10');
        $dia25 = $this->compra('2026-11-25');
        $this->assertSame('2027-01-01', $this->due($dia20), 'com fechamento 14 caiu na fatura de janeiro');

        $r = $this->tx->recalcCardDueDates($this->cardId, 24, 1);

        $this->assertSame('2026-12-01', $this->due($dia20), 'fecha 24/11, vence 01/12');
        $this->assertSame('2026-12-01', $this->due($dia10), 'antes do dia 14: não muda');
        $this->assertSame('2027-01-01', $this->due($dia25), 'depois do dia 24: não muda');
        $this->assertSame(['moved' => 1, 'unchanged' => 2, 'skipped' => 0], $r);
    }

    public function testCompraPagaNaoMuda(): void
    {
        $paga = $this->compra('2026-11-20', ['status' => 'paid', 'paid_at' => '2026-11-21 10:00:00']);
        $r = $this->tx->recalcCardDueDates($this->cardId, 24, 1);
        $this->assertSame('2027-01-01', $this->due($paga));
        $this->assertSame(0, $r['moved']);
    }

    public function testParceladaMudaSoAsParcelasEmAberto(): void
    {
        $id = $this->compra('2026-11-20', ['amount' => 900, 'installments' => 3]);
        $insts = $this->db->fetchAll('SELECT id, installment_no, due_date FROM fin_installments WHERE transaction_id = ? ORDER BY installment_no', [$id]);
        $this->assertSame(['2027-01-01', '2027-02-01', '2027-03-01'], array_map(fn($i) => substr($i['due_date'], 0, 10), $insts));
        // A 1ª parcela já foi paga (direto no banco: o banco de teste local
        // ainda não tem a coluna affects_balance que o payInstallment grava).
        $this->db->update('fin_installments', ['status' => 'paid', 'paid_at' => '2026-12-30 10:00:00'], 'id = ?', [(int)$insts[0]['id']]);

        $r = $this->tx->recalcCardDueDates($this->cardId, 24, 1);

        $after = $this->db->fetchAll('SELECT installment_no, due_date, status FROM fin_installments WHERE transaction_id = ? ORDER BY installment_no', [$id]);
        $this->assertSame('2027-01-01', substr($after[0]['due_date'], 0, 10), 'paga fica onde está');
        $this->assertSame('2027-01-01', substr($after[1]['due_date'], 0, 10));
        $this->assertSame('2027-02-01', substr($after[2]['due_date'], 0, 10));
        $this->assertSame(2, $r['moved']);
        $this->assertSame('2026-12-01', $this->due($id), 'vencimento do lançamento = 1ª parcela');
    }

    public function testCompraSemDataDaCompraFicaComoEstava(): void
    {
        $id = $this->compra('2026-11-20');
        $this->db->update('fin_transactions', ['purchase_date' => null], 'id = ?', [$id]);
        $r = $this->tx->recalcCardDueDates($this->cardId, 24, 1);
        $this->assertSame('2027-01-01', $this->due($id));
        $this->assertSame(1, $r['skipped']);
    }

    public function testOutroCartaoNaoEAfetado(): void
    {
        $outro = $this->cards->create(['name' => 'Outro ' . uniqid(), 'limit' => 0, 'closing_day' => 14, 'due_day' => 1, 'active' => 1, 'created_by' => $this->userId]);
        try {
            $id = $this->tx->createWithInstallments([
                'kind' => 'gasto', 'direction' => 'out', 'amount' => 50, 'installments' => 1, 'status' => 'pending',
                'purchase_date' => '2026-11-20', 'due_date' => '2027-01-01', 'card_id' => $outro,
                'source' => 'manual', 'created_by' => $this->userId,
            ]);
            $this->tx->recalcCardDueDates($this->cardId, 24, 1);
            $this->assertSame('2027-01-01', $this->due($id));
        } finally {
            $this->db->delete('fin_transactions', 'card_id = ?', [$outro]);
            $this->db->delete('fin_credit_cards', 'id = ?', [$outro]);
        }
    }

    public function testEdicaoGravaOsNovosDias(): void
    {
        $this->cards->update($this->cardId, ['closing_day' => 24, 'due_day' => 1]);
        $c = $this->cards->findById($this->cardId);
        $this->assertSame(24, (int)$c['closing_day']);
        $this->assertSame(1, (int)$c['due_day']);
    }
}
