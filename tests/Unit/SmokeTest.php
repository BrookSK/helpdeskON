<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Teste de fumaça: confirma que o ambiente de testes está configurado
 * (bootstrap carregado, constantes definidas, autoload funcionando).
 */
final class SmokeTest extends TestCase
{
    public function testConstantesBaseDefinidas(): void
    {
        $this->assertTrue(defined('BASE_PATH'), 'BASE_PATH deve estar definida pelo bootstrap');
        $this->assertTrue(defined('APP_PATH'), 'APP_PATH deve estar definida pelo bootstrap');
    }

    public function testAmbienteEhTesting(): void
    {
        $this->assertSame('testing', getenv('APP_ENV'));
    }

    public function testMatematicaBasica(): void
    {
        $this->assertSame(5, 2 + 3);
    }
}
