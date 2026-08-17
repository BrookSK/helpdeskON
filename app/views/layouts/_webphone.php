<?php
// Webphone nativo (WebRTC / SIP over WSS) — roda dentro do CRM.
// Config conforme doc Nvoip (WSS): wss://app.nvoip.com.br:7443, domínio app.nvoip.com.br,
// authorizationUsername = ramal, authorizationPassword = senha SIP.
// O ramal registra em segundo plano; ao ligar/receber, abre um modal com timer e controles.
?>
<div id="nv-call-modal" class="nv-call-overlay" style="display:none;">
    <div class="nv-call-box">
        <div class="nv-call-avatar"><i class="bi bi-person-fill"></i></div>
        <div id="nv-call-peer" class="nv-call-peer">—</div>
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
    </div>
    <audio id="nv-wp-audio" autoplay></audio>
</div>

<style>
.nv-call-overlay { position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:11000; display:flex; align-items:center; justify-content:center; }
.nv-call-box { background:#fff; border-radius:18px; width:300px; max-width:92vw; padding:26px 22px; text-align:center; box-shadow:0 12px 50px rgba(0,0,0,0.3); }
.nv-call-avatar { width:72px; height:72px; border-radius:50%; background:#e6faf6; color:#00997D; display:flex; align-items:center; justify-content:center; font-size:2rem; margin:0 auto 12px; }
.nv-call-peer { font-size:1.15rem; font-weight:700; color:#222; margin-bottom:4px; word-break:break-all; }
.nv-call-status { color:#666; font-size:0.9rem; margin-bottom:6px; display:flex; align-items:center; justify-content:center; gap:8px; }
.nv-call-timer { font-variant-numeric:tabular-nums; font-weight:700; font-size:1.4rem; color:#00997D; margin-bottom:8px; }
.nv-call-actions { display:flex; align-items:center; justify-content:center; gap:16px; margin-top:16px; }
.nv-btn { width:56px; height:56px; border-radius:50%; border:0; color:#fff; font-size:1.3rem; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:transform .12s, filter .12s; }
.nv-btn:hover { filter:brightness(1.08); transform:translateY(-1px); }
.nv-btn-answer { background:#22c55e; }
.nv-btn-mute { background:#6b7280; } .nv-btn-mute.muted { background:#f59e0b; }
.nv-btn-hangup { background:#ef4444; }
.nv-call-warn { margin-top:14px; color:#b91c1c; font-size:0.82rem; }
.nv-call-dots { display:inline-flex; gap:3px; }
.nv-call-dots span { width:6px; height:6px; border-radius:50%; background:#00BFA6; animation:nvdot 1s infinite ease-in-out; }
.nv-call-dots span:nth-child(2){ animation-delay:.15s; } .nv-call-dots span:nth-child(3){ animation-delay:.3s; }
@keyframes nvdot { 0%,80%,100%{ opacity:.3; transform:scale(.8);} 40%{ opacity:1; transform:scale(1);} }
</style>

<script type="module">
import * as SIP from 'https://cdn.jsdelivr.net/npm/sip.js@0.21.2/lib/index.min.js';
window.SIP = SIP;
(function(){
    const CRM_BASE = '<?= baseUrl("") ?>';
    let ua=null, registerer=null, currentSession=null, sipConfig=null;
    let currentRecordId=null, answeredAt=null, timerInterval=null, isMuted=false;
    window.nvWebphoneReady=false;

    const $=id=>document.getElementById(id);
    function setStatusText(t){ const el=$('nv-call-status-text'); if(el) el.textContent=t; }
    function showDots(on){ const el=document.querySelector('.nv-call-dots'); if(el) el.style.display= on?'':'none'; }
    function setPeer(t){ const el=$('nv-call-peer'); if(el) el.textContent=t||'—'; }
    function show(id,on){ const el=$(id); if(el) el.style.display= on?'':'none'; }
    function openModal(){ $('nv-call-modal').style.display='flex'; $('nv-call-reg-warn').style.display='none'; }
    function closeModal(){ $('nv-call-modal').style.display='none'; }

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
    function stopTimer(){ if(timerInterval){clearInterval(timerInterval);timerInterval=null;} show('nv-call-timer',false); }
    function elapsed(){ return answeredAt?Math.floor((Date.now()-answeredAt)/1000):0; }

    function initWebphone(){
        if(!window.SIP){ console.warn('SIP.js não carregou'); return; }
        fetch(CRM_BASE+'crm/sipCredentials',{headers:{'X-Requested-With':'XMLHttpRequest'}})
            .then(r=>r.json()).then(cfg=>{
                if(!cfg.configured){ window.nvNoExtension=true; serverLog('info','Webphone sem credenciais para o usuário ('+(cfg.reason||'')+')'); return; }
                sipConfig=cfg; startUA(cfg);
            }).catch(()=>{});
    }
    let reconnecting=false, reconnectTimer=null;

    function startUA(cfg){
        try{
            if(ua){ serverLog('info','UA já inicializado, ignorando'); return; } // instância única
            const uri=SIP.UserAgent.makeURI(cfg.uri);
            const iceServers = (cfg.ice_servers && cfg.ice_servers.length) ? cfg.ice_servers : [{urls:'stun:stun.l.google.com:19302'}];
            ua=new SIP.UserAgent({
                uri, transportOptions:{server:cfg.ws_server},
                authorizationUsername:cfg.sip_user, authorizationPassword:cfg.sip_password, displayName:cfg.sip_user,
                sessionDescriptionHandlerFactoryOptions:{ peerConnectionConfiguration:{ iceServers } },
                delegate:{ onInvite:inv=>handleIncoming(inv) }
            });

            // Captura o código real de fechamento do WebSocket (diagnóstico)
            try{
                const t = ua.transport;
                if(t && t.onWebSocketClose){
                    const orig = t.onWebSocketClose.bind(t);
                    t.onWebSocketClose = function(ev){ try{ serverLog('error','WS fechado code='+(ev&&ev.code)+' wasClean='+(ev&&ev.wasClean)); }catch(e){} return orig(ev); };
                }
            }catch(e){}

            // ÚNICO ponto de reconexão: ao desconectar, agenda uma reconexão controlada (single-flight)
            ua.transport.stateChange.addListener(ts=>{
                console.log('[Webphone] Transport:',ts); serverLog('info','Transport '+ts);
                if(ts===SIP.TransportState.Connected){ reconnecting=false; }
                if(ts===SIP.TransportState.Disconnected){ scheduleReconnect(); }
            });

            registerer=new SIP.Registerer(ua);
            registerer.stateChange.addListener(s=>{ console.log('[Webphone] Registerer:',s);
                window.nvWebphoneReady=(s===SIP.RegistererState.Registered); serverLog('info','Registerer '+s); });

            ua.start().then(()=>doRegister()).catch(e=>{
                window.nvWebphoneReady=false; serverLog('error','UA start falhou', String(e&&e.message||e)); scheduleReconnect();
            });
        }catch(e){ console.warn('Webphone init falhou',e); }
    }

    function doRegister(){
        if(!registerer) return;
        registerer.register({ requestDelegate:{ onReject:resp=>{ const code=resp&&resp.message?resp.message.statusCode:'';
            window.nvWebphoneReady=false; window.nvRegRejectCode=code; serverLog('error','REGISTER rejeitado '+code); }}}).catch(()=>{});
    }

    // Libera o ramal após a chamada: desregistra e para o UA, para não ocupar o ramal em repouso.
    function releaseRamal(){
        try{
            reconnecting=true; // impede reconexão automática após desligar de propósito
            if(registerer){ registerer.unregister().catch(()=>{}); }
            setTimeout(()=>{ try{ if(ua){ ua.stop().catch(()=>{}); } }catch(e){} ua=null; registerer=null; window.nvWebphoneReady=false; reconnecting=false; }, 800);
        }catch(e){ ua=null; registerer=null; window.nvWebphoneReady=false; reconnecting=false; }
    }

    // Reconexão única com backoff — evita múltiplas tentativas concorrentes que derrubam o registro
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

    // Diagnóstico de ICE/mídia por POLLING (não depende de callbacks que o SIP.js pode sobrescrever).
    function attachIceDiagnostics(session){
        window._nvStatsChecked=false;
        let waited=0, found=false, lastIce='', lastGath='', logged=0;
        const iv=setInterval(()=>{
            waited+=500;
            const pc=session.sessionDescriptionHandler&&session.sessionDescriptionHandler.peerConnection;
            if(!pc){ if(waited>=6000){ clearInterval(iv); serverLog('error','PeerConnection não encontrado (SDH)'); } return; }
            if(!found){ found=true; serverLog('info','PeerConnection detectado'); }
            if(pc.iceConnectionState!==lastIce){ lastIce=pc.iceConnectionState; serverLog('info','ICE state: '+lastIce);
                if(lastIce==='failed') serverLog('error','ICE falhou (mídia não estabelecida)'); }
            if(pc.iceGatheringState!==lastGath){ lastGath=pc.iceGatheringState; serverLog('info','ICE gathering: '+lastGath); }
            // Ao completar a coleta, conta os tipos de candidato local pela SDP
            if(pc.iceGatheringState==='complete' && !logged){
                logged=1;
                try{
                    const sdp=(pc.localDescription&&pc.localDescription.sdp)||'';
                    const host=(sdp.match(/typ host/g)||[]).length;
                    const srflx=(sdp.match(/typ srflx/g)||[]).length;
                    const relay=(sdp.match(/typ relay/g)||[]).length;
                    serverLog('info','ICE candidates (SDP): host='+host+' srflx='+srflx+' relay='+relay);
                }catch(e){}
            }
            // Ao conectar, checa estatísticas de RTP após 3s (se não houver mídia, a Nvoip derruba)
            if((lastIce==='connected'||lastIce==='completed') && !window._nvStatsChecked){
                window._nvStatsChecked=true;
                setTimeout(()=>{ try{ pc.getStats().then(stats=>{
                    let inb=0, outb=0;
                    stats.forEach(r=>{ if(r.type==='inbound-rtp'&&r.kind==='audio') inb=r.packetsReceived||0;
                        if(r.type==='outbound-rtp'&&r.kind==='audio') outb=r.packetsSent||0; });
                    serverLog('info','RTP áudio: recebidos='+inb+' enviados='+outb);
                }); }catch(e){} }, 3000);
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

    // Encerra e descarta a sessão atual para NÃO deixar chamada pendurada na Nvoip (evita 503).
    function cleanupSession(){
        const s=currentSession;
        if(s){
            try{
                if(s.state===SIP.SessionState.Established) s.bye();
                else if(s instanceof SIP.Inviter) s.cancel();
                else if(s.reject) s.reject();
            }catch(e){}
        }
        currentSession=null; answeredAt=null; resetControls();
    }

    function wireSession(session, peerLabel){
        currentSession=session; setPeer(peerLabel||'');
        session.stateChange.addListener(state=>{
            if(state===SIP.SessionState.Establishing){ showDots(true); setStatusText('Chamando...'); reportEvent('ringing'); }
            else if(state===SIP.SessionState.Established){ showDots(false); setStatusText('Em chamada'); show('nv-call-mute',true); show('nv-call-answer',false);
                attachAudio(session); startTimer(); reportEvent('answered'); }
            else if(state===SIP.SessionState.Terminated){ const dur=elapsed(); showDots(false); setStatusText('Chamada encerrada'); resetControls();
                serverLog('info','Session Terminated (duração '+dur+'s)');
                reportEvent('ended',{duration:dur}); currentSession=null; currentRecordId=null; answeredAt=null; setTimeout(closeModal,900);
                // Libera o ramal ao encerrar (desregistra) — evita manter o ramal ocupado após a chamada
                releaseRamal(); }
        });
    }

    function handleIncoming(invitation){
        openModal();
        const from=invitation.remoteIdentity&&invitation.remoteIdentity.uri?invitation.remoteIdentity.uri.user:'';
        showDots(true); setStatusText('Chamada recebida'); setPeer(from); show('nv-call-answer',true);
        const fd=new FormData(); fd.append('from',from||'');
        fetch(CRM_BASE+'crm/registerInbound',{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}})
            .then(r=>r.json()).then(d=>{ if(d&&d.call_record_id) currentRecordId=d.call_record_id; }).catch(()=>{});
        wireSession(invitation, from); window._nvInvitation=invitation;
    }

    window.nvAnswer=function(){ const inv=window._nvInvitation; if(!inv) return;
        inv.accept({sessionDescriptionHandlerOptions:{constraints:{audio:true,video:false}}}); show('nv-call-answer',false); };
    window.nvHangup=function(){ const s=currentSession;
        if(!s){ if(currentRecordId){ reportEvent('ended',{duration:0,cause:'cancelled'}); currentRecordId=null; } closeModal(); return; }
        try{ if(s.state===SIP.SessionState.Established) s.bye(); else if(s instanceof SIP.Inviter) s.cancel(); else s.reject(); }catch(e){} };

    window.nvCall=function(numero, recordId){
        if(!ua||!sipConfig){ alert('Webphone não inicializado.'); return false; }
        // Trava: impede iniciar nova ligação enquanto há uma sessão ativa (evita acúmulo no ramal → 503/480)
        if(currentSession){ alert('Já existe uma ligação em andamento. Encerre-a antes de iniciar outra.'); return false; }
        const target=SIP.UserAgent.makeURI('sip:'+numero+'@'+sipConfig.domain);
        if(!target){ alert('Número inválido.'); return false; }
        if(window.nvNoExtension){
            openModal(); setPeer(numero); showDots(false); setStatusText('Sem ramal');
            const w=$('nv-call-reg-warn'); w.style.display=''; w.textContent='Seu usuário não tem Ramal/Senha SIP cadastrados. Peça ao administrador em Usuários → editar.';
            return false;
        }
        currentRecordId=recordId||null;
        openModal(); setPeer(numero); showDots(true); setStatusText('Preparando...'); show('nv-call-answer',false); show('nv-call-mute',false); stopTimer();

        // Registra o ramal sob demanda (só ao ligar) e pede o microfone, então disca.
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
            reportEvent('ended',{duration:0,cause:'prepare_failed'}); currentRecordId=null;
        });
        return true;
    };

    function doDial(numero){
        const target=SIP.UserAgent.makeURI('sip:'+numero+'@'+sipConfig.domain);
        if(!window.nvWebphoneReady){
            showDots(false); setStatusText('Ramal indisponível');
            const w=$('nv-call-reg-warn'); w.style.display='';
            if(window.nvNoExtension){
                w.textContent='Seu usuário não tem Ramal/Senha SIP cadastrados. Peça ao administrador para preencher em Usuários → editar.';
            } else if(window.nvRegRejectCode===401||window.nvRegRejectCode===403){
                w.textContent='Senha SIP incorreta para o seu ramal. Verifique no cadastro do usuário.';
            } else {
                w.textContent='Ramal não registrado. Aguarde alguns segundos e tente novamente, ou verifique o ramal no cadastro do usuário.';
            }
            reportEvent('ended',{duration:0,cause:'ramal_indisponivel'}); currentRecordId=null; return false;
        }
        serverLog('info','INVITE enviado para '+numero);
        // earlyMedia: aplica a SDP que a Nvoip envia no 183 (sem isso o ICE fica em "new" e dá 408)
        const inviter=new SIP.Inviter(ua,target,{ earlyMedia:true, sessionDescriptionHandlerOptions:{constraints:{audio:true,video:false}}});
        wireSession(inviter, numero);
        inviter.invite({ requestDelegate:{
            onProgress:r=>{ serverLog('info','INVITE progress '+r.message.statusCode+' '+r.message.reasonPhrase); },
            onAccept:r=>{ serverLog('info','INVITE accept '+r.message.statusCode); },
            onReject:r=>{ const code=r.message.statusCode; showDots(false); setStatusText('Recusada ('+code+')');
                serverLog('error','INVITE rejeitado '+code+' '+r.message.reasonPhrase);
                // Mensagem clara para 503 (limite de chamadas simultâneas do ramal)
                if(code===503){ const w=$('nv-call-reg-warn'); if(w){ w.style.display=''; w.textContent='Limite de chamadas simultâneas atingido no ramal. Aguarde ~1 min (chamadas presas expiram) e tente de novo.'; } }
                cleanupSession(); setTimeout(closeModal,2000);
            }
        }}).catch(e=>{ showDots(false); setStatusText('Falha ao chamar'); serverLog('error','invite() exception',String(e&&e.message||e)); reportEvent('ended',{duration:0,cause:'invite_failed'}); cleanupSession(); setTimeout(closeModal,1500); });
        attachIceDiagnostics(inviter);
        return true;
    };

    // Carrega apenas as credenciais (não registra ainda). O registro é sob demanda, ao ligar.
    function preloadCredentials(){
        if(!window.SIP){ return; }
        fetch(CRM_BASE+'crm/sipCredentials',{headers:{'X-Requested-With':'XMLHttpRequest'}})
            .then(r=>r.json()).then(cfg=>{
                if(!cfg.configured){ window.nvNoExtension=true; return; }
                sipConfig=cfg; // guarda; só registra quando o usuário for ligar
            }).catch(()=>{});
    }

    // Registra o ramal sob demanda (retorna Promise que resolve quando registrar)
    function ensureRegistered(){
        return new Promise((resolve, reject)=>{
            if(window.nvWebphoneReady){ resolve(); return; }
            if(!sipConfig){ reject('no_extension'); return; }
            if(!ua) startUA(sipConfig);
            // Aguarda o registro até 8s
            let waited=0;
            const iv=setInterval(()=>{
                waited+=300;
                if(window.nvWebphoneReady){ clearInterval(iv); resolve(); }
                else if(waited>=8000){ clearInterval(iv); reject('timeout'); }
            },300);
        });
    }

    if(document.readyState==='loading') document.addEventListener('DOMContentLoaded', preloadCredentials);
    else preloadCredentials();
})();
</script>
