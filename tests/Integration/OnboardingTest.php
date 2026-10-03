<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Onboarding;
use FinanceProject;
use Database;

/**
 * Testes de integração do Onboarding (Fase 6) contra helpdesk_on_test.
 * Cobre: criação a partir do projeto com etapas padrão, bloqueio de etapa
 * obrigatória sem requisito, conclusão de etapas, start só com entrada paga e
 * conclusão do onboarding (canFinish).
 */
final class OnboardingTest extends TestCase
{
    private Database $db;
    private Onboarding $onb;
    private FinanceProject $proj;
    private int $userId;
    private array $onboardingIds = [];
    private array $projectIds = [];

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste não configurado.');
        }
        $this->db = Database::getInstance();
        $this->onb = new Onboarding();
        $this->proj = new FinanceProject();
        $u = uniqid();
        $this->userId = (int) $this->db->insert('users', [
            'name' => "Onb {$u}", 'email' => "o_{$u}@example.test",
            'password' => password_hash('x', PASSWORD_BCRYPT), 'role' => 'super_admin',
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->onboardingIds as $id) {
            try { $this->db->delete('onboarding_events', 'onboarding_id = ?', [$id]); } catch (\Throwable $e) {}
            try { $this->db->delete('onboarding_steps', 'onboarding_id = ?', [$id]); } catch (\Throwable $e) {}
            try { $this->db->delete('client_contacts', 'onboarding_id = ?', [$id]); } catch (\Throwable $e) {}
            try { $this->db->delete('onboardings', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
        foreach ($this->projectIds as $id) {
            try { $this->db->delete('finance_charges', 'project_id = ?', [$id]); } catch (\Throwable $e) {}
            try { $this->db->delete('finance_projects', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
        try { $this->db->delete('users', 'id = ?', [$this->userId]); } catch (\Throwable $e) {}
    }

    private function novoProjeto(float $total = 1000): array
    {
        $id = $this->proj->create([
            'title' => 'Projeto Onb', 'total_value' => $total, 'status' => 'open', 'created_by' => $this->userId,
        ]);
        $this->projectIds[] = $id;
        return $this->proj->findById($id);
    }

    private function novoOnboarding(array $project): int
    {
        $id = $this->onb->createFromProject($project, null, $this->userId);
        $this->onboardingIds[] = $id;
        return $id;
    }

    public function testCreateFromProjectGeraEtapasPadrao(): void
    {
        $project = $this->novoProjeto();
        $id = $this->novoOnboarding($project);

        $onb = $this->onb->findById($id);
        $this->assertSame('blocked', $onb['status']); // nasce bloqueado
        $steps = $this->onb->getSteps($id);
        $this->assertCount(count(\OnboardingRules::defaultSteps()), $steps);
        // Primeira etapa é a padrão na posição 0.
        $this->assertSame('tech_responsible', $steps[0]['step_key']);
    }

    public function testEtapaObrigatoriaBloqueiaSemRequisito(): void
    {
        $project = $this->novoProjeto();
        $id = $this->novoOnboarding($project);
        $steps = $this->onb->getSteps($id);
        // Acha uma etapa obrigatória.
        $obrig = null;
        foreach ($steps as $s) { if ((int)$s['required'] === 1) { $obrig = $s; break; } }
        $this->assertNotNull($obrig);
        // Sem requisito cumprido: recusa.
        $this->assertFalse($this->onb->completeStep((int)$obrig['id'], false, $this->userId));
        $this->assertSame('pending', $this->onb->findStep((int)$obrig['id'])['status']);
        // Com requisito: conclui.
        $this->assertTrue($this->onb->completeStep((int)$obrig['id'], true, $this->userId));
        $this->assertSame('done', $this->onb->findStep((int)$obrig['id'])['status']);
    }

    public function testStartSoComEntradaPaga(): void
    {
        $project = $this->novoProjeto(1000);
        $this->proj->replaceCharges($project['id'], \FinanceRules::buildChargePlan([
            'total' => 1000, 'entry' => 500, 'installments' => 1,
        ]));
        $id = $this->novoOnboarding($this->proj->findById($project['id']));

        // Entrada não paga: start recusa, permanece bloqueado.
        $entryPaid = $this->proj->canStartOnboarding($project['id']);
        $this->assertFalse($entryPaid);
        $this->assertFalse($this->onb->start($id, $entryPaid, $this->userId));
        $this->assertSame('blocked', $this->onb->findById($id)['status']);

        // Paga a entrada.
        foreach ($this->proj->getCharges($project['id']) as $c) {
            if ($c['kind'] === 'entry') { $this->proj->markChargePaid((int)$c['id']); }
        }
        $entryPaid = $this->proj->canStartOnboarding($project['id']);
        $this->assertTrue($entryPaid);
        $this->assertTrue($this->onb->start($id, $entryPaid, $this->userId));
        $this->assertSame('in_progress', $this->onb->findById($id)['status']);
    }

    public function testFinishExigeTodasObrigatorias(): void
    {
        $project = $this->novoProjeto();
        $id = $this->novoOnboarding($project);
        $this->onb->update($id, ['status' => 'in_progress']);

        // Ainda há obrigatórias pendentes -> não conclui.
        $this->assertFalse($this->onb->finish($id, $this->userId));
        $this->assertNotEmpty($this->onb->pendingRequired($id));

        // Conclui todas as obrigatórias.
        foreach ($this->onb->getSteps($id) as $s) {
            if ((int)$s['required'] === 1) {
                $this->onb->completeStep((int)$s['id'], true, $this->userId);
            }
        }
        $this->assertTrue($this->onb->finish($id, $this->userId));
        $this->assertSame('done', $this->onb->findById($id)['status']);
    }

    public function testAddContactEEventos(): void
    {
        $project = $this->novoProjeto();
        $id = $this->novoOnboarding($project);
        $this->onb->addContact([
            'onboarding_id' => $id, 'name' => 'Fulano', 'role' => 'TI', 'is_primary' => 1,
        ]);
        $contacts = $this->onb->getContacts($id);
        $this->assertCount(1, $contacts);
        $this->assertSame('Fulano', $contacts[0]['name']);

        $this->onb->addEvent($id, $this->userId, 'nota', 'teste');
        $this->assertNotEmpty($this->onb->getEvents($id));
    }
}
