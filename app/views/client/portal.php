<?php $pageTitle = 'Contratos e cobranças - ON Solutions Helpdesk'; $currentPage = 'portal'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>
<?php
$brl = fn($v) => 'R$ ' . number_format((float)$v, 2, ',', '.');
$dt = fn($d) => $d ? date('d/m/Y', strtotime($d)) : '—';
$chargeStatus = ['pending' => ['A pagar', 'warning text-dark'], 'paid' => ['Paga', 'success'], 'overdue' => ['Vencida', 'danger']];
$projectStatus = ['planning' => 'Planejamento', 'in_progress' => 'Em andamento', 'delivered' => 'Entregue', 'warranty' => 'Em garantia', 'closed' => 'Encerrado'];
?>
<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0">Contratos e cobranças</h5>
            <small class="text-muted">Contratos assinados, cobranças e projetos da sua empresa</small>
        </div>
    </div>

    <?php if (!$hasCompany): ?>
    <div class="alert alert-info">Seu usuário ainda não está ligado a uma empresa. Fale com a equipe.</div>
    <?php else: ?>

    <div class="card mb-3" id="portal-contracts">
        <div class="card-header py-2"><strong><i class="bi bi-file-earmark-check"></i> Contratos assinados</strong></div>
        <div class="card-body p-0">
            <table class="table table-sm mb-0 align-middle">
                <thead class="table-light"><tr><th>Contrato</th><th>Assinado em</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($contracts as $c): ?>
                    <tr>
                        <td><?= escape($c['title']) ?></td>
                        <td><?= $dt($c['signed_at']) ?></td>
                        <td class="text-end">
                            <?php if ($c['signed_file_status'] === 'stored'): ?>
                            <a class="btn btn-sm btn-outline-primary" href="<?= baseUrl('portal/contract/' . (int)$c['id']) ?>" target="_blank">PDF assinado</a>
                            <?php else: ?><span class="text-muted small">PDF em preparação</span><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($contracts)): ?><tr><td colspan="3" class="text-center text-muted py-3">Nenhum contrato assinado.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card mb-3" id="portal-charges">
        <div class="card-header py-2"><strong><i class="bi bi-receipt"></i> Cobranças</strong></div>
        <div class="card-body p-0">
            <table class="table table-sm mb-0 align-middle">
                <thead class="table-light"><tr><th>Descrição</th><th>Valor</th><th>Vencimento</th><th>Situação</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($charges as $c):
                    $isSubscription = $c['kind'] === 'recurring' && !empty($c['asaas_subscription_id']) && empty($c['asaas_charge_id']);
                    [$label, $badge] = $chargeStatus[$c['status']] ?? [$c['status'], 'secondary']; ?>
                    <tr>
                        <td><?= escape($c['description'] ?: 'Cobrança') ?> <small class="text-muted d-block"><?= escape(preg_replace('/^Financeiro — /u', '', (string)$c['project_title'])) ?></small></td>
                        <td><?= $brl($c['amount']) ?><?= $c['kind'] === 'recurring' && $isSubscription ? '/mês' : '' ?></td>
                        <td><?= $isSubscription ? 'a partir de ' . $dt($c['due_date']) : $dt($c['due_date']) ?></td>
                        <td><?php if ($isSubscription): ?><span class="badge bg-info text-dark">Assinatura mensal</span>
                            <?php else: ?><span class="badge bg-<?= $badge ?>"><?= $label ?></span><?php endif; ?></td>
                        <td class="text-end">
                            <?php if ($c['status'] !== 'paid' && !empty($c['invoice_url'])): ?>
                            <a class="btn btn-sm btn-success" href="<?= escape($c['invoice_url']) ?>" target="_blank" rel="noopener">Pagar</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($charges)): ?><tr><td colspan="5" class="text-center text-muted py-3">Nenhuma cobrança.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card" id="portal-projects">
        <div class="card-header py-2"><strong><i class="bi bi-kanban"></i> Projetos</strong></div>
        <div class="card-body p-0">
            <table class="table table-sm mb-0 align-middle">
                <thead class="table-light"><tr><th>Projeto</th><th>Situação</th><th>Garantia até</th><th>Suporte</th></tr></thead>
                <tbody>
                <?php foreach ($projects as $p): ?>
                    <tr>
                        <td><?= escape($p['name']) ?></td>
                        <td><?= $projectStatus[$p['status']] ?? escape($p['status']) ?></td>
                        <td><?= $p['warranty_ends_at'] ? $dt($p['warranty_ends_at']) : '<span class="text-muted">após a entrega</span>' ?></td>
                        <td><?= (int)$p['support_contract'] === 1 ? 'Contratado' : '<span class="text-muted">Não contratado</span>' ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($projects)): ?><tr><td colspan="4" class="text-center text-muted py-3">Nenhum projeto.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php require APP_PATH . '/views/layouts/footer.php'; ?>
