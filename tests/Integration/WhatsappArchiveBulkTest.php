<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use WhatsappContact;
use Database;

/**
 * Testes de integração do arquivamento e das ações em massa do chat de WhatsApp
 * contra o banco helpdesk_on_test.
 *
 * Cobre as operações de MODELO que sustentam as novas actions do controller:
 *  - setArchived faz o contato/grupo sair da listagem (getAll oculta
 *    is_archived = 1 por padrão) e desarquivar o traz de volta;
 *  - assignTo atribui responsável (base do "atribuir em massa");
 *  - updateServiceStatus = 'concluido' (base do "concluir em massa").
 *
 * Cria os próprios dados e limpa no tearDown; não depende de nada pré-existente.
 */
final class WhatsappArchiveBulkTest extends TestCase
{
    private Database $db;
    private WhatsappContact $contacts;
    private int $userId;
    private int $instanceId;

    /** @var int[] */
    private array $contactIds = [];

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste (helpdesk_on_test) não configurado.');
        }

        $this->db = Database::getInstance();
        $this->contacts = new WhatsappContact();

        $u = uniqid();
        $this->userId = (int) $this->db->insert('users', [
            'name' => "Atendente {$u}", 'email' => "att_{$u}@example.test",
            'password' => password_hash('x', PASSWORD_BCRYPT), 'role' => 'attendant',
        ]);
        $this->instanceId = (int) $this->db->insert('whatsapp_instances', [
            'instance_name' => "arqinst_{$u}", 'api_url' => 'http://localhost', 'api_key' => 'k',
            'user_id' => $this->userId,
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->contactIds as $cid) {
            try { $this->db->delete('whatsapp_messages', 'contact_id = ?', [$cid]); } catch (\Throwable $e) {}
            try { $this->db->delete('whatsapp_contacts', 'id = ?', [$cid]); } catch (\Throwable $e) {}
        }
        try { $this->db->delete('whatsapp_instances', 'id = ?', [$this->instanceId]); } catch (\Throwable $e) {}
        try { $this->db->delete('users', 'id = ?', [$this->userId]); } catch (\Throwable $e) {}
    }

    private function novoContato(array $overrides = []): int
    {
        $u = uniqid();
        $id = (int) $this->db->insert('whatsapp_contacts', array_merge([
            'instance_id'  => $this->instanceId,
            'remote_jid'   => "5511{$u}@s.whatsapp.net",
            'phone'        => '5511' . substr(preg_replace('/\D/', '', $u . '0000000'), 0, 9),
            'contact_name' => "Lead {$u}",
            'is_group'     => 0,
        ], $overrides));
        $this->contactIds[] = $id;
        return $id;
    }

    /** IDs visíveis na listagem do chat (sem filtro de arquivados). */
    private function idsNaLista(): array
    {
        $rows = $this->contacts->getAll([$this->instanceId], [], 'contacts');
        return array_map(fn($r) => (int) $r['id'], $rows);
    }

    public function testArquivarTiraDaListaEDesarquivarTraz(): void
    {
        $cid = $this->novoContato();
        $this->assertContains($cid, $this->idsNaLista(), 'Contato novo deve aparecer na lista.');

        $this->contacts->setArchived($cid, true);
        $this->assertNotContains($cid, $this->idsNaLista(), 'Arquivado deve sumir da lista.');

        $this->contacts->setArchived($cid, false);
        $this->assertContains($cid, $this->idsNaLista(), 'Desarquivado deve voltar à lista.');
    }

    public function testConcluirEmMassaMarcaStatusEArquiva(): void
    {
        // Simula o efeito de bulkComplete no nível do model para dois contatos.
        $c1 = $this->novoContato();
        $c2 = $this->novoContato();

        foreach ([$c1, $c2] as $cid) {
            $this->contacts->updateServiceStatus($cid, 'concluido');
            $this->contacts->setArchived($cid, true);
        }

        foreach ([$c1, $c2] as $cid) {
            $card = $this->contacts->findById($cid);
            $this->assertSame('concluido', $card['service_status']);
            $this->assertSame(1, (int) $card['is_archived']);
        }
        // Ambos somem da listagem padrão.
        $ids = $this->idsNaLista();
        $this->assertNotContains($c1, $ids);
        $this->assertNotContains($c2, $ids);
    }

    public function testAtribuirEmMassaDefineResponsavel(): void
    {
        $c1 = $this->novoContato(['assigned_to' => null]);
        $c2 = $this->novoContato(['assigned_to' => null]);

        foreach ([$c1, $c2] as $cid) {
            $this->contacts->assignTo($cid, $this->userId);
        }

        $this->assertSame($this->userId, (int) $this->contacts->findById($c1)['assigned_to']);
        $this->assertSame($this->userId, (int) $this->contacts->findById($c2)['assigned_to']);
    }

    public function testArquivarFuncionaTambemParaGrupo(): void
    {
        $g = $this->novoContato([
            'is_group'   => 1,
            'remote_jid' => '12036300@g.us',
            'phone'      => null,
        ]);
        $this->assertContains($g, array_map(
            fn($r) => (int) $r['id'],
            $this->contacts->getAll([$this->instanceId], [], 'groups')
        ), 'Grupo deve aparecer na aba de grupos.');

        $this->contacts->setArchived($g, true);
        $this->assertNotContains($g, array_map(
            fn($r) => (int) $r['id'],
            $this->contacts->getAll([$this->instanceId], [], 'groups')
        ), 'Grupo arquivado deve sumir da aba de grupos.');
    }
}
