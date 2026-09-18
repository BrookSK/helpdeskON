<?php
$base = rtrim(baseUrl(''), '/');
$title = htmlspecialchars(($room['title'] ?? 'Gravação') ?: 'Gravação', ENT_QUOTES);
$recToken = htmlspecialchars($rec['token'], ENT_QUOTES);
$videoUrl = htmlspecialchars($videoUrl, ENT_QUOTES);
$status = $rec['transcribe_status'] ?? 'none';
$summary = (string)($rec['summary'] ?? '');
$segJson = json_encode($segments ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$shareUrl = $base . '/videocall/share/' . $recToken;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?> · Gravação</title>
    <?php $faviconUrl = Config::get('app_favicon'); if ($faviconUrl): ?>
    <link rel="icon" href="<?= baseUrl($faviconUrl) ?>" type="image/x-icon">
    <link rel="shortcut icon" href="<?= baseUrl($faviconUrl) ?>">
    <?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root { --brand:#00BFA6; --brand-dark:#009e88; --bg:#0f1020; --panel:#1a1c2e; --panel2:#23263d; }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--bg); color:#e8eaf1; font-family:'Segoe UI',system-ui,Arial,sans-serif; }
        .top { display:flex; align-items:center; gap:12px; padding:12px 18px; background:rgba(0,0,0,.25); }
        .top h1 { font-size:1.05rem; margin:0; font-weight:600; }
        .top .sp { flex:1; }
        .btn-brand { background:var(--brand); border:none; color:#fff; }
        .btn-brand:hover { background:var(--brand-dark); color:#fff; }
        .wrap { display:grid; grid-template-columns:1fr 380px; gap:16px; padding:16px; max-width:1400px; margin:0 auto; }
        @media (max-width:900px){ .wrap { grid-template-columns:1fr; } }
        .player-col video { width:100%; border-radius:14px; background:#000; max-height:64vh; }
        .speed-bar { display:flex; align-items:center; gap:6px; flex-wrap:wrap; margin-top:10px; }
        .speed-bar .lbl { color:#9aa2c0; font-size:.8rem; margin-right:4px; }
        .speed-btn { background:var(--panel2); border:1px solid #33375a; color:#e8eaf1; border-radius:8px; padding:4px 10px; font-size:.8rem; cursor:pointer; }
        .speed-btn.active { background:var(--brand); border-color:var(--brand); color:#fff; }
        .side { background:var(--panel); border-radius:14px; overflow:hidden; display:flex; flex-direction:column; max-height:78vh; }
        .tabs { display:flex; border-bottom:1px solid #2a2d44; }
        .tab { flex:1; background:none; border:none; color:#9aa2c0; padding:12px; cursor:pointer; font-size:.85rem; font-weight:600; }
        .tab.active { color:#fff; box-shadow:inset 0 -2px 0 var(--brand); }
        .tab-body { flex:1; overflow:auto; padding:14px; }
        .seg { padding:7px 9px; border-radius:8px; cursor:pointer; display:flex; gap:9px; font-size:.86rem; line-height:1.4; }
        .seg:hover { background:var(--panel2); }
        .seg.active { background:rgba(0,191,166,.16); }
        .seg .t { color:var(--brand); font-variant-numeric:tabular-nums; flex:0 0 auto; font-size:.78rem; padding-top:1px; }
        .summary-body { white-space:pre-wrap; font-size:.88rem; line-height:1.6; }
        .muted { color:#9aa2c0; }
        .toolbar { display:flex; gap:8px; padding:10px 14px; border-top:1px solid #2a2d44; }
        .toolbar .btn { font-size:.8rem; }
        .empty { color:#9aa2c0; text-align:center; padding:30px 10px; }
        .spin { width:26px; height:26px; border:3px solid #33375a; border-top-color:var(--brand); border-radius:50%; display:inline-block; animation:spin 1s linear infinite; vertical-align:middle; }
        @keyframes spin { to { transform:rotate(360deg); } }
    </style>
</head>
<body>
<div class="top">
    <i class="bi bi-camera-reels" style="color:var(--brand);font-size:1.3rem;"></i>
    <h1><?= $title ?></h1>
    <span class="sp"></span>
    <?php if (empty($isPublic)): ?>
    <a href="<?= $base ?>/videocall/myRecordings" class="btn btn-sm btn-outline-light"><i class="bi bi-arrow-left"></i> Gravações</a>
    <?php endif; ?>
    <button class="btn btn-sm btn-brand" onclick="copyShare()"><i class="bi bi-link-45deg"></i> Copiar link</button>
</div>

<div class="wrap">
    <div class="player-col">
        <video id="player" controls playsinline src="<?= $videoUrl ?>"></video>
        <div class="speed-bar">
            <span class="lbl"><i class="bi bi-speedometer2"></i> Velocidade:</span>
            <button class="speed-btn" data-s="0.5" onclick="setSpeed(0.5,this)">0.5x</button>
            <button class="speed-btn active" data-s="1" onclick="setSpeed(1,this)">1x</button>
            <button class="speed-btn" data-s="1.5" onclick="setSpeed(1.5,this)">1.5x</button>
            <button class="speed-btn" data-s="2" onclick="setSpeed(2,this)">2x</button>
            <button class="speed-btn" data-s="3" onclick="setSpeed(3,this)">3x</button>
            <span class="sp" style="flex:1"></span>
            <a class="btn btn-sm btn-outline-light" href="<?= $videoUrl ?>" download><i class="bi bi-download"></i> Baixar</a>
        </div>
    </div>

    <div class="side">
        <div class="tabs">
            <button class="tab active" id="tab-tr-btn" onclick="showTab('tr')">Transcrição</button>
            <button class="tab" id="tab-sm-btn" onclick="showTab('sm')">Resumo</button>
        </div>
        <div class="tab-body" id="tab-tr">
            <div id="tr-list"></div>
            <div id="tr-empty" class="empty" style="display:none;">
                <p>Esta gravação ainda não foi transcrita.</p>
                <button class="btn btn-sm btn-brand" id="tr-btn" onclick="startTranscription()"><i class="bi bi-magic"></i> Transcrever com IA</button>
            </div>
        </div>
        <div class="tab-body" id="tab-sm" style="display:none;">
            <div id="sm-body" class="summary-body"></div>
        </div>
        <div class="toolbar">
            <button class="btn btn-sm btn-outline-light" onclick="copyTranscript()"><i class="bi bi-clipboard"></i> Copiar transcrição</button>
            <button class="btn btn-sm btn-outline-light" onclick="copySummary()"><i class="bi bi-clipboard-check"></i> Copiar resumo</button>
        </div>
    </div>
</div>

<script>
const BASE = '<?= $base ?>';
const REC_TOKEN = '<?= $recToken ?>';
const VIDEO_URL = '<?= $videoUrl ?>';
const SHARE_URL = '<?= htmlspecialchars($shareUrl, ENT_QUOTES) ?>';
let segments = <?= $segJson ?: '[]' ?>;
let summary = <?= json_encode($summary, JSON_UNESCAPED_UNICODE) ?>;
let status = '<?= $status ?>';
const player = document.getElementById('player');

function fmt(t) {
    t = Math.max(0, Math.floor(t || 0));
    const h = Math.floor(t / 3600), m = Math.floor((t % 3600) / 60), s = t % 60;
    return h > 0 ? `${h}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}` : `${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
}
function esc(s){ return (s||'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

function renderSegments() {
    const list = document.getElementById('tr-list');
    const empty = document.getElementById('tr-empty');
    if (!segments || !segments.length) {
        list.innerHTML = '';
        empty.style.display = 'block';
        // Se está processando, mostra spinner.
        if (status === 'processing') { empty.innerHTML = '<p><span class="spin"></span> Transcrevendo… isso pode levar alguns minutos em reuniões longas.</p>'; pollStatus(); }
        return;
    }
    empty.style.display = 'none';
    list.innerHTML = segments.map((s, i) =>
        `<div class="seg" id="seg-${i}" onclick="seek(${s.start})"><span class="t">${fmt(s.start)}</span><span>${esc(s.text)}</span></div>`
    ).join('');
}
function seek(t) { player.currentTime = t; player.play(); }

// Destaca o segmento conforme o vídeo avança.
player.addEventListener('timeupdate', () => {
    if (!segments || !segments.length) return;
    const t = player.currentTime;
    let idx = -1;
    for (let i = 0; i < segments.length; i++) { if (t >= segments[i].start) idx = i; else break; }
    document.querySelectorAll('.seg.active').forEach(e => e.classList.remove('active'));
    if (idx >= 0) {
        const el = document.getElementById('seg-' + idx);
        if (el) { el.classList.add('active'); const b = el.getBoundingClientRect(), p = el.parentElement.getBoundingClientRect();
            if (b.top < p.top || b.bottom > p.bottom) el.scrollIntoView({ block:'center', behavior:'smooth' }); }
    }
});

function setSpeed(s, btn) {
    player.playbackRate = s;
    document.querySelectorAll('.speed-btn').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
}
function showTab(w) {
    document.getElementById('tab-tr-btn').classList.toggle('active', w === 'tr');
    document.getElementById('tab-sm-btn').classList.toggle('active', w === 'sm');
    document.getElementById('tab-tr').style.display = (w === 'tr') ? 'block' : 'none';
    document.getElementById('tab-sm').style.display = (w === 'sm') ? 'block' : 'none';
}
function renderSummary() {
    document.getElementById('sm-body').innerHTML = summary ? esc(summary) : '<div class="muted">Sem resumo ainda. Ele é gerado junto com a transcrição.</div>';
}

function copyShare() { navigator.clipboard?.writeText(SHARE_URL).then(()=>alert('Link copiado:\n'+SHARE_URL)).catch(()=>alert(SHARE_URL)); }
function copyTranscript() {
    const txt = (segments && segments.length) ? segments.map(s => '[' + fmt(s.start) + '] ' + s.text).join('\n') : '';
    if (!txt) { alert('Sem transcrição para copiar.'); return; }
    navigator.clipboard?.writeText(txt).then(()=>alert('Transcrição copiada! Cole no GPT para gerar as tarefas.'));
}
function copySummary() {
    if (!summary) { alert('Sem resumo para copiar.'); return; }
    navigator.clipboard?.writeText(summary).then(()=>alert('Resumo copiado!'));
}

const CHUNK_SECONDS = 600; // 10 min por pedaço (fica bem abaixo dos 25 MB em WAV 16kHz mono)

function trProgress(msg) {
    document.getElementById('tr-empty').style.display = 'block';
    document.getElementById('tr-empty').innerHTML = '<p><span class="spin"></span> ' + esc(msg) + '</p>';
}

async function startTranscription() {
    const btn = document.getElementById('tr-btn');
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spin"></span> Transcrevendo…'; }
    status = 'processing';
    trProgress('Preparando o áudio da gravação…');
    try {
        // 1) Baixa o arquivo da gravação e decodifica o áudio (no navegador).
        const resp = await fetch(VIDEO_URL);
        const buf = await resp.arrayBuffer();
        const AC = window.AudioContext || window.webkitAudioContext;
        const actx = new AC();
        const audio = await actx.decodeAudioData(buf.slice(0));
        const total = audio.duration;
        const sr = audio.sampleRate;
        const chunks = Math.max(1, Math.ceil(total / CHUNK_SECONDS));

        // 2) Corta em pedaços e transcreve cada um, juntando os segmentos.
        let allSegs = [];
        for (let i = 0; i < chunks; i++) {
            const startSec = i * CHUNK_SECONDS;
            const endSec = Math.min(total, startSec + CHUNK_SECONDS);
            trProgress('Transcrevendo parte ' + (i + 1) + ' de ' + chunks + '…');
            const wav = audioSliceToWav(audio, startSec, endSec, sr);
            const fd = new FormData();
            fd.append('audio', wav, 'parte.wav');
            fd.append('offset', String(startSec));
            fd.append('index', String(i));
            const r = await fetch(`${BASE}/videocall/transcribeChunk/${REC_TOKEN}`, { method: 'POST', body: fd }).then(x => x.json());
            if (r.error) throw new Error(r.error);
            allSegs = allSegs.concat(r.segments || []);
        }
        try { actx.close(); } catch (e) {}

        // 3) Salva a transcrição montada e gera o resumo no servidor.
        trProgress('Gerando o resumo…');
        const save = await fetch(`${BASE}/videocall/saveTranscript/${REC_TOKEN}`, {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ segments: allSegs })
        }).then(x => x.json());
        if (save.error) throw new Error(save.error);

        segments = allSegs; summary = save.summary || ''; status = 'done';
        document.getElementById('tr-empty').style.display = 'none';
        renderSegments(); renderSummary();
    } catch (e) {
        document.getElementById('tr-empty').style.display = 'block';
        document.getElementById('tr-empty').innerHTML = '<p class="text-danger">Falha ao transcrever: ' + esc(e.message || 'erro') + '</p>'
            + '<button class="btn btn-sm btn-brand" onclick="startTranscription()">Tentar de novo</button>';
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-magic"></i> Transcrever com IA'; }
    }
}

// Extrai um trecho [startSec,endSec) do AudioBuffer como WAV mono 16kHz (leve).
function audioSliceToWav(audio, startSec, endSec, sr) {
    const OUT_SR = 16000;
    const chs = audio.numberOfChannels;
    const startF = Math.floor(startSec * sr);
    const endF = Math.floor(endSec * sr);
    const len = endF - startF;
    // Mixa canais em mono.
    const mono = new Float32Array(len);
    for (let c = 0; c < chs; c++) {
        const data = audio.getChannelData(c);
        for (let i = 0; i < len; i++) mono[i] += (data[startF + i] || 0) / chs;
    }
    // Reamostra para 16kHz (linear simples).
    const ratio = sr / OUT_SR;
    const outLen = Math.floor(len / ratio);
    const out = new Float32Array(outLen);
    for (let i = 0; i < outLen; i++) {
        const idx = i * ratio;
        const i0 = Math.floor(idx), i1 = Math.min(len - 1, i0 + 1), frac = idx - i0;
        out[i] = mono[i0] * (1 - frac) + mono[i1] * frac;
    }
    return encodeWav(out, OUT_SR);
}

// Gera um Blob WAV PCM 16-bit a partir de amostras Float32 mono.
function encodeWav(samples, sampleRate) {
    const buffer = new ArrayBuffer(44 + samples.length * 2);
    const view = new DataView(buffer);
    const wr = (off, s) => { for (let i = 0; i < s.length; i++) view.setUint8(off + i, s.charCodeAt(i)); };
    wr(0, 'RIFF'); view.setUint32(4, 36 + samples.length * 2, true); wr(8, 'WAVE');
    wr(12, 'fmt '); view.setUint32(16, 16, true); view.setUint16(20, 1, true); view.setUint16(22, 1, true);
    view.setUint32(24, sampleRate, true); view.setUint32(28, sampleRate * 2, true);
    view.setUint16(32, 2, true); view.setUint16(34, 16, true);
    wr(36, 'data'); view.setUint32(40, samples.length * 2, true);
    let off = 44;
    for (let i = 0; i < samples.length; i++, off += 2) {
        let s = Math.max(-1, Math.min(1, samples[i]));
        view.setInt16(off, s < 0 ? s * 0x8000 : s * 0x7FFF, true);
    }
    return new Blob([view], { type: 'audio/wav' });
}
// Se outra pessoa iniciou a transcrição, acompanha o status.
let pollTimer = null;
function pollStatus() {
    if (pollTimer) return;
    pollTimer = setInterval(async () => {
        try {
            const r = await fetch(`${BASE}/videocall/recordingInfo/${REC_TOKEN}`).then(x => x.json());
            if (r.status === 'done') {
                clearInterval(pollTimer); pollTimer = null;
                segments = r.transcript_json || []; summary = r.summary || ''; status = 'done';
                renderSegments(); renderSummary();
            } else if (r.status === 'error') {
                clearInterval(pollTimer); pollTimer = null;
                document.getElementById('tr-empty').innerHTML = '<p class="text-danger">' + esc(r.error_message || 'A transcrição falhou.') + '</p>'
                    + '<button class="btn btn-sm btn-brand" onclick="startTranscription()">Tentar de novo</button>';
            }
        } catch (e) {}
    }, 4000);
}

renderSegments();
renderSummary();
</script>
</body>
</html>
