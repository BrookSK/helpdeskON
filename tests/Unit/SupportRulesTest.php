<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use SupportRules;

/**
 * Testes unitários das regras puras do fluxo de SUPORTE (SupportRules).
 * Cobrem: validação/normalização de gravidade, SLA de análise por gravidade,
 * cálculo do prazo-limite de análise, faixa do prazo de resolução e a detecção
 * de "é suporte?" pela categoria.
 */
final class SupportRulesTest extends TestCase
{
    public function testGravidadesValidasEInvalidas(): void
    {
        foreach (['critico', 'alto', 'medio', 'baixo'] as $s) {
            $this->assertTrue(SupportRules::isValidSeverity($s));
        }
        $this->assertFalse(SupportRules::isValidSeverity('urgente'));
        $this->assertFalse(SupportRules::isValidSeverity(''));
        $this->assertFalse(SupportRules::isValidSeverity(null));
    }

    public function testNormalizeGravidade(): void
    {
        // Normaliza caixa e espaços; 'critico' é escrito sem acento no ENUM.
        $this->assertSame('critico', SupportRules::normalizeSeverity('CRITICO'));
        $this->assertSame('alto', SupportRules::normalizeSeverity('ALTO'));
        $this->assertSame('baixo', SupportRules::normalizeSeverity(' baixo '));
        $this->assertNull(SupportRules::normalizeSeverity('nada'));
        $this->assertNull(SupportRules::normalizeSeverity(null));
    }

    public function testSlaDeAnalisePorGravidade(): void
    {
        // Crítico 30min, Alto 2h, Médio 4h, Baixo 8h.
        $this->assertSame(30, SupportRules::analysisSlaMinutes('critico'));
        $this->assertSame(120, SupportRules::analysisSlaMinutes('alto'));
        $this->assertSame(240, SupportRules::analysisSlaMinutes('medio'));
        $this->assertSame(480, SupportRules::analysisSlaMinutes('baixo'));
        $this->assertNull(SupportRules::analysisSlaMinutes('invalida'));
    }

    public function testPrazoDeAnaliseCalculadoAPartirDaAbertura(): void
    {
        $opened = '2026-01-10 10:00:00';
        $this->assertSame('2026-01-10 10:30:00', SupportRules::analysisDueAt($opened, 'critico'));
        $this->assertSame('2026-01-10 12:00:00', SupportRules::analysisDueAt($opened, 'alto'));
        $this->assertSame('2026-01-10 14:00:00', SupportRules::analysisDueAt($opened, 'medio'));
        $this->assertSame('2026-01-10 18:00:00', SupportRules::analysisDueAt($opened, 'baixo'));
        $this->assertNull(SupportRules::analysisDueAt($opened, 'xpto'));
    }

    public function testFaixaDoPrazoDeResolucao(): void
    {
        // 30 min a 48h (2880 min).
        $this->assertTrue(SupportRules::isValidResolutionMinutes(30));
        $this->assertTrue(SupportRules::isValidResolutionMinutes(2880));
        $this->assertTrue(SupportRules::isValidResolutionMinutes('120'));
        $this->assertFalse(SupportRules::isValidResolutionMinutes(29));
        $this->assertFalse(SupportRules::isValidResolutionMinutes(2881));
        $this->assertFalse(SupportRules::isValidResolutionMinutes('abc'));
        $this->assertFalse(SupportRules::isValidResolutionMinutes(-10));
    }

    public function testRotuloESituacaoDaGravidade(): void
    {
        $this->assertSame('Crítico', SupportRules::severityLabel('critico'));
        $this->assertSame('—', SupportRules::severityLabel(null));
        $this->assertStringContainsString('Produção parada', SupportRules::severitySituation('critico'));
        $this->assertSame('', SupportRules::severitySituation('invalida'));
    }

    public function testDeteccaoDeSuportePelaCategoria(): void
    {
        $this->assertTrue(SupportRules::isSupportCategory('suporte'));
        $this->assertTrue(SupportRules::isSupportCategory('  SUPORTE '));
        $this->assertFalse(SupportRules::isSupportCategory('desenvolvimento'));
        $this->assertFalse(SupportRules::isSupportCategory(null));
    }
}
