<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ApiKey;

/**
 * Testes unitários da geração/hash de API Keys. generatePlainKey() e hashKey()
 * são estáticos e puros (não tocam banco).
 */
final class ApiKeyTest extends TestCase
{
    public function testGeneratePlainKeyTemPrefixoEHash(): void
    {
        $gen = ApiKey::generatePlainKey();

        $this->assertArrayHasKey('plain', $gen);
        $this->assertArrayHasKey('hash', $gen);
        $this->assertArrayHasKey('prefix', $gen);

        $this->assertStringStartsWith(ApiKey::KEY_PREFIX, $gen['plain']);
        // O hash guardado corresponde ao hash da chave em claro.
        $this->assertSame(ApiKey::hashKey($gen['plain']), $gen['hash']);
        // O prefixo é um pedaço inicial da chave (para identificação na UI).
        $this->assertStringStartsWith($gen['prefix'], $gen['plain']);
    }

    public function testChavesGeradasSaoUnicas(): void
    {
        $a = ApiKey::generatePlainKey();
        $b = ApiKey::generatePlainKey();
        $this->assertNotSame($a['plain'], $b['plain']);
        $this->assertNotSame($a['hash'], $b['hash']);
    }

    public function testHashEhDeterministicoESha256(): void
    {
        $plain = 'hk_live_abc123';
        $this->assertSame(hash('sha256', $plain), ApiKey::hashKey($plain));
        $this->assertSame(ApiKey::hashKey($plain), ApiKey::hashKey($plain));
        $this->assertSame(64, strlen(ApiKey::hashKey($plain))); // sha256 hex = 64 chars
    }
}
