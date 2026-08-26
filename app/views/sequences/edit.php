<?php $pageTitle = ($sequence ? 'Editar' : 'Nova') . ' Sequência - CRM'; $currentPage = 'sequences'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <div class="d-flex align-items-center gap-2 flex-grow-1">
            <a href="<?= baseUrl('sequences') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
            <input type="text" id="seq-name" class="form-control form-control-sm" style="max-width:320px;"
                   placeholder="Nome da sequência" value="<?= $sequence ? escape($sequence['name']) : '' ?>">
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-sm btn-outline-primary" onclick="openParticipants()" <?= $sequence ? '' : 'disabled title="Salve primeiro"' ?>><i class="bi bi-people"></i> Leads</button>
            <button class="btn btn-sm btn-primary" onclick="saveSeq()"><i class="bi bi-check-lg"></i> Salvar</button>
        </div>
    </div>

    <div class="row g-3">
        <!-- Paleta -->
        <div class="col-lg-2">
            <div class="card">
                <div class="card-header bg-white py-2"><h6 class="mb-0" style="font-size:0.82rem;">Blocos</h6></div>
                <div class="card-body p-2 d-flex flex-column gap-2" id="palette">
                    <button class="btn btn-sm btn-outline-secondary text-start" onclick="addNode('send')"><i class="bi bi-envelope"></i> Enviar e-mail</button>
                    <button class="btn btn-sm btn-outline-secondary text-start" onclick="addNode('wait')"><i class="bi bi-clock"></i> Aguardar</button>
                    <button class="btn btn-sm btn-outline-secondary text-start" onclick="addNode('condition')"><i class="bi bi-signpost-split"></i> Condição</button>
                    <button class="btn btn-sm btn-outline-secondary text-start" onclick="addNode('tag')"><i class="bi bi-tag"></i> Adicionar tag</button>
                    <button class="btn btn-sm btn-outline-secondary text-start" onclick="addNode('score')"><i class="bi bi-star"></i> Alterar score</button>
                    <button class="btn btn-sm btn-outline-secondary text-start" onclick="addNode('move')"><i class="bi bi-kanban"></i> Mover card</button>
                    <button class="btn btn-sm btn-outline-secondary text-start" onclick="addNode('end')"><i class="bi bi-stop-circle"></i> Encerrar</button>
                    <hr class="my-1">
                    <small class="text-muted" style="font-size:0.7rem;">Clique num bloco para adicioná-lo. Arraste para posicionar. Clique numa bolinha e depois em outro bloco para conectar.</small>
                </div>
            </div>

            <div class="card mt-2">
                <div class="card-header bg-white py-2"><h6 class="mb-0" style="font-size:0.82rem;">Configurações</h6></div>
                <div class="card-body p-2">
                    <label class="form-label small mb-1">Conta de envio</label>
                    <select id="seq-account" class="form-select form-select-sm mb-2">
                        <option value="">Primeira ativa</option>
                        <?php foreach ($accounts as $a): ?>
                        <option value="<?= $a['id'] ?>" <?= ($sequence && $sequence['email_account_id'] == $a['id']) ? 'selected' : '' ?>><?= escape($a['display_name'] ?: $a['email']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label class="form-label small mb-1">Limite diário</label>
                    <input type="number" id="seq-daily" class="form-control form-control-sm mb-2" min="1" value="<?= $sequence ? (int)$sequence['daily_limit'] : 100 ?>">
                    <div class="row g-1 mb-2">
                        <div class="col-6">
                            <label class="form-label small mb-1">Início</label>
                            <input type="time" id="seq-wstart" class="form-control form-control-sm" value="<?= $sequence ? substr($sequence['window_start'],0,5) : '08:00' ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label small mb-1">Fim</label>
                            <input type="time" id="seq-wend" class="form-control form-control-sm" value="<?= $sequence ? substr($sequence['window_end'],0,5) : '18:00' ?>">
                        </div>
                    </div>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" id="seq-weekends" <?= ($sequence && $sequence['send_weekends']) ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="seq-weekends">Enviar fins de semana</label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="seq-active" <?= (!$sequence || $sequence['is_active']) ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="seq-active">Sequência ativa</label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Canvas -->
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body p-0" style="position:relative;overflow:auto;height:70vh;background:#f8f9fb;">
                    <svg id="edges" style="position:absolute;top:0;left:0;width:2000px;height:2000px;pointer-events:none;"></svg>
                    <div id="canvas" style="position:relative;width:2000px;height:2000px;"></div>
                </div>
            </div>
        </div>

        <!-- Inspector -->
        <div class="col-lg-3">
            <div class="card">
                <div class="card-header bg-white py-2"><h6 class="mb-0" style="font-size:0.82rem;">Propriedades</h6></div>
                <div class="card-body" id="inspector">
                    <p class="text-muted small mb-0">Selecione um bloco para editar.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal participantes -->
<div class="modal fade" id="partModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-people"></i> Leads na sequência</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="input-group input-group-sm mb-3">
                    <select id="add-lead-select" class="form-select form-select-sm"><option value="">Carregando leads...</option></select>
                    <button class="btn btn-primary" onclick="addSelectedLead()"><i class="bi bi-plus-lg"></i> Adicionar</button>
                </div>
                <div id="part-list"><div class="text-center text-muted py-3"><span class="spinner-border spinner-border-sm"></span></div></div>
            </div>
        </div>
    </div>
</div>

<?php require APP_PATH . '/views/sequences/_editor_script.php'; ?>
<?php require APP_PATH . '/views/layouts/footer.php'; ?>
