# API v1 — Criação de Chamados (helpdeskON)

Guia de integração para sistemas externos criarem chamados (demandas) no
helpdeskON de forma automática.

Esta versão (v1) tem um único objetivo: **permitir que um sistema externo abra
chamados**. Não há, nesta versão, endpoints para consultar, editar ou excluir
chamados.

---

## Visão geral

- **Endpoint:** `POST /api/v1/tickets`
- **Autenticação:** cabeçalho `X-Api-Key`
- **Formato:** JSON (request e response)
- **Vínculo com empresa:** cada empresa tem uma única chave (relação 1:1). Os
  chamados criados com ela são registrados automaticamente em nome do usuário de
  integração daquela empresa — o sistema externo não informa usuário nem empresa.

---

## Autenticação

Toda requisição deve enviar a chave no cabeçalho `X-Api-Key`:

```
X-Api-Key: hk_live_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

A chave é gerada no painel (Configurações → Integrações → API Keys de
integração). Modelo simplificado:

- **Uma chave por empresa.** Cada empresa tem uma única chave, exibida na lista
  de empresas do painel. Basta clicar em **Gerar chave** na empresa desejada.
- A chave fica visível no painel (com botão de copiar) para você repassá-la ao
  sistema do cliente por canal seguro.
- Trate a chave como uma senha: não a exponha em código público ou no navegador
  do usuário final.
- Troca de chave (evento raro, ex.: vazamento) é feita diretamente no banco.

---

## Campos do request

| Campo               | Tipo   | Obrigatório | Valores aceitos                     | Observação |
|---------------------|--------|-------------|-------------------------------------|------------|
| `title`             | string | Sim         | 1 a 255 caracteres                  | Título do chamado. |
| `description`       | string | Sim         | texto                               | Descrição do chamado. |
| `priority`          | string | Não         | `low`, `medium`, `high`, `urgent`   | Padrão: `medium`. |
| `category`          | string | Não         | até 100 caracteres                  | Categoria livre. |
| `requester_name`    | string | Não         | livre                               | Anexado ao topo da descrição para rastreabilidade. |
| `requester_company` | string | Não         | livre                               | Anexado ao topo da descrição para rastreabilidade. |
| `external_ref`      | string | Não         | até 191 caracteres                  | Identificador do chamado no sistema externo. Usado para idempotência (ver abaixo). |

Observações:

- O `status` do chamado sempre nasce como `open`; não é aceito no request.
- `requester_name` e `requester_company` não são colunas do chamado; quando
  informados, são adicionados ao início da descrição.

---

## Exemplo de request

```bash
curl -X POST https://SEU-DOMINIO/api/v1/tickets \
  -H "X-Api-Key: hk_live_suachave" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Erro ao emitir nota fiscal",
    "description": "Ao clicar em emitir, o sistema retorna erro 500.",
    "priority": "high",
    "category": "suporte",
    "requester_name": "Maria Souza",
    "requester_company": "ACME Ltda",
    "external_ref": "CHAMADO-2026-000123"
  }'
```

Exemplo em PHP (dentro do sistema do cliente):

```php
$ch = curl_init('https://SEU-DOMINIO/api/v1/tickets');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'X-Api-Key: hk_live_suachave',
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'title' => 'Erro ao emitir nota fiscal',
        'description' => 'Retorna erro 500 ao emitir.',
        'priority' => 'high',
        'external_ref' => 'CHAMADO-2026-000123',
    ]),
    CURLOPT_RETURNTRANSFER => true,
]);
$response = curl_exec($ch);
$httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
```

---

## Exemplos de response

Sucesso — `201 Created`:

```json
{
  "success": true,
  "data": {
    "id": 4821,
    "client_ticket_number": 37,
    "title": "Erro ao emitir nota fiscal",
    "status": "open",
    "priority": "high",
    "category": "suporte",
    "company_id": 12,
    "external_ref": "CHAMADO-2026-000123",
    "created_at": "2026-09-23 14:20:11"
  }
}
```

Idempotente — `200 OK` (já existia um chamado com o mesmo `external_ref` para a
mesma empresa; nenhum chamado novo é criado):

```json
{
  "success": true,
  "idempotent": true,
  "data": {
    "id": 4821,
    "client_ticket_number": 37,
    "status": "open"
  }
}
```

Erro de validação — `422`:

```json
{
  "success": false,
  "error": {
    "code": "validation_error",
    "message": "Título e descrição são obrigatórios.",
    "fields": ["title", "description"]
  }
}
```

---

## Idempotência (evitar chamados duplicados)

Se o sistema externo enviar `external_ref`, o helpdeskON garante que **o mesmo
`external_ref` não cria dois chamados** para a mesma empresa. Em um reenvio
(retry) com o mesmo `external_ref`, a resposta é `200 OK` com `"idempotent": true`
e os dados do chamado já existente — sem criar um novo.

Recomendação: gere um `external_ref` único por chamado no seu sistema e reenvie
o mesmo valor em caso de timeout/retry. Sem `external_ref`, cada requisição cria
um novo chamado.

---

## Códigos HTTP e erros

| Situação                         | HTTP | `error.code`         |
|----------------------------------|------|----------------------|
| Chamado criado                   | 201  | —                    |
| Idempotente (já existia)         | 200  | —                    |
| Método diferente de POST         | 405  | `method_not_allowed` |
| Corpo JSON inválido              | 400  | `bad_request`        |
| Cabeçalho `X-Api-Key` ausente    | 401  | `missing_api_key`    |
| Chave inválida                   | 401  | `invalid_api_key`    |
| Campos obrigatórios ausentes     | 422  | `validation_error`   |
| Valor inválido (ex.: `priority`) | 422  | `invalid_value`      |
| Erro interno                     | 500  | `internal_error`     |

Formato de erro (padrão):

```json
{ "success": false, "error": { "code": "...", "message": "..." } }
```

---

## O que acontece ao criar um chamado

Um chamado criado via API passa pelo mesmo fluxo interno de uma demanda criada
pela interface:

- É registrado na fila/lista de chamados da empresa correspondente;
- Gera automaticamente o card no Planejamento (PlanningCard);
- Dispara as notificações internas (e webhook, se configurado).

---

## Como testar

Para validar a integração, use uma ferramenta de requisições HTTP (curl,
Postman, Insomnia) com a chave da empresa e o exemplo de request acima. Um
retorno `201` com um `id` confirma que o chamado foi criado — verifique também
que ele aparece na lista de Demandas e no Planejamento.

---

## Requisitos de instalação (lado do helpdeskON)

Para a API funcionar, as seguintes migrations precisam estar aplicadas no banco:

- `migrations/133_api_keys.sql` — tabela de API Keys.
- `migrations/134_tickets_external_ref.sql` — coluna e índice de idempotência.
