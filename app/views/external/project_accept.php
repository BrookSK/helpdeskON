<?php
/**
 * Página pública de ACEITE FORMAL da entrega de um projeto.
 * Acessada pelo cliente via link com token. Confirmação autenticada por PIN
 * (client_pin, 4 dígitos). Sem login de sessão.
 */
$appName = htmlspecialchars((string) Config::get('app_name') ?: 'ON Solutions', ENT_QUOTES);
$logo    = Config::get('app_logo');
$tk      = htmlspecialchars($token, ENT_QUOTES);
$pname   = htmlspecialchars($project['name'] ?? 'Projeto', ENT_QUOTES);
$cname   = htmlspecialchars($project['company_name'] ?? '', ENT_QUOTES);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aceite da entrega · <?= $pname ?></title>
    <?php if ($fav = Config::get('app_favicon')): ?><link rel="icon" href="<?= baseUrl($fav) ?>"><?php endif; ?>
    <style>
        * { box-sizing:border-box; }
        body { margin:0; background:#f3f4f8; color:#1b1d2a; font-family:'Segoe UI',system-ui,Arial,sans-serif; }
        .sheet { max-width:560px; margin:24px auto; background:#fff; border-radius:10px; box-shadow:0 8px 30px rgba(0,0,0,.08); overflow:hidden; }
        .hd { display:flex; align-items:center; gap:14px; padding:22px 32px; border-bottom:3px solid #00BFA6; }
        .hd img { max-height:46px; } .hd .co { font-size:1.15rem; font-weight:700; }
        .bd { padding:28px 32px; }
        .bd h1 { font-size:1.3rem; margin:.2em 0 .6em; }
        .banner { padding:12px 16px; border-radius:8px; font-size:.9rem; margin-bottom:16px; }
        .banner.ok { background:#e6f7f3; color:#0b8c7a; } .banner.no { background:#fdeaea; color:#c0392b; }
        label { display:block; font-size:.85rem; color:#6b7088; margin-bottom:6px; }
        input[type=text] { width:100%; border:1px solid #cfd3e2; border-radius:8px; padding:12px; font-size:1.2rem; letter-spacing:.3em; text-align:center; }
        .btn { border:none; border-radius:8px; padding:12px 18px; font-size:.98rem; cursor:pointer; width:100%; margin-top:16px; }
        .btn-ok { background:#00BFA6; color:#fff; }
        .ft { padding:16px 32px; border-top:1px solid #e6e8f0; font-size:.78rem; color:#6b7088; text-align:center; }
        .info { background:#f7f8fc; border-radius:8px; padding:12px 14px; font-size:.88rem; margin-bottom:16px; }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="hd">
            <?php if ($logo): ?><img src="<?= baseUrl($logo) ?>" alt="<?= $appName ?>"><?php else: ?><span class="co"><?= $appName ?></span><?php endif; ?>
        </div>
        <div class="bd">
            <?php if ($alreadyAccepted): ?>
                <div class="banner ok">✓ A entrega deste projeto já foi aceita. Obrigado!</div>
                <h1><?= $pname ?></h1>
                <?php if ($cname): ?><p class="info">Cliente: <strong><?= $cname ?></strong></p><?php endif; ?>
            <?php else: ?>
                <h1>Aceite da entrega</h1>
                <div class="info">
                    Projeto: <strong><?= $pname ?></strong><br>
                    <?php if ($cname): ?>Cliente: <strong><?= $cname ?></strong><br><?php endif; ?>
                    Ao confirmar, você declara que recebeu e aceita a entrega. A garantia passa a vigorar a partir desta data.
                </div>
                <div id="banner" class="banner no" style="display:none"></div>
                <form id="accept-form" onsubmit="return false;">
                    <label for="pin">Digite seu PIN de 4 dígitos</label>
                    <input type="text" id="pin" inputmode="numeric" maxlength="4" autocomplete="off" placeholder="0000">
                    <button class="btn btn-ok" id="btn-accept" onclick="confirmAccept()">Confirmar aceite</button>
                </form>
            <?php endif; ?>
        </div>
        <div class="ft"><?= $appName ?></div>
    </div>

    <script>
    const ROOT = '<?= rtrim(baseUrl(''), '/') ?>';
    const TOKEN = '<?= $tk ?>';
    async function confirmAccept() {
        const banner = document.getElementById('banner');
        const pin = document.getElementById('pin').value.trim();
        if (!/^\d{4}$/.test(pin)) {
            banner.textContent = 'Informe os 4 dígitos do seu PIN.';
            banner.style.display = 'block';
            return;
        }
        const btn = document.getElementById('btn-accept');
        btn.disabled = true;
        const fd = new FormData();
        fd.append('pin', pin);
        try {
            const res = await fetch(`${ROOT}/project/confirmAccept/${TOKEN}`, {
                method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await res.json();
            if (data.error) {
                banner.textContent = data.error;
                banner.style.display = 'block';
                btn.disabled = false;
                return;
            }
            location.reload();
        } catch (e) {
            banner.textContent = 'Falha de conexão. Tente novamente.';
            banner.style.display = 'block';
            btn.disabled = false;
        }
    }
    </script>
</body>
</html>
