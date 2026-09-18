<?php $pageTitle = 'Gravações - ON Solutions Helpdesk'; $currentPage = 'videocall'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>
<?php
$base = rtrim(baseUrl(''), '/');
function vc_fmt_dur($s) {
    $s = (int)$s; if ($s <= 0) return '—';
    $h = intdiv($s, 3600); $m = intdiv($s % 3600, 60); $sec = $s % 60;
    return $h > 0 ? sprintf('%d:%02d:%02d', $h, $m, $sec) : sprintf('%02d:%02d', $m, $sec);
}
function vc_fmt_size($b) {
    $b = (int)$b; if ($b <= 0) return '—';
    if ($b < 1048576) return round($b / 1024) . ' KB';
    if ($b < 1073741824) return round($b / 1048576, 1) . ' MB';
    return round($b / 1073741824, 2) . ' GB';
}
?>
<div class="main-content">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h5 class="mb-0 fw-semibold"><i class="bi bi-collection-play"></i> Gravações de reuniões</h5>
            <small class="text-muted">Gravações das videochamadas do sistema, com transcrição e resumo por IA.</small>
        </div>
        <a href="<?= $base ?>/agenda" class="btn btn-sm btn-outline-primary"><i class="bi bi-calendar2-week"></i> Ir para a Agenda</a>
    </div>

    <?php if (empty($recs)): ?>
    <div class="card"><div class="card-body text-center text-muted py-5">
        <i class="bi bi-camera-reels" style="font-size:2.4rem;"></i>
        <p class="mt-2 mb-0">Nenhuma gravação ainda. Grave uma videochamada para vê-la aqui.</p>
    </div></div>
    <?php else: ?>
    <div class="card"><div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Reunião</th>
                        <th>Data</th>
                        <th>Duração</th>
                        <th>Tamanho</th>
                        <th>Gravou</th>
                        <th>Transcrição</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recs as $r): ?>
                    <tr>
                        <td>
                            <div class="fw-medium"><?= escape($r['room_title'] ?: 'Videochamada') ?></div>
                            <?php if (($r['visibility'] ?? 'public') === 'private'): ?>
                                <span class="badge bg-secondary" style="font-size:.68rem;"><i class="bi bi-shield-lock"></i> Privada</span>
                            <?php else: ?>
                                <span class="badge bg-info text-dark" style="font-size:.68rem;"><i class="bi bi-globe"></i> Pública</span>
                            <?php endif; ?>
                        </td>
                        <td class="small text-muted"><?= date('d/m/Y H:i', strtotime($r['created_at'])) ?></td>
                        <td class="small"><?= vc_fmt_dur($r['duration_sec']) ?></td>
                        <td class="small"><?= vc_fmt_size($r['file_size']) ?></td>
                        <td class="small"><?= escape($r['recorder_name'] ?? $r['recorded_by_name'] ?? '—') ?></td>
                        <td>
                            <?php $ts = $r['transcribe_status'] ?? 'none'; ?>
                            <?php if ($ts === 'done'): ?>
                                <span class="badge bg-success" style="font-size:.68rem;">Pronta</span>
                            <?php elseif ($ts === 'processing'): ?>
                                <span class="badge bg-warning text-dark" style="font-size:.68rem;">Processando</span>
                            <?php elseif ($ts === 'error'): ?>
                                <span class="badge bg-danger" style="font-size:.68rem;">Erro</span>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <a href="<?= $base ?>/videocall/watch/<?= escape($r['token']) ?>" class="btn btn-sm btn-primary"><i class="bi bi-play-fill"></i> Abrir</a>
                            <button class="btn btn-sm btn-outline-secondary" onclick="copyShare('<?= escape($r['token']) ?>')" title="Copiar link de compartilhamento"><i class="bi bi-link-45deg"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div></div>
    <?php endif; ?>
</div>

<script>
const VC_BASE = '<?= $base ?>';
function copyShare(token) {
    const url = VC_BASE + '/videocall/share/' + token;
    navigator.clipboard?.writeText(url).then(() => {
        if (window.showToast) showToast('Link de compartilhamento copiado!');
        else alert('Link copiado:\n' + url);
    }).catch(() => alert(url));
}
</script>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
