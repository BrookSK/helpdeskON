<?php
/**
 * Planejamento no celular/tablet (< 992px) — dados e lógica.
 * Estrutura/estilos em _mobile_markup.php. Incluído no fim de index.php,
 * depois do script principal (usa BASE, openCardModal, openCreateModal,
 * calendarFilterParams, applyView, cardIsOverdue e os helpers de categoria).
 *
 * Filtros: aplicados no servidor (mesmos parâmetros do Kanban + prioridade,
 * técnico e prazo), recarregando a página — assim contagens e "Carregar mais"
 * batem exatamente. A busca digitada filtra na hora os cards já carregados;
 * "Enter" busca no servidor (título e descrição, todos os cards).
 *
 * Ações rápidas (Concluir/Iniciar/Reabrir, Mover, ações em lote) aplicam na
 * tela na hora e só gravam depois de alguns segundos, dando tempo de
 * "Desfazer" sem disparar notificações em dobro. Status usa o mesmo endpoint
 * do arrastar no Kanban (planning/updateStatus).
 */
$pmUser = $user ?? [];
$pmForced = in_array($pmUser['role'] ?? '', ['whatsapp_agent', 'developer', 'analyst', 'comercial'], true);

$pmShort = [
    'open' => 'Aberto', 'in_progress' => 'Andamento', 'em_revisao_interna' => 'Revisão',
    'waiting_client' => 'Aguard.', 'em_homologacao' => 'Homolog.', 'aprovado_producao' => 'Aprov. Prod.',
    'completed' => 'Concluído', 'denied' => 'Negado', 'archived' => 'Arquivado',
];
$pmStatuses = [];
foreach ($statusLabels as $k => $info) {
    if (!array_key_exists($k, (array) ($columnCounts ?? []))) continue;
    $pmStatuses[] = ['k' => $k, 'l' => $info[0], 's' => $pmShort[$k] ?? $info[0], 'c' => $info[1]];
}

$pmCards = [];
foreach ((array) ($grouped ?? []) as $pmList) {
    foreach ($pmList as $c) {
        $pmCards[] = [
            'id' => (int) $c['id'],
            'title' => (string) $c['title'],
            'status' => (string) $c['status'],
            'priority' => (string) $c['priority'],
            'company_name' => $c['company_name'] ?? null,
            'created_by_name' => $c['created_by_name'] ?? null,
            'assigned_name' => $c['assigned_name'] ?? null,
            'assigned_to' => isset($c['assigned_to']) ? (int) $c['assigned_to'] : null,
            'due_date' => $c['due_date'] ?? null,
            'category' => $c['category'] ?? null,
            'scope_rejected_reason' => !empty($c['scope_rejected_reason']) ? 1 : null,
            'scope_approved_at' => !empty($c['scope_approved_at']) ? 1 : null,
        ];
    }
}

$pmPeople = fn($list) => array_values(array_map(fn($m) => ['id' => (string) $m['id'], 'name' => (string) $m['name']], (array) $list));

$pmData = [
    'cards' => $pmCards,
    'counts' => array_map('intval', (array) ($columnCounts ?? [])),
    'statuses' => $pmStatuses,
    'quick' => array_map('intval', (array) ($quickCounts ?? [])),
    'me' => ['id' => (string) ($pmUser['id'] ?? ''), 'name' => (string) ($pmUser['name'] ?? '')],
    'forced' => $pmForced,
    'team' => $pmPeople($teamMembers ?? []),
    'requesters' => $pmPeople($requesters ?? []),
    'technicians' => $pmPeople($techniciansList ?? []),
    'attendants' => $pmPeople($attendantsList ?? []),
    'companies' => $pmPeople($companies ?? []),
    'activeView' => $activeView ?? 'kanban',
];
?>
<script>
(function () {
    'use strict';
    const D = <?= json_encode($pmData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const mq = window.matchMedia('(max-width: 991.98px)');
    const root = document.getElementById('pm');
    if (!root) return;
    const $ = id => document.getElementById(id);
    const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
    const norm = s => String(s == null ? '' : s).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');

    const ST = D.statuses;
    const stOf = k => ST.find(s => s.k === k) || { k, l: k, s: k, c: '#667085' };
    const PRIO = [
        { k: 'high', l: 'Alta', c: '#d6293e' },
        { k: 'medium', l: 'Média', c: '#f08c00' },
        { k: 'low', l: 'Baixa', c: '#3b82f6' },
        { k: 'urgent', l: 'Urgente', c: '#9c27b0' },
    ];
    const prOf = k => PRIO.find(p => p.k === k) || { k, l: k, c: '#667085' };
    const CLOSED = ['completed', 'denied', 'archived'];
    const ORDERS = [['', 'Ordenação padrão'], ['overdue', 'Vencidos primeiro'], ['priority', 'Por prioridade'], ['newest', 'Mais recentes']];
    const DEADLINES = [['late', 'Atrasados'], ['today', 'Vencem hoje'], ['week', 'Próximos 7 dias']];
    const nameMap = {};
    [D.team, D.requesters, D.technicians, D.attendants].forEach(l => l.forEach(p => { nameMap[p.id] = p.name; }));
    const companyMap = {};
    D.companies.forEach(c => { companyMap[c.id] = c.name; });

    // Filtros efetivos aplicados pelo servidor (definidos no script principal).
    const EF = (typeof EFFECTIVE_FILTERS !== 'undefined') ? EFFECTIVE_FILTERS : {};
    const CUR = {
        company_id: (EF.company_id || []).map(String),
        assigned_to: (EF.assigned_to || []).map(String),
        created_by: (EF.created_by || []).map(String),
        statuses: (EF.statuses || []).slice(),
        priority: (EF.priority || []).slice(),
        technical: (EF.technical || []).map(String),
        deadline: EF.deadline || '',
        order: EF.order || '',
        q: EF.q || '',
    };
    const copyF = f => JSON.parse(JSON.stringify(f));

    // ---------- estado ----------
    const cards = new Map();
    const order = {};
    ST.forEach(s => { order[s.k] = []; });
    D.cards.forEach(c => {
        if (cards.has(c.id)) return;
        cards.set(c.id, c);
        (order[c.status] = order[c.status] || []).push(c.id);
    });
    const counts = Object.assign({}, D.counts);
    const offsets = {};
    Object.keys(order).forEach(k => { offsets[k] = order[k].length; });

    let tab = sessionStorage.getItem('pm-tab') || 'all';
    if (tab !== 'all' && !ST.some(s => s.k === tab)) tab = 'all';
    let shut = {};
    try { shut = JSON.parse(localStorage.getItem('pm-shut') || '{}') || {}; } catch (e) { shut = {}; }
    let compact = localStorage.getItem('pm-compact') === '1';
    let localQ = '';
    let selMode = false;
    let sel = new Set();
    let pending = null;
    let dirty = false;

    // ---------- helpers ----------
    const p2 = n => String(n).padStart(2, '0');
    function parseDate(s) { return s ? new Date(String(s).replace(' ', 'T')) : null; }
    function fmtDue(s) { const d = parseDate(s); return d && !isNaN(d) ? p2(d.getDate()) + '/' + p2(d.getMonth() + 1) + ' ' + p2(d.getHours()) + ':' + p2(d.getMinutes()) : ''; }
    function isLate(c) { return typeof cardIsOverdue === 'function' ? cardIsOverdue(c) : false; }
    function ini(n) { return String(n || '?').trim().split(/\s+/).map(p => p[0] || '').slice(0, 2).join('').toUpperCase(); }
    function hue(n) { let h = 0; const s = String(n || ''); for (let i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) % 360; return 'hsl(' + h + ',45%,42%)'; }
    function matches(c) {
        if (!localQ) return true;
        const hay = norm('#' + c.id + ' ' + c.title + ' ' + (c.company_name || '') + ' ' + (c.created_by_name || '') + ' ' + (c.assigned_name || ''));
        return norm(localQ).split(/\s+/).filter(Boolean).every(t => hay.indexOf(t) >= 0);
    }
    function idsOf(status) { return (order[status] || []).filter(id => cards.has(id) && matches(cards.get(id))); }
    function tabCount(status) { return localQ ? idsOf(status).length : (counts[status] || 0); }
    function hasUnloaded() { return ST.some(s => (counts[s.k] || 0) > (order[s.k] || []).length); }
    function isMine(f) { return f.assigned_to.length === 1 && f.assigned_to[0] === D.me.id; }
    function goAction(c) {
        if (c.status === 'open') return { to: 'in_progress', l: 'Iniciar', i: 'bi-play-fill' };
        if (CLOSED.indexOf(c.status) >= 0) return { to: 'open', l: 'Reabrir', i: 'bi-arrow-counterclockwise' };
        return { to: 'completed', l: 'Concluir', i: 'bi-check2' };
    }
    function nFilters(f) {
        return f.priority.length + (D.forced ? 0 : f.assigned_to.length) + f.created_by.length + f.technical.length
            + f.company_id.length + (f.deadline ? 1 : 0) + (f.order ? 1 : 0);
    }
    function paramsFor(f) {
        const p = new URLSearchParams();
        p.set('show_all', '1');
        ['company_id', 'assigned_to', 'created_by', 'statuses', 'priority', 'technical'].forEach(k => {
            if (k === 'assigned_to' && D.forced) return;
            (f[k] || []).forEach(v => p.append(k + '[]', v));
        });
        if (f.order) p.set('order', f.order);
        if (f.q) p.set('q', f.q);
        if (f.deadline) p.set('deadline', f.deadline);
        return p;
    }
    function navigate(f) {
        commitPending(true);
        location.href = BASE + 'planning?' + paramsFor(f).toString();
    }

    // ---------- render ----------
    function render() {
        root.classList.toggle('compact', compact);
        root.classList.toggle('selmode', selMode);
        renderTabs();
        const total = ST.reduce((a, s) => a + (counts[s.k] || 0), 0);
        $('pm-sub').textContent = total + ' card' + (total === 1 ? '' : 's');
        const nf = nFilters(CUR);
        $('pm-fbadge').hidden = !nf;
        $('pm-fbadge').textContent = nf;
        $('pm-filter').classList.toggle('on', nf > 0);
        $('pm-btn-cmp').classList.toggle('on', compact);
        $('pm-btn-sel').classList.toggle('on', selMode);
        $('pm-search').classList.toggle('has', !!localQ);
        renderQuick();
        renderActives();
        renderList();
        $('pm-sel-cnt').textContent = sel.size + ' marcado' + (sel.size === 1 ? '' : 's');
    }

    function renderTabs() {
        const all = ST.reduce((a, s) => a + tabCount(s.k), 0);
        const T = [{ k: 'all', s: 'Todos', c: '#1f2937', n: all }].concat(ST.map(s => ({ k: s.k, s: s.s, c: s.c, n: tabCount(s.k) })));
        $('pm-tabs').innerHTML = T.map(t =>
            '<button type="button" class="pm-tab" role="tab" data-tab="' + t.k + '" style="--c:' + t.c + '" aria-selected="' + (t.k === tab) + '">' +
            '<span class="n">' + t.n + '</span>' + esc(t.s) + '</button>').join('');
        const act = $('pm-tabs').querySelector('[aria-selected="true"]');
        if (act) {
            const bar = $('pm-tabs');
            const left = act.offsetLeft - (bar.clientWidth - act.offsetWidth) / 2;
            bar.scrollLeft = Math.max(0, left);
        }
    }

    function renderQuick() {
        const q = [];
        if (!D.forced) q.push({ k: 'mine', l: 'Minhas', on: isMine(CUR), n: D.quick.mine });
        q.push({ k: 'late', l: 'Atrasadas', on: CUR.deadline === 'late', n: D.quick.late, dot: 1 });
        q.push({ k: 'today', l: 'Hoje', on: CUR.deadline === 'today', n: D.quick.today });
        $('pm-quick').innerHTML = q.map(x =>
            '<button type="button" class="pm-qb' + (x.on ? ' on' : '') + '" data-quick="' + x.k + '" aria-pressed="' + x.on + '">' +
            (x.dot ? '<span class="dot"></span>' : '') + x.l + ' <b>' + (x.n || 0) + '</b></button>').join('');
    }

    function activeChips() {
        const out = [];
        CUR.priority.forEach(v => out.push({ l: 'Prior. ' + prOf(v).l, rm: f => { f.priority = f.priority.filter(x => x !== v); } }));
        if (!D.forced && !isMine(CUR)) {
            CUR.assigned_to.forEach(v => out.push({ l: 'Atend. ' + (v === 'none' ? 'sem responsável' : (nameMap[v] || '#' + v)), rm: f => { f.assigned_to = f.assigned_to.filter(x => x !== v); } }));
        }
        CUR.created_by.forEach(v => out.push({ l: 'Solic. ' + (nameMap[v] || '#' + v), rm: f => { f.created_by = f.created_by.filter(x => x !== v); } }));
        CUR.technical.forEach(v => out.push({ l: v === 'none' ? 'Sem técnico' : 'Téc. ' + (nameMap[v] || '#' + v), rm: f => { f.technical = f.technical.filter(x => x !== v); } }));
        CUR.company_id.forEach(v => out.push({ l: companyMap[v] || ('Empresa #' + v), rm: f => { f.company_id = f.company_id.filter(x => x !== v); } }));
        if (CUR.deadline === 'week') out.push({ l: 'Próximos 7 dias', rm: f => { f.deadline = ''; } });
        if (CUR.order) out.push({ l: (ORDERS.find(o => o[0] === CUR.order) || ['', CUR.order])[1], rm: f => { f.order = ''; } });
        if (CUR.statuses.length) out.push({ l: CUR.statuses.length + ' status', rm: f => { f.statuses = []; } });
        if (CUR.q) out.push({ l: 'Busca: ' + CUR.q, rm: f => { f.q = ''; } });
        return out;
    }
    let chipList = [];
    function renderActives() {
        chipList = activeChips();
        let html = chipList.map((c, i) => '<button type="button" class="pm-ac" data-chip="' + i + '"><span>' + esc(c.l) + '</span><i class="bi bi-x"></i></button>').join('');
        if (chipList.length > 1) html += '<button type="button" class="pm-ac clear" data-clear="1">Limpar tudo</button>';
        $('pm-actives').innerHTML = html;
    }

    function cardHtml(c) {
        const p = prOf(c.priority);
        const late = isLate(c);
        const g = goAction(c);
        let tags = '';
        if (c.category && typeof categoryLabelJs === 'function') {
            tags += '<span class="pm-tag ' + categoryBadgeClassJs(c.category) + '" style="' + categoryBadgeStyleJs(c.category) + '">' + esc(categoryLabelJs(c.category)) + '</span>';
        }
        if (c.status === 'aprovado_producao') tags += '<span class="pm-tag bg-success">Aprov. Produção</span>';
        else if (c.scope_rejected_reason) tags += '<span class="pm-tag bg-danger">Recusado</span>';
        else if (c.scope_approved_at) tags += '<span class="pm-tag bg-success">Aprovado</span>';
        const meta = [];
        if (c.company_name) meta.push('<li class="m-emp"><i class="bi bi-building"></i><span>' + esc(c.company_name) + '</span></li>');
        if (c.created_by_name) meta.push('<li class="m-sol"><i class="bi bi-person-vcard"></i><span>' + esc(c.created_by_name) + '</span></li>');
        meta.push('<li class="m-at"><i class="bi bi-person"></i><span>' + esc(c.assigned_name || 'Não atribuído') + '</span></li>');
        const due = c.due_date ? '<span class="due"><i class="bi bi-clock"></i>' + fmtDue(c.due_date) + '</span>' : '';
        return '<article class="pm-card' + (late ? ' late' : '') + (sel.has(c.id) ? ' sel' : '') + '" data-id="' + c.id + '" style="--pc:' + p.c + '">' +
            '<span class="pm-check"><i class="bi bi-check-lg"></i></span>' +
            '<div class="c1"><span class="id">#' + c.id + '</span><span class="pr">' + esc(p.l) + '</span></div>' +
            '<h3>' + esc(c.title) + '</h3>' +
            (tags ? '<div class="pm-tags">' + tags + '</div>' : '') +
            '<div class="mrow"><ul class="meta">' + meta.join('') + '</ul>' + due + '</div>' +
            '<div class="acts"><button type="button" class="go" data-go="' + c.id + '">' + g.l + ' <i class="bi ' + g.i + '"></i></button>' +
            '<button type="button" class="mv" data-move="' + c.id + '" aria-label="Mover card #' + c.id + '"><i class="bi bi-three-dots"></i></button></div>' +
            '</article>';
    }

    function moreHtml(status) {
        if (localQ) return '';
        const rest = (counts[status] || 0) - (order[status] || []).length;
        if (rest <= 0) return '';
        return '<button type="button" class="pm-more" data-more="' + status + '"><i class="bi bi-chevron-down"></i> Carregar mais (' + rest + ' restante' + (rest === 1 ? '' : 's') + ')</button>';
    }

    function visibleIds() {
        if (tab === 'all') return ST.reduce((a, s) => a.concat(idsOf(s.k)), []);
        return idsOf(tab);
    }

    function renderList() {
        const ids = visibleIds();
        const shownTotal = tab === 'all' ? ST.reduce((a, s) => a + tabCount(s.k), 0) : tabCount(tab);
        const lateN = ids.filter(id => isLate(cards.get(id))).length;
        $('pm-res').innerHTML = shownTotal + ' card' + (shownTotal === 1 ? '' : 's') +
            (localQ ? ' encontrado' + (shownTotal === 1 ? '' : 's') : '') +
            (lateN ? ' · <span style="color:var(--pm-late)">' + lateN + ' atrasado' + (lateN > 1 ? 's' : '') + '</span>' : '');

        let html = '';
        if (!shownTotal) {
            html = '<div class="pm-empty"><b>Nenhum card aqui</b>' +
                (localQ || nFilters(CUR) || CUR.q ? 'Nenhum card combina com a busca ou os filtros atuais.' : 'Os cards movidos para este status aparecem aqui.') + '</div>';
        } else if (tab === 'all') {
            ST.forEach(s => {
                const l = idsOf(s.k);
                const n = tabCount(s.k);
                if (!n) return;
                html += '<button type="button" class="pm-gh' + (shut[s.k] ? ' shut' : '') + '" style="--c:' + s.c + '" data-group="' + s.k + '" aria-expanded="' + !shut[s.k] + '">' +
                    esc(s.l) + '<span class="cnt">' + n + '</span><span class="chev"><i class="bi bi-chevron-down"></i></span></button>';
                if (!shut[s.k]) html += '<div class="pm-gl">' + l.map(id => cardHtml(cards.get(id))).join('') + moreHtml(s.k) + '</div>';
            });
        } else {
            html = '<div class="pm-gl">' + ids.map(id => cardHtml(cards.get(id))).join('') + moreHtml(tab) + '</div>';
        }
        if (localQ && hasUnloaded()) {
            html += '<div class="pm-hint">A busca acima olha só os cards já carregados. <button type="button" data-search-all="1">Buscar em todos os cards</button></div>';
        }
        $('pm-cards').innerHTML = html;
    }

    // ---------- alterações com "Desfazer" ----------
    function moveStatus(c, to) {
        const from = c.status;
        if (from === to) return;
        order[from] = (order[from] || []).filter(x => x !== c.id);
        counts[from] = Math.max(0, (counts[from] || 0) - 1);
        (order[to] = order[to] || []).unshift(c.id);
        counts[to] = (counts[to] || 0) + 1;
        c.status = to;
    }
    function applyChange(ch, val) {
        const c = cards.get(ch.id);
        if (!c) return;
        if (ch.field === 'status') moveStatus(c, val);
        else if (ch.field === 'priority') c.priority = val;
        else if (ch.field === 'assigned_to') {
            c.assigned_to = val ? parseInt(val, 10) : null;
            c.assigned_name = val ? (nameMap[String(val)] || c.assigned_name) : null;
        }
    }
    function mergeCard(card) {
        const id = parseInt(card.id, 10);
        const c = cards.get(id);
        if (!c) return false;
        if (card.status && card.status !== c.status) {
            const from = c.status;
            moveStatus(c, card.status);
            offsets[from] = Math.max(0, (offsets[from] || 0) - 1);
            offsets[card.status] = (offsets[card.status] || 0) + 1;
        }
        ['title', 'priority', 'company_name', 'created_by_name', 'assigned_name', 'due_date', 'category', 'scope_rejected_reason', 'scope_approved_at'].forEach(k => {
            if (k in card) c[k] = card[k];
        });
        if ('assigned_to' in card) c.assigned_to = card.assigned_to != null ? parseInt(card.assigned_to, 10) : null;
        return true;
    }
    function send(ch, keepalive) {
        const fd = new FormData();
        let url;
        if (ch.field === 'status') {
            url = BASE + 'planning/updateStatus/' + ch.id;
            fd.append('status', ch.value);
            fd.append('position', '0');
        } else {
            url = BASE + 'planning/update/' + ch.id;
            fd.append(ch.field, ch.value == null ? '' : ch.value);
        }
        return fetch(url, { method: 'POST', body: fd, keepalive: !!keepalive, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(async r => {
                const d = await r.json().catch(() => ({}));
                if (!r.ok || !d.success) throw new Error(d.error || 'Falha ao salvar');
                return d;
            });
    }
    function stage(changes, msg) {
        changes = changes.filter(ch => { const c = cards.get(ch.id); return c && String(c[ch.field] == null ? '' : c[ch.field]) !== String(ch.value == null ? '' : ch.value); });
        if (!changes.length) return;
        commitPending(false);
        changes.forEach(ch => applyChange(ch, ch.value));
        pending = { changes, timer: setTimeout(() => commitPending(false), 4500) };
        render();
        toast(msg, () => {
            if (!pending) return;
            clearTimeout(pending.timer);
            pending.changes.forEach(ch => applyChange(ch, ch.prev));
            pending = null;
            render();
        });
    }
    function commitPending(keepalive) {
        if (!pending) return Promise.resolve();
        const p = pending;
        pending = null;
        clearTimeout(p.timer);
        hideUndo();
        return Promise.all(p.changes.map(ch => send(ch, keepalive).then(d => {
            dirty = true;
            if (ch.field === 'status') {
                offsets[ch.prev] = Math.max(0, (offsets[ch.prev] || 0) - 1);
                offsets[ch.value] = (offsets[ch.value] || 0) + 1;
            }
            if (d && d.card) mergeCard(d.card);
        }).catch(err => {
            applyChange(ch, ch.prev);
            render();
            toast('Não foi possível salvar #' + ch.id + ': ' + err.message);
        }))).then(() => render());
    }
    window.addEventListener('pagehide', () => commitPending(true));

    // ---------- aviso ----------
    let toastTimer = null;
    let undoFn = null;
    function toast(msg, undo) {
        const t = $('pm-toast');
        undoFn = undo || null;
        t.innerHTML = '<span>' + esc(msg) + '</span>' + (undo ? '<button type="button" id="pm-undo">Desfazer</button>' : '');
        t.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => t.classList.remove('show'), 4500);
        if (undo) $('pm-undo').onclick = () => { const f = undoFn; undoFn = null; t.classList.remove('show'); if (f) f(); };
    }
    function hideUndo() {
        if (!undoFn) return;
        undoFn = null;
        $('pm-toast').classList.remove('show');
    }

    // ---------- gavetas ----------
    const sheet = $('pm-sheet');
    const veil = $('pm-veil');
    function openSheet(html, tall) {
        sheet.className = 'pm-sheet' + (tall ? ' tall' : '');
        sheet.innerHTML = '<div class="pm-grab"></div>' + html;
        sheet.setAttribute('aria-hidden', 'false');
        veil.classList.add('open');
        document.body.classList.add('pm-sheet-open');
        requestAnimationFrame(() => sheet.classList.add('open'));
    }
    function closeSheet() {
        veil.classList.remove('open');
        sheet.classList.remove('open');
        sheet.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('pm-sheet-open');
        sheet.onclick = null;
    }
    veil.addEventListener('click', closeSheet);
    const closeBtn = '<button type="button" class="pm-ib-close" data-close aria-label="Fechar"><i class="bi bi-x-lg"></i></button>';

    function optionsSheet(title, opts, cur, onPick) {
        openSheet('<div class="pm-sh"><h2>' + esc(title) + '</h2>' + closeBtn + '</div><div class="pm-sbody"><div class="pm-opts">' +
            opts.map((o, i) => '<button type="button" class="pm-opt" data-o="' + i + '">' + (o.c ? '<span class="dot" style="--c:' + o.c + '"></span>' : '') +
                (o.av ? '<span class="pm-av" style="background:' + hue(o.l) + '">' + esc(ini(o.l)) + '</span>' : '') + esc(o.l) +
                (o.k === cur ? '<span class="ck"><i class="bi bi-check-lg"></i></span>' : '') + '</button>').join('') + '</div></div>');
        sheet.onclick = e => {
            if (e.target.closest('[data-close]')) { closeSheet(); return; }
            const b = e.target.closest('[data-o]');
            if (b) { closeSheet(); onPick(opts[+b.dataset.o]); }
        };
    }
    const statusOpts = () => ST.map(s => ({ k: s.k, l: s.l, c: s.c }));

    // --- filtros ---
    const SECS = [
        { k: 'deadline', l: 'Prazo', single: 1, opts: DEADLINES.map(d => ({ v: d[0], l: d[1] })) },
        { k: 'priority', l: 'Prioridade', opts: PRIO.map(p => ({ v: p.k, l: p.l, c: p.c })) },
        { k: 'assigned_to', l: 'Atendente', hide: D.forced, opts: [{ v: 'none', l: 'Sem responsável' }].concat(D.team.map(p => ({ v: p.id, l: p.name, av: 1 }))) },
        { k: 'created_by', l: 'Solicitante', opts: D.requesters.map(p => ({ v: p.id, l: p.name, av: 1 })) },
        { k: 'technical', l: 'Técnico', opts: [{ v: 'none', l: 'Sem técnico' }].concat(D.technicians.map(p => ({ v: p.id, l: p.name, av: 1 }))) },
        { k: 'company_id', l: 'Empresa', opts: D.companies.map(c => ({ v: c.id, l: c.name })) },
        { k: 'order', l: 'Ordenar por', single: 1, req: 1, opts: ORDERS.map(o => ({ v: o[0], l: o[1] })) },
    ].filter(s => !s.hide);
    let FS = null;
    let openSec = {};
    let countTimer = null;
    let countSeq = 0;
    function secSummary(s) {
        const v = FS[s.k];
        if (s.single) {
            const o = s.opts.find(x => x.v === v);
            return o ? o.l : 'Todos';
        }
        if (!v.length) return 'Todos';
        if (v.length === 1) { const o = s.opts.find(x => x.v === v[0]); return o ? o.l : '1 selecionado'; }
        return v.length + ' selecionados';
    }
    function paintFilter() {
        const body = $('pm-fbody');
        const top = body ? body.scrollTop : 0;
        body.innerHTML = SECS.map(s => {
            const on = s.single ? !!FS[s.k] : FS[s.k].length > 0;
            return '<div class="pm-fs' + (openSec[s.k] ? ' open' : '') + '"><button type="button" class="pm-fh" data-sec="' + s.k + '" aria-expanded="' + !!openSec[s.k] + '">' +
                '<span class="t">' + s.l + '</span><span class="s' + (on ? ' on' : '') + '">' + esc(secSummary(s)) + '</span><span class="chev"><i class="bi bi-chevron-down"></i></span></button>' +
                '<div class="pm-fo">' + (s.opts.length ? s.opts.map(o => {
                    const isOn = s.single ? FS[s.k] === o.v : FS[s.k].indexOf(o.v) >= 0;
                    return '<button type="button" class="pm-fc' + (isOn ? ' on' : '') + '" data-f="' + s.k + '" data-v="' + esc(o.v) + '" aria-pressed="' + isOn + '">' +
                        (o.c ? '<span class="dot" style="--c:' + o.c + '"></span>' : '') +
                        (o.av ? '<span class="pm-av" style="background:' + hue(o.l) + '">' + esc(ini(o.l)) + '</span>' : '') +
                        '<span>' + esc(o.l) + '</span>' + (isOn ? '<i class="bi bi-check-lg"></i>' : '') + '</button>';
                }).join('') : '<span class="text-muted small">Nenhuma opção.</span>') + '</div></div>';
        }).join('');
        body.scrollTop = top;
        refreshCount();
    }
    function refreshCount() {
        const btn = $('pm-fapply');
        if (!btn) return;
        clearTimeout(countTimer);
        const seq = ++countSeq;
        btn.textContent = 'Ver cards';
        countTimer = setTimeout(() => {
            fetch(BASE + 'planning/countCards?' + paramsFor(FS).toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(r => r.json())
                .then(d => { if (seq === countSeq && d && typeof d.total === 'number') btn.textContent = 'Ver ' + d.total + ' card' + (d.total === 1 ? '' : 's'); })
                .catch(() => {});
        }, 200);
    }
    function filterSheet() {
        FS = copyF(CUR);
        openSec = { deadline: true, priority: true };
        SECS.forEach(s => { if (s.single ? FS[s.k] : FS[s.k].length) openSec[s.k] = true; });
        openSheet('<div class="pm-sh"><h2>Filtros</h2><button type="button" class="lnk" id="pm-fclear">Limpar tudo</button>' + closeBtn + '</div>' +
            '<div class="pm-sbody" id="pm-fbody"></div><div class="pm-sf"><button type="button" class="pm-apply" id="pm-fapply">Ver cards</button></div>', true);
        paintFilter();
        sheet.onclick = e => {
            if (e.target.closest('[data-close]')) { closeSheet(); return; }
            if (e.target.closest('#pm-fapply')) { closeSheet(); navigate(FS); return; }
            if (e.target.closest('#pm-fclear')) {
                const q = FS.q;
                FS = { company_id: [], assigned_to: [], created_by: [], statuses: [], priority: [], technical: [], deadline: '', order: '', q };
                paintFilter();
                return;
            }
            const h = e.target.closest('[data-sec]');
            if (h) { openSec[h.dataset.sec] = !openSec[h.dataset.sec]; paintFilter(); return; }
            const c = e.target.closest('[data-f]');
            if (c) {
                const k = c.dataset.f, v = c.dataset.v;
                const s = SECS.find(x => x.k === k);
                if (s.single) FS[k] = (FS[k] === v && !s.req) ? '' : v;
                else { const i = FS[k].indexOf(v); if (i >= 0) FS[k].splice(i, 1); else FS[k].push(v); }
                paintFilter();
            }
        };
    }

    // ---------- carregar mais ----------
    function loadMore(status, btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Carregando...';
        const params = (typeof calendarFilterParams === 'function') ? calendarFilterParams() : new URLSearchParams();
        params.set('status', status);
        params.set('offset', String(offsets[status] || 0));
        fetch(BASE + 'planning/columnCards?' + params.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(d => {
                const list = (d && d.cards) || [];
                list.forEach(c => {
                    const id = parseInt(c.id, 10);
                    if (cards.has(id)) return;
                    c.id = id;
                    c.assigned_to = c.assigned_to != null ? parseInt(c.assigned_to, 10) : null;
                    cards.set(id, c);
                    (order[c.status] = order[c.status] || []).push(id);
                });
                offsets[status] = (offsets[status] || 0) + list.length;
                if (!list.length) counts[status] = (order[status] || []).length;
                render();
            })
            .catch(() => { toast('Falha ao carregar mais cards.'); render(); });
    }

    // ---------- eventos ----------
    $('pm-menu').addEventListener('click', () => { const b = $('btn-toggle-sidebar'); if (b) b.click(); });
    $('pm-filter').addEventListener('click', filterSheet);
    $('pm-cal').addEventListener('click', () => setCalendar(!document.body.classList.contains('pm-cal')));
    $('pm-fab').addEventListener('click', () => {
        commitPending(false);
        const st = (tab !== 'all' && !document.body.classList.contains('pm-cal')) ? tab : 'open';
        const stSelect = $('create-status');
        if (stSelect) stSelect.value = st;
        const lbl = $('create-submit-mobile-label');
        if (lbl) lbl.textContent = 'Criar em ' + stOf(st).l;
        if (typeof openCreateModal === 'function') openCreateModal();
    });
    $('pm-up').addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
    window.addEventListener('scroll', () => { $('pm-up').classList.toggle('show', window.scrollY > 700); }, { passive: true });

    $('pm-tabs').addEventListener('click', e => {
        const t = e.target.closest('[data-tab]');
        if (!t) return;
        tab = t.dataset.tab;
        sessionStorage.setItem('pm-tab', tab);
        render();
        window.scrollTo({ top: 0 });
    });
    $('pm-quick').addEventListener('click', e => {
        const b = e.target.closest('[data-quick]');
        if (!b) return;
        const f = copyF(CUR);
        const k = b.dataset.quick;
        if (k === 'mine') f.assigned_to = isMine(CUR) ? [] : [D.me.id];
        else f.deadline = CUR.deadline === k ? '' : k;
        navigate(f);
    });
    $('pm-actives').addEventListener('click', e => {
        if (e.target.closest('[data-clear]')) {
            navigate({ company_id: [], assigned_to: [], created_by: [], statuses: [], priority: [], technical: [], deadline: '', order: '', q: '' });
            return;
        }
        const b = e.target.closest('[data-chip]');
        if (!b) return;
        const f = copyF(CUR);
        chipList[+b.dataset.chip].rm(f);
        navigate(f);
    });
    const qIn = $('pm-q');
    qIn.addEventListener('input', () => { localQ = qIn.value.trim(); render(); });
    qIn.addEventListener('keydown', e => {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        const v = qIn.value.trim();
        if (!v) return;
        const f = copyF(CUR);
        f.q = v;
        navigate(f);
    });
    $('pm-qclr').addEventListener('click', () => { qIn.value = ''; localQ = ''; render(); qIn.focus(); });
    $('pm-btn-cmp').addEventListener('click', () => { compact = !compact; localStorage.setItem('pm-compact', compact ? '1' : '0'); render(); });
    $('pm-btn-sel').addEventListener('click', () => { selMode = !selMode; if (!selMode) sel.clear(); render(); });

    // Toque longo no card entra no modo de seleção.
    const listEl = $('pm-cards');
    let pressTimer = null;
    let pressed = false;
    listEl.addEventListener('touchstart', e => {
        const c = e.target.closest('.pm-card');
        if (!c || e.target.closest('button')) return;
        pressed = false;
        pressTimer = setTimeout(() => {
            pressed = true;
            selMode = true;
            if (navigator.vibrate) { try { navigator.vibrate(12); } catch (x) {} }
            toggleSel(parseInt(c.dataset.id, 10));
        }, 450);
    }, { passive: true });
    ['touchend', 'touchmove', 'touchcancel'].forEach(n => listEl.addEventListener(n, () => clearTimeout(pressTimer), { passive: true }));
    listEl.addEventListener('contextmenu', e => { if (e.target.closest('.pm-card')) e.preventDefault(); });
    function toggleSel(id) { if (sel.has(id)) sel.delete(id); else sel.add(id); render(); }

    listEl.addEventListener('click', e => {
        if (pressed) { pressed = false; return; }
        let b;
        if ((b = e.target.closest('[data-group]'))) {
            const k = b.dataset.group;
            shut[k] = !shut[k];
            localStorage.setItem('pm-shut', JSON.stringify(shut));
            render();
            return;
        }
        if ((b = e.target.closest('[data-more]'))) { loadMore(b.dataset.more, b); return; }
        if ((b = e.target.closest('[data-search-all]'))) { const f = copyF(CUR); f.q = localQ; navigate(f); return; }
        if ((b = e.target.closest('[data-go]'))) {
            const c = cards.get(parseInt(b.dataset.go, 10));
            if (!c) return;
            const g = goAction(c);
            stage([{ id: c.id, field: 'status', value: g.to, prev: c.status }], '#' + c.id + ' → ' + stOf(g.to).l);
            return;
        }
        if ((b = e.target.closest('[data-move]'))) {
            const c = cards.get(parseInt(b.dataset.move, 10));
            if (!c) return;
            optionsSheet('Mover #' + c.id + ' para', statusOpts(), c.status, o => {
                stage([{ id: c.id, field: 'status', value: o.k, prev: c.status }], '#' + c.id + ' → ' + o.l);
            });
            return;
        }
        const card = e.target.closest('.pm-card');
        if (!card) return;
        const id = parseInt(card.dataset.id, 10);
        if (selMode) { toggleSel(id); return; }
        // Grava antes qualquer ação pendente, para o detalhe abrir já atualizado.
        commitPending(false).then(() => { if (typeof openCardModal === 'function') openCardModal(id); });
    });

    // Seleção em lote
    function endSel() { sel.clear(); selMode = false; }
    function needSel() { if (!sel.size) { toast('Marque ao menos um card'); return false; } return true; }
    $('pm-sel-cancel').addEventListener('click', () => { endSel(); render(); });
    $('pm-sel-all').addEventListener('click', () => { visibleIds().forEach(id => sel.add(id)); render(); });
    $('pm-sel-move').addEventListener('click', () => {
        if (!needSel()) return;
        const n = sel.size;
        optionsSheet('Mover ' + n + ' card' + (n > 1 ? 's' : '') + ' para', statusOpts(), null, o => {
            const ids = Array.from(sel);
            endSel();
            stage(ids.map(id => ({ id, field: 'status', value: o.k, prev: cards.get(id).status })), (ids.length > 1 ? ids.length + ' cards' : '#' + ids[0]) + ' → ' + o.l);
        });
    });
    $('pm-sel-prio').addEventListener('click', () => {
        if (!needSel()) return;
        optionsSheet('Definir prioridade', PRIO.map(p => ({ k: p.k, l: p.l, c: p.c })), null, o => {
            const ids = Array.from(sel);
            endSel();
            stage(ids.map(id => ({ id, field: 'priority', value: o.k, prev: cards.get(id).priority })), ids.length + ' card(s): prioridade ' + o.l.toLowerCase());
        });
    });
    $('pm-sel-who').addEventListener('click', () => {
        if (!needSel()) return;
        const opts = [{ k: '', l: 'Não atribuído' }].concat(D.attendants.map(p => ({ k: p.id, l: p.name, av: 1 })));
        optionsSheet('Atribuir atendente', opts, null, o => {
            const ids = Array.from(sel);
            endSel();
            stage(ids.map(id => ({ id, field: 'assigned_to', value: o.k, prev: cards.get(id).assigned_to != null ? String(cards.get(id).assigned_to) : '' })),
                ids.length + ' card(s) para ' + o.l);
        });
    });

    // ---------- calendário ----------
    function setCalendar(on) {
        document.body.classList.toggle('pm-cal', on);
        const b = $('pm-cal');
        b.innerHTML = on ? '<i class="bi bi-list-ul"></i>' : '<i class="bi bi-calendar3"></i>';
        b.setAttribute('aria-label', on ? 'Ver lista' : 'Ver calendário');
        b.classList.toggle('on', on);
        if (typeof applyView === 'function') applyView(on ? 'calendar' : 'kanban', true);
        window.scrollTo({ top: 0 });
    }

    // ---------- integração com o detalhe/criação (chamados pelo index.php) ----------
    window.PM = {
        isActive: () => mq.matches,
        onCardSaved(card, affected) {
            dirty = true;
            if (card) mergeCard(card);
            (affected || []).forEach(a => mergeCard(a));
            render();
            toast('Card #' + (card ? card.id : '') + ' salvo');
        },
        onCardCreated(card) {
            dirty = true;
            const id = parseInt(card.id, 10);
            card.id = id;
            card.assigned_to = card.assigned_to != null ? parseInt(card.assigned_to, 10) : null;
            if (!cards.has(id)) {
                cards.set(id, card);
                (order[card.status] = order[card.status] || []).unshift(id);
                counts[card.status] = (counts[card.status] || 0) + 1;
                offsets[card.status] = (offsets[card.status] || 0) + 1;
            }
            render();
            toast('#' + id + ' criado');
            window.scrollTo({ top: 0 });
        },
    };

    // Mantém a altura do cabeçalho fixo para os títulos de grupo grudarem logo abaixo.
    const top = $('pm-top');
    function syncTopH() { root.style.setProperty('--pm-top-h', top.offsetHeight + 'px'); }
    if (window.ResizeObserver) new ResizeObserver(syncTopH).observe(top);

    // Troca entre celular e desktop (girar tablet, redimensionar janela). Se algo
    // mudou na lista do celular, recarrega para o Kanban vir atualizado.
    function onMode() {
        document.body.classList.toggle('pm-on', mq.matches);
        if (!mq.matches && dirty) { commitPending(true); location.reload(); return; }
        if (mq.matches) { render(); syncTopH(); }
    }
    if (mq.addEventListener) mq.addEventListener('change', onMode); else if (mq.addListener) mq.addListener(onMode);

    if (D.activeView === 'calendar' && mq.matches) {
        document.body.classList.add('pm-cal');
        $('pm-cal').innerHTML = '<i class="bi bi-list-ul"></i>';
        $('pm-cal').classList.add('on');
    }
    render();
    syncTopH();
})();
</script>
