<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use FinancialReportRules;

/**
 * Filtros e relatórios do Controle Financeiro: período, situação, categoria,
 * conta, cartão, pessoa, busca, totais e agrupamentos.
 */
final class FinancialReportRulesTest extends TestCase
{
    private const TODAY = '2026-11-02';

    private function rows(): array
    {
        return [
            ['id' => 1, 'kind' => 'salario', 'direction' => 'out', 'amount' => 3000, 'status' => 'paid',
             'due_date' => '2026-11-05', 'category_id' => 9, 'category_name' => 'Salário', 'user_id' => 7,
             'person_name' => 'Amanda', 'account_id' => 2, 'bank_account_name' => 'C6', 'description' => 'Salário'],
            ['id' => 2, 'kind' => 'salario', 'direction' => 'out', 'amount' => 2500, 'status' => 'pending',
             'due_date' => '2026-11-05', 'category_id' => 9, 'category_name' => 'Salário', 'user_id' => 8,
             'person_name' => 'Júlia', 'account_id' => 2, 'bank_account_name' => 'C6', 'description' => 'Salário'],
            ['id' => 3, 'kind' => 'gasto', 'direction' => 'out', 'amount' => 120, 'status' => 'pending',
             'due_date' => '2026-11-01', 'category_id' => 4, 'category_name' => 'Infra', 'card_id' => 5,
             'card_name' => 'XP', 'account_id' => 3, 'bank_account_name' => 'XP', 'description' => 'Servidor AWS'],
            ['id' => 4, 'kind' => 'ganho', 'direction' => 'in', 'amount' => 10000, 'status' => 'paid',
             'due_date' => '2026-11-10', 'category_id' => 1, 'category_name' => 'Vendas', 'account_id' => 2,
             'bank_account_name' => 'C6', 'description' => 'Projeto X'],
            ['id' => 5, 'kind' => 'salario', 'direction' => 'out', 'amount' => 3000, 'status' => 'pending',
             'due_date' => '2026-12-05', 'is_projected' => true, 'user_id' => 7, 'person_name' => 'Amanda',
             'category_id' => 9, 'category_name' => 'Salário', 'description' => 'Salário'],
            ['id' => 6, 'kind' => 'gasto', 'direction' => 'out', 'amount' => 999, 'status' => 'cancelled',
             'due_date' => '2026-11-03', 'description' => 'Cancelado'],
        ];
    }

    private function filter(array $in): array
    {
        $f = FinancialReportRules::normalizeFilters($in, self::TODAY);
        return array_values(array_map(
            fn($r) => $r['id'],
            array_filter($this->rows(), fn($r) => FinancialReportRules::matches($r, $f, self::TODAY))
        ));
    }

    public function testPeriodoPadraoEhOMesAtual(): void
    {
        $f = FinancialReportRules::normalizeFilters([], self::TODAY);
        $this->assertSame('month', $f['mode']);
        $this->assertSame('2026-11-01', $f['from']);
        $this->assertSame('2026-11-30', $f['to']);
    }

    public function testIntervaloDeDatasEInvertidas(): void
    {
        $f = FinancialReportRules::normalizeFilters(['from' => '2026-12-31', 'to' => '2026-09-01', 'month' => '2026-05'], self::TODAY);
        $this->assertSame('range', $f['mode']);
        $this->assertSame('2026-09-01', $f['from']);  // trocadas
        $this->assertSame('2026-12-31', $f['to']);
        // Só uma ponta: completa o mês.
        $g = FinancialReportRules::normalizeFilters(['from' => '2026-10-15'], self::TODAY);
        $this->assertSame('2026-10-31', $g['to']);
        // Data inválida é ignorada.
        $this->assertSame('month', FinancialReportRules::normalizeFilters(['from' => '2026-02-31'], self::TODAY)['mode']);
    }

    public function testValoresInvalidosViramSemFiltro(): void
    {
        $f = FinancialReportRules::normalizeFilters([
            'kind' => 'xxx', 'direction' => 'side', 'status' => 'foo', 'category_id' => 'abc', 'group' => 'drop table',
        ], self::TODAY);
        $this->assertSame('', $f['kind']);
        $this->assertSame('', $f['direction']);
        $this->assertSame('', $f['status']);
        $this->assertNull($f['category_id']);
        $this->assertSame('', $f['group']);
        $this->assertFalse(FinancialReportRules::hasActiveFilters($f));
    }

    public function testFiltraPorTipoCategoriaContaCartaoPessoa(): void
    {
        $this->assertSame([1, 2], $this->filter(['month' => '2026-11', 'kind' => 'salario']));
        $this->assertSame([3], $this->filter(['month' => '2026-11', 'category_id' => 4]));
        $this->assertSame([1, 2, 4], $this->filter(['month' => '2026-11', 'account_id' => 2]));
        $this->assertSame([3], $this->filter(['month' => '2026-11', 'card_id' => 5]));
        $this->assertSame([1], $this->filter(['month' => '2026-11', 'user_id' => 7]));
        $this->assertSame([4], $this->filter(['month' => '2026-11', 'direction' => 'in']));
        $this->assertTrue(FinancialReportRules::hasActiveFilters(FinancialReportRules::normalizeFilters(['user_id' => 7], self::TODAY)));
    }

    public function testSituacaoPagoAbertoAtrasadoPrevisto(): void
    {
        $this->assertSame([1, 4], $this->filter(['month' => '2026-11', 'status' => 'paid']));
        $this->assertSame([2, 3], $this->filter(['month' => '2026-11', 'status' => 'pending']));
        $this->assertSame([3], $this->filter(['month' => '2026-11', 'status' => 'overdue'])); // venceu 01/11
        $this->assertSame([5], $this->filter(['month' => '2026-12', 'status' => 'projected']));
        // Previsto conta como "em aberto", mas não como atrasado.
        $this->assertSame([5], $this->filter(['month' => '2026-12', 'status' => 'pending']));
        $this->assertSame([], $this->filter(['month' => '2026-12', 'status' => 'overdue']));
    }

    public function testBuscaPorTextoEmVariosCampos(): void
    {
        $this->assertSame([3], $this->filter(['month' => '2026-11', 'q' => 'aws']));
        $this->assertSame([2], $this->filter(['month' => '2026-11', 'q' => 'júlia']));
        $this->assertSame([4], $this->filter(['month' => '2026-11', 'q' => 'VENDAS']));
    }

    public function testIntervaloPegaVariosMeses(): void
    {
        $this->assertSame([1, 2, 3, 4, 5, 6], $this->filter(['from' => '2026-11-01', 'to' => '2026-12-31']));
    }

    public function testTotaisIgnoramCancelados(): void
    {
        $t = FinancialReportRules::totals($this->rows());
        $this->assertSame(5, $t['count']);
        $this->assertSame(10000.0, $t['in']);
        $this->assertSame(8620.0, $t['out']);       // 3000 + 2500 + 120 + 3000
        $this->assertSame(1380.0, $t['net']);
        $this->assertSame(3000.0, $t['paid_out']);
        $this->assertSame(5620.0, $t['open_out']);
        $this->assertSame(10000.0, $t['paid_in']);
    }

    public function testRelatorioPorCategoriaPessoaEMes(): void
    {
        $cat = FinancialReportRules::groupTotals($this->rows(), 'category');
        $this->assertSame('Vendas', $cat[0]['label']);     // maior movimento primeiro
        $byLabel = array_column($cat, null, 'label');
        $this->assertSame(8500.0, $byLabel['Salário']['out']);
        $this->assertSame(3, $byLabel['Salário']['count']);
        $this->assertSame(120.0, $byLabel['Infra']['out']);

        $people = array_column(FinancialReportRules::groupTotals($this->rows(), 'person'), null, 'label');
        $this->assertSame(6000.0, $people['Amanda']['out']);
        $this->assertSame(2500.0, $people['Júlia']['out']);
        $this->assertArrayHasKey('Sem pessoa', $people);

        $months = FinancialReportRules::groupTotals($this->rows(), 'month');
        $this->assertSame(['2026-11', '2026-12'], array_column($months, 'label')); // cronológico

        $status = array_column(FinancialReportRules::groupTotals($this->rows(), 'status'), null, 'label');
        $this->assertSame(3000.0, $status['Previsto']['out']);
        $this->assertSame([], FinancialReportRules::groupTotals($this->rows(), 'invalido'));
    }

    public function testExportacao(): void
    {
        $x = FinancialReportRules::exportRows([$this->rows()[1], $this->rows()[3]]);
        $this->assertCount(14, $x['headers']);
        $this->assertSame('Salário', $x['rows'][0][2]);
        $this->assertSame('Saída', $x['rows'][0][3]);
        $this->assertSame('Júlia', $x['rows'][0][6]);
        $this->assertSame('-2.500,00', $x['rows'][0][10]);
        $this->assertSame('Em aberto', $x['rows'][0][11]);
        $this->assertSame('10.000,00', $x['rows'][1][10]);
        $this->assertSame('Pago', $x['rows'][1][11]);

        $f = FinancialReportRules::normalizeFilters(['month' => '2026-11'], self::TODAY);
        $this->assertSame('financeiro_2026-11-01_a_2026-11-30.xlsx', FinancialReportRules::fileName($f));
    }
}
