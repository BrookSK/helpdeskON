# PLANO DE IMPLEMENTAÇÃO — Relatório Diário

> **Fonte de requisitos:** Guia Operacional – Relatório Diário de Desenvolvimento  
> **Complemento:** Transcrição da reunião (Lucas / Lucas R. Vacari / Julia)  
> **Data da análise:** 2026-10-03  
> **Status:** Análise concluída — aguardando implementação

---

## 1. Mapa de arquivos relevantes (sistema atual)

| Tipo | Arquivo |
|---|---|
| Migration — tabela principal | `migrations/131_daily_reports_module.sql` |
| Migration — coluna company_id | `migrations/136_daily_reports_company.sql` |
| Migration — ajuste de comment | `migrations/137_daily_reports_company_comment.sql` |
| Model | `app/models/DailyReport.php` |
| Regras de negócio puras | `app/core/RdoRules.php` |
| Controller | `app/controllers/RdoController.php` |
| View (SPA única) | `app/views/rdo/index.php` |
| Permissões por papel | `app/core/Permissions.php` |
| Testes unitários | `tests/Unit/RdoRulesTest.php` |
| Testes de integração | `tests/Integration/DailyReportTest.php` |

---

## 2. Estrutura atual da tabela `daily_reports`

```sql
CREATE TABLE daily_reports (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT NOT NULL,              -- FK users.id ON DELETE CASCADE
    company_id   INT NULL,                  -- FK companies.id ON DELETE SET NULL
    report_date  DATE NOT NULL,
    title        VARCHAR(255) NULL,
    activities   LONGTEXT NULL,
    occurrences  LONGTEXT NULL,
    has_occurrence TINYINT(1) NOT NULL DEFAULT 0,
    status       ENUM('em_andamento','finalizado') NOT NULL DEFAULT 'em_andamento',
    transcription LONGTEXT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
-- Tabelas filhas: daily_report_attachments, daily_report_collaborators
```

**Ausências relevantes na tabela atual:**
- Nenhuma coluna de `deadline` (horário limite configurável)
- Nenhuma coluna de `submitted_at` (momento real do preenchimento)
- Nenhuma coluna de `review_status` (pendência de revisão para o admin)
- Nenhuma coluna de `locked_at` / `locked` (bloqueio por prazo vencido)
- Nenhuma coluna de `approved_at` / `approved_by` (aprovação do relatório)
- Nenhuma tabela de histórico de alterações

---

## 3. Endpoints e rotas atuais (RdoController)

| Rota | Método | Descrição |
|---|---|---|
| `GET /rdo` | GET | Renderiza a SPA |
| `GET /rdo/list` | XHR | JSON: lista + cards de resumo |
| `GET /rdo/get/{id}` | XHR | JSON: relatório com anexos e colaboradores |
| `POST /rdo/create` | XHR | Cria relatório |
| `POST /rdo/update/{id}` | XHR | Atualiza relatório |
| `POST /rdo/delete/{id}` | XHR | Exclui relatório |
| `POST /rdo/upload/{id}` | XHR | Upload de anexo |
| `POST /rdo/deleteAttachment/{id}` | XHR | Remove anexo |
| `POST /rdo/transcribe` | XHR | Transcrição de áudio via OpenAI |

**Ausentes:**
- `POST /rdo/submitEdit/{id}` — submeter uma alteração em relatório já finalizado/aprovado (entra como pendente)
- `POST /rdo/approveEdit/{id}` — aprovar a alteração pendente (super_admin)
- `POST /rdo/rejectEdit/{id}` — recusar a alteração pendente (super_admin)
- `GET /rdo/pendingReviews` — listar relatórios com pendências de revisão para o admin
- `POST /rdo/requestUnlock/{id}` — profissional solicita liberação após prazo vencido

---

## 4. Análise comparativa: requisito a requisito

### 4.1 Preenchimento diário até as 19h

**Requisito (doc):**  
Todo profissional preenche o próprio relatório até as 19h do dia correspondente.  
Após as 19h, o relatório fica **bloqueado por prazo** — a pessoa não consegue mais preencher sem solicitação de liberação ao administrador.

**Transcrição (complemento):**  
"Se ela preencher 7h01 [após o horário limite], ele vai ficar bloqueado por prazo. Então a pessoa não consegue mais preencher. Ela tem que solicitar liberação para um administrador."

**Situação atual:** `AUSENTE`  
Não há nenhuma verificação de horário limite. Qualquer usuário pode criar ou editar relatórios de qualquer data a qualquer hora, sem bloqueio.

---

### 4.2 Configurabilidade do horário limite

**Requisito (doc):**  
O horário de corte (19h) é **configurável**.

**Situação atual:** `AUSENTE`  
Nenhuma configuração de horário limite existe em qualquer tabela de settings ou em `RdoRules`.

---

### 4.3 Relatório não preenchido no dia → pendência de revisão para o admin

**Requisito (doc):**  
Gera automaticamente uma pendência de revisão para o administrador:
- Relatório não preenchido no dia.
- Relatório preenchido após as 19h.
- Relatório preenchido em data posterior ao dia trabalhado.

**Transcrição (complemento):**  
"A gente vai pra essa tela também que você vai criar falando pra liberar."

**Situação atual:** `AUSENTE`  
Não há mecanismo de pendência de revisão, nem coluna `review_status` na tabela, nem tela de revisão para o administrador.

---

### 4.4 Bloqueio de edição em relatórios de dias anteriores → fluxo de aprovação

**Requisito (doc):**  
Toda alteração em relatório de dia anterior fica pendente de aprovação.  
As informações só são atualizadas após a aprovação.  
Se aprovado → atualiza o dado. Se não aprovado → mantém o que estava.

**Transcrição (complemento):**  
"Se depois de, por exemplo, dois dias você quiser alterar ele, ele vai salvar essa alteração e aí ele vai mandar pra você, pra você aprovar ou recusar essa alteração."  
"Caso não seja aprovado, ele mantém o que já estava. Ou seja, o cara não consegue alterar o histórico."

**Situação atual:** `AUSENTE`  
O endpoint `POST /rdo/update/{id}` sobrescreve o relatório diretamente, sem nenhuma etapa intermediária. Não há staging de alteração, nem fluxo de aprovação.

---

### 4.5 Histórico completo de alterações

**Requisito (doc):**  
O Helpdesk registra automaticamente o histórico completo de alterações e o responsável por cada alteração.

**Transcrição (complemento):**  
"E ainda que isso o faça, fica salvo no histórico."  
"Desde a última aprovação, ele tava em aprovado e ele foi pra pendente. E aí ele foi finalizado de não para sim."

**Situação atual:** `AUSENTE`  
A tabela `daily_reports` possui apenas `updated_at` (timestamp de última modificação), sem rastrear o que mudou, por quem, nem quando. Não existe tabela de changelog/histórico de versões.

---

### 4.6 Tela de revisão para o administrador

**Requisito (doc):**  
Administrador analisa as pendências de revisão e aprova ou recusa alterações.

**Transcrição (complemento):**  
"Vai salvar essa alteração e aí ele vai mandar pra você, pra você aprovar ou recusar essa alteração."  
Lucas R. Vacari: "essa tela que vai ter que criar, para onde a gente vai ter que aprovar essa edição sua."

**Situação atual:** `AUSENTE`  
Não existe tela de revisão, nem fila de aprovações, nem interface para o admin aprovar/recusar.

---

### 4.7 Preenchimento pelo próprio profissional

**Requisito (doc):**  
Cada profissional preenche o **próprio** relatório. O dono não pode ser forjado.

**Situação atual:** `COMPATÍVEL`  
No `RdoController::create()`, `user_id` é sempre `$this->currentUser()['id']`, ignorando qualquer valor enviado via POST. Correto.

---

### 4.8 Escopo de visibilidade por papel

**Requisito (doc — implícito):**  
Cada profissional vê apenas o próprio relatório. Gestor/admin vê de todos.

**Situação atual:** `COMPATÍVEL`  
`RdoRules::canViewReportOf()` e `scopeFilters()` no controller implementam corretamente: `super_admin` tem visão global; todos os demais papéis (inclusive `developer`) veem apenas o próprio.

---

### 4.9 Acesso por papel ao módulo RDO

**Requisito (doc):**  
Aplica-se a "todos os profissionais envolvidos em desenvolvimento: devs, QA, design e afins."

**Situação atual:** `COMPATÍVEL`  
`Permissions::ROLE_MODULES` libera `'rdo'` para: `marketing`, `comercial`, `attendant`, `analyst`, `whatsapp_agent`, `super_admin`, `developer`. Papel `client` não tem acesso. Alinhado com o documento.

---

### 4.10 Registro das atividades do dia (campos do formulário)

**Requisito (doc):**  
Campos esperados: atividades realizadas, o que ficou pendente, impedimentos/bloqueios, plano para o próximo dia.

**Situação atual:** `DIVERGENTE`  
O formulário atual possui: `title`, `activities`, `occurrences`, `status`, `company_id`, colaboradores, anexos.  
**Faltam campos:** "o que ficou em andamento/pendente" (está coberto parcialmente por `occurrences` mas o documento os distingue) e "plano para o próximo dia".  
A transcrição não detalha esses campos extras; o documento, contudo, os lista. **Requer decisão: adicionar campos separados ou manter como campo livre em `activities`?** Indicar na implementação.

---

### 4.11 Relatório objetivo (boa prática)

**Requisito (doc — boas práticas):**  
Poucas linhas bem escritas; relacionar à atividade/projeto/cliente; nunca genérico.

**Situação atual:** `COMPATÍVEL`  
Não é uma regra de sistema, mas de conduta. O sistema já possui o vínculo obrigatório por `company_id` (cliente/empresa). Nenhuma ação técnica necessária.

---

### 4.12 Proibição de dados sensíveis no relatório

**Requisito (doc):**  
Não incluir senhas, dados sensíveis ou confidenciais.

**Situação atual:** `COMPATÍVEL`  
Não é validado em código (impossível sem IA), é política de conduta. Nenhuma ação técnica necessária.

---

## 5. Resumo de classificação

| # | Requisito | Classificação |
|---|---|---|
| 4.1 | Bloqueio por prazo (horário limite) | **AUSENTE** |
| 4.2 | Horário limite configurável | **AUSENTE** |
| 4.3 | Pendência de revisão para admin | **AUSENTE** |
| 4.4 | Fluxo de aprovação de alterações pós-prazo | **AUSENTE** |
| 4.5 | Histórico de alterações (changelog) | **AUSENTE** |
| 4.6 | Tela de revisão para o administrador | **AUSENTE** |
| 4.7 | Dono do relatório não pode ser forjado | COMPATÍVEL |
| 4.8 | Escopo de visibilidade por papel | COMPATÍVEL |
| 4.9 | Acesso ao módulo por papel | COMPATÍVEL |
| 4.10 | Campos: pendente / plano para amanhã | **DIVERGENTE** |
| 4.11 | Vínculo com cliente/empresa | COMPATÍVEL |
| 4.12 | Política de dados sensíveis (conduta) | COMPATÍVEL |

**COMPATÍVEIS: 5 | DIVERGENTES: 1 | AUSENTES: 6**

---

## 6. Alterações necessárias

> Lista apenas dos requisitos DIVERGENTES e AUSENTES, com detalhamento de onde e o que alterar.

---

### [AUSENTE-1] Bloqueio por prazo (horário limite de preenchimento)

**O que precisa ser feito:**

**Schema (`migrations/`):**
- Criar migration para adicionar coluna `submission_deadline TIME NULL DEFAULT '19:00:00'` na tabela `settings` (ou em uma nova tabela `rdo_settings`), configurável pelo admin.
- Adicionar coluna `submitted_at TIMESTAMP NULL` em `daily_reports` para registrar o momento exato do primeiro preenchimento.
- Adicionar coluna `is_locked TINYINT(1) NOT NULL DEFAULT 0` em `daily_reports` para marcar relatórios bloqueados por prazo vencido.
- Adicionar coluna `lock_reason ENUM('deadline','manual') NULL` em `daily_reports`.

**`RdoRules.php`:**
- Adicionar método `isWithinDeadline(string $reportDate, string $deadlineTime): bool` — retorna true se o relatório for do dia atual E o horário atual for <= deadline, OU se for de data futura.
- Adicionar método `canEditDirectly(array $report, string $userRole, string $deadlineTime): bool` — retorna true somente se o relatório estiver dentro do prazo e não bloqueado.
- Adicionar constante `DEFAULT_DEADLINE = '19:00:00'`.

**`RdoController.php`:**
- Em `create()`: após salvar, gravar `submitted_at = NOW()`. Se a data do relatório for anterior a hoje OU o horário já tiver passado do deadline → criar já como `is_locked = 1`, `lock_reason = 'deadline'`.
- Em `update()`: antes de aplicar qualquer alteração, verificar `is_locked`. Se bloqueado, retornar erro 423 (Locked) com mensagem explicando que é necessário solicitar liberação.
- Adicionar endpoint `POST /rdo/requestUnlock/{id}`: cria pendência de revisão do tipo `unlock` para o admin.

---

### [AUSENTE-2] Horário limite configurável

**O que precisa ser feito:**

**Schema:**
- Dentro da migration do [AUSENTE-1], criar uma entrada na tabela `settings` (ou `rdo_settings`) com chave `rdo_deadline_time` e valor padrão `19:00:00`.

**`RdoRules.php`:**
- O método `isWithinDeadline` deve receber o horário como parâmetro (não hardcoded), lido das configurações do sistema.

**`RdoController.php` / `SettingsController.php`:**
- Expor um campo na tela de Configurações (admin) para alterar o horário limite do RDO.
- Carregar o valor da configuração a cada chamada de `create()` e `update()` (ou cachear na sessão).

---

### [AUSENTE-3] Pendências de revisão para o administrador

**O que precisa ser feito:**

**Schema (`migrations/`):**
- Criar tabela `daily_report_reviews`:
  ```sql
  CREATE TABLE daily_report_reviews (
      id            INT AUTO_INCREMENT PRIMARY KEY,
      report_id     INT NOT NULL,              -- FK daily_reports.id
      type          ENUM('late_fill','post_deadline','edit_request','unlock_request') NOT NULL,
      requested_by  INT NOT NULL,              -- FK users.id (quem gerou)
      requested_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      reviewed_by   INT NULL,                  -- FK users.id (admin que revisou)
      reviewed_at   TIMESTAMP NULL,
      status        ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
      notes         TEXT NULL,                 -- observação do admin ao aprovar/recusar
      KEY idx_report (report_id),
      KEY idx_status (status)
  );
  ```
- Adicionar coluna `review_status ENUM('none','pending_review') NOT NULL DEFAULT 'none'` em `daily_reports` para facilitar filtro rápido na listagem.

**`DailyReport.php`:**
- Adicionar métodos: `createReview($data)`, `getPendingReviews()`, `findReview($id)`, `updateReview($id, $data)`.

**`RdoRules.php`:**
- Adicionar método `requiresReview(string $reportDate, string $submittedDate, string $deadlineTime): bool` — retorna true se o relatório foi preenchido fora do prazo, em dia posterior ou estava com prazo vencido.
- Adicionar whitelist de tipos de revisão: `REVIEW_TYPES`.

**`RdoController.php`:**
- Em `create()`: após salvar, chamar `RdoRules::requiresReview()`. Se verdadeiro, inserir automaticamente em `daily_report_reviews` com `type = 'late_fill'` ou `'post_deadline'` e definir `review_status = 'pending_review'` no relatório.

---

### [AUSENTE-4] Fluxo de aprovação de alterações em relatórios de dias anteriores

**O que precisa ser feito:**

**Schema (`migrations/`):**
- Criar tabela `daily_report_edit_drafts` para armazenar a alteração pendente (snapshot dos novos dados antes da aprovação):
  ```sql
  CREATE TABLE daily_report_edit_drafts (
      id            INT AUTO_INCREMENT PRIMARY KEY,
      report_id     INT NOT NULL,
      review_id     INT NOT NULL,              -- FK daily_report_reviews.id
      field_name    VARCHAR(100) NOT NULL,     -- qual campo foi alterado
      old_value     LONGTEXT NULL,
      new_value     LONGTEXT NULL,
      created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      KEY idx_report (report_id),
      KEY idx_review (review_id)
  );
  ```
  *Alternativa mais simples (recomendada): um único registro JSON com o snapshot completo em vez de linha por campo.*

**`DailyReport.php`:**
- Adicionar métodos: `createEditDraft($data)`, `getEditDraft($reviewId)`, `applyEditDraft($draftId)`, `discardEditDraft($draftId)`.

**`RdoRules.php`:**
- Adicionar método `canEditDirectly(array $report, string $userRole): bool` — define se o usuário pode salvar direto ou precisa passar pelo fluxo de aprovação. Regra: pode editar diretamente apenas relatórios do dia atual dentro do prazo, não bloqueados.
- Adicionar método `requiresApprovalForEdit(array $report): bool` — retorna true se `report_date < hoje` OU `is_locked = 1`.

**`RdoController.php`:**
- Modificar `update()`: se `RdoRules::requiresApprovalForEdit($report)` retornar true, **não** aplicar o update diretamente. Em vez disso:
  1. Salvar o snapshot dos novos dados em `daily_report_edit_drafts`.
  2. Criar um registro em `daily_report_reviews` com `type = 'edit_request'`.
  3. Marcar `review_status = 'pending_review'` no relatório.
  4. Retornar JSON `{ 'pending': true, 'message': 'Alteração enviada para aprovação.' }`.
- Adicionar endpoint `POST /rdo/approveEdit/{reviewId}` (apenas super_admin):
  1. Busca o draft correspondente.
  2. Aplica o update no relatório original.
  3. Marca o review como `approved`, grava `reviewed_by` e `reviewed_at`.
  4. Remove o draft.
  5. Reseta `review_status = 'none'` se não houver outras pendências.
- Adicionar endpoint `POST /rdo/rejectEdit/{reviewId}` (apenas super_admin):
  1. Marca o review como `rejected`.
  2. Descarta o draft (o relatório original fica intacto).
  3. Reseta `review_status = 'none'` se não houver outras pendências.

---

### [AUSENTE-5] Histórico de alterações (changelog/audit trail)

**O que precisa ser feito:**

**Schema (`migrations/`):**
- Criar tabela `daily_report_history`:
  ```sql
  CREATE TABLE daily_report_history (
      id           INT AUTO_INCREMENT PRIMARY KEY,
      report_id    INT NOT NULL,
      changed_by   INT NOT NULL,              -- FK users.id
      changed_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      action       ENUM('created','updated','approved','rejected','locked','unlocked') NOT NULL,
      snapshot     JSON NULL,                 -- snapshot dos dados no momento da ação
      review_id    INT NULL,                  -- FK daily_report_reviews.id (se aplicável)
      notes        TEXT NULL,
      KEY idx_report (report_id)
  );
  ```

**`DailyReport.php`:**
- Adicionar método `addHistory($data)` e `getHistory($reportId)`.

**`RdoController.php`:**
- Chamar `addHistory()` em cada ação relevante: criação, edição direta, aprovação, rejeição, bloqueio, desbloqueio.

**`app/views/rdo/index.php` (ou nova partial view):**
- No modal de visualização, adicionar aba/seção "Histórico de alterações" que exibe a timeline de eventos do relatório.

---

### [AUSENTE-6] Tela de revisão para o administrador

**O que precisa ser feito:**

**`RdoController.php`:**
- Adicionar endpoint `GET /rdo/pendingReviews` (apenas super_admin): retorna JSON com todos os registros de `daily_report_reviews` com `status = 'pending'`, joined com dados do relatório e do solicitante.

**`app/views/rdo/index.php`:**
- Adicionar aba ou seção "Pendências de revisão" visível apenas para super_admin:
  - Lista de cards/linhas com: profissional, data do relatório, tipo de pendência (preenchimento tardio / alteração), data da solicitação.
  - Para pendências do tipo `edit_request`: exibir diff entre o valor atual e o valor proposto (campos lado a lado ou destacados).
  - Botões "Aprovar" e "Recusar" por item.
  - Badge de contagem no título da aba (ex.: "Pendências (3)").
  - Ao aprovar/recusar, recarregar a lista.

**`RdoRules.php`:**
- Adicionar método `canReview(?string $role): bool` — retorna true apenas para `super_admin`.

---

### [DIVERGENTE-1] Campos do formulário: pendente / plano para amanhã

**O que precisa ser feito:**

**Decisão necessária antes de implementar:**  
O documento lista 4 itens distintos para o relatório: (1) atividades realizadas, (2) o que ficou em andamento/pendente, (3) impedimentos/bloqueios, (4) plano para o próximo dia. O sistema atual tem `activities` e `occurrences`.

**Opção A — Campos separados (mais fiel ao documento):**
- Schema: adicionar colunas `pending_tasks LONGTEXT NULL` e `next_day_plan LONGTEXT NULL` em `daily_reports` (nova migration).
- `RdoController.php`: ler e persistir os novos campos em `create()` e `update()`.
- `app/views/rdo/index.php`: adicionar os dois novos campos no modal de criação/edição e no modal de visualização.

**Opção B — Campo único livre (mais simples, mantém schema atual):**
- Nenhuma mudança no schema.
- Apenas orientar no placeholder/label do campo `activities` a incluir pendentes e plano para amanhã.

> **Recomendação:** Opção A, pois o documento trata os quatro itens como campos distintos. A transcrição não contradiz isso. Confirmar antes de implementar.

---

## 7. Novas migrations necessárias (resumo)

| # | Migration | Tabela/coluna alvo | Descrição |
|---|---|---|---|
| M1 | `152_daily_reports_deadline.sql` | `daily_reports` + settings | Colunas `submitted_at`, `is_locked`, `lock_reason`; configuração `rdo_deadline_time` |
| M2 | `153_daily_reports_reviews.sql` | `daily_report_reviews` (nova) | Fila de pendências de revisão e aprovações |
| M3 | `154_daily_reports_edit_drafts.sql` | `daily_report_edit_drafts` (nova) | Snapshot das alterações pendentes de aprovação |
| M4 | `155_daily_reports_history.sql` | `daily_report_history` (nova) | Audit trail / histórico de alterações |
| M5 | `156_daily_reports_extra_fields.sql` | `daily_reports` | Colunas `pending_tasks`, `next_day_plan` (somente se Opção A aprovada) |
| M6 | `157_daily_reports_review_status.sql` | `daily_reports` | Coluna `review_status` |

---

## 8. Novos endpoints necessários (resumo)

| Rota | Método | Quem acessa | Descrição |
|---|---|---|---|
| `POST /rdo/requestUnlock/{id}` | POST | Profissional dono | Solicita desbloqueio ao admin |
| `GET /rdo/pendingReviews` | GET (XHR) | super_admin | Lista pendências de revisão |
| `POST /rdo/approveEdit/{reviewId}` | POST | super_admin | Aprova alteração → aplica draft |
| `POST /rdo/rejectEdit/{reviewId}` | POST | super_admin | Recusa alteração → descarta draft |

---

## 9. Testes a criar junto com a implementação

### Unitários (`tests/Unit/RdoRulesTest.php`)
- `testIsWithinDeadline_*`: casos — dentro do prazo, após o prazo, relatório de dia anterior.
- `testCanEditDirectly_*`: dentro do prazo (pode), após o prazo (não pode), bloqueado (não pode).
- `testRequiresApprovalForEdit_*`: dia atual não bloqueado (não), dia anterior (sim), is_locked=1 (sim).
- `testRequiresReview_*`: preenchido no prazo (não), preenchido após prazo (sim), data retroativa (sim).
- `testCanReview_*`: super_admin (sim), developer (não), demais papéis (não).

### Integração (`tests/Integration/`)
- `DailyReportReviewTest.php` (novo):
  - Criar relatório dentro do prazo → sem pendência.
  - Criar relatório fora do prazo → cria pendência automaticamente.
  - Editar relatório de dia anterior → cria draft + pendência.
  - Aprovar pendência → aplica draft, review_status = 'none'.
  - Recusar pendência → descarta draft, relatório original intacto.
  - Bloquear relatório → update direto retorna 423.
- `DailyReportHistoryTest.php` (novo):
  - Verificar que cada ação grava entrada no histórico.

---

## 10. Itens fora do escopo de testes automatizados

Os itens abaixo dependem de configuração externa ou comportamento de usuário, e **não podem ser validados por testes automatizados**:

- Verificação do horário de sistema em ambientes com timezone diferente (testar manualmente com horário real do servidor).
- Comportamento do admin ao aprovar/recusar via interface (cobrir com E2E Playwright).
- Envio de notificação ao profissional sobre aprovação/rejeição da alteração (integração externa — validar manualmente se houver implementação futura de notificações).

---

## 11. Ordem sugerida de implementação

1. **Schema (migrations M1→M6)** — sem lógica ainda; apenas estrutura.
2. **`RdoRules.php`** — adicionar os métodos puros; já testar com unitários.
3. **`DailyReport.php`** — adicionar os novos métodos de persistência (reviews, drafts, history).
4. **`RdoController.php`** — modificar `create()` e `update()`; adicionar os 4 novos endpoints.
5. **`app/views/rdo/index.php`** — adicionar aba de pendências, campos novos no modal, seção de histórico.
6. **`SettingsController.php` / settings view** — campo para configurar `rdo_deadline_time`.
7. **Testes** — unitários e integração para todos os fluxos novos.
8. **E2E (Playwright)** — fluxo de aprovação/recusa pelo admin.

---

*Arquivo gerado automaticamente pela análise pré-implementação. Não modificar manualmente sem atualizar a seção de classificação.*
