<?php
/**
 * Gráficos de linha do PDF da proposta, no estilo do modelo (linhas com
 * marcadores, legenda no canto, rótulo de valor no fim de cada linha).
 * Gera PNG opaco via GD — o dompdf não desenha SVG/gráficos de forma fiel.
 */
class ProposalChart
{
    public const BLACK = [17, 20, 23];
    public const TEAL = [26, 156, 128];
    public const MINT = [62, 230, 196];

    private const SS = 2;
    private const BG = [243, 246, 245];

    public static function available(): bool
    {
        return function_exists('imagecreatetruecolor') && function_exists('imagettftext')
            && function_exists('imagepng') && function_exists('imagecopyresampled');
    }

    /**
     * @param array $series [['label' => string, 'color' => [r,g,b], 'values' => float[], 'text' => [r,g,b]?]]
     * @param array $opts   width, height, fontDir, xLabels, xTitle, yTitle, format(callable),
     *                      endLabels(bool), points [['series' => i, 'index' => n, 'label' => string]]
     * @return string|null  data URI do PNG, ou null se o GD não estiver disponível
     */
    public static function line(array $series, array $opts): ?string
    {
        if (!self::available() || !$series) return null;

        $font = rtrim((string)($opts['fontDir'] ?? ''), '/\\');
        $regular = $font . '/Poppins-Regular.ttf';
        $semi = is_file($font . '/Poppins-SemiBold.ttf') ? $font . '/Poppins-SemiBold.ttf' : $regular;
        if (!is_file($regular)) return null;

        $W = (int)($opts['width'] ?? 1500);
        $H = (int)($opts['height'] ?? 1150);
        $s = self::SS;
        $img = imagecreatetruecolor($W * $s, $H * $s);
        $col = fn(array $c) => imagecolorallocate($img, $c[0], $c[1], $c[2]);
        imagefilledrectangle($img, 0, 0, $W * $s, $H * $s, $col(self::BG));

        $format = $opts['format'] ?? fn($v) => number_format($v, 0, ',', '.');
        $n = 0;
        $min = 0.0;
        $max = 0.0;
        foreach ($series as $ser) {
            $n = max($n, count($ser['values']));
            foreach ($ser['values'] as $v) { $min = min($min, $v); $max = max($max, $v); }
        }
        if ($n < 2 || $max == $min) { imagedestroy($img); return null; }
        [$yMin, $yMax, $step] = self::niceRange($min, $max);

        $gray = $col([111, 122, 120]);
        $grid = $col([222, 229, 227]);
        $axis = $col([150, 160, 158]);
        $tick = 25;

        $yLabelW = 0;
        for ($v = $yMin; $v <= $yMax + $step / 2; $v += $step) {
            $yLabelW = max($yLabelW, self::textWidth($regular, $tick, $format($v)));
        }
        $left = 56 + $yLabelW + 16;
        $right = 40;
        if (!empty($opts['endLabels'])) {
            foreach ($series as $ser) {
                $vals = array_values($ser['values']);
                $right = max($right, 36 + self::textWidth($semi, 26, $format(end($vals))));
            }
        }
        $top = 30;
        $bottom = 120;
        $pw = $W - $left - $right;
        $ph = $H - $top - $bottom;
        $X = fn($i) => $left + $pw * $i / ($n - 1);
        $Y = fn($v) => $top + $ph * ($yMax - $v) / ($yMax - $yMin);

        for ($v = $yMin; $v <= $yMax + $step / 2; $v += $step) {
            $y = $Y($v);
            self::thickLine($img, $left, $y, $left + $pw, $y, 1.3, abs($v) < $step / 1000 && $yMin < 0 ? $col([95, 104, 102]) : $grid);
            $label = $format($v);
            self::text($img, $regular, $tick, $left - 12 - self::textWidth($regular, $tick, $label), $y + 7, $gray, $label);
        }
        self::thickLine($img, $left, $top, $left, $top + $ph, 1.6, $axis);
        self::thickLine($img, $left, $top + $ph, $left + $pw, $top + $ph, 1.6, $axis);

        $xLabels = $opts['xLabels'] ?? range(1, $n);
        foreach ($xLabels as $i => $label) {
            $x = $X($i);
            self::thickLine($img, $x, $top + $ph, $x, $top + $ph + 8, 1.4, $axis);
            $label = (string)$label;
            self::text($img, $regular, $tick, $x - self::textWidth($regular, $tick, $label) / 2, $top + $ph + 42, $gray, $label);
        }
        if (!empty($opts['xTitle'])) {
            $t = (string)$opts['xTitle'];
            self::text($img, $regular, $tick, $left + $pw / 2 - self::textWidth($regular, $tick, $t) / 2, $H - 24, $gray, $t);
        }
        if (!empty($opts['yTitle'])) {
            $t = (string)$opts['yTitle'];
            self::text($img, $regular, $tick, 32, $top + $ph / 2 + self::textWidth($regular, $tick, $t) / 2, $gray, $t, 90);
        }

        foreach ($series as $ser) {
            $c = $col($ser['color']);
            $pts = array_values($ser['values']);
            for ($i = 1; $i < count($pts); $i++) {
                self::thickLine($img, $X($i - 1), $Y($pts[$i - 1]), $X($i), $Y($pts[$i]), 5, $c);
            }
            foreach ($pts as $i => $v) self::dot($img, $X($i), $Y($v), 6, $c);
        }

        if (!empty($opts['endLabels'])) {
            foreach ($series as $ser) {
                $pts = array_values($ser['values']);
                $last = count($pts) - 1;
                self::text($img, $semi, 26, $X($last) + 16, $Y($pts[$last]) + 8, $col($ser['text'] ?? $ser['color']), $format($pts[$last]));
            }
        }

        foreach ($opts['points'] ?? [] as $p) {
            $ser = $series[$p['series']] ?? null;
            if (!$ser || !isset($ser['values'][$p['index']])) continue;
            $x = $X($p['index']);
            $y = $Y($ser['values'][$p['index']]);
            self::dot($img, $x, $y, 11, $col($ser['color']));
            $label = (string)$p['label'];
            $lw = self::textWidth($semi, 26, $label);
            $lx = max($x - $lw - 18, $left + 6);
            self::text($img, $semi, 26, $lx, $y - 16, $col($ser['text'] ?? $ser['color']), $label);
        }

        $ly = $top + 24;
        foreach ($series as $ser) {
            $c = $col($ser['color']);
            $lx = $left + 22;
            self::thickLine($img, $lx, $ly, $lx + 44, $ly, 5, $c);
            self::dot($img, $lx + 22, $ly, 6, $c);
            self::text($img, $regular, 24, $lx + 60, $ly + 9, $col([40, 46, 45]), (string)$ser['label']);
            $ly += 40;
        }

        $out = imagecreatetruecolor($W, $H);
        imagecopyresampled($out, $img, 0, 0, 0, 0, $W, $H, $W * $s, $H * $s);
        imagedestroy($img);
        ob_start();
        imagepng($out, null, 6);
        $png = (string)ob_get_clean();
        imagedestroy($out);
        return 'data:image/png;base64,' . base64_encode($png);
    }

    /** Eixo com divisões "redondas" (1, 2, 2,5 ou 5 × 10^n). */
    public static function niceRange(float $min, float $max): array
    {
        $span = max($max - $min, 1e-9);
        $raw = $span / 5;
        $mag = 10 ** floor(log10($raw));
        $step = $mag;
        foreach ([1, 2, 2.5, 5, 10] as $m) {
            if ($m * $mag >= $raw) { $step = $m * $mag; break; }
        }
        return [floor($min / $step) * $step, ceil($max / $step) * $step, $step];
    }

    /** Índice do gráfico de custo (plano 3 meses no mês 12 = 100). */
    public static function index(float $v): string
    {
        return (string)(int)round($v);
    }

    /** Valores em reais abreviados para eixos e rótulos: "R$ 12 mil", "R$ 1,2 mi". */
    public static function money(float $v): string
    {
        $sign = $v < 0 ? '-' : '';
        $a = abs($v);
        if ($a >= 1000000) return $sign . 'R$ ' . self::short($a / 1000000) . ' mi';
        if ($a >= 1000) return $sign . 'R$ ' . self::short($a / 1000) . ' mil';
        return $sign . 'R$ ' . number_format($a, 0, ',', '.');
    }

    private static function short(float $v): string
    {
        $d = ($v < 10 && abs($v - round($v)) >= 0.05) ? 1 : 0;
        return number_format($v, $d, ',', '.');
    }

    private static function textWidth(string $font, float $px, string $text): float
    {
        $b = imagettfbbox($px * 0.75 * self::SS, 0, $font, $text);
        return ($b[2] - $b[0]) / self::SS;
    }

    private static function text($img, string $font, float $px, float $x, float $y, int $color, ?string $text = null, float $angle = 0): void
    {
        if ($text === null || $text === '') return;
        imagettftext($img, $px * 0.75 * self::SS, $angle, (int)round($x * self::SS), (int)round($y * self::SS), $color, $font, $text);
    }

    private static function dot($img, float $x, float $y, float $r, int $color): void
    {
        $d = (int)round($r * 2 * self::SS);
        imagefilledellipse($img, (int)round($x * self::SS), (int)round($y * self::SS), $d, $d, $color);
    }

    private static function thickLine($img, float $x1, float $y1, float $x2, float $y2, float $w, int $color): void
    {
        $s = self::SS;
        $dx = $x2 - $x1;
        $dy = $y2 - $y1;
        $len = sqrt($dx * $dx + $dy * $dy);
        if ($len == 0) return;
        $ox = -$dy / $len * $w / 2;
        $oy = $dx / $len * $w / 2;
        $poly = [
            ($x1 + $ox) * $s, ($y1 + $oy) * $s, ($x2 + $ox) * $s, ($y2 + $oy) * $s,
            ($x2 - $ox) * $s, ($y2 - $oy) * $s, ($x1 - $ox) * $s, ($y1 - $oy) * $s,
        ];
        imagefilledpolygon($img, array_map(fn($v) => (int)round($v), $poly), $color);
    }
}
