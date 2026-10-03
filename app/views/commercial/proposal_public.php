<?php
$appName = htmlspecialchars((string) Config::get('app_name') ?: 'ON Solutions', ENT_QUOTES);
$logo = Config::get('app_logo');
$token = htmlspecialchars($proposal['public_token'], ENT_QUOTES);
$statusLabels = [
    'draft' => 'Em elaboração', 'ready' => 'Montada', 'sent' => 'Enviada', 'awaiting' => 'Aguardando seu retorno',
    'accepted' => 'Aceita', 'rejected' => 'Recusada', 'cancelled' => 'Cancelada',
];
$total = (float)$proposal['total'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proposta · <?= htmlspecialchars($proposal['title'], ENT_QUOTES) ?></title>
    <?php if ($fav = Config::get('app_favicon')): ?><link rel="icon" href="<?= baseUrl($fav) ?>"><?php endif; ?>
    <style>
        * { box-sizing:border-box; }
        body { margin:0; background:#f3f4f8; color:#1b1d2a; font-family:'Segoe UI',system-ui,Arial,sans-serif; }
        .sheet { max-width:820px; margin:24px auto; background:#fff; border-radius:10px; box-shadow:0 8px 30px rgba(0,0,0,.08); overflow:hidden; }
        .hd { display:flex; align-items:center; gap:14px; padding:22px 32px; border-bottom:3px solid #00BFA6; }
        .hd img { max-height:46px; } .hd .co { font-size:1.15rem; font-weight:700; } .hd .sp { flex:1; }
        .hd .meta { text-align:right; font-size:.8rem; color:#6b7088; }
        .bd { padding:28px 32px; }
        .bd h1 { font-size:1.4rem; margin:.2em 0 .4em; }
        table { width:100%; border-collapse:collapse; margin:16px 0; }
        th, td { text-align:left; padding:8px 6px; border-bottom:1px solid #e6e8f0; font-size:.9rem; }
        th { color:#6b7088; font-weight:600; }
        .tot { text-align:right; font-size:1.2rem; font-weight:700; margin-top:8px; }
        .obs { background:#f7f8fc; border-radius:8px; padding:12px 14px; font-size:.88rem; margin-top:14px; white-space:pre-wrap; }
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
            <div class="meta">
                <div><strong>Proposta comercial</strong></div>
                <?php if (!empty($proposal['validity_date'])): ?><div>Válida até <?= date('d/m/Y', strtotime($proposal['validity_date'])) ?></div><?php endif; ?>
            </div>
        </div>
        <div class="bd">
            <?php if ($proposal['status'] === 'accepted'): ?>
                <div class="banner ok">✓ Você aceitou esta proposta. Obrigado! Em breve daremos sequência.</div>
            <?php elseif ($proposal['status'] === 'rejected'): ?>
                <div class="banner no">Esta proposta foi recusada.</div>
            <?php endif; ?>

            <h1><?= htmlspecialchars($proposal['title'], ENT_QUOTES) ?></h1>
            <?php if (!empty($proposal['client_name'])): ?><p>Para: <strong><?= htmlspecialchars($proposal['client_name'], ENT_QUOTES) ?></strong></p><?php endif; ?>

            <table>
                <thead><tr><th>Item</th><th style="width:70px;">Horas</th><th style="width:110px;">R$/hora</th><th style="width:120px;text-align:right;">Total</th></tr></thead>
                <tbody>
                    <?php foreach ($items as $it): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($it['description'], ENT_QUOTES) ?></strong>
                            <?php if (!empty($it['scope'])): ?><br><small style="color:#6b7088;"><?= htmlspecialchars($it['scope'], ENT_QUOTES) ?></small><?php endif; ?>
                        </td>
                        <td><?= $it['hours'] !== null ? htmlspecialchars($it['hours'], ENT_QUOTES) : '—' ?></td>
                        <td><?= $it['hourly_rate'] !== null ? ('R$ ' . number_format((float)$it['hourly_rate'],2,',','.')) : '—' ?></td>
                        <td style="text-align:right;">R$ <?= number_format((float)$it['amount'],2,',','.') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($items)): ?><tr><td colspan="4" style="color:#6b7088;">Sem itens.</td></tr><?php endif; ?>
                </tbody>
            </table>
            <div class="tot">Total: R$ <?= number_format($total,2,',','.') ?></div>

            <?php if (!empty($proposal['observations'])): ?>
            <div class="obs"><?= htmlspecialchars($proposal['observations'], ENT_QUOTES) ?></div>
            <?php endif; ?>

            <?php if (!$isTerminal): ?>
            <div class="actions">
                <button class="btn btn-ok" onclick="accept()">Aceitar proposta</button>
                <button class="btn btn-no" onclick="document.getElementById('reject-box').style.display='block'">Recusar</button>
            </div>
            <div id="reject-box">
                <label style="font-size:.9rem;">Por favor, informe o motivo da recusa:</label>
                <textarea id="reject-reason" rows="3" placeholder="Ex.: valor acima do previsto, prazo, escopo…"></textarea>
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
