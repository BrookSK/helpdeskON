<?php $pageTitle = 'Sequências de E-mail - CRM'; $currentPage = 'sequences'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0"><i class="bi bi-diagram-3"></i> Sequências de E-mail</h5>
            <small class="text-muted">Follow-up automático de leads do CRM</small>
        </div>
        <a href="<?= baseUrl('sequences/edit') ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> Nova sequência</a>
    </div>

    <div class="row g-3">
        <?php if (empty($sequences)): ?>
        <div class="col-12">
            <div class="card"><div class="card-body text-center py-5">
                <i class="bi bi-diagram-3" style="font-size:3rem;color:#ccc;"></i>
                <h6 class="mt-3">Nenhuma sequência criada</h6>
                <p class="text-muted">Crie uma sequência para automatizar follow-ups de e-mail.</p>
                <a href="<?= baseUrl('sequences/edit') ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Criar sequência</a>
            </div></div>
        </div>
        <?php else: ?>
        <?php foreach ($sequences as $s): ?>
        <div class="col-md-6 col-lg-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h6 class="mb-0"><?= escape($s['name']) ?></h6>
                        <span class="badge <?= $s['is_active'] ? 'bg-success' : 'bg-secondary' ?>"><?= $s['is_active'] ? 'Ativa' : 'Inativa' ?></span>
                    </div>
                    <?php if ($s['description']): ?>
                    <p class="text-muted small mb-2"><?= escape($s['description']) ?></p>
                    <?php endif; ?>
                    <div class="d-flex gap-3 small text-muted mb-3">
                        <span><i class="bi bi-people"></i> <?= (int)$s['total_participants'] ?> leads</span>
                        <span><i class="bi bi-play-circle"></i> <?= (int)$s['active_participants'] ?> ativos</span>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="<?= baseUrl('sequences/edit/' . $s['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Editar</a>
                        <button class="btn btn-sm btn-outline-danger" onclick="delSeq(<?= $s['id'] ?>)"><i class="bi bi-trash"></i></button>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
const BASE = '<?= baseUrl('') ?>';
function delSeq(id) {
    if (!confirm('Excluir esta sequência? Os participantes e o histórico serão removidos.')) return;
    fetch(BASE + 'sequences/delete/' + id, { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'} })
        .then(r=>r.json()).then(d=>{ if(d.error){alert(d.error);return;} location.reload(); });
}
</script>
<?php require APP_PATH . '/views/layouts/footer.php'; ?>
