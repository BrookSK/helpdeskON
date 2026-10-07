# Implementações necessárias — Adequação do sistema aos Guias Operacionais

> **Status:** backlog aberto · **Última atualização:** 2026-10-07
>
> Documento vivo. Lista o que o sistema **helpdeskON** precisa implementar para
> ficar 100% de acordo com os Guias Operacionais (os `.docx`).

## Princípios (regras de condução deste trabalho)

1. **O documento é o piso, não o teto.** O sistema deve cumprir tudo que o guia
   descreve. O que já existe e vai **além** do guia **é mantido** — nunca se
   remove nem se enfraquece funcionalidade/validação existente para "bater" com
   o papel. Todas as mudanças são **aditivas** (ou migração sem perda).
2. **Os guias não serão alterados.** A adequação é sempre do lado do código.
3. **Gate de testes obrigatório** (conforme steering `pre-producao`): cada item
   só é "pronto" após `php -l` limpo, banco de teste recriado quando o schema
   muda, PHPUnit (Unit + Integration) verde e Playwright/E2E verde, com
   resultado reportado.
4. **Regra pura primeiro.** Lógica de decisão (status, cálculo, validação) vai
   para classe pura testável (`*Rules`), seguindo o padrão já usado
   (`ProposalRules`, `ContractRules`, `OnboardingRules`, `SupportRules` etc.).
5. **Em caso de ambiguidade ou conflito com o existente, parar e alinhar** antes
   de mexer no código.

## Legenda

- 🟥 **Ausente** — não existe no código; implementação nova.
- 🟧 **Parcial/Divergente** — existe algo, mas não cobre o guia.
- ⚠️ **Conflito** — o guia colide com um comportamento existente; precisa de
  decisão antes de implementar (ver "Pontos a alinhar").
- 🟩 **Já conforme** — nada a fazer (listado só para registro).

---

## Pontos a alinhar antes de codar (decisões pendentes)

Estes bloqueiam ou alteram o escopo de itens abaixo. Preciso da sua decisão.

| # | Decisão | Contexto |
|---|---------|----------|
| A | **Item do orçamento fora do catálogo** — manter a "linha livre" existente ou travar tudo ao catálogo? | O guia Comercial diz *"nunca incluir item fora do catálogo"*. Hoje `Proposal::replaceItems` aceita qualquer linha com descrição (`service_id` opcional). Travar = remover um comportamento existente (conflita com o Princípio 1). |
| B | **Status de suporte** — mapear o fluxo do guia sobre os status atuais ou adicionar novos sem remover os de dev/homologação? | Os status atuais (`aguardando_aprovacao_escopo`, `em_homologacao`, `aprovado_producao`...) são usados de verdade. O guia descreve Abertura→Classificação→Análise→Resolução→Validação→Encerramento. Não podemos apagar os existentes. |
| C | **Módulo do orçamento** — entidade nova (tabela `proposal_modules`) ou agrupador textual por item (coluna `module_name`)? | Afeta tamanho do item 3 e como os módulos viram "etapas do projeto" no item 5. |
| D | **Condição de pagamento padrão** ao gerar parcelas no "Assinado" (item 5) quando o orçamento não especifica (ex.: 30% entrada / 40% / 30%). | O guia cita exemplos, mas não fixa um default. |
  | E | **Prestadores → contas a pagar** (item 6.2) — criar um módulo de contas a pagar, ou registrar os lançamentos de outra forma? | O guia de Prestadores diz "gerar os lançamentos no contas a pagar". Hoje **não existe** módulo de contas a pagar (o Financeiro só trata recebíveis). Mudança grande; precisa de decisão de escopo. |

---

## Guia 1 — Relatório Diário de Desenvolvimento

Fonte: `app/controllers/RdoController.php`, `app/core/RdoRules.php`,
`app/models/DailyReport.php`, `app/views/rdo/`.

### 1.1 🟩 Já conforme (manter)
- Aba de RDO com atividades, impedimentos/ocorrências, pendências e plano do
  próximo dia.
- Pendência de revisão automática para **preenchido após as 19h** (`late_fill`)
  e **data posterior/retroativo** (`post_deadline`) — `RdoRules::reviewTypeForCreate`.
- Edição de dia anterior/bloqueado exige **aprovação** (`requiresApprovalForEdit`
  + `approveEdit`/`rejectEdit`).
- Histórico completo (quem/o quê) em `daily_report_history`.
- Prazo das 19h configurável.

### 1.2 🟥 Pendência automática por relatório NÃO preenchido
**Guia:** gera pendência de revisão para o administrador quando o relatório não
foi preenchido no dia.
**Hoje:** a pendência só nasce no ato do preenchimento tardio; não há detecção de
ausência.
**A fazer:**
- Rotina (cron/`CronController`) que, após o horário limite (19h), para cada
  profissional elegível **sem** `daily_report` do dia, cria uma pendência de
  revisão do tipo `missing` (novo tipo) para o admin.
- Regra pura em `RdoRules` (ex.: `shouldFlagMissing(userId, date, hasReport, nowTime, deadline)`)
  para ficar testável.
- Idempotência: não duplicar a pendência se já existir para aquele dia/usuário.
- Definir "profissionais elegíveis" (quem tem o módulo `rdo` e perfil de dev/QA/etc.).
**Impacto:** schema (novo tipo de review / possível coluna), cron, regra pura.
**Testes:** Unit (regra `shouldFlagMissing`), Integration (cron cria 1 pendência,
não duplica).

---

## Guia 2 — Processo Comercial (Orçamento, Proposta e Contrato)

Fonte: `app/controllers/{Crm,Proposal,Contract}Controller.php`,
`app/core/{Proposal,Contract,Crm}Rules.php`,
`app/models/{Proposal,Contract,ServiceCatalog,CrmBoard,FinanceProject}.php`,
`app/views/commercial/`.

> Esta é a frente de maior esforço. Ordem sugerida: **2.1 → 2.3 → 2.4 → 2.5 → 2.6**
> (tipos de catálogo e módulos são pré-requisito da automação do "Assinado").
> **2.2** depende da decisão (A).

### 2.1 🟧 Catálogo de serviços com 3 tipos (hora / único / recorrente)
**Guia:** três tipos — por hora (valor/hora), único (valor unitário),
recorrente (valor/mês).
**Hoje:** `service_catalog` só tem `est_hours`, `hourly_rate`, `is_hosting`,
`active`. Só o modelo hora×valor/hora.
**A fazer (aditivo):**
- Coluna `type` ENUM(`hora`,`unico`,`recorrente`) com default `hora`
  (registros atuais viram `hora`, sem perda).
- Colunas `unit_price` (único) e `monthly_price` (recorrente).
- **Manter** `est_hours`/`hourly_rate`/`is_hosting`.
- UI do catálogo (`services.php`): seletor de tipo, campos condicionais.
- Validação em `ServiceCatalog`/regra pura por tipo.
**Testes:** Unit (validação por tipo), Integration (CRUD por tipo).

### 2.2 ⚠️ Item do orçamento obrigatoriamente do catálogo — **DEPENDE DA DECISÃO (A)**
**Guia:** nenhum item fora do catálogo.
**Hoje:** `Proposal::replaceItems` aceita linha livre (`service_id` opcional).
**A fazer (se a decisão A for "guia prevalece"):**
- Tornar `service_id` obrigatório em `replaceItems` e no form.
- Validação pura rejeitando itens sem `service_id`.
**Se a decisão A for "manter linha livre":** item descartado; registrar no doc
que o sistema permite exceção por design.

### 2.3 🟧 Orçamento por módulos + desconto + total à vista e total recorrente
**Guia:** orçamento tem módulos/frentes; cada item num módulo; cálculo de
subtotal, desconto, total à vista e total recorrente.
**Hoje:** `proposal_items` é lista plana; existe só um `total` único; sem
módulos, sem desconto, sem separação à vista/recorrente.
**A fazer (aditivo, migração sem perda):**
- Módulos — **ver decisão (C)**: tabela `proposal_modules` *ou* coluna
  `module_name` em `proposal_items`.
- Campo de desconto (percentual e/ou fixo) na proposta.
- Cálculo em `ProposalRules`: `subtotal`, `desconto`, `totalAVista`,
  `totalRecorrente` (recorrente = soma dos itens tipo `recorrente`).
- **Manter** `total` atual (passa a ser o "total à vista" ou derivado, sem
  quebrar leitura antiga).
- UI do `proposal_form.php`: agrupar itens por módulo; exibir os quatro totais.
**Testes:** Unit (todos os cálculos, incluindo desconto 0, só recorrente, misto),
Integration (salvar/ler proposta com módulos e recalcular).

### 2.4 🟥 Revisões versionadas (R01, R02...)
**Guia:** após envio, orçamento não é editado direto — cria **nova revisão**
preservando histórico.
**Hoje:** `Proposal::update`/`replaceItems` sobrescrevem; só há `proposal_events`
(log de status). Sem versionamento.
**A fazer:**
- Modelo de revisão: `parent_proposal_id` + `revision_no` (ou tabela
  `proposal_revisions` com snapshot). Decidir no detalhamento.
- Ao editar proposta já enviada (`sent`+), **criar nova revisão** em vez de
  sobrescrever; a anterior fica como histórico (read-only).
- Numeração R01, R02... e exibição do histórico na tela.
- Regra pura: quando uma edição exige nova revisão (status != draft).
**Testes:** Unit (decisão de versionar por status), Integration (editar enviada
cria R02, R01 intacta).

### 2.5 🟥 Automação ao marcar contrato "Assinado"
**Guia:** ao assinar, o sistema automaticamente (a) gera parcelas no contas a
receber conforme condição de pagamento; (b) cria cobrança recorrente se houver;
(c) cria projeto no board de execução com os módulos como etapas; (d) notifica
responsáveis internos; (e) vincula tudo ao número do orçamento; (f) inicia o
fluxo de onboarding.
**Hoje:** webhook `ContractController::clicksignWebhook` (ramo `signed`) só:
marca `signed`, cria **FinanceProject** (idempotente) e **notifica a equipe**
(d ✅; e parcial via `proposal_id`). **Faltam a, b, c, f.** O onboarding só
começa manualmente após a *entrada paga*.
**A fazer:**
- (a) Gerar parcelas (`FinanceRules`/`FinanceProject::buildChargePlan` +
  `replaceCharges`) a partir da **condição de pagamento** — **ver decisão (D)**
  para o default.
- (b) Criar cobrança **recorrente** (já há suporte a `recurring` + Asaas; falta
  o disparo automático a partir dos itens recorrentes do orçamento).
- (c) Criar **projeto no board** de execução usando os **módulos** (2.3) como
  etapas iniciais.
- (f) **Disparar o onboarding** respeitando a trava de entrada paga
  (`OnboardingRules::canStart`): criar/gatilhar o onboarding automaticamente, que
  **permanece bloqueado** até a entrada ser paga (não enfraquecer a regra atual).
- Idempotência em tudo (webhook pode repetir).
- **Manter** a criação do FinanceProject e a notificação já existentes.
**Depende de:** 2.3 (módulos) e decisão (D).
**Testes:** Unit (plano de parcelas por condição; mapeamento módulo→etapa),
Integration (assinar gera parcelas + recorrência + projeto + onboarding
bloqueado; reexecução não duplica).

### 2.6 🟧 Upload manual do contrato assinado
**Guia:** enviar para assinatura digital por link **ou** fazer upload manual do
contrato assinado.
**Hoje:** só ClickSign por link; `signed` só vem pelo webhook. Sem upload manual.
**A fazer (aditivo):**
- Endpoint em `ContractController` para upload do PDF assinado (armazenar como
  anexo do contrato) + transição para `signed` com evento registrado.
- Disparar a **mesma** automação do 2.5 (reaproveitar o caminho do webhook).
- **Manter** o fluxo ClickSign intacto.
**Testes:** Integration (upload → status signed → automações disparadas).

### 2.7 🟧 Funil CRM (registro de divergência conceitual)
**Guia:** funil com Rascunho→Enviado→Em negociação→Aceito→Contrato gerado→
Aguardando assinatura→Assinado; e "Perdido" com motivo obrigatório.
**Hoje:** CRM é **Kanban de colunas livres** (não enum fixo); proposta tem seu
próprio ciclo de status; motivo obrigatório existe na recusa de proposta/contrato,
mas não como "Perdido com motivo" no card.
**A fazer:** definir no detalhamento se criamos colunas/estados padrão que
espelhem o funil do guia **sem remover** a flexibilidade atual do Kanban, e se o
"Perdido" passa a exigir motivo. **Candidato a alinhar** junto com a frente CRM.

---

## Guia 3 — Onboarding do Cliente

Fonte: `app/controllers/OnboardingController.php`, `app/core/OnboardingRules.php`,
`app/models/Onboarding.php`.

### 3.1 🟩 Já conforme (manter)
- Checklist com responsáveis; `canStart` só com **entrada paga**; trava de
  conclusão por etapas obrigatórias (`canFinish`); acesso do cliente com
  login+PIN e notificação (`provisionClientAccess`).

### 3.2 🟧 Etapas faltantes no checklist
**Guia (checklist):** inclui "WhatsApp configurado para envios automáticos" e
"reuniões gravadas, transcritas e com resumo no Helpdesk".
**Hoje:** `OnboardingRules::defaultSteps()` não tem essas duas como etapas (o
WhatsApp é só campo de contato; gravação/transcrição vive no módulo de vídeo).
**A fazer (aditivo):**
- Adicionar steps `whatsapp_configured` e `meetings_recorded` ao `defaultSteps()`.
- Definir se são obrigatórias (sugestão: não obrigatórias, para não travar
  onboardings legados).
- **Não remover** nenhuma etapa existente; cuidar da migração de onboardings em
  andamento (steps novos entram como `pending`).
**Testes:** Unit (defaultSteps contém as novas), Integration (criar onboarding
gera as etapas).

### 3.3 🟧 Ambientes Dev / Homologação / Produção separados
**Guia:** ambientes Dev, Homologação e Produção criados.
**Hoje:** uma única etapa genérica `environment`.
**A fazer:** desdobrar em `env_dev`, `env_homolog`, `env_prod` (ou subitens),
**mantendo** compatibilidade com onboardings que já têm `environment`
(migração: `environment` concluído → os três como concluídos, ou manter o
antigo e só adicionar os novos daqui pra frente). Decidir no detalhamento.
**Testes:** Unit/Integration conforme a decisão de migração.

---

## Guia 4 — Fluxo de Recebimento de Demanda de Suporte

Fonte: `app/controllers/TicketsController.php`, `app/core/SupportRules.php`,
`app/core/TicketAccess.php`, `app/models/Ticket.php`.

### 4.1 🟩 Já conforme (manter)
- Gravidade e SLA de análise **exatos**: Crítico 30min, Alto 2h, Médio 4h,
  Baixo 8h (`SupportRules`).
- Flag de terceiros (`is_third_party`) e solução temporária
  (`support_workaround`).
- "Não assumir prazo" para origem externa (resolução definida caso a caso).

### 4.2 ⚠️ Fluxo/status nomeado de suporte — **DEPENDE DA DECISÃO (B)**
**Guia:** Abertura → Classificação → Análise → Resolução (ou solução temporária)
→ Validação com cliente → Encerramento.
**Hoje:** status reais são de dev/homologação (`open`, `in_progress`,
`aguardando_aprovacao_escopo`, `em_homologacao`, `aprovado_producao`, ...), que
são usados de verdade e **não podem ser removidos**.
**A fazer (após decisão B):** mapear as fases do guia sobre os status existentes
(ex.: Classificação ≈ definir gravidade; Validação ≈ `em_homologacao`→
`aprovado_producao`) e/ou adicionar o que faltar, sem apagar estados atuais.

### 4.3 🟥 Abertura automática de ticket por WhatsApp / telefone / e-mail
**Guia:** toda solicitação é registrada no Helpdesk, **inclusive** recebidas por
WhatsApp, telefone ou e-mail.
**Hoje:** registro manual, sim; ingestão **automática** (mensagem de WhatsApp /
e-mail virando ticket) não foi encontrada.
**A fazer:**
- Converter mensagem recebida (WhatsApp via `WhatsappController`/webhook; e-mail
  via `EmailMessageService`) em ticket, quando aplicável.
- Regras de desduplicação (não abrir ticket duplicado por thread).
- Telefone: provavelmente registro assistido (não há telefonia integrada) —
  confirmar expectativa.
**Depende de:** alinhamento sobre canais realmente integrados.
**Testes:** Integration (mensagem → ticket; não duplica).

---

## Guia 5 — Reuniões e Comunicação com Clientes

Fonte: `app/controllers/{Agenda,Videocall}Controller.php`,
`app/models/{AgendaMeeting,VideoRoom}.php`,
`app/core/MeetingMinutesRules.php`, `app/core/MeetingMinutesDelivery.php`.

### 5.1 🟩 Já conforme (manter)
- Agendamento vinculado ao contato/perfil do cliente (`AgendaMeeting.contact_id`).
- Gravação + transcrição (Whisper) no `VideocallController`.
- Minuta estruturada (tópicos, decisões, próximos passos, resumo) gerada e
  enviada ao cliente (`MeetingMinutesRules` + `MeetingMinutesDelivery`).

### 5.2 🟥 Reconhecimento / assinatura online da minuta pelo cliente
**Guia:** o cliente faz o **reconhecimento online (assinatura)** da minuta; sem
assinatura, o ponto não é formalmente aprovado; se discordar, revisa-se e
reenvia.
**Hoje:** a minuta é apenas gerada e enviada (PDF/link); não há aceite do
cliente (diferente do contrato, que tem aprovação pública por token).
**A fazer:**
- Página pública da minuta por token (análoga à do contrato) com ação de
  **reconhecer/assinar**.
- Persistir aceite (quem, quando, IP/registro) e status da minuta
  (enviada → reconhecida / contestada).
- Fluxo de "discordância": revisar e reenviar (nova versão da minuta).
- Regra pura para transições de status da minuta.
**Testes:** Unit (transições), Integration (token → reconhecer grava aceite;
contestar reabre).

---

---

# 2ª leva de guias (auditada em 2026-10-07)

> Esta leva confirma e detalha o Guia Comercial (já coberto no Guia 2 acima) e
> acrescenta 6 novos guias. **Boa notícia:** a maioria descreve fluxos que o
> sistema **já implementa bem** (Entrega/Garantia, Prestadores) ou processos de
> trabalho humano/ferramentas externas que não são responsabilidade do sistema
> (Esteira, Arquitetura). As pendências reais são poucas.

## Guia 6 — Comercial: Captação e Apresentação

Fonte: `app/controllers/CrmController.php`, `app/models/CrmBoard.php`,
`app/models/AgendaMeeting.php`, `MeetingMinutesRules`.

**O que o guia pede:** primeiro contato → agendamento → reunião de captação
(briefing) → registro no CRM + minuta em até 1h → análise → proposta → contrato
→ onboarding. Registrar o briefing no CRM vinculado ao perfil do cliente;
reunião com gravação/transcrição/minuta.

### 6.1 🟩 Já conforme (manter)
- Cadastro do potencial cliente/contato no CRM; agendamento vinculado ao perfil
  (`AgendaMeeting.contact_id`); reunião com gravação/transcrição/minuta
  (mesmos recursos do Guia 5).

### 6.2 🟧 Roteiro de briefing estruturado no CRM
**Guia:** registrar o briefing seguindo um roteiro (negócio, problema, resultado
esperado, contexto técnico, decisão e prazos).
**Hoje:** o CRM registra contato/card e notas livres; não há um formulário de
briefing com esses campos estruturados.
**A fazer (aditivo):** campo/estrutura de briefing no card do CRM (pode ser um
bloco de notas guiado ou campos). Baixo esforço se for textual.

### 6.3 🟧 Minuta da captação em até 1h / aceite
Mesma pendência do item **5.2** (reconhecimento/assinatura da minuta pelo
cliente). Reaproveita a implementação lá.

## Guia 7 — Arquitetura de Novos Projetos

Fonte: fluxo de reuniões (`AgendaController`, `VideocallController`,
`MeetingMinutesRules`), onboarding.

**O que o guia pede:** reunião de levantamento → transcrição → Minuta 1 →
diagrama visual → reunião de validação → Minuta 2 → **reconhecimento do cliente**
→ início do desenvolvimento. Tudo registrado no Helpdesk, minuta em até 1h.

### 7.1 🟩 Já conforme (manter)
- Reuniões com agendamento, gravação, transcrição e minuta estruturada
  (`MeetingMinutesRules` gera contexto, pontos, decisões, próximos passos).

### 7.2 🟧 Reconhecimento/assinatura da Minuta 1 e Minuta 2 pelo cliente
Mesma pendência do item **5.2**. O guia é explícito: "não iniciar o
desenvolvimento sem a arquitetura validada e **assinada**"; "a Minuta 2 assinada
vira a base do prompt mestre". Depende do item 5.2.

### 7.3 🟥 Diagrama visual / arquitetura como artefato no sistema
**Guia:** gerar um "diagrama visual do projeto" (módulos, telas, perfis,
integrações) e anexá-lo ao perfil do cliente no Helpdesk.
**Hoje:** não há entidade de diagrama/arquitetura; só anexos genéricos.
**A fazer:** avaliar se o diagrama é só um **anexo** (upload no perfil do
cliente/projeto — baixo esforço) ou um artefato estruturado. **Candidato a
alinhar** — provavelmente basta anexo + registro de "Minuta 2 assinada".
**Observação:** "prompt mestre", "ChatGPT" e o desenho em si são trabalho
externo/humano; o sistema só precisa **guardar e versionar** o artefato.

## Guia 8 — Esteira de Desenvolvimento (Dev → Homologação → Produção)

**O que o guia pede:** padrão técnico da casa (PHP MVC, migrations, nada de
`.env`, config em tela de admin, 3 conexões por domínio, testes PHPUnit +
Playwright, publicação via Plesk/phpMyAdmin, commit/branch no GitHub).

### 8.1 🟩 Majoritariamente fora do escopo do sistema (processo/infra)
Este guia descreve **como a equipe desenvolve e publica** usando ferramentas
externas (GitHub Desktop, Plesk, phpMyAdmin, ChatGPT, Kiro). Não é
funcionalidade do helpdeskON. O próprio código **já segue** o padrão descrito:
- Arquitetura PHP MVC (`app/controllers|models|core|views`).
- Migrations versionadas em `.sql` (há dezenas; nunca editar criado).
- Config em tela de admin (`app/views/admin/settings.php`), não em `.env`.
- 3 conexões por domínio detectadas em `config/database.php`
  (local/homolog/produção por host ou branch do Git).
- Testes PHPUnit + Playwright (steering `testes`/`e2e-playwright`).

### 8.2 ℹ️ Nenhuma implementação de produto necessária
Não há item de código a implementar aqui — é conformidade de processo. Vale
apenas **manter** o padrão (migrations, sem `.env`, testes no gate). Se desejar,
dá para registrar o `changelog.md`/`README.md` que o guia cita, mas isso é
documentação, não funcionalidade.

## Guia 9 — Fluxo de Entrega, Finalização e Garantia de Projeto

Fonte: `app/controllers/ProjectController.php`, `app/models/Project.php`,
`app/core/ProjectRules.php`.

### 9.1 🟩 Já conforme — fluxo quase completo (manter)
Este guia está **muito bem coberto**:
- Homologação final → publicação (`markPublished`) → documentação/manual
  (`markDocumentation`) → reunião de entrega (`linkDeliveryMeeting`) → **aceite
  formal do cliente por link público** (`generateAcceptanceLink` + `accept`/
  `confirmAccept`) → garantia.
- **Garantia de 90 dias** a partir da entrada em produção/aceite
  (`ProjectRules::DEFAULT_WARRANTY_DAYS = 90`, `warrantyEndDate`).
- **Aviso de 15 dias antes do fim** (`WARRANTY_WARNING_DAYS = 15`,
  `getWarrantyEndingSoon`, cron `warrantyWarnings`, `markWarrantyWarnSent`).
- **Bloqueio de chamados pós-garantia sem suporte** (`canOpenTicket`,
  `blockReason`) e flag de contrato de suporte (`setSupportContract`).
- Notificação ao cliente por e-mail/WhatsApp na publicação.

### 9.2 🟧 Aceite da entrega com **PIN + assinatura da minuta**
**Guia:** "o cliente realiza o aceite formal da entrega pelo link público **com
PIN e pela assinatura digital da minuta**".
**Hoje:** o aceite por link público existe (`accept`/`confirmAccept` por token).
Confirmar se exige **PIN** e se amarra a **assinatura da minuta** da reunião de
entrega. O PIN de cliente existe no sistema (`generateClientPin`,
`ClientPinRules`); falta confirmar que o fluxo de aceite do projeto o utiliza, e
a assinatura da minuta depende do item **5.2**.
**A fazer:** garantir PIN no aceite do projeto (se ainda não) + vincular à minuta
assinada. Pequeno/médio, parte compartilhada com 5.2.

### 9.3 🟩 "Chamados pós-garantia seguem o fluxo de suporte" (manter)
Já tratado por `canOpenTicket`/`support_contract`.

## Guia 10 — Contratação de Prestadores de Serviço

Fonte: `app/controllers/ProviderController.php`, `app/models/Provider.php`,
`app/core/{ProviderRules,ProviderAccessRules}.php`.

### 10.1 🟩 Já conforme — fluxo muito completo (manter)
Está **bem coberto**, espelhando a lógica comercial:
- Funil de contratação com status (Rascunho→Proposta→Aceita→Contrato→Assinado;
  Recusada/Cancelada) — `ProviderRules`/`Provider::changeStatus`.
- Proposta estruturada enviada por link público (`sendProposal`/`showProposal`/
  `acceptProposal`/`rejectProposal`), com motivo obrigatório na recusa.
- Contrato gerado a partir da proposta, com cláusulas de confidencialidade/
  propriedade intelectual/LGPD (`buildProviderContractHtml`) e **assinatura
  digital por link** (ClickSign) — `sendForSignature`/`clicksignWebhook`.
- Tipos de contratação (CLT, PJ mensal, PJ por hora, PJ por projeto) e revisão de
  valor com aprovação do gestor (`requestRevision`/`approveRevision`).
- Pós-assinatura: cria **acesso do prestador** (usuário + PIN + convite),
  registra **pendências de acesso** a conceder, notifica equipe/grupo
  (`onProviderSigned`).
- Encerramento com **revogação de todos os acessos** (`revokeAll`/`terminate`).

### 10.2 🟥 Gerar lançamentos no **contas a pagar** — **DEPENDE DA DECISÃO (E)**
**Guia (passo 7, "Após a assinatura"):** "gerar os lançamentos no **contas a
pagar** (mensal, por hora ou por parcelas)".
**Hoje:** **não existe** módulo de contas a pagar — o Financeiro
(`FinanceProject`/`FinanceAccount`) só trata **recebíveis** (entrada/parcela/
recorrente de clientes). O `onProviderSigned` cria acesso e pendências, mas
nenhum lançamento financeiro.
**A fazer (após decisão E):** criar o contas a pagar (schema + regras + tela) e,
ao assinar o contrato do prestador, gerar os lançamentos conforme o tipo
(mensal/hora/parcelas). Esforço grande.

### 10.3 🟩 "Apresentar guias operacionais ao prestador" (processo, manter)
O guia cita entregar os guias (RDO, Esteira) ao prestador — ação humana, sem
código.

---

## Resumo executivo (visão de esforço)

| Item | Guia | Estado | Esforço | Bloqueios |
|------|------|--------|---------|-----------|
| 1.2 Pendência de não-preenchimento | RDO | 🟥 | P | — |
| 2.1 Catálogo 3 tipos | Comercial | 🟧 | M | — |
| 2.2 Item só do catálogo | Comercial | ⚠️ | P | Decisão A |
| 2.3 Módulos + desconto + totais | Comercial | 🟧 | G | Decisão C |
| 2.4 Revisões R01/R02 | Comercial | 🟥 | G | — |
| 2.5 Automação do "Assinado" | Comercial | 🟥 | G | 2.3, Decisão D |
| 2.6 Upload manual do contrato | Comercial | 🟧 | M | (reusa 2.5) |
| 2.7 Funil CRM | Comercial | 🟧 | M | alinhar |
| 3.2 Etapas faltantes onboarding | Onboarding | 🟧 | P | — |
| 3.3 Ambientes Dev/Hml/Prod | Onboarding | 🟧 | P | migração |
| 4.2 Status de suporte nomeado | Suporte | ⚠️ | M | Decisão B |
| 4.3 Ingestão por WhatsApp/e-mail/tel | Suporte | 🟥 | G | canais |
| 5.2 Assinatura da minuta | Reuniões | 🟥 | M | — |
| 6.2 Roteiro de briefing no CRM | Captação | 🟧 | P | — |
| 6.3 Minuta da captação / aceite | Captação | 🟧 | — | = 5.2 |
| 7.2 Assinatura Minuta 1/Minuta 2 | Arquitetura | 🟧 | — | = 5.2 |
| 7.3 Diagrama/arquitetura como artefato | Arquitetura | 🟥 | P | alinhar |
| 8.x Esteira de desenvolvimento | Esteira | 🟩 | — | fora de escopo (processo) |
| 9.2 Aceite com PIN + assinatura da minuta | Entrega | 🟧 | M | parte = 5.2 |
| 10.2 Prestadores → contas a pagar | Prestadores | 🟥 | G | Decisão E |

Esforço: P = pequeno, M = médio, G = grande. Itens sem esforço reaproveitam
outro item (ex.: 6.3/7.2 dependem de 5.2).

**Guias já conformes (nada a implementar, só manter):** Entrega/Garantia (Guia 9,
exceto 9.2), Contratação de Prestadores (Guia 10, exceto 10.2), Esteira de
Desenvolvimento (Guia 8 — processo/infra).

## Ordem sugerida de execução

1. **Itens pequenos e isolados** (ganho rápido, sem bloqueios): 1.2, 3.2, 6.2, 7.3.
2. **Assinatura/reconhecimento de minuta (base compartilhada):** 5.2 — destrava
   6.3, 7.2 e parte de 9.2. Alto valor por desbloquear vários guias.
3. **Cadeia Comercial** (dependência interna): 2.1 → 2.3 → 2.4 → 2.5 → 2.6 →
   (2.2 e 2.7 conforme decisões).
4. **Suporte**: 4.2 (após decisão B) → 4.3.
5. **Onboarding**: 3.3 (definir migração).
6. **Grandes/condicionados a decisão:** 10.2 (contas a pagar, decisão E).

> Cada item é entregue com o **gate completo** (PHPUnit + Playwright verdes) antes
> de seguir para o próximo, salvo acordo em contrário.
