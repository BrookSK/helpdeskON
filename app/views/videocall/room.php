<?php
$base = rtrim(baseUrl(''), '/');
$roomToken = htmlspecialchars($room['token'], ENT_QUOTES);
$roomTitle = htmlspecialchars($room['title'] ?: 'Videochamada', ENT_QUOTES);
$suggested = htmlspecialchars($suggestedName ?? '', ENT_QUOTES);
$allowRec = (int)($room['allow_recording'] ?? 1) === 1;
$visibility = ($room['visibility'] ?? 'public');
$isAdmin = !empty($isAdmin);
$allowPresentation = !isset($allowPresentation) ? true : (bool)$allowPresentation;
// Em sala privada, gravar é só para administradores.
$canRecord = $allowRec && !($visibility === 'private' && !$isAdmin);
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
    <?php $faviconUrl = Config::get('app_favicon'); if ($faviconUrl): ?>
    <link rel="icon" href="<?= baseUrl($faviconUrl) ?>" type="image/x-icon">
    <link rel="shortcut icon" href="<?= baseUrl($faviconUrl) ?>">
    <?php endif; ?>
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
        .lobby-card { width:100%; max-width:860px; background:var(--panel); border-radius:20px; padding:26px 28px; box-shadow:0 20px 60px rgba(0,0,0,.45); }
        .lobby-card h1 { font-size:1.25rem; font-weight:700; margin:0 0 4px; }
        .lobby-card .sub { color:#9aa2c0; font-size:.86rem; margin-bottom:0; }
        .lobby-head { margin-bottom:18px; }
        .lobby-grid { display:grid; grid-template-columns:1.1fr 1fr; gap:24px; align-items:start; }
        .lobby-left { min-width:0; }
        .lobby-right { min-width:0; }
        @media (max-width:760px){ .lobby-grid { grid-template-columns:1fr; gap:16px; } .lobby-card { max-width:440px; } }
        .private-badge { display:inline-flex; align-items:center; gap:5px; font-size:.72rem; background:rgba(0,191,166,.15); color:var(--brand); padding:3px 9px; border-radius:999px; margin-bottom:12px; }
        .lb-presence { display:flex; align-items:center; gap:10px; background:var(--panel2); border-radius:12px; padding:9px 12px; margin-bottom:14px; }
        .lb-presence-avatars { display:flex; }
        .lb-presence-avatars .av { width:32px; height:32px; border-radius:50%; border:2px solid var(--panel); margin-left:-8px; background:var(--brand); color:#fff; display:flex; align-items:center; justify-content:center; font-size:.8rem; font-weight:700; overflow:hidden; background-size:cover; background-position:center; }
        .lb-presence-avatars .av:first-child { margin-left:0; }
        .lb-presence-avatars .more { background:#33375a; }
        .lb-presence-text { font-size:.82rem; color:#cfd3e6; }
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
        .bg-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:8px; margin-bottom:14px; }
        .bg-opt { position:relative; aspect-ratio:16/10; border-radius:10px; overflow:hidden; cursor:pointer; border:2px solid transparent; background:var(--panel2) center/cover no-repeat; display:flex; align-items:center; justify-content:center; font-size:1.2rem; color:#9aa2c0; }
        .bg-opt.active { border-color:var(--brand); }
        .bg-opt small { position:absolute; left:0; right:0; bottom:0; padding:4px 6px; text-align:center; font-size:.62rem; color:#fff;
            background:linear-gradient(transparent, rgba(0,0,0,.75)); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

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
        /* Animação do bloco surgindo/saindo (experiência estilo Meet). */
        .tile.tile-in { animation:tileIn .28s cubic-bezier(.2,.8,.2,1); }
        .tile.tile-out { animation:tileOut .22s ease forwards; }
        @keyframes tileIn { from { opacity:0; transform:scale(.86); } to { opacity:1; transform:scale(1); } }
        @keyframes tileOut { from { opacity:1; transform:scale(1); } to { opacity:0; transform:scale(.86); } }
        .tile .vwrap { position:absolute; inset:0; overflow:hidden; }
        .tile video { width:100%; height:100%; object-fit:cover; background:#000; transition:transform .12s ease; transform-origin:center center; }
        .tile.self video { transform:scaleX(-1); }
        .tile.screen video { object-fit:contain; }
        /* Câmera em retrato (celular em pé) num tile largo: mostra inteira, sem cortar o rosto. */
        .tile.portrait-cam video { object-fit:contain; }
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

        /* Reação recente na webcam (aparece por alguns segundos) */
        .tile .reaction-badge { position:absolute; left:8px; top:8px; display:none; align-items:center; gap:6px;
            background:rgba(10,12,24,.78); padding:5px 10px; border-radius:999px; z-index:5; max-width:70%; }
        .tile .reaction-badge .emo { font-size:1.3rem; line-height:1; animation:reactPop .4s ease; }
        .tile .reaction-badge .who { font-size:.72rem; color:#e8eaf1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .tile.reacting .reaction-badge { display:flex; }
        @keyframes reactPop { 0%{ transform:scale(.4); } 60%{ transform:scale(1.25); } 100%{ transform:scale(1); } }

        /* Mão levantada bem visível (badge maior + amarelo) */
        .tile .badge-hand { color:#ffd54a; }
        .tile.hand-up .badge-hand { display:flex; background:rgba(224,164,0,.28); }
        .tile.hand-up { outline:2px solid #ffd54a; outline-offset:-2px; }
        /* Quem está falando: borda destacada (verde) com brilho suave. */
        .tile.speaking { outline:3px solid #3ddc84; outline-offset:-3px; box-shadow:0 0 0 1px rgba(61,220,132,.5), 0 0 16px rgba(61,220,132,.55); }
        .tile.speaking.mic-off { outline:none; box-shadow:none; } /* mutado nunca "fala" */

        /* Ferramentas do topo (fixar / mutar admin) — canto superior esquerdo. */
        .tile-tools { position:absolute; top:8px; left:8px; display:flex; gap:5px; opacity:0; transition:opacity .15s; z-index:4; }
        .tile:hover .tile-tools, .tile.show-tools .tile-tools { opacity:1; }
        .tile-tools button { width:30px; height:30px; border:none; border-radius:8px; background:rgba(10,12,24,.72); color:#fff; font-size:.9rem; cursor:pointer; display:flex; align-items:center; justify-content:center; }
        .tile-tools button:hover { background:var(--brand); }
        .tile-tools .tile-mod-btn:hover { background:#c0304a; }
        .filmstrip .tile-tools { transform:scale(.85); transform-origin:top left; }
        /* Controles de zoom da TELA — canto inferior direito. */
        .tile-zoom { position:absolute; right:8px; bottom:8px; display:flex; align-items:center; gap:5px; opacity:0; transition:opacity .15s; z-index:4; }
        .tile:hover .tile-zoom, .tile.show-tools .tile-zoom { opacity:1; }
        .tile-zoom button { width:30px; height:30px; border:none; border-radius:8px; background:rgba(10,12,24,.72); color:#fff; font-size:.9rem; cursor:pointer; display:flex; align-items:center; justify-content:center; }
        .tile-zoom button:hover { background:var(--brand); }
        .tile-zoom .zoom-val { min-width:42px; padding:0 6px; height:30px; border-radius:8px; background:rgba(10,12,24,.72); color:#cfd3e6; font-size:.72rem; display:flex; align-items:center; justify-content:center; }
        .filmstrip .tile-zoom { transform:scale(.85); transform-origin:bottom right; }

        /* Barra de controles */
        .controls-wrap { position:relative; }
        .controls-more { display:none; }
        .controls-fade { display:none; }
        .controls { display:flex; align-items:center; justify-content:center; gap:10px; padding:14px; background:rgba(0,0,0,.3); flex-wrap:wrap; }
        .ctrl-group { position:relative; display:flex; align-items:flex-end; }
        .ctrl { width:52px; height:52px; border-radius:50%; border:none; background:var(--panel2); color:#fff; font-size:1.15rem; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:.15s; position:relative; }
        .ctrl:hover { filter:brightness(1.2); }
        .ctrl.off { background:#c0304a; }
        .ctrl.active { background:var(--brand); }
        .ctrl.hangup { background:#e02a44; width:60px; }
        .ctrl.disabled-ctrl { opacity:.45; }
        .ctrl-caret { width:22px; height:22px; border-radius:50%; border:none; background:var(--panel2); color:#cfd3e6; font-size:.7rem; cursor:pointer; position:absolute; top:-4px; right:-4px; display:flex; align-items:center; justify-content:center; }
        .ctrl-caret:hover { background:var(--brand); color:#fff; }
        .ctrl-label { position:absolute; bottom:-18px; left:50%; transform:translateX(-50%); font-size:.62rem; color:#9aa2c0; white-space:nowrap; }
        .controls .ctrl { margin-bottom:16px; }

        /* Popover (menu de câmera / dispositivos) */
        .popover-menu { position:fixed; background:var(--panel); border:1px solid #33375a; border-radius:14px; padding:14px; width:360px; max-width:94vw; max-height:70vh; overflow:auto; box-shadow:0 16px 50px rgba(0,0,0,.5); z-index:80; display:none; }
        .popover-menu h6 { font-size:.8rem; color:#9aa2c0; text-transform:uppercase; letter-spacing:.5px; margin:0 0 8px; }
        #screen-menu { width:250px; padding:8px; }
        .screen-menu-item { display:flex; align-items:center; gap:10px; width:100%; text-align:left; border:none; background:transparent; color:#e8eaf1; padding:11px 12px; border-radius:10px; font-size:.9rem; cursor:pointer; }
        .screen-menu-item:hover { background:var(--panel2); }
        .screen-menu-item.stop { color:#ff9db0; }
        .screen-menu-item i { font-size:1.1rem; }
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
        .rec-tabs { display:flex; gap:4px; }
        .rec-tab { border:none; background:var(--panel2); color:#cfd3e6; padding:6px 12px; border-radius:8px 8px 0 0; font-size:.8rem; cursor:pointer; }
        .rec-tab.active { background:var(--brand); color:#fff; }
        .rec-tab-body { background:var(--panel2); border-radius:0 10px 10px 10px; padding:12px; max-height:240px; overflow:auto; font-size:.85rem; white-space:pre-wrap; line-height:1.5; }

        /* Tela de saída */
        .exit-screen { height:100vh; display:flex; align-items:center; justify-content:center; padding:20px; }
        .exit-card { background:var(--panel); border-radius:20px; padding:40px 32px; text-align:center; max-width:440px; width:100%; box-shadow:0 20px 60px rgba(0,0,0,.45); }
        .exit-ic { font-size:3.2rem; }
        .exit-card h3 { margin:14px 0 6px; }
        .exit-sub { color:#9aa2c0; font-size:.9rem; margin-bottom:8px; }
        .exit-actions { display:flex; flex-direction:column; gap:10px; margin-top:22px; }
        .ex-btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; padding:12px 18px; border-radius:12px;
            background:var(--panel2); color:#e8eaf1; border:1px solid #33375a; text-decoration:none; font-weight:600; font-size:.92rem; cursor:pointer; }
        .ex-btn:hover { filter:brightness(1.15); }
        .ex-btn.ex-primary { background:var(--brand); border-color:var(--brand); color:#fff; }

        /* Menu de reações (emojis) */
        .emoji-menu { position:fixed; background:var(--panel); border:1px solid #33375a; border-radius:999px; padding:8px 12px; display:none; gap:6px; z-index:80; box-shadow:0 12px 40px rgba(0,0,0,.5); }
        .emoji-menu.open { display:flex; }
        .emoji-menu button { border:none; background:transparent; font-size:1.5rem; cursor:pointer; width:42px; height:42px; border-radius:50%; transition:.12s; }
        .emoji-menu button:hover { background:var(--panel2); transform:scale(1.15); }

        /* Chuva de emojis */
        #emoji-rain { position:fixed; inset:0; pointer-events:none; z-index:88; overflow:hidden; }
        .rain-emoji { position:absolute; top:-8vh; font-size:2rem; animation:rainDown 3.2s ease-in forwards; will-change:transform,opacity; }
        @keyframes rainDown { 0%{ transform:translateY(0) scale(.7); opacity:0; } 10%{ opacity:1; } 90%{ opacity:1; } 100%{ transform:translateY(88vh) scale(1.05); opacity:0; } }

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
            .tile-zoom { right:6px; bottom:6px; gap:4px; }
            .tile-zoom button { width:34px; height:34px; font-size:1rem; }
            .tile-zoom .zoom-val { height:34px; }
            .controls { gap:8px; padding:10px 8px calc(10px + env(safe-area-inset-bottom)); flex-wrap:nowrap; overflow-x:auto; justify-content:flex-start; scroll-behavior:smooth; }
            .controls::-webkit-scrollbar { display:none; }
            /* Dica de arraste: seta pulsante à direita quando há mais botões escondidos. */
            .controls-more { display:none; }
            /* Faixa esfumaçada atrás da setinha: "apaga" os botões embaixo e
               deixa claro que ali é a zona de rolar (não de clicar). */
            .controls-fade { display:none; }
            .controls-wrap.has-overflow .controls-fade {
                display:block; position:absolute; right:0; top:0; bottom:0; width:64px;
                pointer-events:none; z-index:5;
                background:linear-gradient(to right, rgba(15,16,32,0), rgba(15,16,32,.55) 45%, rgba(15,16,32,.9));
                backdrop-filter:blur(2px); -webkit-backdrop-filter:blur(2px);
            }
            .controls-wrap.has-overflow .controls-more {
                display:flex; align-items:center; justify-content:center;
                position:absolute; right:6px; bottom:calc(12px + env(safe-area-inset-bottom));
                width:34px; height:34px; border-radius:50%; border:none;
                background:var(--brand); color:#fff; font-size:1rem; z-index:6;
                box-shadow:0 2px 10px rgba(0,0,0,.5); animation:moreNudge 1.2s ease-in-out infinite;
            }
            @keyframes moreNudge { 0%,100%{ transform:translateX(0); } 50%{ transform:translateX(4px); } }
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
        <div class="lobby-head">
            <h1><i class="bi bi-camera-video"></i> <?= $roomTitle ?></h1>
            <?php if ($visibility === 'private'): ?>
            <div class="private-badge"><i class="bi bi-shield-lock-fill"></i> Sala privada — entrada com aprovação</div>
            <?php endif; ?>
            <div class="sub">Ajuste sua câmera, microfone e o plano de fundo antes de entrar.</div>
        </div>

        <div class="lobby-grid">
            <!-- Coluna esquerda: preview + presença -->
            <div class="lobby-left">
                <div class="preview-wrap">
                    <video id="preview" autoplay muted playsinline class="mirror"></video>
                    <canvas id="preview-canvas" class="mirror hidden"></canvas>
                </div>
                <div class="lobby-toggles">
                    <button type="button" class="toggle-btn" id="lb-mic" onclick="toggleLobby('mic')"><i class="bi bi-mic-fill"></i> Microfone</button>
                    <button type="button" class="toggle-btn" id="lb-cam" onclick="toggleLobby('cam')"><i class="bi bi-camera-video-fill"></i> Câmera</button>
                </div>
                <div id="lb-presence" class="lb-presence" style="display:none;">
                    <div class="lb-presence-avatars" id="lb-presence-avatars"></div>
                    <div class="lb-presence-text" id="lb-presence-text"></div>
                </div>
            </div>

            <!-- Coluna direita: dispositivos, fundo, nome, entrar -->
            <div class="lobby-right">
                <div class="mb-blk" style="margin-bottom:12px;">
                    <label class="form-label">Câmera</label>
                    <select id="lb-cam-select" class="form-select" onchange="changeDevice('video', this.value)"></select>
                </div>
                <div class="mb-blk" style="margin-bottom:12px;">
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
        <span class="rec-dot" id="rec-indicator"><span class="dot"></span> <span id="rec-label">Gravando</span> <span id="rec-time">00:00</span></span>
    </div>
    <div class="stage-wrap" id="stage-wrap">
        <div class="stage" id="stage" style="display:none;"></div>
        <div class="grid" id="grid"></div>
        <div class="filmstrip" id="filmstrip" style="display:none;"></div>
    </div>
    <div class="controls-wrap">
    <div class="controls" id="controls">
        <div class="ctrl-group">
            <button class="ctrl" id="btn-mic" onclick="toggleMic()" title="Microfone"><i class="bi bi-mic-fill"></i><span class="ctrl-label">Mic</span></button>
            <button class="ctrl-caret" onclick="openMicMenu(event)" title="Escolher microfone"><i class="bi bi-chevron-up"></i></button>
        </div>
        <div class="ctrl-group">
            <button class="ctrl" id="btn-cam" onclick="toggleCam()" title="Câmera"><i class="bi bi-camera-video-fill"></i><span class="ctrl-label">Câmera</span></button>
            <button class="ctrl-caret" onclick="openCamMenu(event)" title="Câmera e plano de fundo"><i class="bi bi-chevron-up"></i></button>
        </div>
        <button class="ctrl" id="btn-screen" onclick="toggleScreen(event)" title="Compartilhar tela"><i class="bi bi-display"></i><span class="ctrl-label">Tela</span></button>
        <button class="ctrl" id="btn-react" onclick="openEmojiMenu(event)" title="Reagir"><i class="bi bi-emoji-smile"></i><span class="ctrl-label">Reagir</span></button>
        <button class="ctrl" id="btn-hand" onclick="toggleHand()" title="Levantar a mão"><i class="bi bi-hand-index-thumb"></i><span class="ctrl-label">Mão</span></button>
        <div class="ctrl-group">
            <button class="ctrl" id="btn-hands-list" onclick="toggleHandsPanel()" title="Quem levantou a mão"><i class="bi bi-list-ol"></i><span class="ctrl-label">Fila</span></button>
            <span class="badge-dot" id="hands-count-dot" style="position:absolute;top:-2px;right:-2px;background:#e0a400;border-radius:999px;padding:1px 6px;font-size:.65rem;display:none;"></span>
        </div>
        <?php if ($canRecord): ?>
        <div class="ctrl-group">
            <button class="ctrl" id="btn-rec" onclick="toggleRecording()" title="Gravar"><i class="bi bi-record-circle"></i><span class="ctrl-label">Gravar</span></button>
            <button class="ctrl-caret" id="btn-rec-pause" onclick="togglePauseRecording()" title="Pausar/retomar gravação" style="display:none;"><i class="bi bi-pause-fill"></i></button>
        </div>
        <?php endif; ?>
        <button class="ctrl" id="btn-pip" onclick="togglePip()" title="Abrir em janela flutuante"><i class="bi bi-pip"></i><span class="ctrl-label">Janela</span></button>
        <button class="ctrl" id="btn-copy" onclick="copyLink()" title="Copiar link"><i class="bi bi-link-45deg"></i><span class="ctrl-label">Link</span></button>
        <button class="ctrl hangup" onclick="hangup()" title="Sair"><i class="bi bi-telephone-x-fill"></i><span class="ctrl-label">Sair</span></button>
    </div>
    <!-- Dica: há mais botões ao arrastar para o lado (só aparece no celular quando há overflow) -->
    <div class="controls-fade"></div>
    <button type="button" class="controls-more" id="controls-more" onclick="scrollControls()" title="Mais opções"><i class="bi bi-chevron-right"></i></button>
    </div>

<!-- Popover da CÂMERA: escolher câmera + plano de fundo -->
<div class="popover-menu" id="cam-menu">
    <div class="mb-blk">
        <h6>Câmera</h6>
        <select id="cm-cam-select" class="form-select" onchange="changeDevice('video', this.value)"></select>
    </div>
    <div>
        <h6>Plano de fundo</h6>
        <div class="bg-grid" id="cm-bg-grid"></div>
    </div>
</div>

<!-- Popover do MICROFONE: escolher microfone -->
<div class="popover-menu" id="mic-menu">
    <div>
        <h6>Microfone</h6>
        <select id="cm-mic-select" class="form-select" onchange="changeDevice('audio', this.value)"></select>
    </div>
</div>

<!-- Menu da TELA (quando já está compartilhando): trocar ou parar -->
<div class="popover-menu" id="screen-menu">
    <button type="button" class="screen-menu-item" onclick="screenMenuAction('switch')"><i class="bi bi-arrow-repeat"></i> Trocar tela/janela</button>
    <button type="button" class="screen-menu-item stop" onclick="screenMenuAction('stop')"><i class="bi bi-stop-circle"></i> Parar de compartilhar</button>
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

        <?php if ($isAdmin): ?>
        <div class="sp-section-title">Controles da sala</div>
        <div class="sp-item" style="flex-wrap:wrap;">
            <span class="nm"><i class="bi bi-easel"></i> Apresentar tela<br><small class="text-muted" id="sp-perm-state"><?= $allowPresentation ? 'Qualquer um pode apresentar' : 'Só administradores apresentam' ?></small></span>
            <button id="sp-perm-btn" class="btn btn-sm <?= $allowPresentation ? 'btn-outline-warning' : 'btn-success' ?>" onclick="togglePresentationPerm()"><?= $allowPresentation ? 'Desativar apresentação' : 'Ativar apresentação' ?></button>
        </div>
        <hr style="border-color:#2a2d44;">
        <?php endif; ?>

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
        <p class="small text-secondary mt-2 mb-0">A transcrição e o resumo por IA ficam disponíveis ao abrir a gravação.</p>

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
const IS_LOGGED = <?= !empty($loggedUserId) ? 'true' : 'false' ?>;
let isAdmin = <?= $isAdmin ? 'true' : 'false' ?>;
let allowPresentation = <?= $allowPresentation ? 'true' : 'false' ?>;

// ---- Detecção de dispositivo/rede (otimização mobile e 4G) ----
const IS_MOBILE = /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent) || (('ontouchstart' in window) && Math.min(screen.width, screen.height) < 820);
// iOS (inclui iPad recente que se identifica como Mac com toque).
const IS_IOS = /iPhone|iPad|iPod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
const NET = navigator.connection || navigator.mozConnection || navigator.webkitConnection || null;
function isSlowNetwork() {
    if (!NET) return false;
    const t = NET.effectiveType || '';
    if (t.includes('2g') || t === '3g') return true;
    if (NET.saveData) return true;
    if (NET.downlink && NET.downlink < 2) return true; // < 2 Mbps
    return false;
}
// No celular, o processamento de fundo (MediaPipe) é o que mais trava: desliga por padrão.
const BG_ALLOWED = !IS_MOBILE;

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
// Nomes conhecidos por peer (nunca deixa cair para "Convidado" se já temos um nome).
const peerNames = new Map();
let stateHeartbeat = null;

// Envia meu estado completo (mic/câmera/mão/nome) — usado ao mudar algo e no heartbeat.
function broadcastMyState() {
    broadcast('state', { micMuted: !micOn, camOff: !camOn, handUp: (typeof handUp !== 'undefined' ? handUp : false), name: myName });
}
// Reafirma o estado periodicamente para autocorrigir ícones perdidos/fora de ordem.
function startStateHeartbeat() {
    if (stateHeartbeat) return;
    stateHeartbeat = setInterval(() => { if (joined) broadcastMyState(); }, 3000);
}
function stopStateHeartbeat() { if (stateHeartbeat) { clearInterval(stateHeartbeat); stateHeartbeat = null; } }

// Aplica o estado recebido de um peer ao seu tile e à fila de mãos.
function applyPeerState(from, p) {
    if (!p) return;
    if (p.name) { peerNames.set(from, p.name); refreshPeerName(from, p.name); }
    const t = tileEl(from);
    if (t) {
        t.classList.toggle('mic-off', !!p.micMuted);
        t.classList.toggle('cam-off', !!p.camOff);
    }
    // Mão levantada: sincroniza a fila com o estado real informado.
    const nm = p.name || peerNames.get(from) || 'Convidado';
    if (p.handUp) {
        if (!raisedHands.has(from)) raisedHands.set(from, { name: nm, ts: Date.now() });
        else raisedHands.get(from).name = nm;
        tileEl(from)?.classList.add('hand-up');
    } else {
        if (raisedHands.delete(from)) tileEl(from)?.classList.remove('hand-up');
    }
    renderHands();
}

// Atualiza o nome exibido no tile de um peer (label + dataset), sem virar "Convidado".
function refreshPeerName(id, name) {
    if (!name) return;
    const entry = peers.get(id);
    if (entry) entry.name = name;
    const t = tileEl(id);
    if (t) {
        const isScreen = t.classList.contains('screen');
        const label = t.querySelector('.name');
        if (label) label.textContent = name + (isScreen ? ' (tela)' : '');
    }
}
let polling = false, joined = false, waiting = false;
let curVideoDeviceId = null, curAudioDeviceId = null;

// ---- Preferência de fundo (persistida) ----
// bgMode: 'none' | 'blur' | 'image' ; bgImage: id do arquivo
let bgMode = 'none', bgImageId = null;
try {
    bgMode = localStorage.getItem('vc_bg_mode') || 'none';
    bgImageId = localStorage.getItem('vc_bg_image') || null;
} catch (e) {}
// No celular o efeito de fundo trava demais: desliga por padrão (ignora preferência salva).
if (!BG_ALLOWED) { bgMode = 'none'; bgImageId = null; }
function saveBgPref() {
    try { localStorage.setItem('vc_bg_mode', bgMode); if (bgImageId) localStorage.setItem('vc_bg_image', bgImageId); else localStorage.removeItem('vc_bg_image'); } catch (e) {}
}

// ==========================================================
// PIPELINE DE FUNDO (MediaPipe Selfie Segmentation em canvas)
// ==========================================================
let segmenter = null, bgCanvas = null, bgCtx = null, bgProcessing = false;
let bgRafId = null, processedStream = null, bgImageEl = null;
let maskCanvas = null, maskCtx = null; // máscara suavizada (bordas macias)
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
    // FONTE DA PESSOA: o vídeo ORIGINAL em alta (bgVideoEl), não results.image
    // (que o MediaPipe entrega reduzido e deixava a pessoa borrada).
    const personSrc = (bgVideoEl && bgVideoEl.readyState >= 2) ? bgVideoEl : results.image;

    // Canvas auxiliar: máscara SUAVIZADA (elimina o serrilhado da borda, como no Meet).
    if (!maskCanvas) { maskCanvas = document.createElement('canvas'); maskCtx = maskCanvas.getContext('2d'); }
    if (maskCanvas.width !== w || maskCanvas.height !== h) { maskCanvas.width = w; maskCanvas.height = h; }
    maskCtx.clearRect(0, 0, w, h);
    // Um leve blur na máscara faz a transição pessoa↔fundo ficar macia.
    maskCtx.filter = 'blur(3px)';
    maskCtx.drawImage(results.segmentationMask, 0, 0, w, h);
    maskCtx.filter = 'none';

    bgCtx.save();
    bgCtx.clearRect(0, 0, w, h);
    bgCtx.imageSmoothingEnabled = true;
    bgCtx.imageSmoothingQuality = 'high';
    // 1) Pessoa em resolução cheia.
    bgCtx.drawImage(personSrc, 0, 0, w, h);
    // 2) Recorta com a máscara suavizada.
    bgCtx.globalCompositeOperation = 'destination-in';
    bgCtx.drawImage(maskCanvas, 0, 0, w, h);
    // 3) Fundo atrás.
    bgCtx.globalCompositeOperation = 'destination-over';
    if (bgMode === 'blur') {
        bgCtx.filter = 'blur(14px)';
        bgCtx.drawImage(personSrc, 0, 0, w, h);
        bgCtx.filter = 'none';
    } else if (bgMode === 'image' && bgImageEl && bgImageEl.complete) {
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
    // Canvas na resolução REAL da câmera: a pessoa é desenhada do vídeo original
    // em alta, então não há perda de nitidez. Teto de segurança em 1920 de largura.
    const settings = vtrack.getSettings();
    let cw = settings.width || 1280, ch = settings.height || 720;
    if (cw > 1920) { ch = Math.round(ch * (1920 / cw)); cw = 1920; }
    bgCanvas.width = cw;
    bgCanvas.height = ch;

    bgVideoEl.srcObject = new MediaStream([vtrack]);
    await bgVideoEl.play().catch(() => {});

    bgProcessing = true;
    let bgBusy = false;
    // Usa setInterval (não requestAnimationFrame): rAF PARA quando a aba está em
    // segundo plano, o que congelava a câmera para os outros. O interval segue
    // rodando (com throttle em aba oculta, mas o stream não trava).
    const tick = async () => {
        if (!bgProcessing || bgBusy) return;
        if (bgVideoEl.readyState >= 2) {
            bgBusy = true;
            try { await segmenter.send({ image: bgVideoEl }); } catch (e) {}
            bgBusy = false;
        }
    };
    bgRafId = setInterval(tick, 33); // ~30fps quando visível

    processedStream = bgCanvas.captureStream(30);
    return processedStream.getVideoTracks()[0];
}

function stopBgPipeline() {
    bgProcessing = false;
    if (bgRafId) clearInterval(bgRafId);
    bgRafId = null;
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
    // Re-pluga o detector de "quem está falando" no novo stream de áudio.
    if (audioTrack) attachSpeaking(peerId, localStream);

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

// Restrições de vídeo. No PC busca alta (1080p); no celular/4G começa leve (720/480)
// para não travar e aguentar rede móvel.
const VIDEO_CONSTRAINTS = (IS_MOBILE || isSlowNetwork())
    ? { width: { ideal: 640, max: 1280 }, height: { ideal: 480, max: 720 }, frameRate: { ideal: 24, max: 30 } }
    : { width: { ideal: 1920, max: 1920 }, height: { ideal: 1080, max: 1080 }, frameRate: { ideal: 30, max: 30 } };
const AUDIO_CONSTRAINTS = { echoCancellation: true, noiseSuppression: true, autoGainControl: true };

// Tenta obter câmera+microfone com fallback em cascata (compatível com iOS/Safari,
// que falha se as constraints forem rígidas demais).
async function getCameraStream() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        throw new DOMException('getUserMedia indisponível', 'NotSupportedError');
    }
    const attempts = [
        { video: VIDEO_CONSTRAINTS, audio: AUDIO_CONSTRAINTS },
        // iOS costuma aceitar melhor "facingMode:user" do que largura/altura fixas.
        { video: { facingMode: 'user' }, audio: true },
        { video: true, audio: true },   // o mais simples
        { video: false, audio: true },  // só áudio (sem câmera)
    ];
    let lastErr = null;
    for (const c of attempts) {
        try { return await navigator.mediaDevices.getUserMedia(c); }
        catch (e) { lastErr = e; }
    }
    throw lastErr || new Error('Falha ao acessar mídia');
}

async function initPreview() {
    try {
        rawStream = await getCameraStream();
        const vt = rawStream.getVideoTracks()[0];
        if (vt) curVideoDeviceId = vt.getSettings().deviceId;
        const at = rawStream.getAudioTracks()[0];
        if (at) curAudioDeviceId = at.getSettings().deviceId;
        // Se não veio vídeo (fallback só-áudio), reflete no lobby.
        if (!vt) { lobbyCam = false; camOn = false; }
        await populateDevices();
        await rebuildLocalStream();
        syncLobbyButtons();
    } catch (e) {
        lobbyCam = false; camOn = false;
        showLobbyError(cameraErrorMessage(e));
        syncLobbyButtons();
    }
    renderBgGrids();
    loadLobbyPresence();
    lobbyPresenceTimer = setInterval(loadLobbyPresence, 5000);
}

// Mensagem de erro específica (ajuda muito no iPhone/Safari).
function cameraErrorMessage(e) {
    const name = e && e.name ? e.name : '';
    if (name === 'NotAllowedError' || name === 'SecurityError') {
        if (IS_IOS) return 'A permissão da câmera foi negada. No iPhone: toque em "aA" na barra de endereço do Safari → Ajustes do site → Câmera/Microfone = Permitir, e recarregue. Verifique também Ajustes do iOS → Safari → Câmera.';
        return 'Permissão de câmera/microfone negada. Clique no cadeado ao lado do endereço e permita a câmera, depois recarregue.';
    }
    if (name === 'NotFoundError' || name === 'OverconstrainedError') {
        return 'Nenhuma câmera compatível foi encontrada. Você ainda pode entrar só com áudio.';
    }
    if (name === 'NotReadableError') {
        return 'A câmera está em uso por outro app (feche outras chamadas/apps que usam a câmera) e recarregue.';
    }
    if (name === 'NotSupportedError') {
        return 'Este navegador não permite usar a câmera aqui. No iPhone, use o Safari e acesse por HTTPS.';
    }
    return 'Não foi possível acessar a câmera/microfone. Você ainda pode entrar sem vídeo.';
}

// Mostra no lobby quem já está na chamada (contagem + avatares + nomes).
let lobbyPresenceTimer = null;
async function loadLobbyPresence() {
    if (joined) { if (lobbyPresenceTimer) clearInterval(lobbyPresenceTimer); return; }
    try {
        const r = await fetch(`${BASE}/videocall/preview/${ROOM_TOKEN}`).then(x => x.json());
        const box = document.getElementById('lb-presence');
        const peers = r.peers || [];
        if (!peers.length) { box.style.display = 'none'; return; }
        box.style.display = 'flex';
        // Avatares (até 4) + "+N"
        const avBox = document.getElementById('lb-presence-avatars');
        const show = peers.slice(0, 4);
        avBox.innerHTML = show.map(p => {
            if (p.avatar) return `<div class="av" style="background-image:url('${p.avatar}')"></div>`;
            return `<div class="av">${escapeHtml((p.name || 'C').trim().charAt(0).toUpperCase())}</div>`;
        }).join('') + (peers.length > 4 ? `<div class="av more">+${peers.length - 4}</div>` : '');
        // Texto: "5 pessoas na chamada · Lucas e mais 4"
        const first = peers[0] ? peers[0].name : '';
        let txt = peers.length === 1 ? '1 pessoa na chamada' : (peers.length + ' pessoas na chamada');
        if (peers.length === 1) txt += ' · ' + first;
        else if (peers.length > 1) txt += ' · ' + first + ' e mais ' + (peers.length - 1);
        document.getElementById('lb-presence-text').textContent = txt;
    } catch (e) {}
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
        // iOS às vezes exige play() explícito para o preview não ficar preto.
        v.play && v.play().catch(() => {});
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
    // No celular, o efeito de fundo é desativado para não travar a chamada.
    if (!BG_ALLOWED) {
        grid.style.display = 'block';
        grid.innerHTML = '<div style="grid-column:1/-1;color:#9aa2c0;font-size:.78rem;background:var(--panel2);padding:8px 10px;border-radius:10px;"><i class="bi bi-info-circle"></i> Plano de fundo indisponível no celular (para manter a chamada fluida).</div>';
        return;
    }
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
    if (lobbyPresenceTimer) clearInterval(lobbyPresenceTimer);
    // Admin vê o contador clicável (abre o painel de participantes/pedidos).
    if (isAdmin) {
        document.getElementById('peer-count-wrap').classList.add('clickable');
        startAdminPolling();
    }
    addSelfTile();
    sfx('selfjoin'); // som de "você entrou"
    (res.peers || []).forEach(p => { ensurePeer(p.peer_id, p.name, true); });
    updateCount();
    startPolling();
    startNetworkMonitor();
    startStateHeartbeat();
    // Anuncia meu estado inicial (nome/mic/câmera) para todos já sincronizarem.
    setTimeout(broadcastMyState, 700);
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
    // Zoom só existe em tiles de TELA. Fica no canto INFERIOR DIREITO.
    const zoomTools = screen
        ? `<div class="tile-zoom">
             <button title="Diminuir zoom" onclick="zoomTile('${id}',-0.25)"><i class="bi bi-zoom-out"></i></button>
             <span class="zoom-val" id="zoom-${id}">100%</span>
             <button title="Aumentar zoom" onclick="zoomTile('${id}',0.25)"><i class="bi bi-zoom-in"></i></button>
             <button title="Zoom padrão" onclick="resetZoom('${id}')"><i class="bi bi-arrow-counterclockwise"></i></button>
           </div>`
        : '';
    // Botão de mutar (admin muta o microfone de um participante). Só em tiles de
    // CÂMERA de OUTRA pessoa e apenas quando eu sou admin.
    const isOwnOrScreen = screen || (id === peerId) || id.endsWith('-screen');
    const modTools = (isAdmin && !isOwnOrScreen)
        ? `<button class="tile-mod-btn" title="Silenciar microfone deste participante" onclick="adminMute('${id}')"><i class="bi bi-mic-mute"></i></button>`
        : '';
    div.innerHTML =
        `<div class="vwrap"><video autoplay playsinline ${opts.self ? 'muted' : ''}></video></div>
         <div class="avatar"><span>${initial}</span></div>
         <div class="reaction-badge"><span class="emo"></span><span class="who"></span></div>
         <div class="tile-tools">
            ${modTools}
            <button class="pin-btn" title="Fixar/desafixar" onclick="togglePin('${id}')"><i class="bi bi-pin-angle"></i></button>
         </div>
         ${zoomTools}
         <div class="badges">
            <div class="badge-ic badge-hand"><i class="bi bi-hand-index-thumb-fill"></i></div>
            <div class="badge-ic badge-pin"><i class="bi bi-pin-angle-fill"></i></div>
            <div class="badge-ic badge-mic"><i class="bi bi-mic-mute-fill"></i></div>
            <div class="badge-ic badge-cam"><i class="bi bi-camera-video-off-fill"></i></div>
         </div>
         <div class="name">${escapeHtml(name)}${screen ? ' (tela)' : ''}</div>`;
    document.getElementById('grid').appendChild(div);
    tileZoom.set(id, 1);
    // Animação de "bloco surgindo".
    div.classList.add('tile-in');
    setTimeout(() => div.classList.remove('tile-in'), 300);
    // Ajusta o enquadramento quando o vídeo carrega (câmera retrato x paisagem).
    const vEl = div.querySelector('video');
    if (vEl) {
        vEl.addEventListener('loadedmetadata', () => adjustTileFit(id));
        vEl.addEventListener('resize', () => adjustTileFit(id));
    }
    layoutGrid();
    return div;
}

// Decide entre "cover" (preenche) e "contain" (mostra inteiro) conforme a
// orientação do vídeo x a do tile, para não cortar demais o rosto.
function adjustTileFit(id) {
    const t = tileEl(id); if (!t) return;
    if (t.classList.contains('screen')) return; // tela sempre usa contain
    const v = t.querySelector('video'); if (!v || !v.videoWidth) return;
    const vertVideo = v.videoHeight > v.videoWidth * 1.15; // vídeo em pé (retrato)
    const rect = t.getBoundingClientRect();
    const wideTile = rect.width > rect.height * 1.1;               // tile deitado
    // Câmera em pé dentro de um tile deitado: usa contain para caber inteira.
    t.classList.toggle('portrait-cam', vertVideo && wideTile);
}

// Zoom (apenas tiles de tela). Visualização local de quem clica.
// Deslocamento (pan) por tile: {x,y} em fração (-1..1) da área excedente.
const tilePan = new Map();

function applyZoom(id) {
    const t = tileEl(id); if (!t) return;
    const v = t.querySelector('video'); if (!v) return;
    const z = tileZoom.get(id) || 1;
    const mirror = t.classList.contains('self') ? -1 : 1;
    const pan = tilePan.get(id) || { x: 0, y: 0 };

    // Limite do deslocamento (pan) em CADA eixo, considerando o tamanho REAL
    // renderizado do vídeo dentro do tile (importante quando é "contain": a tela
    // não preenche o tile, então o limite é diferente em X e Y — isso garante
    // que dá para chegar às extremidades, inclusive topo/base).
    const rect = t.getBoundingClientRect();
    const W = rect.width || 1, H = rect.height || 1;
    const vw = v.videoWidth || 16, vh = v.videoHeight || 9;
    const isContain = t.classList.contains('screen') || t.classList.contains('portrait-cam');
    // dimensão base do conteúdo dentro do tile (antes do scale)
    let baseW, baseH;
    if (isContain) {
        const s = Math.min(W / vw, H / vh); baseW = vw * s; baseH = vh * s;
    } else {
        const s = Math.max(W / vw, H / vh); baseW = vw * s; baseH = vh * s;
    }
    // com o zoom aplicado
    const contentW = baseW * z, contentH = baseH * z;
    // excedente além do tile (px) em cada eixo; metade para cada lado
    const overX = Math.max(0, (contentW - W) / 2);
    const overY = Math.max(0, (contentH - H) / 2);
    // translate é em % da altura/largura do ELEMENTO de vídeo (W×H)
    const maxPctX = (overX / W) * 100;
    const maxPctY = (overY / H) * 100;
    const tx = Math.max(-maxPctX, Math.min(maxPctX, pan.x * maxPctX));
    const ty = Math.max(-maxPctY, Math.min(maxPctY, pan.y * maxPctY));

    v.style.transform = `translate(${tx}%, ${ty}%) scaleX(${mirror}) scale(${z})`;
    v.style.cursor = (z > 1) ? 'grab' : '';
    const label = document.getElementById('zoom-' + id);
    if (label) label.textContent = Math.round(z * 100) + '%';
}
function zoomTile(id, delta) {
    if (!isScreenTile(id)) return; // zoom só em tela
    let z = (tileZoom.get(id) || 1) + delta;
    z = Math.max(1, Math.min(4, Math.round(z * 100) / 100));
    tileZoom.set(id, z);
    if (z === 1) tilePan.set(id, { x: 0, y: 0 }); // resetou o zoom, centraliza
    applyZoom(id);
    enablePan(id);
}
function resetZoom(id) { tileZoom.set(id, 1); tilePan.set(id, { x: 0, y: 0 }); applyZoom(id); }

// Habilita arrastar (mouse + toque) para mover a área ampliada.
function enablePan(id) {
    const t = tileEl(id); if (!t || t.dataset.panBound) return;
    t.dataset.panBound = '1';
    let dragging = false, sx = 0, sy = 0, startPan = { x: 0, y: 0 };
    const v = t.querySelector('video');

    const getPoint = (e) => e.touches ? { x: e.touches[0].clientX, y: e.touches[0].clientY } : { x: e.clientX, y: e.clientY };
    const down = (e) => {
        const z = tileZoom.get(id) || 1;
        if (z <= 1) return;               // só arrasta com zoom
        if (e.target.closest('.tile-tools')) return;
        dragging = true;
        const p = getPoint(e); sx = p.x; sy = p.y;
        startPan = Object.assign({ x: 0, y: 0 }, tilePan.get(id));
        if (v) v.style.cursor = 'grabbing';
        e.preventDefault();
    };
    const move = (e) => {
        if (!dragging) return;
        const z = tileZoom.get(id) || 1;
        const rect = t.getBoundingClientRect();
        const p = getPoint(e);
        // Converte o arrasto em fração; o sinal segue o dedo/mouse.
        const dx = (p.x - sx) / rect.width;
        const dy = (p.y - sy) / rect.height;
        const mirror = t.classList.contains('self') ? -1 : 1;
        let nx = startPan.x + (dx * 2 * mirror);
        let ny = startPan.y + (dy * 2);
        nx = Math.max(-1, Math.min(1, nx));
        ny = Math.max(-1, Math.min(1, ny));
        tilePan.set(id, { x: nx, y: ny });
        applyZoom(id);
        e.preventDefault();
    };
    const up = () => { dragging = false; if (v) v.style.cursor = (tileZoom.get(id) > 1) ? 'grab' : ''; };

    t.addEventListener('mousedown', down);
    window.addEventListener('mousemove', move);
    window.addEventListener('mouseup', up);
    t.addEventListener('touchstart', down, { passive: false });
    t.addEventListener('touchmove', move, { passive: false });
    t.addEventListener('touchend', up);
}

function togglePin(id) {
    if (pinned.has(id)) pinned.delete(id); else pinned.add(id);
    updatePinIcon(id);
    layoutGrid();
}
// Troca o ícone do botão de fixar: alfinete inclinado (não fixado) x preenchido (fixado).
function updatePinIcon(id) {
    const t = tileEl(id); if (!t) return;
    const tools = t.querySelector('.tile-tools'); if (!tools) return;
    const btn = tools.querySelector('.pin-btn'); if (!btn) return;
    const ic = btn.querySelector('i'); if (!ic) return;
    const fixed = pinned.has(id);
    ic.className = fixed ? 'bi bi-pin-angle-fill' : 'bi bi-pin-angle';
    btn.title = fixed ? 'Desafixar' : 'Fixar/desafixar';
}

// Admin silencia o microfone de um participante (ele pode reativar depois).
function adminMute(id) {
    if (!isAdmin || id === peerId) return;
    sendSignal(id, 'forcemute', { by: myName });
    const nm = peerNames.get(id) || (peers.get(id)?.name) || 'participante';
    toast('Microfone de ' + nm + ' silenciado.');
    // Reflete visualmente já (o estado real volta pelo heartbeat do peer).
    tileEl(id)?.classList.add('mic-off');
}

/**
 * Aplica a grade preenchendo a ÚLTIMA linha incompleta: os tiles que sobram
 * se esticam para ocupar a largura toda (sem "buraco" vazio). Ex.: 3 pessoas
 * em 2 colunas => 2 em cima e a 3ª ocupando a linha inteira embaixo.
 * Técnica: usa o dobro de colunas (subcolunas) e ajusta o "span" de cada tile.
 */
function applyGridSpans(tiles, cols) {
    const n = tiles.length || 1;
    grid.setAttribute('data-n', Math.min(n, 16));
    const grid_ = document.getElementById('grid');
    if (cols <= 1) {
        grid_.style.gridTemplateColumns = '1fr';
        grid_.style.gridTemplateRows = `repeat(${n}, 1fr)`;
        tiles.forEach(t => { t.style.gridColumn = ''; });
        return;
    }
    const sub = cols * 2; // subcolunas (para poder centralizar linhas incompletas)
    const rows = Math.ceil(n / cols);
    grid_.style.gridTemplateColumns = `repeat(${sub}, 1fr)`;
    grid_.style.gridTemplateRows = `repeat(${rows}, 1fr)`;
    const rest = n % cols; // quantos ficam na última linha (0 = cheia)
    const fullRowsCount = rest === 0 ? n : (n - rest);
    tiles.forEach((t, i) => {
        if (i < fullRowsCount) {
            // Linhas completas: cada tile ocupa 2 subcolunas.
            t.style.gridColumn = 'span 2';
        } else {
            // Última linha incompleta: divide a largura toda entre os que sobraram.
            t.style.gridColumn = 'span ' + Math.floor(sub / rest);
        }
    });
}

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
        const portrait = window.innerHeight >= window.innerWidth; // celular em pé
        const small = window.innerWidth <= 820;
        let cols;
        if (small && portrait) {
            // Celular em pé: empilha (uma câmera EM CIMA da outra), aproveita a altura.
            if (n === 1) cols = 1;
            else if (n <= 2) cols = 1;      // 2 pessoas: uma sobre a outra
            else if (n <= 6) cols = 2;
            else cols = 2;
        } else {
            if (n === 1) cols = 1;
            else if (n === 2) cols = 2;
            else if (n <= 4) cols = 2;
            else if (n <= 9) cols = 3;
            else cols = 4;
            if (small) cols = (n === 1) ? 1 : 2; // celular deitado
        }
        applyGridSpans(allTiles, cols);
    } else {
        grid.style.display = 'none'; stage.style.display = 'grid';
        featured.forEach(t => { t.style.gridColumn = ''; stage.appendChild(t); });
        const fn = featured.length;
        const scols = fn === 1 ? 1 : 2;
        stage.style.gridTemplateColumns = `repeat(${scols}, 1fr)`;
        stage.style.gridTemplateRows = `repeat(${Math.ceil(fn / scols)}, 1fr)`;
        if (others.length) { strip.style.display = 'flex'; wrap.classList.add('with-strip'); others.forEach(t => { t.style.gridColumn = ''; strip.appendChild(t); }); }
        else { strip.style.display = 'none'; wrap.classList.remove('with-strip'); }
    }
    allTiles.forEach(t => { applyZoom(t.dataset.tid); adjustTileFit(t.dataset.tid); });
}

function addSelfTile() {
    const div = makeTile(peerId, myName + ' (você)', { self: true });
    div.querySelector('video').srcObject = localStream;
    div.classList.toggle('mic-off', !micOn);
    div.classList.toggle('cam-off', !camOn);
    attachSpeaking(peerId, localStream);
}
function removeTile(id) { detachSpeaking(id); const t = tileEl(id); if (t) { t.remove(); tileZoom.delete(id); tilePan.delete(id); pinned.delete(id); layoutGrid(); } }
let lastQualityFloor = -1;
function updateCount() {
    document.getElementById('peer-count').textContent = (peers.size + 1);
    // Ajusta o teto de qualidade quando o nº de participantes muda de faixa.
    if (joined) {
        const floor = participantQualityFloor();
        if (floor !== lastQualityFloor) {
            lastQualityFloor = floor;
            if (typeof applyQualityToAll === 'function') applyQualityToAll();
        }
    }
}

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
// Começa em nível mais baixo no celular/4G (2=480p) e no melhor no PC (0=1080p).
let qualityIndex = (IS_MOBILE || isSlowNetwork()) ? 2 : 0;
let autoCamOff = false;        // câmera desligada AUTOMATICAMENTE por rede ruim
let camOffByUser = false;      // usuário desligou manualmente (não religa sozinho)

// TETO de qualidade conforme o nº de participantes (mesh: mais gente = cada
// stream precisa ser mais leve para o upload/CPU de todos aguentar).
// Retorna o índice MÍNIMO de QUALITY_LEVELS permitido (quanto maior, mais leve).
function participantQualityFloor() {
    const total = peers.size + 1; // eu + remotos
    if (total <= 2) return 0;     // 1:1 -> pode 1080p
    if (total <= 4) return 1;     // 3-4 -> teto 720p
    if (total <= 6) return 2;     // 5-6 -> teto 480p
    return 3;                     // 7+  -> teto 360p
}
// Nível efetivo = o mais LEVE entre o adaptativo (rede) e o teto (participantes).
function effectiveQualityIndex() {
    return Math.max(qualityIndex, participantQualityFloor());
}

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
            const lv = QUALITY_LEVELS[effectiveQualityIndex()];
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
            const before = effectiveQualityIndex();
            qualityIndex++; applyQualityToAll(); badStreak = 0;
            if (effectiveQualityIndex() !== before) toast('Conexão instável: qualidade em ' + QUALITY_LEVELS[effectiveQualityIndex()].name + '.');
        }
        // Já no pior nível e ainda ruim: desliga a câmera automaticamente.
        else if (badStreak >= 2 && qualityIndex >= QUALITY_LEVELS.length - 1 && camOn && !camOffByUser) {
            autoDisableCam();
            badStreak = 0;
        }
        // Rede boa por um tempo: sobe a qualidade de volta (respeitando o teto de participantes).
        if (goodStreak >= 3) {
            if (autoCamOff) { autoEnableCam(); goodStreak = 0; }
            else if (qualityIndex > 0) {
                const before = effectiveQualityIndex();
                qualityIndex--; applyQualityToAll(); goodStreak = 0;
                if (effectiveQualityIndex() !== before) toast('Conexão melhorou: qualidade em ' + QUALITY_LEVELS[effectiveQualityIndex()].name + '.');
            }
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
    const entry = { pc, name, polite: peerId < remoteId, makingOffer: false, tile: null, screenTile: null, pendingIce: [], hasCam: false, screenTrackIds: new Set() };
    peers.set(remoteId, entry);

    if (localStream) localStream.getTracks().forEach(t => { if (t.kind === 'video') t.contentHint = 'motion'; const s = pc.addTrack(t, localStream); tuneSender(s, t); });
    if (screenStream) screenStream.getVideoTracks().forEach(t => { const s = pc.addTrack(t, screenStream); if (t) t.contentHint = 'detail'; tuneSender(s, t); });

    pc.onicecandidate = (e) => { if (e.candidate) sendSignal(remoteId, 'ice', e.candidate); };
    pc.ontrack = (e) => {
        const stream = e.streams[0];
        const track = e.track;
        // A tela é identificada pelo trackId anunciado no sinal 'screen' (confiável,
        // funciona mesmo quando a tela tem áudio). Fallback: heurística antiga.
        const isScreen = (track && entry.screenTrackIds && entry.screenTrackIds.has(track.id))
            || (stream && stream.getAudioTracks().length === 0 && stream.getVideoTracks().length === 1 && entry.hasCam && track && track.kind === 'video');
        if (isScreen && track.kind === 'video') {
            if (!entry.screenTile) entry.screenTile = makeTile(remoteId + '-screen', entry.name || name, { screen: true });
            entry.screenTile.querySelector('video').srcObject = stream;
            // Se a track de tela do outro terminar, remove o tile automaticamente
            // (evita a "tela congelada" caso o sinal de parada se perca).
            track.onended = () => { removeTile(remoteId + '-screen'); entry.screenTile = null; };
            track.onmute = () => { /* mantido; onended cobre a remoção */ };
        } else if (track.kind === 'video') {
            const novo = !entry.tile;
            entry.hasCam = true;
            if (!entry.tile) entry.tile = makeTile(remoteId, entry.name || name);
            entry.tile.querySelector('video').srcObject = stream;
            if (novo) sfx('join'); // som de alguém entrando (quando a câmera aparece)
        }
        // Detector de "quem está falando": pluga no áudio da CÂMERA (não da tela).
        if (track.kind === 'audio' && !isScreen) {
            attachSpeaking(remoteId, stream);
        }
        updateCount();
    };
    pc.onnegotiationneeded = async () => {
        try { entry.makingOffer = true; await pc.setLocalDescription(await pc.createOffer()); sendSignal(remoteId, 'offer', pc.localDescription); }
        catch (err) { console.warn('negotiation', err); } finally { entry.makingOffer = false; }
    };
    // Queda de conexão (F5, internet caiu): remove o peer QUASE INSTANTÂNEO,
    // sem esperar o timeout de presença do servidor. Dá um pequeno prazo para
    // reconexões momentâneas antes de derrubar.
    const onConnDown = () => {
        const st = pc.connectionState || pc.iceConnectionState;
        if (st === 'failed' || st === 'closed') { dropPeer(remoteId); return; }
        if (st === 'disconnected') {
            if (entry._downTimer) return;
            entry._downTimer = setTimeout(() => {
                entry._downTimer = null;
                const cur = pc.connectionState || pc.iceConnectionState;
                if (cur === 'disconnected' || cur === 'failed' || cur === 'closed') dropPeer(remoteId);
            }, 2500);
        } else if (entry._downTimer) { clearTimeout(entry._downTimer); entry._downTimer = null; }
    };
    pc.onconnectionstatechange = onConnDown;
    pc.oniceconnectionstatechange = onConnDown;

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
        const nm = (sig.payload && sig.payload.name) || peerNames.get(from) || 'Convidado';
        if (sig.payload && sig.payload.name) peerNames.set(from, sig.payload.name);
        if (!peers.has(from)) ensurePeer(from, nm, false); else refreshPeerName(from, nm);
        // Assim que alguém entra, mando meu estado completo para ele se sincronizar.
        setTimeout(broadcastMyState, 500);
        return;
    }
    if (sig.kind === 'state') { applyPeerState(from, sig.payload); return; }
    if (sig.kind === 'kick') { onKicked(); return; }
    if (sig.kind === 'end') { teardown({ icon: '📴', text: 'Chamada encerrada', }, 'O organizador encerrou a chamada.'); return; }
    if (sig.kind === 'leave') { dropPeer(from); return; }
    if (sig.kind === 'media') {
        if (sig.payload && sig.payload.name) { peerNames.set(from, sig.payload.name); refreshPeerName(from, sig.payload.name); }
        const t = tileEl(from);
        if (t && sig.payload) { t.classList.toggle('mic-off', !!sig.payload.micMuted); t.classList.toggle('cam-off', !!sig.payload.camOff); }
        return;
    }
    if (sig.kind === 'screen') {
        const entry = peers.get(from);
        if (sig.payload && sig.payload.stop) {
            removeTile(from + '-screen');
            if (entry) { entry.screenTile = null; if (entry.screenTrackIds) entry.screenTrackIds.clear(); }
        } else if (sig.payload && sig.payload.start && sig.payload.trackId) {
            // Anuncia qual track é a tela, para o ontrack identificar com certeza.
            if (entry) { entry.screenTrackIds = entry.screenTrackIds || new Set(); entry.screenTrackIds.add(sig.payload.trackId); }
        }
        return;
    }
    if (sig.kind === 'reaction') {
        if (sig.payload && sig.payload.emoji) {
            spawnEmojiRain(sig.payload.emoji);
            showReactionBadge(from, sig.payload.emoji, sig.payload.name || (peers.get(from)?.name) || 'Convidado');
        }
        return;
    }
    if (sig.kind === 'perm') { if (sig.payload) applyPresentationPerm(!!sig.payload.allow_presentation); return; }
    if (sig.kind === 'rec') { showRemoteRecState(sig.payload || {}); return; }
    if (sig.kind === 'forcemute') {
        // O admin pediu para EU silenciar meu microfone. Muto (se estiver aberto) e aviso.
        if (sig.to === peerId) {
            if (micOn) toggleMic();
            toast('🔇 Um administrador silenciou seu microfone. Você pode reativá-lo quando quiser.');
        }
        return;
    }
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
function dropPeer(id) {
    const e = peers.get(id);
    if (e) { if (e._downTimer) clearTimeout(e._downTimer); try { e.pc.close(); } catch (x) {} }
    if (!peers.has(id)) return; // já removido
    peers.delete(id);
    peerNames.delete(id);
    sfx('leave'); // som de alguém saindo
    animateTileOut(id);
    animateTileOut(id + '-screen');
    if (raisedHands.delete(id)) renderHands();
    updateCount();
}
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
    peers.forEach((entry, id) => {
        if (active.has(id)) return;
        // NÃO remove se a conexão P2P ainda está viva: o participante pode só
        // ter travado o heartbeat momentaneamente (ex.: seletor de tela aberto).
        // A conexão WebRTC é a fonte da verdade; a remoção por queda real já é
        // feita em onconnectionstatechange.
        const st = entry.pc && (entry.pc.connectionState || entry.pc.iceConnectionState);
        if (st === 'connected' || st === 'completed' || st === 'checking' || st === 'new') return;
        dropPeer(id);
    });
    activeList.forEach(p => {
        if (p.peer_id === peerId) return;
        // O backend conhece o nome real do participante (da presença): usa-o
        // como fonte da verdade, para nunca ficar "Convidado".
        if (p.name && p.name !== 'Convidado') { peerNames.set(p.peer_id, p.name); }
        if (!peers.has(p.peer_id)) ensurePeer(p.peer_id, peerNames.get(p.peer_id) || p.name, false);
        else refreshPeerName(p.peer_id, peerNames.get(p.peer_id) || p.name);
    });
    updateCount();
    maybeAutoStopRecording();
}

// Se estou gravando e fiquei sozinho na sala (todos saíram), finaliza e envia.
let autoStopTimer = null;
function maybeAutoStopRecording() {
    const recording = mediaRecorder && mediaRecorder.state !== 'inactive';
    if (recording && peers.size === 0) {
        if (!autoStopTimer) {
            // aguarda 5s para evitar parar por uma reconexão momentânea
            autoStopTimer = setTimeout(() => {
                autoStopTimer = null;
                if (mediaRecorder && mediaRecorder.state !== 'inactive' && peers.size === 0) {
                    toast('Todos saíram: finalizando a gravação…');
                    stopRecording();
                }
            }, 5000);
        }
    } else if (autoStopTimer) {
        clearTimeout(autoStopTimer); autoStopTimer = null;
    }
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
    broadcastMyState();
    if (typeof syncPipButtons === 'function') syncPipButtons();
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
    broadcastMyState();
    if (typeof syncPipButtons === 'function') syncPipButtons();
}

async function toggleScreen(ev) {
    // Se já está compartilhando, abre o menu (Parar / Trocar tela).
    if (sharing) { openScreenMenu(ev); return; }
    // Restrição do admin: revalida no servidor (à prova de sinal 'perm' perdido).
    if (!isAdmin) {
        try {
            const pv = await fetch(`${BASE}/videocall/preview/${ROOM_TOKEN}`).then(x => x.json());
            if (pv && typeof pv.allow_presentation !== 'undefined') { allowPresentation = !!pv.allow_presentation; applyPresentationPerm(allowPresentation); }
        } catch (e) {}
        if (!allowPresentation) {
            toast('O administrador desativou o compartilhamento de tela nesta sala.');
            return;
        }
    }
    if (!navigator.mediaDevices || typeof navigator.mediaDevices.getDisplayMedia !== 'function') {
        toast('Seu navegador não permite compartilhar a tela. No celular, isso costuma funcionar só em alguns navegadores (tente o Chrome mais recente) ou pelo computador.');
        return;
    }
    // O próprio seletor de tela do navegador já oferece a opção "compartilhar áudio".
    await startScreenShare(true);
}

// Inicia (ou troca) o compartilhamento de tela. replaceExisting = trocar a tela atual.
// Pedimos audio:true SEMPRE — quem decide é a caixa do navegador (marcar ou não).
async function startScreenShare(withAudio, replaceExisting) {
    let newStream;
    try {
        newStream = await navigator.mediaDevices.getDisplayMedia({
            video: { width: { ideal: 1920 }, height: { ideal: 1080 }, frameRate: { ideal: 10, max: 15 } },
            audio: true
        });
    } catch (e) {
        if (e && (e.name === 'NotAllowedError' || e.name === 'AbortError')) return; // usuário cancelou
        toast('Não foi possível compartilhar a tela neste dispositivo.');
        return;
    }
    if (!newStream) return;

    // Se estava compartilhando (trocar), encerra a anterior sem avisar "parou".
    if (sharing && screenStream) { cleanupScreen(false); }

    screenStream = newStream;
    sharing = true;
    document.getElementById('btn-screen').classList.add('active');

    let selfScreen = tileEl(peerId + '-screen');
    if (!selfScreen) selfScreen = makeTile(peerId + '-screen', myName + ' (sua tela)', { screen: true });
    selfScreen.querySelector('video').srcObject = screenStream;

    const screenTrack = screenStream.getVideoTracks()[0];
    const screenAudio = screenStream.getAudioTracks()[0];
    // Publica a tela (e o áudio dela) em todos e ANUNCIA o trackId da tela.
    peers.forEach((entry) => {
        entry.pc.addTrack(screenTrack, screenStream);
        if (screenAudio) entry.pc.addTrack(screenAudio, screenStream);
    });
    broadcast('screen', { start: true, trackId: screenTrack.id });
    sfx('screen');

    // Quando o usuário para pelo controle nativo do navegador.
    screenTrack.onended = () => stopScreen();
}

// Menu flutuante ao clicar em compartilhar já ativo: Trocar tela / Parar.
function openScreenMenu(ev) {
    if (ev) ev.stopPropagation(); // evita que o listener global feche no mesmo clique
    const menu = document.getElementById('screen-menu');
    if (!menu) { stopScreen(); return; }
    if (menu.style.display === 'block') { menu.style.display = 'none'; return; }
    positionPopover('screen-menu', document.getElementById('btn-screen'));
}
function screenMenuAction(act) {
    const menu = document.getElementById('screen-menu');
    if (menu) menu.style.display = 'none';
    if (act === 'switch') startScreenShare(true, true);
    else if (act === 'stop') stopScreen();
}

// Encerra as tracks/tile da tela. announce=true avisa a sala que parou.
function cleanupScreen(announce) {
    if (screenStream) {
        screenStream.getTracks().forEach(t => {
            t.stop();
            peers.forEach((entry) => { const s = entry.pc.getSenders().find(x => x.track === t); if (s) { try { entry.pc.removeTrack(s); } catch (e) {} } });
        });
    }
    removeTile(peerId + '-screen');
    screenStream = null;
    if (announce) broadcast('screen', { stop: true });
}

function stopScreen() {
    if (!sharing) return;
    sharing = false;
    document.getElementById('btn-screen').classList.remove('active');
    cleanupScreen(true);
}

// Menu de câmera / dispositivos / fundo (popover)
function openCamMenu(ev) {
    ev.stopPropagation();
    populateDevices(); renderBgGrids();
    positionPopover('cam-menu', ev.currentTarget);
}
// Setinha do microfone: só a escolha do microfone.
function openMicMenu(ev) {
    ev.stopPropagation();
    populateDevices();
    positionPopover('mic-menu', ev.currentTarget);
}
function positionPopover(menuId, anchor) {
    const menu = document.getElementById(menuId);
    const open = menu.style.display === 'block';
    // Fecha todos os popovers antes.
    document.querySelectorAll('.popover-menu').forEach(m => m.style.display = 'none');
    if (open) return;
    menu.style.display = 'block';
    const r = anchor.getBoundingClientRect();
    const mw = menu.offsetWidth || 300;
    let left = r.left + r.width / 2 - mw / 2; if (left < 8) left = 8;
    if (left + mw > window.innerWidth) left = window.innerWidth - mw - 8;
    menu.style.left = left + 'px';
    menu.style.bottom = (window.innerHeight - r.top + 10) + 'px';
    menu.style.top = 'auto';
}
document.addEventListener('click', (e) => {
    document.querySelectorAll('.popover-menu').forEach(menu => {
        if (menu.style.display === 'block' && !menu.contains(e.target) && !e.target.closest('.ctrl-caret') && !e.target.closest('#btn-screen')) menu.style.display = 'none';
    });
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

// Sons curtos de experiência (entrar/sair/tela). Evita spam com throttle.
let lastSfx = {};
function sfx(kind) {
    // Se estou compartilhando a tela COM áudio do sistema, os efeitos sonoros
    // sairiam pelos alto-falantes e seriam recapturados (loop de eco). Nesse
    // caso, silencia só os efeitos de interface — o áudio das pessoas segue normal.
    if (sharing && screenStream && screenStream.getAudioTracks().length > 0) return;
    const now = Date.now();
    if (lastSfx[kind] && now - lastSfx[kind] < 400) return; // não repete em rajada
    lastSfx[kind] = now;
    // Notas por evento (subindo = entrar/positivo; descendo = sair).
    const tones = {
        join:      [523, 784],   // dó->sol (alguém entrou)
        leave:     [523, 349],   // dó->fá abaixo (alguém saiu)
        selfjoin:  [523, 659, 784],
        selfleave: [659, 392],
        screen:    [440, 660],
    };
    const seq = tones[kind] || [600];
    try {
        audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
        const g = audioCtx.createGain(); g.connect(audioCtx.destination);
        g.gain.value = 0.06;
        const step = 0.09;
        seq.forEach((f, i) => {
            const o = audioCtx.createOscillator();
            o.type = 'sine'; o.frequency.value = f;
            o.connect(g);
            o.start(audioCtx.currentTime + i * step);
            o.stop(audioCtx.currentTime + i * step + step + 0.02);
        });
    } catch (e) {}
}

// Som curto e característico de cada emoji de reação (WebAudio, sem arquivos).
let lastEmojiSound = 0;
function emojiSound(emoji) {
    // Não toca se estiver compartilhando tela COM áudio (evita loop de eco).
    if (sharing && screenStream && screenStream.getAudioTracks().length > 0) return;
    const now = Date.now();
    if (now - lastEmojiSound < 120) return; // evita estouro em rajada
    lastEmojiSound = now;
    try {
        audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
        const ctx = audioCtx;
        if (ctx.state === 'suspended') ctx.resume();

        // --- Blocos de síntese reutilizáveis (som mais orgânico, menos "beep") ---

        // Nota com envelope suave (attack/release) + filtro passa-baixa.
        // Timbre "sino/marimba" em vez de onda crua. Pequeno detune dá naturalidade.
        function note(freq, at, dur, gain, type) {
            const o = ctx.createOscillator();
            const o2 = ctx.createOscillator(); // 2ª voz levemente desafinada
            const g = ctx.createGain();
            const lp = ctx.createBiquadFilter();
            lp.type = 'lowpass'; lp.frequency.value = Math.min(6000, freq * 6);
            o.type = type || 'triangle'; o.frequency.value = freq;
            o2.type = 'sine'; o2.frequency.value = freq * 1.005;
            o.connect(lp); o2.connect(lp); lp.connect(g); g.connect(ctx.destination);
            const t = ctx.currentTime + at;
            g.gain.setValueAtTime(0.0001, t);
            g.gain.exponentialRampToValueAtTime(gain, t + 0.012);      // attack rápido
            g.gain.exponentialRampToValueAtTime(0.0001, t + dur);      // release natural
            o.start(t); o2.start(t); o.stop(t + dur + 0.02); o2.stop(t + dur + 0.02);
        }

        // Rajada de ruído filtrado — base para "palma" e "confete/festa".
        function noiseBurst(at, dur, peak, freq, q) {
            const n = Math.floor(ctx.sampleRate * dur);
            const buf = ctx.createBuffer(1, n, ctx.sampleRate);
            const d = buf.getChannelData(0);
            for (let i = 0; i < n; i++) d[i] = (Math.random() * 2 - 1);
            const src = ctx.createBufferSource(); src.buffer = buf;
            const bp = ctx.createBiquadFilter(); bp.type = 'bandpass';
            bp.frequency.value = freq || 1800; bp.Q.value = q || 0.9;
            const g = ctx.createGain();
            src.connect(bp); bp.connect(g); g.connect(ctx.destination);
            const t = ctx.currentTime + at;
            g.gain.setValueAtTime(0.0001, t);
            g.gain.exponentialRampToValueAtTime(peak, t + 0.004);      // transiente seco
            g.gain.exponentialRampToValueAtTime(0.0001, t + dur);
            src.start(t); src.stop(t + dur + 0.02);
        }

        switch (emoji) {
            case '👏': { // palmas de verdade: várias batidas de ruído (aplauso/festa)
                const times = [0, 0.11, 0.21, 0.30, 0.40, 0.52];
                times.forEach((tt, i) => noiseBurst(tt, 0.09, 0.16 - i * 0.012, 1900 + Math.random() * 500, 0.7));
                break;
            }
            case '🎉': { // festa: "confete" (ruído) + acorde alegre ascendente
                noiseBurst(0, 0.22, 0.10, 3200, 0.5);
                noiseBurst(0.02, 0.3, 0.06, 1400, 0.6);
                [523, 659, 784, 1047].forEach((f, i) => note(f, 0.02 + i * 0.05, 0.32, 0.09, 'triangle'));
                break;
            }
            case '❤️': { // acorde suave e caloroso
                [392, 523, 659].forEach((f, i) => note(f, i * 0.015, 0.55, 0.08, 'sine'));
                break;
            }
            case '😂': { // risada saltitante (notas curtas alternadas)
                [784, 988, 784, 988, 660].forEach((f, i) => note(f, i * 0.07, 0.14, 0.08, 'triangle'));
                break;
            }
            case '👍': { // positivo, dois toques ascendentes
                note(660, 0, 0.16, 0.09, 'triangle');
                note(990, 0.09, 0.22, 0.09, 'triangle');
                break;
            }
            case '😮': { // surpresa: sobe e segura
                note(523, 0, 0.14, 0.08, 'sine');
                note(880, 0.09, 0.30, 0.08, 'sine');
                break;
            }
            default: {
                note(700, 0, 0.2, 0.08, 'triangle');
                note(1050, 0.08, 0.24, 0.07, 'triangle');
            }
        }
    } catch (e) {}
}

// =====================================================================
// Indicador de "quem está falando" (borda no tile), estilo Google Meet.
// Analisa o nível de áudio de cada stream (local + remotos) via WebAudio e
// alterna a classe .speaking no tile, com histerese para não piscar.
// =====================================================================
const speakingMon = new Map(); // id -> { analyser, data, src, speaking, silentFrames }
let speakingLoopOn = false;

function attachSpeaking(id, stream) {
    try {
        if (!stream || stream.getAudioTracks().length === 0) return;
        audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
        if (audioCtx.state === 'suspended') audioCtx.resume();
        // Remove um monitor anterior deste id (ex.: troca de microfone).
        detachSpeaking(id);
        const src = audioCtx.createMediaStreamSource(stream);
        const analyser = audioCtx.createAnalyser();
        analyser.fftSize = 512;
        analyser.smoothingTimeConstant = 0.6;
        src.connect(analyser); // não conecta ao destino (evita eco/duplicar áudio)
        speakingMon.set(id, { analyser, data: new Uint8Array(analyser.fftSize), src, speaking: false, silentFrames: 0 });
        if (!speakingLoopOn) { speakingLoopOn = true; requestAnimationFrame(speakingLoop); }
    } catch (e) {}
}

function detachSpeaking(id) {
    const m = speakingMon.get(id);
    if (m) { try { m.src.disconnect(); } catch (e) {} speakingMon.delete(id); }
    tileEl(id)?.classList.remove('speaking');
}

let _spkLast = 0;
function speakingLoop(ts) {
    if (!speakingLoopOn) return;
    // ~15 fps é suficiente e leve para o celular.
    if (ts - _spkLast >= 66) {
        _spkLast = ts;
        speakingMon.forEach((m, id) => {
            const t = tileEl(id);
            if (!t) return;
            // Se estiver mutado, nunca marca como falando.
            const muted = (id === peerId) ? !micOn : t.classList.contains('mic-off');
            let level = 0;
            if (!muted) {
                m.analyser.getByteTimeDomainData(m.data);
                let sum = 0;
                for (let i = 0; i < m.data.length; i++) { const v = (m.data[i] - 128) / 128; sum += v * v; }
                level = Math.sqrt(sum / m.data.length); // RMS 0..1
            }
            const THRESH = 0.045; // limiar de voz
            if (level > THRESH) {
                m.silentFrames = 0;
                if (!m.speaking) { m.speaking = true; t.classList.add('speaking'); }
            } else {
                // Histerese: só apaga após alguns quadros em silêncio (evita piscar).
                if (m.speaking && ++m.silentFrames > 8) { m.speaking = false; t.classList.remove('speaking'); }
            }
        });
    }
    requestAnimationFrame(speakingLoop);
}

// Remove um tile com animação de saída.
function animateTileOut(id) {
    const t = tileEl(id);
    if (!t) return;
    detachSpeaking(id);
    t.classList.add('tile-out');
    setTimeout(() => { if (t.parentNode) { t.remove(); tileZoom.delete(id); tilePan.delete(id); pinned.delete(id); layoutGrid(); } }, 220);
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

// ---- Permissão de apresentar (compartilhar tela) ----
function applyPresentationPerm(allow) {
    allowPresentation = allow;
    // Se compartilhar foi bloqueado e um não-admin está compartilhando, para.
    if (!allow && !isAdmin && sharing) { stopScreen(); toast('O administrador desativou o compartilhamento de tela.'); }
    const btn = document.getElementById('btn-screen');
    if (btn) {
        const blocked = (!allow && !isAdmin);
        btn.classList.toggle('disabled-ctrl', blocked);
        btn.title = blocked ? 'Compartilhamento desativado pelo administrador' : 'Compartilhar tela';
    }
    updateAdminPermButton();
}
function updateAdminPermButton() {
    const b = document.getElementById('sp-perm-btn');
    if (!b) return;
    b.textContent = allowPresentation ? 'Desativar apresentação' : 'Ativar apresentação';
    b.className = 'btn btn-sm ' + (allowPresentation ? 'btn-outline-warning' : 'btn-success');
    const st = document.getElementById('sp-perm-state');
    if (st) st.textContent = allowPresentation ? 'Qualquer um pode apresentar' : 'Só administradores apresentam';
}
async function togglePresentationPerm() {
    const novo = allowPresentation ? '0' : '1';
    try {
        const r = await fetch(`${BASE}/videocall/setPresentation/${ROOM_TOKEN}`, { method: 'POST', body: new URLSearchParams({ allow: novo }) }).then(x => x.json());
        if (r.error) { toast(r.error); return; }
        applyPresentationPerm(!!r.allow_presentation);
        toast(allowPresentation ? 'Apresentação liberada para todos.' : 'Apresentação restrita aos administradores.');
    } catch (e) { toast('Erro ao alterar a permissão.'); }
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
    spawnEmojiRain(emoji);                 // chuva local
    showReactionBadge(peerId, emoji, myName + ' (você)'); // badge no meu tile
    broadcast('reaction', { emoji, name: myName });        // e para todos (com origem)
}

// Mostra a reação na webcam de quem reagiu, por alguns segundos.
const reactionTimers = new Map();
function showReactionBadge(pid, emoji, name) {
    const t = tileEl(pid); if (!t) return;
    const badge = t.querySelector('.reaction-badge'); if (!badge) return;
    badge.querySelector('.emo').textContent = emoji;
    badge.querySelector('.who').textContent = name || '';
    t.classList.add('reacting');
    // reinicia a animação do emoji
    const emo = badge.querySelector('.emo');
    emo.style.animation = 'none'; void emo.offsetWidth; emo.style.animation = '';
    if (reactionTimers.has(pid)) clearTimeout(reactionTimers.get(pid));
    reactionTimers.set(pid, setTimeout(() => { t.classList.remove('reacting'); reactionTimers.delete(pid); }, 4000));
}

// Reações recentes (para desenhar também na GRAVAÇÃO/PiP).
const activeReactions = [];
// Chuva de emojis (leve; menos elementos no celular; limite global anti-travamento).
function spawnEmojiRain(emoji) {
    const layer = document.getElementById('emoji-rain');
    if (!layer) return;
    // Som curto e próprio de cada emoji, junto com a chuvinha.
    emojiSound(emoji);
    // Registra para a gravação (some após 3,4s).
    activeReactions.push({ emoji, born: Date.now() });
    if (activeReactions.length > 30) activeReactions.splice(0, activeReactions.length - 30);

    // Limite de elementos vivos na tela (evita travar em rajada).
    if (layer.childElementCount > 40) return;
    const count = IS_MOBILE ? 4 : 6;
    for (let i = 0; i < count; i++) {
        const el = document.createElement('div');
        el.className = 'rain-emoji';
        el.textContent = emoji;
        el.style.left = (10 + Math.random() * 80) + 'vw';
        el.style.fontSize = (1.4 + Math.random() * 1.4) + 'rem';
        el.style.animationDelay = (Math.random() * 0.4) + 's';
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
    // Sinal imediato (resposta rápida) + estado consolidado (autocorreção via heartbeat).
    broadcast('hand', { up: handUp, name: myName });
    broadcastMyState();
    renderHands();
    if (handUp) toast('Você levantou a mão.');
}

function onRemoteHand(from, payload) {
    const up = !!(payload && payload.up);
    const name = (payload && payload.name) || peerNames.get(from) || 'Convidado';
    if (payload && payload.name) peerNames.set(from, payload.name);
    tileEl(from)?.classList.toggle('hand-up', up);
    if (up) { if (!raisedHands.has(from)) raisedHands.set(from, { name, ts: Date.now() }); else raisedHands.get(from).name = name; }
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
// ---- Composição da gravação: grava a REUNIÃO TODA (todas as câmeras + telas
// + áudios de todos) desenhando um mosaico num canvas e mixando o áudio. ----
let compCanvas = null, compCtx = null, compRaf = null, compStream = null;
let recAudioCtx = null, audioDest = null, audioSources = [];

function collectRecordingVideos() {
    // Coleta os elementos <video> visíveis (tiles) + a própria câmera.
    const vids = [];
    document.querySelectorAll('#stage .tile video, #grid .tile video, #filmstrip .tile video').forEach(v => {
        if (v.srcObject && v.videoWidth > 0) vids.push(v);
    });
    if (!vids.length) {
        const self = document.querySelector('#tile-' + peerId + ' video');
        if (self) vids.push(self);
    }
    return vids;
}

// Separa telas compartilhadas das câmeras (o tile de tela tem id terminando em -screen).
function collectComposeSources() {
    const all = collectRecordingVideos();
    const screens = [], cams = [];
    all.forEach(v => {
        const tile = v.closest('.tile');
        const id = tile ? tile.dataset.tid : '';
        if (id && isScreenTile(id)) screens.push(v); else cams.push(v);
    });
    return { screens, cams };
}

// Desenha um vídeo numa região (x,y,w,h) com "cover" e o nome no canto.
function drawTileVideo(ctx, v, x, y, w, h, opts) {
    opts = opts || {};
    const tile = v.closest('.tile');
    const camOff = tile && tile.classList.contains('cam-off');
    const micOff = tile && tile.classList.contains('mic-off');
    const vw = v.videoWidth || 16, vh = v.videoHeight || 9;

    ctx.save();
    ctx.imageSmoothingEnabled = true; ctx.imageSmoothingQuality = 'high';
    ctx.beginPath(); ctx.rect(x + 2, y + 2, w - 4, h - 4); ctx.clip();

    if (camOff) {
        // Câmera desligada: fundo + avatar com a inicial (igual à reunião).
        ctx.fillStyle = '#23263d'; ctx.fillRect(x, y, w, h);
        const nm = (opts.name || 'C').trim();
        const initial = nm.replace(/\s*\(.*$/, '').trim().charAt(0).toUpperCase() || 'C';
        const r = Math.max(24, Math.min(w, h) * 0.18);
        const cx = x + w / 2, cy = y + h / 2;
        ctx.fillStyle = '#00BFA6';
        ctx.beginPath(); ctx.arc(cx, cy, r, 0, Math.PI * 2); ctx.fill();
        ctx.fillStyle = '#fff';
        ctx.font = '700 ' + Math.round(r) + 'px system-ui, Arial, sans-serif';
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.fillText(initial, cx, cy + 1);
        ctx.textAlign = 'start'; ctx.textBaseline = 'alphabetic';
    } else {
        // Tela usa "contain" (mostra tudo); câmera usa "cover".
        const scale = opts.contain ? Math.min(w / vw, h / vh) : Math.max(w / vw, h / vh);
        const dw = vw * scale, dh = vh * scale;
        const dx = x + (w - dw) / 2, dy = y + (h - dh) / 2;
        if (opts.contain) { ctx.fillStyle = '#000'; ctx.fillRect(x, y, w, h); }
        try { ctx.drawImage(v, dx, dy, dw, dh); } catch (e) {}
    }
    ctx.restore();

    if (opts.name) {
        const nm = opts.name;
        const fs = Math.max(12, Math.min(18, Math.round(w * 0.022)));
        ctx.font = '600 ' + fs + 'px system-ui, Arial, sans-serif';
        const padX = 8;
        // Deixa espaço para o ícone de mic mutado antes do nome.
        const micIcoW = micOff ? (fs + 8) : 0;
        const tw = ctx.measureText(nm).width + padX * 2 + micIcoW;
        const bh = fs + 8;
        const bx = x + 8, by = y + h - bh - 8;
        ctx.fillStyle = 'rgba(0,0,0,.6)';
        ctx.fillRect(bx, by, tw, bh);
        if (micOff) drawMicMutedIcon(ctx, bx + padX, by + bh / 2, fs);
        ctx.fillStyle = '#fff';
        ctx.textBaseline = 'middle';
        ctx.fillText(nm, bx + padX + micIcoW, by + bh / 2);
        ctx.textBaseline = 'alphabetic';
    }
}

// Desenha um ícone simples de "microfone mutado" (corpo do mic + barra diagonal).
function drawMicMutedIcon(ctx, cx, cy, size) {
    const s = size * 0.9;
    ctx.save();
    ctx.strokeStyle = '#ff9db0'; ctx.fillStyle = '#ff9db0';
    ctx.lineWidth = Math.max(1.5, s * 0.09);
    // corpo do microfone
    const mw = s * 0.34, mh = s * 0.6;
    const mx = cx + s * 0.15, my = cy - mh / 2;
    ctx.beginPath();
    if (ctx.roundRect) ctx.roundRect(mx, my, mw, mh, mw / 2); else ctx.rect(mx, my, mw, mh);
    ctx.fill();
    // base
    ctx.beginPath();
    ctx.arc(mx + mw / 2, my + mh, mw * 0.8, 0, Math.PI, false);
    ctx.stroke();
    ctx.beginPath();
    ctx.moveTo(mx + mw / 2, my + mh + mw * 0.8); ctx.lineTo(mx + mw / 2, my + mh + mw * 1.2);
    ctx.stroke();
    // barra diagonal (mutado)
    ctx.strokeStyle = '#ff5470'; ctx.lineWidth = Math.max(1.6, s * 0.1);
    ctx.beginPath(); ctx.moveTo(cx + s * 0.02, cy - s * 0.5); ctx.lineTo(cx + s * 0.7, cy + s * 0.5); ctx.stroke();
    ctx.restore();
}

function nameOf(v) { const t = v.closest('.tile'); return t ? (t.querySelector('.name')?.textContent || '') : ''; }

/**
 * Layout de composição (usado na gravação e no PiP):
 * - Com tela compartilhada: modo apresentador (tela grande + câmeras na faixa).
 * - Sem tela: grade normal das câmeras.
 */
function composeLayout(ctx, W, H) {
    ctx.fillStyle = '#0f1020'; ctx.fillRect(0, 0, W, H);
    const { screens, cams } = collectComposeSources();

    if (screens.length > 0) {
        // ----- Modo apresentador -----
        const others = cams.concat(screens.slice(1)); // câmeras + telas extras
        const stripW = others.length ? Math.round(W * 0.24) : 0; // faixa à direita
        const mainW = W - stripW;
        // Tela principal ocupa a área grande (contain para ler o conteúdo).
        drawTileVideo(ctx, screens[0], 0, 0, mainW, H, { contain: true, name: nameOf(screens[0]) });
        // Faixa lateral com as câmeras (e telas extras), empilhadas.
        if (others.length) {
            const cellH = H / others.length;
            others.forEach((v, i) => {
                const scr = screens.indexOf(v) > 0;
                drawTileVideo(ctx, v, mainW, i * cellH, stripW, cellH, { contain: scr, name: nameOf(v) });
            });
        }
    } else {
        // ----- Grade normal das câmeras -----
        const vids = cams.length ? cams : collectRecordingVideos();
        const n = vids.length || 1;
        const cols = Math.ceil(Math.sqrt(n));
        const rows = Math.ceil(n / cols);
        const cw = W / cols, ch = H / rows;
        vids.forEach((v, i) => {
            drawTileVideo(ctx, v, (i % cols) * cw, Math.floor(i / cols) * ch, cw, ch, { name: nameOf(v) });
        });
    }

    // Reações também aparecem na gravação/PiP (emojis subindo).
    drawReactionsOnCanvas(ctx, W, H);
}

// Desenha os emojis recentes subindo no canvas (reflete as reações na gravação).
function drawReactionsOnCanvas(ctx, W, H) {
    if (!activeReactions.length) return;
    const now = Date.now();
    for (let i = activeReactions.length - 1; i >= 0; i--) {
        const r = activeReactions[i];
        const age = now - r.born;
        if (age > 3400) { activeReactions.splice(i, 1); continue; }
        const p = age / 3400;               // 0..1
        const y = H * (0.82 - p * 0.6);     // sobe
        const x = W * (0.12 + ((i * 137) % 76) / 100); // espalha horizontalmente
        ctx.save();
        ctx.globalAlpha = p < 0.15 ? (p / 0.15) : (1 - Math.max(0, (p - 0.7) / 0.3));
        ctx.font = Math.round(H * 0.06) + 'px system-ui, Arial, sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText(r.emoji, x, y);
        ctx.restore();
    }
}

function drawComposite() {
    if (!compCtx) return;
    composeLayout(compCtx, compCanvas.width, compCanvas.height);
    compRaf = requestAnimationFrame(drawComposite);
}

function buildRecordingStream() {
    // Canvas de vídeo composto em alta (para a tela compartilhada ficar legível).
    compCanvas = document.createElement('canvas');
    compCanvas.width = 1920; compCanvas.height = 1080;
    compCtx = compCanvas.getContext('2d');
    drawComposite();
    compStream = compCanvas.captureStream(25);

    // Mixa TODOS os áudios (meu microfone + áudio de cada participante remoto).
    recAudioCtx = new (window.AudioContext || window.webkitAudioContext)();
    audioDest = recAudioCtx.createMediaStreamDestination();
    audioSources = [];
    const addAudio = (stream) => {
        if (!stream) return;
        const at = stream.getAudioTracks();
        if (!at.length) return;
        try { const src = recAudioCtx.createMediaStreamSource(stream); src.connect(audioDest); audioSources.push(src); } catch (e) {}
    };
    if (rawStream) addAudio(rawStream);
    peers.forEach(entry => { const remote = entry.tile?.querySelector('video')?.srcObject; if (remote) addAudio(remote); });

    const out = new MediaStream();
    compStream.getVideoTracks().forEach(t => out.addTrack(t));
    audioDest.stream.getAudioTracks().forEach(t => out.addTrack(t));
    return out;
}

function stopComposite() {
    if (compRaf) cancelAnimationFrame(compRaf);
    compRaf = null;
    if (compStream) { compStream.getTracks().forEach(t => t.stop()); compStream = null; }
    audioSources.forEach(s => { try { s.disconnect(); } catch (e) {} });
    audioSources = [];
    if (recAudioCtx) { try { recAudioCtx.close(); } catch (e) {} recAudioCtx = null; }
    compCanvas = null; compCtx = null;
}
let recElapsedMs = 0, recResumeTs = 0; // controle de tempo com pausa

function toggleRecording() { if (mediaRecorder && mediaRecorder.state !== 'inactive') { stopRecording(); return; } startRecording(); }
function startRecording() {
    let mime = 'video/webm;codecs=vp9,opus';
    if (!MediaRecorder.isTypeSupported(mime)) mime = 'video/webm;codecs=vp8,opus';
    if (!MediaRecorder.isTypeSupported(mime)) mime = 'video/webm';
    try { recordedChunks = []; mediaRecorder = new MediaRecorder(buildRecordingStream(), { mimeType: mime }); }
    catch (e) { toast('Este navegador não suporta gravação.'); return; }

    // Sessão de gravação: os pedaços são ENVIADOS progressivamente ao servidor.
    // Se cair no meio, o que já subiu não se perde.
    recSessId = 'r' + Math.random().toString(36).slice(2, 12) + Date.now().toString(36).slice(-4);
    recUploadChain = Promise.resolve();

    mediaRecorder.ondataavailable = (e) => {
        if (e.data && e.data.size) {
            recordedChunks.push(e.data);           // mantém cópia local (fallback)
            uploadChunk(e.data);                   // e envia o pedaço já
        }
    };
    mediaRecorder.onstop = uploadRecording;
    mediaRecorder.start(4000); // um pedaço a cada 4s
    recStartTs = Date.now();
    recElapsedMs = 0; recResumeTs = Date.now();
    document.getElementById('btn-rec').classList.add('off');
    const pauseBtn = document.getElementById('btn-rec-pause');
    if (pauseBtn) { pauseBtn.style.display = 'flex'; pauseBtn.innerHTML = '<i class="bi bi-pause-fill"></i>'; }
    setRecIndicator('rec');
    recTimer = setInterval(updateRecTime, 500);
    toast('Gravação iniciada. Mantenha esta aba aberta.');
    broadcast('rec', { state: 'start', by: myName });
    if (typeof syncPipButtons === 'function') syncPipButtons();
}
function updateRecTime() {
    const ms = recElapsedMs + (recResumeTs ? (Date.now() - recResumeTs) : 0);
    const s = Math.floor(ms / 1000);
    document.getElementById('rec-time').textContent = String(Math.floor(s / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0');
}
function togglePauseRecording() {
    if (!mediaRecorder) return;
    const btn = document.getElementById('btn-rec-pause');
    if (mediaRecorder.state === 'recording') {
        mediaRecorder.pause();
        recElapsedMs += (Date.now() - recResumeTs); recResumeTs = 0;
        if (btn) btn.innerHTML = '<i class="bi bi-play-fill"></i>';
        document.getElementById('rec-label').textContent = 'Pausado';
        toast('Gravação pausada.');
        broadcast('rec', { state: 'pause', by: myName });
    } else if (mediaRecorder.state === 'paused') {
        mediaRecorder.resume();
        recResumeTs = Date.now();
        if (btn) btn.innerHTML = '<i class="bi bi-pause-fill"></i>';
        document.getElementById('rec-label').textContent = 'Gravando';
        toast('Gravação retomada.');
        broadcast('rec', { state: 'resume', by: myName });
    }
}
function stopRecording() {
    if (mediaRecorder && mediaRecorder.state !== 'inactive') mediaRecorder.stop();
    clearInterval(recTimer);
    recResumeTs = 0;
    stopComposite();
    document.getElementById('btn-rec').classList.remove('off');
    const pauseBtn = document.getElementById('btn-rec-pause');
    if (pauseBtn) pauseBtn.style.display = 'none';
    document.getElementById('rec-label').textContent = 'Gravando';
    setRecIndicator('off');
    broadcast('rec', { state: 'stop', by: myName });
    if (typeof syncPipButtons === 'function') syncPipButtons();
}

// Indicador global de gravação (mostra pra todos que a reunião está sendo gravada).
function setRecIndicator(state) {
    const ind = document.getElementById('rec-indicator');
    if (!ind) return;
    if (state === 'off') { ind.style.display = 'none'; return; }
    ind.style.display = 'flex';
    ind.style.opacity = (state === 'pause') ? '.6' : '1';
}
// Quando eu NÃO sou quem grava, mostro só o aviso "sendo gravada" (sem cronômetro editável).
function showRemoteRecState(payload) {
    const st = payload && payload.state;
    const ind = document.getElementById('rec-indicator');
    const label = document.getElementById('rec-label');
    const timeEl = document.getElementById('rec-time');
    if (!ind) return;
    // Só reflete o estado remoto se EU não estiver gravando localmente.
    if (mediaRecorder && mediaRecorder.state !== 'inactive') return;
    if (st === 'stop') { ind.style.display = 'none'; return; }
    ind.style.display = 'flex';
    if (timeEl) timeEl.textContent = '';
    if (label) label.textContent = (st === 'pause') ? ('Gravação pausada' + (payload.by ? ' · ' + payload.by : '')) : ('Sendo gravada' + (payload.by ? ' · ' + payload.by : ''));
    ind.style.opacity = (st === 'pause') ? '.6' : '1';
}
// Envia um pedaço da gravação ao servidor (em fila, para manter a ordem).
let recSessId = null, recUploadChain = Promise.resolve(), recChunksSent = 0;
function uploadChunk(blob) {
    if (!recSessId) return;
    const sess = recSessId;
    recUploadChain = recUploadChain.then(async () => {
        const fd = new FormData();
        fd.append('sess', sess);
        fd.append('chunk', blob, 'c.webm');
        try { await fetch(`${BASE}/videocall/recChunk/${ROOM_TOKEN}`, { method: 'POST', body: fd }); recChunksSent++; }
        catch (e) { /* pedaço falhou; o fallback local (recordedChunks) cobre no fim */ }
    });
}

// Ao parar: finaliza a gravação por streaming (junta os pedaços no servidor).
// Se por algum motivo nada foi enviado por streaming, cai no upload do arquivo inteiro.
async function uploadRecording() {
    const durationSec = Math.floor((Date.now() - recStartTs) / 1000);
    const sess = recSessId;
    recSessId = null;

    // Espera os pedaços pendentes subirem.
    try { await recUploadChain; } catch (e) {}

    if (sess && recChunksSent > 0) {
        toast('Finalizando a gravação no servidor…');
        try {
            const fd = new FormData();
            fd.append('sess', sess); fd.append('duration_sec', durationSec); fd.append('recorded_by_name', myName);
            const res = await fetch(`${BASE}/videocall/recFinalize/${ROOM_TOKEN}`, { method: 'POST', body: fd }).then(r => r.json());
            if (!res.error) {
                currentRecToken = res.token || null;
                document.getElementById('rec-url').value = res.url; document.getElementById('rec-open').href = res.url;
                document.getElementById('rec-modal').style.display = 'flex';
                recordedChunks = []; recChunksSent = 0;
                return;
            }
        } catch (e) { /* cai para o upload inteiro abaixo */ }
    }

    // Fallback: envia o arquivo inteiro (gravações curtas ou se o streaming falhou).
    if (!recordedChunks.length) return;
    const blob = new Blob(recordedChunks, { type: 'video/webm' });
    toast('Enviando gravação para o servidor…');
    const fd = new FormData();
    fd.append('recording', blob, 'gravacao.webm'); fd.append('duration_sec', durationSec); fd.append('recorded_by_name', myName);
    try {
        const res = await fetch(`${BASE}/videocall/upload/${ROOM_TOKEN}`, { method: 'POST', body: fd }).then(r => r.json());
        if (res.error) { toast('Erro ao salvar: ' + res.error); return; }
        currentRecToken = res.token || null;
        document.getElementById('rec-url').value = res.url; document.getElementById('rec-open').href = res.url;
        document.getElementById('rec-modal').style.display = 'flex';
    } catch (e) { toast('Falha ao enviar a gravação.'); }
    recChunksSent = 0;
}

// Token da última gravação (usado apenas para exibir o link no pop-up).
let currentRecToken = null;

// ==========================================================
// PICTURE-IN-PICTURE (janelinha flutuante com a reunião toda)
// ==========================================================
let pipCanvas = null, pipCtx = null, pipVideo = null, pipStream = null, pipTimer = null;

let docPipWin = null; // janela da Document PiP (com botões)

function pipSupported() {
    return ('documentPictureInPicture' in window) || document.pictureInPictureEnabled || ('requestPictureInPicture' in document.createElement('video'));
}
function pipDraw() {
    if (!pipCtx) return;
    // Mesmo layout da gravação: com tela compartilhada, ela fica grande.
    composeLayout(pipCtx, pipCanvas.width, pipCanvas.height);
}

async function togglePip() {
    // Já aberto? fecha.
    if (docPipWin) { try { docPipWin.close(); } catch (e) {} stopPip(); return; }
    if (document.pictureInPictureElement) { try { await document.exitPictureInPicture(); } catch (e) {} return; }
    if (!pipSupported()) { toast('Seu navegador não suporta janela flutuante.'); return; }

    // Prepara o canvas do mosaico (usado nos dois modos).
    if (!pipCanvas) { pipCanvas = document.createElement('canvas'); pipCanvas.width = 1280; pipCanvas.height = 720; pipCtx = pipCanvas.getContext('2d'); }
    pipDraw();
    if (pipTimer) clearInterval(pipTimer);
    pipTimer = setInterval(pipDraw, 100);

    // 1) Document PiP (Chrome/Edge): janela com vídeo + BOTÕES funcionais.
    if ('documentPictureInPicture' in window) {
        try {
            docPipWin = await window.documentPictureInPicture.requestWindow({ width: 340, height: 250 });
            buildDocPip(docPipWin);
            docPipWin.addEventListener('pagehide', () => { docPipWin = null; stopPip(); });
            document.getElementById('btn-pip').classList.add('active');
            toast('Reunião aberta em janela flutuante com controles.');
            return;
        } catch (e) { docPipWin = null; /* cai para o modo vídeo abaixo */ }
    }

    // 2) PiP clássico de vídeo (sem botões — Safari/celular).
    try {
        pipStream = pipCanvas.captureStream(15);
        if (!pipVideo) { pipVideo = document.createElement('video'); pipVideo.muted = true; pipVideo.playsInline = true; pipVideo.addEventListener('leavepictureinpicture', stopPip); }
        pipVideo.srcObject = pipStream;
        await pipVideo.play().catch(() => {});
        await pipVideo.requestPictureInPicture();
        document.getElementById('btn-pip').classList.add('active');
        toast('Reunião aberta em janela flutuante. (Controles disponíveis apenas no Chrome/Edge.)');
    } catch (e) { toast('Não foi possível abrir a janela flutuante.'); stopPip(); }
}

// Monta o conteúdo da Document PiP: vídeo do mosaico + botões de ação.
function buildDocPip(win) {
    const doc = win.document;
    doc.body.style.cssText = 'margin:0;background:#0f1020;font-family:system-ui,Arial,sans-serif;display:flex;flex-direction:column;height:100vh;overflow:hidden;';
    // Área do vídeo (canvas do mosaico via captureStream).
    pipStream = pipCanvas.captureStream(15);
    const v = doc.createElement('video');
    v.autoplay = true; v.muted = true; v.playsInline = true; v.srcObject = pipStream;
    v.style.cssText = 'flex:1;width:100%;object-fit:contain;background:#000;min-height:0;';
    doc.body.appendChild(v);

    // Barra de botões.
    const bar = doc.createElement('div');
    bar.style.cssText = 'display:flex;gap:8px;justify-content:center;align-items:center;padding:8px;background:rgba(0,0,0,.4);';
    const mkBtn = (id, html, title) => {
        const b = doc.createElement('button');
        b.id = id; b.title = title; b.innerHTML = html;
        b.style.cssText = 'width:42px;height:42px;border-radius:50%;border:none;background:#23263d;color:#fff;font-size:1.05rem;cursor:pointer;display:flex;align-items:center;justify-content:center;';
        bar.appendChild(b); return b;
    };
    const bMic = mkBtn('pip-mic', ico(micOn ? 'mic' : 'mic-off'), 'Microfone');
    const bCam = mkBtn('pip-cam', ico(camOn ? 'cam' : 'cam-off'), 'Câmera');
    const bRec = mkBtn('pip-rec', ico('rec'), 'Gravar');
    const bEnd = mkBtn('pip-end', ico('end'), 'Sair'); bEnd.style.background = '#e02a44';
    doc.body.appendChild(bar);

    bMic.onclick = () => { toggleMic(); syncPipButtons(); };
    bCam.onclick = () => { toggleCam(); syncPipButtons(); };
    bRec.onclick = () => { toggleRecording(); setTimeout(syncPipButtons, 100); };
    bEnd.onclick = () => { try { win.close(); } catch (e) {} hangup(); };
    syncPipButtons();
}
// Ícones inline (a Document PiP não herda o Bootstrap Icons da página principal).
function ico(kind) {
    const s = 'width="20" height="20" viewBox="0 0 16 16" fill="currentColor" xmlns="http://www.w3.org/2000/svg"';
    const map = {
        'mic': `<svg ${s}><path d="M5 3a3 3 0 0 1 6 0v5a3 3 0 0 1-6 0z"/><path d="M3.5 6.5A.5.5 0 0 1 4 7v1a4 4 0 0 0 8 0V7a.5.5 0 0 1 1 0v1a5 5 0 0 1-4.5 4.975V15h2a.5.5 0 0 1 0 1h-5a.5.5 0 0 1 0-1h2v-2.025A5 5 0 0 1 3 8V7a.5.5 0 0 1 .5-.5"/></svg>`,
        'mic-off': `<svg ${s}><path d="M13 8c0 .564-.094 1.107-.266 1.613l-.814-.814A4 4 0 0 0 12 8V7a.5.5 0 0 1 1 0zM8.5 3v3.879l-1-1V3a1.5 1.5 0 0 0-2.679-.929l-.72-.72A2.5 2.5 0 0 1 8.5 3M5 6.5V8a3 3 0 0 0 4.681 2.489l.717.717A4 4 0 0 1 4 8V6.5z"/><path d="M4 8V7l-.997-.003v.917A5 5 0 0 0 7.5 12.975V15h-2a.5.5 0 0 0 0 1h5a.5.5 0 0 0 0-1h-2v-2.025q.415-.04.809-.135l-.72-.72A4 4 0 0 1 4 8m8.646 6.354-12-12 .708-.708 12 12z"/></svg>`,
        'cam': `<svg ${s}><path d="M0 5a2 2 0 0 1 2-2h7.5a2 2 0 0 1 1.983 1.738l3.11-1.382A1 1 0 0 1 16 4.269v7.462a1 1 0 0 1-1.406.913l-3.111-1.382A2 2 0 0 1 9.5 13H2a2 2 0 0 1-2-2z"/></svg>`,
        'cam-off': `<svg ${s}><path d="M10.961 12.365 2.451 3.854A2 2 0 0 0 0 5v6a2 2 0 0 0 2 2h7.5a2 2 0 0 0 1.461-.635M11.5 6.5l3.11-1.382A1 1 0 0 1 16 4.269v7.462a1 1 0 0 1-.184.575zM13.646 14.354l-12-12 .708-.708 12 12z"/></svg>`,
        'rec': `<svg ${s}><circle cx="8" cy="8" r="5"/></svg>`,
        'end': `<svg ${s}><path d="M3.654 1.328a.678.678 0 0 0-1.015-.063L1.605 2.3c-.483.484-.661 1.169-.45 1.77a17.6 17.6 0 0 0 4.168 6.608 17.6 17.6 0 0 0 6.608 4.168c.601.211 1.286.033 1.77-.45l1.034-1.034a.678.678 0 0 0-.063-1.015l-2.307-1.794a.68.68 0 0 0-.58-.122l-2.19.547a1.75 1.75 0 0 1-1.657-.459L5.482 8.062a1.75 1.75 0 0 1-.46-1.657l.548-2.19a.68.68 0 0 0-.122-.58z"/></svg>`
    };
    return map[kind] || '';
}
// Reflete o estado atual (mic/câmera/gravando) nos botões da janelinha.
function syncPipButtons() {
    if (!docPipWin) return;
    const d = docPipWin.document;
    const m = d.getElementById('pip-mic'), c = d.getElementById('pip-cam'), r = d.getElementById('pip-rec');
    if (m) { m.innerHTML = ico(micOn ? 'mic' : 'mic-off'); m.style.background = micOn ? '#23263d' : '#c0304a'; }
    if (c) { c.innerHTML = ico(camOn ? 'cam' : 'cam-off'); c.style.background = camOn ? '#23263d' : '#c0304a'; }
    const recing = mediaRecorder && mediaRecorder.state !== 'inactive';
    if (r) { r.style.background = recing ? '#c0304a' : '#23263d'; r.title = recing ? 'Parar gravação' : 'Gravar'; }
}

function stopPip() {
    if (pipTimer) { clearInterval(pipTimer); pipTimer = null; }
    if (pipStream) { pipStream.getTracks().forEach(t => t.stop()); pipStream = null; }
    if (docPipWin) { try { docPipWin.close(); } catch (e) {} docPipWin = null; }
    const btn = document.getElementById('btn-pip'); if (btn) btn.classList.remove('active');
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
    stopStateHeartbeat();
    if (document.pictureInPictureElement) { try { document.exitPictureInPicture(); } catch (e) {} }
    stopPip();
    if (mediaRecorder && mediaRecorder.state !== 'inactive') { try { stopRecording(); } catch (e) {} }
    stopBgPipeline();
    peers.forEach(e => { try { e.pc.close(); } catch (x) {} });
    if (rawStream) rawStream.getTracks().forEach(t => t.stop());
    if (screenStream) screenStream.getTracks().forEach(t => t.stop());
    // Botões de saída diferentes para logado (equipe) e convidado.
    const rejoin = '<a class="ex-btn ex-primary" href="' + BASE + '/videocall/room/' + ROOM_TOKEN + '"><i class="bi bi-box-arrow-in-right"></i> Entrar novamente</a>';
    let extra = '';
    if (IS_LOGGED) {
        extra = '<a class="ex-btn" href="' + BASE + '/dashboard"><i class="bi bi-house"></i> Voltar ao início</a>'
              + '<a class="ex-btn" href="' + BASE + '/videocall/myRecordings"><i class="bi bi-collection-play"></i> Ver gravações</a>';
    } else {
        extra = '<a class="ex-btn" href="https://onsolutionsbrasil.com.br" target="_blank"><i class="bi bi-globe"></i> Ir para o site</a>'
              + '<button class="ex-btn" onclick="try{window.close()}catch(e){};location.href=\'about:blank\'"><i class="bi bi-x-circle"></i> Fechar</button>';
    }
    document.body.innerHTML =
        '<div class="exit-screen"><div class="exit-card">' +
          '<div class="exit-ic">' + (headline.icon || '👋') + '</div>' +
          '<h3>' + headline.text + '</h3>' +
          (sub ? '<p class="exit-sub">' + sub + '</p>' : '') +
          '<div class="exit-actions">' + rejoin + extra + '</div>' +
        '</div></div>';
}
function hangup() {
    if (!joined) return; joined = false;
    try { sfx('selfleave'); } catch (e) {}
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

// Dica de arraste nos controles (mobile): mostra a seta quando há botões escondidos.
function updateControlsOverflow() {
    const wrap = document.querySelector('.controls-wrap');
    const bar = document.getElementById('controls');
    if (!wrap || !bar) return;
    const overflow = bar.scrollWidth > bar.clientWidth + 8;
    const atEnd = bar.scrollLeft + bar.clientWidth >= bar.scrollWidth - 8;
    wrap.classList.toggle('has-overflow', overflow && !atEnd);
}
function scrollControls() {
    const bar = document.getElementById('controls');
    if (bar) bar.scrollBy({ left: Math.round(bar.clientWidth * 0.6), behavior: 'smooth' });
}
(function () {
    const bar = document.getElementById('controls');
    if (bar) bar.addEventListener('scroll', updateControlsOverflow, { passive: true });
    window.addEventListener('resize', updateControlsOverflow);
    // Reavalia após o layout assentar.
    setTimeout(updateControlsOverflow, 800);
    setTimeout(updateControlsOverflow, 2500);
})();

initPreview();

// iOS: se a câmera não abriu (ex.: permissão concedida após um toque), um clique
// no lobby tenta novamente. Não atrapalha os demais navegadores.
(function () {
    const lb = document.getElementById('lobby');
    if (!lb) return;
    lb.addEventListener('click', function (ev) {
        if (ev.target.closest('button') || ev.target.closest('select') || ev.target.closest('input')) return;
        const semVideo = !rawStream || rawStream.getVideoTracks().length === 0;
        if (semVideo) initPreview();
    }, { passive: true });
})();
</script>
</body>
</html>
