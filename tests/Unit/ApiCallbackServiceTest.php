<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ApiCallbackService;

/**
 * Testes unitários dos métodos PUROS do ApiCallbackService (sem banco/rede):
 *  - shouldNotify(): decide se uma transição de status gera callback;
 *  - isValidCallbackUrl(): valida a URL de callback;
 *  - buildStatusPayload(): monta o corpo do POST de callback.
 *
 * A parte de enfileiramento real (enqueueStatusChange, que toca o banco) é
 * coberta pelo teste de integração ApiCallbackQueueTest.
 */
final class ApiCallbackServiceTest extends TestCase
{
    // ===== shouldNotify =====

    public function testNaoNotificaQuandoStatusNaoMuda(): void
    {
        $this->assertFalse(ApiCallbackService::shouldNotify('open', 'open'));
        $this->assertFalse(ApiCallbackService::shouldNotify('in_progress', 'in_progress'));
    }

    public function testNaoNotificaNaCriacaoQuandoNaoHaStatusAnterior(): void
    {
        // previous = null representa a primeira definição (criação); a criação
        // é confirmada pela resposta 201 do POST, não por callback.
        $this->assertFalse(ApiCallbackService::shouldNotify(null, 'open'));
    }

    public function testNaoNotificaComNovoStatusVazio(): void
    {
        $this->assertFalse(ApiCallbackService::shouldNotify('open', ''));
    }

    public function testNotificaQuandoStatusMuda(): void
    {
        $this->assertTrue(ApiCallbackService::shouldNotify('open', 'in_progress'));
        $this->assertTrue(ApiCallbackService::shouldNotify('in_progress', 'completed'));
    }

    // ===== isValidCallbackUrl =====

    public function testUrlValidaHttpEHttps(): void
    {
        $this->assertTrue(ApiCallbackService::isValidCallbackUrl('https://exemplo.com/callback'));
        $this->assertTrue(ApiCallbackService::isValidCallbackUrl('http://exemplo.com/lrv'));
    }

    public function testUrlInvalidaOuVazia(): void
    {
        $this->assertFalse(ApiCallbackService::isValidCallbackUrl(''));
        $this->assertFalse(ApiCallbackService::isValidCallbackUrl('   '));
        $this->assertFalse(ApiCallbackService::isValidCallbackUrl('nao-e-url'));
        // Esquema não http(s) é recusado.
        $this->assertFalse(ApiCallbackService::isValidCallbackUrl('ftp://exemplo.com'));
    }

    public function testUrlAcimaDoLimiteEhRecusada(): void
    {
        $longUrl = 'https://exemplo.com/' . str_repeat('a', 600);
        $this->assertFalse(ApiCallbackService::isValidCallbackUrl($longUrl));
    }

    // ===== buildStatusPayload =====

    public function testPayloadContemCamposEsperados(): void
    {
        $ticket = [
            'id' => 4821,
            'client_ticket_number' => 37,
            'external_ref' => 'PUNTACANA-2026-000123',
        ];
        $payload = ApiCallbackService::buildStatusPayload($ticket, 'open', 'in_progress');

        $this->assertSame(ApiCallbackService::EVENT_STATUS_CHANGED, $payload['event']);
        $this->assertSame(4821, $payload['id']);
        $this->assertSame(37, $payload['client_ticket_number']);
        $this->assertSame('PUNTACANA-2026-000123', $payload['external_ref']);
        $this->assertSame('open', $payload['previous_status']);
        $this->assertSame('in_progress', $payload['status']);
        $this->assertArrayHasKey('changed_at', $payload);
    }

    public function testPayloadComExternalRefNuloEClientNumberNulo(): void
    {
        $ticket = ['id' => 10, 'client_ticket_number' => null, 'external_ref' => null];
        $payload = ApiCallbackService::buildStatusPayload($ticket, null, 'completed');

        $this->assertNull($payload['external_ref']);
        $this->assertNull($payload['client_ticket_number']);
        $this->assertNull($payload['previous_status']);
        $this->assertSame('completed', $payload['status']);
        $this->assertSame(10, $payload['id']);
    }
}
