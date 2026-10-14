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

## Git Repositories (criação de repo na org + acesso aos devs) — RESOLVIDO

> Decisão (cliente/dev da LRV): a criação do repositório na organização e a
> concessão de acesso aos desenvolvedores são feitas **pela API do LRV Cloud**,
> não por integração GitHub direta no helpdeskON. O helpdeskON apenas **consome**.
> A API v1 já expõe os endpoints (conferido na OpenAPI atualizada) e eles já
> são consumidos aqui.

### Endpoints usados (contrato real da OpenAPI v1)

| Necessidade | Método/endpoint | Payload | Retorno |
|-------------|-----------------|---------|---------|
| Criar repositório na org | `POST /api/v1/git/repositories` | `name` (obrig.), `private` (default true), `org?`, `description?`, `external_ref?`, `client_id?` | 201 `data.{id, full_name, html_url, clone_url, ssh_url, visibility}`; dispara `git.repository.created` |
| Detalhe (com colaboradores) | `GET /api/v1/git/repositories/show?id=` | — | dados do repo |
| Conceder acesso a devs | `POST /api/v1/git/repositories/collaborators` | `id` (repo), `usernames[]`, `permission` (pull/triage/push/maintain/admin, default push) | 200 `data.collaborators` + `data.errors`; dispara `git.collaborator.added` |
| Revogar acesso | `POST /api/v1/git/repositories/collaborators/remove` | `id`, `username` | 200; dispara `git.collaborator.removed` |

> Erros possíveis: 502 (o provedor Git retornou erro), 503 (integração Git não
> configurada na plataforma). Escopo exigido: `git.write`.

### Como o helpdeskON consome (lado nosso — implementado)

- `LrvCloudApi`: `createRepository()`, `showRepository()`,
  `addRepositoryCollaborators()`, `removeRepositoryCollaborator()` — mesmo padrão
  de `request()` (header `X-API-Key`, retorno `success/available/http/data/error`).
- Etapas de provisionamento dependentes do **pipeline** (`ProvisioningRules::defaultSteps($cap, $pipeline)`):
  - **fora_esteira**: `create_client → create_repo → grant_dev_access → create_vps
    → create_database → create_app → deploy → staging → collect_credentials → deliver`.
    O `create_repo` salva `lrv_repo_id` + `git_repo` (clone_url) + `repo_url`
    (html_url); o `create_app` usa esse `git_repo` automaticamente.
  - **esteira_cx**: `create_client → register_cx_repo (manual) → grant_dev_access
    (manual) → collect_credentials → deliver`. Não cria repo/VPS/app pela nossa
    automação — o repo é do CX e o analista registra; só o acesso ao repo importa.
- Webhooks mapeados em `interpretEvent`: `client.created`, `git.repository.created`,
  `git.collaborator.added`, além de `hosting.*`, `application.*`, `domain.added`.
- Migration **158** adiciona `provisionings.lrv_repo_id` e `provisionings.repo_url`.
- `grant_dev_access` recebe os usernames do GitHub via POST `dev_usernames`
  (lista separada por vírgula/espaço) e `dev_permission` (default push).
