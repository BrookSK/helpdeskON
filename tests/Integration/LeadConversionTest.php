<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use LeadConversion;
use Database;

/**
 * Testes de integração da ponte lead->cliente (Fase 9) contra helpdesk_on_test.
 * Cobre: conversão cria empresa+usuário e registra o vínculo, idempotência do
 * link, e reutilização de empresa/usuário existentes.
 */
final class LeadConversionTest extends TestCase
{
    private Database $db;
    private LeadConversion $conv;
    private int $userId;
    private int $instanceId;
    private array $contactIds = [];
    private array $companyIds = [];
    private array $createdUserIds = [];
    private array $linkIds = [];

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste não configurado.');
        }
        $this->db = Database::getInstance();
        $this->conv = new LeadConversion();
        $u = uniqid();
        $this->userId = (int) $this->db->insert('users', [
            'name' => "Conv {$u}", 'email' => "conv_{$u}@example.test",
            'password' => password_hash('x', PASSWORD_BCRYPT), 'role' => 'super_admin',
        ]);
        $this->instanceId = (int) $this->db->insert('whatsapp_instances', [
            'instance_name' => "convinst_{$u}", 'api_url' => 'http://localhost', 'api_key' => 'k',
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->linkIds as $id) { try { $this->db->delete('lead_company_links', 'id = ?', [$id]); } catch (\Throwable $e) {} }
        foreach ($this->contactIds as $id) { try { $this->db->delete('whatsapp_contacts', 'id = ?', [$id]); } catch (\Throwable $e) {} }
        try { $this->db->delete('whatsapp_instances', 'id = ?', [$this->instanceId]); } catch (\Throwable $e) {}
        foreach ($this->createdUserIds as $id) { try { $this->db->delete('users', 'id = ?', [$id]); } catch (\Throwable $e) {} }
        foreach ($this->companyIds as $id) {
            try { $this->db->delete('lead_company_links', 'company_id = ?', [$id]); } catch (\Throwable $e) {}
            try { $this->db->delete('users', 'company_id = ?', [$id]); } catch (\Throwable $e) {}
            try { $this->db->delete('companies', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
        try { $this->db->delete('users', 'id = ?', [$this->userId]); } catch (\Throwable $e) {}
    }

    private function novoContato(): int
    {
        $u = uniqid();
        $id = (int) $this->db->insert('whatsapp_contacts', [
            'instance_id' => $this->instanceId,
            'remote_jid' => "55{$u}@s.whatsapp.net",
            'phone' => '5511999990000',
            'contact_name' => 'Lead Teste',
        ]);
        $this->contactIds[] = $id;
        return $id;
    }

    public function testConverteCriaEmpresaUsuarioEVinculo(): void
    {
        $u = uniqid();
        $contactId = $this->novoContato();
        $r = $this->conv->convert([
            'contact_id' => $contactId,
            'name' => 'João Cliente',
            'company_name' => "Empresa {$u}",
            'email' => "cli_{$u}@example.test",
            'phone' => '5511999990000',
        ], $this->userId);

        $this->companyIds[] = $r['company_id'];
        if (!empty($r['user_id'])) $this->createdUserIds[] = $r['user_id'];
        $this->linkIds[] = $r['link_id'];

        $this->assertTrue($r['created_company']);
        $this->assertTrue($r['created_user']);
        // Empresa e usuário existem.
        $this->assertNotNull($this->db->fetch("SELECT id FROM companies WHERE id = ?", [$r['company_id']]));
        $u2 = $this->db->fetch("SELECT * FROM users WHERE id = ?", [$r['user_id']]);
        $this->assertSame('client', $u2['role']);
        $this->assertSame($r['company_id'], (int)$u2['company_id']);
        // Vínculo registrado.
        $link = $this->db->fetch("SELECT * FROM lead_company_links WHERE id = ?", [$r['link_id']]);
        $this->assertSame($contactId, (int)$link['contact_id']);
        $this->assertSame($r['company_id'], (int)$link['company_id']);
    }

    public function testConversaoIdempotenteNaoDuplicaVinculo(): void
    {
        $u = uniqid();
        $contactId = $this->novoContato();
        $lead = [
            'contact_id' => $contactId, 'name' => 'Maria', 'company_name' => "Emp {$u}",
            'email' => "m_{$u}@example.test", 'phone' => '5511888880000',
        ];
        $r1 = $this->conv->convert($lead, $this->userId);
        $r2 = $this->conv->convert($lead, $this->userId); // repete

        $this->companyIds[] = $r1['company_id'];
        if (!empty($r1['user_id'])) $this->createdUserIds[] = $r1['user_id'];
        $this->linkIds[] = $r1['link_id'];

        // Mesma empresa e mesmo vínculo (idempotente), sem recriar.
        $this->assertSame($r1['company_id'], $r2['company_id']);
        $this->assertSame($r1['link_id'], $r2['link_id']);
        $this->assertFalse($r2['created_company']);
        $this->assertFalse($r2['created_user']);
        $count = $this->db->fetch("SELECT COUNT(*) AS t FROM lead_company_links WHERE contact_id = ? AND company_id = ?", [$contactId, $r1['company_id']]);
        $this->assertSame(1, (int)$count['t']);
    }

    public function testReutilizaEmpresaExistentePorNome(): void
    {
        $u = uniqid();
        $companyName = "Existente {$u}";
        $companyId = (int) $this->db->insert('companies', ['name' => $companyName]);
        $this->companyIds[] = $companyId;

        $contactId = $this->novoContato();
        $r = $this->conv->convert([
            'contact_id' => $contactId, 'name' => 'Z', 'company_name' => $companyName,
            'email' => "z_{$u}@example.test",
        ], $this->userId);
        if (!empty($r['user_id'])) $this->createdUserIds[] = $r['user_id'];
        $this->linkIds[] = $r['link_id'];

        $this->assertSame($companyId, $r['company_id']);
        $this->assertFalse($r['created_company']);
    }
}
