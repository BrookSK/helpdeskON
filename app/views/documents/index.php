<?php $pageTitle = 'Documentos - ON Solutions Helpdesk'; $currentPage = 'documents'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0">Documentos</h5>
            <small class="text-muted">Documentos compartilhados</small>
        </div>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#uploadModal">
            <i class="bi bi-upload"></i> Enviar Documento
        </button>
    </div>

    <?php if ($msg = flash('success')): ?>
        <div class="alert alert-success alert-dismissible fade show"><?= escape($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if ($msg = flash('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?= escape($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body p-0">
            <?php if (!empty($documents)): ?>
            <div class="table-responsive d-none d-md-block">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Documento</th>
                            <th>Enviado por</th>
                            <?php if (in_array($user['role'], ['super_admin', 'attendant'])): ?>
                            <th>Empresa</th>
                            <?php endif; ?>
                            <th>Tamanho</th>
                            <th>Data</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($documents as $doc): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-file-earmark text-muted"></i>
                                    <div>
                                        <div class="fw-medium" style="font-size:0.85rem"><?= escape($doc['title']) ?></div>
                                        <div class="text-muted" style="font-size:0.72rem"><?= escape($doc['file_name']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td style="font-size:0.85rem"><?= escape($doc['uploaded_by']) ?></td>
                            <?php if (in_array($user['role'], ['super_admin', 'attendant'])): ?>
                            <td style="font-size:0.85rem"><?= escape($doc['company_name'] ?? '-') ?></td>
                            <?php endif; ?>
                            <td style="font-size:0.85rem"><?= number_format(($doc['file_size'] ?? 0) / 1024, 0) ?> KB</td>
                            <td style="font-size:0.85rem"><?= date('d/m/Y', strtotime($doc['created_at'])) ?></td>
                            <td>
                                <a href="<?= baseUrl($doc['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="Download">
                                    <i class="bi bi-download"></i>
                                </a>
                                <?php if ($doc['user_id'] == $user['id'] || $user['role'] === 'super_admin'): ?>
                                <a href="<?= baseUrl('documents/delete/' . $doc['id']) ?>" class="btn btn-sm btn-outline-danger" title="Excluir" onclick="return confirm('Excluir este documento?')">
                                    <i class="bi bi-trash"></i>
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <!-- Mobile -->
            <div class="d-md-none p-3">
                <?php foreach ($documents as $doc): ?>
                <div class="border rounded-3 p-3 mb-2">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="fw-medium" style="font-size:0.85rem"><?= escape($doc['title']) ?></div>
                            <div class="text-muted" style="font-size:0.72rem"><?= escape($doc['file_name']) ?></div>
                            <div class="text-muted mt-1" style="font-size:0.72rem">
                                Por <?= escape($doc['uploaded_by']) ?> · <?= timeAgo($doc['created_at']) ?>
                            </div>
                        </div>
                        <div class="d-flex gap-1">
                            <a href="<?= baseUrl($doc['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-download"></i></a>
                            <?php if ($doc['user_id'] == $user['id'] || $user['role'] === 'super_admin'): ?>
                            <a href="<?= baseUrl('documents/delete/' . $doc['id']) ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Excluir?')"><i class="bi bi-trash"></i></a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="text-center text-muted py-5">
                <i class="bi bi-folder2-open fs-2"></i>
                <p class="mt-2 mb-0">Nenhum documento encontrado</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Upload -->
<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Enviar Documento</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= baseUrl('documents/upload') ?>" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-medium small">Título *</label>
                        <input type="text" name="title" class="form-control form-control-sm" required placeholder="Ex: Contrato de serviço">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium small">Descrição</label>
                        <input type="text" name="description" class="form-control form-control-sm" placeholder="Opcional">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium small">Arquivo *</label>
                        <input type="file" name="document" class="form-control form-control-sm" required accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt">
                        <small class="text-muted">Máx. 20MB. PDF, DOC, XLS, imagens, etc.</small>
                    </div>
                    <?php if (in_array($user['role'], ['super_admin', 'attendant'])): ?>
                    <div class="mb-3">
                        <label class="form-label fw-medium small">Empresa destino</label>
                        <select name="company_id" class="form-select form-select-sm">
                            <option value="">Geral (todas)</option>
                            <?php
                            $companies = (new Company())->getAll();
                            foreach ($companies as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= escape($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium small">Visibilidade</label>
                        <select name="visibility" class="form-select form-select-sm">
                            <option value="all">Todos</option>
                            <option value="company">Apenas empresa selecionada</option>
                            <option value="team">Apenas equipe interna</option>
                        </select>
                    </div>
                    <?php else: ?>
                    <input type="hidden" name="visibility" value="all">
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-upload"></i> Enviar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
