<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>
<?php $isNew = empty($template); ?>
<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0"><?= $isNew ? 'Novo modelo de contrato' : 'Editar modelo #' . (int)$template['id'] ?></h5>
            <small class="text-muted">O corpo é copiado ao gerar um contrato e pode ser ajustado por cliente</small>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= baseUrl('contract/templates') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
            <button id="btn-save-tpl" class="btn btn-sm btn-success" onclick="saveTemplate()"><i class="bi bi-check-lg"></i> Salvar modelo</button>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label small fw-medium">Nome do modelo *</label>
                <input id="t-name" class="form-control form-control-sm" value="<?= escape($template['name'] ?? '') ?>" placeholder="Ex.: Contrato de desenvolvimento (padrão)">
            </div>
            <div class="mb-2">
                <label class="form-label small fw-medium mb-1">Variáveis disponíveis <small class="text-muted">(clique para inserir no texto)</small></label>
                <div class="d-flex flex-wrap gap-1">
                    <?php foreach (($vars ?? []) as $key => $desc): ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size:0.78rem;"
                            onclick="insertVar('<?= escape($key) ?>')" title="<?= escape($desc) ?>">
                        {{<?= escape($key) ?>}}
                    </button>
                    <?php endforeach; ?>
                </div>
                <small class="text-muted d-block mt-1">Essas variáveis são preenchidas automaticamente com os dados do cliente/proposta ao gerar o contrato.</small>
            </div>
            <label class="form-label small fw-medium mb-0">Corpo do contrato (aceita HTML)</label>
            <textarea id="t-body" class="form-control" rows="16" style="font-family:ui-monospace,Menlo,Consolas,monospace;font-size:0.85rem;"><?= escape($template['body'] ?? '') ?></textarea>
        </div>
    </div>
</div>

<script>
const BASE = '<?= baseUrl("") ?>';
const CSRF = '<?= csrf_token() ?>';
const TPL_ID = <?= $isNew ? 'null' : (int)$template['id'] ?>;

// Insere {{variavel}} na posição do cursor do textarea do corpo.
function insertVar(key) {
    const ta = document.getElementById('t-body');
    const token = '{{' + key + '}}';
    const start = ta.selectionStart ?? ta.value.length;
    const end = ta.selectionEnd ?? ta.value.length;
    ta.value = ta.value.slice(0, start) + token + ta.value.slice(end);
    ta.focus();
    const pos = start + token.length;
    ta.setSelectionRange(pos, pos);
}

async function saveTemplate() {
    const name = document.getElementById('t-name').value.trim();
    if (!name) { alert('Informe o nome do modelo.'); return; }
    const btn = document.getElementById('btn-save-tpl');
    const orig = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Salvando...';
    const url = TPL_ID ? `${BASE}contract/saveTemplate/${TPL_ID}` : `${BASE}contract/saveTemplate`;
    const r = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
        body: JSON.stringify({ name, body: document.getElementById('t-body').value })
    }).then(x => x.json()).catch(() => ({ error: 'Falha de rede' }));
    btn.disabled = false; btn.innerHTML = orig;
    if (r.error) { alert(r.error); return; }
    // Após criar, vai para a edição (fica com o id); após editar, volta à lista.
    if (!TPL_ID && r.id) { location.href = `${BASE}contract/editTemplate/${r.id}`; }
    else { location.href = `${BASE}contract/templates`; }
}
</script>
<?php require APP_PATH . '/views/layouts/footer.php'; ?>
