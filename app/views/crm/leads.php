<?php $pageTitle = 'Meus Leads - CRM'; $currentPage = 'crm_leads'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<?php
$tempMeta = [
    'frio'   => ['Frio', '#3b82f6'],
    'morno'  => ['Morno', '#f59e0b'],
    'quente' => ['Quente', '#ef4444'],
];
$sourceLabels = [
    'telefonema' => 'Telefonema', 'email' => 'E-mail', 'whatsapp' => 'WhatsApp',
    'linkedin' => 'LinkedIn', 'instagram' => 'Instagram', 'facebook' => 'Facebook',
];
?>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0"><i class="bi bi-person-lines-fill"></i> <?= $isComercial ? 'Meus Leads' : 'Leads' ?></h5>
            <small class="text-muted"><?= $isComercial ? 'Leads atribuídos a você' : 'Todos os leads do sistema' ?></small>
        </div>
        <a href="<?= baseUrl('crm') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> CRM</a>
    </div>

    <!-- Filtros -->
    <div class="card mb-3">
        <div class="card-body py-2 px-3">
            <form method="GET" action="<?= baseUrl('crm/leads') ?>" class="row g-2 align-items-end">
                <div class="col-12 col-md">
                    <label class="form-label small fw-medium mb-1">Buscar</label>
                    <input type="text" name="q" class="form-control form-control-sm" placeholder="Nome ou telefone..." value="<?= escape($filters['search'] ?? '') ?>">
                </div>
                <div class="col-6 col-md-auto">
                    <label class="form-label small fw-medium mb-1">Temperatura</label>
                    <select name="temperature" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        <?php foreach ($tempMeta as $k => $t): ?>
                        <option value="<?= $k ?>" <?= ($filters['temperature'] ?? '') === $k ? 'selected' : '' ?>><?= $t[0] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-auto">
                    <label class="form-label small fw-medium mb-1">Fonte</label>
                    <select name="source" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        <?php foreach ($sourceLabels as $k => $lbl): ?>
                        <option value="<?= $k ?>" <?= ($filters['source'] ?? '') === $k ? 'selected' : '' ?>><?= $lbl ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-search"></i> Filtrar</button>
                    <a href="<?= baseUrl('crm/leads') ?>" class="btn btn-sm btn-outline-secondary">Limpar</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Lista -->
    <div class="card">
        <div class="card-body p-0">
            <?php if (empty($leads)): ?>
            <div class="text-center text-muted py-5">
                <i class="bi bi-inbox" style="font-size:2rem;"></i>
                <p class="mb-0 mt-2">Nenhum lead encontrado.</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size:0.85rem;">
                    <thead class="table-light">
                        <tr>
                            <th>Lead</th>
                            <th>Telefone</th>
                            <th>Temperatura</th>
                            <th>Fonte</th>
                            <th>Etapa CRM</th>
                            <?php if (!$isComercial): ?><th>Responsável</th><?php endif; ?>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leads as $l):
                            $name = $l['contact_name'] ?: ($l['push_name'] ?: 'Sem nome');
                            $tm = !empty($l['lead_temperature']) ? ($tempMeta[$l['lead_temperature']] ?? null) : null;
                        ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= escape($name) ?></div>
                                <?php if (!empty($l['need'])): ?>
                                <small class="text-muted text-truncate d-block" style="max-width:260px;"><?= escape($l['need']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= $l['phone'] ? escape($l['phone']) : '<span class="text-muted">—</span>' ?></td>
                            <td>
                                <?php if ($tm): ?>
                                <span class="badge rounded-pill" style="background:<?= $tm[1] ?>1a;color:<?= $tm[1] ?>;">
                                    <i class="bi bi-circle-fill" style="font-size:0.5rem;"></i> <?= $tm[0] ?>
                                </span>
                                <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                            </td>
                            <td><?= !empty($l['lead_source']) ? escape($sourceLabels[$l['lead_source']] ?? $l['lead_source']) : '<span class="text-muted">—</span>' ?></td>
                            <td>
                                <?php if (!empty($l['crm_column_name'])): ?>
                                <span class="badge bg-light text-dark border"><?= escape($l['crm_column_name']) ?></span>
                                <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                            </td>
                            <?php if (!$isComercial): ?>
                            <td><?= $l['assigned_name'] ? escape($l['assigned_name']) : '<span class="text-muted">Sem dono</span>' ?></td>
                            <?php endif; ?>
                            <td class="text-end text-nowrap">
                                <button class="btn btn-sm btn-outline-secondary" title="Gerenciar" onclick="openLead(<?= $l['id'] ?>)">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                <a class="btn btn-sm btn-success" title="Iniciar chat no WhatsApp" href="<?= baseUrl('whatsapp/chat/' . $l['id']) ?>">
                                    <i class="bi bi-whatsapp"></i>
                                </a>
                                <?php if (!empty($l['phone'])): ?>
                                <button class="btn btn-sm btn-outline-primary btn-call" title="Telefonar (webphone)" onclick="callLead(<?= $l['id'] ?>, this)">
                                    <i class="bi bi-telephone-outbound"></i> Telefonar
                                </button>
                                <button class="btn btn-sm btn-outline-secondary" title="Testar chamada via API (checkDDI)" onclick="callLeadRest(<?= $l['id'] ?>, this)">
                                    <i class="bi bi-phone-vibrate"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require APP_PATH . '/views/crm/_lead_modal.php'; ?>

<script>
const BASE = '<?= baseUrl('') ?>';

// Teste via API REST /calls/ com checkDDI (Nvoip completa o DDI). Origina click-to-call.
function callLeadRest(leadId, btn) {
    if (btn.dataset.loading === '1') return;
    btn.dataset.loading = '1'; const o = btn.innerHTML;
    btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    fetch(BASE + 'crm/callLead/' + leadId, { method: 'POST', headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json())
        .then(d => {
            btn.disabled = false; btn.dataset.loading = '0'; btn.innerHTML = o;
            if (d.error) { alert('Erro: ' + d.error); return; }
            alert('Chamada solicitada via API (checkDDI). Verifique o Destino no relatório Nvoip.\ncallId: ' + (d.call_id || '—'));
        })
        .catch(() => { btn.disabled = false; btn.dataset.loading = '0'; btn.innerHTML = o; alert('Erro na chamada.'); });
}

// Origina a ligação pelo webphone nativo (WebRTC). O backend resolve o telefone do lead
// e registra a ligação; o áudio acontece no navegador, dentro do CRM.
function callLead(leadId, btn) {
    if (btn.dataset.loading === '1') return; // bloqueia múltiplos cliques
    btn.dataset.loading = '1';
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Iniciando...';

    // Registra a ligação no banco e disca pelo webphone nativo (WebRTC) — áudio no navegador.
    fetch(BASE + 'crm/dialLead/' + leadId, { method: 'POST', headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json())
        .then(d => {
            btn.disabled = false; btn.dataset.loading = '0'; btn.innerHTML = original;
            if (d.error) { alert(d.error); return; }
            if (typeof window.nvCall !== 'function') {
                alert('Webphone não disponível nesta tela. Recarregue a página (Ctrl+F5).');
                return;
            }
            window.nvCall(d.called, d.call_record_id); // abre o modal (Chamando/mudo/desligar)
        })
        .catch(() => {
            btn.disabled = false; btn.dataset.loading = '0'; btn.innerHTML = original;
            alert('Erro ao iniciar a ligação.');
        });
}
</script>
<?php require APP_PATH . '/views/layouts/footer.php'; ?>
