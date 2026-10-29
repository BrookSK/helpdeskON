<?php
/**
 * Template HTML do cronograma do Planejamento exportado em PDF.
 *
 * ESTRUTURA (inspirada na referência enviada, com identidade do helpdeskON):
 *   - PÁGINA 1: capa de apresentação (título "CRONOGRAMA", empresa, período,
 *     data de geração, marca ON Solutions · Helpdesk), com padrão de fundo leve.
 *   - PÁGINAS 2+: um CALENDÁRIO MENSAL por página. Faixa teal com os dias da
 *     semana (Dom→Sáb), nome do mês grande na vertical à esquerda e o ano
 *     pequeno; as demandas aparecem como FAIXAS CONTÍNUAS ao longo das datas.
 *
 * Renderizado para string por PlanningController::exportSchedulePdf() e
 * convertido via PdfGenerator::fromHtml (dompdf, A4 paisagem). dompdf não tem
 * flex/grid confiável, então tudo é tabela; faixas multi-dia usam <td colspan>.
 *
 * Identidade visual (do site, app/views/layouts/header.php):
 *   primária teal #00BFA6 / #00997D; marinho da sidebar #1a1a2e; teal claro.
 * As CORES DAS DEMANDAS NÃO são alteradas — vêm de $seg['_cat'].
 *
 * Variáveis esperadas:
 *   $viewMonths        array de meses: ['ym','label','weeks'=>[ ['days','segments','lanes'], ... ]]
 *   $viewWeekdays      array rótulos das colunas ['Dom'..'Sáb']
 *   $viewLegend        array DEADLINE_COLORS (legenda de cores, na capa)
 *   $viewTotal         int    total de demandas com data no período
 *   $viewGeneratedAt   string data/hora de geração (d/m/Y H:i)
 *   $viewCompanyLabel  string empresa(s) ou "Todas as empresas"
 *   $viewPeriodLabel   string período (ex.: "Agosto 2026 – Setembro 2026")
 *   $viewFiltersLabel  string resumo dos filtros (compatibilidade; não usado na capa)
 */
if (!isset($viewMonths))        { $viewMonths = []; }
if (!isset($viewWeekdays))      { $viewWeekdays = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb']; }
if (!isset($viewLegend))        { $viewLegend = []; }
if (!isset($viewTotal))         { $viewTotal = 0; }
if (!isset($viewGeneratedAt))   { $viewGeneratedAt = date('d/m/Y H:i'); }
if (!isset($viewCompanyLabel))  { $viewCompanyLabel = 'Todas as empresas'; }
if (!isset($viewPeriodLabel))   { $viewPeriodLabel = ''; }
if (!isset($viewFiltersLabel))  { $viewFiltersLabel = ''; }

$esc = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
$todayKey = date('Y-m-d');

/**
 * Desenha a linha de uma lane: percorre as 7 colunas e emite um <td colspan>
 * para cada segmento (faixa contínua) e <td> vazio nos espaços livres.
 */
$renderLane = function (array $laneSegments) use ($esc): string {
    $byCol = [];
    foreach ($laneSegments as $s) { $byCol[$s['colStart']] = $s; }

    $html = '';
    $col = 0;
    while ($col <= 6) {
        if (isset($byCol[$col])) {
            $s = $byCol[$col];
            $span = $s['colEnd'] - $s['colStart'] + 1;
            $cat = $s['_cat'] ?? ['bg' => '#eef1f4', 'border' => '#94a3b8', 'text' => '#475569'];
            $title = trim((string) ($s['card']['title'] ?? ''));
            if ($title === '') $title = '(sem título)';

            $brL = !empty($s['isStart']) ? '7pt' : '0';
            $brR = !empty($s['isEnd']) ? '7pt' : '0';
            // Marca escura só no início REAL da demanda.
            $edge = !empty($s['isStart']) ? ('border-left:3.5pt solid ' . $esc($cat['border']) . ';') : 'border-left:3.5pt solid transparent;';

            $html .= '<td class="lane-cell" colspan="' . $span . '">';
            $html .= '<div class="bar" style="'
                . 'background:' . $esc($cat['bg']) . ';'
                . 'color:' . $esc($cat['text']) . ';'
                . 'border-radius:' . $brL . ' ' . $brR . ' ' . $brR . ' ' . $brL . ';'
                . $edge
                . '"><span class="bar-title">' . $esc($title) . '</span></div>';
            $html .= '</td>';
            $col = $s['colEnd'] + 1;
        } else {
            $html .= '<td class="lane-cell"></td>';
            $col++;
        }
    }
    return $html;
};
?>
<style>
    @page { margin: 0; }
    body { font-family: "DejaVu Sans", sans-serif; color: #243041; font-size: 9pt; margin: 0; }

    /* Zera o estilo de tabela genérico injetado pelo PdfGenerator::wrap()
       (td/th com borda cinza e padding), para o nosso layout mandar. */
    table, td, th { border: 0; padding: 0; }

    /* =========================================================
     * CAPA (página 1)
     * ========================================================= */
    .cover {
        position: relative;
        width: 100%; height: 19.0cm;   /* área útil de A4 paisagem */
        padding: 0; margin: 0;
        page-break-after: always;
        /* Padrão leve de pontos (SVG embutido via data URI, sem rede). Se o
           renderizador ignorar, o fundo fica branco limpo — degradação segura. */
        background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='22' height='22'><circle cx='2' cy='2' r='1' fill='%23d9ece9'/></svg>");
        background-repeat: repeat;
    }
    /* Faixa geométrica superior (marinho + teal), identidade do site. */
    .cover-band {
        position: absolute; top: 0; left: 0; right: 0; height: 1.5cm;
        background: #1a1a2e;
    }
    .cover-band-accent {
        position: absolute; top: 1.5cm; left: 0; right: 0; height: 0.18cm;
        background: #00BFA6;
    }
    .cover-brand {
        position: absolute; top: 0.42cm; right: 1.6cm;
        color: #ffffff; font-size: 13pt; font-weight: 700; letter-spacing: 0.5pt;
    }
    .cover-brand .soft { color: #00BFA6; font-weight: 400; }

    .cover-body { position: absolute; top: 5.2cm; left: 2.2cm; right: 2.2cm; }
    .cover-title {
        font-size: 40pt; font-weight: 700; color: #1a1a2e;
        letter-spacing: 1pt; margin: 0; line-height: 1;
    }
    .cover-rule { width: 6.2cm; height: 0.16cm; background: #00BFA6; margin: 14pt 0 0; border-radius: 3pt; }

    .cover-info { position: absolute; top: 11.0cm; left: 2.2cm; right: 2.2cm; }
    .cover-info .company { font-size: 16pt; font-weight: 700; color: #00997D; margin: 0; }
    .cover-info .period  { font-size: 11.5pt; color: #4a5a6a; margin: 7pt 0 0; }

    .cover-footer {
        position: absolute; bottom: 1.2cm; left: 2.2cm; right: 2.2cm;
        border-top: 0.75pt solid #e4e8ee; padding-top: 7pt;
    }
    .cover-footer .fl { color: #00997D; font-size: 8.5pt; font-weight: 700; }
    .cover-footer .fr { color: #8a95a5; font-size: 8.5pt; }

    /* Legenda compacta na capa. */
    .cover-legend { position: absolute; bottom: 2.6cm; left: 2.2cm; right: 2.2cm; font-size: 7.5pt; color: #6b7686; }
    .cover-legend .item { display: inline-block; margin: 0 11pt 3pt 0; white-space: nowrap; }
    .cover-legend .swatch { display: inline-block; width: 9pt; height: 9pt; border-radius: 2pt; vertical-align: middle; margin-right: 4pt; }

    /* =========================================================
     * PÁGINAS DE MÊS
     * ========================================================= */
    .month { position: relative; width: 100%; padding: 0.8cm 0.9cm 0.8cm 2.0cm; page-break-after: always; }
    .month:last-child { page-break-after: auto; }

    /* Nome do mês grande na vertical, à esquerda. */
    .month-vert {
        position: absolute; left: 0.25cm; top: 2.0cm;
        color: #1a1a2e; font-size: 20pt; font-weight: 700; letter-spacing: 1pt;
        /* dompdf: rotação via transform. */
        transform: rotate(-90deg);
        transform-origin: left top;
        white-space: nowrap;
    }
    .month-year { position: absolute; left: 0.3cm; top: 0.7cm; color: #00997D; font-size: 11pt; font-weight: 700; }
    .brand-mini { position: absolute; right: 0.9cm; top: 0.55cm; color: #1a1a2e; font-size: 10pt; font-weight: 700; }
    .brand-mini .soft { color: #00BFA6; font-weight: 400; }

    /* Cabeçalho dos dias da semana (faixa teal). */
    table.weekhead { width: 100%; border-collapse: collapse; table-layout: fixed; margin: 1.15cm 0 0; }
    table.weekhead th {
        width: 14.28%; background: #00BFA6; color: #ffffff;
        font-size: 8pt; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6pt;
        padding: 5pt 2pt; text-align: center; border: 0.75pt solid #ffffff;
    }

    /* Uma semana não se parte entre páginas. */
    .week { page-break-inside: avoid; border: 0.75pt dashed #cfd6df; border-top: 0; }

    table.daynums { width: 100%; border-collapse: collapse; table-layout: fixed; }
    table.daynums td {
        width: 14.28%; vertical-align: top; padding: 3pt 5pt 1pt;
        border-right: 0.5pt dashed #e2e7ee; background: #ffffff;
    }
    table.daynums td:last-child { border-right: 0; }
    td.other-month { background: #f7f9fb; }
    td.today-cell { background: #e6fbf7; }
    .day-num { font-size: 8.5pt; font-weight: 700; color: #1a1a2e; text-align: left; display: block; }
    td.other-month .day-num { color: #c7cfda; font-weight: 400; }
    td.today-cell .day-num { color: #00997D; }

    table.lane { width: 100%; border-collapse: collapse; table-layout: fixed; }
    table.lane td.lane-cell { width: 14.28%; vertical-align: top; padding: 0 3pt 2.5pt; }

    .bar { padding: 2.5pt 6pt; font-size: 6.9pt; line-height: 1.25; min-height: 9pt; }
    .bar-title { display: block; word-wrap: break-word; overflow-wrap: break-word; font-weight: 700; text-transform: uppercase; letter-spacing: 0.2pt; }

    .week-pad { height: 3pt; font-size: 0; line-height: 0; }

    .empty { color: #94a3b8; font-style: italic; padding: 16pt; background: #f8fafc; border: 1pt solid #e8ebf0; border-radius: 8pt; text-align: center; }
</style>

<!-- ===================== CAPA ===================== -->
<div class="cover">
    <div class="cover-band"></div>
    <div class="cover-band-accent"></div>
    <div class="cover-brand">ON Solutions <span class="soft">&middot; Helpdesk</span></div>

    <div class="cover-body">
        <h1 class="cover-title">CRONOGRAMA</h1>
        <div class="cover-rule"></div>
    </div>

    <div class="cover-info">
        <p class="company"><?= $esc($viewCompanyLabel) ?></p>
        <?php if (trim((string) $viewPeriodLabel) !== ''): ?>
            <p class="period">Período: <?= $esc($viewPeriodLabel) ?></p>
        <?php endif; ?>
        <p class="period"><?= (int) $viewTotal ?> demanda<?= $viewTotal === 1 ? '' : 's' ?> no período</p>
    </div>

    <?php if (!empty($viewLegend)): ?>
        <div class="cover-legend">
            <?php foreach ($viewLegend as $cat): ?>
                <span class="item">
                    <span class="swatch" style="background:<?= $esc($cat['bg']) ?>;border:0.75pt solid <?= $esc($cat['border']) ?>;"></span>
                    <?= $esc($cat['label']) ?>
                </span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="cover-footer">
        <table style="width:100%;border-collapse:collapse;"><tr>
            <td class="fl" style="text-align:left;">Sistema de Planejamento &mdash; ON Solutions Helpdesk</td>
            <td class="fr" style="text-align:right;">Gerado em <?= $esc($viewGeneratedAt) ?></td>
        </tr></table>
    </div>
</div>

<!-- ===================== MESES ===================== -->
<?php if (empty($viewMonths)): ?>
    <div class="month"><p class="empty">Nenhuma demanda com data encontrada para os filtros selecionados.</p></div>
<?php else: ?>
    <?php foreach ($viewMonths as $month): ?>
        <?php
        $mName = mb_strtoupper(preg_replace('/\s+\d{4}$/', '', $month['label']), 'UTF-8');
        $mYear = "'" . substr($month['ym'], 2, 2);
        ?>
        <div class="month">
            <div class="month-year"><?= $esc($mYear) ?></div>
            <div class="brand-mini">ON <span class="soft">Solutions</span></div>
            <div class="month-vert"><?= $esc($mName) ?></div>

            <table class="weekhead">
                <tr>
                    <?php foreach ($viewWeekdays as $wd): ?>
                        <th><?= $esc($wd) ?></th>
                    <?php endforeach; ?>
                </tr>
            </table>

            <?php foreach ($month['weeks'] as $week): ?>
                <div class="week">
                    <table class="daynums">
                        <tr>
                            <?php foreach ($week['days'] as $cell): ?>
                                <?php
                                $cls = [];
                                if (!empty($cell['otherMonth'])) $cls[] = 'other-month';
                                if ($cell['date'] === $todayKey)  $cls[] = 'today-cell';
                                ?>
                                <td<?= $cls ? ' class="' . implode(' ', $cls) . '"' : '' ?>>
                                    <span class="day-num"><?= (int) $cell['day'] ?></span>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    </table>

                    <?php
                    $lanes = [];
                    foreach ($week['segments'] as $s) { $lanes[(int) $s['lane']][] = $s; }
                    ksort($lanes);
                    ?>
                    <?php foreach ($lanes as $laneSegs): ?>
                        <table class="lane"><tr><?= $renderLane($laneSegs) ?></tr></table>
                    <?php endforeach; ?>

                    <div class="week-pad">&nbsp;</div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
