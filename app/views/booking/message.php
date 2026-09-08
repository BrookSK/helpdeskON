<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agendamento · ON Solutions Brasil</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>body{background:#f5f7fa;font-family:'Segoe UI',Arial,sans-serif;}</style>
</head>
<body>
<div style="max-width:520px;margin:64px auto;background:#fff;border-radius:14px;box-shadow:0 4px 24px rgba(0,0,0,.08);padding:36px;text-align:center;">
    <i class="bi bi-info-circle text-secondary" style="font-size:2.6rem;"></i>
    <h5 class="mt-3"><?= htmlspecialchars($msgTitle ?? 'Agendamento') ?></h5>
    <p class="text-muted mb-0"><?= htmlspecialchars($msgText ?? '') ?></p>
</div>
</body>
</html>
