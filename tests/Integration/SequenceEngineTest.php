<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use SequenceEngine;
use ApolloCreditUsage;
use Database;

/**
 * Testes de integração do motor de sequências (SequenceEngine) e do controle
 * de créditos Apollo (ApolloCreditUsage) contra o banco helpdesk_on_test.
 *
 * Cobre: elegibilidade por canal (email/whatsapp/mixed), idempotência do
 * enroll, reinício manual (forceRestart), bloqueio de descadastrado e o
 * check/consume de créditos Apollo (limite diário por usuário).
 */
final class SequenceEngineTest extends TestCase
{
    private Database $db;
    private SequenceEngine $engine;
    private int $userId;
    private int $instanceId;
    /** @var int[] */
    private array $contactIds = [];
    /** @var int[] */
    private array $seqIds = [];

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste (helpdesk_on_test) não configurado.');
        }
        $this->db = Database::getInstance();
        $this->engine = new SequenceEngine();

        $u = uniqid();
        $this->userId = (int) $this->db->insert('users', [
            'name' => "Prosp {$u}", 'email' => "prosp_{$u}@example.test",
            'password' => password_hash('x', PASSWORD_BCRYPT), 'role' => 'comercial',
            'apollo_daily_credits' => 10,
        ]);
        $this->instanceId = (int) $this->db->insert('whatsapp_instances', [
            'instance_name' => "seqinst_{$u}", 'api_url' => 'http://localhost', 'api_key' => 'k',
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->seqIds as $sid) {
            try { $this->db->delete('sequence_participants', 'sequence_id = ?', [$sid]); } catch (\Throwable $e) {}
            try { $this->db->delete('email_sequences', 'id = ?', [$sid]); } catch (\Throwable $e) {}
        }
        foreach ($this->contactIds as $cid) {
            try { $this->db->delete('sequence_participants', 'contact_id = ?', [$cid]); } catch (\Throwable $e) {}
            try { $this->db->delete('lead_timeline', 'contact_id = ?', [$cid]); } catch (\Throwable $e) {}
            try { $this->db->delete('whatsapp_contacts', 'id = ?', [$cid]); } catch (\Throwable $e) {}
        }
        try { $this->db->delete('whatsapp_instances', 'id = ?', [$this->instanceId]); } catch (\Throwable $e) {}
        try { $this->db->delete('apollo_credit_usage', 'user_id = ?', [$this->userId]); } catch (\Throwable $e) {}
        try { $this->db->delete('users', 'id = ?', [$this->userId]); } catch (\Throwable $e) {}
    }

    private function novoContato(array $overrides = []): int
    {
        $u = uniqid();
        $id = (int) $this->db->insert('whatsapp_contacts', array_merge([
            'instance_id' => $this->instanceId,
            'remote_jid' => "seq{$u}@s.whatsapp.net",
            'contact_name' => "Lead {$u}", // sem "Apollo"/"ON Solu" para não mexer em cards
            'is_group' => 0,
        ], $overrides));
        $this->contactIds[] = $id;
        return $id;
    }

    private function novaSequencia(string $channel = 'email'): int
    {
        $graph = [
            'start' => 's1',
            'nodes' => [
                ['id' => 's1', 'type' => 'send', 'data' => ['subject' => 'x', 'body' => 'y'], 'next' => 'e1'],
                ['id' => 'e1', 'type' => 'end'],
            ],
        ];
        $id = (int) $this->db->insert('email_sequences', [
            'name' => 'Seq teste ' . uniqid(),
            'channel_type' => $channel,
            'graph' => json_encode($graph),
            'is_active' => 1,
            'created_by' => $this->userId,
        ]);
        $this->seqIds[] = $id;
        return $id;
    }

    // ================= Elegibilidade por canal =================

    public function testEnrollEmailExigeEmail(): void
    {
        $seq = $this->novaSequencia('email');
        $semEmail = $this->novoContato(['phone' => '5511999990000']);
        $r = $this->engine->enroll($seq, $semEmail, $this->userId);
        $this->assertFalse($r['success']);

        $comEmail = $this->novoContato(['lead_email' => 'lead@example.test']);
        $r2 = $this->engine->enroll($seq, $comEmail, $this->userId);
        $this->assertTrue($r2['success']);
        $this->assertArrayHasKey('participant_id', $r2);
    }

    public function testEnrollWhatsappExigeTelefone(): void
    {
        $seq = $this->novaSequencia('whatsapp');
        $semTel = $this->novoContato(['lead_email' => 'x@example.test']);
        $r = $this->engine->enroll($seq, $semTel, $this->userId);
        $this->assertFalse($r['success']);

        $comTel = $this->novoContato(['phone' => '5511988887777']);
        $r2 = $this->engine->enroll($seq, $comTel, $this->userId);
        $this->assertTrue($r2['success']);
    }

    // ================= Idempotência =================

    public function testEnrollNaoReiniciaQuemJaEstaAtivo(): void
    {
        $seq = $this->novaSequencia('email');
        $contato = $this->novoContato(['lead_email' => 'ativo@example.test']);
        $r1 = $this->engine->enroll($seq, $contato, $this->userId);
        $this->assertTrue($r1['success']);

        // Segundo enroll automático (sem forceRestart) é rejeitado.
        $r2 = $this->engine->enroll($seq, $contato, $this->userId);
        $this->assertFalse($r2['success']);

        $count = (int) $this->db->fetch(
            "SELECT COUNT(*) c FROM sequence_participants WHERE sequence_id = ? AND contact_id = ?",
            [$seq, $contato]
        )['c'];
        $this->assertSame(1, $count);
    }

    public function testForceRestartReiniciaParticipante(): void
    {
        $seq = $this->novaSequencia('email');
        $contato = $this->novoContato(['lead_email' => 'restart@example.test']);
        $r1 = $this->engine->enroll($seq, $contato, $this->userId);
        $pid = $r1['participant_id'];

        // Avança/altera o participante manualmente
        $this->db->update('sequence_participants',
            ['status' => 'finished', 'current_node' => 'e1'], 'id = ?', [$pid]);

        // forceRestart reinicia (mesmo id, volta a active e current_node null)
        $r2 = $this->engine->enroll($seq, $contato, $this->userId, true);
        $this->assertTrue($r2['success']);
        $this->assertSame((int) $pid, (int) $r2['participant_id']);

        $p = $this->db->fetch("SELECT * FROM sequence_participants WHERE id = ?", [$pid]);
        $this->assertSame('active', $p['status']);
        $this->assertNull($p['current_node']);
    }

    // ================= Descadastrado =================

    public function testEnrollBloqueiaDescadastradoSalvoForceRestart(): void
    {
        $seq = $this->novaSequencia('email');
        $contato = $this->novoContato(['lead_email' => 'unsub@example.test', 'unsubscribed' => 1]);

        $r = $this->engine->enroll($seq, $contato, $this->userId);
        $this->assertFalse($r['success']);

        // forceRestart reativa o contato e inscreve
        $r2 = $this->engine->enroll($seq, $contato, $this->userId, true);
        $this->assertTrue($r2['success']);
        $reativado = $this->db->fetch("SELECT unsubscribed FROM whatsapp_contacts WHERE id = ?", [$contato]);
        $this->assertSame(0, (int) $reativado['unsubscribed']);
    }

    // ================= createSimpleFollowUp =================

    public function testCreateSimpleFollowUpCriaSequenciaEInscreve(): void
    {
        $contato = $this->novoContato(['lead_email' => 'followup@example.test']);
        $r = $this->engine->createSimpleFollowUp($contato, 2, 'days', 'Assunto', 'Corpo', $this->userId);
        $this->assertTrue($r['success']);
        // Limpa a sequência gerada
        $p = $this->db->fetch("SELECT sequence_id FROM sequence_participants WHERE id = ?", [$r['participant_id']]);
        if ($p) $this->seqIds[] = (int) $p['sequence_id'];
    }

    // ================= ApolloCreditUsage =================

    public function testApolloCheckRespeitaLimiteDiario(): void
    {
        $usage = new ApolloCreditUsage();
        $user = ['id' => $this->userId, 'apollo_daily_credits' => 10];

        $this->assertSame(0, $usage->usedToday($this->userId));
        $chk = $usage->check($user, ApolloCreditUsage::COST_EMAIL);
        $this->assertTrue($chk['allowed']);
        $this->assertSame(10, $chk['limit']);

        // Consome 8 (mobile) -> restam 2
        $usage->consume($this->userId, ApolloCreditUsage::COST_MOBILE);
        $this->assertSame(8, $usage->usedToday($this->userId));

        // Ainda cabe +1 e-mail (8+1<=10), mas não outro mobile (8+8>10)
        $this->assertTrue($usage->check($user, ApolloCreditUsage::COST_EMAIL)['allowed']);
        $this->assertFalse($usage->check($user, ApolloCreditUsage::COST_MOBILE)['allowed']);
    }

    public function testApolloLimiteZeroEhIlimitado(): void
    {
        $usage = new ApolloCreditUsage();
        $user = ['id' => $this->userId, 'apollo_daily_credits' => 0];
        $chk = $usage->check($user, ApolloCreditUsage::COST_MOBILE);
        $this->assertTrue($chk['allowed']);
        $this->assertSame(-1, $chk['remaining']);
    }

    public function testApolloConsumeAcumulaNoMesmoDia(): void
    {
        $usage = new ApolloCreditUsage();
        $usage->consume($this->userId, 1);
        $usage->consume($this->userId, 3);
        $this->assertSame(4, $usage->usedToday($this->userId));
    }
}
