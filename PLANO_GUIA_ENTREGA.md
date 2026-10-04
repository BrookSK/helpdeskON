# PLANO DE IMPLEMENTAÇÃO — Fluxo de Entrega, Finalização e Garantia de Projeto

> **Fonte de requisitos:** Guia Operacional – Fluxo de Entrega, Finalização e Garantia de Projeto
> **Data da análise:** 2026-10-03
> **Status:** Análise concluída — aguardando implementação

---

## 1. Mapa de arquivos relevantes (sistema atual)

| Tipo | Arquivo |
|---|---|
| Migration — projetos, garantia, PIN | `migrations/151_commercial_projects_pins.sql` |
| Migration — homologação, escopo, 48h | `migrations/152_ticket_scope_homologacao.sql` |
| Migration — suporte (severidade, SLA) | `migrations/153_ticket_support_fields.sql` |
| Migration — relacionamentos tickets | `migrations/154_ticket_relations.sql` |
| Model | `app/models/Project.php` |
| Regras puras de projeto/garantia | `app/core/ProjectRules.php` |
| Regras puras de homologação (48h) | `app/core/HomologacaoRules.php` |
| Regras puras de suporte | `app/core/SupportRules.php` |
| Regras puras de escopo | `app/core/ScopeRules.php` |
| Controller | `app/controllers/ProjectController.php` |
| View — listagem | `app/views/commercial/projects.php` |
| View — detalhe | `app/views/commercial/project_detail.php` |
| Cron — régua de homologação | `app/controllers/CronController.php` (método `homologacaoRegua`) |
| Testes unitários | `tests/Unit/ProjectRulesTest.php` |
| Testes de integração | `tests/Integration/ProjectTest.php` |

---

## 2. Estrutura atual da tabela `projects`

```sql
CREATE TABLE projects (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    company_id       INT NULL,
    onboarding_id    INT NULL,
    provisioning_id  INT NULL,
    name             VARCHAR(200) NOT NULL,
    contract_type    ENUM('zero','manutencao','suporte','outro') NOT NULL DEFAULT 'outro',
    status           ENUM('planning','in_progress','delivered','warranty','closed','cancelled') NOT NULL DEFAULT 'planning',
    delivered_at     DATETIME NULL,
    warranty_days    INT NOT NULL DEFAULT 90,
    warranty_ends_at DATETIME NULL,
    support_contract TINYINT(1) NOT NULL DEFAULT 0,
    notes            TEXT NULL,
    created_by       INT NULL,
    created_at       TIMESTAMP,
    updated_at       TIMESTAMP
);
```

**Campos ausentes relevantes para o fluxo:**
- Nenhum campo de aceite formal do cliente (`client_accepted_at`, `client_accepted_by`)
- Nenhum campo de reunião de entrega (`delivery_meeting_id`, `delivery_meeting_at`)
- Nenhum campo de documentação/manual (`manual_url`, `documentation_delivered_at`)
- Nenhum campo de rastreamento de aviso de garantia enviado (`warranty_warn_sent_at`)
- Nenhum campo de datas do contrato de suporte (`support_contract_starts_at`, `support_contract_ends_at`)

---

## 3. Análise comparativa: requisito a requisito

### 3.1 Homologação final

**Requisito (doc):**
Todas as demandas do projeto devem estar homologadas, seguindo o fluxo de nova demanda. Pendências abertas devem ser resolvidas ou formalmente registradas antes da entrega.

**Situação atual:** `COMPATÍVEL`
Implementado completamente: status `em_homologacao`, régua de 48h com 3 contatos automáticos (`HomologacaoRules`), liberação automática para `aprovado_producao` via `CronController::homologacaoRegua()`, campos de rastreamento por contato (`homolog_contact1_at/2/3`), `homolog_denied_reason`. O `ProjectController::deliver()` pode ser chamado após esse fluxo.

---

### 3.2 Publicação em produção

**Requisito (doc):**
O analista publica a versão final em produção. O cliente é notificado via e-mail e WhatsApp.

**Situação atual:** `DIVERGENTE`
O status `aprovado_producao` existe no ENUM de tickets e representa "pronto para publicar". Existe o campo `previsao_publicacao` (DATE) nos tickets. Porém:
- Não há registro explícito do **momento em que a publicação ocorreu** (`published_at`) nem nos tickets nem na tabela `projects`.
- **A notificação ao cliente ao publicar em produção não está implementada**: o 3º contato da régua de homologação avisa que a publicação ocorrerá em 6h, mas não há notificação de confirmação de que a publicação foi realizada.
- A transição `aprovado_producao → completed` no ticket não está conectada ao `project.markDelivered()`.

---

### 3.3 Documentação e manual de uso

**Requisito (doc):**
Entregar ao cliente o manual de uso do software (obrigatório). Também, quando aplicável: acessos, integrações e informações técnicas relevantes. Registrar tudo no Helpdesk, vinculado ao perfil do cliente.

**Situação atual:** `AUSENTE`
Não existe nenhum campo, step de etapa, ou evento dedicado para registrar:
- Se o manual de uso foi produzido e entregue
- A URL ou localização do manual
- A data de entrega do manual
- Confirmação de que o cliente recebeu a documentação

O campo `notes` (genérico) na tabela `projects` não supre esse requisito.

---

### 3.4 Reunião de entrega

**Requisito (doc):**
Apresentar o sistema entregue e confirmar que atende ao escopo contratado. Informar as regras da garantia. Seguir o fluxo de reuniões: gravação, transcrição e minuta enviada em até 1 hora para assinatura.

**Situação atual:** `AUSENTE`
A tabela `projects` não tem nenhum vínculo com `agenda_meetings`. Não há:
- Campo `delivery_meeting_id` (FK para `agenda_meetings`)
- Campo `delivery_meeting_at` (data da reunião de entrega)
- Nenhum step de "Reunião de entrega" no fluxo de `ProjectRules`
- Nenhuma validação que impeça marcar o projeto como entregue sem que a reunião tenha ocorrido

O botão "Marcar entregue" na `project_detail.php` dispara diretamente `ProjectController::deliver()` sem verificar se a reunião aconteceu.

---

### 3.5 Aceite formal do cliente

**Requisito (doc):**
O cliente realiza o aceite formal da entrega pelo link público com PIN e pela assinatura digital da minuta. O aceite fica registrado no Helpdesk. A data da entrega ou da entrada em produção marca o início da garantia.

**Situação atual:** `AUSENTE`
O aceite formal de **projeto** não existe. O que existe são:
- Aceite de **proposta** (via token público em `proposal_public.php`) — diferente
- Aceite implícito de homologação (48h sem resposta = aceite automático) — não é formal

Não há:
- Campo `client_accepted_at` na tabela `projects`
- Campo `client_accepted_by` (qual usuário/PIN fez o aceite)
- Link público para o cliente realizar o aceite formal do projeto entregue
- Registro do aceite via PIN (`client_pin`)
- O início da garantia deveria ser na data do aceite, mas atualmente é calculado a partir de `delivered_at` (data que a equipe interna marca — não o cliente)

---

### 3.6 Garantia (90 dias) — regras e cálculo

**Requisito (doc):**
90 dias contados a partir da entrega ou da entrada em produção. Cobre bugs em funcionalidades dentro do escopo. Não cobre novas funcionalidades, problemas de terceiros, alterações do cliente.

**Situação atual:** `COMPATÍVEL`
Totalmente implementado:
- `ProjectRules::DEFAULT_WARRANTY_DAYS = 90`
- `ProjectRules::warrantyEndDate()` — calcula `delivered_at + warranty_days`
- `ProjectRules::warrantyActive()` — verifica vigência
- `ProjectRules::canOpenTicket()` — bloqueia chamados pós-garantia sem suporte
- `ProjectRules::blockReason()` — mensagem de bloqueio para a UI
- UI em `project_detail.php` exibe `delivered_at`, `warranty_ends_at`, `warranty_days`
- Testes: `ProjectRulesTest.php` e `ProjectTest.php` cobrem todos os casos

---

### 3.7 O que a garantia não cobre (regras de triagem)

**Requisito (doc):**
Novas funcionalidades → nova demanda com orçamento. Problemas de terceiros. Alterações feitas pelo cliente.

**Situação atual:** `COMPATÍVEL` (parcial)
`SupportRules` e `ScopeRules` existem como regras puras. O campo `is_third_party` na tabela `tickets` (migration 153) permite registrar que o problema é de terceiro. A distinção entre bug (garantia) e nova funcionalidade (nova demanda) é gerida pelo tipo de ticket/categoria.
Não há automação para bloquear explicitamente chamados classificados como "nova funcionalidade" durante a garantia — é decisão manual do atendente.

---

### 3.8 Aviso ao cliente 15 dias antes do fim da garantia

**Requisito (doc):**
O cliente deve ser avisado com 15 dias de antecedência do fim da garantia, via e-mail e WhatsApp, recebendo a proposta de suporte contratado.

**Situação atual:** `AUSENTE`
A **lógica de detecção** existe e está testada:
- `ProjectRules::WARRANTY_WARNING_DAYS = 15`
- `ProjectRules::shouldWarnWarrantyEnding()` retorna true quando restam ≤15 dias
- `ProjectController::index()` e `project_detail.php` exibem alerta visual amarelo

Porém o **disparo automático não existe**:
- Nenhum job de cron no `CronController` para envio de aviso de garantia
- Nenhuma tabela/campo de rastreamento de "aviso de garantia já enviado" (`warranty_warn_sent_at`)
- Nenhuma notificação via WhatsApp ou e-mail é enviada automaticamente
- A proposta de suporte contratado mencionada no documento não é enviada automaticamente

---

### 3.9 Suporte contratado (pós-garantia)

**Requisito (doc):**
Após os 90 dias, sem contrato de suporte ativo, chamados só são atendidos mediante orçamento aprovado.

**Situação atual:** `COMPATÍVEL` (parcial)
O booleano `support_contract` implementa o bloqueio/liberação de chamados.
`ProjectRules::canOpenTicket()` bloqueia corretamente sem suporte pós-garantia.
`SupportRules` define gravidades, SLAs de análise e prazos de resolução.

O que está ausente é um objeto formal de "contrato de suporte" com:
- Data de início e fim do contrato
- Notificação de vencimento do contrato
- Integração com o módulo de finanças (`FinanceProject`)

---

### 3.10 Registro de informações no Helpdesk

**Requisito (doc):**
Registrar no Helpdesk a data de início e de fim da garantia. Entregar o manual de uso em toda entrega. Encerrar o projeto apenas com aceite, manual e documentação registrados.

**Situação atual:** `DIVERGENTE`
- Data de início da garantia: **compatível** (`delivered_at` na tabela `projects`)
- Data de fim da garantia: **compatível** (`warranty_ends_at`)
- Manual de uso registrado: **ausente** (nenhum campo dedicado)
- Aceite formal registrado: **ausente** (nenhum campo `client_accepted_at`)
- Impedimento para encerrar sem esses registros: **ausente** (botão "Marcar entregue" não exige confirmação de manual nem aceite)

---

## 4. Resumo de classificação

| # | Requisito | Classificação |
|---|---|---|
| 3.1 | Homologação final (régua 48h) | COMPATÍVEL |
| 3.2 | Publicação em produção + notificação ao cliente | **DIVERGENTE** |
| 3.3 | Documentação / manual de uso entregue | **AUSENTE** |
| 3.4 | Reunião de entrega vinculada ao projeto | **AUSENTE** |
| 3.5 | Aceite formal do cliente (projeto entregue) | **AUSENTE** |
| 3.6 | Garantia 90 dias — regras e cálculo | COMPATÍVEL |
| 3.7 | O que a garantia não cobre | COMPATÍVEL |
| 3.8 | Aviso 15 dias antes (cron + notificação) | **AUSENTE** |
| 3.9 | Suporte contratado — bloqueio de chamados | COMPATÍVEL |
| 3.10 | Registro completo no Helpdesk (manual + aceite + datas) | **DIVERGENTE** |

**COMPATÍVEIS: 4 | DIVERGENTES: 2 | AUSENTES: 4**

---

## 5. Alterações necessárias

> Apenas requisitos DIVERGENTES e AUSENTES.

---

### [DIVERGENTE-1] Publicação em produção + notificação ao cliente

**O que precisa ser feito:**

**Schema (`migrations/`):**
- Adicionar coluna `published_at DATETIME NULL` na tabela `projects` para registrar quando a publicação em produção ocorreu de fato.

**`ProjectController.php`:**
- Adicionar endpoint `POST /project/publish/{id}`: muda `status = 'delivered'` (ou mantém em `warranty`), grava `published_at = NOW()`, registra evento `'published'` em `project_events`, dispara notificação ao cliente (WhatsApp + e-mail).

**`ProjectRules.php`:**
- Adicionar método `canPublish(array $project): bool` — só permite publicar se `status = 'in_progress'` ou `status = 'planning'` (não permitir re-publicar um projeto já entregue sem motivo).

**Notificação:**
- Ao publicar: `WhatsappNotifier::sendToPhone()` e `Mailer::send()` ao contato principal do cliente (`company_id → users → phone/email`).
- Mensagem: informar que o projeto foi publicado em produção e que a garantia de N dias está ativa a partir de hoje.

---

### [DIVERGENTE-2] Registro completo no Helpdesk (manual + aceite + datas)

**O que precisa ser feito:**

**Schema (`migrations/`):**
- Adicionar colunas na tabela `projects`:
  ```sql
  manual_url                VARCHAR(500) NULL  -- link/caminho do manual de uso
  documentation_delivered_at DATETIME NULL     -- quando o manual foi entregue ao cliente
  client_accepted_at        DATETIME NULL      -- data/hora do aceite formal
  client_accepted_by        INT NULL           -- FK users.id (quem fez o aceite — cliente)
  ```

**`ProjectController.php`:**
- No endpoint `deliver()`: bloquear a marcação de entrega se `documentation_delivered_at IS NULL` (manual não entregue) — ou apenas avisar com alerta.
- Adicionar endpoint `POST /project/markDocumentation/{id}`: grava `documentation_delivered_at` e `manual_url`.

**`app/views/commercial/project_detail.php`:**
- Adicionar seção "Documentação" com campo URL do manual, botão "Marcar como entregue ao cliente".
- Exibir `client_accepted_at` e nome do cliente que aceitou, quando preenchido.

---

### [AUSENTE-1] Documentação / manual de uso entregue

**O que precisa ser feito:**

Já descrito em [DIVERGENTE-2] acima. Os campos `manual_url` e `documentation_delivered_at` cobrem esse requisito.

**`ProjectRules.php`:**
- Adicionar método `documentationDelivered(array $project): bool` — verifica se `documentation_delivered_at IS NOT NULL`.

**Registro do evento:**
- Ao marcar documentação como entregue, registrar em `project_events` com `event_type = 'documentation'` e descrição incluindo o link do manual.

---

### [AUSENTE-2] Reunião de entrega vinculada ao projeto

**O que precisa ser feito:**

**Schema (`migrations/`):**
- Adicionar colunas na tabela `projects`:
  ```sql
  delivery_meeting_id INT NULL  -- FK agenda_meetings.id
  delivery_meeting_at DATETIME NULL  -- data/hora da reunião (desnormalizado para consulta rápida)
  ```
  ```sql
  FOREIGN KEY (delivery_meeting_id) REFERENCES agenda_meetings(id) ON DELETE SET NULL
  ```

**`ProjectController.php`:**
- Adicionar endpoint `POST /project/linkMeeting/{id}`: vincula uma reunião (`agenda_meeting_id`) ao projeto, grava `delivery_meeting_id` e `delivery_meeting_at`, registra evento `'delivery_meeting_linked'`.

**`app/views/commercial/project_detail.php`:**
- Adicionar seção "Reunião de entrega" com seletor de reuniões (agenda_meetings do cliente) ou campo de data manual.
- Exibir status da reunião vinculada (data, link de gravação se houver).

**Nota:** O documento exige "gravação, transcrição e minuta enviada em até 1 hora para assinatura". O módulo de videochamada já gera transcrições — o vínculo com `projects` fecha essa rastreabilidade.

---

### [AUSENTE-3] Aceite formal do cliente (projeto entregue)

**O que precisa ser feito:**

**Schema (`migrations/`):**
- Campos `client_accepted_at` e `client_accepted_by` na tabela `projects` (já descritos em [DIVERGENTE-2]).
- Adicionar coluna `acceptance_token VARCHAR(64) NULL UNIQUE` em `projects` — token gerado pela equipe, enviado ao cliente por e-mail/WhatsApp para o aceite via link público.

**`ProjectController.php`:**
- Adicionar endpoint `POST /project/generateAcceptanceLink/{id}` (equipe interna): gera `acceptance_token`, envia link ao cliente.
- Adicionar endpoint público `GET /project/accept/{token}` (cliente): exibe página de aceite com dados do projeto, botão de confirmação com autenticação via `client_pin`.
- Ao confirmar: grava `client_accepted_at`, `client_accepted_by`, registra evento `'client_accepted'` em `project_events`, inicia o cálculo da garantia a partir desta data.

**`ProjectRules.php`:**
- Adicionar método `clientAccepted(array $project): bool` — verifica se `client_accepted_at IS NOT NULL`.

**Nova view pública:**
- `app/views/external/project_accept.php` — página pública com PIN de cliente para aceite formal.

---

### [AUSENTE-4] Aviso 15 dias antes do fim da garantia (cron + notificação)

**O que precisa ser feito:**

**Schema (`migrations/`):**
- Adicionar coluna `warranty_warn_sent_at DATETIME NULL` na tabela `projects` — rastreamento de idempotência (aviso enviado apenas uma vez).

**`CronController.php`:**
- Adicionar endpoint `GET /cron/warrantyWarnings?token=XXX` e método privado `doWarrantyWarnings()`, seguindo o mesmo padrão de `sendCardDueReminders()`:
  ```
  1. Busca projetos com status = 'warranty' E warranty_warn_sent_at IS NULL
     E warrantyEndsAt entre hoje e hoje+15 dias.
  2. Para cada projeto:
     a. Envia WhatsApp ao cliente (company → user → phone).
     b. Envia e-mail ao cliente.
     c. Envia alerta interno ao gestor (WhatsApp/e-mail da equipe).
     d. Grava warranty_warn_sent_at = NOW() (idempotência).
  ```

**`ProjectRules.php`:**
- O método `shouldWarnWarrantyEnding()` já existe e já é testado — o cron o reutiliza para a decisão.

**Mensagem ao cliente:**
- Informar que a garantia expira em X dias (data exata).
- Oferecer contrato de suporte contratado.
- Link para contato da equipe.

**Sugestão de agendamento:**
```
0 9 * * * curl -s "https://seudominio.com/cron/warrantyWarnings?token=TOKEN"
```
(roda diariamente de manhã; a idempotência garante envio único por projeto)

---

## 6. Novas migrations necessárias (resumo)

| # | Migration | Tabela/coluna alvo | Descrição |
|---|---|---|---|
| M1 | `157_project_delivery_fields.sql` | `projects` | Adicionar: `published_at`, `manual_url`, `documentation_delivered_at`, `client_accepted_at`, `client_accepted_by`, `acceptance_token`, `delivery_meeting_id`, `delivery_meeting_at`, `warranty_warn_sent_at` |

> Uma única migration por coesão — todos os campos se relacionam ao mesmo fluxo de entrega.

---

## 7. Novos endpoints necessários (resumo)

| Rota | Método | Quem acessa | Descrição |
|---|---|---|---|
| `POST /project/publish/{id}` | POST | Equipe interna | Registra publicação em produção + notifica cliente |
| `POST /project/markDocumentation/{id}` | POST | Equipe interna | Registra entrega do manual |
| `POST /project/linkMeeting/{id}` | POST | Equipe interna | Vincula reunião de entrega ao projeto |
| `POST /project/generateAcceptanceLink/{id}` | POST | Equipe interna | Gera token de aceite + envia ao cliente |
| `GET /project/accept/{token}` | GET (público) | Cliente | Página pública de aceite formal com PIN |
| `POST /project/accept/{token}` | POST (público) | Cliente | Confirma aceite (autentica com client_pin) |
| `GET /cron/warrantyWarnings?token=XXX` | GET (cron) | Cron | Envia aviso 15 dias antes do fim da garantia |

---

## 8. Testes a criar junto com a implementação

### Unitários (`tests/Unit/ProjectRulesTest.php`)
- `testCanPublish_*`: projeto em andamento pode; já entregue sem re-publicar sem justificativa.
- `testDocumentationDelivered_*`: com e sem `documentation_delivered_at`.
- `testClientAccepted_*`: com e sem `client_accepted_at`.

### Integração (`tests/Integration/ProjectTest.php`)
- `testPublishRegistraDataENotifica`: `published_at` gravado, evento criado.
- `testDocumentacaoRegistrada`: `markDocumentation` grava campos e evento.
- `testAceiteFormalDoCliente`: token gerado, aceite via token, `client_accepted_at` gravado.
- `testWarrantyWarnCron`: projetos com garantia ≤15 dias recebem aviso; `warranty_warn_sent_at` gravado; segundo run não re-envia.

---

## 9. Itens fora do escopo de testes automatizados

- Envio real de e-mail e WhatsApp ao cliente (integrações externas — validar manualmente).
- Assinatura digital da minuta da reunião (depende do ClickSign/módulo de contratos — integração externa).
- Gravação e transcrição da reunião de entrega (módulo de videochamada — validar manualmente).

---

## 10. Ordem sugerida de implementação

1. **Migration M1** — adicionar todos os campos novos na tabela `projects`.
2. **`ProjectRules.php`** — adicionar métodos puros: `canPublish`, `documentationDelivered`, `clientAccepted`; já testar unitariamente.
3. **`Project.php`** — adicionar métodos de persistência para os novos fluxos.
4. **`ProjectController.php`** — adicionar os 6 novos endpoints.
5. **`app/views/commercial/project_detail.php`** — atualizar com seções de documentação, aceite e reunião de entrega.
6. **View pública de aceite** — `app/views/external/project_accept.php`.
7. **`CronController.php`** — adicionar `warrantyWarnings` + `doWarrantyWarnings`.
8. **Testes** — unitários e integração para todos os fluxos novos.

---

*Arquivo gerado pela análise pré-implementação. Não modificar manualmente sem atualizar a seção de classificação.*
