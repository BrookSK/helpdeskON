# API v1 — Criação de Chamados (helpdeskON)

Guia de integração para sistemas externos criarem chamados (demandas) no
helpdeskON de forma automática.

Esta versão (v1) cobre dois fluxos:

1. **Envio (sistema externo → helpdeskON):** o sistema externo abre chamados
   via `POST /api/v1/tickets`.
2. **Retorno de status (helpdeskON → sistema externo):** de forma opcional, o
   helpdeskON avisa o sistema externo sempre que o **status** de um chamado muda,
   fazendo um `POST` para uma **URL de callback** cadastrada por empresa (ver a
   seção "Callback de status" mais abaixo).

Não há, nesta versão, endpoints para o sistema externo consultar, editar ou
excluir chamados.

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

## Callback de status (helpdeskON → sistema externo)

Além de receber chamados, o helpdeskON pode **avisar o sistema externo quando o
status de um chamado muda**. Assim, o sistema externo mantém o próprio registro
em dia sem precisar ficar consultando.

### Como funciona

- O callback é **por empresa** e **opcional**. Ele é ativado no painel do
  helpdeskON (Configurações → bloco "Integração — API de demandas"), informando
  a **URL de callback** da empresa e marcando "Ativo"; as alterações são gravadas
  pelo botão "Salvar Configurações".
- O callback é enviado **apenas para chamados criados via API** (que têm
  `external_ref`). Mudanças de status de demandas criadas internamente pela
  equipe (sem `external_ref`) **não** geram callback — assim o sistema externo
  só recebe de volta o que ele mesmo enviou.
- Sempre que o status de um desses chamados muda, o helpdeskON faz um
  `POST` (JSON) para essa URL.
- A entrega é **assíncrona** (fila processada por cron). Isso garante que a
  operação interna do helpdeskON não trave caso o sistema externo esteja lento
  ou fora do ar. Em caso de falha, há **reenvio automático** (até 3 tentativas).
- O chamado é identificado pelo `external_ref` — a mesma referência que o sistema
  externo enviou na criação. Por isso, recomenda-se sempre enviar `external_ref`.

> Observação: nesta versão o callback cobre **apenas mudança de status**. Ele não
> é assinado (sem HMAC) — o tráfego apenas **sai** do helpdeskON para o sistema
> externo. Recomenda-se usar uma URL de callback não pública/adivinhável.

### Corpo do callback

```
POST <sua URL de callback>
Content-Type: application/json

{
  "event": "ticket.status_changed",
  "id": 4821,
  "client_ticket_number": 37,
  "external_ref": "PUNTACANA-2026-000123",
  "previous_status": "open",
  "status": "in_progress",
  "changed_at": "2026-09-25 14:20:11"
}
```

Campos:

| Campo                  | Descrição |
|------------------------|-----------|
| `event`                | Sempre `ticket.status_changed` nesta versão. |
| `id`                   | Id interno do chamado no helpdeskON. |
| `client_ticket_number` | Número sequencial do chamado por empresa. |
| `external_ref`         | Referência do chamado no sistema externo (a mesma enviada na criação). Sempre presente — o callback só é enviado para chamados que têm `external_ref`. |
| `previous_status`      | Status antes da mudança. |
| `status`               | Novo status (ver valores possíveis abaixo). |
| `changed_at`           | Data/hora da mudança (horário do servidor). |

### Valores possíveis de `status`

`open`, `in_progress`, `em_revisao_interna`, `waiting_client`, `em_homologacao`,
`aprovado_producao`, `completed`, `denied`, `archived`.

### O que o sistema externo deve fazer

- Responder **HTTP 2xx** ao receber o callback. Qualquer resposta fora da faixa
  2xx (ou timeout) faz o helpdeskON reenviar depois.
- Tratar o recebimento de forma **idempotente**: em um reenvio, o mesmo evento
  pode chegar mais de uma vez. Use `external_ref` + `status` para deduplicar.

### Exemplo de recebimento (lado do sistema externo)

Endpoint mínimo em PHP que recebe o callback e atualiza o status do chamado no
próprio sistema:

```php
<?php
// Ex.: rota que responde à URL de callback cadastrada no painel do helpdeskON.
$body = json_decode(file_get_contents('php://input'), true);

if (!is_array($body) || ($body['event'] ?? '') !== 'ticket.status_changed') {
    http_response_code(400);
    exit;
}

$externalRef = $body['external_ref'] ?? null; // seu identificador do chamado
$novoStatus  = $body['status'] ?? null;

if ($externalRef && $novoStatus) {
    // Atualize o status no seu sistema (idempotente: se já estava nesse
    // status, não faça nada). Exemplo:
    // UPDATE chamados SET status = :s WHERE ref = :ref
}

http_response_code(200); // sempre responda 2xx quando processar com sucesso
```

Exemplo do JSON recebido:

```json
{
  "event": "ticket.status_changed",
  "id": 4821,
  "client_ticket_number": 37,
  "external_ref": "PUNTACANA-2026-000123",
  "previous_status": "open",
  "status": "in_progress",
  "changed_at": "2026-09-25 14:20:11"
}
```

---

## Requisitos de instalação (lado do helpdeskON)

Para a API funcionar, as seguintes migrations precisam estar aplicadas no banco:

- `migrations/133_api_keys.sql` — tabela de API Keys.
- `migrations/134_tickets_external_ref.sql` — coluna e índice de idempotência.
- `migrations/135_api_callback.sql` — URL de callback por empresa (colunas em
  `api_keys`) e fila de entrega (`api_callback_queue`).

E, para o **callback de status** funcionar, é preciso agendar o processador da
fila (cron), rodando idealmente a cada minuto:

```
* * * * * curl -s https://SEU-DOMINIO/cron-api-callback.php
```

Sem esse cron, os callbacks ficam enfileirados em `api_callback_queue` mas não
são entregues.
