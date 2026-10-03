<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Contract;
use ContractTemplate;
use Proposal;
use Database;

/**
 * Testes de integração do Contrato (Fase 4) contra helpdesk_on_test.
 * Cobre: criação a partir de proposta, transições do fluxo em duas etapas,
 * aprovação do cliente, "assinatura" (webhook) e modelos.
 */
final class ContractTest extends TestCase
{
    private Database $db;
    private Contract $model;
    private int $userId;
    private array $contractIds = [];
    private array $proposalIds = [];
    private array $templateIds = [];

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste não configurado.');
        }
        $this->db = Database::getInstance();
        $this->model = new Contract();
        $u = uniqid();
        $this->userId = (int) $this->db->insert('users', [
            'name' => "Com {$u}", 'email' => "c_{$u}@example.test",
            'password' => password_hash('x', PASSWORD_BCRYPT), 'role' => 'comercial',
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->contractIds as $id) {
            try { $this->db->delete('contract_events', 'contract_id = ?', [$id]); } catch (\Throwable $e) {}
            try { $this->db->delete('contracts', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
        foreach ($this->proposalIds as $id) {
            try { $this->db->delete('proposal_events', 'proposal_id = ?', [$id]); } catch (\Throwable $e) {}
            try { $this->db->delete('proposals', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
        foreach ($this->templateIds as $id) {
            try { $this->db->delete('contract_templates', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
        try { $this->db->delete('users', 'id = ?', [$this->userId]); } catch (\Throwable $e) {}
    }

    private function novoContrato(array $over = []): int
    {
        $id = $this->model->create(array_merge([
            'public_token' => $this->model->generateToken(),
            'title' => 'Contrato teste',
            'client_name' => 'Cliente',
            'client_email' => 'cliente@x.com',
            'body' => '<p>Cláusulas…</p>',
            'status' => 'draft',
            'created_by' => $this->userId,
        ], $over));
        $this->contractIds[] = $id;
        return $id;
    }

    public function testCriaDePropostaAceitaComModelo(): void
    {
        // Proposta aceita.
        $pid = (new Proposal())->create([
            'public_token' => bin2hex(random_bytes(8)), 'title' => 'Projeto X',
            'client_name' => 'Cliente X', 'client_email' => 'x@x.com',
            'status' => 'accepted', 'total' => 1000, 'created_by' => $this->userId,
        ]);
        $this->proposalIds[] = $pid;
        $proposal = (new Proposal())->findById($pid);

        $tid = (new ContractTemplate())->create(['name' => 'Padrão', 'body' => '<h1>Contrato</h1>', 'active' => 1]);
        $this->templateIds[] = $tid;
        $template = (new ContractTemplate())->findById($tid);

        $cid = $this->model->createFromProposal($proposal, $template, $this->userId);
        $this->contractIds[] = $cid;

        $c = $this->model->findById($cid);
        $this->assertSame('draft', $c['status']);
        $this->assertSame((int)$pid, (int)$c['proposal_id']);
        $this->assertStringContainsString('Contrato', $c['body']);
        $this->assertSame('Cliente X', $c['client_name']);
        $this->assertNotEmpty($c['public_token']);
    }

    public function testFluxoCompletoAteAssinado(): void
    {
        $id = $this->novoContrato();
        // draft -> client_review -> approved -> awaiting_signature -> signed
        $this->assertTrue($this->model->changeStatus($id, 'client_review', $this->userId));
        $this->assertTrue($this->model->changeStatus($id, 'approved', null, ['approved_at' => date('Y-m-d H:i:s')]));
        $this->assertTrue($this->model->changeStatus($id, 'awaiting_signature', $this->userId, ['clicksign_doc_key' => 'DOC123']));
        // Assinatura confirmada (simula o webhook).
        $this->assertTrue($this->model->changeStatus($id, 'signed', null, ['signed_at' => date('Y-m-d H:i:s')]));
        $c = $this->model->findById($id);
        $this->assertSame('signed', $c['status']);
        $this->assertNotEmpty($c['signed_at']);
        // Localiza por chave ClickSign (usado pelo webhook).
        $this->assertSame($id, (int)$this->model->findByClickSignDocKey('DOC123')['id']);
    }

    public function testNaoAssinaSemAprovar(): void
    {
        $id = $this->novoContrato();
        $this->model->changeStatus($id, 'client_review', $this->userId);
        // review -> signed é inválido (precisa aprovar + assinatura).
        $this->assertFalse($this->model->changeStatus($id, 'signed', null));
        $this->assertSame('client_review', $this->model->findById($id)['status']);
    }

    public function testClienteRecusaVoltaParaElaboracao(): void
    {
        $id = $this->novoContrato();
        $this->model->changeStatus($id, 'client_review', $this->userId);
        $this->assertTrue($this->model->changeStatus($id, 'client_rejected', null, ['reject_reason' => 'Ajustar prazo']));
        $c = $this->model->findById($id);
        $this->assertSame('Ajustar prazo', $c['reject_reason']);
        // Volta à elaboração para refazer.
        $this->assertTrue($this->model->changeStatus($id, 'draft', $this->userId));
    }

    public function testTemplateCrudEToggle(): void
    {
        $m = new ContractTemplate();
        $tid = $m->create(['name' => 'CLT', 'body' => '<p>...</p>', 'active' => 1]);
        $this->templateIds[] = $tid;
        $this->assertSame(1, (int)$m->findById($tid)['active']);
        $m->toggleActive($tid);
        $this->assertSame(0, (int)$m->findById($tid)['active']);
    }
}
