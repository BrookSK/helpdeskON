# Roteiro de teste ponta a ponta — Esteira comercial

Objetivo: percorrer a demanda inteira, da captação à entrega, confirmando que
**cada etapa realmente aconteceu** (valor gravado, status mudou, webhook chegou)
— não só que a tela respondeu.

## Pré-requisitos
- Migrations da esteira aplicadas no banco do ambiente (`migrations/_esteira_consolidado.sql`).
- APIs configuradas em **Configurações**: ClickSign (sandbox), Asaas (3 contas, sandbox),
  LRV Cloud (`lrv_test_`), com os **webhooks** apontando para as URLs do sistema.
- Logado como **super_admin** (enxerga todos os módulos).
- Tenha à mão um e-mail e um WhatsApp seus para fazer o papel do "cliente".

> Dica: a URL de cada webhook está no botão **"? Como configurar"** de cada integração
> nas Configurações. Se o ambiente não tiver URL pública acessível pela internet, os
> webhooks externos não chegam — use os botões manuais que o roteiro indica como alternativa.

---

## Fase 0 — Reunião + gravação (opcional, se for testar a origem)
1. Agenda → crie uma reunião com sala de vídeo (deixe **Gravar automaticamente** marcado).
2. Entre na sala. **Confirme:** a gravação inicia sozinha.
3. Com 3 participantes, confira o layout lado a lado.
4. Encerre. Em **Gravações**, abra a reunião.
   - **Confirme:** vídeo com a duração real (precisa de ffmpeg no servidor — senão, validação manual).
   - **Confirme:** aba **Minuta** gera a ata (precisa OpenAI configurada).
   - Botão **Enviar minuta** → chega o link por WhatsApp/e-mail (precisa Evolution/SMTP).

---

## Fase 1 — Lead no CRM
1. CRM → crie/escolha um lead (contato). Preencha o briefing comercial.
2. **Confirme:** o lead aparece no board com os dados.

---

## Fase 2 — Proposta
1. **Propostas** → Nova proposta. Vincule ao lead; dê um título.
2. Adicione itens (ex.: "Desenvolvimento", 40h × R$ 150). **Confirme:** total calcula sozinho (= 6.000).
3. Salve. Clique em **Enviar** → gera o **link público**.
4. Abra o link público numa aba anônima (papel do cliente).
   - **Confirme:** a proposta aparece com itens e total; status vira `awaiting` ao abrir.
5. No link público, clique **Aceitar**.
   - **Confirme (interno):** status da proposta = `accepted`; chega notificação ao criador.
   - **Teste do negativo:** em outra proposta, clique **Recusar** sem motivo → deve exigir motivo;
     com motivo → volta para o CRM.

---

## Fase 3 — Contrato + ClickSign
1. Na proposta **aceita**, botão **Gerar contrato** → escolha um modelo (crie um em Contratos antes, se não houver).
2. Em **Contratos**, abra o contrato (status `draft`). Edite o corpo se quiser.
3. **Enviar para aprovação** → status `client_review`. Abra o link público (cliente) e **Aprovar**
   (ou "Pedir ajuste" com motivo = teste do negativo).
   - **Confirme:** aprovado → status `approved`.
4. **Enviar para assinatura** → o sistema chama a ClickSign (sandbox).
   - **Confirme:** status `awaiting_signature`; `clicksign_doc_key` preenchido (veja no histórico/evento).
   - **Confirme:** o signatário recebe o e-mail de assinatura da ClickSign (sandbox).
5. Assine pela ClickSign (sandbox).
   - **Confirme (webhook):** o status vira `signed` sozinho (via `/contract/clicksignWebhook`),
     com evento "Assinatura confirmada pela ClickSign".
   - Se o webhook não chegar (ambiente sem domínio público): **validação manual** — confira no
     painel ClickSign que o documento fechou.

---

## Fase 4 — Financeiro + Asaas
1. No contrato **assinado**, botão **Iniciar financeiro** → cria o projeto financeiro (puxa o total da proposta).
2. Em **Financeiro → [projeto]**, defina o plano: ex. total 40.000, **entrada** 20.000 (vencimento amanhã, Pix),
   **5 parcelas** de 4.000 (boleto). Gerar plano.
   - **Confirme:** aparecem 6 cobranças (1 entrada + 5 parcelas), somando 40.000.
3. Confirme o pagamento da **entrada**:
   - **Caminho real:** pague a cobrança no Asaas (sandbox) → webhook `/finance/asaasWebhook` marca como paga.
   - **Caminho manual (sem webhook):** botão **Marcar paga** na entrada.
   - **Confirme:** o projeto vira `entry_paid` e aparece o banner **"Entrada paga — onboarding liberado"**.
   - **Teste do negativo:** antes de pagar a entrada, tente o onboarding → deve recusar ("entrada ainda não paga").

---

## Fase 5 — Onboarding
1. No projeto financeiro com entrada paga, botão **Iniciar onboarding** → abre o onboarding (status `blocked`).
2. Clique **Iniciar onboarding** no detalhe → status `in_progress`.
3. Percorra o **checklist**: conclua as etapas. As **obrigatórias** pedem confirmação do requisito.
   - **Confirme:** etapa obrigatória não conclui sem confirmar; as opcionais concluem direto.
4. Cadastre um **ponto focal** (nome, cargo). **Confirme:** aparece na lista.
5. Com todas as obrigatórias concluídas, botão **Concluir onboarding** → status `done`.
   - **Teste do negativo:** tente concluir com obrigatória pendente → lista as pendências.

---

## Fase 6 — Provisionamento (LRV Cloud)
1. No onboarding concluído, botão **Provisionar infra** → cria o provisionamento.
2. **Iniciar** → status `in_progress`. Execute as etapas na ordem:
   - **Criar cliente** → `POST /clients` (sandbox). **Confirme:** etapa `done` com `ref` (id do cliente).
   - **Provisionar VPS** → informe o **plano** (campo `plan`). **Confirme:** 202 e `lrv_vps_id` salvo;
     webhook `hosting.ready` conclui a etapa.
   - **Criar banco** → **Confirme:** credenciais retornadas (senha uma única vez).
   - **Criar aplicação (Git)** → informe o `git_repo`. **Confirme:** app criada + `staging_url` (staging com SSL).
   - **Deploy** → **Confirme:** `application.deployed` conclui a etapa.
3. **Reunir credenciais** e **Entregar** são manuais → marque como feitas.
4. **Concluir**.
   - Em sandbox os writes são simulados; valide pelo retorno/ids. Em produção, confirme a VPS/app no painel LRV Cloud.

---

## Fase 7 — Credenciais no cofre
1. **Credenciais** (só super_admin) → Nova credencial (ex.: "Servidor VPS", usuário, senha).
2. **Confirme:** na lista a senha aparece **mascarada** (••••••••).
3. Clique no olho **Revelar** → mostra o valor; reesconde em 15s.
   - **Confirme (auditoria):** o acesso de "revelar" fica registrado (log de atividade).

---

## Fase 8 — Projeto + Garantia
1. **Projetos** → Novo projeto (tipo **"zero"** para ter garantia de 90 dias). Vincule à empresa.
2. **Marcar entregue**.
   - **Confirme:** status vira `warranty`; "Garantia até" = hoje + 90 dias.
3. **Teste do negativo (bloqueio pós-garantia):** crie um projeto tipo "outro", marque entregue →
   status `delivered` sem garantia → o painel indica **chamados bloqueados**. Ative **Contrato de suporte** → libera.

---

## Fase 9 — Ponte lead→cliente + PIN do cliente
1. (Ponte) Ao converter um lead, o sistema cria/reaproveita **empresa + usuário cliente** e registra o vínculo.
   **Confirme:** o usuário cliente nasce com `role=client` ligado à empresa e recebe o convite de primeiro acesso.
2. (PIN do cliente) Gere o **PIN de 6 dígitos** para o usuário cliente.
3. Abra **`/clientpin`** numa aba anônima, informe o PIN.
   - **Confirme:** cai direto na **Nova demanda** vinculada a ESSE cliente.
   - Crie uma demanda. **Confirme:** aparece no Planejamento/Demandas ligada à empresa certa.
   - **Teste do negativo (não quebrou o antigo):** `/solicitacaoexterna` com o PIN de **equipe**
     (4 dígitos) continua funcionando normal e separado.

---

## Checklist final (fora a fora)
- [ ] Lead → Proposta (total calcula, aceite público funciona)
- [ ] Proposta aceita → Contrato → aprovação do cliente → **assinatura confirmada por webhook**
- [ ] Contrato assinado → Financeiro → plano de cobranças → **entrada paga destrava onboarding**
- [ ] Onboarding por etapas (obrigatórias travam) → concluído
- [ ] Provisionamento LRV Cloud (cliente→VPS→banco→app→deploy→staging)
- [ ] Credenciais criptografadas (máscara + revelar auditado)
- [ ] Projeto entregue com garantia 90d; bloqueio pós-garantia sem suporte
- [ ] PIN do cliente abre nova demanda vinculada; `/solicitacaoexterna` intacto

## Pontos de "validação manual" (dependem de serviço externo real)
- Assinatura ClickSign fechando o documento (confirme no painel ClickSign).
- Pagamento Asaas confirmado (confirme no painel Asaas; sem domínio público use "Marcar paga").
- Provisionamento LRV Cloud real (em sandbox é simulado; confirme ids/retornos).
- Envio real de WhatsApp (Evolution conectado) e e-mail (SMTP).
- Transcrição/minuta (OpenAI) e duração da gravação (ffmpeg no servidor).

> Se algum webhook não chegar, não é bug da esteira: é o ambiente sem URL pública alcançável
> pela plataforma externa. Confirme a URL do webhook no "? Como configurar" de cada integração.
