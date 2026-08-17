<?php
// Webphone oficial da Nvoip embutido no CRM.
// O widget cuida do registro SIP, mídia WebRTC e áudio (funciona com a infra da Nvoip).
// O login no ramal é feito dentro do próprio widget.
?>
<!-- Widget oficial do Webphone Nvoip -->
<script id="web-phone-init" src="https://content.nvoip.com.br/webphone/v2.1/index.js?v=nn4387-cache-safe-1" style="z-index: 1700; position: relative;"></script>

<script>
(function(){
    const CRM_BASE = '<?= baseUrl("") ?>';

    // Registra a ligação no banco (histórico do CRM). Retorna Promise com o id do registro.
    window.nvRegisterCall = function(leadId){
        return fetch(CRM_BASE + 'crm/dialLead/' + leadId, { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'} })
            .then(r => r.json());
    };

    // Tenta acionar a discagem pelo widget oficial da Nvoip.
    // O widget pode expor funções globais diferentes conforme a versão; tentamos as mais comuns.
    function tryWidgetDial(numero){
        const candidates = [
            () => window.webphone && typeof window.webphone.call === 'function' && window.webphone.call(numero),
            () => window.Webphone && typeof window.Webphone.call === 'function' && window.Webphone.call(numero),
            () => window.nvoipWebphone && typeof window.nvoipWebphone.call === 'function' && window.nvoipWebphone.call(numero),
            () => typeof window.makeCall === 'function' && window.makeCall(numero),
            () => typeof window.wpDial === 'function' && window.wpDial(numero),
        ];
        for (const fn of candidates) {
            try { const r = fn(); if (r !== false && r !== undefined) return true; } catch(e) {}
        }
        return false;
    }
    window.nvWidgetDial = tryWidgetDial;
})();
</script>
