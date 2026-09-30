<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use WhatsappContact;
use Database;

/**
 * Testes de integração da propagação de bloqueio entre CONTATOS DUPLICADOS
 * ("irmãos") — WhatsappContact::findSiblings / unsubscribeWithSiblings — contra
 * o banco helpdesk_on_test.
 *
 * Regra: ao bloquear um lead (unsubscribed=1), os irmãos (mesma pessoa por
 * e-mail ou LinkedIn) também são bloqueados. Contatos sem chave forte em comum
 * NÃO são afetados. Telefone não entra na detecção de irmãos.
 */
final class WhatsappContactSiblingsTest extends TestCase
{
    private Database $db;
    private WhatsappContact $model;
    private int $instanceId;
    /** @var int[] */
    private array $contactIds = [];
    private bool $hasLinkedin = false;

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste (helpdesk_on_test) não configurado.');
        }
        $this->db = Database::getInstance();
        $this->model = new WhatsappContact();

        $u = uniqid();
        $this->instanceId = (int) $this->db->insert('whatsapp_instances', [
            'instance_name' => "inst_{$u}", 'api_url' => 'http://localhost', 'api_key' => 'k',
        ]);

        // A coluna linkedin_url pode não existir (migration 080). Detecta.
        try {
            $r = $this->db->fetch(
                "SELECT COUNT(*) c FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'whatsapp_contacts' AND COLUMN_NAME = 'linkedin_url'"
            );
            $this->hasLinkedin = ((int) ($r['c'] ?? 0) > 0);
        } catch (\Throwable $e) {
            $this->hasLinkedin = false;
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->contactIds as $id) {
            try { $this->db->delete('lead_timeline', 'contact_id = ?', [$id]); } catch (\Throwable $e) {}
            try { $this->db->delete('whatsapp_contacts', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
        try { $this->db->delete('whatsapp_instances', 'id = ?', [$this->instanceId]); } catch (\Throwable $e) {}
    }

    private function novoContato(array $overrides = []): int
    {
        $u = uniqid();
        $data = array_merge([
            'instance_id' => $this->instanceId,
            'remote_jid' => "{$u}@s.whatsapp.net",
            'contact_name' => "Lead {$u}",
            'unsubscribed' => 0,
            'is_group' => 0,
        ], $overrides);
        $id = (int) $this->db->insert('whatsapp_contacts', $data);
        $this->contactIds[] = $id;
        return $id;
    }

    private function isUnsub(int $id): bool
    {
        $r = $this->db->fetch("SELECT unsubscribed FROM whatsapp_contacts WHERE id = ?", [$id]);
        return (int) ($r['unsubscribed'] ?? 0) === 1;
    }

    public function testBloqueiaContatoEIrmaoPorEmail(): void
    {
        $email = 'dup_' . uniqid() . '@example.test';
        $a = $this->novoContato(['lead_email' => $email]);
        $b = $this->novoContato(['lead_email' => strtoupper($email)]); // mesma pessoa (caixa difere)
        $outro = $this->novoContato(['lead_email' => 'outro_' . uniqid() . '@example.test']);

        $blocked = $this->model->unsubscribeWithSiblings($a, 'Sem interesse');

        $this->assertGreaterThanOrEqual(2, $blocked, 'Deve bloquear o contato e o irmão.');
        $this->assertTrue($this->isUnsub($a), 'O próprio contato deve ficar bloqueado.');
        $this->assertTrue($this->isUnsub($b), 'O irmão por e-mail deve ficar bloqueado.');
        $this->assertFalse($this->isUnsub($outro), 'Um contato não-irmão NÃO pode ser bloqueado.');
    }

    public function testBloqueiaIrmaoPorLinkedin(): void
    {
        if (!$this->hasLinkedin) {
            $this->markTestSkipped('Coluna linkedin_url não existe neste banco.');
        }
        $li = 'https://linkedin.com/in/dup-' . uniqid();
        $a = $this->novoContato(['linkedin_url' => $li]);
        $b = $this->novoContato(['linkedin_url' => $li]);

        $this->model->unsubscribeWithSiblings($a, 'Sem resposta');

        $this->assertTrue($this->isUnsub($a));
        $this->assertTrue($this->isUnsub($b), 'O irmão por LinkedIn deve ficar bloqueado.');
    }

    public function testNaoBloqueiaSemChaveForteEmComum(): void
    {
        // Dois contatos sem e-mail e sem LinkedIn: não são irmãos.
        $a = $this->novoContato(['phone' => '5511999000001']);
        $b = $this->novoContato(['phone' => '5511999000002']);

        $this->model->unsubscribeWithSiblings($a, 'Sem interesse');

        $this->assertTrue($this->isUnsub($a));
        $this->assertFalse($this->isUnsub($b), 'Sem chave forte em comum, o outro não é irmão.');
    }

    public function testTelefoneIgualNaoTornaIrmao(): void
    {
        // Mesmo telefone, mas SEM e-mail/LinkedIn: por decisão de projeto, telefone
        // não é usado para propagar bloqueio (evita falso positivo).
        $a = $this->novoContato(['phone' => '5511988887777']);
        $b = $this->novoContato(['phone' => '5511988887777']);

        $this->model->unsubscribeWithSiblings($a, 'Sem interesse');

        $this->assertTrue($this->isUnsub($a));
        $this->assertFalse($this->isUnsub($b), 'Telefone igual NÃO deve propagar bloqueio.');
    }

    public function testIdempotente(): void
    {
        $email = 'dup_' . uniqid() . '@example.test';
        $a = $this->novoContato(['lead_email' => $email]);
        $b = $this->novoContato(['lead_email' => $email]);

        $this->model->unsubscribeWithSiblings($a, 'Sem interesse');
        // Segunda chamada: todos já bloqueados, não deve bloquear ninguém novo.
        $blockedAgain = $this->model->unsubscribeWithSiblings($a, 'Sem interesse');

        $this->assertSame(0, $blockedAgain, 'Segunda chamada não deve rebloquear.');
        $this->assertTrue($this->isUnsub($a));
        $this->assertTrue($this->isUnsub($b));
    }

    public function testRegistraNotaNaTimeline(): void
    {
        $email = 'dup_' . uniqid() . '@example.test';
        $a = $this->novoContato(['lead_email' => $email]);
        $b = $this->novoContato(['lead_email' => $email]);

        $this->model->unsubscribeWithSiblings($a, 'Sem interesse');

        $notaIrmao = $this->db->fetch(
            "SELECT COUNT(*) c FROM lead_timeline WHERE contact_id = ? AND event_type = 'note'",
            [$b]
        );
        $this->assertGreaterThanOrEqual(1, (int) ($notaIrmao['c'] ?? 0), 'O irmão deve ter nota na timeline.');
    }
}
