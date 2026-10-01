<?php
/**
 * View pública: documentação da API v1 (/api/documentos).
 *
 * Reflete EXCLUSIVAMENTE o que está implementado hoje:
 *  - Autenticação por header X-Api-Key (ApiController::requireApiKey).
 *  - POST /api/v1/tickets (ApiController::createTicket / normalizePayload).
 *  - Callback de mudança de status (ApiCallbackService).
 *
 * Variáveis esperadas (fornecidas por ApiController::documentos):
 *   $appName, $faviconUrl, $logoUrl, $base, $apiBase, $keyPrefix
 */
$apiBase   = isset($apiBase) ? $apiBase : '';
$appName   = isset($appName) ? $appName : 'ON Solutions Helpdesk';
$keyPrefix = isset($keyPrefix) ? $keyPrefix : 'hk_live_';
$ticketsUrl = $apiBase . '/api/v1/tickets';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documentação da API - <?= htmlspecialchars($appName) ?></title>
    <?php if (!empty($faviconUrl)): ?>
    <link rel="icon" href="<?= htmlspecialchars($base . $faviconUrl) ?>">
    <?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --accent: #00BFA6; --dark: #1a1a2e; }
        * { box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8f9fa; color: #333; margin: 0; }
        a { color: var(--accent); text-decoration: none; }
        a:hover { text-decoration: underline; }
        code, pre { font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace; }
        .layout { display: flex; min-height: 100vh; }

        /* Sidebar de navegação */
        .doc-nav { width: 260px; background: var(--dark); color: #cfd3e2; padding: 24px 18px; position: sticky; top: 0; height: 100vh; overflow-y: auto; flex-shrink: 0; }
        .doc-nav .brand { color: #fff; font-weight: 700; font-size: 1.05rem; margin-bottom: 4px; }
        .doc-nav .brand small { display: block; color: var(--accent); font-weight: 500; font-size: 0.72rem; letter-spacing: 0.5px; }
        .doc-nav h6 { color: #6c7293; text-transform: uppercase; font-size: 0.68rem; letter-spacing: 1px; margin: 22px 0 8px; }
        .doc-nav a { display: block; color: #cfd3e2; padding: 6px 10px; border-radius: 6px; font-size: 0.86rem; }
        .doc-nav a:hover { background: rgba(255,255,255,0.06); color: #fff; text-decoration: none; }

        /* Conteúdo */
        .doc-content { flex: 1; max-width: 900px; margin: 0 auto; padding: 40px 36px 80px; }
        .doc-content h1 { font-size: 1.9rem; font-weight: 700; color: var(--dark); }
        .doc-content h2 { font-size: 1.3rem; font-weight: 700; color: var(--dark); margin-top: 48px; padding-top: 10px; border-top: 1px solid #e8e8ef; }
        .doc-content h3 { font-size: 1.02rem; font-weight: 600; color: var(--dark); margin-top: 28px; }
        .doc-content p, .doc-content li { font-size: 0.92rem; line-height: 1.7; color: #4a4a5a; }
        .lead-sub { color: #777; font-size: 1rem; }

        .pill { display: inline-block; font-size: 0.72rem; font-weight: 700; padding: 3px 9px; border-radius: 5px; letter-spacing: 0.5px; }
        .pill-post { background: #e3f7f2; color: #0a8f78; }
        .pill-cb   { background: #eef0ff; color: #4a4ae0; }
        .endpoint { background: #fff; border: 1px solid #e8e8ef; border-radius: 8px; padding: 12px 16px; font-size: 0.9rem; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .endpoint .url { font-family: Consolas, monospace; color: var(--dark); word-break: break-all; }

        pre { background: #1a1a2e; color: #e6e6f0; border-radius: 10px; padding: 16px 18px; overflow-x: auto; font-size: 0.82rem; line-height: 1.6; }
        pre .cmt { color: #7f8cb3; }
        p code, li code, td code { background: #eef0f4; color: #c7254e; padding: 1px 6px; border-radius: 4px; font-size: 0.84rem; }

        table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; margin: 14px 0; font-size: 0.86rem; }
        th, td { text-align: left; padding: 10px 12px; border-bottom: 1px solid #eef0f4; vertical-align: top; }
        th { background: #f3f4f8; font-weight: 600; color: var(--dark); font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.4px; }
        tr:last-child td { border-bottom: none; }
        .req { color: #c0392b; font-weight: 600; font-size: 0.74rem; }
        .opt { color: #7a7a8a; font-size: 0.74rem; }

        .note { background: #fff8e6; border: 1px solid #ffe8a3; border-radius: 8px; padding: 12px 16px; font-size: 0.86rem; color: #7a5c00; margin: 16px 0; }
        .note.info { background: #eaf6ff; border-color: #b9e0ff; color: #0a5a8f; }

        .status-table td:first-child { white-space: nowrap; font-weight: 600; }
        .footer-back { margin-top: 50px; font-size: 0.86rem; }
        @media (max-width: 820px) { .doc-nav { display: none; } .doc-content { padding: 28px 18px 60px; } }
    </style>
</head>
<body>
<div class="layout">
    <!-- NAV -->
    <nav class="doc-nav">
        <div class="brand">
            <?= htmlspecialchars($appName) ?>
            <small>API &middot; v1</small>
        </div>
        <h6>Começando</h6>
        <a href="#introducao">Introdução</a>
        <a href="#como-utilizar">Como utilizar a API</a>
        <a href="#autenticacao">Autenticação</a>
        <a href="#erros">Formato de erros</a>
        <h6>Endpoints</h6>
        <a href="#criar-chamado">Criar chamado</a>
        <a href="#idempotencia">Idempotência</a>
        <h6>Retorno (callback)</h6>
        <a href="#callback">Callback de status</a>
    </nav>

    <!-- CONTEÚDO -->
    <main class="doc-content">
        <h1>Documentação da API</h1>
        <p class="lead-sub">Referência da API v1 do <?= htmlspecialchars($appName) ?> para integração externa. Esta documentação descreve apenas os recursos atualmente implementados.</p>

        <h2 id="introducao">Introdução</h2>
        <p>A API v1 permite que um sistema externo <strong>abra chamados</strong> no helpdesk em nome de uma empresa e, opcionalmente, <strong>receba de volta</strong> as mudanças de status desses chamados por meio de um callback.</p>
        <ul>
            <li>Todas as requisições e respostas usam <code>application/json</code>.</li>
            <li>A codificação é UTF-8.</li>
            <li>A URL base da sua instalação é: <code><?= htmlspecialchars($apiBase) ?></code></li>
        </ul>

        <h2 id="como-utilizar">Como utilizar a API</h2>
        <p>Para consumir a API você precisa de uma <strong>chave de API</strong> (uma por empresa). A chave é gerada e exibida na tela de <strong>Configurações</strong> do sistema (acesso restrito a administradores) e tem o prefixo <code><?= htmlspecialchars($keyPrefix) ?></code>.</p>
        <ol>
            <li>Obtenha a chave da sua empresa na tela de Configurações do helpdesk.</li>
            <li>Envie a chave em toda requisição no header <code>X-Api-Key</code>.</li>
            <li>Faça um <code>POST</code> para <code>/api/v1/tickets</code> com o corpo JSON do chamado.</li>
        </ol>
        <div class="note info">A chave identifica a empresa e o usuário de integração dela. Os chamados criados ficam vinculados a essa empresa, preservando os fluxos internos (planejamento, notificações e filtros por empresa).</div>

        <h2 id="autenticacao">Autenticação</h2>
        <p>A autenticação é feita por <strong>chave de API no header</strong> <code>X-Api-Key</code>. Não há OAuth, não há Bearer token e não é usada sessão/cookie — é a autenticação de sistema para sistema.</p>
        <pre>X-Api-Key: <?= htmlspecialchars($keyPrefix) ?>sua_chave_aqui</pre>
        <div class="note">Nunca exponha a chave em código client-side, repositórios públicos ou logs. Em caso de vazamento, a troca da chave é feita pela equipe diretamente no sistema.</div>
        <p>Falhas de autenticação retornam <code>401</code>:</p>
        <table>
            <thead><tr><th>Código</th><th>Quando ocorre</th></tr></thead>
            <tbody>
                <tr><td><code>missing_api_key</code></td><td>Header <code>X-Api-Key</code> ausente ou vazio.</td></tr>
                <tr><td><code>invalid_api_key</code></td><td>A chave não corresponde a nenhuma chave registrada.</td></tr>
            </tbody>
        </table>

        <h2 id="erros">Formato de erros</h2>
        <p>Erros seguem sempre o mesmo formato. O campo <code>error.code</code> é estável e pode ser usado programaticamente; <code>error.message</code> é descritivo.</p>
        <pre>{
  <span class="cmt">// erro de validação (HTTP 422) — inclui os campos afetados</span>
  "success": false,
  "error": {
    "code": "validation_error",
    "message": "Título e descrição são obrigatórios.",
    "fields": ["title", "description"]
  }
}</pre>

        <h2 id="criar-chamado">Criar chamado</h2>
        <div class="endpoint">
            <span class="pill pill-post">POST</span>
            <span class="url"><?= htmlspecialchars($ticketsUrl) ?></span>
        </div>
        <p>Cria um novo chamado em nome da empresa dona da chave. Requer o header <code>X-Api-Key</code> e um corpo JSON.</p>

        <h3>Parâmetros do corpo (JSON)</h3>
        <table>
            <thead><tr><th>Campo</th><th>Tipo</th><th>Obrigatório</th><th>Descrição</th></tr></thead>
            <tbody>
                <tr><td><code>title</code></td><td>string</td><td><span class="req">Obrigatório</span></td><td>Título do chamado. Limite de 255 caracteres (truncado se exceder).</td></tr>
                <tr><td><code>description</code></td><td>string</td><td><span class="req">Obrigatório</span></td><td>Descrição detalhada do chamado.</td></tr>
                <tr><td><code>priority</code></td><td>string</td><td><span class="opt">Opcional</span></td><td>Um de <code>low</code>, <code>medium</code>, <code>high</code>, <code>urgent</code>. Padrão: <code>medium</code>.</td></tr>
                <tr><td><code>category</code></td><td>string</td><td><span class="opt">Opcional</span></td><td>Categoria livre. Limite de 100 caracteres (truncado se exceder).</td></tr>
                <tr><td><code>requester_name</code></td><td>string</td><td><span class="opt">Opcional</span></td><td>Nome do solicitante. É anexado ao topo da descrição do chamado.</td></tr>
                <tr><td><code>requester_company</code></td><td>string</td><td><span class="opt">Opcional</span></td><td>Empresa informada pelo solicitante. É anexada ao topo da descrição.</td></tr>
                <tr><td><code>external_ref</code></td><td>string</td><td><span class="opt">Opcional</span></td><td>Sua referência externa do chamado. Habilita idempotência e o callback de status. Limite de 191 caracteres.</td></tr>
            </tbody>
        </table>

        <h3>Exemplo de requisição</h3>
        <pre><span class="cmt"># cURL</span>
curl -X POST "<?= htmlspecialchars($ticketsUrl) ?>" \
  -H "X-Api-Key: <?= htmlspecialchars($keyPrefix) ?>sua_chave_aqui" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Erro ao emitir boleto",
    "description": "Cliente relata falha ao gerar boleto na área logada.",
    "priority": "high",
    "category": "financeiro",
    "requester_name": "Maria Souza",
    "requester_company": "Loja Exemplo LTDA",
    "external_ref": "OS-2026-00123"
  }'</pre>

        <h3>Resposta — <code>201 Created</code></h3>
        <pre>{
  "success": true,
  "data": {
    "id": 4821,
    "client_ticket_number": 12,
    "title": "Erro ao emitir boleto",
    "status": "open",
    "priority": "high",
    "category": "financeiro",
    "company_id": 7,
    "external_ref": "OS-2026-00123",
    "created_at": "2026-09-30 14:05:11"
  }
}</pre>

        <h3 id="idempotencia">Idempotência</h3>
        <p>Quando você envia <code>external_ref</code> e já existe um chamado dessa empresa com a mesma referência, a API <strong>não cria um novo chamado</strong>: retorna o existente com <code>HTTP 200</code> e <code>idempotent: true</code>. Reenviar a mesma criação é seguro.</p>
        <pre>{
  "success": true,
  "idempotent": true,
  "data": {
    "id": 4821,
    "client_ticket_number": 12,
    "status": "open"
  }
}</pre>

        <h3>Códigos de status possíveis</h3>
        <table class="status-table">
            <thead><tr><th>HTTP</th><th>code</th><th>Significado</th></tr></thead>
            <tbody>
                <tr><td>201</td><td>—</td><td>Chamado criado com sucesso.</td></tr>
                <tr><td>200</td><td>—</td><td>Chamado já existia (idempotência via <code>external_ref</code>).</td></tr>
                <tr><td>400</td><td><code>bad_request</code></td><td>Corpo não é um JSON válido.</td></tr>
                <tr><td>401</td><td><code>missing_api_key</code> / <code>invalid_api_key</code></td><td>Chave ausente ou inválida.</td></tr>
                <tr><td>405</td><td><code>method_not_allowed</code></td><td>Método diferente de POST.</td></tr>
                <tr><td>422</td><td><code>validation_error</code> / <code>invalid_value</code></td><td>Campos obrigatórios ausentes ou prioridade inválida.</td></tr>
                <tr><td>500</td><td><code>internal_error</code></td><td>Erro interno ao criar o chamado.</td></tr>
            </tbody>
        </table>

        <h2 id="callback">Callback de status (retorno)</h2>
        <p><span class="pill pill-cb">OUTBOUND</span> &nbsp;Opcionalmente, o helpdesk pode <strong>avisar o seu sistema</strong> quando o status de um chamado muda. É uma entrega de mão dupla: você abre o chamado pela API e recebe de volta as atualizações.</p>
        <p>Condições para o callback disparar:</p>
        <ul>
            <li>A empresa precisa ter uma <strong>URL de callback cadastrada e habilitada</strong> (configurado no sistema).</li>
            <li>O chamado precisa ter sido criado com <code>external_ref</code> — só os chamados que você mesmo enviou geram retorno.</li>
            <li>Só o evento de <strong>mudança de status</strong> gera callback. A criação é confirmada pela própria resposta <code>201</code>.</li>
        </ul>
        <p>O helpdesk faz um <code>POST</code> JSON para a sua URL com o corpo abaixo (evento <code>ticket.status_changed</code>):</p>
        <pre>{
  "event": "ticket.status_changed",
  "id": 4821,
  "client_ticket_number": 12,
  "external_ref": "OS-2026-00123",
  "previous_status": "open",
  "status": "in_progress",
  "changed_at": "2026-09-30 15:20:03"
}</pre>
        <div class="note info">A entrega é assíncrona, com reenvio automático em caso de falha. Identifique o chamado no seu sistema pelo <code>external_ref</code> que você enviou na criação.</div>

        <div class="footer-back">
            <a href="javascript:history.back()">← Voltar</a>
        </div>
    </main>
</div>
</body>
</html>
