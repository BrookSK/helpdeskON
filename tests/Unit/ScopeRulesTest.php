<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ScopeRules;

/**
 * Testes unitários das regras puras de aprovação de ESCOPO (ScopeRules).
 * Cobrem: completude do escopo, janela de decisão do cliente, exigência de
 * motivo na recusa e a resolução da decisão (aprovar/recusar).
 */
final class ScopeRulesTest extends TestCase
{
    public function testEscopoCompletoExigeOQueSeraFeito(): void
    {
        $this->assertTrue(ScopeRules::isScopeComplete(['escopo_incluido' => 'Tela de login nova']));
        $this->assertFalse(ScopeRules::isScopeComplete(['escopo_incluido' => '   ']));
        $this->assertFalse(ScopeRules::isScopeComplete([]));
    }

    public function testClientePodeDecidirSomenteAguardandoEscopo(): void
    {
        $this->assertTrue(ScopeRules::clientCanDecideScope('aguardando_aprovacao_escopo'));
        $this->assertFalse(ScopeRules::clientCanDecideScope('in_progress'));
        $this->assertFalse(ScopeRules::clientCanDecideScope('em_homologacao'));
        $this->assertFalse(ScopeRules::clientCanDecideScope(null));
    }

    public function testSanitizaMotivoDeRecusa(): void
    {
        $this->assertSame('Faltou X', ScopeRules::sanitizeRejectionReason('  Faltou X  '));
        $this->assertNull(ScopeRules::sanitizeRejectionReason('   '));
        $this->assertNull(ScopeRules::sanitizeRejectionReason(''));
        $this->assertNull(ScopeRules::sanitizeRejectionReason(null));
    }

    public function testAprovarEscopoVaiParaInProgress(): void
    {
        $r = ScopeRules::resolveDecision('approve');
        $this->assertTrue($r['ok']);
        $this->assertSame('in_progress', $r['status']);
        $this->assertNull($r['reason']);
        $this->assertNull($r['error']);
    }

    public function testRecusarEscopoExigeMotivo(): void
    {
        // Sem motivo -> erro.
        $semMotivo = ScopeRules::resolveDecision('reject', '   ');
        $this->assertFalse($semMotivo['ok']);
        $this->assertNotNull($semMotivo['error']);

        // Com motivo -> volta para in_progress carregando o motivo saneado.
        $comMotivo = ScopeRules::resolveDecision('reject', '  Mudar o layout  ');
        $this->assertTrue($comMotivo['ok']);
        $this->assertSame('in_progress', $comMotivo['status']);
        $this->assertSame('Mudar o layout', $comMotivo['reason']);
    }

    public function testDecisaoInvalida(): void
    {
        $r = ScopeRules::resolveDecision('talvez');
        $this->assertFalse($r['ok']);
        $this->assertNotNull($r['error']);
    }
}
