<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Project;
use LeadConversion;
use TicketProjectScope;
use ClientPortal;

/**
 * Projeto, chamado e portal da change 02 (tarefas 6.1 a 6.4).
 */
final class ClientProjectFlowTest extends TestCase
{
    use CommercialFixtures;

    private int $userId;

    protected function setUp(): void
    {
        $this->requireTestDb();
        $this->userId = $this->newUser();
    }

    protected function tearDown(): void
    {
        foreach ($this->created as [$table, $id]) {
            if ($table === 'onboardings') $this->trackWhere('projects', 'onboarding_id = ?', [$id]);
        }
        $this->cleanupCreated();
    }

    /** Cadeia proposta aceita -> contrato -> financeiro -> onboarding, sem empresa. */
    private function chain(string $contractType = 'dev_zero', ?int $company = null): array
    {
        $p = $this->newProposal($this->userId, ['status' => 'accepted', 'contract_type' => $contractType, 'company_id' => $company, 'title' => 'Loja ACME'],
            [['Loja virtual', 8000, 'once'], ['Suporte mensal', 400, 'monthly']]);
        $c = $this->newContract($this->userId, ['status' => 'signed', 'proposal_id' => $p, 'company_id' => $company]);
        $f = $this->newFinanceProject($this->userId, ['contract_id' => $c, 'proposal_id' => $p, 'company_id' => $company]);
        $o = $this->insertRow('onboardings', ['project_id' => $f, 'contract_id' => $c, 'company_id' => $company, 'title' => 'Onboarding — Loja ACME', 'status' => 'done', 'created_by' => $this->userId]);
        return ['proposal' => $p, 'contract' => $c, 'finance' => $f, 'onboarding' => $o];
    }

    public function testProjetoNasceUmaVezAoConcluirOOnboarding(): void
    {
        $company = $this->newCompany();
        $ch = $this->chain('dev_suporte', $company);
        $onb = (new \Onboarding())->findById($ch['onboarding']);
        $a = (new Project())->ensureForOnboarding($onb, $this->userId);
        $b = (new Project())->ensureForOnboarding($onb, $this->userId);
        $this->assertTrue($a['created']);
        $this->assertFalse($b['created']);
        $this->assertSame($a['id'], $b['id']);
        $p = (new Project())->findById($a['id']);
        $this->assertSame($company, (int)$p['company_id']);
        $this->assertSame($ch['contract'], (int)$p['contract_id']);
        $this->assertSame($ch['proposal'], (int)$p['proposal_id']);
        $this->assertSame('Loja ACME', $p['name']);
        $this->assertSame('zero', $p['contract_type']);
        $this->assertSame(1, (int)$p['support_contract']);
        $this->assertSame(1, (int)$this->db->fetch('SELECT COUNT(*) n FROM projects WHERE onboarding_id = ?', [$ch['onboarding']])['n']);
    }

    public function testConversaoGravaAEmpresaNaCadeia(): void
    {
        $ch = $this->chain();
        $r2 = $this->newProposal($this->userId, ['status' => 'draft', 'root_proposal_id' => $ch['proposal'], 'revision_no' => 2]);
        $project = (new Project())->ensureForOnboarding((new \Onboarding())->findById($ch['onboarding']), $this->userId);
        $this->track('projects', $project['id']);
        $other = $this->newCompany();
        $manual = $this->newFinanceProject($this->userId, ['contract_id' => $ch['contract'], 'company_id' => $other]);

        $email = 'conv_' . uniqid() . '@example.test';
        $conv = (new LeadConversion())->convert(['company_name' => 'Empresa Conv ' . uniqid(), 'name' => 'Dono', 'email' => $email,
            'contract_id' => $ch['contract'], 'onboarding_id' => $ch['onboarding']], $this->userId);
        $this->track('companies', $conv['company_id']);
        if ($conv['user_id']) $this->track('users', $conv['user_id']);
        $this->trackWhere('lead_company_links', 'company_id = ?', [$conv['company_id']]);
        $cid = $conv['company_id'];

        foreach ([['proposals', $ch['proposal']], ['proposals', $r2], ['contracts', $ch['contract']], ['finance_projects', $ch['finance']],
                  ['onboardings', $ch['onboarding']], ['projects', $project['id']]] as [$t, $id]) {
            $this->assertSame($cid, (int)$this->db->fetch("SELECT company_id FROM {$t} WHERE id = ?", [$id])['company_id'], "{$t} #{$id}");
        }
        $this->assertSame($other, (int)$this->db->fetch('SELECT company_id FROM finance_projects WHERE id = ?', [$manual])['company_id'], 'vínculo manual não muda');
    }

    private function projectRow(int $company, array $over = []): int
    {
        $prop = $this->newProposal($this->userId, ['status' => 'accepted'], [['Site', 5000, 'once'], ['Hospedagem', 100, 'monthly']]);
        return $this->insertRow('projects', array_merge([
            'company_id' => $company, 'proposal_id' => $prop, 'name' => 'Projeto ' . uniqid(), 'contract_type' => 'zero',
            'status' => 'warranty', 'warranty_days' => 90, 'warranty_ends_at' => date('Y-m-d H:i:s', strtotime('+30 days')), 'support_contract' => 0,
        ], $over));
    }

    public function testChamadoComProjetoUnicoEServico(): void
    {
        $company = $this->newCompany();
        $pid = $this->projectRow($company);
        $scope = new TicketProjectScope();
        $item = (int)$this->db->fetch('SELECT pi.id FROM proposal_items pi JOIN projects p ON p.proposal_id = pi.proposal_id WHERE p.id = ? ORDER BY pi.id DESC LIMIT 1', [$pid])['id'];

        $r = $scope->resolve(true, $company, null, $item);
        $this->assertTrue($r['ok']);
        $this->assertSame($pid, $r['project_id'], 'projeto único vem selecionado');
        $this->assertSame($item, $r['proposal_item_id']);

        $this->assertFalse($scope->resolve(true, $company, $pid, 999999)['ok'], 'serviço de outra proposta');
        $other = $this->newCompany();
        $this->assertFalse($scope->resolve(true, $other, $pid, null)['ok'], 'projeto de outra empresa');
        $this->assertNull($scope->resolve(true, $other, null, null)['project_id'], 'empresa sem projeto: chamado sem projeto');
    }

    public function testGarantiaVencidaSemSuporteBloqueiaClienteEAvisaAtendente(): void
    {
        $company = $this->newCompany();
        $pid = $this->projectRow($company, ['warranty_ends_at' => date('Y-m-d H:i:s', strtotime('-1 day'))]);
        $scope = new TicketProjectScope();

        $client = $scope->resolve(true, $company, $pid, null);
        $this->assertFalse($client['ok']);
        $this->assertStringContainsString('contratar o suporte', $client['error']);

        $staff = $scope->resolve(false, $company, $pid, null);
        $this->assertTrue($staff['ok']);
        $this->assertSame($pid, $staff['project_id']);
        $this->assertStringContainsString('fora da cobertura', $staff['warning']);

        $this->db->update('projects', ['support_contract' => 1], 'id = ?', [$pid]);
        $withSupport = $scope->resolve(true, $company, $pid, null);
        $this->assertTrue($withSupport['ok']);
        $this->assertNull($withSupport['warning']);
    }

    public function testPortalMostraSoAEmpresaDoUsuario(): void
    {
        $a = $this->newCompany();
        $b = $this->newCompany();
        $ca = $this->newContract($this->userId, ['status' => 'signed', 'company_id' => $a, 'signed_at' => date('Y-m-d H:i:s')]);
        $cb = $this->newContract($this->userId, ['status' => 'signed', 'company_id' => $b, 'signed_at' => date('Y-m-d H:i:s')]);
        $this->newContract($this->userId, ['status' => 'draft', 'company_id' => $a]);
        $fa = $this->newFinanceProject($this->userId, ['company_id' => $a]);
        $fb = $this->newFinanceProject($this->userId, ['company_id' => $b]);
        $this->insertRow('finance_charges', ['project_id' => $fa, 'kind' => 'entry', 'description' => 'Entrada A', 'amount' => 100, 'due_date' => '2026-11-01',
            'status' => 'pending', 'asaas_charge_id' => 'pay_a_' . uniqid(), 'invoice_url' => 'https://sandbox.asaas.test/i/a']);
        $this->insertRow('finance_charges', ['project_id' => $fb, 'kind' => 'entry', 'description' => 'Entrada B', 'amount' => 200, 'status' => 'pending']);
        $this->projectRow($a);

        $portal = new ClientPortal();
        $this->assertSame([$ca], array_map('intval', array_column($portal->contracts($a), 'id')));
        $this->assertNotNull($portal->findContract($a, $ca));
        $this->assertNull($portal->findContract($a, $cb), 'contrato de outra empresa pela URL');
        $charges = $portal->charges($a);
        $this->assertSame(['Entrada A'], array_column($charges, 'description'));
        $this->assertSame('https://sandbox.asaas.test/i/a', $charges[0]['invoice_url']);
        $this->assertCount(1, $portal->projects($a));
        $this->assertCount(0, $portal->projects($b));
    }
}
