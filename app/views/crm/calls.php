<?php $pageTitle = 'Ligações - CRM'; $currentPage = 'crm_calls'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<?php
$statusMeta = [
    'dialing'   => ['Discando', '#6c757d'],
    'ringing'   => ['Tocando', '#f59e0b'],
    'answered'  => ['Atendida', '#00997D'],
    'ended'     => ['Encerrada', '#3b82f6'],
];
$dirMeta = [
    'outbound' => ['Saída', 'bi-telephone-outbound', '#00997D'],
    'inbound'  => ['Entrada', 'bi-telephone-inbound', '#1565c0'],
];
$fmtDur = function($s) {
    $s = intval($s);
    if ($s <= 0) return '—';
    return sprintf('%02d:%02d', intdiv($s, 60), $s % 60);
};
?>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0"><i class="bi bi-telephone"></i> Ligações</h5>
            <small class="text-muted">Registro das chamadas realizadas e recebidas pelo CRM</small>
        </div>
        <a href="<?= baseUrl('crm/leads') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Meus leads</a>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <?php if (empty($calls)): ?>
            <div class="text-center text-muted py-5">
                <i class="bi bi-telephone-x" style="font-size:2rem;"></i>
                <p class="mb-0 mt-2">Nenhuma ligação registrada.</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size:0.85rem;">
                    <thead class="table-light">
                        <tr>
                            <th>Data</th>
                            <th>Direção</th>
                            <th>Contato</th>
                            <th>Número</th>
                            <th>Usuário</th>
                            <th>Situação</th>
                            <th>Duração</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($calls as $c):
                            $dm = $dirMeta[$c['direction']] ?? ['—', 'bi-telephone', '#888'];
                            $sm = $statusMeta[$c['status']] ?? [$c['status'] ?: '—', '#888'];
                            $num = $c['direction'] === 'inbound' ? $c['caller'] : $c['called'];
                            $name = $c['contact_name'] ?: ($c['push_name'] ?: '—');
                        ?>
                        <tr>
                            <td class="text-nowrap"><?= date('d/m/Y H:i', strtotime($c['created_at'])) ?></td>
                            <td><span style="color:<?= $dm[2] ?>;"><i class="bi <?= $dm[1] ?>"></i> <?= $dm[0] ?></span></td>
                            <td><?= escape($name) ?></td>
                            <td><?= escape($num ?: '—') ?></td>
                            <td><?= escape($c['user_name'] ?: '—') ?></td>
                            <td><span class="badge rounded-pill" style="background:<?= $sm[1] ?>1a;color:<?= $sm[1] ?>;"><?= escape($sm[0]) ?></span></td>
                            <td><?= $fmtDur($c['duration_seconds']) ?></td>
                            <td class="text-end">
                                <?php if (in_array($c['status'], ['dialing', 'ringing', 'answered'], true)): ?>
                                <button class="btn btn-sm btn-outline-danger" title="Encerrar registro" onclick="endCall(<?= $c['id'] ?>, this)">
                                    <i class="bi bi-x-circle"></i> Encerrar
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($totalPages) && $totalPages > 1): ?>
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2 py-2">
            <small class="text-muted">
                Página <?= $page ?> de <?= $totalPages ?> · <?= number_format($total, 0, ',', '.') ?> ligações
            </small>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= baseUrl('crm/calls?page=' . ($page - 1)) ?>">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    </li>
                    <?php
                        $start = max(1, $page - 2);
                        $end = min($totalPages, $page + 2);
                        if ($start > 1) {
                            echo '<li class="page-item"><a class="page-link" href="' . baseUrl('crm/calls?page=1') . '">1</a></li>';
                            if ($start > 2) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                        }
                        for ($p = $start; $p <= $end; $p++):
                    ?>
                    <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                        <a class="page-link" href="<?= baseUrl('crm/calls?page=' . $p) ?>"><?= $p ?></a>
                    </li>
                    <?php
                        endfor;
                        if ($end < $totalPages) {
                            if ($end < $totalPages - 1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                            echo '<li class="page-item"><a class="page-link" href="' . baseUrl('crm/calls?page=' . $totalPages) . '">' . $totalPages . '</a></li>';
                        }
                    ?>
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= baseUrl('crm/calls?page=' . ($page + 1)) ?>">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
const BASE = '<?= baseUrl('') ?>';
// Encerra manualmente um registro de ligação preso (dialing/ringing/answered)
function endCall(id, btn) {
    if (!confirm('Encerrar este registro de ligação?')) return;
    btn.disabled = true;
    const fd = new FormData();
    fd.append('event', 'ended');
    fd.append('duration', '0');
    fd.append('cause', 'manual');
    fetch(BASE + 'crm/callEvent/' + id, { method: 'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json())
        .then(d => { if (d.error) { alert(d.error); btn.disabled = false; return; } location.reload(); })
        .catch(() => { btn.disabled = false; alert('Erro ao encerrar.'); });
}
</script>
<?php require APP_PATH . '/views/layouts/footer.php'; ?>
