<?php
// Webphone nativo (WebRTC / SIP over WSS) — roda dentro do CRM.
// Credenciais SIP são buscadas via crm/sipCredentials (backend) só para o usuário logado.
// Controles: iniciar, mudo, cancelar/desligar. Registra todos os eventos no backend.
?>
<div id="nv-webphone" class="nv-webphone" style="display:none;">
    <div class="nv-wp-header">
        <span><i class="bi bi-headset"></i> Telefone</span>
        <span id="nv-wp-reg" class="nv-wp-reg" title="Status do ramal">•</span>
        <button type="button" class="nv-wp-min" title="Minimizar" onclick="nvToggleWebphone()"><i class="bi bi-dash-lg"></i></button>
    </div>
    <div class="nv-wp-body">
        <div id="nv-wp-status" class="nv-wp-status">Conectando ramal...</div>
        <div id="nv-wp-peer" class="nv-wp-peer"></div>
        <div id="nv-wp-timer" class="nv-wp-timer" style="display:none;">00:00</div>
        <div class="nv-wp-actions">
            <button type="button" id="nv-wp-answer" class="btn btn-sm btn-success" style="display:none;" onclick="nvAnswer()"><i class="bi bi-telephone-inbound"></i> Atender</button>
            <button type="button" id="nv-wp-mute" class="btn btn-sm btn-outline-secondary" style="display:none;" onclick="nvToggleMute()"><i class="bi bi-mic-fill"></i> Mudo</button>
            <button type="button" id="nv-wp-hangup" class="btn btn-sm btn-danger" style="display:none;" onclick="nvHangup()"><i class="bi bi-telephone-x"></i> Desligar</button>
        </div>
    </div>
    <audio id="nv-wp-audio" autoplay></audio>
</div>
<button type="button" id="nv-wp-fab" class="nv-wp-fab" style="display:none;" title="Telefone" onclick="nvToggleWebphone()">
    <i class="bi bi-telephone-fill"></i>
</button>

<style>
.nv-webphone { position: fixed; bottom: 20px; left: 20px; width: 260px; background:#fff; border-radius:14px;
    box-shadow:0 8px 30px rgba(0,0,0,0.18); z-index:10000; overflow:hidden; font-size:0.85rem; }
.nv-wp-header { display:flex; align-items:center; gap:6px; padding:8px 12px; background:#00BFA6; color:#fff; font-weight:600; }
.nv-wp-header .nv-wp-min { margin-left:auto; background:transparent; border:0; color:#fff; cursor:pointer; }
.nv-wp-reg { font-size:1.1rem; color:#ffd54f; line-height:1; }
.nv-wp-reg.online { color:#b9f6ca; }
.nv-wp-reg.offline { color:#ff8a80; }
.nv-wp-body { padding:12px; }
.nv-wp-status { color:#555; margin-bottom:4px; }
.nv-wp-peer { font-weight:600; color:#222; margin-bottom:4px; min-height:1.2em; }
.nv-wp-timer { font-variant-numeric: tabular-nums; font-weight:600; color:#00997D; margin-bottom:8px; }
.nv-wp-actions { display:flex; gap:8px; flex-wrap:wrap; }
.nv-wp-fab { position:fixed; bottom:20px; left:20px; width:52px; height:52px; border-radius:50%; border:0;
    background:#00BFA6; color:#fff; box-shadow:0 4px 15px rgba(0,191,166,0.45); z-index:10000; cursor:pointer; font-size:1.2rem; }
</style>

<!-- SIP.js (WebRTC over WSS) carregado como módulo ESM -->
<script type="module">
import * as SIP from 'https://cdn.jsdelivr.net/npm/sip.js@0.21.2/lib/index.min.js';
window.SIP = SIP;
(function(){
    const CRM_BASE = '<?= baseUrl("") ?>';
    let ua = null, registerer = null, currentSession = null, sipConfig = null;
    let currentRecordId = null;   // id do registro em nvoip_calls (para eventos)
    let answeredAt = null;        // timestamp de atendimento (para duração)
    let timerInterval = null;
    let isMuted = false;

    window.nvWebphoneReady = false;

    function setReg(state) {
        const dot = document.getElementById('nv-wp-reg');
        if (!dot) return;
        dot.className = 'nv-wp-reg ' + (state === 'online' ? 'online' : (state === 'offline' ? 'offline' : ''));
        dot.title = state === 'online' ? 'Ramal registrado' : (state === 'offline' ? 'Ramal offline' : 'Registrando...');
    }
    function setStatus(txt) { const el = document.getElementById('nv-wp-status'); if (el) el.textContent = txt; }
    function setPeer(txt) { const el = document.getElementById('nv-wp-peer'); if (el) el.textContent = txt || ''; }
    function show(id, on) { const el = document.getElementById(id); if (el) el.style.display = on ? '' : 'none'; }

    // Envia um evento do ciclo de vida ao backend (não bloqueia a UI)
    function reportEvent(event, extra) {
        if (!currentRecordId) return;
        const fd = new FormData();
        fd.append('event', event);
        if (extra) for (const k in extra) fd.append(k, extra[k]);
        fetch(CRM_BASE + 'crm/callEvent/' + currentRecordId, { method:'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} }).catch(()=>{});
    }

    function startTimer() {
        answeredAt = Date.now();
        const el = document.getElementById('nv-wp-timer');
        el.style.display = ''; el.textContent = '00:00';
        timerInterval = setInterval(() => {
            const s = Math.floor((Date.now() - answeredAt) / 1000);
            const mm = String(Math.floor(s/60)).padStart(2,'0');
            const ss = String(s%60).padStart(2,'0');
            el.textContent = mm + ':' + ss;
        }, 1000);
    }
    function stopTimer() {
        if (timerInterval) { clearInterval(timerInterval); timerInterval = null; }
        document.getElementById('nv-wp-timer').style.display = 'none';
    }
    function elapsedSeconds() { return answeredAt ? Math.floor((Date.now() - answeredAt) / 1000) : 0; }

    window.nvToggleWebphone = function() {
        const wp = document.getElementById('nv-webphone');
        const fab = document.getElementById('nv-wp-fab');
        if (wp.style.display === 'none') { wp.style.display = ''; fab.style.display = 'none'; }
        else { wp.style.display = 'none'; fab.style.display = ''; }
    };

    function initWebphone() {
        if (!window.SIP) { console.warn('SIP.js não carregou'); return; }
        fetch(CRM_BASE + 'crm/sipCredentials', { headers: {'X-Requested-With':'XMLHttpRequest'} })
            .then(r => r.json())
            .then(cfg => {
                if (!cfg.configured) return;
                sipConfig = cfg;
                document.getElementById('nv-wp-fab').style.display = '';
                startUA(cfg);
            })
            .catch(() => {});
    }

    function startUA(cfg) {
        try {
            const uri = SIP.UserAgent.makeURI(cfg.uri);
            ua = new SIP.UserAgent({
                uri: uri,
                transportOptions: { server: cfg.ws_server },
                authorizationUsername: cfg.sip_user,
                password: cfg.sip_password,
                displayName: cfg.sip_user,
                delegate: { onInvite: function(invitation) { handleIncoming(invitation); } }
            });
            ua.start().then(() => {
                registerer = new SIP.Registerer(ua);
                registerer.stateChange.addListener(function(s){
                    if (s === SIP.RegistererState.Registered) { setReg('online'); setStatus('Ramal registrado'); window.nvWebphoneReady = true; }
                    else if (s === SIP.RegistererState.Unregistered) { setReg('offline'); setStatus('Ramal offline'); window.nvWebphoneReady = false; }
                });
                registerer.register();
            }).catch(e => { setReg('offline'); setStatus('Falha ao conectar ramal'); console.warn(e); });
        } catch (e) { console.warn('Webphone init falhou', e); }
    }

    function attachAudio(session) {
        const remote = document.getElementById('nv-wp-audio');
        const pc = session.sessionDescriptionHandler && session.sessionDescriptionHandler.peerConnection;
        if (!pc) return;
        const stream = new MediaStream();
        pc.getReceivers().forEach(r => { if (r.track) stream.addTrack(r.track); });
        remote.srcObject = stream;
    }

    // Muta/desmuta o microfone local (mantém a chamada ativa)
    window.nvToggleMute = function() {
        const s = currentSession;
        if (!s || !s.sessionDescriptionHandler) return;
        const pc = s.sessionDescriptionHandler.peerConnection;
        if (!pc) return;
        isMuted = !isMuted;
        pc.getSenders().forEach(sender => { if (sender.track && sender.track.kind === 'audio') sender.track.enabled = !isMuted; });
        const btn = document.getElementById('nv-wp-mute');
        btn.innerHTML = isMuted ? '<i class="bi bi-mic-mute-fill"></i> Mudo' : '<i class="bi bi-mic-fill"></i> Mudo';
        btn.classList.toggle('btn-secondary', isMuted);
        btn.classList.toggle('btn-outline-secondary', !isMuted);
    };

    function resetControls() {
        show('nv-wp-answer', false); show('nv-wp-hangup', false); show('nv-wp-mute', false);
        isMuted = false; stopTimer();
    }

    function wireSession(session, peerLabel) {
        currentSession = session;
        setPeer(peerLabel || '');
        session.stateChange.addListener(function(state){
            if (state === SIP.SessionState.Establishing) { setStatus('Chamando...'); reportEvent('ringing'); }
            else if (state === SIP.SessionState.Established) {
                setStatus('Em chamada'); show('nv-wp-hangup', true); show('nv-wp-mute', true); show('nv-wp-answer', false);
                attachAudio(session); startTimer(); reportEvent('answered');
            }
            else if (state === SIP.SessionState.Terminated) {
                const dur = elapsedSeconds();
                setStatus('Chamada encerrada'); resetControls(); setPeer('');
                reportEvent('ended', { duration: dur });
                currentSession = null; currentRecordId = null; answeredAt = null;
            }
        });
    }

    function handleIncoming(invitation) {
        document.getElementById('nv-webphone').style.display = '';
        document.getElementById('nv-wp-fab').style.display = 'none';
        const from = invitation.remoteIdentity && invitation.remoteIdentity.uri ? invitation.remoteIdentity.uri.user : '';
        setStatus('Chamada recebida'); setPeer(from); show('nv-wp-answer', true); show('nv-wp-hangup', true);
        // Registra a ligação recebida no backend
        registerInbound(from);
        wireSession(invitation, from);
        window._nvInvitation = invitation;
    }

    // Cria registro de ligação recebida (para manter histórico de entrada também)
    function registerInbound(from) {
        const fd = new FormData(); fd.append('from', from || '');
        fetch(CRM_BASE + 'crm/registerInbound', { method:'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
            .then(r => r.json()).then(d => { if (d && d.call_record_id) currentRecordId = d.call_record_id; }).catch(()=>{});
    }

    window.nvAnswer = function() {
        const inv = window._nvInvitation;
        if (!inv) return;
        inv.accept({ sessionDescriptionHandlerOptions: { constraints: { audio: true, video: false } } });
        show('nv-wp-answer', false);
    };

    window.nvHangup = function() {
        const s = currentSession;
        if (!s) return;
        try {
            if (s.state === SIP.SessionState.Established) s.bye();
            else if (s instanceof SIP.Inviter) s.cancel();       // cancela chamada em andamento (saída)
            else s.reject();                                     // rejeita chamada recebida
        } catch(e) {}
    };

    // Origina uma chamada de saída pelo ramal (WebRTC). numero = destino já normalizado; recordId = registro backend.
    window.nvCall = function(numero, recordId) {
        if (!ua || !sipConfig) { alert('Webphone não inicializado.'); return false; }
        if (!window.nvWebphoneReady) { alert('O ramal ainda não está registrado. Aguarde o indicador ficar verde.'); return false; }
        const target = SIP.UserAgent.makeURI('sip:' + numero + '@' + sipConfig.domain);
        if (!target) { alert('Número inválido.'); return false; }
        currentRecordId = recordId || null;
        document.getElementById('nv-webphone').style.display = '';
        document.getElementById('nv-wp-fab').style.display = 'none';
        const inviter = new SIP.Inviter(ua, target, { sessionDescriptionHandlerOptions: { constraints: { audio: true, video: false } } });
        wireSession(inviter, numero);
        inviter.invite().catch(e => { setStatus('Falha ao chamar'); console.warn(e); });
        return true;
    };

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initWebphone);
    else initWebphone();
})();
</script>
