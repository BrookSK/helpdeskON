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
            <!-- Quebra-galho MANUAL: imita o botão "Executar campanha" da tela de
                 Prospecção Automática (Apollo), porém disparado por aqui, sem cron.
                 Seleciona-se uma CAMPANHA; ao executar, faz a captação da campanha
                 e depois avança a sequência ligada a ela (+ detecção de respostas). -->
            <div class="input-group input-group-sm" id="run-now-group" style="width:auto;">
                <select id="run-campaign-select" class="form-select form-select-sm" style="max-width:300px;" title="Selecione a campanha de Prospecção a executar manualmente (mesma ação do botão 'Executar campanha', sem cron)">
                    <?php
                    $activeCampaigns = array_filter($campaigns ?? [], fn($c) => !empty($c['is_active']));
                    ?>
                    <?php if (!empty($activeCampaigns)): ?>
                    <option value="">— selecione a campanha —</option>
                    <?php foreach ($activeCampaigns as $c): ?>
                    <option value="<?= (int)$c['id'] ?>"><?= escape($c['name']) ?></option>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <option value="">Nenhuma campanha ativa</option>
                    <?php endif; ?>
                </select>
                <button class="btn btn-outline-secondary" id="btn-run-now" onclick="runCampaignNow(this)" title="Executa manualmente a campanha selecionada (captação + avanço da sequência ligada). Mesma ação do botão 'Executar campanha' da Prospecção, mas sem depender do cron.">
                    <i class="bi bi-play-fill"></i> Executar agora
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
                    <div class="d-flex gap-2 flex-wrap">
                        <button class="btn btn-sm btn-outline-success" onclick="openProgress(<?= (int)$s['id'] ?>, <?= htmlspecialchars(json_encode($s['name']), ENT_QUOTES) ?>)" title="Acompanhar o estado de cada lead nesta sequência"><i class="bi bi-activity"></i> Acompanhar estado</button>
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

<!-- Modal Acompanhar estado -->
<div class="modal fade" id="progressModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h6 class="modal-title mb-0"><i class="bi bi-activity"></i> Acompanhar estado — <span id="prog-seq-name"></span></h6>
                    <small class="text-muted">Estado atual de cada lead. Atualiza automaticamente.</small>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <div class="form-check form-switch mb-0 me-1" title="Atualizar automaticamente a cada 5s">
                        <input class="form-check-input" type="checkbox" id="prog-autorefresh" checked onchange="toggleProgressAuto()">
                        <label class="form-check-label small" for="prog-autorefresh">Auto</label>
                    </div>
                    <button class="btn btn-sm btn-outline-secondary" onclick="refreshProgress()" title="Atualizar agora"><i class="bi bi-arrow-clockwise"></i></button>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
            </div>
            <div class="modal-body">
                <div id="prog-banner" style="display:none;"></div>
                <div id="prog-summary" class="d-flex gap-2 flex-wrap mb-2 small"></div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0" style="font-size:0.83rem;">
                        <thead class="table-light">
                            <tr>
                                <th style="width:36px;"></th>
                                <th>Lead</th>
                                <th>Etapa atual</th>
                                <th>Status</th>
                                <th>Aguardar até</th>
                                <th>Última etapa</th>
                                <th>Próxima etapa</th>
                            </tr>
                        </thead>
                        <tbody id="prog-tbody">
                            <tr><td colspan="7" class="text-center text-muted py-3">Carregando...</td></tr>
                        </tbody>
                    </table>
                </div>
                <small class="text-muted d-block mt-2"><i class="bi bi-info-circle"></i> Blocos "Aguardar" só liberam quando o tempo configurado vence; "Aguardar até" mostra o horário previsto para a próxima execução.</small>
            </div>
            <div class="modal-footer">
                <span class="text-muted small me-auto" id="prog-updated"></span>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
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

// Quebra-galho MANUAL por CAMPANHA: imita o botão "Executar campanha" da
// Prospecção Automática (Apollo), mas disparado aqui e sem depender do cron.
// Executa AGORA, para a campanha selecionada: (1) captação da campanha,
// (2) detecção de respostas por e-mail e (3) avanço da sequência ligada à campanha.
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

            // Passo 1 — captação da campanha (Apollo/Meus Leads), igual ao "Executar campanha"
            const p = d.prospecting || null;
            if (p) {
                msg += '== Captação da campanha ==\n';
                if (p.error) {
                    msg += 'Erro: ' + p.error + '\n';
                } else if (p.skipped) {
                    msg += 'Ignorada: ' + (p.skipped === true ? (p.reason || 'sem detalhes') : p.skipped) + '\n';
                } else {
                    msg += 'Analisados: ' + (p.analyzed ?? p.searched ?? 0)
                         + ' | Duplicados: ' + (p.duplicated ?? 0)
                         + ' | Fora ICP: ' + (p.out_of_icp ?? 0)
                         + ' | Score baixo: ' + (p.low_score ?? 0)
                         + ' | Importados: ' + (p.imported ?? 0)
                         + ' | Captados: ' + (p.enrolled ?? 0) + '\n';
                }
            }

            // Passo 2 — detecção de respostas por e-mail (IMAP), igual ao cron
            msg += '\n== Detecção de respostas (e-mail) ==\n'
                 + 'Respostas detectadas: ' + (d.replies_detected ?? 0)
                 + (d.replies_error ? (' | erro: ' + d.replies_error) : '') + '\n';

            // Passo 3 — avanço da sequência (igual /cron/runSequences), com um
            // resumo LEGÍVEL por participante (o que aconteceu com cada lead).
            const s = d.engine || {};
            msg += '\n== Avanço da sequência ==\n';

            const parts = d.participants || [];
            if (parts.length) {
                msg += 'O que aconteceu com cada lead:\n';
                parts.forEach(p => {
                    let line = '• ' + (p.lead_name || 'Lead') + ': ' + (p.did || '—')
                             + ' → ' + (p.status_text || p.status || '');
                    if (p.wait_until) line += ' (aguardar até ' + fmtWhen(p.wait_until) + ')';
                    msg += line + '\n';
                });
                msg += '\n';
            } else {
                msg += 'Nenhum participante estava pronto para processar nesta passada.\n';
            }

            msg += 'Resumo: Processados ' + (s.processed ?? 0)
                 + ' | Enviados ' + (s.sent ?? 0)
                 + ' | Aguardando/etapas ' + (s.skipped ?? 0)
                 + ' | Finalizados ' + (s.finished ?? 0)
                 + ' | Erros ' + (s.errors ?? 0) + '\n\n'
                 + 'Tarefas LinkedIn aparecem em CRM → Minhas Ações quando um participante chega na etapa LinkedIn.\n'
                 + 'Blocos "Aguardar" só liberam quando o tempo configurado vence (o processo respeita os tempos reais).';

            // Oferece abrir a tela de acompanhamento da sequência processada.
            const seqForProgress = (d.progress && d.progress.sequence) ? d.progress.sequence : null;
            if (seqForProgress) {
                msg += '\n\nDeseja abrir "Acompanhar estado" para ver o andamento em tempo real?';
                if (confirm(msg)) { openProgress(seqForProgress.id, seqForProgress.name); }
            } else {
                alert(msg);
            }
        })
        .catch(()=>{ btn.disabled = false; btn.innerHTML = original; alert('Erro ao executar a campanha.'); });
}

// ---- Acompanhar estado (progresso legível por lead) ----
let progressModal = null;
let progressTimer = null;
let progressSeqId = null;

function getProgressModal(){ if(!progressModal) progressModal = new bootstrap.Modal(document.getElementById('progressModal')); return progressModal; }

function openProgress(seqId, seqName) {
    progressSeqId = seqId;
    document.getElementById('prog-seq-name').textContent = seqName || ('#' + seqId);
    document.getElementById('prog-tbody').innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">Carregando...</td></tr>';
    document.getElementById('prog-summary').innerHTML = '';
    document.getElementById('prog-updated').textContent = '';
    document.getElementById('prog-autorefresh').checked = true;
    getProgressModal().show();
    refreshProgress();
    startProgressAuto();
    // Para o auto-refresh ao fechar o modal.
    document.getElementById('progressModal').addEventListener('hidden.bs.modal', stopProgressAuto, { once:true });
}

function startProgressAuto() {
    stopProgressAuto();
    if (document.getElementById('prog-autorefresh').checked) {
        progressTimer = setInterval(refreshProgress, 5000);
    }
}
function stopProgressAuto() { if (progressTimer) { clearInterval(progressTimer); progressTimer = null; } }
function toggleProgressAuto() { document.getElementById('prog-autorefresh').checked ? startProgressAuto() : stopProgressAuto(); }
function toggleHist(idx) { const r = document.getElementById('hist-'+idx); if (r) r.style.display = (r.style.display === 'none' ? '' : 'none'); }
function toggleBannerDetails(id) { const r = document.getElementById(id); if (r) r.style.display = (r.style.display === 'none' ? '' : 'none'); }

function statusBadgeClass(status) {
    return status === 'active' ? 'bg-primary'
        : status === 'paused' ? 'bg-warning text-dark'
        : status === 'finished' ? 'bg-success'
        : status === 'stopped' ? 'bg-secondary'
        : status === 'failed' ? 'bg-danger' : 'bg-light text-dark';
}

// Formata "YYYY-MM-DD HH:MM:SS" para algo curto e legível (HH:MM, com data se não for hoje).
function fmtWhen(s) {
    if (!s) return '—';
    const d = new Date(s.replace(' ', 'T'));
    if (isNaN(d)) return escapeHtml(s);
    const hhmm = d.toLocaleTimeString([], { hour:'2-digit', minute:'2-digit' });
    const today = new Date();
    const sameDay = d.getFullYear()===today.getFullYear() && d.getMonth()===today.getMonth() && d.getDate()===today.getDate();
    return sameDay ? hhmm : (d.toLocaleDateString([], { day:'2-digit', month:'2-digit' }) + ' ' + hhmm);
}

function refreshProgress() {
    if (!progressSeqId) return;
    fetch(BASE + 'sequences/progress/' + progressSeqId, { headers:{'X-Requested-With':'XMLHttpRequest'} })
        .then(r=>r.json())
        .then(d=>{
            if (d.error) { document.getElementById('prog-tbody').innerHTML = '<tr><td colspan="6" class="text-center text-danger py-3">'+escapeHtml(d.error)+'</td></tr>'; return; }
            const ps = d.participants || [];
            const st = d.stats || {};
            document.getElementById('prog-summary').innerHTML =
                '<span class="badge bg-primary">Ativos: '+(st.active||0)+'</span>'
              + '<span class="badge bg-warning text-dark">Pausados: '+(st.paused||0)+'</span>'
              + '<span class="badge bg-success">Finalizados: '+(st.finished||0)+'</span>'
              + '<span class="badge bg-secondary">Interrompidos: '+(st.stopped||0)+'</span>'
              + '<span class="badge bg-danger">Falhas: '+(st.failed||0)+'</span>';

            // Banner de alertas: agrega impedimentos (danger) e pausas (warning) para
            // que o usuário veja de imediato o que travou/pausou e por quê.
            const banner = document.getElementById('prog-banner');
            const dangers = [], warns = [];
            ps.forEach(p => (p.alerts||[]).forEach(a => {
                if (a.level === 'danger') dangers.push((p.lead_name||'Lead') + ': ' + a.text);
                else if (a.level === 'warning') warns.push((p.lead_name||'Lead') + ': ' + a.text);
            }));
            // Botões discretos com "ver detalhes" (expansível). Sem caixas vermelhas
            // chamativas; apenas um aviso enxuto que revela a lista detalhada ao clicar.
            let bh = '';
            if (dangers.length) {
                bh += '<div class="mb-2">'
                    + '<button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleBannerDetails(\'prog-danger-details\')">'
                    + '<i class="bi bi-exclamation-triangle text-danger"></i> Execução impedida/falha ('+dangers.length+') — ver detalhes'
                    + '</button>'
                    + '<div id="prog-danger-details" style="display:none;" class="mt-2 ps-1">'
                    + '<ul class="small mb-0">'+dangers.map(t=>'<li>'+escapeHtml(t)+'</li>').join('')+'</ul>'
                    + '</div></div>';
            }
            if (warns.length) {
                bh += '<div class="mb-2">'
                    + '<button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleBannerDetails(\'prog-warn-details\')">'
                    + '<i class="bi bi-pause-circle text-warning"></i> Pausado/aguardando ação ('+warns.length+') — ver detalhes'
                    + '</button>'
                    + '<div id="prog-warn-details" style="display:none;" class="mt-2 ps-1">'
                    + '<ul class="small mb-0">'+warns.map(t=>'<li>'+escapeHtml(t)+'</li>').join('')+'</ul>'
                    + '</div></div>';
            }
            // Só exibe o banner quando há alertas; sem alertas, fica totalmente oculto
            // (sem ocupar espaço na tela).
            banner.innerHTML = bh;
            banner.style.display = bh ? '' : 'none';

            if (!ps.length) {
                document.getElementById('prog-tbody').innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3">Nenhum lead nesta sequência ainda.</td></tr>';
            } else {
                document.getElementById('prog-tbody').innerHTML = ps.map((p, idx) => {
                    const waitCell = p.wait_until ? ('<span class="text-nowrap"><i class="bi bi-clock text-warning"></i> '+fmtWhen(p.wait_until)+'</span>') : '—';
                    const lastCell = escapeHtml(p.last_step||'—') + (p.last_at ? ' <span class="text-muted">('+fmtWhen(p.last_at)+')</span>' : '');

                    // Avisos por lead. Erros (danger) não são repetidos em vermelho na
                    // linha — viram um resumo enxuto (a lista completa fica no banner
                    // "ver detalhes"). Avisos de pausa/informação seguem inline, discretos.
                    const alerts = p.alerts || [];
                    let alertsHtml = '';
                    if (alerts.length) {
                        const dangerCount = alerts.filter(a => a.level === 'danger').length;
                        const others = alerts.filter(a => a.level !== 'danger');
                        let parts = [];
                        if (dangerCount) {
                            parts.push('<span class="small text-muted"><i class="bi bi-exclamation-triangle text-danger"></i> '
                                + dangerCount + (dangerCount > 1 ? ' impedimentos/falhas' : ' impedimento/falha')
                                + ' — veja em "ver detalhes" acima</span>');
                        }
                        others.forEach(a => {
                            const cls = a.level === 'warning' ? 'text-warning-emphasis' : 'text-muted';
                            const icon = a.level === 'warning' ? 'bi-pause-circle' : 'bi-info-circle';
                            parts.push('<span class="small '+cls+'"><i class="bi '+icon+'"></i> '+escapeHtml(a.text)+'</span>');
                        });
                        alertsHtml = '<div class="mt-1 d-flex flex-column gap-1">' + parts.join('') + '</div>';
                    }

                    const hist = p.history || [];
                    const hasHist = hist.length > 0;
                    const toggle = hasHist
                        ? '<button class="btn btn-sm btn-link p-0" title="Ver histórico de etapas" onclick="toggleHist('+idx+')"><i class="bi bi-clock-history"></i></button>'
                        : '';

                    // Linha principal + linha de histórico (oculta por padrão).
                    let row = '<tr>'
                        + '<td class="text-center">'+toggle+'</td>'
                        + '<td class="fw-semibold">'+escapeHtml(p.lead_name||'—')+(p.lead_email?('<br><span class="text-muted small">'+escapeHtml(p.lead_email)+'</span>'):'')+alertsHtml+'</td>'
                        + '<td>'+escapeHtml(p.current_step||'—')+'</td>'
                        + '<td><span class="badge '+statusBadgeClass(p.status)+'">'+escapeHtml(p.status_text||p.status||'—')+'</span></td>'
                        + '<td>'+waitCell+'</td>'
                        + '<td>'+lastCell+'</td>'
                        + '<td class="text-muted">'+escapeHtml(p.next_step||'—')+'</td>'
                        + '</tr>';

                    if (hasHist) {
                        const items = hist.map(h => {
                            const rc = h.result === 'failed' ? 'text-danger' : (h.result === 'waiting' ? 'text-warning-emphasis' : 'text-success');
                            const det = h.detail ? ' <span class="text-muted">— '+escapeHtml(h.detail)+'</span>' : '';
                            return '<li class="mb-1"><span class="text-muted">'+fmtWhen(h.at)+'</span> · '
                                 + escapeHtml(h.step)+' → <span class="'+rc+'">'+escapeHtml(h.result_label||h.result||'')+'</span>'+det+'</li>';
                        }).join('');
                        row += '<tr id="hist-'+idx+'" style="display:none;"><td></td><td colspan="6">'
                             + '<div class="border-start ps-2 ms-1">'
                             + '<div class="small fw-semibold mb-1"><i class="bi bi-clock-history"></i> Histórico de etapas (o que já rodou)</div>'
                             + '<ol class="mb-0 ps-3 small">'+items+'</ol>'
                             + '</div></td></tr>';
                    }
                    return row;
                }).join('');
            }
            document.getElementById('prog-updated').textContent = 'Atualizado às ' + new Date().toLocaleTimeString([], { hour:'2-digit', minute:'2-digit', second:'2-digit' });
        })
        .catch(()=>{ document.getElementById('prog-tbody').innerHTML = '<tr><td colspan="6" class="text-center text-danger py-3">Falha ao carregar o estado.</td></tr>'; });
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
