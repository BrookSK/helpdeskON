<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use AgendaController;
use AgendaMeeting;
use VideoRoom;
use Database;

/**
 * Regressão: convite externo saía sem o link de entrada quando a opção era
 * "Sala de vídeo do sistema" e o modal salvava sem o link (sala privada não
 * criada, criação pendente ou com falha), e no reenvio de reunião com sala
 * vinculada por meeting_id mas meet_link vazio.
 *
 * Exercita os helpers privados do AgendaController que os envios usam
 * (createSystemVideoRoom e fillMeetLinkFromLinkedRoom) contra helpdesk_on_test.
 */
final class AgendaInviteLinkTest extends TestCase
{
    private Database $db;
    private AgendaMeeting $model;
    private AgendaController $ctrl;
    private int $ownerId;
    private int $adminId;
    /** @var int[] */
    private array $meetingIds = [];

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste (helpdesk_on_test) não configurado.');
        }
        $this->db = Database::getInstance();
        $this->model = new AgendaMeeting();
        $this->ctrl = new AgendaController();
        $_POST = [];

        $uid = uniqid();
        $this->ownerId = (int) $this->db->insert('users', [
            'name' => "Dono {$uid}", 'email' => "dono_{$uid}@example.test",
            'password' => password_hash('x', PASSWORD_BCRYPT), 'role' => 'comercial',
        ]);
        $this->adminId = (int) $this->db->insert('users', [
            'name' => "Admin {$uid}", 'email' => "adm_{$uid}@example.test",
            'password' => password_hash('x', PASSWORD_BCRYPT), 'role' => 'attendant',
        ]);
    }

    protected function tearDown(): void
    {
        $_POST = [];
        if (!isset($this->db)) return;
        foreach ($this->meetingIds as $id) {
            try { $this->db->delete('video_rooms', 'meeting_id = ?', [$id]); } catch (\Throwable $e) {}
            try { $this->db->delete('agenda_meetings', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
        foreach ([$this->adminId, $this->ownerId] as $id) {
            try { $this->db->delete('users', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
    }

    private function reuniaoExterna(array $overrides = []): int
    {
        $id = (int) $this->model->create(array_merge([
            'title' => 'Convite externo',
            'meeting_type' => 'externo',
            'created_by' => $this->ownerId,
            'assigned_to' => $this->ownerId,
            'urgency' => 'media',
            'status' => 'agendada',
            'position' => 0,
            'meeting_at' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'external_guests' => json_encode([['name' => 'Cliente', 'email' => '', 'phone' => '11999998888']]),
        ], $overrides));
        $this->meetingIds[] = $id;
        return $id;
    }

    private function chamar(string $method, ...$args)
    {
        $m = new \ReflectionMethod(AgendaController::class, $method);
        return $m->invoke($this->ctrl, ...$args);
    }

    private function usuario(): array
    {
        return ['id' => $this->ownerId, 'name' => 'Dono', 'email' => '', 'role' => 'comercial'];
    }

    public function testSalaPrivadaCriadaNoServidorGravaLinkAntesDoConvite(): void
    {
        // Modal salvou com "Sala de vídeo do sistema" privada sem clicar em
        // "Criar sala privada": meet_link vazio no POST.
        $id = $this->reuniaoExterna();
        $_POST = ['video_visibility' => 'private', 'video_admins' => [(string) $this->adminId]];

        $room = $this->chamar('createSystemVideoRoom', $id, $this->usuario(), [], 1);

        $this->assertNotEmpty($room);
        $meeting = $this->model->findById($id);
        $this->assertStringEndsWith('/videocall/room/' . $room['token'], (string) $meeting['meet_link']);
        $this->assertSame('private', $room['visibility']);
        $this->assertSame(1, (int) $room['auto_record']);
        $admins = array_map('intval', (new VideoRoom())->getAdminIds($room['id']));
        $this->assertContains($this->ownerId, $admins);
        $this->assertContains($this->adminId, $admins);
    }

    public function testSalaJaVinculadaSemLinkReusaSalaEPreencheLink(): void
    {
        // Edição: sala criada no modal com meeting_id, mas meet_link não salvo.
        $id = $this->reuniaoExterna();
        $video = new VideoRoom();
        $token = $video->create([
            'title' => 'Convite externo', 'created_by' => $this->ownerId, 'meeting_id' => $id,
            'max_participants' => 15, 'allow_recording' => 1, 'allow_presentation' => 1,
            'status' => 'active', 'visibility' => 'public',
            'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
        ]);

        $room = $this->chamar('createSystemVideoRoom', $id, $this->usuario(), [], 0);

        $this->assertSame($token, $room['token']);
        $rooms = $this->db->fetchAll("SELECT id FROM video_rooms WHERE meeting_id = ?", [$id]);
        $this->assertCount(1, $rooms, 'Não pode criar sala duplicada.');
        $this->assertStringEndsWith('/videocall/room/' . $token, (string) $this->model->findById($id)['meet_link']);
    }

    public function testReenvioRecuperaLinkDaSalaVinculada(): void
    {
        $id = $this->reuniaoExterna();
        $token = (new VideoRoom())->create([
            'title' => 'Convite externo', 'created_by' => $this->ownerId, 'meeting_id' => $id,
            'max_participants' => 15, 'allow_recording' => 1, 'allow_presentation' => 1,
            'status' => 'active', 'visibility' => 'public',
            'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
        ]);

        $this->chamar('fillMeetLinkFromLinkedRoom', $id);

        $this->assertStringEndsWith('/videocall/room/' . $token, (string) $this->model->findById($id)['meet_link']);
    }

    public function testReenvioNaoSobrescreveLinkExistenteNemCriaSala(): void
    {
        $meet = 'https://meet.google.com/abc-defg-hij';
        $comLink = $this->reuniaoExterna(['meet_link' => $meet]);
        $semSala = $this->reuniaoExterna();

        $this->chamar('fillMeetLinkFromLinkedRoom', $comLink);
        $this->chamar('fillMeetLinkFromLinkedRoom', $semSala);

        $this->assertSame($meet, $this->model->findById($comLink)['meet_link']);
        $this->assertEmpty($this->model->findById($semSala)['meet_link']);
        $this->assertCount(0, $this->db->fetchAll("SELECT id FROM video_rooms WHERE meeting_id = ?", [$semSala]));
    }
}
