<?php $meetLink = $meeting['meet_link'] ?? null; $when = !empty($meeting['meeting_at']) ? date('d/m/Y \à\s H:i', strtotime($meeting['meeting_at'])) : null; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reunião agendada · ON Solutions Brasil</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>body{background:#f5f7fa;font-family:'Segoe UI',Arial,sans-serif;}</style>
</head>
<body>
<div style="max-width:520px;margin:64px auto;background:#fff;border-radius:14px;box-shadow:0 4px 24px rgba(0,0,0,.08);padding:36px;text-align:center;">
    <i class="bi bi-check-circle-fill text-success" style="font-size:3rem;"></i>
    <h5 class="mt-3">Você já agendou esta reunião</h5>
    <?php if ($when): ?><p class="text-muted mb-1"><?= $when ?></p><?php endif; ?>
    <?php if ($meetLink): ?>
    <a href="<?= htmlspecialchars($meetLink, ENT_QUOTES) ?>" target="_blank" class="btn btn-success btn-sm mt-2"><i class="bi bi-camera-video"></i> Entrar na reunião</a>
    <?php endif; ?>
</div>
</body>
</html>
