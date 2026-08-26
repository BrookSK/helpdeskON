<?php
/**
 * Dados compartilhados dos filtros de Captação de Leads (Apollo).
 *  - $apolloTech: categoria => [uid => Label]  (tecnologias suportadas pela Apollo,
 *    uids no formato usado pela API — espaços/pontos viram "_").
 *
 * Estados e cidades vêm da API do IBGE (via crm/ibgeStates e crm/ibgeCities),
 * carregados dinamicamente pelo JavaScript.
 *
 * Observação: a Apollo suporta 1.500+ tecnologias; aqui listamos as mais usadas em
 * prospecção. Campos de texto livre continuam disponíveis para uids específicos.
 */

$apolloTech = [
    'CRM & Vendas' => [
        'salesforce' => 'Salesforce', 'hubspot' => 'HubSpot', 'pipedrive' => 'Pipedrive',
        'zoho_crm' => 'Zoho CRM', 'rd_station' => 'RD Station', 'microsoft_dynamics' => 'MS Dynamics',
        'outreach' => 'Outreach', 'salesloft' => 'Salesloft',
    ],
    'Marketing' => [
        'google_analytics' => 'Google Analytics', 'google_tag_manager' => 'Google Tag Manager',
        'mailchimp' => 'Mailchimp', 'marketo' => 'Marketo', 'hotjar' => 'Hotjar',
        'facebook_pixel' => 'Facebook Pixel', 'active_campaign' => 'ActiveCampaign',
        'intercom' => 'Intercom', 'drift' => 'Drift',
    ],
    'E-commerce' => [
        'shopify' => 'Shopify', 'woocommerce' => 'WooCommerce', 'magento' => 'Magento',
        'vtex' => 'VTEX', 'wix' => 'Wix', 'squarespace' => 'Squarespace',
    ],
    'CMS & Web' => [
        'wordpress_org' => 'WordPress', 'drupal' => 'Drupal', 'webflow' => 'Webflow',
        'cloudflare' => 'Cloudflare', 'nginx' => 'Nginx', 'apache' => 'Apache',
    ],
    'Cloud & Infra' => [
        'amazon_aws' => 'Amazon AWS', 'microsoft_azure' => 'Microsoft Azure',
        'google_cloud' => 'Google Cloud', 'digitalocean' => 'DigitalOcean',
        'heroku' => 'Heroku', 'docker' => 'Docker', 'kubernetes' => 'Kubernetes',
    ],
    'Produtividade' => [
        'google_workspace' => 'Google Workspace', 'microsoft_office_365' => 'Office 365',
        'slack' => 'Slack', 'zoom' => 'Zoom', 'notion' => 'Notion',
        'jira' => 'Jira', 'asana' => 'Asana', 'trello' => 'Trello',
    ],
    'Pagamentos' => [
        'stripe' => 'Stripe', 'paypal' => 'PayPal', 'braintree' => 'Braintree',
        'pagseguro' => 'PagSeguro', 'mercado_pago' => 'Mercado Pago',
    ],
    'Suporte' => [
        'zendesk' => 'Zendesk', 'freshdesk' => 'Freshdesk', 'servicenow' => 'ServiceNow',
        'twilio' => 'Twilio', 'freshworks' => 'Freshworks',
    ],
];

// Estados e cidades (Brasil) — lidos do arquivo local e injetados direto no JS.
// Evita qualquer dependência de rede/rota em runtime.
$brLocalitiesFile = APP_PATH . '/data/br_states_cities.json';
$brLocalitiesJson = is_file($brLocalitiesFile) ? file_get_contents($brLocalitiesFile) : '{}';
?>
<script>
// window.BR_LOC = { "SP": { "name": "São Paulo", "cities": [...] }, ... }
window.BR_LOC = <?= $brLocalitiesJson ?: '{}' ?>;
</script>
<?php

