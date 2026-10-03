<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ClickSignRules;

/**
 * Testes unitários das regras puras da ClickSign (Fase 4).
 * Cobre base URL por ambiente, montagem de URL/payloads, validação do HMAC do
 * webhook e interpretação do evento recebido.
 */
final class ClickSignRulesTest extends TestCase
{
    public function testBaseUrlPorAmbiente(): void
    {
        $this->assertStringContainsString('sandbox', ClickSignRules::baseUrl(true));
        $this->assertStringContainsString('app.clicksign.com', ClickSignRules::baseUrl(false));
    }

    public function testEndpointAnexaAccessTokenNaQuery(): void
    {
        $url = ClickSignRules::endpoint(true, '/api/v1/documents', 'tok 123');
        $this->assertStringContainsString('sandbox.clicksign.com/api/v1/documents', $url);
        $this->assertStringContainsString('access_token=tok%20123', $url); // rawurlencode
        // Quando o path já tem query, usa & em vez de ?.
        $url2 = ClickSignRules::endpoint(false, '/api/v1/x?a=1', 'tok');
        $this->assertStringContainsString('?a=1&access_token=tok', $url2);
    }

    public function testDocumentPayload(): void
    {
        $p = ClickSignRules::documentPayload('/c/doc.html', 'data:text/html;base64,AAA');
        $this->assertSame('/c/doc.html', $p['document']['path']);
        $this->assertSame('data:text/html;base64,AAA', $p['document']['content_base64']);
    }

    public function testSignerPayloadNormalizaTelefoneEAuth(): void
    {
        $p = ClickSignRules::signerPayload('a@b.com', 'Fulano', '+55 (41) 99999-8888', 'email');
        $this->assertSame('a@b.com', $p['signer']['email']);
        $this->assertSame(['email'], $p['signer']['auths']);
        $this->assertSame('5541999998888', $p['signer']['phone_number']);
        // Sem telefone: não inclui a chave.
        $p2 = ClickSignRules::signerPayload('a@b.com', 'Fulano');
        $this->assertArrayNotHasKey('phone_number', $p2['signer']);
    }

    public function testListPayload(): void
    {
        $p = ClickSignRules::listPayload('DOC', 'SGN', 'sign');
        $this->assertSame('DOC', $p['list']['document_key']);
        $this->assertSame('SGN', $p['list']['signer_key']);
        $this->assertSame('sign', $p['list']['sign_as']);
    }

    public function testVerifyWebhookSignature(): void
    {
        $body = '{"event":{"name":"auto_close"}}';
        $secret = 'segredo';
        $valid = hash_hmac('sha256', $body, $secret);
        $this->assertTrue(ClickSignRules::verifyWebhookSignature($body, $valid, $secret));
        // Aceita o prefixo "sha256=".
        $this->assertTrue(ClickSignRules::verifyWebhookSignature($body, 'sha256=' . $valid, $secret));
        // Assinatura errada / vazia / secret vazio: recusa.
        $this->assertFalse(ClickSignRules::verifyWebhookSignature($body, 'errado', $secret));
        $this->assertFalse(ClickSignRules::verifyWebhookSignature($body, '', $secret));
        $this->assertFalse(ClickSignRules::verifyWebhookSignature($body, $valid, ''));
    }

    public function testInterpretEvent(): void
    {
        $this->assertSame('signed', ClickSignRules::interpretEvent('auto_close'));
        $this->assertSame('signed', ClickSignRules::interpretEvent('close'));
        $this->assertSame('cancelled', ClickSignRules::interpretEvent('cancel'));
        // Eventos informativos não mudam o contrato.
        $this->assertSame('ignore', ClickSignRules::interpretEvent('sign'));
        $this->assertSame('ignore', ClickSignRules::interpretEvent('upload'));
        $this->assertSame('ignore', ClickSignRules::interpretEvent(null));
    }

    public function testIsConfigured(): void
    {
        $this->assertTrue(ClickSignRules::isConfigured('token'));
        $this->assertFalse(ClickSignRules::isConfigured(''));
        $this->assertFalse(ClickSignRules::isConfigured(null));
    }
}
