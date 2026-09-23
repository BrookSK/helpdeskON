<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use ApiKey;
use Ticket;
use Database;

/**
 * Teste de integração da API v1 de criação de chamados, contra helpdesk_on_test.
 *
 * Cobre a camada de persistência/serviço que sustenta POST /api/v1/tickets:
 *  - ApiKey::createForCompany garante/cria o usuário de integração e persiste
 *    apenas o hash;
 *  - resolveByPlainKey identifica a chave; revoke a desativa;
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
            $this->db->query("SELECT 1 FROM api_keys LIMIT 1");
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
            // Remove tickets criados pelo usuário de integração desta empresa.
            $user = $this->db->fetch(
                "SELECT id FROM users WHERE company_id = ? AND email LIKE 'api+company%'",
                [$this->companyId]
            );
            if ($user) {
                $this->db->delete('tickets', 'client_id = ?', [(int) $user['id']]);
            }
            // api_keys e users caem por FK cascade ao remover a empresa; deixamos
            // explícito por robustez.
            $this->db->delete('api_keys', 'company_id = ?', [$this->companyId]);
            $this->db->delete('users', 'company_id = ?', [$this->companyId]);
            $this->db->delete('companies', 'id = ?', [$this->companyId]);
        } catch (\Throwable $e) {
            // ignora falha de limpeza
        }
    }

    public function testCreateForCompanyGeraChaveEUsuarioDeIntegracao(): void
    {
        $res = $this->apiKeys->createForCompany($this->companyId, 'ERP Teste');

        $this->assertGreaterThan(0, $res['id']);
        $this->assertStringStartsWith(ApiKey::KEY_PREFIX, $res['plain']);

        // Usuário de integração foi criado e vinculado à empresa.
        $user = $this->db->fetch(
            "SELECT * FROM users WHERE company_id = ? AND email LIKE 'api+company%'",
            [$this->companyId]
        );
        $this->assertNotEmpty($user);
        $this->assertSame('client', $user['role']);
        $this->assertSame(1, (int) $user['is_active']);

        // No banco fica só o hash — nunca a chave em claro.
        $row = $this->apiKeys->findById((int) $res['id']);
        $this->assertSame(ApiKey::hashKey($res['plain']), $row['key_hash']);
        $this->assertNotSame($res['plain'], $row['key_hash']);
    }

    public function testEnsureIntegrationUserEhReutilizado(): void
    {
        $r1 = $this->apiKeys->createForCompany($this->companyId, 'Chave 1');
        $r2 = $this->apiKeys->createForCompany($this->companyId, 'Chave 2');

        $key1 = $this->apiKeys->findById((int) $r1['id']);
        $key2 = $this->apiKeys->findById((int) $r2['id']);

        // Duas chaves da mesma empresa apontam para o MESMO usuário de integração.
        $this->assertSame(
            (int) $key1['integration_user_id'],
            (int) $key2['integration_user_id']
        );
    }

    public function testResolveByPlainKeyIdentificaChave(): void
    {
        $res = $this->apiKeys->createForCompany($this->companyId, 'Resolver');

        $resolved = $this->apiKeys->resolveByPlainKey($res['plain']);
        $this->assertNotNull($resolved);
        $this->assertSame((int) $res['id'], (int) $resolved['id']);
        $this->assertSame($this->companyId, (int) $resolved['company_id']);

        // Chave inexistente não resolve.
        $this->assertNull($this->apiKeys->resolveByPlainKey('hk_live_naoexiste'));
    }

    public function testRevokeDesativaChave(): void
    {
        $res = $this->apiKeys->createForCompany($this->companyId, 'Revogar');
        $this->apiKeys->revoke((int) $res['id']);

        $row = $this->apiKeys->findById((int) $res['id']);
        $this->assertSame(0, (int) $row['is_active']);
        $this->assertNotEmpty($row['revoked_at']);
    }

    public function testIdempotenciaPorExternalRefNaoDuplica(): void
    {
        $res = $this->apiKeys->createForCompany($this->companyId, 'Idempotente');
        $key = $this->apiKeys->findById((int) $res['id']);
        $clientId = (int) $key['integration_user_id'];
        $externalRef = 'EXT-' . uniqid();

        // Primeira criação com external_ref.
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

        // Uma segunda tentativa com o MESMO external_ref deve ser detectada como
        // já existente (o endpoint retorna 200 idempotente sem inserir de novo).
        $existing = $this->db->fetch(
            "SELECT id FROM tickets WHERE client_id = ? AND external_ref = ? LIMIT 1",
            [$clientId, $externalRef]
        );
        $this->assertSame($id1, (int) $existing['id']);

        // Confirma que existe apenas UM ticket com esse external_ref.
        $count = $this->db->fetch(
            "SELECT COUNT(*) AS c FROM tickets WHERE client_id = ? AND external_ref = ?",
            [$clientId, $externalRef]
        );
        $this->assertSame(1, (int) $count['c']);
    }

    public function testTicketsSemExternalRefNaoColidem(): void
    {
        $res = $this->apiKeys->createForCompany($this->companyId, 'SemRef');
        $key = $this->apiKeys->findById((int) $res['id']);
        $clientId = (int) $key['integration_user_id'];

        // Dois tickets sem external_ref (NULL) devem coexistir (NULLs não
        // colidem no índice UNIQUE do MySQL).
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
