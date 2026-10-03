<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ProposalRules;

/**
 * Testes unitários das regras puras da Proposta/Orçamento (Fase 3).
 * Cobre cálculo de item/total, normalização de status/tipo, transições de
 * estado e validação da recusa.
 */
final class ProposalRulesTest extends TestCase
{
    // ---- status / tipo ----

    public function testNormalizeStatus(): void
    {
        $this->assertSame('draft', ProposalRules::normalizeStatus('draft'));
        $this->assertSame('accepted', ProposalRules::normalizeStatus('accepted'));
        $this->assertSame('draft', ProposalRules::normalizeStatus('qualquer'));
        $this->assertSame('draft', ProposalRules::normalizeStatus(''));
    }

    public function testNormalizeContractType(): void
    {
        $this->assertSame('dev_zero', ProposalRules::normalizeContractType('dev_zero'));
        $this->assertSame('suporte', ProposalRules::normalizeContractType('suporte'));
        $this->assertNull(ProposalRules::normalizeContractType('invalido'));
        $this->assertNull(ProposalRules::normalizeContractType(''));
    }

    public function testIsTerminal(): void
    {
        $this->assertTrue(ProposalRules::isTerminal('accepted'));
        $this->assertTrue(ProposalRules::isTerminal('rejected'));
        $this->assertTrue(ProposalRules::isTerminal('cancelled'));
        $this->assertFalse(ProposalRules::isTerminal('draft'));
        $this->assertFalse(ProposalRules::isTerminal('sent'));
    }

    // ---- transições ----

    public function testTransicoesValidasDoFluxo(): void
    {
        $this->assertTrue(ProposalRules::canTransition('draft', 'ready'));
        $this->assertTrue(ProposalRules::canTransition('ready', 'sent'));
        $this->assertTrue(ProposalRules::canTransition('sent', 'awaiting'));
        $this->assertTrue(ProposalRules::canTransition('sent', 'accepted'));
        $this->assertTrue(ProposalRules::canTransition('awaiting', 'rejected'));
        // Recusada pode voltar à elaboração (refazer).
        $this->assertTrue(ProposalRules::canTransition('rejected', 'draft'));
        // Mesmo estado é idempotente.
        $this->assertTrue(ProposalRules::canTransition('sent', 'sent'));
    }

    public function testTransicoesInvalidas(): void
    {
        // Não pode pular de draft direto para accepted.
        $this->assertFalse(ProposalRules::canTransition('draft', 'accepted'));
        // Terminal não transiciona.
        $this->assertFalse(ProposalRules::canTransition('accepted', 'draft'));
        $this->assertFalse(ProposalRules::canTransition('cancelled', 'sent'));
        // Estados inválidos.
        $this->assertFalse(ProposalRules::canTransition('x', 'draft'));
        $this->assertFalse(ProposalRules::canTransition('draft', 'y'));
    }

    // ---- cálculo ----

    public function testItemAmountHorasVezesValorHora(): void
    {
        // Exemplo da reunião: 3h x R$50 = R$150.
        $this->assertSame(150.0, ProposalRules::itemAmount(3, 50, null));
        $this->assertSame(500.0, ProposalRules::itemAmount(10, 50, null));
        // Com string BR no valor/hora.
        $this->assertSame(150.0, ProposalRules::itemAmount(3, 'R$ 50,00', null));
    }

    public function testItemAmountValorFixoQuandoSemHoras(): void
    {
        // Sem horas, usa o valor fixo.
        $this->assertSame(1200.0, ProposalRules::itemAmount(null, null, 1200));
        $this->assertSame(1200.0, ProposalRules::itemAmount(0, 0, 'R$ 1.200,00'));
        // Sem nada: zero.
        $this->assertSame(0.0, ProposalRules::itemAmount(null, null, null));
    }

    public function testTotalSomaItens(): void
    {
        $items = [
            ['hours' => 3, 'hourly_rate' => 50],           // 150
            ['hours' => 10, 'hourly_rate' => 50],          // 500
            ['amount' => 1200],                            // 1200 (valor fixo já calculado)
            ['description' => 'vazio'],                     // 0
        ];
        $this->assertSame(1850.0, ProposalRules::total($items));
    }

    public function testTotalListaVaziaEhZero(): void
    {
        $this->assertSame(0.0, ProposalRules::total([]));
    }

    // ---- recusa ----

    public function testSanitizeRejectReason(): void
    {
        $this->assertSame('Preço alto', ProposalRules::sanitizeRejectReason('  Preço alto  '));
        $this->assertNull(ProposalRules::sanitizeRejectReason('   '));
        $this->assertNull(ProposalRules::sanitizeRejectReason(''));
        $this->assertNull(ProposalRules::sanitizeRejectReason(null));
    }
}
