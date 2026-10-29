<?php
/**
 * Template HTML do cronograma do Planejamento exportado em PDF, no formato de
 * CALENDÁRIO MENSAL VISUAL (um mês por página quando couber), fiel ao
 * calendário da tela (app/views/planning/index.php).
 *
 * Renderizado para string por PlanningController::exportSchedulePdf() e
 * convertido via PdfGenerator::fromHtml (dompdf, A4 paisagem).
 *
 * MODELO DE DESENHO (dompdf não tem flex/grid confiável, então tudo é tabela):
 *   - Cada semana é um bloco com:
 *       (a) uma linha de NÚMEROS dos dias (7 colunas, Dom→Sáb);
 *       (b) uma "lane" por faixa de demanda: cada demanda multi-dia vira UMA
 *           faixa contínua via <td colspan> cobrindo todas as colunas do seu
 *           período naquela semana — sem repetir a demanda em cada dia.
 *   - A faixa tem fundo suave na COR ORIGINAL da demanda (categoria de prazo/
 *     status, intocada) e uma marca escura só no INÍCIO real da demanda.
 *   - O título é exibido por inteiro, com quebra automática de linha; a altura
 *     da faixa cresce conforme necessário, sem cortes nem sobreposição.
 *
 * Paleta: elementos GERAIS do calendário usam tons neutros suaves (slate/indigo
 * discreto). As CORES DAS DEMANDAS não são alteradas — vêm de $seg['_cat'].
 *
 * Variáveis esperadas:
 *   $viewMonths  array de meses, cada um:
 *     ['ym','label','weeks'=>[ ['days'=>[['date','day','otherMonth'],x7],
 *                               'segments'=>[...], 'lanes'=>int], ... ]]
 *     Cada segmento: ['card','_cat','colStart','colEnd','isStart','isEnd','kind','lane','id'].
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
$todayKey = date('Y-m-d');

/**
 * Desenha a linha de uma lane: percorre as 7 colunas e emite um <td colspan>
 * para cada segmento da lane (faixa contínua) e <td> vazio nos espaços livres.
 * Segmentos de uma mesma lane nunca se sobrepõem (garantido na alocação).
 */
$renderLane = function (array $laneSegments) use ($esc): string {
    // Indexa segmentos pela coluna inicial.
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

            // Marca escura só no início REAL da demanda (não em continuações).
            $darkEdge = !empty($s['isStart']);
            // Cantos arredondados só nas pontas reais da faixa.
            $brL = !empty($s['isStart']) ? '4pt' : '0';
            $brR = !empty($s['isEnd']) ? '4pt' : '0';

            $html .= '<td class="lane-cell" colspan="' . $span . '">';
            $html .= '<div class="bar" style="'
                . 'background:' . $esc($cat['bg']) . ';'
                . 'color:' . $esc($cat['text']) . ';'
                . 'border-radius:' . $brL . ' ' . $brR . ' ' . $brR . ' ' . $brL . ';'
                . ($darkEdge ? 'border-left:3pt solid ' . $esc($cat['border']) . ';' : 'border-left:3pt solid transparent;')
                . '">'
                . '<span class="bar-title">' . $esc($title) . '</span>'
                . '</div>';
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
    /* Folha A4 paisagem com margens suaves. */
    @page { margin: 0.85cm 0.95cm; }
    body { font-family: "DejaVu Sans", sans-serif; color: #334155; font-size: 9pt; }

    /* ---- Cabeçalho do documento ---- */
    .doc-header { padding: 0 0 8pt; margin: 0 0 6pt; border-bottom: 1pt solid #e8ebf0; }
    .doc-header h1 {
        font-size: 13pt; margin: 0; font-weight: 700;
        color: #1e293b; letter-spacing: 0.2pt;
    }
    .doc-header .meta { color: #94a3b8; font-size: 7.5pt; margin: 3pt 0 0; }
    .doc-header .filters { color: #64748b; font-size: 7.5pt; margin: 2pt 0 0; font-weight: 700; }

    /* ---- Legenda de cores (uma vez, topo da 1ª página) ---- */
    .legend { margin: 6pt 0 10pt; font-size: 7pt; color: #94a3b8; }
    .legend .item { display: inline-block; margin: 0 10pt 3pt 0; white-space: nowrap; }
    .legend .swatch {
        display: inline-block; width: 8pt; height: 8pt;
        border-radius: 2pt; vertical-align: middle; margin-right: 4pt;
    }

    /* ---- Mês: idealmente uma página; se muito cheio, flui sem cortar semana. */
    .month { page-break-after: always; }
    .month:last-child { page-break-after: auto; }
    .month-title {
        font-size: 13pt; font-weight: 700; color: #1e293b;
        margin: 0 0 6pt; padding: 0 0 0 7pt;
        border-left: 3.5pt solid #6366f1;
    }

    /* Cabeçalho dos dias da semana. */
    table.weekhead { width: 100%; border-collapse: collapse; table-layout: fixed; margin-bottom: 3pt; }
    table.weekhead th {
        width: 14.28%; background: #f1f3f7; color: #64748b;
        font-size: 7.5pt; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.5pt; padding: 4pt 2pt; text-align: center;
        border: 1pt solid #ffffff;
    }

    /* Uma semana não se parte entre páginas. */
    .week {
        page-break-inside: avoid;
        border: 0.75pt solid #e8ebf0; border-radius: 6pt;
        margin-bottom: 4pt; padding: 0; overflow: hidden;
    }

    /* Linha dos números dos dias (7 colunas). */
    table.daynums { width: 100%; border-collapse: collapse; table-layout: fixed; }
    table.daynums td {
        width: 14.28%; vertical-align: top;
        padding: 3pt 5pt 1pt; border-right: 0.5pt solid #eef1f5;
        background: #ffffff;
    }
    table.daynums td:last-child { border-right: 0; }
    td.other-month { background: #f7f9fb; }
    td.today-cell { background: #eef1ff; }
    .day-num { font-size: 8.5pt; font-weight: 700; color: #334155; text-align: right; display: block; }
    td.other-month .day-num { color: #c7cfda; font-weight: 400; }
    td.today-cell .day-num { color: #4f46e5; }

    /* Lanes (faixas) — uma tabela de 7 colunas por lane. */
    table.lane { width: 100%; border-collapse: collapse; table-layout: fixed; }
    table.lane td.lane-cell { width: 14.28%; vertical-align: top; padding: 0 2pt 2pt; }

    /* Faixa contínua da demanda: fundo suave na cor original + título integral. */
    .bar {
        padding: 2pt 5pt;
        font-size: 6.8pt;
        line-height: 1.25;
        min-height: 9pt;
    }
    .bar-title {
        display: block;
        word-wrap: break-word;
        overflow-wrap: break-word;
        font-weight: 600;
    }

    /* Espaço mínimo abaixo das lanes para a semana não ficar colada. */
    .week-pad { height: 3pt; font-size: 0; line-height: 0; }

    .empty {
        color: #94a3b8; font-style: italic;
        padding: 16pt; background: #f8fafc;
        border: 1pt solid #e8ebf0; border-radius: 8pt; text-align: center;
    }
</style>

<div class="doc-header">
    <h1>Cronograma de Planejamento</h1>
    <p class="meta">
        Gerado em <?= $esc($viewGeneratedAt) ?> &middot;
        <?= (int) $viewTotal ?> demanda<?= $viewTotal === 1 ? '' : 's' ?> no período
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
    <?php foreach ($viewMonths as $month): ?>
        <div class="month">
            <div class="month-title"><?= $esc($month['label']) ?></div>

            <table class="weekhead">
                <tr>
                    <?php foreach ($viewWeekdays as $wd): ?>
                        <th><?= $esc($wd) ?></th>
                    <?php endforeach; ?>
                </tr>
            </table>

            <?php foreach ($month['weeks'] as $week): ?>
                <div class="week">
                    <!-- Números dos dias -->
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

                    <!-- Faixas (lanes): uma tabela por lane -->
                    <?php
                    // Agrupa os segmentos por lane (0..n), preservando a ordem.
                    $lanes = [];
                    foreach ($week['segments'] as $s) {
                        $lanes[(int) $s['lane']][] = $s;
                    }
                    ksort($lanes);
                    ?>
                    <?php foreach ($lanes as $laneSegs): ?>
                        <table class="lane">
                            <tr><?= $renderLane($laneSegs) ?></tr>
                        </table>
                    <?php endforeach; ?>

                    <div class="week-pad">&nbsp;</div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
