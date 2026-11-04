<?php

/**
 * Regras PURAS do rascunho de proposta gerado a partir da minuta da reunião.
 *
 * - Monta o entendimento do negócio (business_profile/opportunity/diagnosis)
 *   com os campos da ata estruturada (MeetingMinutesRules).
 * - Monta o prompt que pede à IA itens SOMENTE do catálogo ativo.
 * - Filtra a resposta da IA: descarta service_id fora do catálogo ativo,
 *   limita quantidade e número de itens, e transforma o resto em observação.
 */
class ProposalDraftRules
{
    public const MAX_ITEMS = 10;
    public const MAX_QUANTITY = 100;

    /** Linhas "- item" de uma lista, ou '' se vazia. */
    private static function bullets(array $items): string
    {
        $items = array_values(array_filter(array_map('trim', array_map('strval', $items)), 'strlen'));
        return $items ? '- ' . implode("\n- ", $items) : '';
    }

    /** Junta blocos "Título:\n- a\n- b" ignorando os vazios. */
    private static function blocks(array $blocks): string
    {
        $out = [];
        foreach ($blocks as $title => $items) {
            $b = self::bullets($items);
            if ($b !== '') $out[] = $title . ":\n" . $b;
        }
        return implode("\n\n", $out);
    }

    /**
     * Entendimento do negócio preenchido pela ata estruturada (já normalizada).
     * Campo sem informação na minuta fica vazio.
     */
    public static function businessContent(array $minutes): array
    {
        $profile = trim((string)($minutes['resumo'] ?? ''));
        $decisores = self::bullets($minutes['decisores'] ?? []);
        if ($decisores !== '') $profile = trim($profile . "\n\nDecisores:\n" . $decisores);

        return ProposalRules::normalizeContent([
            'business_profile' => $profile,
            'business_opportunity' => self::blocks([
                'Necessidades do cliente' => $minutes['necessidades'] ?? [],
                'Escopo desejado' => $minutes['escopo'] ?? [],
            ]),
            'business_diagnosis' => self::blocks([
                'Prazos citados' => $minutes['prazos'] ?? [],
                'Orçamento citado' => $minutes['orcamento'] ?? [],
                'Pontos em aberto' => $minutes['topicos_nao_resolvidos'] ?? [],
            ]),
        ]);
    }

    /**
     * Mensagens para a IA sugerir itens. Só os serviços ATIVOS entram na lista.
     *
     * @param array $minutes  ata estruturada normalizada
     * @param array $services linhas de service_catalog
     */
    public static function buildMessages(array $minutes, array $services): array
    {
        $catalog = [];
        foreach (self::activeById($services) as $id => $s) {
            $desc = trim(mb_substr(preg_replace('/\s+/', ' ', (string)($s['description'] ?? '')), 0, 200));
            $catalog[] = "{$id}: " . trim((string)$s['name']) . ($desc !== '' ? " ({$desc})" : '');
        }
        $system = 'Você monta o rascunho de uma proposta comercial em português do Brasil. '
            . 'Escolha itens SOMENTE da lista de serviços do catálogo, pelo número (service_id). '
            . 'Responda SOMENTE com um objeto JSON: '
            . '{"itens":[{"service_id":número,"quantidade":número inteiro,"motivo":"por que este serviço atende o cliente"}],'
            . '"fora_do_catalogo":["necessidade citada que não corresponde a nenhum serviço da lista"]}. '
            . 'Não invente serviços nem números. Se nada servir, devolva "itens": [].';
        $user = "Serviços do catálogo (service_id: nome):\n" . implode("\n", $catalog)
            . "\n\nMinuta da reunião:\n" . MeetingMinutesRules::textFromStructured($minutes);
        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ];
    }

    /** Serviços ativos indexados por id. */
    public static function activeById(array $services): array
    {
        $out = [];
        foreach ($services as $s) {
            if ((int)($s['active'] ?? 1) !== 1) continue;
            $out[(int)$s['id']] = $s;
        }
        return $out;
    }

    /**
     * Filtra a sugestão da IA. Devolve os itens no formato de proposal_items
     * (copiando nome, escopo, horas e valor do catálogo) e as observações:
     * o motivo de cada item e o que ficou fora do catálogo.
     *
     * @param array|string|null $aiResponse JSON (string) ou array decodificado
     * @return array{items:array<int,array>,notes:string[],outside:string[],discarded:int}
     */
    public static function filterSuggestions($aiResponse, array $services): array
    {
        $data = is_string($aiResponse) ? json_decode($aiResponse, true) : $aiResponse;
        $active = self::activeById($services);
        $items = [];
        $notes = [];
        $discarded = 0;
        $seen = [];

        foreach ((is_array($data) && is_array($data['itens'] ?? null)) ? $data['itens'] : [] as $it) {
            $id = is_array($it) && is_numeric($it['service_id'] ?? null) ? (int)$it['service_id'] : 0;
            if (!isset($active[$id]) || isset($seen[$id]) || count($items) >= self::MAX_ITEMS) {
                $discarded++;
                continue;
            }
            $seen[$id] = true;
            $s = $active[$id];
            $qty = (int)($it['quantidade'] ?? 1);
            $qty = max(1, min(self::MAX_QUANTITY, $qty));
            $items[] = [
                'service_id' => $id,
                'description' => (string)$s['name'],
                'scope' => (string)($s['description'] ?? ''),
                'quantity' => $qty,
                'hours' => $s['est_hours'] ?? '',
                'hourly_rate' => $s['hourly_rate'] ?? '',
                'billing' => $s['billing'] ?? 'once',
                'renewal' => $s['renewal'] ?? 'none',
                'recurrence_period' => $s['recurrence_period'] ?? null,
                'is_hosting' => (int)($s['is_hosting'] ?? 0),
            ];
            $motivo = trim((string)($it['motivo'] ?? ''));
            $notes[] = $s['name'] . ($motivo !== '' ? ': ' . mb_substr($motivo, 0, 300) : '');
        }

        $outside = [];
        foreach ((is_array($data) && is_array($data['fora_do_catalogo'] ?? null)) ? $data['fora_do_catalogo'] : [] as $o) {
            if (!is_string($o) || trim($o) === '') continue;
            $outside[] = mb_substr(trim($o), 0, 300);
        }
        return ['items' => $items, 'notes' => $notes, 'outside' => array_slice($outside, 0, 20), 'discarded' => $discarded];
    }

    /** Texto de observações do rascunho (o que a IA sugeriu e por quê). */
    public static function observations(array $filtered): string
    {
        $parts = [];
        if (!empty($filtered['notes'])) {
            $parts[] = "Itens sugeridos pela IA a partir da minuta (revise valores e quantidades):\n" . self::bullets($filtered['notes']);
        }
        if (!empty($filtered['outside'])) {
            $parts[] = "Pontos da reunião sem serviço no catálogo:\n" . self::bullets($filtered['outside']);
        }
        return implode("\n\n", $parts);
    }
}
