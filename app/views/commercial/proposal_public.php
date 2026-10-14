<?php
$appName = htmlspecialchars((string) Config::get('app_name') ?: 'ON Solutions', ENT_QUOTES);
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

    <?php if ($proposal['status'] === 'accepted'): ?>
        <div class="banner ok">Você aceitou esta proposta. Obrigado. Em breve damos sequência.</div>
    <?php elseif ($proposal['status'] === 'rejected'): ?>
        <div class="banner no">Esta proposta foi recusada.</div>
    <?php endif; ?>

    <div class="box">
        <div class="row"><span>Serviços pontuais</span><b><?= $brl($totals['once']) ?></b></div>
        <?php if ($totals['monthly'] > 0): ?><div class="row"><span>Serviços mensais</span><b><?= $brl($totals['monthly']) ?>/mês</b></div><?php endif; ?>
        <?php if (!empty($proposal['validity_date'])): ?><div class="row"><span>Válida até</span><b><?= date('d/m/Y', strtotime($proposal['validity_date'])) ?></b></div><?php endif; ?>
    </div>

    <a class="btn btn-pdf" href="<?= htmlspecialchars($pdfLink, ENT_QUOTES) ?>" target="_blank" rel="noopener">Abrir a proposta em PDF</a>

    <?php if (!$isTerminal): ?>
    <div class="box" style="margin-top:16px;">
        <strong>Responder</strong>
        <p style="color:#b8cfd2;margin:6px 0 0;">O aceite confirma o escopo, o investimento e o cronograma do PDF.</p>
        <div class="actions">
            <button class="btn btn-ok" onclick="accept()">Aceitar proposta</button>
            <button class="btn btn-no" onclick="document.getElementById('reject-box').style.display='block'">Recusar</button>
        </div>
        <div id="reject-box">
            <label>Informe o motivo da recusa</label>
            <textarea id="reject-reason" rows="3" placeholder="Ex.: valor, prazo ou escopo"></textarea>
            <div class="actions"><button class="btn btn-no" onclick="reject()">Confirmar recusa</button></div>
        </div>
    </div>
    <?php endif; ?>
    <div class="ft"><?= $appName ?></div>
</div>
<script>
    const BASE = '<?= rtrim(baseUrl(''), '/') ?>';
    const TOKEN = '<?= $token ?>';
    async function accept() {
        if (!confirm('Confirmar o aceite desta proposta?')) return;
        const r = await fetch(`${BASE}/proposal/accept/${TOKEN}`, { method:'POST' }).then(x=>x.json()).catch(()=>({error:'Falha de rede'}));
        if (r.error) { alert(r.error); return; }
        location.reload();
    }
    async function reject() {
        const reason = document.getElementById('reject-reason').value.trim();
        if (!reason) { alert('Informe o motivo da recusa.'); return; }
        const fd = new URLSearchParams(); fd.append('reason', reason);
        const r = await fetch(`${BASE}/proposal/reject/${TOKEN}`, { method:'POST', body: fd }).then(x=>x.json()).catch(()=>({error:'Falha de rede'}));
        if (r.error) { alert(r.error); return; }
        location.reload();
    }
</script>
</body>
</html>
