<?php

/**
 * Regras puras (sem banco/HTTP) da APRESENTAÇÃO comercial (etapa da esteira
 * anterior à Proposta).
 *
 * A apresentação é uma lista ordenada de SEÇÕES editáveis. Cada seção tem um
 * tipo (layout), título, subtítulo, texto e itens (pares título/texto cujo
 * significado depende do tipo: valor/rótulo em "stats", mercado/ON em
 * "compare", etc.). O conteúdo é guardado como JSON em presentations.content.
 *
 * Centraliza, de forma testável:
 *  - o modelo padrão (apresentação curta ao cliente: quem somos, o que fazemos, clientes, como fazemos);
 *  - a sanitização do conteúdo vindo do editor (tipos válidos, limites);
 *  - status e substituição de variáveis ({{cliente}}) na página pública.
 */
class PresentationRules
{
    public const STATUS_DRAFT = 'draft';         // em edição (link público desativado)
    public const STATUS_PUBLISHED = 'published'; // link público ativo

    public const STATUSES = [self::STATUS_DRAFT, self::STATUS_PUBLISHED];

    /** Tipos de seção (layout) => rótulo exibido no editor. */
    public const SECTION_TYPES = [
        'hero'    => 'Capa',
        'text'    => 'Texto',
        'stats'   => 'Indicadores',
        'steps'   => 'Etapas numeradas',
        'cards'   => 'Cards',
        'table'   => 'Tabela',
        'compare' => 'Comparativo',
        'list'    => 'Lista (✓)',
        'dont'    => 'Lista do que evitar (✗)',
        'cta'     => 'Chamada final / contato',
    ];

    public const MAX_SECTIONS = 120;
    public const MAX_ITEMS = 30;

    private const LIMITS = ['title' => 200, 'subtitle' => 300, 'body' => 5000, 'note' => 3000, 'item_title' => 300, 'item_text' => 1500];

    public static function normalizeStatus($value): string
    {
        return in_array($value, self::STATUSES, true) ? $value : self::STATUS_DRAFT;
    }

    public static function isPublished(?array $presentation): bool
    {
        return $presentation !== null && ($presentation['status'] ?? null) === self::STATUS_PUBLISHED;
    }

    public static function isValidType($type): bool
    {
        return is_string($type) && array_key_exists($type, self::SECTION_TYPES);
    }

    /**
     * Limpa o conteúdo recebido do editor. Descarta seções de tipo inválido,
     * corta textos longos e limita quantidade de seções/itens. Nunca lança.
     *
     * @return array<int,array{type:string,title:string,subtitle:string,body:string,note:string,visible:bool,items:array}>
     */
    public static function sanitizeSections($sections): array
    {
        if (!is_array($sections)) return [];
        $out = [];
        foreach ($sections as $s) {
            if (!is_array($s) || !self::isValidType($s['type'] ?? null)) continue;
            $items = [];
            foreach ((is_array($s['items'] ?? null) ? $s['items'] : []) as $it) {
                if (!is_array($it)) continue;
                $t = self::cut($it['title'] ?? '', self::LIMITS['item_title']);
                $x = self::cut($it['text'] ?? '', self::LIMITS['item_text']);
                if ($t === '' && $x === '') continue;
                $items[] = ['title' => $t, 'text' => $x];
                if (count($items) >= self::MAX_ITEMS) break;
            }
            $out[] = [
                'type'     => $s['type'],
                'title'    => self::cut($s['title'] ?? '', self::LIMITS['title']),
                'subtitle' => self::cut($s['subtitle'] ?? '', self::LIMITS['subtitle']),
                'body'     => self::cut($s['body'] ?? '', self::LIMITS['body']),
                'note'     => self::cut($s['note'] ?? '', self::LIMITS['note']),
                'visible'  => !isset($s['visible']) || filter_var($s['visible'], FILTER_VALIDATE_BOOLEAN),
                'items'    => $items,
            ];
            if (count($out) >= self::MAX_SECTIONS) break;
        }
        return $out;
    }

    /**
     * Versão do formato salvo. Conteúdo sem versão ou com versão menor
     * (playbook interno do Gamma) é substituído pelo modelo curto ao cliente.
     */
    public const CONTENT_VERSION = 3;

    /** Decodifica o JSON salvo; conteúdo inválido, vazio ou antigo cai no modelo padrão. */
    public static function decode(?string $json): array
    {
        $data = json_decode((string)$json, true);
        if (!is_array($data) || (int)($data['version'] ?? 0) < self::CONTENT_VERSION) {
            return self::defaultSections();
        }
        return self::sanitizeSections($data['sections'] ?? null) ?: self::defaultSections();
    }

    public static function encode(array $sections): string
    {
        return json_encode(['version' => self::CONTENT_VERSION, 'sections' => self::sanitizeSections($sections)], JSON_UNESCAPED_UNICODE);
    }

    /** Só as seções marcadas como visíveis (o que o cliente vê). */
    public static function visibleSections(array $sections): array
    {
        return array_values(array_filter($sections, fn($s) => !empty($s['visible'])));
    }

    /** Substitui variáveis simples ({{cliente}}, {{empresa}}) num texto. */
    public static function applyVars(string $text, array $vars): string
    {
        $map = [];
        foreach ($vars as $k => $v) $map['{{' . $k . '}}'] = (string)$v;
        return strtr($text, $map);
    }

    /**
     * Transforma endereços já escapados em HTML (lrvweb.com.br, com ou sem
     * https) em links que abrem o site. Não mexe em e-mail.
     */
    public static function linkify(string $escaped): string
    {
        $linked = preg_replace_callback(
            '/(?<![\w@\/.])((?:https?:\/\/)?(?:www\.)?(?:[a-z0-9-]+\.)+[a-z]{2,}(?:\/[a-z0-9._~:\/?#\[\]@!$&\'()*+,;=%-]*)?)/i',
            static function (array $m): string {
                $label = $m[1];
                $trail = '';
                if (preg_match('/^(.+?)([.,;:!?)\\]]+)$/u', $label, $end) && str_contains($end[1], '.')) {
                    $label = $end[1];
                    $trail = $end[2];
                }
                $href = preg_match('#^https?://#i', $label) ? $label : 'https://' . $label;
                return '<a href="' . htmlspecialchars($href, ENT_QUOTES) . '" target="_blank" rel="noopener">'
                    . $label . '</a>' . $trail;
            },
            $escaped
        );
        return is_string($linked) ? $linked : $escaped;
    }

    /** Mensagem de WhatsApp enviada ao CLIENTE com o link da apresentação. */
    public static function clientWhatsapp(string $name, string $link, ?string $company): string
    {
        $saud = $name !== '' ? "Olá, {$name}!" : 'Olá!';
        return "{$saud}\n\n"
            . ($company ? "*{$company}*\n" : '')
            . "Preparamos uma apresentação para você conhecer melhor nosso trabalho:\n"
            . $link;
    }

    public static function clientEmailSubject(?string $company): string
    {
        return 'Apresentação' . ($company ? " — {$company}" : '');
    }

    /** Corpo HTML do e-mail ao cliente (usar com Mailer::template no controller). */
    public static function clientEmailBody(string $name, string $link): string
    {
        $saud = $name !== '' ? htmlspecialchars($name) : 'Olá';
        $href = htmlspecialchars($link, ENT_QUOTES);
        return "<p>Olá, <strong>{$saud}</strong>!</p>"
            . "<p>Preparamos uma apresentação para você conhecer melhor nosso trabalho. Clique no botão abaixo para visualizar:</p>"
            . "<p style='text-align:center;margin:25px 0;'>"
            . "<a href='{$href}' style='background:#00BFA6;color:#fff;padding:12px 30px;border-radius:8px;text-decoration:none;font-weight:600;display:inline-block;'>Ver apresentação</a>"
            . "</p>"
            . "<p style='font-size:0.8rem;color:#888;word-break:break-all;'>Link direto: {$href}</p>";
    }

    private static function cut($value, int $max): string
    {
        $v = trim((string)$value);
        return mb_strlen($v) > $max ? mb_substr($v, 0, $max) : $v;
    }

    /**
     * Modelo padrão: apresentação curta ao cliente (capa, quem somos, o que
     * fazemos, clientes e como fazemos), no recorte dos slides 1–5 do modelo
     * da proposta. Tudo editável no editor.
     */
    public static function defaultSections(): array
    {
        $s = fn($type, $title, $subtitle, $body, array $items = [], string $note = '') => [
            'type' => $type, 'title' => $title, 'subtitle' => $subtitle, 'body' => $body,
            'note' => $note, 'visible' => true,
            'items' => array_map(fn($p) => ['title' => $p[0], 'text' => $p[1] ?? ''], $items),
        ];

        return [
            $s('hero', 'ON Solutions', 'Apresentação comercial',
                "Estratégia e tecnologia aplicadas a resultados.\n\nConsultoria estratégica comercial, inteligência de mercado e soluções sob medida para empresas que querem crescer com clareza."),

            $s('cards', 'Quem somos', 'Estratégia e tecnologia aplicadas a resultados.',
                "A ON Solutions é uma consultoria que une estratégia comercial e tecnologia.\n\nEntendemos o negócio, os processos e o objetivo de cada cliente. Depois organizamos a venda e, quando faz sentido, construímos o sistema que a operação precisa.", [
                ['Consultoria estratégica comercial', 'Diagnóstico, posicionamento, CRM, funil de vendas e acompanhamento para organizar e acelerar a operação comercial.'],
                ['Inteligência de mercado', 'Leitura de dados e indicadores de mercado para decidir com clareza onde investir e como crescer.'],
                ['Tecnologia sob medida', 'Sistemas, ERP, CRM, integrações, automações e IA desenhados a partir do processo real de cada operação.'],
            ], 'Atendemos clientes nacionais e internacionais.'),

            $s('stats', 'Indicadores', '', '', [
                ['100+', 'projetos entregues'],
                ['50+', 'clientes ativos'],
                ['99%', 'de satisfação'],
            ]),

            $s('cards', 'O que fazemos — Estratégia comercial', 'Como a empresa vende e cresce.',
                'Organizamos a operação comercial para o cliente saber o que vender, para quem e como acompanhar o resultado.', [
                ['Diagnóstico e plano estratégico', 'Leitura do negócio, do mercado e do funil.'],
                ['Posicionamento e branding', 'Marca, identidade visual e tom de voz.'],
                ['CRM e funil de vendas', 'Processo comercial organizado e mensurável.'],
                ['Presença digital', 'Site, tráfego pago e redes sociais.'],
                ['Acompanhamento estratégico', 'Reuniões, métricas e ajustes de rota.'],
            ]),

            $s('cards', 'O que fazemos — Tecnologia', 'Sistemas feitos para a operação real.',
                'Desenvolvemos a ferramenta certa para o processo do cliente — sem pacote genérico.', [
                ['Sistemas web e personalizados', 'Plataformas desenhadas para a sua operação.'],
                ['ERP personalizado', 'Financeiro, estoque, contratos e projetos em um só ambiente.'],
                ['CRM', 'Leads, funil, propostas e follow-ups organizados.'],
                ['Integrações e APIs', 'Sistemas conectados, sem redigitação.'],
                ['Automações', 'Tarefas repetitivas acontecendo sozinhas.'],
                ['Inteligência artificial', 'IA aplicada a atendimento, dados e decisões.'],
                ['Dashboards e BI', 'Indicadores claros para decidir mais rápido.'],
                ['SaaS e plataformas', 'Da ideia ao produto digital.'],
                ['Aplicativos', 'Soluções mobile integradas à operação.'],
                ['Consultoria tecnológica', 'Diagnóstico de processos, sistemas e gargalos.'],
            ]),

            $s('cards', 'Clientes', 'Quem confia na ON Solutions.',
                'Mais de 50 clientes ativos e mais de 100 projetos entregues.', [
                ['Brazilianna', "Software de vendas e processos comerciais para envios e pagamentos internacionais.\n+62% público · +54% faturamento · 6 meses"],
                ['Quatri Automação', "Software, performance e inteligência de mercado após dois anos de falhas contínuas.\n+38% público · +41% faturamento · 4 meses"],
                ['Brooks Construtora', "Processos internos, operacionais e comerciais com foco em performance.\n+71% público · +230% faturamento · 12 meses"],
                ['CrossHR', "Software para performance, controle de informações e fluxo de pagamentos.\n+55% público · +47% faturamento · 9 meses"],
                ['Multilife Care', "Automação de processos manuais e software para aumentar performance e reduzir custo.\n+43% público · +58% faturamento · 6 meses"],
                ['Mafedo Engenharia', "Branding e site institucional, com aumento da presença digital.\n+66% público · +73% faturamento · 2 meses"],
                ['Inpasa', "Dados e IA para performance comercial e aumento de vendas.\n+78% público · +76% faturamento · 8 meses"],
            ]),

            $s('steps', 'Como fazemos — Estratégia comercial', 'Do entendimento ao resultado.',
                'Estratégia comercial e inteligência de mercado.', [
                ['Diagnóstico', 'Auditoria da jornada, da oferta e do funil, com plano estratégico em 15 a 30 dias após o onboarding.'],
                ['Implantação', 'Posicionamento, identidade visual, CRM e site.'],
                ['Execução', 'Campanhas de tráfego pago e conteúdo nas redes sociais.'],
                ['Acompanhamento', 'Reuniões quinzenais, relatórios e ajustes de rota.'],
            ]),

            $s('steps', 'Como fazemos — Tecnologia sob medida', '',
                'Escopo, prazo e responsabilidade claros, do primeiro diagnóstico à evolução contínua.', [
                ['Discovery', 'Entendimento do negócio, dos processos e dos objetivos.'],
                ['Planejamento', 'Arquitetura da solução e definição do roadmap.'],
                ['Desenvolvimento', 'Construção em sprints, com entregas contínuas e qualidade.'],
                ['Entrega e suporte', 'Deploy, treinamento e suporte contínuo.'],
            ]),
        ];
    }
}
