<?php $pageTitle = 'Captação de Leads - CRM'; $currentPage = 'crm_capture'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0"><i class="bi bi-search"></i> Captação de Leads</h5>
            <small class="text-muted">Pesquise prospects no Apollo.io, revele os dados e envie para Meus Leads</small>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span id="apollo-status-badge" class="badge bg-secondary"><i class="bi bi-hourglass"></i> Verificando…</span>
            <a href="<?= baseUrl('crm/leads') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-person-lines-fill"></i> Meus Leads</a>
        </div>
    </div>

    <?php if (empty($apolloConfigured)): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle"></i> A integração com o Apollo.io não está configurada.
        <?php if (($user['role'] ?? '') === 'super_admin'): ?>
        Informe a <strong>API key</strong> em <a href="<?= baseUrl('settings') ?>">Configurações</a>.
        <?php else: ?>
        Peça ao administrador para configurar a API key.
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Abas: Pessoas / Empresas / Capturados / Diagnóstico -->
    <ul class="nav nav-pills cap-tabs mb-3 flex-wrap" id="capture-tabs">
        <li class="nav-item"><button class="nav-link active" data-tab="people" onclick="switchTab('people')"><i class="bi bi-person"></i> Pessoas</button></li>
        <li class="nav-item"><button class="nav-link" data-tab="orgs" onclick="switchTab('orgs')"><i class="bi bi-building"></i> Empresas</button></li>
        <li class="nav-item"><button class="nav-link" data-tab="captured" onclick="switchTab('captured')"><i class="bi bi-collection"></i> Capturados</button></li>
        <?php if (($user['role'] ?? '') === 'super_admin'): ?>
        <li class="nav-item"><button class="nav-link" data-tab="diagnostic" onclick="switchTab('diagnostic')"><i class="bi bi-heart-pulse"></i> Diagnóstico</button></li>
        <?php endif; ?>
    </ul>

    <style>
    /* Abas no padrão da plataforma (igual ao módulo de Marketing) */
    .cap-tabs .nav-link { color: #555; font-size: 0.85rem; border-radius: 8px; }
    .cap-tabs .nav-link.active { background: var(--primary); color: #fff; }
    /* Barra de resultados: rola só a lista, mantendo cabeçalho e paginação fixos */
    #results-scroll { max-height: calc(100vh - 340px); min-height: 320px; overflow-y: auto; }
    /* Coluna de filtros e de resultados com a mesma altura de referência */
    #capture-main .card { min-height: 60vh; }
    #results-empty, #results-loading { min-height: 320px; display: flex; flex-direction: column; align-items: center; justify-content: center; }
    /* A barra de ações em massa não deve quebrar o layout quando vazia */
    #bulk-actions { flex-shrink: 0; }
    </style>

    <!-- Painel de Diagnóstico -->
    <?php if (($user['role'] ?? '') === 'super_admin'): ?>
    <div id="diagnostic-panel" style="display:none;">
        <div class="card mb-3">
            <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h6 class="mb-1"><i class="bi bi-heart-pulse"></i> Diagnóstico da Integração Apollo</h6>
                    <small class="text-muted">Executa uma chamada real a cada endpoint e consolida request, resposta e erros.</small>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-primary" id="diag-run-btn" onclick="runDiagnostics()"><i class="bi bi-play-fill"></i> Rodar diagnóstico</button>
                    <button class="btn btn-sm btn-outline-secondary" id="diag-copy-btn" onclick="copyDiagnostics()" style="display:none;"><i class="bi bi-clipboard"></i> Copiar tudo</button>
                </div>
            </div>
        </div>

        <div id="diag-loading" class="text-center text-muted py-5" style="display:none;">
            <div class="spinner-border text-primary"></div>
            <p class="mb-0 mt-2">Executando chamadas aos endpoints…</p>
        </div>

        <!-- Resumo -->
        <div id="diag-summary" class="row g-2 mb-3" style="display:none;">
            <div class="col"><div class="card text-center"><div class="card-body py-2"><div class="fs-4 fw-bold" id="diag-total">0</div><small class="text-muted">Total</small></div></div></div>
            <div class="col"><div class="card text-center"><div class="card-body py-2"><div class="fs-4 fw-bold text-success" id="diag-ok">0</div><small class="text-muted">OK</small></div></div></div>
            <div class="col"><div class="card text-center"><div class="card-body py-2"><div class="fs-4 fw-bold text-danger" id="diag-failed">0</div><small class="text-muted">Falhas</small></div></div></div>
            <div class="col"><div class="card text-center"><div class="card-body py-2"><div class="fs-4 fw-bold text-secondary" id="diag-skipped">0</div><small class="text-muted">Ignorados</small></div></div></div>
        </div>

        <!-- Lista de resultados por endpoint -->
        <div id="diag-results" class="mb-3"></div>

        <!-- Consolidado para copiar/colar -->
        <div class="card" id="diag-consolidated-wrap" style="display:none;">
            <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                <h6 class="mb-0" style="font-size:0.85rem;"><i class="bi bi-clipboard-data"></i> Consolidado (copie e cole para depurar)</h6>
                <button class="btn btn-sm btn-outline-secondary" onclick="copyDiagnostics()"><i class="bi bi-clipboard"></i> Copiar</button>
            </div>
            <div class="card-body p-0">
                <textarea id="diag-consolidated" class="form-control border-0" rows="16" readonly style="font-family:monospace;font-size:0.78rem;white-space:pre;"></textarea>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php require APP_PATH . '/views/crm/_capture_data.php'; ?>

    <div class="row g-3" id="capture-main">
        <!-- Coluna de filtros -->
        <div class="col-lg-3" id="filters-col">
            <div class="card">
                <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0" style="font-size:0.85rem;"><i class="bi bi-funnel"></i> Filtros</h6>
                    <button class="btn btn-sm btn-link p-0 text-muted" onclick="clearFilters()" title="Limpar filtros"><i class="bi bi-x-circle"></i></button>
                </div>
                <div class="card-body" style="max-height:70vh;overflow-y:auto;">
                    <!-- Filtros de Pessoas -->
                    <div id="people-filters">
                        <?php require APP_PATH . '/views/crm/_capture_people_filters.php'; ?>
                    </div>
                    <!-- Filtros de Empresas -->
                    <div id="orgs-filters" style="display:none;">
                        <?php require APP_PATH . '/views/crm/_capture_org_filters.php'; ?>
                    </div>
                </div>
                <div class="card-footer bg-white py-2">
                    <button class="btn btn-sm btn-primary w-100" id="search-btn" onclick="runSearch(1)">
                        <i class="bi bi-search"></i> Pesquisar
                    </button>
                </div>
            </div>
        </div>

        <!-- Coluna de resultados -->
        <div class="col-lg-9" id="results-col">
            <div class="card">
                <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <div class="form-check mb-0" id="select-all-wrap" style="display:none;">
                            <input class="form-check-input" type="checkbox" id="select-all" onclick="toggleSelectAll(this)">
                            <label class="form-check-label small" for="select-all">Selecionar todos</label>
                        </div>
                        <span class="text-muted small" id="result-count"></span>
                    </div>
                    <div class="d-flex gap-2" id="bulk-actions" style="display:none;">
                        <button class="btn btn-sm btn-outline-success" onclick="revealSelected()" title="Liberar e-mail e telefone dos selecionados">
                            <i class="bi bi-unlock"></i> Liberar dados
                        </button>
                        <button class="btn btn-sm btn-success" onclick="importSelected()">
                            <i class="bi bi-download"></i> Enviar p/ Meus Leads (<span id="sel-count">0</span>)
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div id="results-loading" class="text-center text-muted py-5" style="display:none;">
                        <div class="spinner-border text-primary"></div>
                        <p class="mb-0 mt-2">Buscando no Apollo…</p>
                    </div>
                    <div id="results-empty" class="text-center text-muted py-5">
                        <i class="bi bi-search" style="font-size:2rem;"></i>
                        <p class="mb-0 mt-2">Configure os filtros e clique em Pesquisar.</p>
                    </div>
                    <div class="table-responsive" id="results-wrap" style="display:none;">
                        <div id="results-scroll">
                            <table class="table table-hover align-middle mb-0" style="font-size:0.83rem;">
                                <thead class="table-light" id="results-head" style="position:sticky;top:0;z-index:2;"></thead>
                                <tbody id="results-body"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-white py-2 d-flex justify-content-between align-items-center" id="pagination-bar" style="display:none;">
                    <small class="text-muted" id="pagination-info"></small>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-secondary" id="prev-page" onclick="changePage(-1)"><i class="bi bi-chevron-left"></i></button>
                        <button class="btn btn-outline-secondary" id="next-page" onclick="changePage(1)"><i class="bi bi-chevron-right"></i></button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require APP_PATH . '/views/crm/_capture_script.php'; ?>
<?php require APP_PATH . '/views/layouts/footer.php'; ?>
