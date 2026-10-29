<?php
/**
 * Template HTML (documento completo) do cronograma do Planejamento em PDF.
 *
 * ESTRUTURA (identidade do helpdeskON — teal/marinho):
 *   - PÁGINA 1: capa (título "CRONOGRAMA", empresa, período, data, marca).
 *   - PÁGINAS 2+: um CALENDÁRIO MENSAL por página. Cabeçalho com nome do mês +
 *     ano + marca; faixa teal com os dias (Dom→Sáb); demandas como FAIXAS
 *     CONTÍNUAS ao longo das datas, com a cor original de cada demanda.
 *
 * Gerado por PlanningController::exportSchedulePdf() via
 * PdfGenerator::fromDocument (dompdf, A4 paisagem). É um DOCUMENTO HTML
 * COMPLETO: define o próprio @page/CSS, para o dompdf não injetar estilos de
 * tabela genéricos (que quebravam largura/bordas do calendário).
 *
 * Regras de layout à prova de dompdf:
 *   - Sem position:absolute/transform para o conteúdo do mês (causavam estouro
 *     horizontal e sobreposição). Tudo em fluxo normal + tabelas width:100%.
 *   - Título da demanda em UMA linha, truncado com reticências quando não cabe
 *     (white-space:nowrap; overflow:hidden; text-overflow:ellipsis).
 *
 * As CORES DAS DEMANDAS NÃO são alteradas — vêm de $seg['_cat'].
 *
 * Variáveis esperadas:
 *   $viewMonths, $viewWeekdays, $viewLegend, $viewTotal, $viewGeneratedAt,
 *   $viewCompanyLabel, $viewPeriodLabel, $viewFiltersLabel
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
 * Linha de uma lane: percorre as 7 colunas e emite <td colspan> para cada
 * segmento (faixa contínua) e <td> vazio nos espaços livres.
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

            $brL = !empty($s['isStart']) ? '6pt' : '0';
            $brR = !empty($s['isEnd']) ? '6pt' : '0';
            $edge = !empty($s['isStart'])
                ? ('border-left:3.5pt solid ' . $esc($cat['border']) . ';')
                : 'border-left:3.5pt solid transparent;';

            $html .= '<td class="lane-cell" colspan="' . $span . '">';
            $html .= '<div class="bar" style="'
                . 'background:' . $esc($cat['bg']) . ';'
                . 'color:' . $esc($cat['text']) . ';'
                . 'border-radius:' . $brL . ' ' . $brR . ' ' . $brR . ' ' . $brL . ';'
                . $edge
                . '" title="' . $esc($title) . '"><span class="bar-title">' . $esc($title) . '</span></div>';
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
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
    /* Margem real em todos os lados: nada de estouro horizontal. */
    @page { margin: 0.9cm 1.1cm; }
    * { box-sizing: border-box; }
    body { font-family: "DejaVu Sans", sans-serif; color: #243041; font-size: 9pt; margin: 0; }

    /* =========================================================
     * CAPA (página 1)
     * ========================================================= */
    .cover { page-break-after: always; }
    .cover-band { background: #1a1a2e; height: 1.2cm; }
    .cover-band .brand {
        color: #ffffff; font-size: 12pt; font-weight: 700; letter-spacing: 0.5pt;
        padding: 9pt 4pt 0 2pt; text-align: right;
    }
    .cover-band .brand .soft { color: #00BFA6; font-weight: 400; }
    .cover-accent { height: 0.14cm; background: #00BFA6; }

    .cover-title-wrap { margin: 3.4cm 0 0; }
    .cover-title { font-size: 42pt; font-weight: 700; color: #1a1a2e; letter-spacing: 1pt; margin: 0; line-height: 1; }
    .cover-rule { width: 6.4cm; height: 0.16cm; background: #00BFA6; margin: 16pt 0 0; border-radius: 3pt; }

    .cover-info { margin: 2.6cm 0 0; }
    .cover-info .company { font-size: 17pt; font-weight: 700; color: #00997D; margin: 0; }
    .cover-info .period  { font-size: 12pt; color: #4a5a6a; margin: 8pt 0 0; }

    .cover-legend { margin: 1.4cm 0 0; font-size: 7.5pt; color: #6b7686; }
    .cover-legend .item { display: inline-block; margin: 0 12pt 3pt 0; }
    .cover-legend .swatch { display: inline-block; width: 9pt; height: 9pt; border-radius: 2pt; vertical-align: middle; margin-right: 4pt; }

    .cover-footer { margin-top: 1.2cm; border-top: 0.75pt solid #e4e8ee; padding-top: 7pt; }
    .cover-footer td { border: 0; padding: 0; }
    .cover-footer .fl { color: #00997D; font-size: 8.5pt; font-weight: 700; text-align: left; }
    .cover-footer .fr { color: #8a95a5; font-size: 8.5pt; text-align: right; }

    /* =========================================================
     * PÁGINAS DE MÊS
     * ========================================================= */
    .month { page-break-after: always; }
    .month:last-child { page-break-after: auto; }

    /* Cabeçalho do mês em fluxo normal (sem absolute/rotate). */
    table.month-head { width: 100%; border-collapse: collapse; margin: 0 0 6pt; }
    table.month-head td { border: 0; padding: 0; vertical-align: bottom; }
    .m-name { font-size: 22pt; font-weight: 700; color: #1a1a2e; letter-spacing: 0.5pt; }
    .m-year { font-size: 12pt; font-weight: 700; color: #00997D; padding-left: 6pt; }
    .m-brand { text-align: right; font-size: 10pt; font-weight: 700; color: #1a1a2e; }
    .m-brand .soft { color: #00BFA6; font-weight: 400; }

    /* Cabeçalho dos dias da semana (faixa teal). */
    table.weekhead { width: 100%; border-collapse: collapse; table-layout: fixed; }
    table.weekhead th {
        width: 14.285%; background: #00BFA6; color: #ffffff;
        font-size: 8pt; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5pt;
        padding: 5pt 2pt; text-align: center; border: 0.75pt solid #ffffff;
    }

    /* Uma semana não se parte entre páginas. */
    .week { page-break-inside: avoid; border: 0.75pt dashed #cfd6df; border-top: 0; }

    table.daynums { width: 100%; border-collapse: collapse; table-layout: fixed; }
    table.daynums td {
        width: 14.285%; vertical-align: top; padding: 3pt 5pt 1pt;
        border-right: 0.5pt dashed #e2e7ee; background: #ffffff;
    }
    table.daynums td:last-child { border-right: 0; }
    td.other-month { background: #f7f9fb; }
    td.today-cell { background: #e6fbf7; }
    .day-num { font-size: 8.5pt; font-weight: 700; color: #1a1a2e; display: block; }
    td.other-month .day-num { color: #c7cfda; font-weight: 400; }
    td.today-cell .day-num { color: #00997D; }

    table.lane { width: 100%; border-collapse: collapse; table-layout: fixed; }
    table.lane td.lane-cell { width: 14.285%; vertical-align: top; padding: 0 3pt 2.5pt; border: 0; }

    .bar { padding: 2.5pt 6pt; font-size: 6.9pt; line-height: 1.3; }
    /* Título em UMA linha, truncado com reticências quando não cabe. */
    .bar-title {
        display: block;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        font-weight: 700; text-transform: uppercase; letter-spacing: 0.2pt;
    }

    .week-pad { height: 3pt; font-size: 0; line-height: 0; }

    .empty { color: #94a3b8; font-style: italic; padding: 16pt; background: #f8fafc; border: 1pt solid #e8ebf0; border-radius: 8pt; text-align: center; }
</style>
</head>
<body>

<!-- ===================== CAPA ===================== -->
<div class="cover">
    <div class="cover-band"><div class="brand">ON Solutions <span class="soft">&middot; Helpdesk</span></div></div>
    <div class="cover-accent"></div>

    <div class="cover-title-wrap">
        <div class="cover-title">CRONOGRAMA</div>
        <div class="cover-rule"></div>
    </div>

    <div class="cover-info">
        <div class="company"><?= $esc($viewCompanyLabel) ?></div>
        <?php if (trim((string) $viewPeriodLabel) !== ''): ?>
            <div class="period">Período: <?= $esc($viewPeriodLabel) ?></div>
        <?php endif; ?>
        <div class="period"><?= (int) $viewTotal ?> demanda<?= $viewTotal === 1 ? '' : 's' ?> no período</div>
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
            <td class="fl">Sistema de Planejamento &mdash; ON Solutions Helpdesk</td>
            <td class="fr">Gerado em <?= $esc($viewGeneratedAt) ?></td>
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
            <table class="month-head">
                <tr>
                    <td><span class="m-name"><?= $esc($mName) ?></span><span class="m-year"><?= $esc($mYear) ?></span></td>
                    <td class="m-brand">ON <span class="soft">Solutions</span></td>
                </tr>
            </table>

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

</body>
</html>
