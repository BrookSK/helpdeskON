<?php $pageTitle = 'Planejamento - ON Solutions Helpdesk'; $currentPage = 'planning'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<?php
// Helpers da tag/selo de categoria (mesmas cores da tela da demanda).
if (!function_exists('categoryBadgeClass')) {
    function categoryBadgeClass(?string $category): string
    {
        switch (strtolower(trim((string)$category))) {
            case 'suporte':         return 'bg-danger';
            case 'desenvolvimento': return 'bg-primary';
            case 'design':          return 'text-white';
            case 'marketing':       return 'bg-warning text-dark';
            default:                return 'bg-secondary';
        }
    }
}
if (!function_exists('categoryBadgeStyle')) {
    function categoryBadgeStyle(?string $category): string
    {
        return strtolower(trim((string)$category)) === 'design' ? 'background-color:#6f42c1;' : '';
    }
}
if (!function_exists('categoryLabel')) {
    function categoryLabel(?string $category): string
    {
        $c = strtolower(trim((string)$category));
        $map = ['suporte' => 'Suporte', 'desenvolvimento' => 'Desenvolvimento', 'design' => 'Design', 'marketing' => 'Marketing', 'outro' => 'Outro'];
        return $map[$c] ?? ucfirst($c);
    }
}
?>

<?php
$statusLabels = [
    'open' => ['Aberto', '#1565c0'],
    'in_progress' => ['Em andamento', '#e65100'],
    'em_revisao_interna' => ['Em Revisão Interna', '#5c6bc0'],
    'waiting_client' => ['Aguardando', '#7b1fa2'],
    'em_homologacao' => ['Em Homologação', '#0097a7'],
    'aprovado_producao' => ['Aprov. Produção', '#8bc34a'],
    'completed' => ['Concluído', '#2e7d32'],
    'denied' => ['Negado', '#d84315'],
    'archived' => ['Arquivado', '#546e7a'],
];
$priorityLabels = ['low' => 'Baixa', 'medium' => 'Média', 'high' => 'Alta', 'urgent' => 'Urgente'];
?>

<div class="main-content">
    <div class="top-bar d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h5 class="mb-0">Planejamento</h5>
            <small class="text-muted">Gerencie cards da equipe</small>
        </div>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <div class="btn-group btn-group-sm" id="view-toggle">
                <button type="button" class="btn btn-outline-primary active" data-view="kanban"><i class="bi bi-kanban"></i> Kanban</button>
                <button type="button" class="btn btn-outline-primary" data-view="calendar"><i class="bi bi-calendar3"></i> Calendário</button>
            </div>
            <button class="btn btn-primary btn-sm" onclick="openCreateModal()"><i class="bi bi-plus-lg"></i> Novo Card</button>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card mb-3">
        <div class="card-body py-2 px-3">
            <?php
            // Valores selecionados (arrays) para marcar os checkboxes
            $selCompanies = array_map('intval', (array)($filters['company_id'] ?? []));
            $rawAssignedSel = (array)($filters['assigned_to'] ?? []);
            $selAssignedNone = in_array('none', $rawAssignedSel, true) || !empty($filters['assigned_none']);
            $selAssigned  = array_map('intval', array_filter($rawAssignedSel, fn($v) => $v !== 'none'));
            $selRequesters = array_map('intval', (array)($filters['created_by'] ?? []));
            $selStatuses  = (array)($filters['statuses'] ?? []);
            ?>
            <form method="GET" class="row g-2 align-items-center" id="filters-form">
                <input type="hidden" name="show_all" id="show_all_input" value="1">
                <!-- Visão atual (kanban|calendar): preserva a aba ao aplicar o filtro. -->
                <input type="hidden" name="view" id="view_input" value="<?= escape($activeView ?? 'kanban') ?>">

                <!-- Busca por nome/descrição do card -->
                <div class="col-12 col-md-auto">
                    <div class="input-group input-group-sm" style="min-width:220px;">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input type="search" name="q" class="form-control form-control-sm"
                               placeholder="Buscar por nome ou descrição"
                               value="<?= escape($_GET['q'] ?? '') ?>">
                    </div>
                </div>

                <!-- Empresas (múltipla escolha) -->
                <div class="col-6 col-md-auto">
                    <div class="dropdown multi-filter">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle w-100 text-start" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                            <span data-ph="Todas Empresas" data-single="empresa" data-plural="empresas">Todas Empresas</span>
                        </button>
                        <div class="dropdown-menu p-2" style="max-height:280px;overflow-y:auto;min-width:220px;">
                            <label class="dropdown-item d-flex align-items-center gap-2 px-2 py-1 fw-medium" style="cursor:pointer;">
                                <input class="form-check-input mt-0 mf-all" type="checkbox">
                                <span class="small">Selecionar todas</span>
                            </label>
                            <div class="dropdown-divider my-1"></div>
                            <?php foreach ($companies as $c): ?>
                            <label class="dropdown-item d-flex align-items-center gap-2 px-2 py-1" style="cursor:pointer;">
                                <input class="form-check-input mt-0 mf-check" type="checkbox" name="company_id[]" value="<?= $c['id'] ?>" <?= in_array((int)$c['id'], $selCompanies, true) ? 'checked' : '' ?>>
                                <span class="small"><?= escape($c['name']) ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Responsáveis (múltipla escolha) -->
                <div class="col-6 col-md-auto">
                    <div class="dropdown multi-filter">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle w-100 text-start" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                            <span data-ph="Todos Responsáveis" data-single="responsável" data-plural="responsáveis">Todos Responsáveis</span>
                        </button>
                        <div class="dropdown-menu p-2" style="max-height:280px;overflow-y:auto;min-width:220px;">
                            <label class="dropdown-item d-flex align-items-center gap-2 px-2 py-1 fw-medium" style="cursor:pointer;">
                                <input class="form-check-input mt-0 mf-all" type="checkbox">
                                <span class="small">Selecionar todos</span>
                            </label>
                            <label class="dropdown-item d-flex align-items-center gap-2 px-2 py-1" style="cursor:pointer;">
                                <input class="form-check-input mt-0 mf-none" type="checkbox" name="assigned_to[]" value="none" <?= $selAssignedNone ? 'checked' : '' ?>>
                                <span class="small fst-italic text-muted">Sem responsável</span>
                            </label>
                            <div class="dropdown-divider my-1"></div>
                            <?php foreach ($teamMembers as $m): ?>
                            <label class="dropdown-item d-flex align-items-center gap-2 px-2 py-1" style="cursor:pointer;">
                                <input class="form-check-input mt-0 mf-check" type="checkbox" name="assigned_to[]" value="<?= $m['id'] ?>" <?= in_array((int)$m['id'], $selAssigned, true) ? 'checked' : '' ?>>
                                <span class="small"><?= escape($m['name']) ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Solicitantes (múltipla escolha) -->
                <div class="col-6 col-md-auto">
                    <div class="dropdown multi-filter">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle w-100 text-start" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                            <span data-ph="Todos Solicitantes" data-single="solicitante" data-plural="solicitantes">Todos Solicitantes</span>
                        </button>
                        <div class="dropdown-menu p-2" style="max-height:280px;overflow-y:auto;min-width:220px;">
                            <label class="dropdown-item d-flex align-items-center gap-2 px-2 py-1 fw-medium" style="cursor:pointer;">
                                <input class="form-check-input mt-0 mf-all" type="checkbox">
                                <span class="small">Selecionar todos</span>
                            </label>
                            <div class="dropdown-divider my-1"></div>
                            <?php foreach (($requesters ?? []) as $r): ?>
                            <label class="dropdown-item d-flex align-items-center gap-2 px-2 py-1" style="cursor:pointer;">
                                <input class="form-check-input mt-0 mf-check" type="checkbox" name="created_by[]" value="<?= $r['id'] ?>" <?= in_array((int)$r['id'], $selRequesters, true) ? 'checked' : '' ?>>
                                <span class="small"><?= escape($r['name']) ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Status (múltipla escolha) -->
                <div class="col-6 col-md-auto">
                    <div class="dropdown multi-filter">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle w-100 text-start" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                            <span data-ph="Todos os Status" data-single="status" data-plural="status">Todos os Status</span>
                        </button>
                        <div class="dropdown-menu p-2" style="max-height:280px;overflow-y:auto;min-width:220px;">
                            <label class="dropdown-item d-flex align-items-center gap-2 px-2 py-1 fw-medium" style="cursor:pointer;">
                                <input class="form-check-input mt-0 mf-all" type="checkbox">
                                <span class="small">Selecionar todos</span>
                            </label>
                            <div class="dropdown-divider my-1"></div>
                            <?php foreach ($statusLabels as $s => $info): ?>
                            <label class="dropdown-item d-flex align-items-center gap-2 px-2 py-1" style="cursor:pointer;">
                                <input class="form-check-input mt-0 mf-check" type="checkbox" name="statuses[]" value="<?= $s ?>" <?= in_array($s, $selStatuses, true) ? 'checked' : '' ?>>
                                <span class="small" style="color:<?= $info[1] ?>;"><?= $info[0] ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-md-auto">
                    <select name="order" class="form-select form-select-sm">
                        <option value="" <?= empty($_GET['order']) ? 'selected' : '' ?>>Ordenação padrão</option>
                        <option value="overdue" <?= ($_GET['order'] ?? '') === 'overdue' ? 'selected' : '' ?>>Vencidos primeiro</option>
                        <option value="priority" <?= ($_GET['order'] ?? '') === 'priority' ? 'selected' : '' ?>>Por prioridade</option>
                        <option value="newest" <?= ($_GET['order'] ?? '') === 'newest' ? 'selected' : '' ?>>Mais recentes</option>
                    </select>
                </div>
                <div class="col-12 col-md-auto">
                    <button type="submit" class="btn btn-sm btn-primary">Filtrar</button>
                    <a href="<?= baseUrl('planning') ?>?show_all=1" class="btn btn-sm btn-outline-secondary">Limpar</a>
                </div>
            </form>
        </div>
    </div>

    <style>
        /* Barra de rolagem horizontal fixa acima do Kanban */
        #kanban-topscroll {
            position: sticky;
            top: 0;
            z-index: 20;
            overflow-x: auto;
            overflow-y: hidden;
            /* Mostra somente a barra de rolagem, sem conteúdo visível */
            height: 16px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.06);
            margin-bottom: 8px;
            /* Só aparece quando há transbordamento horizontal (controlado via JS) */
            display: none;
        }
        #kanban-topscroll-inner {
            height: 1px;
        }
        /* Deixa a barra de rolagem sempre visível e mais evidente (WebKit) */
        #kanban-topscroll::-webkit-scrollbar { height: 12px; }
        #kanban-topscroll::-webkit-scrollbar-track { background: #f1f1f4; border-radius: 8px; }
        #kanban-topscroll::-webkit-scrollbar-thumb { background: #b9bcc9; border-radius: 8px; }
        #kanban-topscroll::-webkit-scrollbar-thumb:hover { background: #9aa0b3; }
        /* Firefox */
        #kanban-topscroll { scrollbar-width: thin; scrollbar-color: #b9bcc9 #f1f1f4; }
    </style>

    <!-- KANBAN VIEW -->
    <div id="kanban-view">
        <!-- Barra de rolagem horizontal fixa (sincronizada com o Kanban abaixo) -->
        <div id="kanban-topscroll" aria-hidden="true">
            <div id="kanban-topscroll-inner"></div>
        </div>
        <div class="kanban-scroll" style="overflow-x:auto;-webkit-overflow-scrolling:touch;padding-bottom:10px;">
            <div class="d-flex gap-3" id="kanban-track" style="min-width:max-content;">
                <?php
                // Se o usuário filtrou status específicos, mostra apenas essas colunas.
                $visibleStatuses = !empty($selStatuses) ? array_intersect(array_keys($statusLabels), $selStatuses) : array_keys($statusLabels);
                ?>
                <?php foreach ($statusLabels as $status => $info): ?>
                <?php if (!in_array($status, $visibleStatuses, true)) continue; ?>
                <div style="width:260px;flex-shrink:0;">
                    <div class="kanban-column">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0 fw-bold" style="color:<?= $info[1] ?>;font-size:0.85rem;"><?= $info[0] ?></h6>
                            <span class="badge rounded-pill" style="background:<?= $info[1] ?>;color:#fff;font-size:0.7rem"><?= count($grouped[$status] ?? []) ?></span>
                        </div>
                        <?php
                        $colTotal = $columnCounts[$status] ?? count($grouped[$status] ?? []);
                        $colShown = count($grouped[$status] ?? []);
                        ?>
                        <div class="kanban-list" data-status="<?= $status ?>" data-total="<?= (int) $colTotal ?>" data-shown="<?= (int) $colShown ?>" style="min-height:60px;">
                            <?php foreach (($grouped[$status] ?? []) as $card): ?>
                            <?php
                            // Card em atraso: tem prazo no passado e não está concluído/arquivado
                            $isOverdue = !empty($card['due_date'])
                                && strtotime($card['due_date']) < time()
                                && !in_array($card['status'], ['completed', 'archived']);
                            ?>
                            <div class="kanban-card planning-card<?= $isOverdue ? ' overdue' : '' ?>" data-id="<?= $card['id'] ?>" onclick="openCardModal(<?= $card['id'] ?>)">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <span class="text-muted" style="font-size:0.7rem">#<?= $card['id'] ?></span>
                                    <span class="priority-<?= $card['priority'] ?>" style="font-size:0.7rem"><?= $priorityLabels[$card['priority']] ?? '' ?></span>
                                </div>
                                <div class="fw-medium" style="font-size:0.82rem;word-break:break-word;"><?= escape($card['title']) ?></div>
                                <?php
                                // Tag de MARCO de aprovação (uma só, da mais avançada para a menos):
                                //  1) Aprovado p/ Produção (status aprovado_producao)
                                //  2) Recusado (escopo recusado pelo cliente, não reaprovado)
                                //  3) Aprovado (escopo aprovado pelo cliente)
                                $milestoneTag = null;
                                if (($card['status'] ?? '') === 'aprovado_producao') {
                                    $milestoneTag = ['label' => 'Aprov. Produção', 'class' => 'bg-success', 'title' => 'Aprovado para produção'];
                                } elseif (!empty($card['scope_rejected_reason'])) {
                                    $milestoneTag = ['label' => 'Recusado', 'class' => 'bg-danger', 'title' => 'Escopo recusado pelo cliente'];
                                } elseif (!empty($card['scope_approved_at'])) {
                                    $milestoneTag = ['label' => 'Aprovado', 'class' => 'bg-success', 'title' => 'Escopo aprovado pelo cliente'];
                                }
                                ?>
                                <?php if (!empty($card['category']) || $milestoneTag !== null): ?>
                                <div class="mt-1 d-flex flex-wrap gap-1">
                                    <?php if (!empty($card['category'])): ?>
                                    <span class="badge <?= categoryBadgeClass($card['category']) ?>" style="font-size:0.62rem;<?= categoryBadgeStyle($card['category']) ?>"><?= escape(categoryLabel($card['category'])) ?></span>
                                    <?php endif; ?>
                                    <?php if ($milestoneTag !== null): ?>
                                    <span class="badge <?= $milestoneTag['class'] ?>" style="font-size:0.62rem;" title="<?= escape($milestoneTag['title']) ?>"><?= escape($milestoneTag['label']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                                <div class="text-muted mt-2" style="font-size:0.7rem">
                                    <?php if ($card['company_name']): ?>
                                    <span><i class="bi bi-building"></i> <?= escape($card['company_name']) ?></span><br>
                                    <?php endif; ?>
                                    <?php if (!empty($card['created_by_name'])): ?>
                                    <span><i class="bi bi-person-badge"></i> <?= escape($card['created_by_name']) ?></span><br>
                                    <?php endif; ?>
                                    <span><i class="bi bi-person"></i> <?= escape($card['assigned_name'] ?? 'Não atribuído') ?></span>
                                    <?php if ($card['due_date']): ?>
                                    <span class="float-end <?= $isOverdue ? 'text-danger fw-semibold' : '' ?>"><i class="bi bi-clock"></i> <?= date('d/m H:i', strtotime($card['due_date'])) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if ($colTotal > $colShown): ?>
                        <div class="kanban-loadmore" data-status="<?= $status ?>">
                            <button type="button" class="btn btn-sm btn-outline-secondary w-100" onclick="loadMoreColumn('<?= $status ?>', this)">
                                <i class="bi bi-chevron-down"></i> Carregar mais
                                <span class="kanban-loadmore-count">(<?= (int) ($colTotal - $colShown) ?> restantes)</span>
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- CALENDAR VIEW -->
    <div id="calendar-view" style="display:none;">
        <div class="card">
            <div class="card-body p-2 p-md-3">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <div class="btn-group btn-group-sm" id="cal-mode-toggle">
                        <button type="button" class="btn btn-outline-secondary active" data-mode="month">Mês</button>
                        <button type="button" class="btn btn-outline-secondary" data-mode="week">Semana</button>
                        <button type="button" class="btn btn-outline-secondary" data-mode="day">Dia</button>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <button class="btn btn-sm btn-outline-secondary" id="cal-prev"><i class="bi bi-chevron-left"></i></button>
                        <span class="fw-medium" id="cal-title" style="font-size:0.9rem;min-width:140px;text-align:center;"></span>
                        <button class="btn btn-sm btn-outline-secondary" id="cal-next"><i class="bi bi-chevron-right"></i></button>
                        <button class="btn btn-sm btn-outline-primary" id="cal-today">Hoje</button>
                    </div>
                </div>
                <!-- Legenda de cores por prazo/status -->
                <div class="cal-legend" aria-label="Legenda de cores do calendário">
                    <span class="cal-legend-item"><span class="cal-legend-swatch cat-overdue"></span>Passado e não concluído</span>
                    <span class="cal-legend-item"><span class="cal-legend-swatch cat-done"></span>Passado e concluído</span>
                    <span class="cal-legend-item"><span class="cal-legend-swatch cat-on_track"></span>Dentro do prazo</span>
                    <span class="cal-legend-item"><span class="cal-legend-swatch cat-near_due"></span>Próximo do vencimento</span>
                    <span class="cal-legend-item"><span class="cal-legend-swatch cat-due_today"></span>Na data de vencimento</span>
                    <span class="cal-legend-item"><span class="cal-legend-swatch cat-in_progress"></span>Em andamento</span>
                    <span class="cal-legend-item"><span class="cal-legend-swatch cat-revisao"></span>Em revisão interna</span>
                    <span class="cal-legend-item"><span class="cal-legend-swatch cat-waiting"></span>Aguardando cliente</span>
                    <span class="cal-legend-item"><span class="cal-legend-swatch cat-homolog"></span>Em homologação</span>
                    <span class="cal-legend-item"><span class="cal-legend-swatch cat-denied"></span>Negada</span>
                </div>
                <!-- Aviso de demandas sem data: aparece só quando há cards ativos
                     sem prazo/janela. O botão "Revisar" abre o modal de revisão. -->
                <div id="cal-undated-banner" class="cal-undated-banner" style="display:none;" role="status">
                    <div class="cal-undated-msg">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <span><strong id="cal-undated-count">0</strong> <span id="cal-undated-text">demandas sem data definida</span></span>
                        <span class="cal-undated-sub">não aparecem no calendário até receberem um prazo.</span>
                    </div>
                    <button type="button" class="btn btn-sm btn-warning" onclick="openUndatedModal()">
                        <i class="bi bi-calendar-check"></i> Revisar
                    </button>
                </div>
                <div id="calendar-container"></div>
            </div>
        </div>
    </div>

    <!-- MODAL REVISAR DEMANDAS SEM DATA -->
    <div class="modal fade" id="undatedModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title mb-0 fw-bold"><i class="bi bi-calendar-check text-warning"></i> Demandas sem data</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-2">Estas demandas ativas não têm prazo nem janela de desenvolvimento, por isso não aparecem no calendário. Clique para abrir e definir uma data.</p>
                    <div id="undated-list">
                        <div class="text-center py-4 text-muted small"><span class="spinner-border spinner-border-sm"></span> Carregando...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL CRIAR CARD -->
    <div class="modal fade" id="createCardModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">Novo Card</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="<?= baseUrl('planning/create') ?>" method="POST" id="createCardForm">
                    <?php
                    // Query string dos filtros atuais, sem o parâmetro interno "url"
                    // (usado pelo roteador). Fallback caso o envio via AJAX falhe.
                    $returnParams = $_GET;
                    unset($returnParams['url']);
                    $returnQueryStr = http_build_query($returnParams);
                    ?>
                    <input type="hidden" name="return_query" value="<?= escape($returnQueryStr) ?>">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label small fw-medium">Título *</label>
                            <input type="text" name="title" class="form-control form-control-sm" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-medium">Descrição</label>
                            <textarea name="description" class="form-control form-control-sm" rows="3" placeholder="Descreva os detalhes do card..."></textarea>
                        </div>
                        <div class="row g-2">
                            <div class="col-sm-6 mb-3">
                                <label class="form-label small fw-medium">Empresa</label>
                                <select name="company_id" class="form-select form-select-sm">
                                    <option value="">Nenhuma</option>
                                    <?php foreach ($companies as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= escape($c['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-sm-6 mb-3">
                                <label class="form-label small fw-medium">Atendente</label>
                                <select name="assigned_to" class="form-select form-select-sm">
                                    <option value="">Não atribuído</option>
                                    <?php foreach (($attendantsList ?? []) as $m): ?>
                                    <option value="<?= $m['id'] ?>"><?= escape($m['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row g-2">
                            <div class="col-sm-6 mb-3">
                                <label class="form-label small fw-medium">Técnico</label>
                                <select name="technical_responsible_id" class="form-select form-select-sm">
                                    <option value="">Não atribuído</option>
                                    <?php foreach (($techniciansList ?? []) as $m): ?>
                                    <option value="<?= $m['id'] ?>"><?= escape($m['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-sm-6 mb-3">
                                <label class="form-label small fw-medium">Analista</label>
                                <select name="analyst_id" class="form-select form-select-sm">
                                    <option value="">Não atribuído</option>
                                    <?php foreach (($analystsList ?? []) as $m): ?>
                                    <option value="<?= $m['id'] ?>"><?= escape($m['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row g-2">
                            <div class="col-sm-4 mb-3">
                                <label class="form-label small fw-medium">Prioridade</label>
                                <select name="priority" class="form-select form-select-sm">
                                    <option value="low">Baixa</option>
                                    <option value="medium" selected>Média</option>
                                    <option value="high">Alta</option>
                                    <option value="urgent">Urgente</option>
                                </select>
                            </div>
                            <div class="col-sm-4 mb-3">
                                <label class="form-label small fw-medium">Status</label>
                                <select name="status" class="form-select form-select-sm">
                                    <?php foreach ($statusLabels as $s => $info): ?>
                                    <option value="<?= $s ?>"><?= $info[0] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-sm-4 mb-3">
                                <label class="form-label small fw-medium">Prazo</label>
                                <input type="datetime-local" name="due_date" class="form-control form-control-sm">
                            </div>
                        </div>
                        <div class="row g-2">
                            <div class="col-sm-6 mb-3">
                                <label class="form-label small fw-medium">Início Desenvolvimento</label>
                                <input type="datetime-local" name="start_date" class="form-control form-control-sm">
                            </div>
                            <div class="col-sm-6 mb-3">
                                <label class="form-label small fw-medium">Fim Desenvolvimento</label>
                                <input type="datetime-local" name="end_date" class="form-control form-control-sm">
                            </div>
                        </div>
                        <!-- Atividade predecessora: este card só começa depois que o
                             predecessor terminar. Se o predecessor atrasar, as datas
                             deste card são recalculadas (respeitando o prazo). -->
                        <div class="row g-2">
                            <div class="col-sm-8 mb-3">
                                <label class="form-label small fw-medium">
                                    <i class="bi bi-diagram-3 text-primary"></i> Atividade predecessora
                                </label>
                                <!-- Autocomplete: digite para buscar o card predecessor -->
                                <div class="pred-autocomplete" data-pred-root>
                                    <input type="hidden" name="predecessor_id" class="pred-input-id" value="">
                                    <input type="text" class="form-control form-control-sm pred-input-search" autocomplete="off"
                                           placeholder="Buscar card por nº, título ou empresa...">
                                    <div class="pred-results" style="display:none;"></div>
                                </div>
                                <small class="text-muted" style="font-size:0.68rem;">Digite para buscar. Só inicia após o término da atividade escolhida.</small>
                            </div>
                            <div class="col-sm-4 mb-3">
                                <label class="form-label small fw-medium">Folga (dias)</label>
                                <input type="number" name="predecessor_lag_days" class="form-control form-control-sm" min="0" value="0" title="Dias de folga entre o fim do predecessor e o início deste card">
                            </div>
                        </div>
                        <hr class="my-2">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <i class="bi bi-calendar-range text-primary"></i>
                            <span class="small fw-semibold">Cronograma do Cliente</span>
                            <span class="text-muted" style="font-size:0.72rem;">visível para o cliente no painel dele</span>
                        </div>
                        <div class="row g-2">
                            <div class="col-sm-6 mb-3">
                                <label class="form-label small fw-medium">Início (cliente)</label>
                                <input type="date" name="client_start_date" class="form-control form-control-sm">
                            </div>
                            <div class="col-sm-6 mb-3">
                                <label class="form-label small fw-medium">Fim (cliente)</label>
                                <input type="date" name="client_end_date" class="form-control form-control-sm">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-sm btn-primary">Criar Card</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL DETALHE DO CARD -->
    <div class="modal fade" id="cardDetailModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-fullscreen-sm-down">
            <div class="modal-content">
                <div class="modal-header py-2 border-bottom">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-secondary" id="detail-id-badge">#</span>
                        <h6 class="modal-title mb-0 fw-bold" id="detail-title" style="word-break:break-word;white-space:normal;">Card</h6>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge" id="detail-category-badge" style="display:none;"></span>
                        <span class="badge rounded-pill" id="detail-priority-badge"></span>
                        <span class="badge rounded-pill" id="detail-status-badge"></span>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                </div>
                <div class="modal-body p-0" style="max-height:80vh;overflow:hidden;">
                    <div class="row g-0 h-100">
                        <!-- Painel esquerdo: Info principal -->
                        <div class="col-lg-8 border-end" style="max-height:80vh;overflow-y:auto;">
                            <!-- Abas de navegação -->
                            <ul class="nav nav-tabs nav-fill px-3 pt-2 border-bottom sticky-top bg-white" id="cardTabs" role="tablist" style="z-index:10;">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active small py-2" id="tab-descricao" data-bs-toggle="tab" data-bs-target="#pane-descricao" type="button" role="tab">
                                        <i class="bi bi-file-text"></i> Descrição
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link small py-2" id="tab-demanda" data-bs-toggle="tab" data-bs-target="#pane-demanda" type="button" role="tab">
                                        <i class="bi bi-ticket-detailed"></i> Demanda <span class="badge bg-primary ms-1" id="tab-demanda-badge" style="display:none;font-size:0.6rem;"></span>
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link small py-2" id="tab-anexos" data-bs-toggle="tab" data-bs-target="#pane-anexos" type="button" role="tab">
                                        <i class="bi bi-paperclip"></i> Anexos <span class="badge bg-secondary ms-1" id="tab-anexos-badge" style="font-size:0.6rem;">0</span>
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link small py-2" id="tab-tasks" data-bs-toggle="tab" data-bs-target="#pane-tasks" type="button" role="tab">
                                        <i class="bi bi-check2-square"></i> Tasks <span class="badge bg-secondary ms-1" id="tab-tasks-badge" style="font-size:0.6rem;">0</span>
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link small py-2" id="tab-comentarios" data-bs-toggle="tab" data-bs-target="#pane-comentarios" type="button" role="tab">
                                        <i class="bi bi-chat-dots"></i> Comentários <span class="badge bg-secondary ms-1" id="tab-comentarios-badge" style="font-size:0.6rem;">0</span>
                                    </button>
                                </li>
                                <!-- Aba Escopo: escopo técnico, previsão e suporte da demanda vinculada.
                                     Fica desabilitada quando o card não está ligado a uma demanda. -->
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link small py-2" id="tab-escopo" data-bs-toggle="tab" data-bs-target="#pane-escopo" type="button" role="tab">
                                        <i class="bi bi-file-earmark-text"></i> Escopo
                                    </button>
                                </li>
                            </ul>

                            <div class="tab-content p-3">
                                <!-- ABA DESCRIÇÃO -->
                                <div class="tab-pane fade show active" id="pane-descricao" role="tabpanel">
                                    <div id="quill-editor" style="min-height:300px;background:#fff;border-radius:0 0 6px 6px;"></div>
                                </div>

                                <!-- ABA DEMANDA VINCULADA -->
                                <div class="tab-pane fade" id="pane-demanda" role="tabpanel">
                                    <div id="ticket-link-section">
                                        <div class="alert alert-light border text-center py-4" id="no-ticket-msg">
                                            <i class="bi bi-ticket-detailed fs-3 text-muted"></i>
                                            <p class="mb-0 text-muted small mt-2">Este card não está vinculado a nenhuma demanda.</p>
                                        </div>
                                        <div id="ticket-data-section" style="display:none;">
                                            <!-- Info da demanda -->
                                            <div class="card mb-3">
                                                <div class="card-header py-2 px-3 bg-light d-flex justify-content-between align-items-center">
                                                    <span class="fw-medium small"><i class="bi bi-ticket-detailed"></i> Demanda <a href="#" id="ticket-link" target="_blank" class="text-decoration-none">#</a></span>
                                                    <span class="badge bg-info" id="ticket-status-badge"></span>
                                                </div>
                                                <div class="card-body p-2">
                                                    <p class="small mb-1"><strong>Título:</strong> <span id="ticket-title-text"></span></p>
                                                    <p class="small mb-1"><strong>Cliente:</strong> <span id="ticket-client-name"></span></p>
                                                    <p class="small mb-0"><strong>Criado em:</strong> <span id="ticket-created-at"></span></p>
                                                </div>
                                            </div>

                                            <!-- Anexos da demanda (prints, vídeos, arquivos do cliente) -->
                                            <div class="mb-3">
                                                <h6 class="fw-bold small mb-2"><i class="bi bi-images"></i> Anexos da Demanda</h6>
                                                <div id="ticket-attachments-grid" class="row g-2"></div>
                                                <p class="text-muted small" id="no-ticket-attachments" style="display:none;">Nenhum anexo na demanda.</p>
                                            </div>

                                            <!-- Mensagens/Conversação da demanda -->
                                            <div class="mb-3">
                                                <h6 class="fw-bold small mb-2"><i class="bi bi-chat-left-text"></i> Conversação da Demanda</h6>
                                                <div id="ticket-messages-list" style="max-height:300px;overflow-y:auto;"></div>
                                                <p class="text-muted small" id="no-ticket-messages" style="display:none;">Nenhuma mensagem na demanda.</p>
                                            </div>

                                            <!-- Notas internas -->
                                            <div class="mb-3">
                                                <h6 class="fw-bold small mb-2"><i class="bi bi-journal-text"></i> Observações Internas</h6>
                                                <div id="ticket-internal-notes-list" style="max-height:250px;overflow-y:auto;"></div>
                                                <p class="text-muted small" id="no-ticket-notes" style="display:none;">Nenhuma observação interna.</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- ABA ANEXOS DO CARD -->
                                <div class="tab-pane fade" id="pane-anexos" role="tabpanel">
                                    <div id="detail-attachments" class="mb-3"></div>
                                    <div class="d-flex gap-2 align-items-center">
                                        <input type="file" id="detail-file-input" class="form-control form-control-sm" style="max-width:300px;">
                                        <button class="btn btn-sm btn-outline-primary" onclick="uploadCardFile()"><i class="bi bi-upload"></i> Enviar</button>
                                    </div>
                                </div>

                                <!-- ABA TASKS -->
                                <div class="tab-pane fade" id="pane-tasks" role="tabpanel">
                                    <!-- Formulário criar task -->
                                    <div class="card mb-3 border-0 shadow-sm">
                                        <div class="card-body p-3">
                                            <div class="d-flex gap-2 align-items-start">
                                                <div class="flex-grow-1">
                                                    <input type="text" id="new-task-title" class="form-control form-control-sm mb-2" placeholder="Título da task...">
                                                    <textarea id="new-task-description" class="form-control form-control-sm" rows="2" placeholder="Descrição (opcional)..."></textarea>
                                                </div>
                                                <button class="btn btn-sm btn-primary" onclick="createTask()" style="white-space:nowrap;">
                                                    <i class="bi bi-plus-lg"></i> Criar
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Progress bar -->
                                    <div class="mb-3" id="tasks-progress-container" style="display:none;">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <small class="text-muted fw-medium">Progresso</small>
                                            <small class="text-muted" id="tasks-progress-text">0/0</small>
                                        </div>
                                        <div class="progress" style="height:6px;">
                                            <div class="progress-bar bg-success" id="tasks-progress-bar" style="width:0%"></div>
                                        </div>
                                    </div>

                                    <!-- Lista de tasks -->
                                    <div id="tasks-list"></div>
                                </div>

                                <!-- ABA COMENTÁRIOS -->
                                <div class="tab-pane fade" id="pane-comentarios" role="tabpanel">
                                    <div id="detail-comments" class="mb-3" style="max-height:400px;overflow-y:auto;"></div>
                                    <div class="d-flex gap-2 mt-2 align-items-end">
                                        <textarea id="comment-input" class="form-control form-control-sm" placeholder="Escreva um comentário..." rows="2" style="resize:vertical;" onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();addComment();}"></textarea>
                                        <button class="btn btn-sm btn-primary" onclick="addComment()" style="height:fit-content;"><i class="bi bi-send"></i></button>
                                    </div>
                                </div>

                                <!-- ABA ESCOPO (escopo técnico / previsão / suporte da demanda) -->
                                <div class="tab-pane fade" id="pane-escopo" role="tabpanel">
                                    <!-- Mensagem quando o card não tem demanda vinculada -->
                                    <div class="alert alert-light border text-center py-4" id="scope-no-ticket-msg" style="display:none;">
                                        <i class="bi bi-file-earmark-text fs-3 text-muted"></i>
                                        <p class="mb-0 text-muted small mt-2">Este card não está vinculado a nenhuma demanda. O escopo técnico, a previsão e o suporte só se aplicam a cards com demanda.</p>
                                    </div>

                                    <div id="scope-fields-section">
                                        <div class="alert alert-light border small mb-3">
                                            Demanda <strong id="scope-ticket-ref">#</strong>
                                        </div>

                                        <!-- Escopo técnico -->
                                        <div class="card mb-3">
                                            <div class="card-header bg-white py-2"><h6 class="mb-0" style="font-size:0.85rem"><i class="bi bi-file-earmark-text"></i> Escopo técnico</h6></div>
                                            <div class="card-body">
                                                <div class="mb-2">
                                                    <label class="form-label fw-medium small">O que será desenvolvido</label>
                                                    <textarea id="scope-incluido" class="form-control form-control-sm" rows="3"></textarea>
                                                </div>
                                                <div class="mb-2">
                                                    <label class="form-label fw-medium small">O que NÃO será desenvolvido</label>
                                                    <textarea id="scope-excluido" class="form-control form-control-sm" rows="3"></textarea>
                                                </div>
                                                <div class="mb-2">
                                                    <label class="form-label fw-medium small">Como será executado</label>
                                                    <textarea id="scope-execucao" class="form-control form-control-sm" rows="3"></textarea>
                                                </div>
                                                <div class="mb-2">
                                                    <label class="form-label fw-medium small">Estimativa (dias)</label>
                                                    <input type="number" id="scope-estimativa" class="form-control form-control-sm" min="0">
                                                </div>
                                                <div class="d-flex gap-2 flex-wrap">
                                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="saveScopeAjax(false)">Salvar escopo</button>
                                                    <button type="button" class="btn btn-primary btn-sm" onclick="saveScopeAjax(true)">Enviar ao cliente para aprovação</button>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Previsão de publicação -->
                                        <div class="card mb-3">
                                            <div class="card-header bg-white py-2"><h6 class="mb-0" style="font-size:0.85rem"><i class="bi bi-calendar-event"></i> Previsão de publicação</h6></div>
                                            <div class="card-body">
                                                <div class="d-flex gap-2 align-items-end flex-wrap">
                                                    <div class="flex-grow-1">
                                                        <label class="form-label fw-medium small">Data</label>
                                                        <input type="date" id="scope-previsao" class="form-control form-control-sm">
                                                    </div>
                                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="savePrevisaoAjax()">Salvar</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Painel direito: Propriedades -->
                        <div class="col-lg-4 bg-light" style="max-height:80vh;overflow-y:auto;">
                            <div class="p-3">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="fw-bold small text-muted mb-0 text-uppercase"><i class="bi bi-gear"></i> Propriedades</h6>
                                    <button class="btn btn-sm btn-primary" onclick="saveCard()"><i class="bi bi-check-lg"></i> Salvar</button>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-medium text-muted">Título</label>
                                    <textarea id="detail-title-input" class="form-control form-control-sm" rows="2" style="resize:vertical;"></textarea>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-medium text-muted">Atendente</label>
                                    <select id="detail-assigned" class="form-select form-select-sm">
                                        <option value="">Não atribuído</option>
                                        <?php foreach (($attendantsList ?? []) as $m): ?>
                                        <option value="<?= $m['id'] ?>"><?= escape($m['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-medium text-muted">Técnico</label>
                                    <select id="detail-technical" class="form-select form-select-sm">
                                        <option value="">Não atribuído</option>
                                        <?php foreach (($techniciansList ?? []) as $m): ?>
                                        <option value="<?= $m['id'] ?>"><?= escape($m['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-medium text-muted">Analista</label>
                                    <select id="detail-analyst" class="form-select form-select-sm">
                                        <option value="">Não atribuído</option>
                                        <?php foreach (($analystsList ?? []) as $m): ?>
                                        <option value="<?= $m['id'] ?>"><?= escape($m['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-medium text-muted">Empresa</label>
                                    <select id="detail-company" class="form-select form-select-sm">
                                        <option value="">Nenhuma</option>
                                        <?php foreach ($companies as $c): ?>
                                        <option value="<?= $c['id'] ?>"><?= escape($c['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <label class="form-label small fw-medium text-muted">Prioridade</label>
                                        <select id="detail-priority" class="form-select form-select-sm">
                                            <option value="low">Baixa</option>
                                            <option value="medium">Média</option>
                                            <option value="high">Alta</option>
                                            <option value="urgent">Urgente</option>
                                        </select>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-medium text-muted">Status</label>
                                        <select id="detail-status" class="form-select form-select-sm">
                                            <?php foreach ($statusLabels as $s => $info): ?>
                                            <option value="<?= $s ?>"><?= $info[0] ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-medium text-muted">Prazo Entrega</label>
                                    <input type="datetime-local" id="detail-due-date" class="form-control form-control-sm">
                                </div>

                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <label class="form-label small fw-medium text-muted">Início Dev</label>
                                        <input type="datetime-local" id="detail-start-date" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-medium text-muted">Fim Dev</label>
                                        <input type="datetime-local" id="detail-end-date" class="form-control form-control-sm">
                                    </div>
                                </div>

                                <!-- Atividade predecessora (autocomplete) -->
                                <div class="row g-2 mb-3">
                                    <div class="col-8">
                                        <label class="form-label small fw-medium text-muted"><i class="bi bi-diagram-3"></i> Atividade predecessora</label>
                                        <div class="pred-autocomplete" data-pred-root>
                                            <input type="hidden" id="detail-predecessor" class="pred-input-id" value="">
                                            <input type="text" id="detail-predecessor-search" class="form-control form-control-sm pred-input-search" autocomplete="off"
                                                   placeholder="Buscar card por nº, título ou empresa...">
                                            <div class="pred-results" style="display:none;"></div>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <label class="form-label small fw-medium text-muted">Folga (dias)</label>
                                        <input type="number" id="detail-predecessor-lag" class="form-control form-control-sm" min="0" value="0">
                                    </div>
                                </div>
                                <div class="mb-3" id="detail-predecessor-hint" style="display:none;">
                                    <small class="text-muted" style="font-size:0.68rem;"><i class="bi bi-info-circle"></i> Ao mover o prazo/fim desta atividade, as dependentes são reagendadas automaticamente (respeitando o prazo de cada uma).</small>
                                </div>

                                <hr class="my-2">
                                <h6 class="fw-bold small text-muted mb-2 text-uppercase"><i class="bi bi-calendar-range"></i> Cronograma do Cliente</h6>
                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <label class="form-label small fw-medium text-muted">Início (cliente)</label>
                                        <input type="date" id="detail-client-start-date" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-medium text-muted">Fim (cliente)</label>
                                        <input type="date" id="detail-client-end-date" class="form-control form-control-sm">
                                    </div>
                                </div>

                                <hr class="my-2">
                                <h6 class="fw-bold small text-muted mb-2 text-uppercase"><i class="bi bi-git"></i> Referência CX Hub</h6>
                                <div class="row g-2 mb-2">
                                    <div class="col-5">
                                        <label class="form-label small fw-medium text-muted">Nº Demanda CX</label>
                                        <input type="text" id="detail-cx-hub-number" class="form-control form-control-sm" placeholder="Ex: 1234">
                                    </div>
                                    <div class="col-7">
                                        <label class="form-label small fw-medium text-muted">Nome Demanda CX</label>
                                        <input type="text" id="detail-cx-hub-name" class="form-control form-control-sm" placeholder="Título no CX Hub">
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small fw-medium text-muted">Branch</label>
                                    <input type="text" id="detail-branch-name" class="form-control form-control-sm" placeholder="Ex: feature/1234-nome-da-branch">
                                </div>
                                <!-- Segunda branch: escondida até o usuário acionar "+ segunda branch" -->
                                <div class="mb-2" id="detail-branch-2-wrapper" style="display:none;">
                                    <label class="form-label small fw-medium text-muted">Branch</label>
                                    <input type="text" id="detail-branch-name-2" class="form-control form-control-sm" placeholder="Ex: feature/1234-nome-da-branch">
                                </div>
                                <div class="mb-2" id="detail-branch-2-toggle-wrapper">
                                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" onclick="showSecondBranch()">
                                        <i class="bi bi-plus-lg"></i> segunda branch
                                    </button>
                                </div>
                                <div class="row g-2 mb-2 align-items-end">
                                    <div class="col">
                                        <label class="form-label small fw-medium text-muted">Nº do PR</label>
                                        <input type="text" id="detail-pr-number" class="form-control form-control-sm" placeholder="Ex: 87">
                                    </div>
                                    <div class="col-auto">
                                        <button class="btn btn-sm btn-success" onclick="prDone()" title="Salvar PR e enviar para Revisão Interna">
                                            <i class="bi bi-check2-circle"></i> PR Feito
                                        </button>
                                    </div>
                                </div>

                                <hr class="my-2">
                                <div class="mb-3">
                                    <label class="form-label small fw-medium text-muted"><i class="bi bi-link-45deg"></i> Link do Card</label>
                                    <div class="input-group input-group-sm">
                                        <input type="text" id="detail-card-link" class="form-control form-control-sm" readonly style="font-size:0.72rem;background:#fff;">
                                        <button class="btn btn-outline-secondary" type="button" onclick="copyCardLink()" title="Copiar link"><i class="bi bi-clipboard" id="detail-card-link-icon"></i></button>
                                        <a class="btn btn-outline-secondary" id="detail-card-link-open" href="#" target="_blank" title="Abrir link"><i class="bi bi-box-arrow-up-right"></i></a>
                                    </div>
                                    <small class="text-muted" style="font-size:0.68rem;">Compartilhe para abrir este card diretamente.</small>
                                </div>

                                <hr class="my-2">
                                <small class="text-muted d-block mb-2" id="detail-meta" style="font-size:0.72rem;line-height:1.4;"></small>

                                <hr class="my-2">
                                <div class="d-flex gap-2 flex-wrap">
                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteCard()"><i class="bi bi-trash"></i> Excluir</button>
                                    <?php if (($user['role'] ?? '') === 'super_admin'): ?>
                                    <button class="btn btn-sm btn-danger" onclick="deleteCardPermanent()"><i class="bi bi-trash3-fill"></i> Excluir Permanente</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div><!-- end main-content -->

<!-- Quill Editor -->
<link href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>
<!-- SortableJS -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>

<style>
.planning-card { cursor: pointer; transition: box-shadow 0.2s; }
.planning-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
.planning-card.overdue { border: 1.5px solid #dc3545 !important; box-shadow: 0 0 0 1px rgba(220,53,69,0.15); }
.kanban-ghost { opacity: 0.4; background: var(--primary-50) !important; border: 2px dashed var(--primary) !important; }
.kanban-drag { box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important; transform: rotate(1deg); }
/* Botão "Carregar mais" por coluna (paginação do Kanban) */
.kanban-loadmore { margin-top: 8px; }
.kanban-loadmore .btn { font-size: 0.72rem; }
.kanban-loadmore-count { opacity: 0.7; font-size: 0.68rem; }
/* Autocomplete de atividade predecessora */
.pred-autocomplete { position: relative; }
.pred-results { position: absolute; top: 100%; left: 0; right: 0; z-index: 1080; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 6px 20px rgba(0,0,0,0.12); max-height: 240px; overflow-y: auto; margin-top: 2px; }
.pred-result-item { display: block; width: 100%; text-align: left; border: none; background: none; padding: 7px 10px; cursor: pointer; border-bottom: 1px solid #f1f1f4; }
.pred-result-item:last-child { border-bottom: none; }
.pred-result-item:hover { background: #f0faf8; }
.pred-result-title { display: block; font-size: 0.78rem; font-weight: 600; color: #333; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pred-result-company { display: block; font-size: 0.68rem; color: #888; }
.pred-result-empty { padding: 8px 10px; font-size: 0.74rem; color: #999; }

/* Modal card detail styles */
#cardDetailModal .modal-content { border-radius: 12px; overflow: hidden; }
#cardDetailModal .modal-header { background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); }
#cardDetailModal .nav-tabs .nav-link { font-size: 0.78rem; color: #6c757d; border: none; border-bottom: 2px solid transparent; }
#cardDetailModal .nav-tabs .nav-link.active { color: var(--primary, #00BFA6); border-bottom-color: var(--primary, #00BFA6); background: transparent; font-weight: 600; }
#cardDetailModal .nav-tabs .nav-link:hover:not(.active) { color: #333; border-bottom-color: #dee2e6; }
.ticket-msg-bubble { padding: 8px 12px; border-radius: 10px; font-size: 0.8rem; max-width: 85%; word-wrap: break-word; }
.ticket-msg-client { background: #e3f2fd; margin-right: auto; border-bottom-left-radius: 2px; }
.ticket-msg-internal { background: #fff3e0; margin-left: auto; border-bottom-right-radius: 2px; }
.ticket-msg-attendant { background: #e8f5e9; margin-left: auto; border-bottom-right-radius: 2px; }
.ticket-attachment-thumb { width: 100%; aspect-ratio: 16/10; object-fit: cover; border-radius: 8px; cursor: pointer; transition: transform 0.2s; }
.ticket-attachment-thumb:hover { transform: scale(1.03); }
.ticket-attachment-video { width: 100%; border-radius: 8px; max-height: 200px; }
.internal-note-card { background: #fffde7; border: 1px solid #fff9c4; border-radius: 8px; padding: 10px 12px; margin-bottom: 8px; }
.internal-note-card .note-meta { font-size: 0.7rem; color: #666; }
.internal-note-card .note-text { font-size: 0.8rem; margin-top: 4px; }

/* Tasks styles */
.task-item { background: #fff; border: 1px solid #e9ecef; border-radius: 10px; padding: 12px 14px; margin-bottom: 10px; transition: all 0.2s; }
.task-item:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.08); border-color: #dee2e6; }
.task-item.task-completed { background: #f8fdf8; border-color: #c8e6c9; }
.task-item.task-completed .task-title { text-decoration: line-through; color: #888; }
.task-title { font-size: 0.85rem; font-weight: 600; margin: 0; }
.task-description { font-size: 0.78rem; color: #555; margin-top: 4px; white-space: pre-wrap; }
.task-meta { font-size: 0.68rem; color: #999; margin-top: 6px; }
.task-images-grid { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
.task-image-thumb { width: 80px; height: 60px; object-fit: cover; border-radius: 6px; cursor: pointer; border: 1px solid #e9ecef; transition: transform 0.2s; }
.task-image-thumb:hover { transform: scale(1.08); box-shadow: 0 2px 8px rgba(0,0,0,0.15); }
.task-checkbox { width: 18px; height: 18px; cursor: pointer; accent-color: #2e7d32; }
.task-upload-zone { border: 2px dashed #dee2e6; border-radius: 8px; padding: 8px; text-align: center; cursor: pointer; transition: all 0.2s; }
.task-upload-zone:hover { border-color: var(--primary, #00BFA6); background: #f0fdf4; }

#calendar-container table { width: 100%; border-collapse: collapse; }
#calendar-container th, #calendar-container td { border: 1px solid #e9ecef; padding: 4px; vertical-align: top; font-size: 0.8rem; }
#calendar-container th { background: #f8f9fa; text-align: center; font-weight: 600; }
#calendar-container td { min-height: 120px; height: 120px; }
/* Notion-style month calendar grid */
.cal-month-grid { width: 100%; }
.cal-month-header { display: grid; grid-template-columns: repeat(7, 1fr); border-bottom: 2px solid #e2e8f0; }
.cal-month-header-cell { text-align: center; font-weight: 600; font-size: 0.78rem; padding: 8px 4px; color: #555; background: #f8f9fa; border-right: 1px solid #e9ecef; }
.cal-month-header-cell:last-child { border-right: none; }
.cal-month-week { border-bottom: 1px solid #e9ecef; }
.cal-month-days-row { display: grid; grid-template-columns: repeat(7, 1fr); border-bottom: 1px solid #f0f0f0; }
.cal-month-day-num { padding: 6px 8px 4px; font-size: 0.78rem; font-weight: 600; color: #555; border-right: 1px solid #f0f0f0; min-height: 28px; display: flex; align-items: center; justify-content: space-between; gap: 4px; }
.cal-month-day-num:last-child { border-right: none; }
.cal-month-day-num.other-month { opacity: 0.3; }
.cal-month-day-num.today .day-number { background: var(--primary, #00BFA6); color: #fff; border-radius: 50%; width: 24px; height: 24px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.75rem; }
/* Botão "+" para criar card com prazo no dia (aparece no hover do dia) */
.cal-day-add { border: none; background: transparent; color: var(--primary, #00BFA6); cursor: pointer; padding: 0; width: 20px; height: 20px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 0.72rem; line-height: 1; opacity: 0; transition: opacity 0.12s, background 0.12s; flex-shrink: 0; }
.cal-month-days-row .cal-month-day-num:hover .cal-day-add { opacity: 1; }
.cal-day-add:hover { background: var(--primary, #00BFA6); color: #fff; }
/* Em telas touch não há hover: deixa o "+" sempre visível, discreto */
@media (hover: none) {
    .cal-day-add { opacity: 0.5; }
}
.cal-month-events { position: relative; min-height: 30px; padding: 2px 0; overflow: visible; }
/* Barra de evento com extensão multi-dia (estilo Google Agenda) */
.cal-span-event { position: absolute; height: 22px; display: flex; align-items: center; gap: 5px; padding: 2px 8px; cursor: pointer; overflow: hidden; white-space: nowrap; transition: filter 0.15s, box-shadow 0.15s; z-index: 1; }
.cal-span-event:hover { filter: brightness(0.96); box-shadow: 0 2px 8px rgba(0,0,0,0.12); z-index: 10; }
.cal-span-title { font-size: 0.7rem; font-weight: 600; overflow: hidden; text-overflow: ellipsis; }
.cal-span-info { font-size: 0.6rem; opacity: 0.85; margin-left: 4px; overflow: hidden; text-overflow: ellipsis; flex-shrink: 1; }
.cal-span-info i { font-size: 0.55rem; }
.cal-span-badges { display: flex; gap: 2px; margin-left: auto; flex-shrink: 0; }
/* Tag discreta (prioridade) que herda a cor do bloco */
.cal-span-tag { font-size: 0.55rem; padding: 1px 6px; border-radius: 10px; font-weight: 600; white-space: nowrap; background: rgba(255,255,255,0.55); }
/* Evento na visão semana/dia */
.cal-time-event { position: relative; font-size: 0.7rem; padding: 3px 7px; border-radius: 4px; cursor: pointer; margin-bottom: 2px; display: flex; align-items: center; gap: 5px; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
.cal-time-event:hover { filter: brightness(0.96); }
.cal-time-title { font-weight: 600; overflow: hidden; text-overflow: ellipsis; }
/* Week/Day view */
.cal-time-slot { height: 50px; border-bottom: 1px solid #eee; position: relative; }
.cal-time-label { font-size: 0.7rem; color: #999; width: 50px; text-align: right; padding-right: 8px; }
/* Legenda de cores */
.cal-legend { display: flex; flex-wrap: wrap; gap: 6px 16px; align-items: center; padding: 8px 4px 12px; border-bottom: 1px solid #eef0f2; margin-bottom: 8px; }
.cal-legend-item { display: inline-flex; align-items: center; gap: 6px; font-size: 0.72rem; color: #556; }
.cal-legend-swatch { width: 14px; height: 14px; border-radius: 3px; border-left-width: 3px; border-left-style: solid; flex-shrink: 0; }
/* Paleta compartilhada por prazo/status (blocos, tempo e legenda) */
.cat-overdue     { --cat-bg:#fde8e8; --cat-border:#dc2626; }
.cat-done        { --cat-bg:rgba(129,199,132,0.5); --cat-border:rgba(76,175,80,0.6); }
.cat-on_track    { --cat-bg:#e7effd; --cat-border:#2563eb; }
.cat-near_due    { --cat-bg:#fef7d6; --cat-border:#f59e0b; }
.cat-due_today   { --cat-bg:#fde8e8; --cat-border:#dc2626; }
.cat-in_progress { --cat-bg:#ede9fe; --cat-border:#7c3aed; }
.cat-revisao     { --cat-bg:#ffedd5; --cat-border:#ea580c; }
.cat-waiting     { --cat-bg:#f3e5f5; --cat-border:#7b1fa2; }
.cat-homolog     { --cat-bg:#d7f3f7; --cat-border:#0097a7; }
.cat-denied      { --cat-bg:#f1f1f3; --cat-border:#9ca3af; }
.cat-none        { --cat-bg:#eef1f4; --cat-border:#94a3b8; }
.cal-legend-swatch.cat-overdue,
.cal-legend-swatch.cat-done,
.cal-legend-swatch.cat-on_track,
.cal-legend-swatch.cat-near_due,
.cal-legend-swatch.cat-due_today,
.cal-legend-swatch.cat-in_progress,
.cal-legend-swatch.cat-revisao,
.cal-legend-swatch.cat-waiting,
.cal-legend-swatch.cat-homolog,
.cal-legend-swatch.cat-denied { background: var(--cat-bg); border-left-color: var(--cat-border); }
/* Aviso de demandas sem data (acima do calendário) */
.cal-undated-banner { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; background: #fff8e6; border: 1px solid #fde68a; border-radius: 8px; padding: 8px 12px; margin-bottom: 10px; }
.cal-undated-msg { display: flex; align-items: center; gap: 8px; font-size: 0.82rem; color: #7a5900; flex-wrap: wrap; }
.cal-undated-msg > span:first-of-type { white-space: nowrap; }
.cal-undated-msg .bi { color: #f59e0b; }
.cal-undated-sub { color: #9a7b2e; font-size: 0.74rem; }
/* Itens do modal de revisão */
.undated-item { display: flex; align-items: center; gap: 10px; width: 100%; text-align: left; border: 1px solid #eef0f2; background: #fff; border-radius: 8px; padding: 8px 12px; margin-bottom: 6px; cursor: pointer; transition: background 0.15s, border-color 0.15s; }
.undated-item:hover { background: #f0faf8; border-color: var(--primary, #00BFA6); }
.undated-item-main { display: flex; flex-direction: column; flex: 1; min-width: 0; }
.undated-item-title { font-size: 0.82rem; font-weight: 600; color: #333; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.undated-item-meta { font-size: 0.7rem; color: #888; display: flex; gap: 10px; flex-wrap: wrap; }
.undated-item-pr { font-size: 0.68rem; font-weight: 600; white-space: nowrap; }
@media (max-width: 768px) {
    .cal-month-day-num { padding: 4px 4px 2px; font-size: 0.7rem; }
    .cal-span-event { height: 18px; padding: 1px 4px; }
    .cal-span-title { font-size: 0.6rem; }
    .cal-span-info { display: none; }
    .cal-span-badges { display: none; }
    .cal-time-label { width: 35px; font-size: 0.6rem; }
    .cal-legend-item { font-size: 0.66rem; }
}
</style>

<script>
const BASE = '<?= baseUrl("") ?>';
let currentCardId = null;
// Demanda (ticket) vinculada ao card aberto. Alimenta o pop-up de Escopo/Previsão/Suporte.
let currentTicket = null;
let quill = null;
let calendarEvents = [];
let calDate = new Date();
let calMode = 'month';

// Filtros EFETIVOS aplicados pelo servidor (inclui o pré-filtro implícito do
// usuário, ex.: "meus cards", que pode não estar na query string). O calendário
// usa estes filtros para mostrar exatamente o mesmo recorte do Kanban.
const EFFECTIVE_FILTERS = <?= json_encode([
    'company_id'  => array_values(array_map('strval', (array)($filters['company_id'] ?? []))),
    'assigned_to' => array_values(array_map('strval', (array)($filters['assigned_to'] ?? []))),
    'created_by'  => array_values(array_map('strval', (array)($filters['created_by'] ?? []))),
    'statuses'    => array_values((array)($filters['statuses'] ?? [])),
    'order'       => $filters['order'] ?? '',
    'q'           => $filters['q'] ?? '',
], JSON_UNESCAPED_UNICODE) ?>;

// Visão com que a página deve abrir (preserva a aba ao aplicar filtro).
const ACTIVE_VIEW = '<?= escape($activeView ?? 'kanban') ?>';

// Monta os params de filtro do calendário a partir dos filtros EFETIVOS
// (não da query string), para refletir o mesmo recorte do Kanban.
function calendarFilterParams() {
    const p = new URLSearchParams();
    p.set('show_all', '1');
    (EFFECTIVE_FILTERS.company_id  || []).forEach(v => p.append('company_id[]', v));
    (EFFECTIVE_FILTERS.assigned_to || []).forEach(v => p.append('assigned_to[]', v));
    (EFFECTIVE_FILTERS.created_by  || []).forEach(v => p.append('created_by[]', v));
    (EFFECTIVE_FILTERS.statuses    || []).forEach(v => p.append('statuses[]', v));
    if (EFFECTIVE_FILTERS.order) p.set('order', EFFECTIVE_FILTERS.order);
    if (EFFECTIVE_FILTERS.q)     p.set('q', EFFECTIVE_FILTERS.q);
    return p;
}

const priorityColors = {low:'#6b7280',medium:'#f59e0b',high:'#ef4444',urgent:'#dc2626'};
const statusColors = {open:'#1565c0',in_progress:'#e65100',em_revisao_interna:'#5c6bc0',waiting_client:'#7b1fa2',em_homologacao:'#0097a7',aprovado_producao:'#8bc34a',completed:'#2e7d32',denied:'#d84315',archived:'#546e7a'};

// === FILTER FORM ===
// show_all fica sempre em 1: sem responsáveis marcados = ver todos os cards
// (o pré-filtro pelo usuário logado só ocorre no primeiro acesso, sem parâmetros).

// === VIEW TOGGLE ===
// Aplica uma visão (kanban|calendar): alterna os painéis, marca o botão ativo,
// guarda a escolha no hidden do form (para o filtro preservar a aba) e reflete
// na URL sem recarregar (para F5/compartilhar manter a visão).
function applyView(view, pushUrl) {
    view = (view === 'calendar') ? 'calendar' : 'kanban';
    document.querySelectorAll('#view-toggle button').forEach(b => {
        b.classList.toggle('active', b.dataset.view === view);
    });
    const kv = document.getElementById('kanban-view');
    const cv = document.getElementById('calendar-view');
    if (kv) kv.style.display = view === 'kanban' ? '' : 'none';
    if (cv) cv.style.display = view === 'calendar' ? '' : 'none';
    const viewInput = document.getElementById('view_input');
    if (viewInput) viewInput.value = view;
    if (pushUrl) {
        const u = new URL(window.location.href);
        u.searchParams.set('view', view);
        history.replaceState(null, '', u.toString());
    }
    if (view === 'calendar') loadCalendar();
}

document.querySelectorAll('#view-toggle button').forEach(btn => {
    btn.addEventListener('click', function() {
        applyView(this.dataset.view, true);
    });
});

// Abre a página na visão que veio do servidor (preserva a aba ao filtrar).
if (ACTIVE_VIEW === 'calendar') {
    applyView('calendar', false);
}

// === BARRA DE ROLAGEM HORIZONTAL FIXA (sincronizada com o Kanban) ===
(function() {
    const scroll = document.querySelector('#kanban-view .kanban-scroll');
    const track = document.getElementById('kanban-track');
    const topBar = document.getElementById('kanban-topscroll');
    const topInner = document.getElementById('kanban-topscroll-inner');
    if (!scroll || !track || !topBar || !topInner) return;

    let syncing = false;

    // Ajusta a largura do "fantasma" da barra superior à largura real do Kanban
    // e mostra/esconde a barra conforme houver transbordamento horizontal.
    function refresh() {
        const fullWidth = track.scrollWidth;         // largura total das colunas
        const visible = scroll.clientWidth;           // largura visível
        topInner.style.width = fullWidth + 'px';
        const overflowing = fullWidth > visible + 1;
        topBar.style.display = overflowing ? 'block' : 'none';
        if (overflowing) topBar.scrollLeft = scroll.scrollLeft; // mantém alinhado
    }

    // Sincronização bidirecional (evita loop com a flag "syncing")
    topBar.addEventListener('scroll', function() {
        if (syncing) { syncing = false; return; }
        syncing = true;
        scroll.scrollLeft = topBar.scrollLeft;
    });
    scroll.addEventListener('scroll', function() {
        if (syncing) { syncing = false; return; }
        syncing = true;
        topBar.scrollLeft = scroll.scrollLeft;
    });

    window.addEventListener('resize', refresh);

    // Recalcula ao alternar de volta para a visão Kanban (larguras medem 0 quando oculto)
    document.querySelectorAll('#view-toggle button').forEach(btn => {
        btn.addEventListener('click', function() {
            if (this.dataset.view === 'kanban') setTimeout(refresh, 50);
        });
    });

    // Expõe globalmente para recomputar após adicionar/remover cards dinamicamente
    window.refreshKanbanTopScroll = refresh;

    document.addEventListener('DOMContentLoaded', refresh);
    // Estado inicial (caso o DOM já esteja pronto)
    refresh();
})();

// === KANBAN DRAG & DROP ===
document.querySelectorAll('#kanban-view .kanban-list').forEach(list => {
    new Sortable(list, {
        group: 'planning',
        animation: 200,
        ghostClass: 'kanban-ghost',
        dragClass: 'kanban-drag',
        filter: 'a',
        onEnd: function(evt) {
            const cardId = evt.item.dataset.id;
            const newStatus = evt.to.dataset.status;
            const oldStatus = evt.from.dataset.status;

            // Se mudou de coluna, atualizar status primeiro (dispara notificações de ticket)
            if (newStatus !== oldStatus) {
                const formData = new FormData();
                formData.append('status', newStatus);
                formData.append('position', evt.newIndex);
                fetch(BASE + 'planning/updateStatus/' + cardId, { method: 'POST', body: formData, headers: {'X-Requested-With':'XMLHttpRequest'} })
                    .then(r => r.json())
                    .then(data => {
                        if (!data.success) { evt.from.appendChild(evt.item); updateKanbanCounts(); return; }
                        // Persistir ordem completa da coluna destino
                        reorderColumn(evt.to);
                        updateKanbanCounts();
                    })
                    .catch(() => { evt.from.appendChild(evt.item); updateKanbanCounts(); });
            } else {
                // Apenas reordenamento dentro da mesma coluna
                reorderColumn(evt.to);
            }
        }
    });
});

// Persiste a ordem completa de uma coluna no backend
function reorderColumn(listEl) {
    const status = listEl.dataset.status;
    const ids = [...listEl.querySelectorAll('.planning-card')].map(el => el.dataset.id);
    if (!ids.length) return;

    const fd = new FormData();
    fd.append('status', status);
    fd.append('card_ids', ids.join(','));
    fetch(BASE + 'planning/reorder', { method: 'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json())
        .then(data => { if (!data.success) console.warn('Erro ao salvar ordem:', data.error); });
}
function updateKanbanCounts() {
    document.querySelectorAll('.kanban-column').forEach(col => {
        const list = col.querySelector('.kanban-list');
        const badge = col.querySelector('.badge');
        if (!list || !badge) return;
        // O badge mostra o TOTAL da coluna (data-total), não só os cards já
        // carregados — senão numa coluna paginada ficaria "50" em vez de "297".
        const loaded = list.querySelectorAll('.planning-card').length;
        const total = parseInt(list.dataset.total || '0', 10);
        badge.textContent = total > loaded ? total : loaded;
        // Mantém o data-shown coerente com o que está no DOM (drag/drop, criação).
        list.dataset.shown = String(loaded);
    });
}

// === MODALS ===
// Formata uma data LOCAL como YYYY-MM-DD (sem o deslocamento de fuso que o
// toISOString causaria perto da meia-noite).
function calYmd(d) {
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
}

// Reseta o autocomplete de predecessor do form de criar (sem resíduo do uso
// anterior). O form de criação não exclui nenhum card (card ainda não existe).
function resetCreatePredecessor() {
    const form = document.getElementById('createCardForm');
    if (!form) return;
    const root = form.querySelector('[data-pred-root]');
    if (!root) return;
    const idEl = root.querySelector('.pred-input-id');
    const searchEl = root.querySelector('.pred-input-search');
    const resEl = root.querySelector('.pred-results');
    if (idEl) idEl.value = '';
    if (searchEl) searchEl.value = '';
    if (resEl) { resEl.style.display = 'none'; resEl.innerHTML = ''; }
    delete root.dataset.excludeId;
}

function openCreateModal() {
    resetCreatePredecessor();
    // Garante que o prazo não fica preso de uma abertura anterior pelo atalho do dia.
    const dueEl = document.querySelector('#createCardForm [name="due_date"]');
    if (dueEl) dueEl.value = '';
    new bootstrap.Modal(document.getElementById('createCardModal')).show();
}

// Atalho do calendário: abre o modal de criar card já com o PRAZO DE ENTREGA
// (due_date) preenchido no dia clicado. Hora padrão 18:00 (fim do dia útil).
function openCreateForDate(ymd) {
    resetCreatePredecessor();
    const dueEl = document.querySelector('#createCardForm [name="due_date"]');
    if (dueEl) dueEl.value = ymd + 'T18:00';
    new bootstrap.Modal(document.getElementById('createCardModal')).show();
}

// Cria o card via AJAX para NÃO recarregar a página — assim os filtros
// aplicados na aba de Planejamento permanecem exatamente como estavam.
(function () {
    const form = document.getElementById('createCardForm');
    if (!form) return;

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        const submitBtn = form.querySelector('button[type="submit"]');
        const originalHtml = submitBtn ? submitBtn.innerHTML : '';
        if (submitBtn) { submitBtn.disabled = true; submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Criando...'; }

        fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success || !data.card) {
                alert(data.error || 'Erro ao criar o card.');
                return;
            }
            // Reflete o novo card na visão ATIVA, sem F5:
            // - Calendário: recarrega os eventos (o card novo aparece se tiver data).
            // - Kanban: insere o card na coluna correspondente.
            const calView = document.getElementById('calendar-view');
            const isCalendar = calView && calView.style.display !== 'none';
            if (isCalendar) {
                loadCalendar();
            } else {
                addCardToBoard(data.card);
            }
            // Fecha o modal e limpa o formulário para o próximo card.
            const modalEl = document.getElementById('createCardModal');
            const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
            modal.hide();
            form.reset();
            // O form.reset() não limpa o hidden do predecessor nem o due_date do
            // atalho do dia — garante estado limpo para a próxima criação.
            resetCreatePredecessor();
        })
        .catch(() => {
            // Fallback: se o AJAX falhar, faz o envio tradicional (recarrega a página).
            form.submit();
        })
        .finally(() => {
            if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = originalHtml; }
        });
    });
})();

// Verifica se um card está em atraso (prazo no passado e não concluído/arquivado).
function cardIsOverdue(card) {
    return !!card.due_date
        && (new Date(String(card.due_date).replace(' ', 'T')).getTime() < Date.now())
        && card.status !== 'completed' && card.status !== 'archived';
}

// Carrega o próximo lote de cards de uma coluna do Kanban (paginação por coluna).
// Os cards vêm do backend (columnCards) e são anexados ao FIM da coluna, sem
// recarregar a página. Mantém o drag & drop (os cards novos entram na mesma
// lista Sortable). Atualiza o offset (data-shown) e o contador do botão.
const KANBAN_PAGE_SIZE = <?= (int) ($kanbanPageSize ?? 50) ?>;
function loadMoreColumn(status, btn) {
    const list = document.querySelector('.kanban-list[data-status="' + status + '"]');
    if (!list) return;
    const total = parseInt(list.dataset.total || '0', 10);
    const shown = parseInt(list.dataset.shown || '0', 10);
    if (shown >= total) { const w = btn.closest('.kanban-loadmore'); if (w) w.remove(); return; }

    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm" style="width:0.8rem;height:0.8rem;"></span> Carregando...';

    // Usa os filtros EFETIVOS (mesmo recorte do Kanban, incluindo pré-filtro
    // implícito do usuário), não só a query string.
    const params = calendarFilterParams();
    params.set('status', status);
    params.set('offset', String(shown));

    fetch(BASE + 'planning/columnCards?' + params.toString(), { headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json())
        .then(data => {
            const cards = (data && data.cards) ? data.cards : [];
            cards.forEach(card => {
                const div = document.createElement('div');
                div.className = 'kanban-card planning-card' + (cardIsOverdue(card) ? ' overdue' : '');
                div.dataset.id = card.id;
                div.setAttribute('onclick', 'openCardModal(' + card.id + ')');
                div.innerHTML = buildCardInner(card);
                list.appendChild(div);
            });
            const newShown = shown + cards.length;
            list.dataset.shown = String(newShown);
            updateKanbanCounts();
            if (typeof refreshKanbanTopScroll === 'function') refreshKanbanTopScroll();

            const wrapper = btn.closest('.kanban-loadmore');
            const remaining = total - newShown;
            if (remaining > 0 && cards.length > 0) {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-chevron-down"></i> Carregar mais <span class="kanban-loadmore-count">(' + remaining + ' restantes)</span>';
            } else if (wrapper) {
                wrapper.remove();
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = original;
            if (typeof showToast === 'function') showToast('Falha ao carregar mais cards.');
        });
}

// ====== Autocomplete de "atividade predecessora" ======
// Cada bloco [data-pred-root] tem: hidden (.pred-input-id), input de texto
// (.pred-input-search) e um container de resultados (.pred-results). Digitar
// busca cards via planning/searchCards (debounce) e clicar seleciona (preenche
// o hidden com o id e o texto com "#id — título"). Limpar o texto zera o id.
function initPredecessorAutocomplete(root) {
    if (!root || root.dataset.predInit === '1') return;
    root.dataset.predInit = '1';
    const idInput = root.querySelector('.pred-input-id');
    const search  = root.querySelector('.pred-input-search');
    const results = root.querySelector('.pred-results');
    if (!idInput || !search || !results) return;

    let timer = null;
    let lastQuery = '';

    function close() { results.style.display = 'none'; results.innerHTML = ''; }
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }

    function render(cards) {
        if (!cards.length) {
            results.innerHTML = '<div class="pred-result-empty">Nenhum card encontrado</div>';
            results.style.display = '';
            return;
        }
        results.innerHTML = cards.map(c =>
            '<button type="button" class="pred-result-item" data-id="' + c.id + '" data-label="#' + c.id + ' — ' + esc(c.title) + '">' +
                '<span class="pred-result-title">#' + c.id + ' — ' + esc(c.title) + '</span>' +
                (c.company_name ? '<span class="pred-result-company"><i class="bi bi-building"></i> ' + esc(c.company_name) + '</span>' : '') +
            '</button>'
        ).join('');
        results.style.display = '';
        results.querySelectorAll('.pred-result-item').forEach(btn => {
            btn.addEventListener('click', function() {
                idInput.value = this.dataset.id;
                search.value = this.dataset.label;
                close();
            });
        });
    }

    function doSearch() {
        const q = search.value.trim();
        lastQuery = q;
        const exclude = root.dataset.excludeId || '';
        const params = new URLSearchParams();
        params.set('q', q);
        if (exclude) params.set('exclude', exclude);
        fetch(BASE + 'planning/searchCards?' + params.toString(), { headers: {'X-Requested-With':'XMLHttpRequest'} })
            .then(r => r.json())
            .then(data => { if (search.value.trim() === lastQuery) render((data && data.cards) || []); })
            .catch(() => close());
    }

    search.addEventListener('input', function() {
        // Digitar invalida a seleção anterior até escolher um item da lista.
        idInput.value = '';
        clearTimeout(timer);
        const q = search.value.trim();
        if (q === '') { close(); return; }
        timer = setTimeout(doSearch, 250);
    });
    search.addEventListener('focus', function() {
        if (search.value.trim() !== '' && results.innerHTML !== '') results.style.display = '';
        else if (search.value.trim() === '') { doSearch(); } // mostra os mais recentes
    });
    // Fecha ao clicar fora.
    document.addEventListener('click', function(e) {
        if (!root.contains(e.target)) close();
    });
}

// Inicializa todos os autocompletes de predecessor presentes na página.
document.querySelectorAll('[data-pred-root]').forEach(initPredecessorAutocomplete);

// Monta o HTML INTERNO de um card do Kanban a partir do JSON do backend.
// Espelha exatamente o markup renderizado em PHP no board (inclui tags de
// categoria e de marco de aprovação), para que criar/atualizar via AJAX gere
// o mesmo resultado de um reload da página.
function buildCardInner(card) {
    const priorityLabelsMap = {low:'Baixa',medium:'Média',high:'Alta',urgent:'Urgente'};
    const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));

    const isOverdue = cardIsOverdue(card);

    let dueHtml = '';
    if (card.due_date) {
        const d = new Date(String(card.due_date).replace(' ', 'T'));
        const dd = String(d.getDate()).padStart(2,'0');
        const mm = String(d.getMonth()+1).padStart(2,'0');
        const hh = String(d.getHours()).padStart(2,'0');
        const mi = String(d.getMinutes()).padStart(2,'0');
        dueHtml = '<span class="float-end ' + (isOverdue ? 'text-danger fw-semibold' : '') + '"><i class="bi bi-clock"></i> ' + dd + '/' + mm + ' ' + hh + ':' + mi + '</span>';
    }

    // Tag de MARCO de aprovação (uma só, da mais avançada para a menos) —
    // mesma precedência do PHP: aprovado_producao > recusado > aprovado.
    let milestoneHtml = '';
    if ((card.status || '') === 'aprovado_producao') {
        milestoneHtml = '<span class="badge bg-success" style="font-size:0.62rem;" title="Aprovado para produção">Aprov. Produção</span>';
    } else if (card.scope_rejected_reason) {
        milestoneHtml = '<span class="badge bg-danger" style="font-size:0.62rem;" title="Escopo recusado pelo cliente">Recusado</span>';
    } else if (card.scope_approved_at) {
        milestoneHtml = '<span class="badge bg-success" style="font-size:0.62rem;" title="Escopo aprovado pelo cliente">Aprovado</span>';
    }

    const categoryHtml = card.category ? categoryBadgeHtml(card.category) : '';
    const tagsRow = (categoryHtml || milestoneHtml)
        ? '<div class="mt-1 d-flex flex-wrap gap-1">' + categoryHtml + milestoneHtml + '</div>'
        : '';

    return '<div class="d-flex justify-content-between align-items-start mb-1">' +
            '<span class="text-muted" style="font-size:0.7rem">#' + card.id + '</span>' +
            '<span class="priority-' + esc(card.priority) + '" style="font-size:0.7rem">' + (priorityLabelsMap[card.priority] || '') + '</span>' +
        '</div>' +
        '<div class="fw-medium" style="font-size:0.82rem;word-break:break-word;">' + esc(card.title) + '</div>' +
        tagsRow +
        '<div class="text-muted mt-2" style="font-size:0.7rem">' +
            (card.company_name ? '<span><i class="bi bi-building"></i> ' + esc(card.company_name) + '</span><br>' : '') +
            (card.created_by_name ? '<span><i class="bi bi-person-badge"></i> ' + esc(card.created_by_name) + '</span><br>' : '') +
            '<span><i class="bi bi-person"></i> ' + esc(card.assigned_name || 'Não atribuído') + '</span>' +
            dueHtml +
        '</div>';
}

// Insere um card recém-criado na coluna correta do Kanban, sem recarregar.
function addCardToBoard(card) {
    const list = document.querySelector('.kanban-list[data-status="' + card.status + '"]');
    if (!list) return; // Coluna do status não está visível no filtro atual.

    const div = document.createElement('div');
    div.className = 'kanban-card planning-card' + (cardIsOverdue(card) ? ' overdue' : '');
    div.dataset.id = card.id;
    div.setAttribute('onclick', 'openCardModal(' + card.id + ')');
    div.innerHTML = buildCardInner(card);

    list.prepend(div);
    updateKanbanCounts();
}

// Atualiza um card já existente no Kanban a partir do JSON do backend, sem
// recarregar a página. Reescreve o conteúdo, ajusta a classe de atraso e,
// se o status mudou, move o card para a coluna correta.
// Retorna true se conseguiu refletir a mudança no board; false quando o card
// não está visível no DOM (ex.: filtros escondem a coluna) — nesse caso o
// chamador deve recarregar para garantir consistência.
function updateCardOnBoard(card) {
    const el = document.querySelector('.planning-card[data-id="' + card.id + '"]');
    if (!el) return false;

    el.innerHTML = buildCardInner(card);
    el.classList.toggle('overdue', cardIsOverdue(card));

    // Se o status mudou, mover o card para a coluna correspondente.
    const currentList = el.closest('.kanban-list');
    const currentStatus = currentList ? currentList.dataset.status : null;
    if (currentStatus !== card.status) {
        const targetList = document.querySelector('.kanban-list[data-status="' + card.status + '"]');
        if (!targetList) {
            // A coluna do novo status não está visível (filtro). Remove o card
            // da coluna atual e sinaliza que não há como posicioná-lo aqui.
            el.remove();
            updateKanbanCounts();
            return false;
        }
        targetList.prepend(el);
    }

    updateKanbanCounts();
    return true;
}

// Mostra o campo da segunda branch e esconde o botão "+ segunda branch".
function showSecondBranch() {
    const wrap = document.getElementById('detail-branch-2-wrapper');
    const toggle = document.getElementById('detail-branch-2-toggle-wrapper');
    if (wrap) wrap.style.display = '';
    if (toggle) toggle.style.display = 'none';
}
// Colapsa a segunda branch (usado ao abrir um card que não tem branch 2).
function hideSecondBranch() {
    const wrap = document.getElementById('detail-branch-2-wrapper');
    const toggle = document.getElementById('detail-branch-2-toggle-wrapper');
    if (wrap) wrap.style.display = 'none';
    if (toggle) toggle.style.display = '';
}

// Copia o link individual do card para a área de transferência.
function copyCardLink() {
    const input = document.getElementById('detail-card-link');
    if (!input || !input.value) return;
    const icon = document.getElementById('detail-card-link-icon');
    const done = () => {
        if (icon) {
            icon.classList.remove('bi-clipboard');
            icon.classList.add('bi-clipboard-check');
            setTimeout(() => { icon.classList.remove('bi-clipboard-check'); icon.classList.add('bi-clipboard'); }, 1500);
        }
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(input.value).then(done).catch(() => { input.select(); document.execCommand('copy'); done(); });
    } else {
        input.select();
        document.execCommand('copy');
        done();
    }
}

// Abre automaticamente o card indicado na URL (?card=ID), permitindo que o
// link compartilhado abra direto o card em um modal.
document.addEventListener('DOMContentLoaded', function () {
    const cardId = new URLSearchParams(window.location.search).get('card');
    if (cardId && /^\d+$/.test(cardId)) {
        openCardModal(parseInt(cardId, 10));
    }
});

// Helpers JS da tag de categoria (espelham os helpers PHP / cores da tela da demanda).
function categoryLabelJs(cat) {
    const map = { suporte: 'Suporte', desenvolvimento: 'Desenvolvimento', design: 'Design', marketing: 'Marketing', outro: 'Outro' };
    const c = String(cat || '').toLowerCase().trim();
    return map[c] || (c ? c.charAt(0).toUpperCase() + c.slice(1) : '');
}
function categoryBadgeClassJs(cat) {
    switch (String(cat || '').toLowerCase().trim()) {
        case 'suporte': return 'bg-danger';
        case 'desenvolvimento': return 'bg-primary';
        case 'design': return 'text-white';
        case 'marketing': return 'bg-warning text-dark';
        default: return 'bg-secondary';
    }
}
function categoryBadgeStyleJs(cat) {
    return String(cat || '').toLowerCase().trim() === 'design' ? 'background-color:#6f42c1;' : '';
}
function categoryBadgeHtml(cat) {
    if (!cat) return '';
    return '<span class="badge ' + categoryBadgeClassJs(cat) + '" style="font-size:0.62rem;' + categoryBadgeStyleJs(cat) + '">' + esc(categoryLabelJs(cat)) + '</span>';
}

function openCardModal(id) {
    currentCardId = id;
    fetch(BASE + 'planning/get/' + id).then(r => r.json()).then(data => {
        const c = data.card;
        const priorityLabelsMap = {low:'Baixa',medium:'Média',high:'Alta',urgent:'Urgente'};
        const statusLabelsMap = <?= json_encode(array_map(fn($i) => $i[0], $statusLabels)) ?>;
        const statusColorsMap = <?= json_encode(array_map(fn($i) => $i[1], $statusLabels)) ?>;

        // Header badges
        document.getElementById('detail-id-badge').textContent = '#' + c.id;
        document.getElementById('detail-title').textContent = c.title;
        const prBadge = document.getElementById('detail-priority-badge');
        prBadge.textContent = priorityLabelsMap[c.priority] || c.priority;
        prBadge.style.background = priorityColors[c.priority] || '#666';
        prBadge.style.color = '#fff';
        const stBadge = document.getElementById('detail-status-badge');
        stBadge.textContent = statusLabelsMap[c.status] || c.status;
        stBadge.style.background = statusColorsMap[c.status] || '#666';
        stBadge.style.color = '#fff';

        // Tag de categoria (vem do ticket vinculado, via JOIN em findById).
        const catBadge = document.getElementById('detail-category-badge');
        if (catBadge) {
            const cat = c.category || (data.ticket && data.ticket.category) || '';
            if (cat) {
                catBadge.textContent = categoryLabelJs(cat);
                catBadge.className = 'badge ' + categoryBadgeClassJs(cat);
                catBadge.style.cssText = categoryBadgeStyleJs(cat);
                catBadge.style.display = '';
            } else {
                catBadge.style.display = 'none';
                catBadge.textContent = '';
            }
        }

        // Propriedades (painel direito)
        document.getElementById('detail-title-input').value = c.title;
        document.getElementById('detail-assigned').value = c.assigned_to || '';
        document.getElementById('detail-technical').value = c.technical_responsible_id || '';
        document.getElementById('detail-analyst').value = c.analyst_id || '';
        document.getElementById('detail-company').value = c.company_id || '';
        document.getElementById('detail-priority').value = c.priority;
        document.getElementById('detail-status').value = c.status;
        document.getElementById('detail-due-date').value = c.due_date ? c.due_date.slice(0,16) : '';
        document.getElementById('detail-start-date').value = c.start_date ? c.start_date.slice(0,16) : '';
        document.getElementById('detail-end-date').value = c.end_date ? c.end_date.slice(0,16) : '';
        document.getElementById('detail-client-start-date').value = c.client_start_date ? c.client_start_date.slice(0,10) : '';
        document.getElementById('detail-client-end-date').value = c.client_end_date ? c.client_end_date.slice(0,10) : '';

        // Atividade predecessora (autocomplete): preenche o hidden com o id e o
        // campo de busca com o título do predecessor atual. O card em edição é
        // excluído dos resultados (não pode depender de si mesmo).
        const predId = document.getElementById('detail-predecessor');
        const predSearch = document.getElementById('detail-predecessor-search');
        if (predId && predSearch) {
            predId.value = c.predecessor_id ? String(c.predecessor_id) : '';
            predSearch.value = c.predecessor_id
                ? ('#' + c.predecessor_id + ' — ' + (c.predecessor_title || 'Card'))
                : '';
            // Guarda o id do próprio card para excluir da busca de predecessor.
            const root = predId.closest('[data-pred-root]');
            if (root) root.dataset.excludeId = String(c.id);
        }
        const predLag = document.getElementById('detail-predecessor-lag');
        if (predLag) predLag.value = (c.predecessor_lag_days != null ? c.predecessor_lag_days : 0);
        const predHint = document.getElementById('detail-predecessor-hint');
        if (predHint) predHint.style.display = c.predecessor_id ? '' : 'none';

        // Campos CX Hub
        document.getElementById('detail-cx-hub-number').value = c.cx_hub_number || '';
        document.getElementById('detail-cx-hub-name').value = c.cx_hub_name || '';
        document.getElementById('detail-branch-name').value = c.branch_name || '';
        document.getElementById('detail-branch-name-2').value = c.branch_name_2 || '';
        // Mostra a segunda branch já expandida se o card tiver valor; senão, colapsada.
        if (c.branch_name_2) { showSecondBranch(); } else { hideSecondBranch(); }
        document.getElementById('detail-pr-number').value = c.pr_number || '';

        // Link individual do card (para compartilhamento)
        const cardLink = BASE + 'planning?card=' + c.id;
        document.getElementById('detail-card-link').value = cardLink;
        document.getElementById('detail-card-link-open').href = cardLink;

        // Meta info
        let metaHtml = '<i class="bi bi-person-fill"></i> Criado por <strong>' + (c.created_by_name || 'Desconhecido') + '</strong>';
        metaHtml += '<br><i class="bi bi-calendar3"></i> ' + new Date(c.created_at).toLocaleString('pt-BR');
        if (c.ticket_id) {
            metaHtml += '<br><i class="bi bi-link-45deg"></i> Vinculado à demanda <a href="'+BASE+'tickets/show/'+c.ticket_id+'" target="_blank" class="text-decoration-none fw-medium" style="color:var(--primary);">#'+c.ticket_id+' <i class="bi bi-box-arrow-up-right" style="font-size:0.65rem;"></i></a>';
        }
        document.getElementById('detail-meta').innerHTML = metaHtml;

        // Attachments do card (aba Anexos)
        renderAttachments(data.attachments);
        document.getElementById('tab-anexos-badge').textContent = data.attachments.length;

        // Comments do card (aba Comentários)
        renderComments(data.comments);
        document.getElementById('tab-comentarios-badge').textContent = data.comments.length;

        // Demanda vinculada (aba Demanda)
        renderTicketData(data);

        // Tasks internas (aba Tasks)
        renderTasks(data.tasks || []);

        // Guardar description para setar após o Quill estar pronto
        window._pendingDescription = c.description || '';

        // Reset para aba de descrição
        const firstTab = document.getElementById('tab-descricao');
        if (firstTab) {
            const tabInstance = bootstrap.Tab.getOrCreateInstance(firstTab);
            tabInstance.show();
        }

        const modal = new bootstrap.Modal(document.getElementById('cardDetailModal'));
        modal.show();
    });
}

// === RENDERIZAR DADOS DA DEMANDA VINCULADA ===
function renderTicketData(data) {
    const noTicketMsg = document.getElementById('no-ticket-msg');
    const ticketSection = document.getElementById('ticket-data-section');
    const demandaBadge = document.getElementById('tab-demanda-badge');

    // Guarda a demanda vinculada (ou null) para a aba Escopo/Previsão/Suporte.
    currentTicket = data.ticket || null;
    // Preenche (ou limpa) a aba Escopo com os dados da demanda.
    fillScopeTab();

    if (!data.ticket) {
        noTicketMsg.style.display = '';
        ticketSection.style.display = 'none';
        demandaBadge.style.display = 'none';
        return;
    }

    noTicketMsg.style.display = 'none';
    ticketSection.style.display = '';
    demandaBadge.style.display = '';
    demandaBadge.textContent = '#' + data.ticket.id;

    // Info do ticket
    document.getElementById('ticket-link').href = BASE + 'tickets/show/' + data.ticket.id;
    document.getElementById('ticket-link').textContent = '#' + data.ticket.id;
    document.getElementById('ticket-title-text').textContent = data.ticket.title || '';
    document.getElementById('ticket-client-name').textContent = data.ticket.client_name || 'N/A';
    document.getElementById('ticket-created-at').textContent = data.ticket.created_at ? new Date(data.ticket.created_at).toLocaleString('pt-BR') : '';

    const statusBadge = document.getElementById('ticket-status-badge');
    const sLabels = {open:'Aberto',in_progress:'Em andamento',waiting_client:'Aguardando',completed:'Concluído',denied:'Negado'};
    statusBadge.textContent = sLabels[data.ticket.status] || data.ticket.status;

    // Anexos da demanda
    const attachGrid = document.getElementById('ticket-attachments-grid');
    const noAttachMsg = document.getElementById('no-ticket-attachments');
    const ticketAttachments = data.ticket_attachments || [];

    if (!ticketAttachments.length) {
        attachGrid.innerHTML = '';
        noAttachMsg.style.display = '';
    } else {
        noAttachMsg.style.display = 'none';
        attachGrid.innerHTML = ticketAttachments.map(a => {
            const isImage = /\.(jpg|jpeg|png|gif|webp|svg)$/i.test(a.file_name) || (a.file_type && a.file_type.startsWith('image/'));
            const isVideo = /\.(mp4|webm|ogg|mov|avi)$/i.test(a.file_name) || (a.file_type && a.file_type.startsWith('video/'));

            if (isImage) {
                return `<div class="col-6 col-md-4">
                    <div class="border rounded overflow-hidden">
                        <img src="${BASE}${a.file_path}" class="ticket-attachment-thumb" onclick="window.open('${BASE}${a.file_path}','_blank')" alt="${a.file_name}" loading="lazy">
                        <div class="px-2 py-1 bg-light">
                            <small class="text-truncate d-block" style="font-size:0.7rem;" title="${a.file_name}">${a.file_name}</small>
                            <small class="text-muted" style="font-size:0.6rem;">${a.user_name || ''}</small>
                        </div>
                    </div>
                </div>`;
            } else if (isVideo) {
                return `<div class="col-6 col-md-4">
                    <div class="border rounded overflow-hidden">
                        <video class="ticket-attachment-video" controls preload="metadata">
                            <source src="${BASE}${a.file_path}" type="${a.file_type || 'video/mp4'}">
                            Seu navegador não suporta vídeo.
                        </video>
                        <div class="px-2 py-1 bg-light">
                            <small class="text-truncate d-block" style="font-size:0.7rem;" title="${a.file_name}">${a.file_name}</small>
                            <small class="text-muted" style="font-size:0.6rem;">${a.user_name || ''}</small>
                        </div>
                    </div>
                </div>`;
            } else {
                return `<div class="col-6 col-md-4">
                    <div class="border rounded p-2 text-center">
                        <i class="bi bi-file-earmark fs-4 text-muted"></i>
                        <a href="${BASE}${a.file_path}" target="_blank" class="d-block text-truncate small text-decoration-none" title="${a.file_name}">${a.file_name}</a>
                        <small class="text-muted" style="font-size:0.6rem;">${a.user_name || ''}</small>
                    </div>
                </div>`;
            }
        }).join('');
    }

    // Mensagens da demanda
    const msgList = document.getElementById('ticket-messages-list');
    const noMsgEl = document.getElementById('no-ticket-messages');
    const ticketMessages = data.ticket_messages || [];

    if (!ticketMessages.length) {
        msgList.innerHTML = '';
        noMsgEl.style.display = '';
    } else {
        noMsgEl.style.display = 'none';
        msgList.innerHTML = ticketMessages.map(m => {
            const isClient = m.user_role === 'client' || m.user_role === 'sub_client';
            const bubbleClass = isClient ? 'ticket-msg-client' : 'ticket-msg-attendant';
            const align = isClient ? 'align-items-start' : 'align-items-end';
            return `<div class="d-flex flex-column ${align} mb-2">
                <div class="ticket-msg-bubble ${bubbleClass}">
                    <div style="font-size:0.7rem;color:#555;margin-bottom:2px;"><strong>${m.user_name}</strong> &middot; ${new Date(m.created_at).toLocaleString('pt-BR')}</div>
                    <div>${m.message}</div>
                </div>
            </div>`;
        }).join('');
        msgList.scrollTop = msgList.scrollHeight;
    }

    // Notas internas
    const notesList = document.getElementById('ticket-internal-notes-list');
    const noNotesEl = document.getElementById('no-ticket-notes');
    const internalNotes = data.ticket_internal_notes || [];

    if (!internalNotes.length) {
        notesList.innerHTML = '';
        noNotesEl.style.display = '';
    } else {
        noNotesEl.style.display = 'none';
        notesList.innerHTML = internalNotes.map(n => {
            const escapeHtml = (str) => (str||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
            const noteText = escapeHtml(n.note).replace(/\n/g, '<br>');
            return `
            <div class="internal-note-card">
                <div class="note-meta"><i class="bi bi-person-fill"></i> <strong>${escapeHtml(n.user_name) || 'Sistema'}</strong> &middot; ${new Date(n.created_at).toLocaleString('pt-BR')}</div>
                <div class="note-text">${noteText}</div>
            </div>
        `}).join('');
    }
}

function saveCard() {
    const description = quill ? quill.root.innerHTML : '';

    // Verificar se ainda existem imagens base64 grandes (proteção extra)
    const base64Pattern = /src="data:image\/[^;]+;base64,[^"]{50000,}"/;
    if (base64Pattern.test(description)) {
        if (!confirm('A descrição contém imagens coladas muito grandes que podem causar perda de dados. Deseja tentar salvar assim mesmo?\n\nRecomendação: remova as imagens e cole novamente (elas serão enviadas para o servidor automaticamente).')) {
            return;
        }
    }

    const formData = new FormData();
    formData.append('title', document.getElementById('detail-title-input').value);
    formData.append('assigned_to', document.getElementById('detail-assigned').value);
    formData.append('technical_responsible_id', document.getElementById('detail-technical').value);
    formData.append('analyst_id', document.getElementById('detail-analyst').value);
    formData.append('company_id', document.getElementById('detail-company').value);
    formData.append('priority', document.getElementById('detail-priority').value);
    formData.append('status', document.getElementById('detail-status').value);
    formData.append('due_date', document.getElementById('detail-due-date').value);
    formData.append('start_date', document.getElementById('detail-start-date').value);
    formData.append('end_date', document.getElementById('detail-end-date').value);
    formData.append('client_start_date', document.getElementById('detail-client-start-date').value);
    formData.append('client_end_date', document.getElementById('detail-client-end-date').value);
    // Atividade predecessora
    formData.append('predecessor_id', document.getElementById('detail-predecessor').value);
    formData.append('predecessor_lag_days', document.getElementById('detail-predecessor-lag').value || '0');
    // Campos CX Hub
    formData.append('cx_hub_number', document.getElementById('detail-cx-hub-number').value);
    formData.append('cx_hub_name', document.getElementById('detail-cx-hub-name').value);
    formData.append('branch_name', document.getElementById('detail-branch-name').value);
    formData.append('branch_name_2', document.getElementById('detail-branch-name-2').value);
    formData.append('pr_number', document.getElementById('detail-pr-number').value);

    // Enviar descrição como arquivo Blob para contornar limite do ModSecurity
    // (SecRequestBodyNoFilesLimit não se aplica a file parts)
    const descBlob = new Blob([description], { type: 'text/html' });
    formData.append('description_file', descBlob, 'description.html');

    fetch(BASE + 'planning/update/' + currentCardId, { method: 'POST', body: formData, headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(async r => {
            // Em erro (ex.: 400 por dependência circular) o backend devolve JSON
            // com a mensagem — mostramos ao usuário em vez de um alerta genérico.
            if (!r.ok) {
                const errData = await r.json().catch(() => ({}));
                throw new Error(errData.error || ('HTTP ' + r.status));
            }
            return r.json();
        })
        .then(data => {
            if (data.success) {
                // Fecha o modal de detalhe (se estiver aberto).
                const modalEl = document.getElementById('cardDetailModal');
                if (modalEl) {
                    const modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) modal.hide();
                }

                // Se o usuário está na visão de CALENDÁRIO, permanece nela: apenas
                // recarrega os eventos do calendário em vez de dar reload (que
                // voltaria para o Kanban, a view padrão). Assim o usuário continua
                // exatamente onde estava.
                const calView = document.getElementById('calendar-view');
                const isCalendar = calView && calView.style.display !== 'none';
                if (isCalendar) {
                    loadCalendar();
                    if (typeof showToast === 'function') showToast('Card salvo com sucesso!');
                    return;
                }

                // Visão Kanban: reflete a mudança apenas no card afetado no board,
                // sem recarregar a página. Se o card não está no DOM (filtro/escondido
                // ou paginado), cai no reload como garantia.
                const reflected = data.card ? updateCardOnBoard(data.card) : false;
                if (!reflected) {
                    location.reload();
                    return;
                }
                // Cascata da atividade predecessora: o backend pode ter reagendado
                // cards dependentes. Reflete cada um no board; se algum não estiver
                // visível, recarrega para garantir consistência.
                if (Array.isArray(data.affected_cards) && data.affected_cards.length) {
                    let allReflected = true;
                    data.affected_cards.forEach(ac => {
                        if (!updateCardOnBoard(ac)) allReflected = false;
                    });
                    if (!allReflected) { location.reload(); return; }
                    if (typeof showToast === 'function') showToast(data.affected_cards.length + ' card(s) dependente(s) reagendado(s).');
                }
                if (typeof showToast === 'function') showToast('Card salvo com sucesso!');
            } else {
                alert('Erro ao salvar: ' + (data.error || 'Erro desconhecido'));
            }
        }).catch(err => {
            // Mostra a mensagem específica do backend (ex.: dependência circular)
            // quando houver; senão, cai no aviso genérico de requisição.
            const msg = (err && err.message && !/^HTTP \d+$/.test(err.message))
                ? err.message
                : 'Erro na requisição. Verifique se o conteúdo não é muito grande.';
            alert(msg);
            console.error(err);
        });
}

function deleteCard() {
    if (!confirm('Tem certeza que deseja excluir este card?')) return;
    const formData = new FormData();
    fetch(BASE + 'planning/delete/' + currentCardId, { method: 'POST', body: formData, headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json()).then(data => {
            if (data.success) location.reload();
        });
}

function deleteCardPermanent() {
    if (!confirm('ATENÇÃO: isto irá excluir PERMANENTEMENTE este card e a demanda vinculada (mensagens, anexos e notas). Esta ação não pode ser desfeita.\n\nDeseja continuar?')) return;
    const formData = new FormData();
    fetch(BASE + 'planning/deletePermanent/' + currentCardId, { method: 'POST', body: formData, headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json()).then(data => {
            if (data.success) location.reload();
            else alert(data.error || 'Erro ao excluir permanentemente.');
        });
}

function prDone() {
    const prNumber = document.getElementById('detail-pr-number').value.trim();
    if (!prNumber) {
        alert('Informe o número do PR antes de marcar como feito.');
        document.getElementById('detail-pr-number').focus();
        return;
    }
    if (!confirm('Confirmar PR #' + prNumber + ' como feito?\n\nO card será movido para "Em Revisão Interna" e o analista será notificado.')) return;

    const formData = new FormData();
    formData.append('pr_number', prNumber);
    formData.append('cx_hub_number', document.getElementById('detail-cx-hub-number').value);
    formData.append('cx_hub_name', document.getElementById('detail-cx-hub-name').value);
    formData.append('branch_name', document.getElementById('detail-branch-name').value);
    formData.append('branch_name_2', document.getElementById('detail-branch-name-2').value);

    fetch(BASE + 'planning/prDone/' + currentCardId, {
        method: 'POST',
        body: formData,
        headers: {'X-Requested-With': 'XMLHttpRequest'}
    }).then(r => r.json()).then(data => {
        if (data.success) {
            alert('PR registrado! Card movido para Revisão Interna. Analista notificado.');
            location.reload();
        } else {
            alert(data.error || 'Erro ao registrar PR.');
        }
    }).catch(err => {
        alert('Erro na requisição.');
        console.error(err);
    });
}

// === TASKS ===
function renderTasks(tasks) {
    const container = document.getElementById('tasks-list');
    const badge = document.getElementById('tab-tasks-badge');
    const progressContainer = document.getElementById('tasks-progress-container');

    if (!tasks || !tasks.length) {
        container.innerHTML = '<p class="text-muted small text-center py-3"><i class="bi bi-check2-square"></i> Nenhuma task criada ainda.</p>';
        badge.textContent = '0';
        progressContainer.style.display = 'none';
        return;
    }

    const total = tasks.length;
    const completed = tasks.filter(t => t.is_completed == 1).length;
    badge.textContent = completed + '/' + total;
    progressContainer.style.display = '';
    document.getElementById('tasks-progress-text').textContent = completed + '/' + total + ' concluídas';
    document.getElementById('tasks-progress-bar').style.width = (total > 0 ? (completed / total * 100) : 0) + '%';

    container.innerHTML = tasks.map(task => {
        const isCompleted = task.is_completed == 1;
        const images = task.images || [];
        return `
            <div class="task-item ${isCompleted ? 'task-completed' : ''}" data-task-id="${task.id}">
                <div class="d-flex align-items-start gap-2">
                    <input type="checkbox" class="task-checkbox mt-1" ${isCompleted ? 'checked' : ''} onchange="toggleTaskComplete(${task.id})">
                    <div class="flex-grow-1">
                        <p class="task-title">${escapeHtml(task.title)}</p>
                        ${task.description ? '<div class="task-description">' + escapeHtml(task.description) + '</div>' : ''}
                        ${images.length ? renderTaskImages(images) : ''}
                        <div class="task-meta">
                            <i class="bi bi-person"></i> ${task.created_by_name || 'Sistema'}
                            &middot; ${new Date(task.created_at).toLocaleString('pt-BR')}
                            ${isCompleted ? ' &middot; <span class="text-success"><i class="bi bi-check-circle-fill"></i> ' + (task.completed_by_name || '') + '</span>' : ''}
                        </div>
                        <!-- Upload de imagens na task -->
                        <div class="mt-2">
                            <div class="d-flex gap-2 align-items-center">
                                <input type="file" class="form-control form-control-sm task-image-input" data-task-id="${task.id}" accept="image/*" multiple style="max-width:200px;font-size:0.72rem;">
                                <button class="btn btn-outline-secondary btn-sm" onclick="uploadTaskImages(${task.id})" style="font-size:0.7rem;padding:2px 8px;">
                                    <i class="bi bi-image"></i> Enviar
                                </button>
                            </div>
                        </div>
                    </div>
                    <button class="btn btn-sm btn-outline-danger p-0 px-1 flex-shrink-0" onclick="deleteTask(${task.id})" title="Excluir task">
                        <i class="bi bi-trash" style="font-size:0.75rem;"></i>
                    </button>
                </div>
            </div>
        `;
    }).join('');
}

function renderTaskImages(images) {
    return `<div class="task-images-grid">${images.map(img => `
        <div class="position-relative d-inline-block">
            <img src="${BASE}${img.file_path}" class="task-image-thumb" onclick="window.open('${BASE}${img.file_path}','_blank')" alt="${img.file_name}" title="${img.file_name}" loading="lazy">
            <button class="btn btn-danger position-absolute top-0 end-0 p-0" style="width:16px;height:16px;font-size:0.55rem;line-height:1;border-radius:50%;" onclick="event.stopPropagation();deleteTaskImage(${img.id})" title="Remover">
                <i class="bi bi-x"></i>
            </button>
        </div>
    `).join('')}</div>`;
}

function createTask() {
    const titleInput = document.getElementById('new-task-title');
    const descInput = document.getElementById('new-task-description');
    const title = titleInput.value.trim();
    if (!title) { titleInput.focus(); return; }

    const formData = new FormData();
    formData.append('title', title);
    formData.append('description', descInput.value.trim());

    fetch(BASE + 'planning/createTask/' + currentCardId, {
        method: 'POST',
        body: formData,
        headers: {'X-Requested-With': 'XMLHttpRequest'}
    }).then(r => r.json()).then(data => {
        if (data.success) {
            titleInput.value = '';
            descInput.value = '';
            // Recarregar tasks
            reloadTasks();
        } else {
            alert(data.error || 'Erro ao criar task');
        }
    });
}

function toggleTaskComplete(taskId) {
    fetch(BASE + 'planning/toggleTask/' + taskId, {
        method: 'POST',
        headers: {'X-Requested-With': 'XMLHttpRequest'}
    }).then(r => r.json()).then(data => {
        if (data.success) {
            reloadTasks();
        }
    });
}

function deleteTask(taskId) {
    if (!confirm('Excluir esta task?')) return;
    fetch(BASE + 'planning/deleteTask/' + taskId, {
        method: 'POST',
        headers: {'X-Requested-With': 'XMLHttpRequest'}
    }).then(r => r.json()).then(data => {
        if (data.success) {
            reloadTasks();
        }
    });
}

function uploadTaskImages(taskId) {
    const input = document.querySelector(`.task-image-input[data-task-id="${taskId}"]`);
    if (!input || !input.files.length) return;

    const files = input.files;
    let uploads = [];
    for (let i = 0; i < files.length; i++) {
        const formData = new FormData();
        formData.append('image', files[i]);
        uploads.push(
            fetch(BASE + 'planning/uploadTaskImage/' + taskId, {
                method: 'POST',
                body: formData,
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            }).then(r => r.json())
        );
    }

    Promise.all(uploads).then(results => {
        const errors = results.filter(r => !r.success);
        if (errors.length) {
            alert('Alguns uploads falharam: ' + errors.map(e => e.error).join(', '));
        }
        reloadTasks();
    });
}

function deleteTaskImage(imageId) {
    if (!confirm('Remover esta imagem?')) return;
    fetch(BASE + 'planning/deleteTaskImage/' + imageId, {
        method: 'POST',
        headers: {'X-Requested-With': 'XMLHttpRequest'}
    }).then(r => r.json()).then(data => {
        if (data.success) {
            reloadTasks();
        }
    });
}

function reloadTasks() {
    fetch(BASE + 'planning/tasks/' + currentCardId)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                renderTasks(data.tasks);
            }
        });
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// === COMMENTS ===
function renderComments(comments) {
    const container = document.getElementById('detail-comments');
    if (!comments.length) { container.innerHTML = '<p class="text-muted small">Nenhum comentário.</p>'; return; }
    const escapeHtml = (str) => (str||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    container.innerHTML = comments.map(c => `
        <div class="d-flex gap-2 mb-2">
            <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center flex-shrink-0" style="width:28px;height:28px;">
                <i class="bi bi-person text-white" style="font-size:0.7rem;"></i>
            </div>
            <div class="flex-grow-1">
                <div class="small"><strong>${escapeHtml(c.user_name)}</strong> <span class="text-muted" style="font-size:0.7rem;">${new Date(c.created_at).toLocaleString('pt-BR')}</span></div>
                <div class="small">${escapeHtml(c.message).replace(/\n/g, '<br>')}</div>
            </div>
        </div>
    `).join('');
    container.scrollTop = container.scrollHeight;
}

// ====== ABA ESCOPO: escopo técnico / previsão / suporte (equipe) ======
// Preenche a aba Escopo a partir da demanda vinculada ao card. Chamada ao abrir
// o card (dentro de renderTicketData). Sem demanda vinculada, mostra o aviso.
function fillScopeTab() {
    const noMsg = document.getElementById('scope-no-ticket-msg');
    const fields = document.getElementById('scope-fields-section');
    if (!noMsg || !fields) return;

    if (!currentTicket || !currentTicket.id) {
        noMsg.style.display = '';
        fields.style.display = 'none';
        return;
    }
    noMsg.style.display = 'none';
    fields.style.display = '';

    const t = currentTicket;
    document.getElementById('scope-ticket-ref').textContent = '#' + t.id + (t.title ? ' — ' + t.title : '');

    // Escopo
    document.getElementById('scope-incluido').value = t.escopo_incluido || '';
    document.getElementById('scope-excluido').value = t.escopo_excluido || '';
    document.getElementById('scope-execucao').value = t.escopo_execucao || '';
    document.getElementById('scope-estimativa').value = (t.estimativa_dias != null ? t.estimativa_dias : '');

    // Previsão (campo date espera YYYY-MM-DD)
    document.getElementById('scope-previsao').value = t.previsao_publicacao ? String(t.previsao_publicacao).slice(0, 10) : '';
}

// Helper: POST via fetch para as rotas tickets/* que agora respondem JSON em AJAX.
function scopePost(url, formData) {
    return fetch(BASE + url, {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    }).then(r => r.json().catch(() => ({ error: 'Resposta inválida do servidor.' })).then(data => ({ ok: r.ok, data })));
}

function saveScopeAjax(sendToClient) {
    if (!currentTicket || !currentTicket.id) return;
    const fd = new FormData();
    fd.append('escopo_incluido', document.getElementById('scope-incluido').value);
    fd.append('escopo_excluido', document.getElementById('scope-excluido').value);
    fd.append('escopo_execucao', document.getElementById('scope-execucao').value);
    fd.append('estimativa_dias', document.getElementById('scope-estimativa').value);
    if (sendToClient) fd.append('send_to_client', '1');

    scopePost('tickets/saveScope/' + currentTicket.id, fd).then(res => {
        if (res.ok && res.data.success) {
            alert(sendToClient ? 'Escopo enviado ao cliente para aprovação.' : 'Escopo salvo.');
            location.reload();
        } else {
            alert('Erro: ' + (res.data.error || 'Não foi possível salvar o escopo.'));
        }
    }).catch(() => alert('Erro na requisição.'));
}

function savePrevisaoAjax() {
    if (!currentTicket || !currentTicket.id) return;
    const fd = new FormData();
    fd.append('previsao_publicacao', document.getElementById('scope-previsao').value);
    scopePost('tickets/savePrevisao/' + currentTicket.id, fd).then(res => {
        if (res.ok && res.data.success) {
            alert('Previsão de publicação atualizada.');
            location.reload();
        } else {
            alert('Erro: ' + (res.data.error || 'Não foi possível salvar a previsão.'));
        }
    }).catch(() => alert('Erro na requisição.'));
}

function addComment() {
    const input = document.getElementById('comment-input');
    const msg = input.value.trim();
    if (!msg) return;
    const formData = new FormData();
    formData.append('message', msg);
    fetch(BASE + 'planning/comment/' + currentCardId, { method: 'POST', body: formData, headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json()).then(data => {
            if (data.success) {
                input.value = '';
                const container = document.getElementById('detail-comments');
                if (container.querySelector('.text-muted')) container.innerHTML = '';
                const escapeHtml = (str) => (str||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
                const msgHtml = escapeHtml(data.comment.message).replace(/\n/g, '<br>');
                container.innerHTML += `
                    <div class="d-flex gap-2 mb-2">
                        <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center flex-shrink-0" style="width:28px;height:28px;">
                            <i class="bi bi-person text-white" style="font-size:0.7rem;"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="small"><strong>${escapeHtml(data.comment.user_name)}</strong> <span class="text-muted" style="font-size:0.7rem;">agora</span></div>
                            <div class="small">${msgHtml}</div>
                        </div>
                    </div>`;
                container.scrollTop = container.scrollHeight;
            }
        });
}

// === ATTACHMENTS ===
function renderAttachments(attachments) {
    const container = document.getElementById('detail-attachments');
    if (!attachments.length) { container.innerHTML = '<p class="text-muted small">Nenhum anexo.</p>'; return; }
    container.innerHTML = attachments.map(a => `
        <div class="d-flex justify-content-between align-items-center p-2 border rounded mb-1" style="font-size:0.8rem;">
            <a href="${BASE}${a.file_path}" target="_blank" class="text-decoration-none text-truncate">${a.file_name}</a>
            <button class="btn btn-sm btn-outline-danger p-0 px-1" onclick="deleteAttachment(${a.id})"><i class="bi bi-x"></i></button>
        </div>
    `).join('');
}

function uploadCardFile() {
    const input = document.getElementById('detail-file-input');
    if (!input.files[0]) return;
    const formData = new FormData();
    formData.append('file', input.files[0]);
    fetch(BASE + 'planning/upload/' + currentCardId, { method: 'POST', body: formData })
        .then(r => r.json()).then(data => {
            if (data.success) { input.value = ''; openCardModal(currentCardId); }
            else alert(data.error || 'Erro no upload');
        });
}

function deleteAttachment(attId) {
    if (!confirm('Remover anexo?')) return;
    fetch(BASE + 'planning/deleteAttachment/' + attId, { method: 'POST', headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json()).then(data => { if (data.success) openCardModal(currentCardId); });
}

// === CALENDAR ===
document.getElementById('cal-prev').addEventListener('click', () => { navCalendar(-1); });
document.getElementById('cal-next').addEventListener('click', () => { navCalendar(1); });
document.getElementById('cal-today').addEventListener('click', () => { calDate = new Date(); loadCalendar(); });
document.querySelectorAll('#cal-mode-toggle button').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('#cal-mode-toggle button').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        calMode = this.dataset.mode;
        loadCalendar();
    });
});

function navCalendar(dir) {
    if (calMode === 'month') calDate.setMonth(calDate.getMonth() + dir);
    else if (calMode === 'week') calDate.setDate(calDate.getDate() + (7 * dir));
    else calDate.setDate(calDate.getDate() + dir);
    loadCalendar();
}

function loadCalendar() {
    let start, end;
    if (calMode === 'month') {
        start = new Date(calDate.getFullYear(), calDate.getMonth(), 1);
        end = new Date(calDate.getFullYear(), calDate.getMonth() + 1, 0, 23, 59, 59);
    } else if (calMode === 'week') {
        const d = new Date(calDate); d.setDate(d.getDate() - d.getDay());
        start = new Date(d); end = new Date(d); end.setDate(end.getDate() + 6); end.setHours(23,59,59);
    } else {
        start = new Date(calDate); start.setHours(0,0,0);
        end = new Date(calDate); end.setHours(23,59,59);
    }
    // Usa os filtros EFETIVOS (mesmo recorte do Kanban), não só a query string.
    const params = calendarFilterParams();
    params.set('start', fmt(start)); params.set('end', fmt(end));
    fetch(BASE + 'planning/calendar?' + params.toString())
        .then(r => r.json()).then(resp => {
            // A resposta agora é { events, undated_count }. Mantém retrocompat
            // caso venha um array puro (versões antigas).
            const events = Array.isArray(resp) ? resp : (resp.events || []);
            const undatedCount = Array.isArray(resp) ? 0 : (resp.undated_count || 0);
            calendarEvents = events;
            updateUndatedBanner(undatedCount);
            renderCalendar(start, end);
        });
}

// Mostra/oculta o aviso de demandas sem data acima do calendário.
function updateUndatedBanner(count) {
    const banner = document.getElementById('cal-undated-banner');
    if (!banner) return;
    if (count > 0) {
        document.getElementById('cal-undated-count').textContent = count;
        document.getElementById('cal-undated-text').textContent =
            count === 1 ? 'demanda sem data definida' : 'demandas sem data definida';
        banner.style.display = '';
    } else {
        banner.style.display = 'none';
    }
}

// Abre o modal de revisão e carrega a lista de demandas sem data (mesmo
// escopo/filtros do calendário atual, via query string da página).
function openUndatedModal() {
    const modal = new bootstrap.Modal(document.getElementById('undatedModal'));
    const list = document.getElementById('undated-list');
    list.innerHTML = '<div class="text-center py-4 text-muted small"><span class="spinner-border spinner-border-sm"></span> Carregando...</div>';
    modal.show();

    const params = calendarFilterParams();
    fetch(BASE + 'planning/calendarUndated?' + params.toString())
        .then(r => r.json())
        .then(data => {
            const cards = (data && data.cards) ? data.cards : [];
            if (!cards.length) {
                list.innerHTML = '<div class="text-center py-4 text-muted small"><i class="bi bi-check2-circle text-success"></i> Nenhuma demanda sem data. Tudo agendado!</div>';
                return;
            }
            const prLabels = {low:'Baixa',medium:'Média',high:'Alta',urgent:'Urgente'};
            list.innerHTML = cards.map(c => {
                const pr = prLabels[c.priority] || c.priority || '';
                const company = c.company_name ? '<span class="text-muted"><i class="bi bi-building"></i> ' + escHtml(c.company_name) + '</span>' : '';
                const who = c.assigned_name ? '<span class="text-muted"><i class="bi bi-person"></i> ' + escHtml(c.assigned_name) + '</span>' : '';
                const meta = [company, who].filter(Boolean).join(' · ');
                return '<button type="button" class="undated-item" onclick="reviewUndated(' + c.id + ')">' +
                    '<div class="undated-item-main">' +
                        '<span class="undated-item-title">#' + c.id + ' — ' + escHtml(c.title) + '</span>' +
                        (meta ? '<span class="undated-item-meta">' + meta + '</span>' : '') +
                    '</div>' +
                    '<span class="undated-item-pr priority-' + escAttr(c.priority) + '">' + escHtml(pr) + '</span>' +
                    '<i class="bi bi-chevron-right text-muted"></i>' +
                '</button>';
            }).join('');
        })
        .catch(() => {
            list.innerHTML = '<div class="text-center py-4 text-danger small">Falha ao carregar.</div>';
        });
}

// Abre o card para o usuário definir a data; fecha o modal de revisão antes.
function reviewUndated(id) {
    const modalEl = document.getElementById('undatedModal');
    const modal = bootstrap.Modal.getInstance(modalEl);
    if (modal) modal.hide();
    openCardModal(id);
}

function fmt(d) { return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0')+' '+String(d.getHours()).padStart(2,'0')+':'+String(d.getMinutes()).padStart(2,'0')+':00'; }

const statusLabelsJs = {open:'Aberto',in_progress:'Em andamento',em_revisao_interna:'Em Revisão Interna',waiting_client:'Aguardando',em_homologacao:'Em Homologação',aprovado_producao:'Aprov. Produção',completed:'Concluído',denied:'Negado',archived:'Arquivado'};
const priorityLabelsJs = {low:'Baixa',medium:'Média',high:'Alta',urgent:'Urgente'};

// Status que encerram o fluxo ativo (espelha PlanningRules::INACTIVE_STATUSES).
const INACTIVE_STATUSES = ['completed', 'denied', 'archived'];
// Card concluído = status "completed" (não existe flag booleana separada no card).
function isCompletedStatus(status) { return status === 'completed'; }

// Paleta de cores do calendário por prazo/status (regras da demanda).
// Cada entrada define: fundo (bg), borda (border), cor do texto (text) e rótulo.
const DEADLINE_COLORS = {
    overdue:     { key:'overdue',     bg:'#fde8e8', border:'#dc2626', text:'#991b1b', label:'Passado e não concluído' },
    done:        { key:'done',        bg:'rgba(129,199,132,0.5)', border:'rgba(76,175,80,0.6)', text:'#2e5b31', label:'Passado e concluído' },
    on_track:    { key:'on_track',    bg:'#e7effd', border:'#2563eb', text:'#1e3a8a', label:'Dentro do prazo' },
    near_due:    { key:'near_due',    bg:'#fef7d6', border:'#f59e0b', text:'#7a5900', label:'Próximo do vencimento' },
    due_today:   { key:'due_today',   bg:'#fde8e8', border:'#dc2626', text:'#991b1b', label:'Na data de vencimento' },
    // Em andamento / desenvolvimento (dentro do prazo): roxo para separar do "aberto".
    in_progress: { key:'in_progress', bg:'#ede9fe', border:'#7c3aed', text:'#5b21b6', label:'Em andamento' },
    // Em revisão interna: laranja queimado — distinto do vermelho (atrasado),
    // do amarelo claro (vai atrasar) e dos roxos (andamento/aguardando).
    revisao:     { key:'revisao',     bg:'#ffedd5', border:'#ea580c', text:'#9a3412', label:'Em revisão interna' },
    // Aguardando cliente: roxo (combina com a cor do status no Kanban).
    waiting:     { key:'waiting',     bg:'#f3e5f5', border:'#7b1fa2', text:'#5a1773', label:'Aguardando cliente' },
    // Em homologação: ciano/turquesa (combina com a cor do status no Kanban).
    homolog:     { key:'homolog',     bg:'#d7f3f7', border:'#0097a7', text:'#065a67', label:'Em homologação' },
    // Negada: cinza neutro — não é atraso, saiu do fluxo sem conclusão.
    denied:      { key:'denied',      bg:'#f1f1f3', border:'#9ca3af', text:'#4b5563', label:'Negada' },
    none:        { key:'none',        bg:'#eef1f4', border:'#94a3b8', text:'#475569', label:'Sem prazo definido' },
};

// Status de "em homologação" (inclui aprovado p/ produção, etapa final) — cor própria.
const HOMOLOG_STATUSES = ['em_homologacao', 'aprovado_producao'];
// Status de ETAPA avançada do fluxo: já saíram do desenvolvimento e aguardam
// outra pessoa/etapa. Mostram a cor do status MESMO vencidos (um "atraso" aqui
// não é atraso de desenvolvimento, então não deve virar o vermelho de overdue).
const REVISAO_STATUSES = ['em_revisao_interna'];
const WAITING_STATUSES = ['waiting_client'];
// "Em desenvolvimento" de fato: aberto e em andamento. SÓ estes viram vermelho
// quando vencem — é onde o prazo realmente cobra ação.
const IN_PROGRESS_STATUSES = ['in_progress'];

// Janela (em dias) que caracteriza "próximo do vencimento".
const NEAR_DUE_DAYS = 2;

// Início do dia (00:00) para comparações estáveis por data.
function startOfDay(d) { const x = new Date(d); x.setHours(0,0,0,0); return x; }

// Decide a categoria de cor de um evento a partir de status e due_date.
// Não inventa campos: usa apenas status (conclusão) e due_date (prazo).
function deadlineCategory(ev, todayRef) {
    const today = startOfDay(todayRef || new Date());
    const completed = isCompletedStatus(ev.status);
    const denied = ev.status === 'denied';
    const homolog = HOMOLOG_STATUSES.indexOf(ev.status) !== -1;
    const revisao = REVISAO_STATUSES.indexOf(ev.status) !== -1;
    const waiting = WAITING_STATUSES.indexOf(ev.status) !== -1;
    const inProgress = IN_PROGRESS_STATUSES.indexOf(ev.status) !== -1;

    // Status com COR PRÓPRIA que prevalece sobre o prazo (não viram vermelho de
    // atraso): concluída (verde), negada (cinza), e as etapas avançadas do fluxo
    // — revisão interna, aguardando cliente e homologação. Nessas etapas o card
    // já saiu do desenvolvimento, então "vencido" não deve parecer atraso de dev.
    if (completed) return DEADLINE_COLORS.done;
    if (denied)    return DEADLINE_COLORS.denied;
    if (revisao)   return DEADLINE_COLORS.revisao;
    if (waiting)   return DEADLINE_COLORS.waiting;
    if (homolog)   return DEADLINE_COLORS.homolog;

    // A partir daqui: open / in_progress (em desenvolvimento). O prazo cobra ação.
    if (!ev.due_date) {
        return inProgress ? DEADLINE_COLORS.in_progress : DEADLINE_COLORS.none;
    }

    const due = startOfDay(ev.due_date);
    const isPast = due < today;
    const isToday = due.getTime() === today.getTime();

    // Vencido/vence-hoje em desenvolvimento → vermelho (alerta de prazo real).
    if (isPast) return DEADLINE_COLORS.overdue;
    if (isToday) return DEADLINE_COLORS.due_today;

    // Futuro e dentro do prazo: em andamento (roxo) tem prioridade sobre "aberto".
    if (inProgress) return DEADLINE_COLORS.in_progress;
    const diffDays = Math.round((due - today) / 86400000);
    if (diffDays <= NEAR_DUE_DAYS) return DEADLINE_COLORS.near_due;
    return DEADLINE_COLORS.on_track;
}

// Escapa HTML para uso seguro em conteúdo (evita quebra/XSS com títulos).
function escHtml(s) { const d = document.createElement('div'); d.textContent = (s == null ? '' : String(s)); return d.innerHTML; }
// Escapa para uso em atributos (title="...").
function escAttr(s) { return (s == null ? '' : String(s)).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

// Helper: check if a date falls within the card's range or is its due_date
function getEventsForDay(cellDate) {
    const results = [];
    const cellStr = cellDate.toISOString().slice(0,10);
    calendarEvents.forEach(e => {
        let type = null;
        if (e.start_date && e.end_date) {
            const sd = new Date(e.start_date); sd.setHours(0,0,0,0);
            const ed = new Date(e.end_date); ed.setHours(0,0,0,0);
            if (cellDate >= sd && cellDate <= ed) type = 'dev';
        } else if (e.start_date && !e.end_date) {
            const sd = new Date(e.start_date);
            if (sd.toISOString().slice(0,10) === cellStr) type = 'dev';
        }
        if (e.due_date) {
            const dd = new Date(e.due_date);
            if (dd.toISOString().slice(0,10) === cellStr) type = type || 'due';
        }
        if (type) results.push({...e, type});
    });
    const map = {};
    results.forEach(r => { if (!map[r.id] || r.type === 'due') map[r.id] = r; });
    return Object.values(map);
}

// ---- Helpers compartilhados de "span" multi-dia (usados por Mês e por
//      Semana/Dia, para que o comportamento seja idêntico nas duas visões). ----

// Normaliza uma data para 00:00 local.
function spanStartOfDay(d) { const x = new Date(d); x.setHours(0,0,0,0); return x; }

// Gera os segmentos de eventos (barras contínuas estilo Google Agenda) que
// tocam o intervalo [rangeStart..rangeEnd] (ambos no mesmo "row" de dias
// contíguos: uma semana no Mês, ou a faixa all-day na Semana/Dia).
// numCols = quantidade de colunas (dias) do intervalo.
// Mesma regra do Mês: um card com start_date+end_date vira uma barra; o
// due_date vira um marcador de 1 dia (só quando fora do intervalo dev).
function buildSpanSegments(rangeDays) {
    const first = rangeDays[0];
    const last = rangeDays[rangeDays.length - 1];
    const lastIdx = rangeDays.length - 1;
    const segments = [];

    calendarEvents.forEach(ev => {
        let evStart = null, evEnd = null;
        if (ev.start_date && ev.end_date) {
            evStart = spanStartOfDay(ev.start_date);
            evEnd = spanStartOfDay(ev.end_date);
        } else if (ev.start_date && !ev.end_date) {
            evStart = spanStartOfDay(ev.start_date);
            evEnd = new Date(evStart);
        }

        // Segmento do intervalo de desenvolvimento (start→end).
        if (evStart && evEnd) {
            const segStart = new Date(Math.max(evStart.getTime(), first.getTime()));
            const segEnd = new Date(Math.min(evEnd.getTime(), last.getTime()));
            if (segStart <= last && segEnd >= first) {
                const colStart = Math.round((segStart - first) / 86400000);
                const colEnd = Math.round((segEnd - first) / 86400000);
                const isStart = evStart.getTime() === segStart.getTime();
                const isEnd = evEnd.getTime() === segEnd.getTime();
                segments.push({ ...ev, type: 'dev', colStart: Math.max(0, colStart), colEnd: Math.min(lastIdx, colEnd), isStart, isEnd });
            }
        }

        // Marcador de prazo (due_date) de 1 dia, só se não cair dentro do dev.
        if (ev.due_date) {
            const dd = spanStartOfDay(ev.due_date);
            if (dd >= first && dd <= last) {
                const col = Math.round((dd - first) / 86400000);
                const alreadyHasDev = evStart && evEnd && dd.getTime() >= evStart.getTime() && dd.getTime() <= evEnd.getTime();
                if (!alreadyHasDev) {
                    segments.push({ ...ev, type: 'due', colStart: Math.max(0, col), colEnd: Math.max(0, col), isStart: true, isEnd: true });
                }
            }
        }
    });

    return segments;
}

// Aloca "lanes" (linhas) para os segmentos evitando sobreposição horizontal.
// Retorna o número de lanes usadas (cada seg recebe seg.lane).
function allocateSpanLanes(segments) {
    segments.sort((a, b) => a.colStart - b.colStart || (b.colEnd - b.colStart) - (a.colEnd - a.colStart));
    const lanes = [];
    segments.forEach(seg => {
        let placed = false;
        for (let i = 0; i < lanes.length; i++) {
            const lastInLane = lanes[i][lanes[i].length - 1];
            if (lastInLane.colEnd < seg.colStart) {
                lanes[i].push(seg);
                seg.lane = i;
                placed = true;
                break;
            }
        }
        if (!placed) { seg.lane = lanes.length; lanes.push([seg]); }
    });
    return lanes.length;
}

// Monta o HTML de uma barra de span (reaproveitado por Mês e faixa all-day).
// numCols = número de colunas do intervalo (7 na semana, 1 no dia, 7 no mês).
function spanEventHtml(seg, numCols, today) {
    const cat = deadlineCategory(seg, today);
    const left = (seg.colStart / numCols * 100).toFixed(2);
    const width = ((seg.colEnd - seg.colStart + 1) / numCols * 100).toFixed(2);
    const top = seg.lane * 26 + 2;
    const pLabel = priorityLabelsJs[seg.priority] || seg.priority;
    const brL = seg.isStart ? '5px' : '0';
    const brR = seg.isEnd ? '5px' : '0';
    const titleAttr = escAttr(seg.title);
    return `<div class="cal-span-event cat-${cat.key}" onclick="openCardModal(${seg.id})"
        style="left:${left}%;width:${width}%;top:${top}px;
        background:${cat.bg};border-left:3px solid ${cat.border};color:${cat.text};
        border-radius:${brL} ${brR} ${brR} ${brL};"
        title="${titleAttr}">
        <span class="cal-span-title">${escHtml(seg.title)}</span>
        ${seg.isStart ? `<span class="cal-span-info">
            ${seg.company_name ? '<i class="bi bi-building"></i> ' + escHtml(seg.company_name) + ' ' : ''}
            <i class="bi bi-person"></i> ${escHtml(seg.assigned_name || '—')}
        </span>
        <span class="cal-span-badges">
            <span class="cal-span-tag">${escHtml(pLabel)}</span>
        </span>` : ''}
    </div>`;
}

// Helper for time grid (week/day views)
function getEventsForHour(dayDate, hour) {
    const results = [];
    const dayStr = dayDate.toISOString().slice(0,10);
    calendarEvents.forEach(e => {
        let type = null;
        if (e.start_date && e.end_date) {
            const sd = new Date(e.start_date); sd.setHours(0,0,0,0);
            const ed = new Date(e.end_date); ed.setHours(0,0,0,0);
            const checkDate = new Date(dayDate); checkDate.setHours(0,0,0,0);
            if (checkDate >= sd && checkDate <= ed && hour === 8) type = 'dev';
        } else if (e.start_date && !e.end_date) {
            const sd = new Date(e.start_date);
            if (sd.toISOString().slice(0,10) === dayStr && sd.getHours() === hour) type = 'dev';
        }
        if (e.due_date) {
            const dd = new Date(e.due_date);
            if (dd.toISOString().slice(0,10) === dayStr && dd.getHours() === hour) type = type || 'due';
        }
        if (type) results.push({...e, type});
    });
    return results;
}

function renderCalendar(start, end) {
    const container = document.getElementById('calendar-container');
    const title = document.getElementById('cal-title');
    const months = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
    const days = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
    const today = new Date(); today.setHours(0,0,0,0);

    if (calMode === 'month') {
        title.textContent = months[calDate.getMonth()] + ' ' + calDate.getFullYear();

        // Build grid of weeks
        const first = new Date(calDate.getFullYear(), calDate.getMonth(), 1);
        const startDay = first.getDay();
        const totalDays = new Date(calDate.getFullYear(), calDate.getMonth()+1, 0).getDate();

        // Generate all dates in the grid
        const weeks = [];
        let day = 1 - startDay;
        for (let w = 0; w < 6; w++) {
            const week = [];
            for (let d = 0; d < 7; d++, day++) {
                week.push(new Date(calDate.getFullYear(), calDate.getMonth(), day));
            }
            weeks.push(week);
            if (day > totalDays) break;
        }

        // Cores dos eventos vêm de deadlineCategory() (prazo/status), aplicadas
        // por segmento na renderização — sem paleta arbitrária por id.

        // For each event, compute its start/end as day indices relative to grid
        function dateToStr(d) { return d.toISOString().slice(0,10); }
        const gridStart = weeks[0][0];
        const gridEnd = weeks[weeks.length-1][6];

        // Segmentos por semana usam os helpers compartilhados (mesma lógica
        // reaproveitada pela faixa all-day da Semana/Dia).
        function getEventSegments(weekDates) {
            return buildSpanSegments(weekDates);
        }
        function allocateLanes(segments) {
            return allocateSpanLanes(segments);
        }

        // Build HTML
        let html = '<div class="cal-month-grid">';
        // Header
        html += '<div class="cal-month-header">';
        days.forEach(d => html += `<div class="cal-month-header-cell">${d}</div>`);
        html += '</div>';

        weeks.forEach(weekDates => {
            const segments = getEventSegments(weekDates);
            const laneCount = allocateLanes(segments);

            html += '<div class="cal-month-week">';
            // Day numbers row
            html += '<div class="cal-month-days-row">';
            weekDates.forEach((d, i) => {
                const isOther = d.getMonth() !== calDate.getMonth();
                const isToday = d.getTime() === today.getTime();
                const ymd = calYmd(d);
                // Botão "+" (aparece no hover do dia): cria um card já com o prazo
                // de entrega preenchido com este dia. Igual ao atalho da agenda.
                html += `<div class="cal-month-day-num ${isOther?'other-month':''} ${isToday?'today':''}">
                    <span class="day-number">${d.getDate()}</span>
                    <button type="button" class="cal-day-add" title="Novo card com prazo ${d.getDate()}" onclick="openCreateForDate('${ymd}')" aria-label="Criar card neste dia">
                        <i class="bi bi-plus-lg"></i>
                    </button>
                </div>`;
            });
            html += '</div>';

            // Event lanes
            const eventsHeight = laneCount > 0 ? laneCount * 26 + 4 : 4;
            html += `<div class="cal-month-events" style="min-height:${eventsHeight}px;">`;
            segments.forEach(seg => {
                // Barra multi-dia renderizada pelo helper compartilhado (mesma
                // aparência na Semana/Dia). 7 colunas = dias da semana.
                html += spanEventHtml(seg, 7, today);
            });
            html += '</div>';
            html += '</div>'; // end week
        });
        html += '</div>';
        container.innerHTML = html;

    } else if (calMode === 'week') {
        const weekStart = new Date(calDate); weekStart.setDate(weekStart.getDate() - weekStart.getDay());
        title.textContent = `${weekStart.getDate()}/${weekStart.getMonth()+1} - ${new Date(weekStart.getTime()+6*86400000).getDate()}/${new Date(weekStart.getTime()+6*86400000).getMonth()+1}/${weekStart.getFullYear()}`;
        renderTimeGrid(container, weekStart, 7);
    } else {
        title.textContent = `${calDate.getDate()}/${calDate.getMonth()+1}/${calDate.getFullYear()} (${days[calDate.getDay()]})`;
        renderTimeGrid(container, calDate, 1);
    }
}

function renderTimeGrid(container, startDate, numDays) {
    const days = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
    const today = startOfDay(new Date());

    // Barra única contínua (multi-dia) atravessando os dias — mesma lógica do
    // Mês. Cards com start_date+end_date viram UMA barra que se estende, em vez
    // de se repetir dia a dia na grade de horas.
    const gridDays = [];
    for (let d = 0; d < numDays; d++) {
        const dd = spanStartOfDay(startDate); dd.setDate(dd.getDate() + d);
        gridDays.push(dd);
    }
    const spanSegments = buildSpanSegments(gridDays).filter(s => s.type === 'dev');
    const spanLanes = allocateSpanLanes(spanSegments);
    // Ids dos cards que já viram barra contínua: não repetir na grade de horas.
    const spannedIds = new Set(spanSegments.map(s => s.id));

    let html = '<div style="overflow-x:auto;"><table style="min-width:'+(numDays>1?'700px':'100%')+'"><thead><tr><th style="width:50px;"></th>';
    for (let d = 0; d < numDays; d++) {
        const dd = new Date(startDate); dd.setDate(dd.getDate() + d);
        html += `<th>${days[dd.getDay()]} ${dd.getDate()}/${dd.getMonth()+1}</th>`;
    }
    html += '</tr></thead><tbody>';

    // Linha com a(s) barra(s) contínua(s), sem rótulo e sem fundo destacado.
    if (spanSegments.length > 0) {
        const bandHeight = spanLanes * 26 + 4;
        html += '<tr>';
        html += '<td style="padding:0;border:none;"></td>';
        html += `<td colspan="${numDays}" style="padding:0;border:none;">
            <div style="position:relative;min-height:${bandHeight}px;">`;
        spanSegments.forEach(seg => {
            html += spanEventHtml(seg, numDays, today);
        });
        html += `</div></td>`;
        html += '</tr>';
    }

    for (let h = 6; h <= 22; h++) {
        html += '<tr>';
        html += `<td class="cal-time-label">${String(h).padStart(2,'0')}:00</td>`;
        for (let d = 0; d < numDays; d++) {
            const dd = new Date(startDate); dd.setDate(dd.getDate() + d);
            html += '<td class="cal-time-slot" style="position:relative;">';
            // Eventos de intervalo já estão na barra contínua acima; não repetir.
            const hourEvents = getEventsForHour(dd, h).filter(e => !spannedIds.has(e.id));
            hourEvents.forEach(e => {
                const cat = deadlineCategory(e, today);
                const pLabel = priorityLabelsJs[e.priority] || '';
                html += `<div class="cal-time-event cat-${cat.key}" onclick="openCardModal(${e.id})" title="${escAttr(e.title)}"
                    style="background:${cat.bg};border-left:3px solid ${cat.border};color:${cat.text};">
                    <span class="cal-time-title">${escHtml(e.title)}</span>
                    ${pLabel ? `<span class="cal-span-tag">${escHtml(pLabel)}</span>` : ''}
                </div>`;
            });
            html += '</td>';
        }
        html += '</tr>';
    }
    html += '</tbody></table></div>';
    container.innerHTML = html;
}

// === INIT QUILL ===
function quillImageHandler() {
    const input = document.createElement('input');
    input.setAttribute('type', 'file');
    input.setAttribute('accept', 'image/*');
    input.click();
    input.onchange = () => {
        const file = input.files[0];
        if (file) uploadImageToServer(file);
    };
}

function uploadImageToServer(file) {
    const formData = new FormData();
    formData.append('image', file);
    fetch(BASE + 'planning/uploadImage/' + (currentCardId || 0), {
        method: 'POST',
        body: formData,
        headers: {'X-Requested-With': 'XMLHttpRequest'}
    })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.url) {
            const range = quill.getSelection(true);
            quill.insertEmbed(range.index, 'image', data.url);
            quill.setSelection(range.index + 1);
        } else {
            alert('Erro ao enviar imagem: ' + (data.error || 'Erro desconhecido'));
        }
    })
    .catch(err => {
        console.error('Erro upload imagem:', err);
        alert('Erro ao enviar imagem para o servidor.');
    });
}

document.getElementById('cardDetailModal').addEventListener('shown.bs.modal', function() {
    if (!quill) {
        quill = new Quill('#quill-editor', {
            theme: 'snow',
            modules: {
                toolbar: {
                    container: [
                        [{'header':[1,2,3,false]}],
                        ['bold','italic','underline','strike'],
                        [{'list':'ordered'},{'list':'bullet'}],
                        ['blockquote','code-block'],
                        ['link','image'],
                        [{'color':[]},{'background':[]}],
                        ['clean']
                    ],
                    handlers: {
                        image: quillImageHandler
                    }
                },
                clipboard: {
                    matchVisual: false
                }
            },
            placeholder: 'Escreva aqui... (texto, imagens, tabelas, listas...)'
        });

        // Interceptar imagens coladas (paste) e arrastadas (drop)
        quill.root.addEventListener('paste', function(e) {
            const clipboardData = e.clipboardData || window.clipboardData;
            if (!clipboardData) return;
            const items = clipboardData.items;
            for (let i = 0; i < items.length; i++) {
                if (items[i].type.indexOf('image') !== -1) {
                    e.preventDefault();
                    e.stopPropagation();
                    const file = items[i].getAsFile();
                    if (file) uploadImageToServer(file);
                    return;
                }
            }
        });

        quill.root.addEventListener('drop', function(e) {
            const files = e.dataTransfer ? e.dataTransfer.files : [];
            for (let i = 0; i < files.length; i++) {
                if (files[i].type.indexOf('image') !== -1) {
                    e.preventDefault();
                    e.stopPropagation();
                    uploadImageToServer(files[i]);
                    return;
                }
            }
        });
    }
    // Setar conteúdo após Quill estar pronto
    if (window._pendingDescription !== undefined) {
        quill.root.innerHTML = window._pendingDescription;
        delete window._pendingDescription;
    }
});
</script>

<!-- Filtros de múltipla escolha -->
<style>
.multi-filter .dropdown-toggle { min-width: 160px; }
.multi-filter .dropdown-item:active { background: #e9ecef; color: inherit; }
.multi-filter .dropdown-menu label:hover { background: #f5f7fa; border-radius: 4px; }
</style>
<script>
(function () {
    // Atualiza o texto do botão de cada filtro de múltipla escolha.
    function updateLabel(dropdown) {
        var span = dropdown.querySelector('.dropdown-toggle span');
        var checks = dropdown.querySelectorAll('.mf-check');
        var selected = Array.prototype.filter.call(checks, function (c) { return c.checked; });
        // Opção especial "Sem responsável" (mf-none) conta junto no rótulo.
        var noneEl = dropdown.querySelector('.mf-none');
        var noneOn = !!(noneEl && noneEl.checked);
        var count = selected.length + (noneOn ? 1 : 0);
        if (count === 0) {
            span.textContent = span.getAttribute('data-ph');
        } else if (count === 1) {
            // Mostra o próprio nome quando só há um selecionado
            var only = noneOn ? noneEl : selected[0];
            var lbl = only.parentElement.querySelector('span');
            span.textContent = lbl ? lbl.textContent.trim() : ('1 ' + span.getAttribute('data-single'));
        } else {
            span.textContent = count + ' ' + span.getAttribute('data-plural');
        }
    }

    // Sincroniza o estado do "Selecionar todos" com os itens do filtro.
    function syncMaster(dropdown) {
        var master = dropdown.querySelector('.mf-all');
        if (!master) return;
        var checks = dropdown.querySelectorAll('.mf-check');
        var total = checks.length;
        var selected = Array.prototype.filter.call(checks, function (c) { return c.checked; }).length;
        master.checked = (total > 0 && selected === total);
        master.indeterminate = (selected > 0 && selected < total);
    }

    document.querySelectorAll('.multi-filter').forEach(function (dropdown) {
        updateLabel(dropdown);
        syncMaster(dropdown);

        // "Selecionar todos": marca/desmarca todos os itens de uma vez.
        var master = dropdown.querySelector('.mf-all');
        if (master) {
            master.addEventListener('change', function () {
                dropdown.querySelectorAll('.mf-check').forEach(function (chk) { chk.checked = master.checked; });
                master.indeterminate = false;
                updateLabel(dropdown);
            });
        }

        // Item individual: atualiza rótulo e o estado do "Selecionar todos".
        dropdown.querySelectorAll('.mf-check').forEach(function (chk) {
            chk.addEventListener('change', function () {
                updateLabel(dropdown);
                syncMaster(dropdown);
            });
        });

        // Opção "Sem responsável": só atualiza o rótulo (não entra no "selecionar todos").
        var noneEl = dropdown.querySelector('.mf-none');
        if (noneEl) {
            noneEl.addEventListener('change', function () { updateLabel(dropdown); });
        }
    });
})();
</script>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
