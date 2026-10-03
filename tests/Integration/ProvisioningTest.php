<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Provisioning;
use Onboarding;
use Database;

/**
 * Testes de integração do Provisionamento (Fase 7) contra helpdesk_on_test.
 * Cobre: criação a partir do onboarding com etapas padrão, conclusão de etapa
 * manual, etapa auto bloqueada sem apiOk, bloqueio por pendência (gap de API),
 * conclusão do provisionamento.
 */
final class ProvisioningTest extends TestCase
{
    private Database $db;
    private Provisioning $prov;
    private Onboarding $onb;
    private int $userId;
    private array $provIds = [];
    private array $onbIds = [];

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste não configurado.');
        }
        $this->db = Database::getInstance();
        $this->prov = new Provisioning();
        $this->onb = new Onboarding();
        $u = uniqid();
        $this->userId = (int) $this->db->insert('users', [
            'name' => "Prov {$u}", 'email' => "p_{$u}@example.test",
            'password' => password_hash('x', PASSWORD_BCRYPT), 'role' => 'super_admin',
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->provIds as $id) {
            try { $this->db->delete('provisioning_events', 'provisioning_id = ?', [$id]); } catch (\Throwable $e) {}
            try { $this->db->delete('provisioning_steps', 'provisioning_id = ?', [$id]); } catch (\Throwable $e) {}
            try { $this->db->delete('provisionings', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
        foreach ($this->onbIds as $id) {
            try { $this->db->delete('onboarding_events', 'onboarding_id = ?', [$id]); } catch (\Throwable $e) {}
            try { $this->db->delete('onboarding_steps', 'onboarding_id = ?', [$id]); } catch (\Throwable $e) {}
            try { $this->db->delete('onboardings', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
        try { $this->db->delete('users', 'id = ?', [$this->userId]); } catch (\Throwable $e) {}
    }

    private function novoOnboarding(): array
    {
        $id = $this->onb->createFromProject(['id' => null, 'title' => 'Proj'], null, $this->userId);
        $this->onbIds[] = $id;
        return $this->onb->findById($id);
    }

    private function novoProvisioning(): int
    {
        $onb = $this->novoOnboarding();
        $id = $this->prov->createFromOnboarding($onb, $this->userId);
        $this->provIds[] = $id;
        return $id;
    }

    public function testCreateFromOnboardingGeraEtapasPadrao(): void
    {
        $id = $this->novoProvisioning();
        $p = $this->prov->findById($id);
        $this->assertSame('pending', $p['status']);
        $steps = $this->prov->getSteps($id);
        $this->assertCount(count(\ProvisioningRules::defaultSteps()), $steps);
        $this->assertSame('create_client', $steps[0]['step_key']);
    }

    public function testEtapaAutoBloqueiaSemApiOkEManualConclui(): void
    {
        $id = $this->novoProvisioning();
        $steps = $this->prov->getSteps($id);
        $auto = null; $manual = null;
        foreach ($steps as $s) {
            if ($s['mode'] === 'auto' && !$auto) $auto = $s;
            if ($s['mode'] === 'manual' && !$manual) $manual = $s;
        }
        $this->assertNotNull($auto, 'deve haver etapa auto (ex.: create_vps/create_database)');
        $this->assertNotNull($manual, 'deve haver etapa manual (ex.: collect_credentials/deliver)');

        // Auto sem apiOk: não conclui.
        $this->assertFalse($this->prov->completeStep((int)$auto['id'], false, $this->userId));
        $this->assertSame('pending', $this->prov->findStep((int)$auto['id'])['status']);
        // Auto com apiOk: conclui e grava external_ref.
        $this->assertTrue($this->prov->completeStep((int)$auto['id'], true, $this->userId, 'db-123'));
        $done = $this->prov->findStep((int)$auto['id']);
        $this->assertSame('done', $done['status']);
        $this->assertSame('db-123', $done['external_ref']);
        // Manual conclui sem API.
        $this->assertTrue($this->prov->completeStep((int)$manual['id'], false, $this->userId));
        $this->assertSame('done', $this->prov->findStep((int)$manual['id'])['status']);
    }

    public function testBlockStepRegistraPendencia(): void
    {
        $id = $this->novoProvisioning();
        $step = $this->prov->findStepByKey($id, 'create_vps');
        $this->assertNotNull($step);
        $this->assertTrue($this->prov->blockStep((int)$step['id'], 'POST /hosting não existe na API.', $this->userId));
        $after = $this->prov->findStep((int)$step['id']);
        $this->assertSame('blocked', $after['status']);
        $this->assertStringContainsString('não existe', $after['blocked_reason']);
    }

    public function testFinishExigeObrigatorias(): void
    {
        $id = $this->novoProvisioning();
        $this->prov->start($id, $this->userId);
        $this->assertSame('in_progress', $this->prov->findById($id)['status']);
        // Ainda há obrigatórias pendentes.
        $this->assertFalse($this->prov->finish($id, $this->userId));
        $this->assertNotEmpty($this->prov->pendingRequired($id));

        // Resolve as obrigatórias (auto com apiOk; manual direto).
        foreach ($this->prov->getSteps($id) as $s) {
            if ((int)$s['required'] === 1) {
                $apiOk = ($s['mode'] === 'auto');
                $this->prov->completeStep((int)$s['id'], $apiOk, $this->userId);
            }
        }
        $this->assertTrue($this->prov->finish($id, $this->userId));
        $this->assertSame('done', $this->prov->findById($id)['status']);
    }

    public function testFindStepByKeyEEventos(): void
    {
        $id = $this->novoProvisioning();
        $this->assertNotNull($this->prov->findStepByKey($id, 'deploy'));
        $this->assertNull($this->prov->findStepByKey($id, 'inexistente'));
        $this->prov->addEvent($id, $this->userId, 'nota', 'teste');
        $this->assertNotEmpty($this->prov->getEvents($id));
    }
}
