<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use AssignmentRules;

/**
 * Testes unitários das regras puras de vinculação de usuários aos campos da
 * demanda (Atendente / Técnico / Analista), sem banco.
 *
 * Hierarquia exigida pelo negócio:
 *   - Atendente (attendant) PODE ser Técnico.
 *   - Analista  (analyst)   PODE ser Técnico.
 *   - Técnico   (developer) NÃO PODE ser Analista.
 * super_admin é elegível em Técnico e Analista.
 */
final class AssignmentRulesTest extends TestCase
{
    // ---- rolesForField ----

    public function testRolesForFieldAtendente(): void
    {
        $roles = AssignmentRules::rolesForField(AssignmentRules::FIELD_ATTENDANT);
        $this->assertContains('attendant', $roles);
        $this->assertContains('analyst', $roles);
        $this->assertContains('developer', $roles);
        $this->assertContains('super_admin', $roles);
        $this->assertContains('whatsapp_agent', $roles);
    }

    public function testRolesForFieldTecnicoIncluiAtendenteAnalistaDeveloperAdmin(): void
    {
        $roles = AssignmentRules::rolesForField(AssignmentRules::FIELD_TECHNICAL);
        $this->assertContains('developer', $roles);
        $this->assertContains('attendant', $roles);
        $this->assertContains('analyst', $roles);
        $this->assertContains('super_admin', $roles);
    }

    public function testRolesForFieldAnalistaSoAnalystEAdmin(): void
    {
        $roles = AssignmentRules::rolesForField(AssignmentRules::FIELD_ANALYST);
        $this->assertContains('analyst', $roles);
        $this->assertContains('super_admin', $roles);
        // A regra central: técnico (developer) NÃO entra em Analista.
        $this->assertNotContains('developer', $roles);
        $this->assertNotContains('attendant', $roles);
        $this->assertNotContains('whatsapp_agent', $roles);
    }

    public function testRolesForFieldDesconhecidoRetornaVazio(): void
    {
        $this->assertSame([], AssignmentRules::rolesForField('inexistente'));
    }

    // ---- canAssign: regra de hierarquia ----

    public function testAtendentePodeSerTecnico(): void
    {
        $this->assertTrue(AssignmentRules::canAssign(AssignmentRules::FIELD_TECHNICAL, 'attendant'));
    }

    public function testAnalistaPodeSerTecnico(): void
    {
        $this->assertTrue(AssignmentRules::canAssign(AssignmentRules::FIELD_TECHNICAL, 'analyst'));
    }

    public function testDeveloperPodeSerTecnico(): void
    {
        $this->assertTrue(AssignmentRules::canAssign(AssignmentRules::FIELD_TECHNICAL, 'developer'));
    }

    public function testTecnicoNaoPodeSerAnalista(): void
    {
        // developer é o "técnico" de fato: não pode ocupar o campo Analista.
        $this->assertFalse(AssignmentRules::canAssign(AssignmentRules::FIELD_ANALYST, 'developer'));
    }

    public function testAnalistaPodeSerAnalista(): void
    {
        $this->assertTrue(AssignmentRules::canAssign(AssignmentRules::FIELD_ANALYST, 'analyst'));
    }

    public function testSuperAdminPodeSerTecnicoEAnalista(): void
    {
        $this->assertTrue(AssignmentRules::canAssign(AssignmentRules::FIELD_TECHNICAL, 'super_admin'));
        $this->assertTrue(AssignmentRules::canAssign(AssignmentRules::FIELD_ANALYST, 'super_admin'));
    }

    public function testAtendenteNaoPodeSerAnalista(): void
    {
        $this->assertFalse(AssignmentRules::canAssign(AssignmentRules::FIELD_ANALYST, 'attendant'));
    }

    // ---- canAssign: papéis inválidos / limites ----

    public function testClienteNuncaElegivel(): void
    {
        $this->assertFalse(AssignmentRules::canAssign(AssignmentRules::FIELD_ATTENDANT, 'client'));
        $this->assertFalse(AssignmentRules::canAssign(AssignmentRules::FIELD_TECHNICAL, 'client'));
        $this->assertFalse(AssignmentRules::canAssign(AssignmentRules::FIELD_ANALYST, 'client'));
    }

    public function testPapelVazioOuNuloRetornaFalse(): void
    {
        $this->assertFalse(AssignmentRules::canAssign(AssignmentRules::FIELD_TECHNICAL, ''));
        $this->assertFalse(AssignmentRules::canAssign(AssignmentRules::FIELD_TECHNICAL, null));
    }

    public function testCampoDesconhecidoRetornaFalse(): void
    {
        $this->assertFalse(AssignmentRules::canAssign('inexistente', 'super_admin'));
    }

    public function testFieldsRetornaOsTresCampos(): void
    {
        $this->assertSame(
            [AssignmentRules::FIELD_ATTENDANT, AssignmentRules::FIELD_TECHNICAL, AssignmentRules::FIELD_ANALYST],
            AssignmentRules::fields()
        );
    }
}
