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
    'apollo' => 'Apollo.io', 'manual_email' => 'E-mail manual', 'form' => 'Formulário',
    'freelas99' => '99Freelas', 'manual' => 'Manual', 'import' => 'Importação',
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

    <!-- Abas: Ativos / Arquivados -->
    <?php
        $qs = $_GET;
        unset($qs['archived']);
        $baseQs = http_build_query($qs);
        $activeUrl = baseUrl('crm/leads') . ($baseQs ? '?' . $baseQs : '');
        $archivedUrl = baseUrl('crm/leads') . '?' . http_build_query(array_merge($qs, ['archived' => 1]));
    ?>
    <ul class="nav nav-pills mb-3" style="font-size:0.85rem;">
        <li class="nav-item">
            <a class="nav-link <?= empty($showArchived) ? 'active' : '' ?>" href="<?= $activeUrl ?>">
                <i class="bi bi-person-lines-fill"></i> Ativos
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= !empty($showArchived) ? 'active' : '' ?>" href="<?= $archivedUrl ?>">
                <i class="bi bi-archive"></i> Arquivados
            </a>
        </li>
    </ul>

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
                                <?php
                                    $hasPhone = !empty($l['phone']);
                                    $hasEmail = !empty($l['lead_email']);
                                    $hasUrl = !empty($l['lead_source_url']);
                                    $emailData = htmlspecialchars(json_encode([
                                        'contact_id' => $l['id'],
                                        'name' => $l['contact_name'] ?: ($l['push_name'] ?? ''),
                                        'email' => $l['lead_email'] ?? '',
                                    ]), ENT_QUOTES);
                                ?>
                                <div class="btn-group btn-group-sm" role="group">
                                    <!-- Gerenciar -->
                                    <button class="btn btn-outline-secondary" title="Gerenciar lead" onclick="openLead(<?= $l['id'] ?>)"><i class="bi bi-pencil-square"></i></button>
                                    <!-- WhatsApp -->
                                    <a class="btn btn-outline-success <?= $hasPhone ? '' : 'disabled' ?>" title="<?= $hasPhone ? 'Chat no WhatsApp' : 'Sem telefone cadastrado' ?>" href="<?= $hasPhone ? baseUrl('whatsapp/chat/' . $l['id']) : '#' ?>"><i class="bi bi-whatsapp"></i></a>
                                    <!-- E-mail (prospecção pré-preenchida) -->
                                    <button class="btn btn-outline-primary <?= $hasEmail ? '' : 'disabled' ?>" title="<?= $hasEmail ? 'Enviar e-mail (prospecção)' : 'Sem e-mail cadastrado' ?>" <?= $hasEmail ? '' : 'disabled' ?> onclick='openEmailLead(<?= $emailData ?>)'><i class="bi bi-envelope"></i></button>
                                    <!-- Telefonar (webphone) -->
                                    <button class="btn btn-outline-primary btn-call <?= $hasPhone ? '' : 'disabled' ?>" title="<?= $hasPhone ? 'Telefonar (webphone)' : 'Sem telefone cadastrado' ?>" <?= $hasPhone ? '' : 'disabled' ?> onclick="callLead(<?= $l['id'] ?>, this)"><i class="bi bi-telephone-outbound"></i></button>
                                    <!-- Chamada via API -->
                                    <button class="btn btn-outline-secondary <?= $hasPhone ? '' : 'disabled' ?>" title="<?= $hasPhone ? 'Chamada via API' : 'Sem telefone cadastrado' ?>" <?= $hasPhone ? '' : 'disabled' ?> onclick="callLeadRest(<?= $l['id'] ?>, this)"><i class="bi bi-phone-vibrate"></i></button>
                                    <!-- Projeto de origem -->
                                    <a class="btn btn-outline-info <?= $hasUrl ? '' : 'disabled' ?>" title="<?= $hasUrl ? 'Abrir projeto de origem' : 'Sem projeto de origem' ?>" href="<?= $hasUrl ? escape($l['lead_source_url']) : '#' ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i></a>
                                    <!-- Arquivar / Restaurar -->
                                    <?php if (!empty($showArchived)): ?>
                                    <button class="btn btn-outline-success" title="Restaurar" onclick="toggleArchiveLead(<?= $l['id'] ?>, this)"><i class="bi bi-arrow-counterclockwise"></i></button>
                                    <?php else: ?>
                                    <button class="btn btn-outline-danger" title="Arquivar" onclick="toggleArchiveLead(<?= $l['id'] ?>, this)"><i class="bi bi-archive"></i></button>
                                    <?php endif; ?>
                                </div>
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

// Abre a tela de Prospecção por E-mail com os dados do lead já preenchidos.
function openEmailLead(data) {
    const params = new URLSearchParams({
        contact_id: data.contact_id || '',
        email: data.email || '',
        name: data.name || '',
    });
    window.location = BASE + 'prospection?' + params.toString();
}

// Arquiva ou restaura um lead. Remove a linha da tela sem recarregar tudo.
function toggleArchiveLead(leadId, btn) {
    const isArchivedView = <?= !empty($showArchived) ? 'true' : 'false' ?>;
    const msg = isArchivedView
        ? 'Restaurar este lead para a lista de ativos?'
        : 'Arquivar este lead? Ele sairá da lista mas continua salvo na aba Arquivados.';
    if (!confirm(msg)) return;

    btn.disabled = true;
    fetch(BASE + 'crm/toggleArchiveLead/' + leadId, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(d => {
        if (d && d.success) {
            const row = btn.closest('tr');
            if (row) row.remove();
        } else {
            btn.disabled = false;
            alert((d && d.error) ? d.error : 'Não foi possível arquivar o lead.');
        }
    })
    .catch(() => { btn.disabled = false; alert('Erro ao arquivar o lead.'); });
}

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

    // Webphone SIP direto: disca o cliente pelo ramal, com áudio no PC.
    fetch(BASE + 'crm/dialLead/' + leadId, { method: 'POST', headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json())
        .then(d => {
            btn.disabled = false; btn.dataset.loading = '0'; btn.innerHTML = original;
            if (d.error) { alert(d.error); return; }
            if (typeof window.nvCall !== 'function') { alert('Webphone não disponível. Recarregue (Ctrl+F5).'); return; }
            window.nvCall(d.called, d.call_record_id, d.lead || null);
        })
        .catch(() => {
            btn.disabled = false; btn.dataset.loading = '0'; btn.innerHTML = original;
            alert('Erro ao iniciar a ligação.');
        });
}
</script>
<?php require APP_PATH . '/views/layouts/footer.php'; ?>
