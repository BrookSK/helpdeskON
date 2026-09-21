<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use MarketingRules;

/**
 * Testes unitários das regras puras do módulo de Marketing (sem banco).
 * Cobrem whitelist de status, permissão por perfil, exigência de imagem,
 * anti-retrocesso e retorno para aprovação.
 */
final class MarketingRulesTest extends TestCase
{
    // ---- whitelist / normalização ----

    public function testStatusValidoEInvalido(): void
    {
        $this->assertTrue(MarketingRules::isValidStatus('aprovado'));
        $this->assertTrue(MarketingRules::isValidStatus('rascunho'));
        $this->assertFalse(MarketingRules::isValidStatus('inexistente'));
        $this->assertFalse(MarketingRules::isValidStatus(''));
    }

    public function testNormalizeStatusUsaDefaultQuandoInvalido(): void
    {
        $this->assertSame('ideia', MarketingRules::normalizeStatus('xpto'));
        $this->assertSame('rascunho', MarketingRules::normalizeStatus('xpto', 'rascunho'));
        $this->assertSame('publicado', MarketingRules::normalizeStatus('publicado'));
    }

    public function testListaDeStatusCobreOFluxo(): void
    {
        $esperado = ['rascunho', 'ideia', 'em_producao', 'aguardando_aprovacao', 'aprovado', 'agendado', 'publicado', 'rejeitado'];
        $this->assertSame($esperado, MarketingRules::STATUSES);
    }

    public function testLabelAmigavel(): void
    {
        $this->assertSame('Aguardando aprovação', MarketingRules::label('aguardando_aprovacao'));
        $this->assertSame('desconhecido', MarketingRules::label('desconhecido'));
    }

    // ---- permissão por perfil ----

    public function testAdminPodeQualquerStatusValido(): void
    {
        $this->assertTrue(MarketingRules::canSetStatus('super_admin', 'aprovado'));
        $this->assertTrue(MarketingRules::canSetStatus('super_admin', 'rejeitado'));
        $this->assertTrue(MarketingRules::canSetStatus('super_admin', 'publicado'));
        $this->assertFalse(MarketingRules::canSetStatus('super_admin', 'inexistente'));
    }

    public function testMarketingNaoPodeAprovarNemRejeitar(): void
    {
        $this->assertFalse(MarketingRules::canSetStatus('marketing', 'aprovado'));
        $this->assertFalse(MarketingRules::canSetStatus('marketing', 'rejeitado'));
    }

    public function testMarketingPodeStatusDeProducao(): void
    {
        $this->assertTrue(MarketingRules::canSetStatus('marketing', 'ideia'));
        $this->assertTrue(MarketingRules::canSetStatus('marketing', 'em_producao'));
        $this->assertTrue(MarketingRules::canSetStatus('marketing', 'aguardando_aprovacao'));
        $this->assertTrue(MarketingRules::canSetStatus('marketing', 'rascunho'));
    }

    // ---- exigência de imagem ----

    public function testStatusQueExigemImagem(): void
    {
        $this->assertTrue(MarketingRules::requiresImage('em_producao'));
        $this->assertTrue(MarketingRules::requiresImage('aguardando_aprovacao'));
        $this->assertTrue(MarketingRules::requiresImage('publicado'));
    }

    public function testStatusQueNaoExigemImagem(): void
    {
        $this->assertFalse(MarketingRules::requiresImage('rascunho'));
        $this->assertFalse(MarketingRules::requiresImage('ideia'));
    }

    // ---- anti-retrocesso ----

    public function testRetrocessoDeAprovadoParaProducaoEhDetectado(): void
    {
        $this->assertTrue(MarketingRules::isBackwardFromApproved('aprovado', 'em_producao'));
        $this->assertTrue(MarketingRules::isBackwardFromApproved('publicado', 'aguardando_aprovacao'));
        $this->assertTrue(MarketingRules::isBackwardFromApproved('agendado', 'ideia'));
    }

    public function testAvancoEntreStatusPosAprovacaoNaoEhRetrocesso(): void
    {
        $this->assertFalse(MarketingRules::isBackwardFromApproved('aprovado', 'agendado'));
        $this->assertFalse(MarketingRules::isBackwardFromApproved('agendado', 'publicado'));
    }

    public function testAvancoAPartirDeProducaoNaoEhRetrocesso(): void
    {
        $this->assertFalse(MarketingRules::isBackwardFromApproved('em_producao', 'aguardando_aprovacao'));
        $this->assertFalse(MarketingRules::isBackwardFromApproved('ideia', 'em_producao'));
    }

    // ---- retorno para aprovação ----

    public function testSoAprovadoOuAgendadoPodemVoltarParaAprovacao(): void
    {
        $this->assertTrue(MarketingRules::canReturnToApproval('aprovado'));
        $this->assertTrue(MarketingRules::canReturnToApproval('agendado'));
        $this->assertFalse(MarketingRules::canReturnToApproval('publicado'));
        $this->assertFalse(MarketingRules::canReturnToApproval('em_producao'));
        $this->assertFalse(MarketingRules::canReturnToApproval('ideia'));
    }

    // ---- detecção de imagem em anexo ----

    public function testDeteccaoDeImagemPorExtensao(): void
    {
        $this->assertTrue(MarketingRules::isImageAttachment('arte.png'));
        $this->assertTrue(MarketingRules::isImageAttachment('foto.JPEG'));
        $this->assertTrue(MarketingRules::isImageAttachment('banner.webp'));
        $this->assertFalse(MarketingRules::isImageAttachment('documento.pdf'));
        $this->assertFalse(MarketingRules::isImageAttachment('planilha.xlsx'));
    }

    public function testDeteccaoDeImagemPorMime(): void
    {
        $this->assertTrue(MarketingRules::isImageAttachment('arquivo_sem_ext', 'image/png'));
        $this->assertFalse(MarketingRules::isImageAttachment('arquivo_sem_ext', 'application/pdf'));
        $this->assertFalse(MarketingRules::isImageAttachment(null, null));
    }
}
