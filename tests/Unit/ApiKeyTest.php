<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ApiKey;

/**
 * Testes unitários da geração de API Keys. generatePlainKey() é estático e puro
 * (não toca banco). No modelo simplificado, a chave é uma string única com o
 * prefixo padrão.
 */
final class ApiKeyTest extends TestCase
{
    public function testGeneratePlainKeyTemPrefixoPadrao(): void
    {
        $key = ApiKey::generatePlainKey();
        $this->assertIsString($key);
        $this->assertStringStartsWith(ApiKey::KEY_PREFIX, $key);
        // prefixo (8) + 48 chars hex.
        $this->assertSame(strlen(ApiKey::KEY_PREFIX) + 48, strlen($key));
    }

    public function testChavesGeradasSaoUnicas(): void
    {
        $a = ApiKey::generatePlainKey();
        $b = ApiKey::generatePlainKey();
        $this->assertNotSame($a, $b);
    }
}
