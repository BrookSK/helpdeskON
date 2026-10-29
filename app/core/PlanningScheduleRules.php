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

    /**
     * Pares [fundo, texto] dos badges de status, espelhando as classes
     * .badge-* da identidade visual do site (app/views/layouts/header.php):
     * pílula de fundo claro com texto colorido. Fonte única para o PDF ficar
     * fiel à tela, em vez do badge sólido genérico.
     */
    public const STATUS_BADGE = [
        'open'                        => ['#e3f2fd', '#1565c0'],
        'in_progress'                 => ['#fff3e0', '#e65100'],
        'aguardando_aprovacao_escopo' => ['#efebe9', '#8d6e63'],
        'em_revisao_interna'          => ['#e8eaf6', '#5c6bc0'],
        'waiting_client'              => ['#fce4ec', '#c62828'],
        'em_homologacao'              => ['#e0f7fa', '#0097a7'],
        'aprovado_producao'           => ['#f1f8e9', '#689f38'],
        'completed'                   => ['#e8f5e9', '#2e7d32'],
        'denied'                      => ['#fbe9e7', '#d84315'],
        'archived'                    => ['#eceff1', '#546e7a'],
    ];

    /** Rótulos legíveis de prioridade. */
    public const PRIORITY_LABELS = [
        'low'    => 'Baixa',
        'medium' => 'Média',
        'high'   => 'Alta',
        'urgent' => 'Urgente',
    ];

    /*
     * Calibração do resumo da descrição no PDF (critério "até ~15 linhas"):
     *
     * O PHP não conhece a renderização do dompdf, então aproximamos "linhas"
     * por caracteres, medindo a largura da coluna "Atividade" e a fonte usada:
     *   - A4 paisagem, margens 1,2cm cada lado  => largura útil ~26,3 cm;
     *   - coluna "Atividade" = 34% dessa largura => ~8,9 cm;
     *   - descrição em DejaVu Sans 7,5pt: largura média de caractere ~1,45mm,
     *     o que cabe ~60 caracteres por linha dentro da coluna (com folga para
     *     o padding da célula).
     *   - 15 linhas * ~60 caracteres  => ~900 caracteres.
     *
     * Usamos DESC_SUMMARY_MAX_CHARS como o teto antes de resumir. Descrições
     * com até esse tamanho ficam INALTERADAS; acima dele, são resumidas.
     * O valor é uma estimativa calibrável: ajuste olhando o PDF real.
     */
    public const DESC_LINE_CHARS = 60;
    public const DESC_MAX_LINES = 15;
    public const DESC_SUMMARY_MAX_CHARS = self::DESC_LINE_CHARS * self::DESC_MAX_LINES; // ~900

    /** Marca de continuação acrescentada ao fim de uma descrição resumida. */
    public const DESC_CONTINUATION = ' […] (descrição completa no card)';

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

    /**
     * Par de cores do badge de status para o PDF: ['bg' => fundo, 'fg' => texto],
     * fiel às pílulas .badge-* do site. Fallback neutro (cinza claro) para
     * status desconhecido.
     */
    public static function statusBadge($status): array
    {
        $s = (string) $status;
        $pair = self::STATUS_BADGE[$s] ?? ['#eceff1', '#546e7a'];
        return ['bg' => $pair[0], 'fg' => $pair[1]];
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
     * Resume a descrição de uma atividade para o PDF (resumo extrativo, sem IA).
     *
     * Objetivo: descrições que ocupariam mais de ~15 linhas na largura atual
     * viram uma "minuta" que preserva o início (onde normalmente está o cerne)
     * e sinaliza que há continuação, em vez de dominar o cronograma. Descrições
     * dentro do limite ficam INALTERADAS.
     *
     * Estratégia (determinista e testável):
     *  1) limpa o HTML com plainText (texto corrido, sem tags);
     *  2) se couber em $maxChars, devolve o texto completo (sem marca nenhuma);
     *  3) senão, corta no FIM DE FRASE mais próximo antes do limite (último
     *     '.', '!' ou '?'), para não terminar no meio de uma ideia. Se não
     *     houver pontuação numa posição razoável (>= 50% do limite, para não
     *     resumir cedo demais), recua até o fim de PALAVRA mais próximo;
     *  4) acrescenta a marca de continuação (DESC_CONTINUATION).
     *
     * @param mixed $value    Descrição (possivelmente HTML).
     * @param int   $maxChars Teto de caracteres (default: DESC_SUMMARY_MAX_CHARS).
     */
    public static function summarizeDescription($value, int $maxChars = self::DESC_SUMMARY_MAX_CHARS): string
    {
        $text = self::plainText($value, 0); // limpa HTML, sem truncar
        if ($text === '') {
            return '';
        }
        if ($maxChars <= 0 || mb_strlen($text, 'UTF-8') <= $maxChars) {
            return $text; // dentro do limite: inalterada
        }

        // Janela candidata: do início até o teto.
        $window = mb_substr($text, 0, $maxChars, 'UTF-8');

        // 1) Tenta cortar no fim de frase mais próximo dentro da janela.
        $cut = -1;
        foreach (['.', '!', '?'] as $mark) {
            $pos = mb_strrpos($window, $mark, 0, 'UTF-8');
            if ($pos !== false && $pos > $cut) {
                $cut = $pos;
            }
        }
        // Só aceita o fim de frase se não resumir cedo demais (>= metade do teto).
        if ($cut >= (int) ($maxChars * 0.5)) {
            $summary = mb_substr($text, 0, $cut + 1, 'UTF-8'); // inclui a pontuação
        } else {
            // 2) Fallback: recua para o fim de palavra mais próximo.
            $space = mb_strrpos($window, ' ', 0, 'UTF-8');
            $summary = $space !== false
                ? mb_substr($text, 0, $space, 'UTF-8')
                : $window;
        }

        $summary = rtrim($summary);
        return $summary . self::DESC_CONTINUATION;
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

    /* =====================================================================
     * CALENDÁRIO VISUAL (export PDF em formato de calendário mensal).
     *
     * Porta determinista e testável da lógica do calendário da tela
     * (app/views/planning/index.php): categoria de cor por prazo/status
     * (DEADLINE_COLORS/deadlineCategory) e montagem do grid mensal
     * domingo→sábado com distribuição das demandas por dia. É a fonte única
     * de verdade entre o que o PDF desenha e o que o calendário mostra.
     * ===================================================================== */

    /** Status "em andamento" (fora de aberto, ainda não concluído/negado). */
    public const IN_PROGRESS_STATUSES = ['in_progress', 'em_revisao_interna', 'waiting_client', 'em_homologacao', 'aprovado_producao'];

    /** Janela (dias) que caracteriza "próximo do vencimento". */
    public const NEAR_DUE_DAYS = 2;

    /** Nomes dos meses em pt-BR (índice 1..12). */
    public const MONTH_NAMES = [
        1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
        5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
        9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
    ];

    /** Cabeçalho das colunas do calendário (domingo→sábado). */
    public const WEEKDAY_LABELS = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

    /**
     * Paleta de cores por categoria de prazo/status — espelha DEADLINE_COLORS
     * do calendário da tela. Cada entrada: [key, bg, border, text, label].
     */
    public const DEADLINE_COLORS = [
        'overdue'     => ['key' => 'overdue',     'bg' => '#fde8e8', 'border' => '#dc2626', 'text' => '#991b1b', 'label' => 'Passado e não concluído'],
        'done'        => ['key' => 'done',        'bg' => '#cfe9d0', 'border' => '#7bbf80', 'text' => '#2e5b31', 'label' => 'Passado e concluído'],
        'on_track'    => ['key' => 'on_track',    'bg' => '#e7effd', 'border' => '#2563eb', 'text' => '#1e3a8a', 'label' => 'Dentro do prazo'],
        'near_due'    => ['key' => 'near_due',    'bg' => '#fef7d6', 'border' => '#f59e0b', 'text' => '#7a5900', 'label' => 'Próximo do vencimento'],
        'due_today'   => ['key' => 'due_today',   'bg' => '#fde8e8', 'border' => '#dc2626', 'text' => '#991b1b', 'label' => 'Na data de vencimento'],
        'in_progress' => ['key' => 'in_progress', 'bg' => '#ede9fe', 'border' => '#7c3aed', 'text' => '#5b21b6', 'label' => 'Em andamento'],
        'denied'      => ['key' => 'denied',      'bg' => '#f1f1f3', 'border' => '#9ca3af', 'text' => '#4b5563', 'label' => 'Negada'],
        'none'        => ['key' => 'none',        'bg' => '#eef1f4', 'border' => '#94a3b8', 'text' => '#475569', 'label' => 'Sem prazo definido'],
    ];

    /** Converte uma data (string Y-m-d...) para 'Y-m-d' (meia-noite). Null se inválida. */
    private static function dayKey($value): ?string
    {
        if ($value === null) return null;
        $s = trim((string) $value);
        if ($s === '') return null;
        $ts = strtotime($s);
        return $ts === false ? null : date('Y-m-d', $ts);
    }

    /**
     * Categoria de cor de uma demanda, a partir de status + due_date, igual ao
     * deadlineCategory() do calendário. Retorna uma das entradas de
     * DEADLINE_COLORS (array com key/bg/border/text/label).
     *
     * @param array  $card     Linha do card (status, due_date).
     * @param string $todayStr Data de referência 'Y-m-d' (default: hoje).
     */
    public static function deadlineCategory(array $card, ?string $todayStr = null): array
    {
        $today = $todayStr !== null ? $todayStr : date('Y-m-d');
        $status = (string) ($card['status'] ?? '');
        $completed = $status === 'completed';
        $denied = $status === 'denied';
        $inProgress = in_array($status, self::IN_PROGRESS_STATUSES, true);

        if ($completed) return self::DEADLINE_COLORS['done'];
        if ($denied) return self::DEADLINE_COLORS['denied'];

        $due = self::dayKey($card['due_date'] ?? null);
        if ($due === null) {
            return $inProgress ? self::DEADLINE_COLORS['in_progress'] : self::DEADLINE_COLORS['none'];
        }

        if ($due < $today) return self::DEADLINE_COLORS['overdue'];
        if ($due === $today) return self::DEADLINE_COLORS['due_today'];

        // Futuro e dentro do prazo: em andamento tem prioridade.
        if ($inProgress) return self::DEADLINE_COLORS['in_progress'];
        $diffDays = (int) floor((strtotime($due) - strtotime($today)) / 86400);
        if ($diffDays <= self::NEAR_DUE_DAYS) return self::DEADLINE_COLORS['near_due'];
        return self::DEADLINE_COLORS['on_track'];
    }

    /**
     * Os dias "ocupados" por uma demanda, igual à regra do calendário:
     *  - start_date + end_date  => todos os dias do intervalo [start..end];
     *  - só start_date          => o dia de start;
     *  - due_date               => o dia do prazo (somado ao conjunto).
     * Uma demanda sem NENHUMA data retorna lista vazia (não aparece).
     *
     * @return string[] Dias 'Y-m-d' únicos e ordenados.
     */
    public static function cardDays(array $card): array
    {
        $days = [];
        $start = self::dayKey($card['start_date'] ?? null);
        $end = self::dayKey($card['end_date'] ?? null);
        $due = self::dayKey($card['due_date'] ?? null);

        if ($start !== null && $end !== null) {
            $cur = strtotime($start);
            $stop = strtotime($end);
            // Protege contra end < start: cai só no start.
            if ($stop < $cur) {
                $days[$start] = true;
            } else {
                // Limite de segurança para não varrer intervalos absurdos.
                $guard = 0;
                while ($cur <= $stop && $guard < 3660) {
                    $days[date('Y-m-d', $cur)] = true;
                    $cur = strtotime('+1 day', $cur);
                    $guard++;
                }
            }
        } elseif ($start !== null) {
            $days[$start] = true;
        }

        if ($due !== null) {
            $days[$due] = true;
        }

        $out = array_keys($days);
        sort($out);
        return $out;
    }

    /**
     * Lista os meses (chave 'Y-m') que uma demanda toca, a partir de cardDays.
     * @return string[] Meses únicos e ordenados.
     */
    public static function cardMonths(array $card): array
    {
        $months = [];
        foreach (self::cardDays($card) as $d) {
            $months[substr($d, 0, 7)] = true;
        }
        $out = array_keys($months);
        sort($out);
        return $out;
    }

    /**
     * Determina quais meses o PDF deve conter: um por mês que tenha ao menos
     * uma demanda, do primeiro ao último mês tocado (contíguo, sem buracos),
     * para o calendário não "pular" meses no meio do intervalo.
     *
     * @param array $cards Linhas de cards (já filtradas por empresa/escopo).
     * @return string[]    Meses 'Y-m' em ordem crescente (vazio se não há datas).
     */
    public static function monthsForCards(array $cards): array
    {
        $touched = [];
        foreach ($cards as $card) {
            foreach (self::cardMonths($card) as $m) {
                $touched[$m] = true;
            }
        }
        if (empty($touched)) {
            return [];
        }
        $keys = array_keys($touched);
        sort($keys);
        $first = $keys[0];
        $last = $keys[count($keys) - 1];

        // Preenche o intervalo contíguo entre o primeiro e o último mês.
        $out = [];
        $cur = strtotime($first . '-01');
        $stop = strtotime($last . '-01');
        $guard = 0;
        while ($cur <= $stop && $guard < 600) {
            $out[] = date('Y-m', $cur);
            $cur = strtotime('+1 month', $cur);
            $guard++;
        }
        return $out;
    }

    /**
     * Monta o grid de um mês no formato domingo→sábado, igual ao calendário:
     * 6 semanas de 7 dias; os dias fora do mês corrente ficam marcados.
     *
     * @param int $year  Ano (ex.: 2026).
     * @param int $month Mês 1..12.
     * @return array     Lista de semanas; cada semana é lista de 7 dias:
     *                   ['date' => 'Y-m-d', 'day' => int, 'otherMonth' => bool].
     */
    public static function monthGrid(int $year, int $month): array
    {
        $firstTs = mktime(0, 0, 0, $month, 1, $year);
        $startDow = (int) date('w', $firstTs); // 0=Dom .. 6=Sáb

        $weeks = [];
        // Primeiro dia renderizado = 1 - startDow (pode cair no mês anterior).
        $cursor = strtotime(($year) . '-' . sprintf('%02d', $month) . '-01');
        $cursor = strtotime('-' . $startDow . ' day', $cursor);
        for ($w = 0; $w < 6; $w++) {
            $week = [];
            for ($d = 0; $d < 7; $d++) {
                $date = date('Y-m-d', $cursor);
                $week[] = [
                    'date'       => $date,
                    'day'        => (int) date('j', $cursor),
                    'otherMonth' => ((int) date('n', $cursor)) !== $month,
                ];
                $cursor = strtotime('+1 day', $cursor);
            }
            $weeks[] = $week;
        }
        return $weeks;
    }

    /**
     * Indexa as demandas por dia ('Y-m-d' => lista de cards) para um mês,
     * considerando apenas os dias que caem DENTRO do mês pedido (o grid pode
     * exibir dias de outros meses, mas não repetimos a demanda neles). Cada
     * card recebe a categoria de cor já resolvida em ['_cat'].
     *
     * @param array  $cards    Linhas de cards.
     * @param int    $year     Ano.
     * @param int    $month    Mês 1..12.
     * @param string $todayStr Data de referência 'Y-m-d' para as cores.
     * @return array           Mapa dia => lista de cards (com '_cat').
     */
    public static function cardsByDayForMonth(array $cards, int $year, int $month, ?string $todayStr = null): array
    {
        $prefix = sprintf('%04d-%02d', $year, $month);
        $byDay = [];
        foreach ($cards as $card) {
            $cat = self::deadlineCategory($card, $todayStr);
            foreach (self::cardDays($card) as $day) {
                if (strpos($day, $prefix) !== 0) {
                    continue; // só dias do mês pedido
                }
                $entry = $card;
                $entry['_cat'] = $cat;
                $byDay[$day][] = $entry;
            }
        }
        // Ordena os cards de cada dia por título para uma saída estável.
        foreach ($byDay as $day => $list) {
            usort($list, function ($a, $b) {
                return strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''))
                    ?: ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
            });
            $byDay[$day] = $list;
        }
        return $byDay;
    }

    /** Nome legível de um mês 'Y-m' (ex.: 'Outubro 2026'). */
    public static function monthLabel(string $ym): string
    {
        $y = (int) substr($ym, 0, 4);
        $m = (int) substr($ym, 5, 2);
        $name = self::MONTH_NAMES[$m] ?? $ym;
        return $name . ' ' . $y;
    }

    /**
     * Rótulo do período coberto pelo cronograma, para a capa do PDF.
     * Ex.: ['2026-08','2026-09'] => "Agosto 2026 – Setembro 2026".
     * Um único mês => só o nome dele. Lista vazia => string vazia.
     *
     * @param string[] $months Meses 'Y-m' em ordem crescente (de monthsForCards).
     */
    public static function periodLabel(array $months): string
    {
        if (empty($months)) {
            return '';
        }
        $first = self::monthLabel($months[0]);
        $last = self::monthLabel($months[count($months) - 1]);
        return $first === $last ? $first : ($first . ' – ' . $last);
    }

    /**
     * Intervalo contíguo [início, fim] que uma demanda ocupa no calendário,
     * fiel ao calendário da tela (buildSpanSegments):
     *   - start_date + end_date  => [start, end] (faixa de desenvolvimento);
     *   - só start_date          => [start, start] (1 dia);
     *   - senão, só due_date     => [due, due]   (marcador de prazo de 1 dia).
     * Quando há janela de desenvolvimento, o due_date NÃO estende a faixa: ele
     * já é coberto se cair dentro, e um prazo fora da janela é tratado como um
     * segmento próprio de 1 dia (ver weekSegments).
     *
     * @return array|null ['start'=>'Y-m-d','end'=>'Y-m-d','kind'=>'dev'|'due'] ou null.
     */
    public static function cardPrimarySpan(array $card): ?array
    {
        $start = self::dayKey($card['start_date'] ?? null);
        $end = self::dayKey($card['end_date'] ?? null);
        $due = self::dayKey($card['due_date'] ?? null);

        if ($start !== null && $end !== null) {
            if (strtotime($end) < strtotime($start)) $end = $start; // protege invertido
            return ['start' => $start, 'end' => $end, 'kind' => 'dev'];
        }
        if ($start !== null) {
            return ['start' => $start, 'end' => $start, 'kind' => 'dev'];
        }
        if ($due !== null) {
            return ['start' => $due, 'end' => $due, 'kind' => 'due'];
        }
        return null;
    }

    /**
     * Monta os SEGMENTOS por semana para desenhar cada demanda como UMA faixa
     * contínua ao longo dos dias (em vez de repetir a demanda em cada célula).
     * Espelha a lógica do calendário da tela (buildSpanSegments + lanes +
     * marcador de due fora da janela), adaptada para a grade do PDF.
     *
     * Para cada semana (7 dias), devolve:
     *   ['lanes' => int, 'segments' => [
     *       ['card'=>linha, '_cat'=>cat, 'colStart'=>0..6, 'colEnd'=>0..6,
     *        'isStart'=>bool, 'isEnd'=>bool, 'kind'=>'dev'|'due', 'lane'=>int],
     *       ...
     *   ]]
     * colStart/colEnd são índices de coluna (0=Dom .. 6=Sáb) dentro da semana.
     * isStart/isEnd indicam se a faixa começa/termina de fato naquela semana
     * (para arredondar só as pontas reais e pôr a marca escura só no início).
     *
     * @param array  $cards     Demandas já filtradas (com datas).
     * @param array  $weekDates Lista de 7 dias da semana: cada um ['date'=>'Y-m-d',...].
     * @param string $todayStr  Data de referência 'Y-m-d' para as cores.
     */
    public static function weekSegments(array $cards, array $weekDates, ?string $todayStr = null): array
    {
        if (count($weekDates) !== 7) {
            return ['lanes' => 0, 'segments' => []];
        }
        $weekStart = $weekDates[0]['date'];
        $weekEnd = $weekDates[6]['date'];
        $wsTs = strtotime($weekStart);
        $weTs = strtotime($weekEnd);

        // Índice de coluna (0..6) de uma data dentro da semana.
        $colOf = function (string $d) use ($wsTs): int {
            $c = (int) round((strtotime($d) - $wsTs) / 86400);
            return max(0, min(6, $c));
        };

        $segments = [];
        foreach ($cards as $card) {
            $cat = self::deadlineCategory($card, $todayStr);
            $start = self::dayKey($card['start_date'] ?? null);
            $end = self::dayKey($card['end_date'] ?? null);
            $due = self::dayKey($card['due_date'] ?? null);

            // 1) Faixa de desenvolvimento (start→end ou start simples).
            $devStart = null;
            $devEnd = null;
            if ($start !== null && $end !== null) {
                $devStart = $start;
                $devEnd = (strtotime($end) < strtotime($start)) ? $start : $end;
            } elseif ($start !== null) {
                $devStart = $start;
                $devEnd = $start;
            }
            if ($devStart !== null) {
                $segStartTs = max(strtotime($devStart), $wsTs);
                $segEndTs = min(strtotime($devEnd), $weTs);
                if ($segStartTs <= $weTs && $segEndTs >= $wsTs) {
                    $segments[] = [
                        'card'     => $card,
                        '_cat'     => $cat,
                        'colStart' => $colOf(date('Y-m-d', $segStartTs)),
                        'colEnd'   => $colOf(date('Y-m-d', $segEndTs)),
                        'isStart'  => (strtotime($devStart) >= $wsTs),
                        'isEnd'    => (strtotime($devEnd) <= $weTs),
                        'kind'     => 'dev',
                        'id'       => (int) ($card['id'] ?? 0),
                    ];
                }
            }

            // 2) Marcador de prazo (due) de 1 dia — só se estiver nesta semana e
            //    fora da janela de desenvolvimento (mesma regra da tela).
            if ($due !== null) {
                $dueTs = strtotime($due);
                $inWeek = $dueTs >= $wsTs && $dueTs <= $weTs;
                $insideDev = $devStart !== null
                    && $dueTs >= strtotime($devStart) && $dueTs <= strtotime($devEnd);
                if ($inWeek && !$insideDev) {
                    $col = $colOf($due);
                    $segments[] = [
                        'card'     => $card,
                        '_cat'     => $cat,
                        'colStart' => $col,
                        'colEnd'   => $col,
                        'isStart'  => true,
                        'isEnd'    => true,
                        'kind'     => 'due',
                        'id'       => (int) ($card['id'] ?? 0),
                    ];
                }
            }
        }

        // Aloca lanes (linhas) evitando sobreposição horizontal — igual à tela.
        usort($segments, function ($a, $b) {
            return ($a['colStart'] <=> $b['colStart'])
                ?: (($b['colEnd'] - $b['colStart']) <=> ($a['colEnd'] - $a['colStart']))
                ?: ($a['id'] <=> $b['id']);
        });
        $laneEnds = []; // última coluna ocupada por lane
        foreach ($segments as &$seg) {
            $placed = false;
            foreach ($laneEnds as $i => $endCol) {
                if ($endCol < $seg['colStart']) {
                    $seg['lane'] = $i;
                    $laneEnds[$i] = $seg['colEnd'];
                    $placed = true;
                    break;
                }
            }
            if (!$placed) {
                $seg['lane'] = count($laneEnds);
                $laneEnds[] = $seg['colEnd'];
            }
        }
        unset($seg);

        return ['lanes' => count($laneEnds), 'segments' => $segments];
    }
}
