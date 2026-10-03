<?php $pageTitle = 'Acessos - ON Solutions Helpdesk'; $currentPage = 'acessos'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<?php
// Agrupa por categoria para renderizar os cards
$grouped = [];
foreach ($accesses as $a) {
    $grouped[$a['category']][] = $a;
}
ksort($grouped);

// Mapa categoria → ícone Bootstrap Icons
$catIcons = [
    'Geral'        => 'bi-key',
    'Servidor'     => 'bi-hdd-rack',
    'Email'        => 'bi-envelope',
    'CRM'          => 'bi-people',
    'Hospedagem'   => 'bi-cloud',
    'Banco'        => 'bi-database',
    'Redes Sociais'=> 'bi-share',
    'Financeiro'   => 'bi-cash-stack',
    'ERP'          => 'bi-building-gear',
    'VPN'          => 'bi-shield-shaded',
];
$defaultIcon = 'bi-key-fill';

function catIcon(string $cat, array $map, string $default): string {
    return $map[$cat] ?? $default;
}
?>

<div class="main-content">

    <!-- ===== Top bar ===== -->
    <div class="top-bar">
        <div>
            <h5 class="mb-0"><i class="bi bi-shield-lock text-primary me-2"></i>Acessos</h5>
            <small class="text-muted">Cofre de credenciais — visível somente para você</small>
        </div>
        <button class="btn btn-primary btn-sm" id="btn-novo-acesso">
            <i class="bi bi-plus-lg me-1"></i> Novo Acesso
        </button>
    </div>

    <!-- flash -->
    <?php if ($msg = flash('success')): ?>
        <div class="alert alert-success alert-dismissible fade show"><?= escape($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <!-- ===== Estado vazio ===== -->
    <?php if (empty($accesses)): ?>
    <div class="d-flex flex-column align-items-center justify-content-center py-5 text-center" style="min-height:55vh;">
        <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center mb-4" style="width:88px;height:88px;">
            <i class="bi bi-shield-lock text-primary" style="font-size:2.4rem;"></i>
        </div>
        <h5 class="fw-semibold mb-2">Nenhum acesso cadastrado ainda</h5>
        <p class="text-muted mb-4" style="max-width:420px;">
            Guarde aqui logins e senhas de sistemas que você usa — CRM, painel de hospedagem,
            e‑mail corporativo, banco de dados e mais. Suas credenciais ficam criptografadas
            e visíveis apenas para você.
        </p>
        <button class="btn btn-primary" id="btn-novo-acesso-empty">
            <i class="bi bi-plus-lg me-1"></i> Cadastrar primeiro acesso
        </button>
    </div>
    <?php else: ?>

    <!-- ===== Barra de busca + filtro de categoria ===== -->
    <div class="card mb-3">
        <div class="card-body py-2 px-3">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-6">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input type="text" id="search-acessos" class="form-control" placeholder="Buscar por título, login ou URL…">
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <select id="filter-categoria" class="form-select form-select-sm">
                        <option value="">Todas as categorias</option>
                        <?php foreach (array_keys($grouped) as $cat): ?>
                            <option value="<?= escape($cat) ?>"><?= escape($cat) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-2 text-md-end">
                    <small class="text-muted" id="count-label"><?= count($accesses) ?> acesso<?= count($accesses) !== 1 ? 's' : '' ?></small>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== Cards agrupados por categoria ===== -->
    <div id="acessos-container">
    <?php foreach ($grouped as $categoria => $items): ?>
        <div class="categoria-section mb-4" data-categoria="<?= escape($categoria) ?>">
            <h6 class="text-uppercase fw-semibold mb-3 d-flex align-items-center gap-2" style="font-size:0.72rem;letter-spacing:0.6px;color:rgba(0,0,0,0.4);">
                <i class="bi <?= catIcon($categoria, $catIcons, $defaultIcon) ?>" style="font-size:0.9rem;color:rgba(0,0,0,0.3);"></i>
                <?= escape($categoria) ?>
                <span class="badge bg-light text-secondary fw-normal" style="font-size:0.65rem;"><?= count($items) ?></span>
            </h6>
            <div class="row g-3 cards-row">
            <?php foreach ($items as $ac): ?>
                <div class="col-12 col-sm-6 col-xl-4 acesso-card"
                     data-title="<?= escape(mb_strtolower($ac['title'])) ?>"
                     data-username="<?= escape(mb_strtolower($ac['username'] ?? '')) ?>"
                     data-url="<?= escape(mb_strtolower($ac['url'] ?? '')) ?>"
                     data-categoria="<?= escape($ac['category']) ?>">
                    <div class="card h-100 shadow-sm border-0 acesso-card-inner" style="cursor:pointer;transition:box-shadow .15s,transform .15s;" onclick="openDetail(<?= $ac['id'] ?>)">
                        <div class="card-body d-flex gap-3 align-items-start p-3">
                            <!-- Ícone colorido -->
                            <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0"
                                 style="width:44px;height:44px;background:var(--bs-primary-bg-subtle,#dbeafe);">
                                <i class="bi <?= catIcon($categoria, $catIcons, $defaultIcon) ?> text-primary" style="font-size:1.25rem;"></i>
                            </div>
                            <!-- Conteúdo -->
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-semibold text-truncate" style="font-size:0.92rem;"><?= escape($ac['title']) ?></div>
                                <?php if (!empty($ac['username'])): ?>
                                <div class="text-muted text-truncate" style="font-size:0.78rem;">
                                    <i class="bi bi-person me-1"></i><?= escape($ac['username']) ?>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($ac['url'])): ?>
                                <div class="text-truncate mt-1" style="font-size:0.75rem;">
                                    <a href="<?= escape($ac['url']) ?>" target="_blank" rel="noopener"
                                       class="text-decoration-none text-primary"
                                       onclick="event.stopPropagation()">
                                        <i class="bi bi-link-45deg"></i>
                                        <?= escape(parse_url($ac['url'], PHP_URL_HOST) ?: $ac['url']) ?>
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                            <!-- Ações rápidas -->
                            <div class="d-flex flex-column gap-1 flex-shrink-0" onclick="event.stopPropagation()">
                                <?php if (!empty($ac['username'])): ?>
                                <button class="btn btn-outline-secondary btn-sm p-1 lh-1" style="width:28px;height:28px;"
                                        title="Copiar login"
                                        onclick="copyText('<?= escape(addslashes($ac['username'])) ?>', this)">
                                    <i class="bi bi-person-fill" style="font-size:0.75rem;"></i>
                                </button>
                                <?php endif; ?>
                                <button class="btn btn-outline-secondary btn-sm p-1 lh-1" style="width:28px;height:28px;"
                                        title="Copiar senha"
                                        onclick="revealAndCopy(<?= $ac['id'] ?>, this)">
                                    <i class="bi bi-key-fill" style="font-size:0.75rem;"></i>
                                </button>
                            </div>
                        </div>
                        <?php if (!empty($ac['notes'])): ?>
                        <div class="card-footer py-1 px-3 bg-transparent border-top" style="font-size:0.75rem;color:#888;">
                            <i class="bi bi-sticky me-1"></i><?= escape(mb_strimwidth($ac['notes'], 0, 80, '…')) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
    </div>

    <!-- sem resultados na busca -->
    <div id="no-results" class="text-center py-5 text-muted" style="display:none;">
        <i class="bi bi-search" style="font-size:2rem;"></i>
        <p class="mt-2">Nenhum acesso encontrado para esta busca.</p>
    </div>

    <?php endif; // fim !empty($accesses) ?>
</div><!-- /main-content -->


<!-- ============================================================ -->
<!--  OFFCANVAS — detalhes + editar                               -->
<!-- ============================================================ -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasAcesso" style="width:min(420px,100%);">
    <div class="offcanvas-header border-bottom">
        <h6 class="offcanvas-title fw-semibold" id="offcanvas-title">Detalhes do Acesso</h6>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body p-0">

        <!-- Modo visualização -->
        <div id="view-mode" class="p-4">
            <div class="d-flex align-items-center gap-3 mb-4">
                <div id="detail-icon-wrap" class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0"
                     style="width:52px;height:52px;background:var(--bs-primary-bg-subtle,#dbeafe);">
                    <i id="detail-icon" class="bi bi-key text-primary" style="font-size:1.5rem;"></i>
                </div>
                <div>
                    <div id="detail-title" class="fw-semibold" style="font-size:1rem;"></div>
                    <div id="detail-category" class="text-muted" style="font-size:0.78rem;"></div>
                </div>
            </div>

            <!-- Campos -->
            <div class="d-flex flex-column gap-3">
                <div id="wrap-url" style="display:none!important">
                    <label class="form-label text-muted mb-1" style="font-size:0.72rem;text-transform:uppercase;letter-spacing:.4px;">URL / Endereço</label>
                    <div class="d-flex align-items-center gap-2">
                        <a id="detail-url" href="#" target="_blank" rel="noopener" class="text-break text-primary" style="font-size:0.88rem;word-break:break-all;"></a>
                        <button class="btn btn-outline-secondary btn-sm ms-auto flex-shrink-0" onclick="copyText(document.getElementById('detail-url').textContent, this)" title="Copiar URL"><i class="bi bi-copy"></i></button>
                    </div>
                </div>

                <div id="wrap-username" style="display:none!important">
                    <label class="form-label text-muted mb-1" style="font-size:0.72rem;text-transform:uppercase;letter-spacing:.4px;">Login / Usuário</label>
                    <div class="d-flex align-items-center gap-2">
                        <span id="detail-username" class="font-monospace" style="font-size:0.88rem;word-break:break-all;"></span>
                        <button class="btn btn-outline-secondary btn-sm ms-auto flex-shrink-0" onclick="copyText(document.getElementById('detail-username').textContent, this)" title="Copiar login"><i class="bi bi-copy"></i></button>
                    </div>
                </div>

                <div>
                    <label class="form-label text-muted mb-1" style="font-size:0.72rem;text-transform:uppercase;letter-spacing:.4px;">Senha</label>
                    <div class="d-flex align-items-center gap-2">
                        <span id="detail-password" class="font-monospace" style="font-size:0.88rem;letter-spacing:2px;">••••••••</span>
                        <button class="btn btn-outline-secondary btn-sm ms-auto flex-shrink-0" id="btn-reveal" title="Revelar senha (5 s)" onclick="revealInDetail()"><i class="bi bi-eye"></i></button>
                        <button class="btn btn-outline-secondary btn-sm flex-shrink-0" id="btn-copy-pw" title="Copiar senha" onclick="revealAndCopyFromDetail()"><i class="bi bi-copy"></i></button>
                    </div>
                    <div id="reveal-countdown" class="text-muted mt-1" style="font-size:0.72rem;display:none;"></div>
                </div>

                <div id="wrap-notes" style="display:none!important">
                    <label class="form-label text-muted mb-1" style="font-size:0.72rem;text-transform:uppercase;letter-spacing:.4px;">Notas</label>
                    <div id="detail-notes" class="text-muted" style="font-size:0.85rem;white-space:pre-wrap;word-break:break-word;"></div>
                </div>

                <div id="wrap-company" style="display:none!important">
                    <label class="form-label text-muted mb-1" style="font-size:0.72rem;text-transform:uppercase;letter-spacing:.4px;">Empresa relacionada</label>
                    <div id="detail-company" style="font-size:0.85rem;"></div>
                </div>
            </div>

            <!-- Ações -->
            <div class="d-flex gap-2 mt-4 pt-3 border-top">
                <button class="btn btn-outline-primary btn-sm" onclick="switchToEdit()"><i class="bi bi-pencil me-1"></i>Editar</button>
                <button class="btn btn-outline-danger btn-sm ms-auto" onclick="confirmDelete()"><i class="bi bi-trash me-1"></i>Excluir</button>
            </div>
        </div>

        <!-- Modo edição / criação -->
        <form id="edit-mode" class="p-4" style="display:none;" onsubmit="submitForm(event)">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" id="form-id" name="id" value="">

            <div class="mb-3">
                <label class="form-label fw-medium">Título <span class="text-danger">*</span></label>
                <input type="text" name="title" id="f-title" class="form-control" placeholder="Ex: Painel cPanel da Empresa X" required>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-7">
                    <label class="form-label fw-medium">Categoria</label>
                    <input type="text" name="category" id="f-category" class="form-control" list="category-list" placeholder="Ex: Servidor">
                    <datalist id="category-list">
                        <option value="Geral">
                        <option value="Servidor">
                        <option value="Hospedagem">
                        <option value="Email">
                        <option value="CRM">
                        <option value="Banco">
                        <option value="ERP">
                        <option value="Redes Sociais">
                        <option value="Financeiro">
                        <option value="VPN">
                        <?php foreach (array_keys($grouped) as $cat): ?>
                            <option value="<?= escape($cat) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="col-5">
                    <label class="form-label fw-medium">Empresa</label>
                    <select name="company_id" id="f-company" class="form-select">
                        <option value="">— nenhuma —</option>
                        <?php foreach ($companies as $co): ?>
                            <option value="<?= $co['id'] ?>"><?= escape($co['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-medium">URL / Endereço</label>
                <input type="url" name="url" id="f-url" class="form-control" placeholder="https://…">
            </div>

            <div class="mb-3">
                <label class="form-label fw-medium">Login / Usuário</label>
                <input type="text" name="username" id="f-username" class="form-control" placeholder="usuario@empresa.com" autocomplete="off">
            </div>

            <div class="mb-3">
                <label class="form-label fw-medium">Senha</label>
                <div class="input-group">
                    <input type="password" name="password" id="f-password" class="form-control font-monospace"
                           placeholder="<?= isset($editMode) ? 'Deixe em branco para manter a atual' : 'Digite a senha' ?>"
                           autocomplete="new-password">
                    <button type="button" class="btn btn-outline-secondary" onclick="togglePwdVisibility()">
                        <i class="bi bi-eye" id="pwd-eye-icon"></i>
                    </button>
                </div>
                <div id="pw-hint" class="form-text" style="display:none;">Deixe em branco para manter a senha atual.</div>
            </div>

            <div class="mb-4">
                <label class="form-label fw-medium">Notas</label>
                <textarea name="notes" id="f-notes" class="form-control" rows="3" placeholder="Informações adicionais…"></textarea>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm" id="btn-submit-form">
                    <i class="bi bi-check2 me-1"></i><span id="btn-submit-label">Salvar</span>
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="cancelEdit()">Cancelar</button>
            </div>
        </form>

    </div><!-- /offcanvas-body -->
</div><!-- /offcanvas -->


<!-- ============================================================ -->
<!--  JavaScript                                                   -->
<!-- ============================================================ -->
<script>
const CSRF = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
let currentId    = null;   // id do acesso aberto no offcanvas
let revealTimer  = null;   // timer do countdown de reveal
let offcanvas    = null;   // instância Bootstrap Offcanvas

// --------------------------------------------------------------- //
//  Init                                                            //
// --------------------------------------------------------------- //
document.addEventListener('DOMContentLoaded', () => {
    offcanvas = new bootstrap.Offcanvas(document.getElementById('offcanvasAcesso'));

    document.getElementById('btn-novo-acesso')?.addEventListener('click', openNew);
    document.getElementById('btn-novo-acesso-empty')?.addEventListener('click', openNew);

    // Busca em tempo real
    document.getElementById('search-acessos')?.addEventListener('input', applyFilters);
    document.getElementById('filter-categoria')?.addEventListener('change', applyFilters);

    // Hover nos cards
    document.querySelectorAll('.acesso-card-inner').forEach(c => {
        c.addEventListener('mouseenter', () => { c.style.transform = 'translateY(-2px)'; c.style.boxShadow = '0 6px 20px rgba(0,0,0,.10)'; });
        c.addEventListener('mouseleave', () => { c.style.transform = ''; c.style.boxShadow = ''; });
    });
});

// --------------------------------------------------------------- //
//  Filtro / busca                                                  //
// --------------------------------------------------------------- //
function applyFilters() {
    const q   = (document.getElementById('search-acessos')?.value ?? '').toLowerCase().trim();
    const cat = document.getElementById('filter-categoria')?.value ?? '';

    let visible = 0;
    document.querySelectorAll('.acesso-card').forEach(card => {
        const matchQ   = !q || card.dataset.title.includes(q) || card.dataset.username.includes(q) || card.dataset.url.includes(q);
        const matchCat = !cat || card.dataset.categoria === cat;
        const show     = matchQ && matchCat;
        card.style.display = show ? '' : 'none';
        if (show) visible++;
    });

    // Esconde seções vazias
    document.querySelectorAll('.categoria-section').forEach(sec => {
        const hasVisible = [...sec.querySelectorAll('.acesso-card')].some(c => c.style.display !== 'none');
        sec.style.display = hasVisible ? '' : 'none';
    });

    const lbl = document.getElementById('count-label');
    if (lbl) lbl.textContent = visible + ' acesso' + (visible !== 1 ? 's' : '');

    const nr = document.getElementById('no-results');
    if (nr) nr.style.display = visible === 0 ? '' : 'none';
}

// --------------------------------------------------------------- //
//  Abrir detalhes                                                  //
// --------------------------------------------------------------- //
function openDetail(id) {
    currentId = id;
    clearReveal();
    fetch(`<?= baseUrl('acessos/get/') ?>${id}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        if (data.error) { showToast(data.error, 'danger'); return; }
        populateDetail(data);
        showMode('view');
        offcanvas.show();
    })
    .catch(() => showToast('Erro ao carregar acesso.', 'danger'));
}

function populateDetail(data) {
    document.getElementById('offcanvas-title').textContent = 'Detalhes do Acesso';
    document.getElementById('detail-title').textContent    = data.title ?? '';
    document.getElementById('detail-category').textContent = data.category ?? '';
    document.getElementById('detail-password').textContent = '••••••••';
    document.getElementById('detail-icon').className       = `bi <?= $defaultIcon ?> text-primary`;

    setField('wrap-url',      'detail-url',      data.url,      true,  el => { el.href = data.url; el.textContent = data.url; });
    setField('wrap-username', 'detail-username', data.username, false);
    setField('wrap-notes',    'detail-notes',    data.notes,    false);
    setField('wrap-company',  'detail-company',  data.company_id ? `ID ${data.company_id}` : '', false);
}

function setField(wrapId, elId, value, isLink, extra) {
    const wrap = document.getElementById(wrapId);
    const el   = document.getElementById(elId);
    if (!value) { wrap.style.setProperty('display','none','important'); return; }
    wrap.style.removeProperty('display');
    if (extra) extra(el); else el.textContent = value;
}

// --------------------------------------------------------------- //
//  Reveal de senha                                                 //
// --------------------------------------------------------------- //
function revealInDetail() {
    if (!currentId) return;
    fetch(`<?= baseUrl('acessos/reveal/') ?>${currentId}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        if (data.error) { showToast(data.error, 'danger'); return; }
        const el        = document.getElementById('detail-password');
        const countdown = document.getElementById('reveal-countdown');
        const btn       = document.getElementById('btn-reveal');
        el.textContent  = data.password;
        el.style.letterSpacing = '0';
        countdown.style.display = '';
        btn.innerHTML   = '<i class="bi bi-eye-slash"></i>';
        clearReveal();
        let secs = 10;
        countdown.textContent = `A senha será ocultada em ${secs}s`;
        revealTimer = setInterval(() => {
            secs--;
            if (secs <= 0) { clearReveal(); }
            else countdown.textContent = `A senha será ocultada em ${secs}s`;
        }, 1000);
    })
    .catch(() => showToast('Erro ao revelar senha.', 'danger'));
}

function clearReveal() {
    if (revealTimer) { clearInterval(revealTimer); revealTimer = null; }
    const el = document.getElementById('detail-password');
    const countdown = document.getElementById('reveal-countdown');
    const btn = document.getElementById('btn-reveal');
    if (el)        { el.textContent = '••••••••'; el.style.letterSpacing = '2px'; }
    if (countdown) { countdown.style.display = 'none'; countdown.textContent = ''; }
    if (btn)       { btn.innerHTML = '<i class="bi bi-eye"></i>'; }
}

function revealAndCopy(id, btn) {
    fetch(`<?= baseUrl('acessos/reveal/') ?>${id}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        if (data.error) { showToast(data.error, 'danger'); return; }
        navigator.clipboard.writeText(data.password).then(() => {
            const icon = btn.querySelector('i');
            icon.className = 'bi bi-check-lg';
            setTimeout(() => { icon.className = 'bi bi-key-fill'; }, 1500);
        });
    })
    .catch(() => showToast('Erro ao copiar senha.', 'danger'));
}

function revealAndCopyFromDetail() {
    if (!currentId) return;
    fetch(`<?= baseUrl('acessos/reveal/') ?>${currentId}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        if (data.error) { showToast(data.error, 'danger'); return; }
        navigator.clipboard.writeText(data.password).then(() => {
            showToast('Senha copiada!', 'success');
        });
    })
    .catch(() => showToast('Erro ao copiar senha.', 'danger'));
}

// --------------------------------------------------------------- //
//  Formulário criar / editar                                       //
// --------------------------------------------------------------- //
function openNew() {
    currentId = null;
    clearReveal();
    document.getElementById('offcanvas-title').textContent = 'Novo Acesso';
    resetForm();
    document.getElementById('pw-hint').style.display = 'none';
    document.getElementById('btn-submit-label').textContent = 'Criar';
    showMode('edit');
    offcanvas.show();
}

function switchToEdit() {
    if (!currentId) return;
    fetch(`<?= baseUrl('acessos/get/') ?>${currentId}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        if (data.error) { showToast(data.error, 'danger'); return; }
        document.getElementById('offcanvas-title').textContent = 'Editar Acesso';
        document.getElementById('form-id').value      = data.id;
        document.getElementById('f-title').value      = data.title     ?? '';
        document.getElementById('f-category').value  = data.category  ?? 'Geral';
        document.getElementById('f-url').value        = data.url       ?? '';
        document.getElementById('f-username').value   = data.username  ?? '';
        document.getElementById('f-notes').value      = data.notes     ?? '';
        document.getElementById('f-password').value   = '';
        document.getElementById('f-company').value    = data.company_id ?? '';
        document.getElementById('pw-hint').style.display = '';
        document.getElementById('btn-submit-label').textContent = 'Salvar';
        showMode('edit');
    })
    .catch(() => showToast('Erro ao carregar acesso.', 'danger'));
}

function cancelEdit() {
    if (currentId) {
        openDetail(currentId);
    } else {
        offcanvas.hide();
    }
}

function submitForm(e) {
    e.preventDefault();
    const id  = document.getElementById('form-id').value;
    const url = id
        ? `<?= baseUrl('acessos/update/') ?>${id}`
        : `<?= baseUrl('acessos/store') ?>`;

    const btn = document.getElementById('btn-submit-form');
    btn.disabled = true;

    const fd = new FormData(e.target);
    fetch(url, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': CSRF },
        body: fd,
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        if (data.error) { showToast(data.error, 'danger'); return; }
        showToast(id ? 'Acesso atualizado!' : 'Acesso criado!', 'success');
        setTimeout(() => location.reload(), 800);
    })
    .catch(() => { btn.disabled = false; showToast('Erro ao salvar.', 'danger'); });
}

function confirmDelete() {
    if (!currentId) return;
    if (!confirm('Excluir este acesso? A operação não pode ser desfeita.')) return;

    const fd = new FormData();
    fd.append('csrf_token', CSRF);

    fetch(`<?= baseUrl('acessos/delete/') ?>${currentId}`, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': CSRF },
        body: fd,
    })
    .then(r => r.json())
    .then(data => {
        if (data.error) { showToast(data.error, 'danger'); return; }
        offcanvas.hide();
        showToast('Acesso excluído.', 'success');
        setTimeout(() => location.reload(), 800);
    })
    .catch(() => showToast('Erro ao excluir.', 'danger'));
}

// --------------------------------------------------------------- //
//  Utilitários                                                     //
// --------------------------------------------------------------- //
function showMode(mode) {
    document.getElementById('view-mode').style.display = mode === 'view' ? '' : 'none';
    document.getElementById('edit-mode').style.display = mode === 'edit' ? '' : 'none';
}

function resetForm() {
    document.getElementById('form-id').value     = '';
    document.getElementById('f-title').value     = '';
    document.getElementById('f-category').value  = 'Geral';
    document.getElementById('f-url').value       = '';
    document.getElementById('f-username').value  = '';
    document.getElementById('f-password').value  = '';
    document.getElementById('f-notes').value     = '';
    document.getElementById('f-company').value   = '';
}

function copyText(text, btn) {
    navigator.clipboard.writeText(text).then(() => {
        const icon = btn?.querySelector('i');
        if (icon) {
            const orig = icon.className;
            icon.className = 'bi bi-check-lg text-success';
            setTimeout(() => { icon.className = orig; }, 1500);
        }
    });
}

function togglePwdVisibility() {
    const inp  = document.getElementById('f-password');
    const icon = document.getElementById('pwd-eye-icon');
    if (inp.type === 'password') { inp.type = 'text';     icon.className = 'bi bi-eye-slash'; }
    else                         { inp.type = 'password'; icon.className = 'bi bi-eye'; }
}

function showToast(msg, type = 'success') {
    const wrap = document.createElement('div');
    wrap.className = `toast align-items-center text-bg-${type} border-0 show position-fixed bottom-0 end-0 m-3`;
    wrap.style.zIndex = 9999;
    wrap.setAttribute('role','alert');
    wrap.innerHTML = `<div class="d-flex"><div class="toast-body">${msg}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" onclick="this.closest('.toast').remove()"></button></div>`;
    document.body.appendChild(wrap);
    setTimeout(() => wrap.remove(), 3000);
}

// Fecha offcanvas → limpa reveal
document.getElementById('offcanvasAcesso')?.addEventListener('hide.bs.offcanvas', clearReveal);
</script>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
