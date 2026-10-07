<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use PayableRules;

/**
 * Testes unitários das regras puras do contas a pagar dos prestadores.
 * Cobre: mapeamento pay_type->kind, parsing monetário, normalização de parcelas,
 * e a geração do plano para cada tipo (mensal/hora/projeto), incluindo o
 * fechamento exato da soma nas parcelas.
 */
final class PayableRulesTest extends TestCase
{
    public function testKindFromPayType(): void
    {
        $this->assertSame('mensal', PayableRules::kindFromPayType('mensal'));
        $this->assertSame('hora', PayableRules::kindFromPayType('hora'));
        $this->assertSame('parcela', PayableRules::kindFromPayType('projeto'));
        $this->assertNull(PayableRules::kindFromPayType('qualquer'));
        $this->assertNull(PayableRules::kindFromPayType(null));
    }

    public function testMoneyAceitaNumeroEStringBR(): void
    {
        $this->assertSame(1500.0, PayableRules::money(1500));
        $this->assertSame(1500.5, PayableRules::money(1500.5));
        $this->assertSame(1234.56, PayableRules::money('R$ 1.234,56'));
        $this->assertSame(1234.56, PayableRules::money('1234.56'));
        $this->assertSame(0.0, PayableRules::money(null));
        $this->assertSame(0.0, PayableRules::money(''));
        // Nunca negativo.
        $this->assertSame(0.0, PayableRules::money(-50));
    }

    public function testNormalizeInstallments(): void
    {
        $this->assertSame(1, PayableRules::normalizeInstallments(0));
        $this->assertSame(1, PayableRules::normalizeInstallments(-3));
        $this->assertSame(5, PayableRules::normalizeInstallments(5));
        $this->assertSame(PayableRules::MAX_INSTALLMENTS, PayableRules::normalizeInstallments(999));
    }

    public function testPlanoMensalGeraRecorrente(): void
    {
        $plan = PayableRules::buildPlanForProvider(['name' => 'Dev X', 'pay_type' => 'mensal', 'pay_amount' => 4000]);
        $this->assertCount(1, $plan);
        $this->assertSame('mensal', $plan[0]['kind']);
        $this->assertSame('monthly', $plan[0]['recurring_cycle']);
        $this->assertSame(4000.0, $plan[0]['amount']);
    }

    public function testPlanoHoraGeraLancamentoBaseSemValor(): void
    {
        // Por hora: valor fica a liquidar no fechamento do período (amount 0).
        $plan = PayableRules::buildPlanForProvider(['name' => 'Dev Y', 'pay_type' => 'hora', 'pay_amount' => 120]);
        $this->assertCount(1, $plan);
        $this->assertSame('hora', $plan[0]['kind']);
        $this->assertSame(0.0, $plan[0]['amount']);
        $this->assertNull($plan[0]['recurring_cycle']);
    }

    public function testPlanoProjetoDivideEmParcelasQueSomamOTotal(): void
    {
        // 10.000 em 3 parcelas: 3333.33 + 3333.33 + 3333.34 = 10000.00
        $plan = PayableRules::buildPlanForProvider(
            ['name' => 'Dev Z', 'pay_type' => 'projeto', 'pay_amount' => 10000],
            3
        );
        $this->assertCount(3, $plan);
        foreach ($plan as $i => $p) {
            $this->assertSame('parcela', $p['kind']);
            $this->assertSame(3, $p['installment_total']);
            $this->assertSame($i + 1, $p['installment_no']);
        }
        $this->assertSame(10000.0, PayableRules::planTotal($plan));
        // A última parcela absorve o arredondamento.
        $this->assertSame(3333.34, $plan[2]['amount']);
    }

    public function testPlanoProjetoParcelaUnicaPorPadrao(): void
    {
        $plan = PayableRules::buildPlanForProvider(['name' => 'Dev W', 'pay_type' => 'projeto', 'pay_amount' => 5000]);
        $this->assertCount(1, $plan);
        $this->assertSame(5000.0, $plan[0]['amount']);
        $this->assertSame(1, $plan[0]['installment_total']);
    }

    public function testPayTypeInvalidoRetornaPlanoVazio(): void
    {
        $this->assertSame([], PayableRules::buildPlanForProvider(['name' => 'X', 'pay_type' => null, 'pay_amount' => 100]));
        $this->assertSame([], PayableRules::buildPlanForProvider(['name' => 'X', 'pay_amount' => 100]));
    }

    public function testNormalizeKindEStatus(): void
    {
        $this->assertSame('mensal', PayableRules::normalizeKind('mensal'));
        $this->assertNull(PayableRules::normalizeKind('xpto'));
        $this->assertSame('pending', PayableRules::normalizeStatus('xpto'));
        $this->assertSame('paid', PayableRules::normalizeStatus('paid'));
    }
}
