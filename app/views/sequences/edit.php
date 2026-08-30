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
            <button class="btn btn-sm btn-outline-success" onclick="testSequence(this)" <?= $sequence ? '' : 'disabled title="Salve primeiro"' ?>><i class="bi bi-play-circle"></i> Testar agora</button>
            <button class="btn btn-sm btn-primary" onclick="saveSeq()"><i class="bi bi-check-lg"></i> Salvar</button>
        </div>
    </div>

    <!-- Barra superior: Blocos + Configurações -->
    <div class="card mb-2">
        <div class="card-body py-2 px-3">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="fw-semibold small me-1" style="color:#667;">Blocos:</span>
                <button class="btn btn-sm btn-outline-primary" onclick="addNode('send')"><i class="bi bi-envelope"></i> E-mail</button>
                <button class="btn btn-sm btn-outline-success" onclick="addNode('whatsapp')"><i class="bi bi-whatsapp"></i> WhatsApp</button>
                <button class="btn btn-sm btn-outline-warning" onclick="addNode('wait')"><i class="bi bi-clock"></i> Aguardar</button>
                <button class="btn btn-sm btn-outline-secondary" onclick="addNode('condition')"><i class="bi bi-signpost-split"></i> Condição</button>
                <button class="btn btn-sm btn-outline-primary" onclick="addNode('ai')"><i class="bi bi-robot"></i> IA (ChatGPT)</button>
                <button class="btn btn-sm btn-outline-secondary" onclick="addNode('tag')"><i class="bi bi-tag"></i> Tag</button>
                <button class="btn btn-sm btn-outline-secondary" onclick="addNode('score')"><i class="bi bi-star"></i> Score</button>
                <button class="btn btn-sm btn-outline-info" onclick="addNode('move')"><i class="bi bi-kanban"></i> Mover card</button>
                <button class="btn btn-sm btn-outline-danger" onclick="addNode('unsubscribe')"><i class="bi bi-person-dash"></i> Remover da lista</button>
                <button class="btn btn-sm btn-outline-dark" onclick="addNode('reveal_phone')"><i class="bi bi-telephone-plus"></i> Revelar telefone (Apollo)</button>
                <button class="btn btn-sm btn-outline-success" onclick="addNode('schedule')"><i class="bi bi-calendar2-check"></i> Agendamento</button>
                <button class="btn btn-sm btn-outline-danger" onclick="addNode('end')"><i class="bi bi-stop-circle"></i> Encerrar</button>
                <div class="ms-auto">
                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#seq-config">
                        <i class="bi bi-gear"></i> Configurações
                    </button>
                </div>
            </div>
            <!-- Configurações (collapse) -->
            <div class="collapse mt-2" id="seq-config">
                <div class="row g-2 align-items-end border-top pt-2">
                    <div class="col-6 col-md-2">
                        <label class="form-label small mb-1">Canal</label>
                        <select id="seq-channel" class="form-select form-select-sm" title="Define quais leads são elegíveis: e-mail exige e-mail; WhatsApp exige telefone; mista aceita e-mail e/ou telefone">
                            <?php $chan = $sequence['channel_type'] ?? 'email'; ?>
                            <option value="email" <?= $chan === 'email' ? 'selected' : '' ?>>E-mail</option>
                            <option value="whatsapp" <?= $chan === 'whatsapp' ? 'selected' : '' ?>>WhatsApp</option>
                            <option value="mixed" <?= $chan === 'mixed' ? 'selected' : '' ?>>Mista (e-mail + WhatsApp)</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label small mb-1">Conta de envio</label>
                        <select id="seq-account" class="form-select form-select-sm">
                            <option value="">Primeira ativa</option>
                            <?php foreach ($accounts as $a): ?>
                            <option value="<?= $a['id'] ?>" <?= ($sequence && $sequence['email_account_id'] == $a['id']) ? 'selected' : '' ?>><?= escape($a['display_name'] ?: $a['email']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small mb-1">Limite diário</label>
                        <input type="number" id="seq-daily" class="form-control form-control-sm" min="1" value="<?= $sequence ? (int)$sequence['daily_limit'] : 100 ?>">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small mb-1">Início</label>
                        <input type="time" id="seq-wstart" class="form-control form-control-sm" value="<?= $sequence ? substr($sequence['window_start'],0,5) : '08:00' ?>">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small mb-1">Fim</label>
                        <input type="time" id="seq-wend" class="form-control form-control-sm" value="<?= $sequence ? substr($sequence['window_end'],0,5) : '18:00' ?>">
                    </div>
                    <div class="col-6 col-md-3 d-flex gap-3 align-items-center">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="seq-weekends" <?= ($sequence && $sequence['send_weekends']) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="seq-weekends">Fim de semana</label>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="seq-active" <?= (!$sequence || $sequence['is_active']) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="seq-active">Ativa</label>
                        </div>
                    </div>
                </div>
            </div>
            <small class="text-muted d-block mt-1" style="font-size:0.7rem;">Clique num bloco acima para adicioná-lo. Arraste no quadro para posicionar. Selecione um bloco para editar as propriedades.</small>
        </div>
    </div>

    <!-- Canvas em largura total -->
    <div class="card position-relative">
        <!-- Controles de zoom -->
        <div class="seq-zoom-ctrl">
            <button type="button" class="btn btn-sm btn-light border" onclick="zoomStep(0.1)" title="Aproximar"><i class="bi bi-zoom-in"></i></button>
            <button type="button" class="btn btn-sm btn-light border" onclick="zoomReset()" title="Redefinir zoom"><span id="zoom-label">100%</span></button>
            <button type="button" class="btn btn-sm btn-light border" onclick="zoomStep(-0.1)" title="Afastar"><i class="bi bi-zoom-out"></i></button>
        </div>
        <div class="card-body p-0" id="canvas-wrap">
            <div id="canvas-zoom" style="transform-origin:0 0;width:3000px;height:3000px;position:relative;">
                <svg id="edges" style="position:absolute;top:0;left:0;width:3000px;height:3000px;pointer-events:none;"></svg>
                <div id="canvas" style="position:relative;width:3000px;height:3000px;"></div>
            </div>
        </div>
    </div>
    <style>
    .seq-zoom-ctrl { position:absolute; top:8px; right:8px; z-index:50; display:flex; flex-direction:column; gap:4px; }
    .seq-zoom-ctrl .btn { width:38px; padding:4px 0; }
    #zoom-label { font-size:0.7rem; }
    </style>

    <!-- Propriedades: drawer flutuante (não ocupa espaço do canvas) -->
    <div id="inspector-drawer">
        <div class="drawer-hd">
            <span class="fw-semibold small"><i class="bi bi-sliders"></i> Propriedades</span>
            <button class="btn btn-sm btn-link p-0 text-muted" onclick="closeInspector()"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="drawer-bd" id="inspector">
            <p class="text-muted small mb-0">Selecione um bloco para editar.</p>
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

<?php require APP_PATH . '/views/layouts/_var_picker.php'; ?>
<?php require APP_PATH . '/views/sequences/_editor_script.php'; ?>
<?php require APP_PATH . '/views/layouts/footer.php'; ?>
