<script>
const BASE = '<?= baseUrl('') ?>';
let currentTab = 'people';      // people | orgs | captured
let currentPage = 1;
let totalPages = 1;
let lastResults = [];           // resultados exibidos (pessoas ou capturados)
const selected = new Set();     // local_ids selecionados

// ===== Abas =====
function switchTab(tab) {
    currentTab = tab;
    document.querySelectorAll('#capture-tabs .nav-link').forEach(b => b.classList.toggle('active', b.dataset.tab === tab));

    const isDiag = (tab === 'diagnostic');
    // Painel principal (filtros + resultados) e painel de diagnóstico são mutuamente exclusivos
    const mainPanel = document.getElementById('capture-main');
    if (mainPanel) mainPanel.style.display = isDiag ? 'none' : '';
    const diagPanel = document.getElementById('diagnostic-panel');
    if (diagPanel) diagPanel.style.display = isDiag ? '' : 'none';
    if (isDiag) return;

    document.getElementById('people-filters').style.display = (tab === 'people') ? '' : 'none';
    document.getElementById('orgs-filters').style.display = (tab === 'orgs') ? '' : 'none';
    document.getElementById('filters-col').style.display = (tab === 'captured') ? 'none' : '';
    document.getElementById('search-btn').closest('.card-footer').style.display = (tab === 'captured') ? 'none' : '';
    selected.clear();
    updateBulkBar();
    if (tab === 'captured') { loadCaptured(1); }
    else { resetResults(); }
}

function resetResults() {
    lastResults = [];
    document.getElementById('results-wrap').style.display = 'none';
    document.getElementById('pagination-bar').style.display = 'none';
    document.getElementById('select-all-wrap').style.display = 'none';
    document.getElementById('result-count').textContent = '';
    const empty = document.getElementById('results-empty');
    empty.style.display = '';
    empty.querySelector('p').textContent = (currentTab === 'orgs')
        ? 'Configure os filtros e clique em Pesquisar.'
        : 'Configure os filtros e clique em Pesquisar.';
}

// ===== Coleta de filtros =====
function collectFilters() {
    const fd = new FormData();
    const scope = currentTab === 'orgs' ? 'orgs' : 'people';

    // 1) Campos simples: texto (data-key) e checkbox booleano (data-bool)
    document.querySelectorAll('.f-' + scope).forEach(el => {
        const key = el.dataset.key;
        if (el.type === 'checkbox' && el.dataset.bool) {
            fd.append(key, el.checked ? 'true' : 'false');
        } else if (el.type !== 'checkbox' && el.value.trim() !== '') {
            fd.append(key, el.value.trim());
        }
    });

    // 2) Checkboxes de múltipla escolha (chips): agrupa por data-key e junta com vírgula
    const multi = {};
    document.querySelectorAll('.f-' + scope + '-multi:checked').forEach(el => {
        (multi[el.dataset.key] = multi[el.dataset.key] || []).push(el.value);
    });
    Object.keys(multi).forEach(k => fd.append(k, multi[k].join(',')));

    // 3) Campos de intervalo (selects/inputs min-max, datas)
    document.querySelectorAll('.f-' + scope + '-raw').forEach(el => {
        if (el.value !== '') fd.append(el.dataset.key, el.value);
    });
    return fd;
}

function clearFilters() {
    const scope = currentTab === 'orgs' ? 'orgs' : 'people';
    document.querySelectorAll('.f-' + scope).forEach(el => {
        if (el.type === 'checkbox') el.checked = !!el.dataset.bool; // bool volta marcado (default)
        else el.value = '';
    });
    document.querySelectorAll('.f-' + scope + '-multi').forEach(el => el.checked = false);
    document.querySelectorAll('.f-' + scope + '-raw').forEach(el => { el.value = (el.tagName === 'SELECT') ? '' : ''; });
    // Reaplica o estado visual dos chips
    document.querySelectorAll('.cap-chip').forEach(chip => {
        const cb = chip.querySelector('input[type=checkbox]');
        if (cb) chip.classList.toggle('checked', cb.checked);
    });
}

// Reflete o estado do checkbox no visual do chip
function syncChip(cb) {
    const chip = cb.closest('.cap-chip');
    if (chip) chip.classList.toggle('checked', cb.checked);
}

// ===== Busca =====
function runSearch(page) {
    currentPage = page || 1;
    const fd = collectFilters();
    fd.append('page', currentPage);
    fd.append('per_page', 25);

    const endpoint = currentTab === 'orgs' ? 'crm/apolloSearchOrganizations' : 'crm/apolloSearchPeople';
    showLoading(true);
    fetch(BASE + endpoint, { method: 'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json())
        .then(d => {
            showLoading(false);
            if (d.error) { alert(d.error); return; }
            if (currentTab === 'orgs') renderOrganizations(d.organizations || [], d.pagination);
            else renderPeople(d.people || [], d.pagination);
        })
        .catch(() => { showLoading(false); alert('Erro ao pesquisar no Apollo.'); });
}

function showLoading(on) {
    document.getElementById('results-loading').style.display = on ? '' : 'none';
    if (on) {
        document.getElementById('results-empty').style.display = 'none';
        document.getElementById('results-wrap').style.display = 'none';
    }
    const btn = document.getElementById('search-btn');
    btn.disabled = on;
}

// ===== Render: Pessoas / Capturados =====
function renderPeople(people, pagination) {
    lastResults = people;
    selected.clear();
    const head = document.getElementById('results-head');
    head.innerHTML = `<tr>
        <th style="width:32px;"></th>
        <th>Nome / Cargo</th>
        <th>Empresa</th>
        <th>Local</th>
        <th>E-mail</th>
        <th>Telefone</th>
        <th class="text-end">Ações</th>
    </tr>`;
    const body = document.getElementById('results-body');
    if (!people.length) {
        document.getElementById('results-wrap').style.display = 'none';
        const empty = document.getElementById('results-empty');
        empty.style.display = '';
        empty.querySelector('p').textContent = 'Nenhum resultado para os filtros informados.';
        document.getElementById('pagination-bar').style.display = 'none';
        document.getElementById('select-all-wrap').style.display = 'none';
        return;
    }
    body.innerHTML = people.map(p => personRow(p)).join('');
    document.getElementById('results-empty').style.display = 'none';
    document.getElementById('results-wrap').style.display = '';
    document.getElementById('select-all-wrap').style.display = '';
    document.getElementById('select-all').checked = false;
    updatePagination(pagination);
    updateBulkBar();
}

function personRow(p) {
    const loc = [p.city, p.state, p.country].filter(Boolean).join(', ') || '—';
    const email = p.email ? escapeHtml(p.email)
        : (p.imported ? '<span class="text-muted">—</span>'
        : '<span class="badge bg-light text-dark border"><i class="bi bi-lock"></i> oculto</span>');
    const phone = p.phone ? escapeHtml(p.phone) : '<span class="text-muted">—</span>';
    const checkbox = p.imported
        ? '<span class="badge bg-success" title="Já em Meus Leads"><i class="bi bi-check"></i></span>'
        : `<input type="checkbox" class="form-check-input row-check" value="${p.local_id}" onclick="toggleSelect(${p.local_id}, this)">`;
    const linkedin = p.linkedin_url ? `<a href="${escapeAttr(p.linkedin_url)}" target="_blank" class="text-decoration-none" title="LinkedIn"><i class="bi bi-linkedin"></i></a>` : '';

    let actions = '';
    if (!p.imported) {
        if (!p.email) {
            actions += `<button class="btn btn-sm btn-outline-success" title="Revelar dados" onclick="enrichOne(${p.local_id}, this)"><i class="bi bi-unlock"></i></button> `;
        }
        actions += `<button class="btn btn-sm btn-success" title="Enviar p/ Meus Leads" onclick="importOne(${p.local_id}, this)"><i class="bi bi-download"></i></button>`;
    } else {
        actions = '<span class="badge bg-success">Em Meus Leads</span>';
    }

    return `<tr data-id="${p.local_id}">
        <td>${checkbox}</td>
        <td>
            <div class="fw-semibold">${escapeHtml(p.name || '—')} ${linkedin}</div>
            <small class="text-muted">${escapeHtml(p.title || '')}</small>
        </td>
        <td>${escapeHtml(p.organization_name || '—')}<br><small class="text-muted">${escapeHtml(p.organization_industry || '')}</small></td>
        <td class="small">${escapeHtml(loc)}</td>
        <td>${email}</td>
        <td>${phone}</td>
        <td class="text-end text-nowrap">${actions}</td>
    </tr>`;
}

// ===== Render: Empresas =====
function renderOrganizations(orgs, pagination) {
    lastResults = orgs;
    selected.clear();
    const head = document.getElementById('results-head');
    head.innerHTML = `<tr>
        <th>Empresa</th>
        <th>Setor</th>
        <th>Funcionários</th>
        <th>Local</th>
        <th>Site</th>
        <th class="text-end">Ações</th>
    </tr>`;
    const body = document.getElementById('results-body');
    if (!orgs.length) {
        document.getElementById('results-wrap').style.display = 'none';
        const empty = document.getElementById('results-empty');
        empty.style.display = '';
        empty.querySelector('p').textContent = 'Nenhuma empresa para os filtros informados.';
        document.getElementById('pagination-bar').style.display = 'none';
        document.getElementById('select-all-wrap').style.display = 'none';
        return;
    }
    body.innerHTML = orgs.map(o => orgRow(o)).join('');
    document.getElementById('results-empty').style.display = 'none';
    document.getElementById('results-wrap').style.display = '';
    document.getElementById('select-all-wrap').style.display = 'none';
    updatePagination(pagination);
    document.getElementById('bulk-actions').style.display = 'none';
}

function orgRow(o) {
    const loc = [o.city, o.state, o.country].filter(Boolean).join(', ') || '—';
    const site = o.website_url || o.primary_domain || '';
    const siteLink = site ? `<a href="${escapeAttr(site.startsWith('http') ? site : 'https://' + site)}" target="_blank">${escapeHtml(o.primary_domain || site)}</a>` : '—';
    const emps = o.estimated_num_employees || o.organization_num_employees || '—';
    // Buscar pessoas desta empresa aplicando o domínio no filtro de pessoas
    const domain = o.primary_domain || '';
    const findBtn = domain
        ? `<button class="btn btn-sm btn-outline-primary" title="Buscar pessoas nesta empresa" onclick="findPeopleInOrg('${escapeAttr(domain)}')"><i class="bi bi-people"></i></button>`
        : '';
    return `<tr>
        <td class="fw-semibold">${escapeHtml(o.name || '—')}</td>
        <td class="small">${escapeHtml(o.industry || '—')}</td>
        <td>${escapeHtml(String(emps))}</td>
        <td class="small">${escapeHtml(loc)}</td>
        <td class="small">${siteLink}</td>
        <td class="text-end">${findBtn}</td>
    </tr>`;
}

function findPeopleInOrg(domain) {
    switchTab('people');
    document.querySelectorAll('#capture-tabs .nav-link').forEach(b => b.classList.toggle('active', b.dataset.tab === 'people'));
    const input = document.querySelector('.f-people[data-key="q_organization_domains_list"]');
    if (input) input.value = domain;
    runSearch(1);
}

// ===== Seleção =====
function toggleSelect(id, el) {
    if (el.checked) selected.add(id); else selected.delete(id);
    updateBulkBar();
}
function toggleSelectAll(el) {
    document.querySelectorAll('.row-check').forEach(cb => {
        cb.checked = el.checked;
        const id = parseInt(cb.value);
        if (el.checked) selected.add(id); else selected.delete(id);
    });
    updateBulkBar();
}
function updateBulkBar() {
    const bar = document.getElementById('bulk-actions');
    if (currentTab === 'orgs') { bar.style.display = 'none'; return; }
    bar.style.display = selected.size ? '' : 'none';
    document.getElementById('sel-count').textContent = selected.size;
}

// ===== Enriquecimento =====
function enrichOne(id, btn) {
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>'; }
    const fd = new FormData();
    fd.append('reveal_personal_emails', '1');
    fetch(BASE + 'crm/apolloEnrich/' + id, { method: 'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json())
        .then(d => {
            if (d.error) { alert(d.error); if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-unlock"></i>'; } return; }
            replaceRow(d.lead);
        })
        .catch(() => { if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-unlock"></i>'; } alert('Erro ao revelar dados.'); });
}

function enrichSelected() {
    const ids = Array.from(selected);
    if (!ids.length) return;
    if (!confirm(`Revelar dados de ${ids.length} lead(s)? Isso pode consumir créditos do Apollo.`)) return;
    let done = 0;
    ids.forEach(id => {
        const fd = new FormData();
        fd.append('reveal_personal_emails', '1');
        fetch(BASE + 'crm/apolloEnrich/' + id, { method: 'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
            .then(r => r.json()).then(d => { if (!d.error && d.lead) replaceRow(d.lead); })
            .finally(() => { if (++done === ids.length) { /* concluído */ } });
    });
}

// ===== Importação p/ Meus Leads =====
function importOne(id, btn) {
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>'; }
    const fd = new FormData();
    fd.append('ids', id);
    fetch(BASE + 'crm/apolloImport', { method: 'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json())
        .then(d => {
            if (d.error) { alert(d.error); if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-download"></i>'; } return; }
            markRowImported(id);
        })
        .catch(() => { if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-download"></i>'; } alert('Erro ao importar.'); });
}

function importSelected() {
    const ids = Array.from(selected);
    if (!ids.length) return;
    if (!confirm(`Enviar ${ids.length} lead(s) para Meus Leads?`)) return;
    const fd = new FormData();
    ids.forEach(id => fd.append('ids[]', id));
    fetch(BASE + 'crm/apolloImport', { method: 'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json())
        .then(d => {
            if (d.error) { alert(d.error); return; }
            ids.forEach(id => markRowImported(id));
            selected.clear();
            updateBulkBar();
            alert(`${d.imported} lead(s) enviado(s) para Meus Leads.` + (d.skipped ? ` ${d.skipped} ignorado(s).` : ''));
        })
        .catch(() => alert('Erro ao importar.'));
}

// Substitui a linha após enriquecer (atualiza e-mail/telefone)
function replaceRow(lead) {
    const row = document.querySelector(`#results-body tr[data-id="${lead.local_id}"]`);
    if (row) row.outerHTML = personRow(lead);
    const idx = lastResults.findIndex(x => x.local_id === lead.local_id);
    if (idx >= 0) lastResults[idx] = lead;
}

function markRowImported(id) {
    const lead = lastResults.find(x => x.local_id == id);
    if (lead) { lead.imported = true; replaceRow(lead); }
    selected.delete(parseInt(id));
    updateBulkBar();
}

// ===== Paginação =====
function updatePagination(pg) {
    if (!pg) { document.getElementById('pagination-bar').style.display = 'none'; return; }
    currentPage = pg.page || 1;
    totalPages = pg.total_pages || 1;
    document.getElementById('result-count').textContent =
        (pg.total_entries != null ? pg.total_entries.toLocaleString('pt-BR') + ' resultado(s)' : '');
    document.getElementById('pagination-info').textContent = `Página ${currentPage} de ${totalPages}`;
    document.getElementById('prev-page').disabled = currentPage <= 1;
    document.getElementById('next-page').disabled = currentPage >= totalPages;
    document.getElementById('pagination-bar').style.display = (totalPages > 1) ? '' : 'none';
}

function changePage(delta) {
    const next = currentPage + delta;
    if (next < 1 || next > totalPages) return;
    if (currentTab === 'captured') loadCaptured(next);
    else runSearch(next);
}

// ===== Aba Capturados =====
function loadCaptured(page) {
    currentPage = page || 1;
    showLoading(true);
    const qs = new URLSearchParams({ page: currentPage }).toString();
    fetch(BASE + 'crm/apolloLeads?' + qs, { headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json())
        .then(d => {
            showLoading(false);
            if (d.error) { alert(d.error); return; }
            renderPeople(d.leads || [], { page: d.page, total_pages: d.total_pages, total_entries: d.total });
        })
        .catch(() => { showLoading(false); alert('Erro ao carregar capturados.'); });
}

// ===== Status da integração =====
function checkApolloStatus() {
    fetch(BASE + 'crm/apolloStatus', { headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json())
        .then(d => {
            const badge = document.getElementById('apollo-status-badge');
            if (!d.configured) { badge.className = 'badge bg-danger'; badge.innerHTML = '<i class="bi bi-x-circle"></i> Não configurado'; }
            else if (d.healthy) { badge.className = 'badge bg-success'; badge.innerHTML = '<i class="bi bi-check-circle"></i> Conectado'; }
            else { badge.className = 'badge bg-warning text-dark'; badge.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Chave inválida'; }
        })
        .catch(() => {});
}

// ===== Diagnóstico =====
let diagConsolidatedText = '';

function runDiagnostics() {
    const btn = document.getElementById('diag-run-btn');
    btn.disabled = true;
    document.getElementById('diag-loading').style.display = '';
    document.getElementById('diag-summary').style.display = 'none';
    document.getElementById('diag-results').innerHTML = '';
    document.getElementById('diag-consolidated-wrap').style.display = 'none';
    document.getElementById('diag-copy-btn').style.display = 'none';

    fetch(BASE + 'crm/apolloDiagnostics', { method: 'POST', headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json())
        .then(d => {
            btn.disabled = false;
            document.getElementById('diag-loading').style.display = 'none';
            if (d.error) { alert(d.error); return; }
            renderDiagnostics(d.summary, d.results);
        })
        .catch(() => {
            btn.disabled = false;
            document.getElementById('diag-loading').style.display = 'none';
            alert('Erro ao executar o diagnóstico.');
        });
}

function renderDiagnostics(summary, results) {
    // Resumo
    document.getElementById('diag-total').textContent = summary.total;
    document.getElementById('diag-ok').textContent = summary.ok;
    document.getElementById('diag-failed').textContent = summary.failed;
    document.getElementById('diag-skipped').textContent = summary.skipped;
    document.getElementById('diag-summary').style.display = '';

    // Cards por endpoint
    const box = document.getElementById('diag-results');
    box.innerHTML = results.map(r => diagCard(r)).join('');

    // Texto consolidado (copiar/colar)
    diagConsolidatedText = buildConsolidated(summary, results);
    document.getElementById('diag-consolidated').value = diagConsolidatedText;
    document.getElementById('diag-consolidated-wrap').style.display = '';
    document.getElementById('diag-copy-btn').style.display = '';
}

function diagCard(r) {
    let badge, border;
    if (r.skipped) { badge = '<span class="badge bg-secondary">Ignorado</span>'; border = '#6c757d'; }
    else if (r.success) { badge = '<span class="badge bg-success">OK ' + (r.status || '') + '</span>'; border = '#2e7d32'; }
    else { badge = '<span class="badge bg-danger">Falha ' + (r.status || '') + '</span>'; border = '#c62828'; }

    const reqStr = r.request ? JSON.stringify(r.request, null, 2) : '(sem corpo)';
    const respStr = (r.response !== null && r.response !== undefined)
        ? (typeof r.response === 'string' ? r.response : JSON.stringify(r.response, null, 2))
        : '(vazio)';
    const errStr = r.error ? `<div class="alert alert-danger py-1 px-2 small mb-2"><i class="bi bi-exclamation-octagon"></i> ${escapeHtml(r.error)}</div>` : '';

    return `<div class="card mb-2" style="border-left:4px solid ${border};">
        <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center" style="cursor:pointer;" onclick="toggleDiagBody(this)">
            <div>
                <strong style="font-size:0.85rem;">${escapeHtml(r.label)}</strong>
                <code class="ms-2 text-muted" style="font-size:0.75rem;">${escapeHtml(r.method)} ${escapeHtml(r.endpoint)}</code>
            </div>
            <div class="d-flex align-items-center gap-2">${badge}<i class="bi bi-chevron-down"></i></div>
        </div>
        <div class="card-body py-2" style="display:none;">
            ${errStr}
            <div class="mb-2">
                <div class="small fw-medium text-muted mb-1">URL</div>
                <code style="font-size:0.75rem;word-break:break-all;">${escapeHtml(r.url)}</code>
            </div>
            <div class="mb-2">
                <div class="small fw-medium text-muted mb-1">Request (payload)</div>
                <pre class="bg-light p-2 rounded mb-0" style="font-size:0.72rem;max-height:200px;overflow:auto;">${escapeHtml(reqStr)}</pre>
            </div>
            <div>
                <div class="small fw-medium text-muted mb-1">Response</div>
                <pre class="bg-light p-2 rounded mb-0" style="font-size:0.72rem;max-height:280px;overflow:auto;">${escapeHtml(respStr)}</pre>
            </div>
        </div>
    </div>`;
}

function toggleDiagBody(header) {
    const body = header.nextElementSibling;
    const icon = header.querySelector('.bi-chevron-down, .bi-chevron-up');
    const open = body.style.display !== 'none';
    body.style.display = open ? 'none' : '';
    if (icon) icon.className = open ? 'bi bi-chevron-down' : 'bi bi-chevron-up';
}

function buildConsolidated(summary, results) {
    const lines = [];
    lines.push('===== DIAGNÓSTICO APOLLO.IO =====');
    lines.push('Executado em: ' + (summary.ran_at || ''));
    lines.push(`Total: ${summary.total} | OK: ${summary.ok} | Falhas: ${summary.failed} | Ignorados: ${summary.skipped}`);
    lines.push('');
    results.forEach((r, i) => {
        lines.push(`--- [${i + 1}] ${r.label} ---`);
        lines.push(`${r.method} ${r.url}`);
        lines.push('Status: ' + (r.status !== null ? r.status : '-') + (r.skipped ? ' (ignorado)' : (r.success ? ' (OK)' : ' (FALHA)')));
        if (r.request) lines.push('Request: ' + JSON.stringify(r.request));
        if (r.error) lines.push('Erro: ' + r.error);
        const respStr = (r.response !== null && r.response !== undefined)
            ? (typeof r.response === 'string' ? r.response : JSON.stringify(r.response))
            : '(vazio)';
        lines.push('Response: ' + respStr);
        lines.push('');
    });

    // Bloco só de erros, para facilitar a correção
    const errors = results.filter(r => !r.success && !r.skipped);
    if (errors.length) {
        lines.push('===== SOMENTE ERROS =====');
        errors.forEach(r => {
            lines.push(`[${r.label}] ${r.method} ${r.endpoint} → HTTP ${r.status || '-'}`);
            if (r.error) lines.push('  ' + r.error);
        });
    }
    return lines.join('\n');
}

function copyDiagnostics() {
    const ta = document.getElementById('diag-consolidated');
    if (!ta || !ta.value) return;
    ta.select();
    ta.setSelectionRange(0, 999999);
    const done = () => {
        const btns = document.querySelectorAll('#diag-copy-btn, #diag-consolidated-wrap .btn');
        btns.forEach(b => { const o = b.innerHTML; b.innerHTML = '<i class="bi bi-check2"></i> Copiado!'; setTimeout(() => b.innerHTML = o, 1500); });
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(ta.value).then(done).catch(() => { document.execCommand('copy'); done(); });
    } else {
        document.execCommand('copy'); done();
    }
}

// ===== Utils =====
function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
function escapeAttr(s) { return escapeHtml(s); }

// Aplica o estado visual inicial dos chips (ex.: "cargos similares" já vem marcado)
function initChips() {
    document.querySelectorAll('.cap-chip input[type=checkbox]').forEach(cb => {
        cb.closest('.cap-chip').classList.toggle('checked', cb.checked);
    });
}

document.addEventListener('DOMContentLoaded', function () {
    checkApolloStatus();
    initChips();
});
</script>
