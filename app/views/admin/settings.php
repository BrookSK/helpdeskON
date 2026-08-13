<?php $pageTitle = 'Configurações - ON Solutions Helpdesk'; $currentPage = 'settings'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0">Configurações</h5>
            <small class="text-muted">Sistema, email, IA e webhooks</small>
        </div>
    </div>

    <?php if ($msg = flash('success')): ?>
        <div class="alert alert-success alert-dismissible fade show"><?= escape($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <form action="<?= baseUrl('settings/save') ?>" method="POST" enctype="multipart/form-data">
        <!-- Geral -->
        <div class="card mb-4">
            <div class="card-header bg-white"><h6 class="mb-0" style="font-size:0.9rem"><i class="bi bi-gear"></i> Geral</h6></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <label class="form-label fw-medium small">Nome do Sistema</label>
                        <input type="text" name="app_name" class="form-control form-control-sm" value="<?= escape($settings['app_name'] ?? '') ?>">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-medium small">Email do Sistema</label>
                        <input type="email" name="app_email" class="form-control form-control-sm" value="<?= escape($settings['app_email'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Logo e Favicon -->
        <div class="card mb-4">
            <div class="card-header bg-white"><h6 class="mb-0" style="font-size:0.9rem"><i class="bi bi-image"></i> Logo e Favicon</h6></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <label class="form-label fw-medium small">Logo do Sistema</label>
                        <?php if (!empty($settings['app_logo'])): ?>
                        <div class="mb-2">
                            <img src="<?= baseUrl($settings['app_logo']) ?>" alt="Logo" style="max-height:50px;background:#1a1a2e;padding:8px 12px;border-radius:8px;">
                        </div>
                        <?php endif; ?>
                        <input type="file" name="app_logo" class="form-control form-control-sm" accept="image/*">
                        <small class="text-muted">PNG ou SVG recomendado. Fundo transparente.</small>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-medium small">Favicon</label>
                        <?php if (!empty($settings['app_favicon'])): ?>
                        <div class="mb-2">
                            <img src="<?= baseUrl($settings['app_favicon']) ?>" alt="Favicon" style="width:32px;height:32px;border-radius:4px;border:1px solid #ddd;">
                        </div>
                        <?php endif; ?>
                        <input type="file" name="app_favicon" class="form-control form-control-sm" accept="image/*,.ico">
                        <small class="text-muted">ICO, PNG ou SVG. Tamanho ideal: 32x32 ou 64x64.</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- WhatsApp Flutuante -->
        <div class="card mb-4">
            <div class="card-header bg-white"><h6 class="mb-0" style="font-size:0.9rem"><i class="bi bi-whatsapp"></i> WhatsApp Flutuante</h6></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="whatsapp_enabled" value="1" id="whatsappEnabled" <?= ($settings['whatsapp_enabled'] ?? '') === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label fw-medium small" for="whatsappEnabled">Botão flutuante ativado</label>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-medium small">Número do WhatsApp</label>
                        <input type="text" name="whatsapp_number" class="form-control form-control-sm" value="<?= escape($settings['whatsapp_number'] ?? '') ?>" placeholder="5511999999999">
                        <small class="text-muted">Código do país + DDD + número, sem espaços.</small>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-medium small">Mensagem Padrão</label>
                        <input type="text" name="whatsapp_message" class="form-control form-control-sm" value="<?= escape($settings['whatsapp_message'] ?? 'Olá! Preciso de ajuda.') ?>">
                        <small class="text-muted">Texto pré-preenchido no WhatsApp.</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- SMTP -->
        <div class="card mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0" style="font-size:0.9rem"><i class="bi bi-envelope"></i> Email (SMTP)</h6>
                <button type="button" class="btn btn-outline-primary btn-sm" onclick="testSmtp()">
                    <i class="bi bi-send-check"></i> Testar
                </button>
            </div>
            <div class="card-body">
                <div id="smtp-test-result" class="mb-3" style="display:none"></div>
                <div class="row g-3">
                    <div class="col-sm-6">
                        <label class="form-label fw-medium small">Servidor SMTP</label>
                        <input type="text" name="smtp_host" class="form-control form-control-sm" value="<?= escape($settings['smtp_host'] ?? '') ?>" placeholder="smtp.gmail.com">
                    </div>
                    <div class="col-6 col-sm-3">
                        <label class="form-label fw-medium small">Porta</label>
                        <input type="text" name="smtp_port" class="form-control form-control-sm" value="<?= escape($settings['smtp_port'] ?? '587') ?>">
                    </div>
                    <div class="col-6 col-sm-3">
                        <label class="form-label fw-medium small">Criptografia</label>
                        <select name="smtp_encryption" class="form-select form-select-sm">
                            <option value="tls" <?= ($settings['smtp_encryption'] ?? '') === 'tls' ? 'selected' : '' ?>>TLS</option>
                            <option value="ssl" <?= ($settings['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                            <option value="" <?= empty($settings['smtp_encryption'] ?? '') ? 'selected' : '' ?>>Nenhuma</option>
                        </select>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-medium small">Usuário SMTP</label>
                        <input type="text" name="smtp_username" class="form-control form-control-sm" value="<?= escape($settings['smtp_username'] ?? '') ?>">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-medium small">Senha SMTP</label>
                        <input type="password" name="smtp_password" class="form-control form-control-sm" value="<?= escape($settings['smtp_password'] ?? '') ?>">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-medium small">Nome do Remetente</label>
                        <input type="text" name="smtp_from_name" class="form-control form-control-sm" value="<?= escape($settings['smtp_from_name'] ?? '') ?>">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-medium small">Email do Remetente</label>
                        <input type="email" name="smtp_from_email" class="form-control form-control-sm" value="<?= escape($settings['smtp_from_email'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- OpenAI -->
        <div class="card mb-4">
            <div class="card-header bg-white"><h6 class="mb-0" style="font-size:0.9rem"><i class="bi bi-robot"></i> OpenAI</h6></div>
            <div class="card-body">
                <label class="form-label fw-medium small">API Key</label>
                <input type="password" name="openai_api_key" class="form-control form-control-sm" value="<?= escape($settings['openai_api_key'] ?? '') ?>" placeholder="sk-...">
                <small class="text-muted">Necessário para transcrição por voz e datas de marketing.</small>
            </div>
        </div>

        <!-- Buffer (agendamento social) — múltiplas contas -->
        <div class="card mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0" style="font-size:0.9rem"><i class="bi bi-share"></i> Buffer (Redes Sociais)</h6>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="openBufferKeyModal()"><i class="bi bi-plus-lg"></i> Adicionar conta</button>
            </div>
            <div class="card-body">
                <p class="small text-muted mb-2">Conecte uma ou mais contas do Buffer (cada API key). Todos os canais de todas as contas aparecem em Métricas Sociais. Gere a key em <a href="https://publish.buffer.com/settings/api" target="_blank" rel="noopener">publish.buffer.com/settings/api</a>.</p>
                <div id="buffer-accounts-list" class="d-flex flex-column gap-2">
                    <div class="text-muted small">Carregando contas...</div>
                </div>
            </div>
        </div>

        <!-- Modal adicionar conta Buffer -->
        <div class="modal fade" id="bufferKeyModal" tabindex="-1">
            <div class="modal-dialog modal-sm">
                <div class="modal-content">
                    <div class="modal-header">
                        <h6 class="modal-title"><i class="bi bi-share"></i> Adicionar conta Buffer</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-2">
                            <label class="form-label small fw-medium">Nome (opcional)</label>
                            <input type="text" id="bk-label" class="form-control form-control-sm" placeholder="Ex: Conta ON Solutions">
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-medium">API Key *</label>
                            <input type="text" id="bk-key" class="form-control form-control-sm" placeholder="buf_...">
                        </div>
                        <div id="bk-error" class="text-danger small" style="display:none;"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-sm btn-primary" id="bk-save" onclick="saveBufferKey()">Conectar</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
        (function(){
            const B = '<?= baseUrl("") ?>';
            let bkModal = null;
            function loadBufferAccounts() {
                fetch(B + 'buffer/accounts', { headers:{'X-Requested-With':'XMLHttpRequest'} })
                    .then(r=>r.json()).then(d=>{
                        const box = document.getElementById('buffer-accounts-list');
                        const list = d.accounts || [];
                        if (!list.length) { box.innerHTML = '<div class="text-muted small">Nenhuma conta conectada. Clique em "Adicionar conta".</div>'; return; }
                        box.innerHTML = list.map(a => `
                            <div class="d-flex align-items-center justify-content-between border rounded p-2">
                                <div class="min-w-0">
                                    <div class="fw-medium small text-truncate">${escBk(a.label || 'Conta Buffer')}</div>
                                    <div class="text-muted" style="font-size:0.72rem;">${escBk(a.api_key_masked || '')}</div>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteBufferKey(${a.id})"><i class="bi bi-trash3"></i></button>
                            </div>`).join('');
                    }).catch(()=>{});
            }
            function escBk(s){ const d=document.createElement('div'); d.textContent=s||''; return d.innerHTML; }
            window.openBufferKeyModal = function(){
                document.getElementById('bk-label').value=''; document.getElementById('bk-key').value='';
                document.getElementById('bk-error').style.display='none';
                if(!bkModal) bkModal = new bootstrap.Modal(document.getElementById('bufferKeyModal'));
                bkModal.show();
            };
            window.saveBufferKey = function(){
                const key = document.getElementById('bk-key').value.trim();
                const err = document.getElementById('bk-error');
                if(!key){ err.textContent='Informe a API key.'; err.style.display='block'; return; }
                const btn = document.getElementById('bk-save'); btn.disabled=true; btn.textContent='Conectando...';
                const fd = new FormData(); fd.append('api_key', key); fd.append('label', document.getElementById('bk-label').value.trim());
                fetch(B + 'buffer/addAccount', { method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'} })
                    .then(r=>r.json()).then(d=>{
                        btn.disabled=false; btn.textContent='Conectar';
                        if(d.error){ err.textContent=d.error; err.style.display='block'; return; }
                        bkModal.hide(); loadBufferAccounts();
                    }).catch(()=>{ btn.disabled=false; btn.textContent='Conectar'; });
            };
            window.deleteBufferKey = function(id){
                if(!confirm('Remover esta conta Buffer e seus canais?')) return;
                fetch(B + 'buffer/deleteAccount/' + id, { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'} })
                    .then(r=>r.json()).then(()=> loadBufferAccounts());
            };
            document.addEventListener('DOMContentLoaded', loadBufferAccounts);
        })();
        </script>

        <!-- Meta (Facebook / Instagram) -->
        <div class="card mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0" style="font-size:0.9rem"><i class="bi bi-meta"></i> Meta (Facebook / Instagram)</h6>
                <div class="d-flex gap-2">
                    <a href="#metaTutorial" class="btn btn-outline-info btn-sm" data-bs-toggle="collapse" role="button"><i class="bi bi-question-circle"></i> Como gerar o token</a>
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="addMetaToken()"><i class="bi bi-plus-lg"></i> Adicionar token</button>
                </div>
            </div>
            <div class="collapse" id="metaTutorial">
                <div class="card-body border-bottom bg-light" style="font-size:0.78rem;">
                    <strong><i class="bi bi-book"></i> Tutorial: Como gerar o Access Token da Meta</strong>
                    <ol class="mt-2 mb-2 ps-3">
                        <li>Acesse <a href="https://developers.facebook.com/apps/" target="_blank" rel="noopener">developers.facebook.com/apps</a> e crie um App (tipo <strong>Business</strong>) se ainda não tiver.</li>
                        <li>Vá em <a href="https://developers.facebook.com/tools/explorer/" target="_blank" rel="noopener"><strong>Graph API Explorer</strong></a>.</li>
                        <li>Selecione seu App no topo da página.</li>
                        <li>Em <strong>Permissions</strong>, marque:
                            <code>pages_show_list</code>, <code>pages_read_engagement</code>, <code>instagram_basic</code>, <code>instagram_manage_insights</code>
                        </li>
                        <li>Clique em <strong>"Generate Access Token"</strong> — autorize com o Facebook que administra as páginas/Instagram.</li>
                        <li>Copie o token gerado. <em>Esse token é de curta duração (~1h).</em></li>
                        <li><strong>Converter para longa duração (~60 dias):</strong> Acesse no navegador:<br>
                            <code style="word-break:break-all;">https://graph.facebook.com/v21.0/oauth/access_token?grant_type=fb_exchange_token&client_id=<strong>SEU_APP_ID</strong>&client_secret=<strong>SEU_APP_SECRET</strong>&fb_exchange_token=<strong>TOKEN_CURTO</strong></code><br>
                            O <code>access_token</code> retornado é o de longa duração — cole aqui.
                        </li>
                        <li>Para <strong>outra conta/cliente</strong>: repita o processo logado com a outra conta Facebook e clique em "Adicionar token".</li>
                    </ol>
                    <div class="d-flex gap-2">
                        <a href="https://developers.facebook.com/tools/explorer/" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-box-arrow-up-right"></i> Graph API Explorer</a>
                        <a href="https://developers.facebook.com/docs/facebook-login/guides/access-tokens/get-long-lived/" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-box-arrow-up-right"></i> Docs: Token de longa duração</a>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <small class="text-muted d-block mb-3">Token de usuário/sistema de longa duração com <code>instagram_business_basic</code>, <code>instagram_business_manage_insights</code> e <code>pages_read_engagement</code>. Cada token representa uma conta/cliente diferente.</small>
                <div id="meta-tokens-list">
                    <?php
                    // Carrega todos os tokens Meta existentes (meta_access_token, meta_access_token_2, ...)
                    $metaTokens = [];
                    if (!empty($settings['meta_access_token'])) $metaTokens[] = $settings['meta_access_token'];
                    for ($i = 2; $i <= 20; $i++) {
                        $k = 'meta_access_token_' . $i;
                        if (!empty($settings[$k])) $metaTokens[] = $settings[$k];
                    }
                    if (empty($metaTokens)) $metaTokens[] = ''; // pelo menos 1 campo vazio
                    foreach ($metaTokens as $idx => $tkn):
                        $fieldName = $idx === 0 ? 'meta_tokens[]' : 'meta_tokens[]';
                    ?>
                    <div class="input-group input-group-sm mb-2 meta-token-row">
                        <span class="input-group-text"><?= $idx + 1 ?></span>
                        <input type="password" name="meta_tokens[]" class="form-control" value="<?= escape($tkn) ?>" placeholder="EAAB...">
                        <?php if ($idx > 0): ?>
                        <button type="button" class="btn btn-outline-danger" onclick="this.closest('.meta-token-row').remove()" title="Remover"><i class="bi bi-trash3"></i></button>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- LinkedIn (Páginas de organização) -->
        <div class="card mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0" style="font-size:0.9rem"><i class="bi bi-linkedin"></i> LinkedIn (Organização)</h6>
                <div class="d-flex gap-2">
                    <a href="#linkedinTutorial" class="btn btn-outline-info btn-sm" data-bs-toggle="collapse" role="button"><i class="bi bi-question-circle"></i> Como gerar o token</a>
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="addLinkedinToken()"><i class="bi bi-plus-lg"></i> Adicionar token</button>
                </div>
            </div>
            <div class="collapse" id="linkedinTutorial">
                <div class="card-body border-bottom bg-light" style="font-size:0.78rem;">
                    <strong><i class="bi bi-book"></i> Tutorial: Como gerar o Access Token do LinkedIn</strong>
                    <ol class="mt-2 mb-2 ps-3">
                        <li>Acesse <a href="https://www.linkedin.com/developers/apps" target="_blank" rel="noopener">linkedin.com/developers/apps</a> e crie um App (ou use um existente).</li>
                        <li>No app, vá na aba <strong>"Products"</strong> e solicite acesso a <strong>"Community Management API"</strong>.</li>
                        <li>Na aba <strong>"Auth"</strong>, copie o <code>Client ID</code> e o <code>Client Secret</code>.</li>
                        <li>Na mesma aba, adicione uma <strong>Redirect URL</strong> (ex: <code>https://helpdesk.onsolutionsbrasil.com.br/callback</code>).</li>
                        <li>Acesse no navegador para autorizar:<br>
                            <code style="word-break:break-all;">https://www.linkedin.com/oauth/v2/authorization?response_type=code&client_id=<strong>SEU_CLIENT_ID</strong>&redirect_uri=<strong>SUA_REDIRECT_URL</strong>&scope=r_organization_social%20r_organization_followers%20rw_organization_admin</code>
                        </li>
                        <li>Após autorizar, você será redirecionado com <code>?code=XXXXX</code> na URL. Copie esse código.</li>
                        <li>Troque o code pelo token via POST (use o Postman, Insomnia ou terminal):<br>
                            <code style="word-break:break-all;">POST https://www.linkedin.com/oauth/v2/accessToken<br>
                            grant_type=authorization_code&code=<strong>CODIGO</strong>&redirect_uri=<strong>SUA_REDIRECT_URL</strong>&client_id=<strong>CLIENT_ID</strong>&client_secret=<strong>CLIENT_SECRET</strong></code>
                        </li>
                        <li>O <code>access_token</code> retornado dura <strong>60 dias</strong>. Cole aqui.</li>
                        <li>Para outra organização com admin diferente: repita logado com a outra conta e clique em "Adicionar token".</li>
                    </ol>
                    <div class="d-flex gap-2">
                        <a href="https://www.linkedin.com/developers/apps" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-box-arrow-up-right"></i> LinkedIn Developer Apps</a>
                        <a href="https://learn.microsoft.com/en-us/linkedin/shared/authentication/authorization-code-flow" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-box-arrow-up-right"></i> Docs: OAuth 2.0 Flow</a>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <small class="text-muted d-block mb-3">Token OAuth para acessar dados da organização. Cada token representa uma organização/cliente diferente.</small>

                <!-- Credenciais do App LinkedIn (para OAuth automático) -->
                <div class="row g-2 mb-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-medium">Client ID</label>
                        <input type="text" name="linkedin_client_id" class="form-control form-control-sm" value="<?= escape($settings['linkedin_client_id'] ?? '') ?>" placeholder="77qae2ikx3r2b7">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-medium">Client Secret</label>
                        <input type="password" name="linkedin_client_secret" class="form-control form-control-sm" value="<?= escape($settings['linkedin_client_secret'] ?? '') ?>" placeholder="WPL_AP1.xxxxx">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-medium">Escopos (separados por espaço)</label>
                        <input type="text" name="linkedin_scopes" class="form-control form-control-sm" id="linkedin-scopes" value="<?= escape($settings['linkedin_scopes'] ?? 'r_organization_social r_organization_followers w_organization_social') ?>" placeholder="r_organization_social r_organization_followers">
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2 mb-3">
                    <button type="button" class="btn btn-sm btn-success <?= empty($settings['linkedin_client_id']) ? 'disabled' : '' ?>" onclick="authorizeLinkedin()"><i class="bi bi-key"></i> Autorizar LinkedIn</button>
                    <small class="text-muted"><i class="bi bi-info-circle"></i> Salve primeiro, depois clique em Autorizar. O token será gerado e salvo automaticamente.</small>
                </div>
                <div class="alert alert-light border small py-2 px-3 mb-3">
                    <strong>Escopos disponíveis (depende do Product adicionado no app):</strong><br>
                    <code>r_organization_social</code> — ler posts da organização<br>
                    <code>r_organization_followers</code> — ler seguidores (requer Community Management API)<br>
                    <code>w_organization_social</code> — postar como organização<br>
                    <code>r_basicprofile</code> ou <code>openid profile</code> — dados básicos do perfil<br>
                    <small class="text-muted mt-1 d-block">Se aparecer erro "scope is not valid", vá em <a href="https://www.linkedin.com/developers/apps" target="_blank">LinkedIn Developers</a> → seu app → Products e adicione <strong>Community Management API</strong>.</small>
                </div>

                <hr class="my-3">
                <script>
                function authorizeLinkedin() {
                    const clientId = document.querySelector('[name="linkedin_client_id"]').value.trim();
                    const scopes = document.getElementById('linkedin-scopes').value.trim();
                    if (!clientId) { alert('Preencha o Client ID primeiro.'); return; }
                    const redirectUri = '<?= rtrim(baseUrl("callback"), "/") ?>';
                    const url = 'https://www.linkedin.com/oauth/v2/authorization?response_type=code'
                        + '&client_id=' + encodeURIComponent(clientId)
                        + '&redirect_uri=' + encodeURIComponent(redirectUri)
                        + '&scope=' + encodeURIComponent(scopes)
                        + '&state=linkedin';
                    window.location.href = url;
                }
                </script>
                <label class="form-label small fw-medium">Tokens salvos</label>
                <div id="linkedin-tokens-list">
                    <?php
                    $linkedinTokens = [];
                    if (!empty($settings['linkedin_access_token'])) $linkedinTokens[] = $settings['linkedin_access_token'];
                    for ($i = 2; $i <= 20; $i++) {
                        $k = 'linkedin_access_token_' . $i;
                        if (!empty($settings[$k])) $linkedinTokens[] = $settings[$k];
                    }
                    if (empty($linkedinTokens)) $linkedinTokens[] = '';
                    foreach ($linkedinTokens as $idx => $tkn):
                    ?>
                    <div class="input-group input-group-sm mb-2 linkedin-token-row">
                        <span class="input-group-text"><?= $idx + 1 ?></span>
                        <input type="password" name="linkedin_tokens[]" class="form-control" value="<?= escape($tkn) ?>" placeholder="AQV...">
                        <?php if ($idx > 0): ?>
                        <button type="button" class="btn btn-outline-danger" onclick="this.closest('.linkedin-token-row').remove()" title="Remover"><i class="bi bi-trash3"></i></button>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Google Agenda / Meet -->
        <div class="card mb-4">
            <div class="card-header bg-white"><h6 class="mb-0" style="font-size:0.9rem"><i class="bi bi-google"></i> Google Agenda / Meet</h6></div>
            <div class="card-body">
                <small class="text-muted d-block mb-3">Integração para criar eventos no Google Agenda com link do Google Meet ao agendar reuniões. Crie credenciais OAuth (tipo Web) no Google Cloud com o escopo <code>https://www.googleapis.com/auth/calendar</code> e gere um <strong>refresh token</strong> offline.</small>
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label fw-medium small">Client ID</label>
                        <input type="text" name="google_client_id" class="form-control form-control-sm" value="<?= escape($settings['google_client_id'] ?? '') ?>" placeholder="xxxxx.apps.googleusercontent.com">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-medium small">Client Secret</label>
                        <input type="password" name="google_client_secret" class="form-control form-control-sm" value="<?= escape($settings['google_client_secret'] ?? '') ?>" placeholder="GOCSPX-...">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label fw-medium small">Refresh Token</label>
                        <input type="password" name="google_refresh_token" class="form-control form-control-sm" value="<?= escape($settings['google_refresh_token'] ?? '') ?>" placeholder="1//0g...">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-medium small">Calendar ID</label>
                        <input type="text" name="google_calendar_id" class="form-control form-control-sm" value="<?= escape($settings['google_calendar_id'] ?? '') ?>" placeholder="primary">
                    </div>
                </div>
            </div>
        </div>

        <script>
        function addMetaToken() {
            const list = document.getElementById('meta-tokens-list');
            const count = list.querySelectorAll('.meta-token-row').length + 1;
            const row = document.createElement('div');
            row.className = 'input-group input-group-sm mb-2 meta-token-row';
            row.innerHTML = '<span class="input-group-text">' + count + '</span>'
                + '<input type="password" name="meta_tokens[]" class="form-control" placeholder="EAAB...">'
                + '<button type="button" class="btn btn-outline-danger" onclick="this.closest(\'.meta-token-row\').remove()" title="Remover"><i class="bi bi-trash3"></i></button>';
            list.appendChild(row);
        }
        function addLinkedinToken() {
            const list = document.getElementById('linkedin-tokens-list');
            const count = list.querySelectorAll('.linkedin-token-row').length + 1;
            const row = document.createElement('div');
            row.className = 'input-group input-group-sm mb-2 linkedin-token-row';
            row.innerHTML = '<span class="input-group-text">' + count + '</span>'
                + '<input type="password" name="linkedin_tokens[]" class="form-control" placeholder="AQV...">'
                + '<button type="button" class="btn btn-outline-danger" onclick="this.closest(\'.linkedin-token-row\').remove()" title="Remover"><i class="bi bi-trash3"></i></button>';
            list.appendChild(row);
        }
        </script>

        <!-- Webhook WhatsApp -->
        <div class="card mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0" style="font-size:0.9rem"><i class="bi bi-broadcast"></i> Webhook WhatsApp (Notificação de Novas Demandas)</h6>
                <button type="button" class="btn btn-outline-primary btn-sm" onclick="testWebhook()">
                    <i class="bi bi-send-check"></i> Testar
                </button>
            </div>
            <div class="card-body">
                <div id="webhook-test-result" class="mb-3" style="display:none"></div>
                <div class="row g-3">
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="webhook_enabled" value="1" id="webhookEnabled" <?= ($settings['webhook_enabled'] ?? '') === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label fw-medium small" for="webhookEnabled">Webhook ativado</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-medium small">URL do Webhook</label>
                        <input type="url" name="webhook_url" class="form-control form-control-sm" value="<?= escape($settings['webhook_url'] ?? '') ?>" placeholder="https://seu-disparo-whatsapp.com/api/send">
                        <small class="text-muted">URL que receberá o POST com os campos: <code>phone</code>, <code>name</code>, <code>message</code></small>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-medium small">Telefones (separados por vírgula)</label>
                        <textarea name="webhook_phones" class="form-control form-control-sm" rows="3" placeholder="5511999999999, 5511888888888"><?= escape($settings['webhook_phones'] ?? $settings['webhook_phone'] ?? '') ?></textarea>
                        <small class="text-muted">Um disparo será feito para cada número. Formato: código do país + DDD + número.</small>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-medium small">Nomes (separados por vírgula, mesma ordem dos telefones)</label>
                        <textarea name="webhook_names" class="form-control form-control-sm" rows="3" placeholder="João, Maria"><?= escape($settings['webhook_names'] ?? $settings['webhook_name'] ?? '') ?></textarea>
                        <small class="text-muted">Nome de cada destinatário correspondente ao telefone.</small>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-medium small">Template da Mensagem</label>
                        <textarea name="webhook_message_template" class="form-control form-control-sm" rows="4" placeholder="🔔 *Nova Demanda #{ticket_id}*&#10;&#10;*Cliente:* {client_name}&#10;*Título:* {ticket_title}&#10;*Prioridade:* {priority}&#10;&#10;Acesse o painel para ver os detalhes."><?= escape($settings['webhook_message_template'] ?? '') ?></textarea>
                        <small class="text-muted">
                            Variáveis disponíveis: <code>{ticket_id}</code>, <code>{ticket_title}</code>, <code>{client_name}</code>, <code>{priority}</code>, <code>{category}</code>, <code>{name}</code> (destinatário), <code>{date}</code>
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Grupo de Notificações WhatsApp -->
        <div class="card mb-4">
            <div class="card-header bg-white"><h6 class="mb-0" style="font-size:0.9rem"><i class="bi bi-people-fill text-success"></i> Grupo Padrão de Notificações (WhatsApp)</h6></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="whatsapp_group_notify_enabled" value="1" id="groupNotifyEnabled" <?= ($settings['whatsapp_group_notify_enabled'] ?? '') === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label fw-medium small" for="groupNotifyEnabled">Enviar atualizações de status para o grupo padrão</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-medium small">Grupo padrão (empresa dona do helpdesk)</label>
                        <select name="whatsapp_default_group_jid" class="form-select form-select-sm">
                            <option value="">Nenhum grupo selecionado</option>
                            <?php foreach (($whatsappGroups ?? []) as $g): ?>
                            <option value="<?= escape($g['remote_jid']) ?>" <?= ($settings['whatsapp_default_group_jid'] ?? '') === $g['remote_jid'] ? 'selected' : '' ?>>
                                <?= escape($g['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Todas as atualizações de status das demandas serão enviadas neste grupo, para que todos os integrantes recebam. Usa a conexão de WhatsApp já existente no chat.</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Banco -->
        <div class="card mb-4">
            <div class="card-header bg-white"><h6 class="mb-0" style="font-size:0.9rem"><i class="bi bi-database"></i> Banco de Dados</h6></div>
            <div class="card-body">
                <a href="<?= baseUrl('settings/database') ?>" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-wrench"></i> Configurar Banco
                </a>
            </div>
        </div>

        <button type="submit" class="btn btn-primary px-4">
            <i class="bi bi-check-lg"></i> Salvar Configurações
        </button>
    </form>
</div>

<script>
function testSmtp() {
    const btn = event.target.closest('button');
    const result = document.getElementById('smtp-test-result');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Enviando...';
    result.style.display = 'none';

    fetch('<?= baseUrl("settings/testEmail") ?>', { method: 'POST' })
        .then(r => r.json())
        .then(data => {
            result.style.display = 'block';
            if (data.success) {
                result.className = 'mb-3 alert alert-success py-2';
                result.innerHTML = '<small><i class="bi bi-check-circle"></i> ' + data.message + '</small>';
            } else {
                result.className = 'mb-3 alert alert-danger py-2';
                result.innerHTML = '<small><i class="bi bi-x-circle"></i> ' + data.message + '</small>';
            }
        })
        .catch(() => {
            result.style.display = 'block';
            result.className = 'mb-3 alert alert-danger py-2';
            result.innerHTML = '<small>Erro na requisição.</small>';
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-send-check"></i> Testar';
        });
}

function testWebhook() {
    const btn = event.target.closest('button');
    const result = document.getElementById('webhook-test-result');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Enviando...';
    result.style.display = 'none';

    fetch('<?= baseUrl("settings/testWebhook") ?>', { method: 'POST' })
        .then(r => r.json())
        .then(data => {
            result.style.display = 'block';
            if (data.success) {
                result.className = 'mb-3 alert alert-success py-2';
                result.innerHTML = '<small><i class="bi bi-check-circle"></i> ' + data.message + '</small>';
            } else {
                result.className = 'mb-3 alert alert-danger py-2';
                result.innerHTML = '<small><i class="bi bi-x-circle"></i> ' + data.message + '</small>';
            }
        })
        .catch(() => {
            result.style.display = 'block';
            result.className = 'mb-3 alert alert-danger py-2';
            result.innerHTML = '<small>Erro na requisição.</small>';
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-send-check"></i> Testar';
        });
}
</script>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
