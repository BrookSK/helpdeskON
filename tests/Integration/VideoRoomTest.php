<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use VideoRoom;
use Database;

/**
 * Testes de integração da Sala de Vídeo (VideoRoom) contra o banco
 * helpdesk_on_test. Cobre salas, presença (join/heartbeat/leave/ativos/
 * dedup por navegador), sinalização (push/pull + efêmeros), gravações
 * (add/list/find/update + visibilidade), administradores e pedidos de entrada.
 */
final class VideoRoomTest extends TestCase
{
    private Database $db;
    private VideoRoom $model;
    private int $ownerId;
    private int $otherId;
    /** @var string[] tokens de salas criadas */
    private array $roomTokens = [];
    /** @var int[] ids de salas criadas */
    private array $roomIds = [];

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste (helpdesk_on_test) não configurado.');
        }
        $this->db = Database::getInstance();
        $this->model = new VideoRoom();

        $u = uniqid();
        $this->ownerId = $this->novoUsuario("Dono {$u}", "dono_{$u}@example.test", 'attendant');
        $this->otherId = $this->novoUsuario("Outro {$u}", "outro_{$u}@example.test", 'attendant');
    }

    protected function tearDown(): void
    {
        foreach ($this->roomIds as $id) {
            try { $this->db->delete('video_room_participants', 'room_id = ?', [$id]); } catch (\Throwable $e) {}
            try { $this->db->delete('video_room_signals', 'room_id = ?', [$id]); } catch (\Throwable $e) {}
            try { $this->db->delete('video_recordings', 'room_id = ?', [$id]); } catch (\Throwable $e) {}
            try { $this->db->delete('video_room_admins', 'room_id = ?', [$id]); } catch (\Throwable $e) {}
            try { $this->db->delete('video_room_join_requests', 'room_id = ?', [$id]); } catch (\Throwable $e) {}
            try { $this->db->delete('video_rooms', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
        foreach ([$this->otherId, $this->ownerId] as $id) {
            try { $this->db->delete('users', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
    }

    private function novoUsuario(string $name, string $email, string $role): int
    {
        return (int) $this->db->insert('users', [
            'name' => $name, 'email' => $email,
            'password' => password_hash('x', PASSWORD_BCRYPT), 'role' => $role,
        ]);
    }

    private function novaSala(array $overrides = []): array
    {
        $token = $this->model->create(array_merge([
            'title' => 'Sala teste',
            'created_by' => $this->ownerId,
            'max_participants' => 8,
            'allow_recording' => 1,
            'allow_presentation' => 1,
            'status' => 'active',
            'visibility' => 'public',
            'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
        ], $overrides));
        $room = $this->model->findByToken($token);
        $this->roomTokens[] = $token;
        $this->roomIds[] = (int) $room['id'];
        return $room;
    }

    // ================= Salas =================

    public function testCriarSalaGeraTokenEEncontraPorToken(): void
    {
        $room = $this->novaSala(['title' => 'Reunião X']);
        $this->assertNotEmpty($room['token']);
        $this->assertSame('Reunião X', $room['title']);
        $this->assertSame('active', $room['status']);
        $this->assertSame('public', $room['visibility']);

        $porId = $this->model->findById($room['id']);
        $this->assertSame($room['token'], $porId['token']);
    }

    public function testEncerrarSala(): void
    {
        $room = $this->novaSala();
        $this->model->end($room['id']);
        $atual = $this->model->findById($room['id']);
        $this->assertSame('ended', $atual['status']);
        $this->assertNotEmpty($atual['ended_at']);
    }

    public function testListByUser(): void
    {
        $room = $this->novaSala();
        $lista = $this->model->listByUser($this->ownerId);
        $ids = array_map(fn($r) => (int) $r['id'], $lista);
        $this->assertContains((int) $room['id'], $ids);
    }

    // ================= Presença =================

    public function testJoinHeartbeatEActiveParticipants(): void
    {
        $room = $this->novaSala();
        $this->model->joinPresence($room['id'], 'peerA', 'Alice', $this->ownerId, 'host');
        $this->model->joinPresence($room['id'], 'peerB', 'Bob', $this->otherId, 'participant');

        $ativos = $this->model->activeParticipants($room['id']);
        $peers = array_column($ativos, 'peer_id');
        $this->assertContains('peerA', $peers);
        $this->assertContains('peerB', $peers);
        $this->assertSame(2, $this->model->activeCount($room['id']));

        // Excluindo um peer da listagem
        $semB = $this->model->activeParticipants($room['id'], 'peerB');
        $this->assertNotContains('peerB', array_column($semB, 'peer_id'));
    }

    public function testLeavePresenceRemoveDosAtivos(): void
    {
        $room = $this->novaSala();
        $this->model->joinPresence($room['id'], 'peerA', 'Alice');
        $this->model->leavePresence($room['id'], 'peerA');

        $ativos = $this->model->activeParticipants($room['id']);
        $this->assertNotContains('peerA', array_column($ativos, 'peer_id'));
    }

    public function testJoinPresenceEhUpsertPorRoomEPeer(): void
    {
        $room = $this->novaSala();
        $id1 = $this->model->joinPresence($room['id'], 'peerA', 'Alice');
        // Reentrada do mesmo peer atualiza (não duplica) — UNIQUE(room_id,peer_id)
        $this->model->joinPresence($room['id'], 'peerA', 'Alice 2');
        $rows = $this->db->fetchAll(
            "SELECT * FROM video_room_participants WHERE room_id = ? AND peer_id = ?",
            [$room['id'], 'peerA']
        );
        $this->assertCount(1, $rows);
        $this->assertSame('Alice 2', $rows[0]['display_name']);
    }

    public function testActiveByBrowserDetectaSessaoDuplicada(): void
    {
        $room = $this->novaSala();
        $this->model->joinPresence($room['id'], 'peerA', 'Alice', null, 'participant', 'browser-1');
        // Outra guia (mesmo browser-1), peer diferente
        $dupes = $this->model->activeByBrowser($room['id'], 'browser-1', 'peerB');
        $this->assertNotEmpty($dupes);
        $this->assertSame('peerA', $dupes[0]['peer_id']);
        // Excluindo o próprio peer não acusa duplicata
        $none = $this->model->activeByBrowser($room['id'], 'browser-1', 'peerA');
        $this->assertEmpty($none);
    }

    // ================= Sinalização =================

    public function testPushEPullSignalDirecionado(): void
    {
        $room = $this->novaSala();
        $this->model->pushSignal($room['id'], 'peerA', 'peerB', 'offer', ['sdp' => 'x']);

        // peerB recebe o sinal direcionado a ele
        $sinais = $this->model->pullSignals($room['id'], 'peerB');
        $this->assertNotEmpty($sinais);
        $this->assertSame('offer', $sinais[0]['kind']);

        // Uma segunda leitura não reentrega (marcado como delivered)
        $sinais2 = $this->model->pullSignals($room['id'], 'peerB');
        $this->assertEmpty($sinais2);
    }

    public function testBroadcastNaoVoltaParaOProprioRemetente(): void
    {
        $room = $this->novaSala();
        $this->model->pushSignal($room['id'], 'peerA', null, 'join', ['name' => 'Alice']);

        // O próprio remetente não recebe seu broadcast
        $paraA = $this->model->pullSignals($room['id'], 'peerA');
        $this->assertEmpty($paraA);
        // Outro peer recebe
        $paraB = $this->model->pullSignals($room['id'], 'peerB');
        $this->assertNotEmpty($paraB);
        $this->assertSame('join', $paraB[0]['kind']);
    }

    public function testSinalEfemeroAntigoNaoEhEntregue(): void
    {
        $room = $this->novaSala();
        // Insere uma reação com created_at "velho" (fora da janela de 8s)
        $this->db->insert('video_room_signals', [
            'room_id' => $room['id'],
            'from_peer_id' => 'peerA',
            'to_peer_id' => null,
            'kind' => 'reaction',
            'payload_json' => json_encode(['emoji' => '👍']),
            'created_at' => date('Y-m-d H:i:s', time() - 60),
        ]);
        // Reação antiga não é entregue a quem entrou depois
        $sinais = $this->model->pullSignals($room['id'], 'peerB');
        $kinds = array_column($sinais, 'kind');
        $this->assertNotContains('reaction', $kinds);
    }

    // ================= Gravações =================

    public function testAddListEFindRecording(): void
    {
        $room = $this->novaSala();
        $recToken = $this->model->addRecording([
            'room_id' => $room['id'],
            'file_path' => 'recordings/rec_teste.webm',
            'mime_type' => 'video/webm',
            'duration_sec' => 120,
            'recorded_by' => $this->ownerId,
            'recorded_by_name' => 'Dono',
        ]);
        $this->assertNotEmpty($recToken);

        $rec = $this->model->findRecordingByToken($recToken);
        $this->assertSame((int) $room['id'], (int) $rec['room_id']);
        $this->assertSame(120, (int) $rec['duration_sec']);

        $lista = $this->model->listRecordings($room['id']);
        $tokens = array_column($lista, 'token');
        $this->assertContains($recToken, $tokens);
    }

    public function testUpdateRecordingDuracao(): void
    {
        $room = $this->novaSala();
        $recToken = $this->model->addRecording([
            'room_id' => $room['id'],
            'file_path' => 'recordings/rec_dur.webm',
            'mime_type' => 'video/webm',
        ]);
        $this->model->updateRecording($recToken, ['duration_sec' => 300]);
        $rec = $this->model->findRecordingByToken($recToken);
        $this->assertSame(300, (int) $rec['duration_sec']);
    }

    // ================= Administradores =================

    public function testSetEGetAdminsEIsAdminUser(): void
    {
        $room = $this->novaSala(['visibility' => 'private']);
        $this->model->setAdmins($room['id'], [$this->ownerId, $this->otherId]);

        $adminIds = $this->model->getAdminIds($room['id']);
        $this->assertContains($this->ownerId, $adminIds);
        $this->assertContains($this->otherId, $adminIds);

        // O criador é sempre admin (mesmo sem estar na lista explícita)
        $this->assertTrue($this->model->isAdminUser($room, $this->ownerId));
        $this->assertTrue($this->model->isAdminUser($room, $this->otherId));
        // Um terceiro qualquer não é admin
        $this->assertFalse($this->model->isAdminUser($room, 999999));
    }

    public function testCriadorEhAdminMesmoSemSetAdmins(): void
    {
        $room = $this->novaSala();
        $this->assertTrue($this->model->isAdminUser($room, $this->ownerId));
    }

    // ================= Pedidos de entrada (sala privada) =================

    public function testFluxoDePedidoDeEntrada(): void
    {
        $room = $this->novaSala(['visibility' => 'private']);
        $this->model->requestJoin($room['id'], 'peerX', 'Convidado', null);

        $this->assertSame('pending', $this->model->getRequestStatus($room['id'], 'peerX'));
        $pend = $this->model->pendingRequests($room['id']);
        $this->assertSame('peerX', $pend[0]['peer_id']);

        // Admin admite
        $this->model->decideRequest($room['id'], 'peerX', 'admitted', $this->ownerId);
        $this->assertSame('admitted', $this->model->getRequestStatus($room['id'], 'peerX'));
        // Não fica mais pendente
        $this->assertEmpty($this->model->pendingRequests($room['id']));
    }

    public function testDecideRequestRejeitaStatusInvalido(): void
    {
        $room = $this->novaSala(['visibility' => 'private']);
        $this->model->requestJoin($room['id'], 'peerY', 'Convidado', null);
        // Status fora de admitted/denied não altera nada
        $ret = $this->model->decideRequest($room['id'], 'peerY', 'qualquer', $this->ownerId);
        $this->assertSame(0, $ret);
        $this->assertSame('pending', $this->model->getRequestStatus($room['id'], 'peerY'));
    }
}
