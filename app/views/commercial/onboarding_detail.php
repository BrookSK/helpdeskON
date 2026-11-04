<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>
<?php
$statusLabels = ['blocked'=>'Bloqueado','in_progress'=>'Em andamento','done'=>'Concluído','cancelled'=>'Cancelado'];
$statusBadge  = ['blocked'=>'warning','in_progress'=>'primary','done'=>'success','cancelled'=>'dark'];
$stepLabels = ['pending'=>'Pendente','in_progress'=>'Em andamento','done'=>'Concluída','blocked'=>'Bloqueada'];
$stepBadge  = ['pending'=>'secondary','in_progress'=>'info','done'=>'success','blocked'=>'warning'];
$st = $onboarding['status'];
?>
<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0">Onboarding #<?= (int)$onboarding['id'] ?></h5>
            <small class="text-muted"><?= escape($onboarding['title']) ?></small>
        </div>
        <div>
            <span class="badge bg-<?= $statusBadge[$st] ?? 'secondary' ?> me-2"><?= $statusLabels[$st] ?? $st ?></span>
            <a href="<?= baseUrl('onboarding') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
        </div>
    </div>

    <?php if ($st === 'blocked'): ?>
    <div class="alert alert-warning py-2 d-flex justify-content-between align-items-center">
        <span><i class="bi bi-lock"></i> Onboarding bloqueado. Inicie quando a entrada estiver paga.</span>
        <button class="btn btn-sm btn-primary" onclick="startOnb()"><i class="bi bi-play-fill"></i> Iniciar onboarding</button>
    </div>
    <?php elseif ($st === 'in_progress' && !empty($pendingRequired)): ?>
    <div class="alert alert-info py-2">
        <i class="bi bi-info-circle"></i> Para concluir, faltam etapas obrigatórias:
        <strong><?= escape(implode(', ', $pendingRequired)) ?></strong>
    </div>
    <?php elseif ($st === 'in_progress'): ?>
    <div class="alert alert-success py-2 d-flex justify-content-between align-items-center">
        <span><i class="bi bi-check2-all"></i> Todas as etapas obrigatórias concluídas.</span>
        <button class="btn btn-sm btn-success" onclick="finishOnb()"><i class="bi bi-flag-fill"></i> Concluir onboarding</button>
    </div>
    <?php elseif ($st === 'done'): ?>
    <div class="alert alert-success py-2 d-flex justify-content-between align-items-center">
        <span><i class="bi bi-check-circle"></i> Onboarding concluído.</span>
        <?php if (\Permissions::canAccess($user['role'] ?? null, 'provisioning')): ?>
        <button class="btn btn-sm btn-outline-primary" onclick="startProvisioning()"><i class="bi bi-hdd-network"></i> Provisionar infra</button>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header py-2"><strong>Checklist de etapas</strong></div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0 align-middle">
                        <thead class="table-light"><tr><th>Etapa</th><th>Responsável</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($steps as $s): ?>
                            <tr>
                                <td>
                                    <?= escape($s['title']) ?>
                                    <?php if ((int)$s['required'] === 1): ?><span class="badge bg-light text-danger border ms-1" title="Obrigatória">obrigatória</span><?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($s['responsible_name'])): ?>
                                        <small class="text-muted"><?= escape($s['responsible_name']) ?></small>
                                    <?php else: ?>
                                        <?php $defRole = \OnboardingRules::defaultRoleLabelForStep($s['step_key'] ?? null); ?>
                                        <small class="text-muted fst-italic" title="Responsável padrão sugerido para esta etapa"><?= escape($defRole) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-<?= $stepBadge[$s['status']] ?? 'secondary' ?>"><?= $stepLabels[$s['status']] ?? $s['status'] ?></span></td>
                                <td class="text-end">
                                    <?php if ($s['status'] !== 'done' && $st === 'in_progress'): ?>
                                    <button class="btn btn-sm btn-outline-success"
                                        onclick="completeStep(<?= (int)$s['id'] ?>, <?= (int)$s['required'] ?>)">Concluir</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                    <strong>Pontos focais</strong>
                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#contactForm"><i class="bi bi-plus"></i></button>
                </div>
                <div class="card-body">
                    <div class="collapse mb-3" id="contactForm">
                        <div class="row g-2">
                            <div class="col-7"><input id="c-name" class="form-control form-control-sm" placeholder="Nome"></div>
                            <div class="col-5"><input id="c-role" class="form-control form-control-sm" placeholder="Cargo"></div>
                            <div class="col-6"><input id="c-email" class="form-control form-control-sm" placeholder="E-mail"></div>
                            <div class="col-6"><input id="c-phone" class="form-control form-control-sm" placeholder="Telefone"></div>
                            <div class="col-12"><input id="c-resp" class="form-control form-control-sm" placeholder="Responsabilidade"></div>
                            <div class="col-12 form-check ms-2">
                                <input class="form-check-input" type="checkbox" id="c-primary">
                                <label class="form-check-label small" for="c-primary">Principal</label>
                            </div>
                            <div class="col-12"><button class="btn btn-sm btn-primary" onclick="addContact()">Adicionar</button></div>
                        </div>
                    </div>
                    <?php if (empty($contacts)): ?>
                    <p class="text-muted small mb-0">Nenhum ponto focal cadastrado.</p>
                    <?php else: foreach ($contacts as $c): ?>
                    <div class="d-flex justify-content-between border-bottom py-1">
                        <div>
                            <strong><?= escape($c['name']) ?></strong>
                            <?php if (!empty($c['is_primary'])): ?><span class="badge bg-primary ms-1">Principal</span><?php endif; ?>
                            <br><small class="text-muted"><?= escape($c['role'] ?? '') ?> <?= $c['email'] ? '· ' . escape($c['email']) : '' ?></small>
                        </div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
            <div class="card mb-3" id="credential-links-card">
                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                    <strong>Link para o cliente enviar credenciais</strong>
                    <?php if ($vaultReady): ?>
                    <button class="btn btn-sm btn-outline-primary py-0" id="btn-cred-link" onclick="genCredLink()"><i class="bi bi-link-45deg"></i> Gerar link</button>
                    <?php endif; ?>
                </div>
                <div class="card-body small">
                    <?php if (!$vaultReady): ?>
                    <p class="text-warning mb-0" id="cred-link-blocked"><i class="bi bi-shield-exclamation"></i> Bloqueado: defina a chave do cofre (credentials_key) em Configurações. Sem ela, os dados do cliente ficariam com a chave fixa de reserva.</p>
                    <?php else: ?>
                    <p class="text-muted mb-2">O cliente preenche serviço, usuário e senha. Tudo vai criptografado para o cofre. O link vale 7 dias.</p>
                    <div id="cred-link-new" class="alert alert-success py-2 d-none"><div class="mb-1">Link gerado (copie agora, ele não aparece de novo):</div><input id="cred-link-url" class="form-control form-control-sm" readonly onclick="this.select()"></div>
                    <?php endif; ?>
                    <?php foreach ($credentialLinks as $l):
                        $state = $l['revoked_at'] ? 'revogado' : (strtotime($l['expires_at']) < time() ? 'vencido' : 'válido'); ?>
                    <div class="border-top py-1 d-flex justify-content-between align-items-center cred-link-row">
                        <span>#<?= (int)$l['id'] ?> · <?= $state ?> até <?= date('d/m/Y H:i', strtotime($l['expires_at'])) ?> · <?= (int)$l['use_count'] ?> envio(s)<?= $l['last_used_at'] ? ' · último ' . date('d/m H:i', strtotime($l['last_used_at'])) . ' (IP ' . escape($l['last_used_ip']) . ')' : '' ?></span>
                        <?php if ($state === 'válido'): ?><button class="btn btn-sm btn-outline-danger py-0" onclick="revokeCredLink(<?= (int)$l['id'] ?>)">Revogar</button><?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="card">
                <div class="card-header py-2"><strong>Histórico</strong></div>
                <div class="card-body" style="max-height:260px;overflow:auto;">
                    <?php if (empty($events)): ?>
                    <p class="text-muted small mb-0">Sem eventos.</p>
                    <?php else: foreach ($events as $e): ?>
                    <div class="small border-bottom py-1">
                        <span class="text-muted"><?= date('d/m H:i', strtotime($e['created_at'])) ?></span> ·
                        <?= escape($e['description'] ?? $e['event_type']) ?>
                        <?php if (!empty($e['user_name'])): ?><em class="text-muted">(<?= escape($e['user_name']) ?>)</em><?php endif; ?>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const BASE = '<?= baseUrl("") ?>';
const CSRF = '<?= csrf_token() ?>';
const OID = <?= (int)$onboarding['id'] ?>;

async function post(url, data) {
    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    for (const k in (data||{})) fd.append(k, data[k]);
    return fetch(`${BASE}${url}`, { method:'POST', body: fd, headers:{'X-Requested-With':'XMLHttpRequest'} })
        .then(x=>x.json()).catch(()=>({error:'Falha de rede'}));
}
async function startOnb() {
    const r = await post(`onboarding/start/${OID}`, {});
    if (r.error) { alert(r.error); return; }
    const d = r.delivery || {};
    const canais = [];
    if (d.sent_whats) canais.push('WhatsApp');
    if (d.sent_email) canais.push('e-mail');
    if (canais.length) alert('Onboarding iniciado. Cliente avisado por: ' + canais.join(' e ') + '.');
    else if (d.no_contact) alert('Onboarding iniciado. Cadastre um ponto focal com telefone/e-mail para avisar o cliente.');
    location.reload();
}
async function completeStep(stepId, required) {
    let met = true;
    if (required) met = confirm('Esta etapa é obrigatória. Confirma que o requisito foi cumprido?');
    if (required && !met) return;
    const r = await post(`onboarding/completeStep/${stepId}`, { requirement_met: met ? 1 : 0 });
    if (r.error) { alert(r.error); return; } location.reload();
}
async function addContact() {
    const name = document.getElementById('c-name').value.trim();
    if (!name) { alert('Informe o nome.'); return; }
    const r = await post(`onboarding/addContact/${OID}`, {
        name, role: document.getElementById('c-role').value,
        email: document.getElementById('c-email').value,
        phone: document.getElementById('c-phone').value,
        responsibility: document.getElementById('c-resp').value,
        is_primary: document.getElementById('c-primary').checked ? 1 : 0,
    });
    if (r.error) { alert(r.error); return; } location.reload();
}
async function finishOnb() {
    const r = await post(`onboarding/finish/${OID}`, {});
    if (r.error) { alert(r.error + (r.pending ? '\n' + r.pending.join(', ') : '')); return; } location.reload();
}
async function genCredLink() {
    const r = await post(`onboarding/credentialLink/${OID}`, {});
    if (r.error) { alert(r.error); return; }
    document.getElementById('cred-link-new').classList.remove('d-none');
    const input = document.getElementById('cred-link-url');
    input.value = r.url; input.select();
}
async function revokeCredLink(linkId) {
    if (!confirm('Revogar este link? O cliente não poderá mais usá-lo.')) return;
    const r = await post(`onboarding/revokeCredentialLink/${linkId}`, {});
    if (r.error) { alert(r.error); return; } location.reload();
}
async function startProvisioning() {
    const r = await post('provisioning/fromOnboarding', { onboarding_id: OID });
    if (r.error) { alert(r.error); return; }
    location.href = `${BASE}provisioning/edit/${r.id}`;
}
</script>
<?php require APP_PATH . '/views/layouts/footer.php'; ?>
