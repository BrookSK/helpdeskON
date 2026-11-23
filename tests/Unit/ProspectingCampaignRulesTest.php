<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ProspectingCampaignRules;

/**
 * Testes da tradução do estado de uma campanha de prospecção para linguagem
 * simples (ProspectingCampaignRules).
 *
 * Origem: a usuária criou campanhas ativas, a coluna "Hoje" ficou em 0 e o
 * "Log" só mostrava JSON cru repetido a cada minuto ("0 candidatos (página 1)").
 * Ela não conseguia saber se a campanha funcionava. Os cenários cobrem cada
 * situação que o painel precisa explicar sem ajuda de ninguém.
 *
 * Datas fixas via mktime (fuso do bootstrap). 2026-11-23 é uma segunda-feira.
 */
final class ProspectingCampaignRulesTest extends TestCase
{
    private function seg1559(): int { return mktime(15, 59, 30, 11, 23, 2026); }
    private function seg2000(): int { return mktime(20, 0, 0, 11, 23, 2026); }
    private function sab1000(): int { return mktime(10, 0, 0, 11, 28, 2026); }

    private function camp(array $over = []): array
    {
        return array_merge([
            'id' => 6, 'name' => 'Captação Site Operacional (11 - 50 func)', 'is_active' => 1,
            'lead_source' => 'apollo', 'sequence_id' => 3, 'auto_route' => 0,
            'daily_target' => 50, 'min_score' => 50,
            'days_of_week' => '1,2,3,4,5', 'window_start' => '08:00:00', 'window_end' => '18:00:00',
            'search_filters' => json_encode(['person_titles' => ['CEO', 'Diretor'], 'organization_num_employees_ranges' => ['11,50']]),
            'icp_rules' => json_encode(['seniorities' => ['owner', 'c_suite'], 'require_website' => true]),
        ], $over);
    }

    private function lastRun(array $m, string $at = '2026-11-23 15:59:04'): array
    {
        return ['detail' => json_encode(array_merge(['campaign' => 6, 'source' => 'apollo'], $m)), 'created_at' => $at];
    }

    private function ctx(array $over = []): array
    {
        return array_merge(['captured_today' => 0, 'last_run' => null, 'credit_limit_today' => 0, 'apollo_configured' => true], $over);
    }

    // ---- parseRunDetail / summarizeRun ----

    public function testParseRunDetailToleraJsonInvalidoECompletaChaves(): void
    {
        $m = ProspectingCampaignRules::parseRunDetail('não é json');
        $this->assertSame(0, $m['searched']);
        $this->assertNull($m['error']);
        $this->assertSame('apollo', $m['source']);

        $m = ProspectingCampaignRules::parseRunDetail('{"searched":"7","error":""}');
        $this->assertSame(7, $m['searched']);
        $this->assertNull($m['error'], 'Erro vazio não é erro.');
    }

    public function testResumoQuandoApolloNaoEncontraNinguem(): void
    {
        $s = ProspectingCampaignRules::summarizeRun(['searched' => 0]);
        $this->assertSame('warning', $s['tone']);
        $this->assertStringContainsString('Apollo não encontrou nenhuma pessoa', $s['text']);

        $s = ProspectingCampaignRules::summarizeRun(['source' => 'my_leads', 'searched' => 0]);
        $this->assertStringContainsString('Meus Leads', $s['text']);
    }

    public function testResumoComLeadsCaptadosUsaSingularEPlural(): void
    {
        $this->assertSame('1 lead novo captado e colocado na sequência.', ProspectingCampaignRules::summarizeRun(['searched' => 50, 'enrolled' => 1])['text']);
        $s = ProspectingCampaignRules::summarizeRun(['searched' => 50, 'enrolled' => 12]);
        $this->assertSame('success', $s['tone']);
        $this->assertSame('12 leads novos captados e colocados na sequência.', $s['text']);
    }

    public function testResumoExplicaPorQueNinguemFoiCaptado(): void
    {
        $s = ProspectingCampaignRules::summarizeRun(['searched' => 40, 'duplicated' => 30, 'out_of_icp' => 6, 'low_score' => 4]);
        $this->assertSame('warning', $s['tone']);
        $this->assertSame('40 pessoas encontradas, mas nenhuma foi captada: 30 já estavam no sistema, 6 fora do perfil (ICP), 4 com score abaixo do mínimo.', $s['text']);
    }

    public function testResumoComErroTemPrioridade(): void
    {
        $s = ProspectingCampaignRules::summarizeRun(['searched' => 0, 'error' => 'HTTP 401']);
        $this->assertSame('danger', $s['tone']);
        $this->assertStringContainsString('HTTP 401', $s['text']);
    }

    public function testSumRunsSomaAsExecucoesDoDia(): void
    {
        $t = ProspectingCampaignRules::sumRuns([
            json_encode(['searched' => 10, 'duplicated' => 4, 'enrolled' => 2, 'revealed_email' => 2]),
            json_encode(['searched' => 0, 'error' => 'timeout']),
            ['searched' => 5, 'low_score' => 5],
        ]);
        $this->assertSame(3, $t['runs']);
        $this->assertSame(15, $t['searched']);
        $this->assertSame(4, $t['duplicated']);
        $this->assertSame(5, $t['low_score']);
        $this->assertSame(2, $t['enrolled']);
        $this->assertSame(1, $t['errors']);
    }

    // ---- horário ----

    public function testScheduleLabel(): void
    {
        $this->assertSame('seg a sex, 08:00 às 18:00', ProspectingCampaignRules::scheduleLabel('1,2,3,4,5', '08:00:00', '18:00:00'));
        $this->assertSame('todos os dias, 09:30 às 17:00', ProspectingCampaignRules::scheduleLabel('', '09:30:00', '17:00:00'));
        $this->assertSame('seg, qua, sáb, 08:00 às 18:00', ProspectingCampaignRules::scheduleLabel('6,1,3', null, null));
    }

    public function testScheduleStateDentroEForaDaJanela(): void
    {
        $this->assertTrue(ProspectingCampaignRules::scheduleState('1,2,3,4,5', '08:00:00', '18:00:00', $this->seg1559())['inside']);

        $st = ProspectingCampaignRules::scheduleState('1,2,3,4,5', '08:00:00', '18:00:00', $this->seg2000());
        $this->assertFalse($st['inside']);
        $this->assertSame('amanhã às 08:00', ProspectingCampaignRules::whenLabel($st['next_ts'], $this->seg2000()));

        // Sábado: próxima é segunda.
        $st = ProspectingCampaignRules::scheduleState('1,2,3,4,5', '08:00:00', '18:00:00', $this->sab1000());
        $this->assertSame('seg (30/11) às 08:00', ProspectingCampaignRules::whenLabel($st['next_ts'], $this->sab1000()));

        // Dias vazios = todos os dias (mesma regra do serviço de captação).
        $this->assertTrue(ProspectingCampaignRules::scheduleState('', '08:00:00', '18:00:00', $this->sab1000())['inside']);
    }

    public function testAgoLabel(): void
    {
        $now = $this->seg1559();
        $this->assertSame('agora há pouco', ProspectingCampaignRules::agoLabel('2026-11-23 15:59:04', $now));
        $this->assertSame('há 7 min', ProspectingCampaignRules::agoLabel('2026-11-23 15:52:03', $now));
        $this->assertSame('há 3 h', ProspectingCampaignRules::agoLabel('2026-11-23 12:30:00', $now));
        $this->assertSame('em 20/11 às 10:00', ProspectingCampaignRules::agoLabel('2026-11-20 10:00:00', $now));
        $this->assertSame('', ProspectingCampaignRules::agoLabel(null, $now));
    }

    // ---- diagnóstico ----

    public function testCenarioDoPrintRodandoSemResultadosNoApollo(): void
    {
        $d = ProspectingCampaignRules::diagnose($this->camp(), $this->ctx([
            'last_run' => $this->lastRun(['searched' => 0, 'analyzed' => 0, 'enrolled' => 0]),
        ]), $this->seg1559());
        $this->assertSame('warning', $d['tone']);
        $this->assertSame('Rodando, mas sem resultados', $d['title']);
        $this->assertSame('Sem resultados no Apollo', $d['short']);
        $this->assertStringContainsString('afrouxe os filtros', $d['action']);
    }

    public function testCampanhaInativa(): void
    {
        $d = ProspectingCampaignRules::diagnose($this->camp(['is_active' => 0]), $this->ctx(), $this->seg1559());
        $this->assertSame('secondary', $d['tone']);
        $this->assertSame('Campanha inativa', $d['title']);
    }

    public function testSemSequenciaEhErro(): void
    {
        $d = ProspectingCampaignRules::diagnose($this->camp(['sequence_id' => null]), $this->ctx(), $this->seg1559());
        $this->assertSame('danger', $d['tone']);
        $this->assertSame('Sem sequência', $d['short']);

        // Roteamento automático com sequência por canal conta como configurado.
        $d = ProspectingCampaignRules::diagnose($this->camp(['sequence_id' => null, 'auto_route' => 1, 'sequence_id_email' => 9]), $this->ctx(), $this->seg1559());
        $this->assertNotSame('Sem sequência', $d['short']);
    }

    public function testApolloNaoConfiguradoSoImportaParaOrigemApollo(): void
    {
        $d = ProspectingCampaignRules::diagnose($this->camp(), $this->ctx(['apollo_configured' => false]), $this->seg1559());
        $this->assertSame('Apollo desconectado', $d['short']);

        $d = ProspectingCampaignRules::diagnose($this->camp(['lead_source' => 'my_leads']), $this->ctx(['apollo_configured' => false]), $this->seg1559());
        $this->assertNotSame('Apollo desconectado', $d['short']);
    }

    public function testMetaAtingidaMesmoForaDoHorario(): void
    {
        $d = ProspectingCampaignRules::diagnose($this->camp(), $this->ctx(['captured_today' => 50]), $this->seg2000());
        $this->assertSame('success', $d['tone']);
        $this->assertSame('Meta atingida (50/50)', $d['short']);
    }

    public function testForaDoHorarioDizQuandoVoltaARodar(): void
    {
        $d = ProspectingCampaignRules::diagnose($this->camp(), $this->ctx([
            'last_run' => $this->lastRun(['searched' => 0], '2026-11-23 17:59:00'),
        ]), $this->seg2000());
        $this->assertSame('secondary', $d['tone']);
        $this->assertSame('Fora do horário de captação', $d['title']);
        $this->assertSame('Volta amanhã às 08:00', $d['short']);
        $this->assertStringContainsString('seg a sex, 08:00 às 18:00', $d['text']);
    }

    public function testAguardandoQuandoAindaNaoRodouHoje(): void
    {
        $d = ProspectingCampaignRules::diagnose($this->camp(), $this->ctx(), $this->seg1559());
        $this->assertSame('info', $d['tone']);
        $this->assertSame('Aguardando execução', $d['short']);

        // Última execução foi em outro dia: também aguardando a de hoje.
        $d = ProspectingCampaignRules::diagnose($this->camp(), $this->ctx([
            'last_run' => $this->lastRun(['searched' => 0], '2026-11-20 17:00:00'),
        ]), $this->seg1559());
        $this->assertSame('Aguardando execução', $d['short']);
    }

    public function testErroNaUltimaExecucaoDeHoje(): void
    {
        $d = ProspectingCampaignRules::diagnose($this->camp(), $this->ctx([
            'last_run' => $this->lastRun(['searched' => 0, 'error' => 'Falha na busca (HTTP 422)']),
        ]), $this->seg1559());
        $this->assertSame('danger', $d['tone']);
        $this->assertStringContainsString('HTTP 422', $d['text']);
    }

    public function testLimiteDeCreditos(): void
    {
        $d = ProspectingCampaignRules::diagnose($this->camp(), $this->ctx([
            'captured_today' => 8, 'credit_limit_today' => 3,
            'last_run' => $this->lastRun(['searched' => 50, 'selected' => 20, 'reveal_failed' => 20]),
        ]), $this->seg1559());
        $this->assertSame('warning', $d['tone']);
        $this->assertSame('Sem créditos hoje', $d['short']);
        $this->assertStringContainsString('8 de 50', $d['text']);
    }

    public function testFuncionandoComLeadsHojeMesmoSeUltimaBuscaVeioVazia(): void
    {
        // Ao chegar ao fim das páginas o serviço reinicia a busca: uma execução
        // vazia depois de captar não deve virar alerta de "sem resultados".
        $d = ProspectingCampaignRules::diagnose($this->camp(), $this->ctx([
            'captured_today' => 12,
            'last_run' => $this->lastRun(['searched' => 0]),
        ]), $this->seg1559());
        $this->assertSame('success', $d['tone']);
        $this->assertSame('Funcionando (12/50)', $d['short']);
    }

    public function testEncontraMasNinguemPassaComConselhoDoMotivoPrincipal(): void
    {
        $d = ProspectingCampaignRules::diagnose($this->camp(), $this->ctx([
            'last_run' => $this->lastRun(['searched' => 50, 'duplicated' => 5, 'low_score' => 45]),
        ]), $this->seg1559());
        $this->assertSame('warning', $d['tone']);
        $this->assertSame('Ninguém passa nos critérios', $d['short']);
        $this->assertStringContainsString('Score mínimo', $d['action']);

        $d = ProspectingCampaignRules::diagnose($this->camp(), $this->ctx([
            'last_run' => $this->lastRun(['searched' => 50, 'duplicated' => 50]),
        ]), $this->seg1559());
        $this->assertStringContainsString('já está no sistema', $d['action']);
    }

    // ---- filtros ----

    public function testFiltersSummaryApolloEmLinguagemSimples(): void
    {
        $f = ProspectingCampaignRules::filtersSummary($this->camp());
        $map = array_column($f, 'value', 'label');
        $this->assertSame('CEO, Diretor', $map['Cargos']);
        $this->assertSame('11 a 50', $map['Funcionários']);
        $this->assertSame('senioridades: Dono, C-Level · exige empresa com site', $map['Perfil (ICP)']);
        $this->assertSame('50', $map['Score mínimo']);
    }

    public function testFiltersSummarySemFiltrosEMeusLeads(): void
    {
        $map = array_column(ProspectingCampaignRules::filtersSummary($this->camp(['search_filters' => '{}', 'icp_rules' => '{}'])), 'value', 'label');
        $this->assertSame('Sem filtros (qualquer pessoa)', $map['Busca']);

        $map = array_column(ProspectingCampaignRules::filtersSummary($this->camp([
            'lead_source' => 'my_leads', 'my_leads_filters' => '{"temperature":"quente"}', 'my_leads_ids' => '[1,2,3]',
        ])), 'value', 'label');
        $this->assertSame('3 selecionado(s) manualmente', $map['Leads escolhidos']);
        $this->assertSame('quente', $map['Temperatura']);
    }

    // ---- linha do tempo ----

    public function testTimelineAgrupaExecucoesIguaisSeguidasDoPrint(): void
    {
        // Mesma sequência do print: searched/done a cada minuto, sempre 0 candidatos.
        $rows = [];
        foreach (['15:59:04', '15:58:06', '15:57:04', '15:56:04'] as $t) {
            $rows[] = ['action' => 'done', 'detail' => json_encode(['searched' => 0]), 'created_at' => "2026-11-23 $t"];
            $rows[] = ['action' => 'searched', 'detail' => '0 candidatos (página 1)', 'created_at' => "2026-11-23 $t"];
        }
        $tl = ProspectingCampaignRules::timeline($rows);
        $this->assertCount(1, $tl);
        $this->assertSame(4, $tl[0]['count']);
        $this->assertSame('2026-11-23 15:56:04', $tl[0]['from']);
        $this->assertSame('2026-11-23 15:59:04', $tl[0]['to']);
        $this->assertSame('warning', $tl[0]['tone']);
        $this->assertStringContainsString('Apollo não encontrou nenhuma pessoa', $tl[0]['text']);
        $this->assertArrayNotHasKey('key', $tl[0]);
    }

    public function testTimelineNaoAgrupaLeadsCaptadosNemResultadosDiferentes(): void
    {
        $rows = [
            ['action' => 'done', 'detail' => json_encode(['searched' => 50, 'enrolled' => 2]), 'created_at' => '2026-11-23 10:01:00'],
            ['action' => 'enrolled', 'detail' => 'Apollo → sequência', 'created_at' => '2026-11-23 10:00:50', 'contact_name' => 'Ana'],
            ['action' => 'reveal_email', 'detail' => '1 crédito(s)', 'created_at' => '2026-11-23 10:00:49'],
            ['action' => 'imported', 'detail' => 'Ana (novo)', 'created_at' => '2026-11-23 10:00:49'],
            ['action' => 'enrolled', 'detail' => 'Apollo → sequência', 'created_at' => '2026-11-23 10:00:40', 'contact_name' => 'Bruno'],
            ['action' => 'credit_limit', 'detail' => 'x', 'created_at' => '2026-11-23 10:00:30'],
            ['action' => 'done', 'detail' => json_encode(['searched' => 0]), 'created_at' => '2026-11-23 09:00:00'],
        ];
        $tl = ProspectingCampaignRules::timeline($rows);
        $texts = array_column($tl, 'text');
        $this->assertCount(5, $tl, 'reveal_email e imported são etapas internas e não aparecem.');
        $this->assertSame('Execução: 2 leads novos captados e colocados na sequência.', $texts[0]);
        $this->assertSame('Lead captado: Ana (entrou na sequência)', $texts[1]);
        $this->assertSame('Lead captado: Bruno (entrou na sequência)', $texts[2]);
        $this->assertStringContainsString('créditos', $texts[3]);
        $this->assertSame(1, $tl[4]['count']);
    }

    public function testDescreveSkippedEFalhaDeBusca(): void
    {
        $r = ProspectingCampaignRules::describeLogRow(['action' => 'skipped', 'detail' => 'Lead sem interesse — não reprospectado (contact 9).', 'created_at' => 'x']);
        $this->assertSame('Lead ignorado: já disse que não tem interesse.', $r['text']);
        $r = ProspectingCampaignRules::describeLogRow(['action' => 'search_failed', 'detail' => 'HTTP 429 (página 3)', 'created_at' => 'x']);
        $this->assertSame('A busca no Apollo falhou: HTTP 429', $r['text']);
        $this->assertNull(ProspectingCampaignRules::describeLogRow(['action' => 'searched', 'detail' => '0', 'created_at' => 'x']));
    }
}
