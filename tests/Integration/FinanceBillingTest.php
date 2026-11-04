<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use FinanceBilling;
use FinanceProject;
use ContractSigning;
use OnboardingStarter;

/**
 * Financeiro da change 02 (tarefas 4.3, 4.5, 4.6, 4.7 e 5.1) com o Asaas
 * substituído por um falso: emissão com reserva e externalReference, cliente
 * reaproveitado, assinatura, webhook idempotente e onboarding automático.
 */
final class FinanceBillingTest extends TestCase
{
    use CommercialFixtures;

    private FinanceProject $proj;
    private FakeAsaas $asaas;
    private int $userId;

    protected function setUp(): void
    {
        $this->requireTestDb();
        $this->proj = new FinanceProject();
        $this->asaas = new FakeAsaas();
        $this->userId = $this->newUser('super_admin');
    }

    protected function tearDown(): void
    {
        foreach ($this->created as [$table, $id]) {
            if ($table === 'finance_projects') $this->trackWhere('onboardings', 'project_id = ?', [$id]);
            if ($table === 'contracts') $this->trackWhere('finance_projects', 'contract_id = ?', [$id]);
        }
        $this->cleanupCreated();
    }

    private function billing(): FinanceBilling
    {
        $fake = $this->asaas;
        return new FinanceBilling($this->proj, fn(array $account) => $fake->forAccount($account));
    }

    /** Projeto com contrato (CNPJ), conta sandbox e o plano entrada + entrega + mensalidade. */
    private function project(array $contractOver = []): array
    {
        $account = $this->insertRow('finance_accounts', ['name' => 'Conta teste', 'purpose' => 'parcela', 'asaas_token' => 'fake', 'sandbox' => 1, 'active' => 1]);
        $contract = $this->newContract($this->userId, array_merge(['status' => 'signed', 'client_document' => '12.345.678/0001-90'], $contractOver));
        $pid = $this->newFinanceProject($this->userId, ['contract_id' => $contract, 'total_value' => 9000]);
        $this->proj->replaceCharges($pid, \FinanceRules::buildChargePlan([
            'total' => 9000, 'entry' => 4500, 'entry_due' => '2026-11-05', 'entry_method' => 'pix',
            'installments' => 1, 'first_installment_due' => '2026-12-15', 'installment_method' => 'boleto',
            'recurring_amount' => 500, 'recurring_start' => '2026-11-10', 'recurring_method' => 'boleto',
        ]), ['entry' => $account, 'installment' => $account, 'recurring' => $account]);
        $byKind = [];
        foreach ($this->proj->getCharges($pid) as $c) $byKind[$c['kind']] = $c;
        return ['id' => $pid, 'account' => $account, 'contract' => $contract, 'charges' => $byKind];
    }

    public function testEmiteEntradaCriaClienteEGuardaLinks(): void
    {
        $p = $this->project();
        $r = $this->billing()->issue((int)$p['charges']['entry']['id'], $this->userId);
        $this->assertTrue($r['ok'], $r['error'] ?? '');
        $c = $r['charge'];
        $this->assertSame('pay_1', $c['asaas_charge_id']);
        $this->assertSame('https://sandbox.asaas.test/i/pay_1', $c['invoice_url']);
        $this->assertNull($c['issuing_at']);
        $this->assertSame('pending', $c['status']);
        $this->assertSame('12345678000190', $this->asaas->customers[0]['cpfCnpj']);
        $this->assertSame('hd-charge-' . $c['id'], $this->asaas->payments[0]['externalReference']);
        $this->assertSame('cus_1', $this->proj->findById($p['id'])['asaas_customer_id']);

        // Segunda parcela reaproveita o cliente do projeto.
        $this->assertTrue($this->billing()->issue((int)$p['charges']['installment']['id'])['ok']);
        $this->assertCount(1, $this->asaas->customers);
        $this->assertSame(0, $this->asaas->customerLookups - 1, 'só a primeira emissão procura o cliente');
    }

    public function testClienteJaExisteNoAsaas(): void
    {
        $this->asaas->existingCustomer = 'cus_antigo';
        $p = $this->project();
        $this->assertTrue($this->billing()->issue((int)$p['charges']['entry']['id'])['ok']);
        $this->assertCount(0, $this->asaas->customers);
        $this->assertSame('cus_antigo', $this->asaas->payments[0]['customer']);
    }

    public function testRecusaDoAsaasDeixaPendenteComAMensagem(): void
    {
        $p = $this->project();
        $this->asaas->rejectWith = 'A data de vencimento não pode ser anterior à data atual.';
        $id = (int)$p['charges']['entry']['id'];
        $r = $this->billing()->issue($id);
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('não pode ser anterior', $r['error']);
        $c = $this->proj->findChargeById($id);
        $this->assertNull($c['asaas_charge_id']);
        $this->assertNull($c['issuing_at'], 'reserva liberada para tentar de novo');
        $this->assertStringContainsString('não pode ser anterior', $c['issue_error']);
    }

    public function testCliqueDuploEnviaUmaCobranca(): void
    {
        $p = $this->project();
        $id = (int)$p['charges']['entry']['id'];
        // O primeiro clique ainda está no meio da emissão (reserva gravada).
        $this->db->update('finance_charges', ['issuing_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        $r = $this->billing()->issue($id);
        $this->assertSame(409, $r['code']);
        $this->assertCount(0, $this->asaas->payments);

        $this->db->update('finance_charges', ['issuing_at' => null], 'id = ?', [$id]);
        $this->assertTrue($this->billing()->issue($id)['ok']);
        $again = $this->billing()->issue($id);
        $this->assertSame(409, $again['code']);
        $this->assertCount(1, $this->asaas->payments);
    }

    public function testEstouroDeTempoRecuperaPelaReferencia(): void
    {
        $p = $this->project();
        $id = (int)$p['charges']['entry']['id'];
        // O Asaas criou a cobrança, mas a resposta não chegou.
        $this->asaas->timeoutAfterCreate = true;
        $r = $this->billing()->issue($id);
        $this->assertFalse($r['ok']);
        $this->assertCount(1, $this->asaas->payments);
        $this->assertNull($this->proj->findChargeById($id)['asaas_charge_id']);

        $this->asaas->timeoutAfterCreate = false;
        $r = $this->billing()->issue($id);
        $this->assertTrue($r['ok'], $r['error'] ?? '');
        $this->assertCount(1, $this->asaas->payments, 'recuperou a cobrança, não criou outra');
        $this->assertSame('pay_1', $r['charge']['asaas_charge_id']);
    }

    public function testReservaVelhaPodeSerRetomada(): void
    {
        $p = $this->project();
        $id = (int)$p['charges']['entry']['id'];
        $this->db->query("UPDATE finance_charges SET issuing_at = NOW() - INTERVAL 10 MINUTE WHERE id = ?", [$id]);
        $this->assertTrue($this->billing()->issue($id)['ok']);
    }

    public function testSemDocumentoNaoEmite(): void
    {
        $p = $this->project(['client_document' => null]);
        $r = $this->billing()->issue((int)$p['charges']['entry']['id']);
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('CPF/CNPJ', $r['error']);
        $this->assertCount(0, $this->asaas->payments);
    }

    public function testMensalidadeViraAssinatura(): void
    {
        $p = $this->project();
        $id = (int)$p['charges']['recurring']['id'];
        $r = $this->billing()->issue($id);
        $this->assertTrue($r['ok'], $r['error'] ?? '');
        $this->assertSame('sub_1', $this->proj->findById($p['id'])['asaas_subscription_id']);
        $sub = $this->asaas->subscriptions[0];
        $this->assertSame('MONTHLY', $sub['cycle']);
        $this->assertSame('2026-11-10', $sub['nextDueDate']);
        $this->assertSame(500.0, $sub['value']);
        $this->assertSame('hd-sub-' . $p['id'], $sub['externalReference']);
        $this->assertSame(409, $this->billing()->issue($id)['code']);
        $this->assertCount(1, $this->asaas->subscriptions);
    }

    public function testWebhookBaixaUmaVezECriaOnboarding(): void
    {
        $p = $this->project();
        $entry = $this->billing()->issue((int)$p['charges']['entry']['id'])['charge'];
        $payload = FakeAsaas::event('PAYMENT_RECEIVED', ['id' => $entry['asaas_charge_id'], 'value' => 4500, 'externalReference' => 'hd-charge-' . $entry['id']]);

        $a = $this->billing()->handleWebhook($payload);
        $b = $this->billing()->handleWebhook($payload);
        $this->assertTrue($a['unlocked']);
        $this->assertNotNull($a['onboarding_id']);
        $this->assertSame($a['onboarding_id'], $b['onboarding_id']);
        $this->assertSame('paid', $this->proj->findChargeById((int)$entry['id'])['status']);
        $this->assertSame('entry_paid', $this->proj->findById($p['id'])['status']);
        $this->assertSame(1, (int)$this->db->fetch("SELECT COUNT(*) n FROM finance_events WHERE project_id = ? AND event_type = 'paid'", [$p['id']])['n']);
        $this->assertSame(1, (int)$this->db->fetch("SELECT COUNT(*) n FROM onboardings WHERE project_id = ?", [$p['id']])['n']);
    }

    public function testPagamentoDeAssinaturaCriaMensalidadePaga(): void
    {
        $p = $this->project();
        $this->billing()->issue((int)$p['charges']['recurring']['id']);
        $payload = FakeAsaas::event('PAYMENT_CONFIRMED', ['id' => 'pay_sub_1', 'value' => 500, 'subscription' => 'sub_1', 'dueDate' => '2026-11-10']);
        $this->billing()->handleWebhook($payload);
        $this->billing()->handleWebhook($payload);
        $rows = $this->db->fetchAll("SELECT * FROM finance_charges WHERE project_id = ? AND asaas_charge_id = 'pay_sub_1'", [$p['id']]);
        $this->assertCount(1, $rows);
        $this->assertSame('paid', $rows[0]['status']);
        $this->assertSame('recurring', $rows[0]['kind']);
        $this->assertSame('Mensalidade 11/2026', $rows[0]['description']);
        $this->assertSame((int)$p['account'], (int)$rows[0]['account_id']);
    }

    public function testWebhookLigaCobrancaCujaRespostaNaoChegou(): void
    {
        $p = $this->project();
        $id = (int)$p['charges']['entry']['id'];
        $this->billing()->handleWebhook(FakeAsaas::event('PAYMENT_RECEIVED', ['id' => 'pay_perdida', 'value' => 4500, 'externalReference' => 'hd-charge-' . $id]));
        $c = $this->proj->findChargeById($id);
        $this->assertSame('pay_perdida', $c['asaas_charge_id']);
        $this->assertSame('paid', $c['status']);
    }

    public function testVencidaEEventoDesconhecido(): void
    {
        $p = $this->project();
        $entry = $this->billing()->issue((int)$p['charges']['entry']['id'])['charge'];
        $this->assertSame('ignore', $this->billing()->handleWebhook(FakeAsaas::event('PAYMENT_CREATED', ['id' => $entry['asaas_charge_id']]))['action']);
        $this->billing()->handleWebhook(FakeAsaas::event('PAYMENT_OVERDUE', ['id' => $entry['asaas_charge_id']]));
        $this->assertSame('overdue', $this->proj->findChargeById((int)$entry['id'])['status']);
    }

    public function testSalvarPlanoDeNovoDepoisDaEntradaPaga(): void
    {
        $p = $this->project();
        $entryId = (int)$p['charges']['entry']['id'];
        $this->proj->markChargePaid($entryId, $this->userId);
        $merge = $this->proj->replaceCharges($p['id'], \FinanceRules::buildChargePlan([
            'total' => 9000, 'entry' => 3000, 'installments' => 2, 'first_installment_due' => '2026-12-01',
        ]), ['entry' => $p['account'], 'installment' => $p['account']]);
        $charges = $this->proj->getCharges($p['id']);
        $this->assertSame('paid', $this->proj->findChargeById($entryId)['status']);
        $this->assertSame(1, count(array_filter($charges, fn($c) => $c['kind'] === 'entry')), 'a entrada paga não é duplicada');
        $this->assertSame(['3000.00', '3000.00'], array_column(array_values(array_filter($charges, fn($c) => $c['kind'] === 'installment')), 'amount'));
        $this->assertSame(1, $merge['kept']);
    }

    public function testFinanceiroUnicoQuandoOContratoJaTemProjeto(): void
    {
        $contract = $this->newContract($this->userId, ['status' => 'signed']);
        $existing = $this->newFinanceProject($this->userId, ['contract_id' => $contract]);
        $r = (new ContractSigning())->onContractSigned($contract, ContractSigning::ORIGIN_UPLOAD, $this->userId);
        $this->assertSame($existing, $r['finance_id']);
        $this->assertSame(1, (int)$this->db->fetch('SELECT COUNT(*) n FROM finance_projects WHERE contract_id = ?', [$contract])['n']);
    }

    public function testOnboardingManualExistenteNaoDuplica(): void
    {
        $p = $this->project();
        $this->proj->markChargePaid((int)$p['charges']['entry']['id']);
        $manual = $this->insertRow('onboardings', ['project_id' => $p['id'], 'title' => 'Manual', 'status' => 'blocked', 'created_by' => $this->userId]);
        $r = (new OnboardingStarter($this->proj))->ensureForFinanceProject($p['id']);
        $this->assertSame(['id' => $manual, 'created' => false], $r);
        $this->assertNull((new OnboardingStarter($this->proj))->ensureForFinanceProject($this->newFinanceProject($this->userId)), 'sem entrada paga não cria');
    }
}

/** Asaas falso: guarda o que recebeu e simula recusa e estouro de tempo. */
final class FakeAsaas
{
    public array $customers = [];
    public array $payments = [];
    public array $subscriptions = [];
    public ?string $existingCustomer = null;
    public ?string $rejectWith = null;
    public bool $timeoutAfterCreate = false;
    public int $customerLookups = 0;
    private bool $sandbox = true;

    public static function event(string $event, array $payment): array
    {
        return ['id' => 'evt_' . uniqid(), 'event' => $event, 'payment' => $payment + ['object' => 'payment', 'status' => 'RECEIVED']];
    }

    public function forAccount(array $account): self
    {
        $this->sandbox = (int)$account['sandbox'] === 1;
        return $this;
    }

    public function isConfigured(): bool { return true; }
    public function isSandbox(): bool { return $this->sandbox; }

    public function findCustomerByDocument(string $doc): array
    {
        $this->customerLookups++;
        $list = $this->existingCustomer ? [['id' => $this->existingCustomer]] : array_values(array_filter($this->customers, fn($c) => $c['cpfCnpj'] === preg_replace('/\D/', '', $doc)));
        return ['success' => true, 'data' => ['data' => $list]];
    }

    public function createCustomer(string $name, string $doc, ?string $email = null, ?string $phone = null, ?string $ref = null): array
    {
        $c = \AsaasRules::customerPayload($name, $doc, $email, $phone, $ref) + ['id' => 'cus_' . (count($this->customers) + 1)];
        $this->customers[] = $c;
        return ['success' => true, 'data' => $c];
    }

    public function findPaymentByReference(string $ref): array
    {
        return ['success' => true, 'data' => ['data' => array_values(array_filter($this->payments, fn($p) => $p['externalReference'] === $ref))]];
    }

    public function createCharge(string $customer, float $value, ?string $due, ?string $method, ?string $desc, ?string $ref = null): array
    {
        if ($this->rejectWith) return ['success' => false, 'http' => 400, 'error' => $this->rejectWith];
        $id = 'pay_' . (count($this->payments) + 1);
        $p = \AsaasRules::chargePayload($customer, $value, $due, $method, $desc, $ref) + ['id' => $id, 'invoiceUrl' => 'https://sandbox.asaas.test/i/' . $id];
        $this->payments[] = $p;
        if ($this->timeoutAfterCreate) return ['success' => false, 'error' => 'Falha de conexão com o Asaas: Operation timed out'];
        return ['success' => true, 'data' => $p];
    }

    public function findSubscriptionByReference(string $ref): array
    {
        return ['success' => true, 'data' => ['data' => array_values(array_filter($this->subscriptions, fn($s) => $s['externalReference'] === $ref))]];
    }

    public function createSubscription(string $customer, float $value, string $cycle, ?string $next, ?string $method, ?string $desc, ?string $ref = null): array
    {
        $s = \AsaasRules::subscriptionPayload($customer, $value, $cycle, $next, $method, $desc, $ref) + ['id' => 'sub_' . (count($this->subscriptions) + 1)];
        $this->subscriptions[] = $s;
        return ['success' => true, 'data' => $s];
    }
}
