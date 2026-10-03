<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ProvisioningRules;

/**
 * Testes unitários das regras puras do Provisionamento (Fase 7).
 * Cobre: etapas padrão + modo auto/manual conforme capacidade da API, conclusão
 * de etapa (auto exige apiOk), conclusão do processo, webhook (HMAC + evento).
 */
final class ProvisioningRulesTest extends TestCase
{
    public function testEtapasPadraoRefletemCapacidadeDaApi(): void
    {
        $steps = ProvisioningRules::defaultSteps();
        $byKey = [];
        foreach ($steps as $s) { $byKey[$s['step_key']] = $s; }
        // API LRV Cloud v1.1: os 5 gaps foram implementados -> etapas de infra
        // viram 'auto' (criar cliente/VPS/banco/app/deploy/staging).
        $this->assertSame('auto', $byKey['create_client']['mode']);
        $this->assertSame('auto', $byKey['create_vps']['mode']);
        $this->assertSame('auto', $byKey['create_database']['mode']);
        $this->assertSame('auto', $byKey['create_app']['mode']);
        $this->assertSame('auto', $byKey['deploy']['mode']);
        // Reunir credenciais e entregar continuam sendo ações manuais.
        $this->assertSame('manual', $byKey['collect_credentials']['mode']);
        $this->assertSame('manual', $byKey['deliver']['mode']);
    }

    public function testCapacidadesCustomizadasControlamOModo(): void
    {
        // Se um endpoint ficar indisponível (flag false), a etapa volta a manual.
        $cap = ['create_client' => false, 'create_vps' => false, 'create_app' => false, 'deploy' => false];
        $steps = ProvisioningRules::defaultSteps($cap);
        $byKey = [];
        foreach ($steps as $s) { $byKey[$s['step_key']] = $s; }
        $this->assertSame('manual', $byKey['create_client']['mode']);
        $this->assertSame('manual', $byKey['create_vps']['mode']);
        $this->assertSame('manual', $byKey['deploy']['mode']);
    }

    public function testCanCompleteStepAutoExigeApiOk(): void
    {
        $auto = ['mode' => 'auto'];
        $this->assertFalse(ProvisioningRules::canCompleteStep($auto, false));
        $this->assertTrue(ProvisioningRules::canCompleteStep($auto, true));
        // Manual conclui sem API.
        $manual = ['mode' => 'manual'];
        $this->assertTrue(ProvisioningRules::canCompleteStep($manual, false));
    }

    public function testCanFinishExigeObrigatoriasDoneOuSkipped(): void
    {
        $steps = [
            ['required' => 1, 'status' => 'done'],
            ['required' => 1, 'status' => 'pending'],
            ['required' => 0, 'status' => 'pending'],
        ];
        $this->assertFalse(ProvisioningRules::canFinish($steps));
        $steps[1]['status'] = 'skipped'; // skipped conta como resolvido
        $this->assertTrue(ProvisioningRules::canFinish($steps));
    }

    public function testPendingRequiredEManualSteps(): void
    {
        $steps = [
            ['required' => 1, 'status' => 'pending', 'title' => 'VPS', 'mode' => 'manual'],
            ['required' => 1, 'status' => 'done', 'title' => 'Banco', 'mode' => 'auto'],
            ['required' => 0, 'status' => 'pending', 'title' => 'Homolog', 'mode' => 'manual'],
        ];
        $this->assertSame(['VPS'], ProvisioningRules::pendingRequired($steps));
        $this->assertSame(['VPS', 'Homolog'], ProvisioningRules::manualSteps($steps));
    }

    public function testVerifyWebhookSignature(): void
    {
        $body = '{"event":"hosting.created"}';
        $secret = 'segredo-lrv';
        $sig = 'sha256=' . hash_hmac('sha256', $body, $secret);
        $this->assertTrue(ProvisioningRules::verifyWebhookSignature($body, $sig, $secret));
        $this->assertFalse(ProvisioningRules::verifyWebhookSignature($body, $sig, 'outro'));
        $this->assertFalse(ProvisioningRules::verifyWebhookSignature($body, '', $secret));
    }

    public function testInterpretEvent(): void
    {
        $this->assertSame(['step_key' => 'create_vps', 'done' => true], ProvisioningRules::interpretEvent('hosting.created'));
        $this->assertSame(['step_key' => 'deploy', 'done' => true], ProvisioningRules::interpretEvent('application.installed'));
        $this->assertSame(['step_key' => 'staging', 'done' => true], ProvisioningRules::interpretEvent('domain.added'));
        $this->assertSame(['step_key' => null, 'done' => false], ProvisioningRules::interpretEvent('ticket.created'));
    }

    public function testNormalizadoresEIsConfigured(): void
    {
        $this->assertSame('pending', ProvisioningRules::normalizeStatus('xyz'));
        $this->assertSame('manual', ProvisioningRules::normalizeMode('nope'));
        $this->assertSame('skipped', ProvisioningRules::normalizeStepStatus('skipped'));
        $this->assertTrue(ProvisioningRules::isConfigured('lrv_live_abc'));
        $this->assertFalse(ProvisioningRules::isConfigured(''));
        $this->assertFalse(ProvisioningRules::isConfigured(null));
    }
}
