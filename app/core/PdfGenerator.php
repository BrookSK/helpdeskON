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
