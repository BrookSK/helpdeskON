<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use AgendaRules;

/**
 * Testes unitários das regras puras da Agenda (sem banco).
 * Cobrem whitelists de enums, normalização de data, visibilidade/limite da
 * sala de vídeo e validação dos convidados externos.
 */
final class AgendaRulesTest extends TestCase
{
    // ---- meeting_type ----

    public function testMeetingTypeValidosSaoMantidos(): void
    {
        $this->assertSame('comercial', AgendaRules::normalizeMeetingType('comercial'));
        $this->assertSame('operacional', AgendaRules::normalizeMeetingType('operacional'));
        $this->assertSame('externo', AgendaRules::normalizeMeetingType('externo'));
    }

    public function testMeetingTypeInvalidoViraComercial(): void
    {
        $this->assertSame('comercial', AgendaRules::normalizeMeetingType('xpto'));
        $this->assertSame('comercial', AgendaRules::normalizeMeetingType(''));
        $this->assertSame('comercial', AgendaRules::normalizeMeetingType(null));
    }

    // ---- urgency ----

    public function testUrgencyValidaEInvalida(): void
    {
        $this->assertSame('alta', AgendaRules::normalizeUrgency('alta'));
        $this->assertSame('urgente', AgendaRules::normalizeUrgency('urgente'));
        $this->assertSame('media', AgendaRules::normalizeUrgency('inexistente'));
        $this->assertSame('media', AgendaRules::normalizeUrgency(''));
    }

    // ---- temperature ----

    public function testTemperatureValidaEInvalida(): void
    {
        $this->assertSame('frio', AgendaRules::normalizeTemperature('frio'));
        $this->assertSame('quente', AgendaRules::normalizeTemperature('quente'));
        $this->assertNull(AgendaRules::normalizeTemperature('gelado'));
        $this->assertNull(AgendaRules::normalizeTemperature(''));
    }

    // ---- status ----

    public function testStatusValidoEInvalido(): void
    {
        $this->assertSame('agendada', AgendaRules::normalizeStatus('agendada'));
        $this->assertSame('convertida', AgendaRules::normalizeStatus('convertida'));
        $this->assertSame('a_agendar', AgendaRules::normalizeStatus('foo'));
        $this->assertTrue(AgendaRules::isValidStatus('cancelada'));
        $this->assertFalse(AgendaRules::isValidStatus('foo'));
    }

    public function testStatusListaCobreTodosOsEstadosDoKanban(): void
    {
        $esperados = ['a_agendar', 'agendada', 'confirmada', 'realizada', 'convertida', 'remarcada', 'cancelada'];
        $this->assertSame($esperados, AgendaRules::STATUSES);
    }

    // ---- meeting_at ----

    public function testMeetingAtConverteTParaEspaco(): void
    {
        $this->assertSame('2026-01-15 14:30', AgendaRules::normalizeMeetingAt('2026-01-15T14:30'));
    }

    public function testMeetingAtVazioViraNull(): void
    {
        $this->assertNull(AgendaRules::normalizeMeetingAt(''));
        $this->assertNull(AgendaRules::normalizeMeetingAt('   '));
    }

    // ---- sala de vídeo: visibilidade e limite ----

    public function testVideoVisibilityApenasPrivateExplicito(): void
    {
        $this->assertSame('private', AgendaRules::normalizeVideoVisibility('private'));
        $this->assertSame('public', AgendaRules::normalizeVideoVisibility('public'));
        $this->assertSame('public', AgendaRules::normalizeVideoVisibility('qualquer'));
        $this->assertSame('public', AgendaRules::normalizeVideoVisibility(''));
    }

    public function testVideoMaxParticipantsFicaEntre2e15(): void
    {
        $this->assertSame(2, AgendaRules::clampVideoMaxParticipants(0));
        $this->assertSame(2, AgendaRules::clampVideoMaxParticipants(1));
        $this->assertSame(8, AgendaRules::clampVideoMaxParticipants(8));
        $this->assertSame(15, AgendaRules::clampVideoMaxParticipants(15));
        $this->assertSame(15, AgendaRules::clampVideoMaxParticipants(50));
    }

    // ---- convidados externos ----

    public function testConvidadoComNomeEEmailEhValido(): void
    {
        $g = AgendaRules::parseExternalGuests(['Maria'], ['maria@example.com'], ['']);
        $this->assertCount(1, $g);
        $this->assertSame('Maria', $g[0]['name']);
        $this->assertSame('maria@example.com', $g[0]['email']);
        $this->assertSame('', $g[0]['phone']);
    }

    public function testConvidadoComNomeETelefoneEhValido(): void
    {
        $g = AgendaRules::parseExternalGuests(['João'], [''], ['(11) 98888-7777']);
        $this->assertCount(1, $g);
        $this->assertSame('11988887777', $g[0]['phone']); // só dígitos
    }

    public function testConvidadoSemNomeEhDescartado(): void
    {
        $g = AgendaRules::parseExternalGuests([''], ['x@x.com'], ['11999998888']);
        $this->assertCount(0, $g);
    }

    public function testConvidadoSemCanalDeContatoEhDescartado(): void
    {
        $g = AgendaRules::parseExternalGuests(['Sem Contato'], [''], ['']);
        $this->assertCount(0, $g);
    }

    public function testConvidadoComEmailInvalidoESemTelefoneEhDescartado(): void
    {
        // email inválido vira '' e, sem telefone, o convidado perde o canal -> descartado
        $g = AgendaRules::parseExternalGuests(['Zé'], ['email-invalido'], ['']);
        $this->assertCount(0, $g);
    }

    public function testConvidadoComEmailInvalidoMasComTelefoneEhMantidoSemEmail(): void
    {
        $g = AgendaRules::parseExternalGuests(['Ana'], ['invalido'], ['11 3333-4444']);
        $this->assertCount(1, $g);
        $this->assertSame('', $g[0]['email']); // email inválido foi limpo
        $this->assertSame('1133334444', $g[0]['phone']);
    }

    public function testVariosConvidadosMisturandoValidosEInvalidos(): void
    {
        $g = AgendaRules::parseExternalGuests(
            ['Maria', '', 'João', 'SemCanal'],
            ['maria@x.com', 'ignorado@x.com', '', ''],
            ['', '', '11999990000', '']
        );
        // Maria (nome+email) e João (nome+telefone) valem; linha 2 (sem nome) e
        // "SemCanal" (sem contato) são descartadas.
        $this->assertCount(2, $g);
        $this->assertSame('Maria', $g[0]['name']);
        $this->assertSame('João', $g[1]['name']);
    }

    public function testAceitaEntradaEscalar(): void
    {
        // Quando o formulário envia um único convidado (não-array), ainda funciona.
        $g = AgendaRules::parseExternalGuests('Solo', 'solo@x.com', '');
        $this->assertCount(1, $g);
        $this->assertSame('Solo', $g[0]['name']);
    }
}
