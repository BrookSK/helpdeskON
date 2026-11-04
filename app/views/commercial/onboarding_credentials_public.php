<?php
$appName = htmlspecialchars((string) Config::get('app_name') ?: 'ON Solutions', ENT_QUOTES);
$logo = Config::get('app_logo');
$ok = !empty($check['ok']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Envio de credenciais · <?= $appName ?></title>
    <?php if ($fav = Config::get('app_favicon')): ?><link rel="icon" href="<?= baseUrl($fav) ?>"><?php endif; ?>
    <style>
        * { box-sizing:border-box; }
        body { margin:0; background:#f3f4f8; color:#1b1d2a; font-family:'Segoe UI',system-ui,Arial,sans-serif; }
        .sheet { max-width:620px; margin:24px auto; background:#fff; border-radius:10px; box-shadow:0 8px 30px rgba(0,0,0,.08); overflow:hidden; }
        .hd { display:flex; align-items:center; gap:14px; padding:20px 28px; border-bottom:3px solid #00BFA6; }
        .hd img { max-height:42px; } .hd .co { font-size:1.1rem; font-weight:700; }
        .bd { padding:24px 28px; line-height:1.55; }
        .banner { padding:12px 16px; border-radius:8px; font-size:.92rem; margin-bottom:14px; }
        .banner.ok { background:#e6f7f3; color:#0b8c7a; } .banner.err { background:#fdecea; color:#b03a2e; } .banner.info { background:#eef2ff; color:#3a46a0; }
        label { display:block; font-size:.85rem; font-weight:600; margin:12px 0 4px; }
        input, textarea { width:100%; border:1px solid #cfd3e2; border-radius:8px; padding:10px; font:inherit; }
        .btn { border:none; border-radius:8px; padding:11px 18px; font-size:.95rem; cursor:pointer; background:#00BFA6; color:#fff; margin-top:16px; }
        .btn:disabled { opacity:.6; }
        .ft { padding:14px 28px; border-top:1px solid #e6e8f0; font-size:.78rem; color:#6b7088; text-align:center; }
        @media (max-width:640px) { .sheet { margin:0; border-radius:0; } .bd, .hd { padding-left:16px; padding-right:16px; } }
    </style>
</head>
<body>
<div class="sheet">
    <div class="hd">
        <?php if ($logo): ?><img src="<?= baseUrl($logo) ?>" alt=""><?php endif; ?>
        <div class="co"><?= $appName ?></div>
    </div>
    <div class="bd">
        <?php if (!$ok): ?>
        <div class="banner err" id="link-invalid"><?= htmlspecialchars($check['error'] ?? 'Este link não é válido.', ENT_QUOTES) ?></div>
        <?php else: ?>
        <h3 style="margin-top:0">Envio de acessos</h3>
        <p>Use este formulário para enviar os acessos que a equipe pediu (hospedagem, domínio, e-mail, painel). Os dados vão criptografados para o cofre da equipe e não aparecem de novo nesta página.</p>
        <div class="banner info">Este link vale até <?= date('d/m/Y H:i', strtotime($check['link']['expires_at'])) ?>. Envie um acesso por vez.</div>
        <div class="banner ok" id="sent-ok" style="display:none">Acesso recebido. Você pode enviar outro.</div>
        <div class="banner err" id="sent-err" style="display:none"></div>
        <form id="cred-form" autocomplete="off" onsubmit="return sendCred(event)">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <label for="service_label">Serviço</label>
            <input id="service_label" name="service_label" maxlength="160" required placeholder="ex.: Hospedagem, Registro.br, Painel do e-mail">
            <label for="url">Endereço de acesso (opcional)</label>
            <input id="url" name="url" maxlength="500" placeholder="https://...">
            <label for="username">Usuário</label>
            <input id="username" name="username" maxlength="255" autocomplete="off">
            <label for="secret">Senha ou token</label>
            <input id="secret" name="secret" type="password" autocomplete="new-password">
            <label for="notes">Observação (opcional)</label>
            <textarea id="notes" name="notes" rows="3"></textarea>
            <button class="btn" id="btn-send" type="submit">Enviar com segurança</button>
        </form>
        <?php endif; ?>
    </div>
    <div class="ft"><?= $appName ?> · os dados são gravados criptografados</div>
</div>
<?php if ($ok): ?>
<script>
async function sendCred(ev) {
    ev.preventDefault();
    const form = document.getElementById('cred-form');
    const btn = document.getElementById('btn-send');
    const okBox = document.getElementById('sent-ok'), errBox = document.getElementById('sent-err');
    okBox.style.display = 'none'; errBox.style.display = 'none';
    btn.disabled = true;
    const r = await fetch(location.pathname, { method: 'POST', body: new FormData(form), headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(x => x.json()).catch(() => ({ error: 'Falha de rede. Tente de novo.' }));
    btn.disabled = false;
    if (r.error) { errBox.textContent = r.error; errBox.style.display = 'block'; return false; }
    ['service_label', 'url', 'username', 'secret', 'notes'].forEach(id => document.getElementById(id).value = '');
    okBox.style.display = 'block';
    return false;
}
</script>
<?php endif; ?>
</body>
</html>
