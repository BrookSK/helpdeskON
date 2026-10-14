<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Router;
use ReflectionMethod;

/**
 * Garante que o Router resolve o arquivo do controller mesmo quando o segmento
 * da URL (minúsculo) não bate exatamente com o nome camelCase do arquivo.
 *
 * Causa raiz do bug "/servicecatalog cai no dashboard": em FS case-sensitive
 * (produção Linux), "ServicecatalogController.php" (gerado por ucfirst) não
 * existe — o arquivo real é "ServiceCatalogController.php". Sem resolução
 * case-insensitive, o Router caía no fallback (LoginController) e o usuário
 * logado terminava no dashboard.
 */
class RouterTest extends TestCase
{
    private function callResolve(string $wanted): ?string
    {
        $router = new Router();
        $m = new ReflectionMethod(Router::class, 'resolveControllerFile');
        return $m->invoke($router, $wanted);
    }

    public function testResolveControllerCamelCaseAPartirDeSegmentoMinusculo(): void
    {
        // "servicecatalog" -> ucfirst -> "ServicecatalogController" (não existe)
        $resolved = $this->callResolve('ServicecatalogController');
        $this->assertSame('ServiceCatalogController', $resolved);
    }

    public function testResolveControllerSimplesContinuaFuncionando(): void
    {
        $this->assertSame('DashboardController', $this->callResolve('DashboardController'));
        $this->assertSame('VideocallController', $this->callResolve('VideocallController'));
    }

    public function testControllerInexistenteRetornaNull(): void
    {
        $this->assertNull($this->callResolve('NaoExisteController'));
    }
}
