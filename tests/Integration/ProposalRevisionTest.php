<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Proposal;
use ProposalResponse;

/**
 * Versões da proposta e aceite pelo link público (change 02, tarefas 2.2,
 * 2.3 e 2.5): R01 recusada gera a R02 sem perder a R01, o link antigo não
 * aceita resposta e o aceite grava nome, IP, navegador, versão e move o card.
 */
final class ProposalRevisionTest extends TestCase
{
    use CommercialFixtures;

    private Proposal $model;
    private int $userId;

    protected function setUp(): void
    {
        $this->requireTestDb();
        $this->model = new Proposal();
        $this->userId = $this->newUser();
    }

    protected function tearDown(): void
    {
        $this->trackWhere('notifications', 'user_id = ?', [$this->userId]);
        $this->cleanupCreated();
    }

    private function revisionOf(int $id): int
    {
        $new = $this->model->createRevision($id, $this->userId);
        $this->assertNotNull($new);
        $this->track('proposals', $new);
        $this->trackWhere('proposal_items', 'proposal_id = ?', [$new]);
        return $new;
    }

    public function testRecusadaGeraR02EPreservaR01(): void
    {
        $r1 = $this->newProposal($this->userId, ['status' => 'rejected', 'reject_reason' => 'caro'],
            [['Site', 10000, 'once'], ['Hospedagem', 300, 'monthly']]);
        $r2 = $this->revisionOf($r1);

        $old = $this->model->findById($r1);
        $new = $this->model->findById($r2);
        $this->assertSame($r2, (int)$old['superseded_by']);
        $this->assertSame('rejected', $old['status']);
        $this->assertSame('caro', $old['reject_reason']);
        $this->assertSame('draft', $new['status']);
        $this->assertSame(2, (int)$new['revision_no']);
        $this->assertSame($r1, (int)$new['root_proposal_id']);
        $this->assertNotSame($old['public_token'], $new['public_token']);
        $this->assertNull($new['reject_reason']);

        // Itens copiados, e os da R01 continuam intactos.
        $this->assertSame(['Site', 'Hospedagem'], array_column($this->model->getItems($r2), 'description'));
        $this->assertSame(['Site', 'Hospedagem'], array_column($this->model->getItems($r1), 'description'));
        $this->model->replaceItems($r2, [['description' => 'Site novo', 'amount' => 9000]]);
        $this->assertSame(10300.0, (float)$this->model->findById($r1)['total']);
        $this->assertSame(['Site', 'Hospedagem'], array_column($this->model->getItems($r1), 'description'));

        // A R01 não gera outra versão (está substituída).
        $this->assertNull($this->model->createRevision($r1, $this->userId));
    }

    public function testTresVersoesNoHistorico(): void
    {
        $r1 = $this->newProposal($this->userId, ['status' => 'sent']);
        $r2 = $this->revisionOf($r1);
        $this->db->update('proposals', ['status' => 'awaiting'], 'id = ?', [$r2]);
        $r3 = $this->revisionOf($r2);

        $revs = $this->model->getRevisions($this->model->findById($r3));
        $this->assertSame([1, 2, 3], array_map('intval', array_column($revs, 'revision_no')));
        $this->assertSame([$r1, $r2, $r3], array_map('intval', array_column($revs, 'id')));
        // Também a partir da R01.
        $this->assertCount(3, $this->model->getRevisions($this->model->findById($r1)));
    }

    public function testRascunhoNaoCriaVersao(): void
    {
        $id = $this->newProposal($this->userId, ['status' => 'draft']);
        $this->assertNull($this->model->createRevision($id, $this->userId));
        $this->assertNull($this->model->findById($id)['superseded_by']);
    }

    public function testLinkAntigoNaoAceitaNemRecusa(): void
    {
        $r1 = $this->newProposal($this->userId, ['status' => 'sent']);
        $this->revisionOf($r1);
        $old = $this->model->findByToken($this->model->findById($r1)['public_token']);

        $svc = new ProposalResponse($this->model);
        $acc = $svc->accept($old, ['name' => 'Fulano', 'ip' => '10.0.0.1', 'user_agent' => 'X']);
        $this->assertFalse($acc['ok']);
        $this->assertSame(409, $acc['code']);
        $this->assertStringContainsString('versão mais nova', $acc['error']);
        $rej = $svc->reject($old, 'caro');
        $this->assertFalse($rej['ok']);
        $this->assertSame('sent', $this->model->findById($r1)['status']);
    }

    public function testAceiteValidoRegistraEMoveCard(): void
    {
        $board = $this->insertRow('crm_boards', ['name' => 'Comercial ' . uniqid()]);
        $colA = $this->insertRow('crm_columns', ['board_id' => $board, 'name' => 'Proposta enviada', 'position' => 1]);
        $colWon = $this->insertRow('crm_columns', ['board_id' => $board, 'name' => 'Fechado', 'position' => 5]);
        $card = $this->insertRow('crm_cards', ['column_id' => $colA, 'title' => 'Lead X']);
        $this->trackWhere('crm_card_activities', 'card_id = ?', [$card]);

        $r1 = $this->newProposal($this->userId, ['status' => 'sent', 'card_id' => $card, 'validity_date' => date('Y-m-d')]);
        $r2 = $this->revisionOf($r1);
        $this->db->update('proposals', ['status' => 'sent'], 'id = ?', [$r2]);

        $r = (new ProposalResponse($this->model))->accept($this->model->findById($r2), [
            'name' => '  Maria   Souza ', 'ip' => '200.1.2.3', 'user_agent' => 'Mozilla/5.0 Teste',
        ]);
        $this->trackWhere('crm_card_activities', 'card_id = ?', [$card]);
        $this->assertTrue($r['ok'], $r['error'] ?? '');
        $this->assertSame('R02', $r['revision']);

        $p = $this->model->findById($r2);
        $this->assertSame('accepted', $p['status']);
        $this->assertSame('Maria Souza', $p['accepted_name']);
        $this->assertSame('200.1.2.3', $p['accepted_ip']);
        $this->assertSame('Mozilla/5.0 Teste', $p['accepted_user_agent']);
        $this->assertSame(2, (int)$p['accepted_revision']);
        $this->assertNotNull($p['responded_at']);

        $c = $this->db->fetch('SELECT * FROM crm_cards WHERE id = ?', [$card]);
        $this->assertSame($colWon, (int)$c['column_id']);
        $this->assertSame('converted', $c['lead_outcome']);
        $n = $this->db->fetch("SELECT * FROM notifications WHERE user_id = ? AND title = 'Proposta aceita'", [$this->userId]);
        $this->assertNotFalse($n);
    }

    public function testQuadroSemColunaDeGanhoConcluiSemMover(): void
    {
        $board = $this->insertRow('crm_boards', ['name' => 'Suporte ' . uniqid()]);
        $col = $this->insertRow('crm_columns', ['board_id' => $board, 'name' => 'Em conversa', 'position' => 1]);
        $card = $this->insertRow('crm_cards', ['column_id' => $col, 'title' => 'Lead Y']);
        $id = $this->newProposal($this->userId, ['status' => 'awaiting', 'card_id' => $card]);

        $r = (new ProposalResponse($this->model))->accept($this->model->findById($id), ['name' => 'João', 'ip' => '1.1.1.1']);
        $this->assertTrue($r['ok']);
        $this->assertFalse($r['card']['moved']);
        $this->assertSame('accepted', $this->model->findById($id)['status']);
        $this->assertSame($col, (int)$this->db->fetch('SELECT column_id FROM crm_cards WHERE id = ?', [$card])['column_id']);
        $ev = $this->db->fetch("SELECT * FROM proposal_events WHERE proposal_id = ? AND event_type = 'crm'", [$id]);
        $this->assertStringContainsString('não tem coluna de ganho', $ev['description']);
    }

    public function testPropostaVencidaNaoAceita(): void
    {
        $id = $this->newProposal($this->userId, ['status' => 'sent', 'validity_date' => date('Y-m-d', strtotime('-1 day'))]);
        $r = (new ProposalResponse($this->model))->accept($this->model->findById($id), ['name' => 'João']);
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('venceu', $r['error']);
        $this->assertSame('sent', $this->model->findById($id)['status']);
    }

    public function testAceiteSemNomeERecusado(): void
    {
        $id = $this->newProposal($this->userId, ['status' => 'sent']);
        $r = (new ProposalResponse($this->model))->accept($this->model->findById($id), ['name' => '   ']);
        $this->assertFalse($r['ok']);
        $this->assertSame(400, $r['code']);
        $this->assertSame('sent', $this->model->findById($id)['status']);
    }
}
