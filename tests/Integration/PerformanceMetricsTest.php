<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use AgendaMeeting;
use WhatsappMessage;
use EmailProspection;
use EmailAccount;
use Database;

/**
 * Testes de integração das métricas que alimentam o Dashboard de Performance.
 * Cobre AgendaMeeting (getPerformanceStats/getClosingStats/getUniqueContactsByUser/
 * getMonthlyTrend), WhatsappMessage (getMessageStatsByUser/getContactResponseStats)
 * e EmailProspection (getStatsByUser/getMonthlyTrend).
 */
final class PerformanceMetricsTest extends TestCase
{
    private Database $db;
    private AgendaMeeting $agenda;
    private WhatsappMessage $wpp;
    private EmailProspection $email;
    private int $userA;
    private int $userB;
    private int $contact1;
    private int $contact2;
    private int $emailAccountId;
    /** @var int[] */
    private array $meetingIds = [];
    /** @var int[] */
    private array $prospectionIds = [];

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste (helpdesk_on_test) não configurado.');
        }
        $this->db = Database::getInstance();
        $this->agenda = new AgendaMeeting();
        $this->wpp = new WhatsappMessage();
        $this->email = new EmailProspection();

        $u = uniqid();
        $this->userA = $this->novoUsuario("Comercial A {$u}", "ca_{$u}@example.test", 'comercial');
        $this->userB = $this->novoUsuario("Comercial B {$u}", "cb_{$u}@example.test", 'comercial');

        $this->contact1 = $this->novoContato($u . '1', $this->userA);
        $this->contact2 = $this->novoContato($u . '2', $this->userA);

        $this->emailAccountId = (int) $this->db->insert('email_accounts', [
            'email' => "conta_{$u}@example.test",
            'smtp_host' => 'smtp.example.test',
            'smtp_port' => 587,
            'smtp_encryption' => 'tls',
            'smtp_username' => "conta_{$u}",
            'smtp_password' => 'x',
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->prospectionIds as $id) {
            try { $this->db->delete('email_prospections', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
        foreach ($this->meetingIds as $id) {
            try { $this->db->delete('agenda_meetings', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
        // mensagens saem via CASCADE do contato; limpa contatos e conta e usuários
        try { $this->db->delete('email_accounts', 'id = ?', [$this->emailAccountId]); } catch (\Throwable $e) {}
        foreach ([$this->contact1, $this->contact2] as $id) {
            try { $this->db->delete('whatsapp_messages', 'contact_id = ?', [$id]); } catch (\Throwable $e) {}
            try { $this->db->delete('whatsapp_contacts', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
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

    private function novoContato(string $suffix, int $assignedTo): int
    {
        $phone = '5511' . random_int(100000000, 999999999);
        return (int) $this->db->insert('whatsapp_contacts', [
            'remote_jid' => $phone . '@s.whatsapp.net',
            'contact_name' => "Lead {$suffix}",
            'phone' => $phone,
            'assigned_to' => $assignedTo,
        ]);
    }

    private function novaReuniao(array $overrides = []): int
    {
        $id = (int) $this->agenda->create(array_merge([
            'title' => 'Reunião perf',
            'meeting_type' => 'comercial',
            'created_by' => $this->userA,
            'assigned_to' => $this->userA,
            'urgency' => 'media',
            'status' => 'agendada',
            'position' => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ], $overrides));
        $this->meetingIds[] = $id;
        return $id;
    }

    private function novaMensagem(int $contactId, string $jid, bool $fromMe, string $ts): void
    {
        $this->db->insert('whatsapp_messages', [
            'contact_id' => $contactId,
            'remote_jid' => $jid,
            'from_me' => $fromMe ? 1 : 0,
            'message_type' => 'text',
            'message_text' => $fromMe ? 'Olá' : 'Oi, tudo bem',
            'timestamp' => $ts,
        ]);
    }

    // ===== AgendaMeeting: performance =====

    public function testGetPerformanceStatsAgrupaPorStatus(): void
    {
        $hoje = date('Y-m-d');
        $this->novaReuniao(['status' => 'agendada']);
        $this->novaReuniao(['status' => 'realizada']);
        $this->novaReuniao(['status' => 'convertida', 'closed_by' => $this->userA]);

        $stats = $this->agenda->getPerformanceStats($hoje, $hoje, $this->userA);
        $this->assertArrayHasKey($this->userA, $stats);
        $s = $stats[$this->userA];
        $this->assertSame(1, $s['agendada']);
        $this->assertSame(1, $s['realizada']);
        $this->assertSame(1, $s['convertida']);
        $this->assertSame(3, $s['total']);
    }

    public function testGetClosingStatsSelfEForOthers(): void
    {
        $hoje = date('Y-m-d');
        // A fecha a própria (self)
        $this->novaReuniao(['status' => 'convertida', 'assigned_to' => $this->userA, 'closed_by' => $this->userA]);
        // B é responsável, mas A fechou (para A: closed_for_others; para B: closed_by_others)
        $this->novaReuniao(['status' => 'convertida', 'assigned_to' => $this->userB, 'closed_by' => $this->userA]);

        $stats = $this->agenda->getClosingStats($hoje, $hoje, null);
        $this->assertSame(1, $stats[$this->userA]['closed_self']);
        $this->assertSame(1, $stats[$this->userA]['closed_for_others']);
        $this->assertSame(1, $stats[$this->userB]['closed_by_others']);
    }

    public function testGetUniqueContactsByUser(): void
    {
        $hoje = date('Y-m-d');
        $this->novaReuniao(['contact_id' => $this->contact1]);
        $this->novaReuniao(['contact_id' => $this->contact1]); // mesmo contato
        $this->novaReuniao(['contact_id' => $this->contact2]);

        $uniq = $this->agenda->getUniqueContactsByUser($hoje, $hoje, $this->userA);
        $this->assertSame(2, $uniq[$this->userA]); // 2 contatos distintos
    }

    public function testGetMonthlyTrendAncoraNoFimDoPeriodo(): void
    {
        // Cria reunião no mês atual e confere que a série termina no mês âncora.
        $this->novaReuniao(['status' => 'agendada']);
        $trend = $this->agenda->getMonthlyTrend(3, $this->userA, date('Y-m-d'));
        $this->assertCount(3, $trend);
        $ultimo = end($trend);
        $this->assertSame(date('Y-m'), $ultimo['month']);
    }

    // ===== WhatsappMessage =====

    public function testMessageStatsByUserContaEnviadasERecebidas(): void
    {
        $hoje = date('Y-m-d');
        $jid = $this->db->fetch("SELECT remote_jid FROM whatsapp_contacts WHERE id = ?", [$this->contact1])['remote_jid'];
        $this->novaMensagem($this->contact1, $jid, true, date('Y-m-d H:i:s'));
        $this->novaMensagem($this->contact1, $jid, true, date('Y-m-d H:i:s'));
        $this->novaMensagem($this->contact1, $jid, false, date('Y-m-d H:i:s'));

        $stats = $this->wpp->getMessageStatsByUser($hoje, $hoje, $this->userA);
        $this->assertArrayHasKey($this->userA, $stats);
        $this->assertSame(2, $stats[$this->userA]['sent']);
        $this->assertSame(1, $stats[$this->userA]['received']);
        $this->assertSame(1, $stats[$this->userA]['contacts_messaged']);
    }

    public function testContactResponseStats(): void
    {
        $hoje = date('Y-m-d');
        $jid1 = $this->db->fetch("SELECT remote_jid FROM whatsapp_contacts WHERE id = ?", [$this->contact1])['remote_jid'];
        $jid2 = $this->db->fetch("SELECT remote_jid FROM whatsapp_contacts WHERE id = ?", [$this->contact2])['remote_jid'];
        // contact1: enviado + respondeu
        $this->novaMensagem($this->contact1, $jid1, true, date('Y-m-d H:i:s'));
        $this->novaMensagem($this->contact1, $jid1, false, date('Y-m-d H:i:s'));
        // contact2: só enviado, sem resposta
        $this->novaMensagem($this->contact2, $jid2, true, date('Y-m-d H:i:s'));

        $stats = $this->wpp->getContactResponseStats($hoje, $hoje, $this->userA);
        $this->assertSame(2, $stats[$this->userA]['contacted']);
        $this->assertSame(1, $stats[$this->userA]['replied']);
        $this->assertSame(1, $stats[$this->userA]['no_reply']);
    }

    // ===== EmailProspection =====

    private function novaProspeccao(array $overrides = []): int
    {
        $id = (int) $this->email->create(array_merge([
            'user_id' => $this->userA,
            'email_account_id' => $this->emailAccountId,
            'contact_id' => $this->contact1,
            'recipient_email' => 'lead@example.test',
            'subject' => 'Proposta',
            'body' => '<p>Olá</p>',
            'status' => 'sent',
            'sent_at' => date('Y-m-d H:i:s'),
        ], $overrides));
        $this->prospectionIds[] = $id;
        return $id;
    }

    public function testEmailStatsByUser(): void
    {
        $hoje = date('Y-m-d');
        $this->novaProspeccao(['status' => 'sent', 'contact_id' => $this->contact1]);
        $this->novaProspeccao(['status' => 'sent', 'contact_id' => $this->contact2]);
        $this->novaProspeccao(['status' => 'failed', 'contact_id' => $this->contact1, 'sent_at' => date('Y-m-d H:i:s')]);

        $stats = $this->email->getStatsByUser($hoje, $hoje, $this->userA);
        $this->assertArrayHasKey($this->userA, $stats);
        $this->assertSame(2, $stats[$this->userA]['sent']);
        $this->assertSame(1, $stats[$this->userA]['failed']);
        $this->assertSame(3, $stats[$this->userA]['total']);
        $this->assertSame(2, $stats[$this->userA]['unique_contacts']);
    }

    public function testEmailCreateNormalizaStatusInvalido(): void
    {
        // Bug D: status fora do enum vira 'sent' (não grava '' nem quebra).
        $id = $this->novaProspeccao(['status' => 'received']);
        $row = $this->db->fetch("SELECT status FROM email_prospections WHERE id = ?", [$id]);
        $this->assertSame('sent', $row['status']);
    }

    public function testEmailMonthlyTrend(): void
    {
        $this->novaProspeccao(['status' => 'sent']);
        $trend = $this->email->getMonthlyTrend(3, $this->userA);
        $this->assertCount(3, $trend);
        $ultimo = end($trend);
        $this->assertGreaterThanOrEqual(1, $ultimo['sent']);
    }
}
