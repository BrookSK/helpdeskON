<?php $pageTitle = 'Saúde da Integração - Captação de Leads'; $currentPage = 'leadcapture_health'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<?php
$runStatusMeta = [
    'success' => ['Sucesso', 'success'], 'partial' => ['Parcial', 'warning'],
    'failed' => ['Falha', 'danger'], 'running' => ['Rodando', 'secondary'],
];
// Alerta de parser quebrado: cards detectados > 0 mas parseados == 0
$parserBroken = !empty($health['cards_detected_last_run']) && (int)$health['projects_parsed_last_run'] === 0;
?>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0"><i class="bi bi-activity"></i> Saúde da Integração</h5>
            <small class="text-muted">Fonte: 99Freelas</small>
        </div>
        <a href="<?= baseUrl('leadcapture/opportunities') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Oportunidades</a>
    </div>

    <?php if ($parserBroken): ?>
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-octagon"></i> <strong>Parser possivelmente desatualizado:</strong>
        a última coleta detectou <?= (int)$health['cards_detected_last_run'] ?> cards no HTML mas extraiu 0 projetos.
        O 99Freelas pode ter mudado o HTML.
    </div>
    <?php endif; ?>

    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
            <div class="card"><div class="card-body py-3 text-center">
                <div class="text-muted small">Última coleta</div>
                <div class="fw-bold"><?= !empty($health['last_run_at']) ? timeAgo($health['last_run_at']) : '—' ?></div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card"><div class="card-body py-3 text-center">
                <div class="text-muted small">Último sucesso</div>
                <div class="fw-bold"><?= !empty($health['last_success_at']) ? timeAgo($health['last_success_at']) : '—' ?></div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card"><div class="card-body py-3 text-center">
                <div class="text-muted small">Falhas consecutivas</div>
                <div class="fw-bold <?= (int)($health['consecutive_failures'] ?? 0) > 0 ? 'text-danger' : 'text-success' ?>"><?= (int)($health['consecutive_failures'] ?? 0) ?></div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card"><div class="card-body py-3 text-center">
                <div class="text-muted small">Encontrados (última)</div>
                <div class="fw-bold"><?= (int)($health['projects_found_last_run'] ?? 0) ?></div>
            </div></div>
        </div>
    </div>

    <?php if (!empty($health['last_error'])): ?>
    <div class="alert alert-warning small"><strong>Último erro:</strong> <?= escape($health['last_error']) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header bg-white py-2"><h6 class="mb-0" style="font-size:0.9rem;">Histórico das últimas 20 coletas</h6></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0" style="font-size:0.82rem;">
                    <thead class="table-light">
                        <tr>
                            <th>Início</th><th>Gatilho</th><th>Status</th>
                            <th class="text-center">Páginas</th>
                            <th class="text-center" title="Cards no HTML">Cards</th>
                            <th class="text-center" title="Projetos extraídos">Parseados</th>
                            <th class="text-center">Novos</th><th class="text-center">Conhecidos</th>
                            <th class="text-center">Erros</th><th class="text-center">Duração</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($runs)): ?>
                        <tr><td colspan="10" class="text-center text-muted py-3">Nenhuma coleta executada ainda.</td></tr>
                        <?php else: foreach ($runs as $r):
                            $sm = $runStatusMeta[$r['status']] ?? [$r['status'], 'secondary'];
                            $rowBroken = (int)$r['cards_detected'] > 0 && (int)$r['projects_parsed'] === 0;
                        ?>
                        <tr class="<?= $rowBroken ? 'table-danger' : '' ?>">
                            <td class="text-nowrap"><?= date('d/m H:i', strtotime($r['started_at'])) ?></td>
                            <td><?= $r['trigger_type'] === 'manual' ? 'Manual' : 'Agendado' ?></td>
                            <td><span class="badge bg-<?= $sm[1] ?>"><?= $sm[0] ?></span></td>
                            <td class="text-center"><?= (int)$r['pages_fetched'] ?></td>
                            <td class="text-center"><?= (int)$r['cards_detected'] ?></td>
                            <td class="text-center"><?= (int)$r['projects_parsed'] ?></td>
                            <td class="text-center"><strong><?= (int)$r['projects_new'] ?></strong></td>
                            <td class="text-center"><?= (int)$r['projects_known'] ?></td>
                            <td class="text-center"><?= ((int)$r['http_errors'] + (int)$r['parser_errors']) ?: '—' ?></td>
                            <td class="text-center"><?= $r['duration_ms'] !== null ? round($r['duration_ms']/1000, 1) . 's' : '—' ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
