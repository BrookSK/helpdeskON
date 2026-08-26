<?php

/**
 * Normaliza um RawProject do parser para o schema de `opportunities`.
 *
 * Regras críticas (correções dos defeitos do projeto base):
 *  - datas: parse de verdade; falhou => NULL (NUNCA now());
 *  - identidade: external_id da URL (título nunca entra na identidade);
 *  - moeda: detectada por evidência; sem evidência => NULL;
 *  - score: ordenação por correspondência com termos/skills monitorados.
 */
class LeadNormalizer
{
    /**
     * @param array $raw       RawProject do Freelas99Parser
     * @param array $terms     termos monitorados (para score/matched_terms)
     * @return array|null      registro pronto para upsert, ou null se inválido
     */
    public static function normalize($raw, $terms = [])
    {
        if (empty($raw['external_id']) || empty($raw['canonical_url']) || empty($raw['title'])) {
            return null;
        }

        // published_at: a partir do epoch do parser (segundos) — parse real
        $publishedAt = null;
        if (!empty($raw['published_ts']) && is_numeric($raw['published_ts'])) {
            $publishedAt = date('Y-m-d H:i:s', (int) $raw['published_ts']);
        } elseif (!empty($raw['published_text'])) {
            $publishedAt = self::parseRelativeDate($raw['published_text']);
        }

        $skills = is_array($raw['skills'] ?? null) ? $raw['skills'] : [];

        $matched = self::matchTerms($raw, $terms);
        $score = self::computeScore($raw, $terms, $matched);

        return [
            'source' => 'freelas99',
            'external_id' => (string) $raw['external_id'],
            'canonical_url' => $raw['canonical_url'],
            'title' => mb_substr($raw['title'], 0, 300),
            'description' => $raw['description'] ?? null,
            'category' => $raw['category'] ?? null,
            'experience_level' => $raw['experience_level'] ?? null,
            'skills' => $skills,
            'budget_min' => isset($raw['budget_min']) ? $raw['budget_min'] : null,
            'budget_max' => isset($raw['budget_max']) ? $raw['budget_max'] : null,
            'currency' => $raw['currency'] ?? null,
            'published_at' => $publishedAt,
            'proposal_count' => isset($raw['proposal_count']) ? (int) $raw['proposal_count'] : null,
            'interested_count' => isset($raw['interested_count']) ? (int) $raw['interested_count'] : null,
            'client_name' => $raw['client_name'] ?? null,
            'client_rating' => $raw['client_rating'] ?? null,
            'score' => $score,
            'matched_terms' => $matched,
            'raw_data' => $raw,
        ];
    }

    /**
     * Converte data relativa/absoluta em 'Y-m-d H:i:s'. Falhou => null (nunca now()).
     */
    public static function parseRelativeDate($text)
    {
        if (!is_string($text)) return null;
        $t = trim(mb_strtolower($text));
        if ($t === '') return null;

        $now = time();

        if ($t === 'hoje') return date('Y-m-d H:i:s', $now);
        if ($t === 'ontem') return date('Y-m-d H:i:s', $now - 86400);

        // "há 5 minutos", "há 2 horas", "há 3 dias"
        if (preg_match('/h[áa]\s+(\d+)\s*(minuto|hora|dia|semana|m[êe]s|ano)/u', $t, $m)) {
            return date('Y-m-d H:i:s', $now - self::unitToSeconds((int)$m[1], $m[2]));
        }
        // "2 dias atrás", "5 minutos atrás"
        if (preg_match('/(\d+)\s*(minuto|hora|dia|semana|m[êe]s|ano)s?\s+atr[áa]s/u', $t, $m)) {
            return date('Y-m-d H:i:s', $now - self::unitToSeconds((int)$m[1], $m[2]));
        }
        // dd/mm/aaaa
        if (preg_match('#(\d{2})/(\d{2})/(\d{4})#', $t, $m)) {
            $ts = mktime(0, 0, 0, (int)$m[2], (int)$m[1], (int)$m[3]);
            return $ts ? date('Y-m-d H:i:s', $ts) : null;
        }
        // ISO 8601
        if (preg_match('/^\d{4}-\d{2}-\d{2}([ t]\d{2}:\d{2})?/', $t)) {
            $ts = strtotime($text);
            return $ts ? date('Y-m-d H:i:s', $ts) : null;
        }
        return null;
    }

    private static function unitToSeconds($n, $unit)
    {
        $map = [
            'minuto' => 60, 'hora' => 3600, 'dia' => 86400,
            'semana' => 604800, 'mes' => 2592000, 'mês' => 2592000, 'ano' => 31536000,
        ];
        $unit = str_replace(['ê'], ['e'], $unit);
        return $n * ($map[$unit] ?? 0);
    }

    /**
     * Termos monitorados que aparecem no título/descrição/skills do projeto.
     * @return array lista de termos correspondentes
     */
    private static function matchTerms($raw, $terms)
    {
        if (empty($terms)) return [];
        $haystack = mb_strtolower(
            ($raw['title'] ?? '') . ' ' .
            ($raw['description'] ?? '') . ' ' .
            ($raw['category'] ?? '') . ' ' .
            implode(' ', $raw['skills'] ?? [])
        );
        $matched = [];
        foreach ($terms as $term) {
            $t = trim(mb_strtolower($term));
            if ($t !== '' && mb_strpos($haystack, $t) !== false) {
                $matched[] = $term;
            }
        }
        return array_values(array_unique($matched));
    }

    /**
     * Score de ordenação (0-100). Correspondência de termos + sinais de baixa concorrência.
     */
    private static function computeScore($raw, $terms, $matched)
    {
        $score = 0;

        // Correspondência com termos monitorados (peso maior)
        if (!empty($terms)) {
            $score += min(60, count($matched) * 20);
        }

        // Menos propostas = menos concorrência = melhor
        $proposals = $raw['proposal_count'] ?? null;
        if ($proposals !== null) {
            if ($proposals <= 5) $score += 25;
            elseif ($proposals <= 15) $score += 15;
            elseif ($proposals <= 30) $score += 8;
        }

        // Tem orçamento definido é um sinal positivo
        if (!empty($raw['budget_min'])) $score += 10;

        // Descrição rica
        if (!empty($raw['description']) && mb_strlen($raw['description']) > 200) $score += 5;

        return max(0, min(100, $score));
    }
}
