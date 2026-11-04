<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ContractTemplateVars;
use ContractRules;

/**
 * Variáveis novas do contrato (change 02, tarefa 3.1) e conferência do PDF
 * assinado e do documento (3.2 e 3.4).
 */
final class ContractTemplateVarsTest extends TestCase
{
    public function testDocumentoEnderecoItensECondicao(): void
    {
        $proposal = [
            'client_name' => 'ACME', 'client_document' => '12.345.678/0001-90', 'client_address' => 'Rua A, 10',
            'content' => json_encode(['payment_method' => '30% na assinatura']),
        ];
        $items = [
            ['description' => 'Site <institucional>', 'quantity' => 1, 'amount' => 10000, 'billing' => 'once'],
            ['description' => 'Hospedagem', 'quantity' => 2, 'amount' => 500, 'billing' => 'monthly'],
        ];
        $v = ContractTemplateVars::valuesFromProposal($proposal, 'Prestador', 'ACME Ltda', $items, 'padrão das configurações');
        $this->assertSame('12.345.678/0001-90', $v['cliente_documento']);
        $this->assertSame('Rua A, 10', $v['cliente_endereco']);
        $this->assertSame('30% na assinatura', $v['condicao_pagamento']);
        $this->assertStringContainsString('Site &lt;institucional&gt;', $v['proposta_itens']);
        $this->assertStringContainsString('R$ 10.000,00', $v['proposta_itens']);
        $this->assertStringContainsString('R$ 500,00/mês', $v['proposta_itens']);
        $this->assertStringContainsString('<td style="text-align:center">2</td>', $v['proposta_itens']);

        $body = ContractTemplateVars::render('{{cliente_documento}} | {{ condicao_pagamento }}', $v);
        $this->assertSame('12.345.678/0001-90 | 30% na assinatura', $body);
    }

    public function testCondicaoCaiNoPadraoDasConfiguracoesEDepoisACombinar(): void
    {
        $this->assertSame('Conforme configuração', ContractTemplateVars::paymentCondition(['content' => '{}'], 'Conforme configuração'));
        $this->assertSame('a combinar', ContractTemplateVars::paymentCondition([], ''));
        $this->assertSame('', ContractTemplateVars::itemsTable([]));
    }

    public function testCatalogoListaAsVariaveisNovas(): void
    {
        $cat = ContractTemplateVars::catalog();
        foreach (['cliente_documento', 'cliente_endereco', 'proposta_itens', 'condicao_pagamento'] as $k) {
            $this->assertArrayHasKey($k, $cat);
        }
    }

    public function testArquivoAssinado(): void
    {
        $this->assertNull(ContractRules::signedFileError(1000, '%PDF-'));
        $this->assertSame('O arquivo não é um PDF.', ContractRules::signedFileError(1000, 'PK' . "\x03\x04x"));
        $this->assertSame('O arquivo passa de 20 MB.', ContractRules::signedFileError(20 * 1024 * 1024 + 1, '%PDF-'));
        $this->assertNotNull(ContractRules::signedFileError(0, ''));
    }

    public function testDocumentoEUploadPorStatus(): void
    {
        $this->assertTrue(ContractRules::hasClientDocument('123.456.789-01'));
        $this->assertTrue(ContractRules::hasClientDocument('12.345.678/0001-90'));
        $this->assertFalse(ContractRules::hasClientDocument('123'));
        $this->assertFalse(ContractRules::hasClientDocument(null));
        $this->assertTrue(ContractRules::canTransition('approved', 'signed'));
        $this->assertTrue(ContractRules::canUploadSigned('awaiting_signature'));
        $this->assertFalse(ContractRules::canUploadSigned('draft'));
        $this->assertFalse(ContractRules::canUploadSigned('cancelled'));
    }
}
