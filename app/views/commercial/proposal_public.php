<?php
$appName = htmlspecialchars(ProposalPdf::BRAND, ENT_QUOTES);
$respondedAt = !empty($proposal['responded_at']) ? date('d/m/Y \à\s H:i', strtotime($proposal['responded_at'])) : '';
$emblem = asset('on-emblem.png') . '?v=2';
$token = htmlspecialchars($proposal['public_token'], ENT_QUOTES);
$brl = fn($v) => 'R$ ' . number_format((float)$v, 2, ',', '.');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= htmlspecialchars($proposal['title'], ENT_QUOTES) ?> · Proposta</title>
    <?php if ($fav = Config::get('app_favicon')): ?><link rel="icon" href="<?= baseUrl($fav) ?>"><?php endif; ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing:border-box; }
        body { margin:0; min-height:100vh; font-family:'Inter',system-ui,sans-serif; color:#fff;
            background: radial-gradient(ellipse 60% 50% at 50% 0%, rgba(94,234,212,.14), transparent 70%), linear-gradient(170deg, #07141b 0%, #0b1f27 45%, #0e3d38 100%); }
        .wrap { max-width:560px; margin:0 auto; padding:48px 20px; }
        .brand { display:flex; align-items:center; gap:12px; margin-bottom:36px; }
        .brand img { width:44px; filter:drop-shadow(0 0 16px rgba(0,191,166,.45)); }
        .brand strong { font-size:1.05rem; }
        .pill { display:inline-block; padding:6px 12px; border:1px solid rgba(94,234,212,.35); border-radius:999px; color:#5eead4; font-size:.72rem; letter-spacing:.14em; text-transform:uppercase; }
        h1 { font-size:1.9rem; line-height:1.15; margin:14px 0 6px; letter-spacing:-.02em; }
        .for { color:#b8cfd2; margin:0 0 24px; }
        .box { background:rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.1); border-radius:14px; padding:20px; margin-bottom:16px; }
        .row { display:flex; justify-content:space-between; padding:6px 0; color:#d5e4e6; }
        .row b { color:#fff; }
        .btn { display:inline-block; border:none; border-radius:10px; padding:13px 18px; font-size:.95rem; font-weight:700; cursor:pointer; text-decoration:none; font-family:inherit; }
        .btn-pdf { background:#fff; color:#07141b; width:100%; text-align:center; }
        .btn-ok { background:#00bfa6; color:#fff; }
        .btn-no { background:transparent; color:#ffb4ad; border:1px solid #ffb4ad; }
        .actions { display:flex; gap:10px; margin-top:12px; flex-wrap:wrap; }
        .banner { padding:14px; border-radius:10px; margin-bottom:16px; }
        .banner.ok { background:rgba(0,191,166,.18); color:#a7f3e4; }
        .banner.no { background:rgba(255,120,110,.15); color:#ffc8c2; }
        .done { display:flex; gap:14px; align-items:flex-start; padding:18px; border-radius:14px; margin-bottom:16px; }
        .done.ok { background:rgba(0,191,166,.16); border:1px solid rgba(94,234,212,.45); }
        .done.no { background:rgba(255,120,110,.12); border:1px solid rgba(255,180,173,.4); }
        .done .ic { flex:0 0 40px; height:40px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.3rem; font-weight:800; }
        .done.ok .ic { background:#00bfa6; color:#07141b; }
        .done.no .ic { background:#ffb4ad; color:#07141b; }
        .done strong { display:block; font-size:1.05rem; margin-bottom:4px; }
        .done p { margin:0; color:#d5e4e6; font-size:.92rem; line-height:1.45; }
        #reject-box { display:none; margin-top:14px; }
        textarea { width:100%; border-radius:10px; border:1px solid rgba(255,255,255,.2); background:rgba(0,0,0,.2); color:#fff; padding:10px; font-family:inherit; }
        .ft { text-align:center; color:#7f979d; font-size:.78rem; margin-top:28px; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="brand"><img src="<?= htmlspecialchars($emblem, ENT_QUOTES) ?>" alt=""><strong><?= $appName ?></strong></div>
    <span class="pill">Proposta comercial</span>
    <h1><?= htmlspecialchars($proposal['title'], ENT_QUOTES) ?></h1>
    <?php if (!empty($proposal['client_name'])): ?><p class="for">Preparado para <?= htmlspecialchars($proposal['client_name'], ENT_QUOTES) ?></p><?php endif; ?>

    <div id="result">
    <?php if ($proposal['status'] === 'accepted'): ?>
        <div class="done ok"><div class="ic">✓</div><div>
            <strong>Proposta aceita</strong>
            <p>Recebemos o seu aceite<?= $respondedAt !== '' ? ' em ' . $respondedAt : '' ?>. Obrigado! A equipe da <?= $appName ?> vai entrar em contato para os próximos passos.</p>
        </div></div>
    <?php elseif ($proposal['status'] === 'rejected'): ?>
        <div class="done no"><div class="ic">✕</div><div>
            <strong>Proposta recusada</strong>
            <p>Registramos a sua resposta<?= $respondedAt !== '' ? ' em ' . $respondedAt : '' ?>. Obrigado pelo retorno.</p>
        </div></div>
    <?php elseif ($proposal['status'] === 'cancelled'): ?>
        <div class="banner no">Esta proposta foi cancelada.</div>
    <?php endif; ?>
    </div>

    <div class="box">
        <div class="row"><span>Serviços pontuais</span><b><?= $brl($totals['once']) ?></b></div>
        <?php if ($totals['monthly'] > 0): ?><div class="row"><span>Serviços mensais</span><b><?= $brl($totals['monthly']) ?>/mês</b></div><?php endif; ?>
        <?php if (!empty($proposal['validity_date'])): ?><div class="row"><span>Válida até</span><b><?= date('d/m/Y', strtotime($proposal['validity_date'])) ?></b></div><?php endif; ?>
    </div>

    <a class="btn btn-pdf" href="<?= htmlspecialchars($pdfLink, ENT_QUOTES) ?>" target="_blank" rel="noopener">Abrir a proposta em PDF</a>

    <?php if (!$isTerminal): ?>
    <div class="box" id="respond-box" style="margin-top:16px;">
        <strong>Responder</strong>
        <p style="color:#b8cfd2;margin:6px 0 0;">O aceite confirma o escopo, o investimento e o cronograma do PDF.</p>
        <div class="actions">
            <button class="btn btn-ok" onclick="accept(this)">Aceitar proposta</button>
            <button class="btn btn-no" onclick="document.getElementById('reject-box').style.display='block'">Recusar</button>
        </div>
        <div id="reject-box">
            <label>Informe o motivo da recusa</label>
            <textarea id="reject-reason" rows="3" placeholder="Ex.: valor, prazo ou escopo"></textarea>
            <div class="actions"><button class="btn btn-no" onclick="reject(this)">Confirmar recusa</button></div>
        </div>
    </div>
    <?php endif; ?>
    <div class="ft"><?= $appName ?></div>
</div>
<script>
    const BASE = '<?= rtrim(baseUrl(''), '/') ?>';
    const TOKEN = '<?= $token ?>';
    const BRAND = '<?= $appName ?>';
    function showResult(ok, when) {
        const box = document.getElementById('respond-box');
        if (box) box.remove();
        const title = ok ? 'Proposta aceita' : 'Proposta recusada';
        const text = ok
            ? `Recebemos o seu aceite${when ? ' em ' + when : ''}. Obrigado! A equipe da ${BRAND} vai entrar em contato para os próximos passos.`
            : `Registramos a sua resposta${when ? ' em ' + when : ''}. Obrigado pelo retorno.`;
        document.getElementById('result').innerHTML =
            `<div class="done ${ok ? 'ok' : 'no'}"><div class="ic">${ok ? '✓' : '✕'}</div><div><strong>${title}</strong><p>${text}</p></div></div>`;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    async function send(url, body, btn) {
        btn.disabled = true;
        const old = btn.textContent;
        btn.textContent = 'Enviando...';
        const r = await fetch(url, { method:'POST', body }).then(x => x.json()).catch(() => ({ error: 'Falha de rede. Tente novamente.' }));
        if (r.error) { alert(r.error); btn.disabled = false; btn.textContent = old; return null; }
        return r;
    }
    async function accept(btn) {
        if (!confirm('Confirmar o aceite desta proposta?')) return;
        const r = await send(`${BASE}/proposal/accept/${TOKEN}`, null, btn);
        if (r) showResult(true, r.responded_at || '');
    }
    async function reject(btn) {
        const reason = document.getElementById('reject-reason').value.trim();
        if (!reason) { alert('Informe o motivo da recusa.'); return; }
        const fd = new URLSearchParams(); fd.append('reason', reason);
        const r = await send(`${BASE}/proposal/reject/${TOKEN}`, fd, btn);
        if (r) showResult(false, '');
    }
</script>
</body>
</html>
