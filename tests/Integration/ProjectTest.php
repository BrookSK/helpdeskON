<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Project;
use Database;

/**
 * Testes de integração de Projetos/Garantia (Fase 9) contra helpdesk_on_test.
 * Cobre: criação com normalização, entrega calculando a garantia, bloqueio de
 * chamados pós-garantia e liberação via contrato de suporte.
 */
final class ProjectTest extends TestCase
{
    private Database $db;
    private Project $proj;
    private int $userId;
    private array $projectIds = [];

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste não configurado.');
        }
        $this->db = Database::getInstance();
        $this->proj = new Project();
        $u = uniqid();
        $this->userId = (int) $this->db->insert('users', [
            'name' => "Prj {$u}", 'email' => "prj_{$u}@example.test",
            'password' => password_hash('x', PASSWORD_BCRYPT), 'role' => 'super_admin',
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->projectIds as $id) {
            try { $this->db->delete('project_events', 'project_id = ?', [$id]); } catch (\Throwable $e) {}
            try { $this->db->delete('projects', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
        try { $this->db->delete('users', 'id = ?', [$this->userId]); } catch (\Throwable $e) {}
    }

    private function novo(array $over = []): int
    {
        $id = $this->proj->create(array_merge([
            'name' => 'Projeto X', 'contract_type' => 'zero', 'created_by' => $this->userId,
        ], $over));
        $this->projectIds[] = $id;
        return $id;
    }

    public function testCriaComDefaults(): void
    {
        $id = $this->novo(['contract_type' => 'invalido']);
        $p = $this->proj->findById($id);
        $this->assertSame('outro', $p['contract_type']); // normalizado
        $this->assertSame('planning', $p['status']);
        $this->assertSame(90, (int)$p['warranty_days']);
    }

    public function testEntregaCalculaGarantia(): void
    {
        $id = $this->novo(['contract_type' => 'zero']);
        $this->assertTrue($this->proj->markDelivered($id, $this->userId));
        $p = $this->proj->findById($id);
        $this->assertNotNull($p['delivered_at']);
        $this->assertNotNull($p['warranty_ends_at']);
        // Em garantia logo após entregar.
        $this->assertSame('warranty', $p['status']);
        $this->assertTrue($this->proj->canOpenTicket($id));
    }

    public function testEntregaSemGarantiaBloqueiaChamados(): void
    {
        $id = $this->novo(['contract_type' => 'outro']);
        $this->proj->markDelivered($id, $this->userId);
        $p = $this->proj->findById($id);
        $this->assertSame('delivered', $p['status']); // sem janela de garantia
        $this->assertNull($p['warranty_ends_at']);
        // Entregue, sem garantia e sem suporte -> bloqueado.
        $this->assertFalse($this->proj->canOpenTicket($id));
        // Ativando suporte, libera.
        $this->proj->setSupportContract($id, true, $this->userId);
        $this->assertTrue($this->proj->canOpenTicket($id));
    }

    public function testEventosRegistrados(): void
    {
        $id = $this->novo();
        $this->proj->addEvent($id, $this->userId, 'nota', 'teste');
        $this->assertNotEmpty($this->proj->getEvents($id));
    }
}
