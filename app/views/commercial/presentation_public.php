<?php
$appName = htmlspecialchars((string) Config::get('app_name') ?: 'ON Solutions', ENT_QUOTES);
$t = fn($text) => PresentationRules::linkify(htmlspecialchars(PresentationRules::applyVars((string)$text, $vars), ENT_QUOTES));
$p = fn($text) => nl2br($t($text));
$emblem = asset('on-emblem.png') . '?v=2';

// As divisórias não aparecem como bloco: viram o rótulo de "parte" da seção seguinte e o índice da capa.
$partOf = [];
$parts = [];
$pending = null;
foreach ($sections as $idx => $s) {
    if ($s['type'] === 'divider') { $pending = $s['title']; continue; }
    if ($pending !== null && $s['type'] !== 'hero') {
        $partOf[$idx] = $pending;
        $parts[] = ['idx' => $idx, 'title' => $pending];
        $pending = null;
    }
}
$hero = null;
foreach ($sections as $s) { if ($s['type'] === 'hero') { $hero = $s; break; } }
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $appName ?> · Apresentação</title>
    <?php if ($fav = Config::get('app_favicon')): ?><link rel="icon" href="<?= baseUrl($fav) ?>"><?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink:#0b1a24; --ink-2:#13303a; --text:#33434b; --muted:#6b7b83; --line:#e3e9ec;
            --bg:#ffffff; --bg-alt:#f5f8f9; --teal:#00bfa6; --teal-d:#0a8f7d; --mint:#5eead4;
            --sans:'Inter','Segoe UI',system-ui,Arial,sans-serif; --mono:'JetBrains Mono',ui-monospace,Consolas,monospace;
        }
        * { box-sizing:border-box; }
        html { scroll-behavior:smooth; scroll-padding-top:80px; }
        body { margin:0; background:var(--bg); color:var(--text); font-family:var(--sans); font-size:16px; line-height:1.65; -webkit-font-smoothing:antialiased; }
        a { color:var(--teal-d); font-weight:600; text-decoration:underline; text-decoration-color:rgba(10,143,125,.35); text-underline-offset:3px; }
        a:hover { text-decoration-color:currentColor; }
        .wrap { max-width:1280px; margin:0 auto; padding:0 56px; }

        .preview-bar { background:#fff3cd; color:#7a5b00; text-align:center; padding:8px; font-size:.85rem; }

        /* Barra superior */
        .topbar { position:sticky; top:0; z-index:20; background:rgba(11,26,36,.92); backdrop-filter:blur(10px); border-bottom:1px solid rgba(255,255,255,.08); }
        .topbar .wrap { display:flex; align-items:center; justify-content:space-between; height:64px; gap:24px; }
        .brand { display:flex; align-items:center; gap:10px; flex-shrink:0; white-space:nowrap; color:#fff; font-weight:700; letter-spacing:-.01em; text-decoration:none; }
        .brand img { width:30px; height:30px; }
        .current { font-family:var(--mono); font-size:.72rem; letter-spacing:.1em; text-transform:uppercase; color:var(--mint); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; opacity:0; transition:opacity .2s; }
        .current.on { opacity:1; }
        .progress { position:absolute; left:0; bottom:-1px; height:2px; width:0; background:linear-gradient(90deg, var(--teal), var(--mint)); }

        /* Capa */
        .hero { position:relative; overflow:hidden; color:#fff; padding:120px 0 110px;
            background:
                radial-gradient(ellipse 60% 70% at 88% 100%, rgba(20,150,125,.45) 0%, rgba(20,150,125,0) 70%),
                radial-gradient(ellipse 40% 50% at 10% 0%, rgba(94,234,212,.10) 0%, rgba(94,234,212,0) 70%),
                linear-gradient(160deg, #0b1a24 0%, #0d2a2f 40%, #0e4740 75%, #11665a 100%); }
        .hero::before { content:''; position:absolute; inset:0; pointer-events:none;
            background-image:linear-gradient(rgba(255,255,255,.04) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.04) 1px, transparent 1px);
            background-size:56px 56px; mask-image:linear-gradient(180deg, #000 0%, transparent 90%); -webkit-mask-image:linear-gradient(180deg, #000 0%, transparent 90%); }
        .hero .wrap { position:relative; display:grid; grid-template-columns:minmax(0,1.35fr) minmax(0,.9fr); gap:72px; align-items:center; }
        .hero-brand { display:flex; align-items:center; gap:22px; margin-bottom:28px; }
        .hero-brand img { width:96px; height:96px; flex:0 0 96px; filter:drop-shadow(0 0 24px rgba(94,234,212,.25)); }
        .hero h1 { margin:0; font-size:clamp(2.6rem, 5.2vw, 4.4rem); line-height:1.02; font-weight:800; letter-spacing:-.035em; color:#fff; }
        .hero .kicker { font-family:var(--mono); font-size:.95rem; color:var(--mint); letter-spacing:.02em; margin:0 0 28px; padding-left:16px; border-left:2px solid var(--teal); }
        .hero .lead { font-size:1.12rem; color:#c3d3d7; max-width:620px; margin:0; }
        .hero a { color:var(--mint); }
        .toc { background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.10); border-radius:10px; padding:26px 28px; backdrop-filter:blur(4px); }
        .toc h3 { margin:0 0 14px; font-size:.72rem; letter-spacing:.14em; text-transform:uppercase; color:#8fa8ae; font-weight:600; }
        .toc ol { list-style:none; margin:0; padding:0; counter-reset:toc; }
        .toc li a { display:flex; gap:16px; align-items:baseline; padding:12px 0; border-top:1px solid rgba(255,255,255,.08); color:#e7f0f1; font-weight:500; font-size:.95rem; text-decoration:none; transition:color .15s, padding .15s; }
        .toc li:first-child a { border-top:none; }
        .toc li a::before { counter-increment:toc; content:counter(toc, decimal-leading-zero); font-family:var(--mono); font-size:.78rem; color:var(--teal); }
        .toc li a:hover { color:var(--mint); padding-left:6px; }

        /* Seções */
        .sec { padding:96px 0; border-top:1px solid var(--line); background:var(--bg); position:relative; }
        .sec.alt { background:var(--bg-alt); }
        .sec .wrap { display:grid; grid-template-columns:300px minmax(0,1fr); gap:72px; }
        .sec-head { position:sticky; top:104px; align-self:start; }
        .part { display:inline-block; font-family:var(--mono); font-size:.7rem; font-weight:700; letter-spacing:.12em; text-transform:uppercase; color:var(--teal-d); margin-bottom:22px; padding-bottom:8px; border-bottom:1px solid var(--teal); }
        .num { display:block; font-family:var(--mono); font-weight:700; font-size:.9rem; color:var(--teal-d); margin-bottom:10px; }
        .num::before { content:''; display:inline-block; width:22px; height:2px; background:var(--teal); vertical-align:middle; margin-right:10px; }
        .sec h2 { margin:0; color:var(--ink); font-size:1.9rem; line-height:1.18; font-weight:700; letter-spacing:-.025em; }
        .sec-body > :first-child { margin-top:0; }
        .sub { font-size:1.2rem; line-height:1.55; color:var(--ink-2); font-weight:500; margin:0 0 20px; }
        .body { font-size:1.02rem; color:var(--text); max-width:820px; }
        .body + .block, .sub + .block { margin-top:32px; }

        /* Indicadores */
        .stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); border-top:1px solid var(--line); border-bottom:1px solid var(--line); }
        .stat { padding:30px 28px; border-left:1px solid var(--line); }
        .stat:first-child { border-left:none; padding-left:0; }
        .stat strong { display:block; font-size:3rem; line-height:1; font-weight:800; letter-spacing:-.04em; color:var(--ink); margin-bottom:10px; }
        .stat strong::after { content:''; display:block; width:28px; height:3px; background:var(--teal); margin-top:14px; }
        .stat span { color:var(--muted); font-size:.92rem; }

        /* Etapas */
        .steps { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:0; counter-reset:step; border-top:1px solid var(--line); }
        .step { padding:26px 24px 8px 0; position:relative; }
        .step::before { counter-increment:step; content:counter(step, decimal-leading-zero); display:block; font-family:var(--mono); font-weight:700; font-size:.85rem; color:var(--teal-d); margin-bottom:12px; }
        .step::after { content:''; position:absolute; top:-1px; left:0; width:40px; height:2px; background:var(--teal); }
        .step strong { display:block; color:var(--ink); font-size:1.05rem; margin-bottom:6px; }
        .step p { margin:0; color:var(--muted); font-size:.95rem; }

        /* Cards (malha de blocos) */
        .cards { display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:1px; background:var(--line); border:1px solid var(--line); border-radius:8px; overflow:hidden; }
        .card { background:var(--bg); padding:28px; position:relative; transition:background .2s; }
        .sec.alt .card { background:#fff; }
        .card::before { content:''; position:absolute; top:0; left:0; height:2px; width:0; background:var(--teal); transition:width .25s; }
        .card:hover::before { width:100%; }
        .card strong { display:block; color:var(--ink); font-size:1.05rem; margin-bottom:8px; letter-spacing:-.01em; }
        .card p { margin:0; color:var(--muted); font-size:.95rem; }

        /* Tabelas */
        .table-wrap { overflow-x:auto; border:1px solid var(--line); border-radius:8px; background:#fff; }
        table { width:100%; border-collapse:collapse; font-size:.95rem; }
        th { text-align:left; padding:14px 20px; font-size:.72rem; letter-spacing:.1em; text-transform:uppercase; font-weight:700; color:#fff; background:var(--ink); white-space:nowrap; }
        td { padding:16px 20px; border-top:1px solid var(--line); vertical-align:top; color:var(--text); }
        td:first-child { font-weight:600; color:var(--ink); }
        tbody tr:hover td { background:#f2fbf9; }
        table.cmp th:first-child { background:#e9eef0; color:var(--muted); }
        table.cmp th:last-child { background:var(--teal-d); }
        table.cmp td:first-child { font-weight:400; color:var(--muted); }

        /* Listas */
        ul.list { margin:0; padding:0; list-style:none; display:grid; grid-template-columns:repeat(auto-fit,minmax(300px,1fr)); gap:0 40px; }
        ul.list li { position:relative; padding:14px 0 14px 34px; border-bottom:1px solid var(--line); color:var(--ink-2); }
        ul.list li::before { content:''; position:absolute; left:0; top:17px; width:20px; height:20px; border-radius:50%; background:rgba(0,191,166,.12)
            url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='none' stroke='%230a8f7d' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round' d='M4 8.5l2.5 2.5L12 5.5'/%3E%3C/svg%3E") center/12px no-repeat; }
        ul.list li small { display:block; color:var(--muted); font-size:.88rem; margin-top:2px; }
        ul.list.dont li::before { background:rgba(214,69,69,.10)
            url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='none' stroke='%23d64545' stroke-width='2.2' stroke-linecap='round' d='M5 5l6 6M11 5l-6 6'/%3E%3C/svg%3E") center/11px no-repeat; }

        /* Observação */
        .note { margin-top:32px; display:grid; grid-template-columns:auto 1fr; gap:16px; background:#eefaf7; border-left:3px solid var(--teal); padding:20px 24px; font-size:.95rem; color:#24534c; border-radius:0 8px 8px 0; max-width:900px; }
        .note::before { content:'i'; display:flex; align-items:center; justify-content:center; width:22px; height:22px; border-radius:50%; border:1.5px solid var(--teal-d); color:var(--teal-d); font:700 .78rem/1 var(--mono); margin-top:2px; }
        .note a { color:#0b6b5e; }

        /* Fechamento */
        .sec.cta { background:linear-gradient(160deg, #0b1a24 0%, #0e4740 75%, #11665a 100%); color:#fff; border-top:none; }
        .sec.cta h2 { color:#fff; } .sec.cta .sub { color:var(--mint); } .sec.cta .body { color:#c3d3d7; } .sec.cta .num { color:var(--mint); }
        .sec.cta a { color:var(--mint); }
        .contacts { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:1px; background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.12); border-radius:8px; overflow:hidden; }
        .contacts div { background:#0d2a2f; padding:20px 22px; color:#fff; font-weight:500; }
        .contacts span { display:block; font-family:var(--mono); font-size:.7rem; color:#8fa8ae; text-transform:uppercase; letter-spacing:.1em; margin-bottom:6px; }

        /* Rodapé */
        .footer { background:var(--ink); color:#8fa8ae; padding:40px 0; font-size:.85rem; }
        .footer .wrap { display:flex; align-items:center; justify-content:space-between; gap:16px; }
        .footer .brand { font-size:.95rem; }

        /* Pré-visualização: seções ocultas */
        .sec[data-hidden] .wrap { opacity:.35; }
        .sec[data-hidden]::after { content:'Oculta para o cliente'; position:absolute; top:20px; right:24px; background:#6b7088; color:#fff; font-size:.72rem; padding:4px 10px; border-radius:6px; }

        @media (max-width:1100px) {
            .hero .wrap { grid-template-columns:minmax(0,1fr); gap:48px; }
            .sec .wrap { grid-template-columns:minmax(0,1fr); gap:28px; }
            .sec-head { position:static; }
        }
        @media (max-width:640px) {
            .wrap { padding:0 22px; }
            .hero { padding:72px 0; }
            .hero-brand { gap:14px; } .hero-brand img { width:64px; height:64px; flex-basis:64px; }
            .sec { padding:64px 0; }
            .sec h2 { font-size:1.5rem; }
            .stat { padding:22px 0; border-left:none; border-top:1px solid var(--line); } .stat:first-child { border-top:none; }
            .card { padding:22px; }
            .footer .wrap { flex-direction:column; text-align:center; }
        }
        @media print { .topbar, .preview-bar { display:none; } .sec-head { position:static; } .sec { break-inside:avoid; } }
    </style>
</head>
<body>
    <?php if ($isPreview): ?>
    <div class="preview-bar">Pré-visualização interna — <?= PresentationRules::isPublished($presentation) ? 'o link público está ativo.' : 'o link público ainda está desativado.' ?></div>
    <?php endif; ?>

    <header class="topbar">
        <div class="wrap">
            <a class="brand" href="#top"><img src="<?= $emblem ?>" alt=""><?= $hero ? $t($hero['title']) : $appName ?></a>
            <?php if ($parts): ?><span class="current" id="current"></span><?php endif; ?>
        </div>
        <div class="progress" id="progress"></div>
    </header>

    <main id="top">
        <?php $alt = false; ?>
        <?php foreach ($sections as $idx => $s): ?>
            <?php if ($s['type'] === 'divider') continue; ?>
            <?php $hiddenAttr = empty($s['visible']) ? ' data-hidden="1"' : ''; ?>

            <?php if ($s['type'] === 'hero'): ?>
            <section class="hero" id="sec-<?= (int)$idx ?>"<?= $hiddenAttr ?>>
                <div class="wrap">
                    <div>
                        <div class="hero-brand">
                            <img src="<?= $emblem ?>" alt="">
                            <h1><?= $t($s['title']) ?></h1>
                        </div>
                        <?php if ($s['subtitle'] !== ''): ?><p class="kicker"><?= $t($s['subtitle']) ?></p><?php endif; ?>
                        <?php if ($s['body'] !== ''): ?><p class="lead"><?= $p($s['body']) ?></p><?php endif; ?>
                    </div>
                    <?php if ($parts): ?>
                    <nav class="toc">
                        <h3>Conteúdo</h3>
                        <ol><?php foreach ($parts as $part): ?><li><a href="#sec-<?= (int)$part['idx'] ?>"><?= $t($part['title']) ?></a></li><?php endforeach; ?></ol>
                    </nav>
                    <?php endif; ?>
                </div>
            </section>
            <?php continue; endif; ?>

            <?php
            $num = '';
            $title = $s['title'];
            if (preg_match('/^(\d+)\.\s+(.+)$/u', $title, $m)) { $num = str_pad($m[1], 2, '0', STR_PAD_LEFT); $title = $m[2]; }
            $isCta = $s['type'] === 'cta';
            $classes = 'sec' . ($isCta ? ' cta' : ($alt ? ' alt' : ''));
            if (!$isCta) $alt = !$alt;
            ?>
            <section class="<?= $classes ?>" id="sec-<?= (int)$idx ?>"<?= $hiddenAttr ?>>
                <div class="wrap">
                    <div class="sec-head">
                        <?php if (isset($partOf[$idx])): ?><span class="part"><?= $t($partOf[$idx]) ?></span><?php endif; ?>
                        <?php if ($num !== ''): ?><span class="num"><?= $num ?></span><?php endif; ?>
                        <?php if ($title !== ''): ?><h2><?= $t($title) ?></h2><?php endif; ?>
                    </div>
                    <div class="sec-body">
                        <?php if ($s['subtitle'] !== ''): ?><p class="sub"><?= $t($s['subtitle']) ?></p><?php endif; ?>
                        <?php if ($s['body'] !== ''): ?><div class="body"><?= $p($s['body']) ?></div><?php endif; ?>

                        <?php if (!empty($s['items'])): ?>
                        <div class="block">
                            <?php if ($s['type'] === 'stats'): ?>
                            <div class="stats"><?php foreach ($s['items'] as $it): ?><div class="stat"><strong><?= $t($it['title']) ?></strong><span><?= $t($it['text']) ?></span></div><?php endforeach; ?></div>
                            <?php elseif ($s['type'] === 'steps'): ?>
                            <div class="steps"><?php foreach ($s['items'] as $it): ?><div class="step"><?php if ($it['title'] !== ''): ?><strong><?= $t($it['title']) ?></strong><?php endif; ?><p><?= $p($it['text']) ?></p></div><?php endforeach; ?></div>
                            <?php elseif ($s['type'] === 'cards'): ?>
                            <div class="cards"><?php foreach ($s['items'] as $it): ?><div class="card"><?php if ($it['title'] !== ''): ?><strong><?= $t($it['title']) ?></strong><?php endif; ?><p><?= $p($it['text']) ?></p></div><?php endforeach; ?></div>
                            <?php elseif ($s['type'] === 'table'): ?>
                            <?php $rows = array_map(fn($it) => array_merge([$it['title']], $it['text'] !== '' ? array_map('trim', explode('|', $it['text'])) : []), $s['items']); $head = array_shift($rows); ?>
                            <div class="table-wrap"><table>
                                <thead><tr><?php foreach ($head as $c): ?><th><?= $t($c) ?></th><?php endforeach; ?></tr></thead>
                                <tbody><?php foreach ($rows as $row): ?><tr><?php foreach ($row as $c): ?><td><?= $t($c) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody>
                            </table></div>
                            <?php elseif ($s['type'] === 'compare'): ?>
                            <div class="table-wrap"><table class="cmp">
                                <thead><tr><th>O que o mercado oferece</th><th>O que nós entregamos</th></tr></thead>
                                <tbody><?php foreach ($s['items'] as $it): ?><tr><td><?= $t($it['title']) ?></td><td><?= $t($it['text']) ?></td></tr><?php endforeach; ?></tbody>
                            </table></div>
                            <?php elseif ($s['type'] === 'list' || $s['type'] === 'dont'): ?>
                            <ul class="list<?= $s['type'] === 'dont' ? ' dont' : '' ?>"><?php foreach ($s['items'] as $it): ?><li><?= $t($it['title']) ?><?php if ($it['text'] !== ''): ?><small><?= $t($it['text']) ?></small><?php endif; ?></li><?php endforeach; ?></ul>
                            <?php elseif ($isCta): ?>
                            <div class="contacts"><?php foreach ($s['items'] as $it): ?><div><span><?= $t($it['title']) ?></span><?= $t($it['text']) ?></div><?php endforeach; ?></div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <?php if (($s['note'] ?? '') !== ''): ?><div class="note"><div><?= $p($s['note']) ?></div></div><?php endif; ?>
                    </div>
                </div>
            </section>
        <?php endforeach; ?>
    </main>

    <footer class="footer">
        <div class="wrap">
            <span class="brand"><img src="<?= $emblem ?>" alt=""><?= $hero ? $t($hero['title']) : $appName ?></span>
            <span>© <?= date('Y') ?> <?= $appName ?></span>
        </div>
    </footer>

    <script>
    (function () {
        var bar = document.getElementById('progress');
        var label = document.getElementById('current');
        var marks = Array.prototype.slice.call(document.querySelectorAll('.sec .part'));
        function onScroll() {
            var h = document.documentElement;
            var max = h.scrollHeight - h.clientHeight;
            bar.style.width = (max > 0 ? (h.scrollTop / max) * 100 : 0) + '%';
            if (!label) return;
            var current = null;
            marks.forEach(function (el) { if (el.closest('.sec').getBoundingClientRect().top < 140) current = el; });
            label.textContent = current ? current.textContent : '';
            label.classList.toggle('on', !!current);
        }
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    })();
    </script>
</body>
</html>
