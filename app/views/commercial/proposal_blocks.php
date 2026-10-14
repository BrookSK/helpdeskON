<?php $pageTitle = 'Conteúdo fixo da proposta - ON Solutions Helpdesk'; $currentPage = 'proposals'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0">Conteúdo fixo da proposta</h5>
            <small class="text-muted">Texto igual em todas as propostas. Serviços, valores, cliente e cronograma continuam em cada proposta.</small>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= baseUrl('proposal') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Propostas</a>
            <?php if (empty($missing)): ?>
            <button class="btn btn-sm btn-success" id="btn-save-blocks" onclick="saveBlocks()"><i class="bi bi-check-lg"></i> Salvar</button>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($missing)): ?>
    <div class="alert alert-warning">A tabela de conteúdo fixo ainda não existe neste banco. Aplique a migration <code>162_proposal_document.sql</code>.</div>
    <?php endif; ?>

    <div class="row g-3">
        <?php foreach ($blocks as $b): ?>
        <div class="col-12">
            <div class="card block-card" data-id="<?= (int)($b['id'] ?? 0) ?>">
                <div class="card-body">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-8">
                            <label class="form-label small fw-medium">Título da seção</label>
                            <input class="form-control form-control-sm b-title" value="<?= escape($b['title'] ?? '') ?>" <?= !empty($missing) ? 'disabled' : '' ?>>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-medium">Onde aparece no link</label>
                            <select class="form-select form-select-sm b-place" <?= !empty($missing) ? 'disabled' : '' ?>>
                                <option value="before" <?= (($b['placement'] ?? '') !== 'after') ? 'selected' : '' ?>>Antes dos serviços</option>
                                <option value="after" <?= (($b['placement'] ?? '') === 'after') ? 'selected' : '' ?>>Depois do cronograma</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-medium">Texto</label>
                            <textarea class="form-control form-control-sm b-body" rows="5" <?= !empty($missing) ? 'disabled' : '' ?>><?= escape($b['body'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<script>
const PROP_BASE = '<?= baseUrl("") ?>';
const CSRF = '<?= csrf_token() ?>';
async function saveBlocks() {
    const blocks = [];
    document.querySelectorAll('.block-card').forEach(card => {
        blocks.push({
            id: card.getAttribute('data-id'),
            title: card.querySelector('.b-title').value,
            placement: card.querySelector('.b-place').value,
            body: card.querySelector('.b-body').value,
        });
    });
    const btn = document.getElementById('btn-save-blocks');
    btn.disabled = true;
    try {
        const r = await fetch(`${PROP_BASE}proposal/saveBlocks`, {
            method: 'POST',
            headers: {'Content-Type':'application/json','X-CSRF-Token':CSRF,'X-Requested-With':'XMLHttpRequest'},
            body: JSON.stringify({ blocks })
        }).then(x => x.json());
        if (r.error) { alert(r.error); return; }
        btn.innerHTML = '<i class="bi bi-check-lg"></i> Salvo';
    } catch (e) {
        alert('Falha de rede ao salvar.');
    } finally {
        btn.disabled = false;
    }
}
</script>
<?php require APP_PATH . '/views/layouts/footer.php'; ?>
