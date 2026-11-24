<?php
/**
 * Regras puras (sem banco/HTTP) que traduzem o estado de uma campanha de
 * prospecção (apollo_campaigns + apollo_prospecting_log) em linguagem simples
 * para quem opera a campanha.
 *
 * Motivação: o "Log" da campanha mostrava o JSON cru de cada execução
 * ({"campaign":6,"searched":0,...}) e a coluna "Hoje" só um número. A pessoa
 * que criou a campanha não conseguia saber se ela estava funcionando, parada
 * por horário, sem resultados no Apollo ou sem crédito. Esta classe é a fonte
 * única dessa tradução, usada pela tabela de campanhas e pelo painel de
 * acompanhamento, e é coberta por teste unitário.
 *
 * Tons devolvidos (mapeiam para cores do Bootstrap):
 *   success = funcionando | warning = precisa de atenção | danger = erro
 *   info = aguardando | secondary = parada (inativa / fora do horário)
 */
class ProspectingCampaignRules
{
    private const DAY_LABELS = [1 => 'seg', 2 => 'ter', 3 => 'qua', 4 => 'qui', 5 => 'sex', 6 => 'sáb', 7 => 'dom'];

    /** Rótulos das senioridades do Apollo (mesmos do formulário da campanha). */
    private const SENIORITY_LABELS = [
        'owner' => 'Dono', 'founder' => 'Fundador', 'c_suite' => 'C-Level', 'partner' => 'Sócio',
        'vp' => 'VP', 'head' => 'Head', 'director' => 'Diretor', 'manager' => 'Gerente',
        'senior' => 'Sênior', 'entry' => 'Júnior', 'intern' => 'Estagiário',
    ];

    /** Métricas de uma execução (linha action='done'), sempre com todas as chaves. */
    public static function parseRunDetail($detail): array
    {
        $base = [
            'source' => 'apollo', 'searched' => 0, 'analyzed' => 0, 'duplicated' => 0,
            'out_of_icp' => 0, 'low_score' => 0, 'selected' => 0, 'revealed_email' => 0,
            'reveal_failed' => 0, 'imported' => 0, 'enrolled' => 0, 'pages' => 0, 'error' => null,
        ];
        $m = is_array($detail) ? $detail : json_decode((string) $detail, true);
        if (!is_array($m)) return $base;
        foreach ($base as $k => $v) {
            if (!array_key_exists($k, $m)) continue;
            if ($k === 'source') $base[$k] = $m[$k] === 'my_leads' ? 'my_leads' : 'apollo';
            elseif ($k === 'error') $base[$k] = ($m[$k] === null || $m[$k] === '') ? null : (string) $m[$k];
            else $base[$k] = (int) $m[$k];
        }
        return $base;
    }

    /**
     * Frase simples que resume UMA execução.
     * @return array{tone:string,text:string}
     */
    public static function summarizeRun(array $m): array
    {
        $m = self::parseRunDetail($m);
        if ($m['error']) {
            return ['tone' => 'danger', 'text' => 'Erro ao executar: ' . $m['error']];
        }
        if ($m['enrolled'] > 0) {
            return ['tone' => 'success', 'text' => self::plural($m['enrolled'], 'lead novo captado e colocado', 'leads novos captados e colocados') . ' na sequência.'];
        }
        if ($m['searched'] === 0) {
            return ['tone' => 'warning', 'text' => $m['source'] === 'my_leads'
                ? 'Nenhum lead de "Meus Leads" atende aos filtros desta campanha.'
                : 'O Apollo não encontrou nenhuma pessoa com os filtros desta campanha.'];
        }
        $reasons = self::discardReasons($m);
        $found = self::plural($m['searched'], 'pessoa encontrada', 'pessoas encontradas');
        return ['tone' => 'warning', 'text' => $found . ', mas nenhuma foi captada'
            . ($reasons ? ': ' . implode(', ', $reasons) . '.' : '.')];
    }

    /** Motivos de descarte em linguagem simples (só os que ocorreram). */
    public static function discardReasons(array $m): array
    {
        $m = self::parseRunDetail($m);
        $out = [];
        if ($m['duplicated'] > 0) $out[] = $m['duplicated'] . ' já estavam no sistema';
        if ($m['out_of_icp'] > 0) $out[] = $m['out_of_icp'] . ' fora do perfil (ICP)';
        if ($m['low_score'] > 0) $out[] = $m['low_score'] . ' com score abaixo do mínimo';
        if ($m['reveal_failed'] > 0) $out[] = $m['reveal_failed'] . ' sem e-mail/telefone disponível';
        return $out;
    }

    /** Soma as métricas de várias execuções (ex.: todas as de hoje). */
    public static function sumRuns(array $runs): array
    {
        $tot = ['runs' => 0, 'searched' => 0, 'duplicated' => 0, 'out_of_icp' => 0, 'low_score' => 0,
                'reveal_failed' => 0, 'revealed_email' => 0, 'enrolled' => 0, 'errors' => 0];
        foreach ($runs as $r) {
            $m = self::parseRunDetail($r);
            $tot['runs']++;
            foreach (['searched', 'duplicated', 'out_of_icp', 'low_score', 'reveal_failed', 'revealed_email', 'enrolled'] as $k) {
                $tot[$k] += $m[$k];
            }
            if ($m['error']) $tot['errors']++;
        }
        return $tot;
    }

    /** "seg a sex, 08:00 às 18:00" */
    public static function scheduleLabel($daysCsv, $start, $end): string
    {
        $days = SequenceWindowRules::parseDays($daysCsv);
        sort($days);
        if (empty($days) || $days === [1, 2, 3, 4, 5, 6, 7]) $daysTxt = 'todos os dias';
        elseif ($days === [1, 2, 3, 4, 5]) $daysTxt = 'seg a sex';
        else $daysTxt = implode(', ', array_map(fn($d) => self::DAY_LABELS[$d], $days));
        return $daysTxt . ', ' . self::hhmm($start ?: '08:00:00') . ' às ' . self::hhmm($end ?: '18:00:00');
    }

    /**
     * A campanha pode rodar agora? E quando é a próxima janela?
     * Mesma regra do ApolloProspectingService::isWithinSchedule (dias vazios = todos).
     * @return array{inside:bool,next_ts:int}
     */
    public static function scheduleState($daysCsv, $start, $end, int $nowTs): array
    {
        $start = $start ?: '08:00:00';
        $end = $end ?: '18:00:00';
        $inside = SequenceWindowRules::canSendNow($start, $end, $daysCsv, true, $nowTs);
        $next = $inside ? $nowTs : SequenceWindowRules::nextSendTime($start, $end, $daysCsv, true, $nowTs);
        return ['inside' => $inside, 'next_ts' => $next];
    }

    /** "hoje às 08:00" | "amanhã às 08:00" | "seg (24/11) às 08:00" */
    public static function whenLabel(int $ts, int $nowTs): string
    {
        $diffDays = (int) round((strtotime(date('Y-m-d', $ts)) - strtotime(date('Y-m-d', $nowTs))) / 86400);
        if ($diffDays === 0) $day = 'hoje';
        elseif ($diffDays === 1) $day = 'amanhã';
        else $day = self::DAY_LABELS[(int) date('N', $ts)] . ' (' . date('d/m', $ts) . ')';
        return $day . ' às ' . date('H:i', $ts);
    }

    /** "agora há pouco" | "há 5 min" | "há 2 h" | "em 20/11 às 15:04" */
    public static function agoLabel(?string $datetime, int $nowTs): string
    {
        $ts = $datetime ? strtotime($datetime) : false;
        if (!$ts) return '';
        $diff = $nowTs - $ts;
        if ($diff < 60) return 'agora há pouco';
        if ($diff < 3600) return 'há ' . (int) floor($diff / 60) . ' min';
        if ($diff < 86400 && date('Y-m-d', $ts) === date('Y-m-d', $nowTs)) return 'há ' . (int) floor($diff / 3600) . ' h';
        return 'em ' . date('d/m', $ts) . ' às ' . date('H:i', $ts);
    }

    /**
     * Diagnóstico geral: a campanha está funcionando? O que a pessoa precisa fazer?
     *
     * $ctx:
     *   captured_today (int)      leads inscritos hoje
     *   last_run       (?array)   ['detail' => json|array, 'created_at' => 'Y-m-d H:i:s'] da última execução
     *   credit_limit_today (int)  quantas vezes o limite diário de créditos barrou um reveal hoje
     *   apollo_configured (bool)
     *
     * @return array{tone:string,icon:string,title:string,text:string,action:?string,short:string}
     */
    public static function diagnose(array $camp, array $ctx, int $nowTs): array
    {
        $source = ($camp['lead_source'] ?? 'apollo') === 'my_leads' ? 'my_leads' : 'apollo';
        $target = max(0, (int) ($camp['daily_target'] ?? 0));
        $captured = (int) ($ctx['captured_today'] ?? 0);
        $lastRun = $ctx['last_run'] ?? null;
        $lastRunTs = $lastRun && !empty($lastRun['created_at']) ? strtotime($lastRun['created_at']) : false;
        $lastToday = $lastRunTs && date('Y-m-d', $lastRunTs) === date('Y-m-d', $nowTs);
        $m = $lastRun ? self::parseRunDetail($lastRun['detail'] ?? null) : null;
        $ago = $lastRun ? self::agoLabel($lastRun['created_at'] ?? null, $nowTs) : '';
        $sched = self::scheduleState($camp['days_of_week'] ?? '', $camp['window_start'] ?? null, $camp['window_end'] ?? null, $nowTs);
        $schedTxt = self::scheduleLabel($camp['days_of_week'] ?? '', $camp['window_start'] ?? null, $camp['window_end'] ?? null);
        $hasSequence = !empty($camp['sequence_id'])
            || (!empty($camp['auto_route']) && (!empty($camp['sequence_id_email']) || !empty($camp['sequence_id_whatsapp']) || !empty($camp['sequence_id_mixed'])));

        $r = fn($tone, $icon, $title, $text, $action, $short) =>
            ['tone' => $tone, 'icon' => $icon, 'title' => $title, 'text' => $text, 'action' => $action, 'short' => $short];

        if (empty($camp['is_active'])) {
            return $r('secondary', 'pause-circle', 'Campanha inativa',
                'Ela não capta leads enquanto estiver inativa.',
                'Para começar, clique no botão liga/desliga (⏻) da campanha.', 'Inativa');
        }
        if (!$hasSequence) {
            return $r('danger', 'exclamation-octagon', 'Campanha sem sequência',
                'Os leads captados não teriam para onde ir.',
                'Edite a campanha e escolha a sequência.', 'Sem sequência');
        }
        if ($source === 'apollo' && array_key_exists('apollo_configured', $ctx) && !$ctx['apollo_configured']) {
            return $r('danger', 'plug', 'Apollo não configurado',
                'O sistema não consegue buscar leads sem a chave da API do Apollo.',
                'Peça a um administrador para informar a chave em Configurações.', 'Apollo desconectado');
        }
        if ($target > 0 && $captured >= $target) {
            return $r('success', 'check-circle', 'Meta de hoje atingida',
                "Hoje já entraram {$captured} de {$target} leads na sequência.",
                'Nada a fazer. A campanha volta a captar no próximo dia de captação.', "Meta atingida ({$captured}/{$target})");
        }
        if ($m && $lastToday && $m['error']) {
            return $r('danger', 'x-octagon', 'A última execução deu erro',
                'Erro: ' . $m['error'] . ($ago ? " (última tentativa {$ago})." : '.'),
                'Se o erro continuar, avise o suporte com um print desta tela.', 'Erro na execução');
        }
        if (!$sched['inside']) {
            $next = self::whenLabel($sched['next_ts'], $nowTs);
            $extra = $captured > 0 ? " Hoje entraram {$captured} leads." : '';
            return $r('secondary', 'moon', 'Fora do horário de captação',
                "A campanha capta {$schedTxt}. Próxima captação: {$next}." . $extra,
                'Nada a fazer: ela volta a rodar sozinha no próximo horário.', 'Volta ' . $next);
        }
        if (!$m || !$lastToday) {
            return $r('info', 'hourglass-split', 'Aguardando a primeira execução de hoje',
                'A captação roda sozinha, de tempos em tempos, dentro do horário (' . $schedTxt . ').',
                'Nada a fazer. Se quiser ver o resultado já, clique em ▶ Executar agora (gasta créditos do Apollo).',
                'Aguardando execução');
        }
        if (!empty($ctx['credit_limit_today'])) {
            return $r('warning', 'coin', 'Limite diário de créditos Apollo atingido',
                "Hoje entraram {$captured} de {$target} leads. O sistema parou de revelar e-mails porque o limite diário de créditos do responsável pela campanha acabou.",
                'Aguarde até amanhã ou peça a um administrador para aumentar o limite de créditos do usuário.', 'Sem créditos hoje');
        }
        if ($captured > 0) {
            return $r('success', 'check-circle', 'Funcionando',
                "Hoje entraram {$captured} de {$target} leads na sequência. A campanha continua captando até bater a meta.",
                'Nada a fazer.', "Funcionando ({$captured}/{$target})");
        }
        if ($m['searched'] === 0) {
            return $r('warning', 'search', 'Rodando, mas sem resultados',
                ($source === 'my_leads'
                    ? 'A campanha está rodando, mas nenhum lead de "Meus Leads" atende aos filtros.'
                    : 'A campanha está rodando, mas o Apollo não encontra nenhuma pessoa com os filtros escolhidos.')
                . ($ago ? " Última tentativa {$ago}." : ''),
                $source === 'my_leads'
                    ? 'Edite a campanha e afrouxe os filtros (temperatura, fonte, responsável).'
                    : 'Edite a campanha e afrouxe os filtros. Cargos, local, faixa de funcionários e domínios se somam: quanto mais combinados, menos pessoas o Apollo encontra. Nichos marcados com "(palavra-chave)" no resumo trazem poucos resultados; troque por um nicho da lista.',
                'Sem resultados no Apollo');
        }
        if ($m['enrolled'] === 0) {
            return $r('warning', 'funnel', 'Encontra pessoas, mas nenhuma passa nos critérios',
                'Na última execução (' . $ago . '): ' . self::summarizeRun($m)['text'],
                self::adviceFor($m) ?? 'Revise os critérios da campanha.',
                'Ninguém passa nos critérios');
        }
        return $r('success', 'check-circle', 'Funcionando',
            'Na última execução (' . $ago . '): ' . self::summarizeRun($m)['text'],
            'Nada a fazer.', 'Funcionando');
    }

    /** Conselho para o motivo de descarte que mais pesou. */
    public static function adviceFor(array $m): ?string
    {
        $m = self::parseRunDetail($m);
        $top = ['duplicated' => $m['duplicated'], 'out_of_icp' => $m['out_of_icp'], 'low_score' => $m['low_score'], 'reveal_failed' => $m['reveal_failed']];
        arsort($top);
        $key = array_key_first($top);
        if (!$key || $top[$key] <= 0) return null;
        $advice = [
            'duplicated' => 'A maioria já está no sistema. Amplie os filtros da busca (outros cargos, regiões ou faixas de empresa) para chegar em gente nova.',
            'out_of_icp' => 'A maioria fica fora do perfil (ICP). Revise "ICP: senioridades/cargos", a faixa de funcionários e a opção "Exigir empresa com site".',
            'low_score' => 'A maioria fica abaixo do score mínimo. Diminua o "Score mínimo" da campanha.',
            'reveal_failed' => 'O Apollo não tem e-mail para a maioria. Amplie os filtros ou use uma sequência que aceite WhatsApp.',
        ];
        return $advice[$key];
    }

    /**
     * Linhas legíveis dos filtros/critérios da campanha.
     * @return array<int,array{label:string,value:string}>
     */
    public static function filtersSummary(array $camp): array
    {
        $out = [];
        if (($camp['lead_source'] ?? 'apollo') === 'my_leads') {
            $f = json_decode($camp['my_leads_filters'] ?? '{}', true) ?: [];
            $ids = json_decode($camp['my_leads_ids'] ?? '[]', true) ?: [];
            if (!empty($ids)) $out[] = ['label' => 'Leads escolhidos', 'value' => count($ids) . ' selecionado(s) manualmente'];
            if (!empty($f['temperature'])) $out[] = ['label' => 'Temperatura', 'value' => (string) $f['temperature']];
            if (!empty($f['source'])) $out[] = ['label' => 'Fonte do lead', 'value' => (string) $f['source']];
            if (!empty($f['assigned_to'])) $out[] = ['label' => 'Responsável', 'value' => '#' . (int) $f['assigned_to']];
            if (empty($out)) $out[] = ['label' => 'Filtros', 'value' => 'Todos os leads do CRM elegíveis'];
            return $out;
        }

        $f = json_decode($camp['search_filters'] ?? '{}', true) ?: [];
        $icp = json_decode($camp['icp_rules'] ?? '{}', true) ?: [];
        $list = fn($v) => implode(', ', array_map('strval', is_array($v) ? $v : [$v]));
        $sen = fn($v) => implode(', ', array_map(fn($s) => self::SENIORITY_LABELS[$s] ?? $s, is_array($v) ? $v : [$v]));
        $ranges = fn($v) => implode('; ', array_map(fn($r) => str_replace(',', ' a ', (string) $r), is_array($v) ? $v : [$v]));

        if (!empty($f['person_titles'])) $out[] = ['label' => 'Cargos', 'value' => $list($f['person_titles'])];
        if (!empty($f['person_seniorities'])) $out[] = ['label' => 'Senioridades', 'value' => $sen($f['person_seniorities'])];
        if (!empty($f['person_locations'])) $out[] = ['label' => 'Local da pessoa', 'value' => $list($f['person_locations'])];
        if (!empty($f['organization_locations'])) $out[] = ['label' => 'Local da empresa', 'value' => $list($f['organization_locations'])];
        if (!empty($f['organization_num_employees_ranges'])) $out[] = ['label' => 'Funcionários', 'value' => $ranges($f['organization_num_employees_ranges'])];
        $niches = ApolloNicheRules::nichesFromFilters($f);
        if ($niches) {
            $r = ApolloNicheRules::resolve($niches);
            $txt = implode(', ', array_map(fn($n) => isset($r['mapped'][$n]) ? $n : $n . ' (palavra-chave)', $niches));
            $out[] = ['label' => 'Nicho', 'value' => $txt . ' — qualquer um deles'];
        }
        if (!empty($f['q_organization_domains_list'])) $out[] = ['label' => 'Domínios', 'value' => $list($f['q_organization_domains_list'])];
        if (empty($out)) $out[] = ['label' => 'Busca', 'value' => 'Sem filtros (qualquer pessoa)'];

        $icpTxt = [];
        if (!empty($icp['seniorities'])) $icpTxt[] = 'senioridades: ' . $sen($icp['seniorities']);
        if (!empty($icp['titles_any'])) $icpTxt[] = 'cargo contém: ' . $list($icp['titles_any']);
        if (!empty($icp['employee_min']) || !empty($icp['employee_max'])) {
            $icpTxt[] = 'funcionários: ' . (int) ($icp['employee_min'] ?? 0) . ' a ' . (!empty($icp['employee_max']) ? (int) $icp['employee_max'] : 'sem limite');
        }
        if (!empty($icp['require_website'])) $icpTxt[] = 'exige empresa com site';
        if ($icpTxt) $out[] = ['label' => 'Perfil (ICP)', 'value' => implode(' · ', $icpTxt)];
        $out[] = ['label' => 'Score mínimo', 'value' => (string) (int) ($camp['min_score'] ?? 0)];
        return $out;
    }

    /**
     * Converte as linhas do log (mais recentes primeiro) numa linha do tempo
     * legível. Junta execuções seguidas com o mesmo resultado em uma só linha
     * ("12 execuções entre 15:48 e 15:59"), para o histórico não virar uma
     * parede de registros iguais.
     *
     * @param array $rows linhas com action, detail, credits, created_at (+ contact_name opcional)
     * @return array<int,array{tone:string,icon:string,text:string,from:string,to:string,count:int}>
     */
    public static function timeline(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $item = self::describeLogRow($row);
            if ($item === null) continue;
            $last = $out ? $out[count($out) - 1] : null;
            if ($last && $last['key'] === $item['key']) {
                // Linhas vêm da mais nova para a mais antiga: estende o início.
                $out[count($out) - 1]['from'] = $item['from'];
                $out[count($out) - 1]['count']++;
                continue;
            }
            $out[] = $item;
        }
        return array_map(function ($i) { unset($i['key']); return $i; }, $out);
    }

    /** Uma linha do log em linguagem simples; null = linha técnica sem valor para o usuário. */
    public static function describeLogRow(array $row): ?array
    {
        $action = (string) ($row['action'] ?? '');
        $detail = (string) ($row['detail'] ?? '');
        $at = (string) ($row['created_at'] ?? '');
        $who = trim((string) ($row['contact_name'] ?? ''));
        $mk = fn($tone, $icon, $text, $key = null) =>
            ['tone' => $tone, 'icon' => $icon, 'text' => $text, 'from' => $at, 'to' => $at, 'count' => 1, 'key' => $key ?? ($action . '|' . $text)];

        switch ($action) {
            case 'done':
                $s = self::summarizeRun(self::parseRunDetail($detail));
                $icon = ['success' => 'check-circle', 'warning' => 'exclamation-triangle', 'danger' => 'x-octagon'][$s['tone']] ?? 'arrow-repeat';
                // Execuções com leads captados nunca são agrupadas (cada uma é relevante).
                $key = $s['tone'] === 'success' ? 'done|' . $at . '|' . $s['text'] : 'done|' . $s['text'];
                return $mk($s['tone'], $icon, 'Execução: ' . $s['text'], $key);
            case 'enrolled':
                return $mk('success', 'person-check', 'Lead captado' . ($who ? ': ' . $who : '') . ' (entrou na sequência)', 'enrolled|' . $at . '|' . $who);
            case 'search_failed':
                return $mk('danger', 'x-octagon', 'A busca no Apollo falhou: ' . preg_replace('/\s*\(página \d+\)$/u', '', $detail));
            case 'credit_limit':
                return $mk('warning', 'coin', 'Limite diário de créditos Apollo atingido: e-mail não revelado.');
            case 'reveal_error':
                return $mk('danger', 'x-octagon', 'Erro ao revelar e-mail no Apollo: ' . $detail);
            case 'skipped':
                if (stripos($detail, 'sem interesse') !== false) return $mk('secondary', 'slash-circle', 'Lead ignorado: já disse que não tem interesse.');
                if (stripos($detail, 'já passou') !== false) return $mk('secondary', 'slash-circle', 'Lead ignorado: já passou por esta sequência antes.');
                return $mk('secondary', 'slash-circle', 'Lead ignorado' . ($detail !== '' ? ': ' . $detail : '.'));
            // Etapas internas já resumidas na linha "Execução" (ou no "Lead captado").
            case 'searched':
            case 'imported':
            case 'reveal_email':
            case 'reveal_phone':
                return null;
            default:
                return $mk('secondary', 'dot', ($action !== '' ? $action : 'registro') . ($detail !== '' ? ': ' . $detail : ''));
        }
    }

    private static function hhmm($t): string
    {
        return substr((string) $t, 0, 5);
    }

    private static function plural(int $n, string $one, string $many): string
    {
        return $n . ' ' . ($n === 1 ? $one : $many);
    }
}
