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
 * Cada mês fica num container com page-break-after para sair em página própria.
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
?>
<style>
    @page { margin: 1.1cm 1cm; }
    body { font-family: "DejaVu Sans", sans-serif; color: #2b2b3a; font-size: 9pt; }

    /* Cabeçalho do documento — faixa com a cor primária do site. */
    .doc-header {
        background: #00BFA6;
        color: #ffffff;
        padding: 10pt 12pt;
        border-radius: 7pt;
        margin-bottom: 8pt;
    }
    .doc-header h1 { font-size: 14pt; margin: 0; font-weight: 700; }
    .doc-header .meta { color: #e6fffb; font-size: 8pt; margin: 3pt 0 0; }
    .doc-header .filters { color: #e6fffb; font-size: 8pt; margin: 2pt 0 0; }

    /* Legenda de cores (mesma leitura do calendário da tela). */
    .legend { margin: 0 0 8pt; font-size: 7.5pt; color: #5a5a6a; }
    .legend .item { display: inline-block; margin: 0 8pt 2pt 0; white-space: nowrap; }
    .legend .swatch {
        display: inline-block; width: 9pt; height: 9pt;
        border-radius: 2pt; vertical-align: middle; margin-right: 3pt;
    }

    /* Cada mês ocupa a sua própria página. */
    .month { page-break-after: always; }
    .month:last-child { page-break-after: auto; }
    .month-title {
        font-size: 12pt; font-weight: 700; color: #00997D;
        margin: 0 0 5pt; padding: 0 0 3pt;
        border-bottom: 2pt solid #00BFA6;
    }

    table.cal { width: 100%; border-collapse: collapse; table-layout: fixed; }
    table.cal th {
        background: #00997D; color: #ffffff;
        font-size: 8pt; font-weight: 700; text-transform: uppercase;
        padding: 4pt 2pt; text-align: center; border: 1pt solid #e2e5ec;
        width: 14.28%;
    }
    table.cal td {
        border: 1pt solid #e2e5ec; vertical-align: top;
        height: 92pt; padding: 2pt; width: 14.28%;
    }
    td.other-month { background: #f3f5f8; }
    td.today { background: #fff8e1; }

    .day-num { font-size: 8pt; font-weight: 700; color: #5a5a6a; display: block; margin-bottom: 2pt; }
    td.other-month .day-num { color: #b5bcc6; }

    /* Pílula de demanda dentro do dia: fundo/borda/texto da categoria. */
    .ev {
        display: block;
        font-size: 6.8pt;
        line-height: 1.15;
        padding: 1.5pt 3pt;
        margin: 0 0 2pt;
        border-radius: 3pt;
        border-left: 2.5pt solid #94a3b8;
        word-wrap: break-word;
        overflow-wrap: break-word;
    }

    .empty {
        color: #8a8a99; font-style: italic;
        padding: 14pt; background: #f8fafc;
        border: 1pt solid #e2e5ec; border-radius: 8pt; text-align: center;
    }
</style>

<div class="doc-header">
    <h1>Cronograma de Atividades &mdash; Planejamento</h1>
    <p class="meta">
        Gerado em <?= $esc($viewGeneratedAt) ?>.
        Total: <?= (int) $viewTotal ?> demanda<?= $viewTotal === 1 ? '' : 's' ?> com data no período.
    </p>
    <?php if (trim((string) $viewFiltersLabel) !== ''): ?>
        <p class="filters"><?= $esc($viewFiltersLabel) ?></p>
    <?php endif; ?>
</div>

<?php if (!empty($viewLegend)): ?>
    <div class="legend">
        <?php foreach ($viewLegend as $cat): ?>
            <span class="item">
                <span class="swatch" style="background:<?= $esc($cat['bg']) ?>;border:1pt solid <?= $esc($cat['border']) ?>;"></span>
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
                                ?>
                                <td<?= $classes ? ' class="' . implode(' ', $classes) . '"' : '' ?>>
                                    <span class="day-num"><?= (int) $cell['day'] ?></span>
                                    <?php foreach ($dayCards as $card): ?>
                                        <?php
                                        $cat = $card['_cat'] ?? ['bg' => '#eef1f4', 'border' => '#94a3b8', 'text' => '#475569'];
                                        $title = trim((string) ($card['title'] ?? ''));
                                        if ($title === '') $title = '(sem título)';
                                        ?>
                                        <span class="ev" style="background:<?= $esc($cat['bg']) ?>;border-left-color:<?= $esc($cat['border']) ?>;color:<?= $esc($cat['text']) ?>;"><?= $esc($title) ?></span>
                                    <?php endforeach; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
