<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use PlanningRules;

/**
 * Testes unitários das regras puras do Planejamento (sem banco).
 *
 * Cobrem a janela de vencimento (<24h) usada pelo cron de lembrete e a
 * detecção de mudança de data/horário usada para avisar os superadmins.
 */
final class PlanningRulesTest extends TestCase
{
    private const NOW = '2026-09-26 12:00:00';

    // ---- isWithinDueWindow ----

    public function testDentroDaJanelaDe24h(): void
    {
        // Vence em 10h — dentro.
        $this->assertTrue(PlanningRules::isWithinDueWindow('2026-09-26 22:00:00', self::NOW, 24));
        // Vence em 23h59 — dentro.
        $this->assertTrue(PlanningRules::isWithinDueWindow('2026-09-27 11:59:00', self::NOW, 24));
    }

    public function testExatamente24hAindaEstaDentro(): void
    {
        // Limite inclusive: exatamente now+24h.
        $this->assertTrue(PlanningRules::isWithinDueWindow('2026-09-27 12:00:00', self::NOW, 24));
    }

    public function testAlemDe24hFicaForaDaJanela(): void
    {
        // Vence em 24h01 — fora.
        $this->assertFalse(PlanningRules::isWithinDueWindow('2026-09-27 12:01:00', self::NOW, 24));
        // Vence daqui a 3 dias — fora.
        $this->assertFalse(PlanningRules::isWithinDueWindow('2026-09-29 12:00:00', self::NOW, 24));
    }

    public function testPrazoJaVencidoNaoEntraNaJanela(): void
    {
        // Igual a now (já "venceu" o instante) — fora.
        $this->assertFalse(PlanningRules::isWithinDueWindow(self::NOW, self::NOW, 24));
        // No passado — fora.
        $this->assertFalse(PlanningRules::isWithinDueWindow('2026-09-26 08:00:00', self::NOW, 24));
    }

    public function testSemPrazoNaoEntraNaJanela(): void
    {
        $this->assertFalse(PlanningRules::isWithinDueWindow(null, self::NOW, 24));
        $this->assertFalse(PlanningRules::isWithinDueWindow('', self::NOW, 24));
        $this->assertFalse(PlanningRules::isWithinDueWindow('   ', self::NOW, 24));
    }

    public function testJanelaComHorasInvalidaRetornaFalse(): void
    {
        $this->assertFalse(PlanningRules::isWithinDueWindow('2026-09-26 22:00:00', self::NOW, 0));
        $this->assertFalse(PlanningRules::isWithinDueWindow('2026-09-26 22:00:00', self::NOW, -5));
    }

    // ---- shouldSendDueReminder ----

    public function testDeveEnviarQuandoDentroDaJanelaAtivoESemLembrete(): void
    {
        $this->assertTrue(
            PlanningRules::shouldSendDueReminder('2026-09-26 20:00:00', 'open', null, self::NOW, 24)
        );
        $this->assertTrue(
            PlanningRules::shouldSendDueReminder('2026-09-26 20:00:00', 'in_progress', '', self::NOW, 24)
        );
        // waiting_client ainda é ativo (o prazo continua correndo).
        $this->assertTrue(
            PlanningRules::shouldSendDueReminder('2026-09-26 20:00:00', 'waiting_client', null, self::NOW, 24)
        );
    }

    public function testNaoEnviaSeStatusInativo(): void
    {
        foreach (['completed', 'denied', 'archived'] as $status) {
            $this->assertFalse(
                PlanningRules::shouldSendDueReminder('2026-09-26 20:00:00', $status, null, self::NOW, 24),
                "Status inativo {$status} não deveria enviar"
            );
        }
    }

    public function testNaoReenviaSeJaFoiLembrado(): void
    {
        $this->assertFalse(
            PlanningRules::shouldSendDueReminder('2026-09-26 20:00:00', 'open', '2026-09-26 12:05:00', self::NOW, 24)
        );
    }

    public function testNaoEnviaSeForaDaJanela(): void
    {
        $this->assertFalse(
            PlanningRules::shouldSendDueReminder('2026-09-30 20:00:00', 'open', null, self::NOW, 24)
        );
    }

    // ---- normalizeDateTime ----

    public function testNormalizeDateTime(): void
    {
        $this->assertNull(PlanningRules::normalizeDateTime(null));
        $this->assertNull(PlanningRules::normalizeDateTime(''));
        $this->assertNull(PlanningRules::normalizeDateTime('   '));
        $this->assertSame('2026-09-26 12:00:00', PlanningRules::normalizeDateTime('2026-09-26 12:00:00'));
        // Formatos diferentes que representam o mesmo instante normalizam igual.
        $this->assertSame('2026-09-26 12:00:00', PlanningRules::normalizeDateTime('2026-09-26T12:00:00'));
    }

    // ---- scheduleChanged ----

    public function testScheduleChangedDetectaMudancaDeData(): void
    {
        $old = ['due_date' => '2026-09-26 12:00:00', 'start_date' => null, 'end_date' => null];
        $new = ['due_date' => '2026-09-27 12:00:00'];
        $this->assertTrue(PlanningRules::scheduleChanged($old, $new));
    }

    public function testScheduleChangedFalseQuandoDataIgual(): void
    {
        $old = ['due_date' => '2026-09-26 12:00:00'];
        $new = ['due_date' => '2026-09-26 12:00:00'];
        $this->assertFalse(PlanningRules::scheduleChanged($old, $new));
    }

    public function testScheduleChangedIgnoraCamposAusentes(): void
    {
        // Edição que não mexe em datas (só título) não conta como mudança.
        $old = ['due_date' => '2026-09-26 12:00:00', 'start_date' => '2026-09-20 09:00:00'];
        $new = ['title' => 'Novo título'];
        $this->assertFalse(PlanningRules::scheduleChanged($old, $new));
    }

    public function testScheduleChangedNullVsVazioNaoContaComoMudanca(): void
    {
        $old = ['due_date' => null];
        $new = ['due_date' => ''];
        $this->assertFalse(PlanningRules::scheduleChanged($old, $new));
    }

    public function testScheduleChangedDefinirDataAntesVazia(): void
    {
        $old = ['due_date' => null];
        $new = ['due_date' => '2026-09-27 15:00:00'];
        $this->assertTrue(PlanningRules::scheduleChanged($old, $new));
    }

    public function testScheduleChangedRemoverDataExistente(): void
    {
        $old = ['due_date' => '2026-09-27 15:00:00'];
        $new = ['due_date' => null];
        $this->assertTrue(PlanningRules::scheduleChanged($old, $new));
    }

    public function testScheduleChangedDetectaMudancaEmStartEEnd(): void
    {
        $old = ['start_date' => '2026-09-20 09:00:00', 'end_date' => '2026-09-25 18:00:00'];
        $this->assertTrue(PlanningRules::scheduleChanged($old, ['start_date' => '2026-09-21 09:00:00']));
        $this->assertTrue(PlanningRules::scheduleChanged($old, ['end_date' => '2026-09-26 18:00:00']));
    }

    // ---- dueDateChanged ----

    public function testDueDateChanged(): void
    {
        $old = ['due_date' => '2026-09-26 12:00:00', 'start_date' => '2026-09-20 09:00:00'];
        // due_date mudou.
        $this->assertTrue(PlanningRules::dueDateChanged($old, ['due_date' => '2026-09-27 12:00:00']));
        // due_date ausente no update — não mudou.
        $this->assertFalse(PlanningRules::dueDateChanged($old, ['start_date' => '2026-09-21 09:00:00']));
        // due_date presente mas igual — não mudou.
        $this->assertFalse(PlanningRules::dueDateChanged($old, ['due_date' => '2026-09-26 12:00:00']));
    }

    // ---- recalcDependentDates (atividade predecessora / cascata) ----

    public function testRecalculoDeslocaDependentePreservandoDuracao(): void
    {
        // Predecessor termina 10/01 12:00. Dependente durava 2 dias (12→14).
        // Sem folga e com prazo folgado, deve começar no fim do predecessor e
        // manter os 2 dias de duração.
        $r = PlanningRules::recalcDependentDates(
            '2026-01-10 12:00:00',
            '2026-01-12 12:00:00',
            '2026-01-14 12:00:00',
            '2026-01-31 23:59:00',
            0
        );
        $this->assertNotNull($r);
        $this->assertSame('2026-01-10 12:00:00', $r['start_date']);
        $this->assertSame('2026-01-12 12:00:00', $r['end_date']); // 10 + 2 dias
    }

    public function testRecalculoAplicaFolgaEmDias(): void
    {
        // Folga de 3 dias: início = fim do predecessor + 3 dias.
        $r = PlanningRules::recalcDependentDates(
            '2026-01-10 00:00:00',
            '2026-01-11 00:00:00',
            '2026-01-12 00:00:00', // duração 1 dia
            '2026-01-31 00:00:00',
            3
        );
        $this->assertSame('2026-01-13 00:00:00', $r['start_date']); // 10 + 3
        $this->assertSame('2026-01-14 00:00:00', $r['end_date']);   // + 1 dia
    }

    public function testRecalculoNuncaUltrapassaOPrazoFinal(): void
    {
        // Predecessor atrasou e termina 28/01. Dependente durava 5 dias, mas o
        // prazo final é 30/01: o fim é grampeado no prazo e a janela encolhe.
        $r = PlanningRules::recalcDependentDates(
            '2026-01-28 00:00:00',
            '2026-01-10 00:00:00',
            '2026-01-15 00:00:00', // duração 5 dias
            '2026-01-30 00:00:00', // prazo final (teto)
            0
        );
        // Fim não pode passar do prazo final.
        $this->assertSame('2026-01-30 00:00:00', $r['end_date']);
        // Início recua para manter duração (30 - 5 = 25), mas não antes do fim
        // do predecessor (28). Como 25 < 28, usa o fim do predecessor.
        $this->assertSame('2026-01-28 00:00:00', $r['start_date']);
    }

    public function testRecalculoSemFimDoPredecessorRetornaNull(): void
    {
        $this->assertNull(PlanningRules::recalcDependentDates(null, '2026-01-10 00:00:00', '2026-01-12 00:00:00', null, 0));
        $this->assertNull(PlanningRules::recalcDependentDates('', '2026-01-10 00:00:00', '2026-01-12 00:00:00', null, 0));
    }

    public function testRecalculoDependenteSemDatasAssumeUmDia(): void
    {
        // Sem start/end definidos, apenas desloca para após o predecessor com 1 dia.
        $r = PlanningRules::recalcDependentDates('2026-01-10 08:00:00', null, null, null, 0);
        $this->assertSame('2026-01-10 08:00:00', $r['start_date']);
        $this->assertSame('2026-01-11 08:00:00', $r['end_date']);
    }

    public function testRecalculoLagNegativoViraZero(): void
    {
        $r = PlanningRules::recalcDependentDates('2026-01-10 00:00:00', null, null, null, -5);
        $this->assertSame('2026-01-10 00:00:00', $r['start_date']);
    }

    // ---- wouldCreateCycle (proteção contra dependência circular) ----

    public function testCicloDiretoProprioCardEhProibido(): void
    {
        $this->assertTrue(PlanningRules::wouldCreateCycle(1, 1, []));
    }

    public function testCicloIndiretoEhDetectado(): void
    {
        // B já depende de A. Tentar fazer A depender de B fecha o ciclo A→B→A.
        $map = [2 => 1]; // card 2 (B) tem predecessor 1 (A)
        $this->assertTrue(PlanningRules::wouldCreateCycle(1, 2, $map));
    }

    public function testVinculoValidoNaoEhCiclo(): void
    {
        // A independente; fazer B depender de A é válido.
        $this->assertFalse(PlanningRules::wouldCreateCycle(2, 1, [1 => null]));
    }

    public function testSemPredecessorNaoEhCiclo(): void
    {
        $this->assertFalse(PlanningRules::wouldCreateCycle(1, null, [2 => 1]));
    }

    public function testCadeiaLongaValidaNaoEhCiclo(): void
    {
        // A→B→C (C depende de B, B depende de A). Fazer D depender de C é válido.
        $map = [3 => 2, 2 => 1];
        $this->assertFalse(PlanningRules::wouldCreateCycle(4, 3, $map));
    }
}
