<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use PlanningCard;
use Database;

/**
 * Filtros do Planejamento usados pela tela do celular (prioridade, técnico,
 * prazo) e a contagem countCards(), contra o banco helpdesk_on_test.
 *
 * Todos os cards são de uma empresa criada no setUp, e cada consulta filtra
 * por essa empresa, para não depender de dados pré-existentes no banco.
 */
final class PlanningMobileFiltersTest extends TestCase
{
    private PlanningCard $cards;
    private Database $db;
    private int $userId;
    private int $techId;
    private int $companyId;

    /** Hora de referência fixa para os filtros de prazo. */
    private const NOW = '2026-11-20 12:00:00';

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste (helpdesk_on_test) não configurado.');
        }
        $this->db = Database::getInstance();
        $this->cards = new PlanningCard();
        $this->companyId = (int) $this->db->insert('companies', ['name' => 'Empresa Mobile ' . uniqid()]);
        $mkUser = fn(string $role) => (int) $this->db->insert('users', [
            'name' => 'Usuario ' . $role,
            'email' => 'pmf_' . $role . '_' . uniqid() . '@example.test',
            'password' => password_hash('x', PASSWORD_BCRYPT),
            'role' => $role,
        ]);
        $this->userId = $mkUser('attendant');
        $this->techId = $mkUser('developer');
    }

    protected function tearDown(): void
    {
        try { $this->db->delete('planning_cards', 'company_id = ?', [$this->companyId]); } catch (\Throwable $e) {}
        foreach ([$this->userId ?? 0, $this->techId ?? 0] as $uid) {
            try { $this->db->delete('users', 'id = ?', [$uid]); } catch (\Throwable $e) {}
        }
        try { $this->db->delete('companies', 'id = ?', [$this->companyId]); } catch (\Throwable $e) {}
    }

    private function card(array $o = []): int
    {
        return (int) $this->cards->create(array_merge([
            'title' => 'Card mobile',
            'status' => 'open',
            'priority' => 'medium',
            'company_id' => $this->companyId,
            'created_by' => $this->userId,
            'position' => 0,
        ], $o));
    }

    /** IDs retornados por getGroupedByStatus com o filtro + a empresa do teste. */
    private function ids(array $filters): array
    {
        $filters = array_merge(['company_id' => [$this->companyId], 'now' => self::NOW], $filters);
        $ids = [];
        foreach ($this->cards->getGroupedByStatus($filters) as $list) {
            foreach ($list as $c) $ids[] = (int) $c['id'];
        }
        sort($ids);
        return $ids;
    }

    private function total(array $filters): int
    {
        return $this->cards->countCards(array_merge(['company_id' => [$this->companyId], 'now' => self::NOW], $filters));
    }

    public function testFiltroDePrioridadeAceitaVariasEIgnoraValorInvalido(): void
    {
        $alta = $this->card(['priority' => 'high']);
        $urgente = $this->card(['priority' => 'urgent']);
        $baixa = $this->card(['priority' => 'low']);

        $this->assertSame([$alta], $this->ids(['priority' => ['high']]));
        $this->assertSame([$alta, $urgente], $this->ids(['priority' => ['high', 'urgent']]));
        // Valor desconhecido não vira filtro (não zera a lista nem injeta SQL).
        $this->assertSame([$alta, $urgente, $baixa], $this->ids(['priority' => ["x' OR 1=1 --"]]));
    }

    public function testFiltroDeTecnicoPorIdESemTecnico(): void
    {
        $comTec = $this->card(['technical_responsible_id' => $this->techId]);
        $semTec = $this->card(['technical_responsible_id' => null]);

        $this->assertSame([$comTec], $this->ids(['technical' => [(string) $this->techId]]));
        $this->assertSame([$semTec], $this->ids(['technical' => ['none']]));
        $this->assertSame([$comTec, $semTec], $this->ids(['technical' => [(string) $this->techId, 'none']]));
    }

    public function testFiltroDePrazoAtrasadoIgnoraConcluidoEArquivado(): void
    {
        $atrasado = $this->card(['due_date' => '2026-11-19 10:00:00', 'status' => 'in_progress']);
        $this->card(['due_date' => '2026-11-19 10:00:00', 'status' => 'completed']);
        $this->card(['due_date' => '2026-11-19 10:00:00', 'status' => 'archived']);
        $this->card(['due_date' => '2026-11-21 10:00:00']); // futuro
        $this->card(['due_date' => null]);                  // sem prazo

        $this->assertSame([$atrasado], $this->ids(['deadline' => 'late']));
        $this->assertSame(1, $this->total(['deadline' => 'late']));
    }

    public function testFiltroDePrazoHojeESeteDias(): void
    {
        $hojeCedo = $this->card(['due_date' => '2026-11-20 08:00:00']); // hoje, já passou
        $hojeTarde = $this->card(['due_date' => '2026-11-20 23:30:00']);
        $amanha = $this->card(['due_date' => '2026-11-21 09:00:00']);
        $em6dias = $this->card(['due_date' => '2026-11-26 11:00:00']);
        $this->card(['due_date' => '2026-11-28 09:00:00']); // fora dos 7 dias
        $this->card(['due_date' => '2026-11-19 23:59:00']); // ontem

        $this->assertSame([$hojeCedo, $hojeTarde], $this->ids(['deadline' => 'today']));
        // Próximos 7 dias: de agora em diante (não inclui o que já venceu hoje cedo).
        $this->assertSame([$hojeTarde, $amanha, $em6dias], $this->ids(['deadline' => 'week']));
    }

    public function testPrazoInvalidoNaoFiltra(): void
    {
        $a = $this->card(['due_date' => '2026-11-19 10:00:00']);
        $b = $this->card(['due_date' => null]);
        $this->assertSame([$a, $b], $this->ids(['deadline' => 'qualquer']));
    }

    public function testCountCardsSomaStatusERespeitaFiltros(): void
    {
        $this->card(['status' => 'open', 'priority' => 'high']);
        $this->card(['status' => 'completed', 'priority' => 'high']);
        $this->card(['status' => 'in_progress', 'priority' => 'low', 'assigned_to' => $this->userId]);

        $this->assertSame(3, $this->total([]));
        $this->assertSame(2, $this->total(['priority' => ['high']]));
        $this->assertSame(1, $this->total(['priority' => ['high'], 'statuses' => ['completed']]));
        $this->assertSame(1, $this->total(['assigned_to' => [$this->userId]]));
        $this->assertSame(2, $this->total(['assigned_to' => ['none']]));
    }

    public function testContagensEPaginacaoUsamOsMesmosFiltrosNovos(): void
    {
        for ($i = 0; $i < 3; $i++) $this->card(['priority' => 'high', 'title' => 'Alta ' . $i]);
        $this->card(['priority' => 'low']);
        $f = ['company_id' => [$this->companyId], 'priority' => ['high']];

        $counts = $this->cards->getColumnCounts($f);
        $this->assertSame(3, $counts['open']);
        $pag = $this->cards->getColumnCards('open', $f, 0, 2);
        $pag2 = $this->cards->getColumnCards('open', $f, 2, 2);
        $this->assertCount(2, $pag);
        $this->assertCount(1, $pag2);
        foreach (array_merge($pag, $pag2) as $c) $this->assertSame('high', $c['priority']);
    }
}
