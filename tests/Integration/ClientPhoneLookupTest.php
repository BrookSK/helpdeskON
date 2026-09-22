<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use User;
use Database;

/**
 * Integração da fonte de dados usada pelo convite de acesso externo a partir
 * de um cliente cadastrado (contra helpdesk_on_test).
 *
 * Regra de negócio coberta: ao escolher um cliente cadastrado, o convite usa o
 * WhatsApp (users.phone) desse cliente; se o cliente não tiver número, o fluxo
 * deve tratar como "sem telefone" (o endpoint responde com aviso). Aqui
 * validamos a base disso: recuperar o phone do cliente pelo id.
 */
final class ClientPhoneLookupTest extends TestCase
{
    private Database $db;
    private User $users;
    private array $createdIds = [];

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste (helpdesk_on_test) não configurado.');
        }
        $this->db = Database::getInstance();
        $this->users = new User();
    }

    protected function tearDown(): void
    {
        foreach ($this->createdIds as $id) {
            try {
                $this->db->delete('users', 'id = ?', [$id]);
            } catch (\Throwable $e) {
                // ignora limpeza
            }
        }
    }

    private function novoCliente(?string $phone): int
    {
        $data = [
            'name'     => 'Cliente Teste',
            'email'    => 'cli_' . uniqid() . '@example.test',
            'password' => password_hash('x', PASSWORD_BCRYPT),
            'role'     => 'client',
        ];
        if ($phone !== null) {
            $data['phone'] = $phone;
        }
        $id = (int) $this->db->insert('users', $data);
        $this->createdIds[] = $id;
        return $id;
    }

    public function testClienteComTelefoneRetornaONumero(): void
    {
        $id = $this->novoCliente('11999998888');
        $client = $this->users->findById($id);

        $this->assertSame('client', $client['role']);
        $this->assertSame('11999998888', preg_replace('/\D/', '', $client['phone'] ?? ''));
    }

    public function testClienteSemTelefoneRetornaVazio(): void
    {
        $id = $this->novoCliente(null);
        $client = $this->users->findById($id);

        $this->assertSame('', preg_replace('/\D/', '', $client['phone'] ?? ''));
    }
}
