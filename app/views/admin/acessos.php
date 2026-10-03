<?php $pageTitle = 'Acessos - ON Solutions Helpdesk'; $currentPage = 'acessos'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<?php
$grouped = [];
foreach ($accesses as $a) {
    $grouped[$a['category']][] = $a;
}
ksort($grouped);

// Mapa categoria → [ícone, cor de fundo, cor do ícone]
$catStyles = [
    'Geral'         => ['icon' => 'bi-key-fill',        'bg' => '#E0F7F4', 'color' => '#00BFA6'],
    'Servidor'      => ['icon' => 'bi-hdd-rack',        'bg' => '#FFF3E0', 'color' => '#F57C00'],
    'Hospedagem'    => ['icon' => 'bi-cloud-fill',      'bg' => '#E3F2FD', 'color' => '#1565C0'],
    'Email'         => ['icon' => 'bi-envelope-fill',   'bg' => '#FCE4EC', 'color' => '#C62828'],
    'CRM'           => ['icon' => 'bi-people-fill',     'bg' => '#E8EAF6', 'color' => '#3949AB'],
    'Banco'         => ['icon' => 'bi-database-fill',   'bg' => '#F3E5F5', 'color' => '#7B1FA2'],
    'Redes Sociais' => ['icon' => 'bi-share-fill',      'bg' => '#E8F5E9', 'color' => '#2E7D32'],
    'Financeiro'    => ['icon' => 'bi-cash-stack',      'bg' => '#FFFDE7', 'color' => '#F9A825'],
    'ERP'           => ['icon' => 'bi-building-gear',   'bg' => '#ECEFF1', 'color' => '#546E7A'],
    'VPN'           => ['icon' => 'bi-shield-fill',     'bg' => '#FBE9E7', 'color' => '#BF360C'],
];
$defaultStyle = ['icon' => 'bi-key-fill', 'bg' => '#E0F7F4', 'color' => '#00BFA6'];

function catStyle(string $cat, array $map, array $default): array {
    return $map[$cat] ?? $default;
}

// Mapa de nome amigável para a seção
$catSectionIcons = array_map(fn($s) => $s['icon'], $catStyles);
$catSectionColors = array_map(fn($s) => $s['color'], $catStyles);
?>

<style>
.vault-card {
    border: 1px solid #eef0f3 !important;
    border-radius: 14px !important;
    transition: transform 0.15s, box-shadow 0.15s, border-color 0.15s !important;
    cursor: pointer;
    overflow: hidden;
}
.vault-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(0,191,166,.13) !important;
    border-color: #B2F2E8 !important;
}
.vault-cat-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
    letter-spacing: 0.3px;
}
.vault-action-btn {
    border: 1px solid #e0e0e0;
    background: #fff;
    border-radius: 8px;
    padding: 5px 10px;
    font-size: 0.72rem;
    font-weight: 500;
    color: #555;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    cursor: pointer;
    transition: all 0.15s;
    white-space: nowrap;
}
.vault-action-btn:hover {
    background: #E0F7F4;
    border-color: #00BFA6;
    color: #00997D;
}
.vault-action-btn.key-btn:hover {
    background: #FFF3E0;
    border-color: #F57C00;
    color: #E65100;
}
.vault-action-btn.copied {
    background: #E0F7F4;
    border-color: #00BFA6;
    color: #00997D;
}
.section-divider {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 14px;
}
.section-divider::after {
    content: '';
    flex: 1;
    height: 1px;
    background: #eef0f3;
}
.offcanvas-field-block {
    background: #f8fafb;
    border-radius: 10px;
    padding: 12px 14px;
    border: 1px solid #eef0f3;
}
.offcanvas-field-label {
    font-size: 0.68rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #aaa;
    margin-bottom: 6px;
    font-weight: 600;
}
.offcanvas-field-value {
    font-size: 0.88rem;
    color: #222;
    word-break: break-all;
}
.reveal-bar {
    height: 4px;
    border-radius: 2px;
    background: #E0F7F4;
    overflow: hidden;
    margin-top: 8px;
    display: none;
}
.reveal-bar-inner {
    height: 100%;
    background: var(--primary, #00BFA6);
    transition: width 1s linear;
}
</style>

<div class="main-content">

    <!-- Top bar -->
    <div class="top-bar">
        <div>
            <h5 class="mb-0">
                <span class="rounded-2 d-inline-flex align-items-center justify-content-center me-2"
                      style="width:32px;height:32px;background:#E0F7F4;vertical-align:middle;">
                    <i class="bi bi-shield-lock-fill" style="color:#00BFA6;font-size:1rem;"></i>
                </span>
                Acessos
            </h5>
            <small class="text-muted ms-1">Cofre de credenciais — visível somente para você</small>
        </div>
        <button class="btn btn-primary btn-sm" id="btn-novo-acesso">
            <i class="bi bi-plus-lg me-1"></i> Novo Acesso
        </button>
    </div>

    <?php if ($msg = flash('success')): ?>
        <div class="alert alert-success alert-dismissible fade show"><?= escape($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <!-- Estado vazio -->
    <?php if (empty($accesses)): ?>
    <div class="d-flex flex-column align-items-center justify-content-center py-5 text-center" style="min-height:55vh;">
        <div class="rounded-circle d-flex align-items-center justify-content-center mb-4"
             style="width:96px;height:96px;background:linear-gradient(135deg,#E0F7F4,#B2F2E8);">
            <i class="bi bi-shield-lock-fill" style="font-size:2.6rem;color:#00BFA6;"></i>
        </div>
        <h5 class="fw-semibold mb-2">Nenhum acesso cadastrado ainda</h5>
        <p class="text-muted mb-4" style="max-width:400px;line-height:1.6;">
            Guarde aqui logins e senhas de sistemas — CRM, hospedagem,
            e‑mail corporativo, banco de dados e mais.<br>
            <strong style="color:#00BFA6;">Criptografadas</strong> e visíveis somente para você.
        </p>
        <button class="btn btn-primary px-4" id="btn-novo-acesso-empty">
            <i class="bi bi-plus-lg me-1"></i> Cadastrar primeiro acesso
        </button>
    </div>
    <?php else: ?>

    <!-- Barra de busca -->
    <div class="card mb-4">
        <div class="card-body py-2 px-3">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-6">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" id="search-acessos" class="form-control border-start-0 ps-0" placeholder="Buscar por título, login ou URL…">
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
                    <small class="text-muted" id="count-label">
                        <i class="bi bi-shield-check text-primary me-1"></i>
                        <?= count($accesses) ?> acesso<?= count($accesses) !== 1 ? 's' : '' ?>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Cards agrupados -->
    <div id="acessos-container">
    <?php foreach ($grouped as $categoria => $items):
        $cs = catStyle($categoria, $catStyles, $defaultStyle);
    ?>
        <div class="categoria-section mb-5" data-categoria="<?= escape($categoria) ?>">

            <!-- Cabeçalho da seção -->
            <div class="section-divider">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-2 d-flex align-items-center justify-content-center"
                         style="width:28px;height:28px;background:<?= $cs['bg'] ?>;">
                        <i class="bi <?= $cs['icon'] ?>" style="font-size:0.85rem;color:<?= $cs['color'] ?>;"></i>
                    </div>
                    <span class="fw-semibold" style="font-size:0.8rem;color:#444;"><?= escape($categoria) ?></span>
                    <span class="badge rounded-pill" style="background:<?= $cs['bg'] ?>;color:<?= $cs['color'] ?>;font-size:0.65rem;"><?= count($items) ?></span>
                </div>
            </div>

            <div class="row g-3 cards-row">
            <?php foreach ($items as $ac):
                $cs = catStyle($ac['category'], $catStyles, $defaultStyle);
            ?>
                <div class="col-12 col-sm-6 col-xl-4 acesso-card"
                     data-title="<?= escape(mb_strtolower($ac['title'])) ?>"
                     data-username="<?= escape(mb_strtolower($ac['username'] ?? '')) ?>"
                     data-url="<?= escape(mb_strtolower($ac['url'] ?? '')) ?>"
                     data-categoria="<?= escape($ac['category']) ?>">
                    <div class="card vault-card h-100 shadow-sm border-0" onclick="openDetail(<?= $ac['id'] ?>)">

                        <!-- Topo colorido -->
                        <div style="height:4px;background:<?= $cs['color'] ?>;border-radius:14px 14px 0 0;opacity:.7;"></div>

                        <div class="card-body p-3">
                            <div class="d-flex align-items-start gap-3">
                                <!-- Ícone -->
                                <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0"
                                     style="width:46px;height:46px;background:<?= $cs['bg'] ?>;">
                                    <i class="bi <?= $cs['icon'] ?>" style="font-size:1.3rem;color:<?= $cs['color'] ?>;"></i>
                                </div>
                                <!-- Info -->
                                <div class="flex-grow-1 min-w-0">
                                    <div class="fw-semibold text-truncate mb-1" style="font-size:0.93rem;"><?= escape($ac['title']) ?></div>
                                    <?php if (!empty($ac['username'])): ?>
                                    <div class="d-flex align-items-center gap-1 text-truncate" style="font-size:0.78rem;color:#777;">
                                        <i class="bi bi-person flex-shrink-0"></i>
                                        <span class="text-truncate"><?= escape($ac['username']) ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($ac['url'])): ?>
                                    <div class="text-truncate mt-1" style="font-size:0.74rem;">
                                        <a href="<?= escape($ac['url']) ?>" target="_blank" rel="noopener"
                                           style="color:<?= $cs['color'] ?>;text-decoration:none;"
                                           onclick="event.stopPropagation()">
                                            <i class="bi bi-link-45deg"></i>
                                            <?= escape(parse_url($ac['url'], PHP_URL_HOST) ?: $ac['url']) ?>
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Ações com texto -->
                            <div class="d-flex gap-2 mt-3 pt-2 border-top" onclick="event.stopPropagation()">
                                <?php if (!empty($ac['username'])): ?>
                                <button class="vault-action-btn flex-fill justify-content-center"
                                        onclick="copyText('<?= escape(addslashes($ac['username'])) ?>', this)"
                                        title="Copiar login">
                                    <i class="bi bi-person-fill"></i> Copiar login
                                </button>
                                <?php endif; ?>
                                <button class="vault-action-btn key-btn flex-fill justify-content-center"
                                        onclick="revealAndCopy(<?= $ac['id'] ?>, this)"
                                        title="Copiar senha">
                                    <i class="bi bi-key-fill"></i> Copiar senha
                                </button>
                            </div>
                        </div>

                        <?php if (!empty($ac['notes'])): ?>
                        <div class="px-3 pb-2" style="font-size:0.73rem;color:#aaa;">
                            <i class="bi bi-sticky me-1"></i><?= escape(mb_strimwidth($ac['notes'], 0, 72, '…')) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
    </div>

    <div id="no-results" class="text-center py-5 text-muted" style="display:none;">
        <i class="bi bi-search" style="font-size:2rem;opacity:.3;"></i>
        <p class="mt-2">Nenhum acesso encontrado para esta busca.</p>
    </div>

    <?php endif; ?>
</div>


<!-- ============================================================ -->
<!--  OFFCANVAS — detalhes + editar                               -->
<!-- ============================================================ -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasAcesso" style="width:min(440px,100%);">

    <!-- Header do offcanvas com cor dinâmica -->
    <div class="offcanvas-header" id="offcanvas-header" style="border-bottom:3px solid #00BFA6;padding-bottom:12px;">
        <div class="d-flex align-items-center gap-3">
            <div id="detail-icon-wrap" class="rounded-3 d-flex align-items-center justify-content-center"
                 style="width:42px;height:42px;background:#E0F7F4;flex-shrink:0;">
                <i id="detail-icon" class="bi bi-key-fill" style="font-size:1.2rem;color:#00BFA6;"></i>
            </div>
            <div>
                <h6 class="mb-0 fw-semibold" id="offcanvas-title">Detalhes do Acesso</h6>
                <small id="detail-category" class="text-muted"></small>
            </div>
        </div>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="offcanvas"></button>
    </div>

    <div class="offcanvas-body p-0" style="overflow-y:auto;">

        <!-- Modo visualização -->
        <div id="view-mode" class="p-4">

            <div class="d-flex flex-column gap-3">

                <!-- URL -->
                <div id="wrap-url" style="display:none!important">
                    <div class="offcanvas-field-block">
                        <div class="offcanvas-field-label"><i class="bi bi-link-45deg me-1"></i>URL / Endereço</div>
                        <div class="d-flex align-items-center gap-2">
                            <a id="detail-url" href="#" target="_blank" rel="noopener"
                               class="offcanvas-field-value text-decoration-none"
                               style="color:#00BFA6;"></a>
                            <button class="vault-action-btn ms-auto flex-shrink-0"
                                    onclick="copyText(document.getElementById('detail-url').textContent.trim(), this)">
                                <i class="bi bi-copy"></i> Copiar
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Login -->
                <div id="wrap-username" style="display:none!important">
                    <div class="offcanvas-field-block">
                        <div class="offcanvas-field-label"><i class="bi bi-person me-1"></i>Login / Usuário</div>
                        <div class="d-flex align-items-center gap-2">
                            <span id="detail-username" class="offcanvas-field-value font-monospace"></span>
                            <button class="vault-action-btn ms-auto flex-shrink-0"
                                    onclick="copyText(document.getElementById('detail-username').textContent.trim(), this)">
                                <i class="bi bi-copy"></i> Copiar login
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Senha -->
                <div>
                    <div class="offcanvas-field-block">
                        <div class="offcanvas-field-label"><i class="bi bi-key me-1"></i>Senha</div>
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span id="detail-password" class="offcanvas-field-value font-monospace flex-grow-1"
                                  style="letter-spacing:3px;font-size:1rem;">••••••••</span>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="vault-action-btn flex-fill justify-content-center" id="btn-reveal" onclick="revealInDetail()">
                                <i class="bi bi-eye"></i> <span id="btn-reveal-label">Mostrar senha</span>
                            </button>
                            <button class="vault-action-btn key-btn flex-fill justify-content-center" id="btn-copy-pw" onclick="revealAndCopyFromDetail()">
                                <i class="bi bi-clipboard"></i> Copiar senha
                            </button>
                        </div>
                        <!-- Barra de progresso do reveal -->
                        <div class="reveal-bar" id="reveal-bar">
                            <div class="reveal-bar-inner" id="reveal-bar-inner" style="width:100%;"></div>
                        </div>
                        <div id="reveal-countdown" class="mt-1" style="font-size:0.71rem;color:#aaa;display:none;"></div>
                    </div>
                </div>

                <!-- Notas -->
                <div id="wrap-notes" style="display:none!important">
                    <div class="offcanvas-field-block">
                        <div class="offcanvas-field-label"><i class="bi bi-sticky me-1"></i>Notas</div>
                        <div id="detail-notes" class="offcanvas-field-value" style="white-space:pre-wrap;line-height:1.5;color:#555;"></div>
                    </div>
                </div>

                <!-- Empresa -->
                <div id="wrap-company" style="display:none!important">
                    <div class="offcanvas-field-block">
                        <div class="offcanvas-field-label"><i class="bi bi-building me-1"></i>Empresa relacionada</div>
                        <div id="detail-company" class="offcanvas-field-value"></div>
                    </div>
                </div>

            </div>

            <!-- Ações -->
            <div class="d-flex gap-2 mt-4 pt-3 border-top">
                <button class="btn btn-outline-primary btn-sm px-3" onclick="switchToEdit()">
                    <i class="bi bi-pencil me-1"></i>Editar
                </button>
                <button class="btn btn-outline-danger btn-sm px-3 ms-auto" onclick="confirmDelete()">
                    <i class="bi bi-trash me-1"></i>Excluir
                </button>
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
                        <option value="Geral"><option value="Servidor"><option value="Hospedagem">
                        <option value="Email"><option value="CRM"><option value="Banco">
                        <option value="ERP"><option value="Redes Sociais"><option value="Financeiro">
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
                           placeholder="Digite a senha" autocomplete="new-password">
                    <button type="button" class="btn btn-outline-secondary" onclick="togglePwdVisibility()">
                        <i class="bi bi-eye" id="pwd-eye-icon"></i>
                    </button>
                </div>
                <div id="pw-hint" class="form-text" style="display:none;">
                    <i class="bi bi-info-circle me-1"></i>Deixe em branco para manter a senha atual.
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label fw-medium">Notas</label>
                <textarea name="notes" id="f-notes" class="form-control" rows="3" placeholder="Informações adicionais…"></textarea>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm px-4" id="btn-submit-form">
                    <i class="bi bi-check2 me-1"></i><span id="btn-submit-label">Salvar</span>
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="cancelEdit()">Cancelar</button>
            </div>
        </form>

    </div>
</div>


<script>
const CSRF = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
let currentId   = null;
let revealTimer = null;
let offcanvas   = null;
const REVEAL_SECS = 10;

// Mapa categoria → estilo (usado para colorir o offcanvas dinamicamente)
const CAT_STYLES = <?= json_encode($catStyles) ?>;
const DEFAULT_STYLE = <?= json_encode($defaultStyle) ?>;

function getCatStyle(cat) { return CAT_STYLES[cat] ?? DEFAULT_STYLE; }

document.addEventListener('DOMContentLoaded', () => {
    offcanvas = new bootstrap.Offcanvas(document.getElementById('offcanvasAcesso'));
    document.getElementById('btn-novo-acesso')?.addEventListener('click', openNew);
    document.getElementById('btn-novo-acesso-empty')?.addEventListener('click', openNew);
    document.getElementById('search-acessos')?.addEventListener('input', applyFilters);
    document.getElementById('filter-categoria')?.addEventListener('change', applyFilters);
});

// ---- Filtro ----
function applyFilters() {
    const q   = (document.getElementById('search-acessos')?.value ?? '').toLowerCase().trim();
    const cat = document.getElementById('filter-categoria')?.value ?? '';
    let visible = 0;
    document.querySelectorAll('.acesso-card').forEach(card => {
        const show = (!q || card.dataset.title.includes(q) || card.dataset.username.includes(q) || card.dataset.url.includes(q))
                  && (!cat || card.dataset.categoria === cat);
        card.style.display = show ? '' : 'none';
        if (show) visible++;
    });
    document.querySelectorAll('.categoria-section').forEach(sec => {
        const hasVisible = [...sec.querySelectorAll('.acesso-card')].some(c => c.style.display !== 'none');
        sec.style.display = hasVisible ? '' : 'none';
    });
    const lbl = document.getElementById('count-label');
    if (lbl) lbl.innerHTML = `<i class="bi bi-shield-check text-primary me-1"></i>${visible} acesso${visible !== 1 ? 's' : ''}`;
    const nr = document.getElementById('no-results');
    if (nr) nr.style.display = visible === 0 ? '' : 'none';
}

// ---- Abrir detalhes ----
function openDetail(id) {
    currentId = id;
    clearReveal();
    fetch(`<?= baseUrl('acessos/get/') ?>${id}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
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
    const cs = getCatStyle(data.category ?? '');

    // Colore o header do offcanvas
    document.getElementById('offcanvas-header').style.borderBottomColor = cs.color;
    const iconWrap = document.getElementById('detail-icon-wrap');
    iconWrap.style.background = cs.bg;
    const icon = document.getElementById('detail-icon');
    icon.className = 'bi ' + cs.icon;
    icon.style.color = cs.color;

    document.getElementById('offcanvas-title').textContent  = data.title ?? '';
    document.getElementById('detail-category').textContent  = data.category ?? '';
    document.getElementById('detail-password').textContent  = '••••••••';
    document.getElementById('detail-password').style.letterSpacing = '3px';

    setField('wrap-url',      'detail-url',      data.url,       el => { el.href = data.url; el.textContent = data.url; });
    setField('wrap-username', 'detail-username', data.username,  el => { el.textContent = data.username; });
    setField('wrap-notes',    'detail-notes',    data.notes,     el => { el.textContent = data.notes; });
    setField('wrap-company',  'detail-company',  data.company_id ? `ID ${data.company_id}` : '', el => { el.textContent = `ID ${data.company_id}`; });
}

function setField(wrapId, elId, value, setter) {
    const wrap = document.getElementById(wrapId);
    const el   = document.getElementById(elId);
    if (!value) { wrap.style.setProperty('display','none','important'); return; }
    wrap.style.removeProperty('display');
    setter(el);
}

// ---- Reveal ----
function revealInDetail() {
    if (!currentId) return;
    const btn   = document.getElementById('btn-reveal');
    const label = document.getElementById('btn-reveal-label');
    const el    = document.getElementById('detail-password');
    const isShowing = label.textContent.includes('Ocultar');

    if (isShowing) { clearReveal(); return; }

    fetch(`<?= baseUrl('acessos/reveal/') ?>${currentId}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
    .then(data => {
        if (data.error) { showToast(data.error, 'danger'); return; }
        const pwd = data.password || '';
        el.textContent = pwd === '' ? '(sem senha)' : pwd;
        el.style.letterSpacing = '0';
        label.textContent = 'Ocultar';
        btn.querySelector('i').className = 'bi bi-eye-slash';
        if (!pwd) return;

        // Barra de progresso
        const bar = document.getElementById('reveal-bar');
        const barInner = document.getElementById('reveal-bar-inner');
        const countdown = document.getElementById('reveal-countdown');
        bar.style.display = '';
        barInner.style.transition = 'none';
        barInner.style.width = '100%';
        countdown.style.display = '';

        let secs = REVEAL_SECS;
        countdown.textContent = `Ocultando em ${secs}s`;
        // Anima a barra
        setTimeout(() => {
            barInner.style.transition = `width ${REVEAL_SECS}s linear`;
            barInner.style.width = '0%';
        }, 50);

        revealTimer = setInterval(() => {
            secs--;
            if (secs <= 0) { clearReveal(); }
            else countdown.textContent = `Ocultando em ${secs}s`;
        }, 1000);
    })
    .catch(err => showToast('Erro ao revelar: ' + err.message, 'danger'));
}

function clearReveal() {
    if (revealTimer) { clearInterval(revealTimer); revealTimer = null; }
    const el        = document.getElementById('detail-password');
    const countdown = document.getElementById('reveal-countdown');
    const btn       = document.getElementById('btn-reveal');
    const label     = document.getElementById('btn-reveal-label');
    const bar       = document.getElementById('reveal-bar');
    if (el)        { el.textContent = '••••••••'; el.style.letterSpacing = '3px'; }
    if (countdown) { countdown.style.display = 'none'; countdown.textContent = ''; }
    if (bar)       { bar.style.display = 'none'; }
    if (btn)       { btn.querySelector('i').className = 'bi bi-eye'; }
    if (label)     { label.textContent = 'Mostrar senha'; }
}

function revealAndCopy(id, btn) {
    fetch(`<?= baseUrl('acessos/reveal/') ?>${id}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
    .then(data => {
        if (data.error) { showToast(data.error, 'danger'); return; }
        if (!data.password) { showToast('Sem senha cadastrada.', 'warning'); return; }
        navigator.clipboard.writeText(data.password).then(() => {
            btn.innerHTML = '<i class="bi bi-check-lg"></i> Copiada!';
            btn.classList.add('copied');
            setTimeout(() => {
                btn.innerHTML = '<i class="bi bi-key-fill"></i> Copiar senha';
                btn.classList.remove('copied');
            }, 1800);
        });
    })
    .catch(err => showToast('Erro: ' + err.message, 'danger'));
}

function revealAndCopyFromDetail() {
    if (!currentId) return;
    fetch(`<?= baseUrl('acessos/reveal/') ?>${currentId}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
    .then(data => {
        if (data.error) { showToast(data.error, 'danger'); return; }
        if (!data.password) { showToast('Sem senha cadastrada.', 'warning'); return; }
        navigator.clipboard.writeText(data.password).then(() => {
            const btn = document.getElementById('btn-copy-pw');
            btn.innerHTML = '<i class="bi bi-check-lg"></i> Copiada!';
            btn.classList.add('copied');
            setTimeout(() => { btn.innerHTML = '<i class="bi bi-clipboard"></i> Copiar senha'; btn.classList.remove('copied'); }, 1800);
        });
    })
    .catch(err => showToast('Erro: ' + err.message, 'danger'));
}

// ---- Formulário ----
function openNew() {
    currentId = null;
    clearReveal();
    document.getElementById('offcanvas-title').textContent = 'Novo Acesso';
    document.getElementById('offcanvas-header').style.borderBottomColor = '#00BFA6';
    document.getElementById('detail-icon-wrap').style.background = '#E0F7F4';
    const icon = document.getElementById('detail-icon');
    icon.className = 'bi bi-key-fill'; icon.style.color = '#00BFA6';
    document.getElementById('detail-category').textContent = '';
    resetForm();
    document.getElementById('pw-hint').style.display = 'none';
    document.getElementById('btn-submit-label').textContent = 'Criar acesso';
    showMode('edit');
    offcanvas.show();
}

function switchToEdit() {
    if (!currentId) return;
    fetch(`<?= baseUrl('acessos/get/') ?>${currentId}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    .then(r => r.json())
    .then(data => {
        if (data.error) { showToast(data.error, 'danger'); return; }
        document.getElementById('offcanvas-title').textContent = 'Editar Acesso';
        document.getElementById('form-id').value     = data.id;
        document.getElementById('f-title').value     = data.title    ?? '';
        document.getElementById('f-category').value = data.category ?? 'Geral';
        document.getElementById('f-url').value       = data.url      ?? '';
        document.getElementById('f-username').value  = data.username ?? '';
        document.getElementById('f-notes').value     = data.notes    ?? '';
        document.getElementById('f-password').value  = '';
        document.getElementById('f-company').value   = data.company_id ?? '';
        document.getElementById('pw-hint').style.display = '';
        document.getElementById('btn-submit-label').textContent = 'Salvar alterações';
        showMode('edit');
    })
    .catch(() => showToast('Erro ao carregar.', 'danger'));
}

function cancelEdit() {
    if (currentId) openDetail(currentId);
    else offcanvas.hide();
}

function submitForm(e) {
    e.preventDefault();
    const id  = document.getElementById('form-id').value;
    const url = id ? `<?= baseUrl('acessos/update/') ?>${id}` : `<?= baseUrl('acessos/store') ?>`;
    const btn = document.getElementById('btn-submit-form');
    btn.disabled = true;
    fetch(url, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': CSRF },
        body: new FormData(e.target),
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        if (data.error) { showToast(data.error, 'danger'); return; }
        showToast(id ? 'Acesso atualizado!' : 'Acesso criado com sucesso!', 'success');
        setTimeout(() => location.reload(), 800);
    })
    .catch(() => { btn.disabled = false; showToast('Erro ao salvar.', 'danger'); });
}

function confirmDelete() {
    if (!currentId) return;
    if (!confirm('Excluir este acesso? A operação não pode ser desfeita.')) return;
    const fd = new FormData(); fd.append('csrf_token', CSRF);
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

// ---- Utilitários ----
function showMode(mode) {
    document.getElementById('view-mode').style.display = mode === 'view' ? '' : 'none';
    document.getElementById('edit-mode').style.display = mode === 'edit' ? '' : 'none';
}

function resetForm() {
    ['form-id','f-title','f-url','f-username','f-password','f-notes'].forEach(id => {
        document.getElementById(id).value = '';
    });
    document.getElementById('f-category').value = 'Geral';
    document.getElementById('f-company').value  = '';
}

function copyText(text, btn) {
    navigator.clipboard.writeText(text).then(() => {
        const orig = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check-lg"></i> Copiado!';
        btn.classList.add('copied');
        setTimeout(() => { btn.innerHTML = orig; btn.classList.remove('copied'); }, 1800);
    });
}

function togglePwdVisibility() {
    const inp = document.getElementById('f-password');
    const ico = document.getElementById('pwd-eye-icon');
    if (inp.type === 'password') { inp.type = 'text';     ico.className = 'bi bi-eye-slash'; }
    else                         { inp.type = 'password'; ico.className = 'bi bi-eye'; }
}

function showToast(msg, type = 'success') {
    const icons = { success: 'bi-check-circle-fill', danger: 'bi-x-circle-fill', warning: 'bi-exclamation-triangle-fill' };
    const colors = { success: '#00BFA6', danger: '#dc3545', warning: '#f57c00' };
    const wrap = document.createElement('div');
    wrap.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:9999;min-width:260px;max-width:360px;';
    wrap.innerHTML = `<div class="d-flex align-items-center gap-2 p-3 rounded-3 shadow"
        style="background:#fff;border-left:4px solid ${colors[type]||'#00BFA6'};font-size:0.85rem;">
        <i class="bi ${icons[type]||'bi-info-circle'}" style="color:${colors[type]||'#00BFA6'};font-size:1rem;flex-shrink:0;"></i>
        <span class="flex-grow-1">${msg}</span>
        <button onclick="this.closest('[style]').remove()" style="background:none;border:none;cursor:pointer;color:#aaa;font-size:1rem;">×</button>
    </div>`;
    document.body.appendChild(wrap);
    setTimeout(() => wrap.remove(), 3500);
}

document.getElementById('offcanvasAcesso')?.addEventListener('hide.bs.offcanvas', clearReveal);
</script>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
