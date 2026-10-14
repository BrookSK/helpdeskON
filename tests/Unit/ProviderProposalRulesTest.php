<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ProviderProposalRules;

/**
 * Testes das regras puras da proposta do prestador: checklist de documentos por
 * tipo de contratação (CLT vs PJ), documentos faltantes, saneamento de recusa e
 * mensagens.
 */
final class ProviderProposalRulesTest extends TestCase
{
    public function testRequiredDocumentsClt(): void
    {
        $keys = array_column(ProviderProposalRules::requiredDocuments('clt'), 'key');
        $this->assertContains('ctps', $keys);          // admissional CLT
        $this->assertContains('exame_admissional', $keys);
        $this->assertNotContains('cnpj', $keys);        // CNPJ é de PJ
    }

    public function testRequiredDocumentsPj(): void
    {
        $keys = array_column(ProviderProposalRules::requiredDocuments('pj'), 'key');
        $this->assertContains('cnpj', $keys);
        $this->assertContains('contrato_social', $keys);
        $this->assertNotContains('ctps', $keys);        // CTPS é de CLT
    }

    public function testRequiredDocumentsOutroTemMinimo(): void
    {
        $keys = array_column(ProviderProposalRules::requiredDocuments('freelancer'), 'key');
        $this->assertContains('rg_cpf', $keys);
        $this->assertContains('dados_bancarios', $keys);
    }

    public function testDocumentChecklistMarcaPresentes(): void
    {
        $docs = [
            ['doc_type' => 'cnpj'],
            ['doc_type' => 'contrato_social'],
        ];
        $checklist = ProviderProposalRules::documentChecklist('pj', $docs);
        $byKey = [];
        foreach ($checklist as $c) $byKey[$c['key']] = $c['present'];
        $this->assertTrue($byKey['cnpj']);
        $this->assertTrue($byKey['contrato_social']);
        $this->assertFalse($byKey['dados_bancarios']); // não enviado
    }

    public function testMissingDocuments(): void
    {
        $docs = [['doc_type' => 'cnpj']];
        $missing = ProviderProposalRules::missingDocuments('pj', $docs);
        $this->assertNotEmpty($missing);
        $this->assertContains('Contrato social / MEI', $missing);
        // Com todos presentes, não falta nada.
        $all = [['doc_type' => 'cnpj'], ['doc_type' => 'contrato_social'], ['doc_type' => 'rg_cpf_socio'], ['doc_type' => 'dados_bancarios']];
        $this->assertSame([], ProviderProposalRules::missingDocuments('pj', $all));
    }

    public function testSanitizeRejectReason(): void
    {
        $this->assertNull(ProviderProposalRules::sanitizeRejectReason('   '));
        $this->assertNull(ProviderProposalRules::sanitizeRejectReason(''));
        $this->assertSame('valor baixo', ProviderProposalRules::sanitizeRejectReason('  valor baixo '));
    }

    public function testProposalWhatsappContemLinkEFuncao(): void
    {
        $msg = ProviderProposalRules::proposalWhatsapp('Ana', 'https://x/p/abc', 'Dev PHP', 'ON');
        $this->assertStringContainsString('Ana', $msg);
        $this->assertStringContainsString('https://x/p/abc', $msg);
        $this->assertStringContainsString('Dev PHP', $msg);
        $this->assertStringContainsString('ON', $msg);
    }

    public function testProposalEmailSubjectEBody(): void
    {
        $this->assertStringContainsString('ON', ProviderProposalRules::proposalEmailSubject('ON'));
        $body = ProviderProposalRules::proposalEmailBody('<b>x</b>', 'https://x/p', 'Dev');
        $this->assertStringContainsString('https://x/p', $body);
        $this->assertStringNotContainsString('<b>x</b>', $body); // nome escapado
    }
}
