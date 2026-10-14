<?php

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Geração de PDF a partir de HTML (usa dompdf, lib PHP pura — sem binários).
 *
 * Usado para enviar o contrato à ClickSign, que aceita PDF (não HTML). A lógica
 * de "montar o HTML" fica fora daqui; este helper só converte HTML -> PDF binário.
 */
class PdfGenerator
{
    /** O dompdf está disponível (composer instalado)? */
    public static function isAvailable(): bool
    {
        return class_exists('Dompdf\\Dompdf');
    }

    /**
     * Converte HTML em PDF e retorna o conteúdo binário do PDF.
     * @throws \RuntimeException se o dompdf não estiver instalado.
     */
    public static function fromHtml(string $html, string $paper = 'A4', string $orientation = 'portrait'): string
    {
        if (!self::isAvailable()) {
            throw new \RuntimeException('Gerador de PDF (dompdf) não instalado. Rode: composer require dompdf/dompdf');
        }
        $options = new Options();
        $options->set('isRemoteEnabled', false);     // segurança: não busca recursos externos
        $options->set('defaultFont', 'DejaVu Sans'); // suporte a acentuação
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(self::wrap($html), 'UTF-8');
        $dompdf->setPaper($paper, $orientation);
        $dompdf->render();
        return $dompdf->output();
    }

    /**
     * Converte um documento HTML completo (com o próprio CSS e @page) em PDF.
     * Imagens só podem vir de arquivos dentro de $chroot.
     *
     * Fontes declaradas com @font-face precisam estar dentro de $chroot.
     *
     * $pageScript é chamado depois da renderização, por página, e desenha por cima
     * do conteúdo: function (int $page, int $count, $canvas, $fontMetrics).
     * $beforePage desenha por baixo do conteúdo (fundo da folha inteira, fora das
     * margens): function (int $page, $canvas).
     */
    public static function fromDocument(string $html, string $chroot, ?callable $pageScript = null, string $paper = 'A4', string $orientation = 'portrait', ?callable $beforePage = null): string
    {
        if (!self::isAvailable()) {
            throw new \RuntimeException('Gerador de PDF (dompdf) não instalado. Rode: composer require dompdf/dompdf');
        }
        $fontCache = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'dompdf-fonts';
        if (!is_dir($fontCache)) @mkdir($fontCache, 0775, true);

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('chroot', $chroot);
        if (is_dir($fontCache) && is_writable($fontCache)) {
            $options->set('fontDir', $fontCache);
            $options->set('fontCache', $fontCache);
        }

        $dompdf = new Dompdf($options);
        if ($beforePage !== null) {
            $dompdf->setCallbacks([[
                'event' => 'begin_page_render',
                'f' => function ($frame, $canvas) use ($beforePage) {
                    $beforePage($canvas->get_page_number(), $canvas);
                },
            ]]);
        }
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper($paper, $orientation);
        $dompdf->render();
        if ($pageScript !== null) $dompdf->getCanvas()->page_script($pageScript);
        return $dompdf->output();
    }

    /** Envolve o corpo num HTML completo com margens e tipografia legível. */
    private static function wrap(string $bodyHtml): string
    {
        return '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8">'
            . '<style>'
            . '@page { margin: 2.2cm 2cm; }'
            . 'body { font-family: "DejaVu Sans", sans-serif; font-size: 11pt; color: #222; line-height: 1.5; }'
            . 'h1 { font-size: 16pt; text-align: center; } h2 { font-size: 13pt; } h3 { font-size: 12pt; }'
            . 'p { margin: 0 0 8pt; } ul, ol { margin: 0 0 8pt 16pt; }'
            . 'table { width: 100%; border-collapse: collapse; } td, th { border: 1px solid #ccc; padding: 4pt; }'
            . '</style></head><body>' . $bodyHtml . '</body></html>';
    }
}
