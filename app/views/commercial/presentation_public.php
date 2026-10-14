<?php
$appName = htmlspecialchars((string) Config::get('app_name') ?: 'ON Solutions', ENT_QUOTES);
$t = fn($text) => htmlspecialchars(PresentationRules::applyVars((string)$text, $vars), ENT_QUOTES);
$p = fn($text) => nl2br($t($text));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $appName ?> · Apresentação</title>
    <?php if ($fav = Config::get('app_favicon')): ?><link rel="icon" href="<?= baseUrl($fav) ?>"><?php endif; ?>
    <style>
        * { box-sizing:border-box; }
        body { margin:0; background:#eef0f6; color:#2b2f45; font-family:'Segoe UI',system-ui,Arial,sans-serif; line-height:1.55; }
        .preview-bar { position:sticky; top:0; z-index:5; background:#fff3cd; color:#7a5b00; text-align:center; padding:8px; font-size:.85rem; }
        .deck { max-width:960px; margin:0 auto; padding:24px 16px 48px; }
        .slide { background:#fff; border-radius:14px; box-shadow:0 8px 30px rgba(30,40,90,.08); padding:48px 56px; margin-bottom:24px; }
        h1, h2 { color:#34407a; margin:0 0 .3em; letter-spacing:-.01em; }
        h2 { font-size:1.7rem; }
        .sub { color:#5f6890; font-size:1.05rem; margin:0 0 1.2em; }
        .body { font-size:1rem; color:#3c4262; }
        .hero {
            text-align:center; padding:88px 56px; color:#fff;
            background:
                radial-gradient(ellipse at 85% 110%, rgba(20,130,110,.55) 0%, rgba(20,130,110,0) 60%),
                linear-gradient(160deg, #0b1a24 0%, #0d2a2f 35%, #0e4740 70%, #11665a 100%);
        }
        .hero-brand { display:flex; align-items:center; justify-content:center; gap:16px; margin-bottom:.15em; }
        .hero-brand .emblem { width:78px; height:78px; margin:0; flex:0 0 78px; }
        .hero-brand h1 { margin:0; }
        .hero h1 { font-size:3rem; color:#fff; }
        .hero .sub { font-size:1.3rem; font-weight:600; color:#5eead4; }
        .hero hr { border:none; border-top:1px solid rgba(255,255,255,.15); width:70%; margin:22px auto; }
        .hero .body { max-width:620px; margin:0 auto; color:#c7d6d9; font-size:1.05rem; }
        .stats { display:flex; gap:16px; flex-wrap:wrap; margin-top:12px; }
        .stat { flex:1 1 180px; background:#f4f6fb; border-radius:12px; padding:22px; text-align:center; }
        .stat strong { display:block; font-size:2.2rem; color:#00a892; line-height:1.1; }
        .stat span { color:#5f6890; }
        .steps { display:grid; grid-template-columns:repeat(auto-fit,minmax(190px,1fr)); gap:16px; margin-top:12px; counter-reset:step; }
        .step { border-top:3px solid #00BFA6; background:#f8f9fc; border-radius:0 0 10px 10px; padding:16px; }
        .step::before { counter-increment:step; content:counter(step, decimal-leading-zero); display:block; color:#00a892; font-weight:700; font-size:.85rem; margin-bottom:4px; }
        .step strong { display:block; color:#34407a; margin-bottom:4px; }
        .cards { display:grid; grid-template-columns:repeat(auto-fit,minmax(250px,1fr)); gap:14px; margin-top:12px; }
        .card { border:1px solid #e3e6f0; border-radius:12px; padding:18px; }
        .card strong { display:block; color:#34407a; margin-bottom:6px; }
        .card p, .step p { margin:0; color:#5a6185; font-size:.93rem; }
        table.cmp { width:100%; border-collapse:collapse; margin-top:12px; }
        table.cmp th { text-align:left; padding:12px; font-size:.9rem; }
        table.cmp th:first-child { background:#f1f2f6; color:#6b7088; }
        table.cmp th:last-child { background:#00BFA6; color:#fff; }
        table.cmp td { padding:12px; border-bottom:1px solid #e6e8f0; vertical-align:top; font-size:.93rem; }
        table.cmp td:first-child { color:#7a7f98; }
        table.cmp td:last-child { color:#2b2f45; font-weight:500; }
        ul.list { margin:12px 0 0; padding:0; list-style:none; display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:8px 20px; }
        ul.list li { padding-left:26px; position:relative; }
        ul.list li::before { content:'✓'; position:absolute; left:0; color:#00a892; font-weight:700; }
        ul.list li small { display:block; color:#6b7088; }
        ul.list.dont li::before { content:'✗'; color:#d64545; }
        table.grid { width:100%; border-collapse:collapse; margin-top:12px; }
        table.grid th { text-align:left; padding:12px; font-size:.9rem; background:#34407a; color:#fff; }
        table.grid td { padding:12px; border-bottom:1px solid #e6e8f0; vertical-align:top; font-size:.93rem; }
        table.grid td:first-child { font-weight:600; color:#34407a; }
        table.grid tr:nth-child(even) td { background:#f8f9fc; }
        .note { margin-top:18px; background:#e9f8f5; border-left:4px solid #00BFA6; border-radius:8px; padding:14px 16px; font-size:.93rem; color:#245a52; }
        .cta { background:#34407a; color:#fff; text-align:center; }
        .cta h2 { color:#fff; } .cta .body, .cta .sub { color:#d7dbf0; }
        .contacts { display:flex; justify-content:center; gap:28px; flex-wrap:wrap; margin-top:22px; }
        .contacts div { font-size:.95rem; } .contacts span { display:block; font-size:.78rem; color:#aeb5da; text-transform:uppercase; letter-spacing:.05em; }
        .slide[data-hidden] { opacity:.35; position:relative; }
        .slide[data-hidden]::after { content:'Oculta para o cliente'; position:absolute; top:12px; right:16px; background:#6b7088; color:#fff; font-size:.72rem; padding:3px 8px; border-radius:6px; }
        .ft { text-align:center; font-size:.78rem; color:#7b819c; }
        @media (max-width:640px) { .slide { padding:32px 22px; } .hero { padding:48px 22px; } .hero h1 { font-size:2rem; } .hero-brand .emblem { width:56px; height:56px; flex-basis:56px; } h2 { font-size:1.4rem; } }
    </style>
</head>
<body>
    <?php if ($isPreview): ?>
    <div class="preview-bar">Pré-visualização interna — <?= PresentationRules::isPublished($presentation) ? 'o link público está ativo.' : 'o link público ainda está desativado.' ?></div>
    <?php endif; ?>
    <div class="deck">
        <?php foreach ($sections as $idx => $s): ?>
            <?php if ($s['type'] === 'divider') continue; ?>
            <?php $secAttr = 'id="sec-' . (int)$idx . '"' . (empty($s['visible']) ? ' data-hidden="1"' : ''); ?>
            <?php if ($s['type'] === 'hero'): ?>
            <section class="slide hero" <?= $secAttr ?>>
                <div class="hero-brand">
                    <img class="emblem" src="<?= asset('on-emblem.png') ?>" alt="">
                    <h1><?= $t($s['title']) ?></h1>
                </div>
                <?php if ($s['subtitle'] !== ''): ?><p class="sub"><?= $t($s['subtitle']) ?></p><?php endif; ?>
                <?php if ($s['body'] !== ''): ?><hr><div class="body"><?= $p($s['body']) ?></div><?php endif; ?>
            </section>
            <?php else: ?>
            <section class="slide <?= $s['type'] === 'cta' ? 'cta' : '' ?>" <?= $secAttr ?>>
                <?php if ($s['title'] !== ''): ?><h2><?= $t($s['title']) ?></h2><?php endif; ?>
                <?php if ($s['subtitle'] !== ''): ?><p class="sub"><?= $t($s['subtitle']) ?></p><?php endif; ?>
                <?php if ($s['body'] !== ''): ?><div class="body"><?= $p($s['body']) ?></div><?php endif; ?>

                <?php if (!empty($s['items'])): ?>
                    <?php if ($s['type'] === 'stats'): ?>
                    <div class="stats"><?php foreach ($s['items'] as $it): ?><div class="stat"><strong><?= $t($it['title']) ?></strong><span><?= $t($it['text']) ?></span></div><?php endforeach; ?></div>
                    <?php elseif ($s['type'] === 'steps'): ?>
                    <div class="steps"><?php foreach ($s['items'] as $it): ?><div class="step"><?php if ($it['title'] !== ''): ?><strong><?= $t($it['title']) ?></strong><?php endif; ?><p><?= $p($it['text']) ?></p></div><?php endforeach; ?></div>
                    <?php elseif ($s['type'] === 'cards'): ?>
                    <div class="cards"><?php foreach ($s['items'] as $it): ?><div class="card"><?php if ($it['title'] !== ''): ?><strong><?= $t($it['title']) ?></strong><?php endif; ?><p><?= $p($it['text']) ?></p></div><?php endforeach; ?></div>
                    <?php elseif ($s['type'] === 'table'): ?>
                    <?php $rows = array_map(fn($it) => array_merge([$it['title']], $it['text'] !== '' ? array_map('trim', explode('|', $it['text'])) : []), $s['items']); $head = array_shift($rows); ?>
                    <table class="grid">
                        <thead><tr><?php foreach ($head as $c): ?><th><?= $t($c) ?></th><?php endforeach; ?></tr></thead>
                        <tbody><?php foreach ($rows as $row): ?><tr><?php foreach ($row as $c): ?><td><?= $t($c) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody>
                    </table>
                    <?php elseif ($s['type'] === 'dont'): ?>
                    <ul class="list dont"><?php foreach ($s['items'] as $it): ?><li><?= $t($it['title']) ?><?php if ($it['text'] !== ''): ?><small><?= $t($it['text']) ?></small><?php endif; ?></li><?php endforeach; ?></ul>
                    <?php elseif ($s['type'] === 'compare'): ?>
                    <table class="cmp">
                        <thead><tr><th>O que o mercado oferece</th><th>O que nós entregamos</th></tr></thead>
                        <tbody><?php foreach ($s['items'] as $it): ?><tr><td><?= $t($it['title']) ?></td><td><?= $t($it['text']) ?></td></tr><?php endforeach; ?></tbody>
                    </table>
                    <?php elseif ($s['type'] === 'list'): ?>
                    <ul class="list"><?php foreach ($s['items'] as $it): ?><li><?= $t($it['title']) ?><?php if ($it['text'] !== ''): ?><small><?= $t($it['text']) ?></small><?php endif; ?></li><?php endforeach; ?></ul>
                    <?php elseif ($s['type'] === 'cta'): ?>
                    <div class="contacts"><?php foreach ($s['items'] as $it): ?><div><span><?= $t($it['title']) ?></span><?= $t($it['text']) ?></div><?php endforeach; ?></div>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if (($s['note'] ?? '') !== ''): ?><div class="note"><?= $p($s['note']) ?></div><?php endif; ?>
            </section>
            <?php endif; ?>
        <?php endforeach; ?>
        <div class="ft"><?= $appName ?></div>
    </div>
</body>
</html>
