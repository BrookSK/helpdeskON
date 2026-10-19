<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ProposalRules;

/**
 * Testes unitários das regras puras da Proposta/Orçamento (Fase 3).
 * Cobre cálculo de item/total, normalização de status/tipo, transições de
 * estado e validação da recusa.
 */
final class ProposalRulesTest extends TestCase
{
    // ---- status / tipo ----

    public function testNormalizeStatus(): void
    {
        $this->assertSame('draft', ProposalRules::normalizeStatus('draft'));
        $this->assertSame('accepted', ProposalRules::normalizeStatus('accepted'));
        $this->assertSame('draft', ProposalRules::normalizeStatus('qualquer'));
        $this->assertSame('draft', ProposalRules::normalizeStatus(''));
    }

    public function testNormalizeContractType(): void
    {
        $this->assertSame('dev_zero', ProposalRules::normalizeContractType('dev_zero'));
        $this->assertSame('suporte', ProposalRules::normalizeContractType('suporte'));
        $this->assertNull(ProposalRules::normalizeContractType('invalido'));
        $this->assertNull(ProposalRules::normalizeContractType(''));
    }

    public function testIsTerminal(): void
    {
        $this->assertTrue(ProposalRules::isTerminal('accepted'));
        $this->assertTrue(ProposalRules::isTerminal('rejected'));
        $this->assertTrue(ProposalRules::isTerminal('cancelled'));
        $this->assertFalse(ProposalRules::isTerminal('draft'));
        $this->assertFalse(ProposalRules::isTerminal('sent'));
    }

    // ---- transições ----

    public function testTransicoesValidasDoFluxo(): void
    {
        $this->assertTrue(ProposalRules::canTransition('draft', 'ready'));
        $this->assertTrue(ProposalRules::canTransition('ready', 'sent'));
        $this->assertTrue(ProposalRules::canTransition('sent', 'awaiting'));
        $this->assertTrue(ProposalRules::canTransition('sent', 'accepted'));
        $this->assertTrue(ProposalRules::canTransition('awaiting', 'rejected'));
        // Recusada pode voltar à elaboração (refazer).
        $this->assertTrue(ProposalRules::canTransition('rejected', 'draft'));
        // Mesmo estado é idempotente.
        $this->assertTrue(ProposalRules::canTransition('sent', 'sent'));
    }

    public function testTransicoesInvalidas(): void
    {
        // Não pode pular de draft direto para accepted.
        $this->assertFalse(ProposalRules::canTransition('draft', 'accepted'));
        // Terminal não transiciona.
        $this->assertFalse(ProposalRules::canTransition('accepted', 'draft'));
        $this->assertFalse(ProposalRules::canTransition('cancelled', 'sent'));
        // Estados inválidos.
        $this->assertFalse(ProposalRules::canTransition('x', 'draft'));
        $this->assertFalse(ProposalRules::canTransition('draft', 'y'));
    }

    // ---- cálculo ----

    public function testItemAmountHorasVezesValorHora(): void
    {
        // Exemplo da reunião: 3h x R$50 = R$150.
        $this->assertSame(150.0, ProposalRules::itemAmount(3, 50, null));
        $this->assertSame(500.0, ProposalRules::itemAmount(10, 50, null));
        // Com string BR no valor/hora.
        $this->assertSame(150.0, ProposalRules::itemAmount(3, 'R$ 50,00', null));
    }

    public function testItemAmountComQuantidade(): void
    {
        // 2 × 3h × R$50 = R$300. Quantidade vazia continua valendo 1.
        $this->assertSame(300.0, ProposalRules::itemAmount(3, 50, null, 2));
        $this->assertSame(150.0, ProposalRules::itemAmount(3, 50, null, null));
        // Sem horas, quantidade multiplica o valor fixo.
        $this->assertSame(2400.0, ProposalRules::itemAmount(null, null, 1200, 2));
        $this->assertSame(0.0, ProposalRules::itemAmount(3, 50, null, 0));
    }

    public function testForecastECronogramaPorServico(): void
    {
        $this->assertSame(150.0, ProposalRules::forecast(3, 50));
        $this->assertSame(80.0, ProposalRules::forecast(null, 80));
        $this->assertNull(ProposalRules::forecast(null, null));

        $tl = ProposalRules::timeline([
            ['description' => 'Site', 'schedule_start' => '2026-11-02', 'schedule_end' => '2026-11-16'],
            ['description' => 'API', 'schedule_start' => '2026-11-09', 'schedule_end' => '2026-11-30'],
        ], '2026-11-01', '2026-11-30');
        $this->assertSame('2026-11-01', $tl['start']);
        $this->assertSame('2026-11-30', $tl['end']);
        $this->assertNotNull($tl['rows'][0]['bar']);
        $this->assertGreaterThan($tl['rows'][0]['bar']['left'], $tl['rows'][1]['bar']['left']);
        $this->assertTrue(ProposalRules::scheduleOrderOk('2026-11-01', '2026-11-30'));
        $this->assertFalse(ProposalRules::scheduleOrderOk('2026-11-30', '2026-11-01'));
    }

    public function testTotaisSeparadosPorCobranca(): void
    {
        $t = ProposalRules::totals([
            ['amount' => 6000, 'billing' => 'once'],
            ['amount' => 500, 'billing' => 'monthly'],
            ['amount' => 1000],
        ]);
        $this->assertSame(7000.0, $t['once']);
        $this->assertSame(500.0, $t['monthly']);
        $this->assertSame(13000.0, $t['year']);
    }

    public function testProjecao12Meses(): void
    {
        $p = ProposalRules::projection([
            ['amount' => 6000, 'billing' => 'once', 'schedule_start' => '2026-11-02', 'schedule_end' => '2026-12-20'],
            ['amount' => 500, 'billing' => 'monthly', 'schedule_start' => '2026-12-01'],
        ], '2026-11-01', '2026-12-20', 2000);

        $this->assertCount(12, $p['months']);
        $this->assertSame('nov/26', $p['months'][0]['label']);
        // Pontual no mês em que começa; mensal a partir de dezembro.
        $this->assertSame(6000.0, $p['months'][0]['invest']);
        $this->assertSame(500.0, $p['months'][1]['invest']);
        // Retorno começa no mês seguinte ao fim do desenvolvimento (janeiro).
        $this->assertSame(0.0, $p['months'][1]['return']);
        $this->assertSame(2000.0, $p['months'][2]['return']);
        $this->assertSame(11500.0, $p['invest']);
        $this->assertSame(20000.0, $p['return']);
        $this->assertNotNull($p['payback']);
        $this->assertNotNull($p['roi']);

        $sem = ProposalRules::projection([['amount' => 100, 'billing' => 'once']], '2026-11-01');
        $this->assertNull($sem['payback']);
        $this->assertNull($sem['roi']);
    }

    public function testVariaveisELinhasDoTextoFixo(): void
    {
        $this->assertSame(
            'Total R$ 10,00 para ACME. {desconhecida}',
            ProposalRules::applyVars('Total {total} para {cliente}. {desconhecida}', ['total' => 'R$ 10,00', 'cliente' => 'ACME'])
        );
        $lines = ProposalRules::blockLines("Discovery: entender o negócio\n\n- Brasiliana\n");
        $this->assertSame(['title' => 'Discovery', 'text' => 'Entender o negócio'], $lines[0]);
        $this->assertSame(['title' => 'Brasiliana', 'text' => ''], $lines[1]);

        $keys = array_column(ProposalRules::defaultBlocks(), 'block_key');
        $this->assertSame(['payment', 'notes'], $keys);
        $this->assertTrue(ProposalRules::isLegacyBlock('notes', "Proposta válida até {validade}.\r\nValores referem-se somente ao escopo descrito. Mudanças de escopo são orçadas à parte.\r\nDesenvolvimento do zero inclui 90 dias"));
        $this->assertFalse(ProposalRules::isLegacyBlock('notes', ProposalRules::defaultBlocks()[1]['body']));
        $this->assertFalse(ProposalRules::isLegacyBlock('payment', 'Texto editado pelo comercial.'));
        $this->assertSame('monthly', ProposalRules::normalizeBilling('monthly'));
        $this->assertSame('once', ProposalRules::normalizeBilling('x'));
    }

    public function testPlanosComDescontoSobreAMensalidade(): void
    {
        $plans = ProposalRules::plans([
            ['amount' => 15000, 'billing' => 'once'],
            ['amount' => 13600, 'billing' => 'monthly'],
        ], 20, 30);
        $p3 = $plans['items'][0];
        $p6 = $plans['items'][1];
        $p12 = $plans['items'][2];

        $this->assertSame(13600.0, $p3['monthly']);
        $this->assertSame(10880.0, $p6['monthly']);
        $this->assertSame(9520.0, $p12['monthly']);
        $this->assertSame(0.0, $p3['save_month']);
        $this->assertSame(2720.0, $p6['save_month']);
        $this->assertSame(4080.0, $p12['save_month']);
        $this->assertSame(15000.0, $plans['once']);
        // Implantação igual; índice do plano 3 meses no mês 12 = 100.
        $this->assertSame(100, $p3['index12']);
        $this->assertLessThan(100, $p12['index12']);
        $this->assertSame(12, count($p3['cum']));
        $this->assertSame(15000.0 + 13600.0, $p3['cum'][0]);
        $this->assertSame(15000.0 + 13600.0 * 12, $p3['year']);
    }

    public function testProdutosAMaisPorMesUsaCeilDoInvestimento(): void
    {
        // Mesma conta do modelo: 128.760 / (1.500 × 12) = 7,15 → 8 por mês.
        $rows = ProposalRules::extraProducts(128760, 5000, [30, 50, 70]);
        $this->assertSame(1500.0, $rows[0]['profit']);
        $this->assertSame(2500.0, $rows[1]['profit']);
        $this->assertSame(3500.0, $rows[2]['profit']);
        $this->assertSame(8, $rows[0]['per_month']);
        $this->assertSame(5, $rows[1]['per_month']);
        $this->assertSame(4, $rows[2]['per_month']);
        $this->assertSame(0.0 - 128760.0, $rows[0]['pace'][0]);
    }

    public function testItemAmountValorFixoQuandoSemHoras(): void
    {
        // Sem horas, usa o valor fixo.
        $this->assertSame(1200.0, ProposalRules::itemAmount(null, null, 1200));
        $this->assertSame(1200.0, ProposalRules::itemAmount(0, 0, 'R$ 1.200,00'));
        // Sem nada: zero.
        $this->assertSame(0.0, ProposalRules::itemAmount(null, null, null));
    }

    public function testTotalSomaItens(): void
    {
        $items = [
            ['hours' => 3, 'hourly_rate' => 50],           // 150
            ['hours' => 10, 'hourly_rate' => 50],          // 500
            ['amount' => 1200],                            // 1200 (valor fixo já calculado)
            ['description' => 'vazio'],                     // 0
        ];
        $this->assertSame(1850.0, ProposalRules::total($items));
    }

    public function testTotalListaVaziaEhZero(): void
    {
        $this->assertSame(0.0, ProposalRules::total([]));
    }

    // ---- recusa ----

    public function testSanitizeRejectReason(): void
    {
        $this->assertSame('Preço alto', ProposalRules::sanitizeRejectReason('  Preço alto  '));
        $this->assertNull(ProposalRules::sanitizeRejectReason('   '));
        $this->assertNull(ProposalRules::sanitizeRejectReason(''));
        $this->assertNull(ProposalRules::sanitizeRejectReason(null));
    }

    // ---- recorrência / renovação (estruturadas) ----

    public function testNormalizeRenewalERecurrence(): void
    {
        $this->assertSame('auto', ProposalRules::normalizeRenewal('auto'));
        $this->assertSame('manual', ProposalRules::normalizeRenewal('manual'));
        $this->assertSame('none', ProposalRules::normalizeRenewal('none'));
        $this->assertSame('none', ProposalRules::normalizeRenewal('qualquer'));
        $this->assertSame('none', ProposalRules::normalizeRenewal(null));

        $this->assertSame('monthly', ProposalRules::normalizeRecurrence('monthly'));
        $this->assertSame('yearly', ProposalRules::normalizeRecurrence('yearly'));
        $this->assertNull(ProposalRules::normalizeRecurrence('x'));
        $this->assertNull(ProposalRules::normalizeRecurrence(null));
    }

    public function testRenewalLabel(): void
    {
        $this->assertSame('Renovação automática mensal', ProposalRules::renewalLabel('auto', 'monthly'));
        $this->assertSame('Renovação automática anual', ProposalRules::renewalLabel('auto', 'yearly'));
        $this->assertStringContainsString('confirmação', ProposalRules::renewalLabel('manual', 'monthly'));
        $this->assertSame('Sem renovação automática', ProposalRules::renewalLabel('none'));
    }

    // ---- descontos: normalização ----

    public function testNormalizeDiscountsSaneiaEscoposETipos(): void
    {
        $d = ProposalRules::normalizeDiscounts([
            ['scope' => 'once', 'type' => 'percent', 'value' => 10],
            ['scope' => 'total', 'type' => 'fixed', 'value' => 'R$ 500,00'],
            ['scope' => 'xxx', 'type' => 'yyy', 'value' => 5],   // vira total/percent
            ['scope' => 'monthly', 'type' => 'percent', 'value' => 0], // zero: descartado
        ]);
        $this->assertCount(3, $d);
        $this->assertSame('once', $d[0]['scope']);
        $this->assertSame(10.0, $d[0]['value']);
        $this->assertSame('total', $d[1]['scope']);
        $this->assertSame('fixed', $d[1]['type']);
        $this->assertSame(500.0, $d[1]['value']);
        $this->assertSame('total', $d[2]['scope']);
        $this->assertSame('percent', $d[2]['type']);
    }

    public function testNormalizeDiscountsAceitaJsonString(): void
    {
        $json = json_encode([['scope' => 'monthly', 'type' => 'percent', 'value' => 15]]);
        $d = ProposalRules::normalizeDiscounts($json);
        $this->assertCount(1, $d);
        $this->assertSame(15.0, $d[0]['value']);
    }

    public function testScheduleForcaMensalEExigeMeses(): void
    {
        $d = ProposalRules::normalizeDiscounts([
            ['scope' => 'once', 'type' => 'schedule', 'months' => [
                ['month' => 1, 'type' => 'percent', 'value' => 20],
                ['month' => 2, 'type' => 'percent', 'value' => 10],
            ]],
            ['scope' => 'monthly', 'type' => 'schedule', 'months' => []], // sem meses: descartado
        ]);
        $this->assertCount(1, $d);
        $this->assertSame('monthly', $d[0]['scope']); // schedule sempre mensal
        $this->assertSame('schedule', $d[0]['type']);
        $this->assertSame(1, $d[0]['months'][0]['month']);
    }

    // ---- descontos: aplicação ----

    public function testApplyDiscountsPercentualEFixoNoEscopo(): void
    {
        $disc = ProposalRules::normalizeDiscounts([['scope' => 'once', 'type' => 'percent', 'value' => 10]]);
        $r = ProposalRules::applyDiscounts(1000.0, 'once', $disc, 1000.0, 0.0);
        $this->assertSame(1000.0, $r['gross']);
        $this->assertSame(100.0, $r['discount']);
        $this->assertSame(900.0, $r['net']);

        // Desconto fixo não ultrapassa a base (não fica negativo).
        $disc2 = ProposalRules::normalizeDiscounts([['scope' => 'monthly', 'type' => 'fixed', 'value' => 5000]]);
        $r2 = ProposalRules::applyDiscounts(1000.0, 'monthly', $disc2, 0.0, 1000.0);
        $this->assertSame(1000.0, $r2['discount']);
        $this->assertSame(0.0, $r2['net']);
    }

    public function testApplyDiscountsTotalEhRateadoPorEscopo(): void
    {
        // Desconto de R$ 300 no total (once 2000 + monthly 1000 = 3000):
        // rateio once = 300 * 2000/3000 = 200; monthly = 300 * 1000/3000 = 100.
        $disc = ProposalRules::normalizeDiscounts([['scope' => 'total', 'type' => 'fixed', 'value' => 300]]);
        $once = ProposalRules::applyDiscounts(2000.0, 'once', $disc, 2000.0, 1000.0);
        $monthly = ProposalRules::applyDiscounts(1000.0, 'monthly', $disc, 2000.0, 1000.0);
        $this->assertSame(200.0, $once['discount']);
        $this->assertSame(100.0, $monthly['discount']);
    }

    public function testMonthlyNetByMonthComDescontoTemporal(): void
    {
        // Mensalidade cheia 1000; mês 1 = 20%, mês 2 = 10%, demais cheios.
        $disc = ProposalRules::normalizeDiscounts([
            ['scope' => 'monthly', 'type' => 'schedule', 'months' => [
                ['month' => 1, 'type' => 'percent', 'value' => 20],
                ['month' => 2, 'type' => 'percent', 'value' => 10],
            ]],
        ]);
        $net = ProposalRules::monthlyNetByMonth(1000.0, $disc);
        $this->assertSame(800.0, $net[0]);  // mês 1
        $this->assertSame(900.0, $net[1]);  // mês 2
        $this->assertSame(1000.0, $net[2]); // mês 3 em diante
        $this->assertSame(1000.0, $net[11]);
    }

    public function testMonthlyNetByMonthFixoNoMes(): void
    {
        // Primeiro mês com valor fixo (R$ 300 de abatimento), resto cheio.
        $disc = ProposalRules::normalizeDiscounts([
            ['scope' => 'monthly', 'type' => 'schedule', 'months' => [
                ['month' => 1, 'type' => 'fixed', 'value' => 300],
            ]],
        ]);
        $net = ProposalRules::monthlyNetByMonth(1000.0, $disc);
        $this->assertSame(700.0, $net[0]);
        $this->assertSame(1000.0, $net[1]);
    }

    // ---- totais comerciais ----

    public function testCommercialTotalsComDescontoSimples(): void
    {
        $items = [
            ['amount' => 2000, 'billing' => 'once'],
            ['amount' => 1000, 'billing' => 'monthly'],
        ];
        $disc = [['scope' => 'monthly', 'type' => 'percent', 'value' => 10]];
        $t = ProposalRules::commercialTotals($items, $disc);
        $this->assertSame(2000.0, $t['once']['net']);        // pontual sem desconto
        $this->assertSame(1000.0, $t['monthly']['gross']);
        $this->assertSame(900.0, $t['monthly']['net']);      // mensal -10%
        // Ano = 2000 + 900*12 = 12800.
        $this->assertSame(12800.0, $t['year']['net']);
        $this->assertFalse($t['has_schedule']);
    }

    public function testCommercialTotalsComDescontoTemporal(): void
    {
        $items = [['amount' => 1000, 'billing' => 'monthly']];
        $disc = [['scope' => 'monthly', 'type' => 'schedule', 'months' => [
            ['month' => 1, 'type' => 'percent', 'value' => 20],
            ['month' => 2, 'type' => 'percent', 'value' => 10],
        ]]];
        $t = ProposalRules::commercialTotals($items, $disc);
        $this->assertTrue($t['has_schedule']);
        // Ano = 800 + 900 + 1000*10 = 11700.
        $this->assertSame(11700.0, $t['year']['net']);
        // Mensalidade "estabilizada" (mês 12) = 1000 cheio.
        $this->assertSame(1000.0, $t['monthly']['net']);
    }

    public function testCommercialTotalsSemDescontoBateComTotals(): void
    {
        $items = [
            ['amount' => 6000, 'billing' => 'once'],
            ['amount' => 500, 'billing' => 'monthly'],
        ];
        $t = ProposalRules::commercialTotals($items, []);
        $base = ProposalRules::totals($items);
        $this->assertSame($base['once'], $t['once']['net']);
        $this->assertSame($base['monthly'], $t['monthly']['net']);
        $this->assertSame($base['year'], $t['year']['net']);
    }

    // ---- projeção com desconto temporal ----

    public function testProjecaoConsideraDescontoTemporal(): void
    {
        $items = [['amount' => 1000, 'billing' => 'monthly', 'schedule_start' => '2026-11-01']];
        $net = ProposalRules::monthlyNetByMonth(1000.0, ProposalRules::normalizeDiscounts([
            ['scope' => 'monthly', 'type' => 'schedule', 'months' => [
                ['month' => 1, 'type' => 'percent', 'value' => 20],
                ['month' => 2, 'type' => 'percent', 'value' => 10],
            ]],
        ]));
        $p = ProposalRules::projection($items, '2026-11-01', null, null, $net);
        // Mês 1 = 800, mês 2 = 900, mês 3 = 1000 (líquidos).
        $this->assertSame(800.0, $p['months'][0]['invest']);
        $this->assertSame(900.0, $p['months'][1]['invest']);
        $this->assertSame(1000.0, $p['months'][2]['invest']);
        // Sem o array líquido, continua cheio (compatibilidade).
        $pCheio = ProposalRules::projection($items, '2026-11-01');
        $this->assertSame(1000.0, $pCheio['months'][0]['invest']);
    }

    // ---- fonte única de apresentação ----

    public function testBuildPresentationDataSemMensalidadeNaoTemOpcaoDePlano(): void
    {
        $proposal = ['client_name' => 'ACME', 'content' => null, 'discounts' => null];
        $items = [['amount' => 5000, 'billing' => 'once', 'description' => 'Site']];
        $data = ProposalRules::buildPresentationData($proposal, $items);
        $this->assertTrue($data['has_once']);
        $this->assertFalse($data['has_monthly']);
        $this->assertNull($data['plans']);
        $this->assertSame([], $data['plan_options']); // aceite simples
        $this->assertSame(5000.0, $data['commercial']['once']['net']);
    }

    public function testBuildPresentationDataComMensalidadeOfereceTresPlanos(): void
    {
        $proposal = ['client_name' => 'ACME', 'content' => json_encode(['discount_6' => 20, 'discount_12' => 30]), 'discounts' => null];
        $items = [
            ['amount' => 15000, 'billing' => 'once', 'description' => 'Implantação'],
            ['amount' => 1000, 'billing' => 'monthly', 'description' => 'Gestão', 'renewal' => 'auto', 'recurrence_period' => 'monthly'],
        ];
        $data = ProposalRules::buildPresentationData($proposal, $items);
        $this->assertTrue($data['has_monthly']);
        $this->assertCount(3, $data['plan_options']);
        $this->assertSame('plan_3', $data['plan_options'][0]['key']);
        $this->assertSame('plan_12', $data['plan_options'][2]['key']);
        // Recorrência/renovação estruturada chega montada no item.
        $monthly = array_values(array_filter($data['items'], fn($i) => $i['is_monthly']))[0];
        $this->assertSame('auto', $monthly['renewal']);
        $this->assertStringContainsString('automática', $monthly['renewal_label']);
    }

    public function testBuildPresentationDataAplicaDescontoNasVariaveis(): void
    {
        $proposal = [
            'client_name' => 'ACME',
            'content' => json_encode(['payment_method' => '50/50']),
            'discounts' => json_encode([['scope' => 'once', 'type' => 'percent', 'value' => 10]]),
        ];
        $items = [['amount' => 1000, 'billing' => 'once', 'description' => 'Site']];
        $data = ProposalRules::buildPresentationData($proposal, $items);
        $this->assertTrue($data['has_discount']);
        $this->assertSame(900.0, $data['commercial']['once']['net']);
        // A variável {total_pontual} reflete o líquido.
        $this->assertSame('R$ 900,00', $data['vars']['total_pontual']);
    }
}
