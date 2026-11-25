<?php

use PHPUnit\Framework\TestCase;

/**
 * Intervalo variável entre os WhatsApps automáticos (proteção contra spam):
 * cada envio espera um tempo sorteado entre o mínimo e o máximo da sequência,
 * sem ritmo fixo, e o mesmo envio sempre gera o mesmo tempo (o cron pode
 * reconferir sem encurtar a espera).
 */
class WhatsappPacingRulesTest extends TestCase
{
    public function testNormalizaFaixa(): void
    {
        $this->assertSame([2, 10], WhatsappPacingRules::normalize(null, null), 'sem valor = padrão 2 a 10');
        $this->assertSame([2, 10], WhatsappPacingRules::normalize('', ' '));
        $this->assertSame([3, 8], WhatsappPacingRules::normalize('8', '3'), 'mínimo maior que máximo é invertido');
        $this->assertSame([0, 120], WhatsappPacingRules::normalize(-5, 999), 'limita a 0..120');
        $this->assertSame([5, 5], WhatsappPacingRules::normalize(5, ''), 'só um valor = intervalo fixo');
        $this->assertSame([0, 0], WhatsappPacingRules::normalize(0, 0));
        $this->assertTrue(WhatsappPacingRules::isEnabled(2, 10));
        $this->assertTrue(WhatsappPacingRules::isEnabled(0, 4));
        $this->assertFalse(WhatsappPacingRules::isEnabled(0, 0), '0 e 0 desliga');
    }

    public function testIntervaloFicaNaFaixaEVariaSemPadrao(): void
    {
        $gaps = [];
        for ($id = 1; $id <= 200; $id++) {
            $g = WhatsappPacingRules::gapSeconds($id, 2, 10);
            $this->assertGreaterThanOrEqual(120, $g);
            $this->assertLessThanOrEqual(600, $g);
            $gaps[] = $g;
        }
        // Espalhado pela faixa toda (não fica preso num valor nem num canto)
        $this->assertGreaterThan(120, count(array_unique($gaps)));
        $this->assertNotEmpty(array_filter($gaps, fn($g) => $g < 240), 'tem intervalos curtos');
        $this->assertNotEmpty(array_filter($gaps, fn($g) => $g > 480), 'tem intervalos longos');
        // Mensagens seguidas não repetem o mesmo tempo
        $same = 0;
        for ($i = 1; $i < count($gaps); $i++) if ($gaps[$i] === $gaps[$i - 1]) $same++;
        $this->assertLessThan(3, $same);
    }

    public function testMesmoEnvioSempreGeraOMesmoTempo(): void
    {
        $this->assertSame(WhatsappPacingRules::gapSeconds(4711, 2, 10), WhatsappPacingRules::gapSeconds('4711', 2, 10));
        $this->assertSame(300, WhatsappPacingRules::gapSeconds(99, 5, 5), 'mínimo = máximo é fixo');
        $this->assertSame(0, WhatsappPacingRules::gapSeconds(99, 0, 0));
    }

    public function testQuandoPodeMandarOProximo(): void
    {
        $last = strtotime('2026-11-25 10:00:00');
        $allowed = WhatsappPacingRules::nextAllowedTs($last, 12, 2, 10);
        $this->assertSame($last + WhatsappPacingRules::gapSeconds(12, 2, 10), $allowed);
        $this->assertSame(0, WhatsappPacingRules::nextAllowedTs(null, 12, 2, 10), 'nenhum envio antes: pode já');
        $this->assertSame(0, WhatsappPacingRules::nextAllowedTs($last, 12, 0, 0), 'intervalo desligado: pode já');
    }

    public function testTextoDoHistorico(): void
    {
        $now = strtotime('2026-11-25 10:00:00');
        $txt = WhatsappPacingRules::waitLabel($now + 7 * 60, $now);
        $this->assertStringContainsString('10:07', $txt);
        $this->assertStringContainsString('em 7 min', $txt);
        $this->assertStringContainsString('spam', $txt);
    }
}
