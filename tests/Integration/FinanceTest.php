<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use FinanceProject;
use FinanceAccount;
use Database;

/**
 * Testes de integração do Financeiro (Fase 5) contra helpdesk_on_test.
 * Cobre: projeto + plano de cobranças, seleção de conta, e a regra de que a
 * entrada paga DESTRAVA o onboarding.
 */
final class FinanceTest extends TestCase
{
    private Database $db;
    private FinanceProject $proj;
    private FinanceAccount $acc;
    private int $userId;
    private array $projectIds = [];
    private array $accountIds = [];

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste não configurado.');
        }
        $this->db = Database::getInstance();
        $this->proj = new FinanceProject();
        $this->acc = new FinanceAccount();
        $u = uniqid();
        $this->userId = (int) $this->db->insert('users', [
            'name' => "Fin {$u}", 'email' => "f_{$u}@example.test",
            'password' => password_hash('x', PASSWORD_BCRYPT), 'role' => 'super_admin',
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->projectIds as $id) {
            try { $this->db->delete('finance_charges', 'project_id = ?', [$id]); } catch (\Throwable $e) {}
            try { $this->db->delete('finance_projects', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
        foreach ($this->accountIds as $id) {
            try { $this->db->delete('finance_accounts', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
        try { $this->db->delete('users', 'id = ?', [$this->userId]); } catch (\Throwable $e) {}
    }

    private function novoProjeto(float $total = 40000): int
    {
        $id = $this->proj->create([
            'title' => 'Projeto X', 'total_value' => $total, 'status' => 'open', 'created_by' => $this->userId,
        ]);
        $this->projectIds[] = $id;
        return $id;
    }

    public function testPlanoDeCobrancasPersiste(): void
    {
        $id = $this->novoProjeto(40000);
        $plan = \FinanceRules::buildChargePlan([
            'total' => 40000, 'entry' => 20000, 'entry_due' => '2026-10-04', 'entry_method' => 'pix',
            'installments' => 5, 'first_installment_due' => '2026-11-03', 'installment_interval_days' => 30, 'installment_method' => 'boleto',
        ]);
        $this->proj->replaceCharges($id, $plan);
        $charges = $this->proj->getCharges($id);
        $this->assertCount(6, $charges);
        $this->assertSame('entry', $charges[0]['kind']);
        $this->assertSame(20000.0, (float)$charges[0]['amount']);
        // Soma bate com o total.
        $soma = array_sum(array_map(fn($c) => (float)$c['amount'], $charges));
        $this->assertSame(40000.0, round($soma, 2));
    }

    public function testEntradaPagaDestravaOnboarding(): void
    {
        $id = $this->novoProjeto(1000);
        $this->proj->replaceCharges($id, \FinanceRules::buildChargePlan([
            'total' => 1000, 'entry' => 500, 'installments' => 1,
        ]));
        // Antes de pagar: bloqueado.
        $this->assertFalse($this->proj->canStartOnboarding($id));
        $this->assertSame('open', $this->proj->findById($id)['status']);

        // Paga a parcela (não a entrada): ainda bloqueado.
        $charges = $this->proj->getCharges($id);
        $entry = null; $inst = null;
        foreach ($charges as $c) { if ($c['kind']==='entry') $entry=$c; else $inst=$c; }
        $this->proj->markChargePaid((int)$inst['id']);
        $this->assertFalse($this->proj->canStartOnboarding($id));

        // Paga a ENTRADA: destrava e projeto vira entry_paid.
        $destravou = $this->proj->markChargePaid((int)$entry['id']);
        $this->assertTrue($destravou);
        $this->assertTrue($this->proj->canStartOnboarding($id));
        $this->assertSame('entry_paid', $this->proj->findById($id)['status']);
    }

    public function testContaAsaasCrudEToggle(): void
    {
        $aid = $this->acc->create(['name' => 'Parcelas', 'purpose' => 'parcela', 'asaas_token' => 'tok', 'sandbox' => 1, 'active' => 1]);
        $this->accountIds[] = $aid;
        $this->assertSame('parcela', $this->acc->findById($aid)['purpose']);
        $this->acc->toggleActive($aid);
        $this->assertSame(0, (int)$this->acc->findById($aid)['active']);
    }

    public function testFindChargeByAsaasId(): void
    {
        $id = $this->novoProjeto(500);
        $this->proj->addCharge([
            'project_id' => $id, 'kind' => 'entry', 'amount' => 500, 'status' => 'pending',
            'asaas_charge_id' => 'pay_abc123',
        ]);
        $c = $this->proj->findChargeByAsaasId('pay_abc123');
        $this->assertNotNull($c);
        $this->assertSame($id, (int)$c['project_id']);
    }
}
