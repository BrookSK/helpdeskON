<?php $pageTitle = 'Leads sem interesse - ON Solutions Helpdesk'; $currentPage = 'crm_no_interest'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0"><i class="bi bi-person-dash"></i> Leads sem interesse</h5>
            <small class="text-muted">Contatos que recusaram a prospecção (etiqueta "sem interesse" ou descadastrados). Use para remarketing / público no Google Ads.</small>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-sm btn-outline-success" href="<?= baseUrl('crm/noInterestExport') ?>" id="btn-export">
                <i class="bi bi-download"></i> Exportar CSV
            </a>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body py-2 px-3 d-flex flex-wrap gap-2 align-items-center">
            <div class="input-group input-group-sm" style="max-width:340px;">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" id="ni-search" class="form-control" placeholder="Buscar por nome, e-mail ou telefone...">
            </div>
            <button class="btn btn-sm btn-outline-secondary" onclick="loadNoInterest()"><i class="bi bi-arrow-clockwise"></i> Atualizar</button>
            <span class="ms-auto text-muted small" id="ni-count"></span>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Nome</th>
                        <th>E-mail</th>
                        <th>Telefone</th>
                        <th>Responsável</th>
                        <th>Marcado em</th>
                    </tr>
                </thead>
                <tbody id="ni-tbody">
                    <tr><td colspan="5" class="text-center text-muted py-4">Carregando...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const BASE = '<?= baseUrl('') ?>';

function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

function fmtDate(v) {
    if (!v) return '<span class="text-muted">—</span>';
    // v vem como "YYYY-MM-DD HH:MM:SS"
    const d = new Date(String(v).replace(' ', 'T'));
    if (isNaN(d)) return escapeHtml(v);
    return d.toLocaleString('pt-BR', { day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit' });
}

let niTimer = null;
function loadNoInterest() {
    const search = document.getElementById('ni-search').value.trim();
    const tbody = document.getElementById('ni-tbody');
    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Carregando...</td></tr>';

    const qs = search ? ('?search=' + encodeURIComponent(search)) : '';
    // Mantém a busca também no botão de exportar.
    document.getElementById('btn-export').href = BASE + 'crm/noInterestExport' + qs;

    fetch(BASE + 'crm/noInterestLeads' + qs, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json())
        .then(d => {
            if (!d.success) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-4">' + escapeHtml(d.error || 'Erro ao carregar.') + '</td></tr>';
                return;
            }
            const leads = d.leads || [];
            document.getElementById('ni-count').textContent = leads.length + ' lead(s)';
            if (leads.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Nenhum lead sem interesse encontrado.</td></tr>';
                return;
            }
            tbody.innerHTML = leads.map(l => {
                const phone = l.phone ? escapeHtml(l.phone) : '<span class="text-muted">—</span>';
                const email = l.lead_email ? escapeHtml(l.lead_email) : '<span class="text-muted">—</span>';
                const name = l.contact_name ? escapeHtml(l.contact_name) : ('Lead #' + l.id);
                const resp = l.assigned_name ? escapeHtml(l.assigned_name) : '<span class="text-muted">—</span>';
                return `<tr>
                    <td>${name}</td>
                    <td>${email}</td>
                    <td>${phone}</td>
                    <td class="small">${resp}</td>
                    <td class="small">${fmtDate(l.marked_at)}</td>
                </tr>`;
            }).join('');
        })
        .catch(e => {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-4">Falha de rede.</td></tr>';
        });
}

document.getElementById('ni-search').addEventListener('input', function() {
    clearTimeout(niTimer);
    niTimer = setTimeout(loadNoInterest, 350);
});

loadNoInterest();
</script>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
