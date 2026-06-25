<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Esqueci minha senha - ON Solutions Helpdesk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
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
        .card-auth {
            background: #fff;
            border-radius: 20px;
            padding: 35px 30px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .btn-primary-custom {
            background: var(--primary);
            border-color: var(--primary);
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            color: #fff;
        }
        .btn-primary-custom:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
            color: #fff;
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
            .card-auth { padding: 28px 22px; border-radius: 16px; }
            .logo-text { font-size: 1.6rem; }
        }
    </style>
</head>
<body>
    <div class="card-auth">
        <div class="text-center mb-4">
            <span class="logo-text">ON</span>
            <span class="fs-4 fw-light text-dark"> Solutions</span>
            <p class="text-muted mt-2 mb-0" style="font-size:0.88rem">Helpdesk</p>
        </div>

        <h5 class="text-center mb-2" style="font-size:1.05rem">Esqueceu sua senha?</h5>
        <p class="text-center text-muted mb-4" style="font-size:0.82rem">Informe seu email e enviaremos um link para redefinição.</p>

        <?php if ($msg = flash('success')): ?>
            <div class="alert alert-success py-2" style="font-size:0.82rem">
                <i class="bi bi-check-circle"></i> <?= escape($msg) ?>
            </div>
        <?php endif; ?>
        <?php if ($msg = flash('error')): ?>
            <div class="alert alert-danger py-2" style="font-size:0.82rem">
                <?= escape($msg) ?>
            </div>
        <?php endif; ?>

        <form action="<?= baseUrl('password/sendReset') ?>" method="POST">
            <div class="mb-4">
                <label class="form-label fw-medium" style="font-size:0.85rem">Email cadastrado</label>
                <input type="email" name="email" class="form-control" placeholder="seu@email.com" required autofocus>
            </div>
            <button type="submit" class="btn btn-primary-custom w-100">
                <i class="bi bi-envelope"></i> Enviar Link de Redefinição
            </button>
        </form>

        <div class="text-center mt-4">
            <a href="<?= baseUrl('login') ?>" class="text-muted text-decoration-none" style="font-size:0.82rem">
                <i class="bi bi-arrow-left"></i> Voltar ao login
            </a>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
