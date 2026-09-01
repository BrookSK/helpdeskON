<?php $pageTitle = 'Sequências de E-mail - CRM'; $currentPage = 'sequences'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0"><i class="bi bi-diagram-3"></i> Sequências de E-mail</h5>
            <small class="text-muted">Follow-up automático de leads do CRM</small>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <!-- BETA: ponte manual para o processo que o cron executaria (captação da
                 campanha + avanço da sequência). Selecione a campanha a executar. -->
            <div class="input-group input-group-sm" id="run-now-group" style="width:auto;">
                <select id="run-campaign-select" class="form-select form-select-sm" style="max-width:300px;" title="Selecione a campanha (executa captação + avanço da sequência dela)">
                    <?php if (!empty($campaigns)): ?>
                    <option value="">— selecione a campanha —</option>
                    <?php foreach ($campaigns as $c): ?>
                    <option value="<?= (int)$c['id'] ?>"
                            data-sequence-id="<?= (int)($c['sequence_id'] ?? 0) ?>"
                            <?= empty($c['is_active']) ? 'disabled' : '' ?>>
                        <?= escape($c['name']) ?><?= empty($c['is_active']) ? ' (inativa)' : '' ?><?= !empty($c['sequence_name']) ? ' → ' . escape($c['sequence_name']) : '' ?>
                    </option>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <option value="">Nenhuma campanha cadastrada</option>
                    <?php endif; ?>
                </select>
                <button class="btn btn-outline-secondary" id="btn-run-now" onclick="runCampaignNow(this)" title="Executa manualmente o mesmo processo do cron para a campanha selecionada (captação + avanço da sequência). Uso temporário na BETA.">
                    <i class="bi bi-play-fill"></i> Executar campanha
                </button>
            </div>
            <a href="<?= baseUrl('sequences/edit') ?>" class="btn btn-sm btn-primary" id="btn-new-seq"><i class="bi bi-plus-lg"></i> Nova sequência</a>
            <button class="btn btn-sm btn-primary d-none" id="btn-new-tpl" onclick="openTemplate()"><i class="bi bi-plus-lg"></i> Novo template</button>
        </div>
    </div>

    <ul class="nav nav-pills seq-tabs mb-3" id="seq-tabs">
        <li class="nav-item"><button class="nav-link active" data-tab="sequences" onclick="switchSeqTab('sequences')"><i class="bi bi-diagram-3"></i> Sequências</button></li>
        <li class="nav-item"><button class="nav-link" data-tab="templates" onclick="switchSeqTab('templates')"><i class="bi bi-file-earmark-text"></i> Templates</button></li>
    </ul>
    <style>
    .seq-tabs .nav-link { color:#555; font-size:0.85rem; border-radius:8px; }
    .seq-tabs .nav-link.active { background: var(--primary); color:#fff; }
    </style>

    <!-- ABA TEMPLATES -->
    <div id="tab-templates" style="display:none;">
        <div class="card">
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0" style="font-size:0.85rem;">
                    <thead class="table-light"><tr><th>Nome</th><th>Canal</th><th>Assunto</th><th class="text-end">Ações</th></tr></thead>
                    <tbody id="tpl-tbody"><tr><td colspan="4" class="text-center text-muted py-3">Carregando...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ABA SEQUÊNCIAS -->
    <div id="tab-sequences" class="row g-3">
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

<!-- Modal Template -->
<div class="modal fade" id="tplModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="tpl-modal-title">Novo template</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="tpl-id">
                <div class="row g-2">
                    <div class="col-md-8">
                        <label class="form-label small fw-medium">Nome *</label>
                        <input type="text" id="tpl-name" class="form-control form-control-sm" placeholder="Ex: Apresentação comercial">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-medium">Canal *</label>
                        <select id="tpl-channel" class="form-select form-select-sm" onchange="tplChannelChange()">
                            <option value="email">E-mail</option>
                            <option value="whatsapp">WhatsApp</option>
                            <option value="linkedin">LinkedIn</option>
                        </select>
                    </div>
                    <div class="col-12" id="tpl-subject-wrap">
                        <label class="form-label small fw-medium">Assunto</label>
                        <input type="text" id="tpl-subject" class="form-control form-control-sm" placeholder="Assunto do e-mail">
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-medium">Conteúdo *</label>
                        <textarea id="tpl-body" class="form-control form-control-sm" rows="8" placeholder="Olá {{primeiro_nome}}, ..."></textarea>
                        <small class="text-muted">Variáveis: {{nome}}, {{primeiro_nome}}, {{email}}, {{empresa}}</small>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-sm btn-primary" onclick="saveTemplate()"><i class="bi bi-check-lg"></i> Salvar</button>
            </div>
        </div>
    </div>
</div>

<script>
const BASE = '<?= baseUrl('') ?>';
let tplModal = null;

function switchSeqTab(tab) {
    document.querySelectorAll('#seq-tabs .nav-link').forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
    document.getElementById('tab-sequences').style.display = (tab === 'sequences') ? '' : 'none';
    document.getElementById('tab-templates').style.display = (tab === 'templates') ? '' : 'none';
    document.getElementById('btn-new-seq').classList.toggle('d-none', tab !== 'sequences');
    document.getElementById('btn-new-tpl').classList.toggle('d-none', tab !== 'templates');
    if (tab === 'templates') loadTemplates();
}

function delSeq(id) {
    if (!confirm('Excluir esta sequência? Os participantes e o histórico serão removidos.')) return;
    fetch(BASE + 'sequences/delete/' + id, { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'} })
        .then(r=>r.json()).then(d=>{ if(d.error){alert(d.error);return;} location.reload(); });
}

// BETA: ponte manual do cron para UMA campanha. Executa o mesmo processo real:
// (1) captação da campanha (Apollo→CRM→inscrição) e (2) avanço da sequência dela.
// Respeita tempos de espera, condições, janela, envios e o estado de cada participante.
function runCampaignNow(btn) {
    const sel = document.getElementById('run-campaign-select');
    const campId = sel.value;
    if (!campId) { alert('Selecione a campanha que deseja executar.'); return; }
    const campLabel = sel.selectedOptions[0].text.trim();

    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Executando...';

    const fd = new FormData();
    fd.append('campaign_id', campId);

    fetch(BASE + 'sequences/runNow', { method:'POST', body: fd, headers:{'X-Requested-With':'XMLHttpRequest'} })
        .then(r=>r.json())
        .then(d=>{
            btn.disabled = false; btn.innerHTML = original;
            if (d.error) { alert(d.error); return; }

            let msg = 'Execução manual da campanha concluída.\n(' + (d.scope || campLabel) + ')\n\n';

            // Passo 1 — captação da campanha (igual /cron/runProspecting)
            const p = d.prospecting || null;
            msg += '== Captação da campanha ==\n';
            if (!p) {
                msg += '(não executada)\n';
            } else if (p.error) {
                msg += 'Erro: ' + p.error + '\n';
            } else if (p.skipped) {
                msg += 'Ignorada: ' + p.skipped + (p.captured_today !== undefined ? (' (captados hoje: ' + p.captured_today + ')') : '') + '\n';
            } else {
                msg += 'Buscados: ' + (p.searched ?? 0)
                     + ' | Selecionados: ' + (p.selected ?? 0)
                     + ' | Importados: ' + (p.imported ?? 0)
                     + ' | Inscritos: ' + (p.enrolled ?? 0) + '\n';
            }

            // Passo 2 — detecção de respostas por e-mail (IMAP), igual ao cron
            msg += '\n== Detecção de respostas (e-mail) ==\n'
                 + 'Respostas detectadas: ' + (d.replies_detected ?? 0)
                 + (d.replies_error ? (' | erro: ' + d.replies_error) : '') + '\n';

            // Passo 3 — avanço da sequência (igual /cron/runSequences)
            const s = d.engine || {};
            msg += '\n== Avanço da sequência ==\n'
                 + 'Processados: ' + (s.processed ?? 0)
                 + ' | Enviados: ' + (s.sent ?? 0)
                 + ' | Ignorados: ' + (s.skipped ?? 0)
                 + ' | Finalizados: ' + (s.finished ?? 0)
                 + ' | Erros: ' + (s.errors ?? 0) + '\n\n'
                 + 'Tarefas LinkedIn aparecem em CRM → Minhas Ações quando um participante chega na etapa LinkedIn.\n'
                 + 'Obs.: blocos "Aguardar" só liberam quando o tempo configurado vence (o processo respeita os tempos reais).';

            alert(msg);
        })
        .catch(()=>{ btn.disabled = false; btn.innerHTML = original; alert('Erro ao executar a campanha.'); });
}

// ---- Templates ----
function loadTemplates() {
    fetch(BASE + 'sequences/templates', {headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(r=>r.json()).then(d=>{
            const tb = document.getElementById('tpl-tbody');
            const ts = d.templates || [];
            if (!ts.length) { tb.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">Nenhum template. Clique em "Novo template".</td></tr>'; return; }
            tb.innerHTML = ts.map(t => `<tr>
                <td class="fw-semibold">${escapeHtml(t.name)}</td>
                <td><span class="badge ${t.channel==='whatsapp'?'bg-success':(t.channel==='linkedin'?'bg-info':'bg-primary')}">${t.channel==='whatsapp'?'WhatsApp':(t.channel==='linkedin'?'LinkedIn':'E-mail')}</span></td>
                <td class="text-muted small">${escapeHtml(t.subject||'—')}</td>
                <td class="text-end text-nowrap">
                    <button class="btn btn-sm btn-outline-secondary" onclick='editTemplate(${JSON.stringify(t)})'><i class="bi bi-pencil"></i></button>
                    <button class="btn btn-sm btn-outline-danger" onclick="delTemplate(${t.id})"><i class="bi bi-trash"></i></button>
                </td></tr>`).join('');
        });
}
function getTplModal(){ if(!tplModal) tplModal = new bootstrap.Modal(document.getElementById('tplModal')); return tplModal; }
function openTemplate() {
    document.getElementById('tpl-modal-title').textContent = 'Novo template';
    document.getElementById('tpl-id').value = '';
    document.getElementById('tpl-name').value = '';
    document.getElementById('tpl-channel').value = 'email';
    document.getElementById('tpl-subject').value = '';
    document.getElementById('tpl-body').value = '';
    tplChannelChange();
    getTplModal().show();
}
function editTemplate(t) {
    document.getElementById('tpl-modal-title').textContent = 'Editar template';
    document.getElementById('tpl-id').value = t.id;
    document.getElementById('tpl-name').value = t.name || '';
    document.getElementById('tpl-channel').value = t.channel || 'email';
    document.getElementById('tpl-subject').value = t.subject || '';
    document.getElementById('tpl-body').value = t.body || '';
    tplChannelChange();
    getTplModal().show();
}
function tplChannelChange() {
    const isEmail = document.getElementById('tpl-channel').value === 'email';
    document.getElementById('tpl-subject-wrap').style.display = isEmail ? '' : 'none';
}
function saveTemplate() {
    const fd = new FormData();
    const id = document.getElementById('tpl-id').value;
    if (id) fd.append('id', id);
    fd.append('channel', document.getElementById('tpl-channel').value);
    fd.append('name', document.getElementById('tpl-name').value.trim());
    fd.append('subject', document.getElementById('tpl-subject').value);
    fd.append('body', document.getElementById('tpl-body').value);
    fetch(BASE + 'sequences/saveTemplate', {method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(r=>r.json()).then(d=>{ if(d.error){alert(d.error);return;} getTplModal().hide(); loadTemplates(); });
}
function delTemplate(id) {
    if (!confirm('Excluir este template?')) return;
    fetch(BASE + 'sequences/deleteTemplate/' + id, {method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(r=>r.json()).then(()=>loadTemplates());
}
function escapeHtml(s){return String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
</script>
<?php require APP_PATH . '/views/layouts/footer.php'; ?>
