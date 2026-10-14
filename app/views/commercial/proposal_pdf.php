<?php
// Documento HTML convertido em PDF pelo dompdf: CSS 2.1, tabelas no lugar de
// flex/grid e imagens por caminho local (dentro do chroot).
// O visual segue docs/proposta-modelo.pdf: capa clara, páginas institucionais
// e, a partir das informações da proposta, tabelas com cabeçalho preto e destaque verde.
$e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$brl = fn($v) => 'R$ ' . number_format((float)$v, 2, ',', '.');
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
$bullets = function ($text) {
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
        $p = trim($p);
        if ($p !== '') $out[] = $p;
    }
    return $out;
};
/** Separa um bloco em grupos. "---" divide; linhas "# Título" nomeiam o grupo. */
$groups = function (string $body) {
    $parts = preg_split('/\r?\n\s*---\s*\r?\n/', trim($body)) ?: [];
    $out = [];
    foreach ($parts as $part) {
        $lines = ProposalRules::blockLines($part);
        $title = '';
        $subtitle = '';
        $items = [];
        foreach ($lines as $l) {
            if (isset($l['title'][0]) && $l['title'][0] === '#' && $l['text'] === '') {
                $label = trim(substr($l['title'], 1));
                if ($title === '') $title = $label;
                else $subtitle = $label;
                continue;
            }
            $items[] = $l;
        }
        if ($title !== '' || $items) $out[] = ['title' => $title, 'subtitle' => $subtitle, 'lines' => $items];
    }
    return $out;
};
/** Parágrafos e seções "A Título" / "B Título" de um texto fixo. */
$lettered = function (string $body) {
    $out = [];
    foreach (preg_split('/\r?\n\s*\r?\n/', trim($body)) as $chunk) {
        $lines = [];
        foreach (preg_split('/\r?\n/', trim($chunk)) as $line) {
            $line = trim((string)preg_replace('/^[-•*]\s*/u', '', trim($line)));
            if ($line !== '') $lines[] = $line;
        }
        if (!$lines) continue;
        if (preg_match('/^([A-C])\s+(.{2,80})$/u', $lines[0], $m)) {
            array_shift($lines);
            $out[] = ['letter' => $m[1], 'title' => $m[2], 'lines' => $lines];
        } else {
            $out[] = ['letter' => '', 'title' => '', 'lines' => $lines];
        }
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

$about = $fixed('about');
$aboutParas = [];
$aboutCards = [];
if ($about) {
    foreach ($paras($about['body']) as $p) {
        $lines = ProposalRules::blockLines($p);
        $cards = [];
        $prose = [];
        foreach ($lines as $l) {
            if (preg_match('/^(\d{2})\s+(.+)$/u', $l['title'], $m)) {
                $cards[] = ['n' => $m[1], 'title' => $m[2], 'text' => $l['text']];
            } else {
                $prose[] = $l['text'] !== '' ? ($l['title'] . ': ' . $l['text']) : $l['title'];
            }
        }
        if ($cards && !$prose) {
            $aboutCards = array_merge($aboutCards, $cards);
        } else {
            $aboutParas[] = $p;
        }
    }
}

$business = array_filter([
    'Perfil da empresa' => $c('business_profile'),
    'Presença e canais atuais' => $c('business_channels'),
    'Oportunidades a validar no diagnóstico' => $c('business_opportunity'),
    'Validar no diagnóstico' => $c('business_diagnosis'),
], fn($v) => $v !== '');

$onceItems = array_values(array_filter($items, fn($it) => ($it['billing'] ?? 'once') !== 'monthly'));
$monthItems = array_values(array_filter($items, fn($it) => ($it['billing'] ?? 'once') === 'monthly'));
$expectedReturn = (float)($content['expected_return'] ?? 0);
$hasReturn = $expectedReturn > 0 || $c('return_text') !== '';

$origin = $timeline['start'] ?? null;
if (!$origin) {
    foreach ($items as $it) {
        if (!empty($it['schedule_start'])) { $origin = $it['schedule_start']; break; }
    }
}
$originMonth = $origin ? strtotime(date('Y-m-01', strtotime($origin))) : null;
$slot = function ($date) use ($originMonth) {
    if (!$date || !$originMonth) return null;
    $t = strtotime((string)$date);
    if (!$t) return null;
    $m = ((int)date('Y', $t) - (int)date('Y', $originMonth)) * 12 + ((int)date('n', $t) - (int)date('n', $originMonth));
    return max(0, min(11, $m));
};

$maxMonth = 0.0;
foreach ($projection['months'] as $m) $maxMonth = max($maxMonth, $m['invest'], $m['return']);

$client = trim((string)($proposal['client_name'] ?? ''));
$clientShort = $client !== '' ? $client : 'cliente';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 18mm 12mm 14mm 12mm; }
    @page :first { margin: 0; }
    * { box-sizing: border-box; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #24312e; line-height: 1.45; margin: 0; background: #f4f7f6; }
    h1, h2, h3, p { margin: 0; }
    .section { page-break-before: always; }

    .hdr { position: fixed; top: -18mm; left: -12mm; right: -12mm; height: 12mm; background: #0c1014; }
    .hdr table { width: 100%; border-collapse: collapse; }
    .hdr td { color: #d5e4e2; font-size: 8pt; vertical-align: middle; padding: 0 12mm; height: 11.2mm; }
    .hdr img { height: 5.5mm; vertical-align: middle; }
    .hdr .brand { color: #ffffff; font-weight: bold; font-size: 9pt; }
    .hdr .bar { position: absolute; left: 0; right: 0; bottom: 0; height: 0.8mm; background: #1ad4bc; }
    .ftr { position: fixed; bottom: -9mm; left: 0; right: 0; height: 6mm; font-size: 7pt; color: #8b9694; }

    .kicker { font-size: 8pt; font-weight: bold; letter-spacing: 1.4pt; text-transform: uppercase; color: #12b5a0; }
    h2 { font-size: 20pt; line-height: 1.15; color: #16191c; font-weight: bold; }
    .rule { width: 22mm; height: 1mm; background: #1ad4bc; margin: 3.5mm 0 6mm; }
    .fullrule { height: 0.7mm; background: #1ad4bc; margin: 2mm 0 5mm; }
    .aside { text-align: right; color: #12b5a0; font-size: 8pt; }
    .muted { color: #7d8b88; }
    .lead { font-size: 10pt; color: #3a4a46; line-height: 1.5; }

    /* Capa */
    .cover { width: 210mm; height: 296mm; background: #ffffff; color: #1a1d20; position: relative; }
    .cover .brand { position: absolute; left: 22mm; top: 78mm; }
    .cover .brand img { height: 8mm; vertical-align: middle; }
    .cover .brand span { font-size: 11pt; font-weight: bold; color: #1a1d20; vertical-align: middle; }
    .cover .mid { position: absolute; left: 22mm; right: 22mm; top: 98mm; }
    .cover h1 { font-size: 26pt; font-weight: normal; line-height: 1.15; color: #1a1d20; margin-top: 5mm; }
    .cover h1 strong { font-weight: bold; }
    .cover .sub { margin-top: 6mm; color: #8b9694; font-size: 10.5pt; line-height: 1.45; width: 130mm; }
    .cover .date { position: absolute; right: 16mm; bottom: 14mm; color: #9aa6a3; font-size: 9pt; }

    .mint { background: #e7f7f3; }
    .card { background: #e7f7f3; padding: 4mm 4.5mm; vertical-align: top; }
    .card .n { color: #12b5a0; font-weight: bold; font-size: 11pt; }
    .card h3 { font-size: 10.5pt; color: #16191c; margin: 1mm 0; }
    .card p { color: #4a5c58; font-size: 8.5pt; }
    .stat { text-align: center; vertical-align: top; }
    .stat b { display: block; font-size: 22pt; color: #16191c; line-height: 1; }
    .stat span { color: #7d8b88; font-size: 8pt; }
    .stat .tick { width: 10mm; height: 0.8mm; background: #1ad4bc; margin: 0 auto 2mm; }
    .pill { background: #e7f7f3; color: #16191c; text-align: center; padding: 2.5mm 4mm; border-radius: 8mm; }

    .panel { background: #e7f7f3; padding: 5mm; vertical-align: top; }
    .panel h3 { font-size: 13pt; color: #16191c; margin-bottom: 1mm; }
    .panel .sub { color: #5c6e6a; font-size: 8pt; margin-bottom: 3mm; }
    .dot { color: #1ad4bc; font-weight: bold; }
    table.items { width: 100%; border-collapse: collapse; }
    table.items td { border-top: 0.25mm solid #cfe4df; padding: 1.8mm 1mm; vertical-align: top; font-size: 8pt; }
    table.items td.t { font-weight: bold; color: #16191c; }
    table.items td.d { color: #5c6e6a; }

    .client { background: #f7fbfa; border: 0.3mm solid #e1eeeb; text-align: center; vertical-align: middle; height: 28mm; padding: 3mm; }
    .client b { font-size: 10pt; color: #16191c; }
    .client span { display: block; color: #7d8b88; font-size: 7.5pt; margin-top: 1mm; }
    .more { background: #d9f6ef; text-align: center; vertical-align: middle; height: 28mm; }
    .more b { display: block; font-size: 18pt; color: #16191c; }
    .more span { color: #12b5a0; font-size: 8.5pt; }

    .flow td { text-align: center; vertical-align: top; padding: 0 2mm; }
    .circ { width: 8mm; height: 8mm; border: 0.45mm solid #1ad4bc; border-radius: 4mm; text-align: center; color: #16191c; font-weight: bold; line-height: 7mm; margin: 0 auto 2mm; background: #ffffff; }
    .band { background: #d9f6ef; padding: 5mm 3mm 4mm; }
    .band .circ { background: #16191c; color: #ffffff; border-color: #16191c; }
    .band-title { font-size: 7.5pt; font-weight: bold; letter-spacing: 1pt; text-transform: uppercase; color: #12b5a0; margin-bottom: 4mm; }
    .note { text-align: center; color: #8b9694; font-size: 8pt; margin-top: 5mm; }

    /* Abertura da proposta */
    table.opener { width: 100%; border-collapse: collapse; }
    table.opener td.dark { background: #0c1014; color: #ffffff; vertical-align: top; padding: 8mm 7mm; width: 36%; height: 242mm; }
    table.opener td.sheet { background: #f4f7f6; vertical-align: top; padding: 6mm 6mm 4mm 6mm; }
    .dark .tag { color: #1ad4bc; font-size: 8pt; font-weight: bold; letter-spacing: 1.2pt; text-transform: uppercase; margin-top: 6mm; }
    .dark h2 { color: #ffffff; font-size: 16pt; margin-top: 2mm; }
    .dark .who { color: #ffffff; font-size: 13pt; font-weight: bold; margin-top: 1mm; }
    .dark .sub { color: #9ec9c2; font-size: 8pt; margin-top: 1mm; }
    .dark .bar { width: 18mm; height: 0.9mm; background: #1ad4bc; margin: 4mm 0; }
    table.info { width: 100%; border-collapse: collapse; margin-bottom: 5mm; }
    table.info td { border: 0.25mm solid #d5e0dc; padding: 2mm 2.5mm; font-size: 8pt; background: #ffffff; vertical-align: top; }
    table.info td.k { color: #7d8b88; width: 22%; }
    .comp { font-size: 7.5pt; font-weight: bold; letter-spacing: 0.8pt; text-transform: uppercase; color: #5c6e6a; margin: 2mm 0; }
    .barline { height: 2.2mm; background: #d7f3ee; margin: 1mm 0 2.5mm; }
    .barline div { height: 2.2mm; background: #1ad4bc; }
    .barline.dark div { background: #16191c; }

    /* Tabelas da proposta */
    table.sheet { width: 100%; border-collapse: collapse; margin-bottom: 4mm; }
    table.sheet th { background: #16191c; color: #ffffff; font-size: 8pt; font-weight: bold; text-align: left; padding: 2.2mm 2.5mm; }
    table.sheet td { background: #ffffff; border-bottom: 0.2mm solid #e3eeeb; padding: 2mm 2.5mm; font-size: 8.5pt; vertical-align: middle; }
    table.sheet td.r, table.sheet th.r { text-align: right; white-space: nowrap; }
    table.sheet tr.total td { background: #16191c; color: #1ad4bc; font-weight: bold; border-bottom: none; }
    table.sheet tr.total td.blk { color: #ffffff; }
    table.sheet tr.hi td { background: #e7f7f3; }
    .scope-h { border-bottom: 0.35mm solid #16191c; padding-bottom: 1.5mm; margin-top: 4mm; }
    .scope-h .n { color: #12b5a0; font-weight: bold; }
    .scope-h h3 { display: inline; font-size: 11pt; color: #16191c; }
    table.scope { width: 100%; border-collapse: collapse; margin: 0 0 1mm; }
    table.scope td { border-bottom: 0.2mm solid #e3eeeb; padding: 1.7mm 2mm; font-size: 8.5pt; background: #ffffff; }
    .foot { color: #8b9694; font-size: 7.5pt; margin-top: 3mm; }

    table.ticks, table.gantt { width: 100%; border-collapse: collapse; table-layout: fixed; }
    table.gantt td { font-size: 8pt; vertical-align: middle; padding: 1.6mm 0; }
    table.gantt td.name, table.ticks td.gap { width: 32%; padding-right: 2mm; color: #16191c; }
    .track { height: 4.2mm; background: #e7eeec; }
    .fill { height: 4.2mm; background: #1ad4bc; }
    .fill.m { background: #16191c; }
    .ticks td { text-align: center; font-size: 7pt; color: #8b9694; padding-bottom: 1mm; }

    table.chart { width: 100%; border-collapse: collapse; table-layout: fixed; }
    table.chart td.col { height: 36mm; vertical-align: bottom; text-align: center; padding: 0 0.6mm; border-bottom: 0.35mm solid #16191c; }
    table.chart td.lbl { text-align: center; font-size: 6.5pt; color: #7d8b88; padding-top: 1mm; }
    .b-inv { background: #1ad4bc; width: 42%; display: inline-block; }
    .b-ret { background: #16191c; width: 42%; display: inline-block; }

    .sign { margin-top: 8mm; }
    .sign .line { border-bottom: 0.3mm solid #16191c; height: 8mm; }
    .cta { display: inline-block; background: #1ad4bc; color: #0c1014; padding: 2.5mm 5mm; text-decoration: none; font-weight: bold; margin-top: 3mm; }
</style>
</head>
<body>

<div class="hdr">
    <table><tr>
        <td>
            <?php if ($emblemPath): ?><img src="<?= $e($emblemPath) ?>" alt=""><?php endif; ?>
            <span class="brand">&nbsp;<?= $e($appName) ?></span>
        </td>
        <td style="text-align:right;">Proposta comercial<?php if ($client !== ''): ?> &middot; <?= $e(mb_strimwidth($clientShort, 0, 42, '…')) ?><?php endif; ?></td>
    </tr></table>
    <div class="bar"></div>
</div>
<div class="ftr"><?= $e($appName) ?> &middot; Proposta comercial<?php if ($client !== ''): ?> &middot; <?= $e($clientShort) ?><?php endif; ?> &middot; Documento confidencial</div>

<!-- CAPA -->
<div class="cover">
    <div class="brand">
        <?php if ($emblemPath): ?><img src="<?= $e($emblemPath) ?>" alt=""><?php endif; ?>
        <span>&nbsp;<?= $e($appName) ?></span>
    </div>
    <div class="mid">
        <div class="kicker">Apresentação comercial</div>
        <h1>Estratégia e tecnologia<br><strong>aplicadas a resultados.</strong></h1>
        <div class="rule"></div>
        <p class="sub">Consultoria estratégica comercial, inteligência de mercado e soluções sob medida para empresas que querem crescer com clareza.</p>
    </div>
    <div class="date"><?= $e($monthYear($proposal['created_at'] ?? null)) ?></div>
</div>

<?php if ($about): ?>
<div class="section">
    <div class="kicker"><?= $e($about['title'] ?: 'Quem somos') ?></div>
    <h2>Estratégia e tecnologia<br>aplicadas a resultados.</h2>
    <div class="rule"></div>
    <table width="100%" cellspacing="0" cellpadding="0"><tr>
        <td width="48%" style="vertical-align:top;padding-right:5mm;">
            <?php foreach ($aboutParas as $p): ?><p class="lead" style="margin-bottom:4mm;"><?= nl2br($e($p)) ?></p><?php endforeach; ?>
        </td>
        <td width="52%" style="vertical-align:top;">
            <?php if ($aboutCards): ?>
            <table width="100%" cellspacing="0" cellpadding="0">
                <?php foreach ($aboutCards as $card): ?>
                <tr><td class="card" style="padding-bottom:4mm;">
                    <div><span class="n"><?= $e($card['n']) ?></span> <strong style="color:#16191c;font-size:10.5pt;"> <?= $e($card['title']) ?></strong></div>
                    <?php if ($card['text'] !== ''): ?><p style="margin-top:1mm;"><?= $e($card['text']) ?></p><?php endif; ?>
                </td></tr>
                <tr><td style="height:2.5mm;"></td></tr>
                <?php endforeach; ?>
            </table>
            <?php endif; ?>
        </td>
    </tr></table>
    <table width="100%" cellspacing="0" cellpadding="0" style="margin-top:8mm;"><tr>
        <td class="stat" width="33%"><div class="tick"></div><b>100+</b><span>projetos entregues</span></td>
        <td class="stat" width="33%"><div class="tick"></div><b>50+</b><span>clientes ativos</span></td>
        <td class="stat" width="33%"><div class="tick"></div><b>99%</b><span>de satisfação</span></td>
    </tr></table>
    <p class="pill" style="margin-top:6mm;">Atendemos clientes nacionais e internacionais</p>
</div>
<?php endif; ?>

<?php if ($b = $fixed('what_we_do')): $cols = $groups($b['body']); ?>
<div class="section">
    <div class="kicker"><?= $e($b['title'] ?: 'O que fazemos') ?></div>
    <h2>Estratégia e tecnologia, lado a lado.</h2>
    <div class="rule"></div>
    <table width="100%" cellspacing="0" cellpadding="0"><tr>
        <?php foreach ($cols as $i => $col): ?>
        <td class="panel" width="<?= count($cols) > 1 ? '50%' : '100%' ?>" <?= $i === 0 && count($cols) > 1 ? 'style="padding-right:3mm;"' : '' ?>>
            <?php if ($col['title'] !== ''): ?><div class="n" style="color:#12b5a0;font-weight:bold;"><?= sprintf('%02d', $i + 1) ?></div><h3><?= $e($col['title']) ?></h3><?php endif; ?>
            <?php if ($col['subtitle'] !== ''): ?><p class="sub"><?= $e($col['subtitle']) ?></p><?php endif; ?>
            <table class="items">
                <?php foreach ($col['lines'] as $l): ?>
                <tr>
                    <td class="t"><span class="dot">●</span> <?= $e($l['title']) ?></td>
                </tr>
                <?php if ($l['text'] !== ''): ?><tr><td class="d" style="border-top:none;padding-top:0;"><?= $e($l['text']) ?></td></tr><?php endif; ?>
                <?php endforeach; ?>
            </table>
        </td>
        <?php if ($i === 0 && count($cols) > 1): ?><td style="width:3mm;"></td><?php endif; ?>
        <?php endforeach; ?>
    </tr></table>
</div>
<?php endif; ?>

<?php if ($b = $fixed('clients')): $names = ProposalRules::blockLines($b['body']); ?>
<div class="section">
    <div class="kicker"><?= $e($b['title'] ?: 'Clientes') ?></div>
    <table width="100%" cellspacing="0" cellpadding="0"><tr>
        <td><h2>Quem confia na ON Solutions.</h2></td>
        <td style="text-align:right;vertical-align:bottom;" class="muted">Mais de 50 clientes ativos e mais de 100 projetos entregues.</td>
    </tr></table>
    <div class="rule"></div>
    <?php
        $cells = $names;
        $cells[] = ['title' => '__more__', 'text' => ''];
    ?>
    <table width="100%" cellspacing="0" cellpadding="0">
        <?php foreach (array_chunk($cells, 4) as $row): ?>
        <tr>
            <?php foreach ($row as $l): ?>
            <?php if ($l['title'] === '__more__'): ?>
            <td class="more" width="25%"><b>50+</b><span>clientes ativos<br>nacionais e internacionais</span></td>
            <?php else: ?>
            <td class="client" width="25%"><b><?= $e($l['title']) ?></b><?php if ($l['text'] !== ''): ?><span><?= $e($l['text']) ?></span><?php endif; ?></td>
            <?php endif; ?>
            <?php endforeach; ?>
            <?php for ($i = count($row); $i < 4; $i++): ?><td width="25%"></td><?php endfor; ?>
        </tr>
        <tr><td colspan="4" style="height:3mm;"></td></tr>
        <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>

<?php if ($b = $fixed('how_we_work')): $tracks = $groups($b['body']); ?>
<div class="section">
    <div class="kicker"><?= $e($b['title'] ?: 'Como fazemos') ?></div>
    <h2>Do entendimento ao resultado.</h2>
    <div class="rule"></div>
    <?php foreach ($tracks as $ti => $track): ?>
    <div class="<?= $ti > 0 ? 'band' : '' ?>" style="margin-bottom:5mm;">
        <?php if ($track['title'] !== ''): ?><div class="band-title"><?= $e($track['title']) ?></div><?php endif; ?>
        <table class="flow" width="100%" cellspacing="0" cellpadding="0"><tr>
            <?php foreach ($track['lines'] as $si => $l): ?>
            <td width="<?= max(1, (int)floor(100 / max(1, count($track['lines'])))) ?>%">
                <div class="circ"><?= $si + 1 ?></div>
                <strong><?= $e($l['title']) ?></strong>
                <?php if ($l['text'] !== ''): ?><p class="muted" style="margin-top:1mm;font-size:8pt;"><?= $e($l['text']) ?></p><?php endif; ?>
            </td>
            <?php endforeach; ?>
        </tr></table>
    </div>
    <?php endforeach; ?>
    <p class="note">Escopo, prazo e responsabilidade claros, do primeiro diagnóstico à evolução contínua.</p>
</div>
<?php endif; ?>

<!-- INFORMAÇÕES DA PROPOSTA -->
<div class="section">
    <table class="opener"><tr>
        <td class="dark">
            <?php if ($emblemPath): ?><img src="<?= $e($emblemPath) ?>" alt="" style="height:16mm;"><?php endif; ?>
            <div style="font-size:13pt;font-weight:bold;margin-top:3mm;"><?= $e($appName) ?></div>
            <div class="bar"></div>
            <div class="tag">Proposta comercial</div>
            <h2><?= $e($proposal['title']) ?></h2>
            <?php if ($client !== ''): ?>
            <div class="tag">Preparada para</div>
            <div class="who"><?= $e($client) ?></div>
            <div class="sub">
                <?= $e($proposal['client_email'] ?? '') ?>
                <?php if (!empty($proposal['client_phone'])): ?><br><?= $e($proposal['client_phone']) ?><?php endif; ?>
            </div>
            <?php endif; ?>
            <div class="tag">Data</div>
            <div class="who" style="font-size:11pt;"><?= $e($monthYear($proposal['created_at'] ?? null)) ?></div>
            <p style="margin-top:18mm;color:#6d7c78;font-size:8pt;">Documento confidencial</p>
        </td>
        <td class="sheet">
            <div class="comp">Informações da proposta</div>
            <table class="info">
                <tr>
                    <td class="k">Cliente</td><td><?= $e($client !== '' ? $client : 'A definir') ?></td>
                    <td class="k">Proponente</td><td><?= $e($appName) ?></td>
                </tr>
                <tr>
                    <td class="k">Projeto</td><td><?= $e($proposal['title']) ?></td>
                    <td class="k">Validade</td><td><?= !empty($proposal['validity_date']) ? $e($brDate($proposal['validity_date'])) : 'A combinar' ?></td>
                </tr>
                <tr>
                    <td class="k">Implantação</td>
                    <td><?= (!empty($timeline['start']) && !empty($timeline['end'])) ? $e($brDate($timeline['start']) . ' a ' . $brDate($timeline['end'])) : 'A definir' ?></td>
                    <td class="k">Mensalidade</td>
                    <td><?= $monthItems ? $e(implode(', ', array_map(fn($it) => $it['description'], $monthItems))) : 'Não inclusa' ?></td>
                </tr>
            </table>

            <?php if ($onceItems && $totals['once'] > 0): ?>
            <div class="comp">Composição da implantação</div>
            <?php foreach ($onceItems as $it):
                $pct = $totals['once'] > 0 ? round((float)$it['amount'] / $totals['once'] * 100) : 0;
            ?>
            <table width="100%" cellspacing="0" cellpadding="0"><tr>
                <td style="font-size:8.5pt;"><?= $e($it['description']) ?></td>
                <td style="text-align:right;font-size:8.5pt;width:16mm;"><?= (int)$pct ?>%</td>
            </tr></table>
            <div class="barline <?= $pct >= 40 ? 'dark' : '' ?>"><div style="width:<?= max(2, (int)$pct) ?>%;"></div></div>
            <?php endforeach; ?>
            <?php endif; ?>

            <p class="foot">Valores em reais nas páginas de investimento.<?= !empty($proposal['observations']) ? ' ' . $e($proposal['observations']) : '' ?></p>
        </td>
    </tr></table>
</div>

<?php if ($business): $n = 0; ?>
<div class="section">
    <table width="100%"><tr>
        <td><h2>Entendimento do negócio</h2></td>
        <td class="aside"><?= $e($client !== '' ? $client : 'Informações da proposta') ?></td>
    </tr></table>
    <div class="fullrule"></div>
    <?php foreach ($business as $label => $text): $n++; ?>
    <div class="scope-h"><span class="n"><?= sprintf('%02d', $n) ?></span> <h3><?= $e($label) ?></h3></div>
    <table class="scope">
        <?php foreach ($bullets($text) as $line): ?>
        <tr><td>&bull; <?= $e($line) ?></td></tr>
        <?php endforeach; ?>
    </table>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php
$scopeBlocks = [];
if ($onceItems) $scopeBlocks[] = ['title' => 'Escopo dos serviços — Implantação', 'tag' => 'Pagamento único', 'items' => $onceItems];
if ($monthItems) $scopeBlocks[] = ['title' => 'Escopo dos serviços — Acompanhamento mensal', 'tag' => 'Recorrente', 'items' => $monthItems];
$scopeN = 0;
foreach ($scopeBlocks as $block):
?>
<div class="section">
    <table width="100%"><tr>
        <td><h2><?= $e($block['title']) ?></h2></td>
        <td class="aside"><?= $e($block['tag']) ?></td>
    </tr></table>
    <div class="fullrule"></div>
    <?php foreach ($block['items'] as $it): $scopeN++; $lines = $bullets($it['scope'] ?? ''); ?>
    <table width="100%" class="scope-h" cellspacing="0" cellpadding="0"><tr>
        <td><span class="n"><?= sprintf('%02d', $scopeN) ?></span> <h3><?= $e($it['description']) ?></h3></td>
    </tr></table>
    <?php if ($lines): ?>
    <table class="scope">
        <?php foreach ($lines as $line): ?><tr><td>&bull; <?= $e($line) ?></td></tr><?php endforeach; ?>
    </table>
    <?php endif; ?>
    <?php endforeach; ?>
    <p class="foot">Os valores de cada item estão na página de Investimento.</p>
</div>
<?php endforeach; ?>

<?php if (!$items): ?>
<div class="section">
    <h2>Escopo dos serviços</h2>
    <div class="fullrule"></div>
    <p class="muted">Nenhum serviço selecionado nesta proposta.</p>
</div>
<?php endif; ?>

<div class="section">
    <table width="100%"><tr>
        <td><h2>Cronograma de etapas</h2></td>
        <td class="aside">Projeção de 12 meses</td>
    </tr></table>
    <div class="fullrule"></div>
    <?php if (!$timeline['rows']): ?>
        <p class="muted">O cronograma é definido pelas datas de cada serviço.</p>
    <?php else: ?>
    <table class="ticks" width="100%" cellspacing="0" cellpadding="0"><tr>
        <td class="gap"></td>
        <?php for ($i = 1; $i <= 12; $i++): ?><td><?= $i ?></td><?php endfor; ?>
    </tr></table>
    <table class="gantt">
        <?php foreach ($timeline['rows'] as $row):
            $a = $slot($row['start']);
            $b = !empty($row['ongoing']) ? 11 : $slot($row['end'] ?: $row['start']);
            if ($a === null && $b !== null) $a = $b;
            if ($b === null && $a !== null) $b = $a;
            $left = $a !== null ? $a / 12 * 100 : 0;
            $width = ($a !== null && $b !== null) ? max(6, ($b - $a + 1) / 12 * 100) : 0;
            $monthly = !empty($row['ongoing']) || (isset($row['billing']) && $row['billing'] === 'monthly');
        ?>
        <tr>
            <td class="name"><?= $e($row['description']) ?></td>
            <td>
                <?php if ($width > 0): ?>
                <div class="track"><div class="fill<?= $monthly ? ' m' : '' ?>" style="margin-left:<?= round($left, 2) ?>%;width:<?= round(min($width, 100 - $left), 2) ?>%;"></div></div>
                <?php else: ?><span class="muted">A definir</span><?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php if (!empty($timeline['start']) && !empty($timeline['end'])): ?>
    <p class="foot">Período: <?= $e($brDate($timeline['start'])) ?> a <?= $e($brDate($timeline['end'])) ?><?php if ($timeline['days'] > 0): ?> (<?= (int)$timeline['days'] ?> dias)<?php endif; ?>. Serviços mensais seguem até o 12º mês.</p>
    <?php endif; ?>
    <?php endif; ?>
</div>

<div class="section">
    <table width="100%"><tr>
        <td><h2>Investimento</h2></td>
        <td class="aside">Valores em reais</td>
    </tr></table>
    <div class="fullrule"></div>

    <?php if ($onceItems): ?>
    <table class="sheet">
        <thead><tr><th>Implantação (pagamento único)</th><th class="r">Valor</th></tr></thead>
        <tbody>
        <?php foreach ($onceItems as $it): ?>
            <tr><td><?= $e($it['description']) ?></td><td class="r"><?= $brl($it['amount']) ?></td></tr>
        <?php endforeach; ?>
            <tr class="total"><td class="blk">Total da implantação</td><td class="r"><?= $brl($totals['once']) ?></td></tr>
        </tbody>
    </table>
    <?php endif; ?>

    <?php if ($monthItems): ?>
    <table class="sheet">
        <thead><tr><th>Mensalidade</th><th class="r">Valor mensal</th></tr></thead>
        <tbody>
        <?php foreach ($monthItems as $it): ?>
            <tr><td><?= $e($it['description']) ?></td><td class="r"><?= $brl($it['amount']) ?></td></tr>
        <?php endforeach; ?>
            <tr class="total"><td class="blk">Total da mensalidade</td><td class="r"><?= $brl($totals['monthly']) ?></td></tr>
        </tbody>
    </table>
    <?php endif; ?>

    <?php if (!$items): ?><p class="muted">Nenhum item de investimento nesta proposta.</p><?php endif; ?>
    <p class="foot">Condição de pagamento: <?= $e($vars['condicao_pagamento']) ?>. O detalhamento está na página de Condições de pagamento e rescisão.</p>
</div>

<div class="section">
    <table width="100%"><tr>
        <td><h2>Projeção de 12 meses</h2></td>
        <td class="aside">Investimento mês a mês</td>
    </tr></table>
    <div class="fullrule"></div>
    <p class="lead" style="margin-bottom:3mm;">Distribuição do investimento<?= $expectedReturn > 0 ? ', comparada ao retorno estimado' : '' ?>.</p>
    <table class="chart">
        <tr>
            <?php foreach ($projection['months'] as $m):
                $hi = $maxMonth > 0 ? max(0.6, $m['invest'] / $maxMonth * 32) : 0;
                $hr = $maxMonth > 0 && $m['return'] > 0 ? max(0.6, $m['return'] / $maxMonth * 32) : 0;
            ?>
            <td class="col">
                <?php if ($m['invest'] > 0): ?><div class="b-inv" style="height:<?= round($hi, 2) ?>mm;"></div><?php endif; ?>
                <?php if ($hr > 0): ?><div class="b-ret" style="height:<?= round($hr, 2) ?>mm;"></div><?php endif; ?>
            </td>
            <?php endforeach; ?>
        </tr>
        <tr><?php foreach ($projection['months'] as $m): ?><td class="lbl"><?= $e($m['label']) ?></td><?php endforeach; ?></tr>
    </table>
    <p class="foot" style="margin-bottom:3mm;"><span style="color:#1ad4bc;">■</span> Investimento<?php if ($expectedReturn > 0): ?> &nbsp; <span style="color:#16191c;">■</span> Retorno estimado<?php endif; ?></p>
    <table class="sheet">
        <thead><tr>
            <th>Mês</th><th class="r">Investimento</th>
            <?php if ($expectedReturn > 0): ?><th class="r">Retorno</th><?php endif; ?>
            <th class="r">Investido acumulado</th>
            <?php if ($expectedReturn > 0): ?><th class="r">Retorno acumulado</th><?php endif; ?>
        </tr></thead>
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
            <tr class="total">
                <td class="blk">12 meses</td>
                <td class="r"><?= $brl($projection['invest']) ?></td>
                <?php if ($expectedReturn > 0): ?><td class="r"><?= $brl($projection['return']) ?></td><?php endif; ?>
                <td class="r"><?= $brl($projection['invest']) ?></td>
                <?php if ($expectedReturn > 0): ?><td class="r"><?= $brl($projection['return']) ?></td><?php endif; ?>
            </tr>
        </tbody>
    </table>
</div>

<?php if ($hasReturn): ?>
<div class="section">
    <table width="100%"><tr>
        <td><h2>Previsibilidade do retorno</h2></td>
        <td class="aside">Ponto de equilíbrio</td>
    </tr></table>
    <div class="fullrule"></div>
    <?php if ($expectedReturn > 0): ?>
    <table class="sheet">
        <thead><tr><th></th><th class="r">Valor</th></tr></thead>
        <tbody>
            <tr><td>Retorno mensal estimado, a partir do mês seguinte à entrega</td><td class="r"><?= $brl($expectedReturn) ?></td></tr>
            <tr><td>Mês em que o retorno acumulado cobre o investido</td><td class="r"><?= $projection['payback'] ? 'Mês ' . (int)$projection['payback'] : 'Após 12 meses' ?></td></tr>
            <tr class="hi"><td>Resultado em 12 meses</td><td class="r"><?= $projection['roi'] !== null ? number_format($projection['roi'], 1, ',', '.') . '%' : '—' ?> (<?= $brl($projection['return'] - $projection['invest']) ?>)</td></tr>
        </tbody>
    </table>
    <?php endif; ?>
    <?php if ($c('return_text') !== ''): ?><div class="lead" style="margin-top:4mm;"><?php foreach ($paras($c('return_text')) as $p): ?><p style="margin-bottom:2mm;"><?= nl2br($e($p)) ?></p><?php endforeach; ?></div><?php endif; ?>
    <p class="foot">O cálculo considera o investimento desta proposta. Premissas de retorno são estimativas e serão ajustadas com os números reais da empresa. Não representam promessa de resultado.</p>
</div>
<?php endif; ?>

<?php if ($c('renewal_text') !== '' || $onceItems): ?>
<div class="section">
    <table width="100%"><tr>
        <td><h2>Renovações e serviços pontuais</h2></td>
        <td class="aside">Valores em reais</td>
    </tr></table>
    <div class="fullrule"></div>
    <?php if ($c('renewal_text') !== ''): ?>
        <?php foreach ($paras($c('renewal_text')) as $p): ?><p class="lead" style="margin-bottom:3mm;"><?= nl2br($e($p)) ?></p><?php endforeach; ?>
    <?php endif; ?>
    <?php if ($onceItems): ?>
    <table class="sheet">
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
    <table width="100%"><tr>
        <td><h2>Resumo final</h2></td>
        <td class="aside">Valores em reais</td>
    </tr></table>
    <div class="fullrule"></div>
    <table class="sheet">
        <thead><tr>
            <th></th><th class="r">Implantação</th><th class="r">Mensalidade</th><th class="r">Primeiro ano</th><th class="r">Prazo</th>
        </tr></thead>
        <tbody>
            <tr class="hi">
                <td>Esta proposta</td>
                <td class="r"><?= $brl($totals['once']) ?></td>
                <td class="r"><?= $brl($totals['monthly']) ?></td>
                <td class="r"><?= $brl($totals['year']) ?></td>
                <td class="r"><?= $timeline['days'] > 0 ? (int)$timeline['days'] . ' dias' : 'A definir' ?></td>
            </tr>
        </tbody>
    </table>
    <?php if ($c('summary_text') !== ''): ?><p class="lead" style="margin-top:4mm;"><?= nl2br($e($c('summary_text'))) ?></p><?php endif; ?>
</div>

<?php if ($b = $fixed('payment')): ?>
<div class="section">
    <table width="100%"><tr>
        <td><h2><?= $e($b['title'] ?: 'Condições de pagamento e rescisão') ?></h2></td>
        <td class="aside">Valores em reais</td>
    </tr></table>
    <div class="fullrule"></div>
    <table class="sheet" style="margin-bottom:5mm;">
        <thead><tr><th>Condição</th><th class="r">Valor</th></tr></thead>
        <tbody>
            <tr><td>Implantação (pagamento único)</td><td class="r"><?= $brl($totals['once']) ?></td></tr>
            <tr><td>Mensalidade</td><td class="r"><?= $brl($totals['monthly']) ?></td></tr>
            <tr><td>Condição de pagamento</td><td class="r"><?= $e($vars['condicao_pagamento']) ?></td></tr>
            <tr class="total"><td class="blk">Total do primeiro ano</td><td class="r"><?= $brl($totals['year']) ?></td></tr>
        </tbody>
    </table>
    <?php foreach ($lettered($b['body']) as $sec): ?>
        <?php if ($sec['letter'] === ''): ?>
            <?php foreach ($sec['lines'] as $line): ?><p class="lead" style="margin-bottom:2mm;"><?= $e($line) ?></p><?php endforeach; ?>
        <?php else: ?>
            <div class="scope-h" style="margin-top:4mm;"><h3><?= $e($sec['letter']) ?> <?= $e($sec['title']) ?></h3></div>
            <?php if ($sec['lines']): ?>
            <table class="scope">
                <?php foreach ($sec['lines'] as $line): ?><tr><td>&bull; <?= $e($line) ?></td></tr><?php endforeach; ?>
            </table>
            <?php endif; ?>
        <?php endif; ?>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($b = $fixed('notes')): ?>
<div class="section">
    <h2><?= $e($b['title'] ?: 'Notas e condições') ?></h2>
    <div class="fullrule"></div>
    <?php foreach ($lettered($b['body']) as $sec): ?>
        <?php if ($sec['letter'] === ''): ?>
            <table class="scope">
                <?php foreach ($sec['lines'] as $line): ?><tr><td>&bull; <?= $e($line) ?></td></tr><?php endforeach; ?>
            </table>
        <?php else: ?>
            <div class="scope-h"><h3><?= $e($sec['letter']) ?> <?= $e($sec['title']) ?></h3></div>
            <table class="scope">
                <?php foreach ($sec['lines'] as $line): ?><tr><td>&bull; <?= $e($line) ?></td></tr><?php endforeach; ?>
            </table>
        <?php endif; ?>
    <?php endforeach; ?>

    <h3 style="margin-top:7mm;font-size:12pt;">Aceite</h3>
    <p class="lead" style="margin:2mm 0 4mm;">O aceite confirma o escopo, o investimento e o cronograma descritos neste documento.</p>
    <table class="sign" width="100%" cellspacing="0" cellpadding="0"><tr>
        <td width="42%"><div class="line"></div><p class="foot"><?= $e($client !== '' ? $client : 'Contratante') ?> (contratante)</p></td>
        <td width="6%"></td>
        <td width="42%"><div class="line"></div><p class="foot"><?= $e($appName) ?> (contratada)</p></td>
    </tr></table>
    <p style="margin-top:5mm;"><a class="cta" href="<?= $e($acceptLink) ?>">Aceitar ou responder esta proposta</a></p>
    <p class="foot"><?= $e($acceptLink) ?></p>
</div>
<?php endif; ?>

</body>
</html>
