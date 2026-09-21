<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use EmailProspection;
use EmailAccount;
use Database;

/**
 * Testes de integração de Prospecção de E-mail e Contas de E-mail contra o
 * banco helpdesk_on_test. Cobre CRUD do histórico, filtros, criptografia de
 * senha, vínculo conta↔usuário e a sanitização de header (unitária).
 */
final class EmailProspectionTest extends TestCase
{
    private Database $db;
    private EmailProspection $model;
    private EmailAccount $accountModel;
    private int $userA;
    private int $userB;
    private int $accountId;
    private int $contactId;
    /** @var int[] */
    private array $prospectionIds = [];

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste (helpdesk_on_test) não configurado.');
        }
        $this->db = Database::getInstance();
        $this->model = new EmailProspection();
        $this->accountModel = new EmailAccount();

        $u = uniqid();
        $this->userA = $this->novoUsuario("User A {$u}", "ua_{$u}@example.test", 'comercial');
        $this->userB = $this->novoUsuario("User B {$u}", "ub_{$u}@example.test", 'comercial');

        $phone = '5511' . random_int(100000000, 999999999);
        $this->contactId = (int) $this->db->insert('whatsapp_contacts', [
            'remote_jid' => $phone . '@s.whatsapp.net',
            'contact_name' => "Lead {$u}",
            'phone' => $phone,
        ]);

        $this->accountId = (int) $this->accountModel->create([
            'email' => "conta_{$u}@example.test",
            'display_name' => 'Conta Teste',
            'smtp_host' => 'smtp.example.test',
            'smtp_port' => 587,
            'smtp_encryption' => 'tls',
            'smtp_username' => "conta_{$u}",
            'smtp_password' => 'SenhaSecreta123',
            'created_by' => $this->userA,
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->prospectionIds as $id) {
            try { $this->db->delete('email_prospections', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
        try { $this->db->delete('email_account_users', 'email_account_id = ?', [$this->accountId]); } catch (\Throwable $e) {}
        try { $this->db->delete('email_accounts', 'id = ?', [$this->accountId]); } catch (\Throwable $e) {}
        try { $this->db->delete('whatsapp_contacts', 'id = ?', [$this->contactId]); } catch (\Throwable $e) {}
        foreach ([$this->userA, $this->userB] as $id) {
            try { $this->db->delete('users', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
    }

    private function novoUsuario(string $name, string $email, string $role): int
    {
        return (int) $this->db->insert('users', [
            'name' => $name, 'email' => $email,
            'password' => password_hash('x', PASSWORD_BCRYPT), 'role' => $role,
        ]);
    }

    private function novaProspeccao(array $overrides = []): int
    {
        $id = (int) $this->model->create(array_merge([
            'user_id' => $this->userA,
            'email_account_id' => $this->accountId,
            'contact_id' => $this->contactId,
            'recipient_email' => 'lead@example.test',
            'subject' => 'Assunto',
            'body' => '<p>corpo</p>',
            'status' => 'sent',
            'sent_at' => date('Y-m-d H:i:s'),
        ], $overrides));
        $this->prospectionIds[] = $id;
        return $id;
    }

    // ===== Criptografia de senha =====

    public function testSenhaEhCriptografadaERecuperavel(): void
    {
        $acc = $this->accountModel->findById($this->accountId);
        // No banco não fica em texto puro
        $this->assertNotSame('SenhaSecreta123', $acc['smtp_password']);
        // Mas descriptografa de volta corretamente
        $this->assertSame('SenhaSecreta123', EmailAccount::decryptPassword($acc['smtp_password']));
    }

    // ===== Vínculo conta ↔ usuário =====

    public function testVinculoDeUsuarios(): void
    {
        $this->accountModel->setLinkedUsers($this->accountId, [$this->userA, $this->userB]);
        $ids = array_map('intval', $this->accountModel->getLinkedUserIds($this->accountId));
        $this->assertContains($this->userA, $ids);
        $this->assertContains($this->userB, $ids);

        // getByUser respeita o vínculo e is_active
        $contas = $this->accountModel->getByUser($this->userA);
        $accIds = array_map(fn($c) => (int) $c['id'], $contas);
        $this->assertContains($this->accountId, $accIds);

        // Substituição: agora só userB
        $this->accountModel->setLinkedUsers($this->accountId, [$this->userB]);
        $ids = array_map('intval', $this->accountModel->getLinkedUserIds($this->accountId));
        $this->assertNotContains($this->userA, $ids);
        $this->assertContains($this->userB, $ids);
    }

    // ===== Histórico / filtros =====

    public function testGetAllFiltraPorUsuarioEStatus(): void
    {
        $enviado = $this->novaProspeccao(['status' => 'sent', 'user_id' => $this->userA]);
        $falho = $this->novaProspeccao(['status' => 'failed', 'user_id' => $this->userA, 'error_message' => 'erro']);
        $doB = $this->novaProspeccao(['status' => 'sent', 'user_id' => $this->userB]);

        $daA = $this->model->getAll(['user_id' => $this->userA]);
        $ids = array_map(fn($r) => (int) $r['id'], $daA);
        $this->assertContains($enviado, $ids);
        $this->assertContains($falho, $ids);
        $this->assertNotContains($doB, $ids);

        $falhas = $this->model->getAll(['user_id' => $this->userA, 'status' => 'failed']);
        $idsFalha = array_map(fn($r) => (int) $r['id'], $falhas);
        $this->assertContains($falho, $idsFalha);
        $this->assertNotContains($enviado, $idsFalha);
    }

    public function testFindByIdTrazJoins(): void
    {
        $id = $this->novaProspeccao();
        $row = $this->model->findById($id);
        $this->assertNotEmpty($row);
        $this->assertNotEmpty($row['user_name']);
        $this->assertNotEmpty($row['account_email']);
        $this->assertNotEmpty($row['lead_name']);
    }

    public function testCreateNormalizaStatusForaDoEnum(): void
    {
        $id = $this->novaProspeccao(['status' => 'queued']);
        $row = $this->db->fetch("SELECT status FROM email_prospections WHERE id = ?", [$id]);
        $this->assertSame('sent', $row['status']);
    }

    // ===== Sanitização de header (segurança) =====

    public function testSanitizeHeaderRemoveCRLF(): void
    {
        $malicioso = "Assunto\r\nBcc: vitima@example.com";
        $limpo = EmailProspection::sanitizeHeader($malicioso);
        $this->assertStringNotContainsString("\r", $limpo);
        $this->assertStringNotContainsString("\n", $limpo);
        $this->assertSame('AssuntoBcc: vitima@example.com', $limpo);
    }
}
