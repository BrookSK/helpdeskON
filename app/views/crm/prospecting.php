<?php $pageTitle = 'Prospecção Automática - CRM'; $currentPage = 'crm_prospecting'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0"><i class="bi bi-robot"></i> Prospecção Automática (Apollo)</h5>
            <small class="text-muted">Configure as campanhas de captação. O cron executa Search → filtro → score → reveal → CRM → sequência.</small>
        </div>
        <button class="btn btn-sm btn-primary" onclick="openCampaign()"><i class="bi bi-plus-lg"></i> Nova campanha</button>
    </div>

    <?php if ($campaigns === null): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle"></i> As tabelas de prospecção ainda não existem. Rode as migrations
        <code>072_apollo_email_templates.sql</code>, <code>073_apollo_whatsapp_templates.sql</code> e
        <code>074_apollo_prospecting_sequence.sql</code> no banco.
    </div>
    <?php endif; ?>

    <?php if (empty($apolloConfigured)): ?>
    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> A API do Apollo não está configurada. Informe a chave em <a href="<?= baseUrl('settings') ?>">Configurações</a>.</div>
    <?php endif; ?>

    <!-- Instruções do cron -->
    <?php $cronQs = !empty($cronToken) ? ('?token=' . rawurlencode($cronToken)) : ''; ?>
    <div class="alert alert-light border d-flex align-items-start gap-2 py-2 px-3 mb-3" style="font-size:0.82rem;">
        <i class="bi bi-clock-history text-info mt-1"></i>
        <div>
            <strong>Agendamento automático:</strong> configure no servidor um cron chamando a URL abaixo (a cada 30 min em horário comercial).
            A cada execução, cada campanha ativa capta até a meta diária restante, respeitando a janela de dias/horário.
            <div class="mt-1"><code id="cron-url"><?= escape($baseUrl) ?>/cron/runProspecting<?= escape($cronQs) ?></code>
            <button class="btn btn-sm btn-outline-secondary py-0 px-1 ms-1" onclick="copyCron()"><i class="bi bi-clipboard"></i></button></div>
            <div class="mt-1 text-muted"><code>*/30 8-18 * * 1-5 curl -s "<?= escape($baseUrl) ?>/cron/runProspecting<?= escape($cronQs) ?>" &gt; /dev/null</code></div>
            <div class="mt-1 text-muted">Também mantenha o worker das sequências: <code>*/5 * * * * curl -s "<?= escape($baseUrl) ?>/cron/runSequences<?= escape($cronQs) ?>" &gt; /dev/null</code></div>
        </div>
    </div>

    <!-- Abas -->
    <ul class="nav nav-pills mb-3" id="prospect-tabs">
        <li class="nav-item"><button class="nav-link active" data-tab="campaigns" onclick="switchProspectTab('campaigns')"><i class="bi bi-collection"></i> Campanhas</button></li>
        <li class="nav-item"><button class="nav-link" data-tab="logs" onclick="switchProspectTab('logs')"><i class="bi bi-clock-history"></i> Logs de execução</button></li>
    </ul>

    <!-- Lista de campanhas -->
    <div class="card" id="tab-campaigns">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size:0.85rem;">
                    <thead class="table-light">
                        <tr>
                            <th>Campanha</th>
                            <th>Fonte</th>
                            <th>Sequência</th>
                            <th>Board / Coluna</th>
                            <th>Meta/dia</th>
                            <th>Hoje</th>
                            <th>Score mín.</th>
                            <th>Reveal</th>
                            <th>Status</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (($campaigns ?: []) as $c): ?>
                        <tr data-id="<?= $c['id'] ?>">
                            <td class="fw-semibold"><?= escape($c['name']) ?><br><small class="text-muted"><?= escape($c['assigned_name'] ?? 'Sem responsável') ?></small></td>
                            <td class="small">
                                <?php if (($c['lead_source'] ?? 'apollo') === 'my_leads'): ?>
                                    <span class="badge bg-secondary"><i class="bi bi-people"></i> Meus Leads</span>
                                <?php else: ?>
                                    <span class="badge bg-dark"><i class="bi bi-robot"></i> Apollo.io</span>
                                <?php endif; ?>
                            </td>
                            <td class="small"><?= escape($c['sequence_name'] ?? '—') ?></td>
                            <td class="small"><?= escape($c['board_name'] ?? '—') ?><?= $c['column_name'] ? ' / ' . escape($c['column_name']) : '' ?></td>
                            <td><?= (int)$c['daily_target'] ?></td>
                            <td><span class="badge bg-info text-dark"><?= (int)$c['captured_today'] ?></span></td>
                            <td><?= (int)$c['min_score'] ?></td>
                            <td class="small"><?= $c['reveal_email'] ? '✉️' : '' ?> <?= $c['reveal_phone'] ? '📞' : '' ?></td>
                            <td>
                                <?php if ($c['is_active']): ?><span class="badge bg-success">Ativa</span><?php else: ?><span class="badge bg-secondary">Inativa</span><?php endif; ?>
                            </td>
                            <td class="text-end text-nowrap">
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-primary" title="Executar agora" onclick="runCampaign(<?= $c['id'] ?>, this)"><i class="bi bi-play-fill"></i></button>
                                    <button class="btn btn-outline-secondary" title="Editar" onclick='editCampaign(<?= json_encode($c, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'><i class="bi bi-pencil"></i></button>
                                    <button class="btn btn-outline-info" title="Log" onclick="showLog(<?= $c['id'] ?>)"><i class="bi bi-list-ul"></i></button>
                                    <button class="btn btn-outline-<?= $c['is_active'] ? 'warning' : 'success' ?>" title="Ativar/Inativar" onclick="toggleCampaign(<?= $c['id'] ?>, this)"><i class="bi bi-power"></i></button>
                                    <button class="btn btn-outline-danger" title="Excluir" onclick="deleteCampaign(<?= $c['id'] ?>, this)"><i class="bi bi-trash"></i></button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($campaigns)): ?>
                        <tr><td colspan="10" class="text-center text-muted py-4">Nenhuma campanha. Clique em "Nova campanha".</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Aba de logs de execução -->
    <div id="tab-logs" style="display:none;">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <small class="text-muted">Etapas concluídas por cada lead nas sequências de prospecção, participantes e erros.</small>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-info" onclick="testEmailOpen(this)" title="Simula a abertura do último e-mail enviado para conferir se o tracking grava"><i class="bi bi-bug"></i> Testar registro de abertura</button>
                <button class="btn btn-sm btn-outline-secondary" onclick="loadExecLog()"><i class="bi bi-arrow-clockwise"></i> Atualizar</button>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header bg-white py-2 fw-semibold small"><i class="bi bi-people"></i> Participantes</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0" style="font-size:0.8rem;">
                        <thead class="table-light"><tr><th>Lead</th><th>E-mail</th><th>Telefone</th><th>Status</th><th>Nó atual</th><th>A/B</th><th>Próx. execução</th><th>Motivo</th></tr></thead>
                        <tbody id="exec-participants"><tr><td colspan="8" class="text-center text-muted py-3">Carregando...</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header bg-white py-2 fw-semibold small"><i class="bi bi-journal-text"></i> Registro de prospecção</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0" style="font-size:0.8rem;">
                        <thead class="table-light"><tr><th>Quando</th><th>Ação</th><th>Lead</th><th>Detalhe</th><th>Créditos</th></tr></thead>
                        <tbody id="exec-prospectlog"><tr><td colspan="5" class="text-center text-muted py-3">Carregando...</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header bg-white py-2 fw-semibold small"><i class="bi bi-check2-square"></i> Etapas executadas</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0" style="font-size:0.8rem;">
                        <thead class="table-light"><tr><th>Quando</th><th>Lead</th><th>Etapa</th><th>Tipo</th><th>Resultado</th><th>Detalhe</th></tr></thead>
                        <tbody id="exec-steps"><tr><td colspan="6" class="text-center text-muted py-3">Carregando...</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header bg-white py-2 fw-semibold small"><i class="bi bi-envelope-open"></i> E-mails enviados (abertura / clique / resposta)</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0" style="font-size:0.8rem;">
                        <thead class="table-light"><tr><th>Enviado em</th><th>Destinatário</th><th>Assunto</th><th>A/B</th><th class="text-center">Aberturas</th><th class="text-center">1ª abertura</th><th class="text-center">Cliques</th><th class="text-center">Respondeu</th></tr></thead>
                        <tbody id="exec-emails"><tr><td colspan="8" class="text-center text-muted py-3">Carregando...</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white py-2 fw-semibold small text-danger"><i class="bi bi-exclamation-triangle"></i> Erros recentes</div>
            <div class="card-body">
                <pre id="exec-errors" class="small mb-0" style="max-height:280px;overflow:auto;white-space:pre-wrap;background:#f8f9fa;padding:10px;border-radius:8px;">Carregando...</pre>
            </div>
        </div>
    </div>
</div><!-- /.main-content -->

<!-- Modal Campanha -->
<div class="modal fade" id="campaignModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-robot"></i> <span id="camp-modal-title">Nova campanha</span></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="camp-id">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label small fw-medium">Nome da campanha *</label>
                        <input type="text" id="camp-name" class="form-control form-control-sm" placeholder="Ex: Captação SaaS SP">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-medium">Fonte dos leads *</label>
                        <select id="camp-source" class="form-select form-select-sm" onchange="onCampSourceChange()">
                            <option value="apollo">Apollo.io</option>
                            <option value="my_leads">Meus Leads</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-medium">Sequência</label>
                        <select id="camp-sequence" class="form-select form-select-sm">
                            <option value="">Selecione...</option>
                            <?php foreach ($sequences as $s): ?><option value="<?= $s['id'] ?>"><?= escape($s['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-medium">Board</label>
                        <select id="camp-board" class="form-select form-select-sm" onchange="onCampBoardChange()">
                            <option value="">Selecione...</option>
                            <?php foreach ($boards as $b): ?><option value="<?= $b['id'] ?>"><?= escape($b['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-medium">Coluna</label>
                        <select id="camp-column" class="form-select form-select-sm"><option value="">Selecione o board...</option></select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-medium">Responsável (leads)</label>
                        <select id="camp-assigned" class="form-select form-select-sm">
                            <option value="">Sem responsável</option>
                            <?php foreach ($team as $t): ?><option value="<?= $t['id'] ?>"><?= escape($t['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-medium">Meta/dia</label>
                        <input type="number" id="camp-daily" class="form-control form-control-sm" value="12" min="1">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-medium">Score mín.</label>
                        <input type="number" id="camp-minscore" class="form-control form-control-sm" value="70" min="0">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-medium">Por página</label>
                        <input type="number" id="camp-perpage" class="form-control form-control-sm" value="50" min="10" max="100">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="camp-active" checked>
                            <label class="form-check-label small" for="camp-active">Ativa</label>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-medium">Dias da semana</label>
                        <input type="text" id="camp-days" class="form-control form-control-sm" value="1,2,3,4,5" placeholder="1,2,3,4,5 (1=seg)">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-medium">Início</label>
                        <input type="time" id="camp-wstart" class="form-control form-control-sm" value="08:00">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-medium">Fim</label>
                        <input type="time" id="camp-wend" class="form-control form-control-sm" value="18:00">
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="camp-global-dedupe" checked>
                            <label class="form-check-label small" for="camp-global-dedupe">Deduplicação global — nunca prospectar novamente quem já foi captado por qualquer campanha automática</label>
                        </div>
                    </div>

                    <!-- ===== Origem: Meus Leads ===== -->
                    <div class="col-12 myleads-section" style="display:none;">
                        <div class="row g-3">
                            <div class="col-12"><hr class="my-1"><strong class="small"><i class="bi bi-people"></i> Filtros de Meus Leads</strong>
                                <div class="text-muted small">Inscreve leads já existentes no CRM. Não busca nem revela na Apollo, e não altera o responsável do lead.</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Temperatura</label>
                                <select id="camp-ml-temperature" class="form-select form-select-sm">
                                    <option value="">Qualquer</option>
                                    <option value="frio">Frio</option>
                                    <option value="morno">Morno</option>
                                    <option value="quente">Quente</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Fonte do lead</label>
                                <input type="text" id="camp-ml-source" class="form-control form-control-sm" placeholder="ex: apollo, manual_email, form">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Responsável</label>
                                <select id="camp-ml-assigned" class="form-select form-select-sm">
                                    <option value="">Qualquer</option>
                                    <?php foreach ($team as $t): ?><option value="<?= $t['id'] ?>"><?= escape($t['name']) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <div class="d-flex align-items-center justify-content-between border rounded p-2 bg-light">
                                    <div class="small">
                                        <strong><i class="bi bi-check2-square"></i> Leads específicos (opcional)</strong>
                                        <div class="text-muted" id="camp-ml-selected-info">Nenhum lead específico selecionado — usa os filtros acima.</div>
                                    </div>
                                    <div class="text-nowrap">
                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="openLeadPicker()"><i class="bi bi-list-check"></i> Selecionar</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearLeadSelection()" id="camp-ml-clear" style="display:none;"><i class="bi bi-x-lg"></i></button>
                                    </div>
                                </div>
                                <input type="hidden" id="camp-ml-ids" value="">
                            </div>
                        </div>
                    </div>

                    <!-- ===== Origem: Apollo ===== -->
                    <div class="col-12 apollo-section">
                        <div class="row g-3">
                    <div class="col-12">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" id="camp-reveal-email" checked>
                            <label class="form-check-label small" for="camp-reveal-email">Revelar e-mail</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" id="camp-reveal-phone">
                            <label class="form-check-label small" for="camp-reveal-phone">Revelar telefone no início (não recomendado — o padrão é progressivo)</label>
                        </div>
                    </div>

                    <div class="col-12"><hr class="my-1"><strong class="small"><i class="bi bi-funnel"></i> Filtros da busca (Apollo)</strong></div>
                    <div class="col-md-6">
                        <label class="form-label small">Cargos (person_titles)</label>
                        <input type="text" id="camp-f-titles" class="form-control form-control-sm" placeholder="ceo, diretor, gerente">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Senioridades</label>
                        <input type="text" id="camp-f-seniorities" class="form-control form-control-sm" placeholder="owner, founder, c_suite, vp, head, director">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Localização da pessoa</label>
                        <input type="text" id="camp-f-ploc" class="form-control form-control-sm" placeholder="Brazil">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Faixas de funcionários</label>
                        <input type="text" id="camp-f-emp" class="form-control form-control-sm" placeholder="11,50, 51,200, 201,500">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Palavras-chave</label>
                        <input type="text" id="camp-f-keywords" class="form-control form-control-sm" placeholder="ex: logística">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Domínios (opcional)</label>
                        <input type="text" id="camp-f-domains" class="form-control form-control-sm" placeholder="empresa.com, outra.com">
                    </div>

                    <div class="col-12"><hr class="my-1"><strong class="small"><i class="bi bi-sliders"></i> ICP e pesos do score</strong></div>
                    <div class="col-md-6">
                        <label class="form-label small">ICP — senioridades aceitas</label>
                        <input type="text" id="camp-icp-sen" class="form-control form-control-sm" placeholder="owner, founder, c_suite, vp, head, director">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">ICP — cargos (contém)</label>
                        <input type="text" id="camp-icp-titles" class="form-control form-control-sm" placeholder="ceo, diretor, gerente">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Funcionários mín.</label>
                        <input type="number" id="camp-icp-empmin" class="form-control form-control-sm" placeholder="11">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Funcionários máx.</label>
                        <input type="number" id="camp-icp-empmax" class="form-control form-control-sm" placeholder="500">
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="camp-icp-website" checked>
                            <label class="form-check-label small" for="camp-icp-website">Exigir empresa com site</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label small">Pesos do score</label>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="input-group input-group-sm" style="width:auto;"><span class="input-group-text">Decisor</span><input type="number" id="camp-w-decisor" class="form-control" value="30" style="width:70px;"></span>
                            <span class="input-group input-group-sm" style="width:auto;"><span class="input-group-text">Cargo</span><input type="number" id="camp-w-title" class="form-control" value="20" style="width:70px;"></span>
                            <span class="input-group input-group-sm" style="width:auto;"><span class="input-group-text">Porte</span><input type="number" id="camp-w-size" class="form-control" value="15" style="width:70px;"></span>
                            <span class="input-group input-group-sm" style="width:auto;"><span class="input-group-text">Região</span><input type="number" id="camp-w-region" class="form-control" value="10" style="width:70px;"></span>
                            <span class="input-group input-group-sm" style="width:auto;"><span class="input-group-text">Site</span><input type="number" id="camp-w-website" class="form-control" value="5" style="width:70px;"></span>
                            <span class="input-group input-group-sm" style="width:auto;"><span class="input-group-text">Tec.</span><input type="number" id="camp-w-technology" class="form-control" value="10" style="width:70px;"></span>
                        </div>
                    </div>
                        </div><!-- /.row (apollo-section) -->
                    </div><!-- /.apollo-section -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
                <button class="btn btn-sm btn-primary" id="camp-save" onclick="saveCampaign(this)"><i class="bi bi-check-lg"></i> Salvar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Seleção de Leads (multiseleção) -->
<div class="modal fade" id="leadPickerModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-list-check"></i> Selecionar leads específicos</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex gap-2 mb-2">
                    <input type="text" id="lp-search" class="form-control form-control-sm" placeholder="Buscar por nome, e-mail ou telefone...">
                    <button class="btn btn-sm btn-outline-secondary text-nowrap" onclick="loadLeadPicker()"><i class="bi bi-search"></i></button>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2 small">
                    <div>
                        <a href="#" onclick="lpSelectAll(true);return false;">Selecionar todos</a> ·
                        <a href="#" onclick="lpSelectAll(false);return false;">Limpar</a>
                    </div>
                    <span class="text-muted"><span id="lp-count">0</span> selecionado(s)</span>
                </div>
                <div class="table-responsive" style="max-height:420px;overflow:auto;">
                    <table class="table table-sm table-hover mb-0" style="font-size:0.82rem;">
                        <thead class="table-light sticky-top"><tr>
                            <th style="width:36px;"></th><th>Nome</th><th>E-mail</th><th>Responsável</th><th>Temp.</th>
                        </tr></thead>
                        <tbody id="lp-body"><tr><td colspan="5" class="text-center text-muted py-3">Carregando...</td></tr></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-sm btn-primary" onclick="applyLeadSelection()"><i class="bi bi-check-lg"></i> Aplicar seleção</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Log -->
<div class="modal fade" id="campLogModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header"><h6 class="modal-title"><i class="bi bi-list-ul"></i> Log da campanha</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body"><div id="camp-log-body" class="small">Carregando...</div></div>
        </div>
    </div>
</div>

<script>
const BASE = '<?= baseUrl('') ?>';
const CAMP_BOARDS = <?= json_encode(array_map(fn($b) => ['id' => $b['id'], 'columns' => array_map(fn($c) => ['id' => $c['id'], 'name' => $c['name']], $b['columns'])], $boards)) ?>;
let campModal, logModal;

function copyCron() { navigator.clipboard.writeText(document.getElementById('cron-url').textContent); }

// ===== Abas =====
function switchProspectTab(tab) {
    document.querySelectorAll('#prospect-tabs .nav-link').forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
    document.getElementById('tab-campaigns').style.display = tab === 'campaigns' ? '' : 'none';
    document.getElementById('tab-logs').style.display = tab === 'logs' ? '' : 'none';
    if (tab === 'logs') loadExecLog();
}

const STEP_LABELS = { send:'E-mail', whatsapp:'WhatsApp', wait:'Aguardar', condition:'Condição', tag:'Etiqueta', score:'Score', move:'Mover card', reveal_phone:'Revelar (Apollo)', end:'Encerrar' };

function loadExecLog() {
    fetch(BASE + 'crm/prospectingExecLog', { headers:{'X-Requested-With':'XMLHttpRequest'} })
        .then(r=>r.json()).then(d=>{
            // Participantes
            const pt = document.getElementById('exec-participants');
            const parts = d.participants || [];
            pt.innerHTML = parts.length ? parts.map(p => {
                const st = { active:'success', paused:'warning', finished:'secondary', stopped:'info', failed:'danger' }[p.status] || 'secondary';
                return `<tr>
                    <td>${escapeH(p.contact_name||'—')}</td>
                    <td>${escapeH(p.lead_email||'—')}</td>
                    <td>${escapeH(p.phone||'—')}</td>
                    <td><span class="badge bg-${st}">${escapeH(p.status)}</span></td>
                    <td>${escapeH(p.current_node||'—')}</td>
                    <td>${escapeH(p.ab_variant||'—')}</td>
                    <td>${escapeH(p.next_run_at||'—')}</td>
                    <td>${escapeH(p.stop_reason||'—')}</td>
                </tr>`;
            }).join('') : '<tr><td colspan="8" class="text-center text-muted py-3">Nenhum participante ainda.</td></tr>';

            // Etapas
            const stb = document.getElementById('exec-steps');
            const steps = d.steps || [];
            stb.innerHTML = steps.length ? steps.map(s => {
                const rc = { done:'success', waiting:'warning', skipped:'secondary', failed:'danger' }[s.result] || 'secondary';
                return `<tr>
                    <td class="text-nowrap">${escapeH(s.executed_at||'')}</td>
                    <td>${escapeH(s.contact_name||s.lead_email||'—')}</td>
                    <td>${escapeH(s.node_id||'')}</td>
                    <td>${escapeH(STEP_LABELS[s.node_type]||s.node_type)}</td>
                    <td><span class="badge bg-${rc}">${escapeH(s.result||'')}</span></td>
                    <td class="text-muted">${escapeH(s.detail||'')}</td>
                </tr>`;
            }).join('') : '<tr><td colspan="6" class="text-center text-muted py-3">Nenhuma etapa executada ainda.</td></tr>';

            // Registro de prospecção
            const plb = document.getElementById('exec-prospectlog');
            const plog = d.prospect_log || [];
            plb.innerHTML = plog.length ? plog.map(l => `<tr>
                <td class="text-nowrap">${escapeH(l.created_at||'')}</td>
                <td><span class="badge bg-light text-dark border">${escapeH(l.action||'')}</span></td>
                <td>${escapeH(l.contact_name||l.lead_email||'—')}</td>
                <td class="text-muted">${escapeH(l.detail||'')}</td>
                <td>${escapeH(String(l.credits||0))}</td>
            </tr>`).join('') : '<tr><td colspan="5" class="text-center text-muted py-3">Nenhum registro ainda.</td></tr>';

            // E-mails enviados (abertura/clique/resposta)
            const emb = document.getElementById('exec-emails');
            const emails = d.emails || [];
            emb.innerHTML = emails.length ? emails.map(m => `<tr>
                <td class="text-nowrap">${escapeH(m.sent_at||'—')}</td>
                <td>${escapeH(m.contact_name||m.recipient_email||'—')}</td>
                <td>${escapeH(m.subject||'')}</td>
                <td>${escapeH(m.ab_variant||'—')}</td>
                <td class="text-center">${(Number(m.open_count)>0)?('<span class="badge bg-success">'+m.open_count+'</span>'):'<span class="text-muted">0</span>'}</td>
                <td class="text-center small">${escapeH(m.first_open_at||'—')}</td>
                <td class="text-center">${escapeH(String(m.click_count||0))}</td>
                <td class="text-center">${m.replied_at?'<span class="badge bg-primary">sim</span>':'<span class="text-muted">não</span>'}</td>
            </tr>`).join('') : '<tr><td colspan="8" class="text-center text-muted py-3">Nenhum e-mail enviado ainda.</td></tr>';

            // Erros
            const errs = d.errors || [];
            document.getElementById('exec-errors').textContent = errs.length ? errs.join('\n') : 'Sem erros registrados.';
        })
        .catch(()=>{ document.getElementById('exec-errors').textContent = 'Erro ao carregar os logs.'; });
}

function onCampSourceChange() {
    const src = document.getElementById('camp-source').value;
    const isMy = src === 'my_leads';
    document.querySelectorAll('.apollo-section').forEach(el => el.style.display = isMy ? 'none' : '');
    document.querySelectorAll('.myleads-section').forEach(el => el.style.display = isMy ? '' : 'none');
}

// ===== Seleção de leads específicos (multiseleção) =====
let leadPickerModal;
let selectedLeadIds = [];      // aplicados à campanha
let lpTempSelected = new Set(); // seleção temporária no modal
let lpLeadCache = {};           // id -> nome (para exibir contagem)

function refreshLeadSelectionInfo() {
    const info = document.getElementById('camp-ml-selected-info');
    const clearBtn = document.getElementById('camp-ml-clear');
    document.getElementById('camp-ml-ids').value = selectedLeadIds.join(',');
    if (selectedLeadIds.length) {
        info.innerHTML = '<span class="text-success fw-medium">' + selectedLeadIds.length + ' lead(s) selecionado(s)</span> — os filtros acima serão ignorados.';
        clearBtn.style.display = '';
    } else {
        info.textContent = 'Nenhum lead específico selecionado — usa os filtros acima.';
        clearBtn.style.display = 'none';
    }
}

function clearLeadSelection() {
    selectedLeadIds = [];
    refreshLeadSelectionInfo();
}

function openLeadPicker() {
    if (!leadPickerModal) leadPickerModal = new bootstrap.Modal(document.getElementById('leadPickerModal'));
    lpTempSelected = new Set(selectedLeadIds.map(String));
    document.getElementById('lp-search').value = '';
    leadPickerModal.show();
    loadLeadPicker();
}

function loadLeadPicker() {
    const body = document.getElementById('lp-body');
    body.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Carregando...</td></tr>';
    const qs = new URLSearchParams();
    const s = document.getElementById('lp-search').value.trim();
    if (s) qs.set('search', s);
    const t = document.getElementById('camp-ml-temperature').value; if (t) qs.set('temperature', t);
    const src = document.getElementById('camp-ml-source').value.trim(); if (src) qs.set('source', src);
    const a = document.getElementById('camp-ml-assigned').value; if (a) qs.set('assigned_to', a);

    fetch(BASE + 'crm/leadsForCampaign?' + qs.toString(), { headers:{'X-Requested-With':'XMLHttpRequest'} })
        .then(r=>r.json()).then(d=>{
            const leads = d.leads || [];
            if (!leads.length) { body.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Nenhum lead com e-mail encontrado.</td></tr>'; return; }
            body.innerHTML = leads.map(l => {
                lpLeadCache[l.id] = l.contact_name || l.lead_email;
                const checked = lpTempSelected.has(String(l.id)) ? 'checked' : '';
                return `<tr onclick="lpToggleRow(${l.id}, event)" style="cursor:pointer;">
                    <td><input type="checkbox" class="form-check-input lp-check" value="${l.id}" ${checked} onclick="event.stopPropagation();lpToggle(${l.id}, this.checked)"></td>
                    <td>${escapeH(l.contact_name||'—')}</td>
                    <td>${escapeH(l.lead_email||'—')}</td>
                    <td>${escapeH(l.assigned_name||'—')}</td>
                    <td>${escapeH(l.lead_temperature||'—')}</td>
                </tr>`;
            }).join('');
            lpUpdateCount();
        })
        .catch(()=>{ body.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-3">Erro ao carregar leads.</td></tr>'; });
}

function lpToggle(id, on) {
    if (on) lpTempSelected.add(String(id)); else lpTempSelected.delete(String(id));
    lpUpdateCount();
}
function lpToggleRow(id, ev) {
    const cb = ev.currentTarget.querySelector('.lp-check');
    if (cb) { cb.checked = !cb.checked; lpToggle(id, cb.checked); }
}
function lpSelectAll(on) {
    document.querySelectorAll('#lp-body .lp-check').forEach(cb => { cb.checked = on; lpToggle(parseInt(cb.value,10), on); });
}
function lpUpdateCount() { document.getElementById('lp-count').textContent = lpTempSelected.size; }

function applyLeadSelection() {
    selectedLeadIds = Array.from(lpTempSelected).map(v => parseInt(v,10)).filter(Boolean);
    refreshLeadSelectionInfo();
    leadPickerModal.hide();
}

function onCampBoardChange() {
    const bid = document.getElementById('camp-board').value;
    const sel = document.getElementById('camp-column');
    const b = CAMP_BOARDS.find(x => String(x.id) === String(bid));
    if (!b || !b.columns.length) { sel.innerHTML = '<option value="">Sem colunas</option>'; return; }
    sel.innerHTML = '<option value="">Selecione...</option>' + b.columns.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
}

function openCampaign() {
    document.getElementById('camp-modal-title').textContent = 'Nova campanha';
    ['camp-id','camp-name','camp-f-titles','camp-f-seniorities','camp-f-ploc','camp-f-emp','camp-f-keywords','camp-f-domains','camp-icp-sen','camp-icp-titles','camp-icp-empmin','camp-icp-empmax'].forEach(f => document.getElementById(f).value = '');
    document.getElementById('camp-sequence').value = '';
    document.getElementById('camp-board').value = '';
    document.getElementById('camp-column').innerHTML = '<option value="">Selecione o board...</option>';
    document.getElementById('camp-assigned').value = '';
    document.getElementById('camp-daily').value = 12;
    document.getElementById('camp-minscore').value = 70;
    document.getElementById('camp-perpage').value = 50;
    document.getElementById('camp-days').value = '1,2,3,4,5';
    document.getElementById('camp-wstart').value = '08:00';
    document.getElementById('camp-wend').value = '18:00';
    document.getElementById('camp-active').checked = true;
    document.getElementById('camp-reveal-email').checked = true;
    document.getElementById('camp-reveal-phone').checked = false;
    document.getElementById('camp-icp-website').checked = true;
    document.getElementById('camp-source').value = 'apollo';
    document.getElementById('camp-global-dedupe').checked = true;
    document.getElementById('camp-ml-temperature').value = '';
    document.getElementById('camp-ml-source').value = '';
    document.getElementById('camp-ml-assigned').value = '';
    selectedLeadIds = [];
    refreshLeadSelectionInfo();
    onCampSourceChange();
    ['decisor:30','title:20','size:15','region:10','website:5','technology:10'].forEach(p => { const [k,v]=p.split(':'); document.getElementById('camp-w-'+k).value = v; });
    if (!campModal) campModal = new bootstrap.Modal(document.getElementById('campaignModal'));
    campModal.show();
}

function editCampaign(c) {
    openCampaign();
    document.getElementById('camp-modal-title').textContent = 'Editar campanha';
    document.getElementById('camp-id').value = c.id;
    document.getElementById('camp-name').value = c.name || '';
    document.getElementById('camp-sequence').value = c.sequence_id || '';
    document.getElementById('camp-board').value = c.board_id || '';
    onCampBoardChange();
    document.getElementById('camp-column').value = c.column_id || '';
    document.getElementById('camp-assigned').value = c.assigned_to || '';
    document.getElementById('camp-daily').value = c.daily_target;
    document.getElementById('camp-minscore').value = c.min_score;
    document.getElementById('camp-perpage').value = c.search_per_page;
    document.getElementById('camp-days').value = c.days_of_week || '1,2,3,4,5';
    document.getElementById('camp-wstart').value = (c.window_start||'08:00:00').slice(0,5);
    document.getElementById('camp-wend').value = (c.window_end||'18:00:00').slice(0,5);
    document.getElementById('camp-active').checked = !!Number(c.is_active);
    document.getElementById('camp-reveal-email').checked = !!Number(c.reveal_email);
    document.getElementById('camp-reveal-phone').checked = !!Number(c.reveal_phone);
    document.getElementById('camp-source').value = c.lead_source || 'apollo';
    document.getElementById('camp-global-dedupe').checked = c.global_dedupe == null ? true : !!Number(c.global_dedupe);
    onCampSourceChange();

    try {
        const ml = JSON.parse(c.my_leads_filters || '{}');
        document.getElementById('camp-ml-temperature').value = ml.temperature || '';
        document.getElementById('camp-ml-source').value = ml.source || '';
        document.getElementById('camp-ml-assigned').value = ml.assigned_to || '';
    } catch(e){}
    try {
        const ids = JSON.parse(c.my_leads_ids || '[]');
        selectedLeadIds = Array.isArray(ids) ? ids.map(v => parseInt(v,10)).filter(Boolean) : [];
    } catch(e){ selectedLeadIds = []; }
    refreshLeadSelectionInfo();

    try {
        const f = JSON.parse(c.search_filters || '{}');
        document.getElementById('camp-f-titles').value = (f.person_titles||[]).join(', ');
        document.getElementById('camp-f-seniorities').value = (f.person_seniorities||[]).join(', ');
        document.getElementById('camp-f-ploc').value = (f.person_locations||[]).join(', ');
        document.getElementById('camp-f-emp').value = (f.organization_num_employees_ranges||[]).join(', ');
        document.getElementById('camp-f-keywords').value = f.q_keywords || '';
        document.getElementById('camp-f-domains').value = (f.q_organization_domains_list||[]).join(', ');
    } catch(e){}
    try {
        const icp = JSON.parse(c.icp_rules || '{}');
        document.getElementById('camp-icp-sen').value = (icp.seniorities||[]).join(', ');
        document.getElementById('camp-icp-titles').value = (icp.titles_any||[]).join(', ');
        document.getElementById('camp-icp-empmin').value = icp.employee_min || '';
        document.getElementById('camp-icp-empmax').value = icp.employee_max || '';
        document.getElementById('camp-icp-website').checked = !!icp.require_website;
        const w = icp.score || {};
        ['decisor','title','size','region','website','technology'].forEach(k => { if (w[k]!=null) document.getElementById('camp-w-'+k).value = w[k]; });
    } catch(e){}
}

function saveCampaign(btn) {
    const name = document.getElementById('camp-name').value.trim();
    if (!name) { alert('Informe o nome.'); return; }
    btn.disabled = true;
    const fd = new FormData();
    fd.append('id', document.getElementById('camp-id').value);
    fd.append('name', name);
    fd.append('sequence_id', document.getElementById('camp-sequence').value);
    fd.append('board_id', document.getElementById('camp-board').value);
    fd.append('column_id', document.getElementById('camp-column').value);
    fd.append('assigned_to', document.getElementById('camp-assigned').value);
    fd.append('daily_target', document.getElementById('camp-daily').value);
    fd.append('min_score', document.getElementById('camp-minscore').value);
    fd.append('search_per_page', document.getElementById('camp-perpage').value);
    fd.append('days_of_week', document.getElementById('camp-days').value);
    fd.append('window_start', document.getElementById('camp-wstart').value);
    fd.append('window_end', document.getElementById('camp-wend').value);
    if (document.getElementById('camp-active').checked) fd.append('is_active', '1');
    if (document.getElementById('camp-reveal-email').checked) fd.append('reveal_email', '1');
    if (document.getElementById('camp-reveal-phone').checked) fd.append('reveal_phone', '1');
    fd.append('lead_source', document.getElementById('camp-source').value);
    if (document.getElementById('camp-global-dedupe').checked) fd.append('global_dedupe', '1');
    fd.append('ml_temperature', document.getElementById('camp-ml-temperature').value);
    fd.append('ml_source', document.getElementById('camp-ml-source').value);
    fd.append('ml_assigned_to', document.getElementById('camp-ml-assigned').value);
    fd.append('my_leads_ids', selectedLeadIds.join(','));
    fd.append('f_titles', document.getElementById('camp-f-titles').value);
    fd.append('f_seniorities', document.getElementById('camp-f-seniorities').value);
    fd.append('f_person_locations', document.getElementById('camp-f-ploc').value);
    fd.append('f_employee_ranges', document.getElementById('camp-f-emp').value);
    fd.append('f_keywords', document.getElementById('camp-f-keywords').value);
    fd.append('f_domains', document.getElementById('camp-f-domains').value);
    fd.append('icp_seniorities', document.getElementById('camp-icp-sen').value);
    fd.append('icp_titles', document.getElementById('camp-icp-titles').value);
    fd.append('icp_employee_min', document.getElementById('camp-icp-empmin').value);
    fd.append('icp_employee_max', document.getElementById('camp-icp-empmax').value);
    if (document.getElementById('camp-icp-website').checked) fd.append('icp_require_website', '1');
    ['decisor','title','size','region','website','technology'].forEach(k => fd.append('w_'+k, document.getElementById('camp-w-'+k).value));

    fetch(BASE + 'crm/saveCampaign', { method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'} })
        .then(r=>r.json()).then(d=>{ btn.disabled=false; if(d.error){alert(d.error);return;} location.reload(); })
        .catch(()=>{ btn.disabled=false; alert('Erro ao salvar.'); });
}

function runCampaign(id, btn) {
    if (!confirm('Executar esta campanha agora? Isso consome créditos do Apollo.')) return;
    btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    fetch(BASE + 'crm/runCampaign/' + id, { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'} })
        .then(r=>r.json()).then(d=>{
            btn.disabled=false; btn.innerHTML='<i class="bi bi-play-fill"></i>';
            if(d.error){alert(d.error);return;}
            const r = d.result || {};
            alert(`Busca: ${r.searched||0} | Duplicados: ${r.duplicated||0} | Fora ICP: ${r.out_of_icp||0} | Score baixo: ${r.low_score||0} | Selecionados: ${r.selected||0} | Captados: ${r.enrolled||0}`);
            location.reload();
        })
        .catch(()=>{ btn.disabled=false; btn.innerHTML='<i class="bi bi-play-fill"></i>'; alert('Erro ao executar.'); });
}

function toggleCampaign(id, btn) {
    fetch(BASE + 'crm/toggleCampaign/' + id, { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'} })
        .then(r=>r.json()).then(d=>{ if(d.error){alert(d.error);return;} location.reload(); });
}

function deleteCampaign(id, btn) {
    if (!confirm('Excluir esta campanha? (os leads já captados permanecem)')) return;
    fetch(BASE + 'crm/deleteCampaign/' + id, { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'} })
        .then(r=>r.json()).then(d=>{ if(d.error){alert(d.error);return;} location.reload(); });
}

function showLog(id) {
    if (!logModal) logModal = new bootstrap.Modal(document.getElementById('campLogModal'));
    const body = document.getElementById('camp-log-body');
    body.innerHTML = 'Carregando...';
    logModal.show();
    fetch(BASE + 'crm/campaignLog/' + id, { headers:{'X-Requested-With':'XMLHttpRequest'} })
        .then(r=>r.json()).then(d=>{
            const rows = d.log || [];
            if (!rows.length) { body.innerHTML = '<div class="text-muted">Sem registros ainda.</div>'; return; }
            body.innerHTML = rows.map(l => `<div class="border-bottom py-1"><span class="badge bg-light text-dark border">${l.action}</span> <small class="text-muted">${l.created_at}</small>${l.detail ? '<div class="small">'+escapeH(l.detail)+'</div>' : ''}</div>`).join('');
        });
}
function escapeH(s){ const d=document.createElement('div'); d.textContent=String(s??''); return d.innerHTML; }

// Diagnóstico: simula a abertura do último e-mail e recarrega os logs
function testEmailOpen(btn) {
    const orig = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    fetch(BASE + 'crm/testEmailOpen', { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'} })
        .then(r=>r.json()).then(d=>{
            btn.disabled = false; btn.innerHTML = orig;
            if (d.error) { alert(d.error); return; }
            alert((d.message||'Registrado.') + '\n\nURL do pixel:\n' + (d.pixel_url||''));
            loadExecLog();
        })
        .catch(()=>{ btn.disabled=false; btn.innerHTML=orig; alert('Erro no diagnóstico.'); });
}
</script>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
