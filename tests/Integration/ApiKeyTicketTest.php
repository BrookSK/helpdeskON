<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use ApiKey;
use Ticket;
use Database;

/**
 * Teste de integração da API v1 de criação de chamados, contra helpdesk_on_test.
 *
 * Cobre a camada de persistência/serviço que sustenta POST /api/v1/tickets no
 * modelo simplificado (uma chave por empresa, chave gravada inteira):
 *  - getOrCreateForCompany cria a chave + o usuário de integração e reaproveita
 *    a chave existente numa segunda chamada (uma por empresa);
 *  - resolveByPlainKey identifica a chave pela string;
 *  - idempotência por (client_id, external_ref) evita duplicar o ticket.
 *
 * Não exercita a camada HTTP (headers/echo/exit do controller). A validação de
 * payload é coberta por unit (ApiTicketPayloadTest).
 *
 * Estratégia de dados: cria uma empresa no setUp; no tearDown remove a empresa
 * (FK ON DELETE CASCADE remove o usuário de integração e a api_key; os tickets
 * do usuário caem por cascade de tickets.client_id -> users.id).
 */
final class ApiKeyTicketTest extends TestCase
{
    private Database $db;
    private Ticket $tickets;
    private ApiKey $apiKeys;
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

        // Skip amigável se as migrations 133/134 não tiverem sido aplicadas.
        try {
            $this->db->query("SELECT api_key FROM api_keys LIMIT 1");
            $this->db->query("SELECT external_ref FROM tickets LIMIT 1");
        } catch (\Throwable $e) {
            $this->markTestSkipped('Migrations 133/134 não aplicadas no banco de teste.');
        }

        $this->companyId = (int) $this->db->insert('companies', [
            'name' => 'Empresa API ' . uniqid(),
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
                $this->db->delete('tickets', 'client_id = ?', [(int) $user['id']]);
            }
            $this->db->delete('api_keys', 'company_id = ?', [$this->companyId]);
            $this->db->delete('users', 'company_id = ?', [$this->companyId]);
            $this->db->delete('companies', 'id = ?', [$this->companyId]);
        } catch (\Throwable $e) {
            // ignora falha de limpeza
        }
    }

    public function testGetOrCreateGeraChaveEUsuarioDeIntegracao(): void
    {
        $key = $this->apiKeys->getOrCreateForCompany($this->companyId);

        $this->assertNotEmpty($key['api_key']);
        $this->assertStringStartsWith(ApiKey::KEY_PREFIX, $key['api_key']);
        $this->assertSame($this->companyId, (int) $key['company_id']);

        // Usuário de integração foi criado e vinculado à empresa.
        $user = $this->db->fetch(
            "SELECT * FROM users WHERE company_id = ? AND email LIKE 'api+company%'",
            [$this->companyId]
        );
        $this->assertNotEmpty($user);
        $this->assertSame('client', $user['role']);
        $this->assertSame(1, (int) $user['is_active']);
        $this->assertSame((int) $user['id'], (int) $key['integration_user_id']);
    }

    public function testGetOrCreateReaproveitaChaveExistente(): void
    {
        $k1 = $this->apiKeys->getOrCreateForCompany($this->companyId);
        $k2 = $this->apiKeys->getOrCreateForCompany($this->companyId);

        // Uma chave por empresa: a segunda chamada devolve a mesma.
        $this->assertSame((int) $k1['id'], (int) $k2['id']);
        $this->assertSame($k1['api_key'], $k2['api_key']);
    }

    public function testResolveByPlainKeyIdentificaChave(): void
    {
        $key = $this->apiKeys->getOrCreateForCompany($this->companyId);

        $resolved = $this->apiKeys->resolveByPlainKey($key['api_key']);
        $this->assertNotNull($resolved);
        $this->assertSame((int) $key['id'], (int) $resolved['id']);
        $this->assertSame($this->companyId, (int) $resolved['company_id']);

        // Chave inexistente não resolve.
        $this->assertNull($this->apiKeys->resolveByPlainKey('hk_live_naoexiste'));
    }

    public function testIdempotenciaPorExternalRefNaoDuplica(): void
    {
        $key = $this->apiKeys->getOrCreateForCompany($this->companyId);
        $clientId = (int) $key['integration_user_id'];
        $externalRef = 'EXT-' . uniqid();

        $number = $this->proximoNumero($clientId);
        $id1 = (int) $this->tickets->create([
            'client_id' => $clientId,
            'title' => 'Chamado via API',
            'description' => 'Descrição',
            'priority' => 'medium',
            'status' => 'open',
            'client_ticket_number' => $number,
            'external_ref' => $externalRef,
        ]);
        $this->assertGreaterThan(0, $id1);

        // Segunda tentativa com o MESMO external_ref: o endpoint detecta o
        // existente (200 idempotente) sem inserir de novo.
        $existing = $this->db->fetch(
            "SELECT id FROM tickets WHERE client_id = ? AND external_ref = ? LIMIT 1",
            [$clientId, $externalRef]
        );
        $this->assertSame($id1, (int) $existing['id']);

        $count = $this->db->fetch(
            "SELECT COUNT(*) AS c FROM tickets WHERE client_id = ? AND external_ref = ?",
            [$clientId, $externalRef]
        );
        $this->assertSame(1, (int) $count['c']);
    }

    public function testTicketsSemExternalRefNaoColidem(): void
    {
        $key = $this->apiKeys->getOrCreateForCompany($this->companyId);
        $clientId = (int) $key['integration_user_id'];

        $id1 = (int) $this->tickets->create([
            'client_id' => $clientId, 'title' => 'A', 'description' => 'a',
            'priority' => 'medium', 'status' => 'open',
            'client_ticket_number' => $this->proximoNumero($clientId),
        ]);
        $id2 = (int) $this->tickets->create([
            'client_id' => $clientId, 'title' => 'B', 'description' => 'b',
            'priority' => 'medium', 'status' => 'open',
            'client_ticket_number' => $this->proximoNumero($clientId),
        ]);

        $this->assertGreaterThan(0, $id1);
        $this->assertGreaterThan(0, $id2);
        $this->assertNotSame($id1, $id2);
    }

    private function proximoNumero(int $clientId): int
    {
        $row = $this->db->fetch(
            "SELECT MAX(client_ticket_number) AS last_num FROM tickets WHERE client_id = ?",
            [$clientId]
        );
        return ($row['last_num'] ?? 0) + 1;
    }
}
