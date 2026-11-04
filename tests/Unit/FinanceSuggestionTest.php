<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use FinanceRules;
use ProposalRules;
use AsaasRules;

/**
 * Plano sugerido a partir da proposta aceita, plano salvo de novo e payloads
 * novos do Asaas (change 02, tarefas 4.1, 4.3 e 4.4).
 */
final class FinanceSuggestionTest extends TestCase
{
    /** Pontual líquido e mensalidade do plano aceito, como o model calcula. */
    private function fromItems(array $items, array $discounts, ?string $plan, $pct = null, ?string $devEnd = null): array
    {
        $proposal = ['discounts' => json_encode($discounts), 'content' => json_encode(['discount_6' => '20', 'discount_12' => '30']), 'accepted_plan' => $plan];
        $data = ProposalRules::buildPresentationData($proposal, $items);
        $monthly = $data['commercial']['monthly']['net'];
        $months = null;
        foreach ($data['plan_options'] as $o) {
            if ($o['key'] === $plan) { $monthly = $o['monthly']; $months = $o['months']; }
        }
        return FinanceRules::suggestFromProposal($data['commercial']['once']['net'], $monthly, $pct, $devEnd, $months);
    }

    public function testExemploDaSpecPontualComDescontoEMensal(): void
    {
        $s = $this->fromItems(
            [['description' => 'Site', 'amount' => 10000, 'billing' => 'once'], ['description' => 'Suporte', 'amount' => 500, 'billing' => 'monthly']],
            [['scope' => 'once', 'type' => 'percent', 'value' => 10]],
            'plan_3', null, '2026-12-15'
        );
        $this->assertSame(9000.0, $s['once_total']);
        $this->assertSame(4500.0, $s['entry']);
        $this->assertSame(4500.0, $s['delivery']);
        $this->assertSame(500.0, $s['monthly']);
        $this->assertSame('2026-12-15', $s['delivery_due']);
        $this->assertSame(50.0, $s['entry_percent']);
    }

    public function testSoPontual(): void
    {
        $s = $this->fromItems([['description' => 'Site', 'amount' => 8000, 'billing' => 'once']], [], null, '30');
        $this->assertSame(2400.0, $s['entry']);
        $this->assertSame(5600.0, $s['delivery']);
        $this->assertSame(0.0, $s['monthly']);
        $this->assertNull($s['monthly_months']);
    }

    public function testSoMensal(): void
    {
        $s = $this->fromItems([['description' => 'Gestão', 'amount' => 1000, 'billing' => 'monthly']], [], 'plan_6');
        $this->assertSame(0.0, $s['entry']);
        $this->assertSame(0.0, $s['delivery']);
        $this->assertSame(800.0, $s['monthly']);
        $this->assertSame(6, $s['monthly_months']);
    }

    public function testPlano12MesesComDescontoNaMensalidade(): void
    {
        $s = $this->fromItems(
            [['description' => 'Site', 'amount' => 10000, 'billing' => 'once'], ['description' => 'Suporte', 'amount' => 500, 'billing' => 'monthly']],
            [], 'plan_12'
        );
        $this->assertSame(350.0, $s['monthly']);
        $this->assertSame(12, $s['monthly_months']);
    }

    public function testPercentualDeEntrada(): void
    {
        $this->assertSame(50.0, FinanceRules::entryPercent(null));
        $this->assertSame(50.0, FinanceRules::entryPercent(''));
        $this->assertSame(50.0, FinanceRules::entryPercent('abc'));
        $this->assertSame(40.0, FinanceRules::entryPercent('40'));
        $this->assertSame(100.0, FinanceRules::entryPercent('150'));
        $this->assertSame(0.0, FinanceRules::entryPercent('-5'));
    }

    public function testPlanoComMensalidadeGeraLinhaRecorrente(): void
    {
        $plan = FinanceRules::buildChargePlan(['total' => 9000, 'entry' => 4500, 'installments' => 1, 'first_installment_due' => '2026-12-15',
            'recurring_amount' => 500, 'recurring_start' => '2026-11-10', 'recurring_method' => 'boleto']);
        $this->assertSame(['entry', 'installment', 'recurring'], array_column($plan, 'kind'));
        $this->assertSame(500.0, $plan[2]['amount']);
        $this->assertSame('monthly', $plan[2]['recurring_cycle']);
        $this->assertSame('2026-11-10', $plan[2]['recurring_start']);
    }

    public function testSalvarDeNovoPreservaPagaEEmitida(): void
    {
        $existing = [
            ['id' => 1, 'kind' => 'entry', 'status' => 'paid'],
            ['id' => 2, 'kind' => 'installment', 'status' => 'pending', 'asaas_charge_id' => 'pay_x'],
            ['id' => 3, 'kind' => 'installment', 'status' => 'pending'],
            ['id' => 4, 'kind' => 'recurring', 'status' => 'pending', 'asaas_subscription_id' => 'sub_1'],
        ];
        $plan = [['kind' => 'entry'], ['kind' => 'installment'], ['kind' => 'installment'], ['kind' => 'recurring']];
        $m = FinanceRules::mergePlan($existing, $plan);
        $this->assertSame([3], $m['remove_ids']);
        $this->assertSame([1, 2, 4], array_column($m['keep'], 'id'));
        $this->assertSame(['installment', 'installment'], array_column($m['add'], 'kind'), 'não repete entrada paga nem a assinatura');
        $this->assertFalse(FinanceRules::isReplaceable(['status' => 'pending', 'issuing_at' => '2026-10-25 10:00:00']));
    }

    public function testPayloadsDoAsaas(): void
    {
        $c = AsaasRules::customerPayload('ACME', '12.345.678/0001-90', 'fin@acme.test', '+55 (11) 98888-7777', 'hd-project-9');
        $this->assertSame('12345678000190', $c['cpfCnpj']);
        $this->assertSame('11988887777', $c['mobilePhone']);
        $this->assertSame('fin@acme.test', $c['email']);
        $this->assertSame('hd-project-9', $c['externalReference']);
        $this->assertArrayNotHasKey('email', AsaasRules::customerPayload('X', '123', 'invalido'));

        $p = AsaasRules::chargePayload('cus_1', 4500, '2026-11-01', 'pix', 'Entrada', AsaasRules::chargeReference(42));
        $this->assertSame('hd-charge-42', $p['externalReference']);
        $this->assertSame('PIX', $p['billingType']);
        $this->assertSame(42, AsaasRules::chargeIdFromReference('hd-charge-42'));
        $this->assertNull(AsaasRules::chargeIdFromReference('outro-42'));
        $s = AsaasRules::subscriptionPayload('cus_1', 500, 'monthly', '2026-11-10', 'boleto', 'Mensalidade', AsaasRules::subscriptionReference(7));
        $this->assertSame('hd-sub-7', $s['externalReference']);
        $this->assertSame('MONTHLY', $s['cycle']);

        $this->assertSame('CPF inválido; Data no passado', AsaasRules::errorMessage(['errors' => [['code' => 'x', 'description' => 'CPF inválido'], ['description' => 'Data no passado']]], 400));
        $this->assertSame('Asaas HTTP 500', AsaasRules::errorMessage(null, 500));
        $this->assertSame(['id' => 'a'], AsaasRules::firstListed(['data' => [['id' => 'a']]]));
        $this->assertNull(AsaasRules::firstListed(['data' => []]));
    }
}
