<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Database;
use EmailAccount;
use EmailHtmlRules;
use EmailMessageService;
use EmailProspection;

/** Camada SMTP falsa: monta o HTML final como o envio real faz e guarda. */
final class FakeSmtpForFormattedTest extends EmailProspection
{
    public array $sent = [];
    public function sendEmail($account, $to, $subject, $htmlBody, $cc = null, $bcc = null, $attachments = [], array $options = [])
    {
        $this->sent[] = ['to' => $to, 'subject' => $subject, 'html' => self::buildHtml($htmlBody, $account)];
        return true;
    }
}

/**
 * Integração (helpdesk_on_test): o e-mail de prospecção sai FORMATADO e com o
 * layout do sistema, tanto do editor (HTML) quanto de template em texto puro.
 * O SMTP é falso; o registro em email_messages é o real.
 */
final class EmailFormattedSendTest extends TestCase
{
    private Database $db;
    private int $userId;
    private int $accountId;
    private int $contactId;

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste não configurado.');
        }
        $this->db = Database::getInstance();
        $u = uniqid();
        $this->userId = (int) $this->db->insert('users', [
            'name' => "Fmt {$u}", 'email' => "fmt_{$u}@example.test",
            'password' => password_hash('x', PASSWORD_BCRYPT), 'role' => 'comercial',
        ]);
        $this->contactId = (int) $this->db->insert('whatsapp_contacts', [
            'remote_jid' => "fmt{$u}@s.whatsapp.net", 'contact_name' => "Lead Fmt {$u}", 'lead_email' => "lead_{$u}@example.test",
        ]);
        $this->accountId = (int) (new EmailAccount())->create([
            'email' => "conta_{$u}@example.test", 'display_name' => 'Conta Fmt',
            'smtp_host' => 'smtp.example.test', 'smtp_port' => 587, 'smtp_encryption' => 'tls',
            'smtp_username' => "conta_{$u}", 'smtp_password' => 'x', 'created_by' => $this->userId,
        ]);
    }

    protected function tearDown(): void
    {
        foreach (['email_events', 'email_messages', 'email_prospections', 'lead_timeline'] as $t) {
            try { $this->db->delete($t, 'contact_id = ?', [$this->contactId]); } catch (\Throwable $e) {}
        }
        try { $this->db->delete('email_accounts', 'id = ?', [$this->accountId]); } catch (\Throwable $e) {}
        try { $this->db->delete('whatsapp_contacts', 'id = ?', [$this->contactId]); } catch (\Throwable $e) {}
        try { $this->db->delete('users', 'id = ?', [$this->userId]); } catch (\Throwable $e) {}
    }

    private function send(string $body, array $extra = []): array
    {
        $svc = new EmailMessageService();
        $fake = new FakeSmtpForFormattedTest();
        $p = new \ReflectionProperty(EmailMessageService::class, 'prospection');
        $p->setValue($svc, $fake);
        $account = (new EmailAccount())->findById($this->accountId);
        $r = $svc->send(array_merge([
            'contact_id' => $this->contactId, 'account' => $account, 'to' => 'lead@example.test',
            'subject' => 'Proposta', 'body_html' => $body, 'origin' => 'sequence', 'sent_by' => $this->userId,
        ], $extra));
        $this->assertTrue($r['success'], $r['error'] ?? '');
        $this->assertCount(1, $fake->sent);
        return [$fake->sent[0]['html'], $this->db->fetch("SELECT body, status FROM email_messages WHERE id = ?", [$r['message_id']])];
    }

    public function testCorpoDoEditorChegaFormatadoComLayoutEAssinatura(): void
    {
        [$html, $row] = $this->send('<p class="ql-align-center"><strong>Olá</strong>, <em>tudo bem?</em></p><ul><li>Item</li></ul><p><a href="https://on.com.br">site</a></p>', ['add_signature' => true]);

        $this->assertStringContainsString('<strong>Olá</strong>', $html);
        $this->assertStringContainsString('<em>tudo bem?</em>', $html);
        $this->assertStringContainsString('text-align:center', $html);
        $this->assertStringNotContainsString('ql-align', $html);
        $this->assertStringContainsString(EmailHtmlRules::LAYOUT_MARKER, $html, 'layout do sistema');
        $this->assertStringStartsWith('<!DOCTYPE html>', $html);
        $this->assertSame(1, substr_count($html, 'data-onsolu-signature'), 'assinatura uma vez só');
        $this->assertStringContainsString('track/open', $html, 'pixel de abertura dentro do layout');
        $this->assertStringContainsString('track/click', $html, 'link rastreado');
        $this->assertSame('sent', $row['status']);
        $this->assertStringContainsString('text-align:center', $row['body'], 'o histórico guarda o corpo formatado');
    }

    public function testTemplateEmTextoPuroMantemAsQuebrasDeLinha(): void
    {
        [$html, $row] = $this->send("Olá Ana,\nTudo bem?\n\nAbraço");
        $this->assertStringContainsString('Olá Ana,<br>', $html);
        $this->assertStringContainsString('Abraço</p>', $html);
        $this->assertStringContainsString('<p style="margin:0 0 12px 0">Olá Ana,<br>', $row['body']);
    }
}
