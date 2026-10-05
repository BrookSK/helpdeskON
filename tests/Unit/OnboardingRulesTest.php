<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use OnboardingRules;

/**
 * Testes unitários das regras puras do Onboarding (Fase 6).
 * Cobre: etapas padrão, bloqueio por entrada paga, conclusão de etapa
 * obrigatória com/sem requisito, e conclusão do onboarding.
 */
final class OnboardingRulesTest extends TestCase
{
    public function testEtapasPadraoTemObrigatorias(): void
    {
        $steps = OnboardingRules::defaultSteps();
        $this->assertNotEmpty($steps);
        $required = array_filter($steps, fn($s) => $s['required'] === 1);
        // Pelo menos ambiente/servidor/armazenamento/credenciais são obrigatórias.
        $this->assertGreaterThanOrEqual(4, count($required));
        // Cada etapa tem chave e título.
        foreach ($steps as $s) {
            $this->assertArrayHasKey('step_key', $s);
            $this->assertArrayHasKey('title', $s);
        }
    }

    public function testCanStartSoComEntradaPaga(): void
    {
        $this->assertTrue(OnboardingRules::canStart(true));
        $this->assertFalse(OnboardingRules::canStart(false));
        // Sem projeto financeiro vinculado (null) -> bloqueado por segurança.
        $this->assertFalse(OnboardingRules::canStart(null));
    }

    public function testCanCompleteStepObrigatoriaExigeRequisito(): void
    {
        $obrig = ['required' => 1];
        $this->assertFalse(OnboardingRules::canCompleteStep($obrig, false));
        $this->assertTrue(OnboardingRules::canCompleteStep($obrig, true));
        // Não obrigatória: conclui livremente.
        $opc = ['required' => 0];
        $this->assertTrue(OnboardingRules::canCompleteStep($opc, false));
        $this->assertTrue(OnboardingRules::canCompleteStep($opc, true));
    }

    public function testCanFinishExigeObrigatoriasDone(): void
    {
        $steps = [
            ['required' => 1, 'status' => 'done',    'title' => 'A'],
            ['required' => 1, 'status' => 'pending', 'title' => 'B'],
            ['required' => 0, 'status' => 'pending', 'title' => 'C'],
        ];
        $this->assertFalse(OnboardingRules::canFinish($steps));
        $this->assertSame(['B'], OnboardingRules::pendingRequired($steps));

        // Todas as obrigatórias done -> conclui (mesmo com opcional pendente).
        $steps[1]['status'] = 'done';
        $this->assertTrue(OnboardingRules::canFinish($steps));
        $this->assertSame([], OnboardingRules::pendingRequired($steps));
    }

    public function testCanFinishSemObrigatoriasExigeNadaPendente(): void
    {
        $this->assertTrue(OnboardingRules::canFinish([
            ['required' => 0, 'status' => 'done'],
            ['required' => 0, 'status' => 'done'],
        ]));
        $this->assertFalse(OnboardingRules::canFinish([
            ['required' => 0, 'status' => 'done'],
            ['required' => 0, 'status' => 'pending'],
        ]));
    }

    public function testNormalizadores(): void
    {
        $this->assertSame('in_progress', OnboardingRules::normalizeStatus('in_progress'));
        $this->assertSame('blocked', OnboardingRules::normalizeStatus('lixo'));
        $this->assertSame('pending', OnboardingRules::normalizeStepStatus('xyz'));
        $this->assertSame(OnboardingRules::PIPELINE_CX, OnboardingRules::normalizePipeline('esteira_cx'));
        $this->assertNull(OnboardingRules::normalizePipeline('nope'));
    }

    public function testNormalizeProjectType(): void
    {
        $this->assertSame('zero', OnboardingRules::normalizeProjectType('zero'));
        $this->assertSame('esteira', OnboardingRules::normalizeProjectType('esteira'));
        $this->assertNull(OnboardingRules::normalizeProjectType('invalido'));
    }

    public function testPipelineFromProjectType(): void
    {
        $this->assertSame(OnboardingRules::PIPELINE_CX, OnboardingRules::pipelineFromProjectType('esteira'));
        $this->assertSame(OnboardingRules::PIPELINE_OUT, OnboardingRules::pipelineFromProjectType('zero'));
        $this->assertSame(OnboardingRules::PIPELINE_OUT, OnboardingRules::pipelineFromProjectType('manutencao'));
        $this->assertNull(OnboardingRules::pipelineFromProjectType('xpto'));
    }

    public function testEtapasPadraoIncluemCatalogoEDecisaoPipeline(): void
    {
        $keys = array_column(OnboardingRules::defaultSteps(), 'step_key');
        $this->assertContains('service_scope', $keys);
        $this->assertContains('pipeline_decision', $keys);
    }
}
