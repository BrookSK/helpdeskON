<?php
/**
 * Template HTML do cronograma consolidado de atividades (exportação em PDF).
 *
 * Renderizado para string por PlanningController::exportSchedulePdf() e
 * convertido via PdfGenerator (dompdf). Usa apenas CSS inline/simples: o dompdf
 * tem suporte limitado a CSS e recursos externos ficam desabilitados.
 *
 * Identidade visual alinhada ao site (app/views/layouts/header.php):
 *   - cor primária teal #00BFA6 / #00997D (faixa do cabeçalho, títulos);
 *   - fundo claro #f5f7fa, cartões com cantos arredondados e borda suave;
 *   - tabela com cabeçalho em maiúsculas/cinza e zebra leve;
 *   - badges de status como pílula de fundo claro + texto colorido
 *     (PlanningScheduleRules::statusBadge, espelhando as classes .badge-*).
 *   - tipografia: o site usa Inter; o dompdf não a embarca, então usamos
 *     DejaVu Sans (fallback com acentuação) preservando pesos/tamanhos.
 *
 * Quebra de página: cada bloco "empresa + tabela" fica em um container com
 * page-break-inside: avoid, então uma empresa que não caiba no resto da página
 * é deslocada inteira para a próxima. Se uma única empresa tiver linhas demais
 * para caber numa página, o cabeçalho da tabela repete (table-header-group).
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
    @page { margin: 1.4cm 1.2cm; }
    body { font-family: "DejaVu Sans", sans-serif; color: #2b2b3a; font-size: 9.5pt; }

    /* Cabeçalho do documento — faixa com a cor primária do site. */
    .doc-header {
        background: #00BFA6;
        color: #ffffff;
        padding: 12pt 14pt;
        border-radius: 8pt;
        margin-bottom: 12pt;
    }
    .doc-header h1 { font-size: 15pt; margin: 0; font-weight: 700; letter-spacing: 0.2pt; }
    .doc-header .meta { color: #e6fffb; font-size: 8.5pt; margin: 4pt 0 0; }
    .doc-header .filters { color: #e6fffb; font-size: 8.5pt; margin: 3pt 0 0; }

    /*
     * Bloco de cada empresa. NÃO usamos page-break-inside: avoid aqui de
     * propósito: uma empresa grande (muitas linhas) poderia não caber no espaço
     * restante da página e seria empurrada inteira para a próxima, deixando um
     * vazio grande. Em vez disso, aproveitamos o espaço disponível e deixamos a
     * TABELA quebrar entre páginas quando necessário — protegendo cada linha
     * individualmente (tr) e o cabeçalho da empresa (não fica órfão no rodapé).
     */
    .group { margin-bottom: 12pt; }

    .company {
        font-size: 11pt;
        font-weight: 700;
        color: #00997D;
        padding: 0 0 4pt;
        margin: 0 0 5pt;
        border-bottom: 2pt solid #00BFA6;
        /* O título nunca fica sozinho no fim da página: gruda na tabela. */
        page-break-after: avoid;
    }
    .company .count { color: #8a8a99; font-weight: 400; font-size: 8.5pt; }

    table { width: 100%; border-collapse: collapse; }
    /* Repete o cabeçalho da tabela no topo da continuação quando ela quebra. */
    thead { display: table-header-group; }
    /* Nenhuma atividade é cortada ao meio entre páginas. */
    tr { page-break-inside: avoid; }
    th {
        background: #f0f2f6;
        color: #5a5a6a;
        text-align: left;
        font-size: 7.5pt;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.3pt;
        padding: 5pt 6pt;
        border-bottom: 1pt solid #e2e5ec;
    }
    td {
        padding: 5pt 6pt;
        vertical-align: top;
        font-size: 8.5pt;
        border-bottom: 1pt solid #eceef3;
    }
    tr:nth-child(even) td { background: #f8fafc; }

    .act-title { font-weight: 700; color: #2b2b3a; }
    .act-desc { color: #8a8a99; font-size: 7.5pt; margin-top: 1.5pt; }

    /* Badge de status: pílula clara com texto colorido (igual ao site). */
    .st {
        display: inline-block;
        padding: 2pt 7pt;
        border-radius: 10pt;
        font-size: 7.5pt;
        font-weight: 700;
        white-space: nowrap;
    }

    .muted { color: #a0a0ac; }
    .empty {
        color: #8a8a99;
        font-style: italic;
        padding: 14pt;
        background: #f8fafc;
        border: 1pt solid #e2e5ec;
        border-radius: 8pt;
        text-align: center;
    }
</style>

<div class="doc-header">
    <h1>Cronograma de Atividades &mdash; Planejamento</h1>
    <p class="meta">
        Gerado em <?= $esc($viewGeneratedAt) ?>.
        <?= $viewOnlyActive ? 'Exibindo as próximas etapas (atividades em andamento).' : 'Exibindo todas as atividades.' ?>
        Total: <?= (int) $viewTotal ?> atividade<?= $viewTotal === 1 ? '' : 's' ?>.
    </p>
    <?php if (trim((string) $viewFiltersLabel) !== ''): ?>
        <p class="filters">Filtros aplicados &mdash; <?= $esc($viewFiltersLabel) ?></p>
    <?php endif; ?>
</div>

<?php if (empty($viewGroups)): ?>
    <p class="empty">Nenhuma atividade encontrada para os filtros selecionados.</p>
<?php else: ?>
    <?php foreach ($viewGroups as $group): ?>
        <div class="group">
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
                        $badge = PlanningScheduleRules::statusBadge($card['status'] ?? '');
                        $desc = PlanningScheduleRules::plainText($card['description'] ?? '', 180);
                        $assigned = trim((string) ($card['assigned_name'] ?? ''));
                        $requester = trim((string) ($card['created_by_name'] ?? ''));
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
                            <td><span class="st" style="background:<?= $esc($badge['bg']) ?>;color:<?= $esc($badge['fg']) ?>;"><?= $esc(PlanningScheduleRules::statusLabel($card['status'] ?? '')) ?></span></td>
                            <td<?= $assigned === '' ? ' class="muted"' : '' ?>><?= $assigned !== '' ? $esc($assigned) : '—' ?></td>
                            <td<?= $requester === '' ? ' class="muted"' : '' ?>><?= $requester !== '' ? $esc($requester) : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
