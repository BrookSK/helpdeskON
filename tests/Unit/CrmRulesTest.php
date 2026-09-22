<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use CrmRules;

/**
 * Testes unitários das regras puras do CRM / comercial (sem banco).
 * Cobrem: parse de valor monetário BR, detecção de coluna fechada/perdida,
 * normalização de número para discagem e cálculo de comissão.
 */
final class CrmRulesTest extends TestCase
{
    // ---- parseMoneyBR ----

    public function testParseMoneyComRealEMilharEDecimal(): void
    {
        $this->assertSame(5000.0, CrmRules::parseMoneyBR('R$ 5.000,00'));
        $this->assertSame(1234.56, CrmRules::parseMoneyBR('1.234,56'));
        $this->assertSame(1234.56, CrmRules::parseMoneyBR('R$ 1.234,56'));
    }

    public function testParseMoneyInteiroPuro(): void
    {
        $this->assertSame(5000.0, CrmRules::parseMoneyBR('5000'));
        $this->assertSame(5000.0, CrmRules::parseMoneyBR('R$ 5000'));
    }

    public function testParseMoneyMilharSemDecimal(): void
    {
        // "5.000" (ponto de milhar, sem decimal) => 5000, não 5.0
        $this->assertSame(5000.0, CrmRules::parseMoneyBR('5.000'));
        $this->assertSame(1500000.0, CrmRules::parseMoneyBR('1.500.000'));
    }

    public function testParseMoneyValorPequenoComDecimal(): void
    {
        $this->assertSame(5.5, CrmRules::parseMoneyBR('5,50'));
        $this->assertSame(0.99, CrmRules::parseMoneyBR('0,99'));
    }

    public function testParseMoneyVazioOuNuloRetornaNull(): void
    {
        $this->assertNull(CrmRules::parseMoneyBR(''));
        $this->assertNull(CrmRules::parseMoneyBR('   '));
        $this->assertNull(CrmRules::parseMoneyBR(null));
        $this->assertNull(CrmRules::parseMoneyBR('R$'));
    }

    public function testParseMoneyDecimalComPonto(): void
    {
        // Formato en-US como fallback: "1234.56" => 1234.56
        $this->assertSame(1234.56, CrmRules::parseMoneyBR('1234.56'));
    }

    // ---- isClosedColumn ----

    public function testIsClosedColumn(): void
    {
        $this->assertTrue(CrmRules::isClosedColumn('Fechado'));
        $this->assertTrue(CrmRules::isClosedColumn('fechado'));
        $this->assertTrue(CrmRules::isClosedColumn('Ganho'));
        $this->assertTrue(CrmRules::isClosedColumn('Convertido'));
        $this->assertTrue(CrmRules::isClosedColumn('  Fechado/Ganho  '));
        $this->assertFalse(CrmRules::isClosedColumn('Novo'));
        $this->assertFalse(CrmRules::isClosedColumn('Perdido'));
        $this->assertFalse(CrmRules::isClosedColumn(null));
    }

    // ---- isLostColumn ----

    public function testIsLostColumn(): void
    {
        $this->assertTrue(CrmRules::isLostColumn('Perdido'));
        $this->assertTrue(CrmRules::isLostColumn('perdido'));
        $this->assertTrue(CrmRules::isLostColumn('Descartado'));
        $this->assertTrue(CrmRules::isLostColumn('Perdido/Descartado'));
        $this->assertFalse(CrmRules::isLostColumn('Fechado'));
        $this->assertFalse(CrmRules::isLostColumn('Novo'));
        $this->assertFalse(CrmRules::isLostColumn(null));
    }

    // ---- normalizeDialNumber ----

    public function testNormalizeDialNumberAdiciona55(): void
    {
        $this->assertSame('5511988887777', CrmRules::normalizeDialNumber('11988887777'));
        $this->assertSame('5511988887777', CrmRules::normalizeDialNumber('(11) 98888-7777'));
    }

    public function testNormalizeDialNumberJaTem55(): void
    {
        $this->assertSame('5511988887777', CrmRules::normalizeDialNumber('5511988887777'));
        $this->assertSame('5511988887777', CrmRules::normalizeDialNumber('+55 11 98888-7777'));
    }

    public function testNormalizeDialNumberColapsa55Duplicado(): void
    {
        // "5555119..." não pode virar dois prefixos 55
        $this->assertSame('5511988887777', CrmRules::normalizeDialNumber('5555119888 87777'));
    }

    public function testNormalizeDialNumberVazio(): void
    {
        $this->assertSame('', CrmRules::normalizeDialNumber(''));
        $this->assertSame('', CrmRules::normalizeDialNumber('abc'));
    }

    // ---- commission ----

    public function testComissaoFechamentoEProspeccao(): void
    {
        // Fechou 10.000 (10%) + prospectou 5.000 (5%)
        $c = CrmRules::commission(10000.0, 5000.0, 10.0, 5.0);
        $this->assertSame(1000.0, $c['closing']);
        $this->assertSame(250.0, $c['prospection']);
        $this->assertSame(1250.0, $c['total']);
    }

    public function testComissaoUsaLegadoQuandoClosingZero(): void
    {
        // closingPct = 0 => cai no legacyPct
        $c = CrmRules::commission(10000.0, 0.0, 0.0, 0.0, 8.0);
        $this->assertSame(800.0, $c['closing']);
        $this->assertSame(0.0, $c['prospection']);
        $this->assertSame(800.0, $c['total']);
    }

    public function testComissaoComValoresIguaisNaoColapsa(): void
    {
        // Regressão: dois cards de MESMO valor (2x 5.000) devem somar 10.000,
        // não colapsar via DISTINCT. Aqui o valor já vem somado pelo caller,
        // então provamos que a matemática respeita o total agregado.
        $closed = 5000.0 + 5000.0; // dois negócios de mesmo valor
        $c = CrmRules::commission($closed, 0.0, 10.0, 0.0);
        $this->assertSame(1000.0, $c['closing']); // 10% de 10.000
    }

    public function testComissaoTudoZero(): void
    {
        $c = CrmRules::commission(0.0, 0.0, 0.0, 0.0);
        $this->assertSame(0.0, $c['total']);
    }
}
