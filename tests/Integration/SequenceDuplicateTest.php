<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Database;
use EmailSequence;

/**
 * Integração (helpdesk_on_test): "Duplicar" na lista de Sequências copia o
 * fluxo e as configurações, sem levar os leads, e não mexe na original.
 */
final class SequenceDuplicateTest extends TestCase
{
    private Database $db;
    private string $u;
    private int $userId;
    private int $contactId;
    private int $seqId;
    private array $graph;

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste não configurado.');
        }
        $this->db = Database::getInstance();
        $this->u = uniqid();
        $this->userId = (int) $this->db->insert('users', [
            'name' => "Dup {$this->u}", 'email' => "dup_{$this->u}@example.test",
            'password' => password_hash('x', PASSWORD_BCRYPT), 'role' => 'comercial',
        ]);
        $this->contactId = (int) $this->db->insert('whatsapp_contacts', [
            'remote_jid' => "dup{$this->u}@s.whatsapp.net", 'contact_name' => "Lead Dup {$this->u}", 'lead_email' => "dup_{$this->u}@lead.test",
        ]);
        $this->graph = ['start' => 'n1', 'nodes' => [
            ['id' => 'n1', 'type' => 'send', 'x' => 60, 'y' => 60, 'data' => ['subject' => 'Olá {{primeiro_nome}}', 'body' => '<p><strong>Oi</strong></p>'], 'next' => 'n2'],
            ['id' => 'n2', 'type' => 'wait', 'x' => 60, 'y' => 200, 'data' => ['amount' => 2, 'unit' => 'days'], 'next' => 'n3'],
            ['id' => 'n3', 'type' => 'condition', 'x' => 60, 'y' => 340, 'data' => ['kind' => 'replied']],
        ]];
        $this->seqId = (int) (new EmailSequence())->create([
            'name' => "Seq Dup {$this->u}", 'description' => 'Fluxo de clínicas',
            'graph' => json_encode($this->graph, JSON_UNESCAPED_UNICODE), 'is_active' => 1,
            'daily_limit' => 37, 'window_start' => '09:15:00', 'window_end' => '17:45:00', 'send_weekends' => 1,
            'created_by' => $this->userId,
        ]);
        $this->db->insert('sequence_participants', [
            'sequence_id' => $this->seqId, 'contact_id' => $this->contactId, 'status' => 'active', 'added_by' => $this->userId,
        ]);
    }

    protected function tearDown(): void
    {
        $ids = array_map(fn($r) => (int) $r['id'], $this->db->fetchAll("SELECT id FROM email_sequences WHERE name LIKE ?", ["%{$this->u}%"]));
        foreach ($ids as $id) {
            try { $this->db->delete('sequence_participants', 'sequence_id = ?', [$id]); } catch (\Throwable $e) {}
            $this->db->delete('email_sequences', 'id = ?', [$id]);
        }
        try { $this->db->delete('whatsapp_contacts', 'id = ?', [$this->contactId]); } catch (\Throwable $e) {}
        try { $this->db->delete('users', 'id = ?', [$this->userId]); } catch (\Throwable $e) {}
    }

    public function testCopiaLevaFluxoEConfiguracoesSemOsLeads(): void
    {
        $model = new EmailSequence();
        $before = $model->findById($this->seqId);
        $copyId = $model->duplicate($this->seqId, $this->userId);

        $this->assertGreaterThan($this->seqId, $copyId);
        $copy = $model->findById($copyId);
        $this->assertSame("Seq Dup {$this->u} (cópia)", $copy['name']);
        $this->assertSame($before['graph'], $copy['graph'], 'fluxo idêntico (blocos, posições, ligações e conteúdo)');
        $this->assertEquals($this->graph, json_decode($copy['graph'], true));
        foreach (['description', 'is_active', 'daily_limit', 'window_start', 'window_end', 'send_weekends', 'email_account_id'] as $c) {
            $this->assertEquals($before[$c], $copy[$c], $c);
        }
        if (array_key_exists('channel_type', $before)) $this->assertSame($before['channel_type'], $copy['channel_type']);
        $this->assertSame($this->userId, (int) $copy['created_by']);

        $count = fn($id) => (int) $this->db->fetch("SELECT COUNT(*) c FROM sequence_participants WHERE sequence_id = ?", [$id])['c'];
        $this->assertSame(0, $count($copyId), 'leads não vão para a cópia');
        $this->assertSame(1, $count($this->seqId), 'original mantém o lead');
        $this->assertSame($before, $model->findById($this->seqId), 'original não muda');
    }

    public function testDuplicarDeNovoNumeraEInexistenteDevolveNull(): void
    {
        $model = new EmailSequence();
        $first = $model->duplicate($this->seqId, $this->userId);
        $second = $model->duplicate($this->seqId, $this->userId);
        $fromCopy = $model->duplicate($first, $this->userId);
        $this->assertSame("Seq Dup {$this->u} (cópia 2)", $model->findById($second)['name']);
        $this->assertSame("Seq Dup {$this->u} (cópia 3)", $model->findById($fromCopy)['name'], 'cópia da cópia não empilha sufixo');
        $this->assertNull($model->duplicate(999999999, $this->userId));
    }
}
