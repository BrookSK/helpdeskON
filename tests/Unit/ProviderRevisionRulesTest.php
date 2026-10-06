<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ProviderRevisionRules;

/**
 * Testes das regras puras da revisão de valor do prestador: estados, quem pode
 * aprovar e validação do pedido.
 */
final class ProviderRevisionRulesTest extends TestCase
{
    public function testNormalizeStatus(): void
    {
        $this->assertSame('pending', ProviderRevisionRules::normalizeStatus('xpto'));
        $this->assertSame('approved', ProviderRevisionRules::normalizeStatus('approved'));
    }

    public function testCanReviewSoPendente(): void
    {
        $this->assertTrue(ProviderRevisionRules::canReview('pending'));
        $this->assertFalse(ProviderRevisionRules::canReview('approved'));
        $this->assertFalse(ProviderRevisionRules::canReview('rejected'));
    }

    public function testRoleCanApprove(): void
    {
        $this->assertTrue(ProviderRevisionRules::roleCanApprove('super_admin'));
        $this->assertTrue(ProviderRevisionRules::roleCanApprove('developer'));
        $this->assertFalse(ProviderRevisionRules::roleCanApprove('comercial'));
        $this->assertFalse(ProviderRevisionRules::roleCanApprove(null));
    }

    public function testValidateRequestExigeMotivo(): void
    {
        $r = ProviderRevisionRules::validateRequest('mensal', 5000, '', 'mensal', 4000);
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('justificativa', mb_strtolower($r['error']));
    }

    public function testValidateRequestExigeMudanca(): void
    {
        // Mesmo tipo e mesmo valor -> sem mudança.
        $r = ProviderRevisionRules::validateRequest('mensal', 4000, 'reajuste', 'mensal', 4000);
        $this->assertFalse($r['ok']);
    }

    public function testValidateRequestValorNegativo(): void
    {
        $r = ProviderRevisionRules::validateRequest('mensal', -10, 'erro', 'mensal', 4000);
        $this->assertFalse($r['ok']);
    }

    public function testValidateRequestOkComNovoValor(): void
    {
        $r = ProviderRevisionRules::validateRequest('mensal', 5000, 'reajuste anual', 'mensal', 4000);
        $this->assertTrue($r['ok']);
        $this->assertNull($r['error']);
    }

    public function testValidateRequestOkComNovoTipo(): void
    {
        $r = ProviderRevisionRules::validateRequest('hora', '', 'mudou para hora', 'mensal', 4000);
        $this->assertTrue($r['ok']);
    }
}
