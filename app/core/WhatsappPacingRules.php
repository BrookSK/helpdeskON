<?php

/**
 * Intervalo entre os WhatsApps automáticos (proteção contra bloqueio por spam).
 *
 * Cada sequência define um intervalo mínimo e máximo, em minutos. Depois de
 * cada WhatsApp disparado por uma conta (instância), o próximo da MESMA conta
 * só sai quando passar um intervalo sorteado entre esses dois valores. Assim
 * 20 mensagens saem espaçadas (ex.: 3 min, 9 min, 2 min, 7 min...), sem ritmo
 * fixo.
 *
 * O sorteio é determinístico a partir da mensagem anterior (id): o mesmo envio
 * sempre gera o mesmo intervalo, então o cron pode reconferir quantas vezes
 * quiser sem "encurtar" a espera, e não é preciso gravar a próxima hora.
 */
final class WhatsappPacingRules
{
    public const DEFAULT_MIN = 2;
    public const DEFAULT_MAX = 10;
    public const LIMIT = 120;

    /**
     * Normaliza o intervalo vindo do formulário ou do banco.
     * Vazio/null usa o padrão (2 a 10). Valores fora de 0..120 são limitados e
     * mínimo maior que máximo é invertido.
     * @return int[] [min, max] em minutos
     */
    public static function normalize($min, $max): array
    {
        $blank = fn($v) => $v === null || (is_string($v) && trim($v) === '');
        if ($blank($min) && $blank($max)) return [self::DEFAULT_MIN, self::DEFAULT_MAX];
        $clamp = fn($v) => max(0, min(self::LIMIT, (int) $v));
        $a = $blank($min) ? null : $clamp($min);
        $b = $blank($max) ? null : $clamp($max);
        if ($a === null) $a = $b;
        if ($b === null) $b = $a;
        return $a <= $b ? [$a, $b] : [$b, $a];
    }

    /** 0 e 0 desliga o intervalo. */
    public static function isEnabled(int $min, int $max): bool
    {
        return $max > 0;
    }

    /** Intervalo (segundos) sorteado a partir da mensagem anterior. */
    public static function gapSeconds($seed, int $min, int $max): int
    {
        [$min, $max] = self::normalize($min, $max);
        $lo = $min * 60;
        $hi = $max * 60;
        if ($hi <= $lo) return $lo;
        $h = crc32('wa-gap|' . (string) $seed);
        return $lo + ($h % ($hi - $lo + 1));
    }

    /**
     * Quando a conta pode mandar o próximo WhatsApp.
     * @param int|null $lastTs momento do último envio automático da conta (null = nenhum)
     * @return int timestamp liberado (0 = já pode)
     */
    public static function nextAllowedTs(?int $lastTs, $seed, int $min, int $max): int
    {
        if (!$lastTs || !self::isEnabled($min, $max)) return 0;
        return $lastTs + self::gapSeconds($seed, $min, $max);
    }

    /** Texto para o histórico do lead. */
    public static function waitLabel(int $allowedTs, int $now): string
    {
        $mins = (int) ceil(max(0, $allowedTs - $now) / 60);
        return 'Aguardando o intervalo entre WhatsApps (proteção contra spam): envia por volta de '
            . date('H:i', $allowedTs) . ($mins > 0 ? " (em {$mins} min)." : '.');
    }
}
