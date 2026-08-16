<?php
// Webphone nativo (WebRTC / SIP over WSS) — roda dentro do CRM.
// O ramal registra em segundo plano (invisível). A janela só aparece quando há uma chamada
// (saída ou recebida), exibindo timer e controles (mudo, cancelar/desligar). É arrastável e minimizável.
?>
<div id="nv-webphone" class="nv-webphone" style="display:none;">
    <div class="nv-wp-header" id="nv-wp-drag">
        <span><i class="bi bi-headset"></i> Telefone</span>
        <span id="nv-wp-reg" class="nv-wp-reg" title="Status do ramal">•</span>
        <button type="button" class="nv-wp-min" title="Minimizar" onclick="nvMinimize()"><i class="bi bi-dash-lg"></i></button>
    </div>
    <div class="nv-wp-body">
        <div id="nv-wp-status" class="nv-wp-status">—</div>
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
<!-- Botão minimizado (aparece só durante uma chamada, quando a janela está minimizada) -->
<button type="button" id="nv-wp-fab" class="nv-wp-fab" style="display:none;" title="Chamada em andamento" onclick="nvRestore()">
    <i class="bi bi-telephone-fill"></i>
    <span id="nv-wp-fab-timer" class="nv-wp-fab-timer">00:00</span>
</button>

<style>
.nv-webphone { position: fixed; bottom: 20px; left: 20px; width: 260px; background:#fff; border-radius:14px;
    box-shadow:0 8px 30px rgba(0,0,0,0.18); z-index:10000; overflow:hidden; font-size:0.85rem; }
.nv-wp-header { display:flex; align-items:center; gap:6px; padding:8px 12px; background:#00BFA6; color:#fff; font-weight:600; cursor:move; user-select:none; }
.nv-wp-header .nv-wp-min { margin-left:auto; background:transparent; border:0; color:#fff; cursor:pointer; }
.nv-wp-reg { font-size:1.1rem; color:#ffd54f; line-height:1; }
.nv-wp-reg.online { color:#b9f6ca; }
.nv-wp-reg.offline { color:#ff8a80; }
.nv-wp-body { padding:12px; }
.nv-wp-status { color:#555; margin-bottom:4px; }
.nv-wp-peer { font-weight:600; color:#222; margin-bottom:4px; min-height:1.2em; }
.nv-wp-timer { font-variant-numeric: tabular-nums; font-weight:600; color:#00997D; margin-bottom:8px; font-size:1.1rem; }
.nv-wp-actions { display:flex; gap:8px; flex-wrap:wrap; }
.nv-wp-fab { position:fixed; bottom:20px; left:20px; height:52px; padding:0 16px; border-radius:26px; border:0;
    background:#00BFA6; color:#fff; box-shadow:0 4px 15px rgba(0,191,166,0.45); z-index:10000; cursor:pointer;
    display:flex; align-items:center; gap:8px; font-weight:600; }
.nv-wp-fab-timer { font-variant-numeric: tabular-nums; }
</style>

<!-- SIP.js (WebRTC over WSS) carregado como módulo ESM -->
<script type="module">
import * as SIP from 'https://cdn.jsdelivr.net/npm/sip.js@0.21.2/lib/index.min.js';
window.SIP = SIP;
(function(){
    const CRM_BASE = '<?= baseUrl("") ?>';
    let ua = null, registerer = null, currentSession = null, sipConfig = null;
    let currentRecordId = null, answeredAt = null, timerInterval = null, isMuted = false;

    window.nvWebphoneReady = false;

    const $ = id => document.getElementById(id);
    function setReg(state) {
        const dot = $('nv-wp-reg'); if (!dot) return;
        dot.className = 'nv-wp-reg ' + (state === 'online' ? 'online' : (state === 'offline' ? 'offline' : ''));
        dot.title = state === 'online' ? 'Ramal registrado' : (state === 'offline' ? 'Ramal offline' : 'Registrando...');
    }
    function setStatus(t){ const el=$('nv-wp-status'); if(el) el.textContent=t; }
    function setPeer(t){ const el=$('nv-wp-peer'); if(el) el.textContent=t||''; }
    function show(id,on){ const el=$(id); if(el) el.style.display= on?'':'none'; }

    // === Janela: abrir só quando há chamada ===
    function openWindow() { $('nv-webphone').style.display=''; $('nv-wp-fab').style.display='none'; }
    function closeWindow() { $('nv-webphone').style.display='none'; $('nv-wp-fab').style.display='none'; }
    window.nvMinimize = function(){ $('nv-webphone').style.display='none'; $('nv-wp-fab').style.display=''; };
    window.nvRestore = function(){ $('nv-webphone').style.display=''; $('nv-wp-fab').style.display='none'; };

    function reportEvent(event, extra){
        if(!currentRecordId) return;
        const fd=new FormData(); fd.append('event',event);
        if(extra) for(const k in extra) fd.append(k, extra[k]);
        fetch(CRM_BASE+'crm/callEvent/'+currentRecordId,{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}}).catch(()=>{});
    }

    function startTimer(){
        answeredAt=Date.now();
        show('nv-wp-timer',true); $('nv-wp-timer').textContent='00:00';
        timerInterval=setInterval(()=>{
            const s=Math.floor((Date.now()-answeredAt)/1000);
            const t=String(Math.floor(s/60)).padStart(2,'0')+':'+String(s%60).padStart(2,'0');
            $('nv-wp-timer').textContent=t; $('nv-wp-fab-timer').textContent=t;
        },1000);
    }
    function stopTimer(){ if(timerInterval){clearInterval(timerInterval);timerInterval=null;} show('nv-wp-timer',false); }
    function elapsed(){ return answeredAt?Math.floor((Date.now()-answeredAt)/1000):0; }

    // === Registro do ramal em segundo plano (sem abrir janela) ===
    function initWebphone(){
        if(!window.SIP){ console.warn('SIP.js não carregou'); return; }
        fetch(CRM_BASE+'crm/sipCredentials',{headers:{'X-Requested-With':'XMLHttpRequest'}})
            .then(r=>r.json()).then(cfg=>{ if(!cfg.configured) return; sipConfig=cfg; startUA(cfg); })
            .catch(()=>{});
    }

    function startUA(cfg){
        try{
            const uri=SIP.UserAgent.makeURI(cfg.uri);
            ua=new SIP.UserAgent({
                uri, transportOptions:{server:cfg.ws_server},
                authorizationUsername:cfg.sip_user, password:cfg.sip_password, displayName:cfg.sip_user,
                delegate:{ onInvite:inv=>handleIncoming(inv) }
            });
            ua.transport.stateChange.addListener(ts=>console.log('[Webphone] Transport:',ts));
            ua.start().then(()=>{
                registerer=new SIP.Registerer(ua);
                registerer.stateChange.addListener(s=>{
                    console.log('[Webphone] Registerer:',s);
                    if(s===SIP.RegistererState.Registered){ setReg('online'); window.nvWebphoneReady=true; }
                    else if(s===SIP.RegistererState.Unregistered){ setReg('offline'); window.nvWebphoneReady=false; }
                });
                registerer.register({ requestDelegate:{ onReject:resp=>{
                    const code=resp&&resp.message?resp.message.statusCode:'';
                    console.error('[Webphone] REGISTER rejeitado:',code);
                    setReg('offline');
                }}});
            }).catch(e=>{ setReg('offline'); console.error('[Webphone] start falhou:',e); });
        }catch(e){ console.warn('Webphone init falhou',e); }
    }

    function attachAudio(session){
        const remote=$('nv-wp-audio');
        const pc=session.sessionDescriptionHandler&&session.sessionDescriptionHandler.peerConnection;
        if(!pc) return;
        const stream=new MediaStream();
        pc.getReceivers().forEach(r=>{ if(r.track) stream.addTrack(r.track); });
        remote.srcObject=stream;
    }

    window.nvToggleMute=function(){
        const s=currentSession; if(!s||!s.sessionDescriptionHandler) return;
        const pc=s.sessionDescriptionHandler.peerConnection; if(!pc) return;
        isMuted=!isMuted;
        pc.getSenders().forEach(sn=>{ if(sn.track&&sn.track.kind==='audio') sn.track.enabled=!isMuted; });
        const b=$('nv-wp-mute');
        b.innerHTML=isMuted?'<i class="bi bi-mic-mute-fill"></i> Mudo':'<i class="bi bi-mic-fill"></i> Mudo';
        b.classList.toggle('btn-secondary',isMuted); b.classList.toggle('btn-outline-secondary',!isMuted);
    };

    function resetControls(){ show('nv-wp-answer',false); show('nv-wp-hangup',false); show('nv-wp-mute',false); isMuted=false; stopTimer(); }

    function wireSession(session, peerLabel){
        currentSession=session; setPeer(peerLabel||'');
        session.stateChange.addListener(state=>{
            if(state===SIP.SessionState.Establishing){ setStatus('Chamando...'); $('nv-wp-hangup').innerHTML='<i class="bi bi-telephone-x"></i> Cancelar'; show('nv-wp-hangup',true); reportEvent('ringing'); }
            else if(state===SIP.SessionState.Established){
                setStatus('Em chamada'); $('nv-wp-hangup').innerHTML='<i class="bi bi-telephone-x"></i> Desligar';
                show('nv-wp-hangup',true); show('nv-wp-mute',true); show('nv-wp-answer',false);
                attachAudio(session); startTimer(); reportEvent('answered');
            }
            else if(state===SIP.SessionState.Terminated){
                const dur=elapsed(); setStatus('Chamada encerrada'); resetControls(); setPeer('');
                reportEvent('ended',{duration:dur});
                currentSession=null; currentRecordId=null; answeredAt=null;
                // Fecha a janela após encerrar
                setTimeout(closeWindow, 1200);
            }
        });
    }

    function handleIncoming(invitation){
        openWindow();
        const from=invitation.remoteIdentity&&invitation.remoteIdentity.uri?invitation.remoteIdentity.uri.user:'';
        setStatus('Chamada recebida'); setPeer(from); show('nv-wp-answer',true); show('nv-wp-hangup',true);
        registerInbound(from);
        wireSession(invitation, from);
        window._nvInvitation=invitation;
    }

    function registerInbound(from){
        const fd=new FormData(); fd.append('from',from||'');
        fetch(CRM_BASE+'crm/registerInbound',{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}})
            .then(r=>r.json()).then(d=>{ if(d&&d.call_record_id) currentRecordId=d.call_record_id; }).catch(()=>{});
    }

    window.nvAnswer=function(){
        const inv=window._nvInvitation; if(!inv) return;
        inv.accept({sessionDescriptionHandlerOptions:{constraints:{audio:true,video:false}}});
        show('nv-wp-answer',false);
    };

    window.nvHangup=function(){
        const s=currentSession; if(!s) return;
        try{
            if(s.state===SIP.SessionState.Established) s.bye();
            else if(s instanceof SIP.Inviter) s.cancel();
            else s.reject();
        }catch(e){}
    };

    // Origina chamada de saída. Abre a janela e mostra controles.
    window.nvCall=function(numero, recordId){
        if(!ua||!sipConfig){ alert('Webphone não inicializado.'); return false; }
        if(!window.nvWebphoneReady){ alert('O ramal ainda não está registrado (offline). Verifique a Senha SIP em Configurações.'); return false; }
        const target=SIP.UserAgent.makeURI('sip:'+numero+'@'+sipConfig.domain);
        if(!target){ alert('Número inválido.'); return false; }
        currentRecordId=recordId||null;
        openWindow();
        setStatus('Iniciando ligação...'); setPeer(numero);
        $('nv-wp-hangup').innerHTML='<i class="bi bi-telephone-x"></i> Cancelar'; show('nv-wp-hangup',true);
        const inviter=new SIP.Inviter(ua,target,{sessionDescriptionHandlerOptions:{constraints:{audio:true,video:false}}});
        wireSession(inviter, numero);
        inviter.invite().catch(e=>{ setStatus('Falha ao chamar'); console.warn(e); reportEvent('ended',{duration:0,cause:'invite_failed'}); resetControls(); setTimeout(closeWindow,1500); });
        return true;
    };

    // === Arrastar a janela ===
    (function makeDraggable(){
        const win=$('nv-webphone'), handle=$('nv-wp-drag');
        if(!win||!handle) return;
        let dx=0, dy=0, dragging=false;
        handle.addEventListener('mousedown', e=>{
            if(e.target.closest('.nv-wp-min')) return;
            dragging=true;
            const r=win.getBoundingClientRect();
            dx=e.clientX-r.left; dy=e.clientY-r.top;
            win.style.bottom='auto'; win.style.right='auto';
            document.body.style.userSelect='none';
        });
        document.addEventListener('mousemove', e=>{
            if(!dragging) return;
            let x=e.clientX-dx, y=e.clientY-dy;
            x=Math.max(0, Math.min(x, window.innerWidth - win.offsetWidth));
            y=Math.max(0, Math.min(y, window.innerHeight - win.offsetHeight));
            win.style.left=x+'px'; win.style.top=y+'px';
        });
        document.addEventListener('mouseup', ()=>{ dragging=false; document.body.style.userSelect=''; });
    })();

    if(document.readyState==='loading') document.addEventListener('DOMContentLoaded', initWebphone);
    else initWebphone();
})();
</script>
