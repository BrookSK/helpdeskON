<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>
<?php
$labels = ['prospect'=>'Prospecto','proposal'=>'Proposta','contract'=>'Contrato','active'=>'Ativo','terminated'=>'Encerrado','cancelled'=>'Cancelado'];
$badge  = ['prospect'=>'secondary','proposal'=>'info','contract'=>'primary','active'=>'success','terminated'=>'dark','cancelled'=>'dark'];
$engage = ['clt'=>'CLT','pj'=>'PJ','freelancer'=>'Freelancer','estagio'=>'Estágio','outro'=>'Outro'];
// Próximos status possíveis (fluxo feliz) para os botões.
$next = ['prospect'=>'proposal','proposal'=>'contract','contract'=>'active'];
$st = $provider['status'];
?>
<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0"><?= escape($provider['name']) ?></h5>
            <small class="text-muted"><?= escape($provider['role_title'] ?? '') ?> · <?= $engage[$provider['engagement_type']] ?? $provider['engagement_type'] ?></small>
        </div>
        <div>
            <span class="badge bg-<?= $badge[$st] ?? 'secondary' ?> me-2"><?= $labels[$st] ?? $st ?></span>
            <a href="<?= baseUrl('provider') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
        </div>
    </div>

    <?php if (isset($next[$st])): ?>
    <div class="alert alert-light border py-2 d-flex justify-content-between align-items-center">
        <span>Avançar para <strong><?= $labels[$next[$st]] ?></strong>?</span>
        <button class="btn btn-sm btn-primary" onclick="changeStatus('<?= $next[$st] ?>')"><i class="bi bi-arrow-right"></i> Avançar</button>
    </div>
    <?php elseif ($st === 'active'): ?>
    <div class="alert alert-warning py-2">
        <i class="bi bi-exclamation-triangle"></i> Para encerrar, revogue <strong>todos os acessos</strong> primeiro.
        <?php if (!empty($pendingAccesses)): ?>Pendentes: <strong><?= escape(implode(', ', $pendingAccesses)) ?></strong><?php endif; ?>
    </div>
    <?php elseif ($st === 'terminated'): ?>
    <div class="alert alert-dark py-2">
        <i class="bi bi-slash-circle"></i> Encerrado em <?= $provider['terminated_at'] ? date('d/m/Y', strtotime($provider['terminated_at'])) : '—' ?>.
        <?php if (!empty($provider['termination_reason'])): ?><br><small><?= escape($provider['termination_reason']) ?></small><?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header py-2"><strong>Dados</strong></div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-6"><label class="form-label small">Nome</label><input id="f-name" class="form-control form-control-sm" value="<?= escape($provider['name']) ?>"></div>
                        <div class="col-6"><label class="form-label small">E-mail</label><input id="f-email" class="form-control form-control-sm" value="<?= escape($provider['email'] ?? '') ?>"></div>
                        <div class="col-6"><label class="form-label small">Função</label><input id="f-role" class="form-control form-control-sm" value="<?= escape($provider['role_title'] ?? '') ?>"></div>
                        <div class="col-6"><label class="form-label small">Jornada</label><input id="f-workload" class="form-control form-control-sm" value="<?= escape($provider['workload'] ?? '') ?>"></div>
                        <div class="col-4"><label class="form-label small">Contratação</label>
                            <select id="f-engage" class="form-select form-select-sm">
                                <?php foreach ($engage as $k=>$v): ?><option value="<?= $k ?>" <?= $provider['engagement_type']===$k?'selected':'' ?>><?= $v ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-4"><label class="form-label small">Modelo</label>
                            <select id="f-model" class="form-select form-select-sm">
                                <option value="">—</option>
                                <?php foreach (['remoto'=>'Remoto','hibrido'=>'Híbrido','presencial'=>'Presencial'] as $k=>$v): ?><option value="<?= $k ?>" <?= ($provider['work_model']??'')===$k?'selected':'' ?>><?= $v ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <?php $valorBloqueado = !in_array($st, ['prospect','proposal'], true); ?>
                        <div class="col-4">
                            <label class="form-label small">Valor (R$)</label>
                            <input id="f-pay" class="form-control form-control-sm" value="<?= $provider['pay_amount'] !== null ? number_format((float)$provider['pay_amount'],2,',','.') : '' ?>" <?= $valorBloqueado ? 'disabled title="Após o contrato, mude o valor por uma revisão aprovada pelo gestor."' : '' ?>>
                        </div>
                        <div class="col-4"><label class="form-label small">Tipo de pagamento</label>
                            <select id="f-paytype" class="form-select form-select-sm" <?= $valorBloqueado ? 'disabled' : '' ?>>
                                <option value="">—</option>
                                <?php foreach (['mensal'=>'Mensal','hora'=>'Por hora','projeto'=>'Por projeto'] as $k=>$v): ?><option value="<?= $k ?>" <?= ($provider['pay_type']??'')===$k?'selected':'' ?>><?= $v ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-4"><label class="form-label small">Disponibilidade</label><input id="f-availability" class="form-control form-control-sm" value="<?= escape($provider['availability'] ?? '') ?>"></div>
                        <div class="col-6"><label class="form-label small">Prazo / condições</label><input id="f-payterm" class="form-control form-control-sm" value="<?= escape($provider['pay_term'] ?? '') ?>"></div>
                        <div class="col-6"><label class="form-label small">Forma de pagamento</label><input id="f-paymethod" class="form-control form-control-sm" value="<?= escape($provider['payment_method'] ?? '') ?>" placeholder="pix, transferência, boleto..."></div>
                        <div class="col-12"><label class="form-label small">Escopo (o que faz parte)</label><textarea id="f-scope" class="form-control form-control-sm" rows="2"><?= escape($provider['scope'] ?? '') ?></textarea></div>
                        <div class="col-12"><label class="form-label small">Fora do escopo (o que NÃO faz parte)</label><textarea id="f-out" class="form-control form-control-sm" rows="2"><?= escape($provider['out_of_scope'] ?? '') ?></textarea></div>
                        <div class="col-12"><button class="btn btn-sm btn-primary" onclick="saveProv()">Salvar dados</button></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <!-- Proposta por link -->
            <?php if (in_array($st, ['prospect','proposal'], true)): ?>
            <div class="card mb-3">
                <div class="card-header py-2"><strong>Proposta</strong></div>
                <div class="card-body">
                    <?php if (!empty($provider['proposal_sent_at'])): ?>
                        <p class="small mb-2">Enviada em <?= date('d/m/Y H:i', strtotime($provider['proposal_sent_at'])) ?>.
                        <?php if (!empty($provider['proposal_accepted_at'])): ?><span class="badge bg-success">aceita</span>
                        <?php elseif (!empty($provider['proposal_reject_reason'])): ?><span class="badge bg-danger">recusada</span><?php endif; ?>
                        </p>
                        <?php if (!empty($provider['proposal_reject_reason'])): ?>
                        <div class="alert alert-light border py-2 small">Motivo da recusa: <?= escape($provider['proposal_reject_reason']) ?></div>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if (!empty($proposalLink)): ?>
                    <div class="input-group input-group-sm mb-2">
                        <input class="form-control" value="<?= escape($proposalLink) ?>" readonly onclick="this.select()">
                        <button class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText('<?= escape($proposalLink) ?>')"><i class="bi bi-clipboard"></i></button>
                    </div>
                    <?php endif; ?>
                    <button class="btn btn-sm btn-primary" onclick="sendProposal()"><i class="bi bi-send"></i> <?= !empty($provider['proposal_sent_at']) ? 'Reenviar proposta' : 'Enviar proposta (WhatsApp + e-mail)' ?></button>
                    <small class="d-block text-muted mt-1">O prestador recebe um link para aceitar ou recusar com motivo.</small>
                </div>
            </div>
            <?php endif; ?>

            <!-- Assinatura (ClickSign) -->
            <?php if ($st === 'contract'): ?>
            <div class="card mb-3 border-primary">
                <div class="card-header py-2 text-primary"><strong>Contrato · Assinatura</strong></div>
                <div class="card-body">
                    <?php if (!empty($provider['signed_at'])): ?>
                        <p class="small mb-0"><span class="badge bg-success">assinado</span> em <?= date('d/m/Y H:i', strtotime($provider['signed_at'])) ?>.</p>
                    <?php else: ?>
                        <?php if (!empty($provider['clicksign_doc_key'])): ?>
                        <p class="small mb-2">Enviado para assinatura. Aguardando o prestador assinar na ClickSign.</p>
                        <?php endif; ?>
                        <button class="btn btn-sm btn-primary" onclick="sendForSignature()"><i class="bi bi-vector-pen"></i> <?= !empty($provider['clicksign_doc_key']) ? 'Reenviar para assinatura' : 'Enviar para assinatura (ClickSign)' ?></button>
                        <small class="d-block text-muted mt-1">Gera o contrato em PDF e envia ao prestador por e-mail e WhatsApp.</small>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Documentos (checklist CLT/PJ) -->
            <div class="card mb-3">
                <div class="card-header py-2"><strong>Documentos (<?= $engage[$provider['engagement_type']] ?? $provider['engagement_type'] ?>)</strong></div>
                <div class="card-body">
                    <?php if (!empty($docChecklist)): ?>
                    <ul class="list-unstyled small mb-3">
                        <?php foreach ($docChecklist as $d): ?>
                        <li>
                            <?php if ($d['present']): ?><i class="bi bi-check-circle-fill text-success"></i>
                            <?php else: ?><i class="bi bi-circle text-muted"></i><?php endif; ?>
                            <?= escape($d['label']) ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                    <div class="row g-2">
                        <div class="col-5">
                            <select id="d-type" class="form-select form-select-sm">
                                <option value="">Tipo…</option>
                                <?php foreach (($docChecklist ?? []) as $d): ?><option value="<?= escape($d['key']) ?>"><?= escape($d['label']) ?></option><?php endforeach; ?>
                                <option value="outro">Outro</option>
                            </select>
                        </div>
                        <div class="col-4"><input id="d-label" class="form-control form-control-sm" placeholder="Nome do documento"></div>
                        <div class="col-3"><button class="btn btn-sm btn-outline-primary w-100" onclick="addDocument()">Registrar</button></div>
                        <div class="col-12"><input id="d-file" class="form-control form-control-sm" placeholder="Link/caminho do arquivo (opcional)"></div>
                    </div>
                    <?php if (!empty($documents)): ?>
                    <hr class="my-2">
                    <?php foreach ($documents as $doc): ?>
                    <div class="small border-bottom py-1"><i class="bi bi-file-earmark-text"></i> <?= escape($doc['doc_label']) ?><?php if (!empty($doc['doc_type'])): ?> <span class="badge bg-light text-dark"><?= escape($doc['doc_type']) ?></span><?php endif; ?></div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Revisão de valor (aprovação do gestor) -->
            <?php if (in_array($st, ['contract','active'], true)): ?>
            <div class="card mb-3">
                <div class="card-header py-2"><strong>Revisão de valor</strong></div>
                <div class="card-body">
                    <?php if (!empty($pendingRevision)): ?>
                        <div class="alert alert-warning py-2 small mb-2">
                            Revisão pendente: <strong><?= $pendingRevision['new_pay_amount'] !== null ? ('R$ ' . number_format((float)$pendingRevision['new_pay_amount'],2,',','.')) : '—' ?></strong>
                            <?= $pendingRevision['new_pay_type'] ? '(' . escape($pendingRevision['new_pay_type']) . ')' : '' ?>
                            <?php if (!empty($pendingRevision['reason'])): ?><br>Motivo: <?= escape($pendingRevision['reason']) ?><?php endif; ?>
                        </div>
                        <?php if (!empty($canApproveRevision)): ?>
                        <button class="btn btn-sm btn-success" onclick="reviewRevision(<?= (int)$pendingRevision['id'] ?>, 'approve')"><i class="bi bi-check"></i> Aprovar</button>
                        <button class="btn btn-sm btn-outline-danger" onclick="reviewRevision(<?= (int)$pendingRevision['id'] ?>, 'reject')"><i class="bi bi-x"></i> Recusar</button>
                        <?php else: ?>
                        <small class="text-muted">Aguardando aprovação de um gestor.</small>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="row g-2">
                            <div class="col-4"><input id="r-amount" class="form-control form-control-sm" placeholder="Novo valor"></div>
                            <div class="col-4">
                                <select id="r-type" class="form-select form-select-sm">
                                    <option value="">Tipo…</option>
                                    <?php foreach (['mensal'=>'Mensal','hora'=>'Por hora','projeto'=>'Por projeto'] as $k=>$v): ?><option value="<?= $k ?>" <?= ($provider['pay_type']??'')===$k?'selected':'' ?>><?= $v ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12"><input id="r-reason" class="form-control form-control-sm" placeholder="Justificativa (obrigatória)"></div>
                            <div class="col-12"><button class="btn btn-sm btn-outline-primary" onclick="requestRevision()">Solicitar revisão</button></div>
                        </div>
                        <small class="d-block text-muted mt-1">O novo valor só vale após a aprovação de um gestor.</small>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="card mb-3">
                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                    <strong>Acessos</strong>
                    <?php if ($st === 'active' && !empty($pendingAccesses)): ?>
                    <button class="btn btn-sm btn-outline-danger" onclick="revokeAll()"><i class="bi bi-x-octagon"></i> Revogar todos</button>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <input id="a-label" class="form-control form-control-sm" list="access-opts" placeholder="Acesso (GitHub, Servidor...)">
                            <datalist id="access-opts">
                                <?php foreach ($defaultAccessLabels as $l): ?><option value="<?= escape($l) ?>"></option><?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="col-4"><input id="a-details" class="form-control form-control-sm" placeholder="Detalhe (opcional)"></div>
                        <div class="col-2"><button class="btn btn-sm btn-outline-primary w-100" onclick="addAccess()">Conceder</button></div>
                    </div>
                    <?php if (empty($accesses)): ?>
                    <p class="text-muted small mb-0">Nenhum acesso concedido.</p>
                    <?php else: foreach ($accesses as $a): ?>
                    <div class="d-flex justify-content-between align-items-center border-bottom py-1">
                        <div>
                            <strong><?= escape($a['access_label']) ?></strong>
                            <?php if (!empty($a['details'])): ?><small class="text-muted">· <?= escape($a['details']) ?></small><?php endif; ?>
                            <?php if (!empty($a['revoked_at'])): ?>
                            <span class="badge bg-dark ms-1">revogado <?= date('d/m', strtotime($a['revoked_at'])) ?></span>
                            <?php else: ?>
                            <span class="badge bg-success ms-1">ativo</span>
                            <?php endif; ?>
                        </div>
                        <?php if (empty($a['revoked_at'])): ?>
                        <button class="btn btn-sm btn-outline-danger py-0 px-1" onclick="revokeAccess(<?= (int)$a['id'] ?>)">Revogar</button>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>

            <?php if ($st === 'active'): ?>
            <div class="card mb-3 border-danger">
                <div class="card-header py-2 text-danger"><strong>Encerrar contrato</strong></div>
                <div class="card-body">
                    <textarea id="t-reason" class="form-control form-control-sm mb-2" rows="2" placeholder="Motivo do encerramento (obrigatório)"></textarea>
                    <button class="btn btn-sm btn-danger" onclick="terminate()"><i class="bi bi-slash-circle"></i> Encerrar (revoga acesso ao sistema)</button>
                    <small class="d-block text-muted mt-1">Só é possível encerrar com todos os acessos revogados.</small>
                </div>
            </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header py-2"><strong>Histórico</strong></div>
                <div class="card-body" style="max-height:220px;overflow:auto;">
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
const PRID = <?= (int)$provider['id'] ?>;
async function post(url, data) {
    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    for (const k in (data||{})) fd.append(k, data[k]);
    return fetch(`${BASE}${url}`, { method:'POST', body: fd, headers:{'X-Requested-With':'XMLHttpRequest'} })
        .then(x=>x.json()).catch(()=>({error:'Falha de rede'}));
}
function val(id){ const el=document.getElementById(id); return el ? el.value : ''; }
async function saveProv() {
    const r = await post(`provider/save/${PRID}`, {
        name: val('f-name'),
        email: val('f-email'),
        role_title: val('f-role'),
        workload: val('f-workload'),
        engagement_type: val('f-engage'),
        work_model: val('f-model'),
        pay_type: val('f-paytype'),
        pay_amount: val('f-pay'),
        pay_term: val('f-payterm'),
        payment_method: val('f-paymethod'),
        availability: val('f-availability'),
        scope: val('f-scope'),
        out_of_scope: val('f-out'),
    });
    if (r.error) { alert(r.error); return; } location.reload();
}
async function sendProposal() {
    if (!confirm('Enviar a proposta ao prestador por WhatsApp e e-mail?')) return;
    const r = await post(`provider/sendProposal/${PRID}`, {});
    if (r.error) { alert(r.error); return; }
    alert('Proposta enviada.' + (r.no_contact ? ' (sem telefone/e-mail válido — copie o link manualmente)' : ''));
    location.reload();
}
async function sendForSignature() {
    if (!confirm('Gerar o contrato e enviar para assinatura na ClickSign?')) return;
    const r = await post(`provider/sendForSignature/${PRID}`, {});
    if (r.error) { alert(r.error); return; }
    alert('Enviado para assinatura.');
    location.reload();
}
async function addDocument() {
    const label = val('d-label').trim();
    const type = val('d-type');
    if (!label) { alert('Informe o nome do documento.'); return; }
    const r = await post(`provider/addDocument/${PRID}`, { doc_label: label, doc_type: type, file_path: val('d-file') });
    if (r.error) { alert(r.error); return; } location.reload();
}
async function requestRevision() {
    const reason = val('r-reason').trim();
    if (!reason) { alert('Informe a justificativa.'); return; }
    const r = await post(`provider/requestRevision/${PRID}`, {
        new_pay_amount: val('r-amount'), new_pay_type: val('r-type'), reason
    });
    if (r.error) { alert(r.error); return; }
    alert('Revisão solicitada. Aguarde a aprovação do gestor.');
    location.reload();
}
async function reviewRevision(id, action) {
    const verb = action === 'approve' ? 'Aprovar' : 'Recusar';
    if (!confirm(verb + ' esta revisão de valor?')) return;
    const notes = prompt('Observação (opcional):') || '';
    const r = await post(`provider/${action}Revision/${id}`, { notes });
    if (r.error) { alert(r.error); return; } location.reload();
}
async function changeStatus(to) {
    const r = await post(`provider/status/${PRID}`, { status: to });
    if (r.error) { alert(r.error); return; } location.reload();
}
async function addAccess() {
    const label = document.getElementById('a-label').value.trim();
    if (!label) { alert('Informe o acesso.'); return; }
    const r = await post(`provider/addAccess/${PRID}`, { access_label: label, details: document.getElementById('a-details').value });
    if (r.error) { alert(r.error); return; } location.reload();
}
async function revokeAccess(id) {
    if (!confirm('Revogar este acesso?')) return;
    const r = await post(`provider/revokeAccess/${id}`, {});
    if (r.error) { alert(r.error); return; } location.reload();
}
async function revokeAll() {
    if (!confirm('Revogar TODOS os acessos ativos?')) return;
    const r = await post(`provider/revokeAll/${PRID}`, {});
    if (r.error) { alert(r.error); return; } location.reload();
}
async function terminate() {
    const reason = document.getElementById('t-reason').value.trim();
    if (!reason) { alert('Informe o motivo.'); return; }
    const r = await post(`provider/terminate/${PRID}`, { reason });
    if (r.error) { alert(r.error + (r.pending ? '\nPendentes: ' + r.pending.join(', ') : '')); return; }
    location.reload();
}
</script>
<?php require APP_PATH . '/views/layouts/footer.php'; ?>
