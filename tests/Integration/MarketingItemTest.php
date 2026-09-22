<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use MarketingItem;
use Database;

/**
 * Testes de integração do módulo de Marketing (MarketingItem) contra o banco
 * helpdesk_on_test. Cobre CRUD, listas (getList/getPendencias/getForCalendar),
 * histórico, anexos, datas comemorativas e o fluxo de status.
 */
final class MarketingItemTest extends TestCase
{
    private Database $db;
    private MarketingItem $model;
    private int $adminId;
    private int $marketerId;
    /** @var int[] */
    private array $itemIds = [];
    /** @var int[] */
    private array $holidayIds = [];

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste (helpdesk_on_test) não configurado.');
        }
        $this->db = Database::getInstance();
        $this->model = new MarketingItem();

        $uid = uniqid();
        $this->adminId = $this->novoUsuario("Admin {$uid}", "adm_{$uid}@example.test", 'super_admin');
        $this->marketerId = $this->novoUsuario("Mkt {$uid}", "mkt_{$uid}@example.test", 'marketing');
    }

    protected function tearDown(): void
    {
        foreach ($this->itemIds as $id) {
            try { $this->db->delete('marketing_items', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
        foreach ($this->holidayIds as $id) {
            try { $this->db->delete('marketing_holidays', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
        foreach ([$this->marketerId, $this->adminId] as $id) {
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

    private function novoItem(array $overrides = []): int
    {
        $id = (int) $this->model->create(array_merge([
            'title' => 'Post de teste',
            'created_by' => $this->marketerId,
            'assigned_to' => $this->marketerId,
            'status' => 'ideia',
        ], $overrides));
        $this->itemIds[] = $id;
        return $id;
    }

    // ===== CRUD =====

    public function testCriarItemGravaComStatusInicial(): void
    {
        $id = $this->novoItem([
            'title' => 'Campanha de Natal',
            'social_network' => 'instagram',
            'briefing' => 'Post comemorativo',
            'status' => 'ideia',
        ]);
        $item = $this->model->findById($id);
        $this->assertSame('Campanha de Natal', $item['title']);
        $this->assertSame('ideia', $item['status']);
        $this->assertSame('instagram', $item['social_network']);
        $this->assertSame($this->marketerId, (int) $item['assigned_to']);
        // JOIN traz o nome do responsável
        $this->assertNotEmpty($item['assigned_name']);
    }

    public function testAtualizarItem(): void
    {
        $id = $this->novoItem();
        $this->model->update($id, ['title' => 'Novo título', 'status' => 'em_producao']);
        $item = $this->model->findById($id);
        $this->assertSame('Novo título', $item['title']);
        $this->assertSame('em_producao', $item['status']);
    }

    public function testDeletarItem(): void
    {
        $id = $this->novoItem();
        $this->assertNotEmpty($this->model->findById($id));
        $this->model->delete($id);
        $this->itemIds = array_values(array_filter($this->itemIds, fn($x) => $x !== $id));
        $this->assertEmpty($this->model->findById($id) ?: []);
    }

    // ===== Listas =====

    public function testGetPendenciasTrazIdeiaRascunhoEAjustes(): void
    {
        $ideia = $this->novoItem(['status' => 'ideia']);
        $rascunho = $this->novoItem(['status' => 'rascunho']);
        $ajustes = $this->novoItem(['status' => 'em_producao', 'review_notes' => 'Corrigir a arte']);
        // Em produção SEM review_notes não é pendência
        $emProducao = $this->novoItem(['status' => 'em_producao']);

        $pend = $this->model->getPendencias(['assigned_to' => $this->marketerId]);
        $ids = array_map(fn($i) => (int) $i['id'], $pend);

        $this->assertContains($ideia, $ids);
        $this->assertContains($rascunho, $ids);
        $this->assertContains($ajustes, $ids);
        $this->assertNotContains($emProducao, $ids);
    }

    public function testGetListFiltraPorStatus(): void
    {
        $aprovado = $this->novoItem(['status' => 'aprovado']);
        $this->novoItem(['status' => 'ideia']);

        $lista = $this->model->getList(['assigned_to' => $this->marketerId, 'status' => 'aprovado']);
        $ids = array_map(fn($i) => (int) $i['id'], $lista);
        $this->assertContains($aprovado, $ids);
        foreach ($lista as $i) {
            $this->assertSame('aprovado', $i['status']);
        }
    }

    public function testGetListFiltraPorMultiplosStatus(): void
    {
        $a = $this->novoItem(['status' => 'aprovado']);
        $b = $this->novoItem(['status' => 'agendado']);
        $c = $this->novoItem(['status' => 'ideia']);

        $lista = $this->model->getList([
            'assigned_to' => $this->marketerId,
            'status' => ['aprovado', 'agendado'],
        ]);
        $ids = array_map(fn($i) => (int) $i['id'], $lista);
        $this->assertContains($a, $ids);
        $this->assertContains($b, $ids);
        $this->assertNotContains($c, $ids);
    }

    public function testGetForCalendarExcluiRejeitadoEUsaScheduledAt(): void
    {
        $agendado = $this->novoItem(['status' => 'agendado', 'scheduled_at' => '2026-06-15 10:00:00']);
        $rejeitado = $this->novoItem(['status' => 'rejeitado', 'scheduled_at' => '2026-06-16 10:00:00']);

        $eventos = $this->model->getForCalendar('2026-06-01 00:00:00', '2026-06-30 23:59:59', ['assigned_to' => $this->marketerId]);
        $ids = array_map(fn($i) => (int) $i['id'], $eventos);
        $this->assertContains($agendado, $ids);
        $this->assertNotContains($rejeitado, $ids);
    }

    // ===== Histórico =====

    public function testHistoricoRegistraAcoes(): void
    {
        $id = $this->novoItem();
        $this->model->addHistory($id, $this->marketerId, 'created', 'Demanda criada.');
        $this->model->addHistory($id, $this->adminId, 'approved', 'Aprovado pelo admin.');

        $hist = $this->model->getHistory($id);
        $this->assertGreaterThanOrEqual(2, count($hist));
        $acoes = array_map(fn($h) => $h['action'], $hist);
        $this->assertContains('created', $acoes);
        $this->assertContains('approved', $acoes);
    }

    // ===== Anexos =====

    public function testAnexosCrud(): void
    {
        $id = $this->novoItem();
        $attId = (int) $this->model->addAttachment([
            'item_id' => $id,
            'user_id' => $this->marketerId,
            'file_name' => 'arte.png',
            'file_path' => 'uploads/marketing/arte.png',
            'file_type' => 'image/png',
            'file_size' => 12345,
        ]);
        $this->assertGreaterThan(0, $attId);

        $anexos = $this->model->getAttachments($id);
        $this->assertCount(1, $anexos);
        $this->assertSame('arte.png', $anexos[0]['file_name']);

        $this->model->deleteAttachment($attId);
        $this->assertCount(0, $this->model->getAttachments($id));
    }

    // ===== Datas comemorativas =====

    public function testHolidaysCrudEDedup(): void
    {
        $hid = (int) $this->model->addHoliday([
            'title' => 'Dia do Teste',
            'holiday_date' => '2026-05-10',
            'category' => 'comercial',
        ]);
        $this->holidayIds[] = $hid;
        $this->assertGreaterThan(0, $hid);

        // holidayExists detecta duplicata (mesma data + título)
        $this->assertNotEmpty($this->model->holidayExists('2026-05-10', 'Dia do Teste'));
        $this->assertEmpty($this->model->holidayExists('2026-05-10', 'Outro Título') ?: []);

        $lista = $this->model->getHolidays('2026-05-01', '2026-05-31');
        $ids = array_map(fn($h) => (int) $h['id'], $lista);
        $this->assertContains($hid, $ids);
    }

    // ===== Fluxo de status =====

    public function testFluxoDeAprovacao(): void
    {
        $id = $this->novoItem(['status' => 'aguardando_aprovacao']);
        // Admin aprova
        $this->model->update($id, ['status' => 'aprovado', 'review_notes' => null]);
        $this->assertSame('aprovado', $this->model->findById($id)['status']);
        // Agenda
        $this->model->update($id, ['status' => 'agendado']);
        $this->assertSame('agendado', $this->model->findById($id)['status']);
        // Publica
        $this->model->update($id, ['status' => 'publicado']);
        $this->assertSame('publicado', $this->model->findById($id)['status']);
    }
}
