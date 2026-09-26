<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use TicketAccess;

/**
 * Testes unitários das regras puras de acesso a demandas (TicketAccess).
 * Fecham os IDOR: equipe vê tudo; cliente só a própria demanda ou as da sua
 * empresa (quando dono); e a trava de status do cliente (só em homologação).
 */
final class TicketAccessTest extends TestCase
{
    // ---- isTeam ----

    public function testPapeisDeEquipeSaoReconhecidos(): void
    {
        foreach (['super_admin', 'developer', 'attendant', 'whatsapp_agent', 'analyst', 'comercial', 'marketing'] as $role) {
            $this->assertTrue(TicketAccess::isTeam($role), "{$role} deveria ser equipe");
        }
        $this->assertFalse(TicketAccess::isTeam('client'));
        $this->assertFalse(TicketAccess::isTeam(null));
    }

    // ---- canAccess: equipe ----

    public function testEquipeAcessaQualquerDemanda(): void
    {
        // attendant acessa demanda de qualquer cliente
        $this->assertTrue(TicketAccess::canAccess('attendant', 10, 999));
        $this->assertTrue(TicketAccess::canAccess('super_admin', 1, 999));
        $this->assertTrue(TicketAccess::canAccess('developer', 2, 999));
    }

    // ---- canAccess: cliente dono ----

    public function testClienteAcessaAPropriaDemanda(): void
    {
        $this->assertTrue(TicketAccess::canAccess('client', 5, 5));
    }

    public function testClienteNaoAcessaDemandaDeOutro(): void
    {
        // cliente comum (não dono de empresa) tentando ver demanda de outro
        $this->assertFalse(TicketAccess::canAccess('client', 5, 6));
    }

    // ---- canAccess: dono da empresa ----

    public function testDonoDaEmpresaAcessaDemandaDaMesmaEmpresa(): void
    {
        // viewer 5 é dono da empresa 100; demanda é do cliente 6, também da empresa 100
        $this->assertTrue(TicketAccess::canAccess('client', 5, 6, true, 100, 100));
    }

    public function testDonoDaEmpresaNaoAcessaDemandaDeOutraEmpresa(): void
    {
        // viewer 5 é dono da empresa 100; demanda é do cliente 6 da empresa 200
        $this->assertFalse(TicketAccess::canAccess('client', 5, 6, true, 100, 200));
    }

    public function testClienteNaoDonoNaoUsaRegraDeEmpresa(): void
    {
        // mesmo estando na mesma empresa, um cliente NÃO dono não vê demanda de outro
        $this->assertFalse(TicketAccess::canAccess('client', 5, 6, false, 100, 100));
    }

    public function testViewerSemIdNaoAcessa(): void
    {
        $this->assertFalse(TicketAccess::canAccess('client', 0, 0));
    }

    // ---- clientCanChangeStatus ----

    public function testClientePodeAprovarOuNegarSomenteEmHomologacao(): void
    {
        $this->assertTrue(TicketAccess::clientCanChangeStatus('em_homologacao', 'aprovado_producao'));
        $this->assertTrue(TicketAccess::clientCanChangeStatus('em_homologacao', 'denied'));
        // fora de homologação, não pode
        $this->assertFalse(TicketAccess::clientCanChangeStatus('open', 'aprovado_producao'));
        $this->assertFalse(TicketAccess::clientCanChangeStatus('in_progress', 'denied'));
        // status de destino inválido para o cliente
        $this->assertFalse(TicketAccess::clientCanChangeStatus('em_homologacao', 'completed'));
        $this->assertFalse(TicketAccess::clientCanChangeStatus('em_homologacao', 'open'));
        $this->assertFalse(TicketAccess::clientCanChangeStatus(null, 'aprovado_producao'));
    }

    // ---- isValidStatus ----

    public function testStatusValidoEInvalido(): void
    {
        $this->assertTrue(TicketAccess::isValidStatus('open'));
        $this->assertTrue(TicketAccess::isValidStatus('archived'));
        $this->assertFalse(TicketAccess::isValidStatus('foo'));
        $this->assertFalse(TicketAccess::isValidStatus(''));
        $this->assertFalse(TicketAccess::isValidStatus(null));
    }
}
