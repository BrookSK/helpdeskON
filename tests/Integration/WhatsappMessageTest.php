<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use WhatsappMessage;
use Database;

/**
 * Testes de integração das mensagens de WhatsApp (WhatsappMessage) contra o
 * banco helpdesk_on_test. Cobre a deduplicação por message_id (inclusive entre
 * instâncias diferentes — o bug das mensagens enviadas de fora), o marcador
 * from_me, markAsRead e as métricas sent vs sent_system (coluna sent_via).
 */
final class WhatsappMessageTest extends TestCase
{
    private Database $db;
    private WhatsappMessage $model;
    private int $instanceId;
    private int $instanceId2;
    private int $userId;
    private int $contactId;

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste (helpdesk_on_test) não configurado.');
        }
        $this->db = Database::getInstance();
        $this->model = new WhatsappMessage();

        $u = uniqid();
        $this->userId = (int) $this->db->insert('users', [
            'name' => "Agente {$u}", 'email' => "ag_{$u}@example.test",
            'password' => password_hash('x', PASSWORD_BCRYPT), 'role' => 'whatsapp_agent',
        ]);
        $this->instanceId = (int) $this->db->insert('whatsapp_instances', [
            'instance_name' => "inst_{$u}", 'api_url' => 'http://localhost', 'api_key' => 'k',
        ]);
        $this->instanceId2 = (int) $this->db->insert('whatsapp_instances', [
            'instance_name' => "inst2_{$u}", 'api_url' => 'http://localhost', 'api_key' => 'k',
        ]);
        $this->contactId = (int) $this->db->insert('whatsapp_contacts', [
            'instance_id' => $this->instanceId,
            'remote_jid' => "5511999{$u}@s.whatsapp.net",
            'phone' => '5511999888777',
            'contact_name' => 'Cliente WA',
            'assigned_to' => $this->userId,
            'is_group' => 0,
        ]);
    }

    protected function tearDown(): void
    {
        try { $this->db->delete('whatsapp_messages', 'contact_id = ?', [$this->contactId]); } catch (\Throwable $e) {}
        try { $this->db->delete('whatsapp_contacts', 'id = ?', [$this->contactId]); } catch (\Throwable $e) {}
        foreach ([$this->instanceId, $this->instanceId2] as $id) {
            try { $this->db->delete('whatsapp_instances', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
        try { $this->db->delete('users', 'id = ?', [$this->userId]); } catch (\Throwable $e) {}
    }

    private function msgBase(array $overrides = []): array
    {
        return array_merge([
            'instance_id' => $this->instanceId,
            'contact_id' => $this->contactId,
            'remote_jid' => "5511999@s.whatsapp.net",
            'message_id' => 'WA_' . uniqid(),
            'from_me' => 0,
            'message_type' => 'text',
            'message_text' => 'oi',
            'timestamp' => date('Y-m-d H:i:s'),
        ], $overrides);
    }

    // ================= Dedup =================

    public function testCreateDeduplicaMesmaInstanciaEMessageId(): void
    {
        $data = $this->msgBase(['message_id' => 'DUP1']);
        $id1 = $this->model->create($data);
        $id2 = $this->model->create($data); // mesma instância + message_id
        $this->assertSame((int) $id1, (int) $id2);

        $count = (int) $this->db->fetch(
            "SELECT COUNT(*) c FROM whatsapp_messages WHERE message_id = ? AND contact_id = ?",
            ['DUP1', $this->contactId]
        )['c'];
        $this->assertSame(1, $count);
    }

    public function testCreateDeduplicaEntreInstanciasDiferentes(): void
    {
        // Bug real: mensagem enviada do celular (webhook, instância X) e o eco do
        // painel (instância Y ou nula) tinham o MESMO message_id da Evolution.
        // Devem deduplicar pelo par (message_id, contact_id).
        $id1 = $this->model->create($this->msgBase([
            'instance_id' => $this->instanceId, 'message_id' => 'EVO123', 'from_me' => 1,
        ]));
        $id2 = $this->model->create($this->msgBase([
            'instance_id' => $this->instanceId2, 'message_id' => 'EVO123', 'from_me' => 1,
        ]));
        $this->assertSame((int) $id1, (int) $id2);

        $count = (int) $this->db->fetch(
            "SELECT COUNT(*) c FROM whatsapp_messages WHERE message_id = ? AND contact_id = ?",
            ['EVO123', $this->contactId]
        )['c'];
        $this->assertSame(1, $count);
    }

    public function testCreateNaoDeduplicaIdsTemporariosLocais(): void
    {
        // IDs temporários (sending_/sent_) são únicos por envio: não deduplicam
        // entre instâncias (senão sumiriam mensagens legítimas distintas).
        $id1 = $this->model->create($this->msgBase([
            'instance_id' => $this->instanceId, 'message_id' => 'sending_abc', 'from_me' => 1,
        ]));
        $id2 = $this->model->create($this->msgBase([
            'instance_id' => $this->instanceId2, 'message_id' => 'sending_abc', 'from_me' => 1,
        ]));
        $this->assertNotSame((int) $id1, (int) $id2);
    }

    // ================= from_me =================

    public function testFromMeEhPersistido(): void
    {
        $id = $this->model->create($this->msgBase(['from_me' => 1, 'sent_via' => 'external']));
        $row = $this->model->findById($id);
        $this->assertSame(1, (int) $row['from_me']);
    }

    // ================= markAsRead =================

    public function testMarkAsReadSomenteRecebidas(): void
    {
        $recebida = $this->model->create($this->msgBase(['from_me' => 0, 'message_id' => 'R1', 'is_read' => 0]));
        $enviada = $this->model->create($this->msgBase(['from_me' => 1, 'message_id' => 'S1', 'is_read' => 0]));

        $this->model->markAsRead($this->contactId);

        $this->assertSame(1, (int) $this->model->findById($recebida)['is_read']);
        // A enviada (from_me=1) não é alterada
        $this->assertSame(0, (int) $this->model->findById($enviada)['is_read']);
    }

    // ================= Métricas sent vs sent_system =================

    public function testMessageStatsSeparaSentDeSentSystem(): void
    {
        $hasSentVia = (bool) $this->db->fetch(
            "SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'whatsapp_messages' AND COLUMN_NAME = 'sent_via'"
        );

        // 2 enviadas pelo sistema, 1 enviada de fora (celular), 1 recebida.
        $this->model->create($this->msgBase(['from_me' => 1, 'message_id' => 'SYS1', 'sent_via' => 'system']));
        $this->model->create($this->msgBase(['from_me' => 1, 'message_id' => 'SYS2', 'sent_via' => 'system']));
        $this->model->create($this->msgBase(['from_me' => 1, 'message_id' => 'EXT1', 'sent_via' => 'external']));
        $this->model->create($this->msgBase(['from_me' => 0, 'message_id' => 'RCV1']));

        $stats = $this->model->getMessageStatsByUser(null, null, $this->userId);
        $this->assertArrayHasKey($this->userId, $stats);
        $s = $stats[$this->userId];

        // sent = todas as saídas (3), received = 1
        $this->assertSame(3, $s['sent']);
        $this->assertSame(1, $s['received']);

        if ($hasSentVia) {
            // sent_system conta só as 2 pelo painel
            $this->assertSame(2, $s['sent_system']);
        } else {
            // Sem a coluna, sent_system iguala sent (compatibilidade)
            $this->assertSame(3, $s['sent_system']);
        }
    }
}
