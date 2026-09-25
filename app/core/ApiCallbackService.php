<?php

/**
 * Serviço de CALLBACK de status da API v1 de demandas/chamados.
 *
 * Contexto: a API v1 (POST /api/v1/tickets) permite que um sistema externo
 * (ex.: Punta Cana) ABRA chamados no helpdeskON. Este serviço cobre o retorno
 * (mão dupla): quando o STATUS de um chamado muda, avisamos o sistema externo.
 *
 * Estratégia de entrega: ASSÍNCRONA (fila + cron). Este serviço apenas
 * ENFILEIRA o callback (INSERT em api_callback_queue). Quem realmente faz o
 * POST HTTP é o cron public/cron-api-callback.php, com retry/backoff — no mesmo
 * padrão do webhook_queue/cron-webhook.php já existente no projeto. Assim, a
 * operação interna (arrastar card, mudar status na tela) nunca trava por causa
 * da saúde do sistema externo, e um callback que falha é reenviado depois.
 *
 * Regras (alinhadas com o produto):
 *  - Só o evento de MUDANÇA DE STATUS gera callback nesta versão.
 *  - Callback é POR EMPRESA: a URL fica em api_keys (callback_url), e só dispara
 *    quando callback_enabled = 1 e a URL está preenchida.
 *  - Sem assinatura HMAC: o tráfego só SAI do LRV para o sistema externo.
 *
 * Não depende de $_SESSION nem de estado de controller: pode ser chamado tanto
 * por uma requisição de sessão (Kanban/tela de tickets) quanto por qualquer
 * outro fluxo interno que mude o status.
 */
class ApiCallbackService
{
    /** Nome do evento enviado no payload (único suportado nesta versão). */
    public const EVENT_STATUS_CHANGED = 'ticket.status_changed';

    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Enfileira um callback de mudança de status, se aplicável.
     *
     * É best-effort e defensivo: NUNCA lança exceção para o chamador (não pode
     * quebrar a mudança de status interna). Retorna o id da linha enfileirada,
     * ou null quando nada foi enfileirado (status igual, empresa sem callback
     * configurado, ticket não criado via API, etc.).
     *
     * @param int         $ticketId       id do ticket
     * @param string|null $previousStatus status antes da mudança
     * @param string      $newStatus      status depois da mudança
     */
    public function enqueueStatusChange(int $ticketId, ?string $previousStatus, string $newStatus): ?int
    {
        try {
            // 1) Só faz sentido se o status realmente mudou.
            if (!self::shouldNotify($previousStatus, $newStatus)) {
                return null;
            }

            // 2) Carrega o ticket (traz client_id, external_ref, client_ticket_number).
            $ticket = $this->db->fetch(
                "SELECT id, client_id, external_ref, client_ticket_number, status
                   FROM tickets WHERE id = ? LIMIT 1",
                [$ticketId]
            );
            if (!$ticket) {
                return null;
            }

            // 2.1) Só notificamos demandas CRIADAS VIA API por aquele sistema
            //      externo — isto é, que possuem external_ref. Assim o integrador
            //      recebe de volta apenas o que ele mesmo enviou, sem ruído das
            //      demandas internas do LRV (criadas pela equipe/tela), que não
            //      têm external_ref e não fazem sentido para ele.
            $externalRef = trim((string)($ticket['external_ref'] ?? ''));
            if ($externalRef === '') {
                return null;
            }

            // 3) Descobre a empresa do ticket (empresa = users.company_id do client).
            $clientUser = $this->db->fetch(
                "SELECT company_id FROM users WHERE id = ? LIMIT 1",
                [(int)$ticket['client_id']]
            );
            $companyId = $clientUser['company_id'] ?? null;
            if (!$companyId) {
                return null;
            }

            // 4) Busca a chave/callback da empresa. Só enfileira se o callback
            //    estiver habilitado e com URL válida.
            $apiKey = $this->db->fetch(
                "SELECT callback_url, callback_enabled
                   FROM api_keys WHERE company_id = ? LIMIT 1",
                [(int)$companyId]
            );
            if (!$apiKey || (int)($apiKey['callback_enabled'] ?? 0) !== 1) {
                return null;
            }
            $callbackUrl = trim((string)($apiKey['callback_url'] ?? ''));
            if (!self::isValidCallbackUrl($callbackUrl)) {
                return null;
            }

            // 5) Monta o payload e enfileira.
            $payload = self::buildStatusPayload($ticket, $previousStatus, $newStatus);

            return (int)$this->db->insert('api_callback_queue', [
                'company_id'   => (int)$companyId,
                'ticket_id'    => (int)$ticketId,
                'callback_url' => $callbackUrl,
                'payload'      => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'status'       => 'pending',
                'attempts'     => 0,
            ]);
        } catch (\Throwable $e) {
            // Best-effort: um problema aqui jamais pode quebrar a mudança de status.
            if (class_exists('Logger')) {
                Logger::warning('Falha ao enfileirar callback de status da API', [
                    'ticket_id' => $ticketId,
                    'error'     => $e->getMessage(),
                ]);
            }
            return null;
        }
    }

    /**
     * Decide se uma transição de status deve gerar callback. Método puro
     * (sem banco/rede) para permitir teste unitário.
     *
     * Regra: só notifica quando o novo status é não-vazio e diferente do
     * anterior. A primeira definição de status na criação (previous = null)
     * NÃO gera callback aqui — a criação é confirmada pela própria resposta
     * 201 do POST /api/v1/tickets.
     */
    public static function shouldNotify(?string $previousStatus, string $newStatus): bool
    {
        if ($newStatus === '') {
            return false;
        }
        if ($previousStatus === null) {
            return false;
        }
        return $previousStatus !== $newStatus;
    }

    /**
     * Valida minimamente a URL de callback. Método puro para teste unitário.
     * Aceita apenas http(s) com host — evita enfileirar lixo que o cron não
     * conseguiria entregar.
     */
    public static function isValidCallbackUrl(string $url): bool
    {
        $url = trim($url);
        if ($url === '' || mb_strlen($url) > 500) {
            return false;
        }
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https'], true);
    }

    /**
     * Monta o corpo (payload) do callback de mudança de status. Método puro
     * (recebe o array do ticket já carregado) para permitir teste unitário.
     *
     * O sistema externo identifica o chamado pelo external_ref (a referência
     * que ELE enviou na criação). Também enviamos os identificadores do lado
     * do helpdeskON (id interno e client_ticket_number) para depuração.
     *
     * @param array       $ticket          linha do ticket (id, external_ref, client_ticket_number)
     * @param string|null $previousStatus  status anterior
     * @param string      $newStatus       status novo
     */
    public static function buildStatusPayload(array $ticket, ?string $previousStatus, string $newStatus): array
    {
        return [
            'event'                => self::EVENT_STATUS_CHANGED,
            'id'                   => isset($ticket['id']) ? (int)$ticket['id'] : null,
            'client_ticket_number' => isset($ticket['client_ticket_number']) && $ticket['client_ticket_number'] !== null
                ? (int)$ticket['client_ticket_number']
                : null,
            'external_ref'         => $ticket['external_ref'] ?? null,
            'previous_status'      => $previousStatus,
            'status'               => $newStatus,
            'changed_at'           => date('Y-m-d H:i:s'),
        ];
    }
}
