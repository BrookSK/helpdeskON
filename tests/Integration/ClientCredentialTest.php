<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use ClientCredential;
use CredentialCrypto;
use Database;

/**
 * Testes de integração do cofre de credenciais (Fase 6) contra helpdesk_on_test.
 * Cobre: o segredo é gravado CRIPTOGRAFADO, reveal recupera o texto puro, a
 * listagem não expõe o valor (só máscara), e edição sem segredo mantém o atual.
 */
final class ClientCredentialTest extends TestCase
{
    private Database $db;
    private ClientCredential $creds;
    private int $userId;
    private array $credIds = [];

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste não configurado.');
        }
        $this->db = Database::getInstance();
        $this->creds = new ClientCredential();
        $u = uniqid();
        $this->userId = (int) $this->db->insert('users', [
            'name' => "Cred {$u}", 'email' => "c_{$u}@example.test",
            'password' => password_hash('x', PASSWORD_BCRYPT), 'role' => 'super_admin',
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->credIds as $id) {
            try { $this->db->delete('client_credentials', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
        try { $this->db->delete('users', 'id = ?', [$this->userId]); } catch (\Throwable $e) {}
    }

    private function nova(string $secret = 'senha123'): int
    {
        $id = $this->creds->create([
            'service_label' => 'Servidor VPS', 'username' => 'root',
            'secret' => $secret, 'url' => 'https://painel.exemplo', 'created_by' => $this->userId,
        ]);
        $this->credIds[] = $id;
        return $id;
    }

    public function testSegredoGravadoCriptografado(): void
    {
        $id = $this->nova('senha-super-secreta');
        // Lê a linha crua direto do banco: nunca contém o texto puro.
        $row = $this->db->fetch("SELECT * FROM client_credentials WHERE id = ?", [$id]);
        $this->assertNotEmpty($row['secret_encrypted']);
        $this->assertStringNotContainsString('senha-super-secreta', (string)$row['secret_encrypted']);
        // E o ciphertext decifra para o valor original.
        $this->assertSame('senha-super-secreta', CredentialCrypto::decrypt($row['secret_encrypted']));
    }

    public function testRevealRecuperaTextoPuro(): void
    {
        $id = $this->nova('tok_abc_123');
        $this->assertSame('tok_abc_123', $this->creds->revealSecret($id));
        // ID inexistente -> null.
        $this->assertNull($this->creds->revealSecret(99999999));
    }

    public function testListagemNaoExpoeSegredo(): void
    {
        $id = $this->nova('nao-mostrar');
        $rows = $this->creds->getAll();
        $found = null;
        foreach ($rows as $r) { if ((int)$r['id'] === $id) { $found = $r; break; } }
        $this->assertNotNull($found);
        // A listagem traz máscara e NÃO o ciphertext nem o valor.
        $this->assertSame('••••••••', $found['secret_mask']);
        $this->assertArrayNotHasKey('secret_encrypted', $found);
    }

    public function testUpdateSemSegredoMantemAtual(): void
    {
        $id = $this->nova('original');
        // Atualiza sem informar secret: mantém o segredo anterior.
        $this->creds->update($id, [
            'service_label' => 'Servidor VPS (editado)', 'username' => 'admin', 'secret' => '',
        ]);
        $this->assertSame('original', $this->creds->revealSecret($id));
        $this->assertSame('Servidor VPS (editado)', $this->creds->findById($id)['service_label']);

        // Atualiza COM novo secret: substitui.
        $this->creds->update($id, ['service_label' => 'Servidor VPS (editado)', 'secret' => 'novo-segredo']);
        $this->assertSame('novo-segredo', $this->creds->revealSecret($id));
    }

    public function testDelete(): void
    {
        $id = $this->nova();
        $this->creds->delete($id);
        // Database::fetch() retorna false (não null) quando não há linha.
        $this->assertEmpty($this->creds->findById($id));
    }
}
