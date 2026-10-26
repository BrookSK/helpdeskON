<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use PlanningCard;
use PlanningRules;
use Database;

/**
 * Teste de integração do histórico de alterações dos cards de Planejamento
 * (tabela planning_card_history), contra o banco helpdesk_on_test.
 *
 * Valida: gravação do diff (logHistory + getHistory), que campos iguais não
 * geram linha, e que a origem (manual/cascade) é persistida. Dados próprios no
 * setUp/tearDown (created_by NOT NULL; ON DELETE CASCADE limpa o histórico).
 */
final class PlanningHistoryTest extends TestCase
{
    private PlanningCard $cards;
    private Database $db;
    private int $userId;

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste (helpdesk_on_test) não configurado.');
        }
        $this->db = Database::getInstance();
        $this->cards = new PlanningCard();
        $this->userId = (int) $this->db->insert('users', [
            'name'     => 'Usuario Historico',
            'email'    => 'hist_' . uniqid() . '@example.test',
            'password' => password_hash('x', PASSWORD_BCRYPT),
            'role'     => 'attendant',
        ]);
    }

    protected function tearDown(): void
    {
        try { $this->db->delete('users', 'id = ?', [$this->userId]); } catch (\Throwable $e) {}
    }

    private function novoCard(array $overrides = []): int
    {
        return (int) $this->cards->create(array_merge([
            'title'      => 'Card histórico',
            'status'     => 'open',
            'priority'   => 'medium',
            'created_by' => $this->userId,
            'position'   => 0,
        ], $overrides));
    }

    public function testLogHistoryGravaEGetHistoryLe(): void
    {
        $id = $this->novoCard(['status' => 'open', 'due_date' => '2026-01-10 18:00:00']);

        // Simula uma edição: status open->in_progress e due_date deslocado.
        $old = $this->cards->findById($id);
        $new = ['status' => 'in_progress', 'due_date' => '2026-01-15 18:00:00'];
        $changes = PlanningRules::diffCardChanges($old, $new);
        $this->cards->logHistory($id, $this->userId, $changes, 'manual');

        $hist = $this->cards->getHistory($id);
        $this->assertGreaterThanOrEqual(2, count($hist), 'Deveria registrar status e due_date.');

        // Cada item tem field/old/new/source e o nome de quem alterou.
        $labels = array_column($hist, 'field');
        $this->assertContains('Status', $labels);
        $this->assertContains('Prazo de entrega', $labels);
        $this->assertSame('manual', $hist[0]['source']);
        $this->assertSame('Usuario Historico', $hist[0]['user_name']);
    }

    public function testEdicaoSemMudancaNaoGeraHistorico(): void
    {
        $id = $this->novoCard(['status' => 'open']);
        $old = $this->cards->findById($id);
        // "Edita" com os mesmos valores => diff vazio => nada gravado.
        $changes = PlanningRules::diffCardChanges($old, ['status' => 'open', 'title' => 'Card histórico']);
        $this->cards->logHistory($id, $this->userId, $changes, 'manual');
        $this->assertSame([], $this->cards->getHistory($id));
    }

    public function testHistoricoDeCascataMarcadoComoAutomatico(): void
    {
        // Predecessor + dependente; muda o fim do predecessor e recalcula.
        $pred = $this->novoCard([
            'title'      => 'Pred',
            'start_date' => '2026-03-02 09:00:00',
            'end_date'   => '2026-03-06 18:00:00',
        ]);
        $dep = $this->novoCard([
            'title'                => 'Dep',
            'predecessor_id'       => $pred,
            'predecessor_lag_days' => 1,
            'start_date'           => '2026-03-09 09:00:00',
            'end_date'             => '2026-03-11 18:00:00',
            'due_date'             => '2026-06-30 23:59:00',
        ]);

        // Captura o estado do dependente ANTES e dispara a cascata (atrasa o pred).
        $before = $this->cards->findById($dep);
        $this->cards->update($pred, ['end_date' => '2026-03-20 18:00:00']);
        $affected = $this->cards->cascadeRecalcDependents($pred);
        $this->assertNotEmpty($affected);

        $after = $this->cards->findById($dep);
        $changes = PlanningRules::diffCardChanges($before, [
            'start_date' => $after['start_date'],
            'end_date'   => $after['end_date'],
        ]);
        $this->cards->logHistory($dep, $this->userId, $changes, 'cascade');

        $hist = $this->cards->getHistory($dep);
        $this->assertNotEmpty($hist, 'A cascata deveria registrar histórico no dependente.');
        $this->assertSame('cascade', $hist[0]['source']);
    }
}
