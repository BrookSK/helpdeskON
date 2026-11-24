<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Database;
use EmailAccount;
use EmailMessageService;
use EmailProspection;
use LeadEmailHistory;

/** SMTP falso: o resto do envio (email_messages + espelho) é o real. */
final class FakeSmtpForHistoryTest extends EmailProspection
{
    public function sendEmail($account, $to, $subject, $htmlBody, $cc = null, $bcc = null, $attachments = [], array $options = [])
    {
        return true;
    }
}

/**
 * Integração (helpdesk_on_test): aba "E-mails" do card no CRM.
 * Envio da sequência → resposta do lead casada (texto completo guardado) →
 * nossa resposta pela Caixa de entrada → nova resposta do lead (registro só
 * para o histórico). Tudo numa conversa, sem duplicar o espelho do envio.
 */
final class LeadEmailHistoryTest extends TestCase
{
    private Database $db;
    private string $u;
    private string $leadEmail;
    private int $userId;
    private int $accountId;
    private int $contactId;
    private int $duplicateContactId;

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste não configurado.');
        }
        $this->db = Database::getInstance();
        \SchemaInfo::reset();
        $this->u = uniqid();
        $this->leadEmail = "carla_{$this->u}@lead.test";
        $this->userId = (int) $this->db->insert('users', [
            'name' => "Evelyn {$this->u}", 'email' => "hist_{$this->u}@example.test",
            'password' => password_hash('x', PASSWORD_BCRYPT), 'role' => 'comercial',
        ]);
        $this->contactId = (int) $this->db->insert('whatsapp_contacts', [
            'remote_jid' => "hist{$this->u}@s.whatsapp.net", 'contact_name' => "Carla {$this->u}", 'lead_email' => $this->leadEmail,
        ]);
        // Mesmo e-mail em outro cadastro (lead duplicado)
        $this->duplicateContactId = (int) $this->db->insert('whatsapp_contacts', [
            'remote_jid' => "hist2{$this->u}@s.whatsapp.net", 'contact_name' => "Carla dup {$this->u}", 'lead_email' => $this->leadEmail,
        ]);
        $this->accountId = (int) (new EmailAccount())->create([
            'email' => "conta_{$this->u}@example.test", 'display_name' => 'Conta Hist',
            'smtp_host' => 'smtp.example.test', 'smtp_port' => 587, 'smtp_encryption' => 'tls',
            'smtp_username' => "conta_{$this->u}", 'smtp_password' => 'x', 'created_by' => $this->userId,
        ]);
    }

    protected function tearDown(): void
    {
        foreach ([$this->contactId, $this->duplicateContactId] as $cid) {
            foreach (['email_events', 'email_messages', 'email_prospections', 'lead_timeline', 'lead_score'] as $t) {
                try { $this->db->delete($t, 'contact_id = ?', [$cid]); } catch (\Throwable $e) {}
            }
        }
        try { $this->db->delete('email_prospections', 'recipient_email = ?', [$this->leadEmail]); } catch (\Throwable $e) {}
        try { $this->db->delete('email_accounts', 'id = ?', [$this->accountId]); } catch (\Throwable $e) {}
        foreach ([$this->contactId, $this->duplicateContactId] as $cid) {
            try { $this->db->delete('whatsapp_contacts', 'id = ?', [$cid]); } catch (\Throwable $e) {}
        }
        try { $this->db->delete('users', 'id = ?', [$this->userId]); } catch (\Throwable $e) {}
        $this->dropAddedColumns();
    }

    private function service(): EmailMessageService
    {
        $svc = new EmailMessageService();
        $p = new \ReflectionProperty(EmailMessageService::class, 'prospection');
        $p->setValue($svc, new FakeSmtpForHistoryTest());
        return $svc;
    }

    private function sendFromSequence(): array
    {
        $r = $this->service()->send([
            'contact_id' => $this->contactId, 'account' => (new EmailAccount())->findById($this->accountId),
            'to' => $this->leadEmail, 'subject' => 'Proposta ON', 'body_html' => '<p><strong>Olá Carla</strong>, segue a proposta.</p>',
            'origin' => 'sequence', 'sent_by' => $this->userId,
        ]);
        $this->assertTrue($r['success'], $r['error'] ?? '');
        return $this->db->fetch("SELECT * FROM email_messages WHERE id = ?", [$r['message_id']]);
    }

    /**
     * Fluxo sem as colunas da migration 140 (banco local em MySQL não tem; a
     * migration usa sintaxe do MariaDB). Respostas gravadas só com as colunas
     * da 065 também aparecem.
     */
    public function testHistoricoComColunasBasicas(): void
    {
        $this->sendFromSequence();
        $base = time();
        // Resposta do lead gravada no cadastro duplicado (só colunas da migration 065)
        $this->db->insert('email_messages', [
            'contact_id' => $this->duplicateContactId, 'direction' => 'inbound', 'origin' => 'manual',
            'thread_key' => $this->leadEmail, 'recipient_email' => $this->leadEmail, 'subject' => 'Re: Proposta ON',
            'body' => "Gostei.\nVamos conversar?", 'status' => 'received', 'created_at' => date('Y-m-d H:i:s', $base + 60),
        ]);
        // Nossa resposta pela Caixa de entrada (sem contact_id)
        $this->db->insert('email_prospections', [
            'user_id' => $this->userId, 'email_account_id' => $this->accountId, 'contact_id' => null,
            'recipient_email' => $this->leadEmail, 'subject' => 'Re: Proposta ON', 'body' => '<p>Vamos sim!</p>',
            'status' => 'sent', 'sent_at' => date('Y-m-d H:i:s', $base + 3600),
        ]);

        $h = (new LeadEmailHistory())->forContact($this->contactId);
        $this->assertCount(1, $h['conversations']);
        $items = $h['conversations'][0]['items'];
        $this->assertSame(['out', 'in', 'out'], array_column($items, 'direction'));
        $this->assertSame("Gostei.\nVamos conversar?", $items[1]['body_text'], 'resposta no cadastro duplicado entra pelo e-mail do lead');
        $this->assertSame('inbox_reply', $items[2]['source']);
        $this->assertSame(['total' => 3, 'sent' => 2, 'failed' => 0, 'replies' => 1], array_slice($h['summary'], 0, 4));

        // O cadastro duplicado (mesmo e-mail) vê a mesma conversa pelo endereço do lead
        $dup = (new LeadEmailHistory())->forContact($this->duplicateContactId);
        $this->assertSame(['out', 'in', 'out'], array_column($dup['conversations'][0]['items'] ?? [], 'direction'));

        if (!\SchemaInfo::hasColumn('email_messages', 'imap_uid')) {
            // Sem a migration 140, registrar e-mail do lead falha em silêncio (não derruba a leitura da caixa).
            $this->assertFalse($this->service()->recordLeadEmail(['uid' => 9, 'from_email' => $this->leadEmail, 'subject' => 'x'], $this->contactId, $this->accountId));
        }
    }

    /**
     * Colunas da migration 140, como em produção (MariaDB). No MySQL local a
     * migration não roda (ADD COLUMN IF NOT EXISTS); o teste cria só no banco de
     * teste e remove no tearDown, deixando o schema como estava.
     */
    private array $addedColumns = [];
    private function ensureReplyTrackingColumns(): void
    {
        $defs = ['references_header' => 'TEXT NULL', 'reply_snippet' => 'TEXT NULL', 'imap_uid' => 'INT NULL', 'received_at' => 'DATETIME NULL'];
        foreach ($defs as $col => $def) {
            if (!\SchemaInfo::hasColumn('email_messages', $col)) {
                $this->db->query("ALTER TABLE email_messages ADD COLUMN `{$col}` {$def}");
                $this->addedColumns[] = $col;
            }
        }
        \SchemaInfo::reset();
    }

    private function dropAddedColumns(): void
    {
        foreach ($this->addedColumns as $col) {
            try { $this->db->query("ALTER TABLE email_messages DROP COLUMN `{$col}`"); } catch (\Throwable $e) {}
        }
        if ($this->addedColumns) \SchemaInfo::reset();
        $this->addedColumns = [];
    }

    public function testConversaCompletaNoCard(): void
    {
        $this->ensureReplyTrackingColumns();
        $out = $this->sendFromSequence();
        $this->assertSame(1, (int) $this->db->fetch("SELECT COUNT(*) c FROM email_prospections WHERE contact_id = ?", [$this->contactId])['c'], 'o envio gera o espelho');

        // 1) Lead responde ao envio (casado por token) — texto completo guardado
        $svc = $this->service();
        $this->assertTrue($svc->registerMatchedReply([
            'uid' => 501, 'from_email' => $this->leadEmail, 'subject' => 'Re: Proposta ON', 'date' => date('Y-m-d H:i:s', time() + 60),
            'message_id' => "<r1-{$this->u}@lead.test>", 'in_reply_to' => $out['message_id'],
            'snippet' => 'Tenho interesse. Pode me ligar?', 'reply_text' => "Tenho interesse.\nPode me ligar?",
        ], $out['track_token'], $this->accountId));

        // 2) Nossa resposta pela Caixa de entrada (fica só em email_prospections, sem contact_id)
        $this->db->insert('email_prospections', [
            'user_id' => $this->userId, 'email_account_id' => $this->accountId, 'contact_id' => null,
            'recipient_email' => $this->leadEmail, 'subject' => 'Re: Proposta ON', 'body' => '<p>Claro! Amanhã às 15h?</p>',
            'status' => 'sent', 'sent_at' => date('Y-m-d H:i:s', time() + 3600),
        ]);

        // 3) Lead responde à nossa resposta: sem token, só registro (caiu no cadastro duplicado)
        $msg = ['uid' => 502, 'from_email' => strtoupper($this->leadEmail), 'subject' => 'RE: Re: Proposta ON',
            'date' => date('Y-m-d H:i:s', time() + 7200), 'message_id' => "<r2-{$this->u}@lead.test>", 'reply_text' => "Combinado.\nAté amanhã."];
        $this->assertTrue($svc->recordLeadEmail($msg, $this->duplicateContactId, $this->accountId));
        $this->assertFalse($svc->recordLeadEmail($msg, $this->duplicateContactId, $this->accountId), 'mesmo UID não grava duas vezes');

        $recorded = $this->db->fetch("SELECT reply_snippet, replied_at, body, thread_key FROM email_messages WHERE imap_uid = 502 AND email_account_id = ?", [$this->accountId]);
        $this->assertNull($recorded['reply_snippet'], 'registro do histórico não alimenta a triagem por IA');
        $this->assertNull($recorded['replied_at']);
        $this->assertSame($this->leadEmail, $recorded['thread_key']);

        $h = (new LeadEmailHistory())->forContact($this->contactId);
        $this->assertCount(1, $h['conversations'], 'tudo na mesma conversa');
        $items = $h['conversations'][0]['items'];
        $this->assertSame(['out', 'in', 'out', 'in'], array_column($items, 'direction'));
        $this->assertSame(['sequence', 'lead', 'inbox_reply', 'lead'], array_column($items, 'source'));
        $this->assertStringContainsString('<strong>Olá Carla</strong>', $items[0]['body_html']);
        $this->assertStringNotContainsString('track/open', $items[0]['body_html'], 'sem pixel: abrir no CRM não conta abertura');
        $this->assertSame("Evelyn {$this->u}", $items[0]['sender']);
        $this->assertNotEmpty($items[0]['replied_at']);
        $this->assertSame("Tenho interesse.\nPode me ligar?", $items[1]['body_text']);
        $this->assertSame('Claro! Amanhã às 15h?', $items[2]['preview']);
        $this->assertSame("Combinado.\nAté amanhã.", $items[3]['body_text']);
        $this->assertSame(2, $h['summary']['sent'], 'espelho do envio não conta de novo');
        $this->assertSame(2, $h['summary']['replies']);

        // Sem corpos (contagem da aba): mesmo resumo, sem HTML
        $light = (new LeadEmailHistory())->forContact($this->contactId, false);
        $this->assertSame($h['summary'], $light['summary']);
        $this->assertNull($light['conversations'][0]['items'][0]['body_html']);
    }

    public function testLeadSemEmailsELeadInexistente(): void
    {
        $h = (new LeadEmailHistory())->forContact($this->contactId);
        $this->assertSame([], $h['conversations']);
        $this->assertSame(0, $h['summary']['total']);
        $this->assertSame([], (new LeadEmailHistory())->forContact(999999999)['conversations']);
    }
}
