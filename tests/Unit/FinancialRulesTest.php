<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use FinancialRules;

/**
 * Testes unitários das regras puras do Controle Financeiro (super_admin).
 * Cobre validação de lançamentos, parcelamento no cartão, resumo do painel,
 * mapeamento de status do Asaas e o guard de super admin do Permissions.
 */
final class FinancialRulesTest extends TestCase
{
    public function testValidateTransactionGanhoOk(): void
    {
        $r = FinancialRules::validateTransaction([
            'kind' => 'ganho', 'amount' => 'R$ 1.500,00',
            'category' => 'Vendas', 'description' => 'Fechamento cliente X',
            'installments' => 1, 'due_date' => '2026-10-20',
        ]);
        $this->assertTrue($r['ok']);
        $this->assertSame('in', $r['data']['direction']);
        $this->assertSame(1500.0, $r['data']['amount']);
        $this->assertSame('pending', $r['data']['status']);
    }

    public function testValidateTransactionParceladoExigeCartao(): void
    {
        $r = FinancialRules::validateTransaction([
            'kind' => 'gasto', 'amount' => 1200, 'installments' => 3,
            'due_date' => '2026-10-20', 'card_id' => null,
        ]);
        $this->assertFalse($r['ok']);

        $ok = FinancialRules::validateTransaction([
            'kind' => 'gasto', 'amount' => 1200, 'installments' => 3,
            'due_date' => '2026-10-20', 'card_id' => 2, 'status' => 'paid',
        ]);
        $this->assertTrue($ok['ok']);
        $this->assertSame('paid', $ok['data']['status']);
        $this->assertSame(2, $ok['data']['card_id']);
    }

    public function testParcelamentoSomenteEmGasto(): void
    {
        $r = FinancialRules::validateTransaction([
            'kind' => 'salario', 'amount' => 5000, 'installments' => 2,
            'due_date' => '2026-10-20',
        ]);
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('Parcelamento', implode(' ', $r['errors']));
    }

    public function testValorZeroOuTipoInvalidoRejeita(): void
    {
        $r = FinancialRules::validateTransaction(['kind' => 'xxxx', 'amount' => 0]);
        $this->assertFalse($r['ok']);
    }

    public function testEmprestimoAceitaDuasDirecoes(): void
    {
        $tomado = FinancialRules::validateTransaction([
            'kind' => 'emprestimo', 'amount' => 10000, 'direction' => 'in',
            'due_date' => '2026-10-20',
        ]);
        $this->assertSame('in', $tomado['data']['direction']);

        // Sem direção explícita, padrão é saída (tomado).
        $padrao = FinancialRules::validateTransaction([
            'kind' => 'emprestimo', 'amount' => 10000, 'due_date' => '2026-10-20',
        ]);
        $this->assertSame('out', $padrao['data']['direction']);
    }

    public function testBuildInstallmentsMensaisSomamTotal(): void
    {
        $plan = FinancialRules::buildInstallments(300, 3, '2026-10-15');
        $this->assertCount(3, $plan);
        $this->assertSame(300.0, round(array_sum(array_column($plan, 'amount')), 2));
        $this->assertSame('2026-10-15', $plan[0]['due_date']);
        $this->assertSame('2026-11-15', $plan[1]['due_date']);
        $this->assertSame('2026-12-15', $plan[2]['due_date']);
        $this->assertSame(3, $plan[2]['installment_total']);
    }

    public function testSummaryConsolidado(): void
    {
        $rows = [
            ['kind' => 'ganho',      'direction' => 'in',  'amount' => 1000, 'status' => 'paid'],
            ['kind' => 'ganho',      'direction' => 'in',  'amount' => 500,  'status' => 'pending'],
            ['kind' => 'gasto',      'direction' => 'out', 'amount' => 300,  'status' => 'paid'],
            ['kind' => 'salario',    'direction' => 'out', 'amount' => 700,  'status' => 'pending'],
            ['kind' => 'emprestimo', 'direction' => 'out', 'amount' => 2000, 'status' => 'pending'],
            ['kind' => 'emprestimo', 'direction' => 'in',  'amount' => 800,  'status' => 'pending'],
            ['kind' => 'gasto',      'direction' => 'out', 'amount' => 999,  'status' => 'cancelled'],
        ];
        $s = FinancialRules::summary($rows);
        $this->assertSame(1000.0, $s['received']);
        $this->assertSame(1300.0, $s['to_receive']);   // 500 + 800
        $this->assertSame(300.0, $s['paid']);
        $this->assertSame(2700.0, $s['to_pay']);       // 700 + 2000 (cancelado fora)
        $this->assertSame(700.0, $s['balance']);
        $this->assertSame(2000.0, $s['loans_out']);
        $this->assertSame(800.0, $s['loans_in']);
        $this->assertSame(-700.0, $s['projected']);    // 700 + 1300 - 2700
    }

    public function testAsaasStatusMapeia(): void
    {
        $this->assertSame('paid', FinancialRules::asaasStatusToInternal('RECEIVED'));
        $this->assertSame('paid', FinancialRules::asaasStatusToInternal('CONFIRMED'));
        $this->assertSame('cancelled', FinancialRules::asaasStatusToInternal('OVERDUE'));
        $this->assertSame('pending', FinancialRules::asaasStatusToInternal('PENDING'));
        $this->assertSame('pending', FinancialRules::asaasStatusToInternal(null));
    }

    public function testImportFromAsaasPayment(): void
    {
        $row = FinancialRules::importFromAsaasPayment([
            'id' => 'pay_123', 'value' => 250.5, 'status' => 'RECEIVED',
            'dueDate' => '2026-10-05', 'paymentDate' => '2026-10-04 10:00:00',
            'description' => 'Site institucional',
        ], 7);
        $this->assertSame('ganho', $row['kind']);
        $this->assertSame('in', $row['direction']);
        $this->assertSame(250.5, $row['amount']);
        $this->assertSame('paid', $row['status']);
        $this->assertSame('asaas', $row['source']);
        $this->assertSame('pay_123', $row['asaas_payment_id']);
        $this->assertSame(7, $row['asaas_account_id']);
        $this->assertNotNull($row['paid_at']);

        $this->assertNull(FinancialRules::importFromAsaasPayment(['value' => 10], 1));
    }

    public function testValidMonth(): void
    {
        $this->assertTrue(FinancialRules::validMonth('2026-10'));
        $this->assertFalse(FinancialRules::validMonth('2026-13'));
        $this->assertFalse(FinancialRules::validMonth(''));
        $this->assertFalse(FinancialRules::validMonth(null));
    }

    public function testPermissaoExclusivaDoSuperAdmin(): void
    {
        $this->assertTrue(\Permissions::isSuperAdmin('super_admin'));
        $this->assertFalse(\Permissions::isSuperAdmin('developer'));
        $this->assertFalse(\Permissions::isSuperAdmin('comercial'));
        $this->assertFalse(\Permissions::isSuperAdmin(null));
    }
}