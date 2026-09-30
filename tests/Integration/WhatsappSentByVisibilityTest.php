<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use WhatsappContact;
use WhatsappMessage;
use Database;

/**
 * Testes de integração da visibilidade do chat por PESSOA (Opção B) contra o
 * banco helpdesk_on_test.
 *
 * Regra implementada: uma conversa aparece para um usuário quando ela está
 * atribuída a ele (whatsapp_contacts.assigned_to) OU quando ele foi o remetente
 * de alguma mensagem dela (whatsapp_messages.sent_by). Isso espelha o e-mail
 * ("vejo o que enviei") e permite que mais de uma pessoa opere a MESMA instância
 * e cada uma enxergue as conversas que efetivamente disparou.
 *
 * Cria os próprios dados (usuários, instância, contatos, mensagens) e limpa tudo
 * no tearDown; não depende de nada pré-existente no banco.
 */
final class WhatsappSentByVisibilityTest extends TestCase
{
    private Database $db;
    private WhatsappContact $contacts;
    private WhatsappMessage $messages;

    private int $senderId;   // quem "envia" (ex.: Fernanda)
    private int $otherId;    // um terceiro que não participou
    private int $instanceId;

    /** @var int[] */
    private array $contactIds = [];

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste (helpdesk_on_test) não configurado.');
        }
        // A coluna sent_by (migration 139) é obrigatória para este comportamento.
        $hasSentBy = (bool) Database::getInstance()->fetch(
            "SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'whatsapp_messages' AND COLUMN_NAME = 'sent_by'"
        );
        if (!$hasSentBy) {
            $this->markTestSkipped('Coluna whatsapp_messages.sent_by ausente: rode a migration 139 no banco de teste.');
        }

        $this->db = Database::getInstance();
        $this->contacts = new WhatsappContact();
        $this->messages = new WhatsappMessage();

        $u = uniqid();
        $this->senderId = (int) $this->db->insert('users', [
            'name' => "Fernanda {$u}", 'email' => "fer_{$u}@example.test",
            'password' => password_hash('x', PASSWORD_BCRYPT), 'role' => 'comercial',
        ]);
        $this->otherId = (int) $this->db->insert('users', [
            'name' => "Outro {$u}", 'email' => "out_{$u}@example.test",
            'password' => password_hash('x', PASSWORD_BCRYPT), 'role' => 'comercial',
        ]);
        $this->instanceId = (int) $this->db->insert('whatsapp_instances', [
            'instance_name' => "visinst_{$u}", 'api_url' => 'http://localhost', 'api_key' => 'k',
            'user_id' => $this->senderId,
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->contactIds as $cid) {
            try { $this->db->delete('whatsapp_messages', 'contact_id = ?', [$cid]); } catch (\Throwable $e) {}
            try { $this->db->delete('whatsapp_contacts', 'id = ?', [$cid]); } catch (\Throwable $e) {}
        }
        try { $this->db->delete('whatsapp_instances', 'id = ?', [$this->instanceId]); } catch (\Throwable $e) {}
        foreach ([$this->senderId, $this->otherId] as $uid) {
            try { $this->db->delete('users', 'id = ?', [$uid]); } catch (\Throwable $e) {}
        }
    }

    /** Cria um contato "conversável" (telefone + JID real) para aparecer no chat. */
    private function novoContato(?int $assignedTo = null): int
    {
        $u = uniqid();
        $id = (int) $this->db->insert('whatsapp_contacts', [
            'instance_id' => $this->instanceId,
            'remote_jid' => "5511{$u}@s.whatsapp.net",
            'phone' => '5511' . substr(preg_replace('/\D/', '', $u . '0000000'), 0, 9),
            'contact_name' => "Lead {$u}",
            'assigned_to' => $assignedTo,
            'is_group' => 0,
        ]);
        $this->contactIds[] = $id;
        return $id;
    }

    private function gravaEnvio(int $contactId, ?int $sentBy): void
    {
        $this->messages->create([
            'instance_id' => $this->instanceId,
            'contact_id' => $contactId,
            'remote_jid' => "5511{$contactId}@s.whatsapp.net",
            'message_id' => 'WA_' . uniqid(),
            'from_me' => 1,
            'message_type' => 'text',
            'message_text' => 'olá',
            'sent_by' => $sentBy,
            'timestamp' => date('Y-m-d H:i:s'),
            'is_read' => 1,
        ]);
    }

    /** Extrai os ids retornados por getAll (lista do chat). */
    private function idsVisiveis(int $userId): array
    {
        $rows = $this->contacts->getAll(
            [$this->instanceId],
            ['assigned_to' => $userId],
            'contacts'
        );
        return array_map(fn($r) => (int) $r['id'], $rows);
    }

    // ================= sent_by é persistido =================

    public function testSentByEhPersistido(): void
    {
        $cid = $this->novoContato();
        $this->gravaEnvio($cid, $this->senderId);

        $row = $this->db->fetch(
            "SELECT sent_by FROM whatsapp_messages WHERE contact_id = ? AND from_me = 1 LIMIT 1",
            [$cid]
        );
        $this->assertSame($this->senderId, (int) $row['sent_by']);
    }

    // ================= Visibilidade por sent_by =================

    public function testRemetenteVeConversaMesmoSemEstarAtribuido(): void
    {
        // Cenário Fernanda: a conversa NÃO está atribuída a ela (assigned_to NULL),
        // mas ela foi quem enviou (sent_by). Deve aparecer na lista dela.
        $cid = $this->novoContato(null);
        $this->gravaEnvio($cid, $this->senderId);

        $this->assertContains($cid, $this->idsVisiveis($this->senderId));
    }

    public function testTerceiroNaoVeConversaQueNaoEnviouNemAtende(): void
    {
        // A mesma conversa não pode aparecer para um terceiro que não a atende
        // nem enviou nada nela.
        $cid = $this->novoContato(null);
        $this->gravaEnvio($cid, $this->senderId);

        $this->assertNotContains($cid, $this->idsVisiveis($this->otherId));
    }

    public function testAtribuicaoContinuaValendoSemSentBy(): void
    {
        // Regressão: quem é responsável (assigned_to) continua vendo a conversa
        // mesmo sem ter enviado nada por ela.
        $cid = $this->novoContato($this->otherId);
        // sem gravar envio de ninguém

        $this->assertContains($cid, $this->idsVisiveis($this->otherId));
        $this->assertNotContains($cid, $this->idsVisiveis($this->senderId));
    }

    public function testAmbosVeem_quandoUmAtendeEOutroEnviou(): void
    {
        // Contato atribuído ao "outro" (atendimento) mas com um envio da Fernanda:
        // os DOIS devem enxergar a conversa (cada um pelo seu eixo).
        $cid = $this->novoContato($this->otherId);
        $this->gravaEnvio($cid, $this->senderId);

        $this->assertContains($cid, $this->idsVisiveis($this->otherId), 'responsável deve ver');
        $this->assertContains($cid, $this->idsVisiveis($this->senderId), 'remetente deve ver');
    }

    // ================= Regressão: ramo 'unassigned' intocado =================

    public function testFiltroUnassignedNaoConsideraSentBy(): void
    {
        // O ramo 'unassigned' (usado pelo super_admin para ver órfãos) NÃO deve
        // ser afetado pela mudança: só lista contatos sem dono. Uma conversa com
        // envio da Fernanda mas SEM assigned_to é "unassigned" e deve aparecer;
        // uma conversa COM dono não deve aparecer em 'unassigned', mesmo que a
        // Fernanda tenha enviado nela.
        $orfao = $this->novoContato(null);
        $this->gravaEnvio($orfao, $this->senderId);

        $comDono = $this->novoContato($this->otherId);
        $this->gravaEnvio($comDono, $this->senderId);

        $rows = $this->contacts->getAll(
            [$this->instanceId],
            ['assigned_to' => 'unassigned'],
            'contacts'
        );
        $ids = array_map(fn($r) => (int) $r['id'], $rows);

        $this->assertContains($orfao, $ids, 'contato sem dono deve aparecer em unassigned');
        $this->assertNotContains($comDono, $ids, 'contato com dono não é unassigned');
    }
}
