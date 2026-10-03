<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ProviderRules;

/**
 * Testes unitários das regras puras da contratação de prestadores (Fase 8).
 * Cobre: máquina de estados, normalizações, e a regra de ouro do encerramento
 * (só encerra com todos os acessos revogados).
 */
final class ProviderRulesTest extends TestCase
{
    public function testNormalizadores(): void
    {
        $this->assertSame('prospect', ProviderRules::normalizeStatus('xyz'));
        $this->assertSame('pj', ProviderRules::normalizeEngagement('nope'));
        $this->assertSame('clt', ProviderRules::normalizeEngagement('clt'));
        $this->assertNull(ProviderRules::normalizeWorkModel('nope'));
        $this->assertSame('remoto', ProviderRules::normalizeWorkModel('remoto'));
        $this->assertNull(ProviderRules::normalizePayType('nope'));
        $this->assertSame('hora', ProviderRules::normalizePayType('hora'));
    }

    public function testTransicoesValidas(): void
    {
        $this->assertTrue(ProviderRules::canTransition('prospect', 'proposal'));
        $this->assertTrue(ProviderRules::canTransition('proposal', 'contract'));
        $this->assertTrue(ProviderRules::canTransition('contract', 'active'));
        $this->assertTrue(ProviderRules::canTransition('active', 'terminated'));
        // Retrocesso permitido no funil.
        $this->assertTrue(ProviderRules::canTransition('proposal', 'prospect'));
        // Cancelar de não-terminal.
        $this->assertTrue(ProviderRules::canTransition('contract', 'cancelled'));
    }

    public function testTransicoesInvalidas(): void
    {
        $this->assertFalse(ProviderRules::canTransition('prospect', 'active')); // pula etapas
        $this->assertFalse(ProviderRules::canTransition('active', 'cancelled')); // ativo só encerra
        $this->assertFalse(ProviderRules::canTransition('terminated', 'active')); // terminal
        $this->assertFalse(ProviderRules::canTransition('cancelled', 'prospect'));
    }

    public function testIsTerminal(): void
    {
        $this->assertTrue(ProviderRules::isTerminal('terminated'));
        $this->assertTrue(ProviderRules::isTerminal('cancelled'));
        $this->assertFalse(ProviderRules::isTerminal('active'));
    }

    public function testCanTerminateExigeTodosAcessosRevogados(): void
    {
        $accesses = [
            ['access_label' => 'GitHub', 'revoked_at' => '2026-10-03 10:00:00'],
            ['access_label' => 'Servidor', 'revoked_at' => null],
        ];
        // Tem acesso ativo -> não encerra.
        $this->assertFalse(ProviderRules::canTerminate('active', $accesses));
        $this->assertSame(['Servidor'], ProviderRules::pendingAccesses($accesses));

        // Revoga o pendente -> pode encerrar.
        $accesses[1]['revoked_at'] = '2026-10-03 10:05:00';
        $this->assertTrue(ProviderRules::canTerminate('active', $accesses));
        $this->assertSame([], ProviderRules::pendingAccesses($accesses));

        // Sem acessos: pode encerrar (ativo).
        $this->assertTrue(ProviderRules::canTerminate('active', []));
        // Mas só se estiver ativo.
        $this->assertFalse(ProviderRules::canTerminate('contract', []));
    }

    public function testSanitizeReason(): void
    {
        $this->assertSame('fim de contrato', ProviderRules::sanitizeReason('  fim de contrato  '));
        $this->assertSame(1000, mb_strlen(ProviderRules::sanitizeReason(str_repeat('x', 2000))));
    }

    public function testDefaultAccessLabels(): void
    {
        $labels = ProviderRules::defaultAccessLabels();
        $this->assertContains('GitHub', $labels);
        $this->assertContains('Helpdesk', $labels);
    }
}
