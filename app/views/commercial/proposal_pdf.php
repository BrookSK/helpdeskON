<?php
// Documento HTML convertido em PDF pelo dompdf (A4 horizontal): CSS 2.1, tabelas
// no lugar de flex/grid e imagens/fontes por caminho local (dentro do chroot).
// Segue docs/proposta-modelo.pdf. As páginas 1–5 são as imagens do próprio modelo;
// cabeçalho, rodapé e "Pág. N" são desenhados pelo ProposalPdf.
$e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$brl = function ($v) {
    $v = (float)$v;
    $dec = abs($v - round($v)) > 0.004 ? 2 : 0;
    return 'R$ ' . number_format($v, $dec, ',', '.');
};
$num = function ($v) {
    if ($v === null || $v === '') return '-';
    return rtrim(rtrim(number_format((float)$v, 2, ',', '.'), '0'), ',');
};
$brDate = function ($d) {
    if (!$d) return '';
    $t = strtotime((string)$d);
    return $t ? date('d/m/Y', $t) : '';
};
$monthYear = function ($d) {
    $t = $d ? strtotime((string)$d) : time();
    if (!$t) $t = time();
    $meses = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
    return ucfirst($meses[(int)date('n', $t) - 1]) . ' de ' . date('Y', $t);
};
$lines = function ($text) {
    $out = [];
    foreach (preg_split('/\r?\n/', trim((string)$text)) as $line) {
        $line = trim((string)preg_replace('/^[-•*]\s*/u', '', trim($line)));
        if ($line !== '') $out[] = $line;
    }
    return $out;
};
$paras = function ($text) {
    $out = [];
    foreach (preg_split('/\r?\n\s*\r?\n/', trim((string)$text)) as $p) {
        if (trim($p) !== '') $out[] = trim($p);
    }
    return $out;
};
/** Parágrafos soltos e seções "A Título" seguidas de uma linha por item. */
$lettered = function (string $body) use ($lines) {
    $out = [];
    foreach (preg_split('/\r?\n\s*\r?\n/', trim($body)) as $chunk) {
        $ls = $lines($chunk);
        if (!$ls) continue;
        if (preg_match('/^([A-Z])\s+(.{2,80})$/u', $ls[0], $m)) {
            array_shift($ls);
            $out[] = ['letter' => $m[1], 'title' => $m[2], 'lines' => $ls];
        } else {
            $out[] = ['letter' => '', 'title' => '', 'lines' => $ls];
        }
    }
    return $out;
};
$fixed = function (string $key) use ($blocks, $vars) {
    $b = $blocks[$key] ?? null;
    if (!$b) return null;
    return ['title' => (string)$b['title'], 'body' => ProposalRules::applyVars((string)$b['body'], $vars)];
};
$c = fn($k) => trim((string)($content[$k] ?? ''));

$client = trim((string)($proposal['client_name'] ?? ''));
$clientLabel = $client !== '' ? $client : 'Cliente';
$onceItems = array_values(array_filter($items, fn($it) => ($it['billing'] ?? 'once') !== 'monthly'));
$monthItems = array_values(array_filter($items, fn($it) => ($it['billing'] ?? 'once') === 'monthly'));
$expectedReturn = (float)($content['expected_return'] ?? 0);
$productPrice = (float)($content['product_price'] ?? 0);
$hasMonthly = $monthItems && (float)$totals['monthly'] > 0;
$hasOnce = $onceItems && (float)$totals['once'] > 0;
$plans = $hasMonthly ? ProposalRules::plans($items, $content['discount_6'] ?? 20, $content['discount_12'] ?? 30) : null;
$plan12 = $plans['items'][2] ?? null;
$extras = ($hasMonthly && $productPrice > 0 && $plan12) ? ProposalRules::extraProducts($plan12['year'], $productPrice) : [];
$hasReturn = $expectedReturn > 0 || $c('return_text') !== '' || $extras;
$period = (!empty($timeline['start']) && !empty($timeline['end'])) ? $brDate($timeline['start']) . ' a ' . $brDate($timeline['end']) : 'A definir';
$saveCell = function ($money, $pct) use ($brl) {
    if ((float)$money <= 0) return '-';
    return $brl($money) . ' (' . number_format((float)$pct, 0, ',', '.') . '%)';
};

$business = array_filter([
    'Perfil da empresa' => $c('business_profile'),
    'Presença e canais atuais' => $c('business_channels'),
    'Oportunidades a validar no diagnóstico' => $c('business_opportunity'),
    'Pontos a validar no diagnóstico' => $c('business_diagnosis'),
], fn($v) => $v !== '');

// Mês 1 do cronograma = mês de início da proposta (o mesmo da projeção).
$originMonth = null;
if (!empty($timeline['start'])) $originMonth = strtotime(date('Y-m-01', strtotime($timeline['start'])));
$slot = function ($date) use ($originMonth) {
    if (!$date || !$originMonth) return null;
    $t = strtotime((string)$date);
    if (!$t) return null;
    $m = ((int)date('Y', $t) - (int)date('Y', $originMonth)) * 12 + ((int)date('n', $t) - (int)date('n', $originMonth));
    return max(0, min(11, $m));
};

$fontUrl = fn($file) => $e(str_replace('\\', '/', $fontDir . '/' . $file));
$alt = fn($i) => $i % 2 ? ' class="alt"' : '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
    @font-face { font-family: 'Poppins'; font-style: normal; font-weight: normal; src: url("<?= $fontUrl('Poppins-Regular.ttf') ?>") format("truetype"); }
    @font-face { font-family: 'Poppins'; font-style: normal; font-weight: bold; src: url("<?= $fontUrl('Poppins-Bold.ttf') ?>") format("truetype"); }
    @font-face { font-family: 'Poppins'; font-style: italic; font-weight: normal; src: url("<?= $fontUrl('Poppins-Italic.ttf') ?>") format("truetype"); }
    @font-face { font-family: 'PoppinsSemi'; font-style: normal; font-weight: normal; src: url("<?= $fontUrl('Poppins-SemiBold.ttf') ?>") format("truetype"); }
    @page { size: A4 landscape; margin: 19mm 14.7mm 15mm 14.7mm; }
    body { font-family: 'Poppins', 'DejaVu Sans', sans-serif; font-size: 7.6pt; color: #1d2124; line-height: 1.25; margin: 0; }
    h1, h2, h3, p { margin: 0; }
    .page { page-break-before: always; }
    .semi { font-family: 'PoppinsSemi', 'DejaVu Sans', sans-serif; }
    .bold { font-weight: bold; }
    .muted { color: #6f7a78; }
    .note { color: #6f7a78; font-size: 6.6pt; margin-top: 2.2mm; }

    /* Páginas do modelo e abertura ocupam a folha toda */
    .sheet { position: relative; height: 1mm; }
    .bleed { position: absolute; top: -19mm; left: -14.7mm; width: 297mm; height: 210mm; }
    .bleed img.full { width: 297mm; height: 210mm; display: block; }

    /* Título das páginas da proposta */
    table.head { width: 100%; border-collapse: collapse; border-bottom: 0.3mm solid #8fd9c9; margin-bottom: 4mm; }
    table.head td { padding: 0 0 2.2mm; vertical-align: bottom; }
    table.head h2 { font-family: 'Poppins'; font-weight: bold; font-size: 13.5pt; color: #111417; }
    table.head .aside { text-align: right; font-family: 'PoppinsSemi'; color: #15a88f; font-size: 7.4pt; }

    /* Seção numerada com linhas (perfil, escopo, condições) */
    table.sec { width: 100%; border-collapse: collapse; margin-top: 2.6mm; }
    table.sec td { padding: 0 0 1.3mm; vertical-align: bottom; }
    table.sec .n { color: #15a88f; font-family: 'PoppinsSemi'; font-size: 9pt; }
    table.sec .t { font-family: 'Poppins'; font-weight: bold; font-size: 9pt; color: #111417; }
    table.sec .tag { text-align: right; font-style: italic; color: #6f7a78; font-size: 6.8pt; }
    table.rows { width: 100%; border-collapse: collapse; border-top: 0.6mm solid #3a3f42; }
    table.rows td { background: #ffffff; border: 0.2mm solid #dfe6e4; border-top: none; padding: 0.7mm 3mm; font-size: 7.3pt; line-height: 1.15; color: #1d2124; }
    table.rows tr.alt td { background: #f6f9f8; }
    table.rows .b { color: #3a3f42; padding-right: 1.6mm; }

    /* Tabelas de valores */
    table.grid { width: 100%; border-collapse: collapse; }
    table.grid th { background: #0b0c0d; color: #ffffff; font-family: 'PoppinsSemi'; font-weight: normal; font-size: 7.3pt; line-height: 1.15; text-align: left; padding: 1.3mm 2.4mm; border: 0.2mm solid #2c3033; }
    table.grid td { background: #ffffff; border: 0.2mm solid #dfe6e4; padding: 1.1mm 2.4mm; font-size: 7.3pt; line-height: 1.15; }
    table.grid tr.alt td { background: #f6f9f8; }
    table.grid .r { text-align: right; white-space: nowrap; }
    table.grid tr.total td { background: #0b0c0d; color: #4ff0d1; font-family: 'PoppinsSemi'; border-color: #2c3033; }
    table.grid tr.total td.lbl { color: #4ff0d1; }
    table.grid tr.hi td { background: #e3f9f3; font-family: 'PoppinsSemi'; }
    table.grid td.mint { background: #e3f9f3; }
    .sub { font-family: 'Poppins'; font-weight: bold; font-size: 9pt; color: #111417; margin: 0 0 2mm; }

    /* Abertura da proposta (Pág. 1); a faixa escura é pintada pelo ProposalPdf */
    .dark { position: absolute; left: 0; top: 0; width: 60mm; padding: 0 12mm; color: #ffffff; }
    .light { position: absolute; left: 84mm; top: 15mm; width: 188.5mm; padding-left: 9.5mm; }
    .dark .emb { text-align: center; padding-top: 14mm; }
    .dark .emb img { width: 15mm; height: 15mm; }
    .dark .brand { font-family: 'PoppinsSemi'; font-size: 13pt; margin-top: 6mm; }
    .dark .line { height: 0.5mm; background: #4ff0d1; width: 54mm; margin: 10mm 0 5mm; }
    .dark .lbl { font-family: 'PoppinsSemi'; color: #4ff0d1; font-size: 6.9pt; text-transform: uppercase; margin-top: 7mm; }
    .dark .title { font-family: 'Poppins'; font-weight: bold; font-size: 13.5pt; line-height: 1.3; margin-top: 2mm; }
    .dark .who { font-family: 'PoppinsSemi'; font-size: 11pt; margin-top: 2mm; }
    .dark .small { color: #9aa5a3; font-size: 6.6pt; margin-top: 1mm; }
    .dark .date { font-size: 8.8pt; margin-top: 2mm; }
    .conf { position: absolute; left: 12mm; top: 196mm; color: #b9c2c0; font-size: 6.2pt; }
    .cap { font-family: 'PoppinsSemi'; color: #4a5250; font-size: 6.6pt; text-transform: uppercase; margin: 0 0 2.4mm; }
    table.info { width: 100%; border-collapse: collapse; margin-bottom: 6mm; }
    table.info td { border: 0.25mm solid #d4dcda; background: #ffffff; padding: 2.4mm 2.4mm; font-size: 7.3pt; vertical-align: middle; }
    table.info td.k { font-family: 'PoppinsSemi'; width: 18%; }
    table.bar { width: 100%; border-collapse: collapse; }
    table.bar td { font-size: 7pt; padding: 0; }
    table.bar td.p { text-align: right; color: #6f7a78; font-size: 6.6pt; }
    .track { height: 2.6mm; background: #e2ebe9; border-radius: 1.3mm; margin: 1mm 0 3.6mm; }
    .fill { height: 2.6mm; border-radius: 1.3mm; }

    /* Cronograma */
    table.gantt { width: 100%; border-collapse: collapse; table-layout: fixed; }
    table.gantt td { height: 12.5mm; vertical-align: middle; border-left: 0.2mm solid #dfe6e4; padding: 0 0.6mm; }
    table.gantt td.name { border-left: none; text-align: right; padding-right: 3mm; font-family: 'Poppins'; font-weight: bold; font-size: 7.3pt; color: #111417; }
    table.gantt tr.months td { height: 7mm; text-align: center; color: #8a9593; font-size: 6.6pt; }
    table.gantt tr.months td.name { border-left: none; }
    .gbar { padding: 2.2mm 0 1.8mm; line-height: 1.2; border-radius: 2mm; text-align: center; font-family: 'PoppinsSemi'; font-size: 6.2pt; }
    .gbar.once { background: #4ff0d1; color: #0b0c0d; }
    .gbar.month { background: #0b0c0d; color: #ffffff; }

    /* Gráficos de colunas */
    table.chart { width: 100%; border-collapse: collapse; table-layout: fixed; }
    table.chart td.col { vertical-align: bottom; text-align: center; padding: 0 0.8mm; font-size: 0; line-height: 0; }
    table.chart td.neg { vertical-align: top; text-align: center; padding: 0 0.8mm; font-size: 0; line-height: 0; }
    table.chart tr.axis td { border-top: 0.3mm solid #9aa5a3; }
    table.chart td.lbl { text-align: center; font-size: 6pt; color: #6f7a78; padding-top: 1mm; }
    .legend { font-size: 6.4pt; color: #4a5250; margin-bottom: 2mm; }
    .legend span { display: inline-block; width: 4mm; height: 1.4mm; margin: 0 1mm 0 3mm; vertical-align: middle; }

    /* Aceite */
    .sigline { color: #3a3f42; letter-spacing: 0.4pt; }
    a.cta { display: inline-block; background: #4ff0d1; color: #0b0c0d; font-family: 'PoppinsSemi'; padding: 2mm 5mm; text-decoration: none; border-radius: 1.5mm; }
</style>
</head>
<body>

<?php foreach ($modelPages as $i => $img): ?>
<div class="sheet<?= $i > 0 ? ' page' : '' ?>"><div class="bleed"><img class="full" src="<?= $e($img) ?>" alt=""></div></div>
<?php endforeach; ?>

<!-- Pág. 1: informações da proposta -->
<div class="sheet<?= $modelPages ? ' page' : '' ?>"><div class="bleed">
    <div class="dark">
        <div class="emb"><?php if ($emblemPath): ?><img src="<?= $e($emblemPath) ?>" alt=""><?php endif; ?></div>
        <div class="brand"><?= $e($brand) ?></div>
        <div class="line"></div>
        <div class="lbl" style="margin-top:0;">Proposta comercial</div>
        <div class="title"><?= $e($proposal['title']) ?></div>
        <div class="lbl" style="margin-top:13mm;">Preparada para</div>
        <div class="who"><?= $e($clientLabel) ?></div>
        <?php $contact = array_filter([trim((string)($proposal['client_email'] ?? '')), trim((string)($proposal['client_phone'] ?? ''))]); ?>
        <?php if ($contact): ?><div class="small"><?= $e(implode(' · ', $contact)) ?></div><?php endif; ?>
        <div class="lbl">Data</div>
        <div class="date"><?= $e($monthYear($proposal['created_at'] ?? null)) ?></div>
    </div>
    <div class="conf">Documento confidencial</div>
    <div class="light">
        <div class="cap">Informações da proposta</div>
        <table class="info">
            <tr>
                <td class="k">Cliente</td><td><?= $e($clientLabel) ?></td>
                <td class="k">Proponente</td><td><?= $e($brand) ?></td>
            </tr>
            <tr>
                <td class="k">Projeto</td><td><?= $e($proposal['title']) ?></td>
                <td class="k">Validade</td><td><?= !empty($proposal['validity_date']) ? $e($brDate($proposal['validity_date'])) : 'A combinar' ?></td>
            </tr>
            <tr>
                <td class="k">Implantação</td><td><?= $onceItems ? $e(implode(', ', array_column($onceItems, 'description'))) : 'Não se aplica' ?></td>
                <td class="k">Prazo</td><td><?= $e($period) ?></td>
            </tr>
            <tr>
                <?php if ($hasMonthly): ?>
                <td class="k">Mensalidade</td><td><?= $e(implode(', ', array_column($monthItems, 'description'))) ?></td>
                <?php else: ?>
                <td class="k">Cobrança</td><td>Pagamento único</td>
                <?php endif; ?>
                <td class="k">Pagamento</td><td><?= $e($vars['condicao_pagamento']) ?></td>
            </tr>
        </table>

        <?php
        $colors = ['#0b0c0d', '#1fb89f', '#4ff0d1'];
        $composition = [
            'Composição da implantação' => [$onceItems, $totals['once']],
            'Composição da mensalidade' => [$monthItems, $totals['monthly']],
        ];
        foreach ($composition as $caption => [$list, $sum]):
            if (!$list || $sum <= 0) continue;
        ?>
        <div class="cap"><?= $e($caption) ?></div>
        <?php foreach ($list as $k => $it): $pct = (float)$it['amount'] / $sum * 100; ?>
        <table class="bar"><tr>
            <td class="semi"><?= $e($it['description']) ?></td>
            <td class="p"><?= number_format($pct, 1, ',', '.') ?>%</td>
        </tr></table>
        <div class="track"><div class="fill" style="width:<?= max(1.5, round($pct, 2)) ?>%;background:<?= $colors[$k % 3] ?>;"></div></div>
        <?php endforeach; ?>
        <?php endforeach; ?>

        <p class="note" style="font-style:italic;">Valores em reais nas páginas de investimento.<?= !empty($proposal['observations']) ? ' ' . $e($proposal['observations']) : '' ?></p>
    </div>
</div></div>

<?php if ($business): $n = 0; ?>
<div class="page">
    <table class="head"><tr>
        <td><h2>Entendimento do negócio</h2></td>
        <td class="aside"><?= $e($clientLabel) ?> · informações públicas</td>
    </tr></table>
    <?php foreach ($business as $label => $text): $n++; ?>
    <table class="sec"><tr><td><span class="n"><?= sprintf('%02d', $n) ?></span>&nbsp; <span class="t"><?= $e($label) ?></span></td></tr></table>
    <table class="rows">
        <?php foreach ($lines($text) as $i => $line): ?><tr<?= $alt($i) ?>><td><span class="b">&bull;</span> <?= $e($line) ?></td></tr><?php endforeach; ?>
    </table>
    <?php endforeach; ?>
    <p class="note">Informações coletadas em fontes públicas e na conversa com o cliente. Os pontos de oportunidade são hipóteses e serão confirmados no diagnóstico.</p>
</div>
<?php endif; ?>

<?php
$scopePages = [];
if ($onceItems) $scopePages[] = ['Escopo dos serviços — Implantação', 'Pagamento único', $onceItems, 'Os valores de cada item de implantação estão na página de Investimento.'];
if ($monthItems) $scopePages[] = ['Escopo dos serviços — Acompanhamento mensal', 'Recorrente', $monthItems, 'Os valores mensais de cada serviço estão na página de Investimento.'];
$scopeN = 0;
foreach ($scopePages as [$title, $tag, $list, $foot]):
?>
<div class="page">
    <table class="head"><tr>
        <td><h2><?= $e($title) ?></h2></td>
        <td class="aside"><?= $e($tag) ?></td>
    </tr></table>
    <?php foreach ($list as $it): $scopeN++;
        $meta = [];
        if ($it['hours'] !== null && $it['hours'] !== '' && (float)$it['hours'] > 0) $meta[] = $num($it['hours']) . ' horas';
        if ((float)($it['quantity'] ?? 1) != 1) $meta[] = 'Quantidade ' . $num($it['quantity']);
        if (!empty($it['schedule_start'])) {
            $meta[] = empty($it['schedule_end']) ? 'A partir de ' . $brDate($it['schedule_start']) : $brDate($it['schedule_start']) . ' a ' . $brDate($it['schedule_end']);
        }
        $scopeLines = $lines($it['scope'] ?? '');
    ?>
    <table class="sec"><tr>
        <td><span class="n"><?= sprintf('%02d', $scopeN) ?></span>&nbsp; <span class="t"><?= $e($it['description']) ?></span></td>
        <td class="tag"><?= $e(implode(' · ', $meta)) ?></td>
    </tr></table>
    <table class="rows">
        <?php if (!$scopeLines): ?><tr><td><span class="b">&bull;</span> <?= $e($it['description']) ?>.</td></tr><?php endif; ?>
        <?php foreach ($scopeLines as $i => $line): ?><tr<?= $alt($i) ?>><td><span class="b">&bull;</span> <?= $e($line) ?></td></tr><?php endforeach; ?>
    </table>
    <?php endforeach; ?>
    <p class="note"><?= $e($foot) ?></p>
</div>
<?php endforeach; ?>

<div class="page">
    <table class="head"><tr>
        <td><h2>Cronograma de etapas</h2></td>
        <td class="aside">Projeção de 12 meses</td>
    </tr></table>
    <?php if (!$timeline['rows'] || !$originMonth): ?>
        <p class="muted">O cronograma é definido pelas datas de cada serviço.</p>
    <?php else: ?>
    <table class="gantt">
        <tr class="months">
            <td class="name" style="width:24%;"></td>
            <?php for ($m = 1; $m <= 12; $m++): ?><td><?= $m ?></td><?php endfor; ?>
        </tr>
        <?php foreach ($timeline['rows'] as $row):
            $a = $slot($row['start']);
            $b = !empty($row['ongoing']) ? 11 : $slot($row['end'] ?: $row['start']);
            if ($a === null) $a = $b;
            if ($b === null) $b = $a;
            $monthly = ($row['billing'] ?? 'once') === 'monthly';
        ?>
        <tr>
            <td class="name"><?= $e($row['description']) ?></td>
            <?php if ($a === null): ?>
                <td colspan="12" class="muted">&nbsp;A definir</td>
            <?php else:
                if ($b < $a) [$a, $b] = [$b, $a];
                for ($m = 0; $m < $a; $m++): ?><td></td><?php endfor; ?>
                <td colspan="<?= $b - $a + 1 ?>"><div class="gbar <?= $monthly ? 'month' : 'once' ?>">M<?= $a + 1 ?><?= $b > $a ? ' a M' . ($b + 1) : '' ?></div></td>
                <?php for ($m = $b + 1; $m < 12; $m++): ?><td></td><?php endfor; ?>
            <?php endif; ?>
        </tr>
        <?php endforeach; ?>
    </table>
    <p class="note">Mês 1 = <?= $e($monthYear($timeline['start'])) ?>. Período de implantação: <?= $e($period) ?><?= $timeline['days'] > 0 ? ' (' . (int)$timeline['days'] . ' dias)' : '' ?>.<?= $hasMonthly ? ' Serviços mensais seguem até o 12º mês.' : '' ?></p>
    <?php endif; ?>
</div>

<div class="page">
    <table class="head"><tr>
        <td><h2>Investimento</h2></td>
        <td class="aside">Valores em reais</td>
    </tr></table>
    <table width="100%" cellspacing="0" cellpadding="0"><tr>
        <?php if ($hasOnce): ?>
        <td width="<?= $hasMonthly ? '49%' : '100%' ?>" style="vertical-align:top;">
            <table class="grid">
                <tr><th>Implantação (pagamento único)</th><th class="r">Valor</th></tr>
                <?php foreach ($onceItems as $i => $it): ?><tr<?= $alt($i) ?>><td><?= $e($it['description']) ?></td><td class="r"><?= $brl($it['amount']) ?></td></tr><?php endforeach; ?>
                <tr class="total"><td class="lbl">Total da implantação</td><td class="r"><?= $brl($totals['once']) ?></td></tr>
            </table>
        </td>
        <?php endif; ?>
        <?php if ($hasMonthly): ?>
        <?php if ($hasOnce): ?><td width="2%"></td><?php endif; ?>
        <td width="<?= $hasOnce ? '49%' : '100%' ?>" style="vertical-align:top;">
            <table class="grid">
                <tr><th>Serviço mensal</th><th class="r">Valor mensal</th></tr>
                <?php foreach ($monthItems as $i => $it): ?><tr<?= $alt($i) ?>><td><?= $e($it['description']) ?></td><td class="r"><?= $brl($it['amount']) ?></td></tr><?php endforeach; ?>
                <tr class="total"><td class="lbl">Total da mensalidade</td><td class="r"><?= $brl($totals['monthly']) ?></td></tr>
            </table>
        </td>
        <?php endif; ?>
    </tr></table>

    <?php if ($items): ?>
    <p class="sub" style="margin-top:6mm;">Detalhamento por serviço</p>
    <table class="grid">
        <tr><th>Serviço</th><th>Cobrança</th><th class="r">Quantidade</th><th class="r">Horas</th><th class="r">Valor unitário</th><th class="r">Total</th></tr>
        <?php foreach ($items as $i => $it):
            $q = (float)($it['quantity'] ?? 1) ?: 1;
            $perHour = (float)($it['hours'] ?? 0) > 0 && (float)($it['hourly_rate'] ?? 0) > 0;
            $unit = $perHour ? (float)$it['hourly_rate'] : (float)$it['amount'] / $q;
            $isMonthly = ($it['billing'] ?? 'once') === 'monthly';
        ?>
        <tr<?= $alt($i) ?>>
            <td><?= $e($it['description']) ?></td>
            <td><?= $isMonthly ? 'Mensal' : 'Pagamento único' ?></td>
            <td class="r"><?= $num($it['quantity'] ?? 1) ?></td>
            <td class="r"><?= $num($it['hours']) ?></td>
            <td class="r"><?= $brl($unit) ?><?= $perHour ? '/h' : '' ?></td>
            <td class="r"><?= $brl($it['amount']) ?><?= $isMonthly ? '/mês' : '' ?></td>
        </tr>
        <?php endforeach; ?>
        <tr class="total"><td class="lbl" colspan="5"><?= $hasMonthly ? 'Investimento no primeiro ano (implantação + 12 mensalidades)' : 'Total da implantação' ?></td><td class="r"><?= $brl($hasMonthly ? $totals['year'] : $totals['once']) ?></td></tr>
    </table>
    <?php endif; ?>
    <p class="note">As condições de pagamento estão na página de Condições de pagamento e rescisão.</p>
</div>

<?php
$planColors = [ProposalChart::BLACK, ProposalChart::TEAL, ProposalChart::MINT];
if ($hasMonthly && $plans):
    $planSeries = [];
    foreach ($plans['items'] as $k => $p) {
        $planSeries[] = ['label' => $p['label'], 'color' => $planColors[$k], 'text' => $planColors[$k], 'values' => $p['index']];
    }
    $planChart = ProposalChart::line($planSeries, [
        'fontDir' => $fontDir, 'width' => 1500, 'height' => 1130,
        'xTitle' => 'Mês', 'yTitle' => 'Custo acumulado (índice plano 3 meses no mês 12 = 100)',
        'format' => [ProposalChart::class, 'index'], 'endLabels' => true,
    ]);
?>
<div class="page">
    <table class="head"><tr>
        <td><h2>Projeção de 12 meses</h2></td>
        <td class="aside">Comparativo entre os planos</td>
    </tr></table>
    <table width="100%" cellspacing="0" cellpadding="0"><tr>
        <td width="47%" style="vertical-align:top;">
            <p class="sub">Custo acumulado ao longo do tempo</p>
            <?php if ($planChart): ?><img src="<?= $planChart ?>" style="width:100%;" alt=""><?php endif; ?>
        </td>
        <td width="3%"></td>
        <td width="50%" style="vertical-align:top;">
            <p class="sub">Quanto cada plano economiza</p>
            <table class="grid">
                <tr>
                    <th></th>
                    <?php foreach ($plans['items'] as $p): ?><th class="r"><?= $e(str_replace('Plano ', 'Plano ', $p['label'])) ?></th><?php endforeach; ?>
                </tr>
                <tr>
                    <td>Mensalidade</td>
                    <?php foreach ($plans['items'] as $p): ?><td class="r"><?= $brl($p['monthly']) ?></td><?php endforeach; ?>
                </tr>
                <tr class="alt">
                    <td>Economia por mês</td>
                    <?php foreach ($plans['items'] as $p): ?><td class="r<?= $p['save_month'] > 0 ? ' mint' : '' ?>"><?= $saveCell($p['save_month'], $p['discount']) ?></td><?php endforeach; ?>
                </tr>
                <tr>
                    <td>Custo médio por mês, com a implantação diluída</td>
                    <?php foreach ($plans['items'] as $p): ?><td class="r"><?= $brl($p['avg']) ?></td><?php endforeach; ?>
                </tr>
                <tr class="alt">
                    <td>Economia no ano</td>
                    <?php foreach ($plans['items'] as $k => $p): ?><td class="r<?= $p['save_year'] > 0 ? ' mint' : '' ?>"><?= $p['save_year'] > 0 ? $brl($p['save_year']) . ' (' . number_format($p['discount'], 0, ',', '.') . '%)' : '-' ?></td><?php endforeach; ?>
                </tr>
            </table>
            <p style="margin-top:4mm;font-size:8pt;">O pagamento único de implantação é cobrado uma só vez, no primeiro mês, e é igual em todos os planos. A diferença está na mensalidade: quanto maior o compromisso, menor o custo ao longo do ano.</p>
        </td>
    </tr></table>
</div>
<?php else:
    $maxCum = 0.0;
    foreach ($projection['months'] as $m) $maxCum = max($maxCum, $m['cum_invest'], $m['cum_return']);
    $costSeries = [['label' => 'Investido acumulado', 'color' => ProposalChart::BLACK, 'values' => array_column($projection['months'], 'cum_invest')]];
    if ($expectedReturn > 0) $costSeries[] = ['label' => 'Retorno acumulado', 'color' => ProposalChart::TEAL, 'values' => array_column($projection['months'], 'cum_return')];
    $costChart = ProposalChart::line($costSeries, [
        'fontDir' => $fontDir, 'width' => 1500, 'height' => 1130,
        'xTitle' => 'Mês', 'yTitle' => 'Valor acumulado (R$)',
        'format' => [ProposalChart::class, 'money'], 'endLabels' => true,
    ]);
?>
<div class="page">
    <table class="head"><tr>
        <td><h2>Projeção de 12 meses</h2></td>
        <td class="aside"><?= $expectedReturn > 0 ? 'Investimento e retorno estimado' : 'Investimento mês a mês' ?></td>
    </tr></table>
    <table width="100%" cellspacing="0" cellpadding="0"><tr>
        <td width="47%" style="vertical-align:top;">
            <p class="sub">Investimento acumulado ao longo do tempo</p>
            <?php if ($costChart): ?><img src="<?= $costChart ?>" style="width:100%;" alt=""><?php endif; ?>
        </td>
        <td width="3%"></td>
        <td width="50%" style="vertical-align:top;">
            <p class="sub">Mês a mês</p>
            <table class="grid">
                <tr>
                    <th>Mês</th><th class="r">Investimento</th><th class="r">Investido acumulado</th>
                    <?php if ($expectedReturn > 0): ?><th class="r">Retorno acumulado</th><?php endif; ?>
                </tr>
                <?php foreach ($projection['months'] as $i => $m): ?>
                <tr<?= $alt($i) ?>>
                    <td><?= $i + 1 ?> · <?= $e($m['label']) ?></td>
                    <td class="r"><?= $brl($m['invest']) ?></td>
                    <td class="r"><?= $brl($m['cum_invest']) ?></td>
                    <?php if ($expectedReturn > 0): ?><td class="r mint"><?= $brl($m['cum_return']) ?></td><?php endif; ?>
                </tr>
                <?php endforeach; ?>
                <tr class="total">
                    <td class="lbl">12 meses</td><td class="r"><?= $brl($projection['invest']) ?></td><td class="r"><?= $brl($projection['invest']) ?></td>
                    <?php if ($expectedReturn > 0): ?><td class="r"><?= $brl($projection['return']) ?></td><?php endif; ?>
                </tr>
            </table>
        </td>
    </tr></table>
</div>
<?php endif; ?>

<?php if ($extras):
    $extraColors = [ProposalChart::BLACK, ProposalChart::TEAL, ProposalChart::MINT];
    $extraSeries = [];
    $extraPoints = [];
    foreach ($extras as $k => $row) {
        $extraSeries[] = ['label' => 'Margem de ' . number_format($row['margin'], 0, ',', '.') . '%', 'color' => $extraColors[$k], 'text' => $extraColors[$k], 'values' => $row['pace']];
        if ($row['per_month'] > 0 && $row['per_month'] < count($row['pace'])) {
            $extraPoints[] = ['series' => $k, 'index' => $row['per_month'], 'label' => $row['per_month'] . ' por mês'];
        }
    }
    $extraChart = ProposalChart::line($extraSeries, [
        'fontDir' => $fontDir, 'width' => 1500, 'height' => 1180,
        'xTitle' => 'Produtos a mais vendidos por mês, durante os 12 meses',
        'yTitle' => 'Lucro líquido adicional no período (R$)',
        'format' => [ProposalChart::class, 'money'], 'endLabels' => true, 'points' => $extraPoints,
        'xLabels' => range(0, count($extras[0]['pace']) - 1),
    ]);
?>
<div class="page">
    <table class="head"><tr>
        <td><h2>Previsibilidade de retorno</h2></td>
        <td class="aside">Ponto de equilíbrio e lucro adicional</td>
    </tr></table>
    <table width="100%" cellspacing="0" cellpadding="0"><tr>
        <td width="50%" style="vertical-align:top;">
            <p class="sub">Premissa: produto de <?= $brl($productPrice) ?></p>
            <table class="grid">
                <tr><th>Cenário de margem</th><th class="r">Custo de produção</th><th class="r">Lucro por produto</th></tr>
                <?php foreach ($extras as $k => $row): ?>
                <tr<?= $alt($k) ?>>
                    <td>Margem de <?= number_format($row['margin'], 0, ',', '.') ?>%</td>
                    <td class="r"><?= number_format($row['cost_share'], 0, ',', '.') ?>% do preço</td>
                    <td class="r"><?= $brl($row['profit']) ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
            <p class="sub" style="margin-top:5mm;">Produtos a mais por mês para haver aumento de lucro</p>
            <table class="grid">
                <tr>
                    <th>Plano</th><th class="r">Investimento no contrato mínimo</th>
                    <?php foreach ($extras as $row): ?><th class="r">Margem de <?= number_format($row['margin'], 0, ',', '.') ?>%</th><?php endforeach; ?>
                </tr>
                <?php foreach ($plans['items'] as $k => $p):
                    $byPlan = ProposalRules::extraProducts($p['term'], $productPrice);
                ?>
                <tr<?= $k === 2 ? ' class="hi"' : $alt($k) ?>>
                    <td style="white-space:nowrap;"><?= $e($p['label']) ?></td>
                    <td class="r"><?= $brl($p['term']) ?></td>
                    <?php foreach ($byPlan as $row): ?><td class="r<?= $k === 2 ? ' mint' : '' ?>"><?= (int)$row['per_month'] ?> por mês</td><?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </table>
            <p style="margin-top:4mm;font-size:8pt;"><span class="bold">Como ler:</span> o número é a quantidade de produtos a mais que <?= $e($clientLabel) ?> precisa vender por mês, mantendo esse ritmo durante todo o contrato, para cobrir o investimento. A partir desse ritmo, o investimento está pago e o que for vendido além dele é lucro líquido adicional.</p>
            <?php foreach ($paras($c('return_text')) as $p): ?><p style="margin-top:2.5mm;font-size:8pt;"><?= nl2br($e($p)) ?></p><?php endforeach; ?>
            <p class="note">O cálculo considera apenas o investimento desta proposta. Preço, custo e margem são premissas ilustrativas, antes de impostos e despesas comerciais, e serão ajustados com os números reais da empresa. Não representam promessa de resultado.</p>
        </td>
        <td width="4%"></td>
        <td width="46%" style="vertical-align:top;">
            <p class="sub">Lucro adicional líquido no Plano 12 meses, por ritmo de vendas</p>
            <?php if ($extraChart): ?><img src="<?= $extraChart ?>" style="width:100%;" alt=""><?php endif; ?>
        </td>
    </tr></table>
</div>
<?php elseif ($expectedReturn > 0):
    $net = [];
    foreach ($projection['months'] as $m) $net[] = $m['cum_return'] - $m['cum_invest'];
?>
<div class="page">
    <table class="head"><tr>
        <td><h2>Previsibilidade de retorno</h2></td>
        <td class="aside">Ponto de equilíbrio e resultado</td>
    </tr></table>
    <p class="sub">Premissa: retorno de <?= $brl($expectedReturn) ?> por mês</p>
    <table class="grid">
        <tr><th>Indicador</th><th class="r">Valor</th></tr>
        <tr><td>Investimento em 12 meses</td><td class="r"><?= $brl($projection['invest']) ?></td></tr>
        <tr class="alt"><td>Retorno estimado em 12 meses</td><td class="r"><?= $brl($projection['return']) ?></td></tr>
        <tr><td>Mês em que o retorno cobre o investimento</td><td class="r"><?= $projection['payback'] ? 'Mês ' . (int)$projection['payback'] : 'Após 12 meses' ?></td></tr>
        <tr class="hi"><td>Resultado em 12 meses</td><td class="r"><?= $brl($projection['return'] - $projection['invest']) ?><?= $projection['roi'] !== null ? ' (' . number_format($projection['roi'], 1, ',', '.') . '%)' : '' ?></td></tr>
    </table>
    <?php foreach ($paras($c('return_text')) as $p): ?><p style="margin-top:3mm;font-size:8pt;"><?= nl2br($e($p)) ?></p><?php endforeach; ?>
    <p class="note">O cálculo considera apenas o investimento desta proposta. Premissas de retorno são ilustrativas e serão ajustadas com os números reais da empresa. Não representam promessa de resultado.</p>
</div>
<?php endif; ?>

<?php if ($hasMonthly || $hasOnce): ?>
<div class="page">
    <table class="head"><tr>
        <td><h2><?= $hasMonthly ? 'Renovações e serviços pontuais' : 'Serviços pontuais' ?></h2></td>
        <td class="aside">Valores em reais</td>
    </tr></table>
    <?php if ($hasMonthly && $plans): ?>
    <p class="sub">Renovações</p>
    <table class="grid">
        <tr><th>Renovação</th><th class="r">Mensalidade</th><th class="r">Desconto vs. 3 meses</th><th class="r">Prazo mínimo</th></tr>
        <?php foreach ($plans['items'] as $k => $p): ?>
        <tr<?= $alt($k) ?>>
            <td><?= $e($p['label']) ?></td>
            <td class="r"><?= $brl($p['monthly']) ?></td>
            <td class="r"><?= $saveCell($p['save_month'], $p['discount']) ?></td>
            <td class="r"><?= (int)$p['months'] ?> meses</td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php foreach ($paras($c('renewal_text')) as $p): ?><p style="margin-top:2.5mm;font-size:8pt;"><?= nl2br($e($p)) ?></p><?php endforeach; ?>
    <?php endif; ?>

    <p class="sub"<?= $hasMonthly ? ' style="margin-top:6mm;"' : '' ?>>Serviços pontuais</p>
    <table class="grid">
        <tr><th>Serviço pontual</th><th class="r">Valor</th></tr>
        <?php foreach ($onceItems as $i => $it): ?>
        <tr<?= $alt($i) ?>><td><?= $e($it['description']) ?></td><td class="r"><?= $brl($it['amount']) ?></td></tr>
        <?php endforeach; ?>
        <tr<?= $alt(count($onceItems)) ?>><td>Novas demandas além do escopo contratado</td><td class="r">Sob orçamento</td></tr>
    </table>
</div>
<?php endif; ?>

<div class="page">
    <table class="head"><tr>
        <td><h2>Resumo final</h2></td>
        <td class="aside">Valores em reais</td>
    </tr></table>
    <?php if ($hasMonthly && $plans): ?>
    <table class="grid">
        <tr><th>Plano</th><th class="r">Implantação</th><th class="r">Mensalidade</th><th class="r">Prazo mínimo</th><th class="r">Total do contrato mínimo</th><th class="r">Média mensal (12 meses)</th></tr>
        <?php foreach ($plans['items'] as $k => $p): ?>
        <tr<?= $k === 2 ? ' class="hi"' : $alt($k) ?>>
            <td><?= $e($p['label']) ?></td>
            <td class="r"><?= $brl($plans['once']) ?></td>
            <td class="r"><?= $brl($p['monthly']) ?></td>
            <td class="r"><?= (int)$p['months'] ?> meses</td>
            <td class="r"><?= $brl($p['term']) ?></td>
            <td class="r"><?= $brl($p['avg']) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php else: ?>
    <table class="grid">
        <tr>
            <th>Proposta</th><th class="r">Implantação</th>
            <?php if ($hasMonthly): ?><th class="r">Mensalidade</th><?php endif; ?>
            <th class="r">Prazo de implantação</th>
            <th class="r">Total<?= $hasMonthly ? ' do primeiro ano' : '' ?></th>
        </tr>
        <tr class="hi">
            <td><?= $e($proposal['title']) ?></td>
            <td class="r"><?= $brl($totals['once']) ?></td>
            <?php if ($hasMonthly): ?><td class="r"><?= $brl($totals['monthly']) ?></td><?php endif; ?>
            <td class="r"><?= $timeline['days'] > 0 ? (int)$timeline['days'] . ' dias' : 'A definir' ?></td>
            <td class="r"><?= $brl($hasMonthly ? $totals['year'] : $totals['once']) ?></td>
        </tr>
    </table>
    <?php endif; ?>
    <?php foreach ($paras($c('summary_text')) as $p): ?><p style="margin-top:3mm;font-size:8pt;"><?= nl2br($e($p)) ?></p><?php endforeach; ?>
    <?php if ($hasMonthly): ?><p class="note">A média mensal considera a implantação e as 12 primeiras mensalidades.</p><?php endif; ?>
</div>

<?php if ($b = $fixed('payment')): ?>
<div class="page">
    <table class="head"><tr>
        <td><h2><?= $e($b['title'] !== '' ? $b['title'] : 'Condições de pagamento e rescisão') ?></h2></td>
        <td class="aside">Valores em reais</td>
    </tr></table>
    <?php if ($hasMonthly && $plans): ?>
    <table class="grid">
        <tr><th>Condição de pagamento</th><th>Quando</th><?php foreach ($plans['items'] as $p): ?><th class="r"><?= $e($p['label']) ?></th><?php endforeach; ?></tr>
        <tr>
            <td>Implantação</td><td><?= $e(ucfirst($vars['condicao_pagamento'])) ?></td>
            <?php foreach ($plans['items'] as $p): ?><td class="r"><?= $brl($plans['once']) ?></td><?php endforeach; ?>
        </tr>
        <tr class="alt">
            <td>Mensalidade</td><td>Todo mês, a partir do primeiro</td>
            <?php foreach ($plans['items'] as $p): ?><td class="r"><?= $brl($p['monthly']) ?></td><?php endforeach; ?>
        </tr>
        <tr class="total">
            <td class="lbl">Total do primeiro mês (implantação + mensalidade)</td><td class="lbl">Primeiro mês</td>
            <?php foreach ($plans['items'] as $p): ?><td class="r"><?= $brl($plans['once'] + $p['monthly']) ?></td><?php endforeach; ?>
        </tr>
    </table>
    <p class="note">A implantação é cobrada uma só vez. A diferença entre os planos está na mensalidade.</p>
    <?php else: ?>
    <table class="grid">
        <tr><th>Condição de pagamento</th><th>Quando</th><th class="r">Valor</th></tr>
        <?php if ($hasOnce): ?><tr><td>Implantação</td><td><?= $e(ucfirst($vars['condicao_pagamento'])) ?></td><td class="r"><?= $brl($totals['once']) ?></td></tr><?php endif; ?>
        <?php if ($hasMonthly): ?><tr class="alt"><td>Mensalidade</td><td>Todo mês, a partir do início dos serviços</td><td class="r"><?= $brl($totals['monthly']) ?></td></tr><?php endif; ?>
        <tr class="total"><td class="lbl"><?= $hasMonthly ? 'Total do primeiro ano (implantação + 12 mensalidades)' : 'Total' ?></td><td class="lbl"><?= $hasMonthly ? '12 meses' : '' ?></td><td class="r"><?= $brl($hasMonthly ? $totals['year'] : $totals['once']) ?></td></tr>
    </table>
    <?php endif; ?>
    <?php foreach ($lettered($b['body']) as $sec): ?>
        <?php if ($sec['letter'] === ''): ?>
            <?php foreach ($sec['lines'] as $line): ?><p class="note" style="font-size:7pt;"><?= $e($line) ?></p><?php endforeach; ?>
        <?php else: ?>
            <table class="sec"><tr><td><span class="n"><?= $e($sec['letter']) ?></span>&nbsp; <span class="t"><?= $e($sec['title']) ?></span></td></tr></table>
            <table class="rows">
                <?php foreach ($sec['lines'] as $i => $line): ?><tr<?= $alt($i) ?>><td><span class="b">&bull;</span> <?= $e($line) ?></td></tr><?php endforeach; ?>
            </table>
        <?php endif; ?>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="page">
    <table class="head"><tr>
        <td><h2><?= $e(($b = $fixed('notes')) && $b['title'] !== '' ? $b['title'] : 'Notas e condições') ?></h2></td>
        <td class="aside"></td>
    </tr></table>
    <?php if ($b = $fixed('notes')): foreach ($lettered($b['body']) as $sec): ?>
        <?php if ($sec['letter'] !== ''): ?>
        <table class="sec"><tr><td><span class="n"><?= $e($sec['letter']) ?></span>&nbsp; <span class="t"><?= $e($sec['title']) ?></span></td></tr></table>
        <?php endif; ?>
        <table class="rows"<?= $sec['letter'] === '' ? ' style="margin-top:2.6mm;"' : '' ?>>
            <?php foreach ($sec['lines'] as $i => $line): ?><tr<?= $alt($i) ?>><td><span class="b">&bull;</span> <?= $e($line) ?></td></tr><?php endforeach; ?>
        </table>
    <?php endforeach; endif; ?>

    <p class="t" style="font-family:'Poppins';font-weight:bold;font-size:9pt;margin-top:4mm;">Aceite</p>
    <?php if ($hasMonthly): ?>
    <p style="font-size:8pt;margin:2mm 0 0;">Plano escolhido: ( ) 3 meses &nbsp; ( ) 6 meses &nbsp; ( ) 12 meses</p>
    <?php endif; ?>
    <table width="100%" cellspacing="0" cellpadding="0" style="margin-top:7mm;page-break-inside:avoid;"><tr>
        <td width="34%" style="vertical-align:bottom;"><div class="sigline">______________________________</div><p class="muted" style="margin-top:1mm;"><?= $e($clientLabel) ?> (contratante)</p></td>
        <td width="34%" style="vertical-align:bottom;"><div class="sigline">______________________________</div><p class="muted" style="margin-top:1mm;"><?= $e($brand) ?> (contratada)</p></td>
        <td width="32%" style="vertical-align:bottom;"><p>Data: ____ / ____ / ________</p><p class="muted" style="margin-top:1mm;">&nbsp;</p></td>
    </tr></table>
    <table width="100%" cellspacing="0" cellpadding="0" style="margin-top:4mm;"><tr>
        <td style="vertical-align:middle;width:72mm;"><a class="cta" href="<?= $e($acceptLink) ?>">Aceitar ou responder esta proposta online</a></td>
        <td style="vertical-align:middle;" class="note"><?= $e($acceptLink) ?></td>
    </tr></table>
</div>

</body>
</html>
