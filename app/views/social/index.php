<?php $pageTitle = 'Redes Sociais - ON Solutions Helpdesk'; $currentPage = 'social_accounts'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<?php
$providerMeta = [
    'meta_instagram' => ['Instagram', 'bi-instagram', '#E1306C'],
    'facebook_page'  => ['Facebook',  'bi-facebook',  '#1877F2'],
    'linkedin_org'   => ['LinkedIn',  'bi-linkedin',  '#0A66C2'],
];
$fmt = fn($v) => ($v !== null && $v !== '') ? number_format((float)$v, 0, ',', '.') : '—';
?>

<div class="main-content">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h5 class="mb-0 fw-semibold"><i class="bi bi-people"></i> Redes Sociais</h5>
            <small class="text-muted">Seguidores, interações e publicações direto das APIs (Meta e LinkedIn)</small>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary btn-sm" onclick="importMeta(this)"><i class="bi bi-download"></i> Importar da Meta</button>
            <button class="btn btn-outline-secondary btn-sm" onclick="openLinkedinModal()"><i class="bi bi-linkedin"></i> Add LinkedIn</button>
            <button class="btn btn-primary btn-sm" onclick="syncSocial(this)"><i class="bi bi-arrow-repeat"></i> Atualizar dados</button>
        </div>
    </div>

    <?php if (!$metaConfigured && !$linkedinConfigured): ?>
    <div class="alert alert-warning py-2 px-3 small">
        <i class="bi bi-exclamation-triangle"></i> Nenhum token configurado.
        <?php if ($isAdmin): ?>Adicione os tokens da Meta e do LinkedIn em <a href="<?= baseUrl('settings') ?>">Configurações</a>.<?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (empty($accounts)): ?>
    <div class="alert alert-light border small">
        Nenhuma conta vinculada. Use <strong>Importar da Meta</strong> (Facebook/Instagram) ou <strong>Add LinkedIn</strong> para começar.
    </div>
    <?php endif; ?>

    <?php foreach ($accounts as $acc):
        $meta = $providerMeta[$acc['provider']] ?? ['Rede', 'bi-globe', '#607d8b'];
        $initials = strtoupper(mb_substr($acc['display_name'] ?? '?', 0, 1));
        $posts = $postsByAccount[$acc['id']] ?? [];
    ?>
    <div class="card mb-3 social-account-card">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                <div class="social-avatar" style="background:<?= $meta[2] ?>1a;color:<?= $meta[2] ?>;">
                    <?php if (!empty($acc['avatar'])): ?>
                    <img src="<?= escape($acc['avatar']) ?>" alt="" onerror="this.parentNode.innerHTML='<i class=\'bi <?= $meta[1] ?>\'></i>'">
                    <?php else: ?><i class="bi <?= $meta[1] ?>"></i><?php endif; ?>
                </div>
                <div class="flex-grow-1 min-w-0">
                    <div class="fw-semibold text-truncate"><?= escape($acc['display_name'] ?: $acc['external_id']) ?></div>
                    <div class="text-muted small">
                        <span class="badge rounded-pill" style="background:<?= $meta[2] ?>1a;color:<?= $meta[2] ?>;"><i class="bi <?= $meta[1] ?>"></i> <?= $meta[0] ?></span>
                        <?php if (!empty($acc['username'])): ?> @<?= escape($acc['username']) ?><?php endif; ?>
                    </div>
                </div>
                <div class="text-muted" style="font-size:0.66rem;">
                    <?= !empty($acc['metrics_updated_at']) ? 'Atualizado ' . date('d/m/Y H:i', strtotime($acc['metrics_updated_at'])) : 'Sem dados ainda' ?>
                </div>
                <?php if ($isAdmin): ?>
                <button class="btn btn-sm btn-outline-danger" onclick="deleteAccount(<?= $acc['id'] ?>)" title="Remover"><i class="bi bi-trash3"></i></button>
                <?php endif; ?>
            </div>

            <!-- Métricas de conta -->
            <div class="social-metrics-grid mb-3">
                <?php
                $accMetrics = [
                    ['followers', 'Seguidores', 'bi-people'],
                    ['follows', 'Seguindo', 'bi-person-check'],
                    ['media_count', 'Publicações', 'bi-collection'],
                    ['reach', 'Alcance', 'bi-broadcast'],
                    ['impressions', 'Impressões', 'bi-eye'],
                    ['profile_views', 'Visitas', 'bi-person-badge'],
                    ['total_likes', 'Curtidas', 'bi-heart'],
                    ['total_comments', 'Comentários', 'bi-chat'],
                    ['total_shares', 'Compart.', 'bi-share'],
                ];
                foreach ($accMetrics as $m):
                    if ($acc[$m[0]] === null) continue;
                ?>
                <div class="social-metric">
                    <div class="sm-val"><?= $fmt($acc[$m[0]]) ?></div>
                    <div class="sm-lbl"><i class="bi <?= $m[2] ?>"></i> <?= $m[1] ?></div>
                </div>
                <?php endforeach; ?>
                <?php if ($acc['engagement_rate'] !== null): ?>
                <div class="social-metric">
                    <div class="sm-val"><?= number_format((float)$acc['engagement_rate'], 1, ',', '.') ?>%</div>
                    <div class="sm-lbl"><i class="bi bi-activity"></i> Engaj.</div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Publicações recentes -->
            <?php if (!empty($posts)): ?>
            <div class="social-posts-title"><i class="bi bi-grid-3x3-gap"></i> Publicações recentes</div>
            <div class="social-posts">
                <?php foreach ($posts as $p): ?>
                <a class="social-post" <?= $p['permalink'] ? 'href="' . escape($p['permalink']) . '" target="_blank" rel="noopener"' : '' ?>>
                    <div class="social-post-cover">
                        <?php if (!empty($p['thumbnail'])): ?>
                        <img src="<?= escape($p['thumbnail']) ?>" alt="" onerror="this.style.display='none'">
                        <?php else: ?><i class="bi bi-image"></i><?php endif; ?>
                    </div>
                    <div class="social-post-caption"><?= escape(mb_strimwidth($p['caption'] ?? '(sem legenda)', 0, 70, '…')) ?></div>
                    <div class="social-post-stats">
                        <span title="Curtidas"><i class="bi bi-heart-fill"></i> <?= $fmt($p['likes']) ?></span>
                        <span title="Comentários"><i class="bi bi-chat-fill"></i> <?= $fmt($p['comments']) ?></span>
                        <?php if ($p['shares'] !== null): ?><span title="Compartilhamentos"><i class="bi bi-share-fill"></i> <?= $fmt($p['shares']) ?></span><?php endif; ?>
                        <?php if ($p['saved'] !== null): ?><span title="Salvos"><i class="bi bi-bookmark-fill"></i> <?= $fmt($p['saved']) ?></span><?php endif; ?>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php elseif (!empty($acc['metrics_updated_at'])): ?>
            <div class="text-muted small">Nenhuma publicação encontrada para esta conta.</div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Modal Add LinkedIn -->
<div class="modal fade" id="linkedinModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-linkedin text-primary"></i> Adicionar organização LinkedIn</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <label class="form-label small fw-medium">ID ou URN da organização</label>
                    <input type="text" id="li-org-id" class="form-control form-control-sm" placeholder="Ex: 12345678">
                    <small class="text-muted">Configurações da página &gt; ID da página.</small>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-medium">Nome (opcional)</label>
                    <input type="text" id="li-name" class="form-control form-control-sm" placeholder="Ex: ON Solutions Brasil">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-sm btn-primary" onclick="addLinkedin(this)">Adicionar</button>
            </div>
        </div>
    </div>
</div>

<style>
.social-account-card { border: 1px solid #eef0f2; border-radius: 14px; }
.social-avatar { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; flex-shrink: 0; overflow: hidden; }
.social-avatar img { width: 100%; height: 100%; object-fit: cover; }
.social-metrics-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(90px, 1fr)); gap: 8px; }
.social-metric { background: #f7f9fa; border-radius: 10px; padding: 8px 4px; text-align: center; }
.sm-val { font-size: 1.05rem; font-weight: 700; color: #2b3440; line-height: 1.1; }
.sm-lbl { font-size: 0.62rem; color: #8a929b; margin-top: 2px; }
.social-posts-title { font-size: 0.7rem; font-weight: 600; color: #99a2ab; text-transform: uppercase; letter-spacing: .4px; margin-bottom: 8px; }
.social-posts { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px; }
.social-post { display: block; text-decoration: none; color: inherit; border: 1px solid #eef0f2; border-radius: 10px; overflow: hidden; transition: box-shadow .15s; }
.social-post:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
.social-post-cover { height: 110px; background: #eef1f4; display: flex; align-items: center; justify-content: center; color: #b0b8c0; font-size: 1.5rem; }
.social-post-cover img { width: 100%; height: 100%; object-fit: cover; }
.social-post-caption { font-size: 0.72rem; color: #444; padding: 6px 8px 2px; min-height: 34px; }
.social-post-stats { display: flex; flex-wrap: wrap; gap: 8px; padding: 4px 8px 8px; font-size: 0.68rem; color: #6b7280; }
.social-post-stats i { color: #00997D; }
</style>

<script>
const BASE = '<?= baseUrl("") ?>';

function importMeta(btn) {
    const o = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    fetch(`${BASE}social/importMeta`, { method: 'POST', headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json()).then(d => {
            btn.disabled = false; btn.innerHTML = o;
            if (d.error) { alert(d.error); return; }
            alert(d.imported + ' conta(s) importada(s).');
            location.reload();
        }).catch(() => { btn.disabled = false; btn.innerHTML = o; });
}

function syncSocial(btn) {
    const o = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Atualizando...';
    fetch(`${BASE}social/syncMetrics`, { method: 'POST', headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json()).then(d => {
            btn.disabled = false; btn.innerHTML = o;
            if (d.error) { alert(d.error); return; }
            if (d.errors && d.errors.length) console.warn('Avisos:', d.errors);
            location.reload();
        }).catch(() => { btn.disabled = false; btn.innerHTML = o; });
}

let linkedinModalInstance = null;
function openLinkedinModal() {
    document.getElementById('li-org-id').value = '';
    document.getElementById('li-name').value = '';
    if (!linkedinModalInstance) linkedinModalInstance = new bootstrap.Modal(document.getElementById('linkedinModal'));
    linkedinModalInstance.show();
}

function addLinkedin(btn) {
    const orgId = document.getElementById('li-org-id').value.trim();
    if (!orgId) { alert('Informe o ID/URN da organização.'); return; }
    const fd = new FormData();
    fd.append('org_id', orgId);
    fd.append('display_name', document.getElementById('li-name').value.trim());
    fetch(`${BASE}social/addLinkedin`, { method: 'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json()).then(d => {
            if (d.error) { alert(d.error); return; }
            location.reload();
        });
}

function deleteAccount(id) {
    if (!confirm('Remover esta conta?')) return;
    fetch(`${BASE}social/delete/${id}`, { method: 'POST', headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json()).then(d => {
            if (d.error) { alert(d.error); return; }
            location.reload();
        });
}
</script>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
