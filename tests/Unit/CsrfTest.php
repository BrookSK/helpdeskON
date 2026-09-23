<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Testes dos helpers de CSRF (csrf_token/csrf_field/verify_csrf), que operam
 * sobre $_SESSION. Garante geração estável do token, comparação timing-safe e
 * rejeição de token ausente/errado.
 */
final class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        // Ambiente de teste roda em CLI sem sessão HTTP; usamos o array $_SESSION
        // diretamente (os helpers só leem/escrevem essa superglobal).
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testCsrfTokenGeraEReutilizaOMesmoValor(): void
    {
        $t1 = csrf_token();
        $this->assertNotEmpty($t1);
        $this->assertSame(64, strlen($t1), 'token deve ter 32 bytes em hex (64 chars)');
        // Segunda chamada reaproveita o mesmo token da sessão.
        $this->assertSame($t1, csrf_token());
        $this->assertSame($t1, $_SESSION['csrf_token']);
    }

    public function testVerifyCsrfAceitaTokenCorreto(): void
    {
        $t = csrf_token();
        $this->assertTrue(verify_csrf($t));
    }

    public function testVerifyCsrfRejeitaTokenErrado(): void
    {
        csrf_token();
        $this->assertFalse(verify_csrf('token-errado'));
        $this->assertFalse(verify_csrf(''));
    }

    public function testVerifyCsrfSemTokenNaSessaoRejeita(): void
    {
        // Sessão sem token: qualquer valor é rejeitado.
        unset($_SESSION['csrf_token']);
        $this->assertFalse(verify_csrf('qualquer'));
        $this->assertFalse(verify_csrf(''));
    }

    public function testCsrfFieldContemOTokenAtual(): void
    {
        $t = csrf_token();
        $html = csrf_field();
        $this->assertStringContainsString('name="csrf_token"', $html);
        $this->assertStringContainsString($t, $html);
        $this->assertStringContainsString('type="hidden"', $html);
    }
}
