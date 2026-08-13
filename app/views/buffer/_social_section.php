<?php
// Seção de contas diretas Meta/LinkedIn dentro da página de Métricas Sociais.
// Variáveis vindas do BufferController: $socialAccounts, $socialPostsByAccount, $socialFollowersGrowth, $metaConfigured, $linkedinConfigured, $isAdmin
$sproviderMeta = [
    'meta_instagram' => ['Instagram', 'bi-instagram', '#E1306C'],
    'facebook_page'  => ['Facebook',  'bi-facebook',  '#1877F2'],
    'linkedin_org'   => ['LinkedIn',  'bi-linkedin',  '#0A66C2'],
];
$sfmt = fn($v) => ($v !== null && $v !== '') ? number_format((float)$v, 0, ',', '.') : '—';
$socialFollowersGrowth = $socialFollowersGrowth ?? [];
?>

<div class="mb-2 mt-2">
    <h6 class="fw-semibold mb-0" style="font-size:0.9rem;"><i class="bi bi-people"></i> Redes conectadas diretamente (Meta / LinkedIn)</h6>
    <small class="text-muted">Seguidores, interações e publicações direto das APIs</small>
</div>

<?php
// Alertas de token expirado
$metaTokenExpired = $metaTokenExpired ?? false;
$linkedinTokenExpired = $linkedinTokenExpired ?? false;
?>
<?php if ($metaTokenExpired || $linkedinTokenExpired): ?>
<div class="token-alert d-flex align-items-center gap-2 mb-3 small">
    <i class="bi bi-exclamation-triangle-fill fs-5"></i>
    <div class="flex-grow-1">
        <strong>Token expirado:</strong>
        <?php if ($metaTokenExpired && $linkedinTokenExpired): ?>
            Os tokens da <strong>Meta</strong> e do <strong>LinkedIn</strong> expiraram. Os dados de seguidores não estão sendo atualizados.
        <?php elseif ($metaTokenExpired): ?>
            O token da <strong>Meta</strong> expirou. Seguidores do Instagram/Facebook não estão sendo atualizados.
        <?php else: ?>
            O token do <strong>LinkedIn</strong> expirou. Seguidores do LinkedIn não estão sendo atualizados.
        <?php endif; ?>
    </div>
    <?php if ($isAdmin): ?>
    <a href="<?= baseUrl('settings') ?>" class="btn btn-sm btn-warning"><i class="bi bi-arrow-repeat"></i> Reconectar</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if (!$metaConfigured && !$linkedinConfigured): ?>
<div class="alert alert-light border py-2 px-3 small">
    <i class="bi bi-info-circle"></i> Configure os tokens da Meta e do LinkedIn em
    <?php if ($isAdmin): ?><a href="<?= baseUrl('settings') ?>">Configurações</a><?php else: ?>Configurações<?php endif; ?>
    para trazer seguidores e interações que o Buffer não expõe.
</div>
<?php endif; ?>

<?php if (empty($socialAccounts)): ?>
<div class="alert alert-light border small">
    Nenhuma conta direta vinculada. Use <strong>Importar da Meta</strong> ou <strong>Add LinkedIn</strong>.
</div>
<?php else: ?>
<?php foreach ($socialAccounts as $acc):
    $pm = $sproviderMeta[$acc['provider']] ?? ['Rede', 'bi-globe', '#607d8b'];
    $posts = $socialPostsByAccount[$acc['id']] ?? [];
    // Rede normalizada para casar com o filtro (instagram/facebook/linkedin)
    $netMap = ['meta_instagram' => 'instagram', 'facebook_page' => 'facebook', 'linkedin_org' => 'linkedin'];
    $netKey = $netMap[$acc['provider']] ?? $acc['provider'];
?>
<div class="social-filter-item" data-network="<?= escape($netKey) ?>" data-account="<?= escape($acc['display_name'] ?: $acc['external_id']) ?>">
<div class="card mb-3 social-account-card">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <div class="social-avatar" style="background:<?= $pm[2] ?>1a;color:<?= $pm[2] ?>;">
                <?php if (!empty($acc['avatar'])): ?>
                <img src="<?= escape($acc['avatar']) ?>" alt="">
                <?php else: ?><i class="bi <?= $pm[1] ?>"></i><?php endif; ?>
            </div>
            <div class="flex-grow-1 min-w-0">
                <div class="fw-semibold text-truncate"><?= escape($acc['display_name'] ?: $acc['external_id']) ?></div>
                <div class="text-muted small">
                    <span class="badge rounded-pill" style="background:<?= $pm[2] ?>1a;color:<?= $pm[2] ?>;"><i class="bi <?= $pm[1] ?>"></i> <?= $pm[0] ?></span>
                    <?php if (!empty($acc['username'])): ?> @<?= escape($acc['username']) ?><?php endif; ?>
                </div>
            </div>
            <div class="text-muted" style="font-size:0.66rem;">
                <?= !empty($acc['metrics_updated_at']) ? 'Atualizado ' . date('d/m/Y H:i', strtotime($acc['metrics_updated_at'])) : 'Sem dados ainda' ?>
            </div>
            <?php if ($isAdmin): ?>
            <button class="btn btn-sm btn-outline-danger" onclick="deleteSocialAccount(<?= $acc['id'] ?>)" title="Remover"><i class="bi bi-trash3"></i></button>
            <?php endif; ?>
        </div>

        <?php
        // Métricas por provedor — sempre exibidas (0 quando não há dado no período)
        $svAcc = fn($k) => ($acc[$k] !== null && $acc[$k] !== '') ? (float)$acc[$k] : 0;
        if ($acc['provider'] === 'linkedin_org') {
            $accMetrics = [
                ['followers', 'Seguidores', 'bi-people'],
                ['impressions', 'Impressões', 'bi-eye'],
                ['total_likes', 'Curtidas', 'bi-heart'],
                ['total_comments', 'Comentários', 'bi-chat'],
                ['total_shares', 'Compart.', 'bi-share'],
            ];
        } elseif ($acc['provider'] === 'facebook_page') {
            $accMetrics = [
                ['followers', 'Seguidores', 'bi-people'],
                ['total_likes', 'Curtidas', 'bi-heart'],
                ['total_comments', 'Comentários', 'bi-chat'],
                ['total_shares', 'Compart.', 'bi-share'],
            ];
        } else { // instagram
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
        }
        ?>
        <div class="social-metrics-grid mb-3">
            <?php foreach ($accMetrics as $m): ?>
            <div class="social-metric">
                <div class="sm-val"><?= $sfmt($svAcc($m[0])) ?></div>
                <div class="sm-lbl"><i class="bi <?= $m[2] ?>"></i> <?= $m[1] ?></div>
            </div>
            <?php endforeach; ?>
            <div class="social-metric">
                <div class="sm-val"><?= number_format((float)$svAcc('engagement_rate'), 1, ',', '.') ?>%</div>
                <div class="sm-lbl"><i class="bi bi-activity"></i> Engaj.</div>
            </div>
        </div>

        <?php
        // === Crescimento de seguidores ===
        $accGrowth = $socialFollowersGrowth[$acc['id']] ?? null;
        if ($accGrowth && $accGrowth['current'] !== null):
            $growthPeriods = [
                '7d'  => '7 dias',
                '30d' => '30 dias',
                '90d' => '90 dias',
            ];
        ?>
        <div class="followers-growth-section mb-3">
            <div class="fg-title"><i class="bi bi-graph-up-arrow"></i> Crescimento de Seguidores</div>
            <div class="fg-grid">
                <div class="fg-card fg-current">
                    <div class="fg-val"><?= $sfmt($accGrowth['current']) ?></div>
                    <div class="fg-lbl">Atual</div>
                </div>
                <?php foreach ($growthPeriods as $pKey => $pLabel):
                    $pd = $accGrowth[$pKey] ?? null;
                    if (!$pd || $pd['diff'] === null) continue;
                    $isPositive = $pd['diff'] >= 0;
                    $arrow = $isPositive ? 'bi-arrow-up-short' : 'bi-arrow-down-short';
                    $colorClass = $isPositive ? 'fg-positive' : 'fg-negative';
                ?>
                <div class="fg-card <?= $colorClass ?>">
                    <div class="fg-val">
                        <i class="bi <?= $arrow ?>"></i>
                        <?= ($isPositive ? '+' : '') . $sfmt($pd['diff']) ?>
                    </div>
                    <div class="fg-pct"><?= ($isPositive ? '+' : '') . number_format((float)$pd['pct'], 1, ',', '.') ?>%</div>
                    <div class="fg-lbl"><?= $pLabel ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

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
                    <span title="Curtidas"><i class="bi bi-heart-fill"></i> <?= $sfmt($p['likes']) ?></span>
                    <span title="Comentários"><i class="bi bi-chat-fill"></i> <?= $sfmt($p['comments']) ?></span>
                    <?php if ($p['shares'] !== null): ?><span title="Compartilhamentos"><i class="bi bi-share-fill"></i> <?= $sfmt($p['shares']) ?></span><?php endif; ?>
                    <?php if ($p['saved'] !== null): ?><span title="Salvos"><i class="bi bi-bookmark-fill"></i> <?= $sfmt($p['saved']) ?></span><?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php elseif (!empty($acc['metrics_updated_at'])): ?>
        <div class="text-muted small">Nenhuma publicação encontrada para esta conta.</div>
        <?php endif; ?>
    </div>
</div>
</div><!-- /.social-filter-item -->
<?php endforeach; ?>
<?php endif; ?>

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
/* Crescimento de seguidores */
.followers-growth-section { border: 1px solid #e8f5e9; border-radius: 12px; padding: 12px 14px; background: #fafffe; }
.fg-title { font-size: 0.72rem; font-weight: 600; color: #2b7a5e; text-transform: uppercase; letter-spacing: .3px; margin-bottom: 10px; }
.fg-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)); gap: 8px; }
.fg-card { text-align: center; padding: 8px 4px; border-radius: 10px; background: #f0faf6; }
.fg-card.fg-current { background: #e8f5e9; }
.fg-card.fg-positive { background: #e8f5e9; }
.fg-card.fg-negative { background: #fef2f2; }
.fg-val { font-size: 1rem; font-weight: 700; color: #2b3440; line-height: 1.2; }
.fg-positive .fg-val { color: #15803d; }
.fg-negative .fg-val { color: #dc2626; }
.fg-pct { font-size: 0.68rem; font-weight: 600; color: inherit; }
.fg-positive .fg-pct { color: #16a34a; }
.fg-negative .fg-pct { color: #ef4444; }
.fg-lbl { font-size: 0.6rem; color: #8a929b; margin-top: 2px; }
/* Alerta de token expirado */
.token-alert { border: 1px solid #fde68a; background: #fffbeb; border-radius: 10px; padding: 10px 14px; }
.token-alert i { color: #d97706; }
</style>

<script>
(function(){
    const B = '<?= baseUrl("") ?>';
    window.importMeta = function(btn) {
        const o = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        fetch(B + 'social/importMeta', { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'} })
            .then(r=>r.json()).then(d=>{ btn.disabled=false; btn.innerHTML=o; if(d.error){alert(d.error);return;} alert((d.imported||0)+' conta(s) importada(s).'); location.reload(); })
            .catch(()=>{ btn.disabled=false; btn.innerHTML=o; });
    };
    window.syncSocial = function(btn) {
        const o = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Atualizando...';
        fetch(B + 'social/syncMetrics', { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'} })
            .then(r=>r.json()).then(d=>{ btn.disabled=false; btn.innerHTML=o; if(d.error){alert(d.error);return;} if(d.errors&&d.errors.length)console.warn('Avisos:',d.errors); location.reload(); })
            .catch(()=>{ btn.disabled=false; btn.innerHTML=o; });
    };
    window.snapshotFollowers = function(btn) {
        const o = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Salvando...';
        fetch(B + 'social/snapshotFollowers', { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'} })
            .then(r=>r.json()).then(d=>{
                btn.disabled=false; btn.innerHTML=o;
                if(d.error){alert(d.error);return;}
                const msg = (d.snapshots_saved||0) + ' conta(s) salva(s) no histórico.';
                if(d.errors&&d.errors.length) alert(msg + '\nAvisos: ' + d.errors.join(', '));
                else alert(msg);
                location.reload();
            })
            .catch(()=>{ btn.disabled=false; btn.innerHTML=o; });
    };
    let liModal = null;
    window.openLinkedinModal = function() {
        document.getElementById('li-org-id').value=''; document.getElementById('li-name').value='';
        if(!liModal) liModal = new bootstrap.Modal(document.getElementById('linkedinModal'));
        liModal.show();
    };
    window.addLinkedin = function(btn) {
        const orgId = document.getElementById('li-org-id').value.trim();
        if(!orgId){ alert('Informe o ID/URN da organização.'); return; }
        const fd = new FormData(); fd.append('org_id', orgId); fd.append('display_name', document.getElementById('li-name').value.trim());
        fetch(B + 'social/addLinkedin', { method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'} })
            .then(r=>r.json()).then(d=>{ if(d.error){alert(d.error);return;} location.reload(); });
    };
    window.deleteSocialAccount = function(id) {
        if(!confirm('Remover esta conta?')) return;
        fetch(B + 'social/delete/' + id, { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'} })
            .then(r=>r.json()).then(d=>{ if(d.error){alert(d.error);return;} location.reload(); });
    };
})();
</script>
