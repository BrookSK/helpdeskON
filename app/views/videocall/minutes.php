<?php
$appName = htmlspecialchars((string) Config::get('app_name') ?: 'ON Solutions', ENT_QUOTES);
$logo = Config::get('app_logo');
$fav = Config::get('app_favicon');
$title = htmlspecialchars(($room['title'] ?? 'Minuta da reunião') ?: 'Minuta da reunião', ENT_QUOTES);
$dateLabel = !empty($rec['created_at']) ? date('d/m/Y', strtotime($rec['created_at'])) : date('d/m/Y');
// A minuta é texto/Markdown confiável (gerado/editado pela equipe). Passamos como
// JSON para o JS renderizar com marked, e escapamos no fallback sem JS.
$minutesJson = json_encode((string) $minutes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$minutesEsc = htmlspecialchars((string) $minutes, ENT_QUOTES);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minuta · <?= $title ?></title>
    <?php if ($fav): ?>
    <link rel="icon" href="<?= baseUrl($fav) ?>">
    <?php endif; ?>
    <style>
        * { box-sizing: border-box; }
        body { margin:0; background:#f3f4f8; color:#1b1d2a; font-family:'Segoe UI',system-ui,Arial,sans-serif; }
        .sheet { max-width:820px; margin:24px auto; background:#fff; border-radius:10px; box-shadow:0 8px 30px rgba(0,0,0,.08); overflow:hidden; }
        .hd { display:flex; align-items:center; gap:14px; padding:22px 32px; border-bottom:3px solid #00BFA6; }
        .hd img { max-height:46px; }
        .hd .co { font-size:1.15rem; font-weight:700; color:#0f1020; }
        .hd .sp { flex:1; }
        .hd .meta { text-align:right; font-size:.8rem; color:#6b7088; }
        .bd { padding:28px 32px; }
        .bd h1 { font-size:1.4rem; margin:.2em 0 .6em; }
        .bd h2 { font-size:1.1rem; margin:1.1em 0 .4em; color:#0b8c7a; border-bottom:1px solid #e6e8f0; padding-bottom:3px; }
        .bd h3 { font-size:1rem; margin:.9em 0 .3em; }
        .bd ul { margin:.3em 0 .8em 1.1em; }
        .bd li { margin:.2em 0; }
        .bd p { line-height:1.6; margin:.5em 0; }
        .bd pre { white-space:pre-wrap; font-family:inherit; }
        .ft { padding:16px 32px; border-top:1px solid #e6e8f0; font-size:.78rem; color:#6b7088; text-align:center; }
        .bar { max-width:820px; margin:0 auto 0; padding:12px 8px; display:flex; justify-content:flex-end; gap:8px; }
        .btn { background:#00BFA6; color:#fff; border:none; border-radius:8px; padding:9px 16px; font-size:.9rem; cursor:pointer; text-decoration:none; }
        .btn:hover { background:#009e88; }
        @media print {
            body { background:#fff; }
            .bar { display:none; }
            .sheet { box-shadow:none; margin:0; max-width:100%; border-radius:0; }
        }
    </style>
</head>
<body>
    <div class="bar">
        <button class="btn" onclick="window.print()">Imprimir / Salvar PDF</button>
    </div>
    <div class="sheet">
        <div class="hd">
            <?php if ($logo): ?><img src="<?= baseUrl($logo) ?>" alt="<?= $appName ?>"><?php else: ?><span class="co"><?= $appName ?></span><?php endif; ?>
            <span class="sp"></span>
            <div class="meta">
                <div><strong>Minuta da reunião</strong></div>
                <div><?= $title ?></div>
                <div><?= $dateLabel ?></div>
            </div>
        </div>
        <div class="bd" id="bd"><pre id="raw"><?= $minutesEsc ?></pre></div>
        <div class="ft"><?= $appName ?> · Documento gerado automaticamente a partir da reunião.</div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script>
        var MINUTES = <?= $minutesJson ?: '""' ?>;
        // Renderiza o Markdown com marked; se o CDN falhar, mantém o <pre> (fallback legível).
        try {
            if (window.marked && MINUTES) {
                document.getElementById('bd').innerHTML = marked.parse(MINUTES);
            }
        } catch (e) { /* mantém o texto puro */ }
    </script>
</body>
</html>
