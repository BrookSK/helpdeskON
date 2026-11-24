<?php
/**
 * Modal "Importar planilha de leads" (usado em Meus Leads e no board do CRM).
 * Variáveis opcionais: $importDefaultBoardId (board pré-selecionado).
 * Botão que abre: onclick="openImportSheet()".
 */
$importIsAdmin = (($user['role'] ?? '') === 'super_admin');
?>
<div class="modal fade" id="importSheetModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-file-earmark-spreadsheet"></i> Importar planilha de leads</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="is-form">
                    <ol class="is-steps">
                        <li>
                            <strong>Baixe a planilha modelo</strong> e preencha uma linha por lead.
                            Também serve a exportação de contatos do próprio Apollo (Export &gt; CSV), sem mudar nada.
                            <div class="mt-1"><a class="btn btn-sm btn-outline-success" id="is-template" href="<?= baseUrl('crm/leadsSheetTemplate') ?>"><i class="bi bi-download"></i> Baixar planilha modelo</a></div>
                            <div class="small text-muted mt-1">Obrigatório: <b>Nome</b> e pelo menos um contato (<b>E-mail</b>, <b>Telefone</b> ou <b>LinkedIn</b>). Aceita .xlsx ou .csv, até <?= LeadImportRules::MAX_ROWS ?> linhas.</div>
                        </li>
                        <li>
                            <strong>Escolha o arquivo</strong>
                            <input type="file" id="is-file" class="form-control form-control-sm mt-1" accept=".csv,.xlsx,.txt">
                        </li>
                        <li>
                            <strong>Onde os leads entram no CRM</strong>
                            <div class="row g-2 mt-0">
                                <div class="col-sm-6">
                                    <label class="form-label small mb-1">Board</label>
                                    <select id="is-board" class="form-select form-select-sm"><option value="">Carregando...</option></select>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label small mb-1">Coluna</label>
                                    <select id="is-column" class="form-select form-select-sm"></select>
                                </div>
                                <?php if ($importIsAdmin): ?>
                                <div class="col-sm-6">
                                    <label class="form-label small mb-1">Responsável pelos leads</label>
                                    <select id="is-owner" class="form-select form-select-sm"><option value="">Eu mesmo</option></select>
                                </div>
                                <?php endif; ?>
                                <div class="col-sm-6">
                                    <label class="form-label small mb-1">Colocar em uma sequência (opcional)</label>
                                    <select id="is-sequence" class="form-select form-select-sm"><option value="">Não colocar</option></select>
                                </div>
                            </div>
                            <div class="small text-muted mt-1">Lead que já existe (mesmo e-mail, telefone ou LinkedIn) não é duplicado: só completa os dados que faltam e mantém o responsável.</div>
                        </li>
                    </ol>
                    <div id="is-error" class="alert alert-danger py-2 small" style="display:none;"></div>
                </div>
                <div id="is-result" style="display:none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal" id="is-close">Fechar</button>
                <button type="button" class="btn btn-sm btn-primary" id="is-submit" onclick="submitImportSheet(this)"><i class="bi bi-upload"></i> Importar</button>
            </div>
        </div>
    </div>
</div>
<style>
.is-steps { padding-left: 1.2rem; margin: 0; font-size: 0.85rem; }
.is-steps > li { margin-bottom: 14px; }
.is-num { font-size: 1.3rem; font-weight: 700; line-height: 1; }
</style>
<script>
(function () {
    const IS_BASE = '<?= baseUrl('') ?>';
    const IS_DEFAULT_BOARD = <?= (int) ($importDefaultBoardId ?? 0) ?>;
    let isModal = null, isLoaded = false, isBoards = [], isImported = false;
    const esc = (s) => { const d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML; };
    const get = (u) => fetch(IS_BASE + u, { headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(r => r.json());

    function fillColumns() {
        const b = isBoards.find(x => String(x.id) === document.getElementById('is-board').value);
        const sel = document.getElementById('is-column');
        sel.innerHTML = (b && b.columns && b.columns.length)
            ? b.columns.map(c => `<option value="${c.id}">${esc(c.name)}</option>`).join('')
            : '<option value="">Sem colunas</option>';
    }

    function loadOptions() {
        if (isLoaded) return;
        isLoaded = true;
        get('crm/listBoards').then(boards => {
            isBoards = Array.isArray(boards) ? boards : [];
            const sel = document.getElementById('is-board');
            sel.innerHTML = isBoards.length
                ? isBoards.map(b => `<option value="${b.id}">${esc(b.name)}</option>`).join('')
                : '<option value="">Nenhum board</option>';
            if (IS_DEFAULT_BOARD && isBoards.some(b => +b.id === IS_DEFAULT_BOARD)) sel.value = String(IS_DEFAULT_BOARD);
            fillColumns();
        }).catch(() => { isLoaded = false; });
        get('crm/sequencesList').then(d => {
            const sel = document.getElementById('is-sequence');
            (d.sequences || []).forEach(s => sel.insertAdjacentHTML('beforeend', `<option value="${s.id}">${esc(s.name)}</option>`));
        }).catch(() => {});
        const owner = document.getElementById('is-owner');
        if (owner) get('crm/leadOwners').then(d => {
            (d.users || []).forEach(u => owner.insertAdjacentHTML('beforeend', `<option value="${u.id}">${esc(u.name)}</option>`));
        }).catch(() => {});
    }

    window.openImportSheet = function () {
        if (!isModal) {
            isModal = new bootstrap.Modal(document.getElementById('importSheetModal'));
            document.getElementById('is-board').addEventListener('change', fillColumns);
            document.getElementById('importSheetModal').addEventListener('hidden.bs.modal', () => { if (isImported) location.reload(); });
        }
        document.getElementById('is-form').style.display = '';
        document.getElementById('is-result').style.display = 'none';
        document.getElementById('is-error').style.display = 'none';
        document.getElementById('is-submit').style.display = '';
        document.getElementById('is-file').value = '';
        loadOptions();
        isModal.show();
    };

    function showError(msg) {
        const e = document.getElementById('is-error');
        e.textContent = msg; e.style.display = '';
    }

    window.submitImportSheet = function (btn) {
        const file = document.getElementById('is-file').files[0];
        const column = document.getElementById('is-column').value;
        document.getElementById('is-error').style.display = 'none';
        if (!file) return showError('Escolha o arquivo da planilha.');
        if (!column) return showError('Escolha o board e a coluna do CRM.');
        const fd = new FormData();
        fd.append('file', file);
        fd.append('column_id', column);
        const owner = document.getElementById('is-owner');
        if (owner && owner.value) fd.append('assigned_to', owner.value);
        const seq = document.getElementById('is-sequence').value;
        if (seq) fd.append('sequence_id', seq);

        const orig = btn.innerHTML;
        btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Importando...';
        fetch(IS_BASE + 'crm/importLeadsSheet', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json().catch(() => ({ error: 'Resposta inválida do servidor.' })))
            .then(d => {
                btn.disabled = false; btn.innerHTML = orig;
                if (d.error) return showError(d.error);
                isImported = (d.created || 0) + (d.cards || 0) > 0;
                renderResult(d);
            })
            .catch(() => { btn.disabled = false; btn.innerHTML = orig; showError('Erro ao enviar a planilha. Tente de novo.'); });
    };

    function renderResult(d) {
        const errs = d.errors || [], warns = d.warnings || [];
        const stat = (n, l, c) => `<div class="col-6 col-md-3"><div class="border rounded p-2 h-100"><div class="is-num text-${c}">${n}</div><div class="small text-muted">${l}</div></div></div>`;
        let h = `<div class="alert ${errs.length ? 'alert-warning' : 'alert-success'} py-2 small mb-2">
                   <i class="bi bi-${errs.length ? 'exclamation-triangle' : 'check-circle'}"></i>
                   Importação concluída${errs.length ? ' com ' + errs.length + ' linha(s) não importada(s)' : ''}.
                 </div>
                 <div class="row g-2 mb-2">
                   ${stat(d.created || 0, 'leads novos', 'success')}
                   ${stat(d.existing || 0, 'já existiam (dados completados)', 'secondary')}
                   ${stat(d.cards || 0, 'cards criados no CRM', 'primary')}
                   ${stat(d.enrolled || 0, 'colocados na sequência', 'info')}
                 </div>`;
        const list = (items, title, cls) => items.length
            ? `<div class="small fw-semibold mt-2 ${cls}">${title}</div><ul class="small mb-0 ps-3" style="max-height:180px;overflow:auto;">${items.map(i => `<li>Linha ${i.line}: ${esc(i.message)}</li>`).join('')}</ul>`
            : '';
        h += list(errs, 'Não importadas', 'text-danger') + list(warns, 'Avisos', 'text-muted');
        const r = document.getElementById('is-result');
        r.innerHTML = h; r.style.display = '';
        document.getElementById('is-form').style.display = 'none';
        document.getElementById('is-submit').style.display = 'none';
    }
})();
</script>
