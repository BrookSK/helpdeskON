<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use CrmBoard;
use Database;
use LeadOriginRules;
use LeadResolver;
use SchemaInfo;

/**
 * Integração (helpdesk_on_test): cargo/empresa gravados no lead e origem do
 * card no CRM (migration 174), lidos de volta como o card do CRM lê.
 */
final class LeadProfileOriginTest extends TestCase
{
    private Database $db;
    private CrmBoard $board;
    private int $userId;
    private int $instanceId;
    private int $boardId;
    private int $columnId;
    private array $contactIds = [];
    private array $cardIds = [];

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste não configurado.');
        }
        $this->db = Database::getInstance();
        SchemaInfo::reset();
        if (!SchemaInfo::hasColumn('crm_cards', 'origin') || !SchemaInfo::hasColumn('whatsapp_contacts', 'job_title')) {
            $this->fail('Banco de teste sem a migration 174: rode tests/setup_test_db.php.');
        }
        $this->board = new CrmBoard();
        $u = uniqid();
        $this->userId = (int) $this->db->insert('users', [
            'name' => "Perfil {$u}", 'email' => "perfil_{$u}@example.test",
            'password' => password_hash('x', PASSWORD_BCRYPT), 'role' => 'comercial',
        ]);
        $this->instanceId = (int) $this->db->insert('whatsapp_instances', [
            'instance_name' => "perfilinst_{$u}", 'api_url' => 'http://localhost', 'api_key' => 'k', 'is_default' => 1,
        ]);
        $this->boardId = (int) $this->board->create(['name' => "Board perfil {$u}", 'created_by' => $this->userId]);
        $this->columnId = (int) $this->board->createColumn(['board_id' => $this->boardId, 'name' => 'Novo']);
    }

    protected function tearDown(): void
    {
        foreach ($this->cardIds as $id) {
            try { $this->db->delete('crm_card_activities', 'card_id = ?', [$id]); } catch (\Throwable $e) {}
            try { $this->db->delete('crm_cards', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
        foreach ($this->contactIds as $id) {
            foreach (['lead_timeline', 'lead_score', 'commercial_briefings'] as $t) {
                try { $this->db->delete($t, 'contact_id = ?', [$id]); } catch (\Throwable $e) {}
            }
            try { $this->db->delete('whatsapp_contacts', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
        try { $this->db->delete('crm_columns', 'board_id = ?', [$this->boardId]); } catch (\Throwable $e) {}
        try { $this->db->delete('crm_boards', 'id = ?', [$this->boardId]); } catch (\Throwable $e) {}
        try { $this->db->delete('whatsapp_instances', 'id = ?', [$this->instanceId]); } catch (\Throwable $e) {}
        try { $this->db->delete('users', 'id = ?', [$this->userId]); } catch (\Throwable $e) {}
    }

    private function resolve(array $data): int
    {
        $id = (int) (new LeadResolver())->resolve($data + ['source' => 'import', 'assigned_to' => $this->userId], $this->userId);
        $this->assertGreaterThan(0, $id);
        if (!in_array($id, $this->contactIds, true)) $this->contactIds[] = $id;
        return $id;
    }

    public function testLeadNovoGuardaCargoEEmpresaEDepoisSoCompletaOQueFalta(): void
    {
        $u = uniqid();
        $resolver = new LeadResolver();
        $id = (int) $resolver->resolve([
            'name' => 'Carla Dias', 'email' => "carla_{$u}@example.test", 'job_title' => 'Diretora',
            'source' => 'import', 'assigned_to' => $this->userId,
        ], $this->userId);
        $this->contactIds[] = $id;
        $this->assertTrue($resolver->lastWasCreated);

        $c = $this->db->fetch("SELECT job_title, company_name, assigned_to FROM whatsapp_contacts WHERE id = ?", [$id]);
        $this->assertSame('Diretora', $c['job_title']);
        $this->assertNull($c['company_name']);
        $this->assertSame($this->userId, (int) $c['assigned_to']);

        // Mesmo e-mail: não duplica, não troca o cargo, mas completa a empresa.
        $again = (int) $resolver->resolve([
            'name' => 'Carla', 'email' => "CARLA_{$u}@example.test", 'job_title' => 'Outro cargo', 'company' => 'Acme',
            'source' => 'import',
        ], $this->userId);
        $this->assertSame($id, $again);
        $this->assertFalse($resolver->lastWasCreated);
        $c = $this->db->fetch("SELECT job_title, company_name FROM whatsapp_contacts WHERE id = ?", [$id]);
        $this->assertSame('Diretora', $c['job_title']);
        $this->assertSame('Acme', $c['company_name']);
    }

    public function testCardGuardaOrigemEOCrmLeDadosDoLead(): void
    {
        $u = uniqid();
        $contactId = $this->resolve([
            'name' => 'João Silva', 'email' => "joao_{$u}@example.test", 'phone' => '41999' . substr((string) hexdec(substr($u, -5)), 0, 6),
            'company' => 'Clínica Boa Saúde', 'job_title' => 'CEO', 'linkedin_url' => 'https://linkedin.com/in/joao' . $u,
        ]);
        $cardId = (int) $this->board->createCard([
            'column_id' => $this->columnId, 'title' => 'João Silva', 'contact_id' => $contactId,
            'created_by' => $this->userId, 'assigned_to' => $this->userId,
            'origin' => LeadOriginRules::AUTO, 'origin_detail' => str_repeat('Campanha longa ', 20),
        ]);
        $this->cardIds[] = $cardId;

        $card = $this->board->findCard($cardId);
        $this->assertSame('prospeccao_auto', $card['origin']);
        $this->assertSame(190, mb_strlen($card['origin_detail']), 'detalhe da origem é cortado no tamanho da coluna');
        $this->assertSame("joao_{$u}@example.test", $card['lead_email']);
        $this->assertSame('CEO', $card['job_title']);
        $this->assertSame('Clínica Boa Saúde', $card['company_name']);

        $cards = $this->board->getCards($this->columnId);
        $this->assertCount(1, $cards);
        $this->assertSame('CEO', $cards[0]['job_title']);
        $this->assertSame('prospeccao_auto', $cards[0]['origin']);

        $p = LeadOriginRules::profile($card);
        $this->assertSame('CEO', $p['job_title']);
        $this->assertSame('https://linkedin.com/in/joao' . $u, $p['linkedin']);
    }

    public function testCardSemOrigemContinuaFuncionando(): void
    {
        $cardId = (int) $this->board->createCard([
            'column_id' => $this->columnId, 'title' => 'Card manual', 'created_by' => $this->userId,
        ]);
        $this->cardIds[] = $cardId;
        $card = $this->board->findCard($cardId);
        $this->assertNull($card['origin']);
        $this->assertNull($card['lead_email']);
        $this->assertNull(LeadOriginRules::originBadge($card['origin']));
    }
}
