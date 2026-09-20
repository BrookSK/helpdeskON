<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demanda enviada - Solicitação Externa</title>
    <?php
    $faviconUrl = Config::get('app_favicon');
    if ($faviconUrl): ?>
    <link rel="icon" href="<?= baseUrl($faviconUrl) ?>">
    <?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #00BFA6; --primary-dark: #00997D; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px;
        }
        .ext-card { background: #fff; border-radius: 20px; padding: 40px 30px; width: 100%; max-width: 440px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); text-align: center; }
        .btn-ext { background: var(--primary); border-color: var(--primary); border-radius: 10px; padding: 10px 18px; font-weight: 600; }
        .btn-ext:hover { background: var(--primary-dark); border-color: var(--primary-dark); }
    </style>
</head>
<body>
    <div class="ext-card">
        <i class="bi bi-check-circle-fill" style="font-size:3rem;color:var(--primary);"></i>
        <h5 class="mt-3 mb-2 fw-bold">Demanda enviada!</h5>
        <p class="text-muted mb-1">Recebemos sua solicitação<?= !empty($ticketTitle) ? ' "' . escape($ticketTitle) . '"' : '' ?>.</p>
        <p class="text-muted small mb-4">O atendente <strong><?= escape($owner['name']) ?></strong> foi notificado e dará andamento.</p>
        <div class="d-flex gap-2 justify-content-center flex-wrap">
            <a href="<?= baseUrl('solicitacaoexterna/novaDemanda') ?>" class="btn btn-ext btn-primary text-white">
                <i class="bi bi-plus-lg"></i> Nova demanda
            </a>
            <a href="<?= baseUrl('solicitacaoexterna/logout') ?>" class="btn btn-outline-secondary">
                <i class="bi bi-box-arrow-right"></i> Sair
            </a>
        </div>
    </div>
</body>
</html>
