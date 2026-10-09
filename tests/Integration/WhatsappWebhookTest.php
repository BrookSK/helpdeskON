<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use WhatsappWebhook;
use WhatsappWebhookRequest;
use WhatsappWebhookStat;
use Database;

/**
 * Integração dos webhooks de ENTRADA do WhatsApp (migration 161).
 *
 * Cobre os models WhatsappWebhook / WhatsappWebhookRequest contra o banco de
 * teste: CRUD do webhook, resolução por token (ativo/inativo), geração de token
 * único, e o registro/consulta das requisições (incl. polling por after_id e a
 * fila pendingForProcessing).
 *
 * NÃO cobre o envio real pela Evolution API (depende de rede/instância); isso é
 * validado manualmente no ambiente com WhatsApp conectado.
 */
final class WhatsappWebhookTest extends TestCase
{
    private Database $db;
    private WhatsappWebhook $webhooks;
    private WhatsappWebhookRequest $requests;
    private int $companyId;
    /** @var int[] */
    private array $webhookIds = [];

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste (helpdesk_on_test) não configurado.');
        }
        $this->db = Database::getInstance();

        // Pula se a migration 161 ainda não foi aplicada no banco de teste.
        try {
            $this->db->query("SELECT 1 FROM whatsapp_webhooks LIMIT 1");
            $this->db->query("SELECT 1 FROM whatsapp_webhook_requests LIMIT 1");
            $this->db->query("SELECT 1 FROM whatsapp_webhook_stats LIMIT 1");
        } catch (\Throwable $e) {
            $this->markTestSkipped('Tabelas de webhooks ausentes (rode a migration 161).');
        }

        $this->webhooks = new WhatsappWebhook();
        $this->requests = new WhatsappWebhookRequest();

        $u = uniqid();
        $this->companyId = (int) $this->db->insert('companies', ['name' => "Empresa WH {$u}"]);
    }

    protected function tearDown(): void
    {
        foreach ($this->webhookIds as $id) {
            // requests/stats caem por ON DELETE CASCADE, mas limpamos defensivamente.
            try { $this->db->delete('whatsapp_webhook_requests', 'webhook_id = ?', [$id]); } catch (\Throwable $e) {}
            try { $this->db->delete('whatsapp_webhook_stats', 'webhook_id = ?', [$id]); } catch (\Throwable $e) {}
            try { $this->db->delete('whatsapp_webhooks', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
        if (!empty($this->companyId)) {
            try { $this->db->delete('companies', 'id = ?', [$this->companyId]); } catch (\Throwable $e) {}
        }
    }

    private function novoWebhook(array $over = []): int
    {
        $data = array_merge([
            'company_id'       => $this->companyId,
            'instance_id'      => null,
            'name'             => 'WH ' . uniqid(),
            'token'            => $this->webhooks->generateUniqueToken(),
            'phone_field'      => 'phone',
            'name_field'       => 'name',
            'email_field'      => 'email',
            'message_field'    => 'message',
            'message_template' => 'Olá {{nome}}, {{mensagem}}',
            'active'           => 1,
        ], $over);
        $id = (int) $this->webhooks->create($data);
        $this->webhookIds[] = $id;
        return $id;
    }

    public function testCriaEResolvePorToken(): void
    {
        $id = $this->novoWebhook();
        $w = $this->webhooks->findById($id);
        $this->assertNotEmpty($w);
        $this->assertSame($this->companyId, (int) $w['company_id']);

        $byToken = $this->webhooks->findActiveByToken($w['token']);
        $this->assertNotEmpty($byToken);
        $this->assertSame($id, (int) $byToken['id']);
    }

    public function testTokenUnicoTem32CharsHex(): void
    {
        $t1 = $this->webhooks->generateUniqueToken();
        $t2 = $this->webhooks->generateUniqueToken();
        $this->assertSame(32, strlen($t1));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $t1);
        $this->assertNotSame($t1, $t2);
    }

    public function testWebhookInativoNaoResolvePorTokenAtivo(): void
    {
        $id = $this->novoWebhook(['active' => 0]);
        $w = $this->webhooks->findById($id);
        // findActiveByToken não resolve inativo: retorna "vazio" (PDO fetch() dá
        // false quando não há linha; o importante é não trazer o webhook).
        $this->assertEmpty($this->webhooks->findActiveByToken($w['token']));
    }

    public function testFindByTokenResolveAtivoEInativo(): void
    {
        // findByToken resolve independentemente de active (modo teste do incoming).
        $ativo = $this->webhooks->findById($this->novoWebhook(['active' => 1]));
        $inativo = $this->webhooks->findById($this->novoWebhook(['active' => 0]));

        $rAtivo = $this->webhooks->findByToken($ativo['token']);
        $rInativo = $this->webhooks->findByToken($inativo['token']);
        $this->assertNotEmpty($rAtivo);
        $this->assertSame((int)$ativo['id'], (int)$rAtivo['id']);
        $this->assertNotEmpty($rInativo);
        $this->assertSame((int)$inativo['id'], (int)$rInativo['id']);
        $this->assertSame(0, (int)$rInativo['active']);
    }

    public function testToggleActive(): void
    {
        $id = $this->novoWebhook(['active' => 1]);
        $this->assertSame(0, $this->webhooks->toggleActive($id));
        $this->assertSame(1, $this->webhooks->toggleActive($id));
    }

    public function testGetByCompanyTrazNomeDaEmpresa(): void
    {
        $id = $this->novoWebhook();
        $rows = $this->webhooks->getByCompany($this->companyId);
        $this->assertNotEmpty($rows);
        $found = null;
        foreach ($rows as $r) { if ((int)$r['id'] === $id) { $found = $r; break; } }
        $this->assertNotNull($found);
        $this->assertArrayHasKey('company_name', $found);
        $this->assertStringStartsWith('Empresa WH', (string) $found['company_name']);
    }

    public function testUpdateAlteraMapeamento(): void
    {
        $id = $this->novoWebhook();
        $this->webhooks->update($id, ['phone_field' => 'data.celular', 'message_template' => 'Novo {{mensagem}}']);
        $w = $this->webhooks->findById($id);
        $this->assertSame('data.celular', $w['phone_field']);
        $this->assertSame('Novo {{mensagem}}', $w['message_template']);
    }

    public function testRegistraRequestEPollingIncremental(): void
    {
        $id = $this->novoWebhook();

        $r1 = (int) $this->requests->create([
            'webhook_id'     => $id,
            'source_ip'      => '127.0.0.1',
            'raw_payload'    => '{"phone":"11999998888","message":"oi"}',
            'parsed_phones'  => json_encode(['5511999998888']),
            'parsed_name'    => 'Ana',
            'parsed_email'   => null,
            'parsed_message' => 'Olá Ana, oi',
            'status'         => 'queued',
        ]);
        $this->assertGreaterThan(0, $r1);

        // Carga inicial (after_id=0) traz a request recém-criada.
        $initial = $this->requests->getByWebhook($id, 0);
        $this->assertCount(1, $initial);
        $this->assertSame('queued', $initial[0]['status']);

        // Polling incremental: nada novo depois do último id.
        $this->assertCount(0, $this->requests->getByWebhook($id, $r1));

        // Nova request -> aparece no incremental.
        $r2 = (int) $this->requests->create([
            'webhook_id'  => $id,
            'raw_payload' => '{}',
            'status'      => 'skipped',
        ]);
        $inc = $this->requests->getByWebhook($id, $r1);
        $this->assertCount(1, $inc);
        $this->assertSame($r2, (int) $inc[0]['id']);
    }

    public function testPendingForProcessingRespeitaStatusEAttempts(): void
    {
        $id = $this->novoWebhook();
        $pend = (int) $this->requests->create([
            'webhook_id' => $id, 'raw_payload' => '{}', 'status' => 'queued', 'attempts' => 0,
        ]);
        $sent = (int) $this->requests->create([
            'webhook_id' => $id, 'raw_payload' => '{}', 'status' => 'sent', 'attempts' => 1,
        ]);
        $exhausted = (int) $this->requests->create([
            'webhook_id' => $id, 'raw_payload' => '{}', 'status' => 'queued', 'attempts' => 3,
        ]);

        $ids = array_map(fn($r) => (int) $r['id'], $this->requests->pendingForProcessing(50, 3));
        $this->assertContains($pend, $ids);          // queued, attempts < 3
        $this->assertNotContains($sent, $ids);       // já enviado
        $this->assertNotContains($exhausted, $ids);  // estourou tentativas
    }

    public function testUpdateStatusPersiste(): void
    {
        $id = $this->novoWebhook();
        $rid = (int) $this->requests->create([
            'webhook_id' => $id, 'raw_payload' => '{}', 'status' => 'queued',
        ]);
        $this->requests->updateStatus($rid, [
            'status' => 'sent', 'sent_count' => 2, 'processed_at' => date('Y-m-d H:i:s'),
        ]);
        $row = $this->requests->findById($rid);
        $this->assertSame('sent', $row['status']);
        $this->assertSame(2, (int) $row['sent_count']);
        $this->assertNotEmpty($row['processed_at']);
    }

    // ───────────────────────────────────────────────────────────────────────
    // Contador diário de volume (whatsapp_webhook_stats)
    // ───────────────────────────────────────────────────────────────────────

    public function testStatBumpAcumulaContadorDoDia(): void
    {
        $id = $this->novoWebhook();
        $stats = new WhatsappWebhookStat();

        // Sem nenhum bump: tudo zero.
        $this->assertSame(['received' => 0, 'sent' => 0, 'failed' => 0], $stats->today($id));

        // Incrementos atômicos: 3 recebidas, 2 enviadas, 1 falha (com step).
        $stats->bump($id, 'received');
        $stats->bump($id, 'received', 2);
        $stats->bump($id, 'sent', 2);
        $stats->bump($id, 'failed');

        $today = $stats->today($id);
        $this->assertSame(3, $today['received']);
        $this->assertSame(2, $today['sent']);
        $this->assertSame(1, $today['failed']);
    }

    public function testStatBumpIgnoraCampoInvalidoEValorNaoPositivo(): void
    {
        $id = $this->novoWebhook();
        $stats = new WhatsappWebhookStat();

        $stats->bump($id, 'inexistente');   // campo fora da lista -> ignora
        $stats->bump($id, 'received', 0);    // step 0 -> ignora
        $stats->bump($id, 'received', -5);   // step negativo -> ignora

        $this->assertSame(['received' => 0, 'sent' => 0, 'failed' => 0], $stats->today($id));
    }

    public function testStatGetStatsTotalizaPeriodo(): void
    {
        $id = $this->novoWebhook();
        $stats = new WhatsappWebhookStat();

        $stats->bump($id, 'received', 10);
        $stats->bump($id, 'sent', 7);
        $stats->bump($id, 'failed', 3);

        $out = $stats->getStats($id, 7);
        $this->assertArrayHasKey('days', $out);
        $this->assertArrayHasKey('totals', $out);
        $this->assertSame(10, $out['totals']['received']);
        $this->assertSame(7, $out['totals']['sent']);
        $this->assertSame(3, $out['totals']['failed']);
        // Pelo menos a linha de hoje.
        $this->assertNotEmpty($out['days']);
    }
}
