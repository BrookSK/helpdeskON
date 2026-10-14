<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use AsaasRules;

/**
 * Testes unitários das regras puras do Asaas (Fase 5).
 */
final class AsaasRulesTest extends TestCase
{
    public function testBaseUrl(): void
    {
        $this->assertStringContainsString('sandbox', AsaasRules::baseUrl(true));
        $this->assertStringContainsString('api.asaas.com', AsaasRules::baseUrl(false));
    }

    public function testBillingType(): void
    {
        $this->assertSame('PIX', AsaasRules::billingType('pix'));
        $this->assertSame('BOLETO', AsaasRules::billingType('boleto'));
        $this->assertSame('CREDIT_CARD', AsaasRules::billingType('cartao'));
        $this->assertSame('UNDEFINED', AsaasRules::billingType(null));
    }

    public function testChargePayload(): void
    {
        $p = AsaasRules::chargePayload('cus_1', 4000, '2026-11-03', 'boleto', 'Parcela 1/5');
        $this->assertSame('cus_1', $p['customer']);
        $this->assertSame('BOLETO', $p['billingType']);
        $this->assertSame(4000.0, $p['value']);
        $this->assertSame('2026-11-03', $p['dueDate']);
        $this->assertSame('Parcela 1/5', $p['description']);
        // Sem data/descrição: chaves ausentes.
        $p2 = AsaasRules::chargePayload('cus_1', 100, null, 'pix', null);
        $this->assertArrayNotHasKey('dueDate', $p2);
        $this->assertArrayNotHasKey('description', $p2);
    }

    public function testSubscriptionPayload(): void
    {
        $p = AsaasRules::subscriptionPayload('cus_1', 300, 'monthly', '2026-12-01', 'pix', 'Hospedagem');
        $this->assertSame('MONTHLY', $p['cycle']);
        $p2 = AsaasRules::subscriptionPayload('cus_1', 300, 'yearly', null, 'boleto', null);
        $this->assertSame('YEARLY', $p2['cycle']);
    }

    public function testInterpretEvent(): void
    {
        $this->assertSame('paid', AsaasRules::interpretEvent('PAYMENT_CONFIRMED'));
        $this->assertSame('paid', AsaasRules::interpretEvent('PAYMENT_RECEIVED'));
        $this->assertSame('overdue', AsaasRules::interpretEvent('PAYMENT_OVERDUE'));
        $this->assertSame('ignore', AsaasRules::interpretEvent('PAYMENT_CREATED'));
        $this->assertSame('ignore', AsaasRules::interpretEvent(null));
    }

    public function testIsConfigured(): void
    {
        $this->assertTrue(AsaasRules::isConfigured('tok'));
        $this->assertFalse(AsaasRules::isConfigured(''));
        $this->assertFalse(AsaasRules::isConfigured(null));
    }
}
