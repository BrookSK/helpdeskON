<?php
/**
 * Regras puras do NICHO da prospecção automática (sem banco/HTTP).
 *
 * Bug de produção: os nichos escolhidos na campanha iam para a busca do Apollo
 * juntos num único texto livre (q_keywords = "saúde logística"). O Apollo exige
 * TODAS as palavras ao mesmo tempo, então dois nichos = 0 resultados; e um nicho
 * em português, procurado como texto no perfil da pessoa, trazia pouquíssimos
 * contatos (ex.: 7).
 *
 * Agora:
 *  - cada nicho é traduzido para os SEGMENTOS (indústrias) do Apollo da empresa
 *    (organization_industries[], em inglês, ex.: "hospital & health care"). O
 *    Apollo trata a lista como "qualquer um destes", então mais nichos = mais
 *    resultados, e a busca continua gratuita (0 crédito);
 *  - termo que não é um nicho conhecido vira uma busca SEPARADA por palavra-chave
 *    (uma por termo, alternando entre elas), nunca mais junto com os outros.
 *  - o cursor de paginação guarda (busca atual, página) num único inteiro, para
 *    caber na coluna apollo_campaigns.search_page já existente.
 */
class ApolloNicheRules
{
    /** Cursor: busca * 1000 + página (o Apollo exibe no máximo 500 páginas). */
    private const CURSOR_BASE = 1000;

    /** Nicho (normalizado, sem acento) => segmentos do Apollo. */
    private const NICHE_INDUSTRIES = [
        'saude' => ['hospital & health care', 'medical practice', 'health, wellness & fitness', 'mental health care', 'medical devices', 'pharmaceuticals'],
        'clinica' => ['medical practice', 'hospital & health care'],
        'clinicas' => ['medical practice', 'hospital & health care'],
        'odontologia' => ['medical practice', 'hospital & health care'],
        'odonto' => ['medical practice', 'hospital & health care'],
        'dentista' => ['medical practice', 'hospital & health care'],
        'hospital' => ['hospital & health care'],
        'hospitais' => ['hospital & health care'],
        'farmacia' => ['pharmaceuticals'],
        'farmaceutico' => ['pharmaceuticals'],
        'estetica' => ['cosmetics', 'health, wellness & fitness'],
        'beleza' => ['cosmetics', 'health, wellness & fitness'],
        'academia' => ['health, wellness & fitness', 'sports'],
        'fitness' => ['health, wellness & fitness'],
        'veterinaria' => ['veterinary'],
        'pet' => ['veterinary'],
        'logistica' => ['logistics & supply chain', 'transportation/trucking/railroad', 'warehousing', 'package/freight delivery'],
        'transporte' => ['transportation/trucking/railroad', 'logistics & supply chain'],
        'transportadora' => ['transportation/trucking/railroad', 'logistics & supply chain'],
        'educacao' => ['education management', 'primary/secondary education', 'higher education', 'e-learning', 'professional training & coaching'],
        'escola' => ['primary/secondary education', 'education management'],
        'ensino' => ['education management', 'primary/secondary education', 'higher education', 'e-learning'],
        'varejo' => ['retail', 'consumer goods', 'supermarkets'],
        'comercio' => ['retail', 'wholesale', 'consumer goods'],
        'atacado' => ['wholesale', 'import & export'],
        'distribuidora' => ['wholesale', 'import & export'],
        'industria' => ['machinery', 'industrial automation', 'mechanical or industrial engineering', 'electrical/electronic manufacturing', 'plastics', 'chemicals', 'packaging & containers', 'textiles', 'paper & forest products', 'glass, ceramics & concrete'],
        'fabrica' => ['machinery', 'industrial automation', 'mechanical or industrial engineering', 'electrical/electronic manufacturing', 'plastics'],
        'manufatura' => ['machinery', 'industrial automation', 'mechanical or industrial engineering', 'electrical/electronic manufacturing'],
        'metalurgia' => ['mining & metals', 'machinery'],
        'tecnologia' => ['information technology & services', 'computer software', 'internet', 'computer & network security'],
        'ti' => ['information technology & services', 'computer software'],
        'software' => ['computer software', 'information technology & services'],
        'construcao civil' => ['construction', 'building materials', 'civil engineering'],
        'construcao' => ['construction', 'building materials', 'civil engineering'],
        'construtora' => ['construction', 'civil engineering'],
        'engenharia' => ['civil engineering', 'mechanical or industrial engineering'],
        'arquitetura' => ['architecture & planning'],
        'imobiliario' => ['real estate', 'commercial real estate'],
        'imobiliaria' => ['real estate', 'commercial real estate'],
        'imoveis' => ['real estate', 'commercial real estate'],
        'financeiro' => ['financial services', 'banking', 'investment management', 'capital markets'],
        'financas' => ['financial services', 'banking', 'investment management'],
        'contabilidade' => ['accounting'],
        'contabil' => ['accounting'],
        'seguros' => ['insurance'],
        'seguradora' => ['insurance'],
        'advocacia' => ['law practice', 'legal services'],
        'juridico' => ['law practice', 'legal services'],
        'direito' => ['law practice', 'legal services'],
        'agronegocio' => ['farming', 'ranching', 'dairy', 'food production'],
        'agro' => ['farming', 'ranching', 'dairy', 'food production'],
        'agricultura' => ['farming'],
        'alimentos' => ['food production', 'food & beverages'],
        'alimenticio' => ['food production', 'food & beverages'],
        'restaurante' => ['restaurants', 'food & beverages'],
        'restaurantes' => ['restaurants', 'food & beverages'],
        'hotelaria' => ['hospitality', 'leisure, travel & tourism'],
        'turismo' => ['leisure, travel & tourism', 'hospitality'],
        'e-commerce' => ['internet', 'retail', 'consumer goods'],
        'ecommerce' => ['internet', 'retail', 'consumer goods'],
        'servicos' => ['management consulting', 'facilities services', 'outsourcing/offshoring', 'professional training & coaching', 'human resources', 'staffing & recruiting'],
        'consultoria' => ['management consulting'],
        'marketing' => ['marketing & advertising'],
        'publicidade' => ['marketing & advertising'],
        'rh' => ['human resources', 'staffing & recruiting'],
        'recursos humanos' => ['human resources', 'staffing & recruiting'],
        'energia' => ['oil & energy', 'renewables & environment', 'utilities'],
        'energia solar' => ['renewables & environment'],
        'automotivo' => ['automotive'],
        'concessionaria' => ['automotive'],
        'telecom' => ['telecommunications'],
        'telecomunicacoes' => ['telecommunications'],
        'moda' => ['apparel & fashion', 'textiles'],
        'confeccao' => ['apparel & fashion', 'textiles'],
        'eventos' => ['events services'],
        'seguranca' => ['security & investigations'],
        'limpeza' => ['facilities services'],
        'ambiental' => ['environmental services', 'renewables & environment'],
        'mineracao' => ['mining & metals'],
        'quimica' => ['chemicals'],
        'grafica' => ['printing'],
        'moveis' => ['furniture'],
        'ong' => ['non-profit organization management'],
    ];

    /** Nichos reconhecidos (normalizados), para avisar na tela quando um termo não é. */
    public static function nicheKeys(): array
    {
        return array_keys(self::NICHE_INDUSTRIES);
    }

    /** Minúsculo, sem acento, espaços únicos. */
    public static function normalize(string $term): string
    {
        $t = mb_strtolower(trim($term), 'UTF-8');
        $t = strtr($t, ['á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'õ' => 'o', 'ô' => 'o', 'ú' => 'u', 'ü' => 'u', 'ç' => 'c']);
        return preg_replace('/\s+/', ' ', $t);
    }

    /** Todos os segmentos do Apollo conhecidos (para aceitar o nome em inglês digitado). */
    public static function knownIndustries(): array
    {
        static $all = null;
        if ($all === null) $all = array_values(array_unique(array_merge(...array_values(self::NICHE_INDUSTRIES))));
        return $all;
    }

    /** Segmentos do Apollo para um nicho; [] se não for um nicho conhecido. */
    public static function industriesFor(string $term): array
    {
        $n = self::normalize($term);
        if ($n === '') return [];
        if (isset(self::NICHE_INDUSTRIES[$n])) return self::NICHE_INDUSTRIES[$n];
        // Nome de segmento do Apollo digitado direto (ex.: "hospital & health care").
        if (in_array($n, self::knownIndustries(), true)) return [$n];
        return [];
    }

    /**
     * Nichos da campanha. Formato novo: filters['niches'] (lista). Formato antigo:
     * q_keywords com os nichos colados por espaço ("construção civil saúde"),
     * separado de volta reconhecendo os nichos conhecidos (inclusive de 2 palavras).
     */
    public static function nichesFromFilters(array $filters): array
    {
        if (!empty($filters['niches']) && is_array($filters['niches'])) {
            return array_values(array_unique(array_filter(array_map(fn($v) => trim((string) $v), $filters['niches']), fn($v) => $v !== '')));
        }
        $q = trim((string) ($filters['q_keywords'] ?? ''));
        if ($q === '') return [];
        $words = preg_split('/[\s,;]+/u', $q, -1, PREG_SPLIT_NO_EMPTY);
        $out = [];
        for ($i = 0; $i < count($words); $i++) {
            // Tenta primeiro o nicho de 2 palavras (ex.: "construção civil").
            if ($i + 1 < count($words)) {
                $two = $words[$i] . ' ' . $words[$i + 1];
                if (isset(self::NICHE_INDUSTRIES[self::normalize($two)])) { $out[] = $two; $i++; continue; }
            }
            $out[] = $words[$i];
        }
        return array_values(array_unique($out));
    }

    /**
     * Separa os nichos em segmentos do Apollo e termos sem tradução.
     * @return array{industries:string[],keywords:string[],mapped:array<string,string[]>}
     */
    public static function resolve(array $niches): array
    {
        $industries = [];
        $keywords = [];
        $mapped = [];
        foreach ($niches as $term) {
            $term = trim((string) $term);
            if ($term === '') continue;
            $ind = self::industriesFor($term);
            if ($ind) { $mapped[$term] = $ind; $industries = array_merge($industries, $ind); }
            else $keywords[] = $term;
        }
        return ['industries' => array_values(array_unique($industries)), 'keywords' => array_values(array_unique($keywords)), 'mapped' => $mapped];
    }

    /**
     * Buscas que a campanha faz no Apollo (uma por vez, alternando):
     *  - uma com todos os segmentos dos nichos conhecidos (organization_industries);
     *  - uma por termo desconhecido (q_keywords com UM termo só).
     * Sem nicho: uma busca só, com os demais filtros.
     * @return array<int,array> filtros de cada busca
     */
    public static function searchVariants(array $filters): array
    {
        $r = self::resolve(self::nichesFromFilters($filters));
        $base = $filters;
        unset($base['niches'], $base['q_keywords'], $base['page'], $base['per_page']);

        $variants = [];
        if ($r['industries']) {
            $v = $base;
            $existing = is_array($base['organization_industries'] ?? null) ? $base['organization_industries'] : [];
            $v['organization_industries'] = array_values(array_unique(array_merge($existing, $r['industries'])));
            $variants[] = $v;
        }
        foreach ($r['keywords'] as $kw) {
            $v = $base;
            $v['q_keywords'] = $kw;
            $variants[] = $v;
        }
        return $variants ?: [$base];
    }

    public static function encodeCursor(int $variant, int $page): int
    {
        return max(0, $variant) * self::CURSOR_BASE + max(1, min(self::CURSOR_BASE - 1, $page));
    }

    /** @return array{0:int,1:int} [busca, página]; reinicia se a busca não existe mais. */
    public static function decodeCursor($cursor, int $variantCount): array
    {
        $c = max(1, (int) $cursor);
        $variant = intdiv($c, self::CURSOR_BASE);
        $page = $c % self::CURSOR_BASE ?: 1;
        if ($variant >= max(1, $variantCount)) return [0, 1];
        return [$variant, $page];
    }

    /**
     * Próxima posição depois de ler uma página.
     * Fim da busca atual (página vazia ou última página) => próxima busca, página 1.
     * @return array{0:int,1:int,2:bool} [busca, página, terminou a busca atual?]
     */
    public static function advance(int $variant, int $page, int $totalPages, int $variantCount, bool $empty): array
    {
        $variantCount = max(1, $variantCount);
        if ($empty || ($totalPages > 0 && $page >= $totalPages)) {
            return [($variant + 1) % $variantCount, 1, true];
        }
        return [$variant, $page + 1, false];
    }
}
