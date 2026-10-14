<?php

/**
 * Regras puras (sem banco/HTTP) da PROPOSTA/ORÇAMENTO comercial (Fase 3).
 *
 * Centraliza, de forma testável:
 *  - cálculo do valor de um item (horas x valor/hora, ou valor fixo);
 *  - soma do total da proposta;
 *  - normalização de status e tipo de contratação;
 *  - transições de status válidas (máquina de estados do processo comercial);
 *  - validação da recusa (motivo obrigatório).
 *
 * Fonte única de verdade: o controller e as views consomem estas funções para
 * não divergir no cálculo nem no fluxo de estados.
 */
class ProposalRules
{
    /** Estados da proposta (espelha o ENUM proposals.status). */
    public const STATUS_DRAFT = 'draft';         // em elaboração
    public const STATUS_READY = 'ready';         // montada
    public const STATUS_SENT = 'sent';           // enviada ao cliente
    public const STATUS_AWAITING = 'awaiting';   // aguardando retorno
    public const STATUS_ACCEPTED = 'accepted';   // aceita
    public const STATUS_REJECTED = 'rejected';   // recusada
    public const STATUS_CANCELLED = 'cancelled'; // cancelada

    public const STATUSES = [
        self::STATUS_DRAFT, self::STATUS_READY, self::STATUS_SENT,
        self::STATUS_AWAITING, self::STATUS_ACCEPTED, self::STATUS_REJECTED,
        self::STATUS_CANCELLED,
    ];

    /** Tipos de contratação (espelha o ENUM proposals.contract_type). */
    public const CONTRACT_TYPES = [
        'dev_zero',        // desenvolvimento do zero (tem começo/meio/fim; garantia 90 dias)
        'dev_manutencao',  // desenvolvimento/manutenção de sistema existente
        'suporte',         // só suporte
        'dev_suporte',     // desenvolvimento + suporte
        'outro',
    ];

    /** Estados que já encerraram a proposta (terminais). */
    public const TERMINAL = [self::STATUS_ACCEPTED, self::STATUS_REJECTED, self::STATUS_CANCELLED];

    /**
     * Transições de status permitidas. A recusa pode voltar a elaboração (refazer),
     * e qualquer estado não-terminal pode ser cancelado.
     *
     * @var array<string,string[]>
     */
    private const TRANSITIONS = [
        self::STATUS_DRAFT     => [self::STATUS_READY, self::STATUS_CANCELLED],
        self::STATUS_READY     => [self::STATUS_SENT, self::STATUS_DRAFT, self::STATUS_CANCELLED],
        self::STATUS_SENT      => [self::STATUS_AWAITING, self::STATUS_ACCEPTED, self::STATUS_REJECTED, self::STATUS_CANCELLED],
        self::STATUS_AWAITING  => [self::STATUS_ACCEPTED, self::STATUS_REJECTED, self::STATUS_CANCELLED],
        // Recusada pode ser retomada para refazer (volta à elaboração).
        self::STATUS_REJECTED  => [self::STATUS_DRAFT, self::STATUS_CANCELLED],
        self::STATUS_ACCEPTED  => [],
        self::STATUS_CANCELLED => [],
    ];

    public static function normalizeStatus($value): string
    {
        return in_array($value, self::STATUSES, true) ? $value : self::STATUS_DRAFT;
    }

    public static function isValidStatus($value): bool
    {
        return in_array($value, self::STATUSES, true);
    }

    public static function normalizeContractType($value): ?string
    {
        return in_array($value, self::CONTRACT_TYPES, true) ? $value : null;
    }

    public static function isTerminal($status): bool
    {
        return in_array($status, self::TERMINAL, true);
    }

    /**
     * A transição de $from para $to é permitida? Transição para o mesmo estado é
     * sempre permitida (idempotente). Estados inválidos retornam false.
     */
    public static function canTransition($from, $to): bool
    {
        if (!self::isValidStatus($from) || !self::isValidStatus($to)) return false;
        if ($from === $to) return true;
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /**
     * Valor de um item. Com horas e valor/hora: quantidade × horas × valor/hora.
     * Sem horas: quantidade × valor fixo. Quantidade vazia vale 1. Nunca negativo.
     *
     * @param float|string|null $hours
     * @param float|string|null $hourlyRate
     * @param float|string|null $fixedAmount Valor fixo quando não há horas.
     * @param float|string|null $quantity
     */
    public static function itemAmount($hours, $hourlyRate, $fixedAmount = null, $quantity = null): float
    {
        $q = self::quantity($quantity);
        $h = self::num($hours);
        $r = self::num($hourlyRate);
        if ($h > 0 && $r > 0) {
            return round($q * $h * $r, 2);
        }
        $fixed = self::num($fixedAmount);
        return $fixed > 0 ? round($q * $fixed, 2) : 0.0;
    }

    /** Quantidade do item. Vazio = 1. Negativo vira 0. */
    public static function quantity($v): float
    {
        if ($v === null || $v === '') return 1.0;
        $n = self::num($v);
        if ($n < 0) return 0.0;
        return round($n, 2);
    }

    /**
     * Previsão de valor do catálogo: horas × valor/hora, ou só o valor/hora
     * quando o serviço não tem horas estimadas.
     */
    public static function forecast($hours, $hourlyRate): ?float
    {
        $h = self::num($hours);
        $r = self::num($hourlyRate);
        if ($h > 0 && $r > 0) return round($h * $r, 2);
        if ($r > 0) return round($r, 2);
        return null;
    }

    /**
     * Soma o total a partir de uma lista de itens já com 'amount' calculado
     * (ou calculável por hours/hourly_rate). Aceita arrays no formato do form.
     *
     * @param array<int,array> $items
     */
    public static function total(array $items): float
    {
        $sum = 0.0;
        foreach ($items as $it) {
            if (isset($it['amount']) && $it['amount'] !== '' && $it['amount'] !== null) {
                $sum += self::num($it['amount']);
            } else {
                $sum += self::itemAmount(
                    $it['hours'] ?? null,
                    $it['hourly_rate'] ?? null,
                    $it['fixed_amount'] ?? null,
                    $it['quantity'] ?? null
                );
            }
        }
        return round($sum, 2);
    }

    /**
     * Validação da recusa: motivo é obrigatório (não vazio após trim).
     * Retorna o motivo saneado ou null se inválido.
     */
    public static function sanitizeRejectReason($reason): ?string
    {
        $r = trim((string)$reason);
        return $r === '' ? null : $r;
    }

    /** Data Y-m-d ou null. Aceita também d/m/Y. */
    public static function normalizeDate($value): ?string
    {
        $v = trim((string)$value);
        if ($v === '') return null;
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m)) {
            return checkdate((int)$m[2], (int)$m[3], (int)$m[1]) ? $v : null;
        }
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $v, $m)) {
            return checkdate((int)$m[2], (int)$m[1], (int)$m[3]) ? sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]) : null;
        }
        return null;
    }

    /** Fim pode ser igual ao início. Datas ausentes não invalidam. */
    public static function scheduleOrderOk($start, $end): bool
    {
        $s = self::normalizeDate($start);
        $e = self::normalizeDate($end);
        if ($s === null || $e === null) return true;
        return $e >= $s;
    }

    /**
     * Cronograma por serviço. Cada linha ganha a posição da barra (0–100)
     * dentro da janela que vai do início ao fim do desenvolvimento.
     *
     * @param array<int,array> $items
     * @return array{start:?string,end:?string,days:int,rows:array<int,array>}
     */
    public static function timeline(array $items, $devStart = null, $devEnd = null): array
    {
        $min = self::normalizeDate($devStart);
        $max = self::normalizeDate($devEnd);
        $rows = [];
        foreach ($items as $it) {
            $s = self::normalizeDate($it['schedule_start'] ?? null);
            $e = self::normalizeDate($it['schedule_end'] ?? null);
            foreach ([$s, $e] as $d) {
                if (!$d) continue;
                if ($min === null || $d < $min) $min = $d;
                if ($max === null || $d > $max) $max = $d;
            }
            $billing = self::normalizeBilling($it['billing'] ?? null);
            $rows[] = [
                'description' => (string)($it['description'] ?? ''),
                'start' => $s,
                'end' => $e,
                'billing' => $billing,
                // Mensal sem fim segue até o fim da janela do cronograma.
                'ongoing' => $billing === self::BILLING_MONTHLY && $e === null,
            ];
        }
        $days = 0;
        if ($min && $max) {
            $days = (int)round((strtotime($max . ' 12:00:00') - strtotime($min . ' 12:00:00')) / 86400);
        }
        foreach ($rows as &$row) {
            $row['bar'] = self::timelineBar($row['start'], $row['ongoing'] ? $max : $row['end'], $min, $max);
        }
        unset($row);
        return ['start' => $min, 'end' => $max, 'days' => $days, 'rows' => $rows];
    }

    /** Cobrança do item: pontual (uma vez) ou mensal (recorrente). */
    public const BILLING_ONCE = 'once';
    public const BILLING_MONTHLY = 'monthly';

    public static function normalizeBilling($value): string
    {
        return $value === self::BILLING_MONTHLY ? self::BILLING_MONTHLY : self::BILLING_ONCE;
    }

    /**
     * Totais da proposta separados por cobrança.
     *
     * @param array<int,array> $items
     * @return array{once:float,monthly:float,year:float}
     */
    public static function totals(array $items): array
    {
        $once = 0.0;
        $monthly = 0.0;
        foreach ($items as $it) {
            $amount = self::num($it['amount'] ?? 0);
            if (self::normalizeBilling($it['billing'] ?? null) === self::BILLING_MONTHLY) $monthly += $amount;
            else $once += $amount;
        }
        return [
            'once' => round($once, 2),
            'monthly' => round($monthly, 2),
            'year' => round($once + $monthly * 12, 2),
        ];
    }

    /**
     * Projeção de 12 meses a partir do início do desenvolvimento.
     * Item pontual entra no mês em que começa no cronograma; item mensal entra
     * todo mês a partir do seu início. O retorno estimado começa no mês
     * seguinte ao fim do desenvolvimento.
     *
     * @param array<int,array> $items
     * @return array{months:array<int,array>,invest:float,return:float,payback:?int,roi:?float}
     */
    public static function projection(array $items, $devStart = null, $devEnd = null, $monthlyReturn = null): array
    {
        $start = self::normalizeDate($devStart) ?: date('Y-m-01');
        $base = (int)date('Y', strtotime($start)) * 12 + (int)date('n', strtotime($start)) - 1;
        $offset = function (?string $d) use ($base): int {
            if (!$d) return 0;
            $t = strtotime($d);
            $o = (int)date('Y', $t) * 12 + (int)date('n', $t) - 1 - $base;
            return max(0, min(11, $o));
        };

        $invest = array_fill(0, 12, 0.0);
        $lastEnd = self::normalizeDate($devEnd);
        foreach ($items as $it) {
            $amount = self::num($it['amount'] ?? 0);
            $from = $offset(self::normalizeDate($it['schedule_start'] ?? null));
            if (self::normalizeBilling($it['billing'] ?? null) === self::BILLING_MONTHLY) {
                for ($m = $from; $m < 12; $m++) $invest[$m] += $amount;
            } else {
                $invest[$from] += $amount;
            }
            $end = self::normalizeDate($it['schedule_end'] ?? null);
            if ($end && ($lastEnd === null || $end > $lastEnd)) $lastEnd = $end;
        }

        $ret = self::num($monthlyReturn);
        $returnFrom = $lastEnd ? min(12, $offset($lastEnd) + 1) : 1;
        $months = [];
        $cumInvest = 0.0;
        $cumReturn = 0.0;
        $payback = null;
        $names = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
        for ($m = 0; $m < 12; $m++) {
            $abs = $base + $m;
            $r = ($ret > 0 && $m >= $returnFrom) ? $ret : 0.0;
            $cumInvest += $invest[$m];
            $cumReturn += $r;
            if ($payback === null && $ret > 0 && $cumInvest > 0 && $cumReturn >= $cumInvest) $payback = $m + 1;
            $months[] = [
                'label' => $names[$abs % 12] . '/' . substr((string)intdiv($abs, 12), 2),
                'invest' => round($invest[$m], 2),
                'return' => round($r, 2),
                'cum_invest' => round($cumInvest, 2),
                'cum_return' => round($cumReturn, 2),
            ];
        }
        $roi = ($ret > 0 && $cumInvest > 0) ? round(($cumReturn - $cumInvest) / $cumInvest * 100, 1) : null;
        return [
            'months' => $months,
            'invest' => round($cumInvest, 2),
            'return' => round($cumReturn, 2),
            'payback' => $payback,
            'roi' => $roi,
        ];
    }

    /** Campos variáveis da proposta (mudam por cliente), com limite de tamanho. */
    public const CONTENT_FIELDS = [
        'business_profile'      => 4000, // Perfil da empresa
        'business_channels'     => 4000, // Presença dos canais atuais
        'business_opportunity'  => 4000, // Oportunidade
        'business_diagnosis'    => 4000, // Validar diagnóstico
        'expected_return'       => 20,   // Retorno mensal estimado (R$)
        'return_text'           => 4000, // Previsibilidade do retorno
        'renewal_text'          => 4000, // Renovação de serviços pontuais
        'summary_text'          => 4000, // Resumo final
        'payment_method'        => 500,  // Condição de pagamento desta proposta
    ];

    /** Texto inicial dos campos variáveis de uma proposta nova. */
    public static function defaultContent(): array
    {
        return [
            'renewal_text' => "Os serviços pontuais desta proposta encerram com a entrega. Ao fim do período, podem ser renovados com novo escopo e valor atualizado, aproveitando o que já foi construído.",
            'payment_method' => '50% na assinatura e 50% na entrega',
        ];
    }

    /** Saneia o conteúdo variável vindo do formulário. */
    public static function normalizeContent($data): array
    {
        $out = [];
        if (!is_array($data)) return $out;
        foreach (self::CONTENT_FIELDS as $key => $max) {
            if (!array_key_exists($key, $data)) continue;
            $v = trim((string)$data[$key]);
            if ($key === 'expected_return') {
                $n = self::num($v);
                $out[$key] = $n > 0 ? (string)round($n, 2) : '';
                continue;
            }
            $out[$key] = mb_strlen($v) > $max ? mb_substr($v, 0, $max) : $v;
        }
        return $out;
    }

    public static function decodeContent($json): array
    {
        if (!is_string($json) || trim($json) === '') return [];
        $data = json_decode($json, true);
        return is_array($data) ? self::normalizeContent($data) : [];
    }

    /**
     * Substitui {variavel} no texto fixo pelos valores da proposta.
     *
     * @param array<string,string> $vars
     */
    public static function applyVars(string $text, array $vars): string
    {
        return preg_replace_callback('/\{([a-z0-9_]+)\}/', function ($m) use ($vars) {
            return array_key_exists($m[1], $vars) ? (string)$vars[$m[1]] : $m[0];
        }, $text);
    }

    /** Variáveis aceitas no texto fixo (exibidas no editor de conteúdo fixo). */
    public const VARS = [
        'cliente' => 'Nome do cliente',
        'total' => 'Total do primeiro ano',
        'total_pontual' => 'Soma dos serviços pontuais',
        'total_mensal' => 'Soma dos serviços mensais',
        'total_12_meses' => 'Pontual + 12 mensalidades',
        'condicao_pagamento' => 'Condição de pagamento da proposta',
        'validade' => 'Validade da proposta',
        'inicio' => 'Início do desenvolvimento',
        'fim' => 'Fim do desenvolvimento',
    ];

    /**
     * Linhas "Título: descrição" de um bloco em lista. Linhas sem ":" viram só título.
     *
     * @return array<int,array{title:string,text:string}>
     */
    public static function blockLines(string $body): array
    {
        $out = [];
        foreach (preg_split('/\r?\n/', $body) as $line) {
            $line = trim($line);
            if ($line === '') continue;
            $line = preg_replace('/^[-•*]\s*/u', '', $line);
            $pos = mb_strpos($line, ':');
            if ($pos !== false && $pos > 0 && $pos < 80) {
                $text = trim(mb_substr($line, $pos + 1));
                if ($text !== '') $text = mb_strtoupper(mb_substr($text, 0, 1)) . mb_substr($text, 1);
                $out[] = ['title' => trim(mb_substr($line, 0, $pos)), 'text' => $text];
            } else {
                $out[] = ['title' => $line, 'text' => ''];
            }
        }
        return $out;
    }

    /**
     * Blocos fixos da proposta, na ordem em que aparecem no PDF. O comercial
     * edita o texto em Propostas → Conteúdo fixo; toda proposta traz todos.
     * kind: text (parágrafos), list/steps/clients (uma linha por item).
     *
     * @return array<int,array{block_key:string,title:string,kind:string,body:string,position:int}>
     */
    public static function defaultBlocks(): array
    {
        return [
            [
                'block_key' => 'about',
                'title' => 'Quem somos',
                'kind' => 'text',
                'position' => 1,
                'body' => "A ON Solutions Brasil atua como parceira de consultoria estratégica comercial e inteligência de mercado e desenvolve soluções tecnológicas sob medida.\n\nComeçamos pelo entendimento do negócio, dos processos e dos objetivos de cada cliente. Não vendemos código: vendemos melhoria operacional e crescimento, por meio de estratégia e tecnologia.\n\n01 Consultoria estratégica comercial: Diagnóstico, posicionamento, CRM, funil de vendas e acompanhamento para organizar e acelerar a operação comercial.\n02 Inteligência de mercado: Leitura de dados, indicadores e mercado para decidir com clareza onde investir e como crescer.\n03 Tecnologia sob medida: Sistemas, ERP, CRM, integrações, automações e IA desenhados a partir do processo real de cada operação.",
            ],
            [
                'block_key' => 'what_we_do',
                'title' => 'O que fazemos',
                'kind' => 'list',
                'position' => 2,
                'body' => "# Estratégia comercial e inteligência de mercado\n# Guiados por dados e tecnologia.\nDiagnóstico e plano estratégico: Leitura do negócio, do mercado e do funil.\nPosicionamento e rebranding: Marca, identidade visual e tom de voz.\nCRM e funil de vendas: Processo comercial organizado e mensurável.\nPresença digital: Site, tráfego pago e social media.\nAcompanhamento estratégico: Reuniões, métricas e ajustes de rota.\n---\n# Tecnologia sob medida\n# Soluções construídas a partir do processo real de cada operação.\nSistemas web e personalizados: Plataformas desenhadas para a operação.\nERP personalizado: Financeiro, estoque, contratos e projetos em um só ambiente.\nCRM: Leads, funil, propostas e follow-ups organizados.\nIntegrações e APIs: Sistemas conectados, sem redigitação.\nAutomações: Tarefas repetitivas acontecendo sozinhas.\nInteligência artificial: IA aplicada a atendimento, dados e decisões.\nDashboards e BI: Indicadores claros para decidir mais rápido.\nSaaS e plataformas: Da ideia ao produto digital.\nAplicativos: Soluções mobile integradas à operação.\nConsultoria tecnológica: Diagnóstico de processos, sistemas e gargalos.",
            ],
            [
                'block_key' => 'clients',
                'title' => 'Clientes ON Solutions',
                'kind' => 'clients',
                'position' => 3,
                'body' => "Braziliana\nQuatri Automação\nBrooks Construtora\nCrossHR\nMultiLife Care\nMafedo Engenharia\nInpasa",
            ],
            [
                'block_key' => 'how_we_work',
                'title' => 'Como fazemos',
                'kind' => 'steps',
                'position' => 4,
                'body' => "# Estratégia comercial e inteligência de mercado\nDiagnóstico: Auditoria da jornada, da oferta e do funil, com plano estratégico entre 15 e 30 dias após o onboarding.\nImplantação: Posicionamento, identidade visual, CRM e site.\nExecução: Campanhas de tráfego pago e conteúdo nas redes sociais.\nAcompanhamento: Reuniões, relatórios e ajustes de rota.\n---\n# Tecnologia sob medida\nDiscovery: Entendimento do negócio, dos processos e dos objetivos.\nPlanejamento: Arquitetura da solução e definição do roadmap.\nDesenvolvimento: Construção em sprints, com entregas contínuas e qualidade.\nEntrega e suporte: Deploy, treinamento e suporte contínuo.",
            ],
            [
                'block_key' => 'payment',
                'title' => 'Condições de pagamento e rescisão',
                'kind' => 'text',
                'position' => 5,
                'body' => "A Vigência, reajuste e renovação\nOs valores dos serviços contratados serão reajustados a cada doze meses de vigência, com base no IGP-M.\nEsta proposta será renovada automaticamente por períodos iguais ao contratado, caso não seja contestada com antecedência mínima de sessenta dias do término.\nNa renovação, não há novo pagamento de implantação.\nEsta proposta tem validade jurídica a partir da assinatura pelas partes e segue os termos do contrato de prestação de serviços.\n\nB Rescisão antecipada\nO cancelamento deve ser comunicado por escrito com trinta dias de antecedência da data de suspensão dos serviços.\nServiços de implantação já iniciados são cobrados proporcionalmente ao que foi entregue até a data do aviso.\nOs valores de implantação já pagos não são reembolsáveis, pois correspondem a serviços entregues ou em execução.\n\nC Outras disposições\nAs informações desta proposta são confidenciais e não poderão ser divulgadas sem autorização prévia da ON Solutions Brasil.\nÉ vedado utilizar a hospedagem e as ferramentas do projeto para fins ilícitos ou que violem as políticas das plataformas utilizadas.",
            ],
            [
                'block_key' => 'notes',
                'title' => 'Notas e condições',
                'kind' => 'list',
                'position' => 6,
                'body' => "A Responsabilidades do cliente\nFornecer os materiais solicitados dentro dos cronogramas combinados.\nDisponibilizar os acessos necessários, de preferência em até 48 horas.\nAprovar ou solicitar ajustes nos materiais em até 3 dias úteis.\nParticipar das reuniões combinadas.\nFornecer as informações necessárias para a execução do escopo.\n\nB Condições gerais\nProposta válida até {validade}.\nValores referem-se somente ao escopo descrito. Mudanças de escopo são orçadas à parte.\nO cronograma começa após a assinatura do contrato e o envio das informações necessárias pelo cliente.\nAtrasos no envio de materiais, aprovações ou acessos podem impactar o cronograma.\nCustos de terceiros (domínio, licenças, anúncios e APIs pagas) não estão inclusos, salvo quando listados.\nOs valores desta proposta estão em reais.",
            ],
        ];
    }

    /** kind de um bloco fixo pela chave. */
    public static function blockKind(string $key): string
    {
        foreach (self::defaultBlocks() as $b) {
            if ($b['block_key'] === $key) return $b['kind'];
        }
        return 'text';
    }

    /**
     * Lê o snapshot JSON gravado na proposta, indexado por block_key.
     *
     * @return array<string,array{block_key:string,title:string,body:string}>
     */
    public static function decodeBlocks($json): array
    {
        if (!is_string($json) || trim($json) === '') return [];
        $data = json_decode($json, true);
        if (!is_array($data)) return [];
        $out = [];
        foreach ($data as $b) {
            if (!is_array($b)) continue;
            $key = (string)($b['block_key'] ?? '');
            if ($key === '') continue;
            $out[$key] = [
                'block_key' => $key,
                'title' => trim((string)($b['title'] ?? '')),
                'body' => trim((string)($b['body'] ?? '')),
            ];
        }
        return $out;
    }

    /**
     * @return array{left:float,width:float}|null
     */
    private static function timelineBar(?string $start, ?string $end, ?string $min, ?string $max): ?array
    {
        if (!$min || !$max) return null;
        $a = $start ?: $end;
        $b = $end ?: $start;
        if (!$a || !$b) return null;
        if ($b < $a) {
            $tmp = $a;
            $a = $b;
            $b = $tmp;
        }
        $t0 = strtotime($min . ' 12:00:00');
        $t1 = strtotime($max . ' 12:00:00');
        $span = $t1 - $t0;
        if ($span <= 0) return ['left' => 0.0, 'width' => 100.0];
        $left = (strtotime($a . ' 12:00:00') - $t0) / $span * 100;
        $width = (strtotime($b . ' 12:00:00') - strtotime($a . ' 12:00:00')) / $span * 100;
        if ($width < 2.5) $width = 2.5;
        if ($left < 0) $left = 0;
        if ($left + $width > 100) $left = max(0, 100 - $width);
        return ['left' => round($left, 2), 'width' => round($width, 2)];
    }

    /**
     * Converte um valor em float aceitando número ou string "R$ 1.234,56".
     * Reaproveita o parser monetário brasileiro do CRM quando a entrada é string.
     */
    private static function num($v): float
    {
        if (is_int($v) || is_float($v)) return (float)$v;
        if ($v === null || $v === '') return 0.0;
        if (is_string($v)) {
            // Número "simples" (ponto decimal) usa casting direto; formato BR usa o parser.
            if (preg_match('/^-?\d+(\.\d+)?$/', trim($v))) return (float)$v;
            if (class_exists('CrmRules')) {
                $parsed = CrmRules::parseMoneyBR($v);
                if ($parsed !== null) return (float)$parsed;
            }
            return (float)preg_replace('/[^0-9.\-]/', '', $v);
        }
        return 0.0;
    }
}
