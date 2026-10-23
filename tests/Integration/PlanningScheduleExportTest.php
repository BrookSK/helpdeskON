<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use PlanningCard;
use PlanningScheduleRules;
use Database;

/**
 * Teste de integração da busca que alimenta o cronograma em PDF
 * (PlanningCard::getForSchedule) contra o banco helpdesk_on_test, combinada com
 * a regra pura PlanningScheduleRules::buildSchedule.
 *
 * Cobre o caminho real: filtro por empresa/responsável/status/busca, o escopo
 * de acesso por empresa (allowed_companies) e a montagem final agrupada por
 * empresa com ocultação (ou não) das atividades encerradas.
 *
 * Estratégia de dados (igual a PlanningCardTest): cria usuário + duas empresas
 * no setUp e remove tudo no tearDown. created_by é NOT NULL com FK para users.
 */
final class PlanningScheduleExportTest extends TestCase
{
    private PlanningCard $cards;
    private Database $db;
    private int $userId;
    private int $otherUserId;
    private int $companyA;
    private int $companyB;

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste (helpdesk_on_test) não configurado.');
        }

        $this->db = Database::getInstance();
        $this->cards = new PlanningCard();

        $this->companyA = (int) $this->db->insert('companies', ['name' => 'Alfa Export ' . uniqid()]);
        $this->companyB = (int) $this->db->insert('companies', ['name' => 'Zeta Export ' . uniqid()]);

        $this->userId = (int) $this->db->insert('users', [
            'name'     => 'Dono Export',
            'email'    => 'export_' . uniqid() . '@example.test',
            'password' => password_hash('x', PASSWORD_BCRYPT),
            'role'     => 'attendant',
        ]);
        $this->otherUserId = (int) $this->db->insert('users', [
            'name'     => 'Outro Dono',
            'email'    => 'export2_' . uniqid() . '@example.test',
            'password' => password_hash('x', PASSWORD_BCRYPT),
            'role'     => 'attendant',
        ]);
    }

    protected function tearDown(): void
    {
        foreach ([$this->companyA, $this->companyB] as $cid) {
            try { $this->db->delete('planning_cards', 'company_id = ?', [$cid]); } catch (\Throwable $e) {}
        }
        // Cards sem empresa criados pelos usuários de teste.
        foreach ([$this->userId, $this->otherUserId] as $uid) {
            try { $this->db->delete('planning_cards', 'created_by = ?', [$uid]); } catch (\Throwable $e) {}
            try { $this->db->delete('users', 'id = ?', [$uid]); } catch (\Throwable $e) {}
        }
        foreach ([$this->companyA, $this->companyB] as $cid) {
            try { $this->db->delete('companies', 'id = ?', [$cid]); } catch (\Throwable $e) {}
        }
    }

    private function novoCard(array $overrides = []): int
    {
        return (int) $this->cards->create(array_merge([
            'title'      => 'Atividade de teste',
            'status'     => 'open',
            'priority'   => 'medium',
            'created_by' => $this->userId,
            'position'   => 0,
        ], $overrides));
    }

    /** IDs retornados por getForSchedule para os filtros dados. */
    private function idsSchedule(array $filters): array
    {
        return array_map(fn($c) => (int) $c['id'], $this->cards->getForSchedule($filters));
    }

    public function testGetForScheduleTrazCamposEsperados(): void
    {
        $id = $this->novoCard([
            'title'       => 'Site de casamento',
            'description' => '<p>Landing page do casal</p>',
            'company_id'  => $this->companyA,
            'assigned_to' => $this->userId,
            'status'      => 'in_progress',
            'priority'    => 'high',
            'start_date'  => '2026-01-10 09:00:00',
            'end_date'    => '2026-01-20 18:00:00',
            'due_date'    => '2026-01-22 12:00:00',
        ]);

        $rows = $this->cards->getForSchedule(['company_id' => [$this->companyA]]);
        $row = null;
        foreach ($rows as $r) {
            if ((int) $r['id'] === $id) { $row = $r; break; }
        }

        $this->assertNotNull($row, 'O card criado deve aparecer na busca do cronograma.');
        $this->assertSame('Site de casamento', $row['title']);
        $this->assertArrayHasKey('description', $row);
        $this->assertArrayHasKey('company_name', $row);
        $this->assertArrayHasKey('assigned_name', $row);
        $this->assertArrayHasKey('created_by_name', $row);
        $this->assertSame('Dono Export', $row['assigned_name']);
        $this->assertSame('Dono Export', $row['created_by_name']);
    }

    public function testFiltroPorEmpresaIsolaOsCards(): void
    {
        $naA = $this->novoCard(['title' => 'Da Alfa', 'company_id' => $this->companyA]);
        $naB = $this->novoCard(['title' => 'Da Zeta', 'company_id' => $this->companyB]);

        $ids = $this->idsSchedule(['company_id' => [$this->companyA]]);

        $this->assertContains($naA, $ids);
        $this->assertNotContains($naB, $ids, 'Filtro de empresa não deve trazer cards de outra empresa.');
    }

    public function testFiltroPorStatus(): void
    {
        $emAndamento = $this->novoCard(['title' => 'Andamento', 'company_id' => $this->companyA, 'status' => 'in_progress']);
        $aberto      = $this->novoCard(['title' => 'Aberto', 'company_id' => $this->companyA, 'status' => 'open']);

        $ids = $this->idsSchedule(['company_id' => [$this->companyA], 'statuses' => ['in_progress']]);

        $this->assertContains($emAndamento, $ids);
        $this->assertNotContains($aberto, $ids, 'Filtro de status deve limitar aos status escolhidos.');
    }

    public function testFiltroPorResponsavelInclunidoSemResponsavel(): void
    {
        $meu     = $this->novoCard(['title' => 'Meu', 'company_id' => $this->companyA, 'assigned_to' => $this->userId]);
        $deOutro = $this->novoCard(['title' => 'De outro', 'company_id' => $this->companyA, 'assigned_to' => $this->otherUserId]);
        $semDono = $this->novoCard(['title' => 'Sem dono', 'company_id' => $this->companyA, 'assigned_to' => null]);

        // Seleciona o usuário + 'none' (sem responsável): traz meu e semDono, não o de outro.
        $ids = $this->idsSchedule(['company_id' => [$this->companyA], 'assigned_to' => [$this->userId, 'none']]);

        $this->assertContains($meu, $ids);
        $this->assertContains($semDono, $ids);
        $this->assertNotContains($deOutro, $ids);
    }

    public function testBuscaLivrePorTituloEDescricao(): void
    {
        $porTitulo    = $this->novoCard(['title' => 'Exportar relatorio', 'company_id' => $this->companyA]);
        $porDescricao = $this->novoCard(['title' => 'Tarefa qualquer', 'company_id' => $this->companyA, 'description' => '<p>ajustar relatorio mensal</p>']);
        $fora         = $this->novoCard(['title' => 'Configurar servidor', 'company_id' => $this->companyA]);

        $ids = $this->idsSchedule(['company_id' => [$this->companyA], 'q' => 'relatorio']);

        $this->assertContains($porTitulo, $ids);
        $this->assertContains($porDescricao, $ids);
        $this->assertNotContains($fora, $ids);
    }

    public function testEscopoAllowedCompaniesBloqueiaEmpresaSemAcesso(): void
    {
        $naA = $this->novoCard(['title' => 'Alfa', 'company_id' => $this->companyA]);
        $naB = $this->novoCard(['title' => 'Zeta', 'company_id' => $this->companyB]);

        // Usuário só pode ver a empresa A.
        $ids = $this->idsSchedule(['allowed_companies' => [$this->companyA]]);

        $this->assertContains($naA, $ids);
        $this->assertNotContains($naB, $ids, 'Sem acesso à empresa B, seus cards não podem sair no PDF.');
    }

    public function testBuildScheduleOcultaConcluidasPorPadraoEAgrupaPorEmpresa(): void
    {
        $ativoA     = $this->novoCard(['title' => 'Ativo Alfa', 'company_id' => $this->companyA, 'status' => 'in_progress', 'start_date' => '2026-02-01 09:00:00']);
        $concluidoA = $this->novoCard(['title' => 'Concluido Alfa', 'company_id' => $this->companyA, 'status' => 'completed']);
        $ativoB     = $this->novoCard(['title' => 'Ativo Zeta', 'company_id' => $this->companyB, 'status' => 'open']);

        $cards = $this->cards->getForSchedule(['company_id' => [$this->companyA, $this->companyB]]);

        // Padrão (onlyActive = true): oculta o concluído.
        $groups = PlanningScheduleRules::buildSchedule($cards, true);
        $allIds = [];
        foreach ($groups as $g) {
            foreach ($g['cards'] as $c) $allIds[] = (int) $c['id'];
        }
        $this->assertContains($ativoA, $allIds);
        $this->assertContains($ativoB, $allIds);
        $this->assertNotContains($concluidoA, $allIds, 'Concluído não entra nas próximas etapas.');

        // Com todas (onlyActive = false): o concluído volta.
        $groupsAll = PlanningScheduleRules::buildSchedule($cards, false);
        $allIdsAll = [];
        foreach ($groupsAll as $g) {
            foreach ($g['cards'] as $c) $allIdsAll[] = (int) $c['id'];
        }
        $this->assertContains($concluidoA, $allIdsAll, 'Com ?all=1 o concluído deve aparecer.');
    }

    public function testCardSemEmpresaEntraNoGrupoSemEmpresa(): void
    {
        $semEmpresa = $this->novoCard(['title' => 'Sem empresa', 'company_id' => null]);

        $cards = $this->cards->getForSchedule(['allowed_companies' => [$this->companyA, $this->companyB]]);
        $groups = PlanningScheduleRules::buildSchedule($cards, true);

        $grupoSemEmpresa = null;
        foreach ($groups as $g) {
            if ($g['company'] === PlanningScheduleRules::NO_COMPANY_LABEL) { $grupoSemEmpresa = $g; break; }
        }

        $this->assertNotNull($grupoSemEmpresa, 'Deve existir o grupo "Sem empresa".');
        $ids = array_map(fn($c) => (int) $c['id'], $grupoSemEmpresa['cards']);
        $this->assertContains($semEmpresa, $ids);
    }
}
