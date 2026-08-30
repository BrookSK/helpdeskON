<style>
#canvas-wrap { position:relative; overflow:auto; height:calc(100vh - 220px); min-height:420px; background:
    linear-gradient(90deg, rgba(0,0,0,.03) 1px, transparent 1px) 0 0/24px 24px,
    linear-gradient(rgba(0,0,0,.03) 1px, transparent 1px) 0 0/24px 24px, #f8f9fb; }
/* Drawer flutuante de Propriedades */
#inspector-drawer { position:fixed; top:0; right:0; width:340px; max-width:90vw; height:100vh;
    background:#fff; box-shadow:-4px 0 20px rgba(0,0,0,.12); z-index:1200; transform:translateX(100%);
    transition:transform .2s ease; display:flex; flex-direction:column; }
#inspector-drawer.open { transform:translateX(0); }
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
.n-send .hd{color:#0d6efd} .n-whatsapp .hd{color:#198754} .n-wait .hd{color:#fd7e14} .n-condition .hd{color:#6f42c1}
.n-tag .hd{color:#20c997} .n-score .hd{color:#e0a800} .n-move .hd{color:#0dcaf0} .n-end .hd{color:#dc3545}
.n-reveal_phone .hd{color:#212529} .n-ai .hd{color:#0d6efd} .n-unsubscribe .hd{color:#dc3545} .n-schedule .hd{color:#198754} .n-connect .hd{color:#0d6efd} .n-reply .hd{color:#0dcaf0} .n-ai_agent .hd{color:#6f42c1}
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
const NODE_LABELS = { send:'Enviar e-mail', whatsapp:'Enviar WhatsApp', wait:'Aguardar', condition:'Condição', ai:'IA (ChatGPT)', ai_agent:'Atendente IA (FAQ)', schedule:'Agendamento', connect:'Conexão de sequência', reply:'Responder ao lead', tag:'Tag', score:'Score', move:'Mover card', unsubscribe:'Remover da lista', reveal_phone:'Revelar telefone', end:'Encerrar' };
const SEQUENCES = <?= json_encode(array_map(fn($s) => ['id'=>$s['id'],'name'=>$s['name']], $sequencesList ?? []), JSON_UNESCAPED_UNICODE) ?>;
let EMAIL_TEMPLATES = [], WA_TEMPLATES = [];

let nodes = [];       // {id, type, x, y, data, next, nextYes, nextNo, _el}
let selectedId = null;
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
function uid() { return 'n' + Math.random().toString(36).slice(2, 8); }

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
    el.innerHTML = `<div class="hd"><span>${NODE_LABELS[n.type]||n.type}</span><span class="x">&times;</span></div>
        <div class="bd">${nodeSummary(n)}</div>${ports}`;

    // Fechar
    el.querySelector('.x').addEventListener('mousedown', (e)=>{ e.stopPropagation(); delNode(n.id); });
    // Portas de saída → inicia ligação
    el.querySelectorAll('.port.out').forEach(p => {
        p.addEventListener('mousedown', (e)=>{ e.stopPropagation(); startLink(n.id, p.dataset.port); });
    });
    // Corpo: drag OU finalizar ligação OU selecionar
    el.addEventListener('mousedown', (e)=>{
        if (e.target.classList.contains('port') || e.target.classList.contains('x')) return;
        if (linkFrom) { finishLink(n.id); return; }
        selectNode(n.id);
        startDrag(e, n);
    });
    if (n.id === selectedId) el.classList.add('sel');
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
        case 'reply': return 'Responde no mesmo canal do lead' + (d.body ? (': ' + escapeHtml(d.body.slice(0,30))) : '');
        case 'ai_agent': return ((d.active===undefined||d.active) ? 'Tira dúvidas em loop até concluir SIM/NÃO' : 'Classifica SIM/NÃO (uma passada)') + ' · ' + escapeHtml(d.model||'gpt-4o-mini');
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
function startDrag(e, n) {
    const el = n._el;
    const startX = e.clientX, startY = e.clientY, ox = n.x, oy = n.y;
    el.style.cursor = 'grabbing'; el.style.zIndex = 10;
    let raf = null;
    function move(ev) {
        n.x = Math.max(0, ox + (ev.clientX - startX) / ZOOM);
        n.y = Math.max(0, oy + (ev.clientY - startY) / ZOOM);
        if (!raf) raf = requestAnimationFrame(() => {
            raf = null;
            el.style.transform = `translate(${n.x}px, ${n.y}px)`;
            drawEdges();
        });
    }
    function up() {
        document.removeEventListener('mousemove', move);
        document.removeEventListener('mouseup', up);
        el.style.cursor = 'grab'; el.style.zIndex = '';
    }
    document.addEventListener('mousemove', move);
    document.addEventListener('mouseup', up);
    e.preventDefault();
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
    if (type === 'ai') return { mode:'simple', model:'gpt-4o-mini', prompt:'', send_channel:'', save_note:1 };
    if (type === 'ai_agent') return { active:1, model:'gpt-4o-mini', company_info:'', instructions:'', max_turns:6 };
    if (type === 'tag') return { label:'', color:'#00BFA6' };
    if (type === 'score') return { delta:3 };
    if (type === 'move') return { column_id:'' };
    if (type === 'unsubscribe') return { reason:'Sem interesse (sequência)' };
    if (type === 'schedule') return { channel:'auto', duration:45, title:'Reunião com a ON Solutions Brasil', message:'' };
    if (type === 'connect') return { sequence_id:'', stop_current:1 };
    if (type === 'reply') return { subject:'ON Solutions Brasil', body:'' };
    if (type === 'reveal_phone') return {};
    return {};
}
function delNode(id) {
    const n = nodes.find(x=>x.id===id);
    if (n && n._el) n._el.remove();
    nodes = nodes.filter(x=>x.id!==id);
    nodes.forEach(x=>{ if(x.next===id)delete x.next; if(x.nextYes===id)delete x.nextYes; if(x.nextNo===id)delete x.nextNo; if(x.nextReply===id)delete x.nextReply; });
    if (selectedId===id) { selectedId=null; renderInspector(); }
    drawEdges();
}

// ================= Seleção =================
function selectNode(id) {
    if (selectedId) { const p = nodes.find(x=>x.id===selectedId); if (p && p._el) p._el.classList.remove('sel'); }
    selectedId = id;
    const n = nodes.find(x=>x.id===id); if (n && n._el) n._el.classList.add('sel');
    renderInspector();
    openInspector();
}
function openInspector() { document.getElementById('inspector-drawer').classList.add('open'); }
function closeInspector() {
    document.getElementById('inspector-drawer').classList.remove('open');
    if (selectedId) { const p = nodes.find(x=>x.id===selectedId); if (p && p._el) p._el.classList.remove('sel'); }
    selectedId = null;
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
        h += `<label class="form-label small mb-1">Mensagem (HTML)</label>` + varChipsHtml() +
             `<textarea id="insp-body" class="form-control form-control-sm" rows="6" oninput="setData('body',this.value)">${escapeHtml(n.data.body||'')}</textarea>`;
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
            h += `<div class="alert alert-light border py-2 px-2 small mb-0"><i class="bi bi-signpost-split text-primary"></i> A IA decide <strong>SIM</strong> ou <strong>NÃO</strong>. Conecte as duas saídas (verde = Sim, vermelha = Não) aos próximos blocos.</div>`;
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
        h += field('Assunto (só e-mail)', `<input class="form-control form-control-sm" value="${escapeAttr(n.data.subject||'ON Solutions Brasil')}" oninput="setData('subject',this.value)">`);
        h += `<label class="form-label small mb-1">Mensagem</label>` + varChipsHtml() +
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
    if (!list.length) return '<div class="mb-2"><small class="text-muted">Nenhum template ' + (channel==='whatsapp'?'de WhatsApp':'de e-mail') + '. Crie em Sequências → Templates.</small></div>';
    const opts = '<option value="">— usar template —</option>' + list.map(t=>`<option value="${t.id}">${escapeHtml(t.name)}</option>`).join('');
    return field('Template', `<select class="form-select form-select-sm" onchange="applyTemplate('${channel}', this.value)">${opts}</select>`);
}
function applyTemplate(channel, id) {
    if (!id) return;
    const list = channel === 'whatsapp' ? WA_TEMPLATES : EMAIL_TEMPLATES;
    const t = list.find(x=>x.id==id); if (!t) return;
    const n = nodes.find(x=>x.id===selectedId); if (!n) return;
    if (channel === 'email' && t.subject) { n.data.subject = t.subject; const s=document.getElementById('insp-subject'); if(s)s.value=t.subject; }
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
        h += `<label class="form-label small mb-1">Mensagem B</label>` + varChipsHtml('b') +
             `<textarea id="insp-body-b" class="form-control form-control-sm" rows="5" oninput="setData('body_b',this.value)">${escapeHtml(n.data.body_b||'')}</textarea>`;
    }
    return h;
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
            (d.templates||[]).forEach(t => { if (t.channel==='whatsapp') WA_TEMPLATES.push(t); else EMAIL_TEMPLATES.push(t); });
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

// ESC cancela ligação
document.addEventListener('keydown', (e)=>{ if (e.key==='Escape' && linkFrom) cancelLink(); });

loadTemplatesForEditor();
buildAll();
applyZoom();
</script>
