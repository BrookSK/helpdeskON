<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ClientPinRules;

/**
 * Testes unitários das regras puras do PIN de login do cliente (Fase 9).
 * Cobre: formato 6 dígitos (distinto do PIN de equipe de 4), normalização,
 * papel permitido e condição de autenticação.
 */
final class ClientPinRulesTest extends TestCase
{
    public function testFormato6Digitos(): void
    {
        $this->assertTrue(ClientPinRules::isValidFormat('123456'));
        $this->assertFalse(ClientPinRules::isValidFormat('1234'));   // PIN de equipe (4) não serve
        $this->assertFalse(ClientPinRules::isValidFormat('12345'));
        $this->assertFalse(ClientPinRules::isValidFormat('abcdef'));
        $this->assertFalse(ClientPinRules::isValidFormat(null));
        $this->assertFalse(ClientPinRules::isValidFormat(''));
    }

    public function testNormalize(): void
    {
        $this->assertSame('123456', ClientPinRules::normalize('  123456  '));
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
        $ok = ['role' => 'client', 'is_active' => 1, 'client_pin' => '654321'];
        $this->assertTrue(ClientPinRules::canAuthenticate($ok));
        // Papel errado.
        $this->assertFalse(ClientPinRules::canAuthenticate(['role' => 'super_admin', 'is_active' => 1, 'client_pin' => '654321']));
        // Inativo.
        $this->assertFalse(ClientPinRules::canAuthenticate(['role' => 'client', 'is_active' => 0, 'client_pin' => '654321']));
        // PIN inválido.
        $this->assertFalse(ClientPinRules::canAuthenticate(['role' => 'client', 'is_active' => 1, 'client_pin' => '12']));
        // Null.
        $this->assertFalse(ClientPinRules::canAuthenticate(null));
    }

    public function testGenerateTem6Digitos(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $pin = ClientPinRules::generate();
            $this->assertSame(6, strlen($pin));
            $this->assertTrue(ClientPinRules::isValidFormat($pin));
        }
    }
}
