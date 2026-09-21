<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use AgendaMeeting;
use VideoRoom;
use Database;

/**
 * Testes de integração do módulo Agenda contra o banco helpdesk_on_test.
 *
 * Cobre os cenários pedidos: reunião comercial (com data/cliente/briefing por
 * notes), operacional, convite externo, participantes (1 e vários), status e
 * transições, e a sala de vídeo do sistema (pública/privada) vinculada.
 *
 * Estratégia de dados: cria usuários e contato próprios no setUp e limpa tudo
 * no tearDown. FKs: agenda_meetings.created_by (NOT NULL, users) e assigned_to
 * (users, ON DELETE SET NULL); participants tem FK CASCADE.
 */
final class AgendaMeetingTest extends TestCase
{
    private Database $db;
    private AgendaMeeting $model;
    private int $ownerId;
    private int $participantAId;
    private int $participantBId;
    private int $contactId;
    /** @var int[] */
    private array $meetingIds = [];
    /** @var int[] */
    private array $videoRoomIds = [];

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste (helpdesk_on_test) não configurado.');
        }

        $this->db = Database::getInstance();
        $this->model = new AgendaMeeting();

        $uid = uniqid();
        $this->ownerId = $this->novoUsuario("Comercial {$uid}", "com_{$uid}@example.test", 'comercial');
        $this->participantAId = $this->novoUsuario("Part A {$uid}", "pa_{$uid}@example.test", 'attendant');
        $this->participantBId = $this->novoUsuario("Part B {$uid}", "pb_{$uid}@example.test", 'developer');
        $phone = '5511' . random_int(100000000, 999999999);
        $this->contactId = (int) $this->db->insert('whatsapp_contacts', [
            'remote_jid' => $phone . '@s.whatsapp.net', // NOT NULL no schema
            'contact_name' => "Lead {$uid}",
            'phone' => $phone,
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->videoRoomIds as $id) {
            try { $this->db->delete('video_rooms', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
        foreach ($this->meetingIds as $id) {
            try { $this->db->delete('agenda_meetings', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
        // Usuários (participantes saem via CASCADE; created_by CASCADE apaga reuniões restantes)
        foreach ([$this->participantAId, $this->participantBId, $this->ownerId] as $id) {
            try { $this->db->delete('users', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
        try { $this->db->delete('whatsapp_contacts', 'id = ?', [$this->contactId]); } catch (\Throwable $e) {}
    }

    private function novoUsuario(string $name, string $email, string $role): int
    {
        return (int) $this->db->insert('users', [
            'name' => $name,
            'email' => $email,
            'password' => password_hash('x', PASSWORD_BCRYPT),
            'role' => $role,
        ]);
    }

    private function novaReuniao(array $overrides = []): int
    {
        $id = (int) $this->model->create(array_merge([
            'title' => 'Reunião teste',
            'meeting_type' => 'comercial',
            'created_by' => $this->ownerId,
            'assigned_to' => $this->ownerId,
            'urgency' => 'media',
            'status' => 'a_agendar',
            'position' => 0,
        ], $overrides));
        $this->meetingIds[] = $id;
        return $id;
    }

    // ================= Reunião COMERCIAL =================

    public function testCriarReuniaoComercialComDataClienteEDescricao(): void
    {
        $id = $this->novaReuniao([
            'title' => 'Apresentação de proposta',
            'meeting_type' => 'comercial',
            'contact_id' => $this->contactId,
            'client_name' => 'Cliente Snapshot',
            'client_phone' => '11999990000',
            'client_email' => 'cliente@example.com',
            'temperature' => 'quente',
            'urgency' => 'alta',
            'status' => 'agendada',
            'meeting_at' => '2026-02-10 15:00:00',
            'notes' => 'Descrição/briefing: cliente quer módulo financeiro.',
        ]);

        $m = $this->model->findById($id);
        $this->assertSame('Apresentação de proposta', $m['title']);
        $this->assertSame('comercial', $m['meeting_type']);
        $this->assertSame('agendada', $m['status']);
        $this->assertSame('quente', $m['temperature']);
        $this->assertSame('2026-02-10 15:00:00', $m['meeting_at']);
        $this->assertStringContainsString('financeiro', $m['notes']);
        // JOIN traz o nome do contato do CRM
        $this->assertSame($this->contactId, (int) $m['contact_id']);
        $this->assertNotEmpty($m['crm_contact_name']);
    }

    public function testReuniaoComercialConvertidaGuardaQuemFechou(): void
    {
        $id = $this->novaReuniao([
            'status' => 'convertida',
            'closed_by' => $this->participantAId,
            'meeting_at' => '2026-03-01 10:00:00',
        ]);
        $m = $this->model->findById($id);
        $this->assertSame('convertida', $m['status']);
        $this->assertSame($this->participantAId, (int) $m['closed_by']);
    }

    // ================= Reunião OPERACIONAL =================

    public function testCriarReuniaoOperacionalSemCliente(): void
    {
        $id = $this->novaReuniao([
            'title' => 'Daily do time',
            'meeting_type' => 'operacional',
            'contact_id' => null,
            'client_name' => null,
            'client_email' => null,
            'temperature' => null,
            'meeting_at' => '2026-02-11 09:00:00',
            'notes' => 'Alinhamento interno.',
        ]);
        $m = $this->model->findById($id);
        $this->assertSame('operacional', $m['meeting_type']);
        $this->assertNull($m['contact_id']);
        $this->assertNull($m['client_name']);
    }

    // ================= Reunião EXTERNA (convite) =================

    public function testCriarReuniaoExternaComConvidados(): void
    {
        $guests = [
            ['name' => 'Convidado 1', 'email' => 'c1@example.com', 'phone' => ''],
            ['name' => 'Convidado 2', 'email' => '', 'phone' => '11955554444'],
        ];
        $id = $this->novaReuniao([
            'title' => 'Reunião com parceiros',
            'meeting_type' => 'externo',
            'contact_id' => null,
            'external_guests' => json_encode($guests, JSON_UNESCAPED_UNICODE),
            'register_google' => 1,
            'meeting_at' => '2026-02-12 16:00:00',
        ]);
        $m = $this->model->findById($id);
        $this->assertSame('externo', $m['meeting_type']);
        $this->assertSame(1, (int) $m['register_google']);
        $decoded = json_decode($m['external_guests'], true);
        $this->assertCount(2, $decoded);
        $this->assertSame('Convidado 1', $decoded[0]['name']);
    }

    // ================= Participantes =================

    public function testUmParticipante(): void
    {
        $id = $this->novaReuniao(['meeting_type' => 'operacional']);
        $this->model->setParticipants($id, [$this->participantAId]);

        $parts = $this->model->getParticipants($id);
        $this->assertCount(1, $parts);
        $this->assertSame($this->participantAId, (int) $parts[0]['id']);
    }

    public function testVariosParticipantes(): void
    {
        $id = $this->novaReuniao(['meeting_type' => 'operacional']);
        $this->model->setParticipants($id, [$this->participantAId, $this->participantBId]);

        $parts = $this->model->getParticipants($id);
        $this->assertCount(2, $parts);

        $emails = $this->model->getParticipantEmails($id);
        $this->assertCount(2, $emails);
    }

    public function testSetParticipantsSubstituiOsAnteriores(): void
    {
        $id = $this->novaReuniao(['meeting_type' => 'operacional']);
        $this->model->setParticipants($id, [$this->participantAId, $this->participantBId]);
        // Redefine para apenas um
        $this->model->setParticipants($id, [$this->participantBId]);

        $parts = $this->model->getParticipants($id);
        $this->assertCount(1, $parts);
        $this->assertSame($this->participantBId, (int) $parts[0]['id']);
    }

    // ================= Status / fluxo =================

    public function testUpdateStatusPercorreOFluxo(): void
    {
        $id = $this->novaReuniao(['status' => 'a_agendar']);
        foreach (['agendada', 'confirmada', 'realizada'] as $st) {
            $this->model->updateStatus($id, $st);
            $this->assertSame($st, $this->model->findById($id)['status']);
        }
    }

    public function testGetGroupedByStatusOrganizaPorColuna(): void
    {
        $a = $this->novaReuniao(['status' => 'agendada', 'assigned_to' => $this->ownerId]);
        $b = $this->novaReuniao(['status' => 'cancelada', 'assigned_to' => $this->ownerId]);

        $grouped = $this->model->getGroupedByStatus(['assigned_to' => $this->ownerId]);
        $this->assertArrayHasKey('agendada', $grouped);
        $this->assertArrayHasKey('cancelada', $grouped);

        $idsAgendada = array_map(fn($m) => (int) $m['id'], $grouped['agendada']);
        $this->assertContains($a, $idsAgendada);
        $idsCancelada = array_map(fn($m) => (int) $m['id'], $grouped['cancelada']);
        $this->assertContains($b, $idsCancelada);
    }

    // ================= Sala de vídeo do sistema =================

    public function testSalaDeVideoPublicaVinculadaAReuniao(): void
    {
        $id = $this->novaReuniao(['title' => 'Reunião com vídeo', 'meeting_type' => 'operacional']);

        $video = new VideoRoom();
        $token = $video->create([
            'title' => 'Reunião com vídeo',
            'created_by' => $this->ownerId,
            'meeting_id' => $id,
            'max_participants' => 8,
            'allow_recording' => 1,
            'allow_presentation' => 1,
            'status' => 'active',
            'visibility' => 'public',
            'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
        ]);

        $room = $video->findByToken($token);
        $this->videoRoomIds[] = (int) $room['id'];

        $this->assertNotEmpty($room);
        $this->assertSame($id, (int) $room['meeting_id']);
        $this->assertSame('public', $room['visibility']);
        $this->assertSame('active', $room['status']);
        $this->assertNotEmpty($room['token']);
    }

    public function testSalaDeVideoPrivadaComAdmins(): void
    {
        $id = $this->novaReuniao(['title' => 'Reunião privada', 'meeting_type' => 'operacional']);

        $video = new VideoRoom();
        $token = $video->create([
            'title' => 'Reunião privada',
            'created_by' => $this->ownerId,
            'meeting_id' => $id,
            'max_participants' => 5,
            'allow_recording' => 1,
            'allow_presentation' => 1,
            'status' => 'active',
            'visibility' => 'private',
            'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
        ]);
        $room = $video->findByToken($token);
        $this->videoRoomIds[] = (int) $room['id'];

        $video->setAdmins($room['id'], [$this->ownerId, $this->participantAId]);
        $adminIds = array_map('intval', $video->getAdminIds($room['id']));

        $this->assertSame('private', $room['visibility']);
        $this->assertContains($this->ownerId, $adminIds);
        $this->assertContains($this->participantAId, $adminIds);
    }

    // ================= Métricas (dashboard) =================

    public function testPerformanceStatsContaPorStatus(): void
    {
        $this->novaReuniao(['status' => 'agendada', 'assigned_to' => $this->ownerId, 'meeting_at' => date('Y-m-d H:i:s')]);
        $this->novaReuniao(['status' => 'convertida', 'assigned_to' => $this->ownerId, 'closed_by' => $this->ownerId, 'meeting_at' => date('Y-m-d H:i:s')]);

        $stats = $this->model->getPerformanceStats(date('Y-m-d'), date('Y-m-d'), $this->ownerId);
        $this->assertArrayHasKey($this->ownerId, $stats);
        $this->assertGreaterThanOrEqual(2, $stats[$this->ownerId]['total']);
    }
}
