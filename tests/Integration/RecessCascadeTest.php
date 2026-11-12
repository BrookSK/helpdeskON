<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use PlanningCard;
use RecessPeriod;
use Database;

/**
 * Teste de integração: cascata de atividade predecessora RESPEITANDO os
 * períodos de recesso (tabela recess_periods), contra o banco helpdesk_on_test.
 *
 * Valida que, ao recalcular as datas dos dependentes (cascadeRecalcDependents),
 * um card que cairia dentro de um recesso é deslocado para depois dele, com a
 * duração estendida (o card "pausa" no recesso) — comportamento acordado.
 *
 * Dados próprios: cria usuário (created_by NOT NULL), empresa e um recesso no
 * setUp; limpa tudo no tearDown. created_by tem ON DELETE CASCADE, então apagar
 * o usuário remove os cards.
 */
final class RecessCascadeTest extends TestCase
{
    private PlanningCard $cards;
    private RecessPeriod $recess;
    private Database $db;
    private int $userId;
    private int $recessId;

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste (helpdesk_on_test) não configurado.');
        }

        $this->db = Database::getInstance();
        $this->cards = new PlanningCard();
        $this->recess = new RecessPeriod();

        $this->userId = (int) $this->db->insert('users', [
            'name'     => 'Usuario Recesso',
            'email'    => 'recess_' . uniqid() . '@example.test',
            'password' => password_hash('x', PASSWORD_BCRYPT),
            'role'     => 'attendant',
        ]);

        // Recesso de fim de ano GLOBAL (company_id NULL): 21/12/2026 a 04/01/2027.
        $this->recessId = (int) $this->recess->create([
            'title'      => 'Recesso Teste ' . uniqid(),
            'start_date' => '2026-12-21',
            'end_date'   => '2027-01-04',
            'company_id' => null,
            'created_by' => $this->userId,
        ]);
    }

    protected function tearDown(): void
    {
        try { $this->db->delete('users', 'id = ?', [$this->userId]); } catch (\Throwable $e) {}
        try { $this->recess->delete($this->recessId); } catch (\Throwable $e) {}
    }

    private function novoCard(array $overrides = []): int
    {
        return (int) $this->cards->create(array_merge([
            'title'      => 'Card recesso',
            'status'     => 'open',
            'priority'   => 'medium',
            'created_by' => $this->userId,
            'position'   => 0,
        ], $overrides));
    }

    public function testGetApplicableTrazRecessoGlobal(): void
    {
        $periodos = $this->recess->getApplicable(null);
        $ids = array_map(fn($p) => (int) $p['id'], $periodos);
        $this->assertContains($this->recessId, $ids, 'Recesso global deve ser aplicável.');
    }

    public function testCascataDeslocaDependenteParaForaDoRecesso(): void
    {
        // Predecessor termina em 18/12 (antes do recesso). Dependente dura ~4
        // dias e começaria logo após (folga 1) — cairia dentro do recesso.
        $pred = $this->novoCard([
            'title'      => 'Predecessor',
            'start_date' => '2026-12-14 09:00:00',
            'end_date'   => '2026-12-18 18:00:00',
        ]);
        $dep = $this->novoCard([
            'title'                => 'Dependente',
            'predecessor_id'       => $pred,
            'predecessor_lag_days' => 1,
            'start_date'           => '2026-12-19 09:00:00',
            'end_date'             => '2026-12-23 18:00:00', // ~4 dias, invade recesso
            'due_date'             => '2027-03-31 23:59:00', // prazo folgado
        ]);

        // Dispara a cascata a partir do predecessor.
        $afetados = $this->cards->cascadeRecalcDependents($pred);
        $this->assertNotEmpty($afetados, 'A cascata deveria ter afetado o dependente.');

        $depCard = $this->cards->findById($dep);
        $start = strtotime((string) $depCard['start_date']);
        $end = strtotime((string) $depCard['end_date']);

        // Nenhuma das pontas pode cair DENTRO do recesso (21/12 a 04/01).
        $recStart = strtotime('2026-12-21 00:00:00');
        $recEnd = strtotime('2027-01-04 23:59:59');
        $this->assertFalse(
            $start >= $recStart && $start <= $recEnd,
            'O início do dependente não pode ficar dentro do recesso.'
        );
        // O fim deve ter sido empurrado para depois do recesso (card pausou).
        $this->assertGreaterThan($recEnd, $end, 'O fim do dependente deve passar do recesso.');
    }

    public function testCascataSemRecessoAplicavelNaoDesloca(): void
    {
        // Cadeia totalmente fora do recesso: datas não devem saltar.
        $pred = $this->novoCard([
            'title'      => 'Pred fora',
            'start_date' => '2026-10-05 09:00:00',
            'end_date'   => '2026-10-09 18:00:00',
        ]);
        $dep = $this->novoCard([
            'title'                => 'Dep fora',
            'predecessor_id'       => $pred,
            'predecessor_lag_days' => 1,
            'start_date'           => '2026-10-10 09:00:00',
            'end_date'             => '2026-10-14 18:00:00',
            'due_date'             => '2026-12-01 23:59:00',
        ]);

        $this->cards->cascadeRecalcDependents($pred);
        $depCard = $this->cards->findById($dep);

        // Começa logo após o fim do predecessor + 1 dia de folga (10/10), sem
        // qualquer salto de recesso.
        $this->assertStringStartsWith('2026-10-10', (string) $depCard['start_date']);
    }
}
