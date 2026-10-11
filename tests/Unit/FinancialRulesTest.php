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

    public function testParcelamentoSomenteEmGastoGanhoEmprestimo(): void
    {
        // Salário e transferência não podem ser parcelados.
        $r = FinancialRules::validateTransaction([
            'kind' => 'salario', 'amount' => 5000, 'installments' => 2,
            'due_date' => '2026-10-20',
        ]);
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('Parcelamento', implode(' ', $r['errors']));

        // Ganho parcelado (entrada em N vezes) OK, sem cartão.
        $ganho = FinancialRules::validateTransaction([
            'kind' => 'ganho', 'amount' => 12000, 'installments' => 6,
            'due_date' => '2026-10-20',
        ]);
        $this->assertTrue($ganho['ok']);

        // Empréstimo parcelado OK.
        $emp = FinancialRules::validateTransaction([
            'kind' => 'emprestimo', 'amount' => 12000, 'installments' => 12,
            'due_date' => '2026-10-20', 'loan_date' => '2026-10-01', 'direction' => 'out',
        ]);
        $this->assertTrue($emp['ok']);
        $this->assertSame('2026-10-01', $emp['data']['loan_date']);
    }

    public function testRecorrenciaValidaEClona(): void
    {
        $ok = FinancialRules::validateTransaction([
            'kind' => 'gasto', 'amount' => 250, 'recurring_cycle' => 'monthly',
            'recurring_until' => '2027-12-31', 'due_date' => '2026-10-10',
        ]);
        $this->assertTrue($ok['ok']);
        $this->assertSame('monthly', $ok['data']['recurring_cycle']);
        $this->assertSame('2027-12-31', $ok['data']['recurring_until']);

        // Recorrente não pode ser parcelado nem em salário.
        $this->assertFalse(FinancialRules::validateTransaction([
            'kind' => 'gasto', 'amount' => 10, 'recurring_cycle' => 'monthly', 'installments' => 3,
        ])['ok']);
        $this->assertFalse(FinancialRules::validateTransaction([
            'kind' => 'salario', 'amount' => 10, 'recurring_cycle' => 'monthly',
        ])['ok']);

        $this->assertSame('2026-11-10', FinancialRules::recurringNextDate('2026-10-10', 'monthly'));
        $this->assertSame('2027-10-10', FinancialRules::recurringNextDate('2026-10-10', 'yearly'));
        $this->assertTrue(FinancialRules::recurringContinues('2026-11-10', null));
        $this->assertTrue(FinancialRules::recurringContinues('2027-12-10', '2027-12-31'));
        $this->assertFalse(FinancialRules::recurringContinues('2028-01-10', '2027-12-31'));
    }

    public function testComissaoValorFixoOuPercentual(): void
    {
        $fixa = FinancialRules::validateCommission([
            'transaction_id' => 5, 'user_id' => 2, 'amount' => 'R$ 1.000,00',
            'installments' => 4, 'first_due_date' => '2026-10-30',
        ], 10000.0);
        $this->assertTrue($fixa['ok']);
        $this->assertSame(1000.0, $fixa['data']['amount']);
        $this->assertSame(4, $fixa['data']['installments']);
        $this->assertSame('2026-10-30', $fixa['data']['next_due_date']);

        // 10% de um ganho de 40k.
        $pct = FinancialRules::validateCommission([
            'transaction_id' => 5, 'recipient' => 'Parceiro X', 'percent' => '10',
            'installments' => 2,
        ], 40000.0);
        $this->assertTrue($pct['ok']);
        $this->assertSame(4000.0, $pct['data']['amount']);

        // Sem ganho, sem recebedor ou sem valor: erro.
        $this->assertFalse(FinancialRules::validateCommission(['transaction_id' => 0, 'amount' => 10], 100)['ok']);
        $this->assertFalse(FinancialRules::validateCommission(['transaction_id' => 5, 'amount' => 10], 100)['ok']);
        $this->assertFalse(FinancialRules::validateCommission(['transaction_id' => 5, 'user_id' => 1], 100)['ok']);
    }

    public function testComissaoProgressoEVencimentos(): void
    {
        $this->assertSame('2026-11-30', FinancialRules::commissionNextDue('2026-10-30', 1));
        $this->assertSame('2027-01-30', FinancialRules::commissionNextDue('2026-10-30', 3));

        // Total 1000 em 4: pagou 1 -> resta 750.
        $this->assertSame(750.0, FinancialRules::commissionRemaining(1000.0, 4, 1));
        $this->assertSame(0.0, FinancialRules::commissionRemaining(1000.0, 4, 4));

        $s = FinancialRules::summaryCommissions([
            ['amount' => 1000, 'installments' => 4, 'installments_paid' => 1, 'status' => 'pending'],
            ['amount' => 500, 'installments' => 1, 'installments_paid' => 1, 'status' => 'pending'],
            ['amount' => 999, 'installments' => 1, 'installments_paid' => 0, 'status' => 'cancelled'],
        ]);
        $this->assertSame(750.0, $s['to_pay']);
        $this->assertSame(750.0, $s['paid']);
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