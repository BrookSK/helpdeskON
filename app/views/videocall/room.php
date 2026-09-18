<?php
$base = rtrim(baseUrl(''), '/');
$roomToken = htmlspecialchars($room['token'], ENT_QUOTES);
$roomTitle = htmlspecialchars($room['title'] ?: 'Videochamada', ENT_QUOTES);
$suggested = htmlspecialchars($suggestedName ?? '', ENT_QUOTES);
$allowRec = (int)($room['allow_recording'] ?? 1) === 1;
$visibility = ($room['visibility'] ?? 'public');
$isAdmin = !empty($isAdmin);
$iceJson = json_encode($iceServers ?? [], JSON_UNESCAPED_SLASHES);
$bgJson = json_encode($backgrounds ?? [], JSON_UNESCAPED_SLASHES);
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
        .hidden { display:none !important; }

        /* ---------- Lobby ---------- */
        #lobby { position:fixed; inset:0; display:flex; align-items:center; justify-content:center; padding:20px; z-index:30; overflow:auto; }
        .lobby-card { width:100%; max-width:440px; background:var(--panel); border-radius:20px; padding:28px 24px; box-shadow:0 20px 60px rgba(0,0,0,.45); }
        .lobby-card h1 { font-size:1.25rem; font-weight:700; margin:0 0 4px; }
        .lobby-card .sub { color:#9aa2c0; font-size:.86rem; margin-bottom:16px; }
        .private-badge { display:inline-flex; align-items:center; gap:5px; font-size:.72rem; background:rgba(0,191,166,.15); color:var(--brand); padding:3px 9px; border-radius:999px; margin-bottom:12px; }
        .preview-wrap { position:relative; background:#000; border-radius:14px; overflow:hidden; aspect-ratio:16/9; margin-bottom:14px; }
        .preview-wrap video, .preview-wrap canvas { width:100%; height:100%; object-fit:cover; }
        .preview-wrap video.mirror, .preview-wrap canvas.mirror { transform:scaleX(-1); }
        .form-label { font-size:.78rem; font-weight:600; color:#9aa2c0; margin-bottom:5px; }
        .form-control, .form-select { background:var(--panel2); border:1.5px solid #33375a; color:#fff; border-radius:12px; padding:10px 12px; font-size:.9rem; }
        .form-control:focus, .form-select:focus { background:var(--panel2); color:#fff; border-color:var(--brand); box-shadow:0 0 0 3px rgba(0,191,166,.2); }
        .form-select option { background:var(--panel2); }
        .btn-join { background:var(--brand); border:none; color:#fff; border-radius:14px; padding:13px; font-weight:700; width:100%; }
        .btn-join:hover:not(:disabled) { background:var(--brand-dark); }
        .btn-join:disabled { opacity:.6; cursor:not-allowed; }
        .lobby-toggles { display:flex; gap:10px; margin-bottom:14px; }
        .toggle-btn { flex:1; background:var(--panel2); border:1.5px solid #33375a; color:#e8eaf1; border-radius:12px; padding:10px; cursor:pointer; font-size:.85rem; }
        .toggle-btn.off { background:#3a1620; border-color:#7a2436; color:#ff9db0; }

        /* Grade de fundos (lobby e menu) */
        .bg-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:8px; margin-bottom:14px; }
        .bg-opt { position:relative; aspect-ratio:1; border-radius:10px; overflow:hidden; cursor:pointer; border:2px solid transparent; background:var(--panel2) center/cover no-repeat; display:flex; align-items:center; justify-content:center; font-size:1.1rem; color:#9aa2c0; }
        .bg-opt.active { border-color:var(--brand); }
        .bg-opt small { position:absolute; bottom:2px; left:0; right:0; text-align:center; font-size:.55rem; color:#fff; text-shadow:0 1px 2px #000; }

        /* ---------- Call ---------- */
        #call { position:fixed; inset:0; display:none; flex-direction:column; }
        .topbar { display:flex; align-items:center; gap:12px; padding:10px 16px; background:rgba(0,0,0,.25); }
        .topbar .title { font-weight:600; font-size:.95rem; }
        .topbar .count { font-size:.8rem; color:#9aa2c0; cursor:default; }
        .topbar .count.clickable { cursor:pointer; }
        .topbar .count .badge-dot { display:none; background:#ff3b57; border-radius:999px; padding:1px 6px; font-size:.65rem; margin-left:4px; }
        .topbar .count.has-req .badge-dot { display:inline-block; }
        .rec-dot { display:none; align-items:center; gap:6px; font-size:.8rem; color:#ff6b81; margin-left:auto; }
        .rec-dot .dot { width:9px; height:9px; border-radius:50%; background:#ff3b57; animation:pulse 1.2s infinite; }
        @keyframes pulse { 0%,100%{opacity:1;} 50%{opacity:.3;} }

        .stage-wrap { flex:1; display:flex; min-height:0; overflow:hidden; }
        .stage-wrap.with-strip { flex-direction:column; }
        .grid { flex:1; display:grid; gap:8px; padding:8px; overflow:hidden; min-height:0; grid-template-columns:1fr; grid-auto-rows:1fr; align-content:stretch; }
        .stage { flex:1; display:grid; gap:8px; padding:8px; min-height:0; min-width:0; overflow:hidden; grid-auto-rows:1fr; }
        .filmstrip { display:flex; gap:8px; padding:8px; overflow-x:auto; overflow-y:hidden; flex:0 0 auto; }
        .stage-wrap.with-strip .filmstrip { height:132px; }
        .filmstrip .tile { flex:0 0 auto; width:210px; height:118px; }
        .filmstrip::-webkit-scrollbar { height:6px; }
        .filmstrip::-webkit-scrollbar-thumb { background:#33375a; border-radius:6px; }

        .tile { position:relative; background:#000; border-radius:14px; overflow:hidden; min-height:0; min-width:0; }
        .tile .vwrap { position:absolute; inset:0; overflow:hidden; }
        .tile video { width:100%; height:100%; object-fit:cover; background:#000; transition:transform .12s ease; transform-origin:center center; }
        .tile.self video { transform:scaleX(-1); }
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

        .tile-tools { position:absolute; top:8px; left:8px; display:flex; gap:5px; opacity:0; transition:opacity .15s; z-index:4; }
        .tile:hover .tile-tools, .tile.show-tools .tile-tools { opacity:1; }
        .tile-tools button { width:30px; height:30px; border:none; border-radius:8px; background:rgba(10,12,24,.72); color:#fff; font-size:.9rem; cursor:pointer; display:flex; align-items:center; justify-content:center; }
        .tile-tools button:hover { background:var(--brand); }
        .tile-tools .zoom-val { min-width:38px; padding:0 6px; height:30px; border-radius:8px; background:rgba(10,12,24,.72); color:#cfd3e6; font-size:.7rem; display:flex; align-items:center; justify-content:center; }
        .filmstrip .tile-tools { transform:scale(.85); transform-origin:top left; }

        /* Barra de controles */
        .controls { display:flex; align-items:center; justify-content:center; gap:10px; padding:14px; background:rgba(0,0,0,.3); flex-wrap:wrap; }
        .ctrl-group { position:relative; display:flex; align-items:flex-end; }
        .ctrl { width:52px; height:52px; border-radius:50%; border:none; background:var(--panel2); color:#fff; font-size:1.15rem; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:.15s; position:relative; }
        .ctrl:hover { filter:brightness(1.2); }
        .ctrl.off { background:#c0304a; }
        .ctrl.active { background:var(--brand); }
        .ctrl.hangup { background:#e02a44; width:60px; }
        .ctrl-caret { width:22px; height:22px; border-radius:50%; border:none; background:var(--panel2); color:#cfd3e6; font-size:.7rem; cursor:pointer; position:absolute; top:-4px; right:-4px; display:flex; align-items:center; justify-content:center; }
        .ctrl-caret:hover { background:var(--brand); color:#fff; }
        .ctrl-label { position:absolute; bottom:-18px; left:50%; transform:translateX(-50%); font-size:.62rem; color:#9aa2c0; white-space:nowrap; }
        .controls .ctrl { margin-bottom:16px; }

        /* Popover (menu de câmera / dispositivos) */
        .popover-menu { position:fixed; background:var(--panel); border:1px solid #33375a; border-radius:14px; padding:14px; width:300px; max-width:92vw; box-shadow:0 16px 50px rgba(0,0,0,.5); z-index:80; display:none; }
        .popover-menu h6 { font-size:.8rem; color:#9aa2c0; text-transform:uppercase; letter-spacing:.5px; margin:0 0 8px; }
        .popover-menu .mb-blk { margin-bottom:14px; }

        .toast-box { position:fixed; top:16px; left:50%; transform:translateX(-50%); z-index:90; }
        .toast-msg { background:var(--panel); border:1px solid #33375a; color:#e8eaf1; padding:10px 16px; border-radius:12px; margin-bottom:8px; font-size:.85rem; box-shadow:0 8px 24px rgba(0,0,0,.4); }

        /* Painel do admin (participantes + pedidos) */
        .side-panel { position:fixed; top:0; right:0; bottom:0; width:340px; max-width:88vw; background:var(--panel); box-shadow:-8px 0 30px rgba(0,0,0,.4); z-index:85; transform:translateX(100%); transition:transform .2s; display:flex; flex-direction:column; }
        .side-panel.open { transform:translateX(0); }
        .side-panel .sp-head { display:flex; align-items:center; justify-content:space-between; padding:14px 16px; border-bottom:1px solid #2a2d44; }
        .side-panel .sp-body { flex:1; overflow:auto; padding:12px 16px; }
        .sp-item { display:flex; align-items:center; gap:10px; background:var(--panel2); border-radius:10px; padding:9px 11px; margin-bottom:8px; }
        .sp-item .nm { flex:1; font-size:.85rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .sp-item .btn { padding:3px 8px; font-size:.75rem; }
        .sp-section-title { font-size:.72rem; text-transform:uppercase; letter-spacing:.5px; color:#9aa2c0; margin:6px 0 8px; }

        /* Espera de aprovação */
        #waiting { position:fixed; inset:0; display:none; align-items:center; justify-content:center; z-index:40; padding:20px; }
        #waiting .box { background:var(--panel); border-radius:18px; padding:34px 28px; text-align:center; max-width:380px; }
        #waiting .spin { width:52px; height:52px; border:4px solid #33375a; border-top-color:var(--brand); border-radius:50%; margin:0 auto 16px; animation:spin 1s linear infinite; }
        @keyframes spin { to { transform:rotate(360deg); } }

        .link-modal { position:fixed; inset:0; background:rgba(0,0,0,.6); z-index:70; display:none; align-items:center; justify-content:center; padding:20px; }
        .link-modal .inner { background:var(--panel); border-radius:16px; padding:24px; max-width:520px; width:100%; }
        .rec-item { background:var(--panel2); border-radius:10px; padding:10px 12px; margin-bottom:8px; display:flex; align-items:center; gap:10px; }

        /* Menu de reações (emojis) */
        .emoji-menu { position:fixed; background:var(--panel); border:1px solid #33375a; border-radius:999px; padding:8px 12px; display:none; gap:6px; z-index:80; box-shadow:0 12px 40px rgba(0,0,0,.5); }
        .emoji-menu.open { display:flex; }
        .emoji-menu button { border:none; background:transparent; font-size:1.5rem; cursor:pointer; width:42px; height:42px; border-radius:50%; transition:.12s; }
        .emoji-menu button:hover { background:var(--panel2); transform:scale(1.15); }

        /* Chuva de emojis */
        #emoji-rain { position:fixed; inset:0; pointer-events:none; z-index:88; overflow:hidden; }
        .rain-emoji { position:absolute; bottom:80px; font-size:2rem; animation:rainUp 3s ease-out forwards; will-change:transform,opacity; }
        @keyframes rainUp { 0%{ transform:translateY(0) scale(.6); opacity:0; } 12%{ opacity:1; } 100%{ transform:translateY(-70vh) scale(1.1); opacity:0; } }

        /* Mão levantada no tile */
        .tile .badge-hand { display:none; color:#ffd54a; }
        .tile.hand-up .badge-hand { display:flex; }
        .tile.hand-up { outline:2px solid #ffd54a; outline-offset:-2px; }
        .ctrl.hand-on { background:#e0a400; }

        /* Painel de mãos levantadas */
        .hands-panel { position:fixed; top:56px; right:12px; width:280px; max-width:86vw; background:var(--panel); border:1px solid #33375a; border-radius:14px; padding:12px 14px; z-index:82; display:none; box-shadow:0 12px 40px rgba(0,0,0,.5); }
        .hands-panel.open { display:block; }
        .hands-panel h6 { font-size:.8rem; color:#9aa2c0; text-transform:uppercase; letter-spacing:.5px; margin:0 0 10px; display:flex; justify-content:space-between; align-items:center; }
        .hand-row { display:flex; align-items:center; gap:9px; padding:7px 0; border-bottom:1px solid #26293f; font-size:.86rem; }
        .hand-row:last-child { border-bottom:none; }
        .hand-row .num { width:22px; height:22px; border-radius:50%; background:var(--brand); color:#fff; font-size:.72rem; display:flex; align-items:center; justify-content:center; flex:0 0 auto; }
        .hand-row .lower { margin-left:auto; }
        .hands-empty { color:#9aa2c0; font-size:.82rem; padding:6px 0; }

        /* ================= MOBILE ================= */
        @media (max-width:768px){
            .topbar { padding:8px 12px; gap:8px; }
            .topbar .title { font-size:.85rem; max-width:46vw; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
            .topbar .count { font-size:.72rem; }
            .rec-dot { font-size:.72rem; }
            .grid, .stage { gap:6px; padding:6px; }
            .stage-wrap.with-strip .filmstrip { height:96px; }
            .filmstrip { gap:6px; padding:6px; }
            .filmstrip .tile { width:150px; height:84px; }
            .tile-tools { top:6px; left:6px; gap:4px; }
            .tile-tools button { width:34px; height:34px; font-size:1rem; }
            .tile-tools .zoom-val { height:34px; }
            .controls { gap:8px; padding:10px 8px calc(10px + env(safe-area-inset-bottom)); flex-wrap:nowrap; overflow-x:auto; justify-content:flex-start; }
            .controls::-webkit-scrollbar { display:none; }
            .ctrl { width:46px; height:46px; font-size:1.05rem; flex:0 0 auto; }
            .ctrl.hangup { width:52px; }
            .ctrl-label { display:none; }
            .controls .ctrl { margin-bottom:0; }
            .tile .name { font-size:.7rem; padding:2px 7px; }
            .bg-grid { grid-template-columns:repeat(4,1fr); }
        }
        @media (max-width:768px) and (orientation:portrait){ .stage { grid-template-columns:1fr !important; } }
        @media (max-width:520px){
            #lobby { padding:12px; align-items:flex-start; }
            .lobby-card { padding:20px 16px; border-radius:16px; margin-top:16px; }
            .lobby-card h1 { font-size:1.05rem; }
            .link-modal .inner { padding:18px; }
        }
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
        <?php if ($visibility === 'private'): ?>
        <div class="private-badge"><i class="bi bi-shield-lock-fill"></i> Sala privada — entrada com aprovação</div>
        <?php endif; ?>
        <div class="sub">Ajuste sua câmera, microfone e o plano de fundo antes de entrar.</div>
        <div class="preview-wrap">
            <video id="preview" autoplay muted playsinline class="mirror"></video>
            <canvas id="preview-canvas" class="mirror hidden"></canvas>
        </div>

        <div class="lobby-toggles">
            <button type="button" class="toggle-btn" id="lb-mic" onclick="toggleLobby('mic')"><i class="bi bi-mic-fill"></i> Microfone</button>
            <button type="button" class="toggle-btn" id="lb-cam" onclick="toggleLobby('cam')"><i class="bi bi-camera-video-fill"></i> Câmera</button>
        </div>

        <div class="mb-blk" style="margin-bottom:12px;">
            <label class="form-label">Câmera</label>
            <select id="lb-cam-select" class="form-select" onchange="changeDevice('video', this.value)"></select>
        </div>
        <div class="mb-blk" style="margin-bottom:14px;">
            <label class="form-label">Microfone</label>
            <select id="lb-mic-select" class="form-select" onchange="changeDevice('audio', this.value)"></select>
        </div>

        <label class="form-label"><i class="bi bi-image"></i> Plano de fundo</label>
        <div class="bg-grid" id="lb-bg-grid"></div>

        <div class="mb-3">
            <label class="form-label">Seu nome</label>
            <input type="text" id="lb-name" class="form-control" placeholder="Como quer aparecer?" value="<?= $suggested ?>" maxlength="120">
        </div>
        <button class="btn-join" id="lb-join" onclick="enterRoom()"><i class="bi bi-box-arrow-in-right"></i> <span id="lb-join-text">Entrar na chamada</span></button>
        <div id="lb-error" class="text-danger small mt-2" style="display:none;"></div>
    </div>
</div>

<!-- ===================== ESPERA DE APROVAÇÃO ===================== -->
<div id="waiting">
    <div class="box">
        <div class="spin"></div>
        <h5>Aguardando aprovação</h5>
        <p class="text-secondary small mb-3">Um administrador da sala precisa autorizar sua entrada. Assim que aprovarem, você entra automaticamente.</p>
        <button class="btn btn-sm btn-outline-light" onclick="cancelWaiting()">Cancelar</button>
    </div>
</div>

<!-- ===================== CALL ===================== -->
<div id="call">
    <div class="topbar">
        <span class="title"><i class="bi bi-camera-video"></i> <?= $roomTitle ?></span>
        <span class="count" id="peer-count-wrap" onclick="onCountClick()"><i class="bi bi-people-fill"></i> <span id="peer-count">1</span><span class="badge-dot" id="req-dot">0</span></span>
        <span class="rec-dot" id="rec-indicator"><span class="dot"></span> Gravando <span id="rec-time">00:00</span></span>
    </div>
    <div class="stage-wrap" id="stage-wrap">
        <div class="stage" id="stage" style="display:none;"></div>
        <div class="grid" id="grid"></div>
        <div class="filmstrip" id="filmstrip" style="display:none;"></div>
    </div>
    <div class="controls">
        <div class="ctrl-group">
            <button class="ctrl" id="btn-mic" onclick="toggleMic()" title="Microfone"><i class="bi bi-mic-fill"></i><span class="ctrl-label">Mic</span></button>
        </div>
        <div class="ctrl-group">
            <button class="ctrl" id="btn-cam" onclick="toggleCam()" title="Câmera"><i class="bi bi-camera-video-fill"></i><span class="ctrl-label">Câmera</span></button>
            <button class="ctrl-caret" onclick="openCamMenu(event)" title="Opções de câmera e fundo"><i class="bi bi-chevron-up"></i></button>
        </div>
        <button class="ctrl" id="btn-screen" onclick="toggleScreen()" title="Compartilhar tela"><i class="bi bi-display"></i><span class="ctrl-label">Tela</span></button>
        <button class="ctrl" id="btn-react" onclick="openEmojiMenu(event)" title="Reagir"><i class="bi bi-emoji-smile"></i><span class="ctrl-label">Reagir</span></button>
        <button class="ctrl" id="btn-hand" onclick="toggleHand()" title="Levantar a mão"><i class="bi bi-hand-index-thumb"></i><span class="ctrl-label">Mão</span></button>
        <div class="ctrl-group">
            <button class="ctrl" id="btn-hands-list" onclick="toggleHandsPanel()" title="Quem levantou a mão"><i class="bi bi-list-ol"></i><span class="ctrl-label">Fila</span></button>
            <span class="badge-dot" id="hands-count-dot" style="position:absolute;top:-2px;right:-2px;background:#e0a400;border-radius:999px;padding:1px 6px;font-size:.65rem;display:none;"></span>
        </div>
        <?php if ($allowRec): ?>
        <button class="ctrl" id="btn-rec" onclick="toggleRecording()" title="Gravar"><i class="bi bi-record-circle"></i><span class="ctrl-label">Gravar</span></button>
        <?php endif; ?>
        <button class="ctrl" id="btn-copy" onclick="copyLink()" title="Copiar link"><i class="bi bi-link-45deg"></i><span class="ctrl-label">Link</span></button>
        <button class="ctrl hangup" onclick="hangup()" title="Sair"><i class="bi bi-telephone-x-fill"></i><span class="ctrl-label">Sair</span></button>
    </div>
</div>

<!-- Popover: câmera / dispositivos / fundo (dentro da call) -->
<div class="popover-menu" id="cam-menu">
    <div class="mb-blk">
        <h6>Câmera</h6>
        <select id="cm-cam-select" class="form-select" onchange="changeDevice('video', this.value)"></select>
    </div>
    <div class="mb-blk">
        <h6>Microfone</h6>
        <select id="cm-mic-select" class="form-select" onchange="changeDevice('audio', this.value)"></select>
    </div>
    <div>
        <h6>Plano de fundo</h6>
        <div class="bg-grid" id="cm-bg-grid"></div>
    </div>
</div>

<!-- Menu de reações (emojis) -->
<div class="emoji-menu" id="emoji-menu"></div>
<!-- Camada da chuva de emojis -->
<div id="emoji-rain"></div>

<!-- Painel: mãos levantadas (todos podem ver) -->
<div class="hands-panel" id="hands-panel">
    <h6>Mãos levantadas <button class="btn btn-sm btn-outline-light py-0 px-1" onclick="toggleHandsPanel(false)"><i class="bi bi-x-lg"></i></button></h6>
    <div id="hands-list"><div class="hands-empty">Ninguém levantou a mão ainda.</div></div>
</div>

<!-- Painel do admin: participantes + pedidos -->
<div class="side-panel" id="admin-panel">
    <div class="sp-head">
        <strong><i class="bi bi-people"></i> Participantes</strong>
        <button class="btn btn-sm btn-outline-light" onclick="toggleAdminPanel(false)"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="sp-body">
        <div id="sp-requests-block" class="hidden">
            <div class="sp-section-title">Pedidos de entrada</div>
            <div id="sp-requests"></div>
            <hr style="border-color:#2a2d44;">
        </div>
        <div class="sp-section-title">Na chamada (<span id="sp-count">0</span>)</div>
        <div id="sp-participants"></div>
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

<!-- MediaPipe Selfie Segmentation (planos de fundo) -->
<script src="https://cdn.jsdelivr.net/npm/@mediapipe/selfie_segmentation/selfie_segmentation.js" crossorigin="anonymous"></script>

<script>
const BASE = '<?= $base ?>';
const ROOM_TOKEN = '<?= $roomToken ?>';
const ICE_SERVERS = <?= $iceJson ?: '[]' ?>;
const BACKGROUNDS = <?= $bgJson ?: '[]' ?>;
const ROOM_VISIBILITY = '<?= $visibility ?>';
let isAdmin = <?= $isAdmin ? 'true' : 'false' ?>;

// ---- Identidade ----
const peerId = 'p' + Math.random().toString(36).slice(2, 10) + Date.now().toString(36).slice(-4);
const browserId = (function () {
    try {
        let b = localStorage.getItem('vc_browser_id');
        if (!b) { b = 'b' + Math.random().toString(36).slice(2, 12) + Date.now().toString(36).slice(-4); localStorage.setItem('vc_browser_id', b); }
        return b;
    } catch (e) { return 'b' + Math.random().toString(36).slice(2, 12); }
})();

// ---- Estado ----
let myName = '<?= $suggested ?>' || 'Convidado';
let rawStream = null;        // câmera/mic crus (do getUserMedia)
let localStream = null;      // stream publicado (pode ser o processado com fundo)
let screenStream = null;
const peers = new Map();
let micOn = true, camOn = true, sharing = false;
let polling = false, joined = false, waiting = false;
let curVideoDeviceId = null, curAudioDeviceId = null;

// ---- Preferência de fundo (persistida) ----
// bgMode: 'none' | 'blur' | 'image' ; bgImage: id do arquivo
let bgMode = 'none', bgImageId = null;
try {
    bgMode = localStorage.getItem('vc_bg_mode') || 'none';
    bgImageId = localStorage.getItem('vc_bg_image') || null;
} catch (e) {}
function saveBgPref() {
    try { localStorage.setItem('vc_bg_mode', bgMode); if (bgImageId) localStorage.setItem('vc_bg_image', bgImageId); else localStorage.removeItem('vc_bg_image'); } catch (e) {}
}

// ==========================================================
// PIPELINE DE FUNDO (MediaPipe Selfie Segmentation em canvas)
// ==========================================================
let segmenter = null, bgCanvas = null, bgCtx = null, bgProcessing = false;
let bgRafId = null, processedStream = null, bgImageEl = null;
const bgVideoEl = document.createElement('video'); // vídeo cru para alimentar o segmenter
bgVideoEl.autoplay = true; bgVideoEl.muted = true; bgVideoEl.playsInline = true;

function bgActive() { return bgMode === 'blur' || (bgMode === 'image' && bgImageId); }

async function ensureSegmenter() {
    if (segmenter || typeof SelfieSegmentation === 'undefined') return segmenter;
    segmenter = new SelfieSegmentation({ locateFile: (f) => `https://cdn.jsdelivr.net/npm/@mediapipe/selfie_segmentation/${f}` });
    segmenter.setOptions({ modelSelection: 1 });
    segmenter.onResults(onSegResults);
    return segmenter;
}

function onSegResults(results) {
    if (!bgCtx || !bgCanvas) return;
    const w = bgCanvas.width, h = bgCanvas.height;
    bgCtx.save();
    bgCtx.clearRect(0, 0, w, h);
    // Desenha a pessoa
    bgCtx.drawImage(results.image, 0, 0, w, h);
    // Mantém apenas a pessoa (máscara)
    bgCtx.globalCompositeOperation = 'destination-in';
    bgCtx.drawImage(results.segmentationMask, 0, 0, w, h);
    // Desenha o fundo atrás
    bgCtx.globalCompositeOperation = 'destination-over';
    if (bgMode === 'blur') {
        bgCtx.filter = 'blur(12px)';
        bgCtx.drawImage(results.image, 0, 0, w, h);
        bgCtx.filter = 'none';
    } else if (bgMode === 'image' && bgImageEl && bgImageEl.complete) {
        // cobre mantendo proporção
        const ir = bgImageEl.width / bgImageEl.height, cr = w / h;
        let dw = w, dh = h, dx = 0, dy = 0;
        if (ir > cr) { dh = h; dw = h * ir; dx = (w - dw) / 2; } else { dw = w; dh = w / ir; dy = (h - dh) / 2; }
        bgCtx.drawImage(bgImageEl, dx, dy, dw, dh);
    } else {
        bgCtx.fillStyle = '#000'; bgCtx.fillRect(0, 0, w, h);
    }
    bgCtx.restore();
}

async function startBgPipeline() {
    if (!rawStream) return null;
    const vtrack = rawStream.getVideoTracks()[0];
    if (!vtrack) return null;
    await ensureSegmenter();
    if (!segmenter) { toast('Fundo não suportado neste navegador.'); bgMode = 'none'; return null; }

    if (!bgCanvas) {
        bgCanvas = document.createElement('canvas');
        bgCtx = bgCanvas.getContext('2d');
    }
    // Com fundo, limita a 720p: mantém boa nitidez sem travar a CPU (o modelo
    // de segmentação é pesado; 1080p processado costuma engasgar).
    const settings = vtrack.getSettings();
    let cw = settings.width || 1280, ch = settings.height || 720;
    if (cw > 1280) { ch = Math.round(ch * (1280 / cw)); cw = 1280; }
    bgCanvas.width = cw;
    bgCanvas.height = ch;

    bgVideoEl.srcObject = new MediaStream([vtrack]);
    await bgVideoEl.play().catch(() => {});

    bgProcessing = true;
    const loop = async () => {
        if (!bgProcessing) return;
        if (bgVideoEl.readyState >= 2) {
            try { await segmenter.send({ image: bgVideoEl }); } catch (e) {}
        }
        bgRafId = requestAnimationFrame(loop);
    };
    loop();

    processedStream = bgCanvas.captureStream(30);
    return processedStream.getVideoTracks()[0];
}

function stopBgPipeline() {
    bgProcessing = false;
    if (bgRafId) cancelAnimationFrame(bgRafId);
    if (processedStream) { processedStream.getTracks().forEach(t => t.stop()); processedStream = null; }
}

function loadBgImage() {
    if (bgMode !== 'image' || !bgImageId) { bgImageEl = null; return; }
    const b = BACKGROUNDS.find(x => x.id === bgImageId);
    if (!b) return;
    bgImageEl = new Image();
    bgImageEl.crossOrigin = 'anonymous';
    bgImageEl.src = b.url;
}

// Reconstrói o localStream aplicando (ou não) o fundo, e troca a track nos peers.
async function rebuildLocalStream() {
    if (!rawStream) return;
    loadBgImage();

    let videoTrack;
    if (bgActive()) {
        stopBgPipeline();
        videoTrack = await startBgPipeline();
    } else {
        stopBgPipeline();
        videoTrack = rawStream.getVideoTracks()[0];
    }

    const audioTrack = rawStream.getAudioTracks()[0];
    const newStream = new MediaStream();
    if (videoTrack) newStream.addTrack(videoTrack);
    if (audioTrack) newStream.addTrack(audioTrack);
    localStream = newStream;

    // Respeita estados de mute
    if (audioTrack) audioTrack.enabled = micOn;
    if (videoTrack && !bgActive()) videoTrack.enabled = camOn;

    // Atualiza o tile próprio
    const selfV = document.querySelector('#tile-' + peerId + ' video');
    if (selfV) selfV.srcObject = localStream;

    // Substitui a track de vídeo publicada em cada peer (sem renegociar)
    if (joined && videoTrack) {
        peers.forEach(entry => {
            const sender = entry.pc.getSenders().find(s => s.track && s.track.kind === 'video' && (!screenStream || !screenStream.getTracks().includes(s.track)));
            if (sender) sender.replaceTrack(videoTrack).then(() => tuneSender(sender, videoTrack)).catch(() => {});
        });
    }
    // Preview do lobby
    updateLobbyPreview();
}

// ==========================================================
// LOBBY
// ==========================================================
let lobbyMic = true, lobbyCam = true;

// Restrições de vídeo em alta qualidade (HD, tende a 1080p quando a câmera permite).
const VIDEO_CONSTRAINTS = {
    width: { ideal: 1920, max: 1920 },
    height: { ideal: 1080, max: 1080 },
    frameRate: { ideal: 30, max: 30 },
};
const AUDIO_CONSTRAINTS = { echoCancellation: true, noiseSuppression: true, autoGainControl: true };

async function initPreview() {
    try {
        rawStream = await navigator.mediaDevices.getUserMedia({ video: VIDEO_CONSTRAINTS, audio: AUDIO_CONSTRAINTS });
        const vt = rawStream.getVideoTracks()[0];
        if (vt) curVideoDeviceId = vt.getSettings().deviceId;
        const at = rawStream.getAudioTracks()[0];
        if (at) curAudioDeviceId = at.getSettings().deviceId;
        await populateDevices();
        await rebuildLocalStream();
        syncLobbyButtons();
    } catch (e) {
        lobbyCam = false; camOn = false;
        showLobbyError('Não foi possível acessar câmera/microfone. Verifique as permissões. Você ainda pode entrar sem enviar vídeo.');
        syncLobbyButtons();
    }
    renderBgGrids();
}

function updateLobbyPreview() {
    const v = document.getElementById('preview');
    const c = document.getElementById('preview-canvas');
    if (!v || !c) return;
    if (bgActive() && bgCanvas) {
        // Mostra o canvas processado no preview.
        v.classList.add('hidden'); c.classList.remove('hidden');
        c.width = bgCanvas.width; c.height = bgCanvas.height;
        const cx = c.getContext('2d');
        const draw = () => {
            if (!bgActive()) return;
            cx.drawImage(bgCanvas, 0, 0, c.width, c.height);
            if (document.getElementById('lobby').style.display !== 'none') requestAnimationFrame(draw);
        };
        draw();
    } else {
        c.classList.add('hidden'); v.classList.remove('hidden');
        v.srcObject = localStream || rawStream;
    }
}

async function populateDevices() {
    let devices = [];
    try { devices = await navigator.mediaDevices.enumerateDevices(); } catch (e) { return; }
    const cams = devices.filter(d => d.kind === 'videoinput');
    const mics = devices.filter(d => d.kind === 'audioinput');
    const fill = (sel, list, cur, kindLabel) => {
        const el = document.getElementById(sel);
        if (!el) return;
        el.innerHTML = '';
        list.forEach((d, i) => {
            const o = document.createElement('option');
            o.value = d.deviceId;
            o.textContent = d.label || (kindLabel + ' ' + (i + 1));
            if (d.deviceId === cur) o.selected = true;
            el.appendChild(o);
        });
    };
    fill('lb-cam-select', cams, curVideoDeviceId, 'Câmera');
    fill('lb-mic-select', mics, curAudioDeviceId, 'Microfone');
    fill('cm-cam-select', cams, curVideoDeviceId, 'Câmera');
    fill('cm-mic-select', mics, curAudioDeviceId, 'Microfone');
}

// Troca de câmera/microfone (ao vivo).
async function changeDevice(kind, deviceId) {
    if (!deviceId) return;
    try {
        const constraints = kind === 'video'
            ? { video: Object.assign({ deviceId: { exact: deviceId } }, VIDEO_CONSTRAINTS), audio: false }
            : { audio: Object.assign({ deviceId: { exact: deviceId } }, AUDIO_CONSTRAINTS), video: false };
        const ns = await navigator.mediaDevices.getUserMedia(constraints);
        const newTrack = (kind === 'video' ? ns.getVideoTracks() : ns.getAudioTracks())[0];
        if (!newTrack) return;

        // Substitui a track crua correspondente
        const old = (kind === 'video' ? rawStream.getVideoTracks() : rawStream.getAudioTracks())[0];
        if (old) { rawStream.removeTrack(old); old.stop(); }
        rawStream.addTrack(newTrack);
        if (kind === 'video') curVideoDeviceId = deviceId; else curAudioDeviceId = deviceId;

        if (kind === 'audio') {
            // Áudio: troca direto nos senders e no localStream
            const oldA = localStream ? localStream.getAudioTracks()[0] : null;
            if (localStream && oldA) localStream.removeTrack(oldA);
            if (localStream) localStream.addTrack(newTrack);
            newTrack.enabled = micOn;
            if (joined) peers.forEach(entry => {
                const s = entry.pc.getSenders().find(x => x.track && x.track.kind === 'audio');
                if (s) s.replaceTrack(newTrack).catch(() => {});
            });
        } else {
            // Vídeo: reconstrói (reaplica fundo se ativo)
            await rebuildLocalStream();
        }
        await populateDevices();
        syncSelects(kind, deviceId);
    } catch (e) { toast('Não foi possível trocar o dispositivo.'); }
}
function syncSelects(kind, deviceId) {
    const ids = kind === 'video' ? ['lb-cam-select', 'cm-cam-select'] : ['lb-mic-select', 'cm-mic-select'];
    ids.forEach(id => { const el = document.getElementById(id); if (el) el.value = deviceId; });
}

// Grades de fundo (lobby + menu da call)
function renderBgGrids() { ['lb-bg-grid', 'cm-bg-grid'].forEach(renderBgGrid); }
function renderBgGrid(gridId) {
    const grid = document.getElementById(gridId);
    if (!grid) return;
    let html = '';
    html += bgOpt('none', 'Nenhum', '<i class="bi bi-slash-circle"></i>');
    html += bgOpt('blur', 'Desfoque', '<i class="bi bi-badge-hd"></i>', '', true);
    BACKGROUNDS.forEach(b => { html += bgOptImg(b); });
    grid.innerHTML = html;
}
function bgOpt(mode, label, icon, style, isBlur) {
    const active = (bgMode === mode) ? ' active' : '';
    return `<div class="bg-opt${active}" ${style ? 'style="' + style + '"' : ''} onclick="pickBg('${mode}',null)" title="${label}">${icon}<small>${label}</small></div>`;
}
function bgOptImg(b) {
    const active = (bgMode === 'image' && bgImageId === b.id) ? ' active' : '';
    return `<div class="bg-opt${active}" style="background-image:url('${b.url}')" onclick="pickBg('image','${b.id}')" title="${escapeHtml(b.label)}"><small>${escapeHtml(b.label)}</small></div>`;
}
async function pickBg(mode, imageId) {
    bgMode = mode; bgImageId = (mode === 'image') ? imageId : null;
    saveBgPref();
    renderBgGrids();
    await rebuildLocalStream();
}

function showLobbyError(msg) { const el = document.getElementById('lb-error'); el.textContent = msg; el.style.display = 'block'; }

function toggleLobby(kind) {
    if (kind === 'mic') { lobbyMic = !lobbyMic; if (rawStream) rawStream.getAudioTracks().forEach(t => t.enabled = lobbyMic); }
    else { lobbyCam = !lobbyCam; if (rawStream) rawStream.getVideoTracks().forEach(t => t.enabled = lobbyCam); if (localStream) localStream.getVideoTracks().forEach(t => t.enabled = lobbyCam); }
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
    try { await doJoin(false); }
    catch (e) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-box-arrow-in-right"></i> Entrar na chamada'; showLobbyError('Erro ao entrar na chamada. Tente novamente.'); }
}

async function doJoin(takeover) {
    const btn = document.getElementById('lb-join');
    const params = { peer_id: peerId, name: myName, browser_id: browserId };
    if (takeover) params.takeover = '1';
    const res = await fetch(`${BASE}/videocall/join/${ROOM_TOKEN}`, {
        method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: new URLSearchParams(params)
    }).then(r => r.json());

    if (res.duplicate) {
        btn.disabled = false; btn.innerHTML = '<i class="bi bi-box-arrow-in-right"></i> Entrar na chamada';
        if (confirm('Você já está nesta chamada em outra guia deste navegador.\n\nDeseja mover a chamada para esta aba? A outra guia será desconectada.')) {
            btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Movendo…'; await doJoin(true);
        }
        return;
    }
    // Sala privada: aguardando aprovação do admin.
    if (res.awaiting) { startWaiting(); return; }
    if (res.error) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-box-arrow-in-right"></i> Entrar na chamada'; showLobbyError(res.error); return; }

    isAdmin = !!res.is_admin;
    enterCall(res);
}

function enterCall(res) {
    document.getElementById('lobby').style.display = 'none';
    document.getElementById('waiting').style.display = 'none';
    document.getElementById('call').style.display = 'flex';
    joined = true; waiting = false;
    // Admin vê o contador clicável (abre o painel de participantes/pedidos).
    if (isAdmin) {
        document.getElementById('peer-count-wrap').classList.add('clickable');
        startAdminPolling();
    }
    addSelfTile();
    (res.peers || []).forEach(p => { ensurePeer(p.peer_id, p.name, true); });
    updateCount();
    startPolling();
    startNetworkMonitor();
}

// ---- Espera (sala privada) ----
let waitTimer = null;
function startWaiting() {
    waiting = true;
    document.getElementById('lobby').style.display = 'none';
    document.getElementById('waiting').style.display = 'flex';
    waitTimer = setInterval(async () => {
        try {
            const r = await fetch(`${BASE}/videocall/requestStatus/${ROOM_TOKEN}?peer_id=${peerId}`).then(x => x.json());
            if (r.status === 'admitted') { clearInterval(waitTimer); await doJoin(false); }
            else if (r.status === 'denied') { clearInterval(waitTimer); teardown({ icon: '🚫', text: 'Entrada recusada' }, 'Um administrador não autorizou sua entrada nesta chamada.'); }
        } catch (e) {}
    }, 1500);
}
function cancelWaiting() {
    if (waitTimer) clearInterval(waitTimer);
    waiting = false;
    document.getElementById('waiting').style.display = 'none';
    document.getElementById('lobby').style.display = 'flex';
    const btn = document.getElementById('lb-join');
    btn.disabled = false; btn.innerHTML = '<i class="bi bi-box-arrow-in-right"></i> Entrar na chamada';
}

// ==========================================================
// TILES / LAYOUT
// ==========================================================
function tileEl(id) { return document.getElementById('tile-' + id); }
const tileZoom = new Map();
const pinned = new Set();

function isScreenTile(id) { return id.endsWith('-screen'); }

function makeTile(id, name, opts = {}) {
    const div = document.createElement('div');
    div.className = 'tile' + (opts.self ? ' self' : '') + (opts.screen ? ' screen' : '');
    div.id = 'tile-' + id;
    div.dataset.tid = id;
    const initial = (name || 'C').trim().charAt(0).toUpperCase();
    const screen = !!opts.screen;
    // Zoom só existe em tiles de TELA. Fixar existe em ambos (câmera e tela).
    const zoomTools = screen
        ? `<button title="Diminuir zoom" onclick="zoomTile('${id}',-0.25)"><i class="bi bi-zoom-out"></i></button>
           <span class="zoom-val" id="zoom-${id}">100%</span>
           <button title="Aumentar zoom" onclick="zoomTile('${id}',0.25)"><i class="bi bi-zoom-in"></i></button>
           <button title="Zoom padrão" onclick="resetZoom('${id}')"><i class="bi bi-arrow-counterclockwise"></i></button>`
        : '';
    div.innerHTML =
        `<div class="vwrap"><video autoplay playsinline ${opts.self ? 'muted' : ''}></video></div>
         <div class="avatar"><span>${initial}</span></div>
         <div class="tile-tools">
            ${zoomTools}
            <button title="Fixar/desafixar" onclick="togglePin('${id}')"><i class="bi bi-pin-angle"></i></button>
         </div>
         <div class="badges">
            <div class="badge-ic badge-hand"><i class="bi bi-hand-index-thumb-fill"></i></div>
            <div class="badge-ic badge-pin"><i class="bi bi-pin-angle-fill"></i></div>
            <div class="badge-ic badge-mic"><i class="bi bi-mic-mute-fill"></i></div>
            <div class="badge-ic badge-cam"><i class="bi bi-camera-video-off-fill"></i></div>
         </div>
         <div class="name">${escapeHtml(name)}${screen ? ' (tela)' : ''}</div>`;
    document.getElementById('grid').appendChild(div);
    tileZoom.set(id, 1);
    layoutGrid();
    return div;
}

// Zoom (apenas tiles de tela). Visualização local de quem clica.
function applyZoom(id) {
    const t = tileEl(id); if (!t) return;
    const v = t.querySelector('video'); if (!v) return;
    const z = tileZoom.get(id) || 1;
    const mirror = t.classList.contains('self') ? -1 : 1;
    v.style.transform = `scaleX(${mirror}) scale(${z})`;
    const label = document.getElementById('zoom-' + id);
    if (label) label.textContent = Math.round(z * 100) + '%';
}
function zoomTile(id, delta) {
    if (!isScreenTile(id)) return; // zoom só em tela
    let z = (tileZoom.get(id) || 1) + delta;
    z = Math.max(1, Math.min(4, Math.round(z * 100) / 100));
    tileZoom.set(id, z); applyZoom(id);
}
function resetZoom(id) { tileZoom.set(id, 1); applyZoom(id); }

function togglePin(id) { if (pinned.has(id)) pinned.delete(id); else pinned.add(id); layoutGrid(); }

function layoutGrid() {
    const grid = document.getElementById('grid');
    const stage = document.getElementById('stage');
    const strip = document.getElementById('filmstrip');
    const wrap = document.getElementById('stage-wrap');
    const allTiles = Array.from(document.querySelectorAll('#stage .tile, #grid .tile, #filmstrip .tile'));

    // Destaque: telas compartilhadas + tiles fixados (câmera ou tela).
    const featured = allTiles.filter(t => isScreenTile(t.dataset.tid) || pinned.has(t.dataset.tid));
    const others = allTiles.filter(t => !featured.includes(t));

    if (featured.length === 0) {
        stage.style.display = 'none'; strip.style.display = 'none'; wrap.classList.remove('with-strip');
        grid.style.display = 'grid';
        allTiles.forEach(t => grid.appendChild(t));
        const n = allTiles.length || 1;
        grid.setAttribute('data-n', Math.min(n, 16));
        // 1 participante => tela cheia (1 coluna, 1 linha).
        let cols = 1;
        if (n === 1) cols = 1;
        else if (n === 2) cols = 2;
        else if (n <= 4) cols = 2;
        else if (n <= 9) cols = 3;
        else cols = 4;
        if (window.innerWidth <= 700) cols = (n === 1) ? 1 : 2;
        grid.style.gridTemplateColumns = `repeat(${cols}, 1fr)`;
        grid.style.gridTemplateRows = `repeat(${Math.ceil(n / cols)}, 1fr)`;
    } else {
        grid.style.display = 'none'; stage.style.display = 'grid';
        featured.forEach(t => stage.appendChild(t));
        const fn = featured.length;
        const scols = fn === 1 ? 1 : 2;
        stage.style.gridTemplateColumns = `repeat(${scols}, 1fr)`;
        stage.style.gridTemplateRows = `repeat(${Math.ceil(fn / scols)}, 1fr)`;
        if (others.length) { strip.style.display = 'flex'; wrap.classList.add('with-strip'); others.forEach(t => strip.appendChild(t)); }
        else { strip.style.display = 'none'; wrap.classList.remove('with-strip'); }
    }
    allTiles.forEach(t => applyZoom(t.dataset.tid));
}

function addSelfTile() {
    const div = makeTile(peerId, myName + ' (você)', { self: true });
    div.querySelector('video').srcObject = localStream;
    div.classList.toggle('mic-off', !micOn);
    div.classList.toggle('cam-off', !camOn);
}
function removeTile(id) { const t = tileEl(id); if (t) { t.remove(); tileZoom.delete(id); pinned.delete(id); layoutGrid(); } }
function updateCount() { document.getElementById('peer-count').textContent = (peers.size + 1); }

// ==========================================================
// WebRTC mesh
// ==========================================================
// ---- Qualidade adaptativa (estilo Meet) ----
// Níveis do melhor para o pior. scaleDown reduz a resolução enviada.
const QUALITY_LEVELS = [
    { name: '1080p', maxBitrate: 2500000, scaleDown: 1,   maxFramerate: 30 },
    { name: '720p',  maxBitrate: 1200000, scaleDown: 1.5, maxFramerate: 30 },
    { name: '480p',  maxBitrate: 600000,  scaleDown: 2.5, maxFramerate: 25 },
    { name: '360p',  maxBitrate: 300000,  scaleDown: 3.5, maxFramerate: 20 },
];
let qualityIndex = 0;          // começa no melhor
let autoCamOff = false;        // câmera desligada AUTOMATICAMENTE por rede ruim
let camOffByUser = false;      // usuário desligou manualmente (não religa sozinho)

// Aplica o nível atual de qualidade a um sender de vídeo (câmera; a tela mantém detalhe).
async function tuneSender(sender, track) {
    if (!sender || !track || track.kind !== 'video') return;
    const isScreen = screenStream && screenStream.getTracks().includes(track);
    try {
        const params = sender.getParameters();
        if (!params.encodings || !params.encodings.length) params.encodings = [{}];
        if (isScreen) {
            params.encodings[0].maxBitrate = 2500000;
            delete params.encodings[0].scaleResolutionDownBy;
            params.degradationPreference = 'maintain-resolution';
        } else {
            const lv = QUALITY_LEVELS[qualityIndex];
            params.encodings[0].maxBitrate = lv.maxBitrate;
            params.encodings[0].maxFramerate = lv.maxFramerate;
            params.encodings[0].scaleResolutionDownBy = lv.scaleDown;
            params.degradationPreference = 'balanced';
        }
        await sender.setParameters(params);
    } catch (e) { /* nem todo navegador aceita; ignora */ }
}

// Reaplica o nível atual em todos os senders de câmera.
function applyQualityToAll() {
    peers.forEach(entry => {
        entry.pc.getSenders().forEach(s => {
            if (s.track && s.track.kind === 'video' && (!screenStream || !screenStream.getTracks().includes(s.track))) tuneSender(s, s.track);
        });
    });
}

// ---- Monitor de rede: sobe/baixa qualidade e desliga a câmera se travar ----
let netTimer = null;
let lastStats = { ts: 0, packetsSent: 0, packetsLost: 0 };
let goodStreak = 0, badStreak = 0;

function startNetworkMonitor() {
    if (netTimer) return;
    netTimer = setInterval(monitorNetwork, 4000);
}
function stopNetworkMonitor() { if (netTimer) { clearInterval(netTimer); netTimer = null; } }

async function monitorNetwork() {
    if (!joined || peers.size === 0) return;
    // Agrega estatísticas de envio de vídeo de todas as conexões.
    let packetsSent = 0, packetsLost = 0, nack = 0;
    for (const entry of peers.values()) {
        try {
            const stats = await entry.pc.getStats();
            stats.forEach(r => {
                if (r.type === 'outbound-rtp' && r.kind === 'video') { packetsSent += (r.packetsSent || 0); nack += (r.nackCount || 0); }
                if (r.type === 'remote-inbound-rtp' && r.kind === 'video') { packetsLost += (r.packetsLost || 0); }
            });
        } catch (e) {}
    }
    const now = Date.now();
    if (lastStats.ts) {
        const dSent = packetsSent - lastStats.packetsSent;
        const dLost = packetsLost - lastStats.packetsLost;
        const total = dSent + dLost;
        const lossRate = total > 0 ? (dLost / total) : 0;

        if (lossRate > 0.08) { badStreak++; goodStreak = 0; }        // >8% perda = ruim
        else if (lossRate < 0.02) { goodStreak++; badStreak = 0; }   // <2% = bom
        else { goodStreak = 0; badStreak = 0; }                      // zona neutra

        // Rede ruim persistente: baixa a qualidade em degraus.
        if (badStreak >= 1 && qualityIndex < QUALITY_LEVELS.length - 1) {
            qualityIndex++; applyQualityToAll(); badStreak = 0;
            toast('Conexão instável: qualidade reduzida para ' + QUALITY_LEVELS[qualityIndex].name + '.');
        }
        // Já no pior nível e ainda ruim: desliga a câmera automaticamente.
        else if (badStreak >= 2 && qualityIndex >= QUALITY_LEVELS.length - 1 && camOn && !camOffByUser) {
            autoDisableCam();
            badStreak = 0;
        }
        // Rede boa por um tempo: sobe a qualidade de volta.
        if (goodStreak >= 3) {
            if (autoCamOff) { autoEnableCam(); goodStreak = 0; }
            else if (qualityIndex > 0) { qualityIndex--; applyQualityToAll(); goodStreak = 0; toast('Conexão melhorou: qualidade em ' + QUALITY_LEVELS[qualityIndex].name + '.'); }
        }
    }
    lastStats = { ts: now, packetsSent, packetsLost };
}

// Desliga a câmera por causa da rede (avisa e lembra que foi automático).
function autoDisableCam() {
    if (!camOn) return;
    autoCamOff = true;
    camOn = false;
    if (localStream) localStream.getVideoTracks().forEach(t => t.enabled = false);
    if (rawStream) rawStream.getVideoTracks().forEach(t => t.enabled = false);
    const b = document.getElementById('btn-cam');
    b.classList.add('off');
    b.innerHTML = '<i class="bi bi-camera-video-off-fill"></i><span class="ctrl-label">Câmera</span>';
    tileEl(peerId)?.classList.add('cam-off');
    broadcast('media', { micMuted: !micOn, camOff: true });
    toast('Sua câmera foi desligada por causa da conexão. Ela volta sozinha quando a internet melhorar.');
}
// Religa a câmera quando a rede se recupera (só se foi desligada automaticamente).
function autoEnableCam() {
    if (!autoCamOff) return;
    autoCamOff = false;
    camOn = true;
    // Volta subindo a qualidade gradualmente (do pior para melhorar aos poucos).
    if (localStream) localStream.getVideoTracks().forEach(t => t.enabled = true);
    if (rawStream) rawStream.getVideoTracks().forEach(t => t.enabled = true);
    const b = document.getElementById('btn-cam');
    b.classList.remove('off');
    b.innerHTML = '<i class="bi bi-camera-video-fill"></i><span class="ctrl-label">Câmera</span>';
    tileEl(peerId)?.classList.remove('cam-off');
    broadcast('media', { micMuted: !micOn, camOff: false });
    toast('Sua internet melhorou: câmera religada.');
}

function ensurePeer(remoteId, name, initiator) {
    if (remoteId === peerId || peers.has(remoteId)) return peers.get(remoteId);
    const pc = new RTCPeerConnection({ iceServers: ICE_SERVERS });
    const entry = { pc, name, polite: peerId < remoteId, makingOffer: false, tile: null, screenTile: null, pendingIce: [], hasCam: false };
    peers.set(remoteId, entry);

    if (localStream) localStream.getTracks().forEach(t => { const s = pc.addTrack(t, localStream); tuneSender(s, t); });
    if (screenStream) screenStream.getVideoTracks().forEach(t => { const s = pc.addTrack(t, screenStream); if (t) t.contentHint = 'detail'; tuneSender(s, t); });

    pc.onicecandidate = (e) => { if (e.candidate) sendSignal(remoteId, 'ice', e.candidate); };
    pc.ontrack = (e) => {
        const stream = e.streams[0];
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
        try { entry.makingOffer = true; await pc.setLocalDescription(await pc.createOffer()); sendSignal(remoteId, 'offer', pc.localDescription); }
        catch (err) { console.warn('negotiation', err); } finally { entry.makingOffer = false; }
    };
    if (initiator) pc.onnegotiationneeded();
    return entry;
}

async function handleSignal(sig) {
    const from = sig.from;
    if (!from) return;

    // Moderação: só o admin reage a 'request'; o solicitante reage a admit/deny.
    if (sig.kind === 'request') { if (isAdmin) { bumpReqDot(); refreshAdminPanel(); } return; }
    if (sig.kind === 'admit' || sig.kind === 'deny') return; // tratados na tela de espera

    if (from === peerId) return;
    if (sig.kind === 'join') {
        if (!peers.has(from)) ensurePeer(from, (sig.payload && sig.payload.name) || 'Convidado', false);
        // Se eu estou com a mão levantada, reavise a sala para o recém-chegado ver.
        if (handUp) setTimeout(() => sendSignal(from, 'hand', { up: true, name: myName }), 800);
        return;
    }
    if (sig.kind === 'kick') { onKicked(); return; }
    if (sig.kind === 'end') { teardown({ icon: '📴', text: 'Chamada encerrada', }, 'O organizador encerrou a chamada.'); return; }
    if (sig.kind === 'leave') { dropPeer(from); return; }
    if (sig.kind === 'media') {
        const t = tileEl(from);
        if (t && sig.payload) { t.classList.toggle('mic-off', !!sig.payload.micMuted); t.classList.toggle('cam-off', !!sig.payload.camOff); }
        return;
    }
    if (sig.kind === 'screen') { if (sig.payload && sig.payload.stop) removeTile(from + '-screen'); return; }
    if (sig.kind === 'reaction') { if (sig.payload && sig.payload.emoji) spawnEmojiRain(sig.payload.emoji); return; }
    if (sig.kind === 'hand') {
        // Admin pediu para EU baixar a mão (sinal direcionado com force).
        if (sig.payload && sig.payload.force && sig.to === peerId) { if (handUp) toggleHand(); return; }
        onRemoteHand(from, sig.payload);
        return;
    }

    const entry = peers.get(from) || ensurePeer(from, 'Convidado', false);
    const pc = entry.pc;
    try {
        if (sig.kind === 'offer') {
            const collision = entry.makingOffer || pc.signalingState !== 'stable';
            if (collision && !entry.polite) return;
            await pc.setRemoteDescription(new RTCSessionDescription(sig.payload));
            await drainIce(entry);
            await pc.setLocalDescription(await pc.createAnswer());
            sendSignal(from, 'answer', pc.localDescription);
        } else if (sig.kind === 'answer') {
            await pc.setRemoteDescription(new RTCSessionDescription(sig.payload)); await drainIce(entry);
        } else if (sig.kind === 'ice') {
            if (pc.remoteDescription && pc.remoteDescription.type) await pc.addIceCandidate(new RTCIceCandidate(sig.payload));
            else entry.pendingIce.push(sig.payload);
        }
    } catch (err) { console.warn('signal', sig.kind, err); }
}

async function drainIce(entry) { while (entry.pendingIce.length) { try { await entry.pc.addIceCandidate(new RTCIceCandidate(entry.pendingIce.shift())); } catch (e) {} } }
function dropPeer(id) { const e = peers.get(id); if (e) { try { e.pc.close(); } catch (x) {} } peers.delete(id); removeTile(id); removeTile(id + '-screen'); if (raisedHands.delete(id)) renderHands(); updateCount(); }
function sendSignal(to, kind, payload) {
    fetch(`${BASE}/videocall/signal/${ROOM_TOKEN}`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ from: peerId, to: to || '', kind, payload }) }).catch(() => {});
}
function broadcast(kind, payload) { sendSignal('', kind, payload); }

async function startPolling() {
    if (polling) return; polling = true;
    while (joined) {
        try {
            const res = await fetch(`${BASE}/videocall/poll/${ROOM_TOKEN}?peer_id=${peerId}`).then(r => r.json());
            if (res.error) { await sleep(1500); continue; }
            (res.signals || []).forEach(handleSignal);
            reconcilePeers(res.peers || []);
        } catch (e) { await sleep(1500); }
    }
}
function reconcilePeers(activeList) {
    const active = new Set(activeList.map(p => p.peer_id));
    peers.forEach((_, id) => { if (!active.has(id)) dropPeer(id); });
    activeList.forEach(p => { if (p.peer_id !== peerId && !peers.has(p.peer_id)) ensurePeer(p.peer_id, p.name, false); });
    updateCount();
}

// ---- Controles de mídia ----
function toggleMic() {
    micOn = !micOn;
    if (localStream) localStream.getAudioTracks().forEach(t => t.enabled = micOn);
    if (rawStream) rawStream.getAudioTracks().forEach(t => t.enabled = micOn);
    const b = document.getElementById('btn-mic');
    b.classList.toggle('off', !micOn);
    b.innerHTML = (micOn ? '<i class="bi bi-mic-fill"></i>' : '<i class="bi bi-mic-mute-fill"></i>') + '<span class="ctrl-label">Mic</span>';
    tileEl(peerId)?.classList.toggle('mic-off', !micOn);
    broadcast('media', { micMuted: !micOn, camOff: !camOn });
}
function toggleCam() {
    camOn = !camOn;
    // Marca intenção manual: se o usuário desligou, o automático não religa;
    // se ligou de volta, limpa o estado "desligado pela rede".
    camOffByUser = !camOn;
    autoCamOff = false;
    if (localStream) localStream.getVideoTracks().forEach(t => t.enabled = camOn);
    if (rawStream) rawStream.getVideoTracks().forEach(t => t.enabled = camOn);
    const b = document.getElementById('btn-cam');
    b.classList.toggle('off', !camOn);
    b.innerHTML = (camOn ? '<i class="bi bi-camera-video-fill"></i>' : '<i class="bi bi-camera-video-off-fill"></i>') + '<span class="ctrl-label">Câmera</span>';
    tileEl(peerId)?.classList.toggle('cam-off', !camOn);
    broadcast('media', { micMuted: !micOn, camOff: !camOn });
}

async function toggleScreen() {
    if (sharing) { stopScreen(); return; }
    try { screenStream = await navigator.mediaDevices.getDisplayMedia({ video: true, audio: false }); } catch (e) { return; }
    sharing = true;
    document.getElementById('btn-screen').classList.add('active');
    const selfScreen = makeTile(peerId + '-screen', myName + ' (sua tela)', { screen: true });
    selfScreen.querySelector('video').srcObject = screenStream;
    const screenTrack = screenStream.getVideoTracks()[0];
    peers.forEach((entry) => { entry.pc.addTrack(screenTrack, screenStream); });
    screenTrack.onended = () => stopScreen();
}
function stopScreen() {
    if (!sharing) return; sharing = false;
    document.getElementById('btn-screen').classList.remove('active');
    if (screenStream) {
        screenStream.getTracks().forEach(t => { t.stop(); peers.forEach((entry) => { const s = entry.pc.getSenders().find(x => x.track === t); if (s) { try { entry.pc.removeTrack(s); } catch (e) {} } }); });
    }
    removeTile(peerId + '-screen'); screenStream = null; broadcast('screen', { stop: true });
}

// Menu de câmera / dispositivos / fundo (popover)
function openCamMenu(ev) {
    ev.stopPropagation();
    const menu = document.getElementById('cam-menu');
    const open = menu.style.display === 'block';
    if (open) { menu.style.display = 'none'; return; }
    populateDevices(); renderBgGrids();
    menu.style.display = 'block';
    const r = ev.currentTarget.getBoundingClientRect();
    let left = r.left - 130; if (left < 8) left = 8;
    if (left + 300 > window.innerWidth) left = window.innerWidth - 308;
    menu.style.left = left + 'px';
    menu.style.bottom = (window.innerHeight - r.top + 10) + 'px';
    menu.style.top = 'auto';
}
document.addEventListener('click', (e) => {
    const menu = document.getElementById('cam-menu');
    if (menu && menu.style.display === 'block' && !menu.contains(e.target) && !e.target.closest('.ctrl-caret')) menu.style.display = 'none';
});

// ==========================================================
// PAINEL DO ADMIN (participantes + pedidos)
// ==========================================================
let adminTimer = null, pendingReqCount = 0;
function onCountClick() { if (isAdmin) toggleAdminPanel(); }
function toggleAdminPanel(force) {
    const p = document.getElementById('admin-panel');
    const open = (force === undefined) ? !p.classList.contains('open') : force;
    p.classList.toggle('open', open);
    if (open) refreshAdminPanel();
}
function bumpReqDot() { document.getElementById('peer-count-wrap').classList.add('has-req'); }
function startAdminPolling() {
    if (adminTimer) return;
    // Polling curto (2s) para o admin ver os pedidos rápido.
    adminTimer = setInterval(refreshAdminPanel, 2000);
    refreshAdminPanel();
}

let knownReqPeers = new Set();
function notifyNewRequests(requests) {
    // Toca som + toast chamativo apenas para pedidos novos.
    const current = new Set(requests.map(q => q.peer_id));
    let novos = requests.filter(q => !knownReqPeers.has(q.peer_id));
    if (novos.length) {
        beep();
        novos.forEach(q => toast('✋ ' + q.name + ' pediu para entrar na chamada.'));
        // Destaca visualmente o contador e abre o painel na primeira vez.
        flashCountButton();
        if (!document.getElementById('admin-panel').classList.contains('open')) toggleAdminPanel(true);
    }
    knownReqPeers = current;
}
function flashCountButton() {
    const el = document.getElementById('peer-count-wrap');
    el.style.transition = 'background .2s'; el.style.background = '#e0a400'; el.style.borderRadius = '8px'; el.style.padding = '2px 6px';
    setTimeout(() => { el.style.background = ''; }, 1200);
}
// Bip curto via WebAudio (sem arquivo).
let audioCtx = null;
function beep() {
    try {
        audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
        const o = audioCtx.createOscillator(), g = audioCtx.createGain();
        o.connect(g); g.connect(audioCtx.destination);
        o.type = 'sine'; o.frequency.value = 880; g.gain.value = 0.08;
        o.start(); o.frequency.setValueAtTime(660, audioCtx.currentTime + 0.12);
        o.stop(audioCtx.currentTime + 0.24);
    } catch (e) {}
}

async function refreshAdminPanel() {
    if (!isAdmin || !joined) return;
    try {
        const r = await fetch(`${BASE}/videocall/roster/${ROOM_TOKEN}`).then(x => x.json());
        if (r.error) return;
        const requests = r.requests || [];
        pendingReqCount = requests.length;
        const dot = document.getElementById('req-dot');
        dot.textContent = pendingReqCount;
        document.getElementById('peer-count-wrap').classList.toggle('has-req', pendingReqCount > 0);

        notifyNewRequests(requests);

        const reqBlock = document.getElementById('sp-requests-block');
        const reqBox = document.getElementById('sp-requests');
        if (pendingReqCount > 0) {
            reqBlock.classList.remove('hidden');
            reqBox.innerHTML = requests.map(q =>
                `<div class="sp-item" id="req-${q.peer_id}"><span class="nm">✋ ${escapeHtml(q.name)}</span>
                  <button class="btn btn-success btn-sm" onclick="admit('${q.peer_id}')">Admitir</button>
                  <button class="btn btn-outline-danger btn-sm" onclick="deny('${q.peer_id}')">Recusar</button></div>`).join('');
        } else { reqBlock.classList.add('hidden'); reqBox.innerHTML = ''; }

        document.getElementById('sp-count').textContent = (r.participants || []).length;
        document.getElementById('sp-participants').innerHTML = (r.participants || []).map(p =>
            `<div class="sp-item"><span class="nm">${escapeHtml(p.name)}${p.role === 'host' ? ' <small class="text-muted">(host)</small>' : ''}</span></div>`).join('');
    } catch (e) {}
}

// Feedback imediato: remove o item da lista na hora e não espera o refresh.
function markDecided(pid) {
    const row = document.getElementById('req-' + pid);
    if (row) { row.querySelectorAll('button').forEach(b => b.disabled = true); row.style.opacity = '.5'; setTimeout(() => row.remove(), 400); }
    knownReqPeers.delete(pid);
}
async function admit(pid) {
    markDecided(pid);
    try { await fetch(`${BASE}/videocall/admit/${ROOM_TOKEN}`, { method: 'POST', body: new URLSearchParams({ peer_id: pid }) }); toast('Entrada autorizada.'); }
    catch (e) { toast('Erro ao autorizar.'); }
    refreshAdminPanel();
}
async function deny(pid) {
    markDecided(pid);
    try { await fetch(`${BASE}/videocall/deny/${ROOM_TOKEN}`, { method: 'POST', body: new URLSearchParams({ peer_id: pid }) }); }
    catch (e) {}
    refreshAdminPanel();
}

// ==========================================================
// REAÇÕES (EMOJIS) E LEVANTAR A MÃO
// ==========================================================
const REACTION_EMOJIS = ['👍', '❤️', '😂', '🎉', '👏', '😮'];

function openEmojiMenu(ev) {
    ev.stopPropagation();
    const menu = document.getElementById('emoji-menu');
    if (menu.classList.contains('open')) { menu.classList.remove('open'); return; }
    if (!menu.dataset.built) {
        menu.innerHTML = REACTION_EMOJIS.map(e => `<button onclick="sendReaction('${e}')">${e}</button>`).join('');
        menu.dataset.built = '1';
    }
    menu.classList.add('open');
    const r = ev.currentTarget.getBoundingClientRect();
    const mw = menu.offsetWidth || 300;
    let left = r.left + r.width / 2 - mw / 2;
    if (left < 8) left = 8;
    if (left + mw > window.innerWidth) left = window.innerWidth - mw - 8;
    menu.style.left = left + 'px';
    menu.style.bottom = (window.innerHeight - r.top + 10) + 'px';
}
document.addEventListener('click', (e) => {
    const menu = document.getElementById('emoji-menu');
    if (menu && menu.classList.contains('open') && !menu.contains(e.target) && !e.target.closest('#btn-react')) menu.classList.remove('open');
});

function sendReaction(emoji) {
    document.getElementById('emoji-menu').classList.remove('open');
    spawnEmojiRain(emoji);                 // mostra localmente
    broadcast('reaction', { emoji });      // e para todos
}

// Chuva de emojis (poucos, subindo e sumindo).
function spawnEmojiRain(emoji) {
    const layer = document.getElementById('emoji-rain');
    if (!layer) return;
    const count = 6;
    for (let i = 0; i < count; i++) {
        const el = document.createElement('div');
        el.className = 'rain-emoji';
        el.textContent = emoji;
        el.style.left = (10 + Math.random() * 80) + 'vw';
        el.style.fontSize = (1.4 + Math.random() * 1.4) + 'rem';
        el.style.animationDelay = (Math.random() * 0.5) + 's';
        layer.appendChild(el);
        setTimeout(() => el.remove(), 3600);
    }
}

// ---- Levantar a mão ----
let handUp = false;
// ordem: peerId -> { name, ts }
const raisedHands = new Map();

function toggleHand() {
    handUp = !handUp;
    const btn = document.getElementById('btn-hand');
    btn.classList.toggle('hand-on', handUp);
    tileEl(peerId)?.classList.toggle('hand-up', handUp);
    if (handUp) raisedHands.set(peerId, { name: myName + ' (você)', ts: Date.now() });
    else raisedHands.delete(peerId);
    broadcast('hand', { up: handUp, name: myName });
    renderHands();
    if (handUp) toast('Você levantou a mão.');
}

function onRemoteHand(from, payload) {
    const up = !!(payload && payload.up);
    const name = (payload && payload.name) || 'Convidado';
    tileEl(from)?.classList.toggle('hand-up', up);
    if (up) { if (!raisedHands.has(from)) raisedHands.set(from, { name, ts: Date.now() }); }
    else raisedHands.delete(from);
    renderHands();
}

function renderHands() {
    // Ordena por horário em que levantou (fila).
    const ordered = Array.from(raisedHands.entries()).sort((a, b) => a[1].ts - b[1].ts);
    const box = document.getElementById('hands-list');
    if (!ordered.length) {
        box.innerHTML = '<div class="hands-empty">Ninguém levantou a mão ainda.</div>';
    } else {
        box.innerHTML = ordered.map(([pid, h], i) => {
            const canLower = (pid === peerId) || isAdmin;
            const lowerBtn = canLower ? `<button class="btn btn-sm btn-outline-light py-0 px-1 lower" onclick="lowerHand('${pid}')" title="Baixar a mão"><i class="bi bi-hand-index-thumb"></i></button>` : '';
            return `<div class="hand-row"><span class="num">${i + 1}</span><span>${escapeHtml(h.name)}</span>${lowerBtn}</div>`;
        }).join('');
    }
    // Badge com a contagem no botão da fila.
    const dot = document.getElementById('hands-count-dot');
    if (ordered.length) { dot.style.display = 'inline-block'; dot.textContent = ordered.length; }
    else dot.style.display = 'none';
}

// Baixar a mão (a própria, ou qualquer uma se for admin).
function lowerHand(pid) {
    if (pid === peerId) {
        if (handUp) toggleHand(); // reaproveita: desliga e avisa a sala
        return;
    }
    if (!isAdmin) return;
    raisedHands.delete(pid);
    tileEl(pid)?.classList.remove('hand-up');
    renderHands();
    // Pede para aquele peer baixar a própria mão.
    sendSignal(pid, 'hand', { up: false, name: '', force: true });
}

function toggleHandsPanel(force) {
    const p = document.getElementById('hands-panel');
    const open = (force === undefined) ? !p.classList.contains('open') : force;
    p.classList.toggle('open', open);
    if (open) renderHands();
}

// ==========================================================
// GRAVAÇÃO
// ==========================================================
let mediaRecorder = null, recordedChunks = [], recStartTs = 0, recTimer = null;
function buildRecordingStream() {
    const mixed = new MediaStream();
    if (localStream) localStream.getTracks().forEach(t => mixed.addTrack(t));
    if (screenStream) screenStream.getVideoTracks().forEach(t => mixed.addTrack(t));
    peers.forEach(entry => { const remote = entry.tile?.querySelector('video')?.srcObject; if (remote) remote.getAudioTracks().forEach(t => mixed.addTrack(t)); });
    return mixed;
}
function toggleRecording() { if (mediaRecorder && mediaRecorder.state !== 'inactive') { stopRecording(); return; } startRecording(); }
function startRecording() {
    let mime = 'video/webm;codecs=vp9,opus';
    if (!MediaRecorder.isTypeSupported(mime)) mime = 'video/webm;codecs=vp8,opus';
    if (!MediaRecorder.isTypeSupported(mime)) mime = 'video/webm';
    try { recordedChunks = []; mediaRecorder = new MediaRecorder(buildRecordingStream(), { mimeType: mime }); }
    catch (e) { toast('Este navegador não suporta gravação.'); return; }
    mediaRecorder.ondataavailable = (e) => { if (e.data && e.data.size) recordedChunks.push(e.data); };
    mediaRecorder.onstop = uploadRecording;
    mediaRecorder.start(1000);
    recStartTs = Date.now();
    document.getElementById('btn-rec').classList.add('off');
    document.getElementById('rec-indicator').style.display = 'flex';
    recTimer = setInterval(() => { const s = Math.floor((Date.now() - recStartTs) / 1000); document.getElementById('rec-time').textContent = String(Math.floor(s / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0'); }, 1000);
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
    fd.append('recording', blob, 'gravacao.webm'); fd.append('duration_sec', durationSec); fd.append('recorded_by_name', myName);
    try {
        const res = await fetch(`${BASE}/videocall/upload/${ROOM_TOKEN}`, { method: 'POST', body: fd }).then(r => r.json());
        if (res.error) { toast('Erro ao salvar: ' + res.error); return; }
        document.getElementById('rec-url').value = res.url; document.getElementById('rec-open').href = res.url;
        document.getElementById('rec-modal').style.display = 'flex';
    } catch (e) { toast('Falha ao enviar a gravação.'); }
}

// ---- Link / sair ----
function copyLink() {
    const url = `${BASE}/videocall/room/${ROOM_TOKEN}`;
    navigator.clipboard?.writeText(url).then(() => toast('Link copiado! Cole no convite ou no Fathom.')).catch(() => toast(url));
}
function copyRecUrl() { const v = document.getElementById('rec-url').value; navigator.clipboard?.writeText(v).then(() => toast('Link da gravação copiado!')); }

function teardown(headline, sub) {
    joined = false; waiting = false;
    if (adminTimer) clearInterval(adminTimer);
    if (waitTimer) clearInterval(waitTimer);
    stopNetworkMonitor();
    if (mediaRecorder && mediaRecorder.state !== 'inactive') { try { stopRecording(); } catch (e) {} }
    stopBgPipeline();
    peers.forEach(e => { try { e.pc.close(); } catch (x) {} });
    if (rawStream) rawStream.getTracks().forEach(t => t.stop());
    if (screenStream) screenStream.getTracks().forEach(t => t.stop());
    document.body.innerHTML = '<div style="height:100vh;display:flex;align-items:center;justify-content:center;flex-direction:column;color:#e8eaf1;font-family:system-ui;text-align:center;padding:20px;"><div style="font-size:3rem;">' + (headline.icon || '👋') + '</div><h3 style="margin-top:12px;">' + headline.text + '</h3>' + (sub ? '<p style="color:#9aa2c0;margin-top:4px;max-width:320px;">' + sub + '</p>' : '') + '<a href="' + BASE + '/videocall/room/' + ROOM_TOKEN + '" style="color:#00BFA6;margin-top:10px;">Entrar novamente</a></div>';
}
function hangup() {
    if (!joined) return; joined = false;
    if (mediaRecorder && mediaRecorder.state !== 'inactive') stopRecording();
    try { navigator.sendBeacon(`${BASE}/videocall/leave/${ROOM_TOKEN}`, new URLSearchParams({ peer_id: peerId })); }
    catch (e) { fetch(`${BASE}/videocall/leave/${ROOM_TOKEN}`, { method: 'POST', body: new URLSearchParams({ peer_id: peerId }) }); }
    teardown({ icon: '👋', text: 'Você saiu da chamada' });
}
function onKicked() { if (!joined) return; joined = false; teardown({ icon: '↪️', text: 'Chamada movida para outra guia' }, 'Você entrou nesta chamada em outra aba deste navegador. Esta sessão foi encerrada para evitar duplicidade.'); }

window.addEventListener('beforeunload', () => { if (joined) { try { navigator.sendBeacon(`${BASE}/videocall/leave/${ROOM_TOKEN}`, new URLSearchParams({ peer_id: peerId })); } catch (e) {} } });

// ---- utils ----
function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }
function escapeHtml(s) { return (s || '').replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c])); }
function toast(msg) { const box = document.getElementById('toasts'); const el = document.createElement('div'); el.className = 'toast-msg'; el.textContent = msg; box.appendChild(el); setTimeout(() => el.remove(), 4000); }

window.addEventListener('resize', () => { if (joined) layoutGrid(); });
window.addEventListener('orientationchange', () => { setTimeout(() => { if (joined) layoutGrid(); }, 300); });

const IS_TOUCH = ('ontouchstart' in window) || navigator.maxTouchPoints > 0;
if (IS_TOUCH) {
    document.addEventListener('click', (ev) => {
        const tile = ev.target.closest('.tile');
        if (ev.target.closest('.tile-tools') || ev.target.closest('.ctrl-caret') || ev.target.closest('.popover-menu')) return;
        document.querySelectorAll('.tile.show-tools').forEach(t => { if (t !== tile) t.classList.remove('show-tools'); });
        if (tile) tile.classList.toggle('show-tools');
    });
}

initPreview();
</script>
</body>
</html>
