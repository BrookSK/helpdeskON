<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Permissions;

/**
 * Testes unitários da matriz de permissões (Permissions) — sem banco.
 * Garante que super_admin/developer têm acesso total, que o WhatsApp Chat é
 * liberado a todos, que cada papel segmentado só vê seus módulos e que o
 * client fica restrito à área do cliente.
 */
final class PermissionsTest extends TestCase
{
    // ---- Acesso total ----

    public function testSuperAdminAcessaTodosOsModulos(): void
    {
        foreach (Permissions::MODULES as $mod) {
            $this->assertTrue(Permissions::canAccess('super_admin', $mod), "super_admin deveria acessar {$mod}");
        }
        $this->assertTrue(Permissions::hasFullAccess('super_admin'));
    }

    public function testDeveloperAcessaTodosOsModulos(): void
    {
        foreach (Permissions::MODULES as $mod) {
            $this->assertTrue(Permissions::canAccess('developer', $mod), "developer deveria acessar {$mod}");
        }
        $this->assertTrue(Permissions::hasFullAccess('developer'));
    }

    // ---- WhatsApp Chat para todos ----

    public function testWhatsappChatLiberadoParaTodosOsPapeis(): void
    {
        foreach (Permissions::ROLES as $role) {
            $this->assertTrue(Permissions::canAccess($role, 'whatsapp'), "{$role} deveria acessar whatsapp");
        }
    }

    // ---- RDO ----

    public function testRdoLiberadoParaEquipeInternaMasNaoParaClient(): void
    {
        foreach (['super_admin', 'developer', 'marketing', 'comercial', 'attendant', 'analyst', 'whatsapp_agent'] as $role) {
            $this->assertTrue(Permissions::canAccess($role, 'rdo'), "{$role} deveria acessar rdo");
        }
        $this->assertFalse(Permissions::canAccess('client', 'rdo'), 'client NÃO deveria acessar rdo');
    }

    // ---- Marketing segmentado ----

    public function testMarketingVeSeusModulosENaoOsDeOutros(): void
    {
        $this->assertTrue(Permissions::canAccess('marketing', 'marketing'));
        $this->assertTrue(Permissions::canAccess('marketing', 'social'));
        $this->assertTrue(Permissions::canAccess('marketing', 'prospection'));
        // Não deve ver CRM, agenda, usuários, empresas
        $this->assertFalse(Permissions::canAccess('marketing', 'crm'));
        $this->assertFalse(Permissions::canAccess('marketing', 'agenda'));
        $this->assertFalse(Permissions::canAccess('marketing', 'users'));
        $this->assertFalse(Permissions::canAccess('marketing', 'companies'));
        $this->assertFalse(Permissions::hasFullAccess('marketing'));
    }

    // ---- Comercial segmentado ----

    public function testComercialVeCrmEAgendaNaoVeMarketingNemAdmin(): void
    {
        $this->assertTrue(Permissions::canAccess('comercial', 'crm'));
        $this->assertTrue(Permissions::canAccess('comercial', 'agenda'));
        $this->assertTrue(Permissions::canAccess('comercial', 'performance_comercial'));
        $this->assertFalse(Permissions::canAccess('comercial', 'marketing'));
        $this->assertFalse(Permissions::canAccess('comercial', 'users'));
        $this->assertFalse(Permissions::canAccess('comercial', 'settings'));
    }

    // ---- Client restrito ----

    public function testClientRestritoAAreaDoCliente(): void
    {
        $this->assertTrue(Permissions::canAccess('client', 'client_tickets'));
        $this->assertTrue(Permissions::canAccess('client', 'client_schedule'));
        $this->assertTrue(Permissions::canAccess('client', 'documents'));
        // Nada de módulos internos
        $this->assertFalse(Permissions::canAccess('client', 'crm'));
        $this->assertFalse(Permissions::canAccess('client', 'marketing'));
        $this->assertFalse(Permissions::canAccess('client', 'tickets'));
        $this->assertFalse(Permissions::canAccess('client', 'users'));
        $this->assertFalse(Permissions::hasFullAccess('client'));
    }

    // ---- Administração só para acesso total ----

    public function testAdministracaoRestritaASuperAdminEDeveloper(): void
    {
        foreach (['users', 'companies', 'settings'] as $mod) {
            $this->assertSame(
                ['super_admin', 'developer'],
                Permissions::rolesForModule($mod),
                "somente super_admin e developer deveriam acessar {$mod}"
            );
        }
    }

    // ---- Robustez ----

    public function testPapelNuloOuDesconhecidoNaoAcessaNada(): void
    {
        $this->assertFalse(Permissions::canAccess(null, 'dashboard'));
        $this->assertFalse(Permissions::canAccess('inexistente', 'dashboard'));
        $this->assertSame([], Permissions::allowedModules('inexistente'));
    }

    public function testCanAccessNaoRetornaTrueParaModuloInexistente(): void
    {
        // Mesmo super_admin não "acessa" um módulo que não existe no catálogo.
        $this->assertFalse(Permissions::canAccess('super_admin', 'modulo_que_nao_existe'));
    }

    public function testRolesForModuleWhatsappIncluiTodos(): void
    {
        $roles = Permissions::rolesForModule('whatsapp');
        foreach (Permissions::ROLES as $role) {
            $this->assertContains($role, $roles);
        }
    }

    // ---- isValidRole ----

    public function testIsValidRoleAceitaTodosOsPapeisConhecidos(): void
    {
        foreach (Permissions::ROLES as $role) {
            $this->assertTrue(Permissions::isValidRole($role), "{$role} deveria ser válido");
        }
    }

    public function testIsValidRoleRejeitaLixo(): void
    {
        $this->assertFalse(Permissions::isValidRole('root'));
        $this->assertFalse(Permissions::isValidRole('admin'));
        $this->assertFalse(Permissions::isValidRole(''));
        $this->assertFalse(Permissions::isValidRole(null));
        $this->assertFalse(Permissions::isValidRole(123));
    }
}
