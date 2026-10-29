<?php
/**
 * Template HTML (documento completo) do RELATÓRIO DIÁRIO CONSOLIDADO em PDF.
 *
 * ESTRUTURA (identidade do helpdeskON — marinho/teal):
 *   - CAPA: faixa de marca, título, período, total de RDOs, data de geração.
 *   - RESUMO EXECUTIVO: texto gerado por IA a partir SOMENTE dos dados reais
 *     (ou aviso discreto quando a IA está indisponível).
 *   - DETALHAMENTO DAS ATIVIDADES: um bloco por RDO, com os campos preenchidos.
 *   - REGISTROS FOTOGRÁFICOS: grade com as imagens anexadas a cada RDO.
 *
 * Gerado por RdoController::exportPdf() via PdfGenerator::fromDocument (dompdf,
 * A4 retrato, chroot = PUBLIC_PATH para embutir as imagens de uploads/rdo).
 *
 * Variáveis esperadas:
 *   $viewTitle, $viewReports, $viewPeriodLabel, $viewTotal, $viewGeneratedAt,
 *   $viewAiSummary, $viewAiError
 */
if (!isset($viewTitle))       { $viewTitle = 'RELATÓRIO DIÁRIO CONSOLIDADO'; }
if (!isset($viewReports))     { $viewReports = []; }
if (!isset($viewPeriodLabel)) { $viewPeriodLabel = ''; }
if (!isset($viewTotal))       { $viewTotal = 0; }
if (!isset($viewGeneratedAt)) { $viewGeneratedAt = date('d/m/Y H:i'); }
if (!isset($viewAiSummary))   { $viewAiSummary = ''; }
if (!isset($viewAiError))     { $viewAiError = ''; }

$esc = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };

// Converte texto plano (parágrafos separados por linha em branco) em <p>.
$paras = function (string $text) use ($esc): string {
    $text = trim($text);
    if ($text === '') return '';
    $chunks = preg_split('/\n\s*\n/', $text);
    $html = '';
    foreach ($chunks as $c) {
        $c = trim($c);
        if ($c === '') continue;
        $html .= '<p>' . nl2br($esc($c)) . '</p>';
    }
    return $html;
};

// Data Y-m-d -> "quinta-feira, 8 de outubro de 2026".
$dateLong = function (string $ymd) use ($esc): string {
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $ymd, $m)) {
        return $esc($ymd);
    }
    $weekdays = ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'];
    $months = ['', 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
    $ts = mktime(0, 0, 0, (int) $m[2], (int) $m[3], (int) $m[1]);
    $wd = $weekdays[(int) date('w', $ts)] ?? '';
    return $esc(ucfirst($wd) . ', ' . (int) $m[3] . ' de ' . $months[(int) $m[2]] . ' de ' . $m[1]);
};

$statusLabels = ['em_andamento' => 'Em andamento', 'finalizado' => 'Finalizado'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 1.6cm 1.6cm; }
    * { box-sizing: border-box; }
    body { font-family: "DejaVu Sans", sans-serif; color: #243041; font-size: 10pt; margin: 0; line-height: 1.5; }

    /* ===================== CAPA ===================== */
    .cover { page-break-after: always; }
    .cover-band { background: #1a1a2e; height: 1.2cm; }
    .cover-band .brand { color: #fff; font-size: 12pt; font-weight: 700; letter-spacing: 0.5pt; padding: 9pt 4pt 0 2pt; text-align: right; }
    .cover-band .brand .soft { color: #00BFA6; font-weight: 400; }
    .cover-accent { height: 0.14cm; background: #00BFA6; }

    .cover-title-wrap { margin: 4cm 0 0; }
    .cover-title { font-size: 34pt; font-weight: 700; color: #1a1a2e; letter-spacing: 1pt; margin: 0; line-height: 1.05; }
    .cover-rule { width: 6.4cm; height: 0.16cm; background: #00BFA6; margin: 16pt 0 0; border-radius: 3pt; }

    .cover-info { margin: 2.6cm 0 0; }
    .cover-info .period { font-size: 13pt; color: #1a1a2e; font-weight: 700; margin: 0; }
    .cover-info .count  { font-size: 11pt; color: #4a5a6a; margin: 8pt 0 0; }

    .cover-footer { position: fixed; bottom: 0; left: 0; right: 0; }
    .cover-footer-inner { border-top: 0.75pt solid #e4e8ee; padding-top: 7pt; }
    .cover-footer td { border: 0; padding: 0; }
    .cover-footer .fl { color: #00997D; font-size: 8.5pt; font-weight: 700; text-align: left; }
    .cover-footer .fr { color: #8a95a5; font-size: 8.5pt; text-align: right; }

    /* ===================== SEÇÕES ===================== */
    .section { page-break-before: always; }
    .section-head { background: #1a1a2e; color: #fff; padding: 8pt 12pt; font-size: 13pt; font-weight: 700; letter-spacing: 0.5pt; text-transform: uppercase; border-left: 5pt solid #00BFA6; }
    .section-body { padding: 12pt 2pt 0; }

    .muted-note { color: #8a95a5; font-style: italic; font-size: 9pt; margin: 4pt 0 0; }

    /* Detalhamento por RDO */
    .rdo { page-break-inside: avoid; margin: 0 0 16pt; padding: 0 0 12pt; border-bottom: 0.75pt solid #e4e8ee; }
    .rdo:last-child { border-bottom: 0; }
    .rdo-date { font-size: 11pt; font-weight: 700; color: #1a1a2e; margin: 0 0 2pt; }
    .rdo-company { font-size: 9.5pt; color: #00997D; font-weight: 700; margin: 0 0 6pt; }
    .rdo-status { display: inline-block; font-size: 7.5pt; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3pt; padding: 1.5pt 6pt; border-radius: 10pt; color: #fff; }
    .rdo-status.done { background: #16a34a; }
    .rdo-status.wip  { background: #d97706; }
    .field { margin: 6pt 0 0; }
    .field .lbl { font-size: 8pt; font-weight: 700; color: #6b7686; text-transform: uppercase; letter-spacing: 0.3pt; }
    .field p { margin: 1pt 0 0; }
    .field.occ p { background: #fff7ed; border-left: 3pt solid #ea580c; padding: 5pt 8pt; }
    .collabs { margin: 6pt 0 0; font-size: 9pt; }

    /* Fotos */
    .photos { margin: 8pt 0 0; }
    .photo-group { page-break-inside: avoid; margin: 0 0 14pt; }
    .photo-group-head { font-size: 9.5pt; font-weight: 700; color: #1a1a2e; margin: 0 0 6pt; }
    table.photo-grid { width: 100%; border-collapse: collapse; table-layout: fixed; }
    table.photo-grid td { width: 50%; vertical-align: top; padding: 4pt; text-align: center; }
    table.photo-grid img { max-width: 100%; max-height: 7cm; border: 0.75pt solid #e4e8ee; }

    .empty { color: #94a3b8; font-style: italic; padding: 16pt; background: #f8fafc; border: 1pt solid #e8ebf0; border-radius: 8pt; text-align: center; }
</style>
</head>
<body>

<!-- ===================== CAPA ===================== -->
<div class="cover">
    <div class="cover-band"><div class="brand">ON Solutions <span class="soft">&middot; Helpdesk</span></div></div>
    <div class="cover-accent"></div>

    <div class="cover-title-wrap">
        <div class="cover-title"><?= $esc($viewTitle) ?></div>
        <div class="cover-rule"></div>
    </div>

    <div class="cover-info">
        <?php if (trim((string) $viewPeriodLabel) !== ''): ?>
            <div class="period">Período: <?= $esc($viewPeriodLabel) ?></div>
        <?php endif; ?>
        <div class="count"><?= (int) $viewTotal ?> relatório<?= (int) $viewTotal === 1 ? '' : 's' ?> diário<?= (int) $viewTotal === 1 ? '' : 's' ?> no período</div>
    </div>

    <div class="cover-footer"><div class="cover-footer-inner">
        <table style="width:100%;border-collapse:collapse;"><tr>
            <td class="fl">Relatório Diário &mdash; ON Solutions Helpdesk</td>
            <td class="fr">Gerado em <?= $esc($viewGeneratedAt) ?></td>
        </tr></table>
    </div></div>
</div>

<?php if (empty($viewReports)): ?>
    <!-- Nenhum RDO no período -->
    <div class="section">
        <div class="section-head">Relatório consolidado</div>
        <div class="section-body">
            <p class="empty">Nenhum relatório diário encontrado no período selecionado.</p>
        </div>
    </div>
<?php else: ?>

    <!-- ===================== RESUMO EXECUTIVO ===================== -->
    <div class="section">
        <div class="section-head">Resumo Executivo</div>
        <div class="section-body">
            <?php if (trim((string) $viewAiSummary) !== ''): ?>
                <?= $paras($viewAiSummary) ?>
            <?php else: ?>
                <p class="muted-note"><?= $esc($viewAiError !== '' ? $viewAiError : 'Resumo executivo automático não disponível.') ?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- ===================== DETALHAMENTO DAS ATIVIDADES ===================== -->
    <div class="section">
        <div class="section-head">Detalhamento das Atividades</div>
        <div class="section-body">
            <?php foreach ($viewReports as $r): ?>
                <?php
                    $status = (string) ($r['status'] ?? 'em_andamento');
                    $stLabel = $statusLabels[$status] ?? $status;
                    $stClass = $status === 'finalizado' ? 'done' : 'wip';
                    $company = trim((string) ($r['company_name'] ?? ''));
                ?>
                <div class="rdo">
                    <div class="rdo-date"><?= $dateLong((string) ($r['report_date'] ?? '')) ?></div>
                    <?php if ($company !== ''): ?>
                        <div class="rdo-company"><?= $esc($company) ?></div>
                    <?php endif; ?>
                    <span class="rdo-status <?= $stClass ?>"><?= $esc($stLabel) ?></span>

                    <?php if (trim((string) ($r['title'] ?? '')) !== ''): ?>
                        <div class="field"><div class="lbl">Título / resumo</div><p><?= $esc($r['title']) ?></p></div>
                    <?php endif; ?>
                    <?php if (trim((string) ($r['activities'] ?? '')) !== ''): ?>
                        <div class="field"><div class="lbl">Atividades realizadas</div><p><?= nl2br($esc($r['activities'])) ?></p></div>
                    <?php endif; ?>
                    <?php if (trim((string) ($r['occurrences'] ?? '')) !== ''): ?>
                        <div class="field occ"><div class="lbl">Impedimentos / ocorrências</div><p><?= nl2br($esc($r['occurrences'])) ?></p></div>
                    <?php endif; ?>
                    <?php if (trim((string) ($r['pending_tasks'] ?? '')) !== ''): ?>
                        <div class="field"><div class="lbl">Pendências / em andamento</div><p><?= nl2br($esc($r['pending_tasks'])) ?></p></div>
                    <?php endif; ?>
                    <?php if (trim((string) ($r['next_day_plan'] ?? '')) !== ''): ?>
                        <div class="field"><div class="lbl">Plano para o próximo dia</div><p><?= nl2br($esc($r['next_day_plan'])) ?></p></div>
                    <?php endif; ?>

                    <?php $collabs = $r['collaborators'] ?? []; ?>
                    <?php if (is_array($collabs) && $collabs): ?>
                        <?php
                            $names = [];
                            foreach ($collabs as $c) {
                                $n = trim((string) ($c['collaborator_name'] ?? ''));
                                if ($n !== '') $names[] = $esc($n);
                            }
                        ?>
                        <?php if ($names): ?>
                            <div class="collabs"><span class="lbl" style="font-weight:700;color:#6b7686;">Colaboradores/Prestadores:</span> <?= implode(', ', $names) ?></div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php $photos = $r['photos'] ?? []; ?>
                    <?php if (is_array($photos) && $photos): ?>
                        <div class="field"><div class="lbl">Registros fotográficos (<?= count($photos) ?>)</div></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ===================== REGISTROS FOTOGRÁFICOS ===================== -->
    <?php
        $hasPhotos = false;
        foreach ($viewReports as $r) {
            if (!empty($r['photos'])) { $hasPhotos = true; break; }
        }
    ?>
    <?php if ($hasPhotos): ?>
    <div class="section">
        <div class="section-head">Registros Fotográficos</div>
        <div class="section-body photos">
            <?php foreach ($viewReports as $r): ?>
                <?php $photos = $r['photos'] ?? []; ?>
                <?php if (!is_array($photos) || !$photos) continue; ?>
                <div class="photo-group">
                    <div class="photo-group-head">
                        <?= $dateLong((string) ($r['report_date'] ?? '')) ?>
                        <?php if (trim((string) ($r['company_name'] ?? '')) !== ''): ?>
                            &mdash; <?= $esc($r['company_name']) ?>
                        <?php endif; ?>
                    </div>
                    <table class="photo-grid">
                        <?php
                            $cols = 2;
                            for ($i = 0; $i < count($photos); $i += $cols):
                        ?>
                        <tr>
                            <?php for ($j = 0; $j < $cols; $j++): ?>
                                <?php $p = $photos[$i + $j] ?? null; ?>
                                <td>
                                    <?php if ($p && !empty($p['pdf_src'])): ?>
                                        <img src="<?= $esc($p['pdf_src']) ?>" alt="">
                                    <?php endif; ?>
                                </td>
                            <?php endfor; ?>
                        </tr>
                        <?php endfor; ?>
                    </table>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

<?php endif; ?>

</body>
</html>
