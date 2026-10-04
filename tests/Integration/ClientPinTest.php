<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use User;
use Database;

/**
 * Testes de integração do PIN de login por usuário (contra helpdesk_on_test).
 * Cobre: gerar/definir PIN único de 4 dígitos, resolver por PIN apenas usuários
 * ativos, e que o PIN vale para QUALQUER papel (não só clientes).
 */
final class ClientPinTest extends TestCase
{
    private Database $db;
    private User $users;
    private array $userIds = [];

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste não configurado.');
        }
        $this->db = Database::getInstance();
        $this->users = new User();
    }

    protected function tearDown(): void
    {
        foreach ($this->userIds as $id) {
            try { $this->db->delete('users', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
    }

    private function novoUsuario(array $over = []): int
    {
        $u = uniqid();
        $id = (int) $this->db->insert('users', array_merge([
            'name' => "User {$u}", 'email' => "cp_{$u}@example.test",
            'password' => password_hash('x', PASSWORD_BCRYPT), 'role' => 'client', 'is_active' => 1,
        ], $over));
        $this->userIds[] = $id;
        return $id;
    }

    public function testGeraPinUnicoDe4DigitosEResolve(): void
    {
        $id = $this->novoUsuario();
        $pin = $this->users->setClientPin($id, null);
        $this->assertNotNull($pin);
        $this->assertSame(4, strlen($pin));
        $found = $this->users->findByClientPin($pin);
        $this->assertNotNull($found);
        $this->assertSame($id, (int)$found['id']);
    }

    public function testPinDuplicadoRecusado(): void
    {
        $id1 = $this->novoUsuario();
        $pin = $this->users->setClientPin($id1, '2468');
        $this->assertSame('2468', $pin);
        // Outro usuário não pode usar o mesmo PIN.
        $id2 = $this->novoUsuario();
        $this->assertNull($this->users->setClientPin($id2, '2468'));
    }

    public function testUsuarioInativoNaoResolve(): void
    {
        $id = $this->novoUsuario(['is_active' => 0]);
        $this->users->setClientPin($id, '1357');
        $this->assertNull($this->users->findByClientPin('1357'));
    }

    public function testPinValeParaQualquerPapel(): void
    {
        // O PIN é por usuário: um papel de equipe (ex.: developer) também loga.
        $dev = $this->novoUsuario(['role' => 'developer', 'email' => 'dev_' . uniqid() . '@example.test']);
        $this->users->setClientPin($dev, '7788');
        $found = $this->users->findByClientPin('7788');
        $this->assertNotNull($found);
        $this->assertSame($dev, (int)$found['id']);
        $this->assertSame('developer', $found['role']);

        $admin = $this->novoUsuario(['role' => 'super_admin', 'email' => 'adm_' . uniqid() . '@example.test']);
        $this->users->setClientPin($admin, '9900');
        $this->assertSame($admin, (int)$this->users->findByClientPin('9900')['id']);
    }
}
