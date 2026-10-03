# API LRV Cloud — provisionamento (Fase 7) — RESOLVIDO

> Status: **os 5 gaps foram implementados pelo time do LRV Cloud (API v1.1.0)** e
> já são consumidos pelo helpdeskON. Conferido na OpenAPI v1 atualizada
> (`https://cloud.lrvweb.com.br/api/v1/openapi.yaml`). Este documento vira o
> registro do contrato final usado pela integração.

## Endpoints usados pelo provisionamento (todos disponíveis)

| Etapa | Método/endpoint | Observações |
|-------|-----------------|-------------|
| Criar cliente | `POST /api/v1/clients` | escopo `clients.write`; dispara `client.created`. Cliente-filho vinculado à conta (revenda) via `created_by_client_id`. |
| Provisionar VPS | `POST /api/v1/hosting` (202) | `plan` por id ou nome; `client_id` opcional (aceita filhos). Dispara `hosting.created` e `hosting.ready`. |
| Suspender/Parar VPS | `POST /api/v1/hosting/suspend`, `POST /api/v1/hosting/stop` | ciclo de vida; `hosting.suspended`. |
| Criar banco | `POST /api/v1/databases` | body `vps_id, db_name, db_user?, db_type`; senha retornada **uma única vez**. |
| Criar app + deploy Git | `POST /api/v1/applications/git` (202) | `vps_id, name, git_repo, git_branch, runtime, domain?, staging_subdomain?`; dispara `application.installed`. |
| Homologação | flag `staging_subdomain: true` no deploy Git | gera subdomínio `.lrvweb` temporário com SSL e retorna `staging_url`. |
| Re-deploy | `POST /api/v1/applications/git/deploy` | dispara `application.deployed`. |
| Detalhe do app | `GET /api/v1/applications/git/show?id=` | status, último commit, `staging_url`. |
| Domínio | `POST /api/v1/domains` | exige `vps_id`. |

## Modelo de isolamento (decisão do LRV Cloud)

Não há "organização/revenda" como entidade; o vínculo é `created_by_client_id`: a
API key do parceiro (helpdeskON) cria clientes-filhos e provisiona/gerencia VPS e
apps deles, com isolamento multi-tenant intacto (ninguém enxerga recurso de outra
árvore). O helpdeskON encadeia as referências: `client_id -> vps_id -> app_id ->
staging_url`, guardadas em `provisionings`.

## Como o helpdeskON consome (lado nosso)

- `LrvCloudApi` implementa as chamadas reais (createClient, createVps,
  suspendVps/stopVps, createDatabase, createApplication, deployApplication,
  showApplication, addDomain).
- `ProvisioningRules::apiCapabilities()` está com todas as etapas de infra em
  `auto`. Se algum endpoint for desativado, basta virar o flag para `false` e a
  etapa volta a ser pendência **manual** rastreável (comportamento preservado).
- Confirmação assíncrona por **webhook** (`hosting.created`/`hosting.ready`,
  `application.installed`/`application.deployed`, `domain.added`) em
  `/provisioning/lrvWebhook` (HMAC-SHA256 validado quando o secret está configurado).

## Configuração necessária (validação manual, fora do ambiente de teste)

- Settings `lrv_cloud_api_key` (chave `lrv_live_...` ou `lrv_test_...` para sandbox).
- Settings `lrv_cloud_webhook_secret` + registrar o webhook apontando para
  `/provisioning/lrvWebhook?id={provisioning_id}` no painel do LRV Cloud.
- O envio/efeito real (criar VPS/app de verdade) depende de credenciais e só é
  validável no ambiente com a API ativa — não é testável no ambiente de dev.
