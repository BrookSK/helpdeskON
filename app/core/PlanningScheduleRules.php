<?php

/**
 * Regras puras (sem banco/HTTP) para montar o cronograma consolidado de
 * atividades do Planejamento exportado em PDF.
 *
 * Decide, de forma determinista e testável:
 *  - quais status ficam de fora quando se quer só as "próximas etapas"
 *    (concluídas/negadas/arquivadas);
 *  - como agrupar as atividades pela empresa responsável;
 *  - como ordenar as empresas e, dentro delas, as atividades por data;
 *  - os rótulos/cores legíveis de status e prioridade usados no documento;
 *  - um resumo textual dos filtros aplicados para o cabeçalho do PDF.
 *
 * Segue o padrão das demais *Rules (PlanningRules, AgendaRules, CrmRules):
 * métodos public static, sem efeitos colaterais, cobertos por testes de unidade.
 * É a fonte única de verdade entre o que o controller monta e o que o teste cobre.
 */
class PlanningScheduleRules
{
    /**
     * Status que NÃO entram quando o cronograma mostra só as "próximas etapas":
     * a demanda já saiu do fluxo ativo. Mantido em sincronia com
     * PlanningRules::INACTIVE_STATUSES.
     */
    public const INACTIVE_STATUSES = ['completed', 'denied', 'archived'];

    /** Rótulo da empresa usada quando a atividade não tem empresa vinculada. */
    public const NO_COMPANY_LABEL = 'Sem empresa';

    /** Rótulos legíveis de status (mesmos textos da tela planning/index). */
    public const STATUS_LABELS = [
        'open'                        => 'Aberto',
        'in_progress'                 => 'Em andamento',
        'aguardando_aprovacao_escopo' => 'Aguard. aprovação de escopo',
        'em_revisao_interna'          => 'Em Revisão Interna',
        'waiting_client'              => 'Aguardando',
        'em_homologacao'              => 'Em Homologação',
        'aprovado_producao'           => 'Aprov. Produção',
        'completed'                   => 'Concluído',
        'denied'                      => 'Negado',
        'archived'                    => 'Arquivado',
    ];

    /** Cores de status (mesma paleta da tela planning/index), usadas no PDF. */
    public const STATUS_COLORS = [
        'open'                        => '#1565c0',
        'in_progress'                 => '#e65100',
        'aguardando_aprovacao_escopo' => '#8d6e63',
        'em_revisao_interna'          => '#5c6bc0',
        'waiting_client'              => '#7b1fa2',
        'em_homologacao'              => '#0097a7',
        'aprovado_producao'           => '#8bc34a',
        'completed'                   => '#2e7d32',
        'denied'                      => '#d84315',
        'archived'                    => '#546e7a',
    ];

    /** Rótulos legíveis de prioridade. */
    public const PRIORITY_LABELS = [
        'low'    => 'Baixa',
        'medium' => 'Média',
        'high'   => 'Alta',
        'urgent' => 'Urgente',
    ];

    /** Uma atividade está ativa (é "próxima etapa", não concluída/negada/arquivada)? */
    public static function isActive($status): bool
    {
        return !in_array((string) $status, self::INACTIVE_STATUSES, true);
    }

    /** Rótulo legível do status (fallback: o próprio valor). */
    public static function statusLabel($status): string
    {
        $s = (string) $status;
        return self::STATUS_LABELS[$s] ?? $s;
    }

    /** Cor do status (fallback: cinza neutro). */
    public static function statusColor($status): string
    {
        $s = (string) $status;
        return self::STATUS_COLORS[$s] ?? '#546e7a';
    }

    /** Rótulo legível da prioridade (fallback: o próprio valor). */
    public static function priorityLabel($priority): string
    {
        $p = (string) $priority;
        return self::PRIORITY_LABELS[$p] ?? $p;
    }

    /**
     * Data usada para ordenar a atividade no cronograma: início da janela de
     * desenvolvimento quando houver, senão o prazo (due_date). Retorna null
     * quando a atividade não tem nenhuma data (vai para o fim da lista).
     *
     * @param array $card Linha do card (chaves start_date / due_date).
     */
    public static function sortDate(array $card): ?string
    {
        foreach (['start_date', 'due_date'] as $field) {
            $v = $card[$field] ?? null;
            if ($v !== null && trim((string) $v) !== '') {
                return (string) $v;
            }
        }
        return null;
    }

    /**
     * Filtra as atividades que entram no cronograma.
     *
     * Com onlyActive = true (padrão) remove concluídas/negadas/arquivadas, que
     * não são "próximas etapas". Com onlyActive = false mantém todas.
     *
     * @param array $cards      Linhas de cards.
     * @param bool  $onlyActive Manter só as ativas.
     * @return array            Lista reindexada.
     */
    public static function filterCards(array $cards, bool $onlyActive = true): array
    {
        if (!$onlyActive) {
            return array_values($cards);
        }
        $out = [];
        foreach ($cards as $card) {
            if (self::isActive($card['status'] ?? null)) {
                $out[] = $card;
            }
        }
        return $out;
    }

    /**
     * Agrupa as atividades pela empresa responsável e ordena tudo para o PDF.
     *
     * - Cards sem empresa caem em NO_COMPANY_LABEL.
     * - Dentro de cada empresa, as atividades são ordenadas por data
     *   (sortDate); as sem data vão para o fim, desempatando por id.
     * - Os grupos são ordenados alfabeticamente pelo nome da empresa, com o
     *   grupo "Sem empresa" sempre por último.
     *
     * Retorna uma lista de grupos: cada item é
     *   ['company' => string, 'cards' => array<linha de card>].
     *
     * @param array $cards Linhas de cards (já filtradas por filterCards, se desejado).
     */
    public static function groupByCompany(array $cards): array
    {
        $groups = [];
        foreach ($cards as $card) {
            $name = trim((string) ($card['company_name'] ?? ''));
            $key = $name !== '' ? $name : self::NO_COMPANY_LABEL;
            $groups[$key][] = $card;
        }

        // Ordena as atividades dentro de cada grupo.
        foreach ($groups as $key => $list) {
            usort($list, function ($a, $b) {
                $da = self::sortDate($a);
                $db = self::sortDate($b);
                // Sem data vai para o fim.
                if ($da === null && $db === null) {
                    return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
                }
                if ($da === null) return 1;
                if ($db === null) return -1;
                $ta = strtotime($da);
                $tb = strtotime($db);
                if ($ta === $tb) {
                    return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
                }
                return $ta <=> $tb;
            });
            $groups[$key] = $list;
        }

        // Ordena os grupos: alfabético, "Sem empresa" por último.
        $names = array_keys($groups);
        usort($names, function ($a, $b) {
            if ($a === self::NO_COMPANY_LABEL) return 1;
            if ($b === self::NO_COMPANY_LABEL) return -1;
            return strcasecmp($a, $b);
        });

        $result = [];
        foreach ($names as $name) {
            $result[] = ['company' => $name, 'cards' => $groups[$name]];
        }
        return $result;
    }

    /**
     * Monta o cronograma pronto para o PDF: filtra e agrupa por empresa.
     *
     * @param array $cards      Linhas vindas do model.
     * @param bool  $onlyActive Manter só atividades ativas (padrão: sim).
     */
    public static function buildSchedule(array $cards, bool $onlyActive = true): array
    {
        return self::groupByCompany(self::filterCards($cards, $onlyActive));
    }

    /** Soma o total de atividades em todos os grupos de um cronograma montado. */
    public static function countActivities(array $groups): int
    {
        $total = 0;
        foreach ($groups as $g) {
            $total += count($g['cards'] ?? []);
        }
        return $total;
    }

    /**
     * Formata uma data para exibição no PDF (d/m/Y). Retorna o placeholder
     * quando vazia/nula ou não parseável como data.
     */
    public static function formatDate($value, string $placeholder = '—'): string
    {
        if ($value === null) {
            return $placeholder;
        }
        $s = trim((string) $value);
        if ($s === '') {
            return $placeholder;
        }
        $ts = strtotime($s);
        return $ts === false ? $placeholder : date('d/m/Y', $ts);
    }

    /**
     * Remove HTML e normaliza espaços de um texto (descrição dos cards costuma
     * vir como HTML). Trunca para no máximo $max caracteres, com reticências.
     * Usado para a descrição aparecer legível e compacta no PDF.
     *
     * @param mixed $value Texto (possivelmente com HTML).
     * @param int   $max   Tamanho máximo (0 = sem truncar).
     */
    public static function plainText($value, int $max = 0): string
    {
        if ($value === null) {
            return '';
        }
        $s = (string) $value;
        // <br> e </p> viram espaço para não grudar palavras ao remover tags.
        $s = preg_replace('/<\s*(br|\/p|\/div|\/li)\s*\/?\s*>/i', ' ', $s);
        $s = strip_tags($s);
        $s = html_entity_decode($s, ENT_QUOTES, 'UTF-8');
        $s = preg_replace('/\s+/u', ' ', $s);
        $s = trim((string) $s);
        if ($max > 0 && mb_strlen($s, 'UTF-8') > $max) {
            $s = rtrim(mb_substr($s, 0, $max, 'UTF-8')) . '…';
        }
        return $s;
    }

    /**
     * Monta um resumo legível dos filtros aplicados, para o cabeçalho do PDF.
     * Só inclui os filtros efetivamente presentes. Retorna string vazia quando
     * nenhum filtro (além do escopo de acesso) foi aplicado.
     *
     * @param array $labels Mapa legível já resolvido pelo controller:
     *                      ['company' => 'DN Solutions', 'assigned' => 'Julia',
     *                       'requester' => 'Maria', 'statuses' => 'Em andamento',
     *                       'q' => 'texto']. Qualquer chave vazia é ignorada.
     */
    public static function filtersSummary(array $labels): string
    {
        $parts = [];
        $map = [
            'company'   => 'Empresa',
            'assigned'  => 'Responsável',
            'requester' => 'Solicitante',
            'statuses'  => 'Status',
            'q'         => 'Busca',
        ];
        foreach ($map as $key => $prefix) {
            $val = trim((string) ($labels[$key] ?? ''));
            if ($val !== '') {
                $parts[] = $prefix . ': ' . $val;
            }
        }
        return implode(' · ', $parts);
    }
}
