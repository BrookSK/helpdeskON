<?php $pageTitle = 'Demanda #' . $ticket['id'] . ' - ON Solutions Helpdesk'; $currentPage = 'tickets'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<?php
// --- Tag de categoria: mapeia a categoria do ticket para uma cor de badge Bootstrap ---
// suporte=vermelho, desenvolvimento=azul, design=roxo, marketing=laranja/warning, outro=secondary.
if (!function_exists('categoryBadgeClass')) {
    function categoryBadgeClass(?string $category): string
    {
        switch (strtolower(trim((string)$category))) {
            case 'suporte':        return 'bg-danger';
            case 'desenvolvimento': return 'bg-primary';
            case 'design':         return 'text-white'; // roxo aplicado via style inline (não há bg-purple no Bootstrap)
            case 'marketing':      return 'bg-warning text-dark';
            default:               return 'bg-secondary';
        }
    }
}
// Estilo inline extra (roxo para design, pois o Bootstrap não tem bg-purple).
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
        $map = [
            'suporte' => 'Suporte',
            'desenvolvimento' => 'Desenvolvimento',
            'design' => 'Design',
            'marketing' => 'Marketing',
            'outro' => 'Outro',
        ];
        return $map[$c] ?? ucfirst($c);
    }
}
?>
<div class="main-content">
    <div class="top-bar">
        <div>
            <?php
            $ticketDisplayNumber = ($user['role'] === 'client' && !empty($ticket['client_ticket_number']))
                ? $ticket['client_ticket_number']
                : $ticket['id'];
            ?>
            <h5 class="mb-0">Demanda #<?= $ticketDisplayNumber ?></h5>
            <small class="text-muted text-truncate d-block" style="max-width:250px"><?= escape($ticket['title']) ?></small>
        </div>
        <a href="<?= baseUrl('tickets') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
    </div>

    <?php if ($msg = flash('success')): ?>
        <div class="alert alert-success alert-dismissible fade show"><?= escape($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if ($msg = flash('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?= escape($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <?php
    // Papéis de equipe (tudo que não é cliente). Usado para liberar os painéis internos.
    $isTeam = ($user['role'] !== 'client');
    ?>

    <?php if (!empty($ticket['scope_rejected_reason'])): ?>
    <!-- Alerta: escopo recusado pelo cliente -->
    <div class="alert alert-warning"><i class="bi bi-x-circle"></i> <strong>Escopo recusado:</strong> <?= nl2br(escape($ticket['scope_rejected_reason'])) ?></div>
    <?php endif; ?>

    <?php if (!empty($ticket['homolog_denied_reason'])): ?>
    <!-- Alerta: homologação recusada pelo cliente -->
    <div class="alert alert-danger"><i class="bi bi-x-circle"></i> <strong>Homologação recusada:</strong> <?= nl2br(escape($ticket['homolog_denied_reason'])) ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Detalhes + Chat -->
        <div class="col-lg-8">
            <!-- Detalhes -->
            <div class="card mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h6 class="mb-0">Detalhes</h6>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <?php if (!empty($ticket['category'])): ?>
                        <!-- Tag/selo colorido da categoria da demanda -->
                        <span class="badge <?= categoryBadgeClass($ticket['category']) ?>" style="<?= categoryBadgeStyle($ticket['category']) ?>"><?= escape(categoryLabel($ticket['category'])) ?></span>
                        <?php endif; ?>
                        <span class="badge-status badge-<?= $ticket['status'] ?>"><?= statusLabel($ticket['status']) ?></span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-2" style="font-size:0.88rem">
                        <div class="col-sm-6">
                            <strong>Cliente:</strong> <?= escape($ticket['client_name']) ?>
                        </div>
                        <div class="col-sm-6">
                            <strong>Email:</strong> <?= escape($ticket['client_email']) ?>
                        </div>
                        <div class="col-sm-6">
                            <strong>Categoria:</strong> <?= escape($ticket['category'] ?? 'Não definida') ?>
                        </div>
                        <div class="col-sm-6">
                            <strong>Prioridade:</strong> <span class="priority-<?= $ticket['priority'] ?>"><?= priorityLabel($ticket['priority']) ?></span>
                        </div>
                        <div class="col-sm-6">
                            <strong>Atendentes:</strong>
                            <?php if (!empty($assignedAttendants)): ?>
                                <?= escape(implode(', ', array_map(function ($a) { return $a['name']; }, $assignedAttendants))) ?>
                            <?php else: ?>
                                <?= escape($ticket['attendant_name'] ?? 'Não atribuído') ?>
                            <?php endif; ?>
                        </div>
                        <div class="col-sm-6">
                            <strong>Responsável Técnico:</strong> <?= escape($ticket['technical_name'] ?? 'Não atribuído') ?>
                        </div>
                        <div class="col-sm-6">
                            <strong>Criado:</strong> <?= date('d/m/Y H:i', strtotime($ticket['created_at'])) ?>
                        </div>
                    </div>
                    <hr>
                    <h6 class="fw-bold" style="font-size:0.88rem">Descrição</h6>
                    <div class="p-3 bg-light rounded" style="font-size:0.85rem;line-height:1.6"><?= nl2br(escape($ticket['description'])) ?></div>

                    <?php if (!empty($ticket['transcription'])): ?>
                    <div class="mt-3">
                        <h6 class="fw-bold" style="font-size:0.88rem"><i class="bi bi-mic"></i> Transcrição Original</h6>
                        <div class="p-3 rounded border" style="font-size:0.83rem;line-height:1.6;background:#f0faf8;border-color:#b2f2e8 !important;">
                            <?= nl2br(escape($ticket['transcription'])) ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($user['role'] === 'client' && $ticket['status'] === 'aguardando_aprovacao_escopo'): ?>
            <!-- Aprovação de escopo pelo CLIENTE (modo leitura + decidir) -->
            <div class="card mb-4 border-primary">
                <div class="card-header bg-white"><h6 class="mb-0"><i class="bi bi-file-earmark-text text-primary"></i> Aprovação de Escopo</h6></div>
                <div class="card-body">
                    <p class="small text-muted">Revise o escopo abaixo e aprove para iniciarmos, ou recuse informando o motivo.</p>
                    <div class="mb-3">
                        <h6 class="fw-bold" style="font-size:0.85rem">O que será desenvolvido</h6>
                        <div class="p-2 bg-light rounded" style="font-size:0.85rem"><?= !empty($ticket['escopo_incluido']) ? nl2br(escape($ticket['escopo_incluido'])) : '<span class="text-muted">—</span>' ?></div>
                    </div>
                    <div class="mb-3">
                        <h6 class="fw-bold" style="font-size:0.85rem">O que NÃO será desenvolvido</h6>
                        <div class="p-2 bg-light rounded" style="font-size:0.85rem"><?= !empty($ticket['escopo_excluido']) ? nl2br(escape($ticket['escopo_excluido'])) : '<span class="text-muted">—</span>' ?></div>
                    </div>
                    <div class="mb-3">
                        <h6 class="fw-bold" style="font-size:0.85rem">Como será executado</h6>
                        <div class="p-2 bg-light rounded" style="font-size:0.85rem"><?= !empty($ticket['escopo_execucao']) ? nl2br(escape($ticket['escopo_execucao'])) : '<span class="text-muted">—</span>' ?></div>
                    </div>
                    <div class="mb-3">
                        <strong style="font-size:0.85rem">Estimativa:</strong>
                        <?= !empty($ticket['estimativa_dias']) ? (int)$ticket['estimativa_dias'] . ' dia(s)' : '<span class="text-muted">—</span>' ?>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <!-- Aprovar escopo: segue para "Em andamento" -->
                        <form action="<?= baseUrl('tickets/updateStatus/' . $ticket['id']) ?>" method="POST">
                            <?= csrf_field() ?>
                            <input type="hidden" name="status" value="in_progress">
                            <button type="submit" class="btn btn-success btn-sm"><i class="bi bi-check-lg"></i> Aprovar escopo</button>
                        </form>
                        <!-- Recusar escopo: abre o campo de motivo -->
                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="document.getElementById('scope-reject-box').classList.toggle('d-none')">
                            <i class="bi bi-x-lg"></i> Recusar
                        </button>
                    </div>
                    <div id="scope-reject-box" class="mt-3 d-none">
                        <!-- Recusa do escopo: volta a demanda para "Aberto" (status=open),
                             com o motivo, para a equipe refazer o escopo e reenviar. -->
                        <form action="<?= baseUrl('tickets/updateStatus/' . $ticket['id']) ?>" method="POST">
                            <?= csrf_field() ?>
                            <input type="hidden" name="status" value="open">
                            <input type="hidden" name="reject" value="1">
                            <label class="form-label fw-medium small">Motivo da recusa *</label>
                            <textarea name="reason" class="form-control form-control-sm mb-2" rows="3" required placeholder="Explique o que precisa ser ajustado no escopo"></textarea>
                            <button type="submit" class="btn btn-danger btn-sm"><i class="bi bi-send"></i> Enviar recusa</button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Anexos -->
            <?php if (!empty($attachments)): ?>
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="bi bi-paperclip"></i> Anexos (<?= count($attachments) ?>)</h6>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <?php foreach ($attachments as $att): ?>
                        <div class="col-6 col-md-4">
                            <div class="border rounded p-2 text-center">
                                <?php if (strpos($att['file_type'], 'image') !== false): ?>
                                    <a href="<?= baseUrl($att['file_path']) ?>" target="_blank">
                                        <img src="<?= baseUrl($att['file_path']) ?>" class="img-fluid rounded" style="max-height:100px;object-fit:cover" alt="Anexo">
                                    </a>
                                <?php else: ?>
                                    <a href="<?= baseUrl($att['file_path']) ?>" target="_blank" class="text-decoration-none">
                                        <i class="bi bi-file-earmark fs-2 text-muted"></i>
                                    </a>
                                <?php endif; ?>
                                <div class="small text-muted mt-1 text-truncate"><?= escape($att['file_name']) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if (in_array($user['role'], ['super_admin', 'attendant'])): ?>
            <!-- Observações Internas -->
            <div class="card mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-journal-text"></i> Observações Internas</h6>
                    <span class="badge bg-warning text-dark" style="font-size:0.65rem"><i class="bi bi-lock-fill"></i> Visível só para equipe</span>
                </div>
                <div class="card-body">
                    <div id="internal-notes-container" style="max-height:300px;overflow-y:auto;">
                        <?php foreach ($internalNotes ?? [] as $note): ?>
                        <div class="d-flex gap-2 mb-3">
                            <div class="rounded-circle bg-warning d-flex align-items-center justify-content-center flex-shrink-0" style="width:30px;height:30px;">
                                <i class="bi bi-person-fill text-dark" style="font-size:0.7rem;"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-center">
                                    <strong style="font-size:0.8rem"><?= escape($note['user_name']) ?></strong>
                                    <small class="text-muted" style="font-size:0.7rem"><?= date('d/m/Y H:i', strtotime($note['created_at'])) ?></small>
                                </div>
                                <div class="p-2 rounded mt-1" style="font-size:0.83rem;background:#fff8e1;border:1px solid #ffe082;"><?= nl2br(escape($note['note'])) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($internalNotes)): ?>
                        <p class="text-muted small text-center mb-0" id="no-notes">Nenhuma observação interna.</p>
                        <?php endif; ?>
                    </div>
                    <div class="mt-3 d-flex gap-2 align-items-end">
                        <textarea id="note-input" class="form-control form-control-sm" placeholder="Escreva uma observação interna..." rows="2" style="resize:vertical;" onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();addInternalNote();}"></textarea>
                        <button type="button" onclick="addInternalNote()" class="btn btn-warning btn-sm px-3" title="Adicionar observação" style="height:fit-content;">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Chat -->
            <div class="card">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="bi bi-chat-dots"></i> Chat</h6>
                </div>
                <div class="card-body">
                    <div class="chat-container" id="chat-container">
                        <?php foreach ($messages as $msg): ?>
                        <div class="chat-message <?= $msg['user_id'] == $user['id'] ? 'mine' : 'other' ?>">
                            <div class="chat-bubble">
                                <div class="chat-sender"><?= escape($msg['user_name']) ?></div>
                                <?= nl2br(escape($msg['message'])) ?>
                                <div class="chat-time"><?= date('d/m H:i', strtotime($msg['created_at'])) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($messages)): ?>
                        <p class="text-center text-muted mb-0" id="no-messages">Nenhuma mensagem. Inicie a conversa!</p>
                        <?php endif; ?>
                    </div>
                    <div class="mt-3 d-flex gap-2">
                        <input type="text" id="chat-input" class="form-control form-control-sm" placeholder="Digite sua mensagem..." onkeypress="if(event.key==='Enter')sendMessage()">
                        <button type="button" onclick="sendMessage()" class="btn btn-primary btn-sm px-3">
                            <i class="bi bi-send"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar de ações -->
        <div class="col-lg-4">
            <?php
            $fullUserView = (new User())->findById($user['id']);
            $isCompanyOwner = ($user['role'] === 'client' && $fullUserView && $fullUserView['is_company_owner']);
            $canEditPriority = in_array($user['role'], ['super_admin']) || $isCompanyOwner;
            $canEditStatus = in_array($user['role'], ['super_admin', 'attendant']);
            ?>

            <?php if ($canEditStatus): ?>
            <!-- Alterar Status -->
            <div class="card mb-3">
                <div class="card-header bg-white"><h6 class="mb-0" style="font-size:0.88rem">Alterar Status</h6></div>
                <div class="card-body">
                    <form action="<?= baseUrl('tickets/updateStatus/' . $ticket['id']) ?>" method="POST">
                        <?= csrf_field() ?>
                        <select name="status" class="form-select form-select-sm mb-2">
                            <option value="open" <?= $ticket['status'] === 'open' ? 'selected' : '' ?>>Aberto</option>
                            <option value="aguardando_aprovacao_escopo" <?= $ticket['status'] === 'aguardando_aprovacao_escopo' ? 'selected' : '' ?>>Aprovação de Escopo</option>
                            <option value="in_progress" <?= $ticket['status'] === 'in_progress' ? 'selected' : '' ?>>Em andamento</option>
                            <option value="em_revisao_interna" <?= $ticket['status'] === 'em_revisao_interna' ? 'selected' : '' ?>>Em Revisão Interna</option>
                            <option value="waiting_client" <?= $ticket['status'] === 'waiting_client' ? 'selected' : '' ?>>Aguardando cliente</option>
                            <option value="em_homologacao" <?= $ticket['status'] === 'em_homologacao' ? 'selected' : '' ?>>Em Homologação</option>
                            <option value="aprovado_producao" <?= $ticket['status'] === 'aprovado_producao' ? 'selected' : '' ?>>Aprovado para Produção</option>
                            <option value="completed" <?= $ticket['status'] === 'completed' ? 'selected' : '' ?>>Concluído</option>
                            <option value="denied" <?= $ticket['status'] === 'denied' ? 'selected' : '' ?>>Negado</option>
                            <option value="archived" <?= $ticket['status'] === 'archived' ? 'selected' : '' ?>>Arquivado</option>
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm w-100">Atualizar</button>
                    </form>
                </div>
            </div>
            <?php elseif ($user['role'] === 'client' && $ticket['status'] === 'em_homologacao'): ?>
            <!-- Cliente pode aprovar/recusar quando está em homologação -->
            <div class="card mb-3 border-success">
                <div class="card-header bg-white"><h6 class="mb-0" style="font-size:0.88rem"><i class="bi bi-check-circle text-success"></i> Homologação</h6></div>
                <div class="card-body">
                    <p class="small text-muted mb-2">Esta demanda está em homologação. Teste e aprove ou solicite ajustes.</p>
                    <?php if (!empty($ticket['previsao_publicacao'])): ?>
                    <p class="small mb-2"><i class="bi bi-calendar-event"></i> <strong>Previsão de publicação:</strong> <?= date('d/m/Y', strtotime($ticket['previsao_publicacao'])) ?></p>
                    <?php endif; ?>
                    <!-- Aprovar para produção -->
                    <form action="<?= baseUrl('tickets/updateStatus/' . $ticket['id']) ?>" method="POST" class="mb-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="status" value="aprovado_producao">
                        <button type="submit" class="btn btn-success btn-sm w-100"><i class="bi bi-check-lg"></i> Aprovar para Produção</button>
                    </form>
                    <!-- Recusar homologação: motivo obrigatório -->
                    <button type="button" class="btn btn-outline-danger btn-sm w-100" onclick="document.getElementById('homolog-reject-box').classList.toggle('d-none')">
                        <i class="bi bi-x-lg"></i> Reprovar / Solicitar ajustes
                    </button>
                    <div id="homolog-reject-box" class="mt-2 d-none">
                        <form action="<?= baseUrl('tickets/updateStatus/' . $ticket['id']) ?>" method="POST">
                            <?= csrf_field() ?>
                            <input type="hidden" name="status" value="denied">
                            <label class="form-label fw-medium small">Motivo da recusa *</label>
                            <textarea name="reason" class="form-control form-control-sm mb-2" rows="3" required placeholder="Descreva o que precisa ser ajustado"></textarea>
                            <button type="submit" class="btn btn-danger btn-sm w-100"><i class="bi bi-send"></i> Enviar recusa</button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($isTeam): ?>
            <!-- ===== Escopo técnico (EQUIPE) ===== -->
            <div class="card mb-3">
                <div class="card-header bg-white"><h6 class="mb-0" style="font-size:0.88rem"><i class="bi bi-file-earmark-text"></i> Escopo técnico</h6></div>
                <div class="card-body">
                    <form action="<?= baseUrl('tickets/saveScope/' . $ticket['id']) ?>" method="POST">
                        <?= csrf_field() ?>
                        <div class="mb-2">
                            <label class="form-label fw-medium small">O que será desenvolvido</label>
                            <textarea name="escopo_incluido" class="form-control form-control-sm" rows="3"><?= escape($ticket['escopo_incluido'] ?? '') ?></textarea>
                        </div>
                        <div class="mb-2">
                            <label class="form-label fw-medium small">O que NÃO será desenvolvido</label>
                            <textarea name="escopo_excluido" class="form-control form-control-sm" rows="3"><?= escape($ticket['escopo_excluido'] ?? '') ?></textarea>
                        </div>
                        <div class="mb-2">
                            <label class="form-label fw-medium small">Como será executado</label>
                            <textarea name="escopo_execucao" class="form-control form-control-sm" rows="3"><?= escape($ticket['escopo_execucao'] ?? '') ?></textarea>
                        </div>
                        <div class="mb-2">
                            <label class="form-label fw-medium small">Estimativa (dias)</label>
                            <input type="number" name="estimativa_dias" class="form-control form-control-sm" min="0" value="<?= escape((string)($ticket['estimativa_dias'] ?? '')) ?>">
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            <button type="submit" class="btn btn-outline-primary btn-sm">Salvar escopo</button>
                            <button type="submit" name="send_to_client" value="1" class="btn btn-primary btn-sm">Enviar ao cliente para aprovação</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ===== Previsão de publicação (EQUIPE) ===== -->
            <div class="card mb-3">
                <div class="card-header bg-white"><h6 class="mb-0" style="font-size:0.88rem"><i class="bi bi-calendar-event"></i> Previsão de publicação</h6></div>
                <div class="card-body">
                    <?php if (!empty($ticket['previsao_publicacao'])): ?>
                    <p class="small mb-2"><strong>Atual:</strong> <?= date('d/m/Y', strtotime($ticket['previsao_publicacao'])) ?></p>
                    <?php endif; ?>
                    <form action="<?= baseUrl('tickets/savePrevisao/' . $ticket['id']) ?>" method="POST" class="d-flex gap-2 align-items-end flex-wrap">
                        <?= csrf_field() ?>
                        <div class="flex-grow-1">
                            <label class="form-label fw-medium small">Data</label>
                            <input type="date" name="previsao_publicacao" class="form-control form-control-sm" value="<?= escape(!empty($ticket['previsao_publicacao']) ? date('Y-m-d', strtotime($ticket['previsao_publicacao'])) : '') ?>">
                        </div>
                        <button type="submit" class="btn btn-outline-primary btn-sm">Salvar</button>
                    </form>
                </div>
            </div>

            <!-- ===== Suporte (EQUIPE) ===== -->
            <div class="card mb-3 <?= SupportRules::isSupportCategory($ticket['category'] ?? null) ? 'border-danger' : '' ?>">
                <div class="card-header bg-white"><h6 class="mb-0" style="font-size:0.88rem"><i class="bi bi-life-preserver"></i> Suporte</h6></div>
                <div class="card-body">
                    <?php if (!empty($ticket['support_severity'])): ?>
                    <p class="small mb-2"><strong>Gravidade atual:</strong> <?= escape(SupportRules::severityLabel($ticket['support_severity'])) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($ticket['support_analysis_due_at'])): ?>
                    <p class="small mb-1"><strong>Prazo de análise:</strong> <?= date('d/m/Y H:i', strtotime($ticket['support_analysis_due_at'])) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($ticket['support_resolution_due_at'])): ?>
                    <p class="small mb-2"><strong>Prazo de resolução:</strong> <?= date('d/m/Y H:i', strtotime($ticket['support_resolution_due_at'])) ?></p>
                    <?php endif; ?>
                    <form action="<?= baseUrl('tickets/saveSupport/' . $ticket['id']) ?>" method="POST">
                        <?= csrf_field() ?>
                        <div class="mb-2">
                            <label class="form-label fw-medium small">Gravidade</label>
                            <select name="support_severity" class="form-select form-select-sm">
                                <option value="" <?= empty($ticket['support_severity']) ? 'selected' : '' ?>>— sem gravidade —</option>
                                <?php foreach (SupportRules::SEVERITIES as $sev): ?>
                                <option value="<?= $sev ?>" <?= ($ticket['support_severity'] ?? '') === $sev ? 'selected' : '' ?>><?= escape(SupportRules::severityLabel($sev)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label fw-medium small">Prazo de resolução (min, 30 a 2880)</label>
                            <input type="number" name="support_resolution_minutes" class="form-control form-control-sm" min="30" max="2880">
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="clear_resolution" value="1" id="support-clear-resolution">
                            <label class="form-check-label small" for="support-clear-resolution">Limpar prazo de resolução</label>
                        </div>
                        <div class="mb-2">
                            <label class="form-label fw-medium small">Solução temporária</label>
                            <textarea name="support_workaround" class="form-control form-control-sm" rows="2"><?= escape($ticket['support_workaround'] ?? '') ?></textarea>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="is_third_party" value="1" id="support-third-party" <?= !empty($ticket['is_third_party']) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="support-third-party">Problema de terceiros</label>
                        </div>
                        <div class="mb-2">
                            <label class="form-label fw-medium small">Terceiro responsável</label>
                            <input type="text" name="third_party_name" class="form-control form-control-sm" value="<?= escape($ticket['third_party_name'] ?? '') ?>">
                        </div>
                        <div class="mb-2">
                            <label class="form-label fw-medium small">Andamento/evidências</label>
                            <textarea name="third_party_notes" class="form-control form-control-sm" rows="2"><?= escape($ticket['third_party_notes'] ?? '') ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-outline-primary btn-sm w-100">Salvar suporte</button>
                    </form>
                </div>
            </div>

            <!-- ===== Demandas relacionadas (EQUIPE) ===== -->
            <div class="card mb-3">
                <div class="card-header bg-white"><h6 class="mb-0" style="font-size:0.88rem"><i class="bi bi-diagram-3"></i> Demandas relacionadas</h6></div>
                <div class="card-body">
                    <?php
                    // Rótulos pt-BR dos tipos de relação.
                    $relationTypeLabels = [
                        'suporte' => 'Suporte',
                        'incidente' => 'Incidente',
                        'correcao' => 'Correção',
                        'relacionado' => 'Relacionado',
                    ];
                    ?>
                    <?php if (!empty($relations)): ?>
                    <ul class="list-unstyled mb-3">
                        <?php foreach ($relations as $r): ?>
                        <li class="border rounded p-2 mb-2">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div class="flex-grow-1">
                                    <a href="<?= baseUrl('tickets/show/' . $r['other_id']) ?>" class="text-decoration-none fw-medium" style="font-size:0.82rem">#<?= (int)$r['other_id'] ?> — <?= escape($r['other_title']) ?></a>
                                    <div class="d-flex gap-1 align-items-center flex-wrap mt-1">
                                        <span class="badge bg-info text-dark" style="font-size:0.65rem"><?= escape($relationTypeLabels[$r['relation_type']] ?? $r['relation_type']) ?></span>
                                        <span class="badge-status badge-<?= $r['other_status'] ?>" style="font-size:0.65rem"><?= statusLabel($r['other_status']) ?></span>
                                    </div>
                                </div>
                                <form action="<?= baseUrl('tickets/unrelate/' . $r['id']) ?>" method="POST" class="flex-shrink-0">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-1" title="Remover relação"><i class="bi bi-x-lg"></i></button>
                                </form>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php else: ?>
                    <p class="text-muted small">Nenhuma demanda relacionada.</p>
                    <?php endif; ?>
                    <form action="<?= baseUrl('tickets/relate/' . $ticket['id']) ?>" method="POST">
                        <?= csrf_field() ?>
                        <div class="mb-2">
                            <label class="form-label fw-medium small">ID da demanda</label>
                            <input type="number" name="target_ticket_id" class="form-control form-control-sm" min="1" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label fw-medium small">Tipo de relação</label>
                            <select name="relation_type" class="form-select form-select-sm">
                                <option value="suporte">Suporte</option>
                                <option value="incidente">Incidente</option>
                                <option value="correcao">Correção</option>
                                <option value="relacionado" selected>Relacionado</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-outline-primary btn-sm w-100">Relacionar</button>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($canEditPriority): ?>
            <!-- Alterar Prioridade -->
            <div class="card mb-3">
                <div class="card-header bg-white"><h6 class="mb-0" style="font-size:0.88rem">Alterar Prioridade</h6></div>
                <div class="card-body">
                    <form action="<?= baseUrl('tickets/updatePriority/' . $ticket['id']) ?>" method="POST">
                        <?= csrf_field() ?>
                        <select name="priority" class="form-select form-select-sm mb-2">
                            <option value="low" <?= $ticket['priority'] === 'low' ? 'selected' : '' ?>>Baixa</option>
                            <option value="medium" <?= $ticket['priority'] === 'medium' ? 'selected' : '' ?>>Média</option>
                            <option value="high" <?= $ticket['priority'] === 'high' ? 'selected' : '' ?>>Alta</option>
                            <option value="urgent" <?= $ticket['priority'] === 'urgent' ? 'selected' : '' ?>>Urgente</option>
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm w-100">Atualizar</button>
                    </form>
                </div>
            </div>

            <?php endif; ?>

            <?php if ($canEditStatus): ?>
            <!-- Atribuir Atendente -->
            <div class="card mb-3">
                <div class="card-header bg-white"><h6 class="mb-0" style="font-size:0.88rem">Atribuir Atendentes</h6></div>
                <div class="card-body">
                    <form action="<?= baseUrl('tickets/assign/' . $ticket['id']) ?>" method="POST">
                        <?= csrf_field() ?>
                        <div class="border rounded-3 p-2 mb-2" style="max-height:180px;overflow-y:auto">
                            <?php foreach ($attendants as $att): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="attendant_ids[]" value="<?= $att['id'] ?>" id="assign-att-<?= $att['id'] ?>" <?= in_array((int)$att['id'], $assignedAttendantIds ?? [], true) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="assign-att-<?= $att['id'] ?>">
                                    <?= escape($att['name']) ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <small class="text-muted d-block mb-2">O primeiro marcado será o atendente principal.</small>
                        <button type="submit" class="btn btn-outline-primary btn-sm w-100">Atribuir</button>
                    </form>
                </div>
            </div>

            <!-- Atribuir Responsável Técnico (hierarquia Papel > Usuários) -->
            <div class="card mb-3">
                <div class="card-header bg-white"><h6 class="mb-0" style="font-size:0.88rem">Responsável Técnico</h6></div>
                <div class="card-body">
                    <form action="<?= baseUrl('tickets/assignTechnical/' . $ticket['id']) ?>" method="POST">
                        <?= csrf_field() ?>
                        <select name="technical_responsible_id" class="form-select form-select-sm mb-2">
                            <option value="">Não atribuído</option>
                            <?php foreach (($technicalGrouped ?? []) as $roleKey => $usersInRole): ?>
                            <optgroup label="<?= roleLabel($roleKey) ?>">
                                <?php foreach ($usersInRole as $tu): ?>
                                <option value="<?= $tu['id'] ?>" <?= ($ticket['technical_responsible_id'] ?? '') == $tu['id'] ? 'selected' : '' ?>>
                                    <?= escape($tu['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </optgroup>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-outline-primary btn-sm w-100">Atribuir</button>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <!-- Upload de anexo -->
            <div class="card mb-3">
                <div class="card-header bg-white"><h6 class="mb-0" style="font-size:0.88rem">Enviar Anexo</h6></div>
                <div class="card-body">
                    <input type="file" id="upload-file" class="form-control form-control-sm mb-2" accept="image/*,video/*,.pdf,.doc,.docx">
                    <button type="button" onclick="uploadFile()" class="btn btn-outline-primary btn-sm w-100">
                        <i class="bi bi-upload"></i> Enviar
                    </button>
                    <div id="upload-result" class="mt-2"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const ticketId = <?= $ticket['id'] ?>;
const userId = <?= $user['id'] ?>;
let lastMessageId = <?= !empty($messages) ? end($messages)['id'] : 0 ?>;

function sendMessage() {
    const input = document.getElementById('chat-input');
    const message = input.value.trim();
    if (!message) return;

    const formData = new FormData();
    formData.append('message', message);

    fetch('<?= baseUrl("tickets/sendMessage/") ?>' + ticketId, {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            appendMessage(data.message, true);
            input.value = '';
            lastMessageId = data.message.id;
            const noMsg = document.getElementById('no-messages');
            if (noMsg) noMsg.remove();
        }
    });
}

function appendMessage(msg, isMine) {
    const container = document.getElementById('chat-container');
    const div = document.createElement('div');
    div.className = 'chat-message ' + (isMine ? 'mine' : 'other');
    div.innerHTML = `<div class="chat-bubble">
        <div class="chat-sender">${msg.user_name}</div>
        ${msg.message}
        <div class="chat-time">${msg.created_at}</div>
    </div>`;
    container.appendChild(div);
    container.scrollTop = container.scrollHeight;
}

// Polling
setInterval(() => {
    fetch(`<?= baseUrl("tickets/getMessages/") ?>${ticketId}?last_id=${lastMessageId}`)
        .then(r => r.json())
        .then(data => {
            if (data.messages && data.messages.length > 0) {
                data.messages.forEach(msg => {
                    if (msg.user_id != userId) {
                        appendMessage({
                            user_name: msg.user_name,
                            message: msg.message,
                            created_at: new Date(msg.created_at).toLocaleString('pt-BR', {day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'})
                        }, false);
                    }
                    lastMessageId = msg.id;
                });
                const noMsg = document.getElementById('no-messages');
                if (noMsg) noMsg.remove();
            }
        });
}, 5000);

function uploadFile() {
    const fileInput = document.getElementById('upload-file');
    if (!fileInput.files[0]) return;

    const formData = new FormData();
    formData.append('file', fileInput.files[0]);

    fetch('<?= baseUrl("tickets/uploadAttachment/") ?>' + ticketId, {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        const result = document.getElementById('upload-result');
        if (data.success) {
            result.innerHTML = '<div class="text-success small"><i class="bi bi-check-circle"></i> Enviado!</div>';
            fileInput.value = '';
            setTimeout(() => location.reload(), 1200);
        } else {
            result.innerHTML = '<div class="text-danger small">' + (data.error || 'Erro') + '</div>';
        }
    });
}

// Scroll chat
document.getElementById('chat-container').scrollTop = document.getElementById('chat-container').scrollHeight;

// Observações internas
function addInternalNote() {
    const input = document.getElementById('note-input');
    if (!input) return;
    const note = input.value.trim();
    if (!note) return;

    const formData = new FormData();
    formData.append('note', note);

    fetch('<?= baseUrl("tickets/addNote/") ?>' + ticketId, {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const container = document.getElementById('internal-notes-container');
            const noNotes = document.getElementById('no-notes');
            if (noNotes) noNotes.remove();

            // Escapar HTML e converter quebras de linha em <br>
            const escapeHtml = (str) => str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
            const noteText = escapeHtml(data.note.note).replace(/\n/g, '<br>');

            const noteHtml = `
                <div class="d-flex gap-2 mb-3">
                    <div class="rounded-circle bg-warning d-flex align-items-center justify-content-center flex-shrink-0" style="width:30px;height:30px;">
                        <i class="bi bi-person-fill text-dark" style="font-size:0.7rem;"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-center">
                            <strong style="font-size:0.8rem">${escapeHtml(data.note.user_name)}</strong>
                            <small class="text-muted" style="font-size:0.7rem">${data.note.created_at}</small>
                        </div>
                        <div class="p-2 rounded mt-1" style="font-size:0.83rem;background:#fff8e1;border:1px solid #ffe082;">${noteText}</div>
                    </div>
                </div>`;
            container.insertAdjacentHTML('beforeend', noteHtml);
            container.scrollTop = container.scrollHeight;
            input.value = '';
        }
    });
}
</script>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
