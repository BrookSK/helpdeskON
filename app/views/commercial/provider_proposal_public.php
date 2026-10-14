<?php
$appName = htmlspecialchars((string) Config::get('app_name') ?: 'ON Solutions', ENT_QUOTES);
$logo = Config::get('app_logo');
$token = htmlspecialchars($provider['public_token'] ?? '', ENT_QUOTES);
$respondido = !empty($provider['proposal_responded_at']);
$aceito = !empty($provider['proposal_accepted_at']);
$valor = $provider['pay_amount'] !== null ? ('R$ ' . number_format((float)$provider['pay_amount'], 2, ',', '.')) : '—';
$payTypeLabels = ['mensal' => 'por mês', 'hora' => 'por hora', 'projeto' => 'por projeto'];
$payType = $payTypeLabels[$provider['pay_type'] ?? ''] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proposta de parceria · <?= $appName ?></title>
    <?php if ($fav = Config::get('app_favicon')): ?><link rel="icon" href="<?= baseUrl($fav) ?>"><?php endif; ?>
    <style>
        * { box-sizing:border-box; }
        body { margin:0; background:#f3f4f8; color:#1b1d2a; font-family:'Segoe UI',system-ui,Arial,sans-serif; }
        .sheet { max-width:760px; margin:24px auto; background:#fff; border-radius:10px; box-shadow:0 8px 30px rgba(0,0,0,.08); overflow:hidden; }
        .hd { display:flex; align-items:center; gap:14px; padding:22px 32px; border-bottom:3px solid #00BFA6; }
        .hd img { max-height:46px; } .hd .co { font-size:1.15rem; font-weight:700; } .hd .sp { flex:1; }
        .hd .meta { text-align:right; font-size:.8rem; color:#6b7088; }
        .bd { padding:28px 32px; }
        .bd h1 { font-size:1.4rem; margin:.2em 0 .4em; }
        .grid { display:grid; grid-template-columns:1fr 1fr; gap:10px 24px; margin:16px 0; }
        .grid .lbl { color:#6b7088; font-size:.78rem; } .grid .val { font-weight:600; }
        .block { background:#f7f8fc; border-radius:8px; padding:12px 14px; font-size:.9rem; margin-top:12px; white-space:pre-wrap; }
        .actions { display:flex; gap:10px; margin-top:22px; }
        .btn { border:none; border-radius:8px; padding:11px 18px; font-size:.95rem; cursor:pointer; }
        .btn-ok { background:#00BFA6; color:#fff; } .btn-no { background:#fff; color:#c0392b; border:1px solid #c0392b; }
        .ft { padding:16px 32px; border-top:1px solid #e6e8f0; font-size:.78rem; color:#6b7088; text-align:center; }
        .banner { padding:12px 16px; border-radius:8px; font-size:.9rem; margin-bottom:12px; }
        .banner.ok { background:#e6f7f3; color:#0b8c7a; } .banner.no { background:#fdeaea; color:#c0392b; }
        #reject-box { display:none; margin-top:12px; }
        textarea { width:100%; border:1px solid #cfd3e2; border-radius:8px; padding:10px; font-family:inherit; }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="hd">
            <?php if ($logo): ?><img src="<?= baseUrl($logo) ?>" alt="<?= $appName ?>"><?php else: ?><span class="co"><?= $appName ?></span><?php endif; ?>
            <span class="sp"></span>
            <div class="meta"><strong>Proposta de parceria</strong></div>
        </div>
        <div class="bd">
            <?php if ($aceito): ?>
                <div class="banner ok">✓ Você aceitou esta proposta. Em breve enviaremos o contrato para assinatura.</div>
            <?php elseif ($respondido): ?>
                <div class="banner no">Esta proposta foi recusada.</div>
            <?php endif; ?>

            <h1>Olá, <?= htmlspecialchars($provider['name'] ?? '', ENT_QUOTES) ?>!</h1>
            <p>Segue nossa proposta de parceria<?= !empty($provider['role_title']) ? ' para a função de <strong>' . htmlspecialchars($provider['role_title'], ENT_QUOTES) . '</strong>' : '' ?>.</p>

            <div class="grid">
                <div><div class="lbl">Tipo de contratação</div><div class="val"><?= htmlspecialchars(strtoupper($provider['engagement_type'] ?? '—'), ENT_QUOTES) ?></div></div>
                <div><div class="lbl">Remuneração</div><div class="val"><?= $valor ?> <?= $payType ?></div></div>
                <?php if (!empty($provider['pay_term'])): ?><div><div class="lbl">Prazo / condições</div><div class="val"><?= htmlspecialchars($provider['pay_term'], ENT_QUOTES) ?></div></div><?php endif; ?>
                <?php if (!empty($provider['payment_method'])): ?><div><div class="lbl">Forma de pagamento</div><div class="val"><?= htmlspecialchars($provider['payment_method'], ENT_QUOTES) ?></div></div><?php endif; ?>
                <?php if (!empty($provider['workload'])): ?><div><div class="lbl">Jornada</div><div class="val"><?= htmlspecialchars($provider['workload'], ENT_QUOTES) ?></div></div><?php endif; ?>
                <?php if (!empty($provider['work_model'])): ?><div><div class="lbl">Modelo de trabalho</div><div class="val"><?= htmlspecialchars(ucfirst($provider['work_model']), ENT_QUOTES) ?></div></div><?php endif; ?>
                <?php if (!empty($provider['availability'])): ?><div><div class="lbl">Disponibilidade</div><div class="val"><?= htmlspecialchars($provider['availability'], ENT_QUOTES) ?></div></div><?php endif; ?>
            </div>

            <?php if (!empty($provider['scope'])): ?>
                <div class="lbl" style="color:#6b7088;font-size:.78rem;">Escopo de atuação</div>
                <div class="block"><?= htmlspecialchars($provider['scope'], ENT_QUOTES) ?></div>
            <?php endif; ?>
            <?php if (!empty($provider['out_of_scope'])): ?>
                <div class="lbl" style="color:#6b7088;font-size:.78rem;margin-top:10px;">O que não faz parte</div>
                <div class="block"><?= htmlspecialchars($provider['out_of_scope'], ENT_QUOTES) ?></div>
            <?php endif; ?>

            <?php if ($canDecide): ?>
            <div class="actions">
                <button class="btn btn-ok" onclick="accept()">Aceitar proposta</button>
                <button class="btn btn-no" onclick="document.getElementById('reject-box').style.display='block'">Recusar</button>
            </div>
            <div id="reject-box">
                <label style="font-size:.9rem;">Por favor, informe o motivo da recusa:</label>
                <textarea id="reject-reason" rows="3" placeholder="Ex.: valor, jornada, modelo de trabalho…"></textarea>
                <div class="actions"><button class="btn btn-no" onclick="reject()">Confirmar recusa</button></div>
            </div>
            <?php endif; ?>
        </div>
        <div class="ft"><?= $appName ?></div>
    </div>

    <script>
        const BASE = '<?= rtrim(baseUrl(''), '/') ?>';
        const TOKEN = '<?= $token ?>';
        async function accept() {
            if (!confirm('Confirmar o aceite desta proposta?')) return;
            const r = await fetch(`${BASE}/provider/acceptProposal/${TOKEN}`, { method:'POST' }).then(x=>x.json()).catch(()=>({error:'Falha de rede'}));
            if (r.error) { alert(r.error); return; }
            location.reload();
        }
        async function reject() {
            const reason = document.getElementById('reject-reason').value.trim();
            if (!reason) { alert('Informe o motivo da recusa.'); return; }
            const fd = new URLSearchParams(); fd.append('reason', reason);
            const r = await fetch(`${BASE}/provider/rejectProposal/${TOKEN}`, { method:'POST', body: fd }).then(x=>x.json()).catch(()=>({error:'Falha de rede'}));
            if (r.error) { alert(r.error); return; }
            location.reload();
        }
    </script>
</body>
</html>
