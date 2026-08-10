<?php $pageTitle = 'CRM - Boards'; $currentPage = 'crm'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0"><i class="bi bi-kanban"></i> CRM</h5>
            <small class="text-muted">Gerencie seus leads e contatos</small>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= baseUrl('crm/dashboard') ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-graph-up"></i> Dashboard CRM</a>
            <a href="<?= baseUrl('whatsapp/chat') ?>" class="btn btn-sm btn-outline-success"><i class="bi bi-whatsapp"></i> Chat</a>
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#newBoardModal">
                <i class="bi bi-plus-lg"></i> Novo Board
            </button>
        </div>
    </div>

    <div class="row g-3">
        <?php if (empty($boards)): ?>
        <div class="col-12">
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="bi bi-kanban" style="font-size:3rem;color:#ccc;"></i>
                    <h6 class="mt-3">Nenhum board criado</h6>
                    <p class="text-muted">Crie seu primeiro board para organizar seus leads.</p>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newBoardModal">
                        <i class="bi bi-plus-lg"></i> Criar Board
                    </button>
                </div>
            </div>
        </div>
        <?php else: ?>
        <?php foreach ($boards as $board): ?>
        <div class="col-md-6 col-lg-4">
            <div class="card board-card" onclick="window.location='<?= baseUrl('crm/board/' . $board['id']) ?>'" style="cursor:pointer;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h6 class="mb-0"><?= escape($board['name']) ?></h6>
                        <span class="badge bg-primary rounded-pill"><?= $board['total_cards'] ?? 0 ?> cards</span>
                    </div>
                    <?php if ($board['description']): ?>
                    <p class="text-muted small mb-2"><?= escape($board['description']) ?></p>
                    <?php endif; ?>
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <small class="text-muted">
                            <i class="bi bi-person"></i> <?= escape($board['created_by_name'] ?? 'Sistema') ?>
                        </small>
                        <small class="text-muted"><?= timeAgo($board['created_at']) ?></small>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Novo Board -->
<div class="modal fade" id="newBoardModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="<?= baseUrl('crm/createBoard') ?>" method="POST">
                <div class="modal-header">
                    <h6 class="modal-title">Novo Board CRM</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Nome *</label>
                        <input type="text" name="name" class="form-control form-control-sm" required placeholder="ex: Leads WhatsApp">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Descrição</label>
                        <textarea name="description" class="form-control form-control-sm" rows="3" placeholder="Descrição opcional do board..."></textarea>
                    </div>
                    <div class="alert alert-info small py-2">
                        <i class="bi bi-info-circle"></i> Colunas padrão serão criadas automaticamente: Novo Lead, Contato Feito, Em Negociação, Fechado, Perdido. Você pode personalizar depois.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-primary">Criar Board</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.board-card { transition: transform 0.2s, box-shadow 0.2s; }
.board-card:hover { transform: translateY(-3px); box-shadow: 0 6px 16px rgba(0,0,0,0.1); }
</style>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
