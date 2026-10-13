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
 *  - o modelo padrão (estrutura recriada a partir da apresentação do Gamma);
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
        'steps'   => 'Etapas / metodologia',
        'cards'   => 'Cards (serviços)',
        'compare' => 'Comparativo',
        'list'    => 'Lista',
        'cta'     => 'Chamada final / contato',
    ];

    public const MAX_SECTIONS = 40;
    public const MAX_ITEMS = 30;

    private const LIMITS = ['title' => 200, 'subtitle' => 300, 'body' => 5000, 'item_title' => 200, 'item_text' => 1000];

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
     * @return array<int,array{type:string,title:string,subtitle:string,body:string,visible:bool,items:array}>
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
                'visible'  => !isset($s['visible']) || filter_var($s['visible'], FILTER_VALIDATE_BOOLEAN),
                'items'    => $items,
            ];
            if (count($out) >= self::MAX_SECTIONS) break;
        }
        return $out;
    }

    /** Decodifica o JSON salvo; conteúdo inválido/vazio cai no modelo padrão. */
    public static function decode(?string $json): array
    {
        $data = json_decode((string)$json, true);
        $sections = self::sanitizeSections(is_array($data) ? ($data['sections'] ?? $data) : null);
        return $sections ?: self::defaultSections();
    }

    public static function encode(array $sections): string
    {
        return json_encode(['sections' => self::sanitizeSections($sections)], JSON_UNESCAPED_UNICODE);
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
     * Modelo padrão: estrutura da apresentação "ON Solutions" do Gamma,
     * adaptada para o cliente (sem as partes internas de SDR/Closer).
     */
    public static function defaultSections(): array
    {
        $s = fn($type, $title, $subtitle, $body, array $items = [], bool $visible = true) => [
            'type' => $type, 'title' => $title, 'subtitle' => $subtitle, 'body' => $body,
            'visible' => $visible,
            'items' => array_map(fn($p) => ['title' => $p[0], 'text' => $p[1] ?? ''], $items),
        ];

        return [
            $s('hero', 'ON Solutions', 'Tecnologia sob medida para a operação de {{cliente}}',
                'Desenvolvemos sistemas, automações e integrações a partir do entendimento real do seu negócio — para que a tecnologia se adapte à operação, e não o contrário.'),

            $s('text', 'Quem somos', '',
                "A ON Solutions Brasil desenvolve soluções tecnológicas sob medida para empresas. O trabalho começa pelo entendimento do negócio, dos processos e dos objetivos do cliente e pode envolver planejamento, desenvolvimento, integração, automação, inteligência artificial e suporte contínuo.\n\nNão vendemos \"código\". Entregamos melhoria operacional por meio de tecnologia."),

            $s('stats', 'Nossos números', '', '', [
                ['100+', 'projetos entregues'],
                ['50+', 'clientes ativos'],
                ['99%', 'de satisfação'],
            ]),

            $s('steps', 'Metodologia', 'Como conduzimos cada projeto', '', [
                ['Discovery', 'Entendimento do negócio, processos e objetivos.'],
                ['Planejamento', 'Arquitetura da solução e definição de roadmap.'],
                ['Desenvolvimento', 'Construção em sprints, entregas contínuas e qualidade.'],
                ['Entrega & Suporte', 'Deploy, treinamento e suporte contínuo.'],
            ]),

            $s('cards', 'O que fazemos', 'Primeiro entendemos o problema; depois conectamos a necessidade à solução adequada.', '', [
                ['Sistemas Web e Personalizados', 'Para empresas que dependem de planilhas, possuem processos específicos, usam ferramentas desconectadas ou precisam de uma plataforma própria.'],
                ['ERP Personalizado', 'Centralização de financeiro, contratos, estoque, compras, projetos, colaboradores, clientes, fornecedores, documentos, indicadores, unidades e centros de custo.'],
                ['CRM', 'Organização de leads, pipeline, funil, histórico, propostas, agenda, tarefas, follow-ups, automações e indicadores comerciais.'],
                ['Integrações & APIs', 'Conexão entre sistemas e eliminação de redigitação: ERP + CRM, CRM + WhatsApp, gateways, bancos, e-commerce e plataformas externas.'],
                ['Automações', 'Automação de tarefas repetitivas: documentos, notificações, atualização de dados, tarefas, relatórios, comunicação e rotinas administrativas.'],
                ['Inteligência Artificial', 'IA aplicada a atendimento, documentos, dados, relatórios, classificação, assistência interna, automações e apoio à decisão.'],
                ['Dashboards & BI', 'Indicadores de financeiro, vendas, operação, produtividade, projetos, clientes, custos e performance.'],
                ['SaaS e Plataformas', 'Transformação de uma ideia de software em produto digital, do planejamento ao desenvolvimento e lançamento.'],
                ['Aplicativos', 'Soluções mobile integradas a sistemas administrativos, bancos de dados, APIs e automações.'],
                ['Consultoria Tecnológica', 'Diagnóstico de operação, sistemas, gargalos, integrações, infraestrutura e oportunidades de automação.'],
            ]),

            $s('compare', 'Por que a ON Solutions', 'Low-code e freelancer avulso versus um parceiro de tecnologia', '', [
                ['Ferramenta genérica adaptada na marra ao processo', 'Solução desenhada a partir do processo real da operação'],
                ['Manutenção depende de uma pessoa só, sem continuidade garantida', 'Equipe técnica e processo de suporte contínuo'],
                ['Integrações frágeis, sem responsabilidade formal por segurança de dados', 'Arquitetura pensada para escalar e para conformidade com a LGPD'],
                ['Custo aparente baixo, mas retrabalho e dependência escondidos', 'Investimento com escopo, prazo e responsabilidade contratual claros'],
            ]),

            $s('list', 'Para quem fazemos', 'Cenários em que mais geramos resultado', '', [
                ['Muitos processos manuais ou dependência de planilhas.'],
                ['Vários sistemas que não conversam entre si.'],
                ['Operação em crescimento ou com múltiplas unidades.'],
                ['Grande volume de clientes, dados, documentos ou transações.'],
                ['Dificuldade de obter indicadores e consolidar informações.'],
                ['Software atual limitado ou excessivamente genérico.'],
                ['Equipe gastando tempo com tarefas repetitivas.'],
                ['Processos internos específicos que ferramentas prontas não atendem.'],
                ['Necessidade de integração, automação ou plataforma própria.'],
            ]),

            $s('steps', 'Como seguimos com você', 'Do diagnóstico à proposta', '', [
                ['Diagnóstico', 'Entendemos a situação atual, o problema e o impacto na operação.'],
                ['Levantamento', 'Mapeamos processos, sistemas envolvidos e o resultado esperado.'],
                ['Escopo', 'Transformamos a necessidade em um escopo viável, com prazo e investimento.'],
                ['Proposta', 'Apresentamos a proposta comercial para sua aprovação.'],
            ]),

            $s('cards', 'LRV Web — sites e hospedagem', 'Para demandas de presença digital e infraestrutura', '', [
                ['Hospedagem Cloud & VPS', 'Infraestrutura VPS com SSD NVMe, proteção DDoS nativa, tráfego ilimitado e suporte humano.'],
                ['Sites Institucionais', 'Sites profissionais, rápidos e otimizados para conversão, com design exclusivo.'],
                ['E-commerce', 'Lojas virtuais completas, com checkout otimizado, integrações de pagamento e gestão de estoque.'],
                ['Automação & WhatsApp', 'Chatbots, disparos e atendimento automatizado.'],
            ], false),

            $s('cta', 'Próximos passos', '',
                'Com base no que conversamos, o próximo passo é estruturarmos o levantamento e transformar a necessidade de {{cliente}} em um escopo com investimento e prazo.',
                [['Site', 'www.onsolutionsbrasil.com.br'], ['E-mail', 'contato@onsolutionsbrasil.com.br']]),
        ];
    }
}
