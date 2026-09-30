<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use SequenceWindowRules;

/**
 * Testes unitários da JANELA DE ENVIO das sequências (SequenceWindowRules).
 *
 * Cobre o bug de produção: envios (WhatsApp) saíam de madrugada e fim de semana,
 * ignorando o horário/dias configurados. A regra: só envia em dia permitido e
 * dentro da faixa de horário; fora disso, calcula o próximo horário válido.
 *
 * Todos os cenários usam um timestamp fixo (determinístico) via mktime, no fuso
 * America/Sao_Paulo definido no bootstrap dos testes.
 */
final class SequenceWindowRulesTest extends TestCase
{
    /** Terça-feira (ISO 2), 2026-09-29, às 10:00. */
    private function terca10h(): int { return mktime(10, 0, 0, 9, 29, 2026); }
    /** Terça-feira, 2026-09-29, às 03:00 (madrugada). */
    private function terca03h(): int { return mktime(3, 0, 0, 9, 29, 2026); }
    /** Terça-feira, 2026-09-29, às 20:00 (após o expediente). */
    private function terca20h(): int { return mktime(20, 0, 0, 9, 29, 2026); }
    /** Sábado, 2026-10-03, às 10:00. */
    private function sabado10h(): int { return mktime(10, 0, 0, 10, 3, 2026); }
    /** Domingo, 2026-10-04, às 10:00. */
    private function domingo10h(): int { return mktime(10, 0, 0, 10, 4, 2026); }

    // ---- parseDays ----

    public function testParseDaysNormalizaEValidaFaixa(): void
    {
        $this->assertSame([1,2,3,4,5], SequenceWindowRules::parseDays('1,2,3,4,5'));
        $this->assertSame([], SequenceWindowRules::parseDays(''));
        $this->assertSame([], SequenceWindowRules::parseDays('   '));
        // Espaços, duplicatas e valores fora de 1..7 são tratados.
        $this->assertSame([1,3], SequenceWindowRules::parseDays(' 1 , 3 , 1 '));
        $this->assertSame([2], SequenceWindowRules::parseDays('0,2,8,9'));
    }

    // ---- isDayAllowed ----

    public function testDiasExplicitosMandamSobreSendWeekends(): void
    {
        // days_of_week definido ignora send_weekends.
        $this->assertTrue(SequenceWindowRules::isDayAllowed(6, '6,7', false));   // sábado permitido
        $this->assertFalse(SequenceWindowRules::isDayAllowed(1, '6,7', true));   // segunda não está na lista
    }

    public function testSemDiasCaiNoFallbackFimDeSemana(): void
    {
        // Sem days_of_week: dias úteis sempre; fim de semana só com send_weekends.
        $this->assertTrue(SequenceWindowRules::isDayAllowed(3, '', false));   // quarta
        $this->assertFalse(SequenceWindowRules::isDayAllowed(6, '', false));  // sábado bloqueado
        $this->assertTrue(SequenceWindowRules::isDayAllowed(6, '', true));    // sábado liberado
        $this->assertTrue(SequenceWindowRules::isDayAllowed(7, '', true));    // domingo liberado
    }

    // ---- isTimeWithin ----

    public function testHorarioDentroDaFaixa(): void
    {
        $this->assertTrue(SequenceWindowRules::isTimeWithin('10:00:00', '08:00:00', '18:00:00'));
        $this->assertTrue(SequenceWindowRules::isTimeWithin('08:00:00', '08:00:00', '18:00:00'));  // limite inicial
        $this->assertTrue(SequenceWindowRules::isTimeWithin('18:00:00', '08:00:00', '18:00:00'));  // limite final
        $this->assertFalse(SequenceWindowRules::isTimeWithin('07:59:59', '08:00:00', '18:00:00'));
        $this->assertFalse(SequenceWindowRules::isTimeWithin('18:00:01', '08:00:00', '18:00:00'));
    }

    // ---- canSendNow ----

    public function testCanSendNowDiaUtilDentroDaJanela(): void
    {
        $this->assertTrue(SequenceWindowRules::canSendNow('08:00:00', '18:30:00', '1,2,3,4,5', false, $this->terca10h()));
    }

    public function testCanSendNowBloqueiaMadrugada(): void
    {
        // Bug relatado: mandava de madrugada. 03:00 está fora da janela.
        $this->assertFalse(SequenceWindowRules::canSendNow('08:00:00', '18:30:00', '1,2,3,4,5', false, $this->terca03h()));
    }

    public function testCanSendNowBloqueiaAposExpediente(): void
    {
        $this->assertFalse(SequenceWindowRules::canSendNow('08:00:00', '18:30:00', '1,2,3,4,5', false, $this->terca20h()));
    }

    public function testCanSendNowBloqueiaFimDeSemanaQuandoNaoPermitido(): void
    {
        $this->assertFalse(SequenceWindowRules::canSendNow('08:00:00', '18:30:00', '1,2,3,4,5', false, $this->sabado10h()));
        $this->assertFalse(SequenceWindowRules::canSendNow('08:00:00', '18:30:00', '1,2,3,4,5', false, $this->domingo10h()));
    }

    public function testCanSendNowFimDeSemanaLiberadoPorDias(): void
    {
        $this->assertTrue(SequenceWindowRules::canSendNow('08:00:00', '18:30:00', '6,7', false, $this->sabado10h()));
    }

    // ---- nextSendTime ----

    public function testNextSendTimeQuandoJaPodeEnviarRetornaAgora(): void
    {
        $now = $this->terca10h();
        $this->assertSame($now, SequenceWindowRules::nextSendTime('08:00:00', '18:30:00', '1,2,3,4,5', false, $now));
    }

    public function testNextSendTimeMadrugadaAgendaParaInicioDoMesmoDia(): void
    {
        // 03:00 de terça (dia permitido) → início da janela HOJE às 08:00.
        $next = SequenceWindowRules::nextSendTime('08:00:00', '18:30:00', '1,2,3,4,5', false, $this->terca03h());
        $this->assertSame('2026-09-29 08:00:00', date('Y-m-d H:i:s', $next));
    }

    public function testNextSendTimeAposExpedienteVaiProProximoDiaUtil(): void
    {
        // 20:00 de terça → próximo dia permitido (quarta) às 08:00.
        $next = SequenceWindowRules::nextSendTime('08:00:00', '18:30:00', '1,2,3,4,5', false, $this->terca20h());
        $this->assertSame('2026-09-30 08:00:00', date('Y-m-d H:i:s', $next));
    }

    public function testNextSendTimeSextaAposExpedientePulaFimDeSemana(): void
    {
        // Sexta 2026-10-02 às 20:00, dias úteis: próximo é segunda 2026-10-05 08:00.
        $sexta20h = mktime(20, 0, 0, 10, 2, 2026);
        $next = SequenceWindowRules::nextSendTime('08:00:00', '18:30:00', '1,2,3,4,5', false, $sexta20h);
        $this->assertSame('2026-10-05 08:00:00', date('Y-m-d H:i:s', $next));
    }

    public function testNextSendTimeSabadoVaiProProximoDiaUtil(): void
    {
        // Sábado 10:00, dias úteis: próximo é segunda 08:00.
        $next = SequenceWindowRules::nextSendTime('08:00:00', '18:30:00', '1,2,3,4,5', false, $this->sabado10h());
        $this->assertSame('2026-10-05 08:00:00', date('Y-m-d H:i:s', $next));
    }
}
