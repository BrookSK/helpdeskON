<?php $pageTitle = 'Oportunidades - Captação de Leads'; $currentPage = 'leadcapture_opps'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<?php
$canCollect = !empty($settings['enabled']) && (!empty($terms) || !empty($settings['collect_general']));
?>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0"><i class="bi bi-binoculars"></i> Oportunidades</h5>
            <small class="text-muted" id="collect-summary">
                <?php if (!empty($health['last_run_at'])): ?>
                Última coleta: <?= timeAgo($health['last_run_at']) ?> ·
                <?= (int)($health['projects_found_last_run'] ?? 0) ?> encontradas
                <?php else: ?>
                Nenhuma coleta ainda.
                <?php endif; ?>
            </small>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= baseUrl('leadcapture/configuracoes') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-gear"></i> Configurações</a>
            <a href="<?= baseUrl('leadcapture/saude') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-activity"></i> Saúde</a>
            <button class="btn btn-sm btn-primary" id="collect-btn" onclick="runCollect()"
                <?= $canCollect ? '' : 'disabled title="Habilite a fonte e cadastre ao menos um termo em Configurações"' ?>>
                <i class="bi bi-cloud-download"></i> Buscar novos projetos agora
            </button>
        </div>
    </div>

    <!-- Abas: Oportunidades / Diagnóstico -->
    <ul class="nav nav-pills lc-tabs mb-3 flex-wrap" id="lc-tabs">
        <li class="nav-item"><button class="nav-link active" data-tab="opps" onclick="switchLcTab('opps')"><i class="bi bi-binoculars"></i> Oportunidades</button></li>
        <li class="nav-item"><button class="nav-link" data-tab="diag" onclick="switchLcTab('diag')"><i class="bi bi-heart-pulse"></i> Diagnóstico</button></li>
    </ul>
    <style>
    .lc-tabs .nav-link { color: #555; font-size: 0.85rem; border-radius: 8px; }
    .lc-tabs .nav-link.active { background: var(--primary); color: #fff; }
    </style>

    <!-- Painel de Diagnóstico -->
    <div id="lc-diag-panel" style="display:none;">
        <div class="card mb-3">
            <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h6 class="mb-1"><i class="bi bi-heart-pulse"></i> Diagnóstico da Integração 99Freelas</h6>
                    <small class="text-muted">Valida parser (contra as capturas reais), normalizer, paginação e faz uma coleta ao vivo. Consolida tudo para copiar.</small>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-primary" id="lc-diag-run" onclick="runDiag()"><i class="bi bi-play-fill"></i> Rodar diagnóstico</button>
                    <button class="btn btn-sm btn-outline-secondary" id="lc-diag-copy" onclick="copyDiag()" style="display:none;"><i class="bi bi-clipboard"></i> Copiar tudo</button>
                </div>
            </div>
        </div>
        <div id="lc-diag-loading" class="text-center text-muted py-5" style="display:none;">
            <div class="spinner-border text-primary"></div>
            <p class="mb-0 mt-2">Executando verificações e coleta ao vivo…</p>
        </div>
        <div id="lc-diag-summary" class="row g-2 mb-3" style="display:none;">
            <div class="col"><div class="card text-center"><div class="card-body py-2"><div class="fs-4 fw-bold" id="lc-d-total">0</div><small class="text-muted">Total</small></div></div></div>
            <div class="col"><div class="card text-center"><div class="card-body py-2"><div class="fs-4 fw-bold text-success" id="lc-d-ok">0</div><small class="text-muted">OK</small></div></div></div>
            <div class="col"><div class="card text-center"><div class="card-body py-2"><div class="fs-4 fw-bold text-warning" id="lc-d-warn">0</div><small class="text-muted">Avisos</small></div></div></div>
            <div class="col"><div class="card text-center"><div class="card-body py-2"><div class="fs-4 fw-bold text-danger" id="lc-d-failed">0</div><small class="text-muted">Falhas</small></div></div></div>
        </div>
        <div id="lc-diag-results" class="mb-3"></div>
        <div class="card" id="lc-diag-cons-wrap" style="display:none;">
            <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                <h6 class="mb-0" style="font-size:0.85rem;"><i class="bi bi-clipboard-data"></i> Consolidado (copie e cole para depurar)</h6>
                <button class="btn btn-sm btn-outline-secondary" onclick="copyDiag()"><i class="bi bi-clipboard"></i> Copiar</button>
            </div>
            <div class="card-body p-0">
                <textarea id="lc-diag-cons" class="form-control border-0" rows="16" readonly style="font-family:monospace;font-size:0.78rem;white-space:pre;"></textarea>
            </div>
        </div>
    </div>

    <!-- Conteúdo principal das Oportunidades -->
    <div id="lc-opps-panel">

    <!-- Aviso pós-coleta -->
    <div id="collect-alert" class="alert alert-info d-none"></div>

    <!-- Filtros -->
    <div class="card mb-3">
        <div class="card-body py-2 px-3">
            <div class="row g-2 align-items-end">
                <div class="col-6 col-md-auto">
                    <label class="form-label small mb-0">Status</label>
                    <select class="form-select form-select-sm f-opp" data-key="status">
                        <option value="">Ativas (novas + vistas)</option>
                        <option value="nova">Somente novas</option>
                        <option value="vista">Somente vistas</option>
                        <option value="convertida">No CRM</option>
                        <option value="ignorada">Ignoradas</option>
                    </select>
                </div>
                <div class="col-6 col-md-auto">
                    <label class="form-label small mb-0">Termo</label>
                    <select class="form-select form-select-sm f-opp" data-key="term">
                        <option value="">Todos</option>
                        <?php foreach ($terms as $t): ?>
                        <option value="<?= escape($t['term']) ?>"><?= escape($t['term']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-auto">
                    <label class="form-label small mb-0">Categoria</label>
                    <select class="form-select form-select-sm f-opp" data-key="category">
                        <option value="">Todas</option>
                        <?php foreach ($categories as $c): ?>
                        <option value="<?= escape($c) ?>"><?= escape($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-auto">
                    <label class="form-label small mb-0">Ordenar</label>
                    <select class="form-select form-select-sm f-opp" data-key="sort">
                        <option value="first_seen">Mais recentes (descobertos)</option>
                        <option value="score">Score</option>
                        <option value="proposals">Menos propostas</option>
                        <option value="published">Data de publicação</option>
                    </select>
                </div>
                <div class="col-12 col-md">
                    <label class="form-label small mb-0">Buscar</label>
                    <input type="text" class="form-control form-control-sm f-opp" data-key="search" placeholder="título ou descrição...">
                </div>
                <div class="col-auto">
                    <button class="btn btn-sm btn-primary" onclick="loadOpps(1)"><i class="bi bi-search"></i></button>
                    <button class="btn btn-sm btn-outline-secondary" onclick="markAllSeen()" title="Marcar todas as novas como vistas"><i class="bi bi-check2-all"></i></button>
                </div>
            </div>
        </div>
    </div>

    <div id="opps-loading" class="text-center text-muted py-5" style="display:none;">
        <div class="spinner-border text-primary"></div>
    </div>
    <div id="opps-list"></div>
    <div id="opps-empty" class="text-center text-muted py-5" style="display:none;">
        <i class="bi bi-inbox" style="font-size:2rem;"></i>
        <p class="mb-0 mt-2">Nenhuma oportunidade para os filtros atuais.</p>
    </div>

    <!-- Paginação -->
    <div class="d-flex justify-content-between align-items-center mt-3" id="opps-pagination" style="display:none;">
        <small class="text-muted" id="opps-page-info"></small>
        <div class="d-flex align-items-center gap-2">
            <select class="form-select form-select-sm" id="opps-perpage" style="width:auto;" onchange="loadOpps(1)">
                <option value="25">25/pág</option>
                <option value="50" selected>50/pág</option>
                <option value="100">100/pág</option>
            </select>
            <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-secondary" id="opps-prev" onclick="changeOppPage(-1)"><i class="bi bi-chevron-left"></i></button>
                <button class="btn btn-outline-secondary" id="opps-next" onclick="changeOppPage(1)"><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>
    </div><!-- /#lc-opps-panel -->
</div>

<?php require APP_PATH . '/views/leadcapture/_opportunities_script.php'; ?>
<?php require APP_PATH . '/views/layouts/footer.php'; ?>
