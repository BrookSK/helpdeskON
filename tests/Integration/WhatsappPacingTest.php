<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Database;
use SchemaInfo;
use SequenceEngine;
use WhatsappPacingRules;

/**
 * Integração (helpdesk_on_test): intervalo entre os WhatsApps automáticos.
 * O motor olha o último WhatsApp que a sequência mandou pela MESMA conta e só
 * libera o próximo depois do intervalo sorteado. Mensagens de outra conta ou
 * escritas por uma pessoa no chat não contam.
 */
final class WhatsappPacingTest extends TestCase
{
    private Database $db;
    private string $u;
    private int $instanceId;
    private int $otherInstanceId;
    private int $contactId;

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste não configurado.');
        }
        $this->db = Database::getInstance();
        SchemaInfo::reset();
        $this->u = uniqid();
        $mkInst = fn($n) => (int) $this->db->insert('whatsapp_instances', [
            'instance_name' => "pacing_{$n}_{$this->u}", 'display_name' => "Pacing {$n}",
            'api_url' => 'https://evolution.example.test', 'api_key' => 'x',
            'connection_status' => 'open', 'is_default' => 0,
        ]);
        $this->instanceId = $mkInst('a');
        $this->otherInstanceId = $mkInst('b');
        $this->contactId = (int) $this->db->insert('whatsapp_contacts', [
            'remote_jid' => '5599' . random_int(10000000, 99999999) . '@s.whatsapp.net', 'contact_name' => "Lead Pacing {$this->u}",
        ]);
    }

    protected function tearDown(): void
    {
        if (!isset($this->db)) return;
        $this->db->query("DELETE FROM whatsapp_messages WHERE contact_id = ?", [$this->contactId]);
        $this->db->delete('whatsapp_contacts', 'id = ?', [$this->contactId]);
        $this->db->query("DELETE FROM whatsapp_instances WHERE id IN (?, ?)", [$this->instanceId, $this->otherInstanceId]);
    }

    private function message(int $instanceId, int $minutesAgo, string $sender = 'Prospecção'): int
    {
        return (int) $this->db->insert('whatsapp_messages', [
            'instance_id' => $instanceId, 'contact_id' => $this->contactId,
            'remote_jid' => 'x@s.whatsapp.net', 'message_id' => uniqid('pace_'), 'from_me' => 1,
            'message_type' => 'text', 'message_text' => 'Oi', 'sender_name' => $sender,
            'timestamp' => date('Y-m-d H:i:s', time() - $minutesAgo * 60), 'is_read' => 1,
        ]);
    }

    private function seq(array $extra = []): array
    {
        return array_merge(['id' => 0, 'whatsapp_instance_id' => $this->instanceId, 'wa_gap_min_minutes' => 2, 'wa_gap_max_minutes' => 10], $extra);
    }

    public function testSemEnvioAnteriorPodeMandarJa(): void
    {
        $this->assertNull((new SequenceEngine())->whatsappPacingWaitUntil($this->seq()));
    }

    public function testLogoDepoisDeUmEnvioEsperaOIntervaloSorteado(): void
    {
        $id = $this->message($this->instanceId, 0);
        $wait = (new SequenceEngine())->whatsappPacingWaitUntil($this->seq());
        $this->assertNotNull($wait);
        $last = strtotime($this->db->fetch("SELECT timestamp FROM whatsapp_messages WHERE id = ?", [$id])['timestamp']);
        $this->assertSame($last + WhatsappPacingRules::gapSeconds($id, 2, 10), $wait);
        $this->assertGreaterThanOrEqual(time() + 110, $wait);
        $this->assertLessThanOrEqual(time() + 600, $wait);
        // Reconferir não muda a espera (o cron roda várias vezes)
        $this->assertSame($wait, (new SequenceEngine())->whatsappPacingWaitUntil($this->seq()));
    }

    public function testDepoisDoMaximoLibera(): void
    {
        $this->message($this->instanceId, 11);
        $this->assertNull((new SequenceEngine())->whatsappPacingWaitUntil($this->seq()));
    }

    public function testOutraContaEMensagemDePessoaNaoContam(): void
    {
        $this->message($this->otherInstanceId, 0);
        $this->message($this->instanceId, 0, 'Evelyn');
        $this->assertNull((new SequenceEngine())->whatsappPacingWaitUntil($this->seq()));
        // ...mas a outra conta espera o próprio intervalo
        $this->assertNotNull((new SequenceEngine())->whatsappPacingWaitUntil($this->seq(['whatsapp_instance_id' => $this->otherInstanceId])));
    }

    public function testIntervaloDesligadoNaoEspera(): void
    {
        $this->message($this->instanceId, 0);
        $this->assertNull((new SequenceEngine())->whatsappPacingWaitUntil($this->seq(['wa_gap_min_minutes' => 0, 'wa_gap_max_minutes' => 0])));
    }

    public function testSequenciaSemAColunaUsaOPadrao(): void
    {
        $this->message($this->instanceId, 1); // 1 min atrás: dentro de 2..10 min
        $seq = $this->seq();
        unset($seq['wa_gap_min_minutes'], $seq['wa_gap_max_minutes']);
        $this->assertNotNull((new SequenceEngine())->whatsappPacingWaitUntil($seq));
    }

    public function testDuplicarSequenciaLevaOIntervalo(): void
    {
        if (!SchemaInfo::hasColumn('email_sequences', 'wa_gap_min_minutes')) $this->markTestSkipped('Migration 175 não aplicada.');
        $row = ['name' => "Seq Pacing {$this->u}", 'graph' => json_encode(['start' => null, 'nodes' => []]), 'is_active' => 0,
            'wa_gap_min_minutes' => 3, 'wa_gap_max_minutes' => 15];
        $id = (int) $this->db->insert('email_sequences', $row);
        $copy = (new \EmailSequence())->duplicate($id);
        try {
            $c = $this->db->fetch("SELECT wa_gap_min_minutes, wa_gap_max_minutes FROM email_sequences WHERE id = ?", [$copy]);
            $this->assertSame([3, 15], [(int) $c['wa_gap_min_minutes'], (int) $c['wa_gap_max_minutes']]);
            $d = $this->db->fetch("SELECT wa_gap_min_minutes, wa_gap_max_minutes FROM email_sequences WHERE id = ?", [$this->db->insert('email_sequences', ['name' => "Seq Pacing D {$this->u}", 'graph' => '{}'])]);
            $this->assertSame([2, 10], [(int) $d['wa_gap_min_minutes'], (int) $d['wa_gap_max_minutes']], 'padrão do banco: 2 a 10');
        } finally {
            $this->db->query("DELETE FROM email_sequences WHERE name LIKE ?", ["%Pacing%{$this->u}%"]);
        }
    }
}
