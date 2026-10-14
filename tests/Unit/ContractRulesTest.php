<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ContractRules;

/**
 * Testes unitários das regras puras do Contrato (Fase 4).
 * Cobre estados, transições do fluxo em duas etapas, edição e recusa.
 */
final class ContractRulesTest extends TestCase
{
    public function testNormalizeStatus(): void
    {
        $this->assertSame('draft', ContractRules::normalizeStatus('draft'));
        $this->assertSame('signed', ContractRules::normalizeStatus('signed'));
        $this->assertSame('draft', ContractRules::normalizeStatus('qualquer'));
    }

    public function testIsTerminal(): void
    {
        $this->assertTrue(ContractRules::isTerminal('signed'));
        $this->assertTrue(ContractRules::isTerminal('cancelled'));
        $this->assertFalse(ContractRules::isTerminal('draft'));
        $this->assertFalse(ContractRules::isTerminal('awaiting_signature'));
    }

    public function testFluxoDuasEtapasTransicoesValidas(): void
    {
        // draft -> review -> approved -> awaiting -> signed
        $this->assertTrue(ContractRules::canTransition('draft', 'client_review'));
        $this->assertTrue(ContractRules::canTransition('client_review', 'approved'));
        $this->assertTrue(ContractRules::canTransition('approved', 'awaiting_signature'));
        $this->assertTrue(ContractRules::canTransition('awaiting_signature', 'signed'));
        // review -> rejeitado -> volta a draft
        $this->assertTrue(ContractRules::canTransition('client_review', 'client_rejected'));
        $this->assertTrue(ContractRules::canTransition('client_rejected', 'draft'));
    }

    public function testTransicoesInvalidas(): void
    {
        // Não pode assinar sem passar por aprovado/aguardando.
        $this->assertFalse(ContractRules::canTransition('draft', 'signed'));
        $this->assertFalse(ContractRules::canTransition('client_review', 'awaiting_signature'));
        // Não pode enviar à assinatura antes de aprovar.
        $this->assertFalse(ContractRules::canTransition('client_review', 'signed'));
        // Terminais não transicionam.
        $this->assertFalse(ContractRules::canTransition('signed', 'draft'));
        $this->assertFalse(ContractRules::canTransition('cancelled', 'draft'));
    }

    public function testCancelarEhPermitidoEnquantoNaoTerminal(): void
    {
        foreach (['draft','client_review','approved','awaiting_signature','client_rejected'] as $st) {
            $this->assertTrue(ContractRules::canTransition($st, 'cancelled'), "deveria cancelar de {$st}");
        }
    }

    public function testCanEditBody(): void
    {
        $this->assertTrue(ContractRules::canEditBody('draft'));
        $this->assertTrue(ContractRules::canEditBody('client_rejected'));
        // Em revisão/aprovado/assinatura não edita mais.
        $this->assertFalse(ContractRules::canEditBody('client_review'));
        $this->assertFalse(ContractRules::canEditBody('approved'));
        $this->assertFalse(ContractRules::canEditBody('awaiting_signature'));
        $this->assertFalse(ContractRules::canEditBody('signed'));
    }

    public function testCanSendToSignatureSoQuandoAprovado(): void
    {
        $this->assertTrue(ContractRules::canSendToSignature('approved'));
        $this->assertFalse(ContractRules::canSendToSignature('client_review'));
        $this->assertFalse(ContractRules::canSendToSignature('draft'));
    }

    public function testSanitizeRejectReason(): void
    {
        $this->assertSame('Trocar cláusula 3', ContractRules::sanitizeRejectReason(' Trocar cláusula 3 '));
        $this->assertNull(ContractRules::sanitizeRejectReason('  '));
        $this->assertNull(ContractRules::sanitizeRejectReason(null));
    }
}
