<?php
$brandName = 'ON Solutions';
$appName = htmlspecialchars($brandName, ENT_QUOTES);
$t = fn($text) => PresentationRules::linkify(htmlspecialchars(PresentationRules::applyVars((string)$text, $vars), ENT_QUOTES));
$p = fn($text) => nl2br($t($text));
$emblem = asset('on-emblem.png') . '?v=2';

// Índice da capa: uma entrada por capítulo (Quem somos, O que fazemos, Clientes, Como fazemos).
$partOf = [];
$parts = [];
$seen = [];
foreach ($sections as $idx => $s) {
    if (in_array($s['type'], ['hero', 'divider', 'stats'], true)) continue;
    if (!preg_match('/^(Quem somos|O que fazemos|Clientes|Como fazemos)/iu', (string)$s['title'], $m)) continue;
    $key = mb_strtolower($m[1]);
    if (isset($seen[$key])) continue;
    $seen[$key] = true;
    $partOf[$idx] = $m[1];
    $parts[] = ['idx' => $idx, 'title' => $m[1], 'subtitle' => ''];
}
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <script>document.documentElement.className = 'js';</script>
    <style>
        :root {
            --bg:#07141b; --bg-1:#0a1b23; --bg-2:#0c222b; --panel:rgba(255,255,255,.035); --panel-h:rgba(94,234,212,.06);
            --line:rgba(255,255,255,.08); --line-t:rgba(94,234,212,.22);
            --white:#f2f8f9; --text:#b4c6cb; --muted:#7f979d;
            --teal:#00bfa6; --teal-2:#14b8a6; --mint:#5eead4; --glow:rgba(0,191,166,.35); --note:#d4ebe7;
            --sans:'Inter','Segoe UI',system-ui,Arial,sans-serif; --mono:'JetBrains Mono',ui-monospace,Consolas,monospace;
        }
        * { box-sizing:border-box; }
        html { scroll-behavior:smooth; }
        body { margin:0; background:var(--bg); color:var(--text); font-family:var(--sans); font-size:17px; line-height:1.7; -webkit-font-smoothing:antialiased; }
        a { color:var(--mint); font-weight:600; text-decoration:underline; text-decoration-color:rgba(94,234,212,.4); text-underline-offset:3px; transition:color .15s; }
        a:hover { color:var(--white); text-decoration-color:currentColor; text-decoration-thickness:2px; }
        .wrap { max-width:1320px; margin:0 auto; padding:0 64px; }
        .preview-bar { background:#fff3cd; color:#7a5b00; text-align:center; padding:8px; font-size:.85rem; position:relative; z-index:2; }

        /* ================= CAPA ================= */
        .hero { position:relative; overflow:hidden; min-height:100vh; display:flex; flex-direction:column; justify-content:center; padding:120px 0 90px; text-align:center; color:#fff;
            background:
                radial-gradient(ellipse 55% 45% at 50% 0%, rgba(94,234,212,.14) 0%, rgba(94,234,212,0) 70%),
                radial-gradient(ellipse 70% 60% at 85% 110%, rgba(17,140,118,.55) 0%, rgba(17,140,118,0) 70%),
                radial-gradient(ellipse 50% 50% at 0% 80%, rgba(14,71,64,.6) 0%, rgba(14,71,64,0) 70%),
                linear-gradient(170deg, #07141b 0%, #0b1f27 35%, #0d3a37 72%, #10594f 100%); }
        .hero::before { content:''; position:absolute; inset:0; pointer-events:none;
            background-image:linear-gradient(rgba(94,234,212,.05) 1px, transparent 1px), linear-gradient(90deg, rgba(94,234,212,.05) 1px, transparent 1px);
            background-size:64px 64px; mask-image:radial-gradient(ellipse 70% 60% at 50% 40%, #000 0%, transparent 85%); -webkit-mask-image:radial-gradient(ellipse 70% 60% at 50% 40%, #000 0%, transparent 85%); }
        .hero::after { content:''; position:absolute; left:50%; top:38%; width:720px; height:720px; transform:translate(-50%,-50%); border-radius:50%; pointer-events:none;
            background:radial-gradient(circle, rgba(0,191,166,.16) 0%, rgba(0,191,166,0) 65%); }
        .hero .wrap { position:relative; z-index:1; }
        .hero-brand { display:inline-flex; align-items:center; justify-content:center; gap:30px; margin-bottom:34px; }
        .hero-brand img { width:clamp(72px, 7.5vw, 112px); height:auto; filter:drop-shadow(0 0 34px var(--glow)); }
        .hero h1 { margin:0; font-size:clamp(2.8rem, 6.6vw, 5.6rem); line-height:1.05; font-weight:800; letter-spacing:-.035em; color:#fff; }
        .kicker { display:inline-block; margin:0 0 30px; padding:9px 22px; border:1px solid var(--line-t); border-radius:999px; background:rgba(0,191,166,.08);
            font-family:var(--mono); font-size:clamp(.82rem, 1.3vw, 1rem); font-weight:500; color:var(--mint); letter-spacing:.02em; }
        .lead { max-width:820px; margin:0 auto; font-size:clamp(1.05rem, 1.6vw, 1.3rem); line-height:1.7; color:#c5d6da; }

        .toc { margin:76px auto 0; max-width:1180px; text-align:left; }
        .toc h3 { margin:0 0 18px; text-align:center; font-family:var(--mono); font-size:.75rem; letter-spacing:.2em; text-transform:uppercase; color:var(--muted); font-weight:500; }
        .toc ol { list-style:none; margin:0; padding:0; display:grid; grid-template-columns:repeat(2, 1fr); gap:14px; counter-reset:toc; }
        .toc li { min-width:0; }
        .toc a { display:flex; gap:16px; align-items:center; height:100%; min-height:78px; min-width:0; padding:18px 20px; border:1px solid var(--line); border-radius:14px; background:rgba(7,20,27,.45); backdrop-filter:blur(6px);
            color:var(--white); font-weight:600; font-size:.86rem; line-height:1.35; text-decoration:none; transition:border-color .2s, transform .2s, background .2s; }
        .toc a span { min-width:0; }
        .toc a::before { counter-increment:toc; content:counter(toc, decimal-leading-zero); flex:0 0 auto; font-family:var(--mono); font-size:.8rem; font-weight:700; color:var(--teal); padding-top:1px; }
        .toc a:hover { border-color:var(--line-t); background:rgba(0,191,166,.08); transform:translateY(-2px); color:#fff; }
        .scroll-hint { position:absolute; left:50%; bottom:28px; transform:translateX(-50%); z-index:1; width:26px; height:42px; border:1.5px solid rgba(255,255,255,.25); border-radius:14px; }
        .scroll-hint::after { content:''; position:absolute; left:50%; top:8px; width:4px; height:8px; margin-left:-2px; border-radius:2px; background:var(--mint); animation:hint 1.8s infinite; }
        @keyframes hint { 0% { opacity:0; transform:translateY(0); } 40% { opacity:1; } 100% { opacity:0; transform:translateY(14px); } }

        /* ================= SEÇÕES ================= */
        .sec { position:relative; padding:130px 0; background:var(--bg-1); overflow:hidden; }
        .sec.light {
            --white:#0b1a24; --text:#41545b; --muted:#6b7b83; --mint:#0a8f7d; --note:#24534c;
            --panel:#ffffff; --panel-h:#ecfaf6; --line:#d7e6e4; --line-t:rgba(0,191,166,.45);
        }
        .sec::before { content:''; position:absolute; top:8%; right:-220px; width:640px; height:640px; border-radius:50%; pointer-events:none;
            background:radial-gradient(circle, rgba(0,191,166,.13) 0%, rgba(0,191,166,0) 70%); }
        .sec:nth-of-type(even)::before { right:auto; left:-220px; }
        .sec.light::after { content:''; position:absolute; inset:0; pointer-events:none; opacity:.35;
            background-image:linear-gradient(rgba(0,191,166,.06) 1px, transparent 1px), linear-gradient(90deg, rgba(0,191,166,.06) 1px, transparent 1px);
            background-size:56px 56px; mask-image:radial-gradient(ellipse 70% 60% at 50% 30%, #000 0%, transparent 80%); -webkit-mask-image:radial-gradient(ellipse 70% 60% at 50% 30%, #000 0%, transparent 80%); }
        .sec .wrap { position:relative; z-index:1; }
        .sec.part-start { padding-top:150px; }
        .part { display:inline-flex; align-items:center; gap:12px; margin-bottom:42px; font-family:var(--mono); font-size:.78rem; font-weight:700; letter-spacing:.16em; text-transform:uppercase; color:var(--mint); }
        .sec-head { display:flex; align-items:center; gap:22px; margin-bottom:28px; }
        .sec-head:not(:has(.num)) { margin-bottom:18px; }
        .sec-head:not(:has(.num)) h2 { font-size:clamp(1.25rem, 2vw, 1.7rem); font-weight:700; letter-spacing:-.02em; }
        .num { flex:0 0 auto; font-family:var(--sans); font-weight:900; font-size:clamp(2.8rem, 5vw, 4.4rem); line-height:.85; letter-spacing:-.04em;
            background:linear-gradient(180deg, var(--mint) 0%, rgba(0,191,166,.15) 100%); -webkit-background-clip:text; background-clip:text; color:transparent; }
        .sec h2 { margin:0; color:var(--white); font-size:clamp(2rem, 4vw, 3.3rem); line-height:1.08; font-weight:800; letter-spacing:-.035em; max-width:1000px; }
        .sec-head + .sub, .sec-head + .body { margin-top:0; }
        .sub { max-width:980px; margin:0 0 22px; font-size:clamp(1.15rem, 1.8vw, 1.4rem); line-height:1.5; font-weight:500; color:var(--mint); }
        .body { max-width:980px; font-size:1.1rem; line-height:1.8; color:var(--text); }
        .block { margin-top:52px; }
        .sec-head + .block { margin-top:40px; }

        /* Indicadores: faixa única, sem card */
        .stats { display:grid; grid-template-columns:repeat(3, 1fr); gap:0; text-align:center; padding:56px 20px 50px; border-radius:28px;
            background:linear-gradient(160deg, #0a8f7d 0%, #0d6e62 55%, #0b5a51 100%); }
        .stat { padding:12px 22px; }
        .stat + .stat { border-left:1px solid rgba(255,255,255,.18); }
        .stat strong { display:block; font-size:clamp(3.2rem, 6vw, 5rem); line-height:.95; font-weight:800; letter-spacing:-.04em; margin-bottom:12px; color:#fff; }
        .stat span { color:rgba(255,255,255,.86); font-size:1.05rem; font-weight:500; }

        /* Etapas — layout A: linha do tempo (estratégia) */
        .steps { display:grid; gap:8px 36px; counter-reset:step; position:relative; }
        .steps-line { grid-template-columns:repeat(4, 1fr); }
        .steps-line::before { content:''; position:absolute; left:8px; right:8px; top:17px; height:2px; background:linear-gradient(90deg, #0a8f7d, rgba(10,143,125,.2)); pointer-events:none; }
        .step { position:relative; padding:0; text-align:left; background:none; border:none; }
        .steps-line .step::before { counter-increment:step; content:counter(step, decimal-leading-zero); display:flex; align-items:center; justify-content:center; width:36px; height:36px; margin:0 0 22px;
            border-radius:50%; background:#0a8f7d; color:#fff; font-family:var(--mono); font-weight:700; font-size:.8rem; position:relative; z-index:1; box-shadow:0 0 0 6px var(--sec-bg, #f4faf8); }
        .step strong { display:block; color:var(--white); font-size:1.18rem; font-weight:700; margin-bottom:8px; letter-spacing:-.01em; }
        .step p { margin:0; color:var(--text); font-size:.98rem; line-height:1.65; }

        /* Etapas — layout B: índice editorial (tecnologia) */
        .steps-rail { grid-template-columns:repeat(2, minmax(0, 1fr)); gap:42px 72px; }
        .steps-rail .step { display:grid; grid-template-columns:4.6rem minmax(0, 1fr); column-gap:8px; row-gap:6px; padding-top:8px; border-top:1px solid var(--line); }
        .steps-rail .step::before { counter-increment:step; content:counter(step, decimal-leading-zero); grid-column:1; grid-row:1 / span 2; align-self:start;
            display:block; width:auto; height:auto; margin:0; padding:0; border-radius:0; background:none; box-shadow:none;
            color:#0a8f7d; font-family:var(--sans); font-weight:800; font-size:clamp(1.9rem, 2.6vw, 2.6rem); line-height:1; letter-spacing:-.04em; }
        .steps-rail .step strong { grid-column:2; margin:4px 0 0; font-size:1.28rem; }
        .steps-rail .step p { grid-column:2; }

        /* Clientes */
        .cards { display:grid; grid-template-columns:repeat(4, 1fr); gap:18px; }
        .card { padding:26px 22px 24px; border:1px solid var(--line); border-radius:18px; background:var(--panel); transition:transform .2s, border-color .2s, box-shadow .2s; }
        .card .logo { width:96px; height:96px; object-fit:contain; object-position:center; background:transparent; border:none; border-radius:0; margin-bottom:16px; display:block; padding:0; }
        .card strong { display:block; color:var(--white); font-size:1.12rem; font-weight:700; margin-bottom:10px; letter-spacing:-.015em; }
        .card p { margin:0; color:var(--text); font-size:.95rem; line-height:1.65; }
        .sec.light .card:hover { transform:translateY(-3px); border-color:var(--line-t); box-shadow:0 12px 28px rgba(10,143,125,.08); }
        .card.more { display:flex; flex-direction:column; justify-content:center; min-height:100%; padding:28px 20px; background:linear-gradient(160deg, #0a8f7d 0%, #0d6e62 100%); text-align:center; border-color:transparent; }
        .card.more strong, .card.more p { color:#fff; }
        .card.more strong { font-size:clamp(2.6rem, 4vw, 3.6rem); margin-bottom:8px; }
        .card.more p { font-size:1.05rem; opacity:.92; }

        /* Tabelas */
        .table-wrap { overflow-x:auto; border:1px solid var(--line); border-radius:18px; background:var(--panel); }
        table { width:100%; border-collapse:collapse; font-size:1rem; }
        th { text-align:left; padding:20px 26px; font-family:var(--mono); font-size:.74rem; letter-spacing:.12em; text-transform:uppercase; font-weight:700; color:var(--mint);
            background:rgba(0,191,166,.08); border-bottom:1px solid var(--line-t); white-space:nowrap; }
        td { padding:20px 26px; border-top:1px solid var(--line); vertical-align:top; color:var(--text); }
        tbody tr:first-child td { border-top:none; }
        td:first-child { font-weight:700; color:var(--white); }
        tbody tr { transition:background .15s; }
        tbody tr:hover { background:rgba(0,191,166,.05); }
        table.cmp th:first-child { color:var(--muted); background:rgba(255,255,255,.03); border-bottom-color:var(--line); }
        table.cmp td:first-child { font-weight:400; color:var(--muted); }
        table.cmp td:last-child { color:var(--white); font-weight:600; }

        /* Listas em blocos leves */
        ul.list { margin:0; padding:0; list-style:none; display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:14px 16px; counter-reset:item; }
        ul.list.tight { grid-template-columns:repeat(3, minmax(0, 1fr)); max-width:none; }
        ul.list li { position:relative; padding:22px 22px 20px 22px; border:1px solid var(--line); border-radius:16px; background:var(--panel); color:var(--white); font-weight:600; font-size:1.05rem; line-height:1.45; transition:transform .2s, border-color .2s, box-shadow .2s; }
        .sec.light ul.list li:hover { transform:translateY(-2px); border-color:var(--line-t); box-shadow:0 10px 24px rgba(10,143,125,.07); }
        ul.list.tight li { padding-top:20px; }
        ul.list.tight li::before { counter-increment:item; content:counter(item, decimal-leading-zero); display:block; width:fit-content; margin:0 0 12px; padding:4px 10px; border-radius:999px; background:rgba(0,191,166,.12); color:#0a8f7d; font:700 .75rem/1 var(--mono); }
        ul.list:not(.tight):not(.dont) li::before { content:''; position:absolute; left:0; top:18px; bottom:18px; width:3px; border-radius:3px; background:var(--teal); }
        ul.list li small { display:block; color:var(--text); font-weight:400; font-size:.95rem; margin-top:6px; line-height:1.55; }
        ul.list.dont li::before { content:''; position:absolute; left:18px; top:22px; width:10px; height:10px; border-radius:50%; background:#f87171; }
        .sec.light table.cmp th:first-child { background:#f4f8f9; }

        /* Observação */
        .note { margin-top:40px; display:flex; align-items:flex-start; gap:14px; max-width:1100px; padding:22px 24px; border:1px solid var(--line-t); border-radius:16px;
            background:linear-gradient(120deg, rgba(0,191,166,.12) 0%, rgba(0,191,166,.03) 100%); color:var(--note); font-size:1rem; line-height:1.7; }
        .note::before { content:'i'; flex:0 0 28px; width:28px; height:28px; display:flex; align-items:center; justify-content:center; margin-top:2px; border-radius:50%; background:#0a8f7d; color:#fff; font:700 .85rem/1 var(--mono); }
        .note > div { min-width:0; }

        /* Fechamento */
        .sec.cta { text-align:center; background:radial-gradient(ellipse 70% 80% at 50% 120%, rgba(17,140,118,.55) 0%, rgba(17,140,118,0) 70%), linear-gradient(170deg, #0b1f27 0%, #0d3a37 100%); }
        .sec.cta .sec-head { justify-content:center; }
        .sec.cta .sub, .sec.cta .body { margin-left:auto; margin-right:auto; }
        .contacts { display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:14px; text-align:left; }
        .contacts div { padding:24px 26px; border:1px solid var(--line); border-radius:14px; background:rgba(7,20,27,.5); color:var(--white); font-weight:600; }
        .contacts span { display:block; font-family:var(--mono); font-size:.72rem; color:var(--mint); text-transform:uppercase; letter-spacing:.12em; margin-bottom:8px; }

        /* Rodapé */
        .footer { padding:80px 0 56px; text-align:center; border-top:1px solid rgba(255,255,255,.08);
            background:radial-gradient(ellipse 50% 80% at 50% 0%, rgba(0,191,166,.12) 0%, rgba(0,191,166,0) 70%), #07141b; }
        .footer img { width:56px; height:auto; filter:drop-shadow(0 0 18px var(--glow)); }
        .footer strong { display:block; margin:14px 0 6px; color:#f2f8f9; font-size:1.35rem; font-weight:800; letter-spacing:-.03em; }
        .footer span { color:#7f979d; font-size:.85rem; }

        /* Entrada suave das seções */
        .js .reveal { opacity:0; transform:translateY(28px); transition:opacity .7s ease, transform .7s ease; }
        .js .reveal.in { opacity:1; transform:none; }

        /* Pré-visualização: seções ocultas */
        .sec[data-hidden] .wrap { opacity:.3; }
        .sec[data-hidden]::after { content:'Oculta para o cliente'; position:absolute; top:24px; right:28px; background:#6b7088; color:#fff; font-size:.75rem; padding:5px 12px; border-radius:6px; }

        @media (max-width:1100px) {
            .cards { grid-template-columns:repeat(2, minmax(0, 1fr)); }
            .steps-line { grid-template-columns:repeat(2, minmax(0, 1fr)); }
            .steps-line::before { display:none; }
        }
        @media (max-width:900px) {
            .wrap { padding:0 28px; }
            .sec { padding:90px 0; }
            .sec-head { flex-direction:column; align-items:flex-start; gap:10px; }
            .cards, .steps-line, .steps-rail, ul.list, .stats, .toc ol { grid-template-columns:minmax(0,1fr); }
            .stat + .stat { border-left:none; border-top:1px solid rgba(255,255,255,.18); }
            .steps-line::before { display:none; }
            .hero-brand { flex-direction:column; gap:20px; }
        }
        @media (max-width:560px) {
            body { font-size:16px; }
            .wrap { padding:0 20px; }
            .hero { padding:90px 0 80px; }
            .sec { padding:70px 0; }
            .stat { padding:18px 12px; }
            .stat + .stat { border-left:none; border-top:1px solid rgba(255,255,255,.18); }
            .card { padding:26px 22px; }
            .steps-line::before { display:none; }
            ul.list li { padding:20px 18px; }
            th, td { padding:14px 16px; }
            .note { padding:18px; }
        }
        @media print { .preview-bar, .scroll-hint { display:none; } .js .reveal { opacity:1; transform:none; } .hero { min-height:auto; } }
    </style>
</head>
<body>
    <?php if ($isPreview): ?>
    <div class="preview-bar">Pré-visualização interna — <?= PresentationRules::isPublished($presentation) ? 'o link público está ativo.' : 'o link público ainda está desativado.' ?></div>
    <?php endif; ?>

    <main>
            <?php
            // Fundo claro com variação de tom. Escuro só na capa, CTA e rodapé.
            $lightTones = ['#f7fbfb', '#eef8f5', '#ffffff', '#e7f4f0', '#f4faf8'];
            $lightN = 0;
            $prevBottom = '#d9f1ec';
            $prevDark = true;
            $stepsN = 0;
            ?>
        <?php foreach ($sections as $idx => $s): ?>
            <?php if ($s['type'] === 'divider') continue; ?>
            <?php $hiddenAttr = empty($s['visible']) ? ' data-hidden="1"' : ''; ?>

            <?php if ($s['type'] === 'hero'): ?>
            <section class="hero" id="sec-<?= (int)$idx ?>"<?= $hiddenAttr ?>>
                <div class="wrap">
                    <div class="hero-brand">
                        <img src="<?= $emblem ?>" alt="">
                        <h1><?= $t($s['title']) ?></h1>
                    </div>
                    <div><?php if ($s['subtitle'] !== ''): ?><p class="kicker"><?= $t($s['subtitle']) ?></p><?php endif; ?></div>
                    <?php if ($s['body'] !== ''): ?><p class="lead"><?= $p($s['body']) ?></p><?php endif; ?>
                    <?php if ($parts): ?>
                    <nav class="toc">
                        <h3>Conteúdo</h3>
                        <ol><?php foreach ($parts as $part): ?><li><a href="#sec-<?= (int)$part['idx'] ?>"><span><?= $t($part['title']) ?></span></a></li><?php endforeach; ?></ol>
                    </nav>
                    <?php endif; ?>
                </div>
                <span class="scroll-hint" aria-hidden="true"></span>
            </section>
            <?php continue; endif; ?>

            <?php
            $num = '';
            $title = $s['title'];
            if (preg_match('/^(\d+)\.\s+(.+)$/u', $title, $m)) { $num = str_pad($m[1], 2, '0', STR_PAD_LEFT); $title = $m[2]; }
            $isCta = $s['type'] === 'cta';
            $dark = $isCta;
            if ($dark) {
                $body = '#0a1b23';
                $bottom = '#0f4f48';
                $top = $prevDark ? $prevBottom : '#0f4f48';
            } else {
                $body = $bottom = $lightTones[$lightN++ % count($lightTones)];
                $top = $prevDark ? '#d9f1ec' : $prevBottom;
            }
            $bg = "background:linear-gradient(180deg, {$top} 0, {$body} 160px, {$body} calc(100% - 160px), {$bottom} 100%);";
            $prevBottom = $bottom;
            $prevDark = $dark;
            $classes = 'sec' . ($isCta ? ' cta' : '') . ($dark ? '' : ' light') . (isset($partOf[$idx]) ? ' part-start' : '');
            ?>
            <section class="<?= $classes ?>" id="sec-<?= (int)$idx ?>" style="<?= $bg ?><?= $dark ? '' : '--sec-bg:' . $body . ';' ?>"<?= $hiddenAttr ?>>
                <div class="wrap reveal">
                    <?php if ($s['type'] !== 'stats' && ($num !== '' || $title !== '')): ?>
                    <div class="sec-head">
                        <?php if ($num !== ''): ?><span class="num"><?= $num ?></span><?php endif; ?>
                        <?php if ($title !== ''): ?><h2><?= $t($title) ?></h2><?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($s['subtitle'] !== ''): ?><p class="sub"><?= $t($s['subtitle']) ?></p><?php endif; ?>
                    <?php if ($s['body'] !== ''): ?><div class="body"><?= $p($s['body']) ?></div><?php endif; ?>

                    <?php if (!empty($s['items'])): ?>
                    <div class="block">
                        <?php if ($s['type'] === 'stats'): ?>
                        <div class="stats"><?php foreach ($s['items'] as $it): ?><div class="stat"><strong><?= $t($it['title']) ?></strong><span><?= $t($it['text']) ?></span></div><?php endforeach; ?></div>
                        <?php elseif ($s['type'] === 'steps'): $stepsN++; $stepsMod = ($stepsN % 2) === 1 ? 'steps-line' : 'steps-rail'; ?>
                        <div class="steps <?= $stepsMod ?>"><?php foreach ($s['items'] as $it): ?><div class="step"><?php if ($it['title'] !== ''): ?><strong><?= $t($it['title']) ?></strong><?php endif; ?><p><?= $p($it['text']) ?></p></div><?php endforeach; ?></div>
                        <?php elseif ($s['type'] === 'cards'): ?>
                        <div class="cards"><?php foreach ($s['items'] as $it):
                            $img = PresentationRules::safeImage($it['image'] ?? '');
                            $more = $it['title'] === '50+';
                            $imgSrc = $img !== '' ? preg_replace('#(?<!:)/{2,}#', '/', str_replace('\\', '/', baseUrl($img))) . '?v=5' : '';
                        ?><div class="card<?= $more ? ' more' : '' ?>"><?php if ($imgSrc !== ''): ?><img class="logo" src="<?= htmlspecialchars($imgSrc, ENT_QUOTES) ?>" alt=""><?php endif; ?><?php if ($it['title'] !== ''): ?><strong><?= $t($it['title']) ?></strong><?php endif; ?><p><?= $p($it['text']) ?></p></div><?php endforeach; ?></div>
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
                        <ul class="list<?= $s['type'] === 'dont' ? ' dont' : '' ?><?= count($s['items']) <= 3 ? ' tight' : '' ?>"><?php foreach ($s['items'] as $it): ?><li><?= $t($it['title']) ?><?php if ($it['text'] !== ''): ?><small><?= $t($it['text']) ?></small><?php endif; ?></li><?php endforeach; ?></ul>
                        <?php elseif ($isCta): ?>
                        <div class="contacts"><?php foreach ($s['items'] as $it): ?><div><span><?= $t($it['title']) ?></span><?= $t($it['text']) ?></div><?php endforeach; ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php if (($s['note'] ?? '') !== ''): ?><div class="note"><div><?= $p($s['note']) ?></div></div><?php endif; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </main>

    <footer class="footer">
        <img src="<?= $emblem ?>" alt="">
        <strong><?= $appName ?></strong>
        <span>© <?= date('Y') ?> <?= $appName ?></span>
    </footer>

    <script>
    (function () {
        var els = document.querySelectorAll('.reveal');
        if (!('IntersectionObserver' in window)) { els.forEach(function (el) { el.classList.add('in'); }); return; }
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } });
        }, { rootMargin: '0px 0px -8% 0px' });
        els.forEach(function (el) { io.observe(el); });
    })();
    </script>
</body>
</html>
