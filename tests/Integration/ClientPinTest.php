<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use User;
use Database;

/**
 * Testes de integração do PIN de login do cliente (Fase 9) contra
 * helpdesk_on_test. Cobre: gerar/definir PIN único, resolver por PIN só clientes
 * ativos, e que o PIN do cliente (client_pin) NÃO colide com o external_pin de
 * equipe (campos distintos — não quebra o /solicitacaoexterna).
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

    private function novoCliente(array $over = []): int
    {
        $u = uniqid();
        $id = (int) $this->db->insert('users', array_merge([
            'name' => "Cli {$u}", 'email' => "cp_{$u}@example.test",
            'password' => password_hash('x', PASSWORD_BCRYPT), 'role' => 'client', 'is_active' => 1,
        ], $over));
        $this->userIds[] = $id;
        return $id;
    }

    public function testGeraPinUnicoEResolve(): void
    {
        $id = $this->novoCliente();
        $pin = $this->users->setClientPin($id, null);
        $this->assertNotNull($pin);
        $this->assertSame(6, strlen($pin));
        // Resolve o cliente pelo PIN.
        $found = $this->users->findByClientPin($pin);
        $this->assertNotNull($found);
        $this->assertSame($id, (int)$found['id']);
    }

    public function testPinDuplicadoRecusado(): void
    {
        $id1 = $this->novoCliente();
        $pin = $this->users->setClientPin($id1, '246810');
        $this->assertSame('246810', $pin);
        // Outro cliente não pode usar o mesmo PIN.
        $id2 = $this->novoCliente();
        $this->assertNull($this->users->setClientPin($id2, '246810'));
    }

    public function testClienteInativoNaoResolve(): void
    {
        $id = $this->novoCliente(['is_active' => 0]);
        $this->users->setClientPin($id, '135790');
        $this->assertNull($this->users->findByClientPin('135790'));
    }

    public function testNaoColideComPinDeEquipe(): void
    {
        // Um usuário de EQUIPE com external_pin (4 dígitos) e um CLIENTE com
        // client_pin (6 dígitos) coexistem sem interferência.
        $u = uniqid();
        $team = (int) $this->db->insert('users', [
            'name' => "Eq {$u}", 'email' => "eq_{$u}@example.test",
            'password' => password_hash('x', PASSWORD_BCRYPT), 'role' => 'attendant',
            'is_active' => 1, 'external_pin' => '4321',
        ]);
        $this->userIds[] = $team;

        $cli = $this->novoCliente();
        $this->users->setClientPin($cli, '432100');

        // findByClientPin não acha o PIN de equipe (campo diferente).
        $this->assertNull($this->users->findByClientPin('4321'));
        // findByPin (equipe) não acha o client_pin.
        $this->assertNull($this->users->findByPin('432100'));
        // Cada um resolve pelo seu campo.
        $this->assertSame($cli, (int)$this->users->findByClientPin('432100')['id']);
        $this->assertSame($team, (int)$this->users->findByPin('4321')['id']);
    }
}
