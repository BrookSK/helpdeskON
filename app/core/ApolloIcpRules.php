<?php
/**
 * Regras puras (sem banco/HTTP) de ICP e Lead Score da prospecção Apollo,
 * aplicadas sobre os dados da BUSCA (antes de gastar crédito com reveal).
 *
 * Motivação (bug de produção): o endpoint atual de busca do Apollo
 * (/mixed_people/api_search) devolve só id, primeiro nome, cargo e flags
 * "has_*" da empresa. NÃO traz seniority, país, site nem nº de funcionários.
 * O filtro ICP antigo lia esses campos e, como vinham sempre vazios, reprovava
 * 100% das pessoas ("350 fora do perfil"), por mais que o ICP fosse afrouxado.
 * O score também ficava baixo demais (só o cargo pontuava).
 *
 * Regras desta classe:
 *  1) O que dá para o Apollo filtrar na busca é mandado para a busca:
 *     senioridades e faixa de funcionários do ICP viram filtros da busca
 *     quando a campanha não definiu esses filtros na busca.
 *  2) Dado AUSENTE na busca nunca reprova (critério "desconhecido"). Dado
 *     PRESENTE e fora do ICP reprova, como antes.
 *  3) O score usa os sinais disponíveis: seniority (ou o cargo, quando a
 *     seniority não vem), has_country, has_employee_count + faixa filtrada.
 *  4) Paginação: a busca nova informa total_entries (sem pagination.total_pages).
 */
class ApolloIcpRules
{
    /** Senioridades consideradas "decisor" no score. */
    public const DECISOR = ['owner', 'founder', 'c_suite', 'partner', 'vp', 'head', 'director'];

    /** Limite de exibição da busca do Apollo: 500 páginas. */
    public const MAX_PAGES = 500;

    /**
     * Palavras do cargo => seniority do Apollo (ordem importa: a primeira que
     * casar vence). Usado só quando a busca não traz o campo seniority.
     */
    private const TITLE_SENIORITY = [
        'founder' => 'founder', 'fundador' => 'founder', 'fundadora' => 'founder', 'co-founder' => 'founder', 'cofounder' => 'founder',
        'owner' => 'owner', 'proprietário' => 'owner', 'proprietario' => 'owner', 'proprietária' => 'owner', 'proprietaria' => 'owner', 'dono' => 'owner', 'dona' => 'owner',
        'ceo' => 'c_suite', 'cfo' => 'c_suite', 'cto' => 'c_suite', 'coo' => 'c_suite', 'cmo' => 'c_suite', 'cio' => 'c_suite', 'cro' => 'c_suite',
        'chief' => 'c_suite', 'presidente' => 'c_suite', 'president' => 'c_suite',
        'sócio' => 'partner', 'socio' => 'partner', 'sócia' => 'partner', 'socia' => 'partner', 'partner' => 'partner',
        'vice-presidente' => 'vp', 'vice presidente' => 'vp', 'vp' => 'vp', 'vice president' => 'vp',
        'head' => 'head',
        'diretor' => 'director', 'diretora' => 'director', 'director' => 'director',
        'gerente' => 'manager', 'manager' => 'manager', 'coordenador' => 'manager', 'coordenadora' => 'manager',
    ];

    /**
     * Filtros efetivamente enviados à busca: os da campanha + o que do ICP o
     * Apollo consegue filtrar (só quando a campanha não definiu na busca).
     */
    public static function effectiveSearchFilters(array $filters, array $icp): array
    {
        if (empty($filters['person_seniorities']) && !empty($icp['seniorities'])) {
            $filters['person_seniorities'] = array_values($icp['seniorities']);
        }
        if (empty($filters['organization_num_employees_ranges'])
            && (!empty($icp['employee_min']) || !empty($icp['employee_max']))) {
            $min = max(1, (int) ($icp['employee_min'] ?? 1));
            $max = !empty($icp['employee_max']) ? (int) $icp['employee_max'] : 1000000;
            $filters['organization_num_employees_ranges'] = [$min . ',' . $max];
        }
        return $filters;
    }

    /** Seniority da pessoa: o campo do Apollo ou, na falta, deduzida do cargo. */
    public static function seniorityOf(array $person): ?string
    {
        $sen = strtolower(trim((string) ($person['seniority'] ?? '')));
        if ($sen !== '') return $sen;
        return self::inferSeniority((string) ($person['title'] ?? ''));
    }

    /** Deduz a seniority pelo cargo (palavra inteira). null = não deu para saber. */
    public static function inferSeniority(string $title): ?string
    {
        $t = mb_strtolower(trim($title), 'UTF-8');
        if ($t === '') return null;
        foreach (self::TITLE_SENIORITY as $word => $sen) {
            if (preg_match('/(^|[^\p{L}])' . preg_quote($word, '/') . '($|[^\p{L}])/u', $t)) return $sen;
        }
        return null;
    }

    /**
     * A pessoa atende ao ICP com os dados da busca?
     * Critério sem dado na busca = desconhecido = não reprova.
     *
     * @param array $searchFilters filtros efetivos enviados ao Apollo
     */
    public static function matchesIcp(array $person, array $icp, array $searchFilters = []): bool
    {
        if (empty($icp)) return true;
        $org = is_array($person['organization'] ?? null) ? $person['organization'] : [];

        if (!empty($icp['seniorities'])) {
            $wanted = array_map('strtolower', $icp['seniorities']);
            $sen = self::seniorityOf($person);
            if ($sen !== null && !in_array($sen, $wanted, true)) {
                // Seniority conhecida e fora do ICP. Exceção: se a busca já filtrou
                // pelo Apollo exatamente as senioridades do ICP, a dedução pelo cargo
                // (ex.: "Sócio e Diretor") não pode contradizer o Apollo.
                $searched = array_map('strtolower', (array) ($searchFilters['person_seniorities'] ?? []));
                $apolloGuarantees = !empty($searched) && !array_diff($searched, $wanted) && empty($person['seniority']);
                if (!$apolloGuarantees) return false;
            }
        }
        if (!empty($icp['titles_any'])) {
            $title = mb_strtolower((string) ($person['title'] ?? ''), 'UTF-8');
            if ($title !== '') {
                $ok = false;
                foreach ($icp['titles_any'] as $t) {
                    if (strpos($title, mb_strtolower((string) $t, 'UTF-8')) !== false) { $ok = true; break; }
                }
                if (!$ok) return false;
            }
        }
        $emp = (int) ($org['estimated_num_employees'] ?? 0);
        if (!empty($icp['employee_min']) && $emp > 0 && $emp < (int) $icp['employee_min']) return false;
        if (!empty($icp['employee_max']) && $emp > 0 && $emp > (int) $icp['employee_max']) return false;

        if (!empty($icp['require_website'])) {
            $known = array_key_exists('website_url', $org) || array_key_exists('primary_domain', $org);
            if ($known && empty($org['website_url']) && empty($org['primary_domain'])) return false;
        }
        return true;
    }

    /**
     * Lead Score com os dados da busca (pesos em icp_rules.score).
     * Padrão: decisor 35, cargo-alvo 30, porte 10, região 15, site 10, tecnologia 0.
     */
    public static function score(array $person, array $icp, array $searchFilters = []): int
    {
        $w = $icp['score'] ?? [];
        $org = is_array($person['organization'] ?? null) ? $person['organization'] : [];
        $score = 0;

        // Decisor: seniority (ou deduzida do cargo); ou a busca só trouxe decisores.
        $sen = self::seniorityOf($person);
        $searched = array_map('strtolower', (array) ($searchFilters['person_seniorities'] ?? []));
        $searchOnlyDecisors = !empty($searched) && !array_diff($searched, self::DECISOR);
        if (($sen !== null && in_array($sen, self::DECISOR, true)) || ($sen === null && $searchOnlyDecisors)) {
            $score += (int) ($w['decisor'] ?? 35);
        }

        if (!empty($icp['titles_any'])) {
            $title = mb_strtolower((string) ($person['title'] ?? ''), 'UTF-8');
            foreach ($icp['titles_any'] as $t) {
                if ($title !== '' && strpos($title, mb_strtolower((string) $t, 'UTF-8')) !== false) { $score += (int) ($w['title'] ?? 30); break; }
            }
        }

        // Porte: nº real quando vem; senão, empresa com porte conhecido pelo
        // Apollo E a busca filtrou por faixa de funcionários.
        $emp = (int) ($org['estimated_num_employees'] ?? 0);
        if ($emp > 0) {
            if ((empty($icp['employee_min']) || $emp >= (int) $icp['employee_min'])
                && (empty($icp['employee_max']) || $emp <= (int) $icp['employee_max'])) {
                $score += (int) ($w['size'] ?? 10);
            }
        } elseif (!empty($org['has_employee_count']) && !empty($searchFilters['organization_num_employees_ranges'])) {
            $score += (int) ($w['size'] ?? 10);
        }

        if (!empty($person['country']) || !empty($org['country']) || !empty($person['has_country']) || !empty($org['has_country'])) {
            $score += (int) ($w['region'] ?? 15);
        }
        if (!empty($org['website_url']) || !empty($org['primary_domain'])) $score += (int) ($w['website'] ?? 10);
        if (!empty($org['technology_names'])) $score += (int) ($w['technology'] ?? 0);

        return $score;
    }

    /**
     * Total de páginas da busca. Aceita o formato antigo (pagination.total_pages)
     * e o novo (total_entries no topo). Limitado a 500 (limite do Apollo).
     */
    public static function totalPages(array $data, int $perPage): int
    {
        $p = is_array($data['pagination'] ?? null) ? $data['pagination'] : [];
        if (!empty($p['total_pages'])) return min(self::MAX_PAGES, max(1, (int) $p['total_pages']));
        $total = $data['total_entries'] ?? ($p['total_entries'] ?? null);
        if ($total === null) return 1;
        return min(self::MAX_PAGES, max(1, (int) ceil(((int) $total) / max(1, $perPage))));
    }
}
