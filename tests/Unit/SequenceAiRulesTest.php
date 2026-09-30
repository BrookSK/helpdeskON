<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use SequenceAiRules;

/**
 * Testes unitários das regras puras da IA (ChatGPT) nas sequências.
 *
 * REGRA DE NEGÓCIO ATUAL (prospecção fria): só quem demonstra INTERESSE CLARO
 * (intent 'yes') segue para o AGENDAMENTO (saída SIM). Recusa explícita ('no'),
 * indecisão ('unclear') no fim do fluxo, ou limite de interações atingido sem
 * interesse claro ENCERRAM com a tag "sem interesse" (saída NÃO). O lead
 * engajado (tirando dúvidas dentro do limite) continua sendo atendido (listen).
 *
 * NOTA DE MUDANÇA: uma versão anterior enviava indecisão/limite/erro para o
 * AGENDAMENTO. Isso foi revertido a pedido do negócio (leads "sem interesse"
 * estavam recebendo agendamento e novas mensagens em produção). Estes testes
 * refletem a regra nova — não mascaram regressão: a expectativa mudou junto com
 * a regra, de forma explícita.
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

    public function testNormalizeIntentDesconhecidoViraUnclear(): void
    {
        // Valor inesperado/ausente vira 'unclear' (não 'yes' nem 'no'): a decisão
        // de encerrar/agendar depende do modo, não de um chute do parser.
        $this->assertSame('unclear', SequenceAiRules::normalizeIntent('talvez'));
        $this->assertSame('unclear', SequenceAiRules::normalizeIntent(''));
        $this->assertSame('unclear', SequenceAiRules::normalizeIntent(null));
        $this->assertSame('unclear', SequenceAiRules::normalizeIntent(['x']));
    }

    // ---- interesse claro sempre agenda ----

    public function testInteresseClaroAgenda(): void
    {
        $this->assertSame('advance_yes', SequenceAiRules::agentAction('yes', true, 0, 6));
        $this->assertSame('advance_yes', SequenceAiRules::agentAction('yes', false, 0, 6));
        // Mesmo no limite, interesse claro agenda.
        $this->assertSame('advance_yes', SequenceAiRules::agentAction('yes', true, 6, 6));
    }

    // ---- recusa explícita sempre encerra ----

    public function testRecusaExplicitaEncerraEmQualquerModo(): void
    {
        $this->assertSame('advance_no', SequenceAiRules::agentAction('no', true, 0, 6));
        $this->assertSame('advance_no', SequenceAiRules::agentAction('no', false, 0, 6));
        $this->assertSame('advance_no', SequenceAiRules::agentAction('no', true, 6, 6));
    }

    // ---- núcleo da regra: sem interesse claro NÃO agenda ----

    public function testModoInativoIndecisaoEncerraComTag(): void
    {
        // Modo classificação (uma passada): sem interesse claro, encerra + tag.
        $this->assertSame('advance_no', SequenceAiRules::agentAction('unclear', false, 0, 6));
        // Valor desconhecido (normaliza p/ unclear) também encerra.
        $this->assertSame('advance_no', SequenceAiRules::agentAction('qualquer', false, 0, 6));
    }

    public function testModoAtivoIndecisaoDentroDoLimiteContinuaOuvindo(): void
    {
        // Lead ainda tirando dúvidas, dentro do limite: continua atendendo.
        $this->assertSame('listen', SequenceAiRules::agentAction('unclear', true, 0, 6));
        $this->assertSame('listen', SequenceAiRules::agentAction('unclear', true, 5, 6));
    }

    public function testModoAtivoLimiteAtingidoSemInteresseEncerra(): void
    {
        // Esgotou as interações sem interesse claro: encerra + tag (não agenda).
        $this->assertSame('advance_no', SequenceAiRules::agentAction('unclear', true, 6, 6));
        $this->assertSame('advance_no', SequenceAiRules::agentAction('unclear', true, 10, 6));
    }

    public function testMaxTurnsMinimoUm(): void
    {
        // maxTurns inválido é elevado a 1: com turns=1 já está no limite → encerra.
        $this->assertSame('advance_no', SequenceAiRules::agentAction('unclear', true, 1, 0));
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
