<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use User;
use AssignmentRules;
use Database;

/**
 * Teste de integração da elegibilidade de vinculação (Atendente/Técnico/
 * Analista) contra o banco helpdesk_on_test.
 *
 * Reproduz fielmente os dois pontos onde a regra atua no PlanningController:
 *   1) Montagem dos seletores: User::getByRoles(AssignmentRules::rolesForField)
 *      — quem APARECE em cada dropdown.
 *   2) Validação do servidor: para cada id recebido, busca o papel real do
 *      usuário e checa AssignmentRules::canAssign (mesma lógica de
 *      validateAssignments do controller).
 *
 * Estratégia de dados: cria um usuário de cada papel relevante no setUp e os
 * remove no tearDown (e-mails únicos por execução).
 */
final class AssignmentEligibilityTest extends TestCase
{
    private Database $db;
    private User $users;
    /** @var array<string,int> papel => id do usuário criado */
    private array $ids = [];

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste (helpdesk_on_test) não configurado.');
        }

        $this->db = Database::getInstance();
        $this->users = new User();

        foreach (['super_admin', 'attendant', 'analyst', 'developer', 'whatsapp_agent'] as $role) {
            $this->ids[$role] = (int) $this->db->insert('users', [
                'name'      => 'Elegib ' . $role,
                'email'     => 'elegib_' . $role . '_' . uniqid() . '@example.test',
                'password'  => password_hash('x', PASSWORD_BCRYPT),
                'role'      => $role,
                'is_active' => 1,
            ]);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->ids as $id) {
            try {
                $this->db->delete('users', 'id = ?', [$id]);
            } catch (\Throwable $e) {
                // ignora falha de limpeza
            }
        }
    }

    /** Dado um id, aplica a mesma checagem que validateAssignments faz. */
    private function canAssignUser(string $field, int $userId): bool
    {
        $u = $this->users->findById($userId);
        return $u !== null && AssignmentRules::canAssign($field, $u['role'] ?? null);
    }

    // ---- Ponto 1: composição dos dropdowns (getByRoles) ----

    public function testDropdownTecnicoListaDeveloperAtendenteAnalista(): void
    {
        $roles = AssignmentRules::rolesForField(AssignmentRules::FIELD_TECHNICAL);
        $rows = $this->users->getByRoles($roles);
        $foundIds = array_map(fn($r) => (int) $r['id'], $rows);

        $this->assertContains($this->ids['developer'], $foundIds, 'developer deve aparecer em Técnico');
        $this->assertContains($this->ids['attendant'], $foundIds, 'attendant deve aparecer em Técnico');
        $this->assertContains($this->ids['analyst'], $foundIds, 'analyst deve aparecer em Técnico');
        $this->assertContains($this->ids['super_admin'], $foundIds, 'super_admin deve aparecer em Técnico');
    }

    public function testDropdownAnalistaNaoListaDeveloper(): void
    {
        $roles = AssignmentRules::rolesForField(AssignmentRules::FIELD_ANALYST);
        $rows = $this->users->getByRoles($roles);
        $foundIds = array_map(fn($r) => (int) $r['id'], $rows);

        $this->assertContains($this->ids['analyst'], $foundIds, 'analyst deve aparecer em Analista');
        $this->assertContains($this->ids['super_admin'], $foundIds, 'super_admin deve aparecer em Analista');
        $this->assertNotContains($this->ids['developer'], $foundIds, 'developer (técnico) NÃO pode aparecer em Analista');
        $this->assertNotContains($this->ids['attendant'], $foundIds, 'attendant NÃO aparece em Analista');
    }

    // ---- Ponto 2: validação do servidor (canAssign sobre usuário real) ----

    public function testAtendenteEAnalistaPodemSerTecnico(): void
    {
        $this->assertTrue($this->canAssignUser(AssignmentRules::FIELD_TECHNICAL, $this->ids['attendant']));
        $this->assertTrue($this->canAssignUser(AssignmentRules::FIELD_TECHNICAL, $this->ids['analyst']));
    }

    public function testDeveloperNaoPodeSerAnalista(): void
    {
        $this->assertFalse($this->canAssignUser(AssignmentRules::FIELD_ANALYST, $this->ids['developer']));
    }

    public function testAnalistaPodeSerAnalistaEDeveloperPodeSerTecnico(): void
    {
        $this->assertTrue($this->canAssignUser(AssignmentRules::FIELD_ANALYST, $this->ids['analyst']));
        $this->assertTrue($this->canAssignUser(AssignmentRules::FIELD_TECHNICAL, $this->ids['developer']));
    }

    public function testAtendentePadraoMantemElegiveis(): void
    {
        $this->assertTrue($this->canAssignUser(AssignmentRules::FIELD_ATTENDANT, $this->ids['attendant']));
        $this->assertTrue($this->canAssignUser(AssignmentRules::FIELD_ATTENDANT, $this->ids['developer']));
        $this->assertTrue($this->canAssignUser(AssignmentRules::FIELD_ATTENDANT, $this->ids['analyst']));
        $this->assertTrue($this->canAssignUser(AssignmentRules::FIELD_ATTENDANT, $this->ids['whatsapp_agent']));
    }
}
