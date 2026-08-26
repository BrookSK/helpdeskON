<style>
.seq-node { position:absolute; width:180px; background:#fff; border:2px solid #dee2e6; border-radius:10px;
    box-shadow:0 2px 6px rgba(0,0,0,.08); cursor:grab; user-select:none; font-size:0.8rem; }
.seq-node.sel { border-color: var(--primary); box-shadow:0 0 0 3px rgba(0,191,166,.2); }
.seq-node .hd { padding:6px 10px; border-bottom:1px solid #eee; font-weight:600; display:flex; justify-content:space-between; align-items:center; border-radius:8px 8px 0 0; }
.seq-node .bd { padding:6px 10px; color:#667; font-size:0.74rem; min-height:24px; }
.seq-node .port { width:14px; height:14px; border-radius:50%; background:#fff; border:2px solid var(--primary); position:absolute; cursor:crosshair; }
.seq-node .port.out { bottom:-8px; left:50%; transform:translateX(-50%); }
.seq-node .port.out.yes { left:30%; background:#d4edda; }
.seq-node .port.out.no { left:70%; background:#f8d7da; }
.seq-node .port.in { top:-8px; left:50%; transform:translateX(-50%); background:#e9ecef; }
.n-send .hd{color:#0d6efd} .n-wait .hd{color:#fd7e14} .n-condition .hd{color:#6f42c1}
.n-tag .hd{color:#20c997} .n-score .hd{color:#ffc107} .n-move .hd{color:#0dcaf0} .n-end .hd{color:#dc3545}
</style>

<script>
const BASE = '<?= baseUrl('') ?>';
const SEQ_ID = <?= $sequence ? (int)$sequence['id'] : 'null' ?>;
const COLUMNS = <?= json_encode(array_map(fn($c) => ['id'=>$c['id'],'label'=>$c['board_name'].' · '.$c['name']], $columns), JSON_UNESCAPED_UNICODE) ?>;

let nodes = [];      // {id, type, x, y, data, next, nextYes, nextNo}
let selectedId = null;
let linkFrom = null; // {id, port}
let seqInstance = null, partModal = null;
const NODE_LABELS = { send:'Enviar e-mail', wait:'Aguardar', condition:'Condição', tag:'Tag', score:'Score', move:'Mover card', end:'Encerrar' };

// Carrega grafo existente
<?php if ($sequence && $sequence['graph']): ?>
(function(){ const g = <?= $sequence['graph'] ?>; if (g && g.nodes) { nodes = g.nodes.map(n => ({x:60,y:60,data:{},...n})); } })();
<?php endif; ?>

function uid() { return 'n' + Math.random().toString(36).slice(2, 8); }

function addNode(type) {
    const n = { id: uid(), type, x: 80 + Math.random()*80, y: 80 + nodes.length*20, data: defaultData(type) };
    nodes.push(n); render(); selectNode(n.id);
}
function defaultData(type) {
    if (type === 'send') return { subject:'', body:'' };
    if (type === 'wait') return { amount:2, unit:'days' };
    if (type === 'condition') return { kind:'replied' };
    if (type === 'tag') return { label:'' };
    if (type === 'score') return { delta:3 };
    if (type === 'move') return { column_id:'' };
    return {};
}

function render() {
    const canvas = document.getElementById('canvas');
    canvas.innerHTML = '';
    nodes.forEach(n => canvas.appendChild(renderNode(n)));
    renderEdges();
}

function renderNode(n) {
    const el = document.createElement('div');
    el.className = 'seq-node n-' + n.type + (n.id === selectedId ? ' sel' : '');
    el.style.left = n.x + 'px'; el.style.top = n.y + 'px';
    el.dataset.id = n.id;
    const summary = nodeSummary(n);
    el.innerHTML = `<div class="hd"><span>${NODE_LABELS[n.type]||n.type}</span><i class="bi bi-x" onclick="delNode('${n.id}');event.stopPropagation();" style="cursor:pointer"></i></div>
        <div class="bd">${summary}</div>
        ${n.type!=='end' ? '<div class="port in"></div>' : '<div class="port in"></div>'}`;
    // portas de saída
    if (n.type === 'condition') {
        el.innerHTML += `<div class="port out yes" title="Sim" onclick="startLink('${n.id}','yes');event.stopPropagation();"></div>
                         <div class="port out no" title="Não" onclick="startLink('${n.id}','no');event.stopPropagation();"></div>`;
    } else if (n.type !== 'end') {
        el.innerHTML += `<div class="port out" onclick="startLink('${n.id}','next');event.stopPropagation();"></div>`;
    }
    // seleção + conexão de destino (clicar no corpo enquanto liga)
    el.addEventListener('mousedown', (e) => {
        if (e.target.classList.contains('port')) return;
        if (linkFrom) { finishLink(n.id); return; }
        selectNode(n.id); startDrag(e, n, el);
    });
    return el;
}

function nodeSummary(n) {
    const d = n.data || {};
    switch (n.type) {
        case 'send': return d.subject ? ('Assunto: ' + escapeHtml(d.subject)) : '<em>sem assunto</em>';
        case 'wait': return 'Aguardar ' + (d.amount||0) + ' ' + ({minutes:'min',hours:'h',days:'dias'}[d.unit]||'dias');
        case 'condition': return 'Se ' + ({replied:'respondeu',opened:'abriu',clicked:'clicou'}[d.kind]||'?') + '?';
        case 'tag': return 'Tag: ' + escapeHtml(d.label||'');
        case 'score': return 'Score ' + (d.delta>0?'+':'') + (d.delta||0);
        case 'move': { const c = COLUMNS.find(x=>x.id==d.column_id); return c ? escapeHtml(c.label) : '<em>escolher coluna</em>'; }
        case 'end': return 'Fim da sequência';
    }
    return '';
}

// ---- Drag ----
function startDrag(e, n, el) {
    const canvas = document.getElementById('canvas');
    const startX = e.clientX, startY = e.clientY, ox = n.x, oy = n.y;
    el.style.cursor = 'grabbing';
    function move(ev) { n.x = Math.max(0, ox + (ev.clientX-startX)); n.y = Math.max(0, oy + (ev.clientY-startY)); el.style.left=n.x+'px'; el.style.top=n.y+'px'; renderEdges(); }
    function up() { document.removeEventListener('mousemove', move); document.removeEventListener('mouseup', up); el.style.cursor='grab'; }
    document.addEventListener('mousemove', move); document.addEventListener('mouseup', up);
}

// ---- Conexões ----
function startLink(nodeId, port) { linkFrom = { id: nodeId, port }; document.body.style.cursor = 'crosshair'; }
function finishLink(targetId) {
    if (!linkFrom || linkFrom.id === targetId) { cancelLink(); return; }
    const n = nodes.find(x=>x.id===linkFrom.id);
    if (n) {
        if (linkFrom.port === 'yes') n.nextYes = targetId;
        else if (linkFrom.port === 'no') n.nextNo = targetId;
        else n.next = targetId;
    }
    cancelLink(); render();
}
function cancelLink() { linkFrom = null; document.body.style.cursor = ''; }
document.addEventListener('click', (e)=>{ if (linkFrom && !e.target.closest('.seq-node')) cancelLink(); });

function renderEdges() {
    const svg = document.getElementById('edges');
    svg.innerHTML = '';
    nodes.forEach(n => {
        const conns = [];
        if (n.next) conns.push([n.next, '#00BFA6']);
        if (n.nextYes) conns.push([n.nextYes, '#28a745']);
        if (n.nextNo) conns.push([n.nextNo, '#dc3545']);
        conns.forEach(([to, color]) => {
            const t = nodes.find(x=>x.id===to); if (!t) return;
            const x1=n.x+90, y1=n.y+70, x2=t.x+90, y2=t.y;
            const path = document.createElementNS('http://www.w3.org/2000/svg','path');
            const mid=(y1+y2)/2;
            path.setAttribute('d', `M${x1},${y1} C${x1},${mid} ${x2},${mid} ${x2},${y2}`);
            path.setAttribute('stroke', color); path.setAttribute('fill','none'); path.setAttribute('stroke-width','2');
            svg.appendChild(path);
        });
    });
}

function delNode(id) {
    nodes = nodes.filter(n=>n.id!==id);
    nodes.forEach(n=>{ if(n.next===id)delete n.next; if(n.nextYes===id)delete n.nextYes; if(n.nextNo===id)delete n.nextNo; });
    if (selectedId===id) { selectedId=null; renderInspector(); }
    render();
}

// ---- Inspector ----
function selectNode(id) { selectedId = id; render(); renderInspector(); }
function renderInspector() {
    const box = document.getElementById('inspector');
    const n = nodes.find(x=>x.id===selectedId);
    if (!n) { box.innerHTML = '<p class="text-muted small mb-0">Selecione um bloco para editar.</p>'; return; }
    let h = `<div class="mb-2 fw-semibold small">${NODE_LABELS[n.type]}</div>`;
    if (n.type==='send') {
        h += field('Assunto', `<input class="form-control form-control-sm" value="${escapeAttr(n.data.subject||'')}" oninput="setData('subject',this.value)">`);
        h += field('Mensagem (HTML)', `<textarea class="form-control form-control-sm" rows="6" oninput="setData('body',this.value)">${escapeHtml(n.data.body||'')}</textarea>`);
        h += `<small class="text-muted">Variáveis: {{nome}}, {{primeiro_nome}}, {{email}}</small>`;
    } else if (n.type==='wait') {
        h += field('Quantidade', `<input type="number" min="1" class="form-control form-control-sm" value="${n.data.amount||1}" oninput="setData('amount',parseInt(this.value)||1)">`);
        h += field('Unidade', `<select class="form-select form-select-sm" onchange="setData('unit',this.value)">
            <option value="minutes" ${n.data.unit==='minutes'?'selected':''}>Minutos</option>
            <option value="hours" ${n.data.unit==='hours'?'selected':''}>Horas</option>
            <option value="days" ${n.data.unit==='days'||!n.data.unit?'selected':''}>Dias</option></select>`);
    } else if (n.type==='condition') {
        h += field('Condição', `<select class="form-select form-select-sm" onchange="setData('kind',this.value)">
            <option value="replied" ${n.data.kind==='replied'?'selected':''}>Respondeu?</option>
            <option value="opened" ${n.data.kind==='opened'?'selected':''}>Abriu?</option>
            <option value="clicked" ${n.data.kind==='clicked'?'selected':''}>Clicou?</option></select>`);
        h += `<small class="text-muted">Verde = Sim · Vermelho = Não</small>`;
    } else if (n.type==='tag') {
        h += field('Tag', `<input class="form-control form-control-sm" value="${escapeAttr(n.data.label||'')}" oninput="setData('label',this.value)">`);
    } else if (n.type==='score') {
        h += field('Pontos (+/-)', `<input type="number" class="form-control form-control-sm" value="${n.data.delta||0}" oninput="setData('delta',parseInt(this.value)||0)">`);
    } else if (n.type==='move') {
        let opts = '<option value="">Selecione a coluna</option>' + COLUMNS.map(c=>`<option value="${c.id}" ${n.data.column_id==c.id?'selected':''}>${escapeHtml(c.label)}</option>`).join('');
        h += field('Coluna do board', `<select class="form-select form-select-sm" onchange="setData('column_id',this.value)">${opts}</select>`);
    } else if (n.type==='end') {
        h += '<p class="text-muted small">Encerra a sequência para o lead.</p>';
    }
    box.innerHTML = h;
}
function field(label, input) { return `<div class="mb-2"><label class="form-label small mb-1">${label}</label>${input}</div>`; }
function setData(key, val) { const n = nodes.find(x=>x.id===selectedId); if(n){ n.data[key]=val; render(); } }

// ---- Salvar ----
function buildGraph() {
    const start = nodes.length ? nodes[0].id : null;
    return { start, nodes: nodes.map(n => ({ id:n.id, type:n.type, x:n.x, y:n.y, data:n.data, next:n.next, nextYes:n.nextYes, nextNo:n.nextNo })) };
}
function saveSeq() {
    const name = document.getElementById('seq-name').value.trim();
    if (!name) { alert('Informe o nome da sequência.'); return; }
    const fd = new FormData();
    if (SEQ_ID) fd.append('id', SEQ_ID);
    fd.append('name', name);
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
            alert('Sequência salva!');
        });
}

// ---- Participantes ----
function openParticipants() {
    if (!SEQ_ID) return;
    if (!partModal) partModal = new bootstrap.Modal(document.getElementById('partModal'));
    partModal.show();
    loadLeadsSelect(); loadParticipants();
}
function loadLeadsSelect() {
    fetch(BASE + 'sequences/leadsForSelect', {headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(r=>r.json()).then(d=>{
            const sel = document.getElementById('add-lead-select');
            sel.innerHTML = '<option value="">Selecione um lead...</option>' +
                (d.leads||[]).map(l=>`<option value="${l.id}">${escapeHtml(l.name||l.lead_email)} — ${escapeHtml(l.lead_email)}</option>`).join('');
        });
}
function addSelectedLead() {
    const cid = document.getElementById('add-lead-select').value;
    if (!cid) return;
    const fd = new FormData(); fd.append('sequence_id', SEQ_ID); fd.append('contact_ids[]', cid);
    fetch(BASE + 'sequences/addLeads', {method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(r=>r.json()).then(d=>{ if(d.errors&&d.errors.length)alert(d.errors.join('\n')); loadParticipants(); });
}
function loadParticipants() {
    fetch(BASE + 'sequences/detail/' + SEQ_ID, {headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(r=>r.json()).then(d=>{
            const box = document.getElementById('part-list');
            const ps = d.participants||[];
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

render();
</script>
