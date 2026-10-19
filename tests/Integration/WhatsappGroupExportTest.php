<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use WhatsappContact;
use WhatsappGroupExportRules;
use Database;

/**
 * Integração das consultas locais usadas na exportação dos contatos dos grupos
 * (WhatsappContact::getExportContactsByPhoneKey / getGroupSenderNames /
 * getLocalGroupsWithSenders) contra o banco helpdesk_on_test.
 */
final class WhatsappGroupExportTest extends TestCase
{
    private Database $db;
    private WhatsappContact $model;
    private int $instanceId;
    private int $otherInstanceId;
    /** @var int[] */
    private array $contactIds = [];
    private string $u;

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste (helpdesk_on_test) não configurado.');
        }
        $this->db = Database::getInstance();
        $this->model = new WhatsappContact();
        $this->u = substr(str_replace('.', '', uniqid('', true)), -8);

        $this->instanceId = (int) $this->db->insert('whatsapp_instances', [
            'instance_name' => 'exp_' . $this->u, 'api_url' => 'http://localhost', 'api_key' => 'k',
        ]);
        $this->otherInstanceId = (int) $this->db->insert('whatsapp_instances', [
            'instance_name' => 'exp2_' . $this->u, 'api_url' => 'http://localhost', 'api_key' => 'k',
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->contactIds as $id) {
            try { $this->db->delete('whatsapp_messages', 'contact_id = ?', [$id]); } catch (\Throwable $e) {}
            try { $this->db->delete('whatsapp_contacts', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
        foreach ([$this->instanceId ?? 0, $this->otherInstanceId ?? 0] as $iid) {
            try { $this->db->delete('whatsapp_instances', 'id = ?', [$iid]); } catch (\Throwable $e) {}
        }
    }

    /** Telefone único e válido (55 + DDD 11 + 9 dígitos). */
    private function phone(string $suffix): string
    {
        return '55119' . str_pad(substr(preg_replace('/\D/', '', $this->u) . $suffix, -8), 8, '0', STR_PAD_LEFT);
    }

    private function contato(array $data): int
    {
        $id = (int) $this->db->insert('whatsapp_contacts', array_merge([
            'instance_id' => $this->instanceId,
            'remote_jid' => uniqid('c') . '@s.whatsapp.net',
            'is_group' => 0,
        ], $data));
        $this->contactIds[] = $id;
        return $id;
    }

    private function mensagem(int $contactId, string $groupJid, ?string $participant, ?string $sender, int $instanceId = 0): void
    {
        $this->db->insert('whatsapp_messages', [
            'instance_id' => $instanceId ?: $this->instanceId,
            'contact_id' => $contactId,
            'remote_jid' => $groupJid,
            'message_id' => uniqid('m', true),
            'from_me' => 0,
            'message_type' => 'text',
            'message_text' => 'oi',
            'sender_name' => $sender,
            'participant_jid' => $participant,
        ]);
    }

    public function testContatosLocaisIndexadosPorPhoneKeyComESemNonoDigito(): void
    {
        $phone = $this->phone('1');
        $this->contato(['phone' => $phone, 'contact_name' => 'Maria CRM', 'lead_email' => 'maria@ex.test']);
        // Grupo e contato sem telefone não entram
        $this->contato(['is_group' => 1, 'remote_jid' => '1203' . $this->u . '@g.us', 'phone' => $phone, 'contact_name' => 'Grupo']);
        $this->contato(['phone' => '', 'contact_name' => 'Sem fone']);

        $map = $this->model->getExportContactsByPhoneKey();

        // Mesmo número sem o 9º dígito encontra o mesmo registro
        $semNove = '5511' . substr($phone, -8);
        $hit = $map[WhatsappGroupExportRules::phoneKey($semNove)] ?? null;
        $this->assertNotNull($hit);
        $this->assertSame('Maria CRM', $hit['contact_name']);
        $this->assertSame('maria@ex.test', $hit['lead_email']);

        foreach ($map as $r) {
            $this->assertNotSame('Sem fone', $r['contact_name'] ?? null);
            $this->assertNotSame('Grupo', $r['contact_name'] ?? null, 'grupos não entram no mapa de contatos');
        }
    }

    public function testDuplicadoMantemRegistroMaisCompleto(): void
    {
        $phone = $this->phone('2');
        $this->contato(['phone' => $phone]);
        $this->contato(['phone' => '+' . $phone, 'contact_name' => 'Completo', 'lead_email' => 'c@ex.test']);

        $hit = $this->model->getExportContactsByPhoneKey()[WhatsappGroupExportRules::phoneKey($phone)] ?? null;
        $this->assertSame('Completo', $hit['contact_name'] ?? null);
    }

    public function testNomesDeRemetentesPorJidEPorTelefone(): void
    {
        $groupJid = '1203' . $this->u . '@g.us';
        $gid = $this->contato(['is_group' => 1, 'remote_jid' => $groupJid, 'contact_name' => 'G']);
        $phone = $this->phone('3');
        $lid = '88' . $this->u . '@lid';
        $this->mensagem($gid, $groupJid, $phone . '@s.whatsapp.net', 'João');
        $this->mensagem($gid, $groupJid, $lid, 'Anônimo');
        $this->mensagem($gid, $groupJid, '77' . $this->u . '@lid', null); // sem nome: ignorado

        $map = $this->model->getGroupSenderNames();
        $this->assertSame('João', $map[$phone . '@s.whatsapp.net'] ?? null);
        $this->assertSame('João', $map[WhatsappGroupExportRules::phoneKey($phone)] ?? null);
        $this->assertSame('Anônimo', $map[$lid] ?? null);
        $this->assertArrayNotHasKey('77' . $this->u . '@lid', $map);
    }

    public function testGruposLocaisComParticipantesDaInstancia(): void
    {
        $groupJid = '1203' . $this->u . '@g.us';
        $gid = $this->contato(['is_group' => 1, 'remote_jid' => $groupJid, 'contact_name' => 'Equipe ' . $this->u]);
        $emptyJid = '1204' . $this->u . '@g.us';
        $this->contato(['is_group' => 1, 'remote_jid' => $emptyJid, 'contact_name' => 'Vazio ' . $this->u]);
        // Grupo de OUTRA instância não pode aparecer
        $this->contato(['instance_id' => $this->otherInstanceId, 'is_group' => 1, 'remote_jid' => '1205' . $this->u . '@g.us', 'contact_name' => 'Outra']);

        $p1 = $this->phone('4') . '@s.whatsapp.net';
        $this->mensagem($gid, $groupJid, $p1, 'Ana');
        $this->mensagem($gid, $groupJid, $p1, 'Ana'); // repetido: um participante só
        $this->mensagem($gid, $groupJid, '55' . $this->u . '@lid', 'Bia');

        $raw = $this->model->getLocalGroupsWithSenders($this->instanceId);
        $this->assertCount(2, $raw);

        $groups = WhatsappGroupExportRules::parseGroups($raw);
        $byName = array_column($groups, null, 'subject');
        $this->assertArrayHasKey('Equipe ' . $this->u, $byName);
        $this->assertArrayHasKey('Vazio ' . $this->u, $byName);
        $this->assertArrayNotHasKey('Outra', $byName);

        $parts = $byName['Equipe ' . $this->u]['participants'];
        $this->assertCount(2, $parts);
        $names = array_column($parts, 'name');
        sort($names);
        $this->assertSame(['Ana', 'Bia'], $names);
        $this->assertSame([], $byName['Vazio ' . $this->u]['participants']);

        // Linha final: telefone formatado para quem tem, "Oculto" para @lid
        $phones = array_map(fn($p) => WhatsappGroupExportRules::buildRow($byName['Equipe ' . $this->u], $p, [], [])[2], $parts);
        sort($phones);
        $this->assertSame('Oculto pelo WhatsApp', $phones[1]);
        $this->assertStringStartsWith('+55 (11) 9', $phones[0]);
    }
}
