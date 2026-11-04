<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use DailyReport;
use RdoRules;
use RdoReportRules;
use Database;

/**
 * Testes de integração da consolidação usada na EXPORTAÇÃO do relatório de RDO
 * em PDF (RdoController::exportPdf), contra o banco helpdesk_on_test.
 *
 * Não renderiza o PDF (dompdf/IA ficam fora): valida a camada de DADOS que o
 * controller monta — filtro por período (date_from/date_to), carga de
 * colaboradores e separação dos anexos de IMAGEM (que viram "fotografias" no
 * PDF) dos demais anexos. Cria os próprios dados no setUp e limpa no tearDown.
 */
final class RdoExportTest extends TestCase
{
    private Database $db;
    private DailyReport $model;
    private int $admin;
    private int $companyX;
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
        $this->admin = (int) $this->db->insert('users', [
            'name' => "Admin {$u}", 'email' => "adm_{$u}@ex.test",
            'password' => password_hash('x', PASSWORD_BCRYPT), 'role' => 'super_admin',
        ]);
        $this->companyX = (int) $this->db->insert('companies', ['name' => "Cliente Export {$u}"]);
        $this->companyIds[] = $this->companyX;
    }

    protected function tearDown(): void
    {
        foreach ($this->reportIds as $id) {
            try { $this->db->delete('daily_report_attachments', 'report_id = ?', [$id]); } catch (\Throwable $e) {}
            try { $this->db->delete('daily_report_collaborators', 'report_id = ?', [$id]); } catch (\Throwable $e) {}
            try { $this->db->delete('daily_reports', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
        try { $this->db->delete('daily_reports', 'user_id = ?', [$this->admin]); } catch (\Throwable $e) {}
        try { $this->db->delete('users', 'id = ?', [$this->admin]); } catch (\Throwable $e) {}
        foreach ($this->companyIds as $id) {
            try { $this->db->delete('companies', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
    }

    private function novoRdo(string $date, array $ov = []): int
    {
        $occ = $ov['occurrences'] ?? null;
        $id = (int) $this->model->create(array_merge([
            'user_id'        => $this->admin,
            'company_id'     => $this->companyX,
            'report_date'    => $date,
            'title'          => 'Relatório',
            'activities'     => 'Atividades do dia',
            'occurrences'    => $occ,
            'has_occurrence' => RdoRules::deriveHasOccurrence($occ),
            'status'         => 'em_andamento',
        ], $ov));
        $this->reportIds[] = $id;
        return $id;
    }

    /**
     * Reproduz a consolidação do controller: busca por período e, para cada
     * RDO, anexa colaboradores e separa as fotos (anexos de imagem).
     */
    private function consolida(string $from, string $to): array
    {
        $reports = $this->model->getList(['date_from' => $from, 'date_to' => $to]);
        foreach ($reports as &$r) {
            $r['collaborators'] = $this->model->getCollaborators((int) $r['id']);
            $photos = [];
            foreach ($this->model->getAttachments((int) $r['id']) as $att) {
                if (RdoReportRules::isImageAttachment($att)) {
                    $photos[] = $att;
                }
            }
            $r['photos'] = $photos;
        }
        unset($r);
        return $reports;
    }

    public function testPeriodoRecortaApenasOsRdosDentroDoIntervalo(): void
    {
        $this->novoRdo('2026-10-05', ['title' => 'Antes']);   // fora (antes)
        $dentro1 = $this->novoRdo('2026-10-08', ['title' => 'Início']);
        $dentro2 = $this->novoRdo('2026-10-23', ['title' => 'Fim']);
        $this->novoRdo('2026-10-24', ['title' => 'Depois']);  // fora (depois)

        $reports = $this->consolida('2026-10-08', '2026-10-23');
        $ids = array_map(fn($r) => (int) $r['id'], $reports);

        // Só os deste caso de teste, dentro do intervalo (inclusivo nas bordas).
        $meus = array_values(array_filter($ids, fn($id) => in_array($id, $this->reportIds, true)));
        $this->assertContains($dentro1, $meus);
        $this->assertContains($dentro2, $meus);
        $this->assertCount(2, array_intersect($this->reportIds, $meus));
    }

    public function testBordasDoPeriodoSaoInclusivas(): void
    {
        $id = $this->novoRdo('2026-10-08');
        $reports = $this->consolida('2026-10-08', '2026-10-08');
        $ids = array_map(fn($r) => (int) $r['id'], $reports);
        $this->assertContains($id, $ids);
    }

    public function testColaboradoresEntramNaConsolidacao(): void
    {
        $id = $this->novoRdo('2026-10-10');
        $this->model->replaceCollaborators($id, [
            ['collaborator_name' => 'Edilawson', 'kind' => 'prestador', 'notes' => 'Forro'],
            ['collaborator_name' => 'Maria',     'kind' => 'colaborador'],
        ]);

        $reports = $this->consolida('2026-10-10', '2026-10-10');
        $meu = null;
        foreach ($reports as $r) { if ((int) $r['id'] === $id) { $meu = $r; break; } }
        $this->assertNotNull($meu);
        $this->assertCount(2, $meu['collaborators']);
        $names = array_map(fn($c) => $c['collaborator_name'], $meu['collaborators']);
        $this->assertContains('Edilawson', $names);
    }

    public function testApenasAnexosDeImagemViramFotos(): void
    {
        $id = $this->novoRdo('2026-10-12');
        // Uma imagem, um áudio e um PDF: só a imagem deve virar "foto".
        $this->model->addAttachment([
            'report_id' => $id, 'user_id' => $this->admin,
            'file_name' => 'foto.jpg', 'file_path' => 'uploads/rdo/foto_test.jpg',
            'file_type' => 'image/jpeg', 'file_size' => 1000,
        ]);
        $this->model->addAttachment([
            'report_id' => $id, 'user_id' => $this->admin,
            'file_name' => 'audio.webm', 'file_path' => 'uploads/rdo/audio_test.webm',
            'file_type' => 'audio/webm', 'file_size' => 2000,
        ]);
        $this->model->addAttachment([
            'report_id' => $id, 'user_id' => $this->admin,
            'file_name' => 'doc.pdf', 'file_path' => 'uploads/rdo/doc_test.pdf',
            'file_type' => 'application/pdf', 'file_size' => 3000,
        ]);

        $reports = $this->consolida('2026-10-12', '2026-10-12');
        $meu = null;
        foreach ($reports as $r) { if ((int) $r['id'] === $id) { $meu = $r; break; } }
        $this->assertNotNull($meu);
        $this->assertCount(1, $meu['photos']);
        $this->assertSame('foto.jpg', $meu['photos'][0]['file_name']);
    }

    public function testPeriodoSemRdosRetornaVazio(): void
    {
        // Intervalo distante no passado onde este teste não criou nada.
        $reports = $this->consolida('1999-01-01', '1999-01-02');
        $meus = array_filter($reports, fn($r) => in_array((int) $r['id'], $this->reportIds, true));
        $this->assertCount(0, $meus);
    }
}
