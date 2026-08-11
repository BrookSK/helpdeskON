# ESPECIFICAÇÃO TÉCNICA COMPLETA — MÓDULOS WHATSAPP + CRM

## Visão Geral

Este documento descreve de forma completa e detalhada os módulos de **WhatsApp** (chat, gerenciamento de instâncias, webhook, respostas rápidas) e **CRM** (kanban, cards, comissões, dashboard) de um sistema de helpdesk. O objetivo é permitir a recriação integral dessas funcionalidades em outro sistema.

---

## PARTE 1: MÓDULO WHATSAPP

---

### 1.1 TELA DE CONFIGURAÇÃO / CONEXÃO DE INSTÂNCIAS

**Rota:** `/whatsapp` ou `/whatsapp/index`
**Permissões:** super_admin, attendant, whatsapp_agent, comercial

#### 1.1.1 Conceito de Instâncias

O sistema suporta **múltiplas instâncias** de WhatsApp. Cada instância representa uma conexão (número) diferente no WhatsApp via **Evolution API v2** (WhatsApp Web via Baileys).

**Regras de instância:**
- Cada instância possui: `instance_name` (único, sem espaços), `display_name`, `api_url`, `api_key`
- Uma instância pode ser **vinculada a um usuário** específico (só ele vê os contatos daquela instância)
- Uma instância pode ser **sem vínculo** (disponível para todos os usuários)
- Uma instância pode ser marcada como **padrão** (apenas uma por vez)
- A URL e API Key só precisam ser informadas **uma vez** — ao criar novas instâncias, pode-se usar as credenciais da instância padrão (checkbox "Usar URL e API Key da instância padrão")

#### 1.1.2 Card de cada Instância

Cada instância exibe um card com:
- Nome de exibição (display_name) e nome técnico (instance_name)
- Badge de status: **Conectado** (verde), **Conectando...** (amarelo), **Desconectado** (cinza)
- Badge **Padrão** (azul) se for a instância padrão
- Telefone do proprietário (owner_phone), se disponível
- Nome do usuário vinculado (ou "Sem usuário vinculado")
- Área de QR Code (aparece ao clicar em Conectar)

#### 1.1.3 Botões de Ação por Instância

| Botão | Quando aparece | Ação |
|-------|----------------|------|
| **Conectar** (QR Code) | Status ≠ 'open' | Gera QR Code na Evolution API e exibe na tela |
| **Desconectar** | Status = 'open' | Faz logout da instância (super_admin only) |
| **Atualizar status** (ícone refresh) | Sempre | Verifica o status atual na Evolution API |
| **Editar** (lápis) | super_admin | Abre modal para editar display_name, api_url, api_key, user_id |
| **Definir como padrão** (estrela) | super_admin + não é padrão | Marca como instância padrão |
| **Excluir** (lixeira) | super_admin | Remove instância da Evolution API e do banco (cascade) |

#### 1.1.4 Modal "Nova Instância"

Campos:
- **Nome da instância** * (sem espaços/especiais) — Ex: "minha-empresa"
- **Nome de exibição** — Ex: "WhatsApp Empresa"
- **Checkbox:** "Usar URL e API Key da instância padrão" (checked por padrão)
  - Se desmarcado, mostra campos de URL e API Key customizados
- **Vincular ao usuário** (opcional) — dropdown com usuários (super_admin, attendant, whatsapp_agent)
  - Se vinculada, apenas este usuário vê os contatos desta instância

**Ao criar:**
1. Chama a Evolution API para criar a instância com webhook configurado
2. Salva no banco local
3. Configura webhook URL apontando para `/whatsapp/webhook`

#### 1.1.5 Modal "Editar Instância"

Campos editáveis:
- Nome de exibição
- URL da Evolution API
- API Key
- Vincular ao usuário (dropdown)

#### 1.1.6 Lógica de seleção da instância do usuário (getUserInstance)

Quando um usuário acessa o chat, o sistema determina qual instância usar:
1. **Primeiro:** instância vinculada diretamente ao usuário (`user_id = ?`)
2. **Segundo:** instância padrão SEM vínculo a nenhum usuário (`is_default = 1 AND user_id IS NULL`)
3. **Terceiro:** qualquer instância SEM vínculo (`user_id IS NULL`)
4. Se nenhuma for encontrada, retorna `null` (usuário sem acesso)

---

### 1.2 TELA DE CHAT (Interface estilo WhatsApp Web)

**Rota:** `/whatsapp/chat` ou `/whatsapp/chat/{contactId}`
**Permissões:** super_admin, attendant, whatsapp_agent, comercial

#### 1.2.1 Layout (3 colunas)

```
┌─────────────────┬──────────────────────────┬───────────────────┐
│ Lista Contatos  │     Área de Chat         │ Detalhes Contato  │
│  (340px)        │      (flex: 1)           │    (320px)        │
│                 │                          │   (toggle)        │
└─────────────────┴──────────────────────────┴───────────────────┘
```

- **Coluna esquerda:** Lista de contatos/grupos (340px, fixa)
- **Coluna central:** Mensagens e input (flex, background bege/whatsapp `#efeae2`)
- **Coluna direita:** Detalhes do contato (320px, aparece/esconde com toggle)

**Mobile (≤768px):** Layout single-page com painéis alternáveis (desliza entre contatos → chat → detalhes)

#### 1.2.2 Coluna Esquerda — Lista de Contatos

**Header da lista:**
- Título "Chat" com ícone WhatsApp
- Botões: Sincronizar grupos, Respostas rápidas (raio), Conexões (engrenagem), CRM (kanban)
- Botão **"Iniciar conversa"** (verde, largura total)
- Campo de busca ("Buscar contato ou grupo...")
- 3 Filtros lado a lado:
  - **Por atendente:** "Todos", "Sem dono", lista de membros da equipe
  - **Por etiqueta:** dropdown com todas as etiquetas criadas
  - **Por status:** "Em atendimento", "Aguardando", "Concluído", "Novo"

**Abas (tabs):**
- **Contatos** (com contador) — apenas contatos individuais (`is_group = 0`)
- **Grupos** (com contador) — apenas grupos (`is_group = 1`)

**Renderização dos contatos (tab Contatos):**

Os contatos são agrupados por status de atendimento, nesta ordem:
1. 🟠 **Em Atendimento** (com contador)
2. 🔴 **Aguardando** (com contador)
3. 🔵 **Novos** (com contador)
4. 🟢 **Concluídos** (com contador)

Cada item mostra:
- **Avatar** (foto de perfil, ou iniciais do nome, ou ícone de grupo)
- **Nome** (contact_name > push_name > phone > "Desconhecido")
- **Prévia da última mensagem** (tipo: texto truncado / 📷 Imagem / 🎤 Áudio / 📎 Documento / 🎥 Vídeo / Sticker)
  - Se a última mensagem for de um grupo, mostra "NomeRemetente: mensagem"
- **Horário** da última mensagem (formato: "10:30", "Ontem", "15/07")
- **Badge de não lidas** (bolinha verde com número)
- **Etiquetas** como badges coloridas pequenas
- **Badge CRM** mostrando "Board › Coluna" quando o contato está em um CRM
- **Dot de status** (bolinha colorida indicando o status de atendimento)

**Renderização dos grupos (tab Grupos):**
- Mostra o **nome real do grupo** (contact_name, NUNCA o push_name do último remetente)
- Avatar verde com ícone de pessoas
- Se tem foto do grupo, mostra a foto
- Mesma prévia de última mensagem

**Filtros (aplicados via AJAX):**
- Busca por nome/telefone (campo search)
- Atendente atribuído (assigned_to)
- Etiqueta (label_id)
- Status de atendimento (service_status)
- Contatos arquivados ficam ocultos por padrão

**Atualização automática:**
- Polling periódico (a cada poucos segundos) recarrega a lista de contatos
- Ao receber nova mensagem via polling, reordena a lista (last_message_at DESC)

#### 1.2.3 Coluna Central — Área de Mensagens

**Header do chat (quando um contato está aberto):**
- Botão voltar (mobile)
- Avatar + Nome + Telefone do contato (clicável → abre painel de detalhes)
- Toggle **"Assinar"** — quando ativo, adiciona `*NomeDoUsuário:*\n` no início de cada mensagem enviada
- Dropdown de **status do atendimento**: Novo / Em atendimento / Aguardando / Concluído
- Botão **Detalhes** (ícone person-lines → toggle painel direito)

**Área de mensagens:**
- Scroll vertical com lazy-loading (50 msgs por vez, scroll para cima carrega mais com `before_id`)
- Separadores de data entre mensagens de dias diferentes: "Hoje", "Ontem", "15/07/2025"
- Mensagens de tipos diferentes com renderização específica (ver seção 1.2.5)
- Indicadores de status (checks): ✓ enviado, ✓✓ entregue, ✓✓ azul = lido
- Reações exibidas como badge flutuante sobre a mensagem
- Mensagens deletadas aparecem em itálico: "🚫 Mensagem apagada"

**Formatação de texto (WhatsApp-style):**
- `*texto*` → **negrito**
- `_texto_` → *itálico*
- `~texto~` → ~~tachado~~
- ` ```texto``` ` → `monospace`
- `@número` → badge de menção azul (resolve para nome do contato se conhecido)
- `\n` → quebra de linha (pular linha)

**Input de mensagem (barra inferior):**
- Botão **emoji** (abre picker de emojis por categoria acima do input)
- Botão **anexar** (clip → input file oculto)
- **Textarea** com auto-resize (min 1 linha, max ~5 linhas)
  - **Shift+Enter** = pular linha
  - **Enter** = enviar mensagem
  - Ao digitar `/` no início, abre autocomplete de respostas rápidas
- Botão **enviar** (ícone send verde)
- Área de stage de mídia (barra acima do input com preview, nome e tamanho do arquivo selecionado + botão cancelar)

#### 1.2.4 Respostas Rápidas (Quick Replies)

**Funcionamento no chat:**
- Ao digitar `/` seguido de texto no textarea, o sistema busca atalhos que começam com esse texto
- Aparece um dropdown de sugestão (autocomplete) acima do textarea
- Ao selecionar, o texto é substituído pela mensagem da resposta rápida
- Se a resposta rápida tem anexo, ele é enviado junto (como mídia + legenda)

**Gerenciamento (Modal de Respostas Rápidas):**
- Botão de raio (⚡) na barra do chat abre o modal
- **Formulário (colapsável) para criar/editar:**
  - Atalho (prefixo `/` automático, minúsculas, sem espaços)
  - Mensagem de texto
  - Anexo de arquivo (opcional) — upload com preview
  - Botão salvar / cancelar
- **Lista de respostas cadastradas:**
  - Card com: badge do atalho (`/bomdia`), preview da mensagem (truncado 2 linhas), link do anexo se houver
  - Botões (aparecem no hover): Editar (azul), Excluir (vermelho)

**Tabela: whatsapp_quick_replies**
| Campo | Tipo | Descrição |
|-------|------|-----------|
| id | INT PK | ID |
| shortcut | VARCHAR(100) UNIQUE | Atalho sem a `/`, minúsculo, sem espaços |
| message | TEXT | Texto da resposta |
| attachment_path | TEXT NULL | Caminho do arquivo anexado (relativo) |
| attachment_name | VARCHAR(255) NULL | Nome original do arquivo |
| attachment_mime | VARCHAR(100) NULL | MIME type do arquivo |
| created_by | INT FK→users | Quem criou |
| created_at | TIMESTAMP | Data criação |
| updated_at | TIMESTAMP | Data atualização |

#### 1.2.5 Tipos de Mensagem — Renderização

| Tipo | Renderização |
|------|-------------|
| **text** | Texto com formatação WhatsApp (bold, italic, strike, mono, mentions, quebras de linha) |
| **image** | Imagem com max-width 220px, clicável → abre **lightbox na mesma tela** (overlay escuro, imagem grande, botão fechar ×). Legenda abaixo se houver. |
| **audio** | Player customizado com: botão play/pause circular, waveform bars animadas, tempo atual/total, botão de volume. Abaixo: botão "Transcrever" (usa OpenAI Whisper). Após transcrição, mostra texto em itálico com background suave. |
| **video** | Tag `<video>` com controles nativos, max-width. Legenda se houver. |
| **document** | Card formatado estilo "documento": ícone colorido por tipo (PDF=vermelho, Excel=verde, Word=azul, etc), nome do arquivo (truncado 2 linhas), tipo/tamanho. Botões: **"Ver"** (abre na mesma tela) e **"Baixar"** (download). |
| **sticker** | Imagem com max-width 120px, sem bordas, fundo transparente (WebP). |
| **reaction** | Badge flutuante no canto inferior da mensagem reagida, com emoji e borda. |
| **location** | Texto com coordenadas (simplificado). |

**Lightbox de imagem:**
- Abre na mesma página (overlay escuro `rgba(0,0,0,0.85)`)
- Imagem centralizada com max 90vw × 90vh
- Clique no overlay ou no × fecha
- NÃO abre em nova guia

**Player de áudio customizado:**
- Botão play/pause circular (preto)
- Waveform com barras que se preenchem conforme o progresso
- Tempo atual / duração total
- Botão de transcrição abaixo (ícone de microfone + "Transcrever")
- Após transcrever, salva no banco (campo `transcription`) e exibe em box itálico

#### 1.2.6 Coluna Direita — Detalhes do Contato

Painel lateral (320px) que abre/fecha clicando no nome do contato no header ou no botão de detalhes.

**Conteúdo do painel:**

1. **Avatar grande** (70×70) + Nome + Telefone
2. **Campo "Nome"** — editável, para renomear o contato manualmente
3. **Campo "Atribuído a"** — dropdown com todos os membros da equipe (super_admin + attendant + whatsapp_agent)
4. **Campo "Observações internas"** — textarea livre para notas
5. **Botão "Salvar"** — salva nome, atribuição e observações
6. **Seção "Etiquetas":**
   - Lista de etiquetas atuais do contato (badges coloridas com × para remover)
   - Dropdown para selecionar etiqueta existente + botão adicionar
   - Botão para **criar nova etiqueta** (abre modal com nome + cor)
7. **Botão "Briefing comercial"** — abre modal completo (ver 1.2.7)
8. **Seção "CRM":**
   - Dropdown para selecionar board
   - Dropdown para selecionar coluna (carrega ao selecionar board)
   - Botão **"Adicionar ao CRM"** — cria card no CRM vinculado a este contato
9. **Botão "Excluir contato permanentemente"** (super_admin only, vermelho) — remove contato, mensagens, etiquetas, briefing e desvincula cards do CRM

#### 1.2.7 Briefing Comercial

Modal grande com formulário estruturado:

| Campo | Tipo | Opções |
|-------|------|--------|
| Necessidade do lead | textarea | — |
| Principal dor/problema | textarea | — |
| Solução atual utilizada | textarea | — |
| Objetivo esperado | textarea | — |
| Urgência/prazo | select | Baixa, Média, Alta, Urgente |
| Faixa de investimento | input text (formatado R$) | — |
| Nível de decisão do contato | select | Decisor, Influenciador, Usuário, Técnico, Sem influência |
| Temperatura do lead | select | Frio, Morno, Quente |
| Data do próximo contato | input date | — |
| Principal objeção | textarea | — |
| Próximo passo combinado | textarea | — |
| Observações importantes | textarea | — |

**Sincronização:** O campo "Faixa de investimento" sincroniza com o campo "valor" do card do CRM (bidirecional).

#### 1.2.8 Iniciar Nova Conversa

Modal simples:
- **Campo "Número (com DDD)"** — somente números, ex: `5517999999999`
- **Campo "Nome"** (opcional) — se vazio, puxa o nome do WhatsApp automaticamente via API
- Ao confirmar:
  1. Valida o número via `checkIsWhatsapp()` na Evolution API
  2. Se válido, busca nome de perfil e foto via API
  3. Cria/localiza o contato no banco
  4. Abre o chat com esse contato
  5. Se o número não tem WhatsApp, mostra erro

#### 1.2.9 Status de Atendimento

Cada contato possui um campo `service_status` com os valores:
- **novo** (padrão ao receber primeira mensagem)
- **em_atendimento** 
- **aguardando**
- **concluido**

**Regras automáticas:**
- Quando um contato está "concluído" e recebe uma nova mensagem, volta automaticamente para "novo"
- O dropdown no header do chat permite trocar manualmente o status

#### 1.2.10 Assinatura de Mensagens

Toggle "Assinar" no header do chat:
- Quando ativo, toda mensagem enviada é prefixada com: `*NomeDoUsuário:*\n` (aparece em negrito no WhatsApp)
- O nome do sender é salvo no campo `sender_name` da mensagem para histórico

#### 1.2.11 Grupos

**Diferenças para contatos individuais:**
- Nome vem de `contact_name` (subject do grupo), NUNCA do `push_name`
- Avatar verde com ícone de pessoas (ou foto do grupo se disponível)
- Cada mensagem no grupo mostra o **sender_name** (quem enviou) em cor diferente acima do texto
- O `participant_jid` identifica quem mandou cada mensagem
- A foto do grupo é puxada via Evolution API

#### 1.2.12 Envio de Emojis

- Botão de emoji (😊) no input abre picker flutuante acima da caixa de texto
- Emojis organizados por categorias (Smileys, Pessoas, Animais, Comida, Viagem, Atividades, Objetos, Símbolos)
- Grid responsivo de emojis clicáveis
- Ao clicar, insere no textarea na posição do cursor
- Fecha ao clicar fora ou enviar

#### 1.2.13 Envio de Arquivos (Mídia)

**Fluxo de envio:**
1. Usuário clica no ícone de clip (📎)
2. Seleciona arquivo (qualquer tipo)
3. Aparece **barra de staging** acima do textarea com:
   - Preview thumbnail (se imagem) ou ícone genérico
   - Nome do arquivo
   - Tamanho do arquivo
   - Botão × para cancelar
4. Opcionalmente digita legenda/caption no textarea
5. Ao clicar enviar:
   - Mensagem é salva imediatamente no banco com status `pending`
   - Upload é feito
   - Arquivo é salvo localmente em `uploads/whatsapp_media/YYYY-MM/`
   - Enviado via Evolution API (tenta URL pública primeiro, fallback para base64)
   - Status atualizado para `sent` ou `failed`

**Tipos de mídia detectados por extensão:**
- Imagem: jpg, jpeg, png, gif, webp
- Vídeo: mp4, avi, mov, 3gp
- Áudio: mp3, ogg, wav, aac, m4a
- Documento: todos os demais

---

### 1.3 WEBHOOK — Recebimento de Mensagens

**Rota:** `/whatsapp/webhook` (POST, sem autenticação)

O webhook recebe eventos da Evolution API e processa em tempo real.

#### 1.3.1 Eventos tratados

| Evento | Ação |
|--------|------|
| `messages.upsert` | Processa mensagem recebida (ver abaixo) |
| `messages.update` | Atualiza status de ACK (enviado/entregue/lido) e detecta deleções |
| `messages.delete` | Marca mensagem como deletada (`is_deleted = 1`) |
| `connection.update` | Atualiza status de conexão da instância no banco |
| `qrcode.updated` | Salva QR code no banco e atualiza status para 'connecting' |

#### 1.3.2 Processamento de mensagem recebida (messages.upsert)

1. Identifica a instância pelo `instanceName` do payload
2. Extrai dados: `remoteJid`, `fromMe`, `messageId`, tipo de mensagem, texto, mídia
3. **Ignora:** mensagens de status/broadcast, mensagens `fromMe` (já salvas no envio), mensagens de distribuição de chave
4. **Detecta tipo:**
   - `conversation` / `extendedTextMessage` → texto
   - `imageMessage` → imagem (com caption)
   - `audioMessage` → áudio
   - `videoMessage` → vídeo (com caption)
   - `documentMessage` → documento (com fileName)
   - `stickerMessage` → sticker
   - `reactionMessage` → reação (emoji + mensagem alvo)
   - `protocolMessage` (REVOKE) → mensagem apagada
5. **Upsert contato:** cria ou atualiza o contato (com deduplicação por últimos 8 dígitos do telefone)
6. **Resolve grupo:** se é grupo, busca o subject real do grupo via API (lazy, uma vez)
7. **Download mídia:** se a mensagem tem mídia mas não veio base64, busca via `/chat/getBase64FromMediaMessage`
8. **Salva mensagem** no banco (com deduplicação por `instance_id + message_id`)
9. **Incrementa não lidas** do contato
10. **Regra automática:** se contato estava "concluído", volta para "novo"
11. **Busca foto de perfil** (lazy, se ainda não tem)

#### 1.3.3 Formato do payload esperado

```json
{
  "event": "messages.upsert",
  "instance": "nome-da-instancia",
  "data": {
    "key": {
      "remoteJid": "5511999999999@s.whatsapp.net",
      "fromMe": false,
      "id": "ABC123DEF456"
    },
    "pushName": "João Silva",
    "messageTimestamp": 1700000000,
    "message": {
      "conversation": "Olá, preciso de ajuda!"
    }
  }
}
```

#### 1.3.4 Registro do Webhook na Evolution API

Ao criar uma instância, o webhook é registrado com:
```json
{
  "webhook": {
    "url": "https://seusite.com/whatsapp/webhook",
    "byEvents": false,
    "base64": true,
    "events": ["MESSAGES_UPSERT", "MESSAGES_UPDATE", "MESSAGES_DELETE", "CONNECTION_UPDATE", "QRCODE_UPDATED"]
  }
}
```

---

### 1.4 SINCRONIZAÇÃO

#### 1.4.1 Sincronizar Grupos

**Botão** no header da lista de contatos (ícone refresh).
- Busca todos os grupos da instância via Evolution API (`/group/fetchAllGroups`)
- Compara com os grupos salvos no banco
- Atualiza o `contact_name` de cada grupo com o subject correto
- Retorna quantos foram atualizados

#### 1.4.2 Sincronizar Fotos de Perfil

- Busca até 100 contatos sem foto de perfil
- Para cada um, faz chamada à Evolution API para buscar a foto
- Atualiza o campo `profile_picture_url`

#### 1.4.3 Re-registrar Webhook

- Envia novamente o webhook com todos os eventos para a Evolution API
- Usado quando a instância perde a configuração de webhook

---

### 1.5 NOTIFICAÇÕES WHATSAPP (WhatsappNotifier)

Serviço para enviar mensagens automáticas do sistema via WhatsApp:

**`sendToGroup($groupJid, $message)`**
- Envia mensagem de texto para um grupo específico
- Usa a instância vinculada ao grupo (ou fallback para padrão)
- Registra no histórico de mensagens (aparece no chat)

**`sendToDefaultGroup($message)`**
- Envia para o grupo padrão configurado nas Settings
- Só funciona se `whatsapp_group_notify_enabled = 1`

**`sendToPhone($phone, $message, $contactName)`**
- Envia mensagem individual para um número
- Cria/atualiza o contato automaticamente
- Registra no chat (mensagem aparece como "Sistema")
- Prioridade de instância: default sem vínculo → qualquer sem vínculo → default → qualquer

---

### 1.6 POLLING E ATUALIZAÇÃO EM TEMPO REAL

**Polling de mensagens:**
- Endpoint: `GET /whatsapp/poll/{contactId}?after_id={lastId}`
- Retorna novas mensagens + IDs de mensagens deletadas
- Frontend faz polling a cada poucos segundos quando o chat está aberto
- Evita duplicatas usando `renderedMessageIds` Set no frontend

**Polling de contatos:**
- Recarrega a lista de contatos periodicamente para atualizar ordem, não lidas, etc.

**Polling de status (ack):**
- Endpoint: `GET /whatsapp/messageStatuses/{contactId}`
- Retorna status de ack das últimas 50 mensagens enviadas
- Atualiza os checks (✓ ✓✓ 🔵✓✓) no frontend

---

### 1.7 ATRIBUIÇÃO AUTOMÁTICA

Ao enviar qualquer mensagem (texto, mídia ou resposta rápida):
- Se o contato não está atribuído a ninguém (`assigned_to IS NULL`)
- O sistema automaticamente atribui ao usuário que está enviando

---

### 1.8 TRANSCRIÇÃO DE ÁUDIO

**Endpoint:** `POST /whatsapp/transcribeAudio/{messageId}`

1. Verifica se a mensagem é tipo `audio` e tem `media_url`
2. Se já tem transcrição em cache (`transcription` no banco), retorna direto
3. Envia o arquivo de áudio para OpenAI Whisper (`POST /v1/audio/transcriptions`)
4. Modelo: `whisper-1`, idioma: `pt`
5. Salva a transcrição no banco (campo `transcription`)
6. Retorna o texto transcrito

---

### 1.9 EVOLUTION API — ENDPOINTS UTILIZADOS

Classe `EvolutionApi` encapsula toda comunicação HTTP com a Evolution API v2.

**Autenticação:** Header `apikey: {chave}` em todas as requisições.

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| POST | `/instance/create` | Criar instância (com webhook) |
| GET | `/instance/connect/{name}` | Gerar QR Code |
| GET | `/instance/connectionState/{name}` | Status da conexão |
| PUT | `/instance/restart/{name}` | Reiniciar instância |
| DELETE | `/instance/logout/{name}` | Desconectar (logout) |
| DELETE | `/instance/delete/{name}` | Deletar instância |
| GET | `/instance/fetchInstances` | Listar todas instâncias |
| POST | `/message/sendText/{name}` | Enviar texto |
| POST | `/message/sendMedia/{name}` | Enviar mídia (image/video/document) |
| POST | `/message/sendWhatsAppAudio/{name}` | Enviar áudio PTT |
| GET | `/chat/findChats/{name}` | Buscar chats |
| POST | `/chat/findMessages/{name}` | Buscar mensagens por JID |
| GET | `/chat/findContacts/{name}` | Buscar contatos |
| POST | `/chat/whatsappNumbers/{name}` | Verificar se números têm WhatsApp |
| PUT | `/chat/markMessageAsRead/{name}` | Marcar como lida |
| GET | `/chat/fetchProfilePictureUrl/{name}` | Foto de perfil |
| POST | `/chat/getBase64FromMediaMessage/{name}` | Download de mídia base64 |
| GET | `/group/fetchAllGroups/{name}` | Listar todos os grupos |

**Normalização de números:**
- Remove tudo exceto dígitos
- Se não começa com `55` e tem ≤11 dígitos, adiciona DDI `55`
- JID individual: `numero@s.whatsapp.net`
- JID grupo: `numero@g.us`

---

## PARTE 2: MÓDULO CRM

---

### 2.1 LISTA DE BOARDS

**Rota:** `/crm` ou `/crm/index`
**Permissões:** super_admin, attendant, whatsapp_agent, comercial

**Interface:**
- Header: "CRM" com links para Chat e botão "Novo Board"
- Grid de cards (col-md-6 col-lg-4) com:
  - Nome do board
  - Badge com total de cards
  - Descrição (se houver)
  - Criado por + tempo relativo
  - Card inteiro clicável → leva para `/crm/board/{id}`
- Se não há boards, mostra empty state com botão de criar

**Modal "Novo Board CRM":**
- Campo: Nome * (obrigatório)
- Campo: Descrição (opcional)
- Info: "Colunas padrão serão criadas automaticamente"
- Ao criar, cria 5 colunas padrão:
  1. **Novo Lead** (azul `#1565c0`)
  2. **Contato Feito** (laranja `#e65100`)
  3. **Em Negociação** (roxo `#7b1fa2`)
  4. **Fechado** (verde `#2e7d32`)
  5. **Perdido** (vermelho `#c62828`)
- Redireciona para o board criado

---

### 2.2 TELA DO BOARD (KANBAN)

**Rota:** `/crm/board/{boardId}`
**Permissões:** super_admin, attendant, whatsapp_agent, comercial

**Interface:**
- Header: Nome do board + descrição
- Botões: Voltar (Boards), + Coluna, + Card
- **Kanban horizontal** com scroll horizontal
- Cada coluna tem largura fixa de 280px

#### 2.2.1 Colunas

Cada coluna mostra:
- **Dot colorido** (cor da coluna) + **Nome** + **Badge** com contagem de cards
- **Menu (⋮):** Renomear, Excluir (super_admin)

**Criar coluna (modal):**
- Nome *
- Cor (color picker)
- Etiqueta (opcional) — vincula uma etiqueta à coluna
- Status (opcional): Novo, Em atendimento, Aguardando, Concluído, Perdido

#### 2.2.2 Cards (Leads)

**Visual do card na coluna:**
- Indicator de temperatura (bolinha colorida): Frio=azul, Morno=amarelo, Quente=vermelho
- **Nome** (contact_name ou title)
- **Badges:** Etiqueta (cor), Convertido ✅ (verde), Perdido ❌ (vermelho), Retomar (data), Em recuperação (roxo)
- Telefone
- Valor (da faixa de investimento do briefing, ou do campo value)
- Nome do responsável
- Card inteiro clicável → abre modal de detalhes

**Drag-and-drop:**
- Cards podem ser arrastados entre colunas
- Ao mover, atualiza `column_id` e `position`
- Registra atividade "Movido para [Nome da Coluna]"

**Modal "Novo Card":**
- Título/Nome *
- Telefone
- Valor (R$) — formatado, sincroniza com briefing
- Coluna (dropdown)
- Responsável (dropdown equipe)
- Etiqueta (dropdown)
- Status (dropdown)
- Descrição (textarea)

#### 2.2.3 Detalhes do Card (Modal/Painel)

Ao clicar em um card, abre modal ou painel com:

**Informações principais:**
- Título (editável)
- Descrição (editável)
- Telefone (editável)
- Valor R$ (editável, sincroniza com briefing)
- Responsável (dropdown)

**Ações:**
- **Converter lead** ✅ — marca `lead_outcome = 'converted'`, registra data e quem converteu
- **Perdido** ❌ — marca `lead_outcome = 'lost'`, registra data
- **Retomar contato em** — agendar follow-up:
  - Quantidade (número)
  - Unidade: minutos, horas, dias
  - Coluna alvo (para onde mover quando vencer)
- **Abrir chat** — link direto para `/whatsapp/chat/{contactId}`
- **Excluir card**
- **Salvar**

**Briefing comercial:**
- Se o card tem `contact_id`, mostra/edita o briefing do contato vinculado
- Exibe todas as informações do briefing (ver seção 1.2.7)

**Histórico de atividades:**
- Lista cronológica com:
  - Tipo: nota, movimentação, criação, atribuição
  - Descrição
  - Quem fez + quando
- Campo para adicionar nova nota/atividade

#### 2.2.4 Follow-Up (Retomada de Contato)

**Funcionalidade:**
- Ao definir "Retomar contato em X [minutos/horas/dias]":
  - Calcula a data/hora futura (`follow_up_at`)
  - Opcionalmente define a coluna de destino (`follow_up_column_id`)
  - Registra atividade "Retomada agendada para DD/MM/YYYY HH:mm → Coluna X"

**Processamento automático:**
- Toda vez que o board é aberto, executa `processFollowUps()`
- Busca cards onde `follow_up_at <= NOW()` e `lead_outcome = 'open'`
- Move cada card para a coluna alvo (ou primeira coluna como fallback)
- Marca `in_recovery = 1`
- Limpa campos de follow-up
- Registra atividade "Retomada de contato — movido para [Coluna] (Em recuperação)"

---

### 2.3 DASHBOARD CRM

**Rota:** `/crm/dashboard`
**Permissões:** super_admin, attendant, whatsapp_agent, comercial

#### 2.3.1 Contadores (primeira linha)

| Indicador | Cor |
|-----------|-----|
| Leads no CRM (total) | Azul |
| Com Etiqueta | Roxo |
| Em Aberto | Laranja |
| Convertidos | Verde |
| Perdidos | Vermelho |

#### 2.3.2 Valores (segunda linha)

| Indicador | Cor |
|-----------|-----|
| Valor Cotado (tudo) | Cinza escuro |
| Valor Convertido | Verde |
| Valor Perdido | Vermelho |
| Valor Recuperação/Agendado | Roxo |
| Ticket Médio (convertido) | Verde-azulado |

*Ticket médio = Valor Convertido / Quantidade Convertida*

#### 2.3.3 Gráficos

**Gráfico de Pizza (esquerda, 1/3):**
- Distribuição: Em Aberto × Convertidos × Perdidos
- Cores: Laranja / Verde / Vermelho
- Legenda na parte inferior

**Gráfico de Linha (direita, 2/3):**
- Evolução últimos 6 meses
- Linha verde: Leads convertidos por mês
- Linha vermelha: Leads perdidos por mês
- Fundo preenchido com transparência (area chart)
- Eixo Y começa em zero, sem decimais

Biblioteca utilizada: **Chart.js v4**

---

### 2.4 COMISSÕES

**Rota:** `/crm/commissions`
**Permissões:** 
- **super_admin:** vê todos os comerciais, pode filtrar por usuário
- **comercial:** vê apenas suas próprias comissões

#### 2.4.1 Regras de Comissão

- Cada usuário com role `comercial` tem um campo `commission_percent` (percentual de comissão)
- A comissão é calculada sobre o **valor dos leads convertidos** (`lead_outcome = 'converted'`) por aquele comercial (`converted_by = user_id`)
- Fórmula: `Comissão = SUM(valor_dos_leads) × commission_percent / 100`

#### 2.4.2 Interface

**Filtros:**
- Mês (input type month) — filtra pela data de conversão (`outcome_at`)
- Usuário (dropdown, só visível para super_admin)

**Totalizadores (cards no topo):**
| Indicador | Descrição |
|-----------|-----------|
| Total a pagar/receber (mês) | Soma de todas as comissões no período |
| Total Convertido | Soma dos valores de todos os leads convertidos |
| Comerciais Cadastrados | Quantidade de usuários role=comercial (só p/ admin) |

**Tabela de comissões:**
| Coluna | Descrição |
|--------|-----------|
| Comercial | Nome do usuário |
| % Comissão | Percentual configurado |
| Leads Convertidos | Quantidade (badge verde) |
| Valor Total | Soma dos valores dos leads |
| Comissão a Pagar | Valor calculado (destaque verde) |
| Expandir | Chevron para expandir detalhes |

**Expandir linha:**
- Ao clicar na linha, expande mostrando os leads convertidos individualmente:
  - Nome do contato
  - Telefone
  - Valor
  - Data de conversão
- Carregado via AJAX (lazy loading)

---

### 2.5 INTEGRAÇÃO WHATSAPP ↔ CRM

| Ação | Resultado |
|------|-----------|
| "Adicionar ao CRM" no painel de detalhes do chat | Cria card vinculado ao contato, usa briefing como fonte de dados |
| Alterar nome do contato no WhatsApp | Atualiza título do card no CRM |
| Alterar valor no card do CRM | Sincroniza com faixa de investimento do briefing |
| Alterar faixa de investimento no briefing | Sincroniza com valor do card |
| Excluir contato no WhatsApp | Desvincula card (contact_id = NULL), não exclui o card |
| Na lista de contatos, mostrar badge CRM | "Board › Coluna" como badge roxa |
| Card no CRM tem botão "Abrir chat" | Leva direto para o chat com aquele contato |

---

## PARTE 3: BANCO DE DADOS (Schema Completo)

---

### 3.1 Tabelas WhatsApp

#### whatsapp_instances
```sql
CREATE TABLE whatsapp_instances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    instance_name VARCHAR(100) NOT NULL UNIQUE,
    display_name VARCHAR(150) DEFAULT NULL,
    api_url VARCHAR(500) NOT NULL,
    api_key VARCHAR(500) NOT NULL,
    owner_phone VARCHAR(20) DEFAULT NULL,
    user_id INT DEFAULT NULL,
    connection_status ENUM('open','connected','close','connecting') DEFAULT 'close',
    qr_code TEXT DEFAULT NULL,
    is_default TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);
```

#### whatsapp_contacts
```sql
CREATE TABLE whatsapp_contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    instance_id INT NOT NULL,
    remote_jid VARCHAR(100) NOT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    contact_name VARCHAR(200) DEFAULT NULL,
    push_name VARCHAR(200) DEFAULT NULL,
    profile_picture_url TEXT DEFAULT NULL,
    is_group TINYINT(1) DEFAULT 0,
    internal_notes TEXT DEFAULT NULL,
    assigned_to INT DEFAULT NULL,
    service_status ENUM('novo','em_atendimento','aguardando','concluido') DEFAULT 'novo',
    last_message_at TIMESTAMP NULL DEFAULT NULL,
    is_archived TINYINT(1) DEFAULT 0,
    unread_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_instance_jid (instance_id, remote_jid),
    FOREIGN KEY (instance_id) REFERENCES whatsapp_instances(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
);
```

#### whatsapp_messages
```sql
CREATE TABLE whatsapp_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    instance_id INT NOT NULL,
    contact_id INT NOT NULL,
    remote_jid VARCHAR(100) NOT NULL,
    message_id VARCHAR(100) DEFAULT NULL,
    from_me TINYINT(1) DEFAULT 0,
    message_type ENUM('text','image','audio','video','document','sticker','location','contact','reaction','poll','list','unknown') DEFAULT 'text',
    message_text TEXT DEFAULT NULL,
    transcription TEXT DEFAULT NULL,
    media_url TEXT DEFAULT NULL,
    media_mime_type VARCHAR(100) DEFAULT NULL,
    media_filename VARCHAR(255) DEFAULT NULL,
    quoted_message_id VARCHAR(100) DEFAULT NULL,
    sender_name VARCHAR(200) DEFAULT NULL,
    participant_jid VARCHAR(100) DEFAULT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_read TINYINT(1) DEFAULT 0,
    is_deleted TINYINT(1) DEFAULT 0,
    ack_status ENUM('pending','sent','delivered','read','failed') DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_message_id (instance_id, message_id),
    KEY idx_contact (contact_id),
    KEY idx_jid_time (remote_jid, timestamp),
    FOREIGN KEY (instance_id) REFERENCES whatsapp_instances(id) ON DELETE CASCADE,
    FOREIGN KEY (contact_id) REFERENCES whatsapp_contacts(id) ON DELETE CASCADE
);
```

#### whatsapp_labels
```sql
CREATE TABLE whatsapp_labels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    color VARCHAR(7) DEFAULT '#6c757d',
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);
```

#### whatsapp_contact_labels
```sql
CREATE TABLE whatsapp_contact_labels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contact_id INT NOT NULL,
    label_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_contact_label (contact_id, label_id),
    FOREIGN KEY (contact_id) REFERENCES whatsapp_contacts(id) ON DELETE CASCADE,
    FOREIGN KEY (label_id) REFERENCES whatsapp_labels(id) ON DELETE CASCADE
);
```

#### whatsapp_quick_replies
```sql
CREATE TABLE whatsapp_quick_replies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shortcut VARCHAR(100) NOT NULL UNIQUE,
    message TEXT DEFAULT NULL,
    attachment_path TEXT DEFAULT NULL,
    attachment_name VARCHAR(255) DEFAULT NULL,
    attachment_mime VARCHAR(100) DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);
```

### 3.2 Tabelas CRM

#### crm_boards
```sql
CREATE TABLE crm_boards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description TEXT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);
```

#### crm_columns
```sql
CREATE TABLE crm_columns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    board_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    color VARCHAR(7) DEFAULT '#6c757d',
    label_id INT DEFAULT NULL,
    status ENUM('novo','em_atendimento','aguardando','concluido','perdido') DEFAULT NULL,
    position INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (board_id) REFERENCES crm_boards(id) ON DELETE CASCADE,
    FOREIGN KEY (label_id) REFERENCES whatsapp_labels(id) ON DELETE SET NULL
);
```

#### crm_cards
```sql
CREATE TABLE crm_cards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    column_id INT NOT NULL,
    contact_id INT DEFAULT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    value DECIMAL(10,2) DEFAULT NULL,
    label_id INT DEFAULT NULL,
    status ENUM('novo','em_atendimento','aguardando','concluido','perdido') DEFAULT NULL,
    lead_outcome ENUM('open','converted','lost') DEFAULT 'open',
    outcome_at DATETIME DEFAULT NULL,
    converted_by INT DEFAULT NULL,
    follow_up_at DATETIME DEFAULT NULL,
    follow_up_column_id INT DEFAULT NULL,
    in_recovery TINYINT(1) DEFAULT 0,
    position INT DEFAULT 0,
    assigned_to INT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (column_id) REFERENCES crm_columns(id) ON DELETE CASCADE,
    FOREIGN KEY (contact_id) REFERENCES whatsapp_contacts(id) ON DELETE SET NULL,
    FOREIGN KEY (label_id) REFERENCES whatsapp_labels(id) ON DELETE SET NULL,
    FOREIGN KEY (follow_up_column_id) REFERENCES crm_columns(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (converted_by) REFERENCES users(id) ON DELETE SET NULL
);
```

#### crm_card_activities
```sql
CREATE TABLE crm_card_activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    card_id INT NOT NULL,
    user_id INT DEFAULT NULL,
    activity_type ENUM('note','move','create','assign','label') DEFAULT 'note',
    description TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (card_id) REFERENCES crm_cards(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);
```

#### commercial_briefings
```sql
CREATE TABLE commercial_briefings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contact_id INT NOT NULL UNIQUE,
    need TEXT DEFAULT NULL,
    main_pain TEXT DEFAULT NULL,
    current_solution TEXT DEFAULT NULL,
    expected_goal TEXT DEFAULT NULL,
    urgency VARCHAR(50) DEFAULT NULL,
    investment_range VARCHAR(100) DEFAULT NULL,
    decision_level VARCHAR(100) DEFAULT NULL,
    lead_temperature ENUM('frio','morno','quente') DEFAULT NULL,
    main_objection TEXT DEFAULT NULL,
    next_step TEXT DEFAULT NULL,
    next_contact_date DATE DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (contact_id) REFERENCES whatsapp_contacts(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);
```

### 3.3 Tabela de Usuários (campos relevantes para WhatsApp/CRM)

```sql
-- Campos adicionais na tabela users:
ALTER TABLE users ADD COLUMN role ENUM('super_admin','admin','attendant','whatsapp_agent','comercial','client') DEFAULT 'client';
ALTER TABLE users ADD COLUMN commission_percent DECIMAL(5,2) DEFAULT 0;
ALTER TABLE users ADD COLUMN is_active TINYINT(1) DEFAULT 1;
```

---

## PARTE 4: PERMISSÕES E ROLES

---

### 4.1 Roles do Sistema

| Role | Acesso WhatsApp | Acesso CRM | Acesso Instâncias | Deletar | Comissões |
|------|-----------------|-------------|-------------------|---------|-----------|
| **super_admin** | ✅ Total | ✅ Total | ✅ CRUD completo | ✅ Tudo | ✅ Ver todos |
| **attendant** | ✅ Chat/Contatos | ✅ Board/Cards | ❌ Só ver | ❌ Não pode | ❌ |
| **whatsapp_agent** | ✅ Chat/Contatos | ✅ Board/Cards | ❌ Só ver | ❌ Não pode | ❌ |
| **comercial** | ✅ Chat/Contatos | ✅ Board/Cards | ❌ Só ver | ❌ Não pode | ✅ Só próprias |
| **client** | ❌ Sem acesso | ❌ Sem acesso | ❌ | ❌ | ❌ |

### 4.2 Restrições Específicas

- **Criar/editar/excluir instâncias:** apenas super_admin
- **Desconectar instância:** apenas super_admin
- **Definir instância padrão:** apenas super_admin
- **Excluir board:** apenas super_admin
- **Excluir coluna:** apenas super_admin
- **Excluir contato permanentemente:** apenas super_admin
- **Ver comissões de todos:** apenas super_admin
- **Comercial vê apenas suas comissões**
- **Todos os roles WhatsApp/CRM podem:** enviar mensagens, gerenciar contatos, criar/mover cards, adicionar notas, converter/perder leads

---

## PARTE 5: APIs / ENDPOINTS

---

### 5.1 Endpoints WhatsApp

| Rota | Método | Descrição |
|------|--------|-----------|
| `/whatsapp` | GET | Tela de instâncias/conexão |
| `/whatsapp/chat` | GET | Tela de chat |
| `/whatsapp/chat/{id}` | GET | Chat com contato específico |
| `/whatsapp/contacts` | GET | Lista contatos (AJAX) - filtros via query string |
| `/whatsapp/messages/{contactId}` | GET | Mensagens do contato (paginado) |
| `/whatsapp/poll/{contactId}` | GET | Polling (novas msgs + deletadas) |
| `/whatsapp/messageStatuses/{contactId}` | GET | Status ACK das mensagens |
| `/whatsapp/send` | POST | Enviar mensagem texto |
| `/whatsapp/sendMedia` | POST | Enviar arquivo/mídia |
| `/whatsapp/sendQuickReply` | POST | Enviar resposta rápida com anexo |
| `/whatsapp/contactDetail/{id}` | GET | Detalhes do contato |
| `/whatsapp/updateContact/{id}` | POST | Atualizar nome/notas/atribuição |
| `/whatsapp/updateServiceStatus/{id}` | POST | Alterar status de atendimento |
| `/whatsapp/toggleLabel` | POST | Adicionar/remover etiqueta |
| `/whatsapp/createLabel` | POST | Criar nova etiqueta |
| `/whatsapp/startConversation` | POST | Iniciar conversa com número novo |
| `/whatsapp/deleteContact/{id}` | POST | Excluir contato permanente |
| `/whatsapp/transcribeAudio/{msgId}` | POST | Transcrever áudio (Whisper) |
| `/whatsapp/quickReplies` | GET | Listar respostas rápidas |
| `/whatsapp/saveQuickReply` | POST | Criar/editar resposta rápida |
| `/whatsapp/deleteQuickReply/{id}` | POST | Excluir resposta rápida |
| `/whatsapp/createInstance` | POST | Criar instância |
| `/whatsapp/connect/{id}` | GET | Gerar QR Code |
| `/whatsapp/status/{id}` | GET | Verificar status |
| `/whatsapp/disconnect/{id}` | POST | Desconectar |
| `/whatsapp/setDefault/{id}` | POST | Definir como padrão |
| `/whatsapp/deleteInstance/{id}` | POST | Excluir instância |
| `/whatsapp/updateInstance/{id}` | POST | Editar instância |
| `/whatsapp/syncGroups` | POST | Sincronizar nomes dos grupos |
| `/whatsapp/syncPhotos` | POST | Sincronizar fotos de perfil |
| `/whatsapp/registerWebhookEvents/{id}` | POST | Re-registrar webhook |
| `/whatsapp/getBriefing/{contactId}` | GET | Buscar briefing |
| `/whatsapp/saveBriefing/{contactId}` | POST | Salvar briefing |
| `/whatsapp/addToCrm` | POST | Criar card CRM do contato |
| `/whatsapp/notifications` | GET | Notificações não lidas |
| `/whatsapp/webhook` | POST | Webhook Evolution API (público) |

### 5.2 Endpoints CRM

| Rota | Método | Descrição |
|------|--------|-----------|
| `/crm` | GET | Lista de boards |
| `/crm/board/{id}` | GET | Kanban do board |
| `/crm/dashboard` | GET | Dashboard com stats e gráficos |
| `/crm/commissions` | GET | Tela de comissões |
| `/crm/commissionLeads/{userId}` | GET | Leads convertidos por usuário (AJAX) |
| `/crm/createBoard` | POST | Criar board |
| `/crm/deleteBoard/{id}` | POST | Excluir board |
| `/crm/listBoards` | GET | JSON com todos boards + colunas |
| `/crm/createColumn` | POST | Criar coluna |
| `/crm/updateColumn/{id}` | POST | Renomear/alterar cor |
| `/crm/deleteColumn/{id}` | POST | Excluir coluna |
| `/crm/createCard` | POST | Criar card |
| `/crm/updateCard/{id}` | POST | Atualizar card |
| `/crm/moveCard` | POST | Mover card (drag-and-drop) |
| `/crm/deleteCard/{id}` | POST | Excluir card |
| `/crm/cardDetail/{id}` | GET | Detalhes + atividades + briefing |
| `/crm/addNote/{cardId}` | POST | Adicionar nota/atividade |
| `/crm/convertLead/{id}` | POST | Converter lead |
| `/crm/lostLead/{id}` | POST | Marcar como perdido |
| `/crm/setFollowUp/{id}` | POST | Agendar retomada |
| `/crm/runFollowUps` | POST | Processar retomadas vencidas |

---

## PARTE 6: DETALHES DE IMPLEMENTAÇÃO FRONTEND

---

### 6.1 Tecnologias utilizadas

- **HTML/CSS/JS** puro (sem frameworks frontend como React/Vue)
- **Bootstrap 5** para layout/componentes
- **Bootstrap Icons** para ícones
- **Chart.js v4** para gráficos do dashboard
- **Fetch API** para chamadas AJAX (sem jQuery)
- **SortableJS** (ou equivalente) para drag-and-drop no kanban
- **CSS puro** para animações e transições

### 6.2 Comportamentos JavaScript chave

**Chat - Envio de mensagem:**
```javascript
// Shift+Enter = pular linha
// Enter (sem Shift) = enviar
textarea.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        handleSend();
    }
});
```

**Chat - Autocomplete de respostas rápidas:**
```javascript
// Ao digitar no textarea, se começa com /
textarea.addEventListener('input', () => {
    const text = textarea.value;
    if (text.startsWith('/') && text.length > 1) {
        const query = text.substring(1).toLowerCase();
        // Filtra respostas rápidas que começam com 'query'
        // Exibe dropdown de sugestões
    }
});
```

**Chat - Formatação WhatsApp:**
```javascript
function formatWhatsApp(text) {
    let html = escapeHtml(text);
    // Monospace ```text```
    html = html.replace(/```([\s\S]+?)```/g, '<code>$1</code>');
    // Bold *text*
    html = html.replace(/\*((?!\s)([^\n*]+?)(?<!\s))\*/g, '<strong>$1</strong>');
    // Italic _text_
    html = html.replace(/_((?!\s)([^\n_]+?)(?<!\s))_/g, '<em>$1</em>');
    // Strikethrough ~text~
    html = html.replace(/~((?!\s)([^\n~]+?)(?<!\s))~/g, '<del>$1</del>');
    // Mentions @número
    html = html.replace(/@(\d{10,15})/g, '<span class="wpp-mention">@$1</span>');
    // Newlines
    html = html.replace(/\n/g, '<br>');
    return html;
}
```

**Kanban - Drag and Drop:**
```javascript
// Cada coluna é um container droppable
// Ao soltar o card em outra coluna:
// POST /crm/moveCard com card_id, column_id, position
```

**Lightbox (imagem):**
```javascript
function openLightbox(imgUrl) {
    document.getElementById('img-lightbox-img').src = imgUrl;
    document.getElementById('img-lightbox').classList.add('open');
}
function closeLightbox(event) {
    if (event.target === document.getElementById('img-lightbox') || event.target.classList.contains('wpp-lightbox-close')) {
        document.getElementById('img-lightbox').classList.remove('open');
    }
}
```

### 6.3 Layout Responsivo (Mobile)

**Chat no mobile (≤768px):**
- Layout single-page com transição entre painéis
- Inicialmente mostra apenas a lista de contatos (100% width)
- Ao clicar em contato: desliza para o chat (100% width)
- Botão "voltar" retorna à lista
- Painel de detalhes também ocupa 100% width
- Hamburguer menu para abrir sidebar

**Kanban no mobile:**
- Scroll horizontal mantido
- Colunas com scroll individual

---

## PARTE 7: FLUXOS DE USO COMPLETOS

---

### 7.1 Fluxo: Receber mensagem e atender

1. Mensagem chega via webhook → salva no banco → incrementa não lidas
2. Frontend atualiza via polling → contato sobe para o topo da lista
3. Atendente abre o contato → mensagens carregam → não lidas são zeradas
4. Atendente muda status para "Em atendimento"
5. Atendente responde (com assinatura ativa: `*João:*\nmensagem`)
6. Contato é automaticamente atribuído ao atendente
7. Atendente adiciona etiqueta (ex: "VIP")
8. Atendente preenche briefing comercial
9. Atendente adiciona ao CRM (seleciona board e coluna)
10. Ao finalizar, muda status para "Concluído"

### 7.2 Fluxo: Gestão do CRM

1. Acessa `/crm` → vê lista de boards
2. Abre board → Kanban com colunas e cards
3. Cards podem ser arrastados entre colunas
4. Abre detalhes do card → vê briefing, atividades, dados
5. Define "Retomar contato em 3 dias" → card some (vai pro limbo)
6. Após 3 dias → card aparece automaticamente na primeira coluna com badge "Em recuperação"
7. Atendente retoma contato (abre chat diretamente do card)
8. Ao fechar negócio → "Converter lead" → registra valor e responsável
9. Dashboard e comissões são atualizados automaticamente

### 7.3 Fluxo: Configurar nova instância

1. super_admin acessa `/whatsapp` → clica "Nova Instância"
2. Preenche nome (ex: "vendas"), display name, marca "usar credenciais da padrão"
3. Opcionalmente vincula a um usuário específico
4. Sistema cria na Evolution API + salva no banco
5. Clica "Conectar" → aparece QR Code
6. Escaneia com WhatsApp → status muda para "Conectado"
7. Webhook é registrado automaticamente → mensagens começam a chegar

### 7.4 Fluxo: Iniciar conversa nova

1. No chat, clica "Iniciar conversa"
2. Digita número com DDD (ex: 5517999999999)
3. Opcionalmente preenche nome
4. Sistema verifica se o número tem WhatsApp
5. Se sim: busca nome e foto do perfil, cria contato, abre chat
6. Se não: mostra erro "Número não encontrado no WhatsApp"

---

## PARTE 8: CONFIGURAÇÕES DO SISTEMA (Settings)

---

### 8.1 Campos relacionados a WhatsApp/CRM nas configurações

| Chave (setting_key) | Descrição | Onde é usado |
|---------------------|-----------|--------------|
| `openai_api_key` | Chave da API OpenAI | Transcrição de áudio (Whisper) |
| `whatsapp_default_group_jid` | JID do grupo padrão para notificações | WhatsappNotifier |
| `whatsapp_group_notify_enabled` | Habilitar notificações em grupo (0/1) | WhatsappNotifier |
| `evolution_api_url` | URL da Evolution API (legado/global) | EvolutionApi fallback |
| `evolution_api_key` | API Key da Evolution API (legado/global) | EvolutionApi fallback |
| `evolution_instance_name` | Nome da instância (legado/global) | EvolutionApi fallback |

### 8.2 Na tela de Settings (super_admin)

- Dropdown para selecionar grupo padrão (lista todos os grupos conhecidos do WhatsApp)
- Checkbox para habilitar/desabilitar notificações em grupo
- Campo para OpenAI API Key (usado na transcrição)

---

## PARTE 9: DETALHES ADICIONAIS IMPORTANTES

---

### 9.1 Deduplicação de contatos

Ao receber mensagem via webhook, o sistema evita criar contatos duplicados:
1. Primeiro busca pelo JID exato (`instance_id + remote_jid`)
2. Se não encontra E não é grupo, busca pelo último 8 dígitos do telefone na mesma instância
3. Isso resolve o problema do 9° dígito (ex: 55179... vs 5517999...)

### 9.2 Upsert de contato (regra de nome)

- Na **criação**: usa `pushName` como `contact_name`
- Na **atualização**: **NUNCA** sobrescreve `contact_name` se já foi definido (pode ter sido editado manualmente pelo atendente)
- Apenas atualiza: `phone`, `push_name`, `is_group`, `last_message_at`

### 9.3 Separadores de data nas mensagens

No chat, entre mensagens de dias diferentes, aparece um separador:
- "Hoje" / "Ontem" / "Anteontem" / data formatada ("15/07/2025")
- Visual: badge centralizado com fundo azul claro, texto escuro

### 9.4 Indicadores de entrega (ACK)

| Status | Visual | Significado |
|--------|--------|-------------|
| `pending` | ⏳ (relógio) | Enviando... |
| `sent` | ✓ (cinza) | Enviado ao servidor |
| `delivered` | ✓✓ (cinza) | Entregue ao destinatário |
| `read` | ✓✓ (azul) | Lido pelo destinatário |
| `failed` | ❌ (vermelho) | Falha no envio |

### 9.5 Mensagens deletadas

Quando o remetente apaga uma mensagem no WhatsApp:
- Webhook envia evento `protocolMessage` com tipo `REVOKE`
- Sistema marca `is_deleted = 1` na mensagem
- Frontend mostra: 🚫 "Mensagem apagada" (itálico, opacidade reduzida)

### 9.6 Reações

- Quando alguém reage a uma mensagem, chega como `reactionMessage`
- Emoji da reação + ID da mensagem reagida
- Frontend exibe badge flutuante com emoji no canto inferior da mensagem
- Se reação é removida (texto vazio), ignora

### 9.7 Stickers/Figurinhas

- Tipo `stickerMessage`, formato WebP
- Download via base64 (mesma lógica de mídia)
- Exibido como imagem pequena (120px max), fundo transparente, sem borda

### 9.8 Auto-grow do textarea

- O textarea de input cresce automaticamente conforme o usuário digita
- Min-height: 34px (1 linha)
- Max-height: 120px (~5 linhas)
- Depois do max, ativa scroll interno

### 9.9 Envio de áudio via sistema

- O sistema envia áudio como PTT (Push to Talk) usando endpoint específico da Evolution API
- O formato esperado é base64 do arquivo de áudio
- Endpoint: `/message/sendWhatsAppAudio/{instanceName}`

### 9.10 Fallback de envio de mídia

Ao enviar mídia (imagem, vídeo, documento):
1. **Primeiro tenta** enviar via URL pública do arquivo (mais rápido)
2. **Se falhar**, converte para base64 e tenta novamente
3. Isso garante compatibilidade com diferentes configurações de rede/DNS

### 9.11 Persistência imediata antes do envio

Toda mídia é salva no banco com status `pending` ANTES de enviar via API:
- Garante que a mensagem aparece na conversa imediatamente
- Se a requisição à API demorar ou falhar, o usuário já vê a mensagem
- Status é atualizado para `sent` ou `failed` após a resposta da API
- Usa `ignore_user_abort(true)` para completar o envio mesmo se o usuário sair da página

### 9.12 Armazenamento de mídia

- Pasta base: `public/uploads/whatsapp_media/`
- Organização por mês: `whatsapp_media/2025-07/`
- Nomes únicos: `uniqid()_timestamp.extensão`
- Respostas rápidas: `public/uploads/quick_replies/`
- Tamanho máximo por arquivo: não há limite explícito (depende do PHP/servidor)

---

## PARTE 10: CORES E DESIGN

---

### 10.1 Paleta de cores principal

| Uso | Cor | Hex |
|-----|-----|-----|
| Primária (botões, links) | Verde-teal | `#00BFA6` |
| Primária escura (hover) | Verde-teal escuro | `#00897B` |
| Primária clara (background) | Verde-teal claro | `#e0f7f4` |
| Background chat | Bege WhatsApp | `#efeae2` |
| Mensagem minha | Verde claro | `#d9fdd3` |
| Mensagem do outro | Branco | `#ffffff` |
| Perigo | Vermelho | `#c62828` |
| Sucesso/Convertido | Verde | `#2e7d32` |
| Aviso/Em aberto | Laranja | `#ff9800` |
| Info | Azul | `#2196f3` |
| Lead Frio | Azul | `#3b82f6` |
| Lead Morno | Amarelo | `#f59e0b` |
| Lead Quente | Vermelho | `#ef4444` |
| Recuperação | Roxo | `#7e57c2` |

### 10.2 Fontes/Tamanhos

- Font-size base: 0.85rem para textos de chat
- Nomes de contato: 0.83rem, font-weight: 500
- Horários/metadados: 0.62-0.72rem
- Badges: 0.6-0.65rem
- Avatares: 38×38px (lista), 70×70px (detalhes)

---

## PARTE 11: RESUMO DE FUNCIONALIDADES (CHECKLIST)

---

### WhatsApp — Funcionalidades

- [x] Múltiplas instâncias por conta (cada uma é um número diferente)
- [x] Instância padrão (apenas uma por vez)
- [x] Vinculação de instância a um usuário específico
- [x] Usar API Key e URL da instância padrão automaticamente
- [x] Criar instância (nome + credenciais + vínculo opcional)
- [x] Conectar via QR Code
- [x] Desconectar instância
- [x] Verificar status da conexão
- [x] Editar instância (display name, url, key, user)
- [x] Excluir instância (remove da API + banco cascade)
- [x] Definir como padrão
- [x] Chat estilo WhatsApp Web (3 colunas desktop, single-page mobile)
- [x] Listagem de contatos com foto de perfil, nome, prévia da última mensagem
- [x] Listagem de grupos separada (aba)
- [x] Nome real do grupo (subject, não pushName)
- [x] Foto de perfil do contato e do grupo
- [x] Busca de contatos por nome/telefone
- [x] Filtro por atendente atribuído
- [x] Filtro por etiqueta
- [x] Filtro por status (Novo, Em atendimento, Aguardando, Concluído)
- [x] Status de atendimento por contato
- [x] Atribuição de contato a atendente (manual e automática)
- [x] Toggle "Assinar" (nome de quem envia aparece em negrito)
- [x] Pular linha com Shift+Enter
- [x] Reconhecer e renderizar negrito (*), itálico (_), tachado (~), mono (```)
- [x] Envio de emojis (picker por categoria)
- [x] Envio de arquivos (qualquer tipo)
- [x] Barra de staging com preview antes de enviar mídia
- [x] Imagem clicável abre lightbox NA MESMA tela (não em outra guia)
- [x] Player de áudio customizado com waveform
- [x] Transcrição de áudio (OpenAI Whisper)
- [x] Documento com card formatado (ícone, nome, tipo) + botões Ver e Baixar
- [x] Figurinhas/stickers (imagem WebP pequena)
- [x] Reações (emoji badge sobre a mensagem)
- [x] Mensagens deletadas (estilo "mensagem apagada")
- [x] Separação por dia (Hoje, Ontem, data)
- [x] Indicadores de entrega (✓, ✓✓, ✓✓ azul, ❌)
- [x] Painel de detalhes do contato (lateral)
- [x] Editar nome do contato
- [x] Atribuir a uma pessoa
- [x] Observações internas
- [x] Etiquetas (criar, adicionar, remover)
- [x] Briefing comercial completo (12+ campos)
- [x] Adicionar ao CRM direto do chat
- [x] Excluir contato permanentemente
- [x] Iniciar nova conversa (digitar número + nome opcional, puxa nome e foto)
- [x] Respostas rápidas (digitar / no chat = autocomplete)
- [x] Gerenciar respostas rápidas (CRUD com atalho + mensagem + anexo)
- [x] Webhook para recebimento de mensagens em tempo real
- [x] Sincronizar grupos (um clique)
- [x] Sincronizar fotos de perfil (um clique)
- [x] Re-registrar webhook (um clique)
- [x] Mensagens em grupo mostram quem enviou (sender_name)
- [x] Contatos agrupados por status na lista
- [x] Badge de não lidas
- [x] Badge CRM na lista de contatos (Board › Coluna)
- [x] Notificações WhatsApp (envio automático pelo sistema)

### CRM — Funcionalidades

- [x] Criar múltiplos boards (CRMs independentes)
- [x] Cada board com múltiplas colunas (editáveis)
- [x] Colunas padrão ao criar board (Novo Lead, Contato Feito, Em Negociação, Fechado, Perdido)
- [x] Criar novas colunas (nome + cor + etiqueta + status opcionais)
- [x] Renomear colunas
- [x] Excluir colunas (super_admin)
- [x] Drag-and-drop de cards entre colunas
- [x] Criar cards (título, telefone, valor, coluna, responsável, etiqueta, status, descrição)
- [x] Editar cards (todos os campos)
- [x] Excluir cards
- [x] Detalhes do card (modal com todas as informações)
- [x] Histórico de atividades do card (notas, movimentações, criação)
- [x] Adicionar notas ao card
- [x] Responsável por card
- [x] Valor monetário (R$) sincronizado com briefing
- [x] Telefone no card
- [x] Título/Nome do card
- [x] Descrição do card
- [x] Converter lead (✅ marca como convertido, registra quem e quando)
- [x] Perder lead (❌ marca como perdido, registra quando)
- [x] Retomada de contato ("retomar em X minutos/horas/dias") — agendamento
- [x] Processamento automático de retomadas vencidas (move para coluna alvo)
- [x] Badge "Em recuperação" nos cards retomados
- [x] Abrir chat diretamente do card (link para WhatsApp)
- [x] Vínculo com contato WhatsApp (contact_id)
- [x] Briefing comercial acessível do card
- [x] Indicador de temperatura do lead (Frio/Morno/Quente com bolinha colorida)
- [x] Etiqueta por card (badge colorida)
- [x] Dashboard com contadores (total, aberto, convertidos, perdidos)
- [x] Dashboard com valores (cotado, convertido, perdido, recuperação, ticket médio)
- [x] Dashboard com gráfico de pizza (distribuição)
- [x] Dashboard com gráfico de linha (evolução 6 meses)
- [x] Comissões por usuário comercial
- [x] Percentual de comissão configurável por usuário
- [x] Cálculo automático: valor convertido × percentual
- [x] Filtro por mês e por comercial
- [x] Expansão inline mostrando leads convertidos de cada comercial
- [x] Totalizadores (total a pagar, total convertido)
- [x] Soft-delete de boards (is_active = 0)

---

## PARTE 12: OBSERVAÇÕES FINAIS

---

### 12.1 Sobre a Evolution API

- O sistema integra via **Evolution API v2** (WhatsApp via Baileys/Multi-device)
- A Evolution API é um serviço separado que precisa estar rodando (self-hosted ou cloud)
- Todos os envios e recebimentos passam por ela
- O webhook da Evolution API bate no endpoint público do sistema

### 12.2 Sobre o OpenAI (Transcrição)

- Usa a API do OpenAI (modelo `whisper-1`) para transcrição de áudios
- Idioma fixo: `pt` (português)
- A chave é configurada nas Settings do sistema
- A transcrição é cacheada no banco (não refaz se já tem)

### 12.3 Performance

- Mensagens carregam 50 por vez (infinite scroll)
- Polling a cada poucos segundos (não websocket)
- Mídia é salva localmente (não depende de CDN)
- Índices no banco: `(contact_id)`, `(remote_jid, timestamp)`, `(instance_id, message_id)` UNIQUE

### 12.4 Segurança

- Webhook é público (sem autenticação) — aceita qualquer POST da Evolution API
- Todas as rotas internas verificam role do usuário (`requireRole()`)
- Upload de arquivos: sem validação de tamanho explícita (depende do PHP)
- Nomes de arquivo sanitizados com `uniqid() + timestamp`

---

*Documento gerado automaticamente a partir do código-fonte do sistema helpdeskON.*
*Última atualização: Agosto 2026*
