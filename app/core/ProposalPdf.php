<?php

/**
 * Monta o PDF da proposta no formato de docs/proposta-modelo.pdf (A4 horizontal).
 *
 * Páginas 1–5 (apresentação da ON Solutions) são as próprias páginas do modelo,
 * em public/assets/proposal/modelo-0N.jpg (JPEG: PNG com transparência deixa o
 * dompdf lento demais). Da página 6 em diante o conteúdo vem
 * da proposta. Cabeçalho, rodapé e "Pág. N" são desenhados por cima de cada página.
 */
class ProposalPdf
{
    public const BRAND = 'ON Solutions Brasil';
    public const MODEL_PAGES = 5;

    /**
     * @param array $data variáveis do template commercial/proposal_pdf.php
     */
    public static function render(array $data, string $public): string
    {
        $data['brand'] = self::BRAND;
        $data['modelPages'] = self::modelPages($public);
        $data['fontDir'] = $public . '/assets/fonts/poppins';
        // Emblema pequeno e opaco sobre o preto do cabeçalho: imagem com transparência deixa o dompdf lento.
        $emblem = $public . '/assets/proposal/emblem-dark.png';
        $data['emblemPath'] = is_file($emblem) ? $emblem : null;

        $html = (function () use ($data) {
            extract($data);
            ob_start();
            include APP_PATH . '/views/commercial/proposal_pdf.php';
            return (string)ob_get_clean();
        })();

        $client = trim((string)($data['proposal']['client_name'] ?? ''));
        return PdfGenerator::fromDocument(
            $html, $public,
            self::chrome($client, $data['emblemPath'], count($data['modelPages']), self::monthYear(time())),
            'A4', 'landscape',
            self::background(count($data['modelPages']))
        );
    }

    /** "Outubro de 2026": mês e ano no momento em que o PDF é gerado. */
    private static function monthYear(int $timestamp): string
    {
        $meses = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
        return ucfirst($meses[(int)date('n', $timestamp) - 1]) . ' de ' . date('Y', $timestamp);
    }

    /** Fundo cinza-claro das páginas da proposta; a abertura leva a faixa escura à esquerda. */
    private static function background(int $modelPages): callable
    {
        $opening = $modelPages + 1;
        return function (int $page, $canvas) use ($opening) {
            if ($page < $opening) return;
            $canvas->filled_rectangle(0, 0, $canvas->get_width(), $canvas->get_height(), [0.953, 0.965, 0.961]);
            if ($page === $opening) {
                $canvas->filled_rectangle(0, 0, 84 * 72 / 25.4, $canvas->get_height(), [0.043, 0.047, 0.051]);
            }
        };
    }

    /** @return string[] caminhos das páginas fixas do modelo que existem no disco */
    public static function modelPages(string $public): array
    {
        $out = [];
        for ($i = 1; $i <= self::MODEL_PAGES; $i++) {
            $file = sprintf('%s/assets/proposal/modelo-%02d.jpg', $public, $i);
            if (is_file($file)) $out[] = $file;
        }
        return $out;
    }

    /**
     * Cabeçalho preto, rodapé e numeração "Pág. N" das páginas da proposta.
     * As páginas do modelo já trazem os seus; a abertura (primeira página da
     * proposta) só leva o número.
     */
    private static function chrome(string $client, ?string $emblem, int $modelPages, string $coverDate): callable
    {
        $brand = self::BRAND;
        $first = $modelPages + 1;
        $right = 'Proposta comercial' . ($client !== '' ? ' · ' . $client : '');
        $footer = $brand . ' · Proposta comercial' . ($client !== '' ? ' · ' . $client : '') . ' · Documento confidencial';

        return function ($page, $count, $canvas, $metrics) use ($brand, $first, $right, $footer, $emblem, $modelPages, $coverDate) {
            if ($page === 1 && $modelPages > 0) {
                $w = $canvas->get_width();
                $h = $canvas->get_height();
                $canvas->filled_rectangle($w * 0.78, $h * 0.888, $w * 0.19, $h * 0.04, [1, 1, 1]);
                $font = $metrics->getFont('Poppins') ?: $metrics->getFont('DejaVu Sans');
                $size = 9;
                $tw = $metrics->getTextWidth($coverDate, $font, $size);
                $canvas->text($w * 0.9186 - $tw, $h * 0.909, $coverDate, $font, $size, [0.62, 0.64, 0.63]);
            }
            if ($page < $first) return;
            $w = $canvas->get_width();
            $h = $canvas->get_height();
            $margin = 42.7;
            $regular = $metrics->getFont('Poppins');
            $semi = $metrics->getFont('PoppinsSemi') ?: $regular;
            $gray = [0.55, 0.6, 0.6];

            $label = 'Pág. ' . ($page - $first + 1);
            $lw = $metrics->getTextWidth($label, $regular, 6.5);
            $canvas->text($w - $margin - $lw, $h - 25, $label, $regular, 6.5, $gray);
            if ($page === $first) return;

            $canvas->filled_rectangle(0, 0, $w, 36.5, [0.043, 0.047, 0.051]);
            $canvas->filled_rectangle(0, 36.5, $w, 1.3, [0.38, 0.92, 0.82]);
            if ($emblem) $canvas->image($emblem, $margin, 8.5, 19.5, 19.5);
            $canvas->text($margin + 26, 12.5, $brand, $semi, 8.5, [1, 1, 1]);
            $rw = $metrics->getTextWidth($right, $regular, 6.8);
            $canvas->text($w - $margin - $rw, 14, $right, $regular, 6.8, [0.72, 0.75, 0.75]);

            $canvas->text($margin, $h - 25, $footer, $regular, 6.2, $gray);
        };
    }
}
