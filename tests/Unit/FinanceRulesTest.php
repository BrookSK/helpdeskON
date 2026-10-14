<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use FinanceRules;

/**
 * Testes unitários das regras puras do Financeiro (Fase 5).
 * Cobre rateio de parcelas, plano de cobranças (exemplo da reunião), seleção de
 * conta Asaas e a regra de bloqueio do onboarding (entrada paga).
 */
final class FinanceRulesTest extends TestCase
{
    public function testMoneyAceitaNumeroEStringBR(): void
    {
        $this->assertSame(40000.0, FinanceRules::money(40000));
        $this->assertSame(40000.0, FinanceRules::money('R$ 40.000,00'));
        $this->assertSame(0.0, FinanceRules::money(''));
        $this->assertSame(0.0, FinanceRules::money(-5)); // nunca negativo
    }

    public function testSplitInstallmentsFechaCentavos(): void
    {
        // 20.000 em 5 => 4000 cada.
        $p = FinanceRules::splitInstallments(20000, 5);
        $this->assertCount(5, $p);
        $this->assertSame(20000.0, round(array_sum($p), 2));
        // 100 em 3 => soma exata 100 (ajuste de centavo na última).
        $p2 = FinanceRules::splitInstallments(100, 3);
        $this->assertSame(100.0, round(array_sum($p2), 2));
        $this->assertSame(33.33, $p2[0]);
        $this->assertSame(33.34, $p2[2]);
    }

    public function testBuildChargePlanExemploDaReuniao(): void
    {
        // Projeto 40k, entrada 20k p/ amanhã, restante 20k em 5x a partir de +30d.
        $plan = FinanceRules::buildChargePlan([
            'total' => 40000, 'entry' => 20000,
            'entry_due' => '2026-10-04', 'entry_method' => 'pix',
            'installments' => 5, 'first_installment_due' => '2026-11-03',
            'installment_interval_days' => 30, 'installment_method' => 'boleto',
        ]);
        // 1 entrada + 5 parcelas = 6 cobranças.
        $this->assertCount(6, $plan);
        $this->assertSame('entry', $plan[0]['kind']);
        $this->assertSame(20000.0, $plan[0]['amount']);
        $this->assertSame('pix', $plan[0]['method']);
        $this->assertSame('2026-10-04', $plan[0]['due_date']);
        // Parcelas: 5 de 4000, método boleto, datas espaçadas 30 dias.
        $this->assertSame('installment', $plan[1]['kind']);
        $this->assertSame(4000.0, $plan[1]['amount']);
        $this->assertSame(1, $plan[1]['installment_no']);
        $this->assertSame(5, $plan[1]['installment_total']);
        $this->assertSame('2026-11-03', $plan[1]['due_date']);
        $this->assertSame('2026-12-03', $plan[2]['due_date']);
        // Soma total = 40.000.
        $soma = array_sum(array_column($plan, 'amount'));
        $this->assertSame(40000.0, round($soma, 2));
    }

    public function testBuildChargePlanSemEntrada(): void
    {
        $plan = FinanceRules::buildChargePlan(['total' => 1000, 'entry' => 0, 'installments' => 2]);
        $this->assertCount(2, $plan);
        $this->assertSame('installment', $plan[0]['kind']);
    }

    public function testBuildChargePlanEntradaIntegral(): void
    {
        // Entrada >= total: só a entrada, sem parcelas.
        $plan = FinanceRules::buildChargePlan(['total' => 1000, 'entry' => 1000, 'installments' => 3]);
        $this->assertCount(1, $plan);
        $this->assertSame('entry', $plan[0]['kind']);
        $this->assertSame(1000.0, $plan[0]['amount']);
    }

    public function testPickAccountPorFinalidade(): void
    {
        $accounts = [
            ['id' => 1, 'purpose' => 'parcela', 'active' => 1],
            ['id' => 2, 'purpose' => 'recorrente', 'active' => 1],
            ['id' => 3, 'purpose' => 'outra', 'active' => 0], // inativa
        ];
        $this->assertSame(2, FinanceRules::pickAccount($accounts, 'recorrente'));
        $this->assertSame(1, FinanceRules::pickAccount($accounts, 'parcela'));
        // Finalidade sem conta própria -> primeira ativa.
        $this->assertSame(1, FinanceRules::pickAccount($accounts, 'inexistente'));
        // Sem contas -> null.
        $this->assertNull(FinanceRules::pickAccount([], 'parcela'));
    }

    public function testEntryIsPaidBloqueiaOnboarding(): void
    {
        // Tem entrada pendente -> bloqueado.
        $charges = [
            ['kind' => 'entry', 'status' => 'pending'],
            ['kind' => 'installment', 'status' => 'paid'],
        ];
        $this->assertFalse(FinanceRules::entryIsPaid($charges));
        // Entrada paga -> libera.
        $charges[0]['status'] = 'paid';
        $this->assertTrue(FinanceRules::entryIsPaid($charges));
    }

    public function testEntryIsPaidSemEntradaExigeAlgumaPaga(): void
    {
        // Sem entrada, nenhuma paga -> bloqueado.
        $this->assertFalse(FinanceRules::entryIsPaid([['kind' => 'recurring', 'status' => 'pending']]));
        // Sem entrada, uma paga -> libera.
        $this->assertTrue(FinanceRules::entryIsPaid([['kind' => 'recurring', 'status' => 'paid']]));
        // Lista vazia -> bloqueado.
        $this->assertFalse(FinanceRules::entryIsPaid([]));
    }

    public function testNormalizeMethodECycle(): void
    {
        $this->assertSame('pix', FinanceRules::normalizeMethod('pix'));
        $this->assertNull(FinanceRules::normalizeMethod('dinheiro'));
        $this->assertSame('monthly', FinanceRules::normalizeCycle('monthly'));
        $this->assertNull(FinanceRules::normalizeCycle('weekly'));
    }
}
