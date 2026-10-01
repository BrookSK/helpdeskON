<?php
/**
 * View pública: documentação da API v1 (/api/documentos).
 *
 * Reflete EXCLUSIVAMENTE o que está implementado hoje:
 *  - Autenticação por header X-Api-Key (ApiController::requireApiKey).
 *  - POST /api/v1/tickets (ApiController::createTicket / normalizePayload).
 *  - Callback de mudança de status (ApiCallbackService).
 *
 * Página standalone (sem login) com navegação própria, mas com estética
 * "irmã" do painel do Helpdesk: mesmas variáveis de cor (--primary #00BFA6,
 * sidebar #1a1a2e), mesma fonte (Inter), mesmos cards (radius 12 + sombra
 * suave) e o mesmo tratamento de nav-link da sidebar interna.
 *
 * Variáveis esperadas (fornecidas por ApiController::documentos):
 *   $appName, $faviconUrl, $logoUrl, $base, $apiBase, $keyPrefix
 */
$apiBase    = isset($apiBase) ? $apiBase : '';
$appName    = isset($appName) ? $appName : 'ON Solutions Helpdesk';
$keyPrefix  = isset($keyPrefix) ? $keyPrefix : 'hk_live_';
$logoUrl    = isset($logoUrl) ? $logoUrl : '';
$base       = isset($base) ? $base : '';
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Mesmas variáveis do painel (layouts/header.php) */
        :root {
            --primary: #00BFA6;
            --primary-dark: #00997D;
            --primary-50: #E0F7F4;
            --sidebar-bg: #1a1a2e;
            --sidebar-width: 260px;
        }
        * { box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background-color: #f5f7fa; color: #333; margin: 0; min-height: 100vh; }
        a { color: var(--primary); text-decoration: none; }
        a:hover { text-decoration: underline; }
        code, pre { font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace; }
        .layout { display: flex; min-height: 100vh; }

        /* ===== Sidebar de navegação (mesma identidade da sidebar interna) ===== */
        .doc-nav {
            width: var(--sidebar-width);
            background: var(--sidebar-bg);
            position: sticky; top: 0;
            height: 100vh; overflow-y: auto;
            flex-shrink: 0;
            display: flex; flex-direction: column;
            padding: 8px 0;
        }
        .doc-nav .doc-nav-header {
            padding: 18px 15px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            margin-bottom: 8px;
        }
        .doc-nav .doc-nav-header img { max-height: 38px; max-width: 180px; }
        .doc-nav .logo-text { color: var(--primary); font-weight: 700; font-size: 1.3rem; }
        .doc-nav .brand-sub { color: var(--primary); font-size: 0.72rem; font-weight: 500; letter-spacing: 0.5px; margin-top: 3px; }
        .doc-nav h6 {
            color: rgba(255,255,255,0.35);
            text-transform: uppercase; font-size: 0.65rem; letter-spacing: 0.5px;
            margin: 18px 0 4px; padding: 0 24px;
        }
        .doc-nav a {
            display: flex; align-items: center; gap: 10px;
            color: rgba(255,255,255,0.7);
            padding: 10px 18px; margin: 2px 10px;
            border-radius: 8px; font-size: 0.88rem;
            transition: all 0.2s;
        }
        .doc-nav a i { width: 20px; font-size: 1rem; }
        .doc-nav a:hover, .doc-nav a.active {
            background: rgba(0, 191, 166, 0.15);
            color: var(--primary);
            text-decoration: none;
        }

        /* ===== Conteúdo ===== */
        .doc-main { flex: 1; padding: 25px; min-height: 100vh; }
        .doc-content { max-width: 920px; margin: 0 auto; }

        /* top-bar branca igual às telas internas */
        .doc-topbar {
            background: #fff; border-radius: 12px;
            padding: 18px 22px; margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .doc-topbar h1 { font-size: 1.3rem; font-weight: 700; color: var(--sidebar-bg); margin: 0; }
        .doc-topbar .lead-sub { color: #777; font-size: 0.9rem; margin: 4px 0 0; }

        /* Seções em cards, como o painel */
        .doc-card {
            background: #fff; border: none; border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            padding: 24px 26px; margin-bottom: 20px;
        }
        .doc-card h2 { font-size: 1.15rem; font-weight: 700; color: var(--sidebar-bg); margin: 0 0 14px; display: flex; align-items: center; gap: 8px; }
        .doc-card h2 i { color: var(--primary); }
        .doc-card h3 { font-size: 1rem; font-weight: 600; color: var(--sidebar-bg); margin-top: 24px; }
        .doc-card p, .doc-card li { font-size: 0.9rem; line-height: 1.7; color: #4a4a5a; }

        .pill { display: inline-block; font-size: 0.72rem; font-weight: 700; padding: 4px 10px; border-radius: 20px; letter-spacing: 0.5px; }
        .pill-post { background: #e8f5e9; color: #2e7d32; }
        .pill-cb   { background: #e3f2fd; color: #1565c0; }
        .endpoint {
            background: #f8f9fa; border: 1px solid #eef0f4; border-radius: 8px;
            padding: 12px 16px; font-size: 0.9rem;
            display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin: 10px 0 4px;
        }
        .endpoint .url { font-family: Consolas, monospace; color: var(--sidebar-bg); word-break: break-all; }

        pre { background: var(--sidebar-bg); color: #e6e6f0; border-radius: 10px; padding: 16px 18px; overflow-x: auto; font-size: 0.82rem; line-height: 1.6; }
        pre .cmt { color: #7f8cb3; }
        p code, li code, td code { background: var(--primary-50); color: var(--primary-dark); padding: 1px 6px; border-radius: 4px; font-size: 0.84rem; }

        table { width: 100%; border-collapse: collapse; margin: 14px 0; font-size: 0.86rem; }
        th, td { text-align: left; padding: 10px 12px; border-bottom: 1px solid #eef0f4; vertical-align: top; }
        th { font-weight: 600; color: #666; font-size: 0.76rem; text-transform: uppercase; letter-spacing: 0.4px; white-space: nowrap; }
        tr:last-child td { border-bottom: none; }
        .req { color: #c62828; font-weight: 600; font-size: 0.74rem; }
        .opt { color: #7a7a8a; font-size: 0.74rem; }

        /* badges HTTP com as mesmas cores de status do painel */
        .http { font-size: 0.72rem; font-weight: 600; padding: 3px 9px; border-radius: 20px; white-space: nowrap; }
        .http-2xx { background: #e8f5e9; color: #2e7d32; }
        .http-4xx { background: #fff3e0; color: #e65100; }
        .http-5xx { background: #fbe9e7; color: #d84315; }

        .note { background: #fff8e6; border: 1px solid #ffe8a3; border-radius: 8px; padding: 12px 16px; font-size: 0.86rem; color: #7a5c00; margin: 16px 0; }
        .note.info { background: #e0f7fa; border-color: #b2ebf2; color: #00697a; }

        .status-table td:first-child { white-space: nowrap; }
        .footer-back { font-size: 0.88rem; }
        .footer-back a { font-weight: 500; }

        @media (max-width: 991.98px) {
            .doc-nav { display: none; }
            .doc-main { padding: 15px; }
            .doc-card { padding: 18px; }
        }
    </style>
</head>
<body>
<div class="layout">
    <!-- NAV (identidade da sidebar interna) -->
    <nav class="doc-nav">
        <div class="doc-nav-header">
            <?php if (!empty($logoUrl)): ?>
            <img src="<?= htmlspecialchars($base . $logoUrl) ?>" alt="Logo">
            <?php else: ?>
            <span class="logo-text">ON</span><span class="text-white fw-light"> Solutions</span>
            <?php endif; ?>
            <div class="brand-sub">API &middot; v1</div>
        </div>
        <h6>Começando</h6>
        <a href="#introducao"><i class="bi bi-info-circle"></i> Introdução</a>
        <a href="#como-utilizar"><i class="bi bi-rocket-takeoff"></i> Como utilizar</a>
        <a href="#autenticacao"><i class="bi bi-key"></i> Autenticação</a>
        <a href="#erros"><i class="bi bi-exclamation-octagon"></i> Formato de erros</a>
        <h6>Endpoints</h6>
        <a href="#criar-chamado"><i class="bi bi-plus-square"></i> Criar chamado</a>
        <a href="#idempotencia"><i class="bi bi-arrow-repeat"></i> Idempotência</a>
        <h6>Retorno</h6>
        <a href="#callback"><i class="bi bi-arrow-left-right"></i> Callback de status</a>
    </nav>

    <!-- CONTEÚDO -->
    <div class="doc-main">
        <div class="doc-content">
            <div class="doc-topbar">
                <h1><i class="bi bi-file-earmark-code text-primary"></i> Documentação da API</h1>
                <p class="lead-sub">Referência da API v1 do <?= htmlspecialchars($appName) ?> para integração externa. Descreve apenas os recursos atualmente implementados.</p>
            </div>

            <div class="doc-card">
                <h2 id="introducao"><i class="bi bi-info-circle"></i> Introdução</h2>
                <p>A API v1 permite que um sistema externo <strong>abra chamados</strong> no helpdesk em nome de uma empresa e, opcionalmente, <strong>receba de volta</strong> as mudanças de status desses chamados por meio de um callback.</p>
                <ul>
                    <li>Todas as requisições e respostas usam <code>application/json</code>.</li>
                    <li>A codificação é UTF-8.</li>
                    <li>A URL base da sua instalação é: <code><?= htmlspecialchars($apiBase) ?></code></li>
                </ul>
            </div>

            <div class="doc-card">
                <h2 id="como-utilizar"><i class="bi bi-rocket-takeoff"></i> Como utilizar a API</h2>
                <p>Para consumir a API você precisa de uma <strong>chave de API</strong> (uma por empresa). A chave é gerada e exibida na tela de <strong>Configurações</strong> do sistema (acesso restrito a administradores) e tem o prefixo <code><?= htmlspecialchars($keyPrefix) ?></code>.</p>
                <ol>
                    <li>Obtenha a chave da sua empresa na tela de Configurações do helpdesk.</li>
                    <li>Envie a chave em toda requisição no header <code>X-Api-Key</code>.</li>
                    <li>Faça um <code>POST</code> para <code>/api/v1/tickets</code> com o corpo JSON do chamado.</li>
                </ol>
                <div class="note info">A chave identifica a empresa e o usuário de integração dela. Os chamados criados ficam vinculados a essa empresa, preservando os fluxos internos (planejamento, notificações e filtros por empresa).</div>
            </div>

            <div class="doc-card">
                <h2 id="autenticacao"><i class="bi bi-key"></i> Autenticação</h2>
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
            </div>

            <div class="doc-card">
                <h2 id="erros"><i class="bi bi-exclamation-octagon"></i> Formato de erros</h2>
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
            </div>

            <div class="doc-card">
                <h2 id="criar-chamado"><i class="bi bi-plus-square"></i> Criar chamado</h2>
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
                        <tr><td><span class="http http-2xx">201</span></td><td>—</td><td>Chamado criado com sucesso.</td></tr>
                        <tr><td><span class="http http-2xx">200</span></td><td>—</td><td>Chamado já existia (idempotência via <code>external_ref</code>).</td></tr>
                        <tr><td><span class="http http-4xx">400</span></td><td><code>bad_request</code></td><td>Corpo não é um JSON válido.</td></tr>
                        <tr><td><span class="http http-4xx">401</span></td><td><code>missing_api_key</code> / <code>invalid_api_key</code></td><td>Chave ausente ou inválida.</td></tr>
                        <tr><td><span class="http http-4xx">405</span></td><td><code>method_not_allowed</code></td><td>Método diferente de POST.</td></tr>
                        <tr><td><span class="http http-4xx">422</span></td><td><code>validation_error</code> / <code>invalid_value</code></td><td>Campos obrigatórios ausentes ou prioridade inválida.</td></tr>
                        <tr><td><span class="http http-5xx">500</span></td><td><code>internal_error</code></td><td>Erro interno ao criar o chamado.</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="doc-card">
                <h2 id="callback"><i class="bi bi-arrow-left-right"></i> Callback de status (retorno)</h2>
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
            </div>

            <div class="footer-back">
                <a href="javascript:history.back()"><i class="bi bi-arrow-left"></i> Voltar</a>
            </div>
        </div>
    </div>
</div>
</body>
</html>
