# Escopo Técnico Global — Esteira Comercial & Reuniões (helpdeskON)

> Documento vivo. Guia único de execução da demanda que vai de **Reuniões/Gravação →
> Captação → Processo Comercial → Proposta → Contrato → Financeiro → Onboarding →
> Provisionamento → Projeto/Entrega**, reaproveitando a mesma base para **contratação
> de prestadores**.
>
> Regra de ouro: **analisar e reaproveitar o que já existe**, não criar estrutura
> paralela. Toda alteração passa pelo gate de testes (`.kiro/steering/testes.md` e
> `.kiro/steering/pre-producao.md`) antes de ir para produção.

---

## 0. Como vamos trabalhar

- Branch de **desenvolvimento**; produção só depois de tudo verde.
- Implementação **por fases**, cada fase pequena e testável. Nada de subir tudo de uma vez.
- A cada fase: `php -l` nos arquivos → recriar `helpdesk_on_test` se mudou schema →
  PHPUnit (unit + integration) → Playwright/E2E quando houver UI → reportar passou/falhou.
- Regras puras de decisão viram classe testável (padrão já usado no projeto:
  `AgendaRules`, `CrmRules`, `RdoRules`, `Permissions`, `TicketAccess`, `ImageUploadRules`).
- Migrations SQL numeradas e **idempotentes** (padrão `information_schema` + `PREPARE`,
  igual migrations 122/126).
- Integrações externas reais (WhatsApp/e-mail/ClickSign/Asaas/LRV/GitHub) **não são
  testáveis no ambiente**: validamos as regras internas por teste e marcamos a parte
  externa como **validação manual**.

---

## 1. Arquitetura atual (o que já existe)

Projeto PHP, **MVC próprio**. Autoload simples em `public/index.php` (core → controllers → models).
Banco MySQL com **migrations SQL manuais numeradas** em `migrations/`. Front com Bootstrap 5.

### 1.1 Autorização / Papéis
- `app/core/Permissions.php` é a **fonte única** de autorização por papel.
  - Papéis: `super_admin`, `developer`, `attendant`, `analyst`, `comercial`,
    `marketing`, `whatsapp_agent`, `client`.
  - `super_admin`/`developer` = acesso total. Demais = módulos segmentados.
  - Módulos relevantes já existentes: `crm`, `agenda`, `videocall`, `companies`,
    `users`, `tickets`, `tickets_create`, `planning`, `client_tickets`,
    `client_schedule`, `subusers`, `settings`, `notifications`.
- **Toda tela/ação nova desta demanda adiciona módulos aqui** (ex.: `commercial`,
  `proposals`, `contracts`, `finance`, `onboarding`, `credentials`, `projects`),
  nunca cria permissão paralela.

### 1.2 CRM (Kanban genérico — reaproveitável como esteira)
- Tabelas (migration 013 + 021/022/023/024/026/059):
  - `crm_boards` (id, name, description, created_by, is_active, visibility).
  - `crm_columns` (id, board_id, name, color, position) → **estágios do funil por NOME**.
  - `crm_cards` (id, column_id, contact_id→`whatsapp_contacts`, title, description,
    phone, value DECIMAL(10,2), position, assigned_to, created_by, lead_outcome
    ENUM('open','converted','lost'), outcome_at, follow_up_at, in_recovery,
    converted_by, prospected_by, label_id, archived).
  - `crm_card_activities` (card_id, user_id, activity_type, description) → timeline por card.
- `app/models/CrmBoard.php` + `app/core/CrmRules.php` (regras puras):
  `moveCard`/`markOutcomeByContact` (anti-regressão, idempotente), métricas,
  follow-up. **Base ideal da esteira**: um board com as colunas de cada etapa.

### 1.3 Lead vs Empresa vs Cliente (gap de ponte)
- **Lead/potencial cliente** = `whatsapp_contacts` (migration 013) + enriquecimento em
  `commercial_briefings` (migration 020: need, main_pain, current_solution,
  expected_goal, urgency, investment_range, decision_level, lead_temperature,
  main_objection, next_step, next_contact_date, notes).
- Captação externa: `opportunities` (061, model `Opportunity`), `apollo_leads` (060).
- **Cliente/entrega** = `companies` (006) + `users` com `role='client'` e `company_id`.
  `CompaniesController::store` já cria empresa + usuário dono + convite de senha.
- **GAP crítico:** não há FK/ponte entre `whatsapp_contacts` (lead) e
  `companies`/`users` (cliente). A conversão lead→cliente **não está modelada**.
  A esteira precisa fechar isso (ver Fase 9).

### 1.4 Agenda / Reuniões
- `agenda_meetings` (040 + alters): meeting_type (`comercial`/`operacional`/`externo`/
  `interno`), contact_id (lead), client_name/phone/email (snapshot), assigned_to,
  status (`a_agendar`…`convertida`…`cancelada`), meeting_at, meet_link, google_event_id,
  external_guests(JSON). `agenda_meeting_participants` (042) = participantes internos.
- `AgendaController::create()` cria reunião (nova/sala) e, com `use_video_room`,
  chama `createSystemVideoRoom()`.
- `AgendaRules` centraliza enums/normalizações. `AgendaMeeting` é o model.

### 1.5 Sala de vídeo nativa, gravação, transcrição, resumo
- Tabelas (117): `video_rooms` (token, meeting_id, allow_recording **DEFAULT 1
  hardcoded**, visibility, max_participants, allow_presentation, status),
  `video_room_participants`, `video_room_signals`, `video_recordings`
  (file_path, duration_sec, token). (122/124): `transcript`, `summary`,
  `transcribe_status`, `transcribed_at`, `transcript_json`.
- `VideocallController.php`: sinalização (long-poll MySQL), gravação por chunks
  (`recChunk`/`recFinalize`), upload inteiro (`upload`), transcrição
  (`transcribeChunk`/`saveTranscript`, Whisper via `OpenAiClient`), resumo (chat
  `gpt-4o-mini`), `injectWebmDuration`, `recoverOrphanRecordings`, `streamFile` (Range).
- `app/views/videocall/room.php`: `MediaRecorder.start(4000)` sobre canvas composto +
  áudio mixado; `uploadChunk`/`uploadRecording`; `maybeAutoStopRecording`;
  `layoutGrid`/`applyGridSpans`.
- `app/views/videocall/watch.php`: transcreve no navegador cortando por `audio.duration`.

### 1.6 Planejamento (card interno) e Demandas (tickets)
- `tickets` (001) = demanda do cliente. `planning_cards` (008) = card interno,
  com `ticket_id` (vínculo opcional) e campos CX Hub (032: cx_hub_number, branch_name,
  pr_number). Status já incluem `homologacao` (010) e `aprovado_producao` (012).
- Já existe o conceito de vincular demanda do cliente ↔ card interno (via `ticket_id`).

### 1.7 PIN de acesso externo (parcial — precisa evoluir)
- Migration 126: `users.external_pin` VARCHAR(4) UNIQUE.
- Hoje o PIN é **do atendente/equipe** (`UsersController`, `User::findByPin`), usado em
  `/solicitacaoexterna` para um cliente **sem conta** criar demanda **em nome do
  atendente**. **Não é** o PIN-do-cliente que a reunião descreveu (login simplificado
  do próprio cliente que cai na página de nova demanda). Precisa ser repensado (Fase 9)
  **sem quebrar** o fluxo atual de `/solicitacaoexterna`.

### 1.8 Notificações e Auditoria
- `notifications` (001): user_id, title, message, type. Usado em todo o sistema
  (`AgendaController::notify`, etc.). **Reutilizar** para os eventos da esteira.
- WhatsApp: `WhatsappNotifier` / `EvolutionApi`. E-mail: `Mailer` / `EmailMessageService`
  (+ `email_signatures`, 114). Logs: `Logger`, `ActivityLogger`/`ActivityLog`.
- **Reaproveitar tudo isso** para notificação e histórico/auditoria.

### 1.9 Testes
- PHPUnit em `tests/Unit` (regras puras) e `tests/Integration` (contra `helpdesk_on_test`).
  Bootstrap força `APP_ENV=testing`. `run_tests.bat` grava `phpunit_result.txt`.
- Padrões a espelhar: `CrmRulesTest`, `AgendaRulesTest`, `PermissionsTest` (unit);
  `CrmBoardTest`, `AgendaMeetingTest`, `CompanyUserTest`, `VideoRoomTest` (integration,
  com setUp/tearDown criando os próprios dados e FKs pai).
- E2E Playwright em `tests-e2e/*.spec.ts`, servidor PHP embutido porta 8199.

---

## 2. Gaps (o que não existe e será criado)

| Área | Situação | Fase |
|------|----------|------|
| Correção duração da gravação | WebM concatenado quebra timeline (~1min) | 0 |
| Layout 3 participantes | 2+1 esticado (intencional hoje) | 0 |
| Auto-encerrar gravação server-side | Só preguiçoso ao abrir lista | 0 |
| Gravação automática na reunião | `allow_recording` fixo, sem `auto_record` | 1 |
| Minuta/ata automática + PDF + envio | Resumo existe (fraco), minuta não | 2 |
| Catálogo de serviços pré-cadastrados | Não existe | 3 |
| Proposta/Orçamento estruturado | Não existe (só `crm_cards.value`) | 3 |
| Contrato + modelos + ClickSign | Não existe | 4 |
| Financeiro + Asaas (3 contas) | Não existe | 5 |
| Onboarding por etapas/checklist | Não existe | 6 |
| Pontos focais | Não existe (só users company_id) | 6 |
| Credenciais seguras por cliente | Não existe | 6 |
| Provisionamento LRV/GitHub/VPS | Não existe | 7 |
| Esteira de prestadores | Não existe (reaproveita comercial) | 8 |
| Ponte lead→cliente + aba Projetos/garantia + PIN cliente | Não existe | 9 |

---

## 3. Modelo de dados proposto (resumo)

> Nomes finais podem ajustar na implementação de cada fase. Tudo com FK coerente,
> `created_at/updated_at`, e tabelas de histórico quando o item for auditável.

- **Catálogo**: `service_catalog` (name, description, default_scope, est_hours,
  hourly_rate, is_hosting TINYINT, active).
- **Proposta**: `proposals` (lead/contact_id, company_id NULL, board_card_id NULL,
  title, status, validity_date, contract_type, total DECIMAL, public_token, created_by)
  + `proposal_items` (proposal_id, service_id NULL, description, scope, hours,
  hourly_rate, amount, position) + `proposal_events` (status/envio/aceite/recusa+motivo).
- **Contrato**: `contract_templates` (name, body HTML) + `contracts` (proposal_id,
  company_id, template_id, body, status, clicksign_doc_key, signed_at, public_token)
  + `contract_events`.
- **Financeiro**: `finance_accounts` (as 3 contas Asaas: name, asaas_token_key,
  purpose) + `finance_charges` (contract_id, account_id, type[entrada/parcela/
  recorrente], amount, due_date, method[pix/boleto/cartao], asaas_charge_id, status)
  + `finance_events`.
- **Onboarding**: `onboarding` (company_id, contract_id, project_type, pipeline_mode
  [esteira_cx/fora_esteira], status, tech_responsible_id) + `onboarding_steps`
  (onboarding_id, step_key, status[pendente/andamento/concluido/bloqueado],
  responsible_id, blocked_reason, notes, position) + `onboarding_events`.
- **Pontos focais**: `client_contacts` (company_id, name, role, email, phone,
  responsibility, is_primary, active, notes).
- **Credenciais**: `client_credentials` (company_id, service_label, username,
  secret_encrypted, url, notes, created_by) — **secret criptografado**, nunca texto puro.
- **Projetos/Entrega**: `projects` (company_id, onboarding_id, name, status,
  delivered_at, warranty_days DEFAULT 90, warranty_ends_at, support_contract TINYINT)
  + vínculo com `tickets`/`planning_cards` já existentes.
- **Ponte lead→cliente**: coluna `company_id` em `whatsapp_contacts` (ou tabela
  `lead_company_link`) para rastrear a conversão sem duplicar cadastro.
- **Gravação**: coluna `auto_record` em `video_rooms` (e espelho em `agenda_meetings`
  se precisar na criação) + `minutes` (ata) ligada a `video_recordings`/`agenda_meetings`.

---

## 4. Fases de implementação

### FASE 0 — Correções da reunião (prioridade máxima, pré-requisito de tudo)
1. **Duração da gravação**: causa raiz = concatenação crua de fragmentos WebM em
   `recChunk`/`recFinalize` (bytes `ab` + `stream_copy_to_stream`) → só o 1º fragmento
   tem header/Segment válido, player e `audio.duration` param em ~1min. **Correção:**
   remux no `recFinalize` e no `recoverOrphanRecordings` (`ffmpeg -i part.webm -c copy
   final.webm`, usando o `ffmpegBin()` já existente); fallback para o fluxo atual se o
   ffmpeg não existir. Garantir vídeo, transcrição e resumo cobrindo a duração total.
2. **Layout 3 participantes**: em `layoutGrid()` tratar `n===3 → cols=3` (lado a lado),
   evitando a última linha esticada do `applyGridSpans`.
3. **Auto-encerrar**: reforçar `maybeAutoStopRecording` no cliente + gatilho server-side
   via `CronController` chamando `recoverOrphanRecordings` (não depender de abrir a lista).

### FASE 1 — Gravação automática
- Nova flag `auto_record` em `video_rooms` (migration idempotente), lida no form de
  **nova reunião** e **sala rápida**, **marcada por padrão**, desmarcável.
- `createSystemVideoRoom` passa a respeitar a flag; `room.php` inicia o `MediaRecorder`
  ao entrar quando `auto_record=1`.

### FASE 2 — Pós-reunião automático (transcrição → resumo → minuta → PDF → envio)
- Ao encerrar a reunião/gravação: encadear transcrição + resumo + **minuta/ata**
  estruturada (contexto, pontos discutidos, decisões, valores acordados, próximos passos).
- Nova aba **Minuta** editável no `watch`; geração de **PDF**.
- Envio automático do PDF por **WhatsApp + e-mail** aos participantes e ao cliente
  (reutiliza `WhatsappNotifier`/`Mailer`). Prazo-alvo ~1h. Registrar envio no histórico.
- Externo (envio real) = validação manual.

### FASE 3 — Catálogo de serviços + Proposta/Orçamento
- `service_catalog` + CRUD + `ServiceCatalogRules` (puro, testável).
- `proposals`/`proposal_items`/`proposal_events`, cálculo automático (horas×valor/hora,
  total), status, `contract_type`, vínculo ao card/lead.
- Link externo público (`public_token`) para o cliente **aceitar/recusar** (recusa com
  motivo obrigatório → volta ao CRM). Header/footer da empresa. Histórico/auditoria.

### FASE 4 — Contrato + ClickSign
- `contract_templates` (vários modelos) + `contracts` editáveis por cliente, estados
  (elaboração/aguardando assinatura/assinado/recusado).
- `ClickSignApi` (core) + webhook confirmando a assinatura efetiva (não concluir só
  por "gerado"). Assinado → segue para Financeiro.

### FASE 5 — Financeiro + Asaas (3 contas)
- `finance_accounts` (as 3 contas Asaas, selecionáveis por cobrança), `finance_charges`
  (entrada/parcelas/recorrente, data, método pix/boleto/cartão), `AsaasApi` (core).
- Encaminhar do contrato assinado; bloquear avanço ao onboarding sem a entrada paga.
- Cobrança real = validação manual.

### FASE 6 — Onboarding + Pontos focais + Credenciais
- `onboarding`/`onboarding_steps` por etapas com checklist, responsável técnico, status
  (pendente/andamento/concluído/bloqueado), observações e histórico.
- **Bloqueios configuráveis** (servidor/armazenamento/credenciais) impedindo marcar
  etapa como concluída sem requisito. Criar cadastro+login+PIN do cliente.
- `client_contacts` (pontos focais, múltiplos, com responsável principal).
- `client_credentials` (área restrita Super Admin, **criptografada**, sem exibir senhas).
- Decisão: **esteira (CX)** vs **fora da esteira (automação LRV)**.

### FASE 7 — Provisionamento automático (fora da esteira)
- `LrvCloudApi` + `GitHubApi` (core). Fluxo: criar cliente no cloud → repositório na
  organização → VPS → banco → ambiente de homologação (domínio temporário) → conectar
  Git → reunir credenciais → entregar link + escopo.
- Esteira (CX): gera **pendência** para o analista (Isaac/Ruan) preencher repositório/CX.

> **✅ API LRV Cloud — gaps RESOLVIDOS (v1.1.0).** O time do LRV Cloud implementou
> os 5 endpoints que faltavam: `POST /clients`, `POST /hosting` (provisionar VPS,
> + `suspend`/`stop`), `POST /databases` (com schema), `POST /applications/git`
> (deploy via Git) e a flag `staging_subdomain` para homologação com SSL. O
> helpdeskON já consome tudo em `LrvCloudApi`, com `ProvisioningRules::apiCapabilities()`
> todas em `auto`. Confirmação por webhook (`hosting.ready`, `application.deployed`).
> Detalhes do contrato final: `.kiro/specs/esteira-comercial/api-lrv-cloud-gaps.md`.
> Isolamento multi-tenant via `created_by_client_id` (clientes-filhos da conta parceira).

### FASE 8 — Contratação de prestadores (reaproveita a esteira)
- CRM/proposta/contrato **internos** para prestador (função, tipo de contratação,
  valores mensal/hora/projeto, prazo, jornada, modelo de trabalho, escopo e o que não
  faz parte). Documentos CLT/PJ. Acesso como developer/perfil adequado, ensino de uso,
  card de demanda automático.
- Encerramento de contrato: **revogar todos os acessos no mesmo dia** (GitHub, Helpdesk,
  e-mail, servidor), garantir código nos repositórios da empresa, registrar motivo/data.

### FASE 9 — Vínculo/rastreabilidade + Projetos/Garantia + PIN cliente
- Fechar ponte **lead→company/user**; manter cadeia potencial cliente → reunião →
  briefing → análise → proposta → contrato → financeiro → onboarding → projeto, sem
  duplicar cadastro, com histórico acessível.
- Aba **Projetos**: botão "Projeto entregue", garantia 90 dias, aviso 15 dias antes do
  fim, bloqueio de chamados pós-garantia sem contrato de suporte. Tipo de contrato
  (zero vs manutenção/A4) define se há garantia de 90 dias.
- Vínculo demanda do cliente ↔ card interno (planning) preservando referência; cliente
  vê andamento, sem expor interno. Aprovação de escopo/homologação com motivo de recusa.
- **PIN do cliente**: login simplificado que cai na página de nova demanda, vinculado ao
  usuário correto, sem acesso a dados de outro cliente — **sem quebrar** o
  `/solicitacaoexterna` atual (que é PIN de equipe). Revisar/alinhar com quem fez a 126.

---

## 5. Riscos e cuidados transversais

- **Não enfraquecer** validações nem alterar testes para esconder regressão (steering).
- **Compatibilidade**: clientes/empresas e fluxos existentes (tickets, planning, agenda,
  videocall) não podem quebrar. Migrations idempotentes e aditivas.
- **Segurança**: credenciais criptografadas; propostas/contratos por `public_token` com
  acesso só ao próprio recurso; financeiro/credenciais nunca visíveis a client/prestador.
- **Validação backend + frontend**; regra importante nunca só no front.
- **Auditoria** em toda transição relevante (status, valores, envios, assinatura,
  aprovação/recusa, credenciais).
- **Integrações externas** sem credenciais no ambiente de teste → validação manual
  explicitamente listada ao fechar cada fase.

---

## 6. Checklist de pronto por fase (gate)

- [ ] `php -l` sem erros nos arquivos alterados.
- [ ] Banco de teste recriado se o schema mudou.
- [ ] Unit relacionados passam.
- [ ] Integration relacionados passam (contra `helpdesk_on_test`).
- [ ] Suíte PHPUnit completa verde (sem regressão).
- [ ] Suíte Playwright/E2E verde quando houver UI.
- [ ] Resultado reportado (passou/falhou por suíte).
- [ ] Itens não testáveis (integrações externas) listados para validação manual.
