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
     * $pageNumber desenha "página / total" (o CSS do dompdf não conhece o total):
     * ['right_mm' => float, 'top_mm' => float, 'size' => float, 'color' => [r,g,b] 0–1, 'skip_first' => bool]
     */
    public static function fromDocument(string $html, string $chroot, ?array $pageNumber = null, string $paper = 'A4', string $orientation = 'portrait'): string
    {
        if (!self::isAvailable()) {
            throw new \RuntimeException('Gerador de PDF (dompdf) não instalado. Rode: composer require dompdf/dompdf');
        }
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('chroot', $chroot);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper($paper, $orientation);
        $dompdf->render();

        if ($pageNumber !== null) {
            $mm = 72 / 25.4;
            $size = (float)($pageNumber['size'] ?? 7);
            $color = $pageNumber['color'] ?? [0.54, 0.6, 0.63];
            $skipFirst = !empty($pageNumber['skip_first']);
            $right = (float)($pageNumber['right_mm'] ?? 16) * $mm;
            $top = (float)($pageNumber['top_mm'] ?? 284) * $mm;
            $dompdf->getCanvas()->page_script(function ($page, $count, $canvas, $metrics) use ($size, $color, $skipFirst, $right, $top) {
                if ($skipFirst && $page === 1) return;
                $font = $metrics->getFont('DejaVu Sans');
                $text = $page . ' / ' . $count;
                $w = $metrics->getTextWidth($text, $font, $size);
                $canvas->text($canvas->get_width() - $right - $w, $top, $text, $font, $size, $color);
            });
        }
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
