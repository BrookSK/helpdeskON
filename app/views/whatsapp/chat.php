<?php $pageTitle = 'WhatsApp Chat'; $currentPage = 'whatsapp_chat'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<div class="main-content wpp-main-override">
    <!-- Layout principal 3 colunas -->
    <div class="wpp-layout">
        <!-- COLUNA ESQUERDA: Lista de contatos -->
        <div class="wpp-contacts-panel" id="contacts-panel">
            <div class="wpp-contacts-header">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="fw-medium" style="font-size:0.9rem;"><i class="bi bi-whatsapp text-success"></i> Chat</span>
                    <div class="d-flex gap-1">
                        <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-1" title="Sincronizar nomes dos grupos" onclick="syncGroups(this)"><i class="bi bi-arrow-repeat" style="font-size:0.75rem;"></i></button>
                        <button type="button" class="btn btn-sm btn-outline-warning py-0 px-1" title="Respostas rápidas" onclick="openQuickRepliesModal()"><i class="bi bi-lightning-charge-fill" style="font-size:0.75rem;"></i></button>
                        <a href="<?= baseUrl('whatsapp') ?>" class="btn btn-sm btn-outline-secondary py-0 px-1" title="Conexões"><i class="bi bi-gear" style="font-size:0.75rem;"></i></a>
                        <a href="<?= baseUrl('crm') ?>" class="btn btn-sm btn-outline-primary py-0 px-1" title="CRM"><i class="bi bi-kanban" style="font-size:0.75rem;"></i></a>
                    </div>
                </div>
                <button class="btn btn-sm btn-success w-100 mb-2" onclick="openStartConversation()">
                    <i class="bi bi-plus-circle"></i> Iniciar conversa
                </button>
                <input type="text" class="form-control form-control-sm" id="contact-search" placeholder="Buscar contato ou grupo...">
                <div class="d-flex gap-1 mt-2 flex-nowrap">
                    <select class="form-select form-select-sm flex-fill" id="filter-assigned" style="font-size:0.72rem;min-width:0;width:33%;">
                        <option value="">Todos</option>
                        <option value="unassigned">Sem dono</option>
                        <?php foreach ($teamMembers as $m): ?>
                        <option value="<?= $m['id'] ?>"><?= escape($m['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select class="form-select form-select-sm flex-fill" id="filter-label" style="font-size:0.72rem;min-width:0;width:33%;">
                        <option value="">Etiquetas</option>
                        <?php foreach ($labels as $l): ?>
                        <option value="<?= $l['id'] ?>"><?= escape($l['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select class="form-select form-select-sm flex-fill" id="filter-status" style="font-size:0.72rem;min-width:0;width:33%;">
                        <option value="">Status</option>
                        <option value="em_atendimento">Em atendimento</option>
                        <option value="aguardando">Aguardando</option>
                        <option value="concluido">Concluído</option>
                        <option value="novo">Novo</option>
                    </select>
                </div>
            </div>
            <!-- Abas Contatos / Grupos -->
            <div class="wpp-tabs">
                <button class="wpp-tab active" data-tab="contacts" onclick="switchTab('contacts')">
                    <i class="bi bi-person"></i> Contatos <span class="wpp-tab-count" id="count-contacts">0</span>
                </button>
                <button class="wpp-tab" data-tab="groups" onclick="switchTab('groups')">
                    <i class="bi bi-people"></i> Grupos <span class="wpp-tab-count" id="count-groups">0</span>
                </button>
            </div>
            <div class="wpp-contacts-list" id="contacts-list">
                <div class="text-center py-4 text-muted small">Carregando...</div>
            </div>
        </div>

        <!-- COLUNA CENTRAL: Mensagens -->
        <div class="wpp-chat-panel" id="chat-panel">
            <div class="wpp-chat-empty" id="chat-empty">
                <i class="bi bi-chat-text" style="font-size:4rem;color:#ccc;"></i>
                <p class="text-muted mt-2">Selecione um contato para iniciar</p>
            </div>
            <!-- Header do chat ativo -->
            <div class="wpp-chat-header" id="chat-header" style="display:none;">
                <div class="d-flex align-items-center gap-2 cursor-pointer" onclick="toggleDetailPanel()">
                    <div class="wpp-avatar-sm" id="chat-avatar">?</div>
                    <div>
                        <div class="fw-medium" style="font-size:0.88rem;" id="chat-contact-name">—</div>
                        <small class="text-muted" id="chat-contact-phone"></small>
                    </div>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <div class="form-check form-switch mb-0" title="Assinar mensagens com seu nome">
                        <input class="form-check-input" type="checkbox" id="toggle-signature" checked>
                        <label class="form-check-label" for="toggle-signature" style="font-size:0.68rem;color:#666;">Assinar</label>
                    </div>
                    <select class="form-select form-select-sm" id="chat-service-status" style="font-size:0.7rem;max-width:130px;" onchange="changeServiceStatus()">
                        <option value="novo">Novo</option>
                        <option value="em_atendimento">Em atendimento</option>
                        <option value="aguardando">Aguardando</option>
                        <option value="concluido">Concluído</option>
                    </select>
                    <button class="btn btn-sm btn-outline-secondary" onclick="toggleDetailPanel()" title="Detalhes">
                        <i class="bi bi-person-lines-fill"></i>
                    </button>
                </div>
            </div>
            <!-- Área de mensagens (com overlay de envio de mídia) -->
            <div class="wpp-messages-wrap" style="flex:1;position:relative;min-height:0;display:flex;flex-direction:column;">
                <div class="wpp-messages" id="messages-area" style="display:none;"></div>

            </div>

            <!-- Prévia minimizada do arquivo selecionado (barra acima da caixa de texto) -->
            <div id="media-staging" class="wpp-media-bar" style="display:none;">
                <div id="media-staging-preview" class="wpp-media-bar-thumb"></div>
                <div class="flex-grow-1 overflow-hidden">
                    <div id="media-staging-name" class="fw-medium text-truncate" style="font-size:0.82rem;"></div>
                    <div id="media-staging-size" class="text-muted" style="font-size:0.72rem;"></div>
                </div>
                <button class="btn btn-sm btn-outline-danger" onclick="cancelStagedMedia()" title="Cancelar"><i class="bi bi-x-lg"></i></button>
            </div>

            <!-- Input de mensagem (textarea para Shift+Enter) -->
            <div class="wpp-input-area" id="input-area" style="display:none;position:relative;">
                <!-- Galeria de emojis -->
                <div id="emoji-picker" class="wpp-emoji-picker" style="display:none;"></div>
                <!-- Autocomplete de respostas rápidas (/atalho) -->
                <div id="quick-reply-suggest" class="wpp-quick-suggest" style="display:none;"></div>
                <button class="btn btn-sm btn-outline-secondary" id="btn-emoji" onclick="toggleEmojiPicker()" title="Emojis">
                    <i class="bi bi-emoji-smile"></i>
                </button>
                <button class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('media-input').click()" title="Anexar arquivo">
                    <i class="bi bi-paperclip"></i>
                </button>
                <input type="file" id="media-input" style="display:none;" onchange="stageMediaFile()">
                <textarea class="form-control form-control-sm" id="message-input" placeholder="Digite uma mensagem..." rows="1"></textarea>
                <button class="btn btn-sm btn-success" id="btn-send" onclick="handleSend()"><i class="bi bi-send"></i></button>
            </div>
        </div>

        <!-- COLUNA DIREITA: Detalhes do contato -->
        <div class="wpp-detail-panel" id="detail-panel">
            <div class="wpp-detail-header">
                <span class="fw-medium">Detalhes do Contato</span>
                <button class="btn btn-sm btn-outline-secondary" onclick="toggleDetailPanel()"><i class="bi bi-x"></i></button>
            </div>
            <div class="wpp-detail-body" id="detail-body">
                <div class="text-center py-4">
                    <div class="wpp-avatar-lg mx-auto" id="detail-avatar">?</div>
                    <h6 class="mt-2" id="detail-name">—</h6>
                    <small class="text-muted" id="detail-phone">—</small>
                </div>
                <hr>
                <div class="mb-3">
                    <label class="form-label small fw-medium">Nome</label>
                    <input type="text" class="form-control form-control-sm" id="detail-name-input">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-medium">Atribuído a</label>
                    <select class="form-select form-select-sm" id="detail-assigned">
                        <option value="">Ninguém</option>
                        <?php foreach ($teamMembers as $m): ?>
                        <option value="<?= $m['id'] ?>"><?= escape($m['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-medium">Observações Internas</label>
                    <textarea class="form-control form-control-sm" id="detail-notes" rows="4" placeholder="Notas sobre este contato..."></textarea>
                </div>
                <button class="btn btn-sm btn-primary w-100 mb-3" onclick="saveContactDetails()">
                    <i class="bi bi-check-lg"></i> Salvar
                </button>
                <hr>
                <!-- Etiquetas -->
                <div class="mb-3">
                    <label class="form-label small fw-medium">Etiquetas</label>
                    <div id="detail-labels" class="d-flex flex-wrap gap-1 mb-2"></div>
                    <div class="d-flex gap-1">
                        <select class="form-select form-select-sm" id="add-label-select" style="font-size:0.75rem;">
                            <option value="">Selecionar...</option>
                            <?php foreach ($labels as $l): ?>
                            <option value="<?= $l['id'] ?>" data-color="<?= $l['color'] ?>"><?= escape($l['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-sm btn-outline-primary" onclick="addLabelToContact()" title="Adicionar etiqueta selecionada">
                            <i class="bi bi-plus"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-success" onclick="openCreateLabelModal()" title="Criar nova etiqueta">
                            <i class="bi bi-tag"></i>
                        </button>
                    </div>
                </div>
                <hr>
                <!-- Briefing Comercial -->
                <div class="mb-3">
                    <button class="btn btn-sm btn-primary w-100" onclick="openBriefingModal()">
                        <i class="bi bi-clipboard-data"></i> Briefing comercial
                    </button>
                </div>
                <hr>
                <!-- Adicionar ao CRM -->
                <div class="mb-3">
                    <label class="form-label small fw-medium">CRM</label>
                    <div class="d-flex gap-1">
                        <select class="form-select form-select-sm" id="crm-board-select" style="font-size:0.75rem;" onchange="loadBoardColumns()">
                            <option value="">Selecione board</option>
                        </select>
                        <select class="form-select form-select-sm" id="crm-column-select" style="font-size:0.75rem;display:none;"></select>
                    </div>
                    <button class="btn btn-sm btn-outline-success w-100 mt-2" onclick="addContactToCrm()">
                        <i class="bi bi-kanban"></i> Adicionar ao CRM
                    </button>
                </div>
                <?php if (($user['role'] ?? '') === 'super_admin'): ?>
                <hr>
                <div class="mb-3">
                    <button class="btn btn-sm btn-outline-danger w-100" onclick="deleteContact()">
                        <i class="bi bi-trash3"></i> Excluir contato permanentemente
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal Respostas Rápidas -->
<div class="modal fade" id="quickRepliesModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-lightning-charge-fill text-warning"></i> Respostas Rápidas</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted">Cadastre mensagens de uso frequente. No chat, digite <code>/atalho</code> para inserir rapidamente.</p>
                <div class="row g-2 align-items-end mb-2 p-2 rounded" style="background:#f8f9fa;">
                    <input type="hidden" id="qr-id">
                    <div class="col-sm-3">
                        <label class="form-label small fw-medium mb-1">Atalho</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">/</span>
                            <input type="text" id="qr-shortcut" class="form-control" placeholder="ex: bomdia">
                        </div>
                    </div>
                    <div class="col-sm-5">
                        <label class="form-label small fw-medium mb-1">Mensagem</label>
                        <textarea id="qr-message" class="form-control form-control-sm qr-message-auto" rows="1" placeholder="Texto da resposta (opcional se houver anexo)..." oninput="autoGrowQrMessage(this)"></textarea>
                    </div>
                    <div class="col-sm-2">
                        <label class="form-label small fw-medium mb-1">Anexo</label>
                        <input type="file" id="qr-attachment" class="form-control form-control-sm" onchange="onQrAttachmentChange()">
                    </div>
                    <div class="col-sm-2 d-grid">
                        <button class="btn btn-sm btn-primary" onclick="saveQuickReply()"><i class="bi bi-check-lg"></i> Salvar</button>
                    </div>
                    <input type="hidden" id="qr-remove-attachment" value="0">
                    <div class="col-12" id="qr-selected-attachment" style="display:none;">
                        <div class="d-inline-flex align-items-center gap-2 small mt-1 px-2 py-1 rounded" style="background:var(--primary-50);border:1px solid var(--primary-light);color:var(--primary-dark);">
                            <i class="bi bi-check-circle-fill"></i>
                            <span class="fw-medium">Arquivo anexado:</span>
                            <span id="qr-selected-attachment-name" class="text-truncate" style="max-width:260px;"></span>
                            <button type="button" class="btn-close btn-close-sm ms-1" style="font-size:0.6rem;" onclick="clearQrSelectedAttachment()" title="Remover"></button>
                        </div>
                    </div>
                    <div class="col-12" id="qr-current-attachment" style="display:none;">
                        <div class="d-inline-flex align-items-center gap-2 small mt-1 px-2 py-1 rounded" style="background:#f1f3f5;border:1px solid #dee2e6;">
                            <i class="bi bi-paperclip text-muted"></i>
                            <span class="text-muted">Anexo atual:</span>
                            <a href="#" id="qr-current-attachment-link" target="_blank" class="text-truncate fw-medium" style="max-width:240px;"></a>
                            <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1" onclick="removeQrAttachment()" title="Remover anexo"><i class="bi bi-x-lg"></i></button>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light"><tr><th>Atalho</th><th>Mensagem</th><th>Anexo</th><th></th></tr></thead>
                        <tbody id="qr-list"><tr><td colspan="4" class="text-muted small text-center py-3">Carregando...</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Lightbox de imagem -->
<div id="img-lightbox" class="wpp-lightbox" onclick="closeLightbox(event)">
    <span class="wpp-lightbox-close" onclick="closeLightbox(event)">&times;</span>
    <img id="img-lightbox-img" src="" alt="">
</div>

<!-- Modal Criar Etiqueta -->
<div class="modal fade" id="createLabelModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Nova Etiqueta</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-medium">Nome *</label>
                    <input type="text" id="new-label-name" class="form-control form-control-sm" placeholder="ex: VIP, Urgente..." required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-medium">Cor</label>
                    <input type="color" id="new-label-color" class="form-control form-control-sm form-control-color" value="#00BFA6">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-sm btn-primary" onclick="createLabel()">Criar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Iniciar Conversa -->
<div class="modal fade" id="startConversationModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-plus-circle text-success"></i> Iniciar conversa</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-medium">Número (com DDD) *</label>
                    <input type="text" id="start-conv-phone" class="form-control form-control-sm" placeholder="Ex: 5511999999999" inputmode="numeric" oninput="this.value=this.value.replace(/\D/g,'')" onpaste="setTimeout(()=>{this.value=this.value.replace(/\\D/g,'')},0)">
                    <small class="text-muted">Somente números. Ex: 5517999999999</small>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-medium">Nome</label>
                    <input type="text" id="start-conv-name" class="form-control form-control-sm" placeholder="Opcional — se vazio, usa o nome do WhatsApp">
                </div>
                <div id="start-conv-error" class="text-danger small" style="display:none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-sm btn-success" id="start-conv-btn" onclick="startConversation()">Iniciar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Briefing Comercial -->
<div class="modal fade" id="briefingModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-clipboard-data"></i> Briefing Comercial</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label small fw-medium">Necessidade do lead</label>
                        <textarea id="bf-need" class="form-control form-control-sm" rows="2"></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-medium">Principal dor/problema</label>
                        <textarea id="bf-main_pain" class="form-control form-control-sm" rows="2"></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-medium">Solução atual utilizada</label>
                        <textarea id="bf-current_solution" class="form-control form-control-sm" rows="2"></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-medium">Objetivo esperado</label>
                        <textarea id="bf-expected_goal" class="form-control form-control-sm" rows="2"></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-medium">Urgência/prazo</label>
                        <select id="bf-urgency" class="form-select form-select-sm">
                            <option value="">Selecione</option>
                            <option value="Baixa">Baixa</option>
                            <option value="Média">Média</option>
                            <option value="Alta">Alta</option>
                            <option value="Urgente">Urgente</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-medium">Faixa de investimento</label>
                        <input type="text" id="bf-investment_range" class="form-control form-control-sm" placeholder="R$ 0,00" oninput="formatCurrencyInput(this)">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-medium">Nível de decisão do contato</label>
                        <select id="bf-decision_level" class="form-select form-select-sm">
                            <option value="">Selecione</option>
                            <option value="Decisor">Decisor</option>
                            <option value="Influenciador">Influenciador</option>
                            <option value="Usuário">Usuário</option>
                            <option value="Técnico">Técnico</option>
                            <option value="Sem influência">Sem influência</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-medium">Temperatura do lead</label>
                        <select id="bf-lead_temperature" class="form-select form-select-sm">
                            <option value="">Selecione</option>
                            <option value="frio">Frio</option>
                            <option value="morno">Morno</option>
                            <option value="quente">Quente</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-medium">Data do próximo contato</label>
                        <input type="date" id="bf-next_contact_date" class="form-control form-control-sm">
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-medium">Principal objeção</label>
                        <textarea id="bf-main_objection" class="form-control form-control-sm" rows="2"></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-medium">Próximo passo combinado</label>
                        <textarea id="bf-next_step" class="form-control form-control-sm" rows="2"></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-medium">Observações importantes</label>
                        <textarea id="bf-notes" class="form-control form-control-sm" rows="3"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-sm btn-primary" onclick="saveBriefing()">Salvar Briefing</button>
            </div>
        </div>
    </div>
</div>

<style>
/* Override do main-content padrão — zerar padding e fixar height sem scroll */
.wpp-main-override {
    padding: 0 !important;
    margin-left: 0 !important;
    height: 100vh !important;
    min-height: unset !important;
    max-height: 100vh !important;
    overflow: hidden !important;
    display: flex !important;
    flex-direction: column !important;
    position: fixed !important;
    top: 0 !important;
    right: 0 !important;
    left: var(--sidebar-width) !important;
    bottom: 0 !important;
    width: auto !important;
    transition: left 0.3s ease;
}
/* Quando o sidebar está recolhido, o chat ocupa o espaço liberado */
body.sidebar-collapsed .wpp-main-override {
    left: 70px !important;
}
/* Esconder a topbar mobile quando estiver na tela de chat */
.mobile-topbar { display: none !important; }
body { overflow: hidden !important; margin: 0; padding: 0; }

.wpp-layout { display: flex; flex: 1; overflow: hidden; height: 100%; }
.wpp-contacts-panel { width: 340px; min-width: 340px; border-right: 1px solid #e9ecef; display: flex; flex-direction: column; background: #fff; height: 100%; }
.wpp-contacts-header { padding: 10px; border-bottom: 1px solid #f0f0f0; flex-shrink: 0; }
.wpp-tabs { display: flex; border-bottom: 1px solid #e9ecef; flex-shrink: 0; }
.wpp-tab { flex: 1; padding: 8px; font-size: 0.78rem; font-weight: 500; border: none; background: none; color: #666; cursor: pointer; transition: all 0.2s; border-bottom: 2px solid transparent; }
.wpp-tab.active { color: var(--primary); border-bottom-color: var(--primary); background: #f0faf8; }
.wpp-tab:hover { background: #f5f5f5; }
.wpp-tab-count { font-size: 0.65rem; background: #e0e0e0; padding: 1px 5px; border-radius: 8px; color: #555; }
.wpp-tab.active .wpp-tab-count { background: var(--primary-light); color: var(--primary-dark); }
.wpp-contacts-list { flex: 1; overflow-y: auto; }
.wpp-section-header { padding: 6px 12px; font-size: 0.68rem; font-weight: 600; color: #888; text-transform: uppercase; letter-spacing: 0.5px; background: #fafafa; border-bottom: 1px solid #f0f0f0; position: sticky; top: 0; z-index: 1; }
.wpp-contact-item { padding: 10px 12px; display: flex; align-items: center; gap: 10px; cursor: pointer; border-bottom: 1px solid #f8f8f8; transition: background 0.15s; }
.wpp-contact-item:hover { background: #f0faf8; }
.wpp-contact-item.active { background: #e0f7f4; border-left: 3px solid var(--primary); }
.wpp-avatar-sm { width: 38px; height: 38px; border-radius: 50%; background: #e0e0e0; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; color: #555; flex-shrink: 0; font-weight: 600; }
.wpp-avatar-sm.group-avatar { background: #c8e6c9; color: #2e7d32; }
.wpp-avatar-lg { width: 70px; height: 70px; border-radius: 50%; background: #e0e0e0; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: #555; font-weight: 600; }
.wpp-contact-info { flex: 1; min-width: 0; }
.wpp-contact-name { font-size: 0.83rem; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.wpp-contact-last { font-size: 0.7rem; color: #888; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.wpp-contact-tags { display: flex; flex-wrap: wrap; gap: 3px; margin-top: 3px; }
.wpp-tag-badge { font-size: 0.6rem; background: #e0f7f4; color: #00997D; border: 1px solid #b2f2e8; border-radius: 10px; padding: 1px 7px; line-height: 1.4; }
.wpp-crm-badge { font-size: 0.6rem; background: #ede7f6; color: #5e35b1; border: 1px solid #d1c4e9; border-radius: 10px; padding: 1px 7px; line-height: 1.4; }
.wpp-contact-meta { text-align: right; flex-shrink: 0; }
.wpp-contact-time { font-size: 0.62rem; color: #999; }
.wpp-unread { background: var(--primary); color: #fff; font-size: 0.6rem; padding: 2px 6px; border-radius: 10px; display: inline-block; margin-top: 2px; }
.wpp-status-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 4px; }
.wpp-status-dot.em_atendimento { background: #ff9800; }
.wpp-status-dot.aguardando { background: #f44336; }
.wpp-status-dot.concluido { background: #4caf50; }
.wpp-status-dot.novo { background: #2196f3; }
.wpp-chat-panel { flex: 1; display: flex; flex-direction: column; background: #efeae2; position: relative; min-width: 0; }
.wpp-chat-empty { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; }
.wpp-chat-header { padding: 10px 16px; background: #fff; border-bottom: 1px solid #e9ecef; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; }
.wpp-messages { flex: 1; overflow-y: auto; padding: 16px; display: flex; flex-direction: column; gap: 4px; min-height: 0; }
.wpp-input-area { padding: 10px 16px; background: #fff; border-top: 1px solid #e9ecef; display: flex; gap: 8px; align-items: flex-end; flex-shrink: 0; }
.wpp-input-area textarea {
    resize: none;
    min-height: 34px;
    max-height: 120px;
    overflow-y: auto;
    line-height: 1.4;
    font-size: 0.85rem;
}
.wpp-detail-panel { width: 320px; min-width: 320px; background: #fff; border-left: 1px solid #e9ecef; display: none; flex-direction: column; overflow-y: auto; }
.wpp-detail-panel.open { display: flex; }
.wpp-detail-header { padding: 12px 16px; border-bottom: 1px solid #e9ecef; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; }
.wpp-detail-body { padding: 16px; overflow-y: auto; flex: 1; }
.wpp-msg { max-width: 65%; padding: 8px 12px; border-radius: 8px; font-size: 0.84rem; word-wrap: break-word; position: relative; }
.wpp-msg.mine { align-self: flex-end; background: #d9fdd3; border-bottom-right-radius: 2px; }
.wpp-msg.other { align-self: flex-start; background: #fff; border-bottom-left-radius: 2px; box-shadow: 0 1px 2px rgba(0,0,0,0.06); }
.wpp-msg-sender { font-size: 0.7rem; font-weight: 600; color: #075e54; margin-bottom: 2px; }
.wpp-msg-time { font-size: 0.62rem; color: #888; margin-top: 3px; text-align: right; }
.wpp-ack { color: #8a8a8a; font-size: 0.85rem; margin-left: 2px; }
.wpp-ack-read { color: #34b7f1; }
/* Dois checks sobrepostos (entregue/lida) */
.wpp-double-check { position: relative; display: inline-block; width: 1.15em; height: 1em; margin-left: 3px; vertical-align: middle; }
.wpp-double-check i { position: absolute; top: 0; font-size: 0.85rem; color: #8a8a8a; }
.wpp-double-check i:first-child { left: 0; }
.wpp-double-check i:last-child { left: 0.32em; }
.wpp-double-check.wpp-ack-read i { color: #34b7f1; }
.wpp-msg { position: relative; }
.wpp-reaction-badge {
    position: absolute;
    bottom: -10px;
    right: 8px;
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 12px;
    padding: 0 5px;
    font-size: 0.85rem;
    line-height: 1.4;
    box-shadow: 0 1px 3px rgba(0,0,0,0.15);
}
.wpp-msg.mine .wpp-reaction-badge { right: auto; left: 8px; }
/* Barra minimizada de prévia do arquivo (acima da caixa de texto) */
.wpp-media-bar {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 12px;
    background: #f5f6f6;
    border-top: 1px solid #e0e0e0;
    flex-shrink: 0;
}
.wpp-media-bar-thumb {
    width: 44px; height: 44px;
    border-radius: 6px;
    background: #fff;
    border: 1px solid #e0e0e0;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    overflow: hidden;
}
/* Spinner sobre a imagem em upload */
.wpp-upload-spinner {
    position: absolute; inset: 0;
    display: flex; align-items: center; justify-content: center;
}
/* Autocomplete de respostas rápidas */
.wpp-quick-suggest {
    position: absolute;
    bottom: 52px;
    left: 8px;
    right: 8px;
    max-height: 220px;
    overflow-y: auto;
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 10px;
    box-shadow: 0 6px 24px rgba(0,0,0,0.15);
    z-index: 35;
}
.wpp-quick-item { padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #f0f0f0; }
.wpp-quick-item:last-child { border-bottom: none; }
.wpp-quick-item.active, .wpp-quick-item:hover { background: #e0f7f4; }
.wpp-quick-item .qr-sc { font-weight: 600; color: #00997D; font-size: 0.8rem; }
.wpp-quick-item .qr-msg { font-size: 0.78rem; color: #666; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.wpp-doc {
    min-width: 260px;
    max-width: 320px;
    background: #fff;
    border-radius: 12px;
    padding: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
}
.wpp-doc-head { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
.wpp-doc-icon {
    flex-shrink: 0;
    width: 42px; height: 42px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.35rem;
}
.wpp-doc-meta { min-width: 0; flex: 1; }
.wpp-doc-name {
    font-size: 0.84rem; font-weight: 600; color: #1f2937; line-height: 1.25;
    word-break: break-word;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.wpp-doc-type { font-size: 0.72rem; color: #9ca3af; margin-top: 2px; }
.wpp-doc-actions { display: flex; gap: 8px; }
.wpp-doc-btn-view {
    background: var(--primary); border: 1px solid var(--primary); color: #fff;
    font-size: 0.76rem; font-weight: 500; padding: 5px 14px; border-radius: 8px;
    display: inline-flex; align-items: center; gap: 5px;
}
.wpp-doc-btn-view:hover { background: var(--primary-dark); border-color: var(--primary-dark); color: #fff; }
.wpp-doc-btn-download {
    background: #fff; border: 1px solid #d1d5db; color: #374151;
    font-size: 0.76rem; font-weight: 500; padding: 5px 14px; border-radius: 8px;
    display: inline-flex; align-items: center; gap: 5px;
}
.wpp-doc-btn-download:hover { background: #f3f4f6; color: #111827; }
.wpp-lightbox {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.85);
    z-index: 2000;
    align-items: center;
    justify-content: center;
    cursor: zoom-out;
}
.wpp-lightbox.open { display: flex; }
.wpp-lightbox img {
    max-width: 90vw;
    max-height: 90vh;
    border-radius: 8px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.5);
    cursor: default;
}
.wpp-lightbox-close {
    position: absolute;
    top: 18px;
    right: 28px;
    color: #fff;
    font-size: 2.4rem;
    line-height: 1;
    cursor: pointer;
    font-weight: 300;
}
.wpp-transcription { margin-top: 5px; font-size: 0.78rem; color: #444; background: rgba(0,0,0,0.04); border-radius: 6px; padding: 5px 8px; font-style: italic; }
.wpp-transcription-action { margin-top: 6px; }
.btn-transcribe {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.76rem;
    font-weight: 600;
    color: var(--primary-dark);
    background: var(--primary-50);
    border: 1px solid var(--primary-light);
    border-radius: 20px;
    padding: 4px 12px;
    line-height: 1;
    cursor: pointer;
    transition: all 0.18s ease;
}
.btn-transcribe:hover { background: var(--primary); border-color: var(--primary); color: #fff; }
.btn-transcribe:disabled { opacity: 0.7; cursor: default; }
.btn-transcribe i { font-size: 1rem; }
.qr-message-auto { resize: none; overflow-y: auto; min-height: 34px; max-height: 200px; line-height: 1.4; }

/* ===== Player de áudio customizado ===== */
.wpp-audio { display: flex; align-items: center; gap: 10px; min-width: 230px; max-width: 290px; padding: 2px 0; }
.wpp-audio audio { display: none; }
.wpp-audio-play {
    flex-shrink: 0;
    width: 34px; height: 34px;
    border-radius: 50%;
    border: none;
    background: #1f2937;
    color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem;
    cursor: pointer;
    transition: background 0.15s, transform 0.1s;
}
.wpp-audio-play:hover { background: #111827; }
.wpp-audio-play:active { transform: scale(0.94); }
.wpp-audio-main { flex: 1; min-width: 0; }
.wpp-wave { position: relative; height: 28px; cursor: pointer; }
.wpp-wave-bars { display: flex; align-items: center; gap: 2px; height: 100%; width: 100%; }
.wpp-wave-bar { flex: 1; min-width: 2px; background: #cbd5e1; border-radius: 2px; }
.wpp-wave-fill {
    position: absolute; top: 0; left: 0; height: 100%; width: 100%;
    pointer-events: none;
    will-change: clip-path;
}
.wpp-wave-fill .wpp-wave-bar { background: var(--primary); }
.wpp-audio-times { display: flex; justify-content: space-between; font-size: 0.66rem; color: #6b7280; margin-top: 1px; }
.wpp-audio-vol { color: #4b5563; font-size: 0.95rem; flex-shrink: 0; }
.wpp-emoji-picker {
    position: absolute;
    bottom: 52px;
    left: 8px;
    right: 8px;
    width: auto;
    max-height: 240px;
    overflow-y: auto;
    overflow-x: hidden;
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 10px;
    box-shadow: 0 6px 24px rgba(0,0,0,0.15);
    padding: 8px;
    z-index: 30;
    box-sizing: border-box;
}
.wpp-emoji-cat-title { font-size: 0.68rem; color: #999; text-transform: uppercase; margin: 6px 2px 2px; font-weight: 600; }
.wpp-emoji-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(34px, 1fr));
    gap: 2px;
    width: 100%;
}
.wpp-emoji-grid button {
    border: none; background: transparent; font-size: 1.25rem; line-height: 1;
    padding: 4px; border-radius: 6px; cursor: pointer; width: 100%;
    display: flex; align-items: center; justify-content: center;
}
.wpp-emoji-grid button:hover { background: #f0f0f0; }
.wpp-msg-media img { max-width: 220px; border-radius: 6px; cursor: pointer; }
.wpp-mention { color: #075e54; font-weight: 600; background: rgba(7,94,84,0.08); padding: 1px 4px; border-radius: 4px; cursor: default; }
.wpp-label-badge { font-size: 0.65rem; padding: 2px 8px; border-radius: 10px; color: #fff; display: inline-block; cursor: default; }
.cursor-pointer { cursor: pointer; }
@media (max-width: 992px) {
    .wpp-main-override { margin-left: 0 !important; left: 0 !important; }
}
@media (max-width: 768px) {
    .wpp-contacts-panel { width: 100%; min-width: 100%; }
    .wpp-chat-panel { display: none; }
    .wpp-chat-panel.active { display: flex; width: 100%; }
    .wpp-detail-panel { position: absolute; right: 0; top: 0; bottom: 0; z-index: 10; width: 100%; }
    .mobile-topbar { display: flex !important; }
}
</style>

<script>
const BASE = '<?= baseUrl("") ?>';
const CURRENT_USER_ID = <?= intval($user['id'] ?? 0) ?>;
const CURRENT_USER_NAME = '<?= escape($user['name'] ?? '') ?>';
let activeContactId = <?= $activeContactId ? intval($activeContactId) : 'null' ?>;
let activeContactAssignedTo = null; // dono atual do contato aberto (para atribuição automática no envio)
let activeContactIsGroup = false;
let pollInterval = null;
let contactsPollInterval = null;
let lastMessageId = 0;
let renderedMessageIds = new Set(); // Para evitar duplicação de mensagens
let isSending = false; // Flag para bloquear polling durante envio
let currentTab = 'contacts';
let allContacts = [];
let allGroups = [];

// =========================================
// FORMATAÇÃO WHATSAPP — *bold* _italic_ ~strike~ ```mono``` + @menções
// =========================================
function formatWhatsApp(text) {
    if (!text) return '';
    let html = escapeHtml(text);
    // Monospace ```text```
    html = html.replace(/```([\s\S]+?)```/g, '<code>$1</code>');
    // Bold *text*
    html = html.replace(/\*((?!\s)([^\n*]+?)(?<!\s))\*/g, '<strong>$1</strong>');
    // Italic _text_
    html = html.replace(/_((?!\s)([^\n_]+?)(?<!\s))_/g, '<em>$1</em>');
    // Strikethrough ~text~
    html = html.replace(/~((?!\s)([^\n~]+?)(?<!\s))~/g, '<del>$1</del>');
    // Menções @número — transforma em badge azul
    html = html.replace(/@(\d{10,15})/g, function(match, number) {
        // Tentar resolver o nome a partir dos contatos carregados
        const resolved = resolveMention(number);
        return `<span class="wpp-mention">@${resolved}</span>`;
    });
    // Newlines
    html = html.replace(/\n/g, '<br>');
    return html;
}

/**
 * Tenta resolver um número de menção para o nome do contato
 */
function resolveMention(number) {
    // Buscar nos contatos carregados
    const allItems = [...allContacts, ...allGroups];
    for (const c of allItems) {
        if (c.phone && c.phone.replace(/\D/g, '').includes(number.replace(/\D/g, ''))) {
            return c.contact_name || c.push_name || number;
        }
        if (c.remote_jid && c.remote_jid.includes(number)) {
            return c.contact_name || c.push_name || number;
        }
    }
    // Buscar no cache de menções (preenchido pelo poll)
    if (mentionCache[number]) return mentionCache[number];
    return number;
}

// Cache para menções resolvidas (preenchido ao abrir grupo)
let mentionCache = {};

// =========================================
// TABS
// =========================================
function switchTab(tab) {
    currentTab = tab;
    document.querySelectorAll('.wpp-tab').forEach(t => t.classList.remove('active'));
    document.querySelector(`.wpp-tab[data-tab="${tab}"]`).classList.add('active');
    renderContactsList();
}

// =========================================
// CONTATOS
// =========================================
function loadContacts(silent = false) {
    const search = document.getElementById('contact-search').value;
    const assigned = document.getElementById('filter-assigned').value;
    const label = document.getElementById('filter-label').value;
    const status = document.getElementById('filter-status').value;

    let url = BASE + 'whatsapp/contacts?type=all';
    if (search) url += '&search=' + encodeURIComponent(search);
    if (assigned) url += '&assigned_to=' + encodeURIComponent(assigned);
    if (label) url += '&label_id=' + encodeURIComponent(label);
    if (status) url += '&service_status=' + encodeURIComponent(status);

    fetch(url, { headers: {'X-Requested-With': 'XMLHttpRequest'} })
    .then(r => r.json())
    .then(data => {
        allContacts = data.contacts || [];
        allGroups = data.groups || [];
        document.getElementById('count-contacts').textContent = allContacts.length;
        document.getElementById('count-groups').textContent = allGroups.length;
        renderContactsList();
    })
    .catch(() => {});
}

function syncGroups(btn) {
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm" style="width:0.75rem;height:0.75rem;"></span>'; }
    fetch(BASE + 'whatsapp/syncGroups', { method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'} })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                loadContacts();
                if (typeof showToast === 'function') showToast(`${data.updated} grupo(s) atualizado(s).`);
            } else if (typeof showToast === 'function') {
                showToast(data.message || 'Falha ao sincronizar.');
            }
        })
        .catch(() => {})
        .finally(() => {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-arrow-repeat" style="font-size:0.75rem;"></i>'; }
        });
}

function renderContactsList() {
    const list = document.getElementById('contacts-list');
    const items = currentTab === 'contacts' ? allContacts : allGroups;

    if (!items.length) {
        list.innerHTML = `<div class="text-center py-4 text-muted small">Nenhum ${currentTab === 'contacts' ? 'contato' : 'grupo'} encontrado</div>`;
        return;
    }

    let html = '';
    if (currentTab === 'contacts') {
        const emAtendimento = items.filter(c => c.service_status === 'em_atendimento');
        const aguardando = items.filter(c => c.service_status === 'aguardando');
        const novos = items.filter(c => c.service_status === 'novo' || !c.service_status);
        const concluidos = items.filter(c => c.service_status === 'concluido');

        if (emAtendimento.length) {
            html += '<div class="wpp-section-header"><span class="wpp-status-dot em_atendimento"></span>Em Atendimento (' + emAtendimento.length + ')</div>';
            html += emAtendimento.map(c => renderContactItem(c, false)).join('');
        }
        if (aguardando.length) {
            html += '<div class="wpp-section-header"><span class="wpp-status-dot aguardando"></span>Aguardando (' + aguardando.length + ')</div>';
            html += aguardando.map(c => renderContactItem(c, false)).join('');
        }
        if (novos.length) {
            html += '<div class="wpp-section-header"><span class="wpp-status-dot novo"></span>Novos (' + novos.length + ')</div>';
            html += novos.map(c => renderContactItem(c, false)).join('');
        }
        if (concluidos.length) {
            html += '<div class="wpp-section-header"><span class="wpp-status-dot concluido"></span>Concluídos (' + concluidos.length + ')</div>';
            html += concluidos.map(c => renderContactItem(c, false)).join('');
        }
    } else {
        html += items.map(c => renderContactItem(c, true)).join('');
    }
    list.innerHTML = html;
}

function renderContactItem(c, isGroup) {
    // Grupo usa sempre o nome real do grupo (contact_name); nunca o push_name (remetente)
    const name = isGroup
        ? (c.contact_name || c.phone || 'Grupo')
        : (c.contact_name || c.push_name || c.phone || 'Desconhecido');
    const initials = name.substring(0, 2).toUpperCase();
    const time = c.last_message_at ? formatTime(c.last_message_at) : '';
    const isActive = activeContactId == c.id ? 'active' : '';
    const unread = c.unread_count > 0 ? `<span class="wpp-unread">${c.unread_count}</span>` : '';
    const avatarClass = isGroup ? 'wpp-avatar-sm group-avatar' : 'wpp-avatar-sm';
    let icon = isGroup ? '<i class="bi bi-people-fill" style="font-size:0.9rem;"></i>' : initials;
    // Foto de perfil quando disponível
    if (c.profile_picture_url) {
        icon = `<img src="${escapeHtml(c.profile_picture_url)}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;" onerror="this.parentNode.textContent='${isGroup ? '' : escapeHtml(initials)}'">`;
    }

    // Prévia da última mensagem (estilo WhatsApp)
    const preview = buildLastMessagePreview(c, isGroup);

    // Etiquetas do lead (quando houver)
    let labelsHtml = '';
    if (c.labels) {
        const labelNames = String(c.labels).split(',').map(s => s.trim()).filter(Boolean);
        labelsHtml = labelNames.map(l => `<span class="wpp-tag-badge">${escapeHtml(l)}</span>`).join('');
    }

    // Localização no CRM (board > coluna), quando o contato está em algum board
    let crmHtml = '';
    if (c.crm_board_name) {
        crmHtml = `<span class="wpp-crm-badge"><i class="bi bi-kanban"></i> ${escapeHtml(c.crm_board_name)}${c.crm_column_name ? ' › ' + escapeHtml(c.crm_column_name) : ''}</span>`;
    }

    const metaTags = (labelsHtml || crmHtml)
        ? `<div class="wpp-contact-tags">${labelsHtml}${crmHtml}</div>`
        : '';

    return `<div class="wpp-contact-item ${isActive}" onclick="openChat(${c.id}, ${isGroup})" data-id="${c.id}">
        <div class="${avatarClass}">${icon}</div>
        <div class="wpp-contact-info">
            <div class="wpp-contact-name">${escapeHtml(name)}</div>
            <div class="wpp-contact-last">${preview}</div>
            ${metaTags}
        </div>
        <div class="wpp-contact-meta">
            <div class="wpp-contact-time">${time}</div>
            ${unread}
        </div>
    </div>`;
}

// Monta a prévia resumida da última mensagem, como no WhatsApp
function buildLastMessagePreview(c, isGroup) {
    const typeLabels = {
        image: '📷 Foto',
        audio: '🎤 Áudio',
        video: '🎥 Vídeo',
        document: '📄 Documento',
        sticker: '🎨 Figurinha',
    };

    let text = '';
    if (c.last_message_type && c.last_message_type !== 'text' && typeLabels[c.last_message_type]) {
        text = typeLabels[c.last_message_type];
    } else if (c.last_message_text) {
        text = c.last_message_text;
    }

    if (!text) {
        // Sem mensagem — mostra o atendente atribuído, se houver
        return c.assigned_name ? `<small style="color:var(--primary);">${escapeHtml(c.assigned_name)}</small>` : '';
    }

    // Prefixo do remetente
    let prefix = '';
    if (c.last_message_from_me == 1) {
        prefix = 'Você: ';
    } else if (isGroup && c.last_message_sender) {
        // Em grupos, mostra quem enviou (primeiro nome)
        prefix = escapeHtml(c.last_message_sender.split(' ')[0]) + ': ';
    }

    // Resumir a mensagem
    let clean = text.replace(/\s+/g, ' ').trim();
    if (clean.length > 38) clean = clean.substring(0, 38) + '…';

    return `<span class="wpp-last-preview">${prefix}${escapeHtml(clean)}</span>`;
}

// =========================================
// CHAT
// =========================================
function openChat(contactId, isGroup = false) {
    activeContactId = contactId;
    activeContactIsGroup = isGroup;

    document.querySelectorAll('.wpp-contact-item').forEach(el => el.classList.remove('active'));
    const active = document.querySelector(`.wpp-contact-item[data-id="${contactId}"]`);
    if (active) active.classList.add('active');

    document.getElementById('chat-empty').style.display = 'none';
    document.getElementById('chat-header').style.display = 'flex';
    document.getElementById('messages-area').style.display = 'flex';
    document.getElementById('input-area').style.display = 'flex';

    document.getElementById('messages-area').innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></div>';
    lastMessageId = 0;

    fetch(BASE + 'whatsapp/messages/' + contactId, { headers: {'X-Requested-With': 'XMLHttpRequest'} })
    .then(r => r.json())
    .then(messages => {
        renderMessages(messages);
        scrollToBottom();
        // Scroll novamente após imagens/mídias carregarem
        setTimeout(scrollToBottom, 300);
        setTimeout(scrollToBottom, 800);
        // Também scrollar quando imagens terminarem de carregar
        document.querySelectorAll('#messages-area img, #messages-area video').forEach(el => {
            el.addEventListener('load', scrollToBottom);
            el.addEventListener('loadeddata', scrollToBottom);
        });
        startPolling();
    });

    fetch(BASE + 'whatsapp/contactDetail/' + contactId, { headers: {'X-Requested-With': 'XMLHttpRequest'} })
    .then(r => r.json())
    .then(contact => {
        // Grupo usa o nome real do grupo (contact_name), nunca o push_name do remetente
        const name = (contact.is_group == 1)
            ? (contact.contact_name || contact.phone || 'Grupo')
            : (contact.contact_name || contact.push_name || contact.phone || 'Desconhecido');
        const initials = name.substring(0, 2).toUpperCase();
        document.getElementById('chat-contact-name').textContent = name;
        document.getElementById('chat-contact-phone').textContent = contact.phone || '';
        const chatAvatar = document.getElementById('chat-avatar');
        if (contact.profile_picture_url) {
            chatAvatar.innerHTML = `<img src="${escapeHtml(contact.profile_picture_url)}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;" onerror="this.parentNode.textContent='${escapeHtml(initials)}'">`;
        } else {
            chatAvatar.textContent = initials;
        }
        document.getElementById('chat-service-status').value = contact.service_status || 'novo';
        document.getElementById('detail-name').textContent = name;
        document.getElementById('detail-phone').textContent = contact.phone || '';
        const detailAvatar = document.getElementById('detail-avatar');
        if (contact.profile_picture_url) {
            detailAvatar.innerHTML = `<img src="${escapeHtml(contact.profile_picture_url)}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;" onerror="this.parentNode.textContent='${escapeHtml(initials)}'">`;
        } else {
            detailAvatar.textContent = initials;
        }
        document.getElementById('detail-name-input').value = contact.contact_name || '';
        document.getElementById('detail-assigned').value = contact.assigned_to || '';
        document.getElementById('detail-notes').value = contact.internal_notes || '';
        activeContactAssignedTo = contact.assigned_to || null;
        renderContactLabels(contact.labels || []);
    });

    loadCrmBoards();
}

function renderMessages(messages) {
    const area = document.getElementById('messages-area');
    if (!messages.length) {
        area.innerHTML = '<div class="text-center text-muted small py-4">Nenhuma mensagem ainda</div>';
        return;
    }
    // Limpar set e popular com IDs carregados
    renderedMessageIds = new Set();
    messages.forEach(m => {
        if (m.participant_jid && m.sender_name) {
            const num = m.participant_jid.replace(/@.*/, '');
            mentionCache[num] = m.sender_name;
        }
    });
    // Renderiza mensagens normais; reações são anexadas às mensagens reagidas
    area.innerHTML = messages.map(m => {
        lastMessageId = Math.max(lastMessageId, m.id);
        renderedMessageIds.add(m.id);
        if (m.message_type === 'reaction') return '';
        return renderSingleMessage(m);
    }).join('');

    // Aplicar reações sobre as bolhas correspondentes
    messages.forEach(m => {
        if (m.message_type === 'reaction') applyReaction(m);
    });
}

// Anexa a reação (emoji) na bolha da mensagem reagida
function applyReaction(m) {
    if (!m.quoted_message_id) return;
    const bubble = document.querySelector(`.wpp-msg[data-wa-id="${cssEscape(m.quoted_message_id)}"]`);
    if (!bubble) return;
    let badge = bubble.querySelector('.wpp-reaction-badge');
    if (!badge) {
        badge = document.createElement('span');
        badge.className = 'wpp-reaction-badge';
        bubble.appendChild(badge);
    }
    badge.textContent = m.message_text || '❤️';
}

function cssEscape(s) {
    return String(s).replace(/["\\]/g, '\\$&');
}

function renderSingleMessage(m) {
    const cls = m.from_me == 1 ? 'mine' : 'other';
    let content = '';

    // Mostrar nome do remetente em grupos (mensagens de outros)
    if (activeContactIsGroup && m.from_me != 1 && m.sender_name) {
        content += `<div class="wpp-msg-sender">${escapeHtml(m.sender_name)}</div>`;
    }

    if (m.message_type === 'reaction') {
        // Reações não são renderizadas como bolha; são anexadas via applyReaction()
        return '';
    } else if (m.message_type === 'sticker' && m.media_url) {
        content += `<div class="wpp-msg-media"><img src="${BASE + m.media_url}" style="max-width:130px;cursor:pointer;" onclick="openLightbox(this.src)"></div>`;
    } else if (m.message_type === 'sticker') {
        content += `<span class="text-muted"><i class="bi bi-emoji-smile"></i> Figurinha</span>`;
    } else if (m.message_type === 'image' && m.media_url) {
        content += `<div class="wpp-msg-media"><img src="${BASE + m.media_url}" style="cursor:pointer;" onclick="openLightbox(this.src)"></div>`;
        if (m.message_text) content += `<div>${formatWhatsApp(m.message_text)}</div>`;
    } else if (m.message_type === 'audio' && m.media_url) {
        content += renderAudioPlayer(m);
        if (m.transcription) {
            content += `<div class="wpp-transcription"><i class="bi bi-quote"></i> ${escapeHtml(m.transcription)}</div>`;
        } else {
            content += `<div class="wpp-transcription-action"><button class="btn-transcribe" onclick="transcribeAudio(${m.id}, this)"><i class="bi bi-soundwave"></i> Transcrever áudio</button></div>`;
        }
    } else if (m.message_type === 'document' && m.media_url) {
        const fileUrl = BASE + m.media_url;
        const fileName = m.media_filename || 'Documento';
        content += renderDocument(fileUrl, fileName);
        if (m.message_text) content += `<div class="mt-1">${formatWhatsApp(m.message_text)}</div>`;
    } else if (m.message_type === 'video' && m.media_url) {
        content += `<video controls src="${BASE + m.media_url}" style="max-width:220px;border-radius:6px;"></video>`;
        if (m.message_text) content += `<div class="mt-1">${formatWhatsApp(m.message_text)}</div>`;
    } else {
        content += formatWhatsApp(m.message_text || '');
    }

    // Horário com formato HH:MM
    const time = m.timestamp ? formatFullTime(m.timestamp) : '';
    // Checkzinho de status (apenas mensagens enviadas por nós)
    let ack = '';
    if (m.from_me == 1) {
        const initAck = m.ack_status || 'sent';
        ack = ` <span class="wpp-msg-ack-holder" data-ack="${initAck}">${renderAckIcon(initAck)}</span>`;
    }
    const waId = m.message_id ? ` data-wa-id="${escapeHtml(m.message_id)}"` : '';
    return `<div class="wpp-msg ${cls}" data-msg-id="${m.id}"${waId}><div class="wpp-msg-body">${content}</div><div class="wpp-msg-time">${time}${ack}</div></div>`;
}

// === LIGHTBOX DE IMAGEM ===
function openLightbox(src) {
    const box = document.getElementById('img-lightbox');
    document.getElementById('img-lightbox-img').src = src;
    box.classList.add('open');
}
function closeLightbox(e) {
    // Fecha ao clicar no fundo, no X, ou em qualquer lugar exceto na própria imagem
    if (e && e.target && e.target.id === 'img-lightbox-img') return;
    document.getElementById('img-lightbox').classList.remove('open');
    document.getElementById('img-lightbox-img').src = '';
}
// Fecha com ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const box = document.getElementById('img-lightbox');
        if (box && box.classList.contains('open')) closeLightbox();
    }
});

// ===== Card de documento/arquivo =====
function docMeta(ext) {
    ext = (ext || '').toLowerCase();
    const map = {
        pdf:  { icon: 'bi-file-earmark-pdf',        color: '#e53935', label: 'Documento PDF' },
        doc:  { icon: 'bi-file-earmark-word',       color: '#1565c0', label: 'Documento Word' },
        docx: { icon: 'bi-file-earmark-word',       color: '#1565c0', label: 'Documento Word' },
        xls:  { icon: 'bi-file-earmark-excel',      color: '#2e7d32', label: 'Planilha Excel' },
        xlsx: { icon: 'bi-file-earmark-excel',      color: '#2e7d32', label: 'Planilha Excel' },
        csv:  { icon: 'bi-file-earmark-spreadsheet',color: '#2e7d32', label: 'Planilha CSV' },
        ppt:  { icon: 'bi-file-earmark-ppt',        color: '#e64a19', label: 'Apresentação' },
        pptx: { icon: 'bi-file-earmark-ppt',        color: '#e64a19', label: 'Apresentação' },
        zip:  { icon: 'bi-file-earmark-zip',        color: '#795548', label: 'Arquivo compactado' },
        rar:  { icon: 'bi-file-earmark-zip',        color: '#795548', label: 'Arquivo compactado' },
        txt:  { icon: 'bi-file-earmark-text',       color: '#546e7a', label: 'Texto' },
    };
    return map[ext] || { icon: 'bi-file-earmark', color: '#546e7a', label: 'Arquivo' };
}

function renderDocument(fileUrl, fileName) {
    const ext = (fileName.split('.').pop() || '').toLowerCase();
    const meta = docMeta(ext);
    return `<div class="wpp-doc">
        <div class="wpp-doc-head">
            <div class="wpp-doc-icon" style="background:${meta.color}1a;color:${meta.color};"><i class="bi ${meta.icon}"></i></div>
            <div class="wpp-doc-meta">
                <div class="wpp-doc-name" title="${escapeHtml(fileName)}">${escapeHtml(fileName)}</div>
                <div class="wpp-doc-type">${meta.label}</div>
            </div>
        </div>
        <div class="wpp-doc-actions">
            <a href="${fileUrl}" target="_blank" rel="noopener" class="btn btn-sm wpp-doc-btn-view"><i class="bi bi-eye"></i> Ver</a>
            <a href="${fileUrl}" download="${escapeHtml(fileName)}" class="btn btn-sm wpp-doc-btn-download"><i class="bi bi-download"></i> Baixar</a>
        </div>
    </div>`;
}

// ===== Player de áudio customizado (waveform) =====
function renderAudioPlayer(m) {
    const src = BASE + m.media_url;
    const pid = 'aud-' + m.id;
    // Barras da waveform (alturas pseudo-aleatórias porém estáveis por id)
    let bars = '';
    let seed = parseInt(m.id, 10) || 1;
    for (let i = 0; i < 40; i++) {
        seed = (seed * 9301 + 49297) % 233280;
        const h = 20 + Math.floor((seed / 233280) * 80); // 20% a 100%
        bars += `<span class="wpp-wave-bar" style="height:${h}%"></span>`;
    }
    return `<div class="wpp-audio" id="${pid}">
        <audio src="${src}" preload="metadata"
            onloadedmetadata="wppAudioMeta('${pid}')"
            onended="wppAudioEnded('${pid}')"></audio>
        <button type="button" class="wpp-audio-play" onclick="wppAudioToggle('${pid}')"><i class="bi bi-play-fill"></i></button>
        <div class="wpp-audio-main">
            <div class="wpp-wave" onclick="wppAudioSeek(event,'${pid}')">
                <div class="wpp-wave-bars">${bars}</div>
                <div class="wpp-wave-fill" style="clip-path:inset(0 100% 0 0)">
                    <div class="wpp-wave-bars">${bars}</div>
                </div>
            </div>
            <div class="wpp-audio-times">
                <span class="wpp-audio-cur">0:00</span>
                <span class="wpp-audio-dur">0:00</span>
            </div>
        </div>
        <i class="bi bi-volume-up wpp-audio-vol"></i>
    </div>`;
}

function wppFmtTime(s) {
    if (!isFinite(s) || s < 0) s = 0;
    const m = Math.floor(s / 60);
    const sec = Math.floor(s % 60);
    return m + ':' + (sec < 10 ? '0' : '') + sec;
}

let wppRafId = null;

// Cacheia as referências de um player para evitar buscas de DOM durante a reprodução
function wppAudioRefs(wrap) {
    if (!wrap._refs) {
        wrap._refs = {
            audio: wrap.querySelector('audio'),
            icon: wrap.querySelector('.wpp-audio-play i'),
            fill: wrap.querySelector('.wpp-wave-fill'),
            cur: wrap.querySelector('.wpp-audio-cur'),
            dur: wrap.querySelector('.wpp-audio-dur'),
            wave: wrap.querySelector('.wpp-wave'),
        };
    }
    return wrap._refs;
}

function wppAudioToggle(pid) {
    const wrap = document.getElementById(pid);
    if (!wrap) return;
    const r = wppAudioRefs(wrap);
    // Pausa qualquer outro áudio tocando
    document.querySelectorAll('.wpp-audio').forEach(w => {
        if (w !== wrap) {
            const a = w.querySelector('audio');
            if (a && !a.paused) a.pause();
            w.classList.remove('playing');
            const ic = w.querySelector('.wpp-audio-play i');
            if (ic) ic.className = 'bi bi-play-fill';
        }
    });
    if (r.audio.paused) {
        r.audio.playbackRate = 1;
        r.audio.play();
        wrap.classList.add('playing');
        r.icon.className = 'bi bi-pause-fill';
        wppStartRaf(wrap);
    } else {
        r.audio.pause();
        wrap.classList.remove('playing');
        r.icon.className = 'bi bi-play-fill';
        wppStopRaf();
    }
}

// Atualiza a barra de progresso via requestAnimationFrame (não bloqueia o áudio)
function wppStartRaf(wrap) {
    wppStopRaf();
    const r = wppAudioRefs(wrap);
    let lastSec = -1;
    const tick = () => {
        if (r.audio.paused) { wppRafId = null; return; }
        const pct = r.audio.duration ? (r.audio.currentTime / r.audio.duration) * 100 : 0;
        r.fill.style.clipPath = 'inset(0 ' + (100 - pct) + '% 0 0)';
        const sec = Math.floor(r.audio.currentTime);
        if (sec !== lastSec) { r.cur.textContent = wppFmtTime(r.audio.currentTime); lastSec = sec; }
        wppRafId = requestAnimationFrame(tick);
    };
    wppRafId = requestAnimationFrame(tick);
}

function wppStopRaf() {
    if (wppRafId) { cancelAnimationFrame(wppRafId); wppRafId = null; }
}

function wppAudioMeta(pid) {
    const wrap = document.getElementById(pid);
    if (!wrap) return;
    const r = wppAudioRefs(wrap);
    r.dur.textContent = wppFmtTime(r.audio.duration);
}

function wppAudioEnded(pid) {
    const wrap = document.getElementById(pid);
    if (!wrap) return;
    const r = wppAudioRefs(wrap);
    wrap.classList.remove('playing');
    r.icon.className = 'bi bi-play-fill';
    r.fill.style.clipPath = 'inset(0 100% 0 0)';
    r.cur.textContent = '0:00';
    wppStopRaf();
}

function wppAudioSeek(e, pid) {
    const wrap = document.getElementById(pid);
    if (!wrap) return;
    const r = wppAudioRefs(wrap);
    const rect = r.wave.getBoundingClientRect();
    const ratio = Math.min(Math.max((e.clientX - rect.left) / rect.width, 0), 1);
    if (r.audio.duration) {
        r.audio.currentTime = ratio * r.audio.duration;
        const pct = ratio * 100;
        r.fill.style.clipPath = 'inset(0 ' + (100 - pct) + '% 0 0)';
        r.cur.textContent = wppFmtTime(r.audio.currentTime);
    }
}

// Transcrever áudio recebido
function transcribeAudio(messageId, btn) {
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm" style="width:0.7rem;height:0.7rem;"></span> Transcrevendo...'; }
    fetch(BASE + 'whatsapp/transcribeAudio/' + messageId, { method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'} })
    .then(r => r.json())
    .then(data => {
        const holder = btn ? btn.closest('.wpp-transcription-action') : null;
        if (data.success && data.transcription) {
            const div = document.createElement('div');
            div.className = 'wpp-transcription';
            div.innerHTML = '<i class="bi bi-quote"></i> ' + escapeHtml(data.transcription);
            if (holder) holder.replaceWith(div);
        } else {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-soundwave"></i> Transcrever áudio'; }
            alert(data.error || 'Falha na transcrição.');
        }
    })
    .catch(() => {
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-soundwave"></i> Transcrever áudio'; }
    });
}

// Ícone de confirmação estilo WhatsApp
function renderAckIcon(status) {
    // Dois checks sobrepostos para visual claro de "entregue"/"lida"
    const doubleCheck = (cls, title) =>
        `<span class="wpp-double-check ${cls}" title="${title}"><i class="bi bi-check2"></i><i class="bi bi-check2"></i></span>`;
    switch (status) {
        case 'read':
            return doubleCheck('wpp-ack-read', 'Lida');
        case 'delivered':
            return doubleCheck('wpp-ack', 'Entregue');
        case 'sent':
            return '<i class="bi bi-check2 wpp-ack" title="Enviada"></i>';
        case 'failed':
            return '<i class="bi bi-exclamation-circle text-danger" title="Falha no envio"></i>';
        case 'pending':
        default:
            return '<i class="bi bi-clock wpp-ack" title="Enviando"></i>';
    }
}
</script>

<script>
// =========================================
// ENVIO — Shift+Enter = nova linha, Enter = enviar
// =========================================
// === GALERIA DE EMOJIS ===
const EMOJI_CATEGORIES = {
    'Sorrisos': ['😀','😁','😂','🤣','😊','😇','🙂','🙃','😉','😌','😍','🥰','😘','😗','😋','😛','😜','🤪','😝','🤗','🤭','🤫','🤔','😐','😑','😶','😏','😒','🙄','😬','😔','😪','😴','😷','🤒','🤕','🤢','🤮','🥵','🥶','😵','🤯','🤠','🥳','😎','🤓','🧐'],
    'Gestos': ['👍','👎','👌','✌️','🤞','🤟','🤙','👈','👉','👆','👇','☝️','✋','🤚','🖐️','🖖','👋','🤝','🙏','💪','🙌','👏','🤲','🤦','🤷','👐'],
    'Corações': ['❤️','🧡','💛','💚','💙','💜','🖤','🤍','🤎','💔','❣️','💕','💞','💓','💗','💖','💘','💝'],
    'Objetos': ['🔥','⭐','🌟','✨','⚡','💯','✅','❌','❗','❓','⚠️','🎉','🎊','🎁','📌','📎','📞','📱','💻','📧','📅','🕐','💰','💵','📈','📉','🤑'],
    'Diversos': ['🚀','🏆','🎯','👀','🗣️','💬','🙈','🙉','🙊','🐶','🐱','☀️','🌈','☕','🍕','🍔','🎵','⚽','🏠','🚗'],
};

let emojiPickerBuilt = false;
function buildEmojiPicker() {
    if (emojiPickerBuilt) return;
    const picker = document.getElementById('emoji-picker');
    let html = '';
    for (const cat in EMOJI_CATEGORIES) {
        html += `<div class="wpp-emoji-cat-title">${cat}</div><div class="wpp-emoji-grid">`;
        html += EMOJI_CATEGORIES[cat].map(e => `<button type="button" onclick="insertEmoji('${e}')">${e}</button>`).join('');
        html += '</div>';
    }
    picker.innerHTML = html;
    emojiPickerBuilt = true;
}

function toggleEmojiPicker() {
    const picker = document.getElementById('emoji-picker');
    if (picker.style.display === 'none' || !picker.style.display) {
        buildEmojiPicker();
        picker.style.display = 'block';
    } else {
        picker.style.display = 'none';
    }
}

function insertEmoji(emoji) {
    const input = document.getElementById('message-input');
    const start = input.selectionStart ?? input.value.length;
    const end = input.selectionEnd ?? input.value.length;
    input.value = input.value.slice(0, start) + emoji + input.value.slice(end);
    const pos = start + emoji.length;
    input.selectionStart = input.selectionEnd = pos;
    input.focus();
}

// Fechar o picker ao clicar fora
document.addEventListener('click', function(e) {
    const picker = document.getElementById('emoji-picker');
    const btn = document.getElementById('btn-emoji');
    if (picker && picker.style.display === 'block' && !picker.contains(e.target) && btn && !btn.contains(e.target)) {
        picker.style.display = 'none';
    }
});

// === RESPOSTAS RÁPIDAS (/atalho) ===
let quickReplies = [];
let quickSuggestIndex = -1;

function loadQuickReplies() {
    fetch(BASE + 'whatsapp/quickReplies', { headers: {'X-Requested-With': 'XMLHttpRequest'} })
    .then(r => r.json())
    .then(data => { quickReplies = data.replies || []; })
    .catch(() => {});
}

// Chamado a cada tecla no campo de mensagem
function handleQuickReplyInput() {
    const input = document.getElementById('message-input');
    const val = input.value;
    const box = document.getElementById('quick-reply-suggest');

    // Só ativa se começar com "/" e não tiver espaço/quebra ainda
    const match = val.match(/^\/(\S*)$/);
    if (!match) { box.style.display = 'none'; quickSuggestIndex = -1; return; }

    const term = match[1].toLowerCase();
    const matches = quickReplies.filter(q => q.shortcut.toLowerCase().includes(term));
    if (!matches.length) { box.style.display = 'none'; return; }

    box.innerHTML = matches.map((q, i) =>
        `<div class="wpp-quick-item ${i === 0 ? 'active' : ''}" data-idx="${i}" onclick="applyQuickReply(${q.id})">
            <div class="qr-sc">/${escapeHtml(q.shortcut)} ${q.attachment_url ? '<i class="bi bi-paperclip" title="Com anexo"></i>' : ''}</div>
            <div class="qr-msg">${escapeHtml(q.message) || '<span class="text-muted">(anexo)</span>'}</div>
        </div>`).join('');
    box._matches = matches;
    quickSuggestIndex = 0;
    box.style.display = 'block';
}

function applyQuickReply(id) {
    const q = quickReplies.find(x => x.id == id);
    if (!q) return;
    const input = document.getElementById('message-input');
    document.getElementById('quick-reply-suggest').style.display = 'none';
    quickSuggestIndex = -1;

    // Se tiver anexo, deixa em prévia (staging) para o usuário clicar em enviar
    if (q.attachment_url) {
        stageQuickReply(q);
        return;
    }

    input.value = q.message;
    autoGrowMessageInput();
    input.focus();
}

// Coloca a resposta rápida com anexo em prévia, aguardando o clique em enviar
function stageQuickReply(q) {
    if (!activeContactId) return;
    // Não permite misturar com um arquivo já anexado manualmente
    if (stagedFile) cancelStagedMedia();
    stagedQuickReply = q;

    const staging = document.getElementById('media-staging');
    const preview = document.getElementById('media-staging-preview');
    document.getElementById('media-staging-name').textContent = q.attachment_name || 'anexo';
    document.getElementById('media-staging-size').textContent = 'Resposta rápida';

    const ext = (q.attachment_name || q.attachment_url).split('.').pop().toLowerCase();
    if (['jpg','jpeg','png','gif','webp'].includes(ext)) {
        preview.innerHTML = `<img src="${q.attachment_url}" style="width:100%;height:100%;object-fit:cover;border-radius:6px;">`;
    } else {
        const dm = docMeta(ext);
        preview.innerHTML = `<i class="bi ${dm.icon}" style="font-size:1.6rem;color:${dm.color};"></i>`;
    }
    staging.style.display = 'flex';

    const input = document.getElementById('message-input');
    input.value = q.message || '';
    autoGrowMessageInput();
    input.placeholder = 'Adicione uma legenda (opcional)...';
    setTimeout(() => input.focus(), 100);
}

// Ajusta a altura do textarea de mensagem do chat conforme o conteúdo
function autoGrowMessageInput() {
    const input = document.getElementById('message-input');
    if (!input) return;
    input.style.height = '34px';
    input.style.height = Math.min(input.scrollHeight, 120) + 'px';
}

// Envia uma resposta rápida que possui anexo (arquivo já no servidor)
function sendQuickReplyWithAttachment(q) {
    if (!activeContactId || isSendingMedia) return;
    isSendingMedia = true;
    isSending = true;

    // Legenda vem do campo de texto (usuário pode ter editado a mensagem antes de enviar)
    const input = document.getElementById('message-input');
    const caption = applySignature((input.value || '').trim());
    const ext = (q.attachment_name || q.attachment_url).split('.').pop().toLowerCase();
    const isImg = ['jpg','jpeg','png','gif','webp'].includes(ext);

    // Limpa a prévia e o campo antes do envio otimista
    stagedQuickReply = null;
    document.getElementById('media-staging').style.display = 'none';
    document.getElementById('media-staging-preview').innerHTML = '';
    input.value = '';
    input.placeholder = 'Digite uma mensagem...';
    autoGrowMessageInput();

    const tempId = 'uploading-' + Date.now();
    const area = document.getElementById('messages-area');
    let inner = '';
    if (isImg) {
        inner = `<div class="wpp-msg-media" style="position:relative;">
            <img src="${q.attachment_url}" style="opacity:0.6;">
            <div class="wpp-upload-spinner"><span class="spinner-border text-light"></span></div>
        </div>`;
    } else {
        const dm = docMeta((q.attachment_name || '').split('.').pop());
        inner = `<div class="wpp-doc">
            <div class="wpp-doc-head">
                <div class="wpp-doc-icon" style="background:${dm.color}1a;color:${dm.color};"><i class="bi ${dm.icon}"></i></div>
                <div class="wpp-doc-meta"><div class="wpp-doc-name">${escapeHtml(q.attachment_name || 'arquivo')}</div><div class="wpp-doc-type">${dm.label}</div></div>
            </div>
            <div class="text-muted" style="font-size:0.72rem;"><span class="spinner-border spinner-border-sm"></span> Enviando...</div>
        </div>`;
    }
    if (caption) inner += `<div>${formatWhatsApp(caption)}</div>`;
    area.insertAdjacentHTML('beforeend', `<div class="wpp-msg mine" id="${tempId}"><div class="wpp-msg-body">${inner}</div><div class="wpp-msg-time"><i class="bi bi-clock wpp-ack"></i></div></div>`);
    scrollToBottom();

    const fd = new FormData();
    fd.append('contact_id', activeContactId);
    fd.append('reply_id', q.id);
    fd.append('caption', caption);

    fetch(BASE + 'whatsapp/sendQuickReply', { method: 'POST', body: fd, headers: {'X-Requested-With': 'XMLHttpRequest'} })
    .then(r => r.json())
    .then(data => {
        const temp = document.getElementById(tempId);
        if (data.success && data.message) {
            reflectAutoAssign();
            renderedMessageIds.add(data.message.id);
            lastMessageId = Math.max(lastMessageId, data.message.id || 0);
            if (temp) temp.outerHTML = renderSingleMessage(data.message);
            scrollToBottom();
        } else {
            if (temp) temp.querySelector('.wpp-msg-time').innerHTML = '<i class="bi bi-exclamation-circle text-danger" title="Falha"></i>';
            alert(data.error || 'Erro ao enviar anexo.');
        }
    })
    .catch(() => {
        const temp = document.getElementById(tempId);
        if (temp) temp.querySelector('.wpp-msg-time').innerHTML = '<i class="bi bi-exclamation-circle text-danger"></i>';
    })
    .finally(() => {
        isSendingMedia = false;
        setTimeout(() => { isSending = false; }, 2000);
    });
}

// Navegação por teclado no autocomplete
function handleQuickReplyKeydown(e) {
    const box = document.getElementById('quick-reply-suggest');
    if (box.style.display !== 'block' || !box._matches) return false;
    const items = box.querySelectorAll('.wpp-quick-item');
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        quickSuggestIndex = Math.min(quickSuggestIndex + 1, items.length - 1);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        quickSuggestIndex = Math.max(quickSuggestIndex - 1, 0);
    } else if (e.key === 'Enter') {
        e.preventDefault();
        const m = box._matches[quickSuggestIndex] || box._matches[0];
        if (m) applyQuickReply(m.id);
        return true;
    } else if (e.key === 'Escape') {
        box.style.display = 'none';
        return true;
    } else {
        return false;
    }
    items.forEach((el, i) => el.classList.toggle('active', i === quickSuggestIndex));
    return true;
}

// === MODAL DE GESTÃO DE RESPOSTAS RÁPIDAS ===
// Ajusta a altura do textarea de mensagem conforme o conteúdo (cresce para baixo)
function autoGrowQrMessage(el) {
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 200) + 'px';
}

function resetQuickReplyForm() {
    document.getElementById('qr-id').value = '';
    document.getElementById('qr-shortcut').value = '';
    const msg = document.getElementById('qr-message');
    msg.value = '';
    msg.style.height = 'auto';
    document.getElementById('qr-attachment').value = '';
    document.getElementById('qr-remove-attachment').value = '0';
    document.getElementById('qr-current-attachment').style.display = 'none';
    document.getElementById('qr-selected-attachment').style.display = 'none';
}

function openQuickRepliesModal() {
    resetQuickReplyForm();
    renderQuickRepliesList();
    new bootstrap.Modal(document.getElementById('quickRepliesModal')).show();
}

function renderQuickRepliesList() {
    fetch(BASE + 'whatsapp/quickReplies', { headers: {'X-Requested-With': 'XMLHttpRequest'} })
    .then(r => r.json())
    .then(data => {
        quickReplies = data.replies || [];
        const tbody = document.getElementById('qr-list');
        if (!quickReplies.length) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-muted small text-center py-3">Nenhuma resposta cadastrada.</td></tr>';
            return;
        }
        tbody.innerHTML = quickReplies.map(q => `
            <tr>
                <td><span class="qr-sc">/${escapeHtml(q.shortcut)}</span></td>
                <td class="small text-truncate" style="max-width:300px;">${escapeHtml(q.message)}</td>
                <td class="small">${q.attachment_url ? `<a href="${q.attachment_url}" target="_blank" title="${escapeHtml(q.attachment_name || '')}"><i class="bi bi-paperclip"></i></a>` : '<span class="text-muted">—</span>'}</td>
                <td class="text-end">
                    <button class="btn btn-sm btn-outline-primary" onclick="editQuickReply(${q.id})"><i class="bi bi-pencil"></i></button>
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteQuickReply(${q.id})"><i class="bi bi-trash"></i></button>
                </td>
            </tr>`).join('');
    });
}

function editQuickReply(id) {
    const q = quickReplies.find(x => x.id == id);
    if (!q) return;
    document.getElementById('qr-id').value = q.id;
    document.getElementById('qr-shortcut').value = q.shortcut;
    const msg = document.getElementById('qr-message');
    msg.value = q.message;
    autoGrowQrMessage(msg);
    document.getElementById('qr-attachment').value = '';
    document.getElementById('qr-remove-attachment').value = '0';
    document.getElementById('qr-selected-attachment').style.display = 'none';
    const box = document.getElementById('qr-current-attachment');
    if (q.attachment_url) {
        const link = document.getElementById('qr-current-attachment-link');
        link.href = q.attachment_url;
        link.textContent = q.attachment_name || 'anexo';
        box.style.display = 'block';
    } else {
        box.style.display = 'none';
    }
}

// Quando o usuário escolhe um novo arquivo, mostra o nome anexado
function onQrAttachmentChange() {
    document.getElementById('qr-remove-attachment').value = '0';
    const input = document.getElementById('qr-attachment');
    const box = document.getElementById('qr-selected-attachment');
    if (input.files && input.files[0]) {
        document.getElementById('qr-selected-attachment-name').textContent = input.files[0].name;
        box.style.display = 'block';
        // Ao anexar um novo, esconde o indicador do anexo atual (será substituído)
        document.getElementById('qr-current-attachment').style.display = 'none';
    } else {
        box.style.display = 'none';
    }
}

// Remove o arquivo recém-selecionado (ainda não salvo)
function clearQrSelectedAttachment() {
    const input = document.getElementById('qr-attachment');
    input.value = '';
    document.getElementById('qr-selected-attachment').style.display = 'none';
    // Se estava editando e havia um anexo atual, volta a mostrá-lo
    const id = document.getElementById('qr-id').value;
    const editing = id ? quickReplies.find(x => x.id == id) : null;
    if (editing && editing.attachment_url) {
        document.getElementById('qr-current-attachment').style.display = 'block';
    }
}

// Marca o anexo atual para remoção ao salvar
function removeQrAttachment() {
    document.getElementById('qr-remove-attachment').value = '1';
    document.getElementById('qr-current-attachment').style.display = 'none';
}

function saveQuickReply() {
    const shortcut = document.getElementById('qr-shortcut').value.trim().replace(/^\//, '');
    const message = document.getElementById('qr-message').value.trim();
    const id = document.getElementById('qr-id').value;
    const fileInput = document.getElementById('qr-attachment');
    const hasNewFile = fileInput.files.length > 0;
    const removeAttachment = document.getElementById('qr-remove-attachment').value === '1';

    // Descobre se a resposta em edição já possui anexo mantido
    const editing = id ? quickReplies.find(x => x.id == id) : null;
    const keepsAttachment = editing && editing.attachment_url && !removeAttachment && !hasNewFile;
    const willHaveAttachment = hasNewFile || keepsAttachment;

    if (!shortcut || (!message && !willHaveAttachment)) {
        alert('Informe o atalho e a mensagem ou um anexo.');
        return;
    }

    const fd = new FormData();
    if (id) fd.append('id', id);
    fd.append('shortcut', shortcut);
    fd.append('message', message);
    if (hasNewFile) fd.append('attachment', fileInput.files[0]);
    if (removeAttachment) fd.append('remove_attachment', '1');

    fetch(BASE + 'whatsapp/saveQuickReply', { method: 'POST', body: fd, headers: {'X-Requested-With': 'XMLHttpRequest'} })
    .then(r => r.json())
    .then(data => {
        if (data.error) { alert(data.error); return; }
        resetQuickReplyForm();
        renderQuickRepliesList();
    });
}

function deleteQuickReply(id) {
    if (!confirm('Excluir esta resposta rápida?')) return;
    fetch(BASE + 'whatsapp/deleteQuickReply/' + id, { method: 'POST', body: new FormData(), headers: {'X-Requested-With': 'XMLHttpRequest'} })
    .then(r => r.json())
    .then(() => renderQuickRepliesList());
}

let stagedFile = null;
let stagedQuickReply = null; // resposta rápida com anexo aguardando envio
let isSendingMedia = false;

// Botão de enviar: prioriza resposta rápida em prévia, depois arquivo em espera, senão texto
function handleSend() {
    if (stagedQuickReply) {
        sendQuickReplyWithAttachment(stagedQuickReply);
    } else if (stagedFile) {
        sendStagedMedia();
    } else {
        sendMessage();
    }
}

// Seleciona o arquivo e mostra a prévia como overlay dentro da janela do chat
function stageMediaFile() {
    const fileInput = document.getElementById('media-input');
    if (!fileInput.files[0] || !activeContactId) return;
    stagedFile = fileInput.files[0];

    const staging = document.getElementById('media-staging');
    const preview = document.getElementById('media-staging-preview');
    document.getElementById('media-staging-name').textContent = stagedFile.name;
    document.getElementById('media-staging-size').textContent = (stagedFile.size / 1024).toFixed(0) + ' KB';

    const ext = (stagedFile.name.split('.').pop() || '').toLowerCase();
    if (['jpg','jpeg','png','gif','webp'].includes(ext)) {
        const url = URL.createObjectURL(stagedFile);
        preview.innerHTML = `<img src="${url}" style="width:100%;height:100%;object-fit:cover;border-radius:6px;">`;
    } else {
        const icon = (ext === 'pdf') ? 'bi-file-earmark-pdf' : 'bi-file-earmark-text';
        preview.innerHTML = `<i class="bi ${icon}" style="font-size:1.6rem;color:#00997D;"></i>`;
    }
    staging.style.display = 'flex';
    const input = document.getElementById('message-input');
    input.placeholder = 'Adicione uma legenda (opcional)...';
    setTimeout(() => input.focus(), 100);
}

function cancelStagedMedia() {
    stagedFile = null;
    stagedQuickReply = null;
    document.getElementById('media-input').value = '';
    document.getElementById('media-staging').style.display = 'none';
    document.getElementById('media-staging-preview').innerHTML = '';
    const input = document.getElementById('message-input');
    input.placeholder = 'Digite uma mensagem...';
    input.value = '';
    autoGrowMessageInput();
}

// Aplica a assinatura (nome do atendente em negrito no topo) quando o toggle "Assinar" está ativo.
// Usado tanto em texto quanto em legendas de mídia/anexos.
function applySignature(text) {
    const toggle = document.getElementById('toggle-signature');
    const signatureEnabled = toggle ? toggle.checked : false;
    if (signatureEnabled && CURRENT_USER_NAME) {
        return text ? ('*' + CURRENT_USER_NAME + '*\n' + text) : ('*' + CURRENT_USER_NAME + '*');
    }
    return text;
}

// Reflete na UI a atribuição automática do contato ao usuário atual (quando estava sem dono)
function reflectAutoAssign() {
    if (activeContactAssignedTo || !CURRENT_USER_ID) return;
    activeContactAssignedTo = CURRENT_USER_ID;
    const sel = document.getElementById('detail-assigned');
    if (sel && !sel.value) sel.value = String(CURRENT_USER_ID);
}

function sendMessage() {
    const input = document.getElementById('message-input');
    const text = input.value.trim();
    if (!text || !activeContactId) return;

    // Aplica a assinatura (nome do atendente) no topo, se ativada
    const finalText = applySignature(text);

    input.value = '';
    input.style.height = '34px';

    // Bloquear polling durante envio
    isSending = true;

    const fd = new FormData();
    fd.append('contact_id', activeContactId);
    fd.append('message', finalText);

    fetch(BASE + 'whatsapp/send', { method: 'POST', body: fd, headers: {'X-Requested-With': 'XMLHttpRequest'} })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.message) {
            reflectAutoAssign();
            renderedMessageIds.add(data.message.id);
            lastMessageId = Math.max(lastMessageId, data.message.id);
            const area = document.getElementById('messages-area');
            area.insertAdjacentHTML('beforeend', renderSingleMessage(data.message));
            scrollToBottom();
        }
    })
    .finally(() => {
        setTimeout(() => { isSending = false; }, 2000);
    });
}

// Envia o arquivo em espera com a legenda digitada (ação do usuário)
function sendStagedMedia() {
    if (!stagedFile || !activeContactId || isSendingMedia) return;
    isSendingMedia = true;
    isSending = true;

    const input = document.getElementById('message-input');
    const caption = applySignature(input.value.trim());
    const fileObj = stagedFile;
    const ext = (fileObj.name.split('.').pop() || '').toLowerCase();
    const isImg = ['jpg','jpeg','png','gif','webp'].includes(ext);
    const previewUrl = isImg ? URL.createObjectURL(fileObj) : null;

    // Bolha otimista com spinner de carregamento, dentro da janela do chat
    const tempId = 'uploading-' + Date.now();
    const area = document.getElementById('messages-area');
    let inner = '';
    if (isImg) {
        inner = `<div class="wpp-msg-media" style="position:relative;">
            <img src="${previewUrl}" style="opacity:0.6;">
            <div class="wpp-upload-spinner"><span class="spinner-border text-light"></span></div>
        </div>`;
    } else {
        const dm = docMeta((fileObj.name || '').split('.').pop());
        inner = `<div class="wpp-doc">
            <div class="wpp-doc-head">
                <div class="wpp-doc-icon" style="background:${dm.color}1a;color:${dm.color};"><i class="bi ${dm.icon}"></i></div>
                <div class="wpp-doc-meta"><div class="wpp-doc-name">${escapeHtml(fileObj.name)}</div><div class="wpp-doc-type">${dm.label}</div></div>
            </div>
            <div class="text-muted" style="font-size:0.72rem;"><span class="spinner-border spinner-border-sm"></span> Enviando...</div>
        </div>`;
    }
    if (caption) inner += `<div>${formatWhatsApp(caption)}</div>`;
    area.insertAdjacentHTML('beforeend', `<div class="wpp-msg mine" id="${tempId}"><div class="wpp-msg-body">${inner}</div><div class="wpp-msg-time"><i class="bi bi-clock wpp-ack"></i></div></div>`);
    scrollToBottom();

    // Fecha a barra de prévia e limpa a legenda; o progresso agora está na bolha do chat
    input.value = '';
    input.style.height = '34px';
    cancelStagedMedia();

    const fd = new FormData();
    fd.append('contact_id', activeContactId);
    fd.append('file', fileObj);
    fd.append('caption', caption);

    fetch(BASE + 'whatsapp/sendMedia', { method: 'POST', body: fd, headers: {'X-Requested-With': 'XMLHttpRequest'} })
    .then(r => r.json())
    .then(data => {
        const temp = document.getElementById(tempId);
        if (data.success && data.message) {
            reflectAutoAssign();
            renderedMessageIds.add(data.message.id);
            lastMessageId = Math.max(lastMessageId, data.message.id || 0);
            if (temp) temp.outerHTML = renderSingleMessage(data.message);
            scrollToBottom();
        } else {
            if (temp) {
                temp.querySelector('.wpp-msg-time').innerHTML = '<i class="bi bi-exclamation-circle text-danger" title="Falha"></i>';
            }
            alert(data.error || 'Erro ao enviar arquivo.');
        }
    })
    .catch(() => {
        const temp = document.getElementById(tempId);
        if (temp) temp.querySelector('.wpp-msg-time').innerHTML = '<i class="bi bi-exclamation-circle text-danger"></i>';
    })
    .finally(() => {
        isSendingMedia = false;
        setTimeout(() => { isSending = false; }, 2000);
    });
}

// =========================================
// POLLING
// =========================================
function startPolling() {
    if (pollInterval) clearInterval(pollInterval);
    pollInterval = setInterval(() => {
        if (!activeContactId || isSending) return;
        fetch(BASE + 'whatsapp/poll/' + activeContactId + '?after_id=' + lastMessageId, { headers: {'X-Requested-With': 'XMLHttpRequest'} })
        .then(r => r.json())
        .then(messages => {
            if (messages && messages.length) {
                const area = document.getElementById('messages-area');
                messages.forEach(m => {
                    // Ignorar mensagens já renderizadas (evita duplicação)
                    if (renderedMessageIds.has(m.id)) return;
                    renderedMessageIds.add(m.id);

                    // Popular cache de menções
                    if (m.participant_jid && m.sender_name) {
                        const num = m.participant_jid.replace(/@.*/, '');
                        mentionCache[num] = m.sender_name;
                    }
                    lastMessageId = Math.max(lastMessageId, m.id);

                    // Reações são anexadas à mensagem reagida, não inseridas como bolha
                    if (m.message_type === 'reaction') {
                        applyReaction(m);
                        return;
                    }
                    area.insertAdjacentHTML('beforeend', renderSingleMessage(m));
                });
                scrollToBottom();
            }
        })
        .catch(() => {});

        // Atualizar os checks (ack) das mensagens já enviadas
        refreshAckStatuses();
    }, 1500);
}

// Ranking de status para nunca "regredir" o check (evita sumir/voltar pra relógio)
const ACK_RANK = { pending: 0, sent: 1, delivered: 2, read: 3, failed: 1 };

// Atualiza os checkzinhos das mensagens enviadas sem recarregar
function refreshAckStatuses() {
    if (!activeContactId) return;
    fetch(BASE + 'whatsapp/messageStatuses/' + activeContactId, { headers: {'X-Requested-With': 'XMLHttpRequest'} })
    .then(r => r.json())
    .then(data => {
        if (!data.statuses) return;
        data.statuses.forEach(s => {
            const el = document.querySelector(`.wpp-msg[data-msg-id="${s.id}"] .wpp-msg-ack-holder`);
            if (!el) return;

            // Novo status vindo do servidor; se vazio, mantém como 'sent' (não volta pra relógio)
            const incoming = s.ack_status || 'sent';
            const current = el.getAttribute('data-ack') || 'sent';

            // Só atualiza se o novo status for igual/superior ao atual (nunca regride)
            if ((ACK_RANK[incoming] ?? 1) >= (ACK_RANK[current] ?? 1)) {
                el.innerHTML = renderAckIcon(incoming);
                el.setAttribute('data-ack', incoming);
            }
        });
    })
    .catch(() => {});
}

function startContactsPolling() {
    if (contactsPollInterval) clearInterval(contactsPollInterval);
    contactsPollInterval = setInterval(() => {
        loadContacts(true);
    }, 3000);
}

// =========================================
// STATUS DE ATENDIMENTO
// =========================================
function changeServiceStatus() {
    if (!activeContactId) return;
    const status = document.getElementById('chat-service-status').value;
    const fd = new FormData();
    fd.append('service_status', status);

    fetch(BASE + 'whatsapp/updateServiceStatus/' + activeContactId, { method: 'POST', body: fd, headers: {'X-Requested-With': 'XMLHttpRequest'} })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Status atualizado!');
            loadContacts(true);
        }
    });
}

// =========================================
// DETALHES DO CONTATO
// =========================================
function toggleDetailPanel() {
    document.getElementById('detail-panel').classList.toggle('open');
}

function saveContactDetails() {
    if (!activeContactId) return;
    const fd = new FormData();
    fd.append('contact_name', document.getElementById('detail-name-input').value);
    fd.append('assigned_to', document.getElementById('detail-assigned').value);
    fd.append('internal_notes', document.getElementById('detail-notes').value);

    fetch(BASE + 'whatsapp/updateContact/' + activeContactId, { method: 'POST', body: fd, headers: {'X-Requested-With': 'XMLHttpRequest'} })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const name = document.getElementById('detail-name-input').value || document.getElementById('detail-phone').textContent;
            document.getElementById('chat-contact-name').textContent = name;
            document.getElementById('detail-name').textContent = name;
            loadContacts(true);
            showToast('Contato atualizado!');
        }
    });
}

function deleteContact() {
    if (!activeContactId) return;
    if (!confirm('Excluir PERMANENTEMENTE este contato? Isso remove a conversa, mensagens, etiquetas e briefing. Esta ação não pode ser desfeita.')) return;
    fetch(BASE + 'whatsapp/deleteContact/' + activeContactId, { method: 'POST', body: new FormData(), headers: {'X-Requested-With': 'XMLHttpRequest'} })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Contato excluído.');
            activeContactId = null;
            // Fechar painéis e voltar ao estado inicial
            const detailPanel = document.getElementById('detail-panel');
            if (detailPanel) detailPanel.classList.remove('open');
            document.getElementById('chat-header').style.display = 'none';
            document.getElementById('messages-area').style.display = 'none';
            document.getElementById('input-area').style.display = 'none';
            document.getElementById('chat-empty').style.display = 'flex';
            loadContacts();
        } else {
            alert(data.error || 'Erro ao excluir contato.');
        }
    });
}

function renderContactLabels(labels) {
    const container = document.getElementById('detail-labels');
    container.innerHTML = labels.map(l =>
        `<span class="wpp-label-badge" style="background:${l.color};">${escapeHtml(l.name)} <i class="bi bi-x-circle" style="cursor:pointer;font-size:0.6rem;" onclick="removeLabelFromContact(${l.id})"></i></span>`
    ).join('');
}

function addLabelToContact() {
    const select = document.getElementById('add-label-select');
    const labelId = select.value;
    if (!labelId || !activeContactId) { alert('Selecione uma etiqueta'); return; }

    const fd = new FormData();
    fd.append('contact_id', activeContactId);
    fd.append('label_id', labelId);
    fd.append('action', 'add');

    fetch(BASE + 'whatsapp/toggleLabel', { method: 'POST', body: fd, headers: {'X-Requested-With': 'XMLHttpRequest'} })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            fetch(BASE + 'whatsapp/contactDetail/' + activeContactId, { headers: {'X-Requested-With': 'XMLHttpRequest'} })
            .then(r => r.json())
            .then(c => renderContactLabels(c.labels || []));
            select.value = '';
            showToast('Etiqueta adicionada!');
        }
    });
}

function removeLabelFromContact(labelId) {
    const fd = new FormData();
    fd.append('contact_id', activeContactId);
    fd.append('label_id', labelId);
    fd.append('action', 'remove');

    fetch(BASE + 'whatsapp/toggleLabel', { method: 'POST', body: fd, headers: {'X-Requested-With': 'XMLHttpRequest'} })
    .then(r => r.json())
    .then(() => {
        fetch(BASE + 'whatsapp/contactDetail/' + activeContactId, { headers: {'X-Requested-With': 'XMLHttpRequest'} })
        .then(r => r.json())
        .then(c => renderContactLabels(c.labels || []));
    });
}

// =========================================
// CRIAR ETIQUETA
// =========================================
function openCreateLabelModal() {
    document.getElementById('new-label-name').value = '';
    document.getElementById('new-label-color').value = '#00BFA6';
    new bootstrap.Modal(document.getElementById('createLabelModal')).show();
}

function createLabel() {
    const name = document.getElementById('new-label-name').value.trim();
    const color = document.getElementById('new-label-color').value;
    if (!name) { alert('Digite o nome da etiqueta'); return; }

    const fd = new FormData();
    fd.append('name', name);
    fd.append('color', color);

    fetch(BASE + 'whatsapp/createLabel', { method: 'POST', body: fd, headers: {'X-Requested-With': 'XMLHttpRequest'} })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const select = document.getElementById('add-label-select');
            const opt = document.createElement('option');
            opt.value = data.label.id;
            opt.textContent = data.label.name;
            select.appendChild(opt);

            const filterSelect = document.getElementById('filter-label');
            const filterOpt = document.createElement('option');
            filterOpt.value = data.label.id;
            filterOpt.textContent = data.label.name;
            filterSelect.appendChild(filterOpt);

            bootstrap.Modal.getInstance(document.getElementById('createLabelModal')).hide();
            showToast('Etiqueta "' + name + '" criada!');

            if (activeContactId) {
                const addFd = new FormData();
                addFd.append('contact_id', activeContactId);
                addFd.append('label_id', data.label.id);
                addFd.append('action', 'add');
                fetch(BASE + 'whatsapp/toggleLabel', { method: 'POST', body: addFd, headers: {'X-Requested-With': 'XMLHttpRequest'} })
                .then(r => r.json())
                .then(() => {
                    fetch(BASE + 'whatsapp/contactDetail/' + activeContactId, { headers: {'X-Requested-With': 'XMLHttpRequest'} })
                    .then(r => r.json())
                    .then(c => renderContactLabels(c.labels || []));
                });
            }
        } else {
            alert(data.error || 'Erro ao criar etiqueta');
        }
    });
}
</script>

<script>
// =========================================
// CRM INTEGRATION
// =========================================
// === INICIAR CONVERSA ===
function openStartConversation() {
    document.getElementById('start-conv-phone').value = '';
    document.getElementById('start-conv-name').value = '';
    document.getElementById('start-conv-error').style.display = 'none';
    new bootstrap.Modal(document.getElementById('startConversationModal')).show();
}

function startConversation() {
    const phone = document.getElementById('start-conv-phone').value.trim();
    const name = document.getElementById('start-conv-name').value.trim();
    const errBox = document.getElementById('start-conv-error');
    const btn = document.getElementById('start-conv-btn');

    if (!phone) {
        errBox.textContent = 'Informe o número.';
        errBox.style.display = 'block';
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    errBox.style.display = 'none';

    const fd = new FormData();
    fd.append('phone', phone);
    fd.append('name', name);

    fetch(BASE + 'whatsapp/startConversation', { method: 'POST', body: fd, headers: {'X-Requested-With': 'XMLHttpRequest'} })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.contact) {
            bootstrap.Modal.getInstance(document.getElementById('startConversationModal')).hide();
            loadContacts();
            // Abrir a conversa criada
            setTimeout(() => openChat(data.contact.id, false), 400);
        } else {
            errBox.textContent = data.error || 'Erro ao iniciar conversa.';
            errBox.style.display = 'block';
        }
    })
    .catch(() => {
        errBox.textContent = 'Erro na requisição.';
        errBox.style.display = 'block';
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = 'Iniciar';
    });
}

// Formata um input de texto como moeda brasileira (R$) enquanto digita
function formatCurrencyInput(el) {
    let digits = (el.value || '').replace(/\D/g, '');
    if (!digits) { el.value = ''; return; }
    // trata como centavos
    const value = (parseInt(digits, 10) / 100);
    el.value = 'R$ ' + value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// === BRIEFING COMERCIAL ===
function openBriefingModal() {
    if (!activeContactId) { alert('Selecione um contato.'); return; }
    // Limpar campos
    ['need','main_pain','current_solution','expected_goal','urgency','investment_range','decision_level','main_objection','next_step','notes'].forEach(f => {
        const el = document.getElementById('bf-' + f); if (el) el.value = '';
    });
    document.getElementById('bf-lead_temperature').value = '';
    document.getElementById('bf-next_contact_date').value = '';

    // Carregar briefing existente
    fetch(BASE + 'whatsapp/getBriefing/' + activeContactId, { headers: {'X-Requested-With': 'XMLHttpRequest'} })
    .then(r => r.json())
    .then(data => {
        const b = data.briefing;
        if (b) {
            for (const key in b) {
                const el = document.getElementById('bf-' + key);
                if (el) el.value = b[key] || '';
            }
        }
        new bootstrap.Modal(document.getElementById('briefingModal')).show();
    });
}

function saveBriefing() {
    if (!activeContactId) return;
    const fields = ['need','main_pain','current_solution','expected_goal','urgency','investment_range','decision_level','lead_temperature','main_objection','next_step','next_contact_date','notes'];
    const fd = new FormData();
    fields.forEach(f => {
        const el = document.getElementById('bf-' + f);
        fd.append(f, el ? el.value : '');
    });

    fetch(BASE + 'whatsapp/saveBriefing/' + activeContactId, { method: 'POST', body: fd, headers: {'X-Requested-With': 'XMLHttpRequest'} })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('briefingModal')).hide();
            showToast('Briefing salvo!');
        } else {
            alert(data.error || 'Erro ao salvar briefing.');
        }
    });
}

function loadCrmBoards() {
    fetch(BASE + 'crm/listBoards', { headers: {'X-Requested-With': 'XMLHttpRequest'} })
    .then(r => r.json())
    .then(boards => {
        const select = document.getElementById('crm-board-select');
        select.innerHTML = '<option value="">Selecione board</option>';
        if (Array.isArray(boards)) {
            boards.forEach(b => {
                select.innerHTML += `<option value="${b.id}" data-columns='${JSON.stringify(b.columns || [])}'>${escapeHtml(b.name)}</option>`;
            });
        }
    })
    .catch(() => {});
}

function loadBoardColumns() {
    const select = document.getElementById('crm-board-select');
    const opt = select.options[select.selectedIndex];
    const colSelect = document.getElementById('crm-column-select');
    if (!opt || !opt.value) { colSelect.style.display = 'none'; return; }
    const columns = JSON.parse(opt.dataset.columns || '[]');
    colSelect.innerHTML = columns.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
    colSelect.style.display = 'block';
}

function addContactToCrm() {
    if (!activeContactId) return;
    const boardId = document.getElementById('crm-board-select').value;
    const columnId = document.getElementById('crm-column-select').value;
    if (!boardId) { alert('Selecione um board'); return; }

    const fd = new FormData();
    fd.append('contact_id', activeContactId);
    fd.append('board_id', boardId);
    if (columnId) fd.append('column_id', columnId);

    fetch(BASE + 'whatsapp/addToCrm', { method: 'POST', body: fd, headers: {'X-Requested-With': 'XMLHttpRequest'} })
    .then(r => r.json())
    .then(data => {
        if (data.success) showToast('Contato adicionado ao CRM!');
        else alert(data.error || 'Erro');
    });
}

// =========================================
// UTILIDADES
// =========================================
function scrollToBottom() {
    const area = document.getElementById('messages-area');
    if (area) area.scrollTop = area.scrollHeight;
}

function formatTime(datetime) {
    if (!datetime) return '';
    const d = new Date(datetime.replace(' ', 'T'));
    const now = new Date();
    const diff = now - d;
    if (diff < 86400000 && d.getDate() === now.getDate()) {
        return d.getHours().toString().padStart(2,'0') + ':' + d.getMinutes().toString().padStart(2,'0');
    }
    if (diff < 604800000) {
        const dias = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
        return dias[d.getDay()];
    }
    return d.getDate().toString().padStart(2,'0') + '/' + (d.getMonth()+1).toString().padStart(2,'0');
}

function formatFullTime(datetime) {
    if (!datetime) return '';
    const d = new Date(datetime.replace(' ', 'T'));
    const now = new Date();
    const diff = now - d;
    const time = d.getHours().toString().padStart(2,'0') + ':' + d.getMinutes().toString().padStart(2,'0');
    if (diff < 86400000 && d.getDate() === now.getDate()) {
        return time;
    }
    const dias = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
    if (diff < 604800000) {
        return dias[d.getDay()] + ' ' + time;
    }
    return d.getDate().toString().padStart(2,'0') + '/' + (d.getMonth()+1).toString().padStart(2,'0') + ' ' + time;
}

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function showToast(msg) {
    const toast = document.createElement('div');
    toast.className = 'position-fixed bottom-0 end-0 m-3 alert alert-success py-2 px-3 shadow';
    toast.style.zIndex = 9999;
    toast.style.fontSize = '0.82rem';
    toast.textContent = msg;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

// =========================================
// TEXTAREA — Auto-resize + Shift+Enter / Enter
// =========================================
function setupTextarea() {
    const textarea = document.getElementById('message-input');
    if (!textarea) return;

    textarea.addEventListener('keydown', function(e) {
        // Se o autocomplete de respostas rápidas estiver aberto, ele trata as setas/Enter/Esc
        if (['ArrowDown','ArrowUp','Enter','Escape'].includes(e.key)) {
            if (handleQuickReplyKeydown(e)) return;
        }
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            handleSend();
        }
        // Shift+Enter insere a quebra de linha (comportamento padrão do textarea)
    });

    textarea.addEventListener('input', function() {
        this.style.height = '34px';
        this.style.height = Math.min(this.scrollHeight, 120) + 'px';
        handleQuickReplyInput();
    });
}

// =========================================
// INIT
// =========================================
document.addEventListener('DOMContentLoaded', () => {
    loadContacts();
    startContactsPolling();
    setupTextarea();
    loadQuickReplies();

    let searchTimer;
    document.getElementById('contact-search').addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => loadContacts(), 300);
    });
    document.getElementById('filter-assigned').addEventListener('change', () => loadContacts());
    document.getElementById('filter-label').addEventListener('change', () => loadContacts());
    document.getElementById('filter-status').addEventListener('change', () => loadContacts());

    if (activeContactId) {
        setTimeout(() => openChat(activeContactId, false), 500);
    }
});
</script>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
