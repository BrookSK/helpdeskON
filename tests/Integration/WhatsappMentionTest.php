<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use WhatsappContact;
use WhatsappMessage;
use Database;

/**
 * Integração das menções (@) em grupos contra o banco helpdesk_on_test:
 *  - flagOwnerMention marca a mensagem e conta UMA vez em mention_count
 *    (o webhook pode reenviar a mesma mensagem);
 *  - abrir a conversa (clearUnread) e clearMentions zeram o "@";
 *  - getAll entrega mention_count para a lista do chat;
 *  - getUnreadMentionNotifications traz a última menção por grupo, só das
 *    instâncias pedidas e só enquanto não vista;
 *  - nomes do autocomplete: histórico do grupo e contatos salvos (com/sem 9º dígito).
 *
 * Cria os próprios dados e limpa no tearDown.
 */
final class WhatsappMentionTest extends TestCase
{
    private Database $db;
    private WhatsappContact $contacts;
    private WhatsappMessage $messages;
    private int $instanceA;
    private int $instanceB;
    /** @var int[] */
    private array $contactIds = [];

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste (helpdesk_on_test) não configurado.');
        }
        $this->db = Database::getInstance();
        $col = $this->db->fetch(
            "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = 'whatsapp_contacts' AND COLUMN_NAME = 'mention_count'"
        );
        if (!$col) {
            $this->fail('Migration 172 ausente no banco de teste: rode tests/setup_test_db.php.');
        }

        $this->contacts = new WhatsappContact();
        $this->messages = new WhatsappMessage();
        $u = uniqid();
        $this->instanceA = (int) $this->db->insert('whatsapp_instances', [
            'instance_name' => "menA_{$u}", 'api_url' => 'http://localhost', 'api_key' => 'k',
            'owner_phone' => '5517988093160', 'owner_lid' => '156079530967127@lid',
        ]);
        $this->instanceB = (int) $this->db->insert('whatsapp_instances', [
            'instance_name' => "menB_{$u}", 'api_url' => 'http://localhost', 'api_key' => 'k',
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->contactIds as $cid) {
            try { $this->db->delete('whatsapp_messages', 'contact_id = ?', [$cid]); } catch (\Throwable $e) {}
            try { $this->db->delete('whatsapp_contacts', 'id = ?', [$cid]); } catch (\Throwable $e) {}
        }
        foreach ([$this->instanceA ?? 0, $this->instanceB ?? 0] as $iid) {
            if ($iid) try { $this->db->delete('whatsapp_instances', 'id = ?', [$iid]); } catch (\Throwable $e) {}
        }
    }

    private function grupo(int $instanceId, string $nome): int
    {
        $u = preg_replace('/\D/', '', uniqid('', true));
        $id = (int) $this->db->insert('whatsapp_contacts', [
            'instance_id' => $instanceId,
            'remote_jid' => '120363' . substr($u . '000000000000', 0, 12) . '@g.us',
            'phone' => '120363' . substr($u . '000000000000', 0, 12),
            'contact_name' => $nome,
            'is_group' => 1,
        ]);
        $this->contactIds[] = $id;
        return $id;
    }

    private function contato(int $instanceId, string $phone, ?string $nome, ?string $push = null): int
    {
        $id = (int) $this->db->insert('whatsapp_contacts', [
            'instance_id' => $instanceId,
            'remote_jid' => $phone . '_' . uniqid() . '@s.whatsapp.net',
            'phone' => $phone,
            'contact_name' => $nome,
            'push_name' => $push,
            'is_group' => 0,
        ]);
        $this->contactIds[] = $id;
        return $id;
    }

    private function msg(int $instanceId, int $contactId, string $texto, string $participant, string $sender, int $fromMe = 0): int
    {
        return (int) $this->messages->create([
            'instance_id' => $instanceId,
            'contact_id' => $contactId,
            'remote_jid' => 'x@g.us',
            'message_id' => 'MEN' . strtoupper(bin2hex(random_bytes(8))),
            'from_me' => $fromMe,
            'message_type' => 'text',
            'message_text' => $texto,
            'sender_name' => $sender,
            'participant_jid' => $participant,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    private function mentionCount(int $contactId): int
    {
        return (int) $this->db->fetch("SELECT mention_count FROM whatsapp_contacts WHERE id = ?", [$contactId])['mention_count'];
    }

    public function testMarcaMencaoUmaVezSo(): void
    {
        $g = $this->grupo($this->instanceA, 'Grupo Menção');
        $m = $this->msg($this->instanceA, $g, '@156079530967127 consegue ver?', '58935558037626@lid', 'Lucas');

        $this->assertTrue($this->messages->flagOwnerMention($m, $g));
        $this->assertSame(1, $this->mentionCount($g));
        $this->assertSame(1, (int) $this->messages->findById($m)['mentions_me']);

        // Reenvio do webhook (mesma mensagem): não conta de novo
        $this->assertFalse($this->messages->flagOwnerMention($m, $g));
        $this->assertSame(1, $this->mentionCount($g));

        // Segunda menção (outra mensagem) soma
        $m2 = $this->msg($this->instanceA, $g, 'de novo @156079530967127', '58935558037626@lid', 'Lucas');
        $this->assertTrue($this->messages->flagOwnerMention($m2, $g));
        $this->assertSame(2, $this->mentionCount($g));
    }

    public function testNaoMarcaMensagemDeOutraConversa(): void
    {
        $g1 = $this->grupo($this->instanceA, 'G1');
        $g2 = $this->grupo($this->instanceA, 'G2');
        $m = $this->msg($this->instanceA, $g1, '@x', '1@lid', 'A');

        // contact_id errado não marca nada
        $this->assertFalse($this->messages->flagOwnerMention($m, $g2));
        $this->assertSame(0, $this->mentionCount($g1));
        $this->assertSame(0, $this->mentionCount($g2));
    }

    public function testAbrirConversaZeraMencoesENaoLidas(): void
    {
        $g = $this->grupo($this->instanceA, 'Grupo Abrir');
        $m = $this->msg($this->instanceA, $g, '@156079530967127', '1@lid', 'A');
        $this->messages->flagOwnerMention($m, $g);
        $this->contacts->incrementUnread($g);

        $this->contacts->clearUnread($g);
        $row = $this->contacts->findById($g);
        $this->assertSame(0, (int) $row['mention_count']);
        $this->assertSame(0, (int) $row['unread_count']);
    }

    public function testClearMentionsNaoMexeNasNaoLidas(): void
    {
        $g = $this->grupo($this->instanceA, 'Grupo Aberto');
        $m = $this->msg($this->instanceA, $g, '@156079530967127', '1@lid', 'A');
        $this->messages->flagOwnerMention($m, $g);
        $this->contacts->incrementUnread($g);

        $this->contacts->clearMentions($g);
        $row = $this->contacts->findById($g);
        $this->assertSame(0, (int) $row['mention_count']);
        $this->assertSame(1, (int) $row['unread_count']);
    }

    public function testListaDoChatEntregaMentionCount(): void
    {
        $g = $this->grupo($this->instanceA, 'Grupo Lista');
        $outro = $this->grupo($this->instanceA, 'Grupo Sem Menção');
        $m = $this->msg($this->instanceA, $g, '@156079530967127', '1@lid', 'A');
        $this->messages->flagOwnerMention($m, $g);

        $rows = array_column($this->contacts->getAll([$this->instanceA], [], 'groups'), null, 'id');
        $this->assertSame(1, (int) $rows[$g]['mention_count']);
        $this->assertSame(0, (int) $rows[$outro]['mention_count']);
    }

    public function testNotificacoesDeMencao(): void
    {
        $gA = $this->grupo($this->instanceA, 'Grupo A');
        $gB = $this->grupo($this->instanceB, 'Grupo B');
        $gVisto = $this->grupo($this->instanceA, 'Grupo Já Visto');

        $m1 = $this->msg($this->instanceA, $gA, 'primeira @156079530967127', '1@lid', 'Ana');
        $this->messages->flagOwnerMention($m1, $gA);
        $this->msg($this->instanceA, $gA, 'mensagem comum depois', '2@lid', 'Bia');
        $m2 = $this->msg($this->instanceA, $gA, 'segunda @156079530967127', '3@lid', 'Caio');
        $this->messages->flagOwnerMention($m2, $gA);

        $mb = $this->msg($this->instanceB, $gB, '@x', '4@lid', 'Davi');
        $this->messages->flagOwnerMention($mb, $gB);

        $mv = $this->msg($this->instanceA, $gVisto, '@156079530967127', '5@lid', 'Eva');
        $this->messages->flagOwnerMention($mv, $gVisto);
        $this->contacts->clearUnread($gVisto); // alguém abriu o grupo

        $rows = $this->contacts->getUnreadMentionNotifications([$this->instanceA]);
        $byContact = array_column($rows, null, 'contact_id');

        $this->assertArrayHasKey($gA, $byContact);
        $this->assertArrayNotHasKey($gB, $byContact, 'outra instância não entra');
        $this->assertArrayNotHasKey($gVisto, $byContact, 'menção já vista não avisa');
        // Traz a menção mais recente do grupo, não a mensagem comum
        $this->assertSame('Caio', $byContact[$gA]['sender_name']);
        $this->assertSame('segunda @156079530967127', $byContact[$gA]['message_text']);
        $this->assertSame(2, (int) $byContact[$gA]['mention_count']);
        $this->assertSame('Grupo A', $byContact[$gA]['contact_name']);

        $this->assertSame([], $this->contacts->getUnreadMentionNotifications([]));
    }

    public function testNomesParaOAutocomplete(): void
    {
        $g = $this->grupo($this->instanceA, 'Grupo Nomes');
        $this->msg($this->instanceA, $g, 'oi', '58935558037626@lid', 'Lucas Vacari');
        $this->msg($this->instanceA, $g, 'enviada por nós', '156079530967127@lid', 'Você', 1);
        // Contato salvo SEM o 9º dígito; participante vem COM o 9
        $this->contato($this->instanceA, '551791253062', 'Ana Cliente');
        $this->contato($this->instanceB, '5521912340002', null, 'Push Bia');

        $maps = $this->contacts->getMentionNameMaps($g, ['5517991253062', '5521912340002', '5599900000000']);

        $this->assertSame('Lucas Vacari', $maps['by_jid']['58935558037626@lid']);
        $this->assertArrayNotHasKey('156079530967127@lid', $maps['by_jid'], 'mensagens nossas não dão nome');
        $this->assertSame('Ana Cliente', $maps['by_phone_key']['551791253062']);
        $this->assertSame('Push Bia', $maps['by_phone_key']['552112340002']);
        $this->assertArrayNotHasKey('559900000000', $maps['by_phone_key']);

        $local = $this->contacts->getLocalGroupParticipants($g);
        $this->assertSame([['id' => '58935558037626@lid', 'name' => 'Lucas Vacari']], $local);
    }
}
