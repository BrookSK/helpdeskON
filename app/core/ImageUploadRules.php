<?php

/**
 * Regras puras (sem HTTP) para validação de upload de IMAGENS de marca
 * (logo/favicon). Não confia no MIME enviado pelo navegador (falsificável) nem
 * na extensão do arquivo original: decide pelo TIPO REAL detectado do conteúdo
 * (getimagesize/finfo) e devolve a extensão canônica correspondente.
 *
 * SVG é recusado de propósito: pode conter <script> e é vetor de XSS quando
 * servido diretamente da pasta pública.
 */
class ImageUploadRules
{
    /** Tamanho máximo padrão (2 MB). */
    public const MAX_BYTES = 2097152;

    /**
     * Tipos de imagem aceitos (por constante IMAGETYPE_*) e a extensão canônica.
     * Ícones .ico entram por MIME detectado (não têm IMAGETYPE próprio universal).
     */
    private const IMAGETYPE_EXT = [
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_GIF => 'gif',
        IMAGETYPE_WEBP => 'webp',
        IMAGETYPE_ICO => 'ico',
    ];

    /** MIMEs reais aceitos (fallback quando IMAGETYPE não cobre, ex.: .ico). */
    private const MIME_EXT = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/x-icon' => 'ico',
        'image/vnd.microsoft.icon' => 'ico',
    ];

    /**
     * Deriva a extensão canônica a partir do TIPO detectado por getimagesize().
     * @param int|false $imageType valor retornado por getimagesize()[2] / exif_imagetype()
     * @return string|null extensão canônica ('png','jpg',...) ou null se não aceito
     */
    public static function extensionForImageType($imageType): ?string
    {
        if (!is_int($imageType)) return null;
        return self::IMAGETYPE_EXT[$imageType] ?? null;
    }

    /** Deriva a extensão canônica a partir de um MIME real detectado. */
    public static function extensionForMime(?string $mime): ?string
    {
        if (!is_string($mime)) return null;
        return self::MIME_EXT[strtolower(trim($mime))] ?? null;
    }

    /** O tamanho está dentro do limite? */
    public static function isSizeAllowed($bytes, int $max = self::MAX_BYTES): bool
    {
        return is_int($bytes) || ctype_digit((string) $bytes)
            ? ((int) $bytes) > 0 && ((int) $bytes) <= $max
            : false;
    }

    /**
     * Gera um nome de arquivo seguro: prefixo controlado + timestamp + extensão
     * canônica (derivada do conteúdo real, nunca da entrada do usuário).
     */
    public static function safeFileName(string $prefix, string $canonicalExt): string
    {
        $prefix = preg_replace('/[^a-z0-9_]/i', '', $prefix) ?: 'img';
        $ext = preg_replace('/[^a-z0-9]/i', '', $canonicalExt) ?: 'png';
        return $prefix . '_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
    }
}
