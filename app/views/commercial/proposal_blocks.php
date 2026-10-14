<?php $pageTitle = 'Conteúdo fixo da proposta - ON Solutions Helpdesk'; $currentPage = 'proposals'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>
<?php
$kindHint = [
    'sections' => 'Cada seção começa com uma letra e o título (ex.: "A Vigência, reajuste e renovação") e segue com um item por linha. Deixe uma linha em branco entre as seções.',
];
?>
<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0">Conteúdo fixo da proposta</h5>
            <small class="text-muted">Páginas finais de toda proposta. As páginas de apresentação (Quem somos, O que fazemos, Clientes, Como fazemos) são as do modelo em PDF; o restante vem de cada proposta.</small>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= baseUrl('proposal') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Propostas</a>
            <?php if (empty($missing)): ?>
            <button class="btn btn-sm btn-success" id="btn-save-blocks" onclick="saveBlocks()"><i class="bi bi-check-lg"></i> Salvar</button>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($missing)): ?>
    <div class="alert alert-warning">A tabela de conteúdo fixo ainda não existe neste banco. Aplique a migration <code>162_proposal_document.sql</code>.</div>
    <?php endif; ?>

    <div class="alert alert-light border small">
        <strong>Variáveis</strong> (trocadas pelos valores de cada proposta):
        <?php foreach (ProposalRules::VARS as $var => $label): ?>
            <code>{<?= $var ?>}</code> <?= escape($label) ?><?= $var !== array_key_last(ProposalRules::VARS) ? ' · ' : '' ?>
        <?php endforeach; ?>
        <div class="mt-1 text-muted">Propostas já enviadas mantêm o texto do momento em que foram salvas.</div>
    </div>

    <div class="row g-3">
        <?php foreach ($blocks as $i => $b): $kind = $b['kind'] ?? ProposalRules::blockKind($b['block_key']); ?>
        <div class="col-12">
            <div class="card block-card" data-id="<?= (int)($b['id'] ?? 0) ?>">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="badge bg-dark"><?= $i + 1 ?></span>
                        <input class="form-control form-control-sm b-title fw-medium" value="<?= escape($b['title'] ?? '') ?>" <?= !empty($missing) ? 'disabled' : '' ?>>
                    </div>
                    <textarea class="form-control form-control-sm b-body" rows="14" <?= !empty($missing) ? 'disabled' : '' ?>><?= escape($b['body'] ?? '') ?></textarea>
                    <small class="text-muted"><?= escape($kindHint[$kind] ?? '') ?></small>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<script>
const PROP_BASE = '<?= baseUrl("") ?>';
const CSRF = '<?= csrf_token() ?>';
async function saveBlocks() {
    const blocks = [];
    document.querySelectorAll('.block-card').forEach(card => {
        blocks.push({
            id: card.getAttribute('data-id'),
            title: card.querySelector('.b-title').value,
            body: card.querySelector('.b-body').value,
        });
    });
    const btn = document.getElementById('btn-save-blocks');
    btn.disabled = true;
    try {
        const r = await fetch(`${PROP_BASE}proposal/saveBlocks`, {
            method: 'POST',
            headers: {'Content-Type':'application/json','X-CSRF-Token':CSRF,'X-Requested-With':'XMLHttpRequest'},
            body: JSON.stringify({ blocks })
        }).then(x => x.json());
        if (r.error) { alert(r.error); return; }
        btn.innerHTML = '<i class="bi bi-check-lg"></i> Salvo';
    } catch (e) {
        alert('Falha de rede ao salvar.');
    } finally {
        btn.disabled = false;
    }
}
</script>
<?php require APP_PATH . '/views/layouts/footer.php'; ?>
