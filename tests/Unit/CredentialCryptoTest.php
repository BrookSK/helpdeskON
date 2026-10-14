<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use CredentialCrypto;

/**
 * Testes unitários da criptografia do cofre de credenciais (Fase 6).
 * Cobre: round-trip encrypt/decrypt, chave errada -> null, vazio, máscara.
 */
final class CredentialCryptoTest extends TestCase
{
    public function testRoundTripComMesmaChave(): void
    {
        $secret = 'minha-chave-secreta';
        $plain = 'senha-super-forte-123!@#';
        $enc = CredentialCrypto::encrypt($plain, $secret);
        $this->assertNotSame('', $enc);
        $this->assertNotSame($plain, $enc); // nunca em claro
        $this->assertSame($plain, CredentialCrypto::decrypt($enc, $secret));
    }

    public function testChaveErradaNaoDecifra(): void
    {
        $enc = CredentialCrypto::encrypt('segredo', 'chave-A');
        // Chave diferente: GCM detecta e retorna null (não lixo).
        $this->assertNull(CredentialCrypto::decrypt($enc, 'chave-B'));
    }

    public function testDadoCorrompidoRetornaNull(): void
    {
        $this->assertNull(CredentialCrypto::decrypt('não é base64 válido $$$', 'k'));
        $this->assertNull(CredentialCrypto::decrypt(base64_encode('curto'), 'k'));
    }

    public function testVazioNaoCriptografa(): void
    {
        $this->assertSame('', CredentialCrypto::encrypt('', 'k'));
        $this->assertSame('', CredentialCrypto::encrypt(null, 'k'));
        $this->assertNull(CredentialCrypto::decrypt('', 'k'));
        $this->assertNull(CredentialCrypto::decrypt(null, 'k'));
    }

    public function testIvsDiferentesGeramCiphertextsDiferentes(): void
    {
        $a = CredentialCrypto::encrypt('igual', 'k');
        $b = CredentialCrypto::encrypt('igual', 'k');
        // IV aleatório: mesmo texto gera ciphertext diferente, mas ambos decifram.
        $this->assertNotSame($a, $b);
        $this->assertSame('igual', CredentialCrypto::decrypt($a, 'k'));
        $this->assertSame('igual', CredentialCrypto::decrypt($b, 'k'));
    }

    public function testMascaraNuncaRevela(): void
    {
        $this->assertSame('', CredentialCrypto::mask(null));
        $this->assertSame('', CredentialCrypto::mask(''));
        $this->assertSame('••••••••', CredentialCrypto::mask('qualquer-ciphertext'));
    }

    public function testRoundTripComSegredoDoSistema(): void
    {
        // Sem passar chave, usa systemSecret (fallback estável em dev/teste).
        $enc = CredentialCrypto::encrypt('tok_abc');
        $this->assertSame('tok_abc', CredentialCrypto::decrypt($enc));
    }
}
