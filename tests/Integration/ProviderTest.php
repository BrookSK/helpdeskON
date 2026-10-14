<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Provider;
use Database;

/**
 * Testes de integração da contratação de prestadores (Fase 8) contra
 * helpdesk_on_test. Cobre: criação com normalização, fluxo de status, acessos
 * (conceder/revogar), encerramento bloqueado por acesso ativo e liberado após
 * revogar tudo (com desativação do usuário vinculado).
 */
final class ProviderTest extends TestCase
{
    private Database $db;
    private Provider $prov;
    private int $userId;
    private array $providerIds = [];
    private array $linkedUserIds = [];

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste não configurado.');
        }
        $this->db = Database::getInstance();
        $this->prov = new Provider();
        $u = uniqid();
        $this->userId = (int) $this->db->insert('users', [
            'name' => "Rh {$u}", 'email' => "rh_{$u}@example.test",
            'password' => password_hash('x', PASSWORD_BCRYPT), 'role' => 'super_admin',
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->providerIds as $id) {
            try { $this->db->delete('provider_events', 'provider_id = ?', [$id]); } catch (\Throwable $e) {}
            try { $this->db->delete('provider_accesses', 'provider_id = ?', [$id]); } catch (\Throwable $e) {}
            try { $this->db->delete('provider_documents', 'provider_id = ?', [$id]); } catch (\Throwable $e) {}
            try { $this->db->delete('providers', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
        foreach ($this->linkedUserIds as $id) {
            try { $this->db->delete('users', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
        try { $this->db->delete('users', 'id = ?', [$this->userId]); } catch (\Throwable $e) {}
    }

    private function novo(array $over = []): int
    {
        $id = $this->prov->create(array_merge([
            'name' => 'Prestador X', 'engagement_type' => 'pj', 'created_by' => $this->userId,
        ], $over));
        $this->providerIds[] = $id;
        return $id;
    }

    public function testCriaComNormalizacao(): void
    {
        $id = $this->novo(['engagement_type' => 'invalido', 'work_model' => 'remoto', 'pay_type' => 'hora']);
        $p = $this->prov->findById($id);
        $this->assertSame('pj', $p['engagement_type']); // normalizado
        $this->assertSame('remoto', $p['work_model']);
        $this->assertSame('prospect', $p['status']);
    }

    public function testFluxoDeStatus(): void
    {
        $id = $this->novo();
        $this->assertTrue($this->prov->changeStatus($id, 'proposal', $this->userId));
        $this->assertTrue($this->prov->changeStatus($id, 'contract', $this->userId));
        $this->assertTrue($this->prov->changeStatus($id, 'active', $this->userId));
        $this->assertSame('active', $this->prov->findById($id)['status']);
        // Transição inválida (ativo não cancela).
        $this->assertFalse($this->prov->changeStatus($id, 'cancelled', $this->userId));
    }

    public function testAcessosConcederERevogar(): void
    {
        $id = $this->novo();
        $this->prov->addAccess($id, 'GitHub');
        $this->prov->addAccess($id, 'Servidor');
        $acc = $this->prov->getAccesses($id);
        $this->assertCount(2, $acc);
        $this->assertNotEmpty($this->prov->pendingAccesses($id));
        // Revoga um.
        $this->prov->revokeAccess((int)$acc[0]['id'], $this->userId);
        $this->assertCount(1, $this->prov->pendingAccesses($id));
        // Revoga todos.
        $n = $this->prov->revokeAllAccesses($id, $this->userId);
        $this->assertSame(1, $n);
        $this->assertEmpty($this->prov->pendingAccesses($id));
    }

    public function testEncerramentoBloqueadoAteRevogarTudoEDesativaUsuario(): void
    {
        // Cria um usuário vinculado (acesso ao sistema).
        $uu = uniqid();
        $linkedUser = (int) $this->db->insert('users', [
            'name' => "Dev {$uu}", 'email' => "dev_{$uu}@example.test",
            'password' => password_hash('x', PASSWORD_BCRYPT), 'role' => 'developer', 'is_active' => 1,
        ]);
        $this->linkedUserIds[] = $linkedUser;

        $id = $this->novo(['user_id' => $linkedUser]);
        $this->prov->changeStatus($id, 'proposal', $this->userId);
        $this->prov->changeStatus($id, 'contract', $this->userId);
        $this->prov->changeStatus($id, 'active', $this->userId);
        $this->prov->addAccess($id, 'GitHub');

        // Com acesso ativo: encerramento recusado.
        $this->assertFalse($this->prov->terminate($id, 'fim', $this->userId));
        $this->assertSame('active', $this->prov->findById($id)['status']);
        $this->assertSame(1, (int)$this->db->fetch("SELECT is_active FROM users WHERE id = ?", [$linkedUser])['is_active']);

        // Revoga tudo e encerra: usuário é desativado.
        $this->prov->revokeAllAccesses($id, $this->userId);
        $this->assertTrue($this->prov->terminate($id, 'fim de contrato', $this->userId));
        $p = $this->prov->findById($id);
        $this->assertSame('terminated', $p['status']);
        $this->assertNotNull($p['terminated_at']);
        $this->assertSame(0, (int)$this->db->fetch("SELECT is_active FROM users WHERE id = ?", [$linkedUser])['is_active']);
    }

    public function testDocumentosEEventos(): void
    {
        $id = $this->novo();
        $this->prov->addDocument($id, 'Contrato PJ', null, 'assinado');
        $this->assertCount(1, $this->prov->getDocuments($id));
        // O model Provider::create() não gera evento (quem gera é o controller).
        // Registramos um evento explicitamente e conferimos a trilha.
        $this->prov->addEvent($id, $this->userId, 'nota', 'teste');
        $this->assertNotEmpty($this->prov->getEvents($id));
    }
}
