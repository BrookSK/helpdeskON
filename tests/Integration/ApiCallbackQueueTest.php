<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use ApiKey;
use ApiCallbackService;
use Ticket;
use Database;

/**
 * Teste de integração do enfileiramento de CALLBACK de status (contra
 * helpdesk_on_test).
 *
 * Cobre ApiCallbackService::enqueueStatusChange e a integração com o choke point
 * Ticket::updateStatus:
 *  - só enfileira quando a empresa tem callback habilitado + URL válida;
 *  - não enfileira quando o status não muda;
 *  - não enfileira quando o callback está desabilitado;
 *  - mudar o status via Ticket::updateStatus insere na fila (api_callback_queue).
 *
 * Estratégia de dados: cria empresa + chave (via getOrCreateForCompany, que cria
 * o usuário de integração). No tearDown, remove tickets do usuário, a fila, a
 * chave, o usuário e a empresa.
 */
final class ApiCallbackQueueTest extends TestCase
{
    private Database $db;
    private Ticket $tickets;
    private ApiKey $apiKeys;
    private ApiCallbackService $callbacks;
    private int $companyId;

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste (helpdesk_on_test) não configurado.');
        }

        $this->db = Database::getInstance();
        $this->tickets = new Ticket();
        $this->apiKeys = new ApiKey();
        $this->callbacks = new ApiCallbackService();

        // Skip amigável se a migration 135 (callback) não tiver sido aplicada.
        try {
            $this->db->query("SELECT callback_url, callback_enabled FROM api_keys LIMIT 1");
            $this->db->query("SELECT id FROM api_callback_queue LIMIT 1");
        } catch (\Throwable $e) {
            $this->markTestSkipped('Migration 135 (api_callback) não aplicada no banco de teste.');
        }

        $this->companyId = (int) $this->db->insert('companies', [
            'name' => 'Empresa Callback ' . uniqid(),
        ]);
    }

    protected function tearDown(): void
    {
        try {
            $user = $this->db->fetch(
                "SELECT id FROM users WHERE company_id = ? AND email LIKE 'api+company%'",
                [$this->companyId]
            );
            if ($user) {
                $ids = $this->db->fetchAll("SELECT id FROM tickets WHERE client_id = ?", [(int)$user['id']]);
                foreach ($ids as $t) {
                    $this->db->delete('api_callback_queue', 'ticket_id = ?', [(int)$t['id']]);
                }
                $this->db->delete('tickets', 'client_id = ?', [(int) $user['id']]);
            }
            $this->db->delete('api_callback_queue', 'company_id = ?', [$this->companyId]);
            $this->db->delete('api_keys', 'company_id = ?', [$this->companyId]);
            $this->db->delete('users', 'company_id = ?', [$this->companyId]);
            $this->db->delete('companies', 'id = ?', [$this->companyId]);
        } catch (\Throwable $e) {
            // ignora falha de limpeza
        }
    }

    /** Cria um ticket para o usuário de integração da empresa e retorna o id. */
    private function criarTicket(int $clientId, ?string $externalRef = null): int
    {
        $row = $this->db->fetch(
            "SELECT MAX(client_ticket_number) AS n FROM tickets WHERE client_id = ?",
            [$clientId]
        );
        $data = [
            'client_id' => $clientId,
            'title' => 'Chamado callback',
            'description' => 'desc',
            'priority' => 'medium',
            'status' => 'open',
            'client_ticket_number' => ($row['n'] ?? 0) + 1,
        ];
        if ($externalRef !== null) {
            $data['external_ref'] = $externalRef;
        }
        return (int) $this->tickets->create($data);
    }

    private function contarFila(int $ticketId): int
    {
        $r = $this->db->fetch("SELECT COUNT(*) AS c FROM api_callback_queue WHERE ticket_id = ?", [$ticketId]);
        return (int) $r['c'];
    }

    public function testNaoEnfileiraSemCallbackConfigurado(): void
    {
        $key = $this->apiKeys->getOrCreateForCompany($this->companyId);
        $clientId = (int) $key['integration_user_id'];
        $ticketId = $this->criarTicket($clientId, 'EXT-' . uniqid());

        // Empresa sem callback configurado: mudar status não enfileira nada.
        $this->tickets->updateStatus($ticketId, 'in_progress');
        $this->assertSame(0, $this->contarFila($ticketId));
    }

    public function testNaoEnfileiraQuandoCallbackDesabilitado(): void
    {
        $key = $this->apiKeys->getOrCreateForCompany($this->companyId);
        $clientId = (int) $key['integration_user_id'];
        // URL válida, mas desabilitado.
        $this->apiKeys->updateCallback($this->companyId, 'https://exemplo.com/callback', false);

        $ticketId = $this->criarTicket($clientId, 'EXT-' . uniqid());
        $this->tickets->updateStatus($ticketId, 'in_progress');
        $this->assertSame(0, $this->contarFila($ticketId));
    }

    public function testEnfileiraQuandoStatusMudaEComCallbackAtivo(): void
    {
        $key = $this->apiKeys->getOrCreateForCompany($this->companyId);
        $clientId = (int) $key['integration_user_id'];
        $this->apiKeys->updateCallback($this->companyId, 'https://exemplo.com/callback', true);

        $externalRef = 'EXT-' . uniqid();
        $ticketId = $this->criarTicket($clientId, $externalRef);

        $this->tickets->updateStatus($ticketId, 'in_progress');

        $this->assertSame(1, $this->contarFila($ticketId));

        $row = $this->db->fetch("SELECT * FROM api_callback_queue WHERE ticket_id = ? LIMIT 1", [$ticketId]);
        $this->assertSame('pending', $row['status']);
        $this->assertSame($this->companyId, (int) $row['company_id']);
        $this->assertSame('https://exemplo.com/callback', $row['callback_url']);

        $payload = json_decode($row['payload'], true);
        $this->assertSame(ApiCallbackService::EVENT_STATUS_CHANGED, $payload['event']);
        $this->assertSame($externalRef, $payload['external_ref']);
        $this->assertSame('open', $payload['previous_status']);
        $this->assertSame('in_progress', $payload['status']);
    }

    public function testNaoEnfileiraQuandoStatusNaoMuda(): void
    {
        $key = $this->apiKeys->getOrCreateForCompany($this->companyId);
        $clientId = (int) $key['integration_user_id'];
        $this->apiKeys->updateCallback($this->companyId, 'https://exemplo.com/callback', true);

        $ticketId = $this->criarTicket($clientId, 'EXT-' . uniqid());
        // Status atual já é 'open'; "mudar" para 'open' não deve enfileirar.
        $this->tickets->updateStatus($ticketId, 'open');
        $this->assertSame(0, $this->contarFila($ticketId));
    }

    public function testNaoEnfileiraTicketSemExternalRef(): void
    {
        // Callback ativo, status muda — MAS o ticket não foi criado via API
        // (sem external_ref, como uma demanda interna do LRV). Não deve enfileirar:
        // o integrador só recebe de volta o que ele mesmo enviou.
        $key = $this->apiKeys->getOrCreateForCompany($this->companyId);
        $clientId = (int) $key['integration_user_id'];
        $this->apiKeys->updateCallback($this->companyId, 'https://exemplo.com/callback', true);

        $ticketId = $this->criarTicket($clientId, null); // sem external_ref
        $this->tickets->updateStatus($ticketId, 'in_progress');
        $this->assertSame(0, $this->contarFila($ticketId));
    }

    public function testChamadasSucessivasEnfileiramCadaMudanca(): void
    {
        $key = $this->apiKeys->getOrCreateForCompany($this->companyId);
        $clientId = (int) $key['integration_user_id'];
        $this->apiKeys->updateCallback($this->companyId, 'https://exemplo.com/callback', true);

        $ticketId = $this->criarTicket($clientId, 'EXT-' . uniqid());

        $this->tickets->updateStatus($ticketId, 'in_progress');   // open -> in_progress
        $this->tickets->updateStatus($ticketId, 'em_homologacao'); // in_progress -> em_homologacao
        $this->tickets->updateStatus($ticketId, 'em_homologacao'); // sem mudança: não enfileira

        $this->assertSame(2, $this->contarFila($ticketId));
    }
}
