<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use OnboardingCredentialLink;
use ClientCredential;
use ProvisioningRules;

/**
 * Link de credenciais do onboarding e pipeline no provisionamento (change 02,
 * tarefas 5.2 e 5.3).
 */
final class OnboardingCredentialLinkTest extends TestCase
{
    use CommercialFixtures;

    private OnboardingCredentialLink $links;
    private int $userId;
    private int $company;
    private int $onb;

    protected function setUp(): void
    {
        $this->requireTestDb();
        $this->links = new OnboardingCredentialLink();
        $this->userId = $this->newUser();
        $this->company = $this->newCompany();
        $this->onb = $this->insertRow('onboardings', ['company_id' => $this->company, 'title' => 'Onb teste', 'status' => 'in_progress', 'created_by' => $this->userId]);
        $this->setSetting('credentials_key', str_repeat('k', 40));
    }

    protected function tearDown(): void
    {
        $this->trackWhere('notifications', 'user_id = ?', [$this->userId]);
        $this->trackWhere('client_credentials', 'onboarding_id = ?', [$this->onb]);
        $this->trackWhere('provisionings', 'onboarding_id = ?', [$this->onb]);
        $this->setSetting('credentials_key', '');
        $this->cleanupCreated();
    }

    private function generate(): array
    {
        $r = $this->links->generate($this->onb, $this->userId);
        $this->assertTrue($r['ok'], $r['error'] ?? '');
        $this->track('onboarding_credential_links', $r['id']);
        return $r;
    }

    public function testLinkValidoGravaNoCofreCriptografadoComIp(): void
    {
        $r = $this->generate();
        $row = $this->db->fetch('SELECT * FROM onboarding_credential_links WHERE id = ?', [$r['id']]);
        $this->assertSame(hash('sha256', $r['token']), $row['token_hash'], 'só o hash fica no banco');
        $this->assertEqualsWithDelta(time() + 7 * 86400, strtotime($row['expires_at']), 5);

        $s = $this->links->submit($r['token'], ['service_label' => 'Hospedagem', 'username' => 'admin', 'secret' => 'S3nh@-Teste', 'notes' => 'painel'], '200.10.20.30');
        $this->assertTrue($s['ok'], $s['error'] ?? '');
        $cred = $this->db->fetch('SELECT * FROM client_credentials WHERE id = ?', [$s['credential_id']]);
        $this->assertSame($this->company, (int)$cred['company_id']);
        $this->assertSame($this->onb, (int)$cred['onboarding_id']);
        $this->assertStringNotContainsString('S3nh@-Teste', (string)$cred['secret_encrypted']);
        $this->assertSame('S3nh@-Teste', (new ClientCredential())->revealSecret($s['credential_id']));

        $row = $this->db->fetch('SELECT * FROM onboarding_credential_links WHERE id = ?', [$r['id']]);
        $this->assertSame('200.10.20.30', $row['last_used_ip']);
        $this->assertSame(1, (int)$row['use_count']);
        $ev = $this->db->fetch("SELECT * FROM onboarding_events WHERE onboarding_id = ? AND event_type = 'credential_received'", [$this->onb]);
        $this->assertStringContainsString('200.10.20.30', $ev['description']);
        $this->assertStringNotContainsString('S3nh@', $ev['description']);
    }

    public function testLinkVencido(): void
    {
        $r = $this->generate();
        $this->db->update('onboarding_credential_links', ['expires_at' => date('Y-m-d H:i:s', time() - 60)], 'id = ?', [$r['id']]);
        $this->assertSame('expired', $this->links->check($r['token'])['reason']);
        $s = $this->links->submit($r['token'], ['service_label' => 'X', 'secret' => 'y'], '1.1.1.1');
        $this->assertFalse($s['ok']);
        $this->assertSame(0, (int)$this->db->fetch('SELECT COUNT(*) n FROM client_credentials WHERE onboarding_id = ?', [$this->onb])['n']);
    }

    public function testLinkRevogado(): void
    {
        $r = $this->generate();
        $this->assertTrue($this->links->revoke($r['id'], $this->userId));
        $this->assertFalse($this->links->revoke($r['id'], $this->userId));
        $this->assertSame('revoked', $this->links->check($r['token'])['reason']);
        $this->assertSame('invalid', $this->links->check('token-que-nao-existe')['reason']);
    }

    public function testSemChaveDoCofreNaoGeraNemAceita(): void
    {
        $r = $this->generate();
        $this->setSetting('credentials_key', '');
        $g = $this->links->generate($this->onb, $this->userId);
        $this->assertFalse($g['ok']);
        $this->assertStringContainsString('credentials_key', $g['error']);
        $this->assertSame('no_key', $this->links->check($r['token'])['reason']);
    }

    public function testEnvioSemServicoERecusado(): void
    {
        $r = $this->generate();
        $this->assertSame(400, $this->links->submit($r['token'], ['service_label' => ' ', 'secret' => 'x'], '1.1.1.1')['code']);
    }

    public function testProvisionamentoUsaOPipelineDoOnboarding(): void
    {
        $this->db->update('onboardings', ['pipeline_mode' => 'esteira_cx'], 'id = ?', [$this->onb]);
        $onb = (new \Onboarding())->findById($this->onb);
        $pipe = ProvisioningRules::pipelineFor($onb['pipeline_mode'], null);
        $this->assertSame('esteira_cx', $pipe);
        $this->assertSame('esteira_cx', ProvisioningRules::pipelineFor($onb['pipeline_mode'], 'fora_esteira'), 'o onboarding vale mais que o POST');
        $this->assertSame('fora_esteira', ProvisioningRules::pipelineFor(null, null));
        $id = (new \Provisioning())->createFromOnboarding($onb, $this->userId, $pipe);
        $this->assertSame('esteira_cx', $this->db->fetch('SELECT pipeline_mode FROM provisionings WHERE id = ?', [$id])['pipeline_mode']);
    }
}
