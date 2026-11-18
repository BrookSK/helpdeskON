<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use VideoRoom;
use VideocallController;
use Database;

/** IA falsa da minuta: devolve a ata estruturada fixa. */
final class AutoSendMinutesAi
{
    public function isConfigured(): bool { return true; }
    public function chat(array $messages, array $options = []): array
    {
        return ['success' => true, 'content' => json_encode([
            'resumo' => 'Alinhamento com o cliente.', 'proximos_passos' => ['Enviar cronograma'],
        ])];
    }
}

/**
 * Reunião de convite externo: a minuta gerada depois da transcrição do cron
 * sai sozinha para a equipe (participantes + organizador) e para os
 * convidados externos. A reunião comercial segue como rascunho
 * (MeetingMinutesDraftTest).
 */
final class MeetingMinutesAutoSendTest extends TestCase
{
    private Database $db;
    private VideoRoom $rooms;
    private array $userIds = [];
    private int $meetingId;
    private int $roomId;
    private string $u;

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste (helpdesk_on_test) não configurado.');
        }
        $this->db = Database::getInstance();
        $this->rooms = new VideoRoom();
        $this->u = $u = uniqid();
        foreach (['Organizador', 'Julia'] as $name) {
            $this->userIds[$name] = (int) $this->db->insert('users', [
                'name' => "{$name} {$u}", 'email' => strtolower($name) . "_{$u}@example.test",
                'password' => password_hash('x', PASSWORD_BCRYPT), 'role' => 'attendant',
            ]);
        }
        $this->meetingId = (int) $this->db->insert('agenda_meetings', [
            'title' => 'Alinhamento Externo', 'created_by' => $this->userIds['Julia'],
            'assigned_to' => $this->userIds['Organizador'], 'meeting_type' => 'externo',
            'external_guests' => json_encode([
                ['name' => 'Convidado', 'email' => "convidado_{$u}@example.test", 'phone' => ''],
                ['name' => 'Sem canal', 'email' => '', 'phone' => ''],
            ]),
        ]);
        // Julia é participante; o organizador não se marcou como participante.
        $this->db->insert('agenda_meeting_participants', ['meeting_id' => $this->meetingId, 'user_id' => $this->userIds['Julia']]);
        $token = $this->rooms->create([
            'title' => 'Sala Externa', 'created_by' => $this->userIds['Julia'], 'meeting_id' => $this->meetingId,
            'status' => 'active', 'visibility' => 'public', 'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
        ]);
        $this->roomId = (int) $this->rooms->findByToken($token)['id'];
    }

    protected function tearDown(): void
    {
        $this->db->delete('video_recordings', 'room_id = ?', [$this->roomId]);
        $this->db->delete('video_rooms', 'id = ?', [$this->roomId]);
        $this->db->delete('agenda_meeting_participants', 'meeting_id = ?', [$this->meetingId]);
        $this->db->delete('agenda_meetings', 'id = ?', [$this->meetingId]);
        foreach ($this->userIds as $id) {
            $this->db->delete('notifications', 'user_id = ?', [$id]);
            $this->db->delete('users', 'id = ?', [$id]);
        }
    }

    private function callPrivate(VideocallController $c, string $method, array $rec): array
    {
        return (new \ReflectionMethod($c, $method))->invoke($c, $rec);
    }

    public function testMinutaDeReuniaoExternaEEnviadaAutomaticamente(): void
    {
        $recToken = $this->rooms->addRecording([
            'room_id' => $this->roomId, 'file_path' => 'recordings/externo.webm', 'mime_type' => 'video/webm',
            'transcribe_status' => 'done', 'transcript' => "[00:01] Vamos alinhar o cronograma\n[20:00] Combinado",
        ]);
        $controller = new VideocallController();

        $controller->completeTranscription($recToken, new AutoSendMinutesAi());

        $rec = $this->rooms->findRecordingByToken($recToken);
        $this->assertSame('done', $rec['minutes_status']);
        $this->assertNotNull($rec['minutes_sent_at'], 'reunião externa envia a minuta sozinha');
        // Enviada: não fica rascunho esperando revisão do organizador.
        $this->assertSame([], $this->db->fetchAll('SELECT id FROM notifications WHERE user_id = ?', [$this->userIds['Organizador']]));

        // Quem recebe: convidado externo (cliente), participante e organizador (equipe).
        $recipients = $this->callPrivate($controller, 'minutesMeeting', $rec)['recipients'];
        $this->assertSame(
            ["convidado_{$this->u}@example.test" => 'client', "julia_{$this->u}@example.test" => 'team', "organizador_{$this->u}@example.test" => 'team'],
            array_column($recipients, 'role', 'email')
        );

        // Contexto da IA traz organizador e convidados.
        $ctx = $this->callPrivate($controller, 'buildMeetingContext', $rec);
        $this->assertSame("Organizador {$this->u}", $ctx['organizer']);
        $this->assertSame('Convidado, Sem canal', $ctx['guests']);
    }
}
