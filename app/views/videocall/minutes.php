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

// Estado do reconhecimento/assinatura da minuta pelo cliente.
$ackStatus  = $rec['minutes_ack_status'] ?? 'pending';
$ackAt      = !empty($rec['minutes_ack_at']) ? date('d/m/Y H:i', strtotime($rec['minutes_ack_at'])) : '';
$contestMsg = htmlspecialchars((string)($rec['minutes_contest_reason'] ?? ''), ENT_QUOTES);
$recTk      = htmlspecialchars((string)($rec['token'] ?? ''), ENT_QUOTES);
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
        .btn-sec { background:#eef0f6; color:#444a63; }
        .btn-sec:hover { background:#e2e5ef; }
        .ack { max-width:820px; margin:16px auto 32px; background:#fff; border-radius:10px; box-shadow:0 8px 30px rgba(0,0,0,.08); padding:22px 32px; }
        .ack h2 { font-size:1.05rem; margin:0 0 .5em; color:#0b8c7a; }
        .ack .banner { padding:12px 16px; border-radius:8px; font-size:.9rem; margin-bottom:14px; }
        .ack .ok { background:#e6f7f3; color:#0b8c7a; }
        .ack .warn { background:#fff6e5; color:#9a6a00; }
        .ack .err { background:#fdeaea; color:#c0392b; }
        .ack label { display:block; font-size:.85rem; color:#6b7088; margin:10px 0 6px; }
        .ack input[type=text] { width:140px; border:1px solid #cfd3e2; border-radius:8px; padding:10px; font-size:1.1rem; letter-spacing:.3em; text-align:center; }
        .ack textarea { width:100%; border:1px solid #cfd3e2; border-radius:8px; padding:10px; font-size:.92rem; min-height:70px; font-family:inherit; }
        .ack .row { display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end; margin-top:8px; }
        @media print {
            body { background:#fff; }
            .bar, .ack { display:none; }
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

    <!-- Reconhecimento/assinatura da minuta pelo cliente -->
    <div class="ack" id="ack">
        <?php if ($ackStatus === 'acknowledged'): ?>
            <div class="banner ok">✓ Minuta reconhecida e assinada pelo cliente<?= $ackAt ? (' em ' . $ackAt) : '' ?>.</div>
        <?php else: ?>
            <h2>Confirmação da minuta</h2>
            <?php if ($ackStatus === 'contested'): ?>
                <div class="banner warn">Você registrou uma discordância<?= $contestMsg ? (': “' . $contestMsg . '”') : '' ?>. Nossa equipe vai revisar e reenviar. Se preferir, pode reconhecer a versão atual abaixo.</div>
            <?php endif; ?>
            <div id="ackBanner" class="banner err" style="display:none"></div>
            <p style="font-size:.9rem;color:#444a63;margin:.2em 0 .6em;">Confira a ata acima. Para <strong>confirmar/assinar</strong>, informe seu PIN de 4 dígitos. Se algo estiver incorreto, você pode <strong>discordar</strong> informando o motivo.</p>
            <div class="row">
                <div>
                    <label for="ack-pin">Seu PIN (4 dígitos)</label>
                    <input type="text" id="ack-pin" inputmode="numeric" maxlength="4" autocomplete="off" placeholder="0000">
                </div>
                <button class="btn" id="ack-btn" onclick="ackMinutes()">Reconhecer e assinar</button>
            </div>
            <label for="ack-reason">Discordo porque…</label>
            <textarea id="ack-reason" placeholder="Descreva o que precisa ser corrigido na ata"></textarea>
            <div class="row">
                <button class="btn btn-sec" id="contest-btn" onclick="contestMinutes()">Discordar da minuta</button>
            </div>
        <?php endif; ?>
    </div>

    <script>
        var ROOT = '<?= rtrim(baseUrl(''), '/') ?>';
        var RECTK = '<?= $recTk ?>';
        function ackBanner(msg) {
            var b = document.getElementById('ackBanner');
            if (!b) return;
            b.textContent = msg; b.style.display = 'block';
        }
        async function ackMinutes() {
            var pin = (document.getElementById('ack-pin').value || '').trim();
            if (!/^\d{4}$/.test(pin)) { ackBanner('Informe os 4 dígitos do seu PIN.'); return; }
            var btn = document.getElementById('ack-btn'); btn.disabled = true;
            var fd = new FormData(); fd.append('pin', pin);
            try {
                var res = await fetch(ROOT + '/videocall/acknowledgeMinutes/' + RECTK, {
                    method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                var data = await res.json();
                if (data.error) { ackBanner(data.error); btn.disabled = false; return; }
                location.reload();
            } catch (e) { ackBanner('Falha de conexão. Tente novamente.'); btn.disabled = false; }
        }
        async function contestMinutes() {
            var reason = (document.getElementById('ack-reason').value || '').trim();
            if (reason.length < 3) { ackBanner('Descreva o motivo da discordância.'); return; }
            var btn = document.getElementById('contest-btn'); btn.disabled = true;
            var fd = new FormData(); fd.append('reason', reason);
            try {
                var res = await fetch(ROOT + '/videocall/contestMinutes/' + RECTK, {
                    method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                var data = await res.json();
                if (data.error) { ackBanner(data.error); btn.disabled = false; return; }
                location.reload();
            } catch (e) { ackBanner('Falha de conexão. Tente novamente.'); btn.disabled = false; }
        }
    </script>

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
