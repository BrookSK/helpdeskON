<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Database;
use EmailAccount;
use EmailMessageService;
use EmailProspection;
use LeadEmailHistory;
use SchemaInfo;
use SequenceEngine;

/** SMTP falso: o resto do envio (email_messages, token) é o real. */
final class FakeSmtpForBounceTest extends EmailProspection
{
    public function sendEmail($account, $to, $subject, $htmlBody, $cc = null, $bcc = null, $attachments = [], array $options = [])
    {
        return true;
    }
}

/**
 * Integração (helpdesk_on_test): e-mail devolvido porque o endereço não existe.
 * O lead sai de todas as sequências, o e-mail dele fica bloqueado (inclusive no
 * cadastro duplicado), não dá para inscrever de novo numa sequência de e-mail e
 * o card mostra o envio como devolvido. Devolução temporária não tira o lead.
 */
final class EmailBounceProcessTest extends TestCase
{
    private Database $db;
    private string $u;
    private string $leadEmail;
    private int $userId;
    private int $accountId;
    private int $contactId;
    private int $dupContactId;
    private int $otherContactId;
    private array $seqIds = [];

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste não configurado.');
        }
        $this->db = Database::getInstance();
        SchemaInfo::reset();
        $this->u = uniqid();
        $this->leadEmail = "cestari_{$this->u}@clubmed.test";
        $this->userId = (int) $this->db->insert('users', [
            'name' => "Evelyn B {$this->u}", 'email' => "bounce_{$this->u}@example.test",
            'password' => password_hash('x', PASSWORD_BCRYPT), 'role' => 'comercial',
        ]);
        $mk = fn($name, $email) => (int) $this->db->insert('whatsapp_contacts', [
            'remote_jid' => 'b' . bin2hex(random_bytes(5)) . '@s.whatsapp.net', 'contact_name' => $name, 'lead_email' => $email,
        ]);
        $this->contactId = $mk("Cestari {$this->u}", $this->leadEmail);
        $this->dupContactId = $mk("Cestari dup {$this->u}", strtoupper($this->leadEmail));
        $this->otherContactId = $mk("Outro {$this->u}", "outro_{$this->u}@ok.test");
        $this->accountId = (int) (new EmailAccount())->create([
            'email' => "evelyn_{$this->u}@lrvweb.test", 'display_name' => 'Evelyn',
            'smtp_host' => 'smtp.example.test', 'smtp_port' => 587, 'smtp_encryption' => 'tls',
            'smtp_username' => "ev_{$this->u}", 'smtp_password' => 'x', 'created_by' => $this->userId,
        ]);
        foreach (['A', 'B'] as $s) {
            $row = ['name' => "Seq Bounce {$s} {$this->u}", 'graph' => json_encode(['start' => null, 'nodes' => []]), 'is_active' => 1, 'created_by' => $this->userId];
            if (SchemaInfo::hasColumn('email_sequences', 'channel_type')) $row['channel_type'] = 'email';
            $this->seqIds[] = (int) $this->db->insert('email_sequences', $row);
        }
        // Cestari em 2 sequências, o cadastro duplicado em 1, outro lead em 1.
        $this->part($this->seqIds[0], $this->contactId);
        $this->part($this->seqIds[1], $this->contactId);
        $this->part($this->seqIds[1], $this->dupContactId);
        $this->part($this->seqIds[0], $this->otherContactId);
    }

    private function part(int $seq, int $contact): void
    {
        $this->db->insert('sequence_participants', [
            'sequence_id' => $seq, 'contact_id' => $contact, 'status' => 'active', 'added_by' => $this->userId,
            'next_run_at' => date('Y-m-d H:i:s', time() + 3600),
        ]);
    }

    protected function tearDown(): void
    {
        foreach ([$this->contactId, $this->dupContactId, $this->otherContactId] as $cid) {
            foreach (['email_events', 'email_messages', 'email_prospections', 'lead_timeline', 'lead_score', 'sequence_participants'] as $t) {
                try { $this->db->delete($t, 'contact_id = ?', [$cid]); } catch (\Throwable $e) {}
            }
        }
        foreach ($this->seqIds as $id) {
            try { $this->db->delete('sequence_participants', 'sequence_id = ?', [$id]); } catch (\Throwable $e) {}
            try { $this->db->delete('email_sequences', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
        try { $this->db->delete('email_accounts', 'id = ?', [$this->accountId]); } catch (\Throwable $e) {}
        foreach ([$this->contactId, $this->dupContactId, $this->otherContactId] as $cid) {
            try { $this->db->delete('whatsapp_contacts', 'id = ?', [$cid]); } catch (\Throwable $e) {}
        }
        try { $this->db->delete('users', 'id = ?', [$this->userId]); } catch (\Throwable $e) {}
    }

    private function service(): EmailMessageService
    {
        $svc = new EmailMessageService();
        $p = new \ReflectionProperty(EmailMessageService::class, 'prospection');
        $p->setValue($svc, new FakeSmtpForBounceTest());
        return $svc;
    }

    private function send(int $contactId, string $to): array
    {
        $r = $this->service()->send([
            'contact_id' => $contactId, 'account' => (new EmailAccount())->findById($this->accountId),
            'to' => $to, 'subject' => 'Proposta ON', 'body_html' => '<p>Olá</p>', 'origin' => 'sequence', 'sent_by' => $this->userId,
        ]);
        return $r;
    }

    private function gmailDsn(string $to, ?string $messageId, string $status = '5.1.1'): array
    {
        $m = ['from' => 'Mail Delivery Subsystem <mailer-daemon@googlemail.com>', 'from_email' => 'mailer-daemon@googlemail.com',
              'subject' => 'Delivery Status Notification (Failure)', 'x_failed_recipients' => $to, 'uid' => 900];
        $text = "Address not found\nYour message wasn't delivered to {$to} because the address couldn't be found.\n"
              . "Final-Recipient: rfc822; {$to}\nAction: failed\nStatus: {$status}\n\n"
              . "From: Evelyn <evelyn_{$this->u}@lrvweb.test>\nTo: {$to}\n" . ($messageId ? "Message-ID: {$messageId}\n" : '');
        return [$m, $text];
    }

    private function participantStatus(int $contactId): array
    {
        return array_map(fn($r) => $r['status'] . ':' . ($r['stop_reason'] ?? ''), $this->db->fetchAll(
            "SELECT status, stop_reason FROM sequence_participants WHERE contact_id = ? ORDER BY id", [$contactId]
        ));
    }

    public function testEnderecoInexistenteTiraDasSequenciasEBloqueia(): void
    {
        $sent = $this->send($this->contactId, $this->leadEmail);
        $this->assertTrue($sent['success']);
        $mid = $this->db->fetch("SELECT message_id FROM email_messages WHERE id = ?", [$sent['message_id']])['message_id'];

        [$m, $text] = $this->gmailDsn($this->leadEmail, $mid);
        $r = $this->service()->processBounce($m, $text, $this->accountId);

        $this->assertTrue($r['handled']);
        $this->assertTrue($r['hard']);
        $this->assertSame($this->contactId, $r['contact_id']);
        $this->assertSame("Cestari {$this->u}", $r['contact_name']);
        $this->assertSame(3, $r['stopped'], 'saiu das 2 sequências + a do cadastro duplicado');

        $this->assertSame(['stopped:bounce', 'stopped:bounce'], $this->participantStatus($this->contactId));
        $this->assertSame(['stopped:bounce'], $this->participantStatus($this->dupContactId), 'cadastro duplicado com o mesmo e-mail também sai');
        $this->assertSame(['active:'], $this->participantStatus($this->otherContactId), 'outros leads não são afetados');
        foreach ([$this->contactId, $this->dupContactId] as $cid) {
            $this->assertSame(1, (int) $this->db->fetch("SELECT email_bounced b FROM whatsapp_contacts WHERE id = ?", [$cid])['b']);
        }
        $this->assertSame(0, (int) $this->db->fetch("SELECT email_bounced b FROM whatsapp_contacts WHERE id = ?", [$this->otherContactId])['b']);

        $row = $this->db->fetch("SELECT error_message FROM email_messages WHERE id = ?", [$sent['message_id']]);
        $this->assertSame('Devolvido: Endereço de e-mail não existe (5.1.1)', $row['error_message']);
        $this->assertSame(1, (int) $this->db->fetch("SELECT COUNT(*) c FROM email_events WHERE message_id = ? AND event_type='bounce'", [$sent['message_id']])['c']);
        $tl = $this->db->fetch("SELECT description FROM lead_timeline WHERE contact_id = ? AND event_type = 'bounce' ORDER BY id DESC LIMIT 1", [$this->contactId]);
        $this->assertStringContainsString('Lead tirado das sequências', $tl['description'] ?? '');

        // A mesma devolução de novo (cron + botão): não repete nada
        $again = $this->service()->processBounce($m, $text, $this->accountId);
        $this->assertTrue($again['already']);
        $this->assertFalse($again['handled']);
        $this->assertSame(1, (int) $this->db->fetch("SELECT COUNT(*) c FROM email_events WHERE message_id = ? AND event_type='bounce'", [$sent['message_id']])['c']);

        // Não envia mais e não deixa inscrever de novo numa sequência de e-mail
        $blocked = $this->send($this->contactId, $this->leadEmail);
        $this->assertFalse($blocked['success']);
        $this->assertStringContainsString('bounce', $blocked['error']);
        $this->db->delete('sequence_participants', 'contact_id = ? AND sequence_id = ?', [$this->contactId, $this->seqIds[0]]);
        $enroll = (new SequenceEngine())->enroll($this->seqIds[0], $this->contactId, $this->userId);
        $this->assertFalse($enroll['success']);
        $this->assertStringContainsString('voltou como inexistente', $enroll['error']);

        // Card do CRM: o envio aparece como devolvido
        $h = (new LeadEmailHistory())->forContact($this->contactId, false);
        $item = $h['conversations'][0]['items'][0];
        $this->assertNotEmpty($item['bounced_at']);
        $this->assertSame('Devolvido: Endereço de e-mail não existe (5.1.1)', $item['error']);
        $this->assertSame(1, $h['summary']['bounced']);
    }

    public function testSemMessageIdUsaOEnderecoQueFalhou(): void
    {
        $sent = $this->send($this->contactId, $this->leadEmail);
        [$m, $text] = $this->gmailDsn(strtoupper($this->leadEmail), null);
        $r = $this->service()->processBounce($m, $text, $this->accountId);
        $this->assertTrue($r['handled']);
        $this->assertSame($this->contactId, $r['contact_id']);
        $this->assertSame(1, (int) $this->db->fetch("SELECT COUNT(*) c FROM email_events WHERE message_id = ? AND event_type='bounce'", [$sent['message_id']])['c']);
    }

    public function testTemporarioSoRegistraELeadContinua(): void
    {
        $sent = $this->send($this->contactId, $this->leadEmail);
        $mid = $this->db->fetch("SELECT message_id FROM email_messages WHERE id = ?", [$sent['message_id']])['message_id'];
        [$m, $text] = $this->gmailDsn($this->leadEmail, $mid, '5.2.2');
        $text = str_replace('Address not found', 'Mailbox full', $text);
        $r = $this->service()->processBounce($m, $text, $this->accountId);

        $this->assertTrue($r['handled']);
        $this->assertFalse($r['hard']);
        $this->assertSame(['active:', 'active:'], $this->participantStatus($this->contactId));
        $this->assertSame(0, (int) $this->db->fetch("SELECT email_bounced b FROM whatsapp_contacts WHERE id = ?", [$this->contactId])['b']);
        $this->assertStringStartsWith('Devolvido: Caixa de entrada do lead cheia', $this->db->fetch("SELECT error_message FROM email_messages WHERE id = ?", [$sent['message_id']])['error_message']);
    }

    public function testDevolucaoDeEmailQueNaoEnviamosEIgnorada(): void
    {
        [$m, $text] = $this->gmailDsn("ninguem_{$this->u}@nada.test", '<seq-ffffffffffffffffffffffffffffffff@x.test>');
        $r = $this->service()->processBounce($m, $text, $this->accountId);
        $this->assertFalse($r['handled']);
        $this->assertNull($r['email']);
        $this->assertSame(['active:', 'active:'], $this->participantStatus($this->contactId));
    }
}
