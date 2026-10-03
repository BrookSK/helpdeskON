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

    public function testRoleCanUseClientPin(): void
    {
        $this->assertTrue(ClientPinRules::roleCanUseClientPin('client'));
        $this->assertFalse(ClientPinRules::roleCanUseClientPin('super_admin'));
        $this->assertFalse(ClientPinRules::roleCanUseClientPin('developer'));
        $this->assertFalse(ClientPinRules::roleCanUseClientPin(null));
    }

    public function testCanAuthenticate(): void
    {
        $ok = ['role' => 'client', 'is_active' => 1, 'client_pin' => '6543'];
        $this->assertTrue(ClientPinRules::canAuthenticate($ok));
        // Papel errado.
        $this->assertFalse(ClientPinRules::canAuthenticate(['role' => 'super_admin', 'is_active' => 1, 'client_pin' => '6543']));
        // Inativo.
        $this->assertFalse(ClientPinRules::canAuthenticate(['role' => 'client', 'is_active' => 0, 'client_pin' => '6543']));
        // PIN inválido.
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
