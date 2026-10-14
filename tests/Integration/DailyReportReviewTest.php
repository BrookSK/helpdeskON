<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use DailyReport;
use RdoRules;
use Database;
use Company;

/**
 * Testes de integração dos fluxos de revisão e histórico do RDO,
 * contra o banco helpdesk_on_test.
 *
 * Cobre:
 *  - Criação dentro do prazo → sem pendência.
 *  - Criação fora do prazo (late_fill / post_deadline) → pendência automática.
 *  - Campos novos: pending_tasks, next_day_plan, is_locked, review_status.
 *  - Aprovar edit_request → dados propostos aplicados ao relatório.
 *  - Recusar edit_request → relatório original intacto.
 *  - Desbloqueio: aprovar unlock_request → is_locked vira 0.
 *  - hasPendingReviews / resetReviewStatus.
 *  - Histórico (addHistory / getHistory).
 *  - Stats inclui campo 'pendencias'.
 */
final class DailyReportReviewTest extends TestCase
{
    private Database $db;
    private DailyReport $model;
    private int $userA;
    private int $admin;
    /** @var int[] */
    private array $reportIds = [];
    /** @var int[] */
    private array $reviewIds = [];

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste (helpdesk_on_test) não configurado.');
        }
        $this->db    = Database::getInstance();
        $this->model = new DailyReport();

        $u           = uniqid();
        $this->userA = $this->novoUsuario("Colaborador {$u}", "col_{$u}@ex.test", 'developer');
        $this->admin = $this->novoUsuario("Admin {$u}",       "adm_{$u}@ex.test", 'super_admin');
    }

    protected function tearDown(): void
    {
        // Remove revisões, histórico e relatórios criados neste teste
        foreach ($this->reportIds as $id) {
            try { $this->db->delete('daily_report_history',       'report_id = ?', [$id]); } catch (\Throwable $e) {}
            try { $this->db->delete('daily_report_reviews',       'report_id = ?', [$id]); } catch (\Throwable $e) {}
            try { $this->db->delete('daily_report_collaborators', 'report_id = ?', [$id]); } catch (\Throwable $e) {}
            try { $this->db->delete('daily_report_attachments',   'report_id = ?', [$id]); } catch (\Throwable $e) {}
            try { $this->db->delete('daily_reports',              'id = ?',        [$id]); } catch (\Throwable $e) {}
        }
        foreach ([$this->userA, $this->admin] as $uid) {
            try { $this->db->delete('daily_reports', 'user_id = ?', [$uid]); } catch (\Throwable $e) {}
            try { $this->db->delete('users',         'id = ?',      [$uid]); } catch (\Throwable $e) {}
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function novoUsuario(string $name, string $email, string $role): int
    {
        return (int) $this->db->insert('users', [
            'name'     => $name,
            'email'    => $email,
            'password' => password_hash('x', PASSWORD_BCRYPT),
            'role'     => $role,
        ]);
    }

    private function novoRdo(int $userId, array $overrides = []): int
    {
        $occ = $overrides['occurrences'] ?? null;
        $id  = (int) $this->model->create(array_merge([
            'user_id'        => $userId,
            'report_date'    => date('Y-m-d'),
            'title'          => 'Relatório teste',
            'activities'     => 'Atividades do dia',
            'occurrences'    => $occ,
            'has_occurrence' => RdoRules::deriveHasOccurrence($occ),
            'pending_tasks'  => null,
            'next_day_plan'  => null,
            'status'         => 'em_andamento',
            'submitted_at'   => date('Y-m-d H:i:s'),
            'is_locked'      => 0,
            'lock_reason'    => null,
            'review_status'  => 'none',
        ], $overrides));
        $this->reportIds[] = $id;
        return $id;
    }

    private function criarRevisao(int $reportId, string $type, int $requestedBy, ?string $payload = null): int
    {
        $id = $this->model->createReview([
            'report_id'    => $reportId,
            'type'         => $type,
            'requested_by' => $requestedBy,
            'payload_json' => $payload,
        ]);
        $this->reviewIds[] = $id;
        return $id;
    }

    // =========================================================================
    // Campos novos: pending_tasks, next_day_plan
    // =========================================================================

    public function testCamposExtrasSaoPersistitdosELidos(): void
    {
        $id = $this->novoRdo($this->userA, [
            'pending_tasks' => 'Terminar módulo de faturamento',
            'next_day_plan' => 'Revisar PR e fazer deploy',
        ]);
        $r = $this->model->findById($id);
        $this->assertSame('Terminar módulo de faturamento', $r['pending_tasks']);
        $this->assertSame('Revisar PR e fazer deploy',      $r['next_day_plan']);
    }

    public function testCamposExtrasNullPorPadrao(): void
    {
        $id = $this->novoRdo($this->userA);
        $r  = $this->model->findById($id);
        $this->assertNull($r['pending_tasks']);
        $this->assertNull($r['next_day_plan']);
    }

    // =========================================================================
    // is_locked e review_status
    // =========================================================================

    public function testIsLockedEReviewStatusPadrao(): void
    {
        $id = $this->novoRdo($this->userA);
        $r  = $this->model->findById($id);
        $this->assertSame(0,      (int) $r['is_locked']);
        $this->assertSame('none', $r['review_status']);
    }

    public function testBloquearRelatorioAlteraFlags(): void
    {
        $id = $this->novoRdo($this->userA);
        $this->model->update($id, ['is_locked' => 1, 'lock_reason' => 'deadline']);
        $r = $this->model->findById($id);
        $this->assertSame(1,          (int) $r['is_locked']);
        $this->assertSame('deadline', $r['lock_reason']);
    }

    // =========================================================================
    // Criação com pendência automática (late_fill / post_deadline)
    // =========================================================================

    public function testCriacaoDentroDoPrazoNaoGeraRevisao(): void
    {
        // Simula criação dentro do prazo (hoje, antes do deadline)
        $reviewType = RdoRules::reviewTypeForCreate(
            date('Y-m-d'), '08:00:00', date('Y-m-d'), '19:00:00'
        );
        $this->assertNull($reviewType, 'Dentro do prazo não deve gerar revisão');
    }

    public function testCriacaoAposDeadlineGeraLateFillEPendenciaNoDb(): void
    {
        $id = $this->novoRdo($this->userA, ['review_status' => 'pending_review']);

        $reviewId = $this->criarRevisao($id, 'late_fill', $this->userA);

        $r = $this->model->findById($id);
        $this->assertSame('pending_review', $r['review_status']);

        $revisoes = $this->model->getPendingReviewsForReport($id);
        $this->assertCount(1, $revisoes);
        $this->assertSame('late_fill', $revisoes[0]['type']);
        $this->assertSame('pending',   $revisoes[0]['status']);
    }

    public function testCriacaoDataAnteriorGeraPostDeadlineEPendencia(): void
    {
        $ontem = date('Y-m-d', strtotime('-1 day'));
        $id    = $this->novoRdo($this->userA, [
            'report_date'   => $ontem,
            'review_status' => 'pending_review',
        ]);

        $reviewId = $this->criarRevisao($id, 'post_deadline', $this->userA);

        $revisoes = $this->model->getPendingReviewsForReport($id);
        $this->assertCount(1, $revisoes);
        $this->assertSame('post_deadline', $revisoes[0]['type']);
    }

    // =========================================================================
    // getAllPendingReviews
    // =========================================================================

    public function testGetAllPendingReviewsRetornaApenasStatusPending(): void
    {
        $id  = $this->novoRdo($this->userA, ['review_status' => 'pending_review']);
        $r1  = $this->criarRevisao($id, 'late_fill', $this->userA);
        $r2  = $this->criarRevisao($id, 'edit_request', $this->userA, '{"current":{},"proposed":{}}');

        // Aprova um dos dois
        $this->model->updateReview($r1, [
            'status'      => 'approved',
            'reviewed_by' => $this->admin,
            'reviewed_at' => date('Y-m-d H:i:s'),
        ]);

        $pending = $this->model->getAllPendingReviews();
        $ids     = array_column($pending, 'id');
        $this->assertContains($r2, $ids,  'edit_request ainda pending deve aparecer');
        $this->assertNotContains($r1, $ids, 'late_fill aprovado não deve aparecer');
    }

    // =========================================================================
    // Fluxo de aprovação de edit_request
    // =========================================================================

    public function testAprovarEditRequestAplicaPropostoAoRelatorio(): void
    {
        $id = $this->novoRdo($this->userA, [
            'title'         => 'Título original',
            'activities'    => 'Atividades originais',
            'review_status' => 'pending_review',
        ]);

        $payload = json_encode([
            'current'  => ['title' => 'Título original', 'activities' => 'Atividades originais'],
            'proposed' => ['title' => 'Título novo',     'activities' => 'Atividades novas'],
        ]);
        $reviewId = $this->criarRevisao($id, 'edit_request', $this->userA, $payload);

        // Simula aprovação: aplica os campos propostos + marca revisão como approved
        $review   = $this->model->findReview($reviewId);
        $payloadD = json_decode($review['payload_json'], true);
        $proposed = $payloadD['proposed'] ?? [];

        $this->model->update($id, $proposed);
        $this->model->updateReview($reviewId, [
            'status'      => 'approved',
            'reviewed_by' => $this->admin,
            'reviewed_at' => date('Y-m-d H:i:s'),
        ]);

        // Reseta review_status se não há mais pendências
        if (!$this->model->hasPendingReviews($id)) {
            $this->model->update($id, ['review_status' => 'none']);
        }

        $atualizado = $this->model->findById($id);
        $this->assertSame('Título novo',      $atualizado['title']);
        $this->assertSame('Atividades novas', $atualizado['activities']);
        $this->assertSame('none',             $atualizado['review_status']);
    }

    public function testRecusarEditRequestMantemRelatorioOriginal(): void
    {
        $id = $this->novoRdo($this->userA, [
            'title'         => 'Título original',
            'review_status' => 'pending_review',
        ]);

        $payload  = json_encode([
            'current'  => ['title' => 'Título original'],
            'proposed' => ['title' => 'Título modificado — RECUSADO'],
        ]);
        $reviewId = $this->criarRevisao($id, 'edit_request', $this->userA, $payload);

        // Recusa: apenas marca a revisão; NÃO toca no relatório
        $this->model->updateReview($reviewId, [
            'status'      => 'rejected',
            'reviewed_by' => $this->admin,
            'reviewed_at' => date('Y-m-d H:i:s'),
            'notes'       => 'Não autorizado',
        ]);

        if (!$this->model->hasPendingReviews($id)) {
            $this->model->update($id, ['review_status' => 'none']);
        }

        $inalterado = $this->model->findById($id);
        $this->assertSame('Título original', $inalterado['title'], 'Relatório deve permanecer com título original após recusa');
        $this->assertSame('none', $inalterado['review_status']);
    }

    // =========================================================================
    // Fluxo de desbloqueio (unlock_request)
    // =========================================================================

    public function testAprovarUnlockRequestDesbloqueiaRelatorio(): void
    {
        $id = $this->novoRdo($this->userA, [
            'is_locked'     => 1,
            'lock_reason'   => 'deadline',
            'review_status' => 'pending_review',
        ]);

        $reviewId = $this->criarRevisao($id, 'unlock_request', $this->userA);

        // Aprovar: desbloqueia + marca revisão como approved
        $this->model->update($id, ['is_locked' => 0, 'lock_reason' => null]);
        $this->model->updateReview($reviewId, [
            'status'      => 'approved',
            'reviewed_by' => $this->admin,
            'reviewed_at' => date('Y-m-d H:i:s'),
        ]);

        if (!$this->model->hasPendingReviews($id)) {
            $this->model->update($id, ['review_status' => 'none']);
        }

        $desbloqueado = $this->model->findById($id);
        $this->assertSame(0,      (int) $desbloqueado['is_locked']);
        $this->assertNull($desbloqueado['lock_reason']);
        $this->assertSame('none', $desbloqueado['review_status']);
    }

    public function testRecusarUnlockRequestMantemBloqueio(): void
    {
        $id = $this->novoRdo($this->userA, [
            'is_locked'     => 1,
            'lock_reason'   => 'deadline',
            'review_status' => 'pending_review',
        ]);

        $reviewId = $this->criarRevisao($id, 'unlock_request', $this->userA);

        // Recusa: bloqueio permanece
        $this->model->updateReview($reviewId, [
            'status'      => 'rejected',
            'reviewed_by' => $this->admin,
            'reviewed_at' => date('Y-m-d H:i:s'),
            'notes'       => 'Justificativa insuficiente',
        ]);

        if (!$this->model->hasPendingReviews($id)) {
            $this->model->update($id, ['review_status' => 'none']);
        }

        $ainda = $this->model->findById($id);
        $this->assertSame(1,          (int) $ainda['is_locked'], 'Relatório deve continuar bloqueado após recusa');
        $this->assertSame('deadline', $ainda['lock_reason']);
    }

    // =========================================================================
    // hasPendingReviews e resetReviewStatus
    // =========================================================================

    public function testHasPendingReviewsVerdadeiroComPendenteEFalsoSemPendente(): void
    {
        $id = $this->novoRdo($this->userA, ['review_status' => 'pending_review']);
        $r  = $this->criarRevisao($id, 'late_fill', $this->userA);

        $this->assertTrue($this->model->hasPendingReviews($id));

        // Aprova
        $this->model->updateReview($r, ['status' => 'approved', 'reviewed_by' => $this->admin, 'reviewed_at' => date('Y-m-d H:i:s')]);

        $this->assertFalse($this->model->hasPendingReviews($id));
    }

    public function testMultiplasPendenciasHasPendingAteMesmaSemPendente(): void
    {
        $id = $this->novoRdo($this->userA, ['review_status' => 'pending_review']);
        $r1 = $this->criarRevisao($id, 'late_fill',    $this->userA);
        $r2 = $this->criarRevisao($id, 'edit_request', $this->userA);

        $this->assertTrue($this->model->hasPendingReviews($id));

        // Resolve apenas a primeira
        $this->model->updateReview($r1, ['status' => 'approved', 'reviewed_by' => $this->admin, 'reviewed_at' => date('Y-m-d H:i:s')]);
        $this->assertTrue($this->model->hasPendingReviews($id), 'Ainda tem uma pendência');

        // Resolve a segunda
        $this->model->updateReview($r2, ['status' => 'rejected', 'reviewed_by' => $this->admin, 'reviewed_at' => date('Y-m-d H:i:s')]);
        $this->assertFalse($this->model->hasPendingReviews($id), 'Sem mais pendências');
    }

    // =========================================================================
    // Histórico (audit trail)
    // =========================================================================

    public function testHistoricoRegistraAcoesEmOrdem(): void
    {
        $id = $this->novoRdo($this->userA);

        $r = $this->model->findById($id);
        $this->model->addHistory([
            'report_id'     => $id,
            'changed_by'    => $this->userA,
            'action'        => 'created',
            'snapshot_json' => RdoRules::buildSnapshot($r),
        ]);
        // Aguarda 1 segundo para garantir changed_at distinto no banco
        sleep(1);
        $this->model->addHistory([
            'report_id'  => $id,
            'changed_by' => $this->userA,
            'action'     => 'updated',
            'notes'      => 'Corrigido título',
        ]);

        $hist = $this->model->getHistory($id);
        $this->assertCount(2, $hist);
        // getHistory ordena por changed_at DESC (mais recente primeiro)
        $this->assertSame('updated', $hist[0]['action']);
        $this->assertSame('created', $hist[1]['action']);
    }

    public function testHistoricoSnapshotJsonValido(): void
    {
        $id = $this->novoRdo($this->userA, ['title' => 'Snapshot teste']);
        $r  = $this->model->findById($id);
        $this->model->addHistory([
            'report_id'     => $id,
            'changed_by'    => $this->userA,
            'action'        => 'created',
            'snapshot_json' => RdoRules::buildSnapshot($r),
        ]);

        $hist     = $this->model->getHistory($id);
        $snapshot = json_decode($hist[0]['snapshot_json'], true);
        $this->assertIsArray($snapshot);
        $this->assertSame('Snapshot teste', $snapshot['title']);
    }

    public function testHistoricoVazioParaRelatorioSemEventos(): void
    {
        $id   = $this->novoRdo($this->userA);
        $hist = $this->model->getHistory($id);
        $this->assertCount(0, $hist);
    }

    // =========================================================================
    // Stats com campo pendencias
    // =========================================================================

    public function testStatsContaPendencias(): void
    {
        $id1 = $this->novoRdo($this->userA, ['review_status' => 'pending_review']);
        $id2 = $this->novoRdo($this->userA, ['review_status' => 'none']);
        $id3 = $this->novoRdo($this->userA, ['review_status' => 'pending_review']);

        $stats = $this->model->getStats(['user_id' => $this->userA]);
        $this->assertArrayHasKey('pendencias', $stats);
        $this->assertGreaterThanOrEqual(2, $stats['pendencias'], 'Deve contar pelo menos 2 relatórios com pendência');
    }

    public function testStatsSemPendenciasRetornaZero(): void
    {
        $id = $this->novoRdo($this->userA, ['review_status' => 'none']);
        // Cria usuário isolado para não poluir com dados de outros testes
        $u = uniqid();
        $isolado = (int) $this->db->insert('users', [
            'name' => "Iso {$u}", 'email' => "iso_{$u}@ex.test",
            'password' => password_hash('x', PASSWORD_BCRYPT), 'role' => 'developer',
        ]);
        $idIso = (int) $this->model->create([
            'user_id' => $isolado, 'report_date' => date('Y-m-d'),
            'activities' => 'x', 'has_occurrence' => 0,
            'status' => 'em_andamento', 'submitted_at' => date('Y-m-d H:i:s'),
            'is_locked' => 0, 'review_status' => 'none',
        ]);
        $this->reportIds[] = $idIso;

        $stats = $this->model->getStats(['user_id' => $isolado]);
        $this->assertSame(0, $stats['pendencias']);

        // Limpeza do usuário isolado
        $this->db->delete('daily_reports', 'user_id = ?', [$isolado]);
        $this->db->delete('users', 'id = ?', [$isolado]);
    }

    // =========================================================================
    // Filtro review_status na listagem
    // =========================================================================

    public function testFiltroReviewStatusRetornaSomentePendentes(): void
    {
        $idPend = $this->novoRdo($this->userA, ['review_status' => 'pending_review', 'title' => 'Pendente']);
        $idOk   = $this->novoRdo($this->userA, ['review_status' => 'none',           'title' => 'Normal']);

        $lista = $this->model->getList([
            'user_id'       => $this->userA,
            'review_status' => 'pending_review',
        ]);
        $ids = array_column($lista, 'id');
        $this->assertContains($idPend, array_map('intval', $ids));
        $this->assertNotContains($idOk, array_map('intval', $ids));
    }
}
