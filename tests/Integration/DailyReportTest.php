<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use DailyReport;
use RdoRules;
use Database;
use Company;

/**
 * Testes de integração do RDO (DailyReport) contra o banco helpdesk_on_test.
 * Cobre CRUD, filtros (busca/data/status/ocorrência), cards de resumo (stats),
 * anexos, colaboradores e o ESCOPO de visibilidade por papel aplicado via
 * filtro user_id (super_admin global vs. demais só o próprio).
 */
final class DailyReportTest extends TestCase
{
    private Database $db;
    private DailyReport $model;
    private int $userA;   // comercial
    private int $userDev; // developer
    private int $admin;   // super_admin
    private int $companyX; // projeto/obra X
    private int $companyY; // projeto/obra Y
    /** @var int[] */
    private array $reportIds = [];
    /** @var int[] */
    private array $companyIds = [];

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste (helpdesk_on_test) não configurado.');
        }
        $this->db = Database::getInstance();
        $this->model = new DailyReport();

        $u = uniqid();
        $this->userA = $this->novoUsuario("Comercial {$u}", "coma_{$u}@ex.test", 'comercial');
        $this->userDev = $this->novoUsuario("Dev {$u}", "dev_{$u}@ex.test", 'developer');
        $this->admin = $this->novoUsuario("Admin {$u}", "adm_{$u}@ex.test", 'super_admin');

        // Projetos/obras = empresas (pais da FK company_id). Nomes controlados
        // para verificar a ordenação por projeto (Obra A... vem antes de Obra B...).
        $this->companyX = $this->novaEmpresa("Obra A {$u}");
        $this->companyY = $this->novaEmpresa("Obra B {$u}");
    }

    protected function tearDown(): void
    {
        foreach ($this->reportIds as $id) {
            try { $this->db->delete('daily_report_attachments', 'report_id = ?', [$id]); } catch (\Throwable $e) {}
            try { $this->db->delete('daily_report_collaborators', 'report_id = ?', [$id]); } catch (\Throwable $e) {}
            try { $this->db->delete('daily_reports', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
        foreach ([$this->userA, $this->userDev, $this->admin] as $id) {
            try { $this->db->delete('daily_reports', 'user_id = ?', [$id]); } catch (\Throwable $e) {}
            try { $this->db->delete('users', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
        foreach ($this->companyIds as $id) {
            try { $this->db->delete('companies', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
    }

    private function novoUsuario(string $name, string $email, string $role): int
    {
        return (int) $this->db->insert('users', [
            'name' => $name, 'email' => $email,
            'password' => password_hash('x', PASSWORD_BCRYPT), 'role' => $role,
        ]);
    }

    private function novaEmpresa(string $name): int
    {
        $id = (int) $this->db->insert('companies', ['name' => $name]);
        $this->companyIds[] = $id;
        return $id;
    }

    private function novoRdo(int $userId, array $ov = []): int
    {
        $occ = $ov['occurrences'] ?? null;
        $id = (int) $this->model->create(array_merge([
            'user_id' => $userId,
            'report_date' => date('Y-m-d'),
            'title' => 'Relatório teste',
            'activities' => 'Fiz tarefas do dia',
            'occurrences' => $occ,
            'has_occurrence' => RdoRules::deriveHasOccurrence($occ),
            'status' => 'em_andamento',
        ], $ov));
        $this->reportIds[] = $id;
        return $id;
    }

    // ================= CRUD =================

    public function testCriarEBuscarRdo(): void
    {
        $id = $this->novoRdo($this->userA, ['title' => 'Meu dia']);
        $r = $this->model->findById($id);
        $this->assertSame('Meu dia', $r['title']);
        $this->assertSame('em_andamento', $r['status']);
        $this->assertSame($this->userA, (int) $r['user_id']);
    }

    public function testAtualizarRdo(): void
    {
        $id = $this->novoRdo($this->userA);
        $this->model->update($id, ['status' => 'finalizado', 'occurrences' => 'Atraso no servidor', 'has_occurrence' => 1]);
        $r = $this->model->findById($id);
        $this->assertSame('finalizado', $r['status']);
        $this->assertSame(1, (int) $r['has_occurrence']);
    }

    public function testExcluirRdo(): void
    {
        $id = $this->novoRdo($this->userA);
        $this->model->delete($id);
        // Database::fetch() retorna false (não null) quando não encontra; o
        // relatório deve ter deixado de existir.
        $this->assertEmpty($this->model->findById($id));
    }

    // ================= Filtros =================

    public function testFiltroPorBuscaEStatus(): void
    {
        $this->novoRdo($this->userA, ['activities' => 'Deploy do modulo financeiro', 'status' => 'finalizado']);
        $this->novoRdo($this->userA, ['activities' => 'Reunião com cliente', 'status' => 'em_andamento']);

        $porBusca = $this->model->getList(['user_id' => $this->userA, 'search' => 'financeiro']);
        $this->assertCount(1, $porBusca);
        $this->assertStringContainsString('financeiro', $porBusca[0]['activities']);

        $porStatus = $this->model->getList(['user_id' => $this->userA, 'status' => 'finalizado']);
        $this->assertCount(1, $porStatus);
        $this->assertSame('finalizado', $porStatus[0]['status']);
    }

    public function testFiltroPorData(): void
    {
        $ontem = date('Y-m-d', strtotime('-1 day'));
        $hoje = date('Y-m-d');
        $this->novoRdo($this->userA, ['report_date' => $ontem]);
        $this->novoRdo($this->userA, ['report_date' => $hoje]);

        $soHoje = $this->model->getList(['user_id' => $this->userA, 'date' => $hoje]);
        $this->assertCount(1, $soHoje);
        $this->assertSame($hoje, $soHoje[0]['report_date']);
    }

    public function testFiltroPorOcorrencia(): void
    {
        $this->novoRdo($this->userA, ['occurrences' => 'Faltou acesso ao sistema']); // has_occurrence=1
        $this->novoRdo($this->userA, ['occurrences' => null]); // has_occurrence=0

        $comOcc = $this->model->getList(['user_id' => $this->userA, 'has_occurrence' => 1]);
        $this->assertCount(1, $comOcc);
        $this->assertSame(1, (int) $comOcc[0]['has_occurrence']);
    }

    // ================= Stats =================

    public function testStatsContaTotaisPorStatusEOcorrencia(): void
    {
        $this->novoRdo($this->userA, ['status' => 'finalizado', 'occurrences' => 'x']); // finalizado + occ
        $this->novoRdo($this->userA, ['status' => 'finalizado']);
        $this->novoRdo($this->userA, ['status' => 'em_andamento']);

        $stats = $this->model->getStats(['user_id' => $this->userA]);
        $this->assertSame(3, $stats['total']);
        $this->assertSame(2, $stats['finalizado']);
        $this->assertSame(1, $stats['em_andamento']);
        $this->assertSame(1, $stats['ocorrencias']);
    }

    // ================= Escopo de visibilidade =================

    public function testEscopoPorUsuarioIsolaRelatorios(): void
    {
        $this->novoRdo($this->userA);
        $this->novoRdo($this->userDev);

        // Filtrando pelo dono A, só vem o de A (simula o que o controller faz
        // para papéis sem visão global).
        $listaA = $this->model->getList(['user_id' => $this->userA]);
        foreach ($listaA as $r) {
            $this->assertSame($this->userA, (int) $r['user_id']);
        }

        // Sem filtro de usuário (visão global do super_admin), aparecem ambos.
        $todos = $this->model->getList([]);
        $ids = array_map(fn($r) => (int) $r['user_id'], $todos);
        $this->assertContains($this->userA, $ids);
        $this->assertContains($this->userDev, $ids);
    }

    public function testRegraDeVisibilidadeCasaComRdoRules(): void
    {
        // super_admin vê o de A; developer NÃO vê o de A (só o próprio).
        $this->assertTrue(RdoRules::canViewReportOf('super_admin', $this->admin, $this->userA));
        $this->assertFalse(RdoRules::canViewReportOf('developer', $this->userDev, $this->userA));
        $this->assertTrue(RdoRules::canViewReportOf('developer', $this->userDev, $this->userDev));
    }

    // ================= Anexos e colaboradores =================

    public function testAnexosCrud(): void
    {
        $id = $this->novoRdo($this->userA);
        $attId = $this->model->addAttachment([
            'report_id' => $id, 'user_id' => $this->userA,
            'file_name' => 'foto.jpg', 'file_path' => 'uploads/rdo/foto.jpg',
            'file_type' => 'image/jpeg', 'file_size' => 1234,
        ]);
        $this->assertNotEmpty($attId);
        $anexos = $this->model->getAttachments($id);
        $this->assertCount(1, $anexos);
        $this->assertSame('foto.jpg', $anexos[0]['file_name']);

        $this->model->deleteAttachment($attId);
        $this->assertCount(0, $this->model->getAttachments($id));
    }

    public function testColaboradoresReplace(): void
    {
        $id = $this->novoRdo($this->userA);
        $this->model->replaceCollaborators($id, [
            ['collaborator_name' => 'João', 'kind' => 'colaborador', 'notes' => 'suporte'],
            ['collaborator_name' => 'Empresa X', 'kind' => 'prestador', 'notes' => ''],
            ['collaborator_name' => '', 'kind' => 'colaborador'], // vazio é ignorado
        ]);
        $cols = $this->model->getCollaborators($id);
        $this->assertCount(2, $cols);

        // replace substitui (não acumula)
        $this->model->replaceCollaborators($id, [
            ['collaborator_name' => 'Maria', 'kind' => 'colaborador'],
        ]);
        $cols2 = $this->model->getCollaborators($id);
        $this->assertCount(1, $cols2);
        $this->assertSame('Maria', $cols2[0]['collaborator_name']);
    }

    // ================= Projeto / obra (company_id) =================

    public function testGravaELeProjetoDoRdo(): void
    {
        $id = $this->novoRdo($this->userA, ['company_id' => $this->companyX]);
        $r = $this->model->findById($id);
        $this->assertSame($this->companyX, (int) $r['company_id']);
        // findById traz o nome da empresa via JOIN.
        $this->assertStringStartsWith('Obra A', (string) $r['company_name']);
    }

    public function testRdoSemProjetoFicaComCompanyNull(): void
    {
        $id = $this->novoRdo($this->userA); // sem company_id
        $r = $this->model->findById($id);
        $this->assertNull($r['company_id']);
    }

    public function testFiltroPorProjeto(): void
    {
        $this->novoRdo($this->userA, ['company_id' => $this->companyX, 'activities' => 'RDO da obra A']);
        $this->novoRdo($this->userA, ['company_id' => $this->companyY, 'activities' => 'RDO da obra B']);
        $this->novoRdo($this->userA); // sem projeto

        $soX = $this->model->getList(['user_id' => $this->userA, 'company_id' => $this->companyX]);
        $this->assertCount(1, $soX);
        $this->assertSame($this->companyX, (int) $soX[0]['company_id']);
        $this->assertStringContainsString('obra A', $soX[0]['activities']);
    }

    public function testListagemVemAgrupadaPorProjeto(): void
    {
        // Cria fora de ordem alfabética para provar que o ORDER BY agrupa.
        $this->novoRdo($this->userA, ['company_id' => $this->companyY]); // Obra B
        $this->novoRdo($this->userA);                                    // Sem projeto
        $this->novoRdo($this->userA, ['company_id' => $this->companyX]); // Obra A
        $this->novoRdo($this->userA, ['company_id' => $this->companyY]); // Obra B (2)

        $lista = $this->model->getList(['user_id' => $this->userA]);

        // A sequência de company_id deve estar agrupada: todos de um projeto
        // ficam contíguos, empresas em ordem de nome (A antes de B), e os
        // "sem projeto" (null) por último.
        $seq = array_map(fn($r) => $r['company_id'] === null ? null : (int) $r['company_id'], $lista);

        // Índices por grupo
        $posA = array_keys($seq, $this->companyX, true);
        $posB = array_keys($seq, $this->companyY, true);
        $posNull = array_keys($seq, null, true);

        // Cada grupo é contíguo (max-min+1 == count).
        foreach ([$posA, $posB, $posNull] as $pos) {
            if (count($pos) > 1) {
                $this->assertSame(count($pos), max($pos) - min($pos) + 1, 'Grupo do projeto deve ser contíguo');
            }
        }
        // Obra A vem antes de Obra B, e null é o último grupo.
        $this->assertLessThan(min($posB), max($posA), 'Obra A deve vir antes de Obra B');
        $this->assertGreaterThan(max($posB), min($posNull), 'Sem projeto deve vir por último');
    }

    public function testStatsRespeitaFiltroDeProjeto(): void
    {
        $this->novoRdo($this->userA, ['company_id' => $this->companyX, 'status' => 'finalizado']);
        $this->novoRdo($this->userA, ['company_id' => $this->companyX, 'status' => 'em_andamento']);
        $this->novoRdo($this->userA, ['company_id' => $this->companyY, 'status' => 'finalizado']);

        $statsX = $this->model->getStats(['user_id' => $this->userA, 'company_id' => $this->companyX]);
        $this->assertSame(2, $statsX['total']);
        $this->assertSame(1, $statsX['finalizado']);
        $this->assertSame(1, $statsX['em_andamento']);
    }
}
