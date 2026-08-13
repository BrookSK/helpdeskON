<?php
// Modais e funções JS das ações sociais (importar Meta, add LinkedIn, sync, snapshot).
// Incluído no final do dashboard.php.
?>

<!-- Modal Add LinkedIn -->
<div class="modal fade" id="linkedinModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-linkedin text-primary"></i> Adicionar organização LinkedIn</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <label class="form-label small fw-medium">ID ou URN da organização</label>
                    <input type="text" id="li-org-id" class="form-control form-control-sm" placeholder="Ex: 12345678">
                    <small class="text-muted">Configurações da página &gt; ID da página.</small>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-medium">Nome (opcional)</label>
                    <input type="text" id="li-name" class="form-control form-control-sm" placeholder="Ex: ON Solutions Brasil">
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-medium">Access Token (opcional)</label>
                    <input type="text" id="li-token" class="form-control form-control-sm" placeholder="Se diferente do global">
                    <small class="text-muted">Use para contas de outros clientes com token próprio.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-sm btn-primary" onclick="addLinkedin(this)">Adicionar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Importar Meta -->
<div class="modal fade" id="metaImportModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-meta text-primary"></i> Importar contas da Meta</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2">Importa páginas do Facebook e contas Instagram Business vinculadas.</p>
                <div class="mb-2">
                    <label class="form-label small fw-medium">Access Token (opcional)</label>
                    <input type="text" id="meta-token" class="form-control form-control-sm" placeholder="Se diferente do global">
                    <small class="text-muted">Se vazio, usa todos os tokens salvos em Configurações.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-sm btn-primary" onclick="doImportMeta(this)">Importar</button>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    const B = '<?= baseUrl("") ?>';

    // Importar Meta
    let metaModal = null;
    window.importMeta = function(btn) {
        document.getElementById('meta-token').value = '';
        if (!metaModal) metaModal = new bootstrap.Modal(document.getElementById('metaImportModal'));
        metaModal.show();
    };
    window.doImportMeta = function(btn) {
        const o = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        const fd = new FormData();
        const t = document.getElementById('meta-token').value.trim();
        if (t) fd.append('access_token', t);
        fetch(B + 'social/importMeta', { method: 'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
            .then(r => r.json()).then(d => { btn.disabled = false; btn.innerHTML = o; if (d.error) { alert(d.error); return; } alert((d.imported||0) + ' conta(s) importada(s).'); location.reload(); })
            .catch(() => { btn.disabled = false; btn.innerHTML = o; });
    };

    // Sync Social
    window.syncSocial = function(btn) {
        const o = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        fetch(B + 'social/syncMetrics', { method: 'POST', headers: {'X-Requested-With':'XMLHttpRequest'} })
            .then(r => r.json()).then(d => { btn.disabled = false; btn.innerHTML = o; if (d.error) { alert(d.error); return; } location.reload(); })
            .catch(() => { btn.disabled = false; btn.innerHTML = o; });
    };

    // Snapshot
    window.snapshotFollowers = function(btn) {
        const o = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        fetch(B + 'social/snapshotFollowers', { method: 'POST', headers: {'X-Requested-With':'XMLHttpRequest'} })
            .then(r => r.json()).then(d => { btn.disabled = false; btn.innerHTML = o; if (d.error) { alert(d.error); return; } alert((d.snapshots_saved||0) + ' conta(s) salva(s) no histórico.'); location.reload(); })
            .catch(() => { btn.disabled = false; btn.innerHTML = o; });
    };

    // LinkedIn
    let liModal = null;
    window.openLinkedinModal = function() {
        document.getElementById('li-org-id').value = '';
        document.getElementById('li-name').value = '';
        document.getElementById('li-token').value = '';
        if (!liModal) liModal = new bootstrap.Modal(document.getElementById('linkedinModal'));
        liModal.show();
    };
    window.addLinkedin = function(btn) {
        const orgId = document.getElementById('li-org-id').value.trim();
        if (!orgId) { alert('Informe o ID/URN da organização.'); return; }
        const fd = new FormData();
        fd.append('org_id', orgId);
        fd.append('display_name', document.getElementById('li-name').value.trim());
        const t = document.getElementById('li-token').value.trim();
        if (t) fd.append('access_token', t);
        fetch(B + 'social/addLinkedin', { method: 'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
            .then(r => r.json()).then(d => { if (d.error) { alert(d.error); return; } location.reload(); });
    };

    // Delete social account
    window.deleteSocialAccount = function(id) {
        if (!confirm('Remover esta conta?')) return;
        fetch(B + 'social/delete/' + id, { method: 'POST', headers: {'X-Requested-With':'XMLHttpRequest'} })
            .then(r => r.json()).then(d => { if (d.error) { alert(d.error); return; } location.reload(); });
    };
})();
</script>
