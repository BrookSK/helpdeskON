<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nova Demanda - Solicitação Externa</title>
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
        body { font-family: 'Inter', sans-serif; background: #f4f6f9; min-height: 100vh; padding: 24px 16px; }
        .ext-wrap { max-width: 720px; margin: 0 auto; }
        .ext-topbar {
            background: #fff; border-radius: 14px; padding: 16px 20px; margin-bottom: 16px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap;
        }
        .logo-text { color: var(--primary); font-weight: 700; font-size: 1.4rem; }
        .card { border: none; border-radius: 14px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .form-control, .form-select { border-radius: 10px; border: 2px solid #e8e8e8; }
        .form-control:focus, .form-select:focus { border-color: var(--primary); box-shadow: 0 0 0 0.2rem rgba(0,191,166,0.15); }
        .btn-ext { background: var(--primary); border-color: var(--primary); border-radius: 10px; font-weight: 600; }
        .btn-ext:hover { background: var(--primary-dark); border-color: var(--primary-dark); }
    </style>
</head>
<body>
    <div class="ext-wrap">
        <div class="ext-topbar">
            <div class="d-flex align-items-center gap-2">
                <?php $logoUrl = Config::get('app_logo'); ?>
                <?php if ($logoUrl): ?>
                <img src="<?= baseUrl($logoUrl) ?>" alt="Logo" style="max-height:34px;">
                <?php else: ?>
                <span class="logo-text">ON</span>
                <?php endif; ?>
                <div>
                    <div class="fw-semibold" style="font-size:0.95rem;">Nova demanda</div>
                    <div class="text-muted" style="font-size:0.78rem;">Atendente: <?= escape($owner['name']) ?></div>
                </div>
            </div>
            <a href="<?= baseUrl('solicitacaoexterna/logout') ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-box-arrow-right"></i> Sair
            </a>
        </div>

        <?php if ($error = flash('error')): ?>
            <div class="alert alert-danger alert-dismissible fade show py-2" style="font-size:0.88rem" role="alert">
                <?= escape($error) ?>
                <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body p-4">
                <form action="<?= baseUrl('solicitacaoexterna/store') ?>" method="POST" enctype="multipart/form-data">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-medium">Seu nome</label>
                            <input type="text" name="requester_name" class="form-control" placeholder="Como podemos te identificar?">
                            <small class="text-muted">Opcional. Ajuda o atendente a saber quem abriu a demanda.</small>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-medium">Título *</label>
                            <input type="text" name="title" class="form-control" placeholder="Resumo da sua demanda" required>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-medium">Categoria</label>
                            <select name="category" class="form-select">
                                <option value="">Selecione</option>
                                <option value="design">Design</option>
                                <option value="desenvolvimento">Desenvolvimento</option>
                                <option value="marketing">Marketing</option>
                                <option value="suporte">Suporte</option>
                                <option value="outro">Outro</option>
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-medium">Prioridade</label>
                            <select name="priority" class="form-select">
                                <option value="low">Baixa</option>
                                <option value="medium" selected>Média</option>
                                <option value="high">Alta</option>
                                <option value="urgent">Urgente</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-medium">Descrição *</label>
                            <textarea name="description" class="form-control" rows="6" placeholder="Descreva detalhadamente sua demanda..." required></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-medium">Anexos</label>
                            <input type="file" name="attachments[]" class="form-control" multiple accept="image/*,video/*,.pdf,.doc,.docx">
                            <small class="text-muted">JPG, PNG, GIF, PDF, DOC, MP4, WebM.</small>
                        </div>
                        <div class="col-12 d-flex gap-2 flex-wrap">
                            <button type="submit" class="btn btn-ext btn-primary text-white px-4">
                                <i class="bi bi-send"></i> Enviar demanda
                            </button>
                            <a href="<?= baseUrl('solicitacaoexterna/logout') ?>" class="btn btn-outline-secondary px-4">Cancelar</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="text-center mt-3">
            <small class="text-muted" style="font-size:0.75rem">&copy; <?= date('Y') ?> ON Solutions.</small>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
