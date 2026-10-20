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

    // ---- clientes múltiplos (parseClients) ----

    public function testClienteComContactIdDoCrmEhValido(): void
    {
        $c = AgendaRules::parseClients(['42'], [''], [''], ['']);
        $this->assertCount(1, $c);
        $this->assertSame(42, $c[0]['contact_id']);
        $this->assertSame('', $c[0]['name']);
    }

    public function testClienteSnapshotComNomeEhValido(): void
    {
        $c = AgendaRules::parseClients([''], ['Bruno'], ['bruno@x.com'], ['(11) 98888-7777']);
        $this->assertCount(1, $c);
        $this->assertNull($c[0]['contact_id']);
        $this->assertSame('Bruno', $c[0]['name']);
        $this->assertSame('bruno@x.com', $c[0]['email']);
        $this->assertSame('11988887777', $c[0]['phone']); // só dígitos
    }

    public function testClienteSnapshotSemNomeESemCrmEhDescartado(): void
    {
        // Sem contact_id e sem nome, não há como identificar o cliente.
        $c = AgendaRules::parseClients([''], [''], ['so-email@x.com'], ['11999998888']);
        $this->assertCount(0, $c);
    }

    public function testClienteComEmailInvalidoMantemClienteSemEmail(): void
    {
        // E-mail inválido é limpo, mas o cliente continua (tem nome).
        $c = AgendaRules::parseClients([''], ['Ana'], ['invalido'], ['']);
        $this->assertCount(1, $c);
        $this->assertSame('', $c[0]['email']);
        $this->assertSame('Ana', $c[0]['name']);
    }

    public function testVariosClientesPreservamOrdemComPrincipalPrimeiro(): void
    {
        $c = AgendaRules::parseClients(
            ['', '', ''],
            ['Principal', 'Segundo', 'Terceiro'],
            ['p@x.com', 's@x.com', 't@x.com'],
            ['', '', '']
        );
        $this->assertCount(3, $c);
        $this->assertSame('Principal', $c[0]['name']);
        $this->assertSame('Terceiro', $c[2]['name']);
    }

    public function testDeduplicaPorContactId(): void
    {
        $c = AgendaRules::parseClients(['7', '7', '9'], ['', '', ''], ['', '', ''], ['', '', '']);
        $this->assertCount(2, $c);
        $this->assertSame(7, $c[0]['contact_id']);
        $this->assertSame(9, $c[1]['contact_id']);
    }

    public function testDeduplicaSnapshotIdentico(): void
    {
        $c = AgendaRules::parseClients(
            ['', ''],
            ['Bruno', 'bruno'],          // nome case-insensitive
            ['b@x.com', 'B@X.COM'],      // e-mail case-insensitive
            ['11999990000', '11999990000']
        );
        $this->assertCount(1, $c);
    }

    public function testContactIdZeroOuNegativoViraNull(): void
    {
        $c = AgendaRules::parseClients(['0', '-3'], ['Zé', 'Léo'], ['', ''], ['', '']);
        $this->assertCount(2, $c);
        $this->assertNull($c[0]['contact_id']);
        $this->assertNull($c[1]['contact_id']);
    }

    public function testClientesMisturamCrmESnapshot(): void
    {
        // Um lead do CRM + um contato de empresa (snapshot), origens diferentes.
        $c = AgendaRules::parseClients(
            ['15', ''],
            ['', 'Contato Empresa'],
            ['', 'contato@empresa.com'],
            ['', '1133334444']
        );
        $this->assertCount(2, $c);
        $this->assertSame(15, $c[0]['contact_id']);
        $this->assertNull($c[1]['contact_id']);
        $this->assertSame('Contato Empresa', $c[1]['name']);
    }

    public function testParseClientsAceitaEntradaEscalar(): void
    {
        $c = AgendaRules::parseClients('', 'Solo', 'solo@x.com', '');
        $this->assertCount(1, $c);
        $this->assertSame('Solo', $c[0]['name']);
    }

    // ---- opção de reunião: resolveMeetingMode ----

    public function testResolveModeMeetEhMantido(): void
    {
        $this->assertSame('meet', AgendaRules::resolveMeetingMode('meet', '', false));
    }

    public function testResolveModeRoomEhMantido(): void
    {
        $this->assertSame('room', AgendaRules::resolveMeetingMode('room', '', false));
    }

    public function testResolveModeNenhumaSemLinkFicaVazio(): void
    {
        $this->assertSame('', AgendaRules::resolveMeetingMode('', '', false));
    }

    public function testResolveModeDeduzRoomPeloLinkNativoQuandoModoAusente(): void
    {
        // Modal antigo (sem meeting_mode): link da sala nativa => 'room'.
        $link = 'https://helpdesk.exemplo.com.br/videocall/room/abc123';
        $this->assertSame('room', AgendaRules::resolveMeetingMode('', $link, true));
    }

    public function testResolveModeDeduzMeetPeloLinkNaoNativoQuandoModoAusente(): void
    {
        $link = 'https://meet.google.com/xxx-yyyy-zzz';
        $this->assertSame('meet', AgendaRules::resolveMeetingMode('', $link, false));
    }

    public function testResolveModeValorDesconhecidoCaiNaDeducao(): void
    {
        // Valor inesperado some e cai na dedução pelo link (aqui, sem link = '').
        $this->assertSame('', AgendaRules::resolveMeetingMode('xpto', '', false));
    }

    // ---- opção de reunião: shouldCreateGoogleEvent (bugs 2 e 3) ----

    public function testCriaEventoGoogleSomenteNoModoMeetSemEventoPrevio(): void
    {
        $this->assertTrue(AgendaRules::shouldCreateGoogleEvent('meet', ''));
        $this->assertTrue(AgendaRules::shouldCreateGoogleEvent('meet', null));
    }

    public function testNaoCriaEventoGoogleNoModoRoom(): void
    {
        // Bug 2/3: sala do sistema não pode gerar Google Meet.
        $this->assertFalse(AgendaRules::shouldCreateGoogleEvent('room', ''));
        $this->assertFalse(AgendaRules::shouldCreateGoogleEvent('room', null));
    }

    public function testNaoCriaEventoGoogleNoModoNenhuma(): void
    {
        $this->assertFalse(AgendaRules::shouldCreateGoogleEvent('', ''));
    }

    public function testNaoCriaEventoGoogleQuandoJaExisteEvento(): void
    {
        // Meet já gerado no modal: não recria (evita duplicar).
        $this->assertFalse(AgendaRules::shouldCreateGoogleEvent('meet', 'evt_123'));
    }
}
