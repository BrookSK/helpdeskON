<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Database;

/**
 * Testes de integração do registro de ligações (nvoip_calls) contra o banco
 * helpdesk_on_test. Valida que o schema suporta o campo `direction` — que o
 * fix passou a gravar em CrmController::callLead — e que o response_json
 * preserva a nota mesclada (fix do saveCallNote que antes sobrescrevia).
 */
final class NvoipCallTest extends TestCase
{
    private Database $db;
    private int $userId;
    private int $instanceId;
    private int $contactId;
    /** @var int[] */
    private array $callIds = [];

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste (helpdesk_on_test) não configurado.');
        }
        $this->db = Database::getInstance();

        $u = uniqid();
        $this->userId = (int) $this->db->insert('users', [
            'name' => "Caller {$u}", 'email' => "caller_{$u}@example.test",
            'password' => password_hash('x', PASSWORD_BCRYPT), 'role' => 'comercial',
        ]);
        $this->instanceId = (int) $this->db->insert('whatsapp_instances', [
            'instance_name' => "callinst_{$u}", 'api_url' => 'http://localhost', 'api_key' => 'k',
        ]);
        $this->contactId = (int) $this->db->insert('whatsapp_contacts', [
            'instance_id' => $this->instanceId,
            'remote_jid' => "5511{$u}@s.whatsapp.net",
            'phone' => '5511988887777',
            'contact_name' => 'Lead Ligação',
            'is_group' => 0,
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->callIds as $id) {
            try { $this->db->delete('nvoip_calls', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
        try { $this->db->delete('whatsapp_contacts', 'id = ?', [$this->contactId]); } catch (\Throwable $e) {}
        try { $this->db->delete('whatsapp_instances', 'id = ?', [$this->instanceId]); } catch (\Throwable $e) {}
        try { $this->db->delete('users', 'id = ?', [$this->userId]); } catch (\Throwable $e) {}
    }

    public function testRegistraChamadaComDirectionOutbound(): void
    {
        $id = (int) $this->db->insert('nvoip_calls', [
            'contact_id' => $this->contactId,
            'user_id' => $this->userId,
            'direction' => 'outbound',
            'call_id' => 'CALL_' . uniqid(),
            'caller' => '551133334444',
            'called' => '5511988887777',
            'status' => 'created',
        ]);
        $this->callIds[] = $id;

        $rec = $this->db->fetch("SELECT * FROM nvoip_calls WHERE id = ?", [$id]);
        $this->assertSame('outbound', $rec['direction']);
        $this->assertSame((int) $this->contactId, (int) $rec['contact_id']);
    }

    public function testNotaMescladaPreservaResponseJson(): void
    {
        // Simula o comportamento do saveCallNote (fix): mescla 'note' no
        // response_json existente sem apagar o retorno original da Nvoip.
        $id = (int) $this->db->insert('nvoip_calls', [
            'contact_id' => $this->contactId,
            'user_id' => $this->userId,
            'direction' => 'outbound',
            'status' => 'answered',
            'response_json' => json_encode(['callId' => 'X1', 'situation' => 'ANSWERED']),
        ]);
        $this->callIds[] = $id;

        // Mescla a nota
        $current = $this->db->fetch("SELECT response_json FROM nvoip_calls WHERE id = ?", [$id]);
        $json = json_decode($current['response_json'], true) ?: [];
        $json['note'] = 'Cliente pediu retorno amanhã';
        $this->db->query("UPDATE nvoip_calls SET response_json = ? WHERE id = ?", [
            json_encode($json, JSON_UNESCAPED_UNICODE), $id,
        ]);

        $rec = $this->db->fetch("SELECT response_json FROM nvoip_calls WHERE id = ?", [$id]);
        $decoded = json_decode($rec['response_json'], true);
        // A nota foi adicionada E o retorno original preservado.
        $this->assertSame('Cliente pediu retorno amanhã', $decoded['note']);
        $this->assertSame('X1', $decoded['callId']);
        $this->assertSame('ANSWERED', $decoded['situation']);
    }
}
