<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use LeadTemperatureRules;

/**
 * Temperatura do lead (LeadTemperatureRules): regra e explicação mostradas no CRM.
 */
final class LeadTemperatureRulesTest extends TestCase
{
    public function testFaixas(): void
    {
        $this->assertSame('frio', LeadTemperatureRules::temperature(0));
        $this->assertSame('morno', LeadTemperatureRules::temperature(1));
        $this->assertSame('morno', LeadTemperatureRules::temperature(4));
        $this->assertSame('engajado', LeadTemperatureRules::classify(3));
        $this->assertSame('quente', LeadTemperatureRules::temperature(5));
        $this->assertSame('quente', LeadTemperatureRules::temperature(60));
    }

    public function testExplicaPeloEngajamento(): void
    {
        $this->assertStringContainsString('ainda não abriu, clicou nem respondeu', LeadTemperatureRules::explain('frio', 0));
        $this->assertSame('Quente: 6 pontos de engajamento (aberturas, cliques e respostas aos nossos contatos).', LeadTemperatureRules::explain('quente', 6));
        $this->assertSame('Morno: 1 ponto de engajamento (aberturas, cliques e respostas aos nossos contatos).', LeadTemperatureRules::explain('morno', 1));
    }

    public function testDetectaAjusteAMao(): void
    {
        $this->assertStringContainsString('ajustada à mão', LeadTemperatureRules::explain('quente', 0));
        $this->assertStringContainsString('seria Frio', LeadTemperatureRules::explain('quente', 0));
        $this->assertStringContainsString('definida à mão', LeadTemperatureRules::explain('morno', null));
        $this->assertSame('à mão', LeadTemperatureRules::shortReason('quente', 0));
        $this->assertSame('à mão', LeadTemperatureRules::shortReason('frio', null));
        $this->assertSame('0 pt', LeadTemperatureRules::shortReason('frio', 0));
        $this->assertSame('6 pts', LeadTemperatureRules::shortReason('quente', 6));
    }

    public function testLegendaTemARegra(): void
    {
        $l = LeadTemperatureRules::legend();
        foreach (['aberto +1', 'clique em link +3', 'resposta +5', 'Frio: 0', 'Morno: 1 a 4', 'Quente: 5 ou mais'] as $s) {
            $this->assertStringContainsString($s, $l);
        }
    }
}
