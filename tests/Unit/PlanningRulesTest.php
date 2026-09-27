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
}
