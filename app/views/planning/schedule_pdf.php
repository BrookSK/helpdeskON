<?php
/**
 * Template HTML do cronograma do Planejamento exportado em PDF, no formato de
 * CALENDÁRIO MENSAL VISUAL (uma página por mês), fiel ao calendário da tela
 * (app/views/planning/index.php).
 *
 * Renderizado para string por PlanningController::exportSchedulePdf() e
 * convertido via PdfGenerator::fromHtml (dompdf, A4 paisagem). O dompdf tem
 * suporte limitado a CSS (sem flex/grid confiável), então a grade do mês é uma
 * <table> de 7 colunas (domingo→sábado); cada demanda do dia é uma "pílula"
 * colorida com o título, usando a mesma paleta de prazo/status do calendário
 * (PlanningScheduleRules::DEADLINE_COLORS / deadlineCategory).
 *
 * UM MÊS = UMA PÁGINA. Cada mês é um bloco com page-break-inside: avoid e
 * page-break-after: always, e a altura das células é dimensionada para que as
 * 6 semanas + o cabeçalho caibam no espaço útil de uma folha A4 paisagem. O
 * cabeçalho do documento e a legenda aparecem só uma vez (topo da 1ª página).
 *
 * Variáveis esperadas:
 *   $viewMonths       array de meses, cada um:
 *                       ['ym' => 'Y-m', 'label' => 'Outubro 2026',
 *                        'weeks' => [[['date','day','otherMonth'], x7], x6],
 *                        'byDay' => ['Y-m-d' => [card + '_cat'], ...]]
 *   $viewWeekdays     array rótulos das colunas ['Dom'..'Sáb']
 *   $viewLegend       array DEADLINE_COLORS (para a legenda de cores)
 *   $viewTotal        int    total de demandas com data no período
 *   $viewGeneratedAt  string data/hora de geração (d/m/Y H:i)
 *   $viewFiltersLabel string resumo dos filtros aplicados (pode ser vazio)
 */
if (!isset($viewMonths))       { $viewMonths = []; }
if (!isset($viewWeekdays))     { $viewWeekdays = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb']; }
if (!isset($viewLegend))       { $viewLegend = []; }
if (!isset($viewTotal))        { $viewTotal = 0; }
if (!isset($viewGeneratedAt))  { $viewGeneratedAt = date('d/m/Y H:i'); }
if (!isset($viewFiltersLabel)) { $viewFiltersLabel = ''; }

$esc = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };

// Limite de demandas visíveis por dia, para a célula não crescer sem controle
// (o que estouraria a altura da página). O excedente vira um "+N" discreto.
$maxPerDay = 3;
?>
<style>
    /* Margem enxuta para o calendário respirar na folha A4 paisagem. */
    @page { margin: 0.8cm 0.9cm; }
    body { font-family: "DejaVu Sans", sans-serif; color: #243041; font-size: 9pt; }

    /* ---- Cabeçalho do documento: compacto, moderno, em uma linha. ---- */
    .doc-header {
        border-left: 4pt solid #00BFA6;
        padding: 0 0 6pt 8pt;
        margin: 0 0 7pt;
    }
    .doc-header h1 {
        font-size: 11pt; margin: 0; font-weight: 700;
        color: #0a7d6b; letter-spacing: 0.2pt;
    }
    .doc-header .meta { color: #8a95a5; font-size: 7.5pt; margin: 2pt 0 0; }
    .doc-header .filters { color: #0a7d6b; font-size: 7.5pt; margin: 1pt 0 0; font-weight: 700; }

    /* ---- Legenda de cores (uma vez, topo da 1ª página). ---- */
    .legend { margin: 0 0 8pt; font-size: 7pt; color: #6b7686; }
    .legend .item { display: inline-block; margin: 0 9pt 2pt 0; white-space: nowrap; }
    .legend .swatch {
        display: inline-block; width: 8pt; height: 8pt;
        border-radius: 2pt; vertical-align: middle; margin-right: 3pt;
    }

    /* ---- Cada mês ocupa a sua própria página (sem cortar). ---- */
    .month { page-break-inside: avoid; page-break-after: always; }
    .month:last-child { page-break-after: auto; }
    .month-title {
        font-size: 12.5pt; font-weight: 700; color: #0a7d6b;
        margin: 0 0 5pt; padding: 0;
    }

    /* ---- Grade do calendário. ----
     * border-collapse: collapse (sem border-spacing) para a altura total ser
     * previsível e caber numa folha. A altura das linhas é fixa e calibrada
     * para 6 semanas + cabeçalhos entrarem numa página A4 paisagem. */
    table.cal { width: 100%; border-collapse: collapse; table-layout: fixed; }
    table.cal th {
        background: #00BFA6; color: #ffffff;
        font-size: 7.5pt; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.4pt;
        padding: 3pt 2pt; text-align: center;
        border: 1pt solid #ffffff; width: 14.28%;
    }
    table.cal td {
        background: #ffffff;
        border: 0.75pt solid #e4e8ee;
        vertical-align: top; height: 60pt; padding: 2.5pt; width: 14.28%;
        overflow: hidden;
    }
    /* Dias de outro mês: apagados, para o mês corrente saltar aos olhos. */
    td.other-month { background: #f5f7fa; border-color: #eef1f5; }
    /* Hoje: leve destaque teal, mantendo os tons do site. */
    td.today { background: #e6fbf7; border-color: #00BFA6; }

    .day-num {
        font-size: 8.5pt; font-weight: 700; color: #3b4758;
        display: block; margin-bottom: 2pt; text-align: right;
    }
    td.other-month .day-num { color: #c2c9d3; font-weight: 400; }
    td.today .day-num { color: #0a7d6b; }

    /* Pílula de demanda: barra lateral colorida (categoria) + título. */
    .ev {
        display: block;
        font-size: 6.6pt;
        line-height: 1.2;
        padding: 1.5pt 4pt;
        margin: 0 0 2pt;
        border-radius: 3pt;
        border-left: 2.5pt solid #94a3b8;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .ev-more { font-size: 6.4pt; color: #8a95a5; font-weight: 700; padding: 0 2pt; }

    .empty {
        color: #8a8a99; font-style: italic;
        padding: 14pt; background: #f8fafc;
        border: 1pt solid #e2e5ec; border-radius: 8pt; text-align: center;
    }
</style>

<div class="doc-header">
    <h1>Cronograma &mdash; Planejamento</h1>
    <p class="meta">
        Gerado em <?= $esc($viewGeneratedAt) ?> &middot;
        <?= (int) $viewTotal ?> demanda<?= $viewTotal === 1 ? '' : 's' ?> com data no período.
    </p>
    <?php if (trim((string) $viewFiltersLabel) !== ''): ?>
        <p class="filters"><?= $esc($viewFiltersLabel) ?></p>
    <?php endif; ?>
</div>

<?php if (!empty($viewLegend)): ?>
    <div class="legend">
        <?php foreach ($viewLegend as $cat): ?>
            <span class="item">
                <span class="swatch" style="background:<?= $esc($cat['bg']) ?>;border:0.75pt solid <?= $esc($cat['border']) ?>;"></span>
                <?= $esc($cat['label']) ?>
            </span>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (empty($viewMonths)): ?>
    <p class="empty">Nenhuma demanda com data encontrada para os filtros selecionados.</p>
<?php else: ?>
    <?php $todayKey = date('Y-m-d'); ?>
    <?php foreach ($viewMonths as $month): ?>
        <div class="month">
            <div class="month-title"><?= $esc($month['label']) ?></div>
            <table class="cal">
                <thead>
                    <tr>
                        <?php foreach ($viewWeekdays as $wd): ?>
                            <th><?= $esc($wd) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($month['weeks'] as $week): ?>
                        <tr>
                            <?php foreach ($week as $cell): ?>
                                <?php
                                $classes = [];
                                if (!empty($cell['otherMonth'])) $classes[] = 'other-month';
                                if ($cell['date'] === $todayKey)  $classes[] = 'today';
                                $dayCards = $month['byDay'][$cell['date']] ?? [];
                                $shown = array_slice($dayCards, 0, $maxPerDay);
                                $extra = count($dayCards) - count($shown);
                                ?>
                                <td<?= $classes ? ' class="' . implode(' ', $classes) . '"' : '' ?>>
                                    <span class="day-num"><?= (int) $cell['day'] ?></span>
                                    <?php foreach ($shown as $card): ?>
                                        <?php
                                        $cat = $card['_cat'] ?? ['bg' => '#eef1f4', 'border' => '#94a3b8', 'text' => '#475569'];
                                        $title = trim((string) ($card['title'] ?? ''));
                                        if ($title === '') $title = '(sem título)';
                                        ?>
                                        <span class="ev" style="background:<?= $esc($cat['bg']) ?>;border-left-color:<?= $esc($cat['border']) ?>;color:<?= $esc($cat['text']) ?>;"><?= $esc($title) ?></span>
                                    <?php endforeach; ?>
                                    <?php if ($extra > 0): ?>
                                        <span class="ev-more">+<?= (int) $extra ?> demanda<?= $extra === 1 ? '' : 's' ?></span>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
