<?php $pageTitle = 'Documentação da API de demandas - ON Solutions Helpdesk'; $currentPage = 'settings'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<?php
    // URL base pública já resolvida, para exibir o endpoint real pronto para
    // copiar — sem placeholders. Mesma lógica usada na tela de Configurações.
    $apiBase = rtrim($settings['app_public_url'] ?? '', '/');
    if ($apiBase === '') { $apiBase = rtrim(baseUrl(''), '/'); }
    $ticketsEndpoint = $apiBase . '/api/v1/tickets';
    $callbackCron = $apiBase . '/cron-api-callback.php';
?>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0"><i class="bi bi-journal-code"></i> Documentação da API de demandas</h5>
            <small class="text-muted">Guia de integração para sistemas externos (envio de demandas e retorno de status)</small>
        </div>
        <div>
            <a href="<?= baseUrl('settings') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Voltar para Configurações</a>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body" style="font-size:0.9rem;">
            <p class="text-muted">
                Informações que o sistema externo (ex.: Punta Cana) precisa para integrar com o LRV.
                Os valores abaixo já estão resolvidos para <strong>este ambiente</strong> — copie e repasse ao integrador.
                A chave <code>X-Api-Key</code> de cada empresa é gerada em
                <a href="<?= baseUrl('settings') ?>">Configurações → Integrações</a> e deve ser repassada por canal seguro.
            </p>

            <!-- Fluxo -->
            <div class="mb-4">
                <h6 class="fw-bold"><i class="bi bi-diagram-3"></i> Fluxo</h6>
                <ol class="mb-0 ps-3">
                    <li><strong>Envio (sistema externo → LRV):</strong> o sistema externo faz <code>POST</code> no endpoint de criação com a chave da empresa (<code>X-Api-Key</code>) e os dados da demanda.</li>
                    <li><strong>Processamento (LRV):</strong> o LRV cria a demanda, gera o card no Planejamento e dispara as notificações internas.</li>
                    <li><strong>Retorno de status (LRV → sistema externo):</strong> sempre que o <strong>status</strong> muda em uma demanda criada via API (com <code>external_ref</code>), o LRV faz um <code>POST</code> na <strong>URL de callback</strong> cadastrada para a empresa, identificando a demanda pelo <code>external_ref</code> enviado na criação.</li>
                </ol>
            </div>

            <!-- Endpoint -->
            <div class="mb-4">
                <h6 class="fw-bold"><i class="bi bi-box-arrow-in-right"></i> Endpoint de criação</h6>
                <div class="input-group input-group-sm mt-1" style="max-width:680px;">
                    <span class="input-group-text">POST</span>
                    <input type="text" class="form-control" readonly value="<?= escape($ticketsEndpoint) ?>">
                    <button type="button" class="btn btn-outline-secondary" title="Copiar"
                        onclick="(function(b){var i=b.previousElementSibling;i.select();document.execCommand('copy');})(this)"><i class="bi bi-clipboard"></i></button>
                </div>
                <div class="small text-muted mt-1">Cabeçalhos: <code>X-Api-Key: &lt;chave da empresa&gt;</code> e <code>Content-Type: application/json</code>.</div>
            </div>

            <!-- Payload de envio -->
            <div class="mb-4">
                <h6 class="fw-bold"><i class="bi bi-arrow-up-right-circle"></i> Corpo do envio (criação da demanda)</h6>
                <pre class="bg-light border rounded p-3 mt-1 mb-1" style="white-space:pre-wrap;">{
  "title": "Erro ao emitir nota fiscal",        // obrigatório
  "description": "Retorna erro 500 ao emitir.",  // obrigatório
  "priority": "high",                            // opcional: low | medium | high | urgent (padrão: medium)
  "category": "suporte",                         // opcional
  "requester_name": "Maria Souza",               // opcional
  "requester_company": "Punta Cana",             // opcional
  "external_ref": "PUNTACANA-2026-000123"        // recomendado: id do chamado no sistema externo (idempotência)
}</pre>
                <div class="small text-muted">Resposta <code>201</code>: <code>{ "success": true, "data": { "id", "client_ticket_number", "status": "open", "external_ref", ... } }</code>. Reenvio com o mesmo <code>external_ref</code> retorna <code>200</code> com <code>"idempotent": true</code> (não duplica).</div>
            </div>

            <!-- Payload de callback -->
            <div class="mb-4">
                <h6 class="fw-bold"><i class="bi bi-arrow-down-left-circle"></i> Corpo do callback (retorno de status, LRV → sistema externo)</h6>
                <pre class="bg-light border rounded p-3 mt-1 mb-1" style="white-space:pre-wrap;">POST &lt;URL de callback cadastrada para a empresa&gt;
Content-Type: application/json

{
  "event": "ticket.status_changed",
  "id": 4821,                              // id interno no LRV
  "client_ticket_number": 37,             // nº sequencial da demanda por empresa
  "external_ref": "PUNTACANA-2026-000123", // a MESMA referência enviada na criação
  "previous_status": "open",
  "status": "in_progress",
  "changed_at": "2026-09-25 14:20:11"
}</pre>
                <div class="small text-muted">O sistema externo deve responder <code>2xx</code>. Sem <code>2xx</code>, o LRV reenvia (até 3 tentativas). Só demandas criadas via API (com <code>external_ref</code>) geram callback.</div>
            </div>

            <!-- Status possíveis -->
            <div class="mb-4">
                <h6 class="fw-bold"><i class="bi bi-list-check"></i> Valores possíveis de <code>status</code></h6>
                <div class="small">
                    <code>open</code>, <code>in_progress</code>, <code>em_revisao_interna</code>,
                    <code>waiting_client</code>, <code>em_homologacao</code>, <code>aprovado_producao</code>,
                    <code>completed</code>, <code>denied</code>, <code>archived</code>
                </div>
            </div>

            <!-- Códigos de erro -->
            <div class="mb-4">
                <h6 class="fw-bold"><i class="bi bi-exclamation-octagon"></i> Códigos HTTP (criação)</h6>
                <div class="table-responsive">
                    <table class="table table-sm mb-0" style="max-width:640px;">
                        <thead><tr class="small text-muted"><th>Situação</th><th>HTTP</th><th><code>error.code</code></th></tr></thead>
                        <tbody class="small">
                            <tr><td>Chamado criado</td><td>201</td><td>—</td></tr>
                            <tr><td>Idempotente (já existia)</td><td>200</td><td>—</td></tr>
                            <tr><td>Método diferente de POST</td><td>405</td><td><code>method_not_allowed</code></td></tr>
                            <tr><td>Corpo JSON inválido</td><td>400</td><td><code>bad_request</code></td></tr>
                            <tr><td>Cabeçalho <code>X-Api-Key</code> ausente</td><td>401</td><td><code>missing_api_key</code></td></tr>
                            <tr><td>Chave inválida</td><td>401</td><td><code>invalid_api_key</code></td></tr>
                            <tr><td>Campos obrigatórios ausentes</td><td>422</td><td><code>validation_error</code></td></tr>
                            <tr><td>Valor inválido (ex.: <code>priority</code>)</td><td>422</td><td><code>invalid_value</code></td></tr>
                            <tr><td>Erro interno</td><td>500</td><td><code>internal_error</code></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Requisitos internos -->
            <div class="mb-0">
                <h6 class="fw-bold"><i class="bi bi-gear"></i> Requisitos internos (lado do LRV)</h6>
                <ul class="small mb-0 ps-3">
                    <li>Migrations aplicadas: <code>133_api_keys.sql</code>, <code>134_tickets_external_ref.sql</code> e <code>135_api_callback.sql</code>.</li>
                    <li>Agendar o processador da fila de callbacks (cron, a cada minuto):
                        <div class="input-group input-group-sm mt-1" style="max-width:680px;">
                            <input type="text" class="form-control" readonly value="<?= escape($callbackCron) ?>">
                            <button type="button" class="btn btn-outline-secondary" title="Copiar"
                                onclick="(function(b){var i=b.previousElementSibling;i.select();document.execCommand('copy');})(this)"><i class="bi bi-clipboard"></i></button>
                        </div>
                    </li>
                    <li class="mt-1">Guia completo também disponível em <code>docs/api-v1-chamados.md</code>.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
