<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use VideoRoom;
use VideocallController;
use Database;

/** IA falsa da minuta: devolve a ata estruturada fixa. */
final class FakeMinutesAi
{
    public int $calls = 0;
    public function isConfigured(): bool { return true; }
    public function chat(array $messages, array $options = []): array
    {
        $this->calls++;
        return ['success' => true, 'content' => json_encode([
            'resumo' => 'Cliente quer um CRM.', 'necessidades' => ['Organizar leads'], 'orcamento' => [],
            'proximos_passos' => ['Enviar proposta'],
        ])];
    }
}

/**
 * Minuta gerada depois da transcrição do cron: nasce como rascunho, não é
 * enviada ao cliente e o organizador da reunião recebe uma notificação.
 */
final class MeetingMinutesDraftTest extends TestCase
{
    private Database $db;
    private VideoRoom $rooms;
    private int $organizerId;
    private int $creatorId;
    private int $meetingId;
    private int $roomId;

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste (helpdesk_on_test) não configurado.');
        }
        $this->db = Database::getInstance();
        $this->rooms = new VideoRoom();
        $u = uniqid();
        $this->organizerId = (int) $this->db->insert('users', [
            'name' => "Comercial {$u}", 'email' => "com_{$u}@example.test",
            'password' => password_hash('x', PASSWORD_BCRYPT), 'role' => 'attendant',
        ]);
        $this->creatorId = (int) $this->db->insert('users', [
            'name' => "Criador {$u}", 'email' => "cri_{$u}@example.test",
            'password' => password_hash('x', PASSWORD_BCRYPT), 'role' => 'attendant',
        ]);
        $this->meetingId = (int) $this->db->insert('agenda_meetings', [
            'title' => 'Descoberta ACME', 'created_by' => $this->creatorId, 'assigned_to' => $this->organizerId,
            'meeting_type' => 'comercial', 'client_name' => 'ACME', 'client_email' => "acme_{$u}@example.test",
        ]);
        $token = $this->rooms->create([
            'title' => 'Sala ACME', 'created_by' => $this->creatorId, 'meeting_id' => $this->meetingId,
            'status' => 'active', 'visibility' => 'public', 'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
        ]);
        $this->roomId = (int) $this->rooms->findByToken($token)['id'];
    }

    protected function tearDown(): void
    {
        $this->db->delete('video_recordings', 'room_id = ?', [$this->roomId]);
        $this->db->delete('video_rooms', 'id = ?', [$this->roomId]);
        $this->db->delete('agenda_meetings', 'id = ?', [$this->meetingId]);
        foreach ([$this->organizerId, $this->creatorId] as $id) {
            $this->db->delete('notifications', 'user_id = ?', [$id]);
            $this->db->delete('users', 'id = ?', [$id]);
        }
    }

    public function testMinutaNasceComoRascunhoENotificaOrganizador(): void
    {
        $recToken = $this->rooms->addRecording([
            'room_id' => $this->roomId, 'file_path' => 'recordings/draft.webm', 'mime_type' => 'video/webm',
            'transcribe_status' => 'done', 'transcript' => "[00:01] Precisamos de um CRM\n[30:00] Mandem a proposta",
        ]);
        $ai = new FakeMinutesAi();

        (new VideocallController())->completeTranscription($recToken, $ai);

        $rec = $this->rooms->findRecordingByToken($recToken);
        $this->assertSame('done', $rec['minutes_status']);
        $this->assertNull($rec['minutes_sent_at'], 'a minuta não pode sair para o cliente sozinha');
        $this->assertStringContainsString("## Necessidades do cliente\n- Organizar leads", $rec['minutes']);
        $this->assertSame([], json_decode($rec['summary'], true)['orcamento']);
        $this->assertSame(1, $ai->calls);

        $notes = $this->db->fetchAll('SELECT title, message FROM notifications WHERE user_id = ?', [$this->organizerId]);
        $this->assertCount(1, $notes);
        $this->assertSame('Minuta pronta para revisão', $notes[0]['title']);
        $this->assertStringContainsString('/videocall/watch/' . $recToken, $notes[0]['message']);
        $this->assertSame([], $this->db->fetchAll('SELECT id FROM notifications WHERE user_id = ?', [$this->creatorId]));
    }
}
