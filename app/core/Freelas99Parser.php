<?php

/**
 * Parser do HTML de listagem do 99Freelas.
 *
 * Construído a partir do HTML REAL (docs/99freelas_fixtures).
 * Estrutura confirmada do card:
 *   <div class="result-item ..." data-id="778913" data-nome="...">
 *     <h1 class="title"><a href="/project/slug-778913?fs=t">Título</a></h1>
 *     <p class="item-text information">Categoria | Nível | Publicado:
 *        <b class="datetime" cp-datetime="1787669316000"></b> |
 *        Propostas: <b>7</b> | Interessados: <b>16</b></p>
 *     <div class="item-text description" data-content="...">Descrição</div>
 *     <p class="item-text client"><b>Cliente:</b> <a>Nome</a>
 *        <span class="avaliacoes-star" data-score="5.0"></span>
 *        <span class="avaliacoes-text">(2 avaliações)</span></p>
 *   </div>
 *
 * Regras:
 *  - função pura: recebe HTML, devolve array de RawProject;
 *  - nunca lança exceção — em erro devolve [];
 *  - âncora primária: data-id do result-item + link /project/<slug>-<id>;
 *  - resiliente a mudança de classe CSS.
 */
class Freelas99Parser
{
    const BASE_URL = 'https://www.99freelas.com.br';

    /**
     * @return array Lista de RawProject (arrays associativos).
     */
    public static function parse($html)
    {
        if (!is_string($html) || trim($html) === '') return [];

        try {
            $prev = libxml_use_internal_errors(true);
            $doc = new DOMDocument();
            // Força UTF-8
            $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
            libxml_clear_errors();
            libxml_use_internal_errors($prev);

            $xpath = new DOMXPath($doc);

            // Âncora primária: containers com class result-item e atributo data-id
            $nodes = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' result-item ')]");
            $projects = [];

            if ($nodes && $nodes->length > 0) {
                foreach ($nodes as $node) {
                    $p = self::parseCard($xpath, $node);
                    if ($p) $projects[] = $p;
                }
            }

            // Fallback: se não achou cards, ancora nos links de projeto
            if (empty($projects)) {
                $projects = self::parseByLinks($xpath);
            }

            return $projects;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Conta quantos links de projeto o HTML contém (métrica cards_detected).
     */
    public static function countProjectLinks($html)
    {
        if (!is_string($html)) return 0;
        // Links únicos /project/<slug>-<digitos>, excluindo /project/new
        preg_match_all('#/project/([a-z0-9\-]+-\d+)#i', $html, $m);
        if (empty($m[1])) return 0;
        $ids = [];
        foreach ($m[1] as $slug) {
            if (preg_match('/-(\d+)$/', $slug, $mm)) $ids[$mm[1]] = true;
        }
        return count($ids);
    }

    // ---- Interno ----

    private static function parseCard(DOMXPath $xpath, DOMNode $node)
    {
        // external_id: data-id do card, ou extraído do link
        $externalId = self::attr($node, 'data-id');

        // Link do projeto dentro do card
        $linkNode = $xpath->query(".//a[contains(@href, '/project/')]", $node)->item(0);
        $href = $linkNode ? $linkNode->getAttribute('href') : null;

        if (!$externalId && $href && preg_match('/-(\d+)(?:\?|$)/', $href, $m)) {
            $externalId = $m[1];
        }
        if (!$externalId || !$href) return null;

        $canonicalUrl = self::canonicalUrl($href);

        // Título: data-nome do card, ou texto do link
        $title = self::decode(self::attr($node, 'data-nome'));
        if (!$title && $linkNode) $title = self::text($linkNode);
        if (!$title) return null;

        // Descrição: data-content da div.description (mantém <br/>), ou texto
        $descNode = $xpath->query(".//*[contains(concat(' ', normalize-space(@class), ' '), ' description ')]", $node)->item(0);
        $description = null;
        if ($descNode) {
            $raw = $descNode->hasAttribute('data-content') ? $descNode->getAttribute('data-content') : self::text($descNode);
            $description = self::decode(self::brToText($raw));
        }

        // Linha de informação: categoria | nível | publicado | propostas | interessados
        $infoNode = $xpath->query(".//p[contains(concat(' ', normalize-space(@class), ' '), ' information ')]", $node)->item(0);
        $category = null; $experience = null; $publishedTs = null;
        $proposalCount = null; $interestedCount = null;
        if ($infoNode) {
            $infoText = self::decode(self::text($infoNode));

            // Categoria e nível: primeiros dois segmentos antes de "Publicado:"
            $head = $infoText;
            $posPub = mb_stripos($head, 'Publicado');
            if ($posPub !== false) $head = mb_substr($head, 0, $posPub);
            $segs = array_values(array_filter(array_map('trim', explode('|', $head)), fn($s) => $s !== ''));
            if (isset($segs[0])) $category = $segs[0];
            if (isset($segs[1])) $experience = $segs[1];

            // Data de publicação: <b class="datetime" cp-datetime="epoch_ms">
            $dtNode = $xpath->query(".//b[contains(concat(' ', normalize-space(@class), ' '), ' datetime ')][@cp-datetime]", $infoNode)->item(0);
            if ($dtNode) {
                $epochMs = $dtNode->getAttribute('cp-datetime');
                if (is_numeric($epochMs) && $epochMs > 0) {
                    $publishedTs = (int) floor(((int)$epochMs) / 1000);
                }
            }

            // Propostas e Interessados
            if (preg_match('/Propostas:\s*(\d+)/iu', $infoText, $m)) $proposalCount = (int) $m[1];
            if (preg_match('/Interessados:\s*(\d+)/iu', $infoText, $m)) $interestedCount = (int) $m[1];
        }

        // Cliente + avaliações
        $clientNode = $xpath->query(".//p[contains(concat(' ', normalize-space(@class), ' '), ' client ')]", $node)->item(0);
        $clientName = null; $clientRating = null;
        if ($clientNode) {
            $cn = $xpath->query(".//a", $clientNode)->item(0);
            if ($cn) $clientName = self::decode(self::text($cn));
            $starNode = $xpath->query(".//span[contains(@class,'avaliacoes-star')][@data-score]", $clientNode)->item(0);
            if ($starNode) {
                $score = $starNode->getAttribute('data-score');
                if ($score !== '' && $score !== null) $clientRating = $score;
            }
        }

        // Skills (quando presentes) — bloco de tags/skills dentro do card
        $skills = [];
        $skillNodes = $xpath->query(".//*[contains(concat(' ', normalize-space(@class), ' '), ' skill ') or contains(concat(' ', normalize-space(@class), ' '), ' tag ')]//a | .//ul[contains(@class,'skills')]//li", $node);
        if ($skillNodes) {
            foreach ($skillNodes as $sn) {
                $s = trim(self::decode(self::text($sn)));
                if ($s !== '') $skills[] = $s;
            }
        }
        $skills = array_values(array_unique($skills));

        // Orçamento (frequentemente ausente): procura por R$/US$ no texto do card
        $budget = self::extractBudget(self::decode(self::text($node)));

        return [
            'external_id' => (string) $externalId,
            'canonical_url' => $canonicalUrl,
            'title' => $title,
            'description' => $description,
            'category' => $category,
            'experience_level' => $experience,
            'skills' => $skills,
            'budget_raw' => $budget['raw'],
            'budget_min' => $budget['min'],
            'budget_max' => $budget['max'],
            'currency' => $budget['currency'],
            'published_ts' => $publishedTs, // epoch em segundos, ou null
            'proposal_count' => $proposalCount,
            'interested_count' => $interestedCount,
            'client_name' => $clientName,
            'client_rating' => $clientRating,
        ];
    }

    /** Fallback: ancora nos links de projeto quando os cards não são detectados. */
    private static function parseByLinks(DOMXPath $xpath)
    {
        $links = $xpath->query("//a[contains(@href, '/project/')]");
        if (!$links) return [];
        $seen = [];
        $projects = [];
        foreach ($links as $a) {
            $href = $a->getAttribute('href');
            if (strpos($href, '/project/new') !== false) continue;
            if (!preg_match('/-(\d+)(?:\?|$)/', $href, $m)) continue;
            $id = $m[1];
            if (isset($seen[$id])) continue;
            $seen[$id] = true;
            $title = self::decode(self::text($a));
            if ($title === '') continue;
            $projects[] = [
                'external_id' => $id,
                'canonical_url' => self::canonicalUrl($href),
                'title' => $title,
                'description' => null, 'category' => null, 'experience_level' => null,
                'skills' => [], 'budget_raw' => null, 'budget_min' => null, 'budget_max' => null,
                'currency' => null, 'published_ts' => null, 'proposal_count' => null,
                'interested_count' => null, 'client_name' => null, 'client_rating' => null,
            ];
        }
        return $projects;
    }

    /** URL canônica: absoluta, sem ?query, sem #hash, sem barra final. */
    public static function canonicalUrl($href)
    {
        $url = self::toAbsoluteUrl($href, self::BASE_URL);
        $url = preg_replace('/[?#].*$/', '', $url);
        return rtrim($url, '/');
    }

    private static function toAbsoluteUrl($href, $base)
    {
        if (preg_match('#^https?://#i', $href)) return $href;
        return $base . '/' . ltrim($href, '/');
    }

    private static function extractBudget($text)
    {
        $out = ['raw' => null, 'min' => null, 'max' => null, 'currency' => null];
        if (!$text) return $out;

        // Detecta símbolo de moeda
        $currency = null;
        if (preg_match('/R\$/u', $text)) $currency = 'BRL';
        elseif (preg_match('/US\$/u', $text)) $currency = 'USD';
        elseif (preg_match('/€|EUR/u', $text)) $currency = 'EUR';
        // "$" isolado => ambíguo => NULL (mantém raw)

        // Captura valores monetários no padrão pt-BR (1.500,00)
        if (preg_match_all('/(?:R\$|US\$|€)\s*([\d\.]+(?:,\d{2})?)/u', $text, $m)) {
            $vals = array_map([self::class, 'parseBrNumber'], $m[1]);
            $vals = array_values(array_filter($vals, fn($v) => $v !== null));
            if (!empty($vals)) {
                $out['raw'] = trim($m[0][0]);
                $out['currency'] = $currency;
                sort($vals);
                $out['min'] = $vals[0];
                $out['max'] = count($vals) > 1 ? end($vals) : null;
            }
        }
        return $out;
    }

    /** Converte "1.500,00" (pt-BR) em float 1500.00. */
    public static function parseBrNumber($s)
    {
        $s = trim($s);
        if ($s === '') return null;
        // ponto = milhar, vírgula = decimal
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
        return is_numeric($s) ? (float) $s : null;
    }

    // ---- utilitários DOM ----

    private static function attr($node, $name)
    {
        if ($node instanceof DOMElement && $node->hasAttribute($name)) {
            return trim($node->getAttribute($name));
        }
        return null;
    }

    private static function text($node)
    {
        return trim(preg_replace('/\s+/u', ' ', $node->textContent ?? ''));
    }

    /** Converte <br> em quebras de linha antes de extrair texto. */
    private static function brToText($html)
    {
        $t = preg_replace('#<br\s*/?>#i', "\n", $html);
        return trim(strip_tags($t));
    }

    /** Decodifica entidades HTML (&eacute; etc.) para UTF-8. */
    private static function decode($s)
    {
        if ($s === null) return null;
        return html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
