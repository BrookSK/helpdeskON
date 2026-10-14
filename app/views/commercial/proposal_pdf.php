<?php
// Documento HTML convertido em PDF pelo dompdf: só CSS 2.1, tabelas no lugar
// de flex/grid e imagens por caminho local (dentro do chroot).
$e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$brl = fn($v) => 'R$ ' . number_format((float)$v, 2, ',', '.');
$num = function ($v) {
    if ($v === null || $v === '') return '—';
    $s = number_format((float)$v, 2, ',', '.');
    return rtrim(rtrim($s, '0'), ',');
};
$brDate = function ($d) {
    if (!$d) return '';
    $t = strtotime((string)$d);
    return $t ? date('d/m/Y', $t) : '';
};
$para = function ($text) use ($e) {
    $out = '';
    foreach (preg_split('/\r?\n\s*\r?\n/', trim((string)$text)) as $p) {
        if (trim($p) === '') continue;
        $out .= '<p>' . nl2br($e(trim($p))) . '</p>';
    }
    return $out;
};
$fixed = function (string $key) use ($blocks, $vars) {
    $b = $blocks[$key] ?? null;
    if (!$b) return null;
    return [
        'title' => $b['title'] !== '' ? $b['title'] : '',
        'body' => ProposalRules::applyVars((string)$b['body'], $vars),
    ];
};
$c = fn($k) => trim((string)($content[$k] ?? ''));

$business = array_filter([
    'Perfil da empresa' => $c('business_profile'),
    'Presença dos canais atuais' => $c('business_channels'),
    'Oportunidade' => $c('business_opportunity'),
    'Validar diagnóstico' => $c('business_diagnosis'),
], fn($v) => $v !== '');

$expectedReturn = (float)($content['expected_return'] ?? 0);
$hasReturn = $expectedReturn > 0 || $c('return_text') !== '';
$onceItems = array_values(array_filter($items, fn($it) => ($it['billing'] ?? 'once') !== 'monthly'));
$billingLabel = fn($it) => (($it['billing'] ?? 'once') === 'monthly') ? 'Mensal' : 'Pontual';

$maxMonth = 0.0;
foreach ($projection['months'] as $m) $maxMonth = max($maxMonth, $m['invest'], $m['return']);

$sec = 0;
$head = function (string $part, string $title) use (&$sec, $e) {
    $sec++;
    return '<div class="kicker">' . sprintf('%02d', $sec) . ' &middot; ' . $e($part) . '</div>'
        . '<h2>' . $e($title) . '</h2><div class="rule"></div>';
};
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 30mm 16mm 22mm 16mm; }
    @page :first { margin: 0; }
    * { box-sizing: border-box; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.6pt; color: #3d5158; line-height: 1.55; margin: 0; }
    p { margin: 0 0 8pt; }
    h2 { margin: 0; font-size: 21pt; line-height: 1.15; color: #0b1a24; letter-spacing: -0.5pt; }
    h3 { margin: 0 0 4pt; font-size: 11pt; color: #0b1a24; }
    .kicker { font-size: 7.5pt; font-weight: bold; letter-spacing: 1.6pt; text-transform: uppercase; color: #0a8f7d; margin-bottom: 6pt; }
    .rule { width: 34mm; height: 1.2mm; background: #00bfa6; margin: 10pt 0 16pt; }
    .lead { font-size: 11.5pt; color: #24363d; line-height: 1.6; }
    .muted { color: #7a8b91; }
    .section { page-break-before: always; }

    /* Cabeçalho e rodapé em todas as páginas (exceto a capa, que tem margem 0) */
    .hdr { position: fixed; top: -30mm; left: -16mm; right: -16mm; height: 17mm; background: #07141b; }
    .hdr td { color: #cfe3e6; font-size: 7.5pt; vertical-align: middle; padding: 0 16mm; height: 17mm; }
    .hdr img { height: 8mm; vertical-align: middle; }
    .hdr .brand { color: #ffffff; font-weight: bold; font-size: 9pt; }
    .hdr .bar { position: absolute; left: 0; right: 0; bottom: 0; height: 0.8mm; background: #00bfa6; }
    .ftr { position: fixed; bottom: -15mm; left: 0; right: 0; height: 8mm; font-size: 7pt; color: #8a9aa0; border-top: 0.3mm solid #dfe9ea; padding-top: 2mm; }

    /* Capa */
    .cover { position: relative; width: 210mm; height: 296mm; background: #07141b; color: #ffffff; overflow: hidden; }
    .cover .band { position: absolute; left: 0; right: 0; bottom: 0; height: 96mm; background: #0d3a37; }
    .cover .band2 { position: absolute; left: 0; right: 0; bottom: 96mm; height: 1mm; background: #00bfa6; }
    .cover .mark { position: absolute; right: -30mm; top: 40mm; width: 150mm; opacity: 0.08; }
    .cover .top { position: absolute; left: 18mm; top: 18mm; right: 18mm; }
    .cover .top img.emb { height: 15mm; vertical-align: middle; }
    .cover .top img.logo { height: 11mm; vertical-align: middle; margin-left: 4mm; }
    .cover .top .name { font-size: 13pt; font-weight: bold; vertical-align: middle; margin-left: 4mm; }
    .cover .mid { position: absolute; left: 18mm; right: 18mm; top: 88mm; }
    .cover .pill { display: inline-block; border: 0.35mm solid #2f8f84; color: #5eead4; font-size: 8pt; letter-spacing: 2pt; text-transform: uppercase; padding: 2mm 4mm; border-radius: 10mm; }
    .cover h1 { font-size: 32pt; line-height: 1.1; margin: 9mm 0 0; color: #ffffff; letter-spacing: -1pt; }
    .cover .bottom { position: absolute; left: 18mm; right: 18mm; bottom: 18mm; }
    .cover .who { font-size: 7.5pt; letter-spacing: 1.6pt; text-transform: uppercase; color: #5eead4; }
    .cover .client { font-size: 17pt; font-weight: bold; margin-top: 2mm; color: #ffffff; }
    .cover .contact { color: #b8cfd2; font-size: 9pt; margin-top: 1mm; }
    .cover table.meta { width: 100%; margin-top: 12mm; border-collapse: collapse; }
    .cover table.meta td { vertical-align: top; padding: 3mm 4mm 0 0; border-top: 0.3mm solid #2a5d58; }
    .cover table.meta b { display: block; font-size: 6.8pt; letter-spacing: 1.4pt; text-transform: uppercase; color: #5eead4; margin-bottom: 1mm; }
    .cover table.meta span { font-size: 10pt; color: #ffffff; }

    /* Componentes */
    table.grid { width: 100%; border-collapse: separate; border-spacing: 3mm; margin: 0 -3mm; }
    .card { background: #f3f8f8; border-left: 1mm solid #00bfa6; padding: 5mm; vertical-align: top; }
    .card h3 { font-size: 10.5pt; }
    .card p { margin: 0; }
    .tile { background: #07141b; color: #ffffff; text-align: center; vertical-align: middle; height: 30mm; border-top: 1mm solid #00bfa6; }
    .tile span { font-size: 13pt; font-weight: bold; color: #ffffff; }
    .step { background: #f3f8f8; padding: 5mm 4mm; vertical-align: top; }
    .step .n { width: 10mm; padding: 2mm 0; text-align: center; background: #0a8f7d; color: #ffffff; font-weight: bold; font-size: 9pt; line-height: 1.2; border-radius: 2mm; margin-bottom: 3mm; }
    .svc { border: 0.3mm solid #dfe9ea; border-left: 1mm solid #00bfa6; padding: 4mm 5mm; margin-bottom: 3.5mm; page-break-inside: avoid; }
    .svc .meta { font-size: 8pt; color: #7a8b91; margin-top: 2mm; }
    table.data { width: 100%; border-collapse: collapse; }
    table.data th { text-align: left; font-size: 7pt; letter-spacing: 1.2pt; text-transform: uppercase; color: #0a8f7d; padding: 3mm 2mm; border-bottom: 0.4mm solid #00bfa6; }
    table.data td { padding: 3mm 2mm; border-bottom: 0.3mm solid #e3ecec; vertical-align: top; }
    table.data .r { text-align: right; white-space: nowrap; }
    table.data tr.tot td { font-weight: bold; color: #0b1a24; border-bottom: none; border-top: 0.5mm solid #0b1a24; }
    table.data tr.sub td { color: #24363d; background: #f3f8f8; }
    .track { width: 100%; height: 4mm; background: #e2f2ef; }
    .fill { height: 4mm; background: #00bfa6; }
    .kpi { background: #07141b; color: #ffffff; padding: 5mm; vertical-align: top; }
    .kpi b { display: block; font-size: 6.8pt; letter-spacing: 1.3pt; text-transform: uppercase; color: #5eead4; margin-bottom: 2mm; }
    .kpi span { font-size: 13pt; font-weight: bold; color: #ffffff; white-space: nowrap; }
    .kpi.sm span { font-size: 11pt; }
    .kpi small { display: block; color: #b8cfd2; font-size: 7.5pt; margin-top: 1mm; }
    table.chart { width: 100%; border-collapse: collapse; table-layout: fixed; }
    table.chart td.col { height: 42mm; vertical-align: bottom; text-align: center; padding: 0 1mm; border-bottom: 0.4mm solid #0b1a24; }
    table.chart td.lbl { text-align: center; font-size: 6.8pt; color: #7a8b91; padding-top: 1.5mm; }
    .b-inv { background: #00bfa6; width: 45%; display: inline-block; }
    .b-ret { background: #0b1a24; width: 45%; display: inline-block; }
    .legend span { display: inline-block; width: 3mm; height: 3mm; margin: 0 1.5mm 0 4mm; vertical-align: middle; }
    ul.ticks { margin: 0; padding: 0; list-style: none; }
    ul.ticks li { padding: 2.5mm 0 2.5mm 7mm; border-bottom: 0.3mm solid #e3ecec; position: relative; }
    ul.ticks li:before { content: ''; position: absolute; left: 0; top: 4.1mm; width: 3mm; height: 3mm; background: #00bfa6; }
    .panel { background: #07141b; color: #d5e4e6; padding: 7mm; margin-top: 6mm; }
    .panel h3 { color: #ffffff; }
    .panel a { color: #5eead4; font-weight: bold; }
    .panel a.cta { display: inline-block; background: #00bfa6; color: #07141b; padding: 3.5mm 7mm; text-decoration: none; font-weight: bold; margin-top: 3mm; }
    table.compact td { padding: 1.7mm 2mm; }
    table.compact th { padding: 2mm; }
</style>
</head>
<body>

<div class="hdr">
    <table width="100%" cellspacing="0" cellpadding="0"><tr>
        <td>
            <?php if ($emblemPath): ?><img src="<?= $e($emblemPath) ?>" alt=""><?php endif; ?>
            <span class="brand">&nbsp;<?= $e($appName) ?></span>
        </td>
        <td style="text-align:right;">PROPOSTA COMERCIAL &middot; <?= $e(mb_strimwidth($proposal['title'], 0, 60, '…')) ?></td>
    </tr></table>
    <div class="bar"></div>
</div>
<div class="ftr">
    <table width="100%" cellspacing="0" cellpadding="0"><tr>
        <td><?= $e($appName) ?><?php if (!empty($proposal['client_name'])): ?> &middot; preparado para <?= $e($proposal['client_name']) ?><?php endif; ?></td>
    </tr></table>
</div>

<!-- CAPA -->
<div class="cover">
    <div class="band"></div>
    <div class="band2"></div>
    <?php if ($emblemPath): ?><img class="mark" src="<?= $e($emblemPath) ?>" alt=""><?php endif; ?>
    <div class="top">
        <?php if ($emblemPath): ?><img class="emb" src="<?= $e($emblemPath) ?>" alt=""><?php endif; ?>
        <?php if ($logoPath): ?><img class="logo" src="<?= $e($logoPath) ?>" alt=""><?php else: ?><span class="name"><?= $e($appName) ?></span><?php endif; ?>
    </div>
    <div class="mid">
        <span class="pill">Proposta comercial</span>
        <h1><?= $e($proposal['title']) ?></h1>
    </div>
    <div class="bottom">
        <?php if (!empty($proposal['client_name'])): ?>
        <div class="who">Preparado para</div>
        <div class="client"><?= $e($proposal['client_name']) ?></div>
        <div class="contact">
            <?= $e($proposal['client_email'] ?? '') ?>
            <?php if (!empty($proposal['client_email']) && !empty($proposal['client_phone'])): ?> &middot; <?php endif; ?>
            <?= $e($proposal['client_phone'] ?? '') ?>
        </div>
        <?php endif; ?>
        <table class="meta"><tr>
            <td><b>Emitida em</b><span><?= $brDate($proposal['created_at'] ?? date('Y-m-d')) ?></span></td>
            <td><b>Válida até</b><span><?= !empty($proposal['validity_date']) ? $brDate($proposal['validity_date']) : 'A combinar' ?></span></td>
            <td><b>Desenvolvimento</b><span><?= (!empty($timeline['start']) && !empty($timeline['end'])) ? $brDate($timeline['start']) . ' a ' . $brDate($timeline['end']) : 'A definir' ?></span></td>
            <td><b>Investimento</b><span><?= $brl($totals['once']) ?><?= $totals['monthly'] > 0 ? ' + ' . $brl($totals['monthly']) . '/mês' : '' ?></span></td>
        </tr></table>
    </div>
</div>

<?php if ($b = $fixed('about')): ?>
<div class="section">
    <?= $head('A ON Solutions', $b['title'] ?: 'Quem somos') ?>
    <div class="lead"><?= $para($b['body']) ?></div>
</div>
<?php endif; ?>

<?php if ($b = $fixed('what_we_do')): $lines = ProposalRules::blockLines($b['body']); ?>
<div class="section">
    <?= $head('A ON Solutions', $b['title'] ?: 'O que fazemos') ?>
    <table class="grid">
        <?php foreach (array_chunk($lines, 2) as $pair): ?>
        <tr>
            <?php foreach ($pair as $l): ?>
            <td class="card" width="50%"><h3><?= $e($l['title']) ?></h3><?php if ($l['text'] !== ''): ?><p><?= $e($l['text']) ?></p><?php endif; ?></td>
            <?php endforeach; ?>
            <?php if (count($pair) < 2): ?><td width="50%"></td><?php endif; ?>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>

<?php if ($b = $fixed('clients')): $lines = ProposalRules::blockLines($b['body']); ?>
<div class="section">
    <?= $head('A ON Solutions', $b['title'] ?: 'Clientes') ?>
    <p class="lead">Empresas que confiam na ON Solutions para desenvolver e evoluir sua operação.</p>
    <table class="grid">
        <?php foreach (array_chunk($lines, 3) as $row): ?>
        <tr>
            <?php foreach ($row as $l): ?>
            <td class="tile" width="33%"><span><?= $e($l['title']) ?></span><?php if ($l['text'] !== ''): ?><br><small style="color:#9fc0c3;font-size:7.5pt;"><?= $e($l['text']) ?></small><?php endif; ?></td>
            <?php endforeach; ?>
            <?php for ($i = count($row); $i < 3; $i++): ?><td width="33%"></td><?php endfor; ?>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>

<?php if ($b = $fixed('how_we_work')): $lines = ProposalRules::blockLines($b['body']); ?>
<div class="section">
    <?= $head('A ON Solutions', $b['title'] ?: 'Como fazemos') ?>
    <table class="grid">
        <?php foreach (array_chunk($lines, 2) as $ri => $pair): ?>
        <tr>
            <?php foreach ($pair as $pi => $l): ?>
            <td class="step" width="50%">
                <div class="n"><?= sprintf('%02d', $ri * 2 + $pi + 1) ?></div>
                <h3><?= $e($l['title']) ?></h3>
                <?php if ($l['text'] !== ''): ?><p style="margin:0;"><?= $e($l['text']) ?></p><?php endif; ?>
            </td>
            <?php endforeach; ?>
            <?php if (count($pair) < 2): ?><td width="50%"></td><?php endif; ?>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>

<?php if ($business): ?>
<div class="section">
    <?= $head('A proposta', 'Entendimento do negócio') ?>
    <table class="grid">
        <?php foreach (array_chunk($business, 2, true) as $pair): ?>
        <tr>
            <?php foreach ($pair as $label => $text): ?>
            <td class="card" width="50%"><h3><?= $e($label) ?></h3><?= $para($text) ?></td>
            <?php endforeach; ?>
            <?php if (count($pair) < 2): ?><td width="50%"></td><?php endif; ?>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>

<div class="section">
    <?= $head('A proposta', 'Escopo do serviço') ?>
    <?php if (!$items): ?>
        <p class="muted">Nenhum serviço selecionado.</p>
    <?php else: foreach ($items as $it): ?>
    <div class="svc">
        <h3><?= $e($it['description']) ?></h3>
        <?= $para($it['scope'] ?? '') ?>
        <div class="meta">
            <?= $billingLabel($it) ?>
            <?php if ((float)($it['quantity'] ?? 1) != 1): ?> &middot; Quantidade <?= $num($it['quantity']) ?><?php endif; ?>
            <?php if ($it['hours'] !== null && $it['hours'] !== ''): ?> &middot; <?= $num($it['hours']) ?> horas<?php endif; ?>
            <?php if (!empty($it['schedule_start']) || !empty($it['schedule_end'])): ?> &middot; <?= $brDate($it['schedule_start'] ?: $it['schedule_end']) ?><?php if (!empty($it['schedule_end']) && $it['schedule_end'] !== $it['schedule_start']): ?> a <?= $brDate($it['schedule_end']) ?><?php endif; ?><?php endif; ?>
        </div>
    </div>
    <?php endforeach; endif; ?>
</div>

<div class="section">
    <?= $head('A proposta', 'Cronograma de etapas') ?>
    <?php if (!empty($timeline['start']) && !empty($timeline['end'])): ?>
    <p class="lead">Período de desenvolvimento: <strong><?= $brDate($timeline['start']) ?> a <?= $brDate($timeline['end']) ?></strong><?php if ($timeline['days'] > 0): ?> (<?= (int)$timeline['days'] ?> dias)<?php endif; ?>.</p>
    <?php endif; ?>
    <?php if (!$timeline['rows']): ?>
        <p class="muted">O cronograma é definido por serviço.</p>
    <?php else: ?>
    <table class="data">
        <thead><tr><th width="34%">Etapa / serviço</th><th width="22%">Período</th><th>Linha do tempo</th></tr></thead>
        <tbody>
        <?php foreach ($timeline['rows'] as $i => $row): ?>
            <tr>
                <td><strong style="color:#0b1a24;"><?= sprintf('%02d', $i + 1) ?>.</strong> <?= $e($row['description']) ?></td>
                <td>
                    <?php if (!empty($row['ongoing']) && $row['start']): ?>A partir de<br><?= $brDate($row['start']) ?> <span class="muted">(mensal)</span>
                    <?php elseif ($row['start'] || $row['end']): ?><?= $brDate($row['start'] ?: $row['end']) ?><?php if ($row['end'] && $row['end'] !== $row['start']): ?><br>a <?= $brDate($row['end']) ?><?php endif; ?>
                    <?php else: ?><span class="muted">A definir</span><?php endif; ?>
                </td>
                <td style="vertical-align:middle;">
                    <?php if ($row['bar']): ?>
                    <div class="track"><div class="fill" style="margin-left:<?= (float)$row['bar']['left'] ?>%;width:<?= (float)$row['bar']['width'] ?>%;"></div></div>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<div class="section">
    <?= $head('Investimento', 'Investimento') ?>
    <table class="data">
        <thead><tr><th>Serviço</th><th>Cobrança</th><th class="r">Qtd</th><th class="r">Horas</th><th class="r">Valor</th><th class="r">Total</th></tr></thead>
        <tbody>
        <?php foreach ($items as $it):
            $q = (float)($it['quantity'] ?? 1) ?: 1;
            $perHour = ((float)($it['hours'] ?? 0) > 0 && (float)($it['hourly_rate'] ?? 0) > 0);
            $unit = $perHour ? (float)$it['hourly_rate'] : ((float)$it['amount'] / $q);
        ?>
            <tr>
                <td><?= $e($it['description']) ?></td>
                <td><?= $billingLabel($it) ?></td>
                <td class="r"><?= $num($it['quantity'] ?? 1) ?></td>
                <td class="r"><?= $num($it['hours']) ?></td>
                <td class="r"><?= $brl($unit) ?><?= $perHour ? '/h' : '' ?></td>
                <td class="r"><?= $brl($it['amount']) ?><?= (($it['billing'] ?? '') === 'monthly') ? '/mês' : '' ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$items): ?><tr><td colspan="6" class="muted">Sem itens.</td></tr><?php endif; ?>
            <tr class="sub"><td colspan="5">Serviços pontuais</td><td class="r"><?= $brl($totals['once']) ?></td></tr>
            <?php if ($totals['monthly'] > 0): ?>
            <tr class="sub"><td colspan="5">Serviços mensais</td><td class="r"><?= $brl($totals['monthly']) ?>/mês</td></tr>
            <?php endif; ?>
            <tr class="tot"><td colspan="5">Investimento no primeiro ano</td><td class="r"><?= $brl($totals['year']) ?></td></tr>
        </tbody>
    </table>
    <?php if (!empty($proposal['observations'])): ?><div style="margin-top:6mm;"><?= $para($proposal['observations']) ?></div><?php endif; ?>
</div>

<div class="section">
    <?= $head('Investimento', 'Projeção de 12 meses') ?>
    <p>Distribuição do investimento mês a mês<?= $expectedReturn > 0 ? ', comparada ao retorno estimado' : '' ?>.</p>
    <table class="chart">
        <tr>
            <?php foreach ($projection['months'] as $m):
                $hi = $maxMonth > 0 ? max(0.8, $m['invest'] / $maxMonth * 39) : 0;
                $hr = $maxMonth > 0 ? ($m['return'] > 0 ? max(0.8, $m['return'] / $maxMonth * 39) : 0) : 0;
            ?>
            <td class="col">
                <?php if ($m['invest'] > 0): ?><div class="b-inv" style="height:<?= round($hi, 2) ?>mm;"></div><?php endif; ?>
                <?php if ($hr > 0): ?><div class="b-ret" style="height:<?= round($hr, 2) ?>mm;"></div><?php endif; ?>
            </td>
            <?php endforeach; ?>
        </tr>
        <tr><?php foreach ($projection['months'] as $m): ?><td class="lbl"><?= $e($m['label']) ?></td><?php endforeach; ?></tr>
    </table>
    <div class="legend" style="margin:3mm 0 4mm;font-size:7.5pt;"><span style="background:#00bfa6;margin-left:0;"></span>Investimento<?php if ($expectedReturn > 0): ?><span style="background:#0b1a24;"></span>Retorno estimado<?php endif; ?></div>
    <table class="data compact">
        <thead><tr><th>Mês</th><th class="r">Investimento</th><?php if ($expectedReturn > 0): ?><th class="r">Retorno</th><?php endif; ?><th class="r">Investido acumulado</th><?php if ($expectedReturn > 0): ?><th class="r">Retorno acumulado</th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($projection['months'] as $m): ?>
            <tr>
                <td><?= $e($m['label']) ?></td>
                <td class="r"><?= $brl($m['invest']) ?></td>
                <?php if ($expectedReturn > 0): ?><td class="r"><?= $brl($m['return']) ?></td><?php endif; ?>
                <td class="r"><?= $brl($m['cum_invest']) ?></td>
                <?php if ($expectedReturn > 0): ?><td class="r"><?= $brl($m['cum_return']) ?></td><?php endif; ?>
            </tr>
        <?php endforeach; ?>
            <tr class="tot"><td>12 meses</td><td class="r"><?= $brl($projection['invest']) ?></td><?php if ($expectedReturn > 0): ?><td class="r"><?= $brl($projection['return']) ?></td><?php endif; ?><td class="r"><?= $brl($projection['invest']) ?></td><?php if ($expectedReturn > 0): ?><td class="r"><?= $brl($projection['return']) ?></td><?php endif; ?></tr>
        </tbody>
    </table>
</div>

<?php if ($hasReturn): ?>
<div class="section">
    <?= $head('Investimento', 'Previsibilidade do retorno') ?>
    <?php if ($expectedReturn > 0): ?>
    <table class="grid"><tr>
        <td class="kpi" width="33%"><b>Retorno mensal estimado</b><span><?= $brl($expectedReturn) ?></span><small>a partir do mês seguinte à entrega</small></td>
        <td class="kpi" width="33%"><b>Retorno do investimento</b><span><?= $projection['payback'] ? 'Mês ' . (int)$projection['payback'] : 'Após 12 meses' ?></span><small>quando o retorno acumulado cobre o investido</small></td>
        <td class="kpi" width="33%"><b>Resultado em 12 meses</b><span><?= $projection['roi'] !== null ? number_format($projection['roi'], 1, ',', '.') . '%' : '—' ?></span><small><?= $brl($projection['return'] - $projection['invest']) ?> sobre o investido</small></td>
    </tr></table>
    <?php endif; ?>
    <?php if ($c('return_text') !== ''): ?><div class="lead" style="margin-top:6mm;"><?= $para($c('return_text')) ?></div><?php endif; ?>
</div>
<?php endif; ?>

<?php if ($c('renewal_text') !== ''): ?>
<div class="section">
    <?= $head('Investimento', 'Renovação de serviços pontuais') ?>
    <div class="lead"><?= $para($c('renewal_text')) ?></div>
    <?php if ($onceItems): ?>
    <table class="data" style="margin-top:4mm;">
        <thead><tr><th>Serviço pontual</th><th class="r">Valor nesta proposta</th></tr></thead>
        <tbody>
        <?php foreach ($onceItems as $it): ?>
            <tr><td><?= $e($it['description']) ?></td><td class="r"><?= $brl($it['amount']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="section">
    <?= $head('Investimento', 'Resumo final') ?>
    <table class="grid"><tr>
        <td class="kpi sm" width="25%"><b>Pontuais</b><span><?= $brl($totals['once']) ?></span></td>
        <td class="kpi sm" width="25%"><b>Mensalidade</b><span><?= $brl($totals['monthly']) ?></span></td>
        <td class="kpi sm" width="25%"><b>Primeiro ano</b><span><?= $brl($totals['year']) ?></span></td>
        <td class="kpi sm" width="25%"><b>Prazo</b><span><?= $timeline['days'] > 0 ? (int)$timeline['days'] . ' dias' : 'A definir' ?></span></td>
    </tr></table>
    <?php if ($c('summary_text') !== ''): ?><div class="lead" style="margin-top:6mm;"><?= $para($c('summary_text')) ?></div><?php endif; ?>
    <?php if ($items): ?>
    <ul class="ticks" style="margin-top:5mm;">
        <?php foreach ($items as $it): ?><li><?= $e($it['description']) ?></li><?php endforeach; ?>
    </ul>
    <?php endif; ?>
</div>

<?php if ($b = $fixed('payment')): ?>
<div class="section">
    <?= $head('Condições', $b['title'] ?: 'Condições de pagamento e rescisão') ?>
    <table class="data" style="margin-bottom:6mm;">
        <tbody>
            <tr><td>Serviços pontuais</td><td class="r"><?= $brl($totals['once']) ?></td></tr>
            <tr><td>Serviços mensais</td><td class="r"><?= $brl($totals['monthly']) ?>/mês</td></tr>
            <tr><td>Condição de pagamento</td><td class="r"><?= $e($vars['condicao_pagamento']) ?></td></tr>
        </tbody>
    </table>
    <?= $para($b['body']) ?>
</div>
<?php endif; ?>

<?php if ($b = $fixed('notes')): ?>
<div class="section">
    <?= $head('Condições', $b['title'] ?: 'Notas e condições') ?>
    <ul class="ticks">
        <?php foreach (preg_split('/\r?\n/', $b['body']) as $line): if (trim($line) === '') continue; ?>
        <li><?= $e(preg_replace('/^[-•*]\s*/u', '', trim($line))) ?></li>
        <?php endforeach; ?>
    </ul>
    <div class="panel">
        <h3>Aceite da proposta</h3>
        <p>O aceite confirma o escopo, o investimento e o cronograma descritos neste documento.</p>
        <a class="cta" href="<?= $e($acceptLink) ?>">Aceitar ou responder esta proposta</a>
        <p style="margin-top:4mm;font-size:7.5pt;color:#9fc0c3;"><?= $e($acceptLink) ?></p>
    </div>
</div>
<?php endif; ?>

</body>
</html>
