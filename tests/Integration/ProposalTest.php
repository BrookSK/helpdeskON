<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Proposal;
use ServiceCatalog;
use Database;

/**
 * Testes de integração da Proposta/Orçamento (Fase 3) contra helpdesk_on_test.
 * Cobre: criação, substituição de itens com recálculo de total, mudança de
 * status (transição válida/inválida), aceite/recusa e catálogo de serviços.
 */
final class ProposalTest extends TestCase
{
    private Database $db;
    private Proposal $model;
    private ServiceCatalog $svc;
    private int $userId;
    private array $proposalIds = [];
    private array $serviceIds = [];

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste não configurado.');
        }
        $this->db = Database::getInstance();
        $this->model = new Proposal();
        $this->svc = new ServiceCatalog();

        $u = uniqid();
        $this->userId = (int) $this->db->insert('users', [
            'name' => "Comercial {$u}", 'email' => "com_{$u}@example.test",
            'password' => password_hash('x', PASSWORD_BCRYPT), 'role' => 'comercial',
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->proposalIds as $id) {
            try { $this->db->delete('proposal_events', 'proposal_id = ?', [$id]); } catch (\Throwable $e) {}
            try { $this->db->delete('proposal_items', 'proposal_id = ?', [$id]); } catch (\Throwable $e) {}
            try { $this->db->delete('proposals', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
        foreach ($this->serviceIds as $id) {
            try { $this->db->delete('service_catalog', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
        try { $this->db->delete('users', 'id = ?', [$this->userId]); } catch (\Throwable $e) {}
    }

    private function novaProposta(array $over = []): int
    {
        $id = $this->model->create(array_merge([
            'public_token' => $this->model->generateToken(),
            'title' => 'Proposta teste',
            'client_name' => 'Cliente Teste',
            'status' => 'draft',
            'total' => 0,
            'created_by' => $this->userId,
        ], $over));
        $this->proposalIds[] = $id;
        return $id;
    }

    public function testCriaPropostaComTokenEStatusInicial(): void
    {
        $id = $this->novaProposta();
        $p = $this->model->findById($id);
        $this->assertNotEmpty($p['public_token']);
        $this->assertSame('draft', $p['status']);
        $this->assertSame(0.0, (float)$p['total']);
        // Localiza por token.
        $this->assertSame($id, (int)$this->model->findByToken($p['public_token'])['id']);
    }

    public function testReplaceItemsRecalculaTotal(): void
    {
        $id = $this->novaProposta();
        $total = $this->model->replaceItems($id, [
            ['description' => 'Instalação', 'hours' => 3, 'hourly_rate' => 50],   // 150
            ['description' => 'Desenvolvimento', 'hours' => 10, 'hourly_rate' => 50], // 500
            ['description' => 'Hospedagem', 'amount' => 1200, 'is_hosting' => 1],  // 1200 fixo
            ['description' => ''], // linha vazia ignorada
        ]);
        $this->assertSame(1850.0, $total);

        $items = $this->model->getItems($id);
        $this->assertCount(3, $items);
        $this->assertSame(150.0, (float)$items[0]['amount']);
        $this->assertSame(1, (int)$items[2]['is_hosting']);

        // O total persistiu na proposta.
        $this->assertSame(1850.0, (float)$this->model->findById($id)['total']);
    }

    public function testReplaceItemsSubstituiAnteriores(): void
    {
        $id = $this->novaProposta();
        $this->model->replaceItems($id, [['description' => 'A', 'amount' => 100]]);
        $this->model->replaceItems($id, [['description' => 'B', 'amount' => 200]]);
        $items = $this->model->getItems($id);
        $this->assertCount(1, $items);
        $this->assertSame('B', $items[0]['description']);
        $this->assertSame(200.0, (float)$this->model->findById($id)['total']);
    }

    public function testChangeStatusRespeitaTransicoes(): void
    {
        $id = $this->novaProposta();
        // draft -> ready -> sent (válidas)
        $this->assertTrue($this->model->changeStatus($id, 'ready', $this->userId));
        $this->assertTrue($this->model->changeStatus($id, 'sent', $this->userId));
        $this->assertSame('sent', $this->model->findById($id)['status']);
        // sent -> draft (inválida)
        $this->assertFalse($this->model->changeStatus($id, 'draft', $this->userId));
        $this->assertSame('sent', $this->model->findById($id)['status']);
        // Evento de status foi registrado.
        $events = $this->model->getEvents($id);
        $this->assertNotEmpty($events);
    }

    public function testFluxoAceite(): void
    {
        $id = $this->novaProposta();
        $this->model->changeStatus($id, 'ready', $this->userId);
        $this->model->changeStatus($id, 'sent', $this->userId);
        $ok = $this->model->changeStatus($id, 'accepted', null, ['responded_at' => date('Y-m-d H:i:s')]);
        $this->assertTrue($ok);
        $p = $this->model->findById($id);
        $this->assertSame('accepted', $p['status']);
        $this->assertNotEmpty($p['responded_at']);
    }

    public function testFluxoRecusaComMotivo(): void
    {
        $id = $this->novaProposta();
        $this->model->changeStatus($id, 'ready', $this->userId);
        $this->model->changeStatus($id, 'sent', $this->userId);
        $this->model->changeStatus($id, 'rejected', null, [
            'responded_at' => date('Y-m-d H:i:s'),
            'reject_reason' => 'Fora do orçamento',
        ]);
        $p = $this->model->findById($id);
        $this->assertSame('rejected', $p['status']);
        $this->assertSame('Fora do orçamento', $p['reject_reason']);
        // Recusada pode voltar a draft (refazer).
        $this->assertTrue($this->model->changeStatus($id, 'draft', $this->userId));
    }

    public function testCatalogoDeServicosCrudEToggle(): void
    {
        $sid = $this->svc->create([
            'name' => 'Instalação', 'description' => 'Setup inicial',
            'est_hours' => 3, 'hourly_rate' => 50, 'is_hosting' => 0, 'active' => 1,
        ]);
        $this->serviceIds[] = $sid;
        $s = $this->svc->findById($sid);
        $this->assertSame('Instalação', $s['name']);
        $this->assertSame(1, (int)$s['active']);

        // Toggle desativa; só os ativos aparecem em getAll(true).
        $this->svc->toggleActive($sid);
        $this->assertSame(0, (int)$this->svc->findById($sid)['active']);
        $ativos = array_map(fn($r) => (int)$r['id'], $this->svc->getAll(true));
        $this->assertNotContains($sid, $ativos);
    }
}
