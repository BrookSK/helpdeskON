<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ON Solutions Helpdesk</title>
    <?php
    require_once dirname(dirname(__DIR__)) . '/core/helpers.php';
    require_once dirname(dirname(__DIR__)) . '/core/Database.php';
    require_once dirname(dirname(__DIR__)) . '/core/Config.php';
    $faviconUrl = Config::get('app_favicon');
    if ($faviconUrl): ?>
    <link rel="icon" href="<?= baseUrl($faviconUrl) ?>">
    <?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #00BFA6;
            --primary-dark: #00997D;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-card {
            background: #fff;
            border-radius: 20px;
            padding: 35px 30px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .btn-login {
            background: var(--primary);
            border-color: var(--primary);
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            font-size: 0.95rem;
        }
        .btn-login:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        .form-control {
            border-radius: 10px;
            padding: 12px 15px;
            border: 2px solid #e8e8e8;
            font-size: 0.9rem;
        }
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(0,191,166,0.15);
        }
        .logo-text {
            color: var(--primary);
            font-weight: 700;
            font-size: 2rem;
        }
        @media (max-width: 480px) {
            .login-card {
                padding: 28px 22px;
                border-radius: 16px;
            }
            .logo-text { font-size: 1.6rem; }
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="text-center mb-4">
            <?php $logoUrl = Config::get('app_logo'); ?>
            <?php if ($logoUrl): ?>
            <img src="<?= baseUrl($logoUrl) ?>" alt="Logo" style="max-height:50px;margin-bottom:8px;">
            <?php else: ?>
            <span class="logo-text">ON</span>
            <span class="fs-4 fw-light text-dark"> Solutions</span>
            <?php endif; ?>
            <p class="text-muted mt-2 mb-0" style="font-size:0.88rem">Helpdesk</p>
        </div>

        <?php if ($error = flash('error')): ?>
            <div class="alert alert-danger alert-dismissible fade show py-2" style="font-size:0.85rem" role="alert">
                <?= escape($error) ?>
                <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form action="<?= baseUrl('login/authenticate') ?>" method="POST">
            <div class="mb-3">
                <label class="form-label fw-medium" style="font-size:0.85rem">Email</label>
                <input type="email" name="email" class="form-control" placeholder="seu@email.com" required autofocus>
            </div>
            <div class="mb-4">
                <label class="form-label fw-medium" style="font-size:0.85rem">Senha</label>
                <input type="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn btn-login btn-primary w-100 text-white">Entrar</button>
        </form>

        <div class="text-center mt-3">
            <a href="<?= baseUrl('password/forgot') ?>" class="text-decoration-none" style="font-size:0.82rem;color:var(--primary);">
                Esqueceu sua senha?
            </a>
        </div>

        <div class="text-center mt-4">
            <small class="text-muted" style="font-size:0.75rem">&copy; <?= date('Y') ?> ON Solutions. Todos os direitos reservados.</small>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
