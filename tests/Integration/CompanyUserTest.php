<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Company;
use User;
use Database;

/**
 * Testes de integração dos módulos administrativos Empresas (Company) e
 * Usuários (User) contra o banco helpdesk_on_test. Cobre CRUD de empresa,
 * contadores, vínculo empresa->usuário com ON DELETE SET NULL, autenticação,
 * unicidade de e-mail, toggle de status e — o ponto de segurança — a proteção
 * do último super_admin ativo.
 */
final class CompanyUserTest extends TestCase
{
    private Database $db;
    private Company $companyModel;
    private User $userModel;
    /** @var int[] */
    private array $companyIds = [];
    /** @var int[] */
    private array $userIds = [];

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste (helpdesk_on_test) não configurado.');
        }
        $this->db = Database::getInstance();
        $this->companyModel = new Company();
        $this->userModel = new User();
    }

    protected function tearDown(): void
    {
        foreach ($this->userIds as $id) {
            try { $this->db->delete('users', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
        foreach ($this->companyIds as $id) {
            try { $this->db->delete('companies', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
    }

    private function novaEmpresa(array $ov = []): int
    {
        $u = uniqid();
        $id = (int) $this->companyModel->create(array_merge([
            'name' => "Empresa {$u}",
            'document' => '12345678000199',
            'phone' => '1130001000',
            'email' => "empresa_{$u}@ex.test",
        ], $ov));
        $this->companyIds[] = $id;
        return $id;
    }

    private function novoUsuario(string $role, array $ov = []): int
    {
        $u = uniqid();
        $id = (int) $this->db->insert('users', array_merge([
            'name' => "User {$u}",
            'email' => "user_{$u}@ex.test",
            'password' => password_hash('secret123', PASSWORD_DEFAULT),
            'role' => $role,
            'is_active' => 1,
        ], $ov));
        $this->userIds[] = $id;
        return $id;
    }

    // ================= Empresa CRUD =================

    public function testCriarAtualizarEExcluirEmpresa(): void
    {
        $id = $this->novaEmpresa(['name' => 'ACME']);
        $c = $this->companyModel->findById($id);
        $this->assertSame('ACME', $c['name']);

        $this->companyModel->update($id, ['name' => 'ACME 2', 'phone' => '1140002000']);
        $c = $this->companyModel->findById($id);
        $this->assertSame('ACME 2', $c['name']);
        $this->assertSame('1140002000', $c['phone']);

        $this->companyModel->delete($id);
        $this->assertEmpty($this->companyModel->findById($id));
    }

    public function testContadorDeUsuariosDaEmpresa(): void
    {
        $companyId = $this->novaEmpresa();
        $this->assertSame(0, $this->companyModel->countUsers($companyId));

        $this->novoUsuario('client', ['company_id' => $companyId]);
        $this->novoUsuario('client', ['company_id' => $companyId]);
        $this->assertSame(2, $this->companyModel->countUsers($companyId));
    }

    public function testDeletarEmpresaNaoApagaOUsuario(): void
    {
        // Excluir a empresa NÃO deve apagar o usuário em cascata (ele continua
        // existindo). Em produção, a FK users.company_id é ON DELETE SET NULL e
        // zera o company_id; no banco de teste as FKs do dump não são
        // preservadas, então validamos apenas o que é determinístico aqui: a
        // empresa some e o usuário permanece. Se a FK estiver ativa, também
        // conferimos que o company_id foi zerado.
        $companyId = $this->novaEmpresa();
        $userId = $this->novoUsuario('client', ['company_id' => $companyId]);

        // Detecta se a FK de users.company_id existe neste ambiente.
        $fkAtiva = (int) ($this->db->fetch(
            "SELECT COUNT(*) c FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
               AND COLUMN_NAME = 'company_id' AND REFERENCED_TABLE_NAME = 'companies'"
        )['c'] ?? 0) > 0;

        $this->companyModel->delete($companyId);
        $this->companyIds = array_values(array_filter($this->companyIds, fn($c) => $c !== $companyId));

        $u = $this->userModel->findById($userId);
        $this->assertNotEmpty($u, 'usuário deve continuar existindo (sem cascata)');

        if ($fkAtiva) {
            $this->assertNull($u['company_id'], 'com FK ON DELETE SET NULL, company_id deve virar NULL');
        } else {
            $this->markTestIncomplete('FK users.company_id ausente no banco de teste; SET NULL não verificável aqui.');
        }
    }

    // ================= Usuário: autenticação e e-mail =================

    public function testAutenticacaoValidaEInvalida(): void
    {
        $u = uniqid();
        $email = "auth_{$u}@ex.test";
        $this->novoUsuario('attendant', ['email' => $email, 'password' => password_hash('certa123', PASSWORD_DEFAULT)]);

        $this->assertNotFalse($this->userModel->authenticate($email, 'certa123'));
        $this->assertFalse($this->userModel->authenticate($email, 'errada'));
        $this->assertFalse($this->userModel->authenticate('naoexiste@ex.test', 'certa123'));
    }

    public function testUsuarioInativoNaoAutentica(): void
    {
        $u = uniqid();
        $email = "inativo_{$u}@ex.test";
        $this->novoUsuario('attendant', ['email' => $email, 'is_active' => 0]);
        $this->assertFalse($this->userModel->authenticate($email, 'secret123'));
    }

    public function testFindByEmailParaUnicidade(): void
    {
        $u = uniqid();
        $email = "unico_{$u}@ex.test";
        $this->novoUsuario('client', ['email' => $email]);
        $found = $this->userModel->findByEmail($email);
        $this->assertNotEmpty($found);
        $this->assertSame($email, $found['email']);
    }

    public function testUpdateNaoRegravaSenhaVazia(): void
    {
        $id = $this->novoUsuario('attendant', ['password' => password_hash('orig123', PASSWORD_DEFAULT)]);
        // update sem senha não deve apagar/alterar a senha atual
        $this->userModel->update($id, ['name' => 'Novo Nome', 'password' => '']);
        $u = $this->userModel->findById($id);
        $this->assertSame('Novo Nome', $u['name']);
        $this->assertTrue(password_verify('orig123', $u['password']), 'senha deve permanecer');
    }

    public function testToggleActiveInverteStatus(): void
    {
        $id = $this->novoUsuario('attendant', ['is_active' => 1]);
        $this->userModel->toggleActive($id);
        $this->assertSame(0, (int) $this->userModel->findById($id)['is_active']);
        $this->userModel->toggleActive($id);
        $this->assertSame(1, (int) $this->userModel->findById($id)['is_active']);
    }

    // ================= Proteção do último super_admin =================

    public function testIsLastActiveSuperAdmin(): void
    {
        // Quantos super_admins ativos já existem no banco de teste (pode haver de
        // outros testes/seed). Criamos um controlado e raciocinamos sobre a contagem.
        $baseCount = $this->userModel->countActiveSuperAdmins();

        $adminId = $this->novoUsuario('super_admin', ['is_active' => 1]);
        $this->assertSame($baseCount + 1, $this->userModel->countActiveSuperAdmins());

        // Um usuário não-admin nunca é "o último super_admin".
        $clientId = $this->novoUsuario('client');
        $this->assertFalse($this->userModel->isLastActiveSuperAdmin($clientId));

        if ($baseCount === 0) {
            // Se não havia nenhum antes, o que criamos é o único => é o último.
            $this->assertTrue($this->userModel->isLastActiveSuperAdmin($adminId));

            // Ao adicionar um segundo super_admin ativo, deixa de ser o último.
            $admin2 = $this->novoUsuario('super_admin', ['is_active' => 1]);
            $this->assertFalse($this->userModel->isLastActiveSuperAdmin($adminId));
            $this->assertFalse($this->userModel->isLastActiveSuperAdmin($admin2));
        } else {
            // Já havia outro(s) super_admin(s): o nosso não é o último.
            $this->assertFalse($this->userModel->isLastActiveSuperAdmin($adminId));
        }
    }

    public function testSuperAdminInativoNaoContaComoUltimo(): void
    {
        $id = $this->novoUsuario('super_admin', ['is_active' => 0]);
        // inativo não é "último super_admin ativo"
        $this->assertFalse($this->userModel->isLastActiveSuperAdmin($id));
    }
}
