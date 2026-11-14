<?php

/**
 * Quem falou em cada trecho da transcrição (regras PURAS, sem banco).
 *
 * Durante a gravação, o navegador de quem grava já mede, a cada 0,1 s, quem
 * está falando (o contorno verde na sala). Ele registra esses intervalos
 * ("de 62,4 s a 75,1 s falou Lucas") no tempo da gravação (pausas não contam)
 * e envia junto com cada segmento de áudio. Na conclusão da transcrição, cada
 * trecho do Whisper recebe o nome de quem mais falou dentro do seu intervalo.
 *
 * Formato de um intervalo: ['s' => início em s, 'e' => fim em s, 'n' => nome].
 */
class SpeakerTimeline
{
    /** Intervalos aceitos por envio (um segmento de áudio de 1 minuto tem poucas dezenas). */
    public const MAX_INTERVALS_PER_POST = 2000;

    /** Intervalos guardados por gravação (uma reunião de 4 h fica bem abaixo). */
    public const MAX_INTERVALS_TOTAL = 50000;

    public const MAX_NAME_LEN = 80;

    /**
     * Trecho sem ninguém falando dentro dele (fala curta, tempo do Whisper um
     * pouco deslocado): usa quem falou até esta distância, em segundos.
     */
    public const NEAREST_GAP_SEC = 1.0;

    /**
     * Valida o que veio do navegador: JSON (string) ou array. Descarta o que
     * não for um intervalo válido, limpa o nome e limita a quantidade.
     *
     * @param mixed $raw
     * @return array<int,array{s:float,e:float,n:string}>
     */
    public static function sanitize($raw, int $max = self::MAX_INTERVALS_PER_POST): array
    {
        if (is_string($raw)) {
            if (trim($raw) === '' || strlen($raw) > 2000000) return [];
            $raw = json_decode($raw, true);
        }
        if (!is_array($raw)) return [];
        $out = [];
        foreach ($raw as $it) {
            if (count($out) >= $max) break;
            if (!is_array($it)) continue;
            $s = $it['s'] ?? null;
            $e = $it['e'] ?? null;
            if (!is_numeric($s) || !is_numeric($e)) continue;
            $s = round(max(0.0, (float)$s), 2);
            $e = round((float)$e, 2);
            if (!is_finite($s) || !is_finite($e) || $e <= $s || $e > 86400) continue;
            $name = self::cleanName($it['n'] ?? '');
            if ($name === '') continue;
            $out[] = ['s' => $s, 'e' => $e, 'n' => $name];
        }
        return $out;
    }

    /** Nome sem caracteres de controle, espaços repetidos nem tamanho excessivo. */
    public static function cleanName($name): string
    {
        if (!is_string($name) && !is_numeric($name)) return '';
        $name = preg_replace('/[\p{Cc}\p{Cf}]+/u', ' ', (string)$name);
        $name = trim((string)preg_replace('/\s+/u', ' ', (string)$name));
        return mb_substr($name, 0, self::MAX_NAME_LEN);
    }

    /**
     * Junta as listas de vários segmentos numa linha do tempo ordenada, sem
     * repetir intervalos idênticos (reenvio do mesmo segmento).
     *
     * @param array<int,array> $lists
     * @return array<int,array{s:float,e:float,n:string}>
     */
    public static function combine(array $lists): array
    {
        $seen = [];
        $all = [];
        foreach ($lists as $list) {
            foreach (self::sanitize(is_array($list) ? $list : [], self::MAX_INTERVALS_TOTAL) as $it) {
                $key = $it['s'] . '|' . $it['e'] . '|' . $it['n'];
                if (isset($seen[$key])) continue;
                $seen[$key] = true;
                $all[] = $it;
                if (count($all) >= self::MAX_INTERVALS_TOTAL) break 2;
            }
        }
        usort($all, static function ($a, $b) { return [$a['s'], $a['e']] <=> [$b['s'], $b['e']]; });
        return $all;
    }

    /**
     * Põe 'speaker' em cada trecho [{start,end,text}] com quem mais falou dentro
     * dele. Sem ninguém dentro, usa quem falou mais perto (até NEAREST_GAP_SEC);
     * sem isso, o trecho fica sem nome. Empate fica com quem começou primeiro.
     *
     * @param array<int,array> $segments trechos com tempo absoluto da gravação
     * @param array<int,array{s:float,e:float,n:string}> $intervals
     */
    public static function assign(array $segments, array $intervals): array
    {
        if (!$intervals) return $segments;
        foreach ($segments as $i => $seg) {
            $start = (float)($seg['start'] ?? 0);
            $end = max($start, (float)($seg['end'] ?? $start));
            $name = self::speakerFor($start, $end, $intervals);
            if ($name !== null) $segments[$i]['speaker'] = $name;
        }
        return $segments;
    }

    /** Quem falou no intervalo [$start, $end]? Null quando não dá para saber. */
    public static function speakerFor(float $start, float $end, array $intervals): ?string
    {
        $byName = [];
        $firstAt = [];
        foreach ($intervals as $it) {
            $ov = min($end, (float)$it['e']) - max($start, (float)$it['s']);
            if ($ov <= 0) continue;
            $byName[$it['n']] = ($byName[$it['n']] ?? 0) + $ov;
            if (!isset($firstAt[$it['n']])) $firstAt[$it['n']] = (float)$it['s'];
        }
        if ($byName) {
            uksort($byName, static function ($a, $b) use ($byName, $firstAt) {
                return [$byName[$b], $firstAt[$a]] <=> [$byName[$a], $firstAt[$b]];
            });
            return (string)array_key_first($byName);
        }
        // Trecho de duração zero ou fala fora dos intervalos: o mais próximo.
        $best = null;
        $bestGap = self::NEAREST_GAP_SEC;
        foreach ($intervals as $it) {
            $gap = $it['e'] < $start ? $start - $it['e'] : ($it['s'] > $end ? $it['s'] - $end : 0.0);
            if ($gap <= $bestGap) { $bestGap = $gap; $best = $it['n']; }
        }
        return $best;
    }
}
