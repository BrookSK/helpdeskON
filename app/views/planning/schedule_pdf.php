<?php
/**
 * Template HTML do cronograma consolidado de atividades (exportação em PDF).
 *
 * Renderizado para string por PlanningController::exportSchedulePdf() e
 * convertido via PdfGenerator (dompdf). Usa apenas CSS inline/simples: o dompdf
 * tem suporte limitado a CSS e recursos externos ficam desabilitados.
 *
 * Variáveis esperadas:
 *   $viewGroups        array de ['company' => string, 'cards' => array]
 *   $viewTotal         int    total de atividades
 *   $viewOnlyActive    bool   se true, só próximas etapas (ativas)
 *   $viewGeneratedAt   string data/hora de geração (d/m/Y H:i)
 *   $viewFiltersLabel  string resumo dos filtros aplicados (pode ser vazio)
 */
if (!isset($viewGroups))       { $viewGroups = []; }
if (!isset($viewTotal))        { $viewTotal = 0; }
if (!isset($viewOnlyActive))   { $viewOnlyActive = true; }
if (!isset($viewGeneratedAt))  { $viewGeneratedAt = date('d/m/Y H:i'); }
if (!isset($viewFiltersLabel)) { $viewFiltersLabel = ''; }

$esc = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
?>
<style>
    body { font-family: "DejaVu Sans", sans-serif; color: #222; font-size: 10pt; }
    h1 { font-size: 16pt; margin: 0 0 2pt; }
    .meta { color: #555; font-size: 9pt; margin: 0 0 2pt; }
    .filters { color: #555; font-size: 9pt; margin: 0 0 12pt; }
    .company { font-size: 12pt; font-weight: bold; color: #1565c0;
               border-bottom: 2px solid #1565c0; padding-bottom: 2pt;
               margin: 14pt 0 6pt; }
    .count { color: #666; font-weight: normal; font-size: 9pt; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 4pt; }
    th { background: #1565c0; color: #fff; text-align: left; font-size: 9pt;
         padding: 4pt 5pt; }
    td { border-bottom: 1px solid #ddd; padding: 4pt 5pt; vertical-align: top;
         font-size: 9pt; }
    tr:nth-child(even) td { background: #f5f7fa; }
    .act-title { font-weight: bold; }
    .act-desc { color: #666; font-size: 8pt; margin-top: 1pt; }
    .st { display: inline-block; padding: 1pt 5pt; border-radius: 8pt;
          color: #fff; font-size: 8pt; }
    .empty { color: #777; font-style: italic; padding: 10pt 0; }
</style>

<h1>Cronograma de Atividades &mdash; Planejamento</h1>
<p class="meta">
    Gerado em <?= $esc($viewGeneratedAt) ?>.
    <?= $viewOnlyActive ? 'Exibindo as próximas etapas (atividades em andamento).' : 'Exibindo todas as atividades.' ?>
    Total: <?= (int) $viewTotal ?> atividade<?= $viewTotal === 1 ? '' : 's' ?>.
</p>
<?php if (trim((string) $viewFiltersLabel) !== ''): ?>
    <p class="filters">Filtros aplicados &mdash; <?= $esc($viewFiltersLabel) ?></p>
<?php endif; ?>

<?php if (empty($viewGroups)): ?>
    <p class="empty">Nenhuma atividade encontrada para os filtros selecionados.</p>
<?php else: ?>
    <?php foreach ($viewGroups as $group): ?>
        <div class="company">
            <?= $esc($group['company']) ?>
            <span class="count">(<?= count($group['cards']) ?>)</span>
        </div>
        <table>
            <thead>
                <tr>
                    <th style="width:34%;">Atividade</th>
                    <th style="width:10%;">Início</th>
                    <th style="width:10%;">Término</th>
                    <th style="width:10%;">Prazo</th>
                    <th style="width:14%;">Status</th>
                    <th style="width:11%;">Responsável</th>
                    <th style="width:11%;">Solicitante</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($group['cards'] as $card): ?>
                    <?php
                    $statusColor = PlanningScheduleRules::statusColor($card['status'] ?? '');
                    $desc = PlanningScheduleRules::plainText($card['description'] ?? '', 180);
                    ?>
                    <tr>
                        <td>
                            <div class="act-title"><?= $esc($card['title'] ?? '') ?></div>
                            <?php if ($desc !== ''): ?>
                                <div class="act-desc"><?= $esc($desc) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= $esc(PlanningScheduleRules::formatDate($card['start_date'] ?? null)) ?></td>
                        <td><?= $esc(PlanningScheduleRules::formatDate($card['end_date'] ?? null)) ?></td>
                        <td><?= $esc(PlanningScheduleRules::formatDate($card['due_date'] ?? null)) ?></td>
                        <td><span class="st" style="background:<?= $esc($statusColor) ?>;"><?= $esc(PlanningScheduleRules::statusLabel($card['status'] ?? '')) ?></span></td>
                        <td><?= $esc($card['assigned_name'] ?? '—') ?></td>
                        <td><?= $esc($card['created_by_name'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endforeach; ?>
<?php endif; ?>
