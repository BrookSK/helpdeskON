<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RdoRules;

/**
 * Testes unitários das regras puras do RDO (sem banco). Cobrem status,
 * normalização, tipo de colaborador, derivação da flag de ocorrência e — o
 * ponto central — a visibilidade por papel (super_admin vê todos; qualquer
 * outro, inclusive developer, vê só o próprio).
 */
final class RdoRulesTest extends TestCase
{
    public function testStatusValidoEInvalido(): void
    {
        $this->assertTrue(RdoRules::isValidStatus('em_andamento'));
        $this->assertTrue(RdoRules::isValidStatus('finalizado'));
        $this->assertFalse(RdoRules::isValidStatus('aprovado'));
        $this->assertFalse(RdoRules::isValidStatus(''));
    }

    public function testNormalizeStatus(): void
    {
        $this->assertSame('finalizado', RdoRules::normalizeStatus('finalizado'));
        $this->assertSame('em_andamento', RdoRules::normalizeStatus('xpto'));
        $this->assertSame('em_andamento', RdoRules::normalizeStatus(''));
        $this->assertSame('finalizado', RdoRules::normalizeStatus('xpto', 'finalizado'));
    }

    public function testLabel(): void
    {
        $this->assertSame('Em andamento', RdoRules::label('em_andamento'));
        $this->assertSame('Finalizado', RdoRules::label('finalizado'));
    }

    public function testNormalizeCollaboratorKind(): void
    {
        $this->assertSame('prestador', RdoRules::normalizeCollaboratorKind('prestador'));
        $this->assertSame('colaborador', RdoRules::normalizeCollaboratorKind('colaborador'));
        $this->assertSame('colaborador', RdoRules::normalizeCollaboratorKind('outro'));
    }

    public function testDeriveHasOccurrence(): void
    {
        $this->assertSame(1, RdoRules::deriveHasOccurrence('Faltou material'));
        $this->assertSame(0, RdoRules::deriveHasOccurrence(''));
        $this->assertSame(0, RdoRules::deriveHasOccurrence('   '));
        $this->assertSame(0, RdoRules::deriveHasOccurrence(null));
    }

    // ---- Visibilidade ----

    public function testSuperAdminTemVisaoGlobal(): void
    {
        $this->assertTrue(RdoRules::hasGlobalView('super_admin'));
        // vê o relatório de qualquer dono
        $this->assertTrue(RdoRules::canViewReportOf('super_admin', 1, 999));
    }

    public function testDeveloperNaoTemVisaoGlobalESoVeOProprio(): void
    {
        $this->assertFalse(RdoRules::hasGlobalView('developer'));
        // developer (id 5) vê o próprio
        $this->assertTrue(RdoRules::canViewReportOf('developer', 5, 5));
        // mas NÃO vê o de outro developer nem de ninguém
        $this->assertFalse(RdoRules::canViewReportOf('developer', 5, 6));
    }

    public function testDemaisPapeisSoVeemOProprio(): void
    {
        foreach (['marketing', 'comercial', 'attendant', 'analyst', 'whatsapp_agent'] as $role) {
            $this->assertTrue(RdoRules::canViewReportOf($role, 10, 10), "{$role} deveria ver o próprio");
            $this->assertFalse(RdoRules::canViewReportOf($role, 10, 11), "{$role} NÃO deveria ver o de outro");
        }
    }

    public function testViewerSemIdNaoVeNada(): void
    {
        $this->assertFalse(RdoRules::canViewReportOf('comercial', 0, 0));
        $this->assertFalse(RdoRules::canViewReportOf(null, 0, 0));
    }
}
