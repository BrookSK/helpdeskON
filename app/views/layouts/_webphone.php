<?php
// Webphone nativo (WebRTC / SIP over WSS) — roda dentro do CRM.
// Config conforme doc Nvoip (WSS): wss://app.nvoip.com.br:7443, domínio app.nvoip.com.br.
// A lógica de discagem/SIP NÃO deve ser alterada (está funcionando). Esta camada cuida da UI:
// modal arrastável que NÃO fecha sozinho, infos do cliente, notas e briefing.
?>
<div id="nv-call-modal" class="nv-call-win" style="display:none;">
    <div class="nv-call-head" id="nv-call-drag">
        <span><i class="bi bi-telephone-fill"></i> Ligação</span>
        <span id="nv-call-reg-dot" class="nv-reg-dot" title="Ramal"></span>
        <button type="button" class="nv-head-btn" title="Fechar" onclick="nvCloseWindow()"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="nv-call-body">
        <div class="nv-call-avatar"><i class="bi bi-person-fill"></i></div>
        <div id="nv-call-peer" class="nv-call-peer">—</div>
        <div id="nv-call-sub" class="nv-call-sub"></div>
        <div id="nv-call-status" class="nv-call-status">
            <span class="nv-call-dots"><span></span><span></span><span></span></span>
            <span id="nv-call-status-text">Chamando...</span>
        </div>
        <div id="nv-call-timer" class="nv-call-timer" style="display:none;">00:00</div>

        <div class="nv-call-actions">
            <button type="button" id="nv-call-answer" class="nv-btn nv-btn-answer" style="display:none;" onclick="nvAnswer()" title="Atender"><i class="bi bi-telephone-inbound-fill"></i></button>
            <button type="button" id="nv-call-mute" class="nv-btn nv-btn-mute" style="display:none;" onclick="nvToggleMute()" title="Mudo"><i class="bi bi-mic-fill"></i></button>
            <button type="button" id="nv-call-hangup" class="nv-btn nv-btn-hangup" onclick="nvHangup()" title="Encerrar"><i class="bi bi-telephone-x-fill"></i></button>
        </div>
        <div id="nv-call-reg-warn" class="nv-call-warn" style="display:none;"></div>

        <!-- Anotações da ligação -->
        <div class="nv-notes-wrap">
            <label class="nv-notes-label"><i class="bi bi-journal-text"></i> Anotações da ligação</label>
            <textarea id="nv-call-note" class="nv-notes" rows="3" placeholder="Escreva as observações da ligação..."></textarea>
            <div class="nv-notes-actions">
                <button type="button" class="nv-mini-btn" id="nv-open-briefing" onclick="nvOpenBriefing()"><i class="bi bi-clipboard-data"></i> Briefing</button>
                <button type="button" class="nv-mini-btn nv-mini-primary" onclick="nvSaveNote(this)"><i class="bi bi-save"></i> Salvar nota</button>
            </div>
            <div id="nv-note-saved" class="nv-note-saved" style="display:none;"><i class="bi bi-check-circle"></i> Nota salva</div>
        </div>
    </div>
    <audio id="nv-wp-audio" autoplay></audio>
</div>

<style>
.nv-call-win { position:fixed; top:90px; right:24px; width:300px; background:#fff; border-radius:16px;
    box-shadow:0 12px 45px rgba(0,0,0,0.28); z-index:11000; overflow:hidden; }
.nv-call-head { display:flex; align-items:center; gap:8px; padding:10px 14px; background:#00BFA6; color:#fff; font-weight:600; cursor:move; user-select:none; font-size:0.9rem; }
.nv-call-head .nv-head-btn { margin-left:auto; background:transparent; border:0; color:#fff; cursor:pointer; font-size:0.9rem; }
.nv-reg-dot { width:9px; height:9px; border-radius:50%; background:#ffd54f; }
.nv-reg-dot.online { background:#b9f6ca; } .nv-reg-dot.offline { background:#ff8a80; }
.nv-call-body { padding:18px 18px 14px; text-align:center; }
.nv-call-avatar { width:64px; height:64px; border-radius:50%; background:#e6faf6; color:#00997D; display:flex; align-items:center; justify-content:center; font-size:1.8rem; margin:0 auto 10px; }
.nv-call-peer { font-size:1.1rem; font-weight:700; color:#222; margin-bottom:2px; word-break:break-word; }
.nv-call-sub { font-size:0.8rem; color:#888; margin-bottom:6px; }
.nv-call-status { color:#666; font-size:0.88rem; margin-bottom:6px; display:flex; align-items:center; justify-content:center; gap:8px; }
.nv-call-timer { font-variant-numeric:tabular-nums; font-weight:700; font-size:1.3rem; color:#00997D; margin-bottom:8px; }
.nv-call-actions { display:flex; align-items:center; justify-content:center; gap:14px; margin:10px 0; }
.nv-btn { width:52px; height:52px; border-radius:50%; border:0; color:#fff; font-size:1.2rem; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:transform .12s, filter .12s; }
.nv-btn:hover { filter:brightness(1.08); transform:translateY(-1px); }
.nv-btn-answer { background:#22c55e; } .nv-btn-mute { background:#6b7280; } .nv-btn-mute.muted { background:#f59e0b; } .nv-btn-hangup { background:#ef4444; }
.nv-call-warn { margin:8px 0; color:#b91c1c; font-size:0.8rem; }
.nv-call-dots { display:inline-flex; gap:3px; }
.nv-call-dots span { width:6px; height:6px; border-radius:50%; background:#00BFA6; animation:nvdot 1s infinite ease-in-out; }
.nv-call-dots span:nth-child(2){ animation-delay:.15s; } .nv-call-dots span:nth-child(3){ animation-delay:.3s; }
@keyframes nvdot { 0%,80%,100%{ opacity:.3; transform:scale(.8);} 40%{ opacity:1; transform:scale(1);} }
.nv-notes-wrap { text-align:left; border-top:1px solid #eef0f2; padding-top:10px; margin-top:6px; }
.nv-notes-label { font-size:0.75rem; font-weight:600; color:#555; display:block; margin-bottom:4px; }
.nv-notes { width:100%; border:1px solid #e5e7eb; border-radius:8px; padding:6px 8px; font-size:0.8rem; resize:vertical; outline:none; }
.nv-notes:focus { border-color:#00BFA6; box-shadow:0 0 0 3px rgba(0,191,166,0.12); }
.nv-notes-actions { display:flex; gap:8px; margin-top:6px; }
.nv-mini-btn { flex:1; border:1px solid #e5e7eb; background:#fff; border-radius:8px; padding:6px 8px; font-size:0.75rem; font-weight:500; color:#555; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; gap:5px; }
.nv-mini-btn:hover { background:#f7f9fa; }
.nv-mini-primary { background:#00BFA6; border-color:#00BFA6; color:#fff; }
.nv-mini-primary:hover { filter:brightness(1.05); background:#00BFA6; }
.nv-note-saved { color:#00997D; font-size:0.75rem; margin-top:5px; text-align:center; }
</style>

<script type="module">
import * as SIP from 'https://cdn.jsdelivr.net/npm/sip.js@0.21.2/lib/index.min.js';
window.SIP = SIP;
(function(){
    const CRM_BASE = '<?= baseUrl("") ?>';
    let ua=null, registerer=null, currentSession=null, sipConfig=null;
    let currentRecordId=null, answeredAt=null, timerInterval=null, isMuted=false;
    let currentLead=null; // {id, name, phone}
    window.nvWebphoneReady=false;

    const $=id=>document.getElementById(id);
    function setStatusText(t){ const el=$('nv-call-status-text'); if(el) el.textContent=t; }
    function showDots(on){ const el=document.querySelector('.nv-call-dots'); if(el) el.style.display= on?'':'none'; }
    function setPeer(t){ const el=$('nv-call-peer'); if(el) el.textContent=t||'—'; }
    function setSub(t){ const el=$('nv-call-sub'); if(el) el.textContent=t||''; }
    function show(id,on){ const el=$(id); if(el) el.style.display= on?'':'none'; }
    function openModal(){ $('nv-call-modal').style.display='block'; $('nv-call-reg-warn').style.display='none'; }
    // Só fecha por ação explícita do usuário
    window.nvCloseWindow=function(){
        if(currentSession){ if(!confirm('Há uma ligação em andamento. Encerrar e fechar?')) return; try{ nvHangup(); }catch(e){} }
        $('nv-call-modal').style.display='none';
    };
    function setRegDot(){ const d=$('nv-call-reg-dot'); if(d) d.className='nv-reg-dot '+(window.nvWebphoneReady?'online':'offline'); }

    function serverLog(level,message,detail){
        try{ const fd=new FormData(); fd.append('level',level); fd.append('message',message);
            if(detail!==undefined) fd.append('detail', typeof detail==='string'?detail:JSON.stringify(detail));
            fetch(CRM_BASE+'crm/webphoneLog',{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}}).catch(()=>{});
        }catch(e){}
    }
    function reportEvent(event,extra){
        if(!currentRecordId) return;
        const fd=new FormData(); fd.append('event',event);
        if(extra) for(const k in extra) fd.append(k,extra[k]);
        fetch(CRM_BASE+'crm/callEvent/'+currentRecordId,{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}}).catch(()=>{});
    }

    function startTimer(){ answeredAt=Date.now(); show('nv-call-timer',true); $('nv-call-timer').textContent='00:00';
        timerInterval=setInterval(()=>{ const s=Math.floor((Date.now()-answeredAt)/1000);
            $('nv-call-timer').textContent=String(Math.floor(s/60)).padStart(2,'0')+':'+String(s%60).padStart(2,'0'); },1000); }
    function stopTimer(){ if(timerInterval){clearInterval(timerInterval);timerInterval=null;} }
    function elapsed(){ return answeredAt?Math.floor((Date.now()-answeredAt)/1000):0; }

    function initWebphone(){
        if(!window.SIP){ console.warn('SIP.js não carregou'); return; }
        preloadCredentials();
    }
    let reconnecting=false, reconnectTimer=null;

    function startUA(cfg){
        try{
            if(ua){ return; }
            const uri=SIP.UserAgent.makeURI(cfg.uri);
            const iceServers = (cfg.ice_servers && cfg.ice_servers.length) ? cfg.ice_servers : [{urls:'stun:stun.l.google.com:19302'}];
            ua=new SIP.UserAgent({
                uri, transportOptions:{server:cfg.ws_server},
                authorizationUsername:cfg.sip_user, authorizationPassword:cfg.sip_password, displayName:cfg.sip_user,
                sessionDescriptionHandlerFactoryOptions:{ peerConnectionConfiguration:{ iceServers } },
                delegate:{ onInvite:inv=>handleIncoming(inv) }
            });
            try{
                const t = ua.transport;
                if(t && t.onWebSocketClose){
                    const orig = t.onWebSocketClose.bind(t);
                    t.onWebSocketClose = function(ev){ try{ serverLog('error','WS fechado code='+(ev&&ev.code)+' wasClean='+(ev&&ev.wasClean)); }catch(e){} return orig(ev); };
                }
            }catch(e){}
            ua.transport.stateChange.addListener(ts=>{
                if(ts===SIP.TransportState.Connected){ reconnecting=false; }
                if(ts===SIP.TransportState.Disconnected){ scheduleReconnect(); }
            });
            registerer=new SIP.Registerer(ua);
            registerer.stateChange.addListener(s=>{ window.nvWebphoneReady=(s===SIP.RegistererState.Registered); setRegDot(); serverLog('info','Registerer '+s); });
            ua.start().then(()=>doRegister()).catch(e=>{ window.nvWebphoneReady=false; setRegDot(); serverLog('error','UA start falhou', String(e&&e.message||e)); scheduleReconnect(); });
        }catch(e){ console.warn('Webphone init falhou',e); }
    }
    function doRegister(){
        if(!registerer) return;
        registerer.register({ requestDelegate:{ onReject:resp=>{ const code=resp&&resp.message?resp.message.statusCode:'';
            window.nvWebphoneReady=false; window.nvRegRejectCode=code; setRegDot(); serverLog('error','REGISTER rejeitado '+code); }}}).catch(()=>{});
    }
    function releaseRamal(){
        if(currentSession) return;
        try{
            reconnecting=true;
            if(registerer){ registerer.unregister().catch(()=>{}); }
            setTimeout(()=>{ try{ if(ua && !currentSession){ ua.stop().catch(()=>{}); ua=null; registerer=null; window.nvWebphoneReady=false; setRegDot(); } }catch(e){} reconnecting=false; }, 1000);
        }catch(e){ reconnecting=false; }
    }
    function scheduleReconnect(){
        if(reconnecting) return;
        reconnecting=true;
        if(reconnectTimer) clearTimeout(reconnectTimer);
        reconnectTimer=setTimeout(()=>{
            if(!ua){ reconnecting=false; return; }
            if(ua.transport.state===SIP.TransportState.Connected){ reconnecting=false; return; }
            ua.reconnect().then(()=>{ doRegister(); }).catch(()=>{ reconnecting=false; scheduleReconnect(); });
        }, 4000);
    }

    function attachIceDiagnostics(session){
        window._nvStatsChecked=false;
        let waited=0, lastIce='';
        const iv=setInterval(()=>{
            waited+=500;
            const pc=session.sessionDescriptionHandler&&session.sessionDescriptionHandler.peerConnection;
            if(!pc){ if(waited>=6000){ clearInterval(iv); } return; }
            if(pc.iceConnectionState!==lastIce){ lastIce=pc.iceConnectionState; serverLog('info','ICE state: '+lastIce); }
            if((lastIce==='connected'||lastIce==='completed') && !window._nvStatsChecked){
                window._nvStatsChecked=true;
                setTimeout(()=>{ try{ pc.getStats().then(stats=>{ let inb=0,outb=0;
                    stats.forEach(r=>{ if(r.type==='inbound-rtp'&&r.kind==='audio') inb=r.packetsReceived||0; if(r.type==='outbound-rtp'&&r.kind==='audio') outb=r.packetsSent||0; });
                    serverLog('info','RTP áudio: recebidos='+inb+' enviados='+outb); }); }catch(e){} }, 3000);
            }
            if(waited>=25000 || lastIce==='failed' || lastIce==='closed'){ clearInterval(iv); }
        },500);
    }

    function attachAudio(session){
        const remote=$('nv-wp-audio');
        const pc=session.sessionDescriptionHandler&&session.sessionDescriptionHandler.peerConnection;
        if(!pc) return;
        const bind=()=>{ const st=new MediaStream(); pc.getReceivers().forEach(r=>{ if(r.track) st.addTrack(r.track); }); remote.srcObject=st; remote.play&&remote.play().catch(()=>{}); };
        bind(); pc.ontrack=bind;
    }

    window.nvToggleMute=function(){
        const s=currentSession; if(!s||!s.sessionDescriptionHandler) return;
        const pc=s.sessionDescriptionHandler.peerConnection; if(!pc) return;
        isMuted=!isMuted;
        pc.getSenders().forEach(sn=>{ if(sn.track&&sn.track.kind==='audio') sn.track.enabled=!isMuted; });
        const b=$('nv-call-mute'); b.classList.toggle('muted',isMuted);
        b.innerHTML= isMuted?'<i class="bi bi-mic-mute-fill"></i>':'<i class="bi bi-mic-fill"></i>';
    };
    function resetControls(){ show('nv-call-answer',false); show('nv-call-mute',false); isMuted=false; stopTimer(); }

    function cleanupSession(){
        const s=currentSession;
        if(s){ try{ if(s.state===SIP.SessionState.Established) s.bye(); else if(s instanceof SIP.Inviter) s.cancel(); else if(s.reject) s.reject(); }catch(e){} }
        currentSession=null; answeredAt=null; resetControls();
    }

    function wireSession(session, peerLabel){
        currentSession=session; if(peerLabel) setPeer(peerLabel);
        session.stateChange.addListener(state=>{
            if(state===SIP.SessionState.Establishing){ showDots(true); setStatusText('Chamando...'); reportEvent('ringing'); }
            else if(state===SIP.SessionState.Established){ showDots(false); setStatusText('Em chamada'); show('nv-call-mute',true); show('nv-call-answer',false);
                attachAudio(session); startTimer(); reportEvent('answered');
                // Monitora desconexão remota via peerConnection
                monitorRemoteHangup(session);
            }
            else if(state===SIP.SessionState.Terminated){ onCallEnded(); }
        });
    }

    function onCallEnded(){
        const dur=elapsed(); showDots(false);
        setStatusText('Chamada encerrada' + (dur? ' • '+fmtDur(dur) : ''));
        resetControls();
        show('nv-call-hangup', false); // Esconde o botão vermelho
        reportEvent('ended',{duration:dur});
        currentSession=null; answeredAt=null;
    }

    /** Monitora se a outra parte desligou (ICE disconnected / connection closed). */
    function monitorRemoteHangup(session){
        const sdh = session.sessionDescriptionHandler;
        if(!sdh || !sdh.peerConnection) return;
        const pc = sdh.peerConnection;
        pc.onconnectionstatechange = function(){
            if(pc.connectionState === 'disconnected' || pc.connectionState === 'failed' || pc.connectionState === 'closed'){
                // Outra parte desligou — encerra do nosso lado também
                if(currentSession && currentSession.state === SIP.SessionState.Established){
                    try { currentSession.bye(); } catch(e){}
                }
            }
        };
    }
    function fmtDur(s){ return String(Math.floor(s/60)).padStart(2,'0')+':'+String(s%60).padStart(2,'0'); }

    function handleIncoming(invitation){
        openModal();
        const from=invitation.remoteIdentity&&invitation.remoteIdentity.uri?invitation.remoteIdentity.uri.user:'';
        wireSession(invitation, from); window._nvInvitation=invitation;
        setSub(from ? ('Número: '+from) : '');
        const fd=new FormData(); fd.append('from',from||'');
        fetch(CRM_BASE+'crm/registerInbound',{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}})
            .then(r=>r.json()).then(d=>{ if(d&&d.call_record_id) currentRecordId=d.call_record_id; }).catch(()=>{});
        if(window._nvAutoAnswer){
            window._nvAutoAnswer=false;
            showDots(true); setStatusText('Conectando...');
            invitation.accept({sessionDescriptionHandlerOptions:{constraints:{audio:true,video:false}}});
        } else {
            showDots(true); setStatusText('Chamada recebida'); show('nv-call-answer',true);
        }
    }

    window.nvAnswer=function(){ const inv=window._nvInvitation; if(!inv) return;
        inv.accept({sessionDescriptionHandlerOptions:{constraints:{audio:true,video:false}}}); show('nv-call-answer',false); };
    window.nvHangup=function(){ const s=currentSession;
        if(!s){ if(currentRecordId){ reportEvent('ended',{duration:0,cause:'cancelled'}); } setStatusText('Chamada encerrada'); showDots(false); resetControls(); show('nv-call-hangup', false); return; }
        try{ if(s.state===SIP.SessionState.Established) s.bye(); else if(s instanceof SIP.Inviter) s.cancel(); else s.reject(); }catch(e){} };

    // Abre o briefing do lead (usa o modal de gerenciamento da tela de Leads, se existir)
    window.nvOpenBriefing=function(){
        if(currentLead && currentLead.id && typeof window.openLead==='function'){ window.openLead(currentLead.id); }
        else { alert('Briefing disponível na tela Meus Leads (botão gerenciar).'); }
    };

    // Salva a nota da ligação no backend (registro + briefing)
    window.nvSaveNote=function(btn){
        if(!currentRecordId){ alert('Sem registro de ligação para anexar a nota.'); return; }
        const note=$('nv-call-note').value.trim();
        const o=btn.innerHTML; btn.disabled=true; btn.innerHTML='<span class="spinner-border spinner-border-sm"></span>';
        const fd=new FormData(); fd.append('note', note); if(currentLead&&currentLead.id) fd.append('contact_id', currentLead.id);
        fetch(CRM_BASE+'crm/saveCallNote/'+currentRecordId,{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}})
            .then(r=>r.json()).then(()=>{ btn.disabled=false; btn.innerHTML=o; const s=$('nv-note-saved'); s.style.display=''; setTimeout(()=>s.style.display='none',2500); })
            .catch(()=>{ btn.disabled=false; btn.innerHTML=o; alert('Erro ao salvar a nota.'); });
    };

    // === API pública de discagem (LÓGICA SIP INALTERADA) ===
    // Aceita (numero, recordId, lead) — lead = {id, name, phone} para exibir no modal.
    window.nvCall=function(numero, recordId, lead){
        if(currentSession){ alert('Já existe uma ligação em andamento. Encerre-a antes de iniciar outra.'); return false; }
        if(!sipConfig && !window.nvNoExtension){ preloadCredentials(); alert('Webphone ainda inicializando. Aguarde 2 segundos e tente novamente.'); return false; }
        currentLead = lead || null;
        currentRecordId=recordId||null;
        openModal();
        setPeer(lead && lead.name ? lead.name : numero);
        setSub(numero ? ('Número: '+numero) : '');
        $('nv-call-note').value='';
        showDots(true); setStatusText('Preparando...'); show('nv-call-answer',false); show('nv-call-mute',false); stopTimer(); show('nv-call-timer',false);

        if(window.nvNoExtension){
            showDots(false); setStatusText('Sem ramal');
            const w=$('nv-call-reg-warn'); w.style.display=''; w.textContent='Seu usuário não tem Ramal/Senha SIP cadastrados. Peça ao administrador em Usuários → editar.';
            return false;
        }
        Promise.all([
            ensureRegistered(),
            navigator.mediaDevices.getUserMedia({audio:true, video:false})
        ]).then(()=>{ serverLog('info','Registrado + Microfone OK'); setStatusText('Chamando...'); doDial(numero); })
        .catch(err=>{
            showDots(false);
            const w=$('nv-call-reg-warn'); w.style.display='';
            if(err==='no_extension'){ setStatusText('Sem ramal'); w.textContent='Seu usuário não tem Ramal/Senha SIP cadastrados.'; }
            else if(err==='timeout'){ setStatusText('Ramal indisponível'); w.textContent='Não foi possível registrar seu ramal. Verifique o ramal/senha no seu cadastro.'; }
            else { setStatusText('Microfone bloqueado'); w.textContent='Permita o acesso ao microfone no navegador para ligar.'; }
            serverLog('error','Falha ao preparar ligação', String(err&&err.name||err||''));
            reportEvent('ended',{duration:0,cause:'prepare_failed'});
        });
        return true;
    };

    function sanitizeDial(n){
        n = String(n).trim();
        const plus = n.charAt(0)==='+';
        n = n.replace(/\D/g,'');
        while (n.length > 13 && n.indexOf('55') === 0) { n = n.substring(2); }
        return plus ? ('+'+n) : n;
    }

    function doDial(numero){
        numero = sanitizeDial(numero);
        const uriStr = 'sip:'+numero+'@'+sipConfig.domain;
        serverLog('info','doDial final='+numero+' uri='+uriStr);
        const target=SIP.UserAgent.makeURI(uriStr);
        if(!window.nvWebphoneReady){
            showDots(false); setStatusText('Ramal indisponível');
            const w=$('nv-call-reg-warn'); w.style.display='';
            w.textContent='Ramal não registrado. Aguarde alguns segundos e tente novamente.';
            reportEvent('ended',{duration:0,cause:'ramal_indisponivel'}); return false;
        }
        serverLog('info','INVITE enviado para '+numero);
        const inviter=new SIP.Inviter(ua,target,{ earlyMedia:true, sessionDescriptionHandlerOptions:{constraints:{audio:true,video:false}}});
        try{
            const toUri = inviter.request && inviter.request.to && inviter.request.to.uri ? inviter.request.to.uri.toString() : '';
            const ruri = inviter.request && inviter.request.ruri ? inviter.request.ruri.toString() : '';
            serverLog('info','SIP To='+toUri+' RURI='+ruri);
        }catch(e){}
        wireSession(inviter, null);
        inviter.invite({ requestDelegate:{
            onProgress:r=>{ serverLog('info','INVITE progress '+r.message.statusCode+' '+r.message.reasonPhrase); },
            onAccept:r=>{ serverLog('info','INVITE accept '+r.message.statusCode); },
            onReject:r=>{ const code=r.message.statusCode; showDots(false); setStatusText('Recusada ('+code+')');
                serverLog('error','INVITE rejeitado '+code+' '+r.message.reasonPhrase);
                if(code===503){ const w=$('nv-call-reg-warn'); if(w){ w.style.display=''; w.textContent='Limite de chamadas simultâneas no ramal. Aguarde ~1 min e tente de novo.'; } }
                cleanupSession(); /* NÃO fecha o modal — usuário decide */
            }
        }}).catch(e=>{ showDots(false); setStatusText('Falha ao chamar'); serverLog('error','invite() exception',String(e&&e.message||e)); reportEvent('ended',{duration:0,cause:'invite_failed'}); cleanupSession(); });
        attachIceDiagnostics(inviter);
        return true;
    }

    function preloadCredentials(){
        if(!window.SIP){ return; }
        fetch(CRM_BASE+'crm/sipCredentials',{headers:{'X-Requested-With':'XMLHttpRequest'}})
            .then(r=>r.json()).then(cfg=>{ if(!cfg.configured){ window.nvNoExtension=true; return; } sipConfig=cfg; }).catch(()=>{});
    }

    function ensureRegistered(){
        return new Promise((resolve, reject)=>{
            if(window.nvWebphoneReady){ resolve(); return; }
            if(!sipConfig){ reject('no_extension'); return; }
            if(!ua) startUA(sipConfig);
            let waited=0;
            const iv=setInterval(()=>{ waited+=300;
                if(window.nvWebphoneReady){ clearInterval(iv); resolve(); }
                else if(waited>=8000){ clearInterval(iv); reject('timeout'); } },300);
        });
    }

    // === Arrastar a janela ===
    (function makeDraggable(){
        const win=$('nv-call-modal'), handle=$('nv-call-drag');
        if(!win||!handle) return;
        let dx=0,dy=0,drag=false;
        handle.addEventListener('mousedown',e=>{ if(e.target.closest('.nv-head-btn')) return;
            drag=true; const r=win.getBoundingClientRect(); dx=e.clientX-r.left; dy=e.clientY-r.top;
            win.style.right='auto'; win.style.bottom='auto'; document.body.style.userSelect='none'; });
        document.addEventListener('mousemove',e=>{ if(!drag) return;
            let x=Math.max(0,Math.min(e.clientX-dx, window.innerWidth-win.offsetWidth));
            let y=Math.max(0,Math.min(e.clientY-dy, window.innerHeight-win.offsetHeight));
            win.style.left=x+'px'; win.style.top=y+'px'; });
        document.addEventListener('mouseup',()=>{ drag=false; document.body.style.userSelect=''; });
    })();

    if(document.readyState==='loading') document.addEventListener('DOMContentLoaded', initWebphone);
    else initWebphone();
})();
</script>
