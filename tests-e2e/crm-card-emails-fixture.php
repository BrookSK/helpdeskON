<?php
/**
 * Fixture do E2E "CRM: aba E-mails do card" (helper, não é teste).
 *
 *   php tests-e2e/crm-card-emails-fixture.php setup
 *       Cria: super_admin, board/coluna, lead com card e uma conversa de e-mail
 *       (envio da sequência → resposta do lead → nossa resposta pela Caixa de
 *       entrada → nova resposta do lead), mais um envio com assunto "perigoso"
 *       (HTML) e um segundo card sem e-mails. Imprime JSON.
 *   php tests-e2e/crm-card-emails-fixture.php teardown '<json>'
 */

$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
date_default_timezone_set('America/Sao_Paulo');

spl_autoload_register(function ($class) {
    foreach (['core', 'controllers', 'models'] as $d) {
        $f = APP_PATH . "/{$d}/{$class}.php";
        if (is_file($f)) { require_once $f; return; }
    }
});

$cmd = $argv[1] ?? '';

try {
    $db = Database::getInstance();

    if ($cmd === 'setup') {
        $u = bin2hex(random_bytes(4));
        $password = 'E2e!' . bin2hex(random_bytes(6));
        $email = "e2e_cardmail_{$u}@example.test";
        $adminId = (int) $db->insert('users', [
            'name' => "Evelyn E2E {$u}", 'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT), 'role' => 'super_admin', 'is_active' => 1,
        ]);
        $board = new CrmBoard();
        $boardId = (int) $board->create(['name' => "E2E Board Mail {$u}", 'created_by' => $adminId]);
        $columnId = (int) $board->createColumn(['board_id' => $boardId, 'name' => 'Novo']);

        $leadEmail = "carla@{$u}.example.test";
        $contactId = (int) (new LeadResolver())->resolve([
            'name' => "Carla Mail {$u}", 'email' => $leadEmail, 'phone' => '5599' . substr((string) hexdec($u), 0, 8),
            'company' => 'Clínica Sorriso', 'source' => 'apollo', 'assigned_to' => $adminId,
        ], $adminId);
        if (!$contactId) throw new RuntimeException('sem instância de WhatsApp no banco local');
        $cardId = (int) $board->createCard([
            'column_id' => $columnId, 'title' => "Carla Mail {$u}", 'contact_id' => $contactId,
            'created_by' => $adminId, 'assigned_to' => $adminId,
        ]);
        $emptyContactId = (int) (new LeadResolver())->resolve([
            // Sem telefone: o LeadResolver casa lead pelo final do número e juntaria com a Carla.
            'name' => "Bruno Sem Email {$u}", 'email' => "bruno@{$u}.example.test",
            'source' => 'manual', 'assigned_to' => $adminId,
        ], $adminId);
        if (!$emptyContactId || $emptyContactId === $contactId) throw new RuntimeException('lead sem e-mails não foi criado separado');
        $emptyCardId = (int) $board->createCard([
            'column_id' => $columnId, 'title' => "Bruno Sem Email {$u}", 'contact_id' => $emptyContactId,
            'created_by' => $adminId, 'assigned_to' => $adminId,
        ]);

        $accountId = (int) (new EmailAccount())->create([
            'email' => "conta_{$u}@example.test", 'display_name' => "Conta E2E {$u}",
            'smtp_host' => 'smtp.example.test', 'smtp_port' => 587, 'smtp_encryption' => 'tls',
            'smtp_username' => "conta_{$u}", 'smtp_password' => 'x', 'created_by' => $adminId,
        ]);
        $seqId = (int) $db->insert('email_sequences', [
            'name' => "E2E Seq Mail {$u}", 'graph' => json_encode(['start' => null, 'nodes' => []]),
            'is_active' => 0, 'created_by' => $adminId,
        ]);
        $partId = (int) $db->insert('sequence_participants', [
            'sequence_id' => $seqId, 'contact_id' => $contactId, 'status' => 'finished', 'added_by' => $adminId,
        ]);

        $t = strtotime('-3 days 09:00');
        $at = fn($h) => date('Y-m-d H:i:s', $t + $h * 3600);
        $mid = "<seq-e2e{$u}@example.test>";
        $db->insert('email_messages', [
            'contact_id' => $contactId, 'email_account_id' => $accountId, 'direction' => 'outbound', 'origin' => 'sequence',
            'sequence_participant_id' => $partId, 'message_id' => $mid, 'thread_key' => $leadEmail, 'recipient_email' => $leadEmail,
            'subject' => 'Proposta ON', 'body' => '<p><strong>Olá Carla</strong>, segue a <em>proposta</em>.</p>',
            'track_token' => bin2hex(random_bytes(16)), 'status' => 'sent', 'open_count' => 2,
            'first_open_at' => $at(1), 'replied_at' => $at(2), 'sent_by' => $adminId, 'sent_at' => $at(0), 'created_at' => $at(0),
        ]);
        $db->insert('email_messages', [
            'contact_id' => $contactId, 'email_account_id' => $accountId, 'direction' => 'inbound', 'origin' => 'sequence',
            'in_reply_to' => $mid, 'thread_key' => $leadEmail, 'recipient_email' => $leadEmail, 'subject' => 'Re: Proposta ON',
            'body' => "Tenho interesse.\nPode me ligar amanhã? <script>alert(1)</script>", 'status' => 'received', 'created_at' => $at(2),
        ]);
        $db->insert('email_prospections', [
            'user_id' => $adminId, 'email_account_id' => $accountId, 'contact_id' => null, 'recipient_email' => $leadEmail,
            'subject' => 'Re: Proposta ON', 'body' => '<p>Claro! Amanhã às 15h?</p>', 'status' => 'sent', 'sent_at' => $at(5),
        ]);
        $db->insert('email_messages', [
            'contact_id' => $contactId, 'email_account_id' => $accountId, 'direction' => 'inbound', 'origin' => 'manual',
            'thread_key' => $leadEmail, 'recipient_email' => $leadEmail, 'subject' => 'RE: Re: Proposta ON',
            'body' => "Combinado.\nAté amanhã.", 'status' => 'received', 'created_at' => $at(6),
        ]);
        // Envio que falhou, com assunto contendo HTML (tem que aparecer como texto)
        $db->insert('email_messages', [
            'contact_id' => $contactId, 'email_account_id' => $accountId, 'direction' => 'outbound', 'origin' => 'manual',
            'thread_key' => $leadEmail, 'recipient_email' => $leadEmail, 'subject' => '<img src=x onerror=alert(1)> Novidades',
            'body' => '<p>Novidades</p>', 'track_token' => bin2hex(random_bytes(16)), 'status' => 'failed',
            'error_message' => 'SMTP recusou', 'sent_by' => $adminId, 'created_at' => $at(1),
        ]);

        echo json_encode([
            'u' => $u, 'boardId' => $boardId, 'cardId' => $cardId, 'emptyCardId' => $emptyCardId,
            'contactId' => $contactId, 'emptyContactId' => $emptyContactId, 'accountId' => $accountId, 'seqId' => $seqId,
            'leadEmail' => $leadEmail, 'admin' => ['id' => $adminId, 'email' => $email, 'password' => $password],
        ]);
        exit(0);
    }

    if ($cmd === 'teardown') {
        $data = json_decode($argv[2] ?? '', true);
        if (!is_array($data)) throw new RuntimeException('json inválido');
        $u = preg_replace('/[^a-f0-9]/', '', (string) $data['u']);
        if ($u === '') throw new RuntimeException('sem u');
        if (!empty($data['leadEmail'])) {
            try { $db->delete('email_prospections', 'recipient_email = ?', [$data['leadEmail']]); } catch (Throwable $e) {}
        }
        foreach ([(int) ($data['contactId'] ?? 0), (int) ($data['emptyContactId'] ?? 0)] as $id) {
            if (!$id) continue;
            foreach (['email_events', 'email_messages', 'email_prospections', 'sequence_participants', 'lead_timeline', 'lead_score', 'commercial_briefings'] as $t) {
                try { $db->delete($t, 'contact_id = ?', [$id]); } catch (Throwable $e) {}
            }
            foreach ($db->fetchAll("SELECT id FROM crm_cards WHERE contact_id = ?", [$id]) as $c) {
                try { $db->delete('crm_card_activities', 'card_id = ?', [(int) $c['id']]); } catch (Throwable $e) {}
                $db->delete('crm_cards', 'id = ?', [(int) $c['id']]);
            }
            $db->delete('whatsapp_contacts', 'id = ?', [$id]);
        }
        if (!empty($data['seqId'])) $db->delete('email_sequences', 'id = ?', [(int) $data['seqId']]);
        if (!empty($data['accountId'])) {
            try { $db->delete('email_account_users', 'email_account_id = ?', [(int) $data['accountId']]); } catch (Throwable $e) {}
            $db->delete('email_accounts', 'id = ?', [(int) $data['accountId']]);
        }
        if (!empty($data['boardId'])) {
            $db->delete('crm_columns', 'board_id = ?', [(int) $data['boardId']]);
            $db->delete('crm_boards', 'id = ?', [(int) $data['boardId']]);
        }
        $adminId = (int) ($data['admin']['id'] ?? 0);
        if ($adminId) {
            foreach (['notifications', 'user_activity_log', 'user_login_history', 'activity_logs', 'lead_timeline'] as $t) {
                try { $db->delete($t, 'user_id = ?', [$adminId]); } catch (Throwable $e) {}
            }
            try { $db->delete('crm_card_activities', 'user_id = ?', [$adminId]); } catch (Throwable $e) {}
            $db->delete('users', 'id = ?', [$adminId]);
        }
        echo 'ok';
        exit(0);
    }

    throw new RuntimeException('comando desconhecido: ' . $cmd);
} catch (Throwable $e) {
    fwrite(STDERR, 'ERRO: ' . $e->getMessage());
    exit(1);
}
