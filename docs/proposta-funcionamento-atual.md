# Proposta comercial — como está funcionando hoje

Documento de referência do estado **atual** do módulo de Proposta (esteira
comercial) no helpdeskON, já incluindo a evolução comercial (descontos,
recorrência/renovação, proposta online, aceite com plano, solicitação de
alteração).

> Atualizado em outubro/2026. Fonte: código em `app/`, `migrations/`, `tests/`.

---

## 1. Visão geral

A proposta é um **documento** gerado a partir de:

- **Conteúdo fixo** (igual em toda proposta): condições de pagamento/rescisão e
  notas, editáveis em *Propostas → Conteúdo fixo*. As páginas de apresentação do
  PDF (quem somos, o que fazemos, clientes, como fazemos) são imagens do modelo.
- **Conteúdo variável** (muda por cliente): entendimento do negócio, serviços do
  catálogo, valores, descontos, cronograma, projeção, recorrência/renovação,
  resumo.

O cliente recebe o **link principal da proposta online** (responsiva) **e o
PDF**. Pela proposta online ele vê tudo, abre o PDF, e no final **aceita**
(escolhendo o plano quando há mais de um), **recusa** (com motivo) ou
**solicita alteração**.

Espaços: **área interna** (módulo `proposals`: montar, salvar, ver PDF, ver
online, enviar) e **área pública** por token (proposta online, PDF, aceite,
recusa, solicitação de alteração).

---

## 2. Mapa de arquivos

| Papel | Arquivo |
|---|---|
| Controller (interno + público) | `app/controllers/ProposalController.php` |
| Regras puras (cálculo, estados, descontos, recorrência, apresentação) | `app/core/ProposalRules.php` |
| Model da proposta | `app/models/Proposal.php` |
| Model do texto fixo | `app/models/ProposalBlock.php` |
| Model do catálogo de serviços | `app/models/ServiceCatalog.php` |
| Controller do catálogo | `app/controllers/ServiceCatalogController.php` |
| Montagem do PDF | `app/core/ProposalPdf.php` |
| Template HTML do PDF | `app/views/commercial/proposal_pdf.php` |
| Conversão HTML→PDF (dompdf) | `app/core/PdfGenerator.php` |
| Gráficos do PDF (GD → PNG) | `app/core/ProposalChart.php` |
| Mensagens de envio/notificação | `app/core/ProposalDelivery.php` |
| Variáveis do contrato | `app/core/ContractTemplateVars.php` |
| View: listagem | `app/views/commercial/proposals.php` |
| View: montagem/edição | `app/views/commercial/proposal_form.php` |
| View: conteúdo fixo | `app/views/commercial/proposal_blocks.php` |
| View: catálogo de serviços | `app/views/commercial/services.php` |
| View: **proposta online pública** | `app/views/commercial/proposal_public.php` |
| Migration documento | `migrations/162_proposal_document.sql` |
| Migration comercial | `migrations/163_proposal_commercial.sql` |
| Testes unitários | `tests/Unit/ProposalRulesTest.php` |
| Testes de integração | `tests/Integration/ProposalTest.php` |

Rotas via `Router` no padrão `controller/action/param`
(`proposal/show/{token}` = online, `proposal/file/{token}` = PDF,
`proposal/accept|reject|change/{token}`, `proposal/pdf/{id}` = prévia interna).

---

## 3. Fonte única de apresentação

`ProposalRules::buildPresentationData($proposal, $items)` é a **fonte única**
consumida pelo PDF e pela proposta online — eles não recalculam nada por conta
própria. Produz:

- `items` com recorrência/renovação montada (`is_monthly`, `renewal`,
  `recurrence_period`, `renewal_label`);
- `commercial` — totais COM desconto: `once`/`monthly`/`year`, cada um com
  `gross`/`discount`/`net`, mais `monthly_by_month` (12 meses) e `has_schedule`;
- `totals_gross` (valores cheios, compatibilidade);
- `plans` e `plan_options` (vazio = aceite simples; preenchido = cliente escolhe);
- `projection` (12 meses, considerando o desconto temporal);
- `discounts`, `has_discount`, `timeline`, `vars` (variáveis de texto já líquidas).

---

## 4. Descontos comerciais estruturados

Guardados em `proposals.discounts` (JSON). Cada desconto:

```json
{ "scope": "once|monthly|total", "type": "percent|fixed|schedule",
  "value": 10, "label": "...",
  "months": [ { "month": 1, "type": "percent", "value": 20 } ] }
```

- `scope`: incide no pontual, na mensalidade ou no **total** (rateado entre
  pontual e mensal na proporção de cada um).
- `type`: percentual, valor fixo ou **por período** (`schedule`, só na
  mensalidade — ex.: 1º mês 20%, 2º mês 10%, demais cheios).

Regras em `ProposalRules`: `normalizeDiscounts`, `applyDiscounts`,
`monthlyNetByMonth` (desconto temporal), `commercialTotals` (cheio/desconto/
líquido). Valor cheio, desconto e líquido aparecem no resumo interno, no PDF
(página de Investimento) e na proposta online.

---

## 5. Recorrência e renovação estruturadas

- `proposal_items.renewal`: `none` / `auto` / `manual`.
- `proposal_items.recurrence_period`: `monthly` / `yearly` (NULL = pontual).
- Espelhadas em `service_catalog` para alimentar os itens.
- `ProposalRules::renewalLabel()` gera o rótulo exibido no PDF e no online.
- Item mensal sem fim segue como `ongoing` ("A partir de"), preservado.

---

## 6. Projeção de 12 meses

Item pontual entra no mês em que começa; item mensal entra todo mês a partir do
início; retorno estimado começa no mês seguinte ao fim do desenvolvimento;
devolve investido/retorno acumulados, `payback` e `roi`.

`projection()` aceita um parâmetro opcional com a mensalidade **líquida por mês**;
havendo desconto por período, usa o valor líquido de cada mês em vez da
mensalidade cheia (sem o parâmetro, mantém o comportamento anterior).

---

## 7. Máquina de estados

`draft → ready → sent → awaiting → accepted/rejected/cancelled`.
`rejected` pode voltar a `draft` (refazer). `accepted`/`cancelled` são
terminais. Transição ao mesmo estado é idempotente. `canTransition` valida.

---

## 8. Proposta online (link principal)

`proposal/show/{token}` é a **proposta online completa e responsiva**
(`commercial/proposal_public.php`), reproduzindo em HTML as seções do PDF a
partir de `buildPresentationData`: investimento (com desconto), entendimento do
negócio, serviços (com recorrência/renovação), cronograma, planos, condições e
notas. Traz:

- botão "Ver / baixar PDF" (`proposal/file/{token}` segue entregando o PDF);
- aceite embutido, com **seleção de plano** quando há mais de uma opção;
- recusa com motivo;
- solicitação de alteração;
- CTA fixo no rodapé em telas pequenas (mobile-first).

Estados terminais (aceita/recusada/cancelada) são refletidos e escondem o aceite.

---

## 9. Aceite, recusa e solicitação de alteração

- **Aceite** (`proposal/accept/{token}`): quando há `plan_options`, exige o
  plano escolhido; grava `proposals.accepted_plan`, evento `accepted` (com o
  plano) e notifica a equipe. Bloqueia novo aceite (estado terminal).
- **Recusa** (`proposal/reject/{token}`): motivo obrigatório, evento `rejected`,
  notifica a equipe e devolve o card do lead para "Análise interna".
- **Solicitação de alteração** (`proposal/change/{token}`): registra evento
  `change_request` com a mensagem do cliente, notifica a equipe (sino +
  WhatsApp) e **não** torna a proposta terminal (equipe edita e reenvia).

Tudo auditável em `proposal_events` (data/hora, tipo, descrição).

---

## 10. Envio ao cliente

`send()` promove a proposta para `sent`, gera o PDF e entrega ao cliente o
**link da proposta online + o PDF**, por WhatsApp e e-mail
(`ProposalDelivery::clientWhatsapp`/`clientEmailBody`). Notifica a equipe. Os
disparos nunca interrompem a resposta.

---

## 11. Contrato após o aceite

`contract/fromProposal` (botão "Gerar contrato" quando aceita) chama
`Contract::createFromProposal`, que usa `ContractTemplateVars::valuesFromProposal`.
Variáveis de modelo: `{{cliente_nome}}`, `{{proposta_total}}` e, acrescentadas,
`{{proposta_total_liquido}}`, `{{proposta_pontual}}`, `{{proposta_mensalidade}}`,
`{{proposta_desconto}}`, `{{plano_aceito}}` — calculadas via `commercialTotals`
com os itens da proposta. O contrato aproveita cliente, valores (com desconto) e
o plano aceito, sem recadastro.

---

## 12. PDF

A4 landscape; páginas 1–5 são imagens do modelo
(`public/assets/proposal/modelo-0N.jpg`); cabeçalho/rodapé/numeração desenhados
por cima; gráficos via `ProposalChart`. Consome `buildPresentationData`: mostra
subtotal, desconto comercial e total líquido na página de Investimento, a tabela
de descontos aplicados (inclusive mês a mês quando há desconto por período) e a
renovação estruturada no escopo dos serviços mensais. Toda a estrutura anterior
(cronograma, projeção, planos, condições, aceite) foi preservada.

---

## 13. Banco de dados

- **162** (`proposals`): `dev_start`, `dev_end`, `fixed_snapshot`, `content`;
  (`proposal_items`): `billing`, `quantity`, `schedule_start/end`;
  (`service_catalog`): `billing`. Cria `proposal_blocks`.
- **163** (`proposals`): `discounts`, `accepted_plan`;
  (`proposal_items`): `renewal`, `recurrence_period`;
  (`service_catalog`): `renewal`, `recurrence_period`.

Ambas idempotentes e retrocompatíveis (colunas NULL/com default seguro;
propostas antigas continuam válidas).

---

## 14. Testes

- **Unit** (`ProposalRulesTest`): regras de status/transições, cálculo de item,
  totais, cronograma, projeção, planos, produtos a mais, e os novos: descontos
  (percent/fixed/schedule), rateio do `total`, desconto temporal, totais
  comerciais, recorrência/renovação, projeção com desconto e
  `buildPresentationData` (com/sem plano). Suíte unit: **487 testes, OK**.
- **Integração** (`ProposalTest`, contra `helpdesk_on_test`): criação, itens,
  transições, aceite, recusa, catálogo e os novos: persistência de
  recorrência/renovação, descontos + aceite com plano, `buildPresentationData`
  com proposta real. Suíte ProposalTest: **11 testes, OK**.
- **E2E** (Playwright): suíte verde (smoke + vídeo), **4 passed**.

> Ambiente desta máquina: banco em **MySQL 8.4** (porta 3306) com
> `only_full_group_by` ativo. Isso faz testes de integração de OUTROS módulos
> (WhatsApp, Minutes) falharem por queries com GROUP BY incompatível — falhas
> **pré-existentes**, não relacionadas à proposta.

---

## 15. Pontos de validação manual

- Disparo real de WhatsApp/e-mail depende de credenciais externas.
- Renderização visual do PDF (dompdf + GD + fontes) e da proposta online no
  navegador — recomendado conferir `proposal/pdf/{id}` e `proposal/show/{token}`.
- As imagens do modelo (`public/assets/proposal/modelo-0N.jpg`) precisam existir.
