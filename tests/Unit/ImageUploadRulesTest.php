<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ImageUploadRules;

/**
 * Testes das regras puras de validação de upload de imagens de marca
 * (ImageUploadRules). Decide pela imagem REAL (IMAGETYPE/MIME), recusa SVG,
 * limita tamanho e gera nome de arquivo seguro.
 */
final class ImageUploadRulesTest extends TestCase
{
    // ---- extensionForImageType ----

    public function testExtensaoPorTipoDeImagemAceito(): void
    {
        $this->assertSame('png', ImageUploadRules::extensionForImageType(IMAGETYPE_PNG));
        $this->assertSame('jpg', ImageUploadRules::extensionForImageType(IMAGETYPE_JPEG));
        $this->assertSame('gif', ImageUploadRules::extensionForImageType(IMAGETYPE_GIF));
        $this->assertSame('webp', ImageUploadRules::extensionForImageType(IMAGETYPE_WEBP));
    }

    public function testExtensaoPorTipoInvalidoRetornaNull(): void
    {
        // SVG não tem IMAGETYPE de bitmap; tipos não mapeados retornam null.
        $this->assertNull(ImageUploadRules::extensionForImageType(IMAGETYPE_PSD));
        $this->assertNull(ImageUploadRules::extensionForImageType(false));
        $this->assertNull(ImageUploadRules::extensionForImageType(null));
    }

    // ---- extensionForMime ----

    public function testExtensaoPorMimeAceito(): void
    {
        $this->assertSame('png', ImageUploadRules::extensionForMime('image/png'));
        $this->assertSame('jpg', ImageUploadRules::extensionForMime('image/jpeg'));
        $this->assertSame('ico', ImageUploadRules::extensionForMime('image/x-icon'));
        $this->assertSame('ico', ImageUploadRules::extensionForMime('image/vnd.microsoft.icon'));
        // case-insensitive
        $this->assertSame('png', ImageUploadRules::extensionForMime('IMAGE/PNG'));
    }

    public function testExtensaoPorMimeRecusaSvgEOutros(): void
    {
        // SVG é recusado de propósito (vetor de XSS).
        $this->assertNull(ImageUploadRules::extensionForMime('image/svg+xml'));
        $this->assertNull(ImageUploadRules::extensionForMime('application/pdf'));
        $this->assertNull(ImageUploadRules::extensionForMime('text/html'));
        $this->assertNull(ImageUploadRules::extensionForMime(null));
    }

    // ---- isSizeAllowed ----

    public function testTamanhoDentroEForaDoLimite(): void
    {
        $this->assertTrue(ImageUploadRules::isSizeAllowed(1024));
        $this->assertTrue(ImageUploadRules::isSizeAllowed(ImageUploadRules::MAX_BYTES));
        $this->assertFalse(ImageUploadRules::isSizeAllowed(ImageUploadRules::MAX_BYTES + 1));
        $this->assertFalse(ImageUploadRules::isSizeAllowed(0));
        // limite customizado
        $this->assertTrue(ImageUploadRules::isSizeAllowed(500, 1000));
        $this->assertFalse(ImageUploadRules::isSizeAllowed(1500, 1000));
    }

    // ---- safeFileName ----

    public function testNomeSeguroUsaPrefixoEExtensaoCanonica(): void
    {
        $name = ImageUploadRules::safeFileName('logo', 'png');
        $this->assertMatchesRegularExpression('/^logo_\d+_\d{4}\.png$/', $name);
    }

    public function testNomeSeguroSanitizaEntradasMaliciosas(): void
    {
        // prefixo e extensão são sanitizados (sem path traversal / caracteres perigosos)
        $name = ImageUploadRules::safeFileName('../../evil', 'php');
        $this->assertStringNotContainsString('/', $name);
        $this->assertStringNotContainsString('.', substr($name, 0, strrpos($name, '.')));
        // a extensão vira "php" só como string sanitizada (o controller nunca
        // chega aqui com php porque o tipo real é validado antes), mas garante
        // que não há caracteres além de alfanuméricos.
        $this->assertMatchesRegularExpression('/^evil_\d+_\d{4}\.php$/', $name);
    }
}
