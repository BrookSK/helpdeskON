<?php
// Widget oficial de telefonia da Nvoip embutido no CRM.
// A API Key (public token) vai na URL do loader, conforme o script de integração oficial da Nvoip.
$nvPublicToken = Config::get('nvoip_webphone_api_key');
?>
<!-- Início do script de integração do widget Nvoip -->
<script id="nvoip-init-widget"
    src="https://content.nvoip.com.br/widget/nvoip-widget-loader.js?public-token=<?= urlencode($nvPublicToken ?: '') ?>"
    async></script>
<!-- Fim do script de integração do widget Nvoip -->

<script>
(function(){
    const CRM_BASE = '<?= baseUrl("") ?>';

    // Registra a ligação no banco (histórico do CRM). Retorna Promise com o registro.
    window.nvRegisterCall = function(leadId){
        return fetch(CRM_BASE + 'crm/dialLead/' + leadId, { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'} })
            .then(r => r.json());
    };

    // Tenta acionar a discagem pelo widget oficial da Nvoip (nomes variam conforme a versão).
    function tryWidgetDial(numero){
        const candidates = [
            () => window.nvoipWidget && typeof window.nvoipWidget.call === 'function' && window.nvoipWidget.call(numero),
            () => window.NvoipWidget && typeof window.NvoipWidget.call === 'function' && window.NvoipWidget.call(numero),
            () => window.nvoip && typeof window.nvoip.call === 'function' && window.nvoip.call(numero),
            () => window.webphone && typeof window.webphone.call === 'function' && window.webphone.call(numero),
            () => typeof window.makeCall === 'function' && window.makeCall(numero),
        ];
        for (const fn of candidates) {
            try { const r = fn(); if (r !== false && r !== undefined) return true; } catch(e) {}
        }
        return false;
    }
    window.nvWidgetDial = tryWidgetDial;
})();
</script>
