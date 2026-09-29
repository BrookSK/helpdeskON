<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use SequenceAiRules;

/**
 * Testes unitários das regras puras da IA (ChatGPT) nas sequências.
 *
 * Cobre o bug relatado: a IA respondia mas ENCERRAVA o atendimento sem chegar à
 * etapa de agendamento. A regra central é: SÓ a recusa EXPLÍCITA do lead (no)
 * segue pela saída NÃO (encerra). Indecisão (unclear), limite de interações no
 * modo ativo, ou classificação indefinida no modo inativo devem seguir para o
 * AGENDAMENTO (saída SIM) — nunca encerrar sozinhos.
 */
final class SequenceAiRulesTest extends TestCase
{
    // ---- normalização de intenção ----

    public function testNormalizeIntentReconheceValoresValidos(): void
    {
        $this->assertSame('yes', SequenceAiRules::normalizeIntent('yes'));
        $this->assertSame('no', SequenceAiRules::normalizeIntent('no'));
        $this->assertSame('unclear', SequenceAiRules::normalizeIntent('unclear'));
        // Case-insensitive e com espaços.
        $this->assertSame('yes', SequenceAiRules::normalizeIntent('  YES '));
        $this->assertSame('no', SequenceAiRules::normalizeIntent('No'));
    }

    public function testNormalizeIntentDesconhecidoNuncaViraRecusa(): void
    {
        // Valor inesperado/ausente vira 'unclear', NUNCA 'no' — a dúvida do
        // modelo, por si só, não pode encerrar o atendimento.
        $this->assertSame('unclear', SequenceAiRules::normalizeIntent('talvez'));
        $this->assertSame('unclear', SequenceAiRules::normalizeIntent(''));
        $this->assertSame('unclear', SequenceAiRules::normalizeIntent(null));
        $this->assertSame('unclear', SequenceAiRules::normalizeIntent(['x']));
    }

    // ---- recusa explícita sempre encerra ----

    public function testRecusaExplicitaEncerraEmQualquerModo(): void
    {
        $this->assertSame('advance_no', SequenceAiRules::agentAction('no', true, 0, 6));
        $this->assertSame('advance_no', SequenceAiRules::agentAction('no', false, 0, 6));
        // Mesmo no limite de interações, a recusa explícita manda no NÃO.
        $this->assertSame('advance_no', SequenceAiRules::agentAction('no', true, 6, 6));
    }

    // ---- interesse claro sempre agenda ----

    public function testInteresseClaroAgenda(): void
    {
        $this->assertSame('advance_yes', SequenceAiRules::agentAction('yes', true, 0, 6));
        $this->assertSame('advance_yes', SequenceAiRules::agentAction('yes', false, 0, 6));
    }

    // ---- núcleo do bug: indecisão não pode encerrar ----

    public function testModoInativoIndecisaoSegueParaAgendamento(): void
    {
        // Modo classificação (uma passada): 'unclear' NÃO encerra — vai ao SIM.
        $this->assertSame('advance_yes', SequenceAiRules::agentAction('unclear', false, 0, 6));
        // Valor desconhecido também não encerra.
        $this->assertSame('advance_yes', SequenceAiRules::agentAction('qualquer', false, 0, 6));
    }

    public function testModoAtivoIndecisaoDentroDoLimiteContinuaOuvindo(): void
    {
        // Ainda há espaço no ciclo: continua tirando dúvidas (listen).
        $this->assertSame('listen', SequenceAiRules::agentAction('unclear', true, 0, 6));
        $this->assertSame('listen', SequenceAiRules::agentAction('unclear', true, 5, 6));
    }

    public function testModoAtivoLimiteAtingidoSegueParaAgendamentoNaoEncerra(): void
    {
        // Cenário do bug: batia o limite e ENCERRAVA (nextNo). Agora, sem recusa
        // do lead, o limite encaminha ao AGENDAMENTO (SIM), não encerra.
        $this->assertSame('advance_yes', SequenceAiRules::agentAction('unclear', true, 6, 6));
        $this->assertSame('advance_yes', SequenceAiRules::agentAction('unclear', true, 10, 6));
    }

    public function testMaxTurnsMinimoUm(): void
    {
        // maxTurns inválido é elevado a 1: com turns=1 já está no limite.
        $this->assertSame('advance_yes', SequenceAiRules::agentAction('unclear', true, 1, 0));
        // Com turns=0 ainda ouve.
        $this->assertSame('listen', SequenceAiRules::agentAction('unclear', true, 0, 0));
    }

    // ---- decisão simples (bloco IA modo decisão) ----

    public function testDecisionActionMapeiaBooleano(): void
    {
        $this->assertSame('advance_yes', SequenceAiRules::decisionAction(true));
        $this->assertSame('advance_no', SequenceAiRules::decisionAction(false));
    }
}
