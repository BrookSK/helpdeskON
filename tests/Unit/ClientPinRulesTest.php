<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ClientPinRules;

/**
 * Testes unitários das regras puras do PIN de login do cliente.
 * Cobre: formato 4 dígitos, normalização, papel permitido e condição de
 * autenticação. (O PIN do cliente e o PIN de equipe têm 4 dígitos, mas são
 * colunas/fluxos de login distintos.)
 */
final class ClientPinRulesTest extends TestCase
{
    public function testFormato4Digitos(): void
    {
        $this->assertTrue(ClientPinRules::isValidFormat('1234'));
        $this->assertFalse(ClientPinRules::isValidFormat('123'));
        $this->assertFalse(ClientPinRules::isValidFormat('12345'));
        $this->assertFalse(ClientPinRules::isValidFormat('abcd'));
        $this->assertFalse(ClientPinRules::isValidFormat(null));
        $this->assertFalse(ClientPinRules::isValidFormat(''));
    }

    public function testNormalize(): void
    {
        $this->assertSame('1234', ClientPinRules::normalize('  1234  '));
        $this->assertSame('', ClientPinRules::normalize('12'));
    }

    public function testRoleCanUseClientPinAceitaQualquerPapel(): void
    {
        // O PIN é por usuário: qualquer papel definido pode usá-lo.
        $this->assertTrue(ClientPinRules::roleCanUseClientPin('client'));
        $this->assertTrue(ClientPinRules::roleCanUseClientPin('super_admin'));
        $this->assertTrue(ClientPinRules::roleCanUseClientPin('developer'));
        // Sem papel definido não vale.
        $this->assertFalse(ClientPinRules::roleCanUseClientPin(null));
        $this->assertFalse(ClientPinRules::roleCanUseClientPin(''));
    }

    public function testCanAuthenticate(): void
    {
        // Qualquer papel ativo com PIN válido autentica.
        $this->assertTrue(ClientPinRules::canAuthenticate(['role' => 'client', 'is_active' => 1, 'client_pin' => '6543']));
        $this->assertTrue(ClientPinRules::canAuthenticate(['role' => 'super_admin', 'is_active' => 1, 'client_pin' => '6543']));
        $this->assertTrue(ClientPinRules::canAuthenticate(['role' => 'developer', 'is_active' => 1, 'client_pin' => '1234']));
        // Inativo não autentica.
        $this->assertFalse(ClientPinRules::canAuthenticate(['role' => 'client', 'is_active' => 0, 'client_pin' => '6543']));
        // PIN inválido não autentica.
        $this->assertFalse(ClientPinRules::canAuthenticate(['role' => 'client', 'is_active' => 1, 'client_pin' => '12']));
        // Null.
        $this->assertFalse(ClientPinRules::canAuthenticate(null));
    }

    public function testGenerateTem4Digitos(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $pin = ClientPinRules::generate();
            $this->assertSame(4, strlen($pin));
            $this->assertTrue(ClientPinRules::isValidFormat($pin));
        }
    }
}
