<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>
<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0">Modelos de contrato</h5>
            <small class="text-muted">Modelos reutilizáveis usados ao gerar um contrato</small>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= baseUrl('contract') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Contratos</a>
            <a href="<?= baseUrl('contract/editTemplate') ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> Novo modelo</a>
        </div>
    </div>
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light"><tr><th>#</th><th>Nome</th><th>Status</th><th>Criado</th><th class="text-end">Ações</th></tr></thead>
                    <tbody>
                        <?php if (empty($templates)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">Nenhum modelo. Crie o primeiro para agilizar a geração de contratos.</td></tr>
                        <?php else: foreach ($templates as $t): ?>
                        <tr>
                            <td><?= (int)$t['id'] ?></td>
                            <td class="fw-medium"><?= escape($t['name']) ?></td>
                            <td><?= ((int)$t['active'] === 1) ? '<span class="badge bg-success">Ativo</span>' : '<span class="badge bg-secondary">Inativo</span>' ?></td>
                            <td><small class="text-muted"><?= !empty($t['created_at']) ? date('d/m/Y', strtotime($t['created_at'])) : '—' ?></small></td>
                            <td class="text-end">
                                <a href="<?= baseUrl('contract/editTemplate/' . (int)$t['id']) ?>" class="btn btn-sm btn-outline-primary py-0 px-2"><i class="bi bi-pencil"></i> Editar</a>
                                <button class="btn btn-sm btn-outline-secondary py-0 px-2" onclick="toggleTpl(<?= (int)$t['id'] ?>)"><?= ((int)$t['active'] === 1) ? 'Desativar' : 'Ativar' ?></button>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script>
const BASE = '<?= baseUrl("") ?>';
const CSRF = '<?= csrf_token() ?>';
async function toggleTpl(id) {
    const fd = new FormData(); fd.append('csrf_token', CSRF);
    const r = await fetch(`${BASE}contract/toggleTemplate/${id}`, { method:'POST', body: fd, headers:{'X-Requested-With':'XMLHttpRequest'} }).then(x=>x.json()).catch(()=>({error:'Falha de rede'}));
    if (r.error) { alert(r.error); return; } location.reload();
}
</script>
<?php require APP_PATH . '/views/layouts/footer.php'; ?>
