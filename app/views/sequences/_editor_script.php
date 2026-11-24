<style>
#sel-marquee { position:absolute; border:1px dashed var(--primary); background:rgba(0,191,166,.08); pointer-events:none; z-index:20; }
#canvas.selecting, #canvas.selecting .seq-node { cursor:crosshair; }
#canvas-wrap { position:relative; overflow:auto; height:calc(100vh - 260px); min-height:420px; background:
    linear-gradient(90deg, rgba(0,0,0,.03) 1px, transparent 1px) 0 0/24px 24px,
    linear-gradient(rgba(0,0,0,.03) 1px, transparent 1px) 0 0/24px 24px, #f8f9fb; }
/* Drawer flutuante de Propriedades */
#inspector-drawer { position:fixed; top:0; right:0; width:340px; max-width:90vw; height:100vh;
    background:#fff; box-shadow:-4px 0 20px rgba(0,0,0,.12); z-index:1200; transform:translateX(100%);
    transition:transform .2s ease; display:flex; flex-direction:column; }
#inspector-drawer.open { transform:translateX(0); }
#inspector-drawer.wide { width:480px; }
#inspector-drawer .ql-editor { min-height:200px; }
#inspector-drawer .drawer-hd { padding:12px 16px; border-bottom:1px solid #eee; display:flex; justify-content:space-between; align-items:center; }
#inspector-drawer .drawer-bd { padding:16px; overflow-y:auto; flex:1; }
.seq-node { position:absolute; width:190px; background:#fff; border:2px solid #dee2e6; border-radius:10px;
    box-shadow:0 2px 6px rgba(0,0,0,.08); cursor:grab; user-select:none; font-size:0.8rem;
    will-change:transform; transition:box-shadow .1s, border-color .1s; }
.seq-node.sel { border-color: var(--primary); box-shadow:0 0 0 3px rgba(0,191,166,.2); }
.seq-node.linktarget { outline:2px dashed var(--primary); outline-offset:2px; }
.seq-node .hd { padding:6px 10px; border-bottom:1px solid #eee; font-weight:600; display:flex; justify-content:space-between; align-items:center; border-radius:8px 8px 0 0; }
.seq-node .hd .x { cursor:pointer; opacity:.4; } .seq-node .hd .x:hover { opacity:1; color:#dc3545; }
.seq-node .bd { padding:6px 10px; color:#667; font-size:0.74rem; min-height:22px; }
.seq-node .port { width:18px; height:18px; border-radius:50%; background:#fff; border:2px solid var(--primary); position:absolute; cursor:crosshair; z-index:3; }
.seq-node .port:hover { background:var(--primary); }
.seq-node .port.out { bottom:-10px; left:calc(50% - 9px); }
.seq-node .port.out.yes { left:26%; background:#d4edda; border-color:#28a745; }
.seq-node .port.out.no { left:66%; background:#f8d7da; border-color:#dc3545; }
.seq-node .port.out.reply { right:-10px; left:auto; top:calc(50% - 9px); bottom:auto; background:#cfe2ff; border-color:#0d6efd; }
.seq-node .port.in { top:-10px; left:calc(50% - 9px); background:#e9ecef; }
/* Módulo acoplado (Atendente de dúvidas) — pendurado na base do bloco IA */
.seq-node .node-attach { margin:0 8px 8px; padding:4px 8px; border-radius:8px; font-size:0.68rem;
    display:flex; align-items:center; gap:6px; border:1px dashed #b7a6e0; background:#f3effc; color:#6f42c1; cursor:pointer; }
.seq-node .node-attach.off { border-color:#d0d0d0; background:#f6f6f6; color:#999; }
.seq-node .node-attach i { font-size:0.85rem; }
.seq-node .node-attach:hover { filter:brightness(0.97); }
.n-send .hd{color:#0d6efd} .n-whatsapp .hd{color:#198754} .n-wait .hd{color:#fd7e14} .n-condition .hd{color:#6f42c1}
.n-tag .hd{color:#20c997} .n-score .hd{color:#e0a800} .n-move .hd{color:#0dcaf0} .n-end .hd{color:#dc3545}
.n-reveal_phone .hd{color:#212529} .n-ai .hd{color:#0d6efd} .n-unsubscribe .hd{color:#dc3545} .n-schedule .hd{color:#198754} .n-connect .hd{color:#0d6efd} .n-reply .hd{color:#0dcaf0} .n-ai_agent .hd{color:#6f42c1} .n-linkedin .hd{color:#0a66c2}
#link-hint { position:fixed; bottom:20px; left:50%; transform:translateX(-50%); background:#1a1a2e; color:#fff;
    padding:8px 16px; border-radius:20px; font-size:0.8rem; z-index:2000; display:none; box-shadow:0 4px 12px rgba(0,0,0,.3); }
</style>

<div id="link-hint"><i class="bi bi-info-circle"></i> Clique no bloco de destino para conectar · <span onclick="cancelLink()" style="cursor:pointer;text-decoration:underline;">cancelar</span></div>

<script>
const BASE = '<?= baseUrl('') ?>';
const SEQ_ID = <?= $sequence ? (int)$sequence['id'] : 'null' ?>;
const COLUMNS = <?= json_encode(array_map(fn($c) => ['id'=>$c['id'],'name'=>$c['name'],'board_id'=>$c['board_id'],'board_name'=>$c['board_name'],'label'=>$c['board_name'].' · '.$c['name']], $columns), JSON_UNESCAPED_UNICODE) ?>;
const LABELS = <?= json_encode(array_map(fn($l) => ['id'=>$l['id'],'name'=>$l['name'],'color'=>$l['color']], $labels ?? []), JSON_UNESCAPED_UNICODE) ?>;
// Lista de boards únicos (para o seletor encadeado do bloco "mover card")
const BOARDS = (function(){ const m={}; COLUMNS.forEach(c=>{ if(!m[c.board_id]) m[c.board_id]={id:c.board_id,name:c.board_name}; }); return Object.values(m); })();
const NODE_LABELS = { send:'Enviar e-mail', whatsapp:'Enviar WhatsApp', linkedin:'LinkedIn (tarefa)', wait:'Aguardar', condition:'Condição', ai:'IA (ChatGPT)', ai_agent:'Atendente IA (FAQ)', schedule:'Agendamento', connect:'Conexão de sequência', reply:'Responder ao lead', tag:'Tag', score:'Score', move:'Mover card', unsubscribe:'Remover da lista', reveal_phone:'Revelar telefone', end:'Encerrar' };
const LINKEDIN_ACTIONS = { connect:'Solicitar conexão', message:'1ª mensagem', followup:'Follow-up', final:'Mensagem final' };
const SEQUENCES = <?= json_encode(array_map(fn($s) => ['id'=>$s['id'],'name'=>$s['name']], $sequencesList ?? []), JSON_UNESCAPED_UNICODE) ?>;
let EMAIL_TEMPLATES = [], WA_TEMPLATES = [], LINKEDIN_TEMPLATES = [];

let nodes = [];       // {id, type, x, y, data, next, nextYes, nextNo, _el}
let selectedId = null;          // bloco aberto no painel de Propriedades
const selection = new Set();    // blocos selecionados (um ou vários)
let linkFrom = null;

<?php if ($sequence && $sequence['graph']): ?>
(function(){
    const g = <?= $sequence['graph'] ?>;
    if (g && g.nodes) {
        nodes = g.nodes.map(n => ({data:{}, ...n}));
        // Auto-layout: se algum nó não tiver coordenadas válidas, distribui em coluna
        // vertical seguindo a ordem do fluxo. Evita todos os blocos sobrepostos.
        const needsLayout = nodes.some(n => !Number.isFinite(n.x) || !Number.isFinite(n.y));
        if (needsLayout) autoLayoutNodes(g.start);
    }
})();

// Distribui os nós verticalmente a partir do start, seguindo o caminho principal.
function autoLayoutNodes(startId) {
    const byId = {}; nodes.forEach(n => byId[n.id] = n);
    const COL_X = 360, BRANCH_X = 700, STEP_Y = 120;
    let y = 20;
    const visited = new Set();
    let cur = startId || (nodes[0] && nodes[0].id);
    while (cur && byId[cur] && !visited.has(cur)) {
        visited.add(cur);
        const n = byId[cur];
        n.x = COL_X; n.y = y; y += STEP_Y;
        cur = n.nextNo || n.next || null;
    }
    let by = 20;
    nodes.forEach(n => { if (!visited.has(n.id)) { n.x = BRANCH_X; n.y = by; by += STEP_Y; } });
}
<?php endif; ?>

const canvas = () => document.getElementById('canvas');
const svg = () => document.getElementById('edges');

// ================= Zoom =================
let ZOOM = 1;
function applyZoom() {
    const z = document.getElementById('canvas-zoom');
    if (z) z.style.transform = `scale(${ZOOM})`;
    const lbl = document.getElementById('zoom-label');
    if (lbl) lbl.textContent = Math.round(ZOOM * 100) + '%';
}
function zoomStep(delta) { ZOOM = Math.min(1.6, Math.max(0.4, Math.round((ZOOM + delta) * 10) / 10)); applyZoom(); }
function zoomReset() { ZOOM = 1; applyZoom(); }
function uid() {
    let id;
    do { id = 'n' + Math.random().toString(36).slice(2, 8); } while (nodes.some(n => n.id === id));
    return id;
}

// ================= Render (uma vez; depois updates pontuais) =================
function buildAll() {
    const c = canvas(); c.innerHTML = '';
    nodes.forEach(n => { n._el = buildNodeEl(n); c.appendChild(n._el); });
    drawEdges();
}

function buildNodeEl(n) {
    const el = document.createElement('div');
    el.className = 'seq-node n-' + n.type;
    el.style.transform = `translate(${n.x}px, ${n.y}px)`;
    el.dataset.id = n.id;

    let ports = '<div class="port in"></div>';
    const aiDecision = (n.type === 'ai' && (n.data || {}).mode === 'decision');
    if (n.type === 'condition' || aiDecision || n.type === 'ai_agent') {
        // Duas saídas SIM/NÃO. No Atendente IA (FAQ), o bloco fica tirando dúvidas
        // em loop até concluir a intenção: SIM = quer seguir, NÃO = quer encerrar.
        ports += `<div class="port out yes" title="Sim" data-port="yes"></div><div class="port out no" title="Não" data-port="no"></div>`;
    } else if (n.type !== 'end') {
        ports += `<div class="port out" data-port="next"></div>`;
    }
    // Blocos de mensagem ganham uma saída extra "Resposta recebida" (à direita),
    // para conectar ao bloco de Conexão de sequência (triagem por IA).
    if (n.type === 'send' || n.type === 'whatsapp') {
        ports += `<div class="port out reply" title="Resposta recebida" data-port="reply"></div>`;
    }
    // Módulo ACOPLADO ao bloco IA (ChatGPT) em modo decisão: "Atendente de dúvidas".
    // Símbolo de duas setas circulares. Quando ativo, o bloco tira dúvidas do lead
    // em loop (respeitando a janela de escuta) até concluir SIM/NÃO.
    let attach = '';
    if (aiDecision) {
        const faqOn = !!(n.data||{}).faq_active;
        attach = `<div class="node-attach ${faqOn?'on':'off'}" title="Atendente de dúvidas (loop)">
            <i class="bi bi-arrow-repeat"></i>
            <span>Dúvidas: ${faqOn?'ativo':'inativo'}</span>
        </div>`;
    }
    el.innerHTML = `<div class="hd"><span>${NODE_LABELS[n.type]||n.type}</span><span class="x">&times;</span></div>
        <div class="bd">${nodeSummary(n)}</div>${attach}${ports}`;

    // Fechar
    el.querySelector('.x').addEventListener('mousedown', (e)=>{ e.stopPropagation(); delNode(n.id); });
    // Clique no módulo acoplado → seleciona o bloco (edita no inspetor)
    const attachEl = el.querySelector('.node-attach');
    if (attachEl) attachEl.addEventListener('mousedown', (e)=>{ e.stopPropagation(); selectNode(n.id); });
    // Portas de saída → inicia ligação
    el.querySelectorAll('.port.out').forEach(p => {
        p.addEventListener('mousedown', (e)=>{ e.stopPropagation(); startLink(n.id, p.dataset.port); });
    });
    // Corpo: drag OU finalizar ligação OU selecionar
    el.addEventListener('mousedown', (e)=>{
        if (e.button !== 0) return;
        if (e.target.classList.contains('port') || e.target.classList.contains('x')) return;
        if (linkFrom) { finishLink(n.id); return; }
        // Shift/Ctrl+clique: põe ou tira o bloco da seleção, sem abrir Propriedades
        if (e.shiftKey || e.ctrlKey || e.metaKey) { e.preventDefault(); toggleInSelection(n.id); return; }
        // Bloco que já faz parte de uma seleção múltipla: arrasta o grupo todo
        if (selection.has(n.id) && selection.size > 1) { startDrag(e, n); return; }
        selectNode(n.id);
        startDrag(e, n);
    });
    if (selection.has(n.id)) el.classList.add('sel');
    return el;
}

// Atualiza só o corpo (summary) de um nó — sem recriar o DOM
function refreshNodeBody(n) {
    if (n._el) n._el.querySelector('.bd').innerHTML = nodeSummary(n);
}

function nodeSummary(n) {
    const d = n.data || {};
    switch (n.type) {
        case 'send': return d.subject ? ('Assunto: ' + escapeHtml(d.subject)) : '<em>sem assunto</em>';
        case 'whatsapp': return d.body ? escapeHtml(d.body.slice(0,50)) : '<em>sem mensagem</em>';
        case 'wait': return 'Aguardar ' + (d.amount||0) + ' ' + ({minutes:'min',hours:'h',days:'dias'}[d.unit]||'dias');
        case 'condition': return 'Se ' + ({replied:'respondeu',opened:'abriu',clicked:'clicou'}[d.kind]||'?') + '?';
        case 'ai': {
            const m = d.mode === 'decision' ? 'Decisão (Sim/Não)' : 'Resposta';
            const md = d.model || 'gpt-4o-mini';
            return `IA · ${m} · ${escapeHtml(md)}`;
        }
        case 'tag': return 'Tag: ' + escapeHtml(d.label||'');
        case 'score': return 'Score ' + (d.delta>0?'+':'') + (d.delta||0);
        case 'move': { const c = COLUMNS.find(x=>x.id==d.column_id); return c ? escapeHtml(c.label) : '<em>escolher coluna</em>'; }
        case 'reveal_phone': return 'Revela telefone no Apollo (se faltar)';
        case 'unsubscribe': return 'Remove o lead da lista (descadastra)';
        case 'schedule': return 'Envia link de agendamento (' + (d.channel||'auto') + ')';
        case 'connect': { const s=(SEQUENCES||[]).find(x=>String(x.id)===String(d.sequence_id)); return s ? ('→ ' + escapeHtml(s.name)) : '<em>escolher sequência</em>'; }
        case 'reply': return (d.ai_reply ? 'IA responde a dúvida + convite' : 'Responde no mesmo canal do lead') + (d.body ? (': ' + escapeHtml(d.body.slice(0,26))) : '');
        case 'ai_agent': return ((d.active===undefined||d.active) ? 'Tira dúvidas em loop até concluir SIM/NÃO' : 'Classifica SIM/NÃO (uma passada)') + ' · ' + escapeHtml(d.model||'gpt-4o-mini');
        case 'linkedin': return '<i class="bi bi-linkedin"></i> ' + (LINKEDIN_ACTIONS[d.action_type]||'Ação') + ' <span class="badge bg-light text-dark border">manual</span>';
        case 'end': return 'Fim da sequência';
    }
    return '';
}

// ================= Arestas (SVG, redesenhadas via rAF) =================
let edgeRaf = null;
function drawEdges() {
    if (edgeRaf) return;
    edgeRaf = requestAnimationFrame(() => {
        edgeRaf = null;
        const s = svg(); let out = '';
        nodes.forEach(n => {
            const conns = [];
            if (n.next) conns.push([n.next, '#00BFA6', 0.5, 'bottom']);
            if (n.nextYes) conns.push([n.nextYes, '#28a745', 0.26, 'bottom']);
            if (n.nextNo) conns.push([n.nextNo, '#dc3545', 0.66, 'bottom']);
            if (n.nextReply) conns.push([n.nextReply, '#0d6efd', 1, 'right']);
            conns.forEach(([to, color, fx, side]) => {
                const t = nodes.find(x=>x.id===to); if (!t) return;
                let x1, y1;
                if (side === 'right') { x1 = n.x + 190; y1 = n.y + 34; }
                else { x1 = n.x + 190*fx; y1 = n.y + 68; }
                const x2 = t.x + 95, y2 = t.y;
                const mid = (y1+y2)/2;
                out += `<path d="M${x1},${y1} C${x1},${mid} ${x2},${mid} ${x2},${y2}" stroke="${color}" fill="none" stroke-width="2" ${side==='right'?'stroke-dasharray="4,3"':''}/>`;
                out += `<circle cx="${x2}" cy="${y2}" r="3" fill="${color}"/>`;
            });
        });
        s.innerHTML = out;
    });
}

// ================= Drag fluido (sem recriar DOM) =================
// Arrasta o bloco; se ele fizer parte de uma seleção múltipla, move o grupo
// inteiro mantendo a distância entre os blocos.
function startDrag(e, n) {
    const group = (selection.has(n.id) && selection.size > 1) ? nodes.filter(x => selection.has(x.id)) : [n];
    const origin = group.map(g => ({ g, x: g.x, y: g.y }));
    const minX = Math.min(...origin.map(o => o.x)), minY = Math.min(...origin.map(o => o.y));
    const startX = e.clientX, startY = e.clientY;
    let moved = false;
    group.forEach(g => { if (g._el) { g._el.style.cursor = 'grabbing'; g._el.style.zIndex = 10; } });
    let raf = null;
    function move(ev) {
        // Não deixa nenhum bloco do grupo sair pela esquerda/topo do quadro
        const dx = Math.max(-minX, (ev.clientX - startX) / ZOOM);
        const dy = Math.max(-minY, (ev.clientY - startY) / ZOOM);
        if (!moved && Math.abs(ev.clientX - startX) + Math.abs(ev.clientY - startY) < 3) return;
        moved = true;
        origin.forEach(o => { o.g.x = o.x + dx; o.g.y = o.y + dy; });
        if (!raf) raf = requestAnimationFrame(() => {
            raf = null;
            group.forEach(g => { if (g._el) g._el.style.transform = `translate(${g.x}px, ${g.y}px)`; });
            drawEdges();
        });
    }
    function up() {
        document.removeEventListener('mousemove', move);
        document.removeEventListener('mouseup', up);
        group.forEach(g => { if (g._el) { g._el.style.cursor = 'grab'; g._el.style.zIndex = ''; } });
        // Clique simples (sem arrastar) num bloco do grupo: abre só esse bloco
        if (!moved && group.length > 1) selectNode(n.id);
    }
    document.addEventListener('mousemove', move);
    document.addEventListener('mouseup', up);
    e.preventDefault();
}

// ================= Seleção por área (arrastar no fundo do quadro) =================
function canvasPoint(ev) {
    const r = canvas().getBoundingClientRect();
    return { x: (ev.clientX - r.left) / ZOOM, y: (ev.clientY - r.top) / ZOOM };
}
function nodesInRect(x1, y1, x2, y2) {
    const L = Math.min(x1, x2), R = Math.max(x1, x2), T = Math.min(y1, y2), B = Math.max(y1, y2);
    return nodes.filter(n => {
        const w = 190, h = (n._el && n._el.offsetHeight) || 70;
        return n.x < R && n.x + w > L && n.y < B && n.y + h > T;
    }).map(n => n.id);
}
function onCanvasMouseDown(e) {
    if (e.button !== 0 || e.target !== canvas()) return;   // só no fundo vazio
    if (linkFrom) { cancelLink(); return; }
    e.preventDefault();
    const additive = e.shiftKey || e.ctrlKey || e.metaKey;
    const base = additive ? new Set(selection) : new Set();
    const start = canvasPoint(e);
    let box = null, moved = false;
    function move(ev) {
        const p = canvasPoint(ev);
        if (!moved && Math.abs(p.x - start.x) + Math.abs(p.y - start.y) < 4) return;
        if (!moved) {
            moved = true;
            hideInspector();
            box = document.createElement('div'); box.id = 'sel-marquee';
            canvas().appendChild(box); canvas().classList.add('selecting');
        }
        box.style.left = Math.min(start.x, p.x) + 'px'; box.style.top = Math.min(start.y, p.y) + 'px';
        box.style.width = Math.abs(p.x - start.x) + 'px'; box.style.height = Math.abs(p.y - start.y) + 'px';
        const ids = new Set(base);
        nodesInRect(start.x, start.y, p.x, p.y).forEach(id => ids.add(id));
        setSelection(ids);
    }
    function up() {
        document.removeEventListener('mousemove', move);
        document.removeEventListener('mouseup', up);
        if (box) box.remove();
        canvas().classList.remove('selecting');
        if (!moved && !additive) clearSelection();   // clique no fundo: limpa a seleção
    }
    document.addEventListener('mousemove', move);
    document.addEventListener('mouseup', up);
}

// ================= Copiar / colar / duplicar =================
// A área de transferência fica no navegador (localStorage): dá para copiar
// blocos de uma sequência e colar em outra.
const CLIP_KEY = 'helpdeskon.seqClipboard.v1';
const LINK_PORTS = ['next', 'nextYes', 'nextNo', 'nextReply'];
let pasteRound = 0;

/**
 * Cópia independente dos blocos, com ids novos. Ligações entre blocos copiados
 * são refeitas entre as cópias; ligações para blocos que ficaram de fora caem
 * (a cópia não "puxa" o fluxo original).
 */
function cloneNodes(list, dx, dy, takenIds) {
    const used = new Set(takenIds || nodes.map(n => n.id));
    const map = {};
    list.forEach(n => {
        let id;
        do { id = 'n' + Math.random().toString(36).slice(2, 8); } while (used.has(id));
        used.add(id); map[n.id] = id;
    });
    return list.map(n => {
        const c = { id: map[n.id], type: n.type, x: Math.max(0, n.x + dx), y: Math.max(0, n.y + dy),
                    data: JSON.parse(JSON.stringify(n.data || {})) };
        LINK_PORTS.forEach(p => { if (n[p] && map[n[p]]) c[p] = map[n[p]]; });
        return c;
    });
}
function plainNode(n) {
    const o = { id: n.id, type: n.type, x: n.x, y: n.y, data: JSON.parse(JSON.stringify(n.data || {})) };
    LINK_PORTS.forEach(p => { if (n[p]) o[p] = n[p]; });
    return o;
}
function selectedNodes() { return nodes.filter(n => selection.has(n.id)); }
function readClipboard() {
    try { const c = JSON.parse(localStorage.getItem(CLIP_KEY) || 'null'); return (c && Array.isArray(c.nodes) && c.nodes.length) ? c : null; }
    catch (e) { return null; }
}
function blocksLabel(k) { return k === 1 ? '1 bloco' : k + ' blocos'; }

function insertNodes(list) {
    list.forEach(c => {
        // Bloco "Conexão de sequência" colado na própria sequência de destino não pode apontar para ela mesma.
        if (c.type === 'connect' && SEQ_ID && String((c.data || {}).sequence_id) === String(SEQ_ID)) c.data.sequence_id = '';
        nodes.push(c);
        c._el = buildNodeEl(c);
        canvas().appendChild(c._el);
    });
    drawEdges();
    hideInspector();
    setSelection(list.map(c => c.id));
}
function duplicateSelection() {
    const src = selectedNodes();
    if (!src.length) return;
    const copies = cloneNodes(src, 40, 40);
    insertNodes(copies);
    showSelToast(blocksLabel(copies.length) + ' duplicado' + (copies.length === 1 ? '' : 's') + '. Arraste para posicionar.');
}
function copySelection(cut) {
    const src = selectedNodes();
    if (!src.length) return;
    try {
        localStorage.setItem(CLIP_KEY, JSON.stringify({ seq: SEQ_ID, cut: !!cut, at: Date.now(), nodes: src.map(plainNode) }));
    } catch (e) { alert('Não foi possível copiar (armazenamento do navegador cheio ou bloqueado).'); return; }
    pasteRound = cut ? 0 : 1;
    updateSelBar();
    showSelToast(blocksLabel(src.length) + (cut ? ' recortado' : ' copiado') + (src.length === 1 ? '' : 's') + '. Cole com Ctrl+V aqui ou em outra sequência.');
}
function pasteClipboard() {
    const clip = readClipboard();
    if (!clip) { showSelToast('Nada copiado ainda. Selecione blocos e use Copiar (Ctrl+C).'); return; }
    let dx, dy;
    if (clip.seq === SEQ_ID) {
        // Mesma sequência: cada colagem desloca um pouco para não ficar por cima
        // (recortar + colar volta ao mesmo lugar na primeira colagem).
        if (pasteRound === 0 && !clip.cut) pasteRound = 1;
        dx = dy = 40 * pasteRound;
        pasteRound++;
    } else {
        // Outra sequência: cola no canto visível do quadro
        const wrap = document.getElementById('canvas-wrap');
        const minX = Math.min(...clip.nodes.map(n => n.x)), minY = Math.min(...clip.nodes.map(n => n.y));
        dx = (wrap ? wrap.scrollLeft / ZOOM : 0) + 60 - minX;
        dy = (wrap ? wrap.scrollTop / ZOOM : 0) + 60 - minY;
    }
    const copies = cloneNodes(clip.nodes, dx, dy);
    insertNodes(copies);
    showSelToast(blocksLabel(copies.length) + ' colado' + (copies.length === 1 ? '' : 's') + '.');
}
function deleteSelection(skipConfirm) {
    const ids = Array.from(selection);
    if (!ids.length) return;
    if (ids.length > 1 && !skipConfirm && !confirm('Excluir ' + ids.length + ' blocos selecionados?')) return;
    ids.forEach(id => delNode(id));
    clearSelection();
}
function nudgeSelection(dx, dy) {
    const list = selectedNodes();
    if (!list.length) return;
    const minX = Math.min(...list.map(n => n.x)), minY = Math.min(...list.map(n => n.y));
    dx = Math.max(-minX, dx); dy = Math.max(-minY, dy);
    list.forEach(n => { n.x += dx; n.y += dy; if (n._el) n._el.style.transform = `translate(${n.x}px, ${n.y}px)`; });
    drawEdges();
}
let selToastTimer = null;
function showSelToast(msg) {
    const t = document.getElementById('sel-toast');
    if (!t) return;
    t.textContent = msg; t.style.display = 'block';
    clearTimeout(selToastTimer);
    selToastTimer = setTimeout(() => { t.style.display = 'none'; }, 3200);
}

// ================= Adicionar / remover =================
function addNode(type) {
    const scroll = document.getElementById('canvas-wrap');
    const n = { id: uid(), type,
        x: 60 + (scroll ? scroll.scrollLeft : 0) + Math.random()*40,
        y: 60 + (scroll ? scroll.scrollTop : 0) + nodes.length*10,
        data: defaultData(type) };
    nodes.push(n);
    n._el = buildNodeEl(n);
    canvas().appendChild(n._el);
    selectNode(n.id);
}
function defaultData(type) {
    if (type === 'send') return { subject:'', body:'' };
    if (type === 'whatsapp') return { body:'' };
    if (type === 'wait') return { amount:2, unit:'days' };
    if (type === 'condition') return { kind:'replied' };
    if (type === 'ai') return { mode:'simple', model:'gpt-4o-mini', prompt:'', send_channel:'', save_note:1, faq_active:0, company_info:'', instructions:'', max_turns:6 };
    if (type === 'ai_agent') return { active:1, model:'gpt-4o-mini', company_info:'', instructions:'', max_turns:6 };
    if (type === 'tag') return { label:'', color:'#00BFA6' };
    if (type === 'score') return { delta:3 };
    if (type === 'move') return { column_id:'' };
    if (type === 'unsubscribe') return { reason:'Sem interesse (sequência)' };
    if (type === 'schedule') return { channel:'auto', duration:45, title:'Reunião com a ON Solutions Brasil', message:'' };
    if (type === 'connect') return { sequence_id:'', stop_current:1 };
    if (type === 'reply') return { subject:'ON Solutions Brasil', body:'', ai_reply:0, model:'gpt-4o-mini', company_info:'' };
    if (type === 'reveal_phone') return {};
    if (type === 'linkedin') return { action_type:'message', objective:'', body:'', cta:'', tone:'', max_length:0, template_id:'', model:'gpt-4o-mini' };
    return {};
}
function delNode(id) {
    const n = nodes.find(x=>x.id===id);
    if (n && n._el) n._el.remove();
    nodes = nodes.filter(x=>x.id!==id);
    nodes.forEach(x=>{ if(x.next===id)delete x.next; if(x.nextYes===id)delete x.nextYes; if(x.nextNo===id)delete x.nextNo; if(x.nextReply===id)delete x.nextReply; });
    if (selection.delete(id)) updateSelBar();
    if (selectedId===id) { selectedId=null; renderInspector(); }
    drawEdges();
}

// ================= Seleção =================
// Clique num bloco: seleciona só ele e abre Propriedades.
function selectNode(id) {
    setSelection([id]);
    selectedId = id;
    renderInspector();
    openInspector();
}
// Aplica a seleção (um ou vários blocos) e atualiza o destaque e a barra.
function setSelection(ids) {
    selection.clear();
    Array.from(ids).forEach(id => { if (nodes.some(n => n.id === id)) selection.add(id); });
    nodes.forEach(n => { if (n._el) n._el.classList.toggle('sel', selection.has(n.id)); });
    updateSelBar();
}
function toggleInSelection(id) {
    const ids = new Set(selection);
    if (ids.has(id)) ids.delete(id); else ids.add(id);
    hideInspector();
    setSelection(ids);
}
function selectAllNodes() { hideInspector(); setSelection(nodes.map(n => n.id)); }
function clearSelection() { hideInspector(); setSelection([]); }
function updateSelBar() {
    const k = selection.size;
    const cnt = document.getElementById('sel-count');
    if (cnt) {
        cnt.textContent = k === 0 ? 'Nenhum bloco selecionado' : (k === 1 ? '1 bloco selecionado' : k + ' blocos selecionados');
        cnt.classList.toggle('on', k > 0);
    }
    ['sel-dup', 'sel-copy', 'sel-del', 'sel-clear'].forEach(id => { const b = document.getElementById(id); if (b) b.disabled = k === 0; });
    const paste = document.getElementById('sel-paste');
    if (paste) paste.disabled = !readClipboard();
}
function openInspector() { document.getElementById('inspector-drawer').classList.add('open'); }
// Fecha o painel de Propriedades sem mexer na seleção.
function hideInspector() {
    document.getElementById('inspector-drawer').classList.remove('open');
    selectedId = null;
}
// Botão X do painel: fecha e tira a seleção.
function closeInspector() {
    hideInspector();
    setSelection([]);
}

// ================= Ligações =================
function startLink(nodeId, port) {
    linkFrom = { id: nodeId, port };
    document.getElementById('link-hint').style.display = 'block';
    nodes.forEach(n => { if (n.id !== nodeId && n._el) n._el.classList.add('linktarget'); });
}
function finishLink(targetId) {
    if (linkFrom && linkFrom.id !== targetId) {
        const n = nodes.find(x=>x.id===linkFrom.id);
        if (n) {
            if (linkFrom.port === 'yes') n.nextYes = targetId;
            else if (linkFrom.port === 'no') n.nextNo = targetId;
            else if (linkFrom.port === 'reply') n.nextReply = targetId;
            else n.next = targetId;
        }
    }
    cancelLink(); drawEdges();
    if (selectedId) renderInspector();
}
function cancelLink() {
    linkFrom = null;
    document.getElementById('link-hint').style.display = 'none';
    nodes.forEach(n => { if (n._el) n._el.classList.remove('linktarget'); });
}

// ================= Inspector =================
function renderInspector() {
    const box = document.getElementById('inspector');
    const n = nodes.find(x=>x.id===selectedId);
    if (!n) { box.innerHTML = '<p class="text-muted small mb-0">Selecione um bloco para editar.</p>'; return; }
    let h = `<div class="mb-2 fw-semibold small">${NODE_LABELS[n.type]}</div>`;
    if (n.type==='send') {
        h += templatePicker('email');
        h += field('Assunto', `<input id="insp-subject" class="form-control form-control-sm" value="${escapeAttr(n.data.subject||'')}" oninput="setData('subject',this.value)">`);
        h += `<label class="form-label small mb-1">Mensagem</label>` +
             `<div id="insp-rich-chips"></div><div id="insp-rich" data-key="body"></div>` +
             `<div class="d-flex justify-content-between align-items-center mt-1 gap-2">
                <small class="text-muted">Negrito, itálico, listas e links chegam formatados, com assinatura e layout.</small>
                <button type="button" class="btn btn-sm btn-outline-primary py-0 text-nowrap" id="insp-preview" onclick="previewNodeEmail('')"><i class="bi bi-eye"></i> Ver como chega</button>
             </div>`;
        // Teste A/B
        h += abBlock(n, 'email');
    } else if (n.type==='whatsapp') {
        h += templatePicker('whatsapp');
        h += `<label class="form-label small mb-1">Mensagem</label>` + varChipsHtml() +
             `<textarea id="insp-body" class="form-control form-control-sm" rows="6" oninput="setData('body',this.value)">${escapeHtml(n.data.body||'')}</textarea>`;
        h += `<small class="text-muted d-block mt-1">Enviada pela conexão de WhatsApp.</small>`;
        h += abBlock(n, 'whatsapp');
    } else if (n.type==='wait') {
        h += field('Quantidade', `<input type="number" min="1" class="form-control form-control-sm" value="${n.data.amount||1}" oninput="setData('amount',parseInt(this.value)||1)">`);
        h += field('Unidade', `<select class="form-select form-select-sm" onchange="setData('unit',this.value)">
            <option value="minutes" ${n.data.unit==='minutes'?'selected':''}>Minutos</option>
            <option value="hours" ${n.data.unit==='hours'?'selected':''}>Horas</option>
            <option value="days" ${(n.data.unit==='days'||!n.data.unit)?'selected':''}>Dias</option></select>`);
    } else if (n.type==='condition') {
        h += field('Condição', `<select class="form-select form-select-sm" onchange="setData('kind',this.value)">
            <option value="replied" ${n.data.kind==='replied'?'selected':''}>Respondeu?</option>
            <option value="opened" ${n.data.kind==='opened'?'selected':''}>Abriu?</option>
            <option value="clicked" ${n.data.kind==='clicked'?'selected':''}>Clicou?</option></select>`);
    } else if (n.type==='ai_agent') {
        const model = n.data.model || 'gpt-4o-mini';
        const agentActive = (n.data.active === undefined) ? true : !!n.data.active;
        h += field('Atendente IA (loop de dúvidas)', `<select class="form-select form-select-sm" onchange="setData('active', this.value==='1'?1:0); renderInspector();">
            <option value="1" ${agentActive?'selected':''}>Ativo — tira dúvidas em loop até concluir</option>
            <option value="0" ${!agentActive?'selected':''}>Inativo — só interpreta SIM/NÃO (uma passada)</option>
        </select>`);
        h += field('Modelo do ChatGPT', `<select class="form-select form-select-sm" onchange="setData('model',this.value)">
            <option value="gpt-4o-mini" ${model==='gpt-4o-mini'?'selected':''}>gpt-4o-mini</option>
            <option value="gpt-4o" ${model==='gpt-4o'?'selected':''}>gpt-4o</option>
            <option value="gpt-4.1" ${model==='gpt-4.1'?'selected':''}>gpt-4.1</option>
            <option value="gpt-4.1-mini" ${model==='gpt-4.1-mini'?'selected':''}>gpt-4.1-mini</option>
        </select>`);
        h += `<label class="form-label small mb-1">Informações da empresa (base de conhecimento)</label>` +
             `<textarea class="form-control form-control-sm" rows="6" placeholder="Ex.: A ON Solutions Brasil faz organização e automação de processos... Setores atendidos... Prazos... Diferenciais..." oninput="setData('company_info',this.value)">${escapeHtml(n.data.company_info||'')}</textarea>`;
        h += `<label class="form-label small mb-1 mt-2">Instruções extras (tom, regras)</label>` +
             `<textarea class="form-control form-control-sm" rows="3" placeholder="Ex.: Seja cordial e objetivo. Não invente preços. Se perguntarem valores, oriente a agendar." oninput="setData('instructions',this.value)">${escapeHtml(n.data.instructions||'')}</textarea>`;
        if (agentActive) {
            h += field('Máx. de interações antes de escalar', `<input type="number" min="1" max="20" class="form-control form-control-sm" value="${n.data.max_turns||6}" oninput="setData('max_turns',parseInt(this.value)||6)">`);
            h += `<div class="alert alert-light border py-2 px-2 small mb-0"><i class="bi bi-arrow-repeat text-primary"></i> <strong>Ativo:</strong> o bloco responde as dúvidas do lead sobre a empresa (com a base acima) e fica nesse ciclo, respeitando a janela de escuta, até concluir a intenção. Conecte as duas saídas:<br>
                <span style="color:#28a745">●</span> <strong>Quer seguir</strong> (SIM) · <span style="color:#dc3545">●</span> <strong>Quer encerrar</strong> (NÃO).</div>`;
        } else {
            h += `<div class="alert alert-light border py-2 px-2 small mb-0"><i class="bi bi-signpost-split text-secondary"></i> <strong>Inativo:</strong> o bloco apenas interpreta a resposta do lead numa única passada (sem loop e sem responder dúvidas) e segue direto pela saída:<br>
                <span style="color:#28a745">●</span> <strong>SIM</strong> (demonstrou interesse) · <span style="color:#dc3545">●</span> <strong>NÃO</strong> (sem interesse/indefinido).</div>`;
        }
    } else if (n.type==='ai') {
        const mode = n.data.mode || 'simple';
        const model = n.data.model || 'gpt-4o-mini';
        h += field('Modelo do ChatGPT', `<select class="form-select form-select-sm" onchange="setData('model',this.value)">
            <option value="gpt-4o-mini" ${model==='gpt-4o-mini'?'selected':''}>gpt-4o-mini (rápido e barato)</option>
            <option value="gpt-4o" ${model==='gpt-4o'?'selected':''}>gpt-4o</option>
            <option value="gpt-4.1" ${model==='gpt-4.1'?'selected':''}>gpt-4.1</option>
            <option value="gpt-4.1-mini" ${model==='gpt-4.1-mini'?'selected':''}>gpt-4.1-mini</option>
            <option value="gpt-3.5-turbo" ${model==='gpt-3.5-turbo'?'selected':''}>gpt-3.5-turbo</option>
        </select>`);
        h += field('Tipo de resposta', `<select class="form-select form-select-sm" id="insp-ai-mode" onchange="onAiModeChange(this.value)">
            <option value="simple" ${mode==='simple'?'selected':''}>Resposta simples</option>
            <option value="decision" ${mode==='decision'?'selected':''}>Decisão Sim/Não (duas saídas)</option>
        </select>`);
        h += `<label class="form-label small mb-1">Prompt / instrução</label>` + varChipsHtml() +
             `<textarea id="insp-body" class="form-control form-control-sm" rows="7" placeholder="Ex.: Analise a última resposta do lead {{primeiro_nome}} e diga se ele demonstrou interesse em conversar." oninput="setData('prompt',this.value)">${escapeHtml(n.data.prompt||'')}</textarea>`;
        h += `<small class="text-muted d-block mt-1 mb-2">A IA recebe automaticamente os dados do lead e o histórico recente de mensagens junto com o seu prompt. Use variáveis como {{primeiro_nome}}, {{empresa}}.</small>`;
        if (mode === 'decision') {
            h += `<div class="alert alert-light border py-2 px-2 small mb-2"><i class="bi bi-signpost-split text-primary"></i> A IA decide <strong>SIM</strong> ou <strong>NÃO</strong>. Conecte as duas saídas (verde = Sim, vermelha = Não) aos próximos blocos.</div>`;

            // ---- Módulo ACOPLADO: Atendente de dúvidas (loop) ----
            const faqOn = !!n.data.faq_active;
            h += `<hr class="my-2"><div class="d-flex align-items-center gap-2 mb-2">
                <i class="bi bi-arrow-repeat text-primary"></i><strong class="small">Atendente de dúvidas (acoplado)</strong></div>`;
            h += field('Estado', `<select class="form-select form-select-sm" onchange="onFaqToggle(this.value==='1'?1:0)">
                <option value="0" ${!faqOn?'selected':''}>Inativo — decide SIM/NÃO direto</option>
                <option value="1" ${faqOn?'selected':''}>Ativo — tira dúvidas em loop até concluir</option>
            </select>`);
            if (faqOn) {
                h += `<label class="form-label small mb-1">Informações da empresa (base de conhecimento)</label>` +
                     `<textarea class="form-control form-control-sm" rows="5" placeholder="Ex.: A ON Solutions Brasil faz organização e automação de processos... Setores atendidos... Prazos... Diferenciais..." oninput="setData('company_info',this.value)">${escapeHtml(n.data.company_info||'')}</textarea>`;
                h += `<label class="form-label small mb-1 mt-2">Instruções extras (tom, regras)</label>` +
                     `<textarea class="form-control form-control-sm" rows="2" placeholder="Ex.: Seja cordial e objetivo. Não invente preços. Se perguntarem valores, oriente a agendar." oninput="setData('instructions',this.value)">${escapeHtml(n.data.instructions||'')}</textarea>`;
                h += field('Máx. de interações antes de concluir', `<input type="number" min="1" max="20" class="form-control form-control-sm" value="${n.data.max_turns||6}" oninput="setData('max_turns',parseInt(this.value)||6)">`);
                h += `<div class="alert alert-light border py-2 px-2 small mb-0"><i class="bi bi-arrow-repeat text-primary"></i> Enquanto o lead ainda tiver dúvidas, a IA responde (mesmo canal) e permanece neste bloco, respeitando a janela de escuta, até concluir <strong>SIM</strong> ou <strong>NÃO</strong>.</div>`;
            } else {
                h += `<small class="text-muted d-block">Com o atendente inativo, a IA apenas classifica a resposta e segue direto por SIM/NÃO.</small>`;
            }
        } else {
            h += field('Registrar resposta como nota no lead', `<select class="form-select form-select-sm" onchange="setData('save_note', parseInt(this.value))">
                <option value="1" ${(n.data.save_note??1)==1?'selected':''}>Sim</option>
                <option value="0" ${(n.data.save_note??1)==0?'selected':''}>Não</option></select>`);
        }
    } else if (n.type==='tag') {
        // Dropdown das etiquetas existentes + opção de criar nova
        const cur = n.data.label || '';
        const known = LABELS.some(l => l.name === cur);
        let opts = '<option value="">— selecione uma etiqueta —</option>';
        opts += LABELS.map(l => `<option value="${escapeAttr(l.name)}" data-color="${escapeAttr(l.color||'#00BFA6')}" ${l.name===cur?'selected':''}>${escapeHtml(l.name)}</option>`).join('');
        opts += `<option value="__new__" ${(cur && !known)?'selected':''}>➕ Criar nova etiqueta…</option>`;
        h += field('Etiqueta (CRM)', `<select class="form-select form-select-sm" id="insp-tag-select" onchange="onTagSelect(this)">${opts}</select>`);
        // Campo para nome da nova etiqueta (aparece quando "criar nova")
        const showNew = (cur && !known);
        h += `<div id="insp-tag-new-wrap" class="mb-2" style="${showNew?'':'display:none;'}">
            <label class="form-label small mb-1">Nome da nova etiqueta</label>
            <input class="form-control form-control-sm" id="insp-tag-new" value="${showNew?escapeAttr(cur):''}" oninput="setData('label',this.value)" placeholder="Ex: prospecao apollo - Ativa">
        </div>`;
        h += field('Cor', `<input type="color" class="form-control form-control-sm form-control-color" id="insp-tag-color" value="${escapeAttr(n.data.color||'#00BFA6')}" oninput="setData('color',this.value)">`);
        h += `<small class="text-muted d-block">A etiqueta é criada no CRM (se não existir) e vinculada ao contato.</small>`;
    } else if (n.type==='reveal_phone') {
        const rp = (n.data.reveal_phone === undefined) ? true : !!n.data.reveal_phone;
        const re = !!n.data.reveal_email;
        h += `<div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" id="insp-rv-phone" ${rp?'checked':''} onchange="setData('reveal_phone', this.checked?1:0)">
            <label class="form-check-label small" for="insp-rv-phone"><i class="bi bi-telephone"></i> Revelar telefone (existente)</label>
        </div>
        <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" id="insp-rv-email" ${re?'checked':''} onchange="setData('reveal_email', this.checked?1:0)">
            <label class="form-check-label small" for="insp-rv-email"><i class="bi bi-envelope"></i> Revelar e-mail</label>
        </div>
        <p class="text-muted small mb-0">Solicita ao Apollo apenas os dados marcados que ainda faltarem no lead (reveal progressivo). Só consome crédito quando o dado não existe e há vínculo com o Apollo. O telefone chega via webhook.</p>`;
    } else if (n.type==='score') {
        h += field('Pontos (+/-)', `<input type="number" class="form-control form-control-sm" value="${n.data.delta||0}" oninput="setData('delta',parseInt(this.value)||0)">`);
    } else if (n.type==='move') {
        // Descobre o board da coluna já escolhida (se houver)
        const chosenCol = COLUMNS.find(c => c.id == n.data.column_id);
        const boardId = n.data.board_id || (chosenCol ? chosenCol.board_id : '');
        const boardOpts = '<option value="">Selecione o board</option>' +
            BOARDS.map(b => `<option value="${b.id}" ${boardId==b.id?'selected':''}>${escapeHtml(b.name)}</option>`).join('');
        h += field('Board', `<select class="form-select form-select-sm" id="insp-board" onchange="onMoveBoardChange(this.value)">${boardOpts}</select>`);

        const cols = COLUMNS.filter(c => c.board_id == boardId);
        const colOpts = '<option value="">Selecione a coluna</option>' +
            cols.map(c => `<option value="${c.id}" ${n.data.column_id==c.id?'selected':''}>${escapeHtml(c.name)}</option>`).join('');
        h += field('Coluna', `<select class="form-select form-select-sm" id="insp-column" ${boardId?'':'disabled'} onchange="setData('column_id',this.value)">${colOpts}</select>`);
    } else if (n.type==='schedule') {
        const ch = n.data.channel || 'auto';
        h += field('Canal do convite', `<select class="form-select form-select-sm" onchange="setData('channel',this.value)">
            <option value="reply" ${ch==='reply'?'selected':''}>Mesmo canal da resposta do lead</option>
            <option value="auto" ${ch==='auto'?'selected':''}>Automático (e-mail e/ou WhatsApp)</option>
            <option value="email" ${ch==='email'?'selected':''}>E-mail</option>
            <option value="whatsapp" ${ch==='whatsapp'?'selected':''}>WhatsApp</option></select>`);
        h += field('Título da reunião', `<input class="form-control form-control-sm" value="${escapeAttr(n.data.title||'Reunião com a ON Solutions Brasil')}" oninput="setData('title',this.value)">`);
        h += field('Duração (min)', `<input type="number" min="15" step="15" class="form-control form-control-sm" value="${n.data.duration||45}" oninput="setData('duration',parseInt(this.value)||45)">`);
        h += `<label class="form-label small mb-1">Mensagem do convite (opcional)</label>` + varChipsHtml() +
             `<textarea id="insp-body" class="form-control form-control-sm" rows="4" placeholder="Ex.: {{primeiro_nome}}, que tal conversarmos? Escolha o melhor horário no link abaixo." oninput="setData('message',this.value)">${escapeHtml(n.data.message||'')}</textarea>`;
        h += `<small class="text-muted d-block mt-1">Gera um link público com os dados do lead pré-preenchidos. Ao agendar, cria o evento no Google Meet e notifica por e-mail e WhatsApp. O link é inserido automaticamente ({{link_agendamento}}).</small>`;
    } else if (n.type==='reply') {
        h += `<div class="alert alert-light border py-2 px-2 small mb-2"><i class="bi bi-reply text-info"></i> Envia pelo <strong>mesmo canal</strong> em que o lead respondeu por último (e-mail ou WhatsApp).</div>`;
        const aiReplyOn = !!n.data.ai_reply;
        h += field('Responder a dúvida do lead com IA', `<select class="form-select form-select-sm" onchange="setData('ai_reply', this.value==='1'?1:0); renderInspector();">
            <option value="0" ${!aiReplyOn?'selected':''}>Não — envia só a mensagem abaixo</option>
            <option value="1" ${aiReplyOn?'selected':''}>Sim — responde a dúvida (curto) e então convida</option>
        </select>`);
        if (aiReplyOn) {
            const model = n.data.model || 'gpt-4o-mini';
            h += field('Modelo do ChatGPT', `<select class="form-select form-select-sm" onchange="setData('model',this.value)">
                <option value="gpt-4o-mini" ${model==='gpt-4o-mini'?'selected':''}>gpt-4o-mini</option>
                <option value="gpt-4o" ${model==='gpt-4o'?'selected':''}>gpt-4o</option>
                <option value="gpt-4.1" ${model==='gpt-4.1'?'selected':''}>gpt-4.1</option>
                <option value="gpt-4.1-mini" ${model==='gpt-4.1-mini'?'selected':''}>gpt-4.1-mini</option>
            </select>`);
            h += `<label class="form-label small mb-1">Informações da empresa (para responder dúvidas)</label>` +
                 `<textarea class="form-control form-control-sm" rows="4" placeholder="Ex.: O que a ON Solutions faz, como funciona, prazos, diferenciais..." oninput="setData('company_info',this.value)">${escapeHtml(n.data.company_info||'')}</textarea>`;
            h += `<small class="text-muted d-block mb-2">A IA responde brevemente a dúvida do lead usando estas informações e o histórico, e em seguida acrescenta a mensagem/convite abaixo.</small>`;
        }
        h += field('Assunto (só e-mail)', `<input class="form-control form-control-sm" value="${escapeAttr(n.data.subject||'ON Solutions Brasil')}" oninput="setData('subject',this.value)">`);
        h += `<label class="form-label small mb-1">Mensagem ${aiReplyOn?'(convite — enviado após a resposta da IA)':''}</label>` + varChipsHtml() +
             `<textarea id="insp-body" class="form-control form-control-sm" rows="5" oninput="setData('body',this.value)">${escapeHtml(n.data.body||'')}</textarea>`;
    } else if (n.type==='connect') {
        const opts = '<option value="">— selecione a sequência —</option>' +
            (SEQUENCES||[]).map(s => `<option value="${s.id}" ${String(n.data.sequence_id)===String(s.id)?'selected':''}>${escapeHtml(s.name)}</option>`).join('');
        h += field('Sequência de destino', `<select class="form-select form-select-sm" onchange="setData('sequence_id',this.value)">${opts}</select>`);
        h += field('Ao conectar', `<select class="form-select form-select-sm" onchange="setData('stop_current',parseInt(this.value))">
            <option value="1" ${(n.data.stop_current??1)==1?'selected':''}>Encerrar esta sequência e seguir na nova</option>
            <option value="0" ${(n.data.stop_current??1)==0?'selected':''}>Manter esta ativa (inscreve em paralelo)</option></select>`);
        h += `<small class="text-muted d-block mt-1">Inscreve o lead na sequência escolhida (respeitando o canal dela). Útil para encadear fluxos — ex.: da cadência principal para a triagem por IA.</small>`;
    } else if (n.type==='unsubscribe') {
        h += field('Motivo (registro interno)', `<input class="form-control form-control-sm" value="${escapeAttr(n.data.reason||'Sem interesse (sequência)')}" oninput="setData('reason',this.value)">`);
        h += `<p class="text-muted small mb-0">Marca o lead como descadastrado (bloqueia novos envios), aplica a etiqueta "sem interesse" e registra na timeline. Coloque este bloco <strong>depois</strong> do e-mail/WhatsApp de confirmação.</p>`;
    } else if (n.type==='linkedin') {
        h += `<div class="alert alert-light border py-2 px-2 small mb-2" style="border-left:3px solid #0a66c2 !important;">
            <i class="bi bi-linkedin text-primary"></i> Etapa <strong>manual</strong>. Gera uma tarefa em <em>Minhas Ações</em>.
            A IA prepara a mensagem; o vendedor abre o perfil, cola e envia no LinkedIn e confirma. Nada é automatizado.</div>`;
        const actOpts = Object.entries(LINKEDIN_ACTIONS).map(([k,v])=>`<option value="${k}" ${(n.data.action_type||'message')===k?'selected':''}>${v}</option>`).join('');
        h += field('Ação', `<select class="form-select form-select-sm" onchange="setData('action_type',this.value)">${actOpts}</select>`);
        h += linkedinTemplatePicker();
        h += field('Objetivo', `<input class="form-control form-control-sm" value="${escapeAttr(n.data.objective||'')}" oninput="setData('objective',this.value)" placeholder="Ex.: agendar uma conversa rápida">`);
        h += `<label class="form-label small mb-1">Mensagem base (a IA personaliza com dados reais)</label>` + varChipsHtml() +
             `<textarea id="insp-body" class="form-control form-control-sm" rows="5" oninput="setData('body',this.value)" placeholder="Use variáveis: {{primeiro_nome}}, {{empresa}}, {{cargo}}...">${escapeHtml(n.data.body||'')}</textarea>`;
        h += `<div class="row g-2 mt-1">`;
        h += `<div class="col-6">` + field('CTA', `<input class="form-control form-control-sm" value="${escapeAttr(n.data.cta||'')}" oninput="setData('cta',this.value)" placeholder="opcional">`) + `</div>`;
        h += `<div class="col-6">` + field('Tom', `<input class="form-control form-control-sm" value="${escapeAttr(n.data.tone||'')}" oninput="setData('tone',this.value)" placeholder="ex.: cordial">`) + `</div>`;
        h += `</div>`;
        h += field('Limite de caracteres (0 = sem limite)', `<input type="number" min="0" class="form-control form-control-sm" value="${n.data.max_length||0}" oninput="setData('max_length',parseInt(this.value)||0)">`);
        h += `<small class="text-muted d-block">A IA usa somente dados reais do lead (nome, cargo, empresa, setor, local). Não inventa contexto.</small>`;
    } else if (n.type==='end') {
        h += '<p class="text-muted small">Encerra a sequência para o lead.</p>';
    }
    // Conexões via dropdown (jeito fácil)
    if (n.type !== 'end') {
        h += '<hr><div class="fw-semibold small mb-2"><i class="bi bi-arrow-down-right-circle"></i> Próximo bloco</div>';
        const others = nodes.filter(x => x.id !== n.id);
        const optsFor = (sel) => '<option value="">— nenhum —</option>' +
            others.map(o => `<option value="${o.id}" ${sel===o.id?'selected':''}>${NODE_LABELS[o.type]||o.type} · ${escapeHtml((nodeSummary(o)||'').replace(/<[^>]+>/g,'').slice(0,20))}</option>`).join('');
        const twoWay = (n.type === 'condition') || (n.type === 'ai' && (n.data||{}).mode === 'decision') || (n.type === 'ai_agent');
        if (twoWay) {
            const yesLbl = (n.type === 'ai_agent') ? 'Se QUER SEGUIR →' : 'Se SIM →';
            const noLbl  = (n.type === 'ai_agent') ? 'Se QUER ENCERRAR →' : 'Se NÃO →';
            h += field(yesLbl, `<select class="form-select form-select-sm" onchange="setNext('nextYes',this.value)">${optsFor(n.nextYes)}</select>`);
            h += field(noLbl, `<select class="form-select form-select-sm" onchange="setNext('nextNo',this.value)">${optsFor(n.nextNo)}</select>`);
        } else {
            h += field('Vai para', `<select class="form-select form-select-sm" onchange="setNext('next',this.value)">${optsFor(n.next)}</select>`);
        }
        // Saída extra "Resposta recebida" para blocos de mensagem
        if (n.type === 'send' || n.type === 'whatsapp') {
            h += field('<i class="bi bi-reply"></i> Resposta recebida →', `<select class="form-select form-select-sm" onchange="setNext('nextReply',this.value)">${optsFor(n.nextReply)}</select>`);
            h += `<small class="text-muted d-block">Para onde ir se o lead responder após este envio (ex.: Conexão → Triagem IA). O sistema detecta a resposta automaticamente por e-mail e WhatsApp.</small>`;
        }
    }
    box.innerHTML = h;
    bindInspectorVars();
    mountInspectorRich(n);
}
function field(label, input) { return `<div class="mb-2"><label class="form-label small mb-1">${label}</label>${input}</div>`; }

// Liga as barras de chips e o botão-direito aos textareas do inspector
function bindInspectorVars() {
    const box = document.getElementById('inspector');
    const chipBars = box.querySelectorAll('.var-chips');
    const bodyA = box.querySelector('#insp-body');
    const bodyB = box.querySelector('#insp-body-b');
    // 1ª barra → corpo A, 2ª barra → corpo B
    if (chipBars[0] && bodyA) bindVarChips(chipBars[0], bodyA);
    if (chipBars[1] && bodyB) bindVarChips(chipBars[1], bodyB);
    // menu de contexto também no assunto
    const subj = box.querySelector('#insp-subject'); if (subj) attachVarPicker(subj);
}

// Seletor de template para preencher o bloco
function templatePicker(channel) {
    const list = channel === 'whatsapp' ? WA_TEMPLATES : EMAIL_TEMPLATES;
    const manage = `<a href="${BASE}prospection/templates" target="_blank" rel="noopener" class="small"><i class="bi bi-pencil-square"></i> Criar / editar templates</a>`;
    if (!list.length) return '<div class="mb-2"><small class="text-muted">Nenhum template ' + (channel==='whatsapp'?'de WhatsApp':'de e-mail') + '.</small> ' + manage + '</div>';
    const opts = '<option value="">— usar template —</option>' + list.map(t=>`<option value="${t.id}">${escapeHtml(t.name)}</option>`).join('');
    return `<div class="mb-2"><div class="d-flex justify-content-between align-items-end"><label class="form-label small mb-1">Template</label>${manage}</div>`
         + `<select class="form-select form-select-sm" id="insp-template" onchange="applyTemplate('${channel}', this.value)">${opts}</select></div>`;
}
function applyTemplate(channel, id) {
    if (!id) return;
    const list = channel === 'whatsapp' ? WA_TEMPLATES : EMAIL_TEMPLATES;
    const t = list.find(x=>x.id==id); if (!t) return;
    const n = nodes.find(x=>x.id===selectedId); if (!n) return;
    if (channel === 'email' && t.subject) { n.data.subject = t.subject; const s=document.getElementById('insp-subject'); if(s)s.value=t.subject; }
    n.data.body = t.body || '';
    delete n.data.template_id;
    const b = document.getElementById('insp-body'); if (b) b.value = n.data.body;
    // E-mail: o template entra FORMATADO no editor e continua editável.
    const rich = document.getElementById('insp-rich');
    if (rich && rich._richEditor) rich._richEditor.set(n.data.body);
    refreshNodeBody(n);
}
// Seletor de template de LinkedIn: preenche a mensagem base do nó.
function linkedinTemplatePicker() {
    if (!LINKEDIN_TEMPLATES.length) return '<div class="mb-2"><small class="text-muted">Nenhum template de LinkedIn. Crie em Sequências → Templates (canal LinkedIn).</small></div>';
    const opts = '<option value="">— usar template —</option>' + LINKEDIN_TEMPLATES.map(t=>`<option value="${t.id}">${escapeHtml(t.name)}</option>`).join('');
    return field('Template LinkedIn', `<select class="form-select form-select-sm" onchange="applyLinkedinTemplate(this.value)">${opts}</select>`);
}
function applyLinkedinTemplate(id) {
    const n = nodes.find(x=>x.id===selectedId); if (!n) return;
    if (!id) { n.data.template_id = ''; return; }
    const t = LINKEDIN_TEMPLATES.find(x=>x.id==id); if (!t) return;
    n.data.template_id = id;
    n.data.body = t.body || '';
    const b = document.getElementById('insp-body'); if (b) b.value = n.data.body;
    refreshNodeBody(n);
}
// Bloco de teste A/B: quando ativado, envia aleatoriamente a variante A ou B
function abBlock(n, channel) {
    const on = !!n.data.ab_enabled;
    let h = `<hr><div class="form-check form-switch mb-2">
        <input class="form-check-input" type="checkbox" id="insp-ab" ${on?'checked':''} onchange="toggleAb(this.checked)">
        <label class="form-check-label small fw-medium" for="insp-ab"><i class="bi bi-shuffle"></i> Teste A/B</label></div>`;
    if (on) {
        h += `<div class="small text-muted mb-1">Variante B (50% dos envios):</div>`;
        if (channel === 'email') {
            h += field('Assunto B', `<input class="form-control form-control-sm" value="${escapeAttr(n.data.subject_b||'')}" oninput="setData('subject_b',this.value)">`);
        }
        if (channel === 'email') {
            h += `<label class="form-label small mb-1">Mensagem B</label>` +
                 `<div id="insp-rich-b-chips"></div><div id="insp-rich-b" data-key="body_b"></div>` +
                 `<div class="text-end mt-1"><button type="button" class="btn btn-sm btn-outline-primary py-0" onclick="previewNodeEmail('b')"><i class="bi bi-eye"></i> Ver como chega (B)</button></div>`;
        } else {
            h += `<label class="form-label small mb-1">Mensagem B</label>` + varChipsHtml('b') +
                 `<textarea id="insp-body-b" class="form-control form-control-sm" rows="5" oninput="setData('body_b',this.value)">${escapeHtml(n.data.body_b||'')}</textarea>`;
        }
    }
    return h;
}

// Editor formatado do bloco "Enviar e-mail" (corpo A e, se houver, corpo B).
// Grava o HTML no próprio bloco a cada alteração.
function mountInspectorRich(n) {
    const drawer = document.getElementById('inspector-drawer');
    drawer.classList.toggle('wide', n && n.type === 'send');
    if (!n || n.type !== 'send' || typeof RichEditor === 'undefined') return;
    [['insp-rich', 'insp-rich-chips', 'body'], ['insp-rich-b', 'insp-rich-b-chips', 'body_b']].forEach(([id, chipsId, key]) => {
        const el = document.getElementById(id);
        if (!el) return;
        RichEditor.mount(el, {
            html: n.data[key] || '',
            chips: document.getElementById(chipsId),
            placeholder: 'Olá {{primeiro_nome}}, ...',
            onChange: (html) => { n.data[key] = html; if (key === 'body') delete n.data.template_id; refreshNodeBody(n); },
        });
    });
}
function previewNodeEmail(variant) {
    const n = nodes.find(x => x.id === selectedId); if (!n) return;
    const b = variant === 'b';
    openEmailPreview({
        subject: (b && n.data.subject_b) ? n.data.subject_b : (n.data.subject || ''),
        body: b ? (n.data.body_b || '') : (n.data.body || ''),
        account_id: (document.getElementById('seq-account') || {}).value || '',
    });
}
function toggleAb(on) { setData('ab_enabled', on ? 1 : 0); renderInspector(); bindInspectorVars(); }

// Dropdown de etiqueta: escolhe existente (preenche cor) ou abre campo de nova
function onTagSelect(sel) {
    const val = sel.value;
    const newWrap = document.getElementById('insp-tag-new-wrap');
    if (val === '__new__') {
        if (newWrap) newWrap.style.display = '';
        const inp = document.getElementById('insp-tag-new');
        setData('label', inp ? inp.value : '');
        if (inp) inp.focus();
        return;
    }
    if (newWrap) newWrap.style.display = 'none';
    setData('label', val);
    // Preenche a cor da etiqueta escolhida
    const opt = sel.options[sel.selectedIndex];
    const color = opt ? opt.getAttribute('data-color') : null;
    if (color) {
        setData('color', color);
        const c = document.getElementById('insp-tag-color'); if (c) c.value = color;
    }
    // Atualiza o resumo do bloco
    const n = nodes.find(x=>x.id===selectedId); if (n) refreshNodeBody(n);
}

function loadTemplatesForEditor() {
    fetch(BASE + 'sequences/templates', {headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(r=>r.json()).then(d=>{
            (d.templates||[]).forEach(t => {
                if (t.channel==='whatsapp') WA_TEMPLATES.push(t);
                else if (t.channel==='linkedin') LINKEDIN_TEMPLATES.push(t);
                else EMAIL_TEMPLATES.push(t);
            });
        }).catch(()=>{});
}

// Atualiza dado sem recriar o nó (mantém foco no input) — só refresca o summary
function setData(key, val) {
    const n = nodes.find(x=>x.id===selectedId);
    if (n) { n.data[key] = val; refreshNodeBody(n); }
}
function setNext(port, targetId) {
    const n = nodes.find(x=>x.id===selectedId);
    if (!n) return;
    if (targetId) n[port] = targetId; else delete n[port];
    drawEdges();
}

// Bloco IA: ao trocar o tipo de resposta (simples/decisão), recria o nó para
// ajustar as portas de saída (1 saída no simples, 2 saídas no decisão) e limpa
// conexões incompatíveis.
function onAiModeChange(mode) {
    const n = nodes.find(x=>x.id===selectedId);
    if (!n) return;
    n.data.mode = mode;
    if (mode === 'decision') {
        delete n.next; // passa a usar nextYes/nextNo
    } else {
        delete n.nextYes; delete n.nextNo; // volta a usar next
    }
    // Recria o elemento do nó para refletir as portas corretas
    if (n._el) n._el.remove();
    n._el = buildNodeEl(n);
    canvas().appendChild(n._el);
    drawEdges();
    renderInspector();
}

// Liga/desliga o módulo ACOPLADO "Atendente de dúvidas" no bloco IA (decisão).
function onFaqToggle(active) {
    const n = nodes.find(x=>x.id===selectedId);
    if (!n) return;
    n.data.faq_active = active ? 1 : 0;
    // Recria o elemento do nó para mostrar/ocultar o módulo acoplado.
    if (n._el) n._el.remove();
    n._el = buildNodeEl(n);
    canvas().appendChild(n._el);
    drawEdges();
    renderInspector();
}

// Bloco "mover card": ao trocar o board, limpa a coluna e mostra só as colunas daquele board
function onMoveBoardChange(boardId) {
    const n = nodes.find(x=>x.id===selectedId);
    if (!n) return;
    n.data.board_id = boardId;
    n.data.column_id = ''; // reseta a coluna ao trocar de board
    refreshNodeBody(n);
    renderInspector();
}

// ================= Salvar / participantes =================
function buildGraph() {
    const start = nodes.length ? nodes[0].id : null;
    return { start, nodes: nodes.map(n => ({ id:n.id, type:n.type, x:n.x, y:n.y, data:n.data, next:n.next, nextYes:n.nextYes, nextNo:n.nextNo, nextReply:n.nextReply })) };
}
function saveSeq() {
    const name = document.getElementById('seq-name').value.trim();
    if (!name) { alert('Informe o nome da sequência.'); return; }
    const fd = new FormData();
    if (SEQ_ID) fd.append('id', SEQ_ID);
    fd.append('name', name);
    fd.append('channel_type', (document.getElementById('seq-channel') || {}).value || 'email');
    fd.append('email_account_id', document.getElementById('seq-account').value);
    fd.append('whatsapp_instance_id', (document.getElementById('seq-wa-instance') || {}).value || '');
    fd.append('daily_limit', document.getElementById('seq-daily').value);
    fd.append('window_start', document.getElementById('seq-wstart').value + ':00');
    fd.append('window_end', document.getElementById('seq-wend').value + ':00');
    fd.append('send_weekends', document.getElementById('seq-weekends').checked ? 1 : 0);
    fd.append('is_active', document.getElementById('seq-active').checked ? 1 : 0);
    fd.append('graph', JSON.stringify(buildGraph()));
    fetch(BASE + 'sequences/save', { method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'} })
        .then(r=>r.json()).then(d=>{
            if (d.error) { alert(d.error); return; }
            if (!SEQ_ID) { location.href = BASE + 'sequences/edit/' + d.id; return; }
            const btn = document.querySelector('[onclick="saveSeq()"]');
            if (btn) { const o=btn.innerHTML; btn.innerHTML='<i class="bi bi-check2"></i> Salvo!'; setTimeout(()=>btn.innerHTML=o,1500); }
        });
}

// Testa a sequência inteira agora (fluxo completo, pulando esperas) e mostra o resultado.
function testSequence(btn) {
    if (!SEQ_ID) { alert('Salve a sequência antes de testar.'); return; }
    if (!confirm('Executar a sequência inteira AGORA em modo teste?\n\nRoda o fluxo completo pulando as esperas. Usa o lead já inscrito (ou o primeiro da sequência). E-mails/WhatsApp reais serão enviados se houver conta configurada.')) return;
    const orig = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Testando...';
    fetch(BASE + 'sequences/runTest/' + SEQ_ID, { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'} })
        .then(r=>r.json()).then(d=>{
            btn.disabled = false; btn.innerHTML = orig;
            if (d.error) { alert(d.error); return; }
            const steps = (d.steps||[]).map(s => '• ' + (s.node||'?') + ' → ' + (s.result||s.error||'')).join('\n');
            const f = d.final || {};
            alert('Teste concluído.\n\nStatus final: ' + (f.status||'?') + (f.stop_reason?(' ('+f.stop_reason+')'):'') + (f.ab_variant?('\nVariante A/B: '+f.ab_variant):'') + '\n\nEtapas:\n' + (steps||'nenhuma') + '\n\nVeja detalhes em CRM → Prospecção Automática → Logs de execução.');
        })
        .catch(()=>{ btn.disabled=false; btn.innerHTML=orig; alert('Erro ao testar.'); });
}

let partModal = null;
function openParticipants() {
    if (!SEQ_ID) return;
    if (!partModal) partModal = new bootstrap.Modal(document.getElementById('partModal'));
    partModal.show(); loadLeadsSelect(); loadParticipants();
}
function loadLeadsSelect() {
    fetch(BASE + 'sequences/leadsForSelect', {headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(r=>r.json()).then(d=>{
            document.getElementById('add-lead-select').innerHTML = '<option value="">Selecione um lead...</option>' +
                (d.leads||[]).map(l=>`<option value="${l.id}">${escapeHtml(l.name||l.lead_email)} — ${escapeHtml(l.lead_email)}</option>`).join('');
        });
}
function addSelectedLead() {
    const cid = document.getElementById('add-lead-select').value; if (!cid) return;
    const fd = new FormData(); fd.append('sequence_id', SEQ_ID); fd.append('contact_ids[]', cid);
    fetch(BASE + 'sequences/addLeads', {method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(r=>r.json()).then(d=>{ if(d.errors&&d.errors.length)alert(d.errors.join('\n')); loadParticipants(); });
}
function loadParticipants() {
    fetch(BASE + 'sequences/detail/' + SEQ_ID, {headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(r=>r.json()).then(d=>{
            const box = document.getElementById('part-list'); const ps = d.participants||[];
            if (!ps.length) { box.innerHTML = '<p class="text-muted small text-center py-3 mb-0">Nenhum lead nesta sequência.</p>'; return; }
            const stMap = {active:['Ativo','success'],finished:['Concluído','secondary'],stopped:['Interrompido','warning'],failed:['Falha','danger'],paused:['Pausado','info']};
            box.innerHTML = `<table class="table table-sm mb-0" style="font-size:0.82rem;"><tbody>` + ps.map(p=>{
                const s = stMap[p.status]||[p.status,'secondary'];
                return `<tr><td>${escapeHtml(p.lead_name||p.lead_email)}<br><small class="text-muted">${escapeHtml(p.lead_email||'')}</small></td>
                    <td><span class="badge bg-${s[1]}">${s[0]}</span>${p.stop_reason?'<br><small class="text-muted">'+p.stop_reason+'</small>':''}</td>
                    <td class="text-end"><button class="btn btn-sm btn-link text-danger p-0" onclick="removePart(${p.id})"><i class="bi bi-x-lg"></i></button></td></tr>`;
            }).join('') + `</tbody></table>`;
        });
}
function removePart(id) {
    const fd = new FormData(); fd.append('participant_id', id);
    fetch(BASE + 'sequences/removeLead', {method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(r=>r.json()).then(()=>loadParticipants());
}

function escapeHtml(s){return String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
function escapeAttr(s){return escapeHtml(s);}

// Atalhos do quadro. Não valem enquanto se digita (campos, editor de texto) nem com janela aberta.
function isTypingTarget(el) {
    return !!(el && el.closest && el.closest('input, textarea, select, [contenteditable="true"], .ql-editor'));
}
document.addEventListener('keydown', (e)=>{
    if (e.key === 'Escape') {
        if (linkFrom) { cancelLink(); return; }
        if (selection.size && !isTypingTarget(e.target) && !document.querySelector('.modal.show')) clearSelection();
        return;
    }
    if (isTypingTarget(e.target) || document.querySelector('.modal.show')) return;
    const mod = e.ctrlKey || e.metaKey;
    const k = (e.key || '').toLowerCase();
    if (mod && k === 'a') { e.preventDefault(); selectAllNodes(); }
    else if (mod && k === 'c') { if (selection.size) { e.preventDefault(); copySelection(false); } }
    else if (mod && k === 'x') { if (selection.size) { e.preventDefault(); copySelection(true); deleteSelection(true); } }
    else if (mod && k === 'v') { if (readClipboard()) { e.preventDefault(); pasteClipboard(); } }
    else if (mod && k === 'd') { if (selection.size) { e.preventDefault(); duplicateSelection(); } }
    else if ((e.key === 'Delete' || e.key === 'Backspace') && selection.size) { e.preventDefault(); deleteSelection(); }
    else if (e.key.startsWith('Arrow') && selection.size && !mod) {
        e.preventDefault();
        const step = e.shiftKey ? 50 : 10;
        const d = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] }[e.key];
        if (d) nudgeSelection(d[0], d[1]);
    }
});
// Copiou em outra aba/sequência: libera o "Colar" aqui.
window.addEventListener('storage', (e) => { if (e.key === CLIP_KEY) updateSelBar(); });

loadTemplatesForEditor();
buildAll();
canvas().addEventListener('mousedown', onCanvasMouseDown);
updateSelBar();
applyZoom();
</script>
