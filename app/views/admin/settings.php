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
                <button type="button" class="btn btn-outline-primary btn-sm" onclick="addMetaToken()"><i class="bi bi-plus-lg"></i> Adicionar token</button>
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
                <button type="button" class="btn btn-outline-primary btn-sm" onclick="addLinkedinToken()"><i class="bi bi-plus-lg"></i> Adicionar token</button>
            </div>
            <div class="card-body">
                <small class="text-muted d-block mb-3">Token OAuth com escopos <code>r_organization_social</code>, <code>r_organization_followers</code> e <code>rw_organization_admin</code>. Cada token representa uma organização/cliente diferente.</small>
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
