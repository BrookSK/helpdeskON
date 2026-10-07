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

    /**
     * Guia de Onboarding: pontos focais, kickoff e apresentação dos fluxos são
     * etapas INDISPENSÁVEIS — devem ser obrigatórias (required=1) e, portanto,
     * travar a conclusão do onboarding enquanto não estiverem 'done'.
     */
    public function testEtapasDoGuiaSaoObrigatorias(): void
    {
        $byKey = [];
        foreach (OnboardingRules::defaultSteps() as $s) {
            $byKey[$s['step_key']] = (int) $s['required'];
        }
        $this->assertSame(1, $byKey['focal_points'] ?? null, 'Pontos focais deve ser obrigatória');
        $this->assertSame(1, $byKey['kickoff'] ?? null, 'Kickoff deve ser obrigatória');
        $this->assertSame(1, $byKey['flows_presented'] ?? null, 'Apresentação dos fluxos deve ser obrigatória');
    }

    /**
     * Com as etapas do guia obrigatórias, o onboarding NÃO pode ser concluído
     * enquanto focal_points/kickoff/flows_presented não estiverem 'done',
     * mesmo que todas as demais obrigatórias já estejam concluídas.
     */
    public function testConclusaoBloqueadaSemEtapasDoGuia(): void
    {
        $steps = [];
        foreach (OnboardingRules::defaultSteps() as $s) {
            // Todas as obrigatórias começam 'done', exceto as três do guia.
            $pendentes = ['focal_points', 'kickoff', 'flows_presented'];
            $s['status'] = in_array($s['step_key'], $pendentes, true) ? 'pending' : 'done';
            $steps[] = $s;
        }
        $this->assertFalse(OnboardingRules::canFinish($steps));
        $pend = OnboardingRules::pendingRequired($steps);
        $this->assertContains('Cadastrar pontos focais', $pend);
        $this->assertContains('Reunião de onboarding (kickoff)', $pend);
        $this->assertContains('Apresentação dos fluxos de atendimento', $pend);

        // Concluindo as três, o onboarding pode ser finalizado.
        foreach ($steps as &$s) { $s['status'] = 'done'; }
        unset($s);
        $this->assertTrue(OnboardingRules::canFinish($steps));
    }
}
