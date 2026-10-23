<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use PlanningScheduleRules;

/**
 * Testes unitários das regras puras do cronograma do Planejamento (export PDF).
 *
 * Cobrem a decisão de quais status entram (próximas etapas vs todas), o
 * agrupamento/ordenação por empresa, os rótulos/cores de status e prioridade,
 * a formatação de datas, a limpeza/trunc da descrição e o resumo de filtros.
 */
final class PlanningScheduleRulesTest extends TestCase
{
    // ---- isActive / filterCards ----

    public function testIsActiveReconheceStatusEncerrados(): void
    {
        $this->assertTrue(PlanningScheduleRules::isActive('open'));
        $this->assertTrue(PlanningScheduleRules::isActive('in_progress'));
        $this->assertTrue(PlanningScheduleRules::isActive('waiting_client'));
        $this->assertFalse(PlanningScheduleRules::isActive('completed'));
        $this->assertFalse(PlanningScheduleRules::isActive('denied'));
        $this->assertFalse(PlanningScheduleRules::isActive('archived'));
    }

    public function testFilterCardsOcultaEncerradosPorPadrao(): void
    {
        $cards = [
            ['id' => 1, 'status' => 'open'],
            ['id' => 2, 'status' => 'completed'],
            ['id' => 3, 'status' => 'in_progress'],
            ['id' => 4, 'status' => 'archived'],
            ['id' => 5, 'status' => 'denied'],
        ];

        $ativos = PlanningScheduleRules::filterCards($cards, true);
        $ids = array_column($ativos, 'id');

        $this->assertSame([1, 3], $ids, 'Só os ativos (open/in_progress) devem permanecer.');
    }

    public function testFilterCardsMantemTodosQuandoOnlyActiveFalse(): void
    {
        $cards = [
            ['id' => 1, 'status' => 'open'],
            ['id' => 2, 'status' => 'completed'],
            ['id' => 3, 'status' => 'archived'],
        ];

        $todos = PlanningScheduleRules::filterCards($cards, false);

        $this->assertCount(3, $todos, 'Com onlyActive=false mantém concluídas/arquivadas.');
    }

    // ---- groupByCompany ----

    public function testAgrupaPorEmpresaEOrdenaAlfabeticamente(): void
    {
        $cards = [
            ['id' => 1, 'company_name' => 'Zeta Ltda',  'status' => 'open', 'start_date' => '2026-01-10'],
            ['id' => 2, 'company_name' => 'Alfa S.A.',   'status' => 'open', 'start_date' => '2026-01-05'],
            ['id' => 3, 'company_name' => 'Alfa S.A.',   'status' => 'open', 'start_date' => '2026-01-02'],
        ];

        $groups = PlanningScheduleRules::groupByCompany($cards);

        $this->assertCount(2, $groups);
        $this->assertSame('Alfa S.A.', $groups[0]['company'], 'Alfa vem antes de Zeta.');
        $this->assertSame('Zeta Ltda', $groups[1]['company']);
        // Dentro de Alfa, ordenado por data (id 3 em 02/01 antes do id 2 em 05/01).
        $this->assertSame([3, 2], array_column($groups[0]['cards'], 'id'));
    }

    public function testCardSemEmpresaVaiParaGrupoSemEmpresaNoFim(): void
    {
        $cards = [
            ['id' => 1, 'company_name' => null,        'status' => 'open'],
            ['id' => 2, 'company_name' => 'Beta Ltda', 'status' => 'open'],
            ['id' => 3, 'company_name' => '',          'status' => 'open'],
        ];

        $groups = PlanningScheduleRules::groupByCompany($cards);

        $this->assertSame('Beta Ltda', $groups[0]['company']);
        $this->assertSame(PlanningScheduleRules::NO_COMPANY_LABEL, $groups[1]['company']);
        // Os dois sem empresa (null e '') caem no mesmo grupo.
        $this->assertCount(2, $groups[1]['cards']);
    }

    public function testOrdenacaoPorDataColocaSemDataNoFim(): void
    {
        $cards = [
            ['id' => 1, 'company_name' => 'X', 'status' => 'open'], // sem data
            ['id' => 2, 'company_name' => 'X', 'status' => 'open', 'due_date' => '2026-02-01'],
            ['id' => 3, 'company_name' => 'X', 'status' => 'open', 'start_date' => '2026-01-15'],
        ];

        $groups = PlanningScheduleRules::groupByCompany($cards);
        $ids = array_column($groups[0]['cards'], 'id');

        // 3 (15/01) antes de 2 (01/02); o 1 (sem data) por último.
        $this->assertSame([3, 2, 1], $ids);
    }

    public function testSortDatePreferenciaStartDateDepoisDueDate(): void
    {
        $this->assertSame('2026-01-10', PlanningScheduleRules::sortDate(['start_date' => '2026-01-10', 'due_date' => '2026-01-20']));
        $this->assertSame('2026-01-20', PlanningScheduleRules::sortDate(['start_date' => '', 'due_date' => '2026-01-20']));
        $this->assertNull(PlanningScheduleRules::sortDate(['start_date' => null, 'due_date' => '']));
    }

    // ---- buildSchedule / countActivities ----

    public function testBuildScheduleFiltraEAgrupaEContaTotal(): void
    {
        $cards = [
            ['id' => 1, 'company_name' => 'Alfa', 'status' => 'open',      'start_date' => '2026-01-02'],
            ['id' => 2, 'company_name' => 'Alfa', 'status' => 'completed', 'start_date' => '2026-01-03'],
            ['id' => 3, 'company_name' => 'Beta', 'status' => 'in_progress'],
        ];

        $groups = PlanningScheduleRules::buildSchedule($cards, true);

        // Concluído (id 2) é removido; sobram Alfa(1) e Beta(1).
        $this->assertSame(2, PlanningScheduleRules::countActivities($groups));
        $this->assertSame('Alfa', $groups[0]['company']);
        $this->assertSame([1], array_column($groups[0]['cards'], 'id'));
    }

    public function testBuildScheduleComTodasAsAtividades(): void
    {
        $cards = [
            ['id' => 1, 'company_name' => 'Alfa', 'status' => 'open'],
            ['id' => 2, 'company_name' => 'Alfa', 'status' => 'archived'],
        ];

        $groups = PlanningScheduleRules::buildSchedule($cards, false);

        $this->assertSame(2, PlanningScheduleRules::countActivities($groups));
    }

    public function testCountActivitiesComGruposVazios(): void
    {
        $this->assertSame(0, PlanningScheduleRules::countActivities([]));
    }

    // ---- labels / cores ----

    public function testStatusLabelEColor(): void
    {
        $this->assertSame('Em andamento', PlanningScheduleRules::statusLabel('in_progress'));
        $this->assertSame('Aguardando', PlanningScheduleRules::statusLabel('waiting_client'));
        $this->assertSame('#1565c0', PlanningScheduleRules::statusColor('open'));
        // Fallback: status desconhecido devolve o próprio valor e cor neutra.
        $this->assertSame('xpto', PlanningScheduleRules::statusLabel('xpto'));
        $this->assertSame('#546e7a', PlanningScheduleRules::statusColor('xpto'));
    }

    public function testPriorityLabel(): void
    {
        $this->assertSame('Alta', PlanningScheduleRules::priorityLabel('high'));
        $this->assertSame('Urgente', PlanningScheduleRules::priorityLabel('urgent'));
        $this->assertSame('???', PlanningScheduleRules::priorityLabel('???'));
    }

    public function testStatusBadgeDevolveParFundoETexto(): void
    {
        // Espelha as pílulas .badge-* do site (fundo claro + texto colorido).
        $open = PlanningScheduleRules::statusBadge('open');
        $this->assertSame('#e3f2fd', $open['bg']);
        $this->assertSame('#1565c0', $open['fg']);

        $done = PlanningScheduleRules::statusBadge('completed');
        $this->assertSame('#e8f5e9', $done['bg']);
        $this->assertSame('#2e7d32', $done['fg']);
    }

    public function testStatusBadgeFallbackNeutroParaDesconhecido(): void
    {
        $b = PlanningScheduleRules::statusBadge('xpto');
        $this->assertSame('#eceff1', $b['bg']);
        $this->assertSame('#546e7a', $b['fg']);
        // Sempre retorna as duas chaves esperadas pela view.
        $this->assertArrayHasKey('bg', $b);
        $this->assertArrayHasKey('fg', $b);
    }

    // ---- formatDate ----

    public function testFormatDate(): void
    {
        $this->assertSame('10/01/2026', PlanningScheduleRules::formatDate('2026-01-10'));
        $this->assertSame('10/01/2026', PlanningScheduleRules::formatDate('2026-01-10 14:30:00'));
        $this->assertSame('—', PlanningScheduleRules::formatDate(null));
        $this->assertSame('—', PlanningScheduleRules::formatDate(''));
        $this->assertSame('(vazio)', PlanningScheduleRules::formatDate('  ', '(vazio)'));
    }

    // ---- plainText (descrição) ----

    public function testPlainTextRemoveHtmlENormalizaEspacos(): void
    {
        $html = "<p>Gerar <strong>PDF</strong>  com   totais</p>";
        $this->assertSame('Gerar PDF com totais', PlanningScheduleRules::plainText($html));
    }

    public function testPlainTextConverteQuebrasEmEspaco(): void
    {
        $html = "<p>Linha 1</p><p>Linha 2</p>";
        $this->assertSame('Linha 1 Linha 2', PlanningScheduleRules::plainText($html));
    }

    public function testPlainTextTruncaComReticencias(): void
    {
        $txt = str_repeat('a', 50);
        $out = PlanningScheduleRules::plainText($txt, 10);
        // 10 caracteres + reticência.
        $this->assertSame(str_repeat('a', 10) . '…', $out);
    }

    public function testPlainTextVazioOuNull(): void
    {
        $this->assertSame('', PlanningScheduleRules::plainText(null));
        $this->assertSame('', PlanningScheduleRules::plainText(''));
        $this->assertSame('', PlanningScheduleRules::plainText('<p>   </p>'));
    }

    // ---- summarizeDescription (resumo >15 linhas) ----

    public function testResumoMantemDescricaoCurtaInalterada(): void
    {
        $txt = '<p>Descrição curta e completa.</p>';
        // Abaixo do limite: volta o texto limpo, sem marca de continuação.
        $out = PlanningScheduleRules::summarizeDescription($txt);
        $this->assertSame('Descrição curta e completa.', $out);
        $this->assertStringNotContainsString(PlanningScheduleRules::DESC_CONTINUATION, $out);
    }

    public function testResumoNoLimiteExatoNaoCorta(): void
    {
        // Texto com exatamente o tamanho do teto não é resumido.
        $txt = str_repeat('a', PlanningScheduleRules::DESC_SUMMARY_MAX_CHARS);
        $out = PlanningScheduleRules::summarizeDescription($txt);
        $this->assertSame($txt, $out);
    }

    public function testResumoCortaNoFimDeFrase(): void
    {
        // Monta um texto longo com frases; o corte deve terminar num ponto final
        // e anexar a marca de continuação.
        $frase = 'Esta é uma frase completa de exemplo com tamanho razoavel. ';
        $txt = str_repeat($frase, 40); // bem acima do teto (~900)
        $out = PlanningScheduleRules::summarizeDescription($txt);

        $this->assertStringEndsWith(PlanningScheduleRules::DESC_CONTINUATION, $out);
        // A parte antes da marca deve terminar com pontuação de fim de frase.
        $core = substr($out, 0, -strlen(PlanningScheduleRules::DESC_CONTINUATION));
        $this->assertMatchesRegularExpression('/[.!?]$/u', $core);
        // E deve ser mais curta que o original.
        $this->assertLessThan(mb_strlen($txt, 'UTF-8'), mb_strlen($out, 'UTF-8'));
    }

    public function testResumoFallbackFimDePalavraQuandoSemPontuacao(): void
    {
        // Sem nenhuma pontuação de fim de frase: recua para fim de palavra, sem
        // cortar no meio de uma palavra, e anexa a marca.
        $txt = str_repeat('palavra ', 300); // 2400 chars, sem ponto final
        $out = PlanningScheduleRules::summarizeDescription($txt);

        $this->assertStringEndsWith(PlanningScheduleRules::DESC_CONTINUATION, $out);
        $core = substr($out, 0, -strlen(PlanningScheduleRules::DESC_CONTINUATION));
        // Não termina no meio de "palavra" (termina exatamente numa palavra inteira).
        $this->assertMatchesRegularExpression('/palavra$/u', rtrim($core));
    }

    public function testResumoLimpaHtmlAntesDeResumir(): void
    {
        $html = '<p>Primeiro parágrafo.</p>' . str_repeat('<li>item de lista bem comprido para passar do limite</li>', 40);
        $out = PlanningScheduleRules::summarizeDescription($html);
        // Não sobra nenhuma tag HTML no resumo.
        $this->assertStringNotContainsString('<', $out);
        $this->assertStringEndsWith(PlanningScheduleRules::DESC_CONTINUATION, $out);
    }

    public function testResumoVazioOuNull(): void
    {
        $this->assertSame('', PlanningScheduleRules::summarizeDescription(null));
        $this->assertSame('', PlanningScheduleRules::summarizeDescription(''));
        $this->assertSame('', PlanningScheduleRules::summarizeDescription('<p>   </p>'));
    }

    public function testResumoMaxCharsZeroNaoCorta(): void
    {
        // Teto 0 desativa o resumo (texto integral).
        $txt = str_repeat('x', 5000);
        $this->assertSame($txt, PlanningScheduleRules::summarizeDescription($txt, 0));
    }

    // ---- filtersSummary ----

    public function testFiltersSummaryMontaResumoLegivel(): void
    {
        $label = PlanningScheduleRules::filtersSummary([
            'company'   => 'DN Solutions',
            'assigned'  => 'Julia',
            'statuses'  => 'Em andamento',
        ]);
        $this->assertSame('Empresa: DN Solutions · Responsável: Julia · Status: Em andamento', $label);
    }

    public function testFiltersSummaryIgnoraChavesVazias(): void
    {
        $label = PlanningScheduleRules::filtersSummary([
            'company'   => '',
            'assigned'  => 'Julia',
            'requester' => null,
            'q'         => '  ',
        ]);
        $this->assertSame('Responsável: Julia', $label);
    }

    public function testFiltersSummaryVazioQuandoSemFiltros(): void
    {
        $this->assertSame('', PlanningScheduleRules::filtersSummary([]));
    }
}
