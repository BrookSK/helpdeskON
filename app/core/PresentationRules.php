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
        'divider' => 'Divisória de parte',
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
     * Modelo padrão: recriação integral do documento "ON Solutions" do Gamma
     * (Base Comercial SDR & Closer), na mesma ordem e com os mesmos textos.
     */
    public static function defaultSections(): array
    {
        $s = fn($type, $title, $subtitle, $body, array $items = [], string $note = '') => [
            'type' => $type, 'title' => $title, 'subtitle' => $subtitle, 'body' => $body,
            'note' => $note, 'visible' => true,
            'items' => array_map(fn($p) => ['title' => $p[0], 'text' => $p[1] ?? ''], $items),
        ];

        return [
            $s('hero', 'ON Solutions', 'BASE COMERCIAL  SDR & Closer | Prospecção Fria B2B',
                'Dar ao time comercial uma base única para entender o que a ON Solutions vende, reconhecer oportunidades reais, abordar prospects frios, qualificar, diagnosticar, avançar oportunidades e registrar o funil com disciplina — em conformidade com a LGPD e alinhado à realidade do mercado brasileiro de tecnologia sob medida.'),

            // ===================== PARTE I =====================
            $s('divider', 'PARTE I — BASE COMERCIAL', '', ''),

            $s('text', '1. Quem somos', '',
                "A ON Solutions Brasil desenvolve soluções tecnológicas sob medida para empresas. O trabalho começa pelo entendimento do negócio, dos processos e dos objetivos do cliente e pode envolver planejamento, desenvolvimento, integração, automação, inteligência artificial, implantação e evolução da solução.\n\nO posicionamento comercial deve ser simples: não vendemos \"código\". Vendemos melhoria operacional por meio de tecnologia."),

            $s('stats', 'Indicadores apresentados no site', '', '', [
                ['100+', 'projetos entregues'],
                ['50+', 'clientes ativos'],
                ['99%', 'de satisfação'],
            ]),

            $s('steps', 'Metodologia apresentada pela empresa', '', '', [
                ['Discovery', 'Entendimento do negócio, processos e objetivos.'],
                ['Planejamento', 'Arquitetura da solução e definição de roadmap.'],
                ['Desenvolvimento', 'Construção em sprints, entregas contínuas e qualidade.'],
                ['Entrega & Suporte', 'Deploy, treinamento e suporte contínuo.'],
            ]),

            $s('cards', '2. O que vendemos', '',
                'O comercial não deve começar oferecendo um produto específico. Primeiro identifica o problema; depois conecta a necessidade à solução adequada.', [
                ['Sistemas Web e Sistemas Personalizados', "Para empresas que dependem de planilhas, possuem processos específicos, usam ferramentas desconectadas ou precisam de uma plataforma própria.\n\n\"Se hoje a operação precisa se adaptar ao software, podemos avaliar o caminho contrário: desenvolver uma solução adaptada à operação.\""],
                ['ERP Personalizado', "Centralização de financeiro, contratos, estoque, compras, projetos, colaboradores, clientes, fornecedores, documentos, indicadores, unidades e centros de custo.\n\n\"Hoje vocês administram a operação em um único ambiente ou precisam combinar sistemas e planilhas?\""],
                ['CRM', "Organização de leads, pipeline, funil, histórico, propostas, agenda, tarefas, follow-ups, automações e indicadores comerciais.\n\n\"Como vocês controlam hoje leads, negociações e follow-ups?\""],
                ['Integrações & APIs', "Conexão entre sistemas e eliminação de redigitação: ERP + CRM, CRM + WhatsApp, gateways, bancos, e-commerce e plataformas externas.\n\n\"Existe informação que a equipe precisa copiar de um sistema para outro?\""],
                ['Automações', "Automação de tarefas repetitivas: documentos, notificações, atualização de dados, tarefas, relatórios, comunicação e rotinas administrativas.\n\n\"Qual tarefa sua equipe repete todos os dias e gostaria que acontecesse automaticamente?\""],
                ['Inteligência Artificial', "IA aplicada a atendimento, documentos, dados, relatórios, classificação, assistência interna, automações e apoio à decisão.\n\n\"Onde existe volume de informação ou trabalho intelectual repetitivo que hoje consome tempo da equipe?\""],
                ['Dashboards & BI', "Indicadores de financeiro, vendas, operação, produtividade, projetos, clientes, custos e performance.\n\n\"A diretoria consegue enxergar os números importantes rapidamente ou depende de relatórios manuais?\""],
                ['SaaS e Plataformas', "Transformação de uma ideia de software em produto digital, do planejamento ao desenvolvimento e lançamento.\n\n\"Existe alguma ideia de plataforma ou produto digital que vocês pretendem tirar do papel?\""],
                ['Aplicativos', "Soluções mobile integradas a sistemas administrativos, bancos de dados, APIs e automações.\n\n\"Existe algum processo que precisa acontecer em campo ou pelo celular?\""],
                ['Consultoria Tecnológica', "Diagnóstico de operação, sistemas, gargalos, integrações, infraestrutura e oportunidades de automação.\n\n\"Antes de decidir tecnologia, podemos mapear o processo e entender onde realmente está o gargalo.\""],
            ], 'Quando a necessidade do prospect é mais simples do que um sistema sob medida, o comercial tem uma rota de saída qualificada: a LRV Web (lrvweb.com.br) — hospedagem, sites institucionais e e-commerce. Diagnostique primeiro; direcione depois.'),

            $s('table', '3. Por que nós — e não low-code ou freelancer', '',
                'O concorrente mais comum hoje não é outra empresa de desenvolvimento — é o low-code/no-code (Make, n8n, Zapier, Bubble) e o freelancer avulso (Workana, 99Freelas, indicação de conhecido). O prospect brasileiro frequentemente já tentou uma dessas alternativas antes de falar com a ON Solutions. Não adianta competir tentando ser "mais barato" — o caminho é tornar essa comparação irrelevante, mostrando onde essas alternativas quebram.', [
                ['O que o mercado oferece (low-code / freelancer)', 'O que a ON Solutions entrega'],
                ['Ferramenta genérica adaptada na marra ao processo', 'Solução desenhada a partir do processo real da operação'],
                ['Manutenção depende de uma pessoa só, sem continuidade garantida', 'Equipe técnica e processo de suporte contínuo'],
                ['Integrações frágeis, sem responsabilidade formal por segurança de dados', 'Arquitetura pensada para escalar e para conformidade com a LGPD'],
                ['Custo aparente baixo, mas retrabalho e dependência escondidos', 'Investimento com escopo, prazo e responsabilidade contratual claros'],
            ], 'Uso em objeção: quando o prospect disser que já resolve com Make/Zapier ou com um freelancer, não desqualifique a ferramenta — pergunte o que acontece quando a pessoa que configurou sai, ou quando o volume cresce. Ver seção 20 (Objeções e respostas).'),

            $s('list', '4. Perfil de cliente ideal', '', '', [
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

            $s('table', 'Segmentação por porte', '',
                'Para priorizar listas e evitar prospecção genérica, classifique a conta antes de abordar:', [
                ['Porte', 'Sinal de prioridade'],
                ['Pequeno (até ~20 funcionários)', 'Prioridade baixa, salvo gatilho forte (ex.: expansão recente)'],
                ['Médio (20–200 funcionários, múltiplas unidades)', 'Prioridade alta — maior parte do ICP real'],
                ['Grande / corporativo', 'Ciclo mais longo, exige múltiplos decisores; tratar como oportunidade estratégica, não volume'],
            ]),

            $s('table', '5. Sinais de oportunidade', '', '', [
                ['O prospect diz', 'Possível oportunidade'],
                ['"A gente controla isso no Excel."', 'Sistema, ERP ou automação'],
                ['"O sistema não faz isso."', 'Customização, sistema próprio ou integração'],
                ['"Precisamos cadastrar duas vezes."', 'Integração / automação'],
                ['"Demoramos para conseguir esses números."', 'Dashboard / BI'],
                ['"Minha equipe perde muito tempo nisso."', 'Automação'],
                ['"Temos vários sistemas."', 'Integração / centralização'],
                ['"Queremos usar IA."', 'IA aplicada ao processo'],
                ['"Temos uma ideia de plataforma."', 'SaaS / desenvolvimento'],
                ['"Nosso software ficou pequeno."', 'Modernização / sistema personalizado'],
                ['"Já uso Make/Zapier/planilha com automação."', 'Substituição de solução frágil por sistema próprio (ver seção 3)'],
            ]),

            $s('list', '6. Perguntas de qualificação comercial', '', '', [
                ['O que a empresa deseja melhorar?'],
                ['Como esse processo funciona hoje?'],
                ['Qual é o principal problema do processo atual?'],
                ['Quais sistemas ou ferramentas são utilizados?'],
                ['Existem atividades manuais ou planilhas envolvidas?'],
                ['Quantas pessoas participam do processo?'],
                ['O problema gera perda de tempo, dinheiro, controle ou oportunidades?'],
                ['Já tentaram resolver anteriormente?'],
                ['Existe integração necessária com sistemas atuais?'],
                ['Qual seria o resultado esperado?'],
            ], 'O vendedor não precisa saber programar. Precisa saber reconhecer problemas que tecnologia pode resolver. Não desenhe tecnicamente a solução durante a prospecção.'),

            $s('text', '7. Pitch institucional curto', '',
                "O pitch começa pelo problema do cliente, não pela empresa. A ON Solutions é a guia — quem tem o protagonismo é o prospect e a operação dele.\n\n\"Muita empresa em crescimento chega num ponto em que os sistemas que usa não acompanham mais a operação — aparecem planilhas paralelas, retrabalho e informação espalhada. A ON Solutions existe para resolver exatamente isso: analisamos a operação e desenvolvemos sistemas, integrações, automações ou IA sob medida, para que a tecnologia se adapte ao negócio — e não o contrário.\"",
                [], 'Se fizer sentido no contexto, feche com a consequência de não resolver: "Sem isso, a tendência é a operação continuar dependendo de esforço manual para crescer — o que trava a expansão em algum momento."'),

            $s('list', '8. Objetivo da primeira conversa', '', '', [
                ['Qual é o problema?'],
                ['Como funciona atualmente?'],
                ['Qual impacto o problema gera?'],
                ['Quem é afetado?'],
                ['O que o cliente gostaria que acontecesse?'],
                ['Existe oportunidade real de tecnologia resolver?'],
            ], 'Havendo oportunidade, o próximo passo é uma reunião de diagnóstico e levantamento da solução.'),

            $s('steps', '9. Processo comercial macro', '', '', [
                ['Leads'],
                ['Qualificação'],
                ['Proposta'],
                ['Entrega'],
            ]),

            // ===================== COMPLIANCE =====================
            $s('divider', 'COMPLIANCE', '', ''),

            $s('text', '10. Compliance e LGPD na prospecção', '',
                'Prospecção fria B2B é legal no Brasil sob a LGPD, com base no legítimo interesse (art. 7º, IX) — desde que a operação siga três condições: finalidade legítima (oferecer algo relevante para a empresa contatada), necessidade (usar o mínimo de dado possível — em geral, e-mail e telefone corporativos) e respeito ao direito de recusa do contato. A ANPD já fiscaliza ativamente e as multas podem chegar a 2% do faturamento (limite de R$ 50 milhões por infração).'),

            $s('steps', 'Regras obrigatórias da operação comercial', '', '', [
                ['', 'Use preferencialmente dados corporativos (e-mail e telefone da empresa), não dados pessoais avulsos.'],
                ['', 'Todo primeiro contato deve deixar claro quem está falando e de qual empresa.'],
                ['', 'Se o prospect pedir para não ser mais contatado, registre no CRM e encerre a cadência imediatamente — sem exceções.'],
                ['', 'Contatos sem resposta após a cadência completa (seção 30) devem ser arquivados; não reative listas antigas sem novo critério de relevância.'],
                ['', 'Para disparo em WhatsApp em volume, utilize sempre a API oficial (WhatsApp Business API) com número aquecido — números pessoais ou o app comum têm alto risco de bloqueio por spam.'],
            ]),

            // ===================== PARTE II =====================
            $s('divider', 'PARTE II — PLAYBOOK OPERACIONAL SDR & CLOSER', '', ''),

            $s('cards', '11. Realidade da prospecção fria', '',
                'O prospect não pediu contato, está ocupado e presume que a abordagem tem intenção comercial. O SDR não precisa conquistar o cliente inteiro no primeiro toque: precisa conquistar atenção suficiente para iniciar uma conversa.', [
                ['Funil mental do SDR', 'Contato → Atenção → Curiosidade → Identificação de problema → Reunião'],
                ['Funil mental do Closer', 'Diagnóstico → Impacto → Prioridade → Solução → Proposta → Negociação → Fechamento'],
            ]),

            $s('list', '12. Quem prospectar', '', 'Priorize empresas em que existam sinais de complexidade operacional:', [
                ['Múltiplas unidades ou filiais.'],
                ['Equipe administrativa relevante.'],
                ['Crescimento ou expansão.'],
                ['Muitos funcionários, clientes ou transações.'],
                ['Logística, financeiro ou operação de campo complexos.'],
                ['Equipes comerciais estruturadas.'],
                ['Construção, indústria, distribuição, serviços B2B e operações com muitos processos.'],
                ['Uso aparente de vários softwares ou processos digitais fragmentados.'],
            ]),

            $s('list', '13. Pesquisa pré-contato: 1 a 3 minutos', '', '', [
                ['O que a empresa faz?'],
                ['Qual o porte aparente?'],
                ['Quem provavelmente decide?'],
                ['Que processo aparenta ter complexidade?'],
                ['Existe um gatilho real: expansão, vaga, nova unidade, crescimento, mudança, notícia ou serviço?'],
            ], 'Fontes: site, LinkedIn, Google, vagas, notícias e páginas institucionais. Pesquisa deve melhorar a abordagem sem destruir a escala.'),

            $s('text', '14. Hipótese, não acusação', '',
                "A abordagem deve responder: \"Por que estou falando especificamente com essa empresa?\"\n\nExemplo: empresa com várias unidades → hipótese de dificuldade de centralização. Empresa com muitos vendedores → hipótese de CRM, acompanhamento ou automação. Empresa crescendo → hipótese de sistemas atuais não acompanharem a operação.",
                [], 'Nunca afirme que a empresa tem um problema sem saber. Use: "Em empresas desse perfil, um problema que aparece bastante é..."'),

            $s('cards', '15. Abordagem por WhatsApp', '', '', [
                ['Primeiro toque — curto', "\"Oi, Ricardo. Tudo bem? Aqui é o Lucas, da ON Solutions. Encontrei vocês pesquisando empresas do setor de [segmento]. Posso te explicar rapidamente o motivo do contato?\"\n\nPare. Não envie PDF, apresentação ou sequência de mensagens antes da resposta."],
                ['Se responder "pode"', '"Obrigado. Nós desenvolvemos sistemas e automações para empresas e tenho conversado com algumas operações de [segmento]. Uma coisa que aparece bastante é equipe dependendo de planilhas, processos manuais ou vários sistemas que não conversam. Vocês têm alguma situação parecida hoje?"'],
                ['Versão direta', '"Ricardo, vou ser bem direto para não tomar seu tempo. Trabalho na ON Solutions e ajudamos empresas a substituir processos manuais, planilhas e sistemas desconectados por sistemas e automações próprias. Não sei se isso é uma dor de vocês hoje. Existe algum processo interno que vocês gostariam de automatizar ou melhorar?"'],
                ['Com gatilho real', '"Ricardo, vi que vocês estão expandindo a operação para novas unidades. Trabalho na ON Solutions e atuamos com desenvolvimento e automação de sistemas internos. Em empresas crescendo dessa forma normalmente começam a aparecer gargalos entre sistemas, planilhas e processos manuais. Isso já acontece com vocês ou a operação está bem centralizada?"'],
            ], 'Disparo de prospecção em volume pelo WhatsApp comum tem alto risco de bloqueio do número por spam. Use a API oficial do WhatsApp Business, com número aquecido gradualmente e limite diário de novos contatos por número.'),

            $s('text', '16. Cold call', '',
                "\"Ricardo? Aqui é o João, da ON Solutions. Vou ser transparente: é uma ligação comercial e você não estava esperando meu contato. Me dá 30 segundos para explicar por que liguei especificamente para vocês e você me diz se faz sentido continuar?\"\n\n\"Se houver permissão: Eu estava pesquisando empresas de [segmento] e encontrei vocês. Trabalhamos desenvolvendo sistemas e automações para operações que acabam acumulando planilhas, processos manuais ou vários sistemas separados. Isso acontece em alguma área de vocês hoje?\""),

            $s('text', '17. Como passar pelo gatekeeper', '',
                "Em empresas médias brasileiras, o primeiro contato costuma ser uma secretária, recepção ou assistente — não o decisor. Não tente contornar disfarçando a intenção; seja direto e peça direcionamento.\n\n\"Oi, tudo bem? Sou o João, da ON Solutions. Estou tentando falar com quem cuida de sistemas ou operações aí na empresa — normalmente é o diretor ou o gerente administrativo. Você consegue me indicar quem seria, ou já me direcionar?\"",
                [], 'Se a pessoa pedir para explicar o motivo, dê a versão de uma frase (o problema, não a empresa), nunca a lista de serviços.'),

            $s('list', '18. Perguntas que geram conversa', '', '', [
                ['Tem algum processo que ainda depende muito de Excel?'],
                ['Tem informação que precisa ser cadastrada em mais de um sistema?'],
                ['Existe alguma atividade que a equipe faz manualmente todos os dias?'],
                ['Os sistemas que vocês usam conversam entre si?'],
                ['Existe alguma informação que a diretoria demora para conseguir?'],
                ['Existe algum software atual que limita a operação?'],
                ['Tem algum processo que vocês já pensaram em automatizar?'],
            ]),

            $s('dont', '19. O que evitar', '', '', [
                ['"Vocês precisam de algum sistema?"'],
                ['"Vocês têm interesse em inteligência artificial?"'],
                ['"Posso apresentar nossa empresa?"'],
                ['Longa lista de serviços no primeiro contato.'],
                ['Jargão técnico sem conexão com impacto de negócio.'],
                ['Fingir conhecer uma dor que ainda não foi confirmada.'],
            ]),

            $s('cards', '20. Objeções e respostas', '', '', [
                ['"Não tenho interesse."', "\"Tranquilo. Só para eu não insistir errado: vocês já possuem essa parte bem resolvida internamente ou simplesmente não é uma prioridade agora?\"\n\nSe estiver resolvido: encerre. Se não for prioridade: descubra se existe previsão futura e registre como nutrição quando fizer sentido."],
                ['"Manda material."', '"Envio sim. Só para eu não te mandar uma apresentação genérica: qual área faria mais sentido — operação, comercial, financeiro ou automação?"'],
                ['"Já temos sistema."', '"Perfeito. Grande parte dos nossos projetos não começa substituindo tudo. Às vezes entramos integrando ou automatizando processos entre sistemas existentes. Hoje vocês sentem alguma limitação ou trabalho manual mesmo utilizando o sistema atual?"'],
                ['"Já resolvemos com Make/Zapier/planilha automatizada."', '"Faz sentido, isso resolve muita coisa no começo. Uma coisa que costuma aparecer depois é a dependência de quem configurou aquilo, ou dificuldade quando o volume cresce. Isso já apareceu para vocês ou ainda está tudo estável?"'],
                ['"Temos um freelancer/conhecido que faz isso."', '"Legal que já tem alguém cuidando disso. Só por curiosidade: se essa pessoa não estiver disponível numa emergência, quem mais na empresa entende do sistema? É algo que já pensaram, ou não chegou a ser um problema ainda?"'],
                ['"Estou sem tempo."', '"Sem problema. Prefere que eu te mande uma mensagem curta e você olha quando conseguir?"'],
                ['"Quanto custa?"', '"Depende bastante do que precisa ser desenvolvido. Como trabalhamos com projetos personalizados, eu estaria inventando um valor sem entender o cenário. Se você me explicar rapidamente o que gostaria de resolver, consigo identificar se faz sentido avançarmos para um levantamento."'],
            ]),

            $s('text', '21. Quando agendar a reunião', '',
                "Agende assim que houver problema + possível impacto + abertura do prospect. Não transforme qualificação em interrogatório.\n\n\"Entendi. Isso já é exatamente o tipo de cenário que analisamos. Em vez de tentar desenhar a solução por WhatsApp, acho mais produtivo colocar você numa conversa com nosso responsável para entender como funciona hoje e avaliar as possibilidades. Consegue falar uns 30 minutos na terça ou quarta?\"",
                [], 'Venda a reunião como diagnóstico, conversa técnica, entendimento do processo, levantamento inicial ou avaliação de viabilidade — não como "apresentação institucional".'),

            $s('list', '22. Qualificação mínima do SDR', '', '', [
                ['Empresa e segmento'],
                ['Nome e cargo do contato'],
                ['Processo discutido'],
                ['Como funciona atualmente'],
                ['Dor identificada'],
                ['Impacto percebido'],
                ['Solução atual: planilha, ERP, CRM, sistema próprio etc.'],
                ['Prioridade'],
                ['Decisor ou participantes da decisão'],
                ['Próximo passo e data'],
            ]),

            $s('text', '23. Passagem SDR → Closer', '',
                'Empresa: ABC Construções | Contato: Ricardo, Diretor | Cenário: 7 obras simultâneas | Atual: ERP + Excel | Problema: financeiro das obras parcialmente em planilhas | Impacto: dificuldade de consolidar custo real por obra | Necessidade: centralizar financeiro e obras | Prioridade: média/alta | Decisão: Ricardo + sócio.',
                [], 'Nunca passe "cliente interessado em ERP". Passe contexto suficiente para o Closer continuar a conversa sem obrigar o prospect a repetir tudo.'),

            $s('text', '24. Papel do Closer', '',
                "O Closer não começa apresentando a ON Solutions. Começa validando o contexto recebido e aprofundando o problema.\n\n\"Ricardo, o João me passou um contexto sobre vocês. Pelo que entendi, hoje vocês utilizam [X], mas ainda existe dificuldade em [Y]. Antes de falar de qualquer solução, queria entender melhor como esse processo funciona hoje.\""),

            $s('table', '25. Estrutura de diagnóstico do Closer', '', '', [
                ['Dimensão', 'Pergunta'],
                ['Situação', 'Como vocês fazem isso atualmente?'],
                ['Problema', 'Onde exatamente começa a dificuldade?'],
                ['Impacto', 'Quando isso acontece, o que gera para a empresa?'],
                ['Frequência', 'Isso é eventual ou faz parte da rotina?'],
                ['Pessoas', 'Quantas pessoas acabam envolvidas?'],
                ['Tentativas anteriores', 'Já tentaram resolver isso?'],
                ['Resultado esperado', 'Se isso fosse resolvido, como seria o cenário ideal?'],
                ['Prioridade', 'Pretendem resolver agora ou estão apenas avaliando possibilidades?'],
            ]),

            $s('text', '26. Quantificar impacto', '',
                'Exemplo: "Temos quatro pessoas consolidando planilhas toda semana." Pergunte quanto tempo cada uma gasta. Se forem 6 horas por semana, são 24 horas semanais. A conversa deixa de ser "automatizar uma planilha" e passa a ser "eliminar dezenas de horas mensais de trabalho operacional".'),

            $s('list', '27. Apresentação da solução', '', 'Somente depois do diagnóstico, organize a devolutiva:', [
                ['Hoje vocês têm: A + B + C.'],
                ['Isso está causando: X + Y + Z.'],
                ['O que entendemos que precisa acontecer: 1 + 2 + 3.'],
                ['A ON Solutions pode desenvolver: solução proposta.'],
            ], "Não apresente 50 funcionalidades. Fale da solução diretamente relacionada à dor discutida.\n\nFeche a devolutiva com a consequência de não agir: \"Se isso continuar como está, o cenário mais provável é [consequência concreta discutida no diagnóstico].\" Isso reforça a urgência sem exagerar ou pressionar."),

            $s('text', '28. Próximo passo do Closer', '',
                '"Com o que você me apresentou, acredito que existe aderência para avançarmos. O próximo passo seria estruturarmos o levantamento e transformar isso em um escopo com investimento e prazo."',
                [], 'Nunca termine com "qualquer coisa me chama". Toda oportunidade ativa deve sair com próximo passo, responsável e data.'),

            $s('text', '29. Pedido de indicação no fechamento', '',
                "Boa parte do fechamento no mercado brasileiro de tecnologia sob medida — especialmente em construção, indústria e distribuição — vem de indicação e confiança local, não apenas de prospecção fria. Ao fechar uma venda, o Closer deve sempre perguntar por indicações.\n\n\"Ricardo, já que fechamos: você conhece outras empresas do seu setor ou da sua rede que passam por um cenário parecido com o que vocês tinham antes? Fico à disposição para uma conversa, sem compromisso.\"",
                [], 'Registre indicações no CRM com origem "indicação de cliente" — esse canal deve ter tratamento prioritário na cadência.'),

            $s('steps', '30. Cadência de follow-up', '', '', [
                ['Dia 1', 'Ligação + canal complementar'],
                ['Dia 3', 'Follow-up curto'],
                ['Dia 6', 'Nova tentativa por outro canal'],
                ['Dia 10', 'Retomar a hipótese/dor'],
                ['Dia 15', 'Nova tentativa'],
                ['Dia 21', 'Último contato da sequência ativa'],
            ], 'Não envie repetidamente "conseguiu ver minha mensagem?". Cada contato deve ter contexto, pergunta ou motivo.'),

            $s('text', 'Trilha de nutrição longa', '',
                "Projetos de sistema/ERP/IA sob medida costumam ter ciclo de decisão de 60 a 180 dias no Brasil, especialmente em empresas familiares. Encerrar definitivamente aos 21 dias descarta oportunidades que ainda vão amadurecer.\n\nApós o encerramento da cadência ativa, mova o contato para nutrição longa: um novo toque a cada 45–60 dias, sempre com gatilho ou conteúdo novo (case, notícia do setor, nova funcionalidade) — nunca apenas \"passando para saber se pensou no assunto\"."),

            $s('cards', '', '', '', [
                ['Follow-up 1', '"Ricardo, retomando meu contato porque acredito que a questão de [problema] pode fazer sentido para vocês. Chegaram a avaliar algo nessa área?"'],
                ['Follow-up 2', '"Ricardo, não quero ficar insistindo se isso não estiver no radar. Hoje [problema] é algo que vocês pretendem melhorar ou posso encerrar por aqui?"'],
                ['Encerramento (cadência ativa)', '"Ricardo, como não consegui falar com você, vou encerrar meus contatos ativos para não ficar insistindo. Vou deixar você na nossa lista de novidades do setor — se em algum momento quiser retomar, é só chamar."'],
            ]),

            $s('text', '31. Multicanal', '',
                'Telefone + LinkedIn + e-mail + WhatsApp, quando adequado. O objetivo não é perseguir o prospect em todos os canais no mesmo minuto, mas aumentar a chance de contato ao longo da cadência.'),

            $s('list', '32. CRM: disciplina obrigatória', '', '', [
                ['Data e horário da tentativa'],
                ['Canal utilizado'],
                ['Resultado'],
                ['Resposta relevante'],
                ['Dor ou hipótese'],
                ['Status'],
                ['Próxima ação'],
                ['Responsável'],
                ['Data do follow-up'],
                ['Origem do lead (prospecção fria, indicação, inbound)'],
            ]),

            $s('steps', '33. Status sugeridos — SDR', '', '', [
                ['Lead identificado'],
                ['Em prospecção'],
                ['Contato realizado'],
                ['Contato estabelecido'],
                ['Em qualificação'],
            ]),

            $s('steps', '34. Status sugeridos — Closer', '', '', [
                ['Diagnóstico'],
                ['Levantamento'],
                ['Escopo'],
                ['Proposta enviada'],
            ]),

            $s('list', '35. Métricas do SDR', '', '', [
                ['Tentativas'],
                ['Contatos efetivos'],
                ['Conversas iniciadas'],
                ['Leads qualificados'],
                ['Reuniões agendadas'],
                ['Reuniões realizadas'],
                ['Oportunidades aceitas pelo Closer'],
                ['Tempo médio de primeira resposta ao lead — speed-to-lead'],
            ], "Não avaliar somente volume. Trinta reuniões ruins podem gerar menos resultado que quinze reuniões qualificadas.\n\nO tempo entre um lead chegar e o primeiro contato ser feito é um dos fatores mais associados à conversão em vendas B2B. Leads não trabalhados no mesmo dia perdem parte relevante da chance de virar reunião. Trate esse indicador com a mesma prioridade dada às métricas de volume."),

            $s('list', '36. Métricas do Closer', '', '', [
                ['Reuniões realizadas'],
                ['Oportunidades qualificadas'],
                ['Propostas enviadas'],
                ['Valor de pipeline'],
                ['Taxa reunião → proposta'],
                ['Taxa proposta → fechamento'],
                ['Ticket médio'],
                ['Ciclo médio de venda'],
                ['Receita fechada'],
                ['Motivos de perda'],
                ['% de vendas originadas por indicação'],
            ]),

            $s('text', '37. Critério de qualidade da reunião', '',
                'Uma reunião deve ser considerada comercialmente útil quando há contexto suficiente para discutir um problema real. Idealmente o SDR terá identificado processo, situação atual, dor, algum impacto, prioridade e interlocutor adequado. Quando algum item não puder ser obtido sem prejudicar a abordagem, o Closer complementa no diagnóstico.'),

            $s('cards', '39. Princípios finais', '', '', [
                ['SDR', 'Vende a conversa. Encontra problema, abertura e contexto. Não precisa desenhar arquitetura.'],
                ['Closer', 'Vende a mudança. Aprofunda impacto, prioridade, resultado esperado e conduz o próximo passo.'],
                ['Equipe técnica', 'Desenha a solução. Recebe um problema contextualizado e transforma necessidade em escopo viável.'],
            ], "A operação comercial da ON Solutions deve encontrar empresas com problemas reais que possam ser resolvidos através de tecnologia — e não tentar convencer qualquer empresa a comprar software.\n\nOs textos deste playbook são pontos de partida, não falas para decorar. Use a hipótese e o contexto real do prospect (seção 14) para adaptar a linguagem — soar natural importa mais do que seguir o script à risca."),

            // ===================== PARTE III =====================
            $s('divider', 'PARTE III — GUIA RÁPIDO DE USO DIÁRIO', 'Checklists Operacionais', ''),

            $s('list', '40. Checklist do SDR antes de iniciar o dia', '', '', [
                ['Lista de contas priorizada.'],
                ['Contato e cargo validados.'],
                ['Hipótese de dor definida por conta.'],
                ['Cadência e próximos follow-ups revisados.'],
                ['CRM aberto e pronto para registro.'],
            ]),

            $s('list', '41. Checklist antes de passar ao Closer', '', '', [
                ['Há um problema ou necessidade minimamente clara?'],
                ['O cenário atual foi registrado?'],
                ['Existe algum impacto percebido?'],
                ['A prioridade foi sondada?'],
                ['O contato participa da decisão ou sabe quem participa?'],
                ['A reunião tem objetivo e contexto registrados?'],
                ['Existe definição de escopo'],
                ['Qual o orçamento definido ?'],
            ]),

            $s('list', '42. Checklist do Closer após a reunião', '', '', [
                ['Situação atual validada.'],
                ['Problema principal definido.'],
                ['Impacto e frequência compreendidos.'],
                ['Resultado esperado registrado.'],
                ['Prioridade e processo decisório entendidos.'],
                ['Próximo passo definido com data.'],
                ['CRM atualizado no mesmo dia.'],
            ]),

            // ===================== LRV WEB =====================
            $s('divider', 'HOSPEDAGEM, E-COMMERCE E SITES INSTITUCIONAIS SIMPLES', '', ''),

            $s('stats', 'LRV Web — Serviço Secundário', '',
                'A LRV Web (lrvweb.com.br) é a marca usada para atender demandas de menor complexidade que não são o foco principal da ON Solutions. Ela existe para que o comercial tenha uma resposta adequada quando a necessidade do prospect é mais simples do que um sistema sob medida — sem forçar a venda de um projeto grande demais, e sem simplesmente descartar o lead.', [
                ['500+', 'projetos entregues'],
                ['200+', 'clientes ativos'],
                ['99.9%', 'de uptime garantido'],
                ['5+', 'anos de mercado'],
            ], 'A LRV Web NUNCA deve ser o ponto de partida da prospecção. É uma rota de saída qualificada: use-a quando, durante a qualificação, a necessidade real do prospect for mais simples do que um sistema, ERP ou automação sob medida.'),

            $s('table', 'Sinais de Encaminhamento', '', '', [
                ['Sinal identificado na conversa', 'Encaminhar para'],
                ['Precisa de site institucional simples, sem sistema por trás', 'LRV Web — Criação de Sites'],
                ['Quer vender online, precisa de loja virtual', 'LRV Web — E-commerce'],
                ['Site atual fora do ar, lento, ou hospedagem ruim/sem suporte', 'LRV Web — Hospedagem Cloud & VPS'],
                ['Precisa de sistema, ERP, CRM, integração ou automação de processo', 'ON Solutions (fluxo principal)'],
                ['Processo interno complexo, múltiplos sistemas, dados de operação', 'ON Solutions (fluxo principal)'],
            ]),

            $s('cards', 'Serviços LRV Web', '', '', [
                ['Hospedagem Cloud & VPS', 'Infraestrutura VPS com processadores Intel Xeon, SSD NVMe, proteção DDoS nativa, tráfego ilimitado e suporte humano — sem robô. Ativação em até 6 dias úteis. Reajuste anual pelo IGPM, sem taxas ocultas.'],
                ['Criação de Sites Institucionais', 'Sites profissionais, rápidos e otimizados para conversão, com design exclusivo — para empresas que precisam apenas de presença digital, sem sistema administrativo por trás.'],
                ['E-commerce', 'Lojas virtuais completas, com checkout otimizado, integrações de pagamento e gestão de estoque.'],
                ['Automação & WhatsApp', 'Chatbots, disparos e atendimento automatizado — oferta complementar quando o prospect qualificado pela LRV Web também tem demanda pontual de automação simples (sem justificar um projeto ON Solutions completo).'],
            ]),

            $s('table', 'Planos de Hospedagem Cloud & VPS', '', '', [
                ['Plano', 'Configuração | Preço'],
                ['Startup', '1 vCPU · 2GB RAM · 20GB SSD | R$ 90/mês'],
                ['Essential', '2 vCPU · 4GB RAM · 40GB SSD | R$ 297/mês'],
                ['Professional', '4 vCPU · 8GB RAM · 80GB SSD | R$ 697/mês'],
                ['Business', '8 vCPU · 16GB RAM · 160GB SSD | R$ 957/mês'],
                ['Enterprise', '16 vCPU · 32GB RAM · 300GB SSD | R$ 1.497/mês'],
                ['Sob Medida', 'Configuração personalizada | Consultar'],
            ]),

            // ===================== ENCAMINHAMENTO =====================
            $s('divider', 'QUALIFICAÇÃO E ENCAMINHAMENTO', '', ''),

            $s('cards', 'Como qualificar e encaminhar para a LRV Web', '', '', [
                ['Quando o prospect pede apenas um site institucional', 'Entendi. Pra esse caso específico — um site institucional, sem sistema por trás — a gente direciona para a nossa frente de sites e hospedagem, a LRV Web. Eles cuidam de site, e-commerce e hospedagem de forma mais enxuta e rápida. Posso te apresentar o que eles fazem?'],
                ['Quando o prospect reclama de hospedagem/site fora do ar', 'Isso já é uma dor bem comum. A gente tem uma frente específica de hospedagem em nuvem — servidores VPS com proteção contra ataque e suporte de verdade, sem robô. Quer que eu te passe os planos?'],
                ['Quando surge e-commerce durante o diagnóstico de um projeto maior', 'Legal, isso também é algo que atendemos, só que por uma frente mais enxuta — a LRV Web cuida especificamente de loja virtual, com checkout e integração de pagamento. Faz sentido eu te conectar com esse time em paralelo à conversa que estamos tendo aqui?'],
            ], 'Não tente vender LRV Web como se fosse um upsell de sistema. É uma oferta de categoria diferente — infraestrutura e presença digital, não sistema de gestão. Manter essa clareza evita confundir o prospect sobre o que está comprando.'),

            $s('list', 'Contato e Handoff', '', '', [
                ['Site: lrvweb.com.br'],
                ['Orçamento: lrvweb.com.br/pt/contato'],
                ['WhatsApp comercial: (17) 98809-3160'],
                ['Portal de planos de hospedagem: cloud.lrvweb.com.br'],
            ], 'Ao encaminhar um lead para a LRV Web, registre no CRM a origem (\'encaminhado por SDR/Closer ON Solutions\') e o motivo do direcionamento, para permitir rastreio de conversão entre as duas frentes.'),
        ];
    }
}

