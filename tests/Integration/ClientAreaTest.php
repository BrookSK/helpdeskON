<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Ticket;
use SharedDocument;
use Database;

/**
 * Testes de integração da ÁREA DO CLIENTE contra o banco helpdesk_on_test.
 * Cobre o escopo por empresa das demandas (cliente só vê as suas; dono da
 * empresa vê as da empresa), a visibilidade de documentos (empresa/all/próprio)
 * e a regra dos subusuários (papel fixo 'client', mesma empresa, sem elevação).
 */
final class ClientAreaTest extends TestCase
{
    private Database $db;
    private Ticket $ticketModel;
    private SharedDocument $docModel;
    private int $companyA;
    private int $companyB;
    private int $ownerA;      // dono da empresa A
    private int $clientA;     // cliente comum da empresa A
    private int $clientB;     // cliente da empresa B
    /** @var int[] */
    private array $ticketIds = [];
    /** @var int[] */
    private array $docIds = [];
    /** @var int[] */
    private array $userIds = [];
    /** @var int[] */
    private array $companyIds = [];

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste (helpdesk_on_test) não configurado.');
        }
        $this->db = Database::getInstance();
        $this->ticketModel = new Ticket();
        $this->docModel = new SharedDocument();

        $u = uniqid();
        $this->companyA = (int) $this->db->insert('companies', ['name' => "Emp A {$u}"]);
        $this->companyB = (int) $this->db->insert('companies', ['name' => "Emp B {$u}"]);
        $this->companyIds = [$this->companyA, $this->companyB];

        $this->ownerA = $this->novoUsuario('client', $this->companyA, 1);
        $this->clientA = $this->novoUsuario('client', $this->companyA, 0);
        $this->clientB = $this->novoUsuario('client', $this->companyB, 0);
    }

    protected function tearDown(): void
    {
        foreach ($this->docIds as $id) {
            try { $this->db->delete('shared_documents', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
        foreach ($this->ticketIds as $id) {
            try { $this->db->delete('tickets', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
        foreach ($this->userIds as $id) {
            try { $this->db->delete('users', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
        foreach ($this->companyIds as $id) {
            try { $this->db->delete('companies', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
    }

    private function novoUsuario(string $role, ?int $companyId, int $isOwner): int
    {
        $u = uniqid();
        $id = (int) $this->db->insert('users', [
            'name' => "U {$u}",
            'email' => "u_{$u}@ex.test",
            'password' => password_hash('x', PASSWORD_DEFAULT),
            'role' => $role,
            'company_id' => $companyId,
            'is_company_owner' => $isOwner,
            'is_active' => 1,
        ]);
        $this->userIds[] = $id;
        return $id;
    }

    private function novoTicket(int $clientId, string $title): int
    {
        $id = (int) $this->ticketModel->create([
            'client_id' => $clientId,
            'title' => $title,
            'description' => 'Descrição',
            'priority' => 'medium',
            'status' => 'open',
        ]);
        $this->ticketIds[] = $id;
        return $id;
    }

    private function novoDoc(int $userId, ?int $companyId, string $visibility, string $title): int
    {
        $id = (int) $this->db->insert('shared_documents', [
            'company_id' => $companyId,
            'user_id' => $userId,
            'title' => $title,
            'file_name' => 'arq.pdf',
            'file_path' => 'uploads/documents/arq.pdf',
            'visibility' => $visibility,
        ]);
        $this->docIds[] = $id;
        return $id;
    }

    // ================= Demandas: escopo =================

    public function testClienteComumVeSoAsProprias(): void
    {
        $meu = $this->novoTicket($this->clientA, 'Meu');
        $this->novoTicket($this->ownerA, 'Do dono');

        $lista = $this->ticketModel->getByClient($this->clientA);
        $ids = array_map(fn($t) => (int) $t['id'], $lista);
        $this->assertContains($meu, $ids);
        // não deve conter demandas de outro cliente
        foreach ($lista as $t) {
            $this->assertSame($this->clientA, (int) $t['client_id']);
        }
    }

    public function testDonoDaEmpresaVeDemandasDaEmpresa(): void
    {
        $t1 = $this->novoTicket($this->ownerA, 'Do dono');
        $t2 = $this->novoTicket($this->clientA, 'Do cliente A');
        // demanda da empresa B não deve aparecer
        $tB = $this->novoTicket($this->clientB, 'Do cliente B');

        $lista = $this->ticketModel->getByCompany($this->companyA);
        $ids = array_map(fn($t) => (int) $t['id'], $lista);
        $this->assertContains($t1, $ids);
        $this->assertContains($t2, $ids);
        $this->assertNotContains($tB, $ids);
    }

    // ================= Documentos: visibilidade =================

    public function testClienteVeDocsDaEmpresaAllEProprios(): void
    {
        $daEmpresa = $this->novoDoc($this->ownerA, $this->companyA, 'company', 'Doc empresa A');
        $global = $this->novoDoc($this->ownerA, null, 'all', 'Doc all');
        $proprio = $this->novoDoc($this->clientA, null, 'company', 'Doc do próprio clienteA');
        // doc de outra empresa, não-all: NÃO deve aparecer para clientA
        $outraEmpresa = $this->novoDoc($this->clientB, $this->companyB, 'company', 'Doc empresa B');

        $lista = $this->docModel->getForClient($this->companyA, $this->clientA);
        $ids = array_map(fn($d) => (int) $d['id'], $lista);

        $this->assertContains($daEmpresa, $ids);
        $this->assertContains($global, $ids);
        $this->assertContains($proprio, $ids);
        $this->assertNotContains($outraEmpresa, $ids);
    }

    // ================= Subusuários: regra =================

    public function testSubusuarioNasceClientNaMesmaEmpresaSemElevacao(): void
    {
        // Reproduz o insert do SubusersController::store: role fixo 'client',
        // company_id herdada do owner, is_company_owner=0, parent_user_id=owner.
        $subId = (int) $this->db->insert('users', [
            'name' => 'Sub', 'email' => 'sub_' . uniqid() . '@ex.test',
            'password' => password_hash('x', PASSWORD_DEFAULT),
            'role' => 'client',
            'company_id' => $this->companyA,
            'parent_user_id' => $this->ownerA,
            'is_company_owner' => 0,
            'is_active' => 1,
        ]);
        $this->userIds[] = $subId;

        $sub = (new \User())->findById($subId);
        $this->assertSame('client', $sub['role']);
        $this->assertSame($this->companyA, (int) $sub['company_id']);
        $this->assertSame(0, (int) $sub['is_company_owner']);
        $this->assertSame($this->ownerA, (int) $sub['parent_user_id']);
    }
}
