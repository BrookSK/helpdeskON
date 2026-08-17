<?php
// Webphone nativo (WebRTC / SIP over WSS) — roda dentro do CRM.
// O ramal registra em segundo plano (invisível). Ao iniciar/receber uma chamada, abre um MODAL
// com estado "Chamando..." (loading) e controles: mudo, cancelar, desligar. Fecha ao encerrar/cancelar.
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
            <button type="button" id="nv-call-answer" class="nv-btn nv-btn-answer" style="display:none;" onclick="nvAnswer()" title="Atender">
                <i class="bi bi-telephone-inbound-fill"></i>
            </button>
            <button type="button" id="nv-call-mute" class="nv-btn nv-btn-mute" style="display:none;" onclick="nvToggleMute()" title="Mudo">
                <i class="bi bi-mic-fill"></i>
            </button>
            <button type="button" id="nv-call-hangup" class="nv-btn nv-btn-hangup" onclick="nvHangup()" title="Encerrar">
                <i class="bi bi-telephone-x-fill"></i>
            </button>
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
.nv-btn-mute { background:#6b7280; }
.nv-btn-mute.muted { background:#f59e0b; }
.nv-btn-hangup { background:#ef4444; }
.nv-call-warn { margin-top:14px; color:#b91c1c; font-size:0.82rem; }
.nv-call-dots { display:inline-flex; gap:3px; }
.nv-call-dots span { width:6px; height:6px; border-radius:50%; background:#00BFA6; animation:nvdot 1s infinite ease-in-out; }
.nv-call-dots span:nth-child(2){ animation-delay:.15s; } .nv-call-dots span:nth-child(3){ animation-delay:.3s; }
@keyframes nvdot { 0%,80%,100%{ opacity:.3; transform:scale(.8);} 40%{ opacity:1; transform:scale(1);} }
</style>

<!-- SIP.js (WebRTC over WSS) carregado como módulo ESM -->
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

    function reportEvent(event, extra){
        if(!currentRecordId) return;
        const fd=new FormData(); fd.append('event',event);
        if(extra) for(const k in extra) fd.append(k, extra[k]);
        fetch(CRM_BASE+'crm/callEvent/'+currentRecordId,{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}}).catch(()=>{});
    }

    function startTimer(){
        answeredAt=Date.now(); show('nv-call-timer',true); $('nv-call-timer').textContent='00:00';
        timerInterval=setInterval(()=>{
            const s=Math.floor((Date.now()-answeredAt)/1000);
            $('nv-call-timer').textContent=String(Math.floor(s/60)).padStart(2,'0')+':'+String(s%60).padStart(2,'0');
        },1000);
    }
    function stopTimer(){ if(timerInterval){clearInterval(timerInterval);timerInterval=null;} show('nv-call-timer',false); }
    function elapsed(){ return answeredAt?Math.floor((Date.now()-answeredAt)/1000):0; }

    // === Registro do ramal em segundo plano ===
    function initWebphone(){
        if(!window.SIP){ console.warn('SIP.js não carregou'); return; }
        fetch(CRM_BASE+'crm/sipCredentials',{headers:{'X-Requested-With':'XMLHttpRequest'}})
            .then(r=>r.json()).then(cfg=>{ if(!cfg.configured) return; sipConfig=cfg; startUA(cfg); }).catch(()=>{});
    }
    function startUA(cfg){
        try{
            const uri=SIP.UserAgent.makeURI(cfg.uri);
            ua=new SIP.UserAgent({ uri, transportOptions:{server:cfg.ws_server},
                authorizationUsername:cfg.sip_user, authorizationPassword:cfg.sip_password, displayName:cfg.sip_user,
                delegate:{ onInvite:inv=>handleIncoming(inv) } });
            ua.transport.stateChange.addListener(ts=>console.log('[Webphone] Transport:',ts));
            ua.start().then(()=>{
                registerer=new SIP.Registerer(ua);
                registerer.stateChange.addListener(s=>{
                    console.log('[Webphone] Registerer:',s);
                    window.nvWebphoneReady = (s===SIP.RegistererState.Registered);
                });
                registerer.register({ requestDelegate:{ onReject:resp=>{
                    const code=resp&&resp.message?resp.message.statusCode:'';
                    console.error('[Webphone] REGISTER rejeitado:',code);
                    window.nvWebphoneReady=false; window.nvRegRejectCode=code;
                }}});
            }).catch(e=>{ window.nvWebphoneReady=false; console.error('[Webphone] start falhou:',e); });
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
        const b=$('nv-call-mute');
        b.classList.toggle('muted',isMuted);
        b.innerHTML= isMuted?'<i class="bi bi-mic-mute-fill"></i>':'<i class="bi bi-mic-fill"></i>';
    };

    function resetControls(){ show('nv-call-answer',false); show('nv-call-mute',false); isMuted=false; stopTimer(); }

    function wireSession(session, peerLabel){
        currentSession=session; setPeer(peerLabel||'');
        session.stateChange.addListener(state=>{
            if(state===SIP.SessionState.Establishing){ showDots(true); setStatusText('Chamando...'); reportEvent('ringing'); }
            else if(state===SIP.SessionState.Established){
                showDots(false); setStatusText('Em chamada'); show('nv-call-mute',true); show('nv-call-answer',false);
                attachAudio(session); startTimer(); reportEvent('answered');
            }
            else if(state===SIP.SessionState.Terminated){
                const dur=elapsed(); showDots(false); setStatusText('Chamada encerrada'); resetControls();
                reportEvent('ended',{duration:dur});
                currentSession=null; currentRecordId=null; answeredAt=null;
                setTimeout(closeModal, 900); // fecha o modal ao encerrar/cancelar
            }
        });
    }

    function handleIncoming(invitation){
        openModal();
        const from=invitation.remoteIdentity&&invitation.remoteIdentity.uri?invitation.remoteIdentity.uri.user:'';
        showDots(true); setStatusText('Chamada recebida'); setPeer(from);
        show('nv-call-answer',true);
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
        show('nv-call-answer',false);
    };
    window.nvHangup=function(){
        const s=currentSession;
        if(!s){
            // Sem sessão ativa (ex.: ramal indisponível): encerra o registro pendente e fecha.
            if(currentRecordId){ reportEvent('ended',{duration:0,cause:'cancelled'}); currentRecordId=null; }
            closeModal();
            return;
        }
        try{
            if(s.state===SIP.SessionState.Established) s.bye();
            else if(s instanceof SIP.Inviter) s.cancel();
            else s.reject();
        }catch(e){}
    };

    // Origina chamada de saída: abre o modal com loading "Chamando..." e controles.
    window.nvCall=function(numero, recordId){
        if(!ua||!sipConfig){ alert('Webphone não inicializado.'); return false; }
        const target=SIP.UserAgent.makeURI('sip:'+numero+'@'+sipConfig.domain);
        if(!target){ alert('Número inválido.'); return false; }

        currentRecordId=recordId||null;
        openModal();
        setPeer(numero); showDots(true); setStatusText('Chamando...');
        show('nv-call-answer',false); show('nv-call-mute',false); stopTimer();

        if(!window.nvWebphoneReady){
            // Ramal não registrado: informa no próprio modal, encerra o registro pendente e permite fechar
            showDots(false); setStatusText('Ramal indisponível');
            const w=$('nv-call-reg-warn');
            w.style.display=''; 
            w.textContent = (window.nvRegRejectCode===401||window.nvRegRejectCode===403)
                ? 'Senha SIP incorreta. Verifique em Configurações.'
                : 'O ramal não está registrado. Verifique a Senha SIP em Configurações.';
            reportEvent('ended',{duration:0,cause:'ramal_indisponivel'});
            currentRecordId=null;
            return false;
        }

        const inviter=new SIP.Inviter(ua,target,{sessionDescriptionHandlerOptions:{constraints:{audio:true,video:false}}});
        wireSession(inviter, numero);
        inviter.invite().catch(e=>{ showDots(false); setStatusText('Falha ao chamar'); console.warn(e); reportEvent('ended',{duration:0,cause:'invite_failed'}); resetControls(); setTimeout(closeModal,1500); });
        return true;
    };

    if(document.readyState==='loading') document.addEventListener('DOMContentLoaded', initWebphone);
    else initWebphone();
})();
</script>
