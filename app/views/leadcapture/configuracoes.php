<?php $pageTitle = 'Configurações de Busca - Captação de Leads'; $currentPage = 'leadcapture_config'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0"><i class="bi bi-gear"></i> Configurações de Busca</h5>
            <small class="text-muted">Fonte: 99Freelas</small>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= baseUrl('leadcapture/opportunities') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Oportunidades</a>
            <button class="btn btn-sm btn-primary" id="collect-btn2" onclick="runCollect2()"><i class="bi bi-cloud-download"></i> Buscar novos projetos agora</button>
        </div>
    </div>

    <div id="cfg-alert" class="alert d-none"></div>

    <div class="row g-3">
        <!-- Configuração da fonte -->
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header bg-white py-2"><h6 class="mb-0" style="font-size:0.9rem;">Fonte 99Freelas</h6></div>
                <div class="card-body">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="cfg-enabled" <?= !empty($settings['enabled']) ? 'checked' : '' ?>>
                        <label class="form-check-label small fw-medium" for="cfg-enabled">Habilitar coleta do 99Freelas</label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Máximo de páginas por termo</label>
                        <input type="number" id="cfg-max-pages" class="form-control form-control-sm" min="1" max="10" value="<?= (int)$settings['max_pages'] ?>">
                        <small class="text-muted">Entre 1 e 10. Padrão: 2.</small>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="cfg-general" <?= !empty($settings['collect_general']) ? 'checked' : '' ?>>
                        <label class="form-check-label small fw-medium" for="cfg-general">Também coletar a listagem geral (descoberta ampla)</label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Intervalo do agendamento (minutos)</label>
                        <input type="number" id="cfg-schedule" class="form-control form-control-sm" min="15" value="<?= (int)$settings['schedule_minutes'] ?>">
                        <small class="text-muted">Usado pela coleta automática (cron). Mínimo 15.</small>
                    </div>

                    <hr>
                    <h6 class="small fw-semibold text-muted mb-2"><i class="bi bi-funnel"></i> Filtros de exibição</h6>
                    <p class="small text-muted">Controlam quais oportunidades aparecem na tela. Use 0 para não limitar.</p>

                    <div class="mb-3">
                        <label class="form-label small fw-medium">Máximo de propostas enviadas</label>
                        <input type="number" id="cfg-max-proposals" class="form-control form-control-sm" min="0" value="<?= (int)($settings['max_proposals'] ?? 0) ?>">
                        <small class="text-muted">Só mostra projetos com até este nº de propostas (menos concorrência). 0 = todos.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Faixa mínima de valor (R$)</label>
                        <input type="number" id="cfg-min-budget" class="form-control form-control-sm" min="0" step="100" value="<?= (int)($settings['min_budget'] ?? 0) ?>">
                        <small class="text-muted">Só mostra projetos com orçamento a partir deste valor. 0 = todos. (Projetos sem orçamento informado não são filtrados por este campo.)</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Idade máxima da publicação (dias)</label>
                        <input type="number" id="cfg-max-age" class="form-control form-control-sm" min="0" value="<?= (int)($settings['max_age_days'] ?? 0) ?>">
                        <small class="text-muted">Só mostra projetos publicados/descobertos nos últimos X dias. 0 = qualquer data.</small>
                    </div>

                    <button class="btn btn-sm btn-primary" onclick="saveCfg()"><i class="bi bi-check-lg"></i> Salvar configurações</button>
                </div>
            </div>
        </div>

        <!-- Termos -->
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0" style="font-size:0.9rem;">Termos monitorados</h6>
                    <div class="input-group input-group-sm" style="width:auto;">
                        <input type="text" id="new-term" class="form-control form-control-sm" placeholder="Novo termo...">
                        <button class="btn btn-sm btn-primary" onclick="addTerm()"><i class="bi bi-plus-lg"></i></button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm table-hover mb-0" style="font-size:0.85rem;">
                        <tbody id="terms-tbody">
                            <?php foreach ($terms as $t): ?>
                            <tr data-id="<?= $t['id'] ?>">
                                <td><?= escape($t['term']) ?></td>
                                <td class="text-center" style="width:80px;">
                                    <div class="form-check form-switch d-inline-block">
                                        <input class="form-check-input" type="checkbox" <?= $t['active'] ? 'checked' : '' ?> onchange="toggleTerm(<?= $t['id'] ?>, this.checked)">
                                    </div>
                                </td>
                                <td class="text-end" style="width:50px;">
                                    <button class="btn btn-sm btn-link text-danger p-0" onclick="delTerm(<?= $t['id'] ?>)"><i class="bi bi-trash"></i></button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if (empty($terms)): ?>
                    <p class="text-muted text-center py-3 mb-0" id="no-terms">Nenhum termo cadastrado.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const BASE = '<?= baseUrl('') ?>';

function saveCfg() {
    const fd = new FormData();
    fd.append('enabled', document.getElementById('cfg-enabled').checked ? 1 : 0);
    fd.append('max_pages', document.getElementById('cfg-max-pages').value);
    fd.append('collect_general', document.getElementById('cfg-general').checked ? 1 : 0);
    fd.append('schedule_minutes', document.getElementById('cfg-schedule').value);
    fd.append('max_proposals', document.getElementById('cfg-max-proposals').value);
    fd.append('min_budget', document.getElementById('cfg-min-budget').value);
    fd.append('max_age_days', document.getElementById('cfg-max-age').value);
    fetch(BASE + 'leadcapture/saveSettings', { method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'} })
        .then(r=>r.json()).then(d=>{ showAlert(d.error ? 'danger' : 'success', d.error || 'Configurações salvas.'); });
}

function addTerm() {
    const input = document.getElementById('new-term');
    const term = input.value.trim();
    if (!term) return;
    const fd = new FormData(); fd.append('term', term);
    fetch(BASE + 'leadcapture/saveTerm', { method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'} })
        .then(r=>r.json()).then(d=>{
            if (d.error) { alert(d.error); return; }
            const tb = document.getElementById('terms-tbody');
            const noTerms = document.getElementById('no-terms'); if (noTerms) noTerms.remove();
            const tr = document.createElement('tr'); tr.dataset.id = d.id;
            tr.innerHTML = `<td>${escapeHtml(term)}</td>
                <td class="text-center"><div class="form-check form-switch d-inline-block"><input class="form-check-input" type="checkbox" checked onchange="toggleTerm(${d.id}, this.checked)"></div></td>
                <td class="text-end"><button class="btn btn-sm btn-link text-danger p-0" onclick="delTerm(${d.id})"><i class="bi bi-trash"></i></button></td>`;
            tb.appendChild(tr); input.value = '';
        });
}

function toggleTerm(id, active) {
    const fd = new FormData(); fd.append('id', id); fd.append('active', active ? 1 : 0);
    fetch(BASE + 'leadcapture/saveTerm', { method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'} });
}

function delTerm(id) {
    if (!confirm('Remover este termo?')) return;
    fetch(BASE + 'leadcapture/deleteTerm/' + id, { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'} })
        .then(r=>r.json()).then(d=>{ if(!d.error){ const tr=document.querySelector(`tr[data-id="${id}"]`); if(tr) tr.remove(); } });
}

function runCollect2() {
    const btn = document.getElementById('collect-btn2');
    btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Buscando...';
    fetch(BASE + 'leadcapture/collect', { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'} })
        .then(r=>r.json()).then(d=>{
            btn.disabled = false; btn.innerHTML = '<i class="bi bi-cloud-download"></i> Buscar novos projetos agora';
            if (d.error) { showAlert('warning', d.error); return; }
            showAlert(d.status==='success'?'success':'warning',
                `Coleta concluída · ${d.projects_found} encontrados · ${d.projects_new} novos · ${d.projects_known} já conhecidos`);
        }).catch(()=>{ btn.disabled=false; btn.innerHTML='<i class="bi bi-cloud-download"></i> Buscar novos projetos agora'; showAlert('danger','Erro na coleta.'); });
}

function showAlert(type, msg) {
    const a = document.getElementById('cfg-alert');
    a.className = 'alert alert-' + type;
    a.textContent = msg;
    setTimeout(()=>{ a.className='alert d-none'; }, 5000);
}
function escapeHtml(s){return String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
</script>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
