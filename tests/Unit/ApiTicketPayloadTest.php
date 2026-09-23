<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ApiController;

/**
 * Testes unitários da validação/normalização do payload da API de criação de
 * chamados. normalizePayload() e buildDescription() são estáticos e puros
 * (sem banco/rede).
 */
final class ApiTicketPayloadTest extends TestCase
{
    public function testTituloEDescricaoSaoObrigatorios(): void
    {
        $out = ApiController::normalizePayload(['title' => '', 'description' => '']);
        $fields = array_column($out['errors'], 'field');
        $this->assertContains('title', $fields);
        $this->assertContains('description', $fields);
        $this->assertSame('validation_error', $out['errors'][0]['code']);
    }

    public function testPayloadValidoNaoTemErros(): void
    {
        $out = ApiController::normalizePayload([
            'title' => 'Erro no sistema',
            'description' => 'Detalhes do erro',
            'priority' => 'high',
            'category' => 'suporte',
        ]);
        $this->assertEmpty($out['errors']);
        $this->assertSame('Erro no sistema', $out['title']);
        $this->assertSame('high', $out['priority']);
        $this->assertSame('suporte', $out['category']);
    }

    public function testPrioridadePadraoEhMedium(): void
    {
        $out = ApiController::normalizePayload(['title' => 'x', 'description' => 'y']);
        $this->assertSame('medium', $out['priority']);
        $this->assertEmpty($out['errors']);
    }

    public function testPrioridadeInvalidaGeraErro(): void
    {
        $out = ApiController::normalizePayload([
            'title' => 'x', 'description' => 'y', 'priority' => 'altissima',
        ]);
        $codes = array_column($out['errors'], 'code');
        $this->assertContains('invalid_value', $codes);
    }

    public function testCategoriaVaziaViraNull(): void
    {
        $out = ApiController::normalizePayload(['title' => 'x', 'description' => 'y', 'category' => '   ']);
        $this->assertNull($out['category']);
    }

    public function testExternalRefVazioViraNull(): void
    {
        $out = ApiController::normalizePayload(['title' => 'x', 'description' => 'y', 'external_ref' => '']);
        $this->assertNull($out['external_ref']);

        $out2 = ApiController::normalizePayload(['title' => 'x', 'description' => 'y', 'external_ref' => 'REF-1']);
        $this->assertSame('REF-1', $out2['external_ref']);
    }

    public function testTituloEhCortadoEm255(): void
    {
        $long = str_repeat('a', 300);
        $out = ApiController::normalizePayload(['title' => $long, 'description' => 'y']);
        $this->assertSame(255, mb_strlen($out['title']));
    }

    public function testBuildDescriptionAnexaSolicitante(): void
    {
        $desc = ApiController::buildDescription('Preciso de ajuda.', 'Maria', 'ACME');
        $this->assertStringContainsString('Solicitado por (API): Maria', $desc);
        $this->assertStringContainsString('Empresa informada: ACME', $desc);
        $this->assertStringContainsString('Preciso de ajuda.', $desc);
    }

    public function testBuildDescriptionSemSolicitanteMantemDescricao(): void
    {
        $desc = ApiController::buildDescription('Somente descrição.');
        $this->assertSame('Somente descrição.', $desc);
    }
}
