<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use VideoRoom;
use Proposal;
use ProposalFromRecording;
use Database;

/** IA falsa da proposta: devolve a resposta programada. */
final class FakeProposalAi
{
    public array $reply = ['success' => false, 'error' => 'fora do ar'];
    public array $calls = [];
    public function isConfigured(): bool { return true; }
    public function chat(array $messages, array $options = []): array
    {
        $this->calls[] = $messages;
        return $this->reply;
    }
}

/**
 * Rascunho de proposta gerado da minuta: cliente e card vêm da reunião, o
 * entendimento do negócio vem da ata e os itens só do catálogo ativo.
 */
final class ProposalFromRecordingTest extends TestCase
{
    private Database $db;
    private VideoRoom $rooms;
    private array $ids = [];
    private string $recToken;
    private array $rec;

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste (helpdesk_on_test) não configurado.');
        }
        $this->db = Database::getInstance();
        $this->rooms = new VideoRoom();
        $u = uniqid();
        $this->ids['user'] = (int) $this->db->insert('users', [
            'name' => "Comercial {$u}", 'email' => "com_{$u}@example.test",
            'password' => password_hash('x', PASSWORD_BCRYPT), 'role' => 'attendant',
        ]);
        $this->ids['contact'] = (int) $this->db->insert('whatsapp_contacts', [
            'remote_jid' => "55119{$u}@s.whatsapp.net", 'phone' => '5511999990000',
            'contact_name' => 'Lucas ACME', 'lead_email' => "lucas_{$u}@example.test",
        ]);
        $this->ids['board'] = (int) $this->db->insert('crm_boards', ['name' => "Quadro {$u}", 'created_by' => $this->ids['user']]);
        $this->ids['column'] = (int) $this->db->insert('crm_columns', ['board_id' => $this->ids['board'], 'name' => 'Reunião', 'position' => 0]);
        $this->ids['card'] = (int) $this->db->insert('crm_cards', [
            'column_id' => $this->ids['column'], 'title' => 'ACME', 'contact_id' => $this->ids['contact'],
        ]);
        $this->ids['svc_active'] = (int) $this->db->insert('service_catalog', ['name' => "Site {$u}", 'hourly_rate' => 100, 'est_hours' => 40, 'active' => 1]);
        $this->ids['svc_inactive'] = (int) $this->db->insert('service_catalog', ['name' => "Antigo {$u}", 'hourly_rate' => 50, 'active' => 0]);
        $this->ids['meeting'] = (int) $this->db->insert('agenda_meetings', [
            'title' => 'Descoberta ACME', 'created_by' => $this->ids['user'], 'meeting_type' => 'comercial',
            'contact_id' => $this->ids['contact'], 'client_name' => 'Lucas ACME',
        ]);
        $token = $this->rooms->create([
            'title' => 'Sala ACME', 'created_by' => $this->ids['user'], 'meeting_id' => $this->ids['meeting'],
            'status' => 'active', 'visibility' => 'public', 'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
        ]);
        $this->ids['room'] = (int) $this->rooms->findByToken($token)['id'];
        $this->recToken = $this->rooms->addRecording([
            'room_id' => $this->ids['room'], 'file_path' => 'recordings/p.webm', 'mime_type' => 'video/webm',
            'minutes_status' => 'done', 'minutes' => 'texto',
            'summary' => json_encode(['resumo' => 'Empresa de logística.', 'necessidades' => ['Organizar leads'], 'escopo' => ['Site novo']]),
        ]);
        $this->rec = $this->rooms->findRecordingByToken($this->recToken);
    }

    protected function tearDown(): void
    {
        foreach ($this->db->fetchAll('SELECT id FROM proposals WHERE recording_id = ?', [$this->rec['id']]) as $p) {
            $this->db->delete('proposal_items', 'proposal_id = ?', [$p['id']]);
            try { $this->db->delete('proposal_events', 'proposal_id = ?', [$p['id']]); } catch (\Throwable $e) {}
            $this->db->delete('proposals', 'id = ?', [$p['id']]);
        }
        $this->db->delete('video_recordings', 'room_id = ?', [$this->ids['room']]);
        $this->db->delete('video_rooms', 'id = ?', [$this->ids['room']]);
        $this->db->delete('agenda_meetings', 'id = ?', [$this->ids['meeting']]);
        $this->db->delete('service_catalog', 'id IN (?, ?)', [$this->ids['svc_active'], $this->ids['svc_inactive']]);
        $this->db->delete('crm_cards', 'id = ?', [$this->ids['card']]);
        $this->db->delete('crm_columns', 'id = ?', [$this->ids['column']]);
        $this->db->delete('crm_boards', 'id = ?', [$this->ids['board']]);
        $this->db->delete('whatsapp_contacts', 'id = ?', [$this->ids['contact']]);
        $this->db->delete('users', 'id = ?', [$this->ids['user']]);
    }

    public function testComIaGeraRascunhoComClienteEntendimentoEItensDoCatalogo(): void
    {
        $ai = new FakeProposalAi();
        $ai->reply = ['success' => true, 'content' => json_encode([
            'itens' => [
                ['service_id' => $this->ids['svc_active'], 'quantidade' => 1, 'motivo' => 'Site novo'],
                ['service_id' => $this->ids['svc_inactive'], 'quantidade' => 1, 'motivo' => 'inativo'],
            ],
            'fora_do_catalogo' => ['Integração com ERP'],
        ])];
        $builder = new ProposalFromRecording($ai);
        $this->assertNull($builder->existingFor((int)$this->rec['id']));

        $r = $builder->create($this->rec, $this->ids['user']);

        $this->assertTrue($r['ai_ok']);
        $p = (new Proposal())->findById($r['id']);
        $this->assertSame('draft', $p['status']);
        $this->assertSame((int)$this->rec['id'], (int)$p['recording_id']);
        $this->assertSame($this->ids['contact'], (int)$p['contact_id']);
        $this->assertSame($this->ids['card'], (int)$p['card_id']);
        $this->assertSame('Lucas ACME', $p['client_name']);
        $this->assertStringContainsString('lucas_', $p['client_email']);
        $this->assertStringContainsString('Integração com ERP', $p['observations']);
        $content = json_decode($p['content'], true);
        $this->assertSame('Empresa de logística.', $content['business_profile']);
        $this->assertStringContainsString('- Organizar leads', $content['business_opportunity']);
        $this->assertSame('50% na assinatura e 50% na entrega', $content['payment_method']);

        $items = (new Proposal())->getItems($r['id']);
        $this->assertCount(1, $items);
        $this->assertSame($this->ids['svc_active'], (int)$items[0]['service_id']);
        $this->assertSame(4000.0, (float)$p['total']);
        $this->assertStringNotContainsString("Antigo", $ai->calls[0][1]['content']);
        $this->assertSame($r['id'], (int)$builder->existingFor((int)$this->rec['id'])['id']);
    }

    public function testFalhaDaIaCriaRascunhoComClienteEVinculo(): void
    {
        $r = (new ProposalFromRecording(new FakeProposalAi()))->create($this->rec, $this->ids['user']);

        $this->assertFalse($r['ai_ok']);
        $this->assertSame(0, $r['items']);
        $p = (new Proposal())->findById($r['id']);
        $this->assertSame('draft', $p['status']);
        $this->assertSame($this->ids['contact'], (int)$p['contact_id']);
        $this->assertSame((int)$this->rec['id'], (int)$p['recording_id']);
        $this->assertNull($p['observations']);
        $this->assertSame([], (new Proposal())->getItems($r['id']));
    }
}
