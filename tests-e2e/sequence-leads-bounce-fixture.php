<?php
/**
 * Fixture do E2E "Leads da sequência: pesquisa + e-mail devolvido" (helper, não é teste).
 *
 *   php tests-e2e/sequence-leads-bounce-fixture.php setup
 *       Cria: super_admin, uma sequência com 3 leads (2 ativos e 1 tirado por
 *       e-mail devolvido) e um board com o card do lead devolvido, com o envio
 *       marcado como devolvido. Imprime JSON.
 *   php tests-e2e/sequence-leads-bounce-fixture.php teardown '<json>'
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
        $email = "e2e_seqbounce_{$u}@example.test";
        $adminId = (int) $db->insert('users', [
            'name' => "E2E Bounce {$u}", 'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT), 'role' => 'super_admin', 'is_active' => 1,
        ]);
        $contact = function ($name, $mail, $bounced = 0) use ($db) {
            return (int) $db->insert('whatsapp_contacts', [
                'remote_jid' => 'lead_e2e' . bin2hex(random_bytes(5)) . '@s.whatsapp.net',
                'contact_name' => $name, 'lead_email' => $mail, 'email_bounced' => $bounced,
            ]);
        };
        $c1 = $contact("Gustavo Machado {$u}", "gustavo@{$u}.example.test");
        $c2 = $contact("Flávia Damaso {$u}", "flavia@{$u}.example.test");
        $c3 = $contact("Cestari Club {$u}", "cestari@{$u}.example.test", 1);

        $seqId = (int) $db->insert('email_sequences', [
            'name' => "E2E SeqBounce {$u}", 'graph' => json_encode(['start' => null, 'nodes' => []]),
            'is_active' => 0, 'created_by' => $adminId,
        ]);
        foreach ([[$c1, 'active', null], [$c2, 'active', null], [$c3, 'stopped', 'bounce']] as [$cid, $st, $why]) {
            $db->insert('sequence_participants', [
                'sequence_id' => $seqId, 'contact_id' => $cid, 'status' => $st, 'stop_reason' => $why, 'added_by' => $adminId,
            ]);
        }

        $board = new CrmBoard();
        $boardId = (int) $board->create(['name' => "E2E Board Bounce {$u}", 'created_by' => $adminId]);
        $columnId = (int) $board->createColumn(['board_id' => $boardId, 'name' => 'Novo']);
        $cardId = (int) $board->createCard([
            'column_id' => $columnId, 'title' => "Cestari Club {$u}", 'contact_id' => $c3,
            'created_by' => $adminId, 'assigned_to' => $adminId,
        ]);
        $at = date('Y-m-d H:i:s', strtotime('-1 day 09:00'));
        $msgId = (int) $db->insert('email_messages', [
            'contact_id' => $c3, 'direction' => 'outbound', 'origin' => 'sequence', 'thread_key' => "cestari@{$u}.example.test",
            'recipient_email' => "cestari@{$u}.example.test", 'subject' => 'Proposta ON', 'body' => '<p>Olá</p>',
            'track_token' => bin2hex(random_bytes(16)), 'status' => 'sent', 'sent_by' => $adminId,
            'error_message' => 'Devolvido: Endereço de e-mail não existe (5.1.1)', 'sent_at' => $at, 'created_at' => $at,
        ]);
        $db->insert('email_events', ['message_id' => $msgId, 'contact_id' => $c3, 'event_type' => 'bounce']);

        echo json_encode([
            'u' => $u, 'seqId' => $seqId, 'boardId' => $boardId, 'cardId' => $cardId, 'contacts' => [$c1, $c2, $c3],
            'admin' => ['id' => $adminId, 'email' => $email, 'password' => $password],
        ]);
        exit(0);
    }

    if ($cmd === 'teardown') {
        $data = json_decode($argv[2] ?? '', true);
        if (!is_array($data)) throw new RuntimeException('json inválido');
        foreach (($data['contacts'] ?? []) as $cid) {
            $cid = (int) $cid;
            foreach (['email_events', 'email_messages', 'sequence_participants', 'lead_timeline', 'lead_score'] as $t) {
                try { $db->delete($t, 'contact_id = ?', [$cid]); } catch (Throwable $e) {}
            }
            foreach ($db->fetchAll("SELECT id FROM crm_cards WHERE contact_id = ?", [$cid]) as $c) {
                try { $db->delete('crm_card_activities', 'card_id = ?', [(int) $c['id']]); } catch (Throwable $e) {}
                $db->delete('crm_cards', 'id = ?', [(int) $c['id']]);
            }
            $db->delete('whatsapp_contacts', 'id = ?', [$cid]);
        }
        if (!empty($data['seqId'])) {
            try { $db->delete('sequence_participants', 'sequence_id = ?', [(int) $data['seqId']]); } catch (Throwable $e) {}
            $db->delete('email_sequences', 'id = ?', [(int) $data['seqId']]);
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
