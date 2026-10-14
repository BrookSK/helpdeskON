<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RdoViewRules;

/**
 * Testes unitários das regras puras das VISÕES do RDO (Kanban por empresa e
 * Calendário por dia). Sem banco/HTTP. Cobrem: normalização da visão padrão,
 * chave de empresa (incluindo os limites: vazio/zero/negativo/não-numérico),
 * agrupamento por empresa (ordem alfabética + "Sem cliente" por último) e o
 * agrupamento por dia a partir de report_date em formatos variados.
 */
final class RdoViewRulesTest extends TestCase
{
    // ===== Visão padrão / normalização =====

    public function testVisaoPadraoEhCalendario(): void
    {
        $this->assertSame('calendar', RdoViewRules::DEFAULT_VIEW);
    }

    public function testVisaoValida(): void
    {
        $this->assertTrue(RdoViewRules::isValidView('calendar'));
        $this->assertTrue(RdoViewRules::isValidView('kanban'));
        $this->assertTrue(RdoViewRules::isValidView('list'));
        $this->assertFalse(RdoViewRules::isValidView('grid'));
        $this->assertFalse(RdoViewRules::isValidView(''));
        $this->assertFalse(RdoViewRules::isValidView(null));
    }

    public function testNormalizeViewCaiNoPadraoQuandoInvalida(): void
    {
        $this->assertSame('kanban', RdoViewRules::normalizeView('kanban'));
        $this->assertSame('list', RdoViewRules::normalizeView('list'));
        $this->assertSame('calendar', RdoViewRules::normalizeView('calendar'));
        $this->assertSame('calendar', RdoViewRules::normalizeView('xpto'));
        $this->assertSame('calendar', RdoViewRules::normalizeView(''));
        $this->assertSame('calendar', RdoViewRules::normalizeView(null));
    }

    // ===== companyKey (limites) =====

    public function testCompanyKeyComEmpresaValida(): void
    {
        $this->assertSame('c3', RdoViewRules::companyKey(['company_id' => 3]));
        $this->assertSame('c3', RdoViewRules::companyKey(['company_id' => '3']));
        $this->assertSame('c42', RdoViewRules::companyKey(['company_id' => 42]));
    }

    public function testCompanyKeySemEmpresaOuInvalida(): void
    {
        $none = RdoViewRules::NO_COMPANY_KEY;
        $this->assertSame($none, RdoViewRules::companyKey(['company_id' => null]));
        $this->assertSame($none, RdoViewRules::companyKey(['company_id' => '']));
        $this->assertSame($none, RdoViewRules::companyKey(['company_id' => 0]));
        $this->assertSame($none, RdoViewRules::companyKey(['company_id' => -5]));
        $this->assertSame($none, RdoViewRules::companyKey(['company_id' => 'abc']));
        $this->assertSame($none, RdoViewRules::companyKey([])); // chave ausente
    }

    // ===== groupByCompany =====

    public function testGroupByCompanyVazioRetornaListaVazia(): void
    {
        $this->assertSame([], RdoViewRules::groupByCompany([]));
    }

    public function testGroupByCompanyOrdenaAlfabeticamenteESemClientePorUltimo(): void
    {
        $items = [
            ['id' => 1, 'company_id' => 2, 'company_name' => 'Zeta'],
            ['id' => 2, 'company_id' => null, 'company_name' => null],
            ['id' => 3, 'company_id' => 1, 'company_name' => 'alfa'], // minúsculo p/ testar case-insensitive
            ['id' => 4, 'company_id' => 2, 'company_name' => 'Zeta'],
        ];
        $cols = RdoViewRules::groupByCompany($items);

        // 3 colunas: alfa, Zeta, Sem cliente (nessa ordem)
        $this->assertCount(3, $cols);
        $this->assertSame('alfa', $cols[0]['company_name']);
        $this->assertSame('Zeta', $cols[1]['company_name']);
        $this->assertSame(RdoViewRules::NO_COMPANY_LABEL, $cols[2]['company_name']);

        // "Sem cliente" é sempre a última, com company_id null
        $this->assertSame('none', $cols[2]['key']);
        $this->assertNull($cols[2]['company_id']);

        // Zeta agrupou os dois relatórios
        $this->assertCount(2, $cols[1]['items']);
        $this->assertSame([1, 4], array_column($cols[1]['items'], 'id'));

        // alfa tem company_id inteiro
        $this->assertSame(1, $cols[0]['company_id']);
        $this->assertCount(1, $cols[0]['items']);
    }

    public function testGroupByCompanyPreservaOrdemDosItensDentroDaColuna(): void
    {
        // Backend entrega por data desc; o agrupamento não deve reordenar itens.
        $items = [
            ['id' => 10, 'company_id' => 5, 'company_name' => 'ACME', 'report_date' => '2026-10-05'],
            ['id' => 11, 'company_id' => 5, 'company_name' => 'ACME', 'report_date' => '2026-10-04'],
            ['id' => 12, 'company_id' => 5, 'company_name' => 'ACME', 'report_date' => '2026-10-03'],
        ];
        $cols = RdoViewRules::groupByCompany($items);
        $this->assertCount(1, $cols);
        $this->assertSame([10, 11, 12], array_column($cols[0]['items'], 'id'));
    }

    public function testGroupByCompanyUsaRotuloSemClienteQuandoNomeAusente(): void
    {
        $items = [['id' => 1, 'company_id' => 0, 'company_name' => null]];
        $cols = RdoViewRules::groupByCompany($items);
        $this->assertCount(1, $cols);
        $this->assertSame(RdoViewRules::NO_COMPANY_LABEL, $cols[0]['company_name']);
        $this->assertNull($cols[0]['company_id']);
    }

    // ===== eventsByDay / dayKey =====

    public function testDayKeyFormatosVariados(): void
    {
        $this->assertSame('2026-10-05', RdoViewRules::dayKey('2026-10-05'));
        $this->assertSame('2026-10-05', RdoViewRules::dayKey('2026-10-05 13:40:00'));
        $this->assertSame('2026-10-05', RdoViewRules::dayKey('2026-10-05T13:40'));
        $this->assertNull(RdoViewRules::dayKey(''));
        $this->assertNull(RdoViewRules::dayKey(null));
        $this->assertNull(RdoViewRules::dayKey('05/10/2026')); // formato BR não é a chave
        $this->assertNull(RdoViewRules::dayKey('sem data'));
    }

    public function testEventsByDayAgrupaEIgnoraSemData(): void
    {
        $items = [
            ['id' => 1, 'report_date' => '2026-10-05'],
            ['id' => 2, 'report_date' => '2026-10-05 09:00:00'],
            ['id' => 3, 'report_date' => '2026-10-06'],
            ['id' => 4, 'report_date' => null],      // ignorado
            ['id' => 5, 'report_date' => ''],        // ignorado
        ];
        $byDay = RdoViewRules::eventsByDay($items);

        $this->assertArrayHasKey('2026-10-05', $byDay);
        $this->assertArrayHasKey('2026-10-06', $byDay);
        $this->assertCount(2, $byDay['2026-10-05']);
        $this->assertSame([1, 2], array_column($byDay['2026-10-05'], 'id'));
        $this->assertCount(1, $byDay['2026-10-06']);
        // Itens sem data não geram nenhuma chave adicional.
        $this->assertCount(2, $byDay);
    }
}
