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

    // ===== Fluxo de Entrega: publicação, documentação, reunião e aceite =====

    public function testMarkPublishedGravaDataEEvento(): void
    {
        $id = $this->novo(['status' => 'in_progress']);
        $this->assertTrue($this->proj->markPublished($id, $this->userId));
        $p = $this->proj->findById($id);
        $this->assertNotNull($p['published_at']);
        $tipos = array_map(fn($e) => $e['event_type'], $this->proj->getEvents($id));
        $this->assertContains('published', $tipos);
    }

    public function testMarkDocumentationGravaCamposEEvento(): void
    {
        $id = $this->novo();
        $this->assertTrue($this->proj->markDocumentation($id, 'https://docs.exemplo/manual.pdf', $this->userId));
        $p = $this->proj->findById($id);
        $this->assertSame('https://docs.exemplo/manual.pdf', $p['manual_url']);
        $this->assertNotNull($p['documentation_delivered_at']);
    }

    public function testLinkDeliveryMeetingGravaDataManual(): void
    {
        $id = $this->novo();
        $this->assertTrue($this->proj->linkDeliveryMeeting($id, 0, '2026-10-10 14:00:00', $this->userId));
        $p = $this->proj->findById($id);
        $this->assertSame('2026-10-10 14:00:00', $p['delivery_meeting_at']);
    }

    public function testSetAcceptanceTokenEBuscaPorToken(): void
    {
        $id = $this->novo();
        $token = $this->proj->setAcceptanceToken($id, $this->userId);
        $this->assertNotNull($token);
        $this->assertSame(32, strlen($token)); // bin2hex(16 bytes)
        $found = $this->proj->findByAcceptanceToken($token);
        $this->assertNotNull($found);
        $this->assertSame($id, (int)$found['id']);
    }

    public function testRegisterClientAcceptanceIniciaGarantia(): void
    {
        // Projeto do tipo 'zero' tem garantia; o aceite inicia a janela.
        $id = $this->novo(['contract_type' => 'zero', 'status' => 'in_progress']);
        $this->assertTrue($this->proj->registerClientAcceptance($id, $this->userId));
        $p = $this->proj->findById($id);
        $this->assertNotNull($p['client_accepted_at']);
        $this->assertSame($this->userId, (int)$p['client_accepted_by']);
        // Garantia calculada e status em garantia.
        $this->assertNotNull($p['warranty_ends_at']);
        $this->assertSame('warranty', $p['status']);
    }

    public function testGetWarrantyEndingSoonEMarkWarrantyWarnSent(): void
    {
        // Entrega hoje: garantia de 90 dias -> NÃO deve aparecer em "15 dias".
        $recent = $this->novo(['contract_type' => 'zero', 'status' => 'in_progress']);
        $this->proj->markDelivered($recent, $this->userId);

        // Projeto cuja garantia termina em ~10 dias -> deve aparecer.
        $soon = $this->novo(['contract_type' => 'zero']);
        $this->proj->update($soon, [
            'status' => 'warranty',
            'delivered_at' => date('Y-m-d H:i:s', strtotime('-80 days')),
            'warranty_ends_at' => date('Y-m-d H:i:s', strtotime('+10 days')),
        ]);

        $rows = $this->proj->getWarrantyEndingSoon(15);
        $ids = array_map(fn($r) => (int)$r['id'], $rows);
        $this->assertContains($soon, $ids, 'Projeto a 10 dias do fim deve aparecer');
        $this->assertNotContains($recent, $ids, 'Projeto a 90 dias não deve aparecer');

        // Após carimbar, não aparece mais (idempotência).
        $this->proj->markWarrantyWarnSent($soon);
        $rows2 = $this->proj->getWarrantyEndingSoon(15);
        $ids2 = array_map(fn($r) => (int)$r['id'], $rows2);
        $this->assertNotContains($soon, $ids2, 'Projeto já avisado não deve reaparecer');
    }
}
