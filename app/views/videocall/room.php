<?php
$base = rtrim(baseUrl(''), '/');
$roomToken = htmlspecialchars($room['token'], ENT_QUOTES);
$roomTitle = htmlspecialchars($room['title'] ?: 'Videochamada', ENT_QUOTES);
$suggested = htmlspecialchars($suggestedName ?? '', ENT_QUOTES);
$allowRec = (int)($room['allow_recording'] ?? 1) === 1;
$iceJson = json_encode($iceServers ?? [], JSON_UNESCAPED_SLASHES);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <meta name="theme-color" content="#0f1020">
    <title><?= $roomTitle ?> · Videochamada</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root { --brand:#00BFA6; --brand-dark:#009e88; --bg:#0f1020; --panel:#1a1c2e; --panel2:#23263d; }
        * { box-sizing:border-box; -webkit-tap-highlight-color:transparent; }
        html,body { height:100%; }
        body { margin:0; background:var(--bg); color:#e8eaf1; font-family:'Segoe UI',system-ui,Arial,sans-serif; overflow:hidden; }

        /* ---------- Lobby ---------- */
        #lobby { position:fixed; inset:0; display:flex; align-items:center; justify-content:center; padding:20px; z-index:30; }
        .lobby-card { width:100%; max-width:420px; background:var(--panel); border-radius:20px; padding:30px 26px; box-shadow:0 20px 60px rgba(0,0,0,.45); }
        .lobby-card h1 { font-size:1.3rem; font-weight:700; margin:0 0 4px; }
        .lobby-card .sub { color:#9aa2c0; font-size:.86rem; margin-bottom:20px; }
        .preview-wrap { position:relative; background:#000; border-radius:14px; overflow:hidden; aspect-ratio:16/9; margin-bottom:16px; }
        .preview-wrap video { width:100%; height:100%; object-fit:cover; transform:scaleX(-1); }
        .form-label { font-size:.78rem; font-weight:600; color:#9aa2c0; margin-bottom:5px; }
        .form-control { background:var(--panel2); border:1.5px solid #33375a; color:#fff; border-radius:12px; padding:11px 14px; }
        .form-control:focus { background:var(--panel2); color:#fff; border-color:var(--brand); box-shadow:0 0 0 3px rgba(0,191,166,.2); }
        .btn-join { background:var(--brand); border:none; color:#fff; border-radius:14px; padding:13px; font-weight:700; width:100%; }
        .btn-join:hover:not(:disabled) { background:var(--brand-dark); }
        .btn-join:disabled { opacity:.6; cursor:not-allowed; }
        .lobby-toggles { display:flex; gap:10px; margin-bottom:16px; }
        .toggle-btn { flex:1; background:var(--panel2); border:1.5px solid #33375a; color:#e8eaf1; border-radius:12px; padding:10px; cursor:pointer; font-size:.85rem; }
        .toggle-btn.off { background:#3a1620; border-color:#7a2436; color:#ff9db0; }

        /* ---------- Call ---------- */
        #call { position:fixed; inset:0; display:none; flex-direction:column; }
        .topbar { display:flex; align-items:center; gap:12px; padding:10px 16px; background:rgba(0,0,0,.25); }
        .topbar .title { font-weight:600; font-size:.95rem; }
        .topbar .count { font-size:.8rem; color:#9aa2c0; }
        .rec-dot { display:none; align-items:center; gap:6px; font-size:.8rem; color:#ff6b81; margin-left:auto; }
        .rec-dot .dot { width:9px; height:9px; border-radius:50%; background:#ff3b57; animation:pulse 1.2s infinite; }
        @keyframes pulse { 0%,100%{opacity:1;} 50%{opacity:.3;} }

        /* Área principal da chamada: pode virar stage + filmstrip quando há destaque. */
        .stage-wrap { flex:1; display:flex; min-height:0; overflow:hidden; }
        .stage-wrap.with-strip { flex-direction:column; }

        /* Grade normal (nada em destaque) */
        .grid { flex:1; display:grid; gap:8px; padding:8px; overflow:hidden; min-height:0;
            grid-template-columns:1fr; grid-auto-rows:1fr; align-content:stretch; }

        /* Palco (itens em destaque: tela compartilhada e/ou tiles fixados) */
        .stage { flex:1; display:grid; gap:8px; padding:8px; min-height:0; min-width:0; overflow:hidden;
            grid-auto-rows:1fr; }
        /* Faixa de miniaturas (câmeras que não estão em destaque) */
        .filmstrip { display:flex; gap:8px; padding:8px; overflow-x:auto; overflow-y:hidden; flex:0 0 auto; }
        .stage-wrap.with-strip .filmstrip { height:132px; }
        .filmstrip .tile { flex:0 0 auto; width:210px; height:118px; }
        .filmstrip::-webkit-scrollbar { height:6px; }
        .filmstrip::-webkit-scrollbar-thumb { background:#33375a; border-radius:6px; }

        .tile { position:relative; background:#000; border-radius:14px; overflow:hidden; min-height:0; min-width:0; }
        .tile .vwrap { position:absolute; inset:0; overflow:hidden; }
        .tile video { width:100%; height:100%; object-fit:cover; background:#000; transition:transform .12s ease; transform-origin:center center; }
        .tile.self video { transform:scaleX(-1); }
        .tile.self.zoomed video, .tile.self video.zoomed { /* self usa scaleX(-1) combinado no JS */ }
        .tile.screen video { object-fit:contain; }
        .tile .name { position:absolute; left:8px; bottom:8px; background:rgba(0,0,0,.55); padding:3px 9px; border-radius:8px; font-size:.78rem; max-width:80%; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; z-index:3; }
        .tile .badges { position:absolute; right:8px; top:8px; display:flex; gap:5px; z-index:3; }
        .tile .badge-ic { background:rgba(0,0,0,.55); width:26px; height:26px; border-radius:8px; display:none; align-items:center; justify-content:center; font-size:.85rem; }
        .tile.mic-off .badge-mic { display:flex; color:#ff9db0; }
        .tile.cam-off .badge-cam { display:flex; color:#ffd27f; }
        .tile.pinned .badge-pin { display:flex; color:var(--brand); }
        .tile .avatar { position:absolute; inset:0; display:none; align-items:center; justify-content:center; background:#23263d; z-index:1; }
        .tile.cam-off .avatar { display:flex; }
        .tile.cam-off video { visibility:hidden; }
        .tile .avatar span { width:72px; height:72px; border-radius:50%; background:var(--brand); display:flex; align-items:center; justify-content:center; font-size:1.8rem; font-weight:700; color:#fff; }
        .tile.pinned { outline:2px solid var(--brand); outline-offset:-2px; }

        /* Barra de ações do tile (aparece no hover): zoom e fixar */
        .tile-tools { position:absolute; top:8px; left:8px; display:flex; gap:5px; opacity:0; transition:opacity .15s; z-index:4; }
        .tile:hover .tile-tools, .tile.show-tools .tile-tools { opacity:1; }
        .tile-tools button { width:30px; height:30px; border:none; border-radius:8px; background:rgba(10,12,24,.72); color:#fff; font-size:.9rem; cursor:pointer; display:flex; align-items:center; justify-content:center; }
        .tile-tools button:hover { background:var(--brand); }
        .tile-tools .zoom-val { min-width:38px; padding:0 6px; height:30px; border-radius:8px; background:rgba(10,12,24,.72); color:#cfd3e6; font-size:.7rem; display:flex; align-items:center; justify-content:center; }
        .filmstrip .tile-tools { transform:scale(.85); transform-origin:top left; }

        /* Barra de controles */
        .controls { display:flex; align-items:center; justify-content:center; gap:10px; padding:14px; background:rgba(0,0,0,.3); flex-wrap:wrap; }
        .ctrl { width:52px; height:52px; border-radius:50%; border:none; background:var(--panel2); color:#fff; font-size:1.15rem; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:.15s; position:relative; }
        .ctrl:hover { filter:brightness(1.2); }
        .ctrl.off { background:#c0304a; }
        .ctrl.active { background:var(--brand); }
        .ctrl.hangup { background:#e02a44; width:60px; }
        .ctrl-label { position:absolute; bottom:-18px; left:50%; transform:translateX(-50%); font-size:.62rem; color:#9aa2c0; white-space:nowrap; }
        .controls .ctrl { margin-bottom:16px; }

        .toast-box { position:fixed; top:16px; left:50%; transform:translateX(-50%); z-index:60; }
        .toast-msg { background:var(--panel); border:1px solid #33375a; color:#e8eaf1; padding:10px 16px; border-radius:12px; margin-bottom:8px; font-size:.85rem; box-shadow:0 8px 24px rgba(0,0,0,.4); }

        /* Modal de gravações / link */
        .link-modal { position:fixed; inset:0; background:rgba(0,0,0,.6); z-index:70; display:none; align-items:center; justify-content:center; padding:20px; }
        .link-modal .inner { background:var(--panel); border-radius:16px; padding:24px; max-width:520px; width:100%; }
        .rec-item { background:var(--panel2); border-radius:10px; padding:10px 12px; margin-bottom:8px; display:flex; align-items:center; gap:10px; }
        /* ================= MOBILE ================= */
        @media (max-width:768px){
            .topbar { padding:8px 12px; gap:8px; }
            .topbar .title { font-size:.85rem; max-width:52vw; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
            .topbar .count { font-size:.72rem; }
            .rec-dot { font-size:.72rem; }

            .grid, .stage { gap:6px; padding:6px; }

            /* Faixa de miniaturas menor no celular */
            .stage-wrap.with-strip .filmstrip { height:96px; }
            .filmstrip { gap:6px; padding:6px; }
            .filmstrip .tile { width:150px; height:84px; }

            /* Ferramentas de zoom/pin: no toque, mostradas via .show-tools */
            .tile-tools { top:6px; left:6px; gap:4px; }
            .tile-tools button { width:34px; height:34px; font-size:1rem; }
            .tile-tools .zoom-val { height:34px; }

            /* Controles: barra rolável horizontal, sem quebrar linha */
            .controls { gap:8px; padding:10px 8px calc(10px + env(safe-area-inset-bottom)); flex-wrap:nowrap; overflow-x:auto; justify-content:flex-start; }
            .controls::-webkit-scrollbar { display:none; }
            .ctrl { width:46px; height:46px; font-size:1.05rem; flex:0 0 auto; }
            .ctrl.hangup { width:52px; }
            .ctrl-label { display:none; }
            .controls .ctrl { margin-bottom:0; }
            .tile .name { font-size:.7rem; padding:2px 7px; }
        }

        @media (max-width:768px) and (orientation:portrait){
            /* Em retrato, palco fica em coluna única (tela grande em cima) */
            .stage { grid-template-columns:1fr !important; }
        }

        /* Lobby no celular */
        @media (max-width:520px){
            #lobby { padding:12px; align-items:flex-start; }
            .lobby-card { padding:20px 16px; border-radius:16px; margin-top:16px; }
            .lobby-card h1 { font-size:1.1rem; }
            .link-modal .inner { padding:18px; }
        }

        /* Telas muito baixas (celular deitado): reduz a faixa e controles */
        @media (max-height:450px){
            .stage-wrap.with-strip .filmstrip { height:78px; }
            .filmstrip .tile { width:120px; height:66px; }
            .controls { padding:6px 8px; }
            .ctrl { width:42px; height:42px; }
        }
    </style>
</head>
<body>
<div class="toast-box" id="toasts"></div>

<!-- ===================== LOBBY ===================== -->
<div id="lobby">
    <div class="lobby-card">
        <h1><i class="bi bi-camera-video"></i> <?= $roomTitle ?></h1>
        <div class="sub">Ajuste sua câmera e microfone antes de entrar.</div>
        <div class="preview-wrap"><video id="preview" autoplay muted playsinline></video></div>
        <div class="lobby-toggles">
            <button type="button" class="toggle-btn" id="lb-mic" onclick="toggleLobby('mic')"><i class="bi bi-mic-fill"></i> Microfone</button>
            <button type="button" class="toggle-btn" id="lb-cam" onclick="toggleLobby('cam')"><i class="bi bi-camera-video-fill"></i> Câmera</button>
        </div>
        <div class="mb-3">
            <label class="form-label">Seu nome</label>
            <input type="text" id="lb-name" class="form-control" placeholder="Como quer aparecer?" value="<?= $suggested ?>" maxlength="120">
        </div>
        <button class="btn-join" id="lb-join" onclick="enterRoom()"><i class="bi bi-box-arrow-in-right"></i> Entrar na chamada</button>
        <div id="lb-error" class="text-danger small mt-2" style="display:none;"></div>
    </div>
</div>

<!-- ===================== CALL ===================== -->
<div id="call">
    <div class="topbar">
        <span class="title"><i class="bi bi-camera-video"></i> <?= $roomTitle ?></span>
        <span class="count"><i class="bi bi-people-fill"></i> <span id="peer-count">1</span></span>
        <span class="rec-dot" id="rec-indicator"><span class="dot"></span> Gravando <span id="rec-time">00:00</span></span>
    </div>
    <div class="stage-wrap" id="stage-wrap">
        <div class="stage" id="stage" style="display:none;"></div>
        <div class="grid" id="grid"></div>
        <div class="filmstrip" id="filmstrip" style="display:none;"></div>
    </div>
    <div class="controls">
        <button class="ctrl" id="btn-mic" onclick="toggleMic()" title="Microfone"><i class="bi bi-mic-fill"></i><span class="ctrl-label">Mic</span></button>
        <button class="ctrl" id="btn-cam" onclick="toggleCam()" title="Câmera"><i class="bi bi-camera-video-fill"></i><span class="ctrl-label">Câmera</span></button>
        <button class="ctrl" id="btn-screen" onclick="toggleScreen()" title="Compartilhar tela"><i class="bi bi-display"></i><span class="ctrl-label">Tela</span></button>
        <?php if ($allowRec): ?>
        <button class="ctrl" id="btn-rec" onclick="toggleRecording()" title="Gravar"><i class="bi bi-record-circle"></i><span class="ctrl-label">Gravar</span></button>
        <?php endif; ?>
        <button class="ctrl" id="btn-copy" onclick="copyLink()" title="Copiar link"><i class="bi bi-link-45deg"></i><span class="ctrl-label">Link</span></button>
        <button class="ctrl hangup" onclick="hangup()" title="Sair"><i class="bi bi-telephone-x-fill"></i><span class="ctrl-label">Sair</span></button>
    </div>
</div>

<!-- Modal: gravação pronta -->
<div class="link-modal" id="rec-modal">
    <div class="inner">
        <h5><i class="bi bi-check-circle-fill text-success"></i> Gravação salva no servidor</h5>
        <p class="small text-secondary mb-2">O arquivo ficou salvo no servidor. Use o link abaixo para assistir ou baixar.</p>
        <div class="rec-item">
            <i class="bi bi-film"></i>
            <input type="text" class="form-control" id="rec-url" readonly>
            <button class="btn btn-sm btn-outline-light" onclick="copyRecUrl()"><i class="bi bi-clipboard"></i></button>
        </div>
        <div class="text-end mt-3">
            <a class="btn btn-sm btn-outline-light" id="rec-open" target="_blank"><i class="bi bi-box-arrow-up-right"></i> Abrir</a>
            <button class="btn btn-sm btn-primary" onclick="document.getElementById('rec-modal').style.display='none'">Fechar</button>
        </div>
    </div>
</div>

<script>
const BASE = '<?= $base ?>';
const ROOM_TOKEN = '<?= $roomToken ?>';
const ICE_SERVERS = <?= $iceJson ?: '[]' ?>;

// ---- Estado global ----
// peerId identifica ESTA aba/sessão. browserId é persistente por navegador
// (compartilhado entre guias) — usado para impedir sessão duplicada no mesmo navegador.
const peerId = 'p' + Math.random().toString(36).slice(2, 10) + Date.now().toString(36).slice(-4);
const browserId = (function () {
    try {
        let b = localStorage.getItem('vc_browser_id');
        if (!b) { b = 'b' + Math.random().toString(36).slice(2, 12) + Date.now().toString(36).slice(-4); localStorage.setItem('vc_browser_id', b); }
        return b;
    } catch (e) { return 'b' + Math.random().toString(36).slice(2, 12); }
})();
let myName = '<?= $suggested ?>' || 'Convidado';
let localStream = null;      // câmera + microfone
let screenStream = null;     // compartilhamento de tela (track adicional)
const peers = new Map();     // remotePeerId -> { pc, name, tiles }
let micOn = true, camOn = true, sharing = false;
let polling = false, joined = false;

// ---------- LOBBY ----------
let lobbyMic = true, lobbyCam = true;

async function initPreview() {
    try {
        localStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
        document.getElementById('preview').srcObject = localStream;
        syncLobbyButtons();
    } catch (e) {
        // Sem câmera/mic: ainda permite entrar (só ouvir).
        lobbyCam = false; camOn = false;
        showLobbyError('Não foi possível acessar câmera/microfone. Verifique as permissões do navegador. Você ainda pode entrar sem enviar vídeo.');
        syncLobbyButtons();
    }
}

function showLobbyError(msg) {
    const el = document.getElementById('lb-error');
    el.textContent = msg; el.style.display = 'block';
}

function toggleLobby(kind) {
    if (!localStream) return;
    if (kind === 'mic') {
        lobbyMic = !lobbyMic;
        localStream.getAudioTracks().forEach(t => t.enabled = lobbyMic);
    } else {
        lobbyCam = !lobbyCam;
        localStream.getVideoTracks().forEach(t => t.enabled = lobbyCam);
    }
    syncLobbyButtons();
}

function syncLobbyButtons() {
    const m = document.getElementById('lb-mic'), c = document.getElementById('lb-cam');
    m.classList.toggle('off', !lobbyMic);
    m.innerHTML = lobbyMic ? '<i class="bi bi-mic-fill"></i> Microfone' : '<i class="bi bi-mic-mute-fill"></i> Mudo';
    c.classList.toggle('off', !lobbyCam);
    c.innerHTML = lobbyCam ? '<i class="bi bi-camera-video-fill"></i> Câmera' : '<i class="bi bi-camera-video-off-fill"></i> Sem vídeo';
}

async function enterRoom() {
    const nameInput = document.getElementById('lb-name').value.trim();
    if (nameInput) myName = nameInput;
    micOn = lobbyMic; camOn = lobbyCam;

    const btn = document.getElementById('lb-join');
    btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Entrando…';

    try {
        await doJoin(false);
    } catch (e) {
        btn.disabled = false; btn.innerHTML = '<i class="bi bi-box-arrow-in-right"></i> Entrar na chamada';
        showLobbyError('Erro ao entrar na chamada. Tente novamente.');
    }
}

// Executa o join. takeover=true força mover a chamada para esta guia (derruba a outra).
async function doJoin(takeover) {
    const btn = document.getElementById('lb-join');
    const params = { peer_id: peerId, name: myName, browser_id: browserId };
    if (takeover) params.takeover = '1';

    const res = await fetch(`${BASE}/videocall/join/${ROOM_TOKEN}`, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: new URLSearchParams(params)
    }).then(r => r.json());

    // Já existe uma sessão deste MESMO navegador em outra guia.
    if (res.duplicate) {
        btn.disabled = false; btn.innerHTML = '<i class="bi bi-box-arrow-in-right"></i> Entrar na chamada';
        const ok = confirm('Você já está nesta chamada em outra guia deste navegador.\n\nDeseja mover a chamada para esta aba? A outra guia será desconectada.');
        if (ok) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Movendo…'; await doJoin(true); }
        return;
    }

    if (res.error) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-box-arrow-in-right"></i> Entrar na chamada'; showLobbyError(res.error); return; }

    document.getElementById('lobby').style.display = 'none';
    document.getElementById('call').style.display = 'flex';
    joined = true;

    addSelfTile();
    // Abre conexão com quem já está na sala. Regra de "quem chama":
    // o novato inicia a oferta para os presentes (glare evitado por polite peer).
    (res.peers || []).forEach(p => { ensurePeer(p.peer_id, p.name, true); });
    updateCount();
    startPolling();
}

// ---------- TILES (grade de vídeos) ----------
function tileEl(id) { return document.getElementById('tile-' + id); }

// Estado de zoom (fator por tile) e de fixação (pin).
const tileZoom = new Map();   // id -> fator (1 = 100%)
const pinned = new Set();     // ids fixados manualmente

function makeTile(id, name, opts = {}) {
    const div = document.createElement('div');
    div.className = 'tile' + (opts.self ? ' self' : '') + (opts.screen ? ' screen' : '');
    div.id = 'tile-' + id;
    div.dataset.tid = id;
    const initial = (name || 'C').trim().charAt(0).toUpperCase();
    div.innerHTML =
        `<div class="vwrap"><video autoplay playsinline ${opts.self ? 'muted' : ''}></video></div>
         <div class="avatar"><span>${initial}</span></div>
         <div class="tile-tools">
            <button title="Diminuir zoom" onclick="zoomTile('${id}',-0.25)"><i class="bi bi-zoom-out"></i></button>
            <span class="zoom-val" id="zoom-${id}">100%</span>
            <button title="Aumentar zoom" onclick="zoomTile('${id}',0.25)"><i class="bi bi-zoom-in"></i></button>
            <button title="Zoom padrão" onclick="resetZoom('${id}')"><i class="bi bi-arrow-counterclockwise"></i></button>
            <button title="Fixar/desafixar" onclick="togglePin('${id}')"><i class="bi bi-pin-angle"></i></button>
         </div>
         <div class="badges">
            <div class="badge-ic badge-pin"><i class="bi bi-pin-angle-fill"></i></div>
            <div class="badge-ic badge-mic"><i class="bi bi-mic-mute-fill"></i></div>
            <div class="badge-ic badge-cam"><i class="bi bi-camera-video-off-fill"></i></div>
         </div>
         <div class="name">${escapeHtml(name)}${opts.screen ? ' (tela)' : ''}</div>`;
    // Nasce no grid; o layoutGrid() reposiciona no stage/filmstrip conforme o destaque.
    document.getElementById('grid').appendChild(div);
    tileZoom.set(id, 1);
    layoutGrid();
    return div;
}

// ---------- Zoom por tile (só afeta a visualização local de quem clica) ----------
function applyZoom(id) {
    const t = tileEl(id); if (!t) return;
    const v = t.querySelector('video'); if (!v) return;
    const z = tileZoom.get(id) || 1;
    const mirror = t.classList.contains('self') ? -1 : 1; // preserva o espelho do próprio vídeo
    v.style.transform = `scaleX(${mirror}) scale(${z})`;
    const label = document.getElementById('zoom-' + id);
    if (label) label.textContent = Math.round(z * 100) + '%';
}
function zoomTile(id, delta) {
    let z = (tileZoom.get(id) || 1) + delta;
    z = Math.max(1, Math.min(4, Math.round(z * 100) / 100));
    tileZoom.set(id, z);
    applyZoom(id);
}
function resetZoom(id) { tileZoom.set(id, 1); applyZoom(id); }

// ---------- Fixar (pin) ----------
function togglePin(id) {
    if (pinned.has(id)) pinned.delete(id); else pinned.add(id);
    layoutGrid();
}

// Um tile de tela compartilhada é sempre destaque automático.
function isScreenTile(id) { return id.endsWith('-screen'); }

/**
 * Distribui os tiles entre o "palco" (destaques) e a grade/faixa (demais),
 * no estilo Meet: tela compartilhada e itens fixados ficam grandes; as câmeras
 * restantes vão para uma faixa de miniaturas. Sem destaque, usa a grade cheia.
 */
function layoutGrid() {
    const grid = document.getElementById('grid');
    const stage = document.getElementById('stage');
    const strip = document.getElementById('filmstrip');
    const wrap = document.getElementById('stage-wrap');

    const allTiles = Array.from(document.querySelectorAll('#stage .tile, #grid .tile, #filmstrip .tile'));

    // Quem é destaque: qualquer tile de tela + qualquer fixado.
    const featured = allTiles.filter(t => isScreenTile(t.dataset.tid) || pinned.has(t.dataset.tid));
    const others = allTiles.filter(t => !featured.includes(t));

    if (featured.length === 0) {
        // ----- Modo grade normal (preenche a tela, sem scroll) -----
        stage.style.display = 'none';
        strip.style.display = 'none';
        wrap.classList.remove('with-strip');
        grid.style.display = 'grid';
        allTiles.forEach(t => grid.appendChild(t));
        const n = allTiles.length || 1;
        grid.setAttribute('data-n', Math.min(n, 16));
        let cols = 1;
        if (n === 2) cols = 2;
        else if (n <= 4) cols = 2;
        else if (n <= 9) cols = 3;
        else cols = 4;
        if (window.innerWidth <= 700) cols = (n === 1) ? 1 : 2;
        grid.style.gridTemplateColumns = `repeat(${cols}, 1fr)`;
        grid.style.gridTemplateRows = `repeat(${Math.ceil(n / cols)}, 1fr)`;
    } else {
        // ----- Modo apresentação: palco + faixa de miniaturas -----
        grid.style.display = 'none';
        stage.style.display = 'grid';
        featured.forEach(t => stage.appendChild(t));
        const fn = featured.length;
        const scols = fn === 1 ? 1 : 2;
        stage.style.gridTemplateColumns = `repeat(${scols}, 1fr)`;
        stage.style.gridTemplateRows = `repeat(${Math.ceil(fn / scols)}, 1fr)`;

        if (others.length) {
            strip.style.display = 'flex';
            wrap.classList.add('with-strip');
            others.forEach(t => strip.appendChild(t));
        } else {
            strip.style.display = 'none';
            wrap.classList.remove('with-strip');
        }
    }
    // Reaplica o zoom de cada tile (o transform se mantém entre reposicionamentos).
    allTiles.forEach(t => applyZoom(t.dataset.tid));
}

function addSelfTile() {
    const div = makeTile(peerId, myName + ' (você)', { self: true });
    div.querySelector('video').srcObject = localStream;
    div.classList.toggle('mic-off', !micOn);
    div.classList.toggle('cam-off', !camOn);
}

function removeTile(id) {
    const t = tileEl(id);
    if (t) { t.remove(); tileZoom.delete(id); pinned.delete(id); layoutGrid(); }
}

function updateCount() { document.getElementById('peer-count').textContent = (peers.size + 1); }

// ---------- WebRTC mesh ----------
function ensurePeer(remoteId, name, initiator) {
    if (remoteId === peerId || peers.has(remoteId)) return peers.get(remoteId);

    const pc = new RTCPeerConnection({ iceServers: ICE_SERVERS });
    const entry = { pc, name, polite: peerId < remoteId, makingOffer: false, tile: null, screenTile: null, pendingIce: [] };
    peers.set(remoteId, entry);

    // Publica a minha mídia (câmera/mic).
    if (localStream) localStream.getTracks().forEach(t => pc.addTrack(t, localStream));
    // Se já estou compartilhando tela, envia também.
    if (screenStream) screenStream.getVideoTracks().forEach(t => pc.addTrack(t, screenStream));

    pc.onicecandidate = (e) => { if (e.candidate) sendSignal(remoteId, 'ice', e.candidate); };

    pc.ontrack = (e) => {
        const stream = e.streams[0];
        // Distingue câmera de tela: a stream de tela costuma ter só vídeo.
        const isScreen = stream && stream.getAudioTracks().length === 0 && stream.getVideoTracks().length === 1 && entry.hasCam;
        if (isScreen) {
            if (!entry.screenTile) entry.screenTile = makeTile(remoteId + '-screen', name, { screen: true });
            entry.screenTile.querySelector('video').srcObject = stream;
        } else {
            entry.hasCam = true;
            if (!entry.tile) entry.tile = makeTile(remoteId, name);
            entry.tile.querySelector('video').srcObject = stream;
        }
        updateCount();
    };

    pc.onnegotiationneeded = async () => {
        try {
            entry.makingOffer = true;
            await pc.setLocalDescription(await pc.createOffer());
            sendSignal(remoteId, 'offer', pc.localDescription);
        } catch (err) { console.warn('negotiation', err); }
        finally { entry.makingOffer = false; }
    };

    pc.onconnectionstatechange = () => {
        if (['failed', 'closed', 'disconnected'].includes(pc.connectionState)) {
            // Deixa o polling de presença decidir a remoção definitiva.
        }
    };

    if (initiator) {
        // Dispara a oferta inicial.
        pc.onnegotiationneeded();
    }
    return entry;
}

async function handleSignal(sig) {
    const from = sig.from;
    if (!from || from === peerId) return;

    if (sig.kind === 'join') {
        // Alguém entrou: o presente NÃO inicia (o novato é quem oferta), só prepara nome.
        if (!peers.has(from)) ensurePeer(from, (sig.payload && sig.payload.name) || 'Convidado', false);
        return;
    }
    if (sig.kind === 'kick') { onKicked(); return; }
    if (sig.kind === 'leave' || sig.kind === 'end') { dropPeer(from); return; }

    if (sig.kind === 'media') {
        const t = tileEl(from);
        if (t && sig.payload) {
            t.classList.toggle('mic-off', !!sig.payload.micMuted);
            t.classList.toggle('cam-off', !!sig.payload.camOff);
        }
        return;
    }
    if (sig.kind === 'screen') {
        // Fim do compartilhamento remoto: remove o tile de tela.
        if (sig.payload && sig.payload.stop) removeTile(from + '-screen');
        return;
    }

    const entry = peers.get(from) || ensurePeer(from, 'Convidado', false);
    const pc = entry.pc;

    try {
        if (sig.kind === 'offer') {
            const offerCollision = entry.makingOffer || pc.signalingState !== 'stable';
            if (offerCollision && !entry.polite) return; // impolite ignora em colisão
            await pc.setRemoteDescription(new RTCSessionDescription(sig.payload));
            await drainIce(entry);
            await pc.setLocalDescription(await pc.createAnswer());
            sendSignal(from, 'answer', pc.localDescription);
        } else if (sig.kind === 'answer') {
            await pc.setRemoteDescription(new RTCSessionDescription(sig.payload));
            await drainIce(entry);
        } else if (sig.kind === 'ice') {
            if (pc.remoteDescription && pc.remoteDescription.type) {
                await pc.addIceCandidate(new RTCIceCandidate(sig.payload));
            } else {
                entry.pendingIce.push(sig.payload);
            }
        }
    } catch (err) { console.warn('signal handling', sig.kind, err); }
}

async function drainIce(entry) {
    while (entry.pendingIce.length) {
        try { await entry.pc.addIceCandidate(new RTCIceCandidate(entry.pendingIce.shift())); } catch (e) {}
    }
}

function dropPeer(id) {
    const entry = peers.get(id);
    if (entry) { try { entry.pc.close(); } catch (e) {} }
    peers.delete(id);
    removeTile(id);
    removeTile(id + '-screen');
    updateCount();
}

function sendSignal(to, kind, payload) {
    fetch(`${BASE}/videocall/signal/${ROOM_TOKEN}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ from: peerId, to: to || '', kind, payload })
    }).catch(() => {});
}
function broadcast(kind, payload) { sendSignal('', kind, payload); }

// ---------- Long-polling ----------
async function startPolling() {
    if (polling) return;
    polling = true;
    while (joined) {
        try {
            const res = await fetch(`${BASE}/videocall/poll/${ROOM_TOKEN}?peer_id=${peerId}`).then(r => r.json());
            if (res.error) { await sleep(1500); continue; }
            (res.signals || []).forEach(handleSignal);
            reconcilePeers(res.peers || []);
        } catch (e) { await sleep(1500); }
    }
}

// Remove tiles de quem saiu (presença) — segurança extra além do sinal 'leave'.
function reconcilePeers(activeList) {
    const active = new Set(activeList.map(p => p.peer_id));
    peers.forEach((_, id) => { if (!active.has(id)) dropPeer(id); });
    // Garante conexão com quem apareceu mas ainda não temos (sem iniciar oferta).
    activeList.forEach(p => { if (p.peer_id !== peerId && !peers.has(p.peer_id)) ensurePeer(p.peer_id, p.name, false); });
    updateCount();
}

// ---------- Controles de mídia ----------
function toggleMic() {
    micOn = !micOn;
    if (localStream) localStream.getAudioTracks().forEach(t => t.enabled = micOn);
    document.getElementById('btn-mic').classList.toggle('off', !micOn);
    document.getElementById('btn-mic').innerHTML = (micOn ? '<i class="bi bi-mic-fill"></i>' : '<i class="bi bi-mic-mute-fill"></i>') + '<span class="ctrl-label">Mic</span>';
    tileEl(peerId)?.classList.toggle('mic-off', !micOn);
    broadcast('media', { micMuted: !micOn, camOff: !camOn });
}

function toggleCam() {
    camOn = !camOn;
    if (localStream) localStream.getVideoTracks().forEach(t => t.enabled = camOn);
    document.getElementById('btn-cam').classList.toggle('off', !camOn);
    document.getElementById('btn-cam').innerHTML = (camOn ? '<i class="bi bi-camera-video-fill"></i>' : '<i class="bi bi-camera-video-off-fill"></i>') + '<span class="ctrl-label">Câmera</span>';
    tileEl(peerId)?.classList.toggle('cam-off', !camOn);
    broadcast('media', { micMuted: !micOn, camOff: !camOn });
}

// Compartilhamento de tela: publica como TRACK ADICIONAL (mantém a câmera).
async function toggleScreen() {
    if (sharing) { stopScreen(); return; }
    try {
        screenStream = await navigator.mediaDevices.getDisplayMedia({ video: true, audio: false });
    } catch (e) { return; }
    sharing = true;
    document.getElementById('btn-screen').classList.add('active');

    // Mostra a própria tela num tile local (vai automaticamente para o palco).
    const selfScreen = makeTile(peerId + '-screen', myName + ' (sua tela)', { self: false, screen: true });
    selfScreen.querySelector('video').srcObject = screenStream;

    const screenTrack = screenStream.getVideoTracks()[0];
    peers.forEach((entry) => { entry.pc.addTrack(screenTrack, screenStream); }); // dispara renegociação
    screenTrack.onended = () => stopScreen();
}

function stopScreen() {
    if (!sharing) return;
    sharing = false;
    document.getElementById('btn-screen').classList.remove('active');
    if (screenStream) {
        screenStream.getTracks().forEach(t => {
            t.stop();
            peers.forEach((entry) => {
                const sender = entry.pc.getSenders().find(s => s.track === t);
                if (sender) { try { entry.pc.removeTrack(sender); } catch (e) {} }
            });
        });
    }
    removeTile(peerId + '-screen');
    screenStream = null;
    broadcast('screen', { stop: true });
}

// ---------- Gravação (MediaRecorder no navegador → salva no servidor) ----------
let mediaRecorder = null, recordedChunks = [], recStartTs = 0, recTimer = null;

function buildRecordingStream() {
    // Mixa áudio de todos + vídeo próprio. Para uma gravação simples e confiável,
    // grava a própria câmera + os áudios remotos captados. (Gravação client-side.)
    const mixed = new MediaStream();
    if (localStream) localStream.getTracks().forEach(t => mixed.addTrack(t));
    if (screenStream) screenStream.getVideoTracks().forEach(t => mixed.addTrack(t));
    // Áudios remotos
    peers.forEach(entry => {
        const remote = entry.tile?.querySelector('video')?.srcObject;
        if (remote) remote.getAudioTracks().forEach(t => mixed.addTrack(t));
    });
    return mixed;
}

function toggleRecording() {
    if (mediaRecorder && mediaRecorder.state !== 'inactive') { stopRecording(); return; }
    startRecording();
}

function startRecording() {
    let mime = 'video/webm;codecs=vp9,opus';
    if (!MediaRecorder.isTypeSupported(mime)) mime = 'video/webm;codecs=vp8,opus';
    if (!MediaRecorder.isTypeSupported(mime)) mime = 'video/webm';
    try {
        recordedChunks = [];
        mediaRecorder = new MediaRecorder(buildRecordingStream(), { mimeType: mime });
    } catch (e) { toast('Este navegador não suporta gravação.'); return; }

    mediaRecorder.ondataavailable = (e) => { if (e.data && e.data.size) recordedChunks.push(e.data); };
    mediaRecorder.onstop = uploadRecording;
    mediaRecorder.start(1000);

    recStartTs = Date.now();
    document.getElementById('btn-rec').classList.add('off');
    document.getElementById('rec-indicator').style.display = 'flex';
    recTimer = setInterval(() => {
        const s = Math.floor((Date.now() - recStartTs) / 1000);
        document.getElementById('rec-time').textContent =
            String(Math.floor(s / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0');
    }, 1000);
    toast('Gravação iniciada. Mantenha esta aba aberta.');
}

function stopRecording() {
    if (mediaRecorder && mediaRecorder.state !== 'inactive') mediaRecorder.stop();
    clearInterval(recTimer);
    document.getElementById('btn-rec').classList.remove('off');
    document.getElementById('rec-indicator').style.display = 'none';
}

async function uploadRecording() {
    if (!recordedChunks.length) return;
    const blob = new Blob(recordedChunks, { type: 'video/webm' });
    const durationSec = Math.floor((Date.now() - recStartTs) / 1000);
    toast('Enviando gravação para o servidor…');

    const fd = new FormData();
    fd.append('recording', blob, 'gravacao.webm');
    fd.append('duration_sec', durationSec);
    fd.append('recorded_by_name', myName);

    try {
        const res = await fetch(`${BASE}/videocall/upload/${ROOM_TOKEN}`, { method: 'POST', body: fd }).then(r => r.json());
        if (res.error) { toast('Erro ao salvar: ' + res.error); return; }
        const url = res.url;
        document.getElementById('rec-url').value = url;
        document.getElementById('rec-open').href = url;
        document.getElementById('rec-modal').style.display = 'flex';
    } catch (e) { toast('Falha ao enviar a gravação.'); }
}

// ---------- Link / sair ----------
function copyLink() {
    const url = `${BASE}/videocall/room/${ROOM_TOKEN}`;
    navigator.clipboard?.writeText(url).then(() => toast('Link copiado! Cole no convite ou no Fathom.')).catch(() => toast(url));
}
function copyRecUrl() {
    const v = document.getElementById('rec-url').value;
    navigator.clipboard?.writeText(v).then(() => toast('Link da gravação copiado!'));
}

// Encerra as mídias/conexões desta guia e devolve uma mensagem de saída.
function teardown(headline, sub) {
    joined = false;
    if (mediaRecorder && mediaRecorder.state !== 'inactive') { try { stopRecording(); } catch (e) {} }
    peers.forEach(e => { try { e.pc.close(); } catch (x) {} });
    if (localStream) localStream.getTracks().forEach(t => t.stop());
    if (screenStream) screenStream.getTracks().forEach(t => t.stop());
    document.body.innerHTML = '<div style="height:100vh;display:flex;align-items:center;justify-content:center;flex-direction:column;color:#e8eaf1;font-family:system-ui;text-align:center;padding:20px;"><div style="font-size:3rem;">' + (headline.icon || '👋') + '</div><h3 style="margin-top:12px;">' + headline.text + '</h3>' + (sub ? '<p style="color:#9aa2c0;margin-top:4px;max-width:320px;">' + sub + '</p>' : '') + '<a href="' + BASE + '/videocall/room/' + ROOM_TOKEN + '" style="color:#00BFA6;margin-top:10px;">Entrar novamente</a></div>';
}

function hangup() {
    if (!joined) return;
    joined = false;
    if (mediaRecorder && mediaRecorder.state !== 'inactive') { stopRecording(); }
    try {
        navigator.sendBeacon(`${BASE}/videocall/leave/${ROOM_TOKEN}`, new URLSearchParams({ peer_id: peerId }));
    } catch (e) {
        fetch(`${BASE}/videocall/leave/${ROOM_TOKEN}`, { method: 'POST', body: new URLSearchParams({ peer_id: peerId }) });
    }
    teardown({ icon: '👋', text: 'Você saiu da chamada' });
}

// Esta guia foi substituída por outra guia do mesmo navegador (takeover).
function onKicked() {
    if (!joined) return;
    joined = false;
    teardown({ icon: '↪️', text: 'Chamada movida para outra guia' },
        'Você entrou nesta chamada em outra aba deste navegador. Esta sessão foi encerrada para evitar duplicidade.');
}

window.addEventListener('beforeunload', () => {
    if (joined) {
        try { navigator.sendBeacon(`${BASE}/videocall/leave/${ROOM_TOKEN}`, new URLSearchParams({ peer_id: peerId })); } catch (e) {}
    }
});

// ---------- utils ----------
function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }
function escapeHtml(s) { return (s || '').replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c])); }
function toast(msg) {
    const box = document.getElementById('toasts');
    const el = document.createElement('div');
    el.className = 'toast-msg'; el.textContent = msg;
    box.appendChild(el);
    setTimeout(() => el.remove(), 4000);
}

window.addEventListener('resize', () => { if (joined) layoutGrid(); });
// Reajusta ao girar o celular (o resize às vezes não dispara no orientationchange).
window.addEventListener('orientationchange', () => { setTimeout(() => { if (joined) layoutGrid(); }, 300); });

// No celular não há hover: um toque no tile mostra/esconde os controles de zoom/pin.
const IS_TOUCH = ('ontouchstart' in window) || navigator.maxTouchPoints > 0;
if (IS_TOUCH) {
    document.addEventListener('click', (ev) => {
        const tile = ev.target.closest('.tile');
        // Toque em um botão das ferramentas não deve alternar a visibilidade.
        if (ev.target.closest('.tile-tools')) return;
        document.querySelectorAll('.tile.show-tools').forEach(t => { if (t !== tile) t.classList.remove('show-tools'); });
        if (tile) tile.classList.toggle('show-tools');
    });
}

initPreview();
</script>
</body>
</html>
