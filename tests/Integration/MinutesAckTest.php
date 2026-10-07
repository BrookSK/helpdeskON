<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use VideoRoom;
use MinutesAckRules;
use Database;

/**
 * Testes de integração do reconhecimento/assinatura da minuta pelo cliente
 * (VideoRoom::acknowledgeMinutes / contestMinutes) contra helpdesk_on_test.
 *
 * Cria seus próprios dados (sala + gravação) no setUp e limpa no tearDown.
 */
final class MinutesAckTest extends TestCase
{
    private Database $db;
    private VideoRoom $model;
    private int $ownerId;
    private int $clientId;
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
        $this->ownerId  = (int) $this->db->insert('users', [
            'name' => "Dono {$u}", 'email' => "dono_{$u}@example.test",
            'password' => password_hash('x', PASSWORD_BCRYPT), 'role' => 'attendant',
        ]);
        $this->clientId = (int) $this->db->insert('users', [
            'name' => "Cliente {$u}", 'email' => "cli_{$u}@example.test",
            'password' => password_hash('x', PASSWORD_BCRYPT), 'role' => 'client',
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->roomIds as $id) {
            try { $this->db->delete('video_recordings', 'room_id = ?', [$id]); } catch (\Throwable $e) {}
            try { $this->db->delete('video_rooms', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
        foreach ([$this->clientId, $this->ownerId] as $id) {
            try { $this->db->delete('users', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
    }

    /**
     * Cria uma gravação com a minuta no estado desejado e devolve o token.
     * $minutesStatus controla se a ata está pronta ('done') ou não.
     */
    private function novaGravacao(string $minutesStatus = 'done'): string
    {
        $token = $this->model->create([
            'title' => 'Reunião teste',
            'created_by' => $this->ownerId,
            'status' => 'active',
            'visibility' => 'public',
            'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
        ]);
        $room = $this->model->findByToken($token);
        $this->roomIds[] = (int) $room['id'];

        $recToken = $this->model->addRecording([
            'room_id' => (int) $room['id'],
            'file_path' => 'recordings/ack_' . uniqid() . '.webm',
            'mime_type' => 'video/webm',
        ]);
        $data = ['minutes_status' => $minutesStatus];
        if ($minutesStatus === 'done') {
            $data['minutes'] = "## Decisões\n- Fechar proposta.";
            $data['minutes_generated_at'] = date('Y-m-d H:i:s');
        }
        $this->model->updateRecording($recToken, $data);
        return $recToken;
    }

    public function testEstadoInicialDoAceiteEhPending(): void
    {
        $recToken = $this->novaGravacao('done');
        $rec = $this->model->findRecordingByToken($recToken);
        $this->assertSame('pending', $rec['minutes_ack_status']);
        $this->assertNull($rec['minutes_ack_by']);
        $this->assertNull($rec['minutes_ack_at']);
    }

    public function testReconhecerMinutaProntaGravaOAceite(): void
    {
        $recToken = $this->novaGravacao('done');

        $ok = $this->model->acknowledgeMinutes($recToken, $this->clientId, '203.0.113.9');
        $this->assertTrue($ok);

        $rec = $this->model->findRecordingByToken($recToken);
        $this->assertSame(MinutesAckRules::STATUS_ACK, $rec['minutes_ack_status']);
        $this->assertSame($this->clientId, (int) $rec['minutes_ack_by']);
        $this->assertNotEmpty($rec['minutes_ack_at']);
        $this->assertSame('203.0.113.9', $rec['minutes_ack_ip']);
    }

    public function testNaoReconheceMinutaNaoPronta(): void
    {
        $recToken = $this->novaGravacao('processing'); // ata ainda não gerada
        $ok = $this->model->acknowledgeMinutes($recToken, $this->clientId, null);
        $this->assertFalse($ok);

        $rec = $this->model->findRecordingByToken($recToken);
        $this->assertSame('pending', $rec['minutes_ack_status']);
    }

    public function testNaoReconheceDuasVezes(): void
    {
        $recToken = $this->novaGravacao('done');
        $this->assertTrue($this->model->acknowledgeMinutes($recToken, $this->clientId, '1.1.1.1'));
        // Segunda tentativa (outro usuário/IP) não pode e não sobrescreve.
        $this->assertFalse($this->model->acknowledgeMinutes($recToken, $this->ownerId, '2.2.2.2'));

        $rec = $this->model->findRecordingByToken($recToken);
        $this->assertSame($this->clientId, (int) $rec['minutes_ack_by']);
        $this->assertSame('1.1.1.1', $rec['minutes_ack_ip']);
    }

    public function testContestarGravaMotivoEStatus(): void
    {
        $recToken = $this->novaGravacao('done');
        $ok = $this->model->contestMinutes($recToken, '  O prazo combinado não ficou na ata  ');
        $this->assertTrue($ok);

        $rec = $this->model->findRecordingByToken($recToken);
        $this->assertSame(MinutesAckRules::STATUS_CONTEST, $rec['minutes_ack_status']);
        $this->assertSame('O prazo combinado não ficou na ata', $rec['minutes_contest_reason']);
    }

    public function testContestarSemMotivoFalha(): void
    {
        $recToken = $this->novaGravacao('done');
        $this->assertFalse($this->model->contestMinutes($recToken, '   '));

        $rec = $this->model->findRecordingByToken($recToken);
        $this->assertSame('pending', $rec['minutes_ack_status']);
    }

    public function testMinutaContestadaPodeSerReconhecidaAposReenvio(): void
    {
        $recToken = $this->novaGravacao('done');
        $this->assertTrue($this->model->contestMinutes($recToken, 'Revisar o escopo do módulo X'));

        // Equipe reenvia (nova ata) — o reset mantém 'contested' até reconhecer;
        // o cliente então reconhece.
        $this->assertTrue($this->model->acknowledgeMinutes($recToken, $this->clientId, '9.9.9.9'));

        $rec = $this->model->findRecordingByToken($recToken);
        $this->assertSame(MinutesAckRules::STATUS_ACK, $rec['minutes_ack_status']);
        // Reconhecer limpa o motivo de contestação anterior.
        $this->assertNull($rec['minutes_contest_reason']);
    }

    public function testReconhecerGravacaoInexistenteFalha(): void
    {
        $this->assertFalse($this->model->acknowledgeMinutes('token-que-nao-existe', $this->clientId, null));
    }
}
