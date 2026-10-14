<?php
$appName = htmlspecialchars((string) Config::get('app_name') ?: 'ON Solutions', ENT_QUOTES);
$logo = Config::get('app_logo');
$token = htmlspecialchars($contract['public_token'], ENT_QUOTES);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contrato · <?= htmlspecialchars($contract['title'], ENT_QUOTES) ?></title>
    <?php if ($fav = Config::get('app_favicon')): ?><link rel="icon" href="<?= baseUrl($fav) ?>"><?php endif; ?>
    <style>
        * { box-sizing:border-box; }
        body { margin:0; background:#f3f4f8; color:#1b1d2a; font-family:'Segoe UI',system-ui,Arial,sans-serif; }
        .sheet { max-width:820px; margin:24px auto; background:#fff; border-radius:10px; box-shadow:0 8px 30px rgba(0,0,0,.08); overflow:hidden; }
        .hd { display:flex; align-items:center; gap:14px; padding:22px 32px; border-bottom:3px solid #00BFA6; }
        .hd img { max-height:46px; } .hd .co { font-size:1.15rem; font-weight:700; } .hd .sp { flex:1; }
        .bd { padding:28px 32px; line-height:1.6; }
        .banner { padding:12px 16px; border-radius:8px; font-size:.9rem; margin-bottom:14px; }
        .banner.ok { background:#e6f7f3; color:#0b8c7a; } .banner.info { background:#eef2ff; color:#3a46a0; }
        .actions { display:flex; gap:10px; margin-top:22px; }
        .btn { border:none; border-radius:8px; padding:11px 18px; font-size:.95rem; cursor:pointer; }
        .btn-ok { background:#00BFA6; color:#fff; } .btn-no { background:#fff; color:#c0392b; border:1px solid #c0392b; }
        .ft { padding:16px 32px; border-top:1px solid #e6e8f0; font-size:.78rem; color:#6b7088; text-align:center; }
        #adj { display:none; margin-top:12px; } textarea { width:100%; border:1px solid #cfd3e2; border-radius:8px; padding:10px; font-family:inherit; }
        @media print { .actions, #adj { display:none; } .sheet { box-shadow:none; margin:0; } }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="hd">
            <?php if ($logo): ?><img src="<?= baseUrl($logo) ?>" alt="<?= $appName ?>"><?php else: ?><span class="co"><?= $appName ?></span><?php endif; ?>
            <span class="sp"></span><strong>Contrato</strong>
        </div>
        <div class="bd">
            <?php if ($contract['status'] === 'approved' || $contract['status'] === 'awaiting_signature'): ?>
                <div class="banner ok">✓ Você aprovou este contrato. Em breve enviaremos para assinatura.</div>
            <?php elseif ($contract['status'] === 'signed'): ?>
                <div class="banner ok">✓ Contrato assinado. Obrigado!</div>
            <?php elseif ($contract['status'] === 'client_rejected'): ?>
                <div class="banner info">Você solicitou ajustes. Vamos revisar e reenviar.</div>
            <?php endif; ?>

            <div><?= $contract['body'] /* corpo é HTML confiável, montado pela equipe */ ?></div>

            <?php if ($canDecide): ?>
            <div class="actions">
                <button class="btn btn-ok" onclick="approve()">Aprovar contrato</button>
                <button class="btn btn-no" onclick="document.getElementById('adj').style.display='block'">Pedir ajuste</button>
            </div>
            <div id="adj">
                <label style="font-size:.9rem;">O que precisa ser ajustado?</label>
                <textarea id="reason" rows="3" placeholder="Descreva o ajuste…"></textarea>
                <div class="actions"><button class="btn btn-no" onclick="requestChanges()">Enviar pedido de ajuste</button></div>
            </div>
            <?php endif; ?>
        </div>
        <div class="ft"><?= $appName ?></div>
    </div>
    <script>
        const BASE = '<?= rtrim(baseUrl(''), '/') ?>';
        const TOKEN = '<?= $token ?>';
        async function approve() {
            if (!confirm('Confirmar a aprovação deste contrato?')) return;
            const r = await fetch(`${BASE}/contract/approve/${TOKEN}`, { method:'POST' }).then(x=>x.json()).catch(()=>({error:'Falha de rede'}));
            if (r.error) { alert(r.error); return; } location.reload();
        }
        async function requestChanges() {
            const reason = document.getElementById('reason').value.trim();
            if (!reason) { alert('Descreva o ajuste.'); return; }
            const fd = new URLSearchParams(); fd.append('reason', reason);
            const r = await fetch(`${BASE}/contract/requestChanges/${TOKEN}`, { method:'POST', body: fd }).then(x=>x.json()).catch(()=>({error:'Falha de rede'}));
            if (r.error) { alert(r.error); return; } location.reload();
        }
    </script>
</body>
</html>
