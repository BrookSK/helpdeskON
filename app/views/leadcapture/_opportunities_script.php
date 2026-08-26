<style>
.opp-card { border-radius: 10px; margin-bottom: 10px; border-left: 4px solid #dee2e6; }
.opp-card.st-nova { border-left-color: var(--primary); background: #f0faf8; }
.opp-card.st-vista { border-left-color: #adb5bd; }
.opp-card.st-convertida { border-left-color: #2e7d32; }
.opp-card.st-ignorada { opacity: .6; }
.opp-badge-nova { background: var(--primary); color: #fff; }
.opp-meta { font-size: 0.76rem; color: #667; }
.opp-skills .badge { background: #eef1f4; color: #445; font-weight: 500; }
</style>

<script>
const BASE = '<?= baseUrl('') ?>';
let oppPage = 1, oppTotalPages = 1;

function oppFilters() {
    const f = {};
    document.querySelectorAll('.f-opp').forEach(el => { if (el.value !== '') f[el.dataset.key] = el.value; });
    return f;
}

function loadOpps(page) {
    oppPage = page || 1;
    const params = new URLSearchParams(oppFilters());
    params.set('page', oppPage);
    params.set('per_page', document.getElementById('opps-perpage').value);

    document.getElementById('opps-loading').style.display = '';
    document.getElementById('opps-list').innerHTML = '';
    document.getElementById('opps-empty').style.display = 'none';
    document.getElementById('opps-pagination').style.display = 'none';

    fetch(BASE + 'leadcapture/list?' + params.toString(), { headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json())
        .then(d => {
            document.getElementById('opps-loading').style.display = 'none';
            if (d.error) { alert(d.error); return; }
            renderOpps(d);
        })
        .catch(() => { document.getElementById('opps-loading').style.display = 'none'; alert('Erro ao carregar oportunidades.'); });
}

function renderOpps(d) {
    const list = document.getElementById('opps-list');
    if (!d.items.length) { document.getElementById('opps-empty').style.display = ''; updateCounts(d.counts); return; }
    list.innerHTML = d.items.map(oppCard).join('');
    oppTotalPages = d.total_pages;
    document.getElementById('opps-page-info').textContent =
        `${d.total} oportunidade(s) · página ${d.page} de ${d.total_pages}`;
    document.getElementById('opps-prev').disabled = d.page <= 1;
    document.getElementById('opps-next').disabled = d.page >= d.total_pages;
    document.getElementById('opps-pagination').style.display = '';
    updateCounts(d.counts);
}

function updateCounts(c) {
    if (!c) return;
    document.getElementById('collect-summary').textContent =
        `${c.total} no total · ${c.nova} novas · ${c.vista} vistas · ${c.convertida} no CRM`;
}

function money(min, max, cur) {
    if (min === null && max === null) return '';
    const sym = cur === 'BRL' ? 'R$' : cur === 'USD' ? 'US$' : cur === 'EUR' ? '€' : '';
    const fmt = v => (sym ? sym + ' ' : '') + Number(v).toLocaleString('pt-BR', {minimumFractionDigits:0});
    if (min !== null && max !== null) return `${fmt(min)} – ${fmt(max)}`;
    return fmt(min !== null ? min : max);
}

function oppCard(o) {
    const statusMap = {
        nova: ['NOVA', 'opp-badge-nova'], vista: ['JÁ VISTA', 'bg-light text-dark border'],
        convertida: ['NO CRM', 'bg-success'], ignorada: ['IGNORADA', 'bg-secondary'],
    };
    const st = statusMap[o.status] || statusMap.vista;
    const budget = money(o.budget_min, o.budget_max, o.currency);
    const skills = (o.skills || []).slice(0, 6).map(s => `<span class="badge">${escapeHtml(s)}</span>`).join(' ');
    const dateLabel = o.published_at
        ? 'publicado ' + timeAgoJs(o.published_at)
        : 'visto ' + timeAgoJs(o.first_seen_at);
    const client = o.client_name
        ? `Cliente: ${escapeHtml(o.client_name)}${o.client_rating ? ' ★ ' + o.client_rating : ' (sem feedback)'}`
        : '';

    // O link do projeto NÃO fica aqui: só é liberado após adicionar ao CRM (em Meus Leads).
    let actions = '';
    if (o.status === 'convertida') {
        actions += `<a class="btn btn-sm btn-success" href="${BASE}crm/leads"><i class="bi bi-person-check"></i> Ver no CRM</a>`;
    } else {
        actions += `<button class="btn btn-sm btn-success" onclick="convertOpp(${o.id}, this)"><i class="bi bi-plus-circle"></i> Adicionar ao CRM</button>`;
        if (o.status !== 'ignorada') {
            actions += ` <button class="btn btn-sm btn-outline-secondary" onclick="ignoreOpp(${o.id})"><i class="bi bi-slash-circle"></i> Ignorar</button>`;
        } else {
            actions += ` <button class="btn btn-sm btn-outline-secondary" onclick="setOpp(${o.id},'nova')"><i class="bi bi-arrow-counterclockwise"></i> Restaurar</button>`;
        }
    }

    const metaBits = [
        '99Freelas', dateLabel,
        o.score !== null ? 'score ' + o.score : '',
        o.proposal_count !== null ? o.proposal_count + ' propostas' : '',
        o.interested_count !== null ? o.interested_count + ' interessados' : '',
    ].filter(Boolean).join(' · ');

    return `<div class="card opp-card st-${o.status}" data-id="${o.id}">
        <div class="card-body py-2 px-3">
            <div class="d-flex justify-content-between align-items-start">
                <span class="badge ${st[1]} mb-1">${st[0]}</span>
                <small class="opp-meta">${metaBits}</small>
            </div>
            <h6 class="mb-1" style="cursor:pointer;" onclick="markSeen(${o.id})">${escapeHtml(o.title)}</h6>
            <p class="mb-1 text-muted small" style="max-height:3em;overflow:hidden;">${escapeHtml(o.description || '')}</p>
            <div class="opp-meta mb-1">
                ${budget ? '<strong>' + budget + '</strong> · ' : ''}${o.category ? escapeHtml(o.category) + ' · ' : ''}${o.experience_level ? escapeHtml(o.experience_level) : ''}
            </div>
            <div class="opp-skills mb-2">${skills}</div>
            ${client ? `<div class="opp-meta mb-2">${client}</div>` : ''}
            <div class="d-flex flex-wrap gap-1">${actions}</div>
        </div>
    </div>`;
}

function changeOppPage(delta) {
    const next = oppPage + delta;
    if (next < 1 || next > oppTotalPages) return;
    loadOpps(next);
}

// ---- Ações ----
function markSeen(id) {
    const card = document.querySelector(`.opp-card[data-id="${id}"]`);
    if (!card || !card.classList.contains('st-nova')) return;
    fetch(BASE + 'leadcapture/setStatus/' + id, { method:'POST', body: form({status:'vista'}), headers:{'X-Requested-With':'XMLHttpRequest'} })
        .then(() => { card.classList.remove('st-nova'); card.classList.add('st-vista');
            const b = card.querySelector('.badge'); if (b) { b.textContent = 'JÁ VISTA'; b.className = 'badge bg-light text-dark border mb-1'; } });
}
function setOpp(id, status) {
    fetch(BASE + 'leadcapture/setStatus/' + id, { method:'POST', body: form({status}), headers:{'X-Requested-With':'XMLHttpRequest'} })
        .then(r=>r.json()).then(d=>{ if(d.error){alert(d.error);return;} loadOpps(oppPage); });
}
function ignoreOpp(id) { setOpp(id, 'ignorada'); }
function markAllSeen() {
    if (!confirm('Marcar todas as oportunidades novas como vistas?')) return;
    fetch(BASE + 'leadcapture/markAllSeen', { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'} })
        .then(r=>r.json()).then(()=> loadOpps(oppPage));
}
function convertOpp(id, btn) {
    if (!confirm('Adicionar esta oportunidade a Meus Leads?')) return;
    btn.disabled = true;
    fetch(BASE + 'leadcapture/convert/' + id, { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'} })
        .then(r=>r.json()).then(d=>{
            btn.disabled = false;
            if (d.error) { alert(d.error); return; }
            alert('Lead criado em Meus Leads!');
            loadOpps(oppPage);
        }).catch(()=>{ btn.disabled=false; alert('Erro ao converter.'); });
}

// ---- Coleta ----
function runCollect() {
    const btn = document.getElementById('collect-btn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Buscando...';
    const alertBox = document.getElementById('collect-alert');
    alertBox.className = 'alert alert-info';
    alertBox.textContent = 'Coleta em andamento. Isso pode levar alguns segundos...';

    fetch(BASE + 'leadcapture/collect', { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json().then(d => ({ ok: r.ok, d })))
        .then(({ok, d}) => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-cloud-download"></i> Buscar novos projetos agora';
            if (!ok || d.error) {
                alertBox.className = 'alert alert-warning';
                alertBox.innerHTML = (d.error || 'Falha na coleta.') +
                    ' <a href="' + BASE + 'leadcapture/saude">Ver Saúde da Integração</a>';
                return;
            }
            let cls = d.status === 'success' ? 'alert-success' : (d.status === 'failed' ? 'alert-danger' : 'alert-warning');
            alertBox.className = 'alert ' + cls;
            if (d.projects_new === 0) {
                alertBox.innerHTML = `<i class="bi bi-info-circle"></i> Coleta concluída · Nenhum projeto novo desde a última coleta. (${d.projects_found} encontrados, ${d.projects_known} já conhecidos)`;
            } else {
                alertBox.innerHTML = `<i class="bi bi-check-circle"></i> Coleta concluída em ${Math.round((d.duration_ms||0)/1000)}s · ${d.pages_fetched} páginas · ${d.projects_found} encontrados · <strong>${d.projects_new} novos</strong> · ${d.projects_known} já conhecidos` +
                    (d.terms_failed ? ` · ${d.terms_failed} termo(s) falharam` : '');
            }
            // Recarrega a lista. Se houver filtro de status que esconda os novos, avisa.
            const statusFilter = document.querySelector('.f-opp[data-key="status"]').value;
            if (d.projects_new > 0 && statusFilter && statusFilter !== 'nova') {
                alertBox.innerHTML += `<br><small>${d.projects_new} novas oportunidades podem estar fora dos filtros atuais. <a href="#" onclick="clearOppFilters();return false;">Limpar filtros</a></small>`;
            }
            loadOpps(1);
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-cloud-download"></i> Buscar novos projetos agora';
            alertBox.className = 'alert alert-danger';
            alertBox.textContent = 'Erro de conexão durante a coleta.';
        });
}

function clearOppFilters() {
    document.querySelectorAll('.f-opp').forEach(el => el.value = '');
    loadOpps(1);
}

// ---- Abas ----
function switchLcTab(tab) {
    document.querySelectorAll('#lc-tabs .nav-link').forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
    document.getElementById('lc-opps-panel').style.display = (tab === 'opps') ? '' : 'none';
    document.getElementById('lc-diag-panel').style.display = (tab === 'diag') ? '' : 'none';
}

// ---- Diagnóstico ----
let lcDiagText = '';

function runDiag() {
    const btn = document.getElementById('lc-diag-run');
    btn.disabled = true;
    document.getElementById('lc-diag-loading').style.display = '';
    document.getElementById('lc-diag-summary').style.display = 'none';
    document.getElementById('lc-diag-results').innerHTML = '';
    document.getElementById('lc-diag-cons-wrap').style.display = 'none';
    document.getElementById('lc-diag-copy').style.display = 'none';

    fetch(BASE + 'leadcapture/diagnostics', { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json())
        .then(d => {
            btn.disabled = false;
            document.getElementById('lc-diag-loading').style.display = 'none';
            if (d.error) { alert(d.error); return; }
            renderDiag(d.summary, d.results);
        })
        .catch(() => { btn.disabled = false; document.getElementById('lc-diag-loading').style.display = 'none'; alert('Erro ao rodar o diagnóstico.'); });
}

function renderDiag(summary, results) {
    document.getElementById('lc-d-total').textContent = summary.total;
    document.getElementById('lc-d-ok').textContent = summary.ok;
    document.getElementById('lc-d-warn').textContent = summary.warn;
    document.getElementById('lc-d-failed').textContent = summary.failed;
    document.getElementById('lc-diag-summary').style.display = '';

    document.getElementById('lc-diag-results').innerHTML = results.map(diagCard).join('');

    lcDiagText = buildDiagText(summary, results);
    document.getElementById('lc-diag-cons').value = lcDiagText;
    document.getElementById('lc-diag-cons-wrap').style.display = '';
    document.getElementById('lc-diag-copy').style.display = '';
}

function diagCard(r) {
    const meta = { ok: ['OK','success','#2e7d32'], warn: ['AVISO','warning','#f5a623'], failed: ['FALHA','danger','#c62828'] };
    const m = meta[r.level] || meta.warn;
    const detailStr = r.detail ? JSON.stringify(r.detail, null, 2) : '';
    const detailBlock = detailStr ? `<pre class="bg-light p-2 rounded mb-0 mt-2" style="font-size:0.72rem;max-height:220px;overflow:auto;">${escapeHtml(detailStr)}</pre>` : '';
    return `<div class="card mb-2" style="border-left:4px solid ${m[2]};">
        <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center" style="cursor:pointer;" onclick="toggleDiagBody(this)">
            <strong style="font-size:0.85rem;">${escapeHtml(r.label)}</strong>
            <div class="d-flex align-items-center gap-2"><span class="badge bg-${m[1]}">${m[0]}</span><i class="bi bi-chevron-down"></i></div>
        </div>
        <div class="card-body py-2" style="display:none;">
            <div class="small">${escapeHtml(r.message)}</div>
            ${detailBlock}
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

function buildDiagText(summary, results) {
    const lines = [];
    lines.push('===== DIAGNÓSTICO CAPTAÇÃO DE LEADS · 99FREELAS =====');
    lines.push('Executado em: ' + (summary.ran_at || ''));
    lines.push(`Total: ${summary.total} | OK: ${summary.ok} | Avisos: ${summary.warn} | Falhas: ${summary.failed}`);
    lines.push('');
    results.forEach((r, i) => {
        lines.push(`--- [${i+1}] ${r.label} · ${r.level.toUpperCase()} ---`);
        lines.push(r.message);
        if (r.detail) lines.push('Detalhe: ' + JSON.stringify(r.detail));
        lines.push('');
    });
    const fails = results.filter(r => r.level === 'failed');
    if (fails.length) {
        lines.push('===== SOMENTE FALHAS =====');
        fails.forEach(r => { lines.push(`[${r.label}] ${r.message}`); });
    }
    return lines.join('\n');
}

function copyDiag() {
    const ta = document.getElementById('lc-diag-cons');
    if (!ta || !ta.value) return;
    ta.select(); ta.setSelectionRange(0, 999999);
    const done = () => document.querySelectorAll('#lc-diag-copy').forEach(b => { const o=b.innerHTML; b.innerHTML='<i class="bi bi-check2"></i> Copiado!'; setTimeout(()=>b.innerHTML=o,1500); });
    if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(ta.value).then(done).catch(()=>{document.execCommand('copy');done();});
    else { document.execCommand('copy'); done(); }
}

// ---- utils ----
function form(obj) { const fd = new FormData(); Object.keys(obj).forEach(k=>fd.append(k,obj[k])); return fd; }
function escapeHtml(s){return String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
function escapeAttr(s){return escapeHtml(s);}
function timeAgoJs(dt) {
    if (!dt) return '';
    const d = new Date(dt.replace(' ','T'));
    const diff = (Date.now() - d.getTime())/1000;
    if (diff < 3600) return 'há ' + Math.max(1,Math.round(diff/60)) + ' min';
    if (diff < 86400) return 'há ' + Math.round(diff/3600) + 'h';
    return 'há ' + Math.round(diff/86400) + ' dias';
}

document.addEventListener('DOMContentLoaded', () => loadOpps(1));
</script>
