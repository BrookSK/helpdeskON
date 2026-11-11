<?php
/**
 * Fixture do E2E "menções (@) em grupos do WhatsApp" (helper, não é teste).
 *
 * Uso:
 *   php tests-e2e/mentions-fixture.php setup <portaFakeEvolution>
 *       Cria no banco local: usuário attendant, instância apontando para a
 *       Evolution FALSA do teste (sem owner_phone/owner_lid: o sistema precisa
 *       descobrir sozinho), dois grupos e mensagens. O "Grupo Menções E2E" já
 *       nasce com uma menção ao nosso número não vista. Imprime JSON.
 *   php tests-e2e/mentions-fixture.php state '<json do setup>'
 *       Imprime JSON com mention_count dos grupos e owner_phone/owner_lid.
 *   php tests-e2e/mentions-fixture.php reset-owner '<json do setup>'
 *       Apaga owner_phone/owner_lid da instância (força nova descoberta).
 *   php tests-e2e/mentions-fixture.php teardown '<json do setup>'
 *       Remove tudo o que o setup (e o teste) criou.
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
        $port = (int) ($argv[2] ?? 0);
        if ($port <= 0) throw new RuntimeException('porta inválida');
        $u = bin2hex(random_bytes(4));
        $password = 'E2e!' . bin2hex(random_bytes(6));
        $email = "e2e_mention_{$u}@example.test";

        $userId = (int) $db->insert('users', [
            'name' => 'E2E Menção ' . $u,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'role' => 'attendant',
            'is_active' => 1,
        ]);
        $instanceName = "e2e_mention_{$u}";
        $instanceId = (int) $db->insert('whatsapp_instances', [
            'instance_name' => $instanceName,
            'display_name' => "E2E Menção {$u}",
            'api_url' => "http://127.0.0.1:{$port}",
            'api_key' => 'e2e-key',
            'user_id' => $userId,
            'connection_status' => 'open',
        ]);

        // Foto já definida: o webhook não tenta baixar/gerar avatar (sem lixo em uploads)
        $pic = 'https://example.test/e2e-group.png';
        $jidSeed = substr(preg_replace('/\D/', '', (string) hexdec($u)) . '000000', 0, 6);
        $group1Jid = "120363900{$jidSeed}001@g.us";
        $group2Jid = "120363900{$jidSeed}002@g.us";
        $group1 = (int) $db->insert('whatsapp_contacts', [
            'instance_id' => $instanceId, 'remote_jid' => $group1Jid, 'phone' => preg_replace('/@.*/', '', $group1Jid),
            'contact_name' => 'Grupo Menções E2E', 'is_group' => 1, 'profile_picture_url' => $pic,
            'last_message_at' => date('Y-m-d H:i:s'), 'unread_count' => 1, 'mention_count' => 1,
        ]);
        $group2 = (int) $db->insert('whatsapp_contacts', [
            'instance_id' => $instanceId, 'remote_jid' => $group2Jid, 'phone' => preg_replace('/@.*/', '', $group2Jid),
            'contact_name' => 'Grupo Webhook E2E', 'is_group' => 1, 'profile_picture_url' => $pic,
            'last_message_at' => date('Y-m-d H:i:s', time() - 60),
        ]);

        $msg = function (int $contactId, string $jid, string $text, string $participant, string $sender, int $mentionsMe, int $secondsAgo) use ($db, $instanceId) {
            $db->insert('whatsapp_messages', [
                'instance_id' => $instanceId, 'contact_id' => $contactId, 'remote_jid' => $jid,
                'message_id' => 'E2EMEN' . strtoupper(bin2hex(random_bytes(6))), 'from_me' => 0,
                'message_type' => 'text', 'message_text' => $text, 'sender_name' => $sender,
                'participant_jid' => $participant, 'mentions_me' => $mentionsMe, 'is_read' => 0,
                'timestamp' => date('Y-m-d H:i:s', time() - $secondsAgo),
            ]);
        };
        $msg($group1, $group1Jid, 'Bom dia pessoal', '58935558037626@lid', 'Lucas Vacari', 0, 120);
        $msg($group1, $group1Jid, '@156079530967127 consegue ver o deploy?', '236932122603562@lid', 'Ana Cliente', 1, 60);
        $msg($group2, $group2Jid, 'Mensagem antiga', '58935558037626@lid', 'Lucas Vacari', 0, 90);

        // Conversa individual (atribuída ao usuário): ali o @ não abre lista
        $contact = (int) $db->insert('whatsapp_contacts', [
            'instance_id' => $instanceId, 'remote_jid' => '5511987659999@s.whatsapp.net', 'phone' => '5511987659999',
            'contact_name' => 'Contato Individual E2E', 'is_group' => 0, 'assigned_to' => $userId,
            'profile_picture_url' => $pic, 'last_message_at' => date('Y-m-d H:i:s'),
        ]);

        echo json_encode([
            'userId' => $userId, 'email' => $email, 'password' => $password,
            'instanceId' => $instanceId, 'instanceName' => $instanceName,
            'group1' => $group1, 'group1Jid' => $group1Jid,
            'group2' => $group2, 'group2Jid' => $group2Jid,
            'contact' => $contact,
        ]);
        exit(0);
    }

    $data = json_decode($argv[2] ?? '', true);
    if (!is_array($data)) throw new RuntimeException('json inválido');

    if ($cmd === 'state') {
        $groups = [];
        foreach (['group1', 'group2'] as $k) {
            $r = $db->fetch("SELECT mention_count, unread_count FROM whatsapp_contacts WHERE id = ?", [(int) $data[$k]]);
            $groups[$k] = ['mention_count' => (int) ($r['mention_count'] ?? -1), 'unread_count' => (int) ($r['unread_count'] ?? -1)];
        }
        $inst = $db->fetch("SELECT owner_phone, owner_lid FROM whatsapp_instances WHERE id = ?", [(int) $data['instanceId']]);
        $lastMention = $db->fetch(
            "SELECT message_text FROM whatsapp_messages WHERE contact_id = ? AND mentions_me = 1 ORDER BY id DESC LIMIT 1",
            [(int) $data['group2']]
        );
        echo json_encode([
            'groups' => $groups,
            'owner_phone' => $inst['owner_phone'] ?? null,
            'owner_lid' => $inst['owner_lid'] ?? null,
            'group2_last_mention' => $lastMention['message_text'] ?? null,
        ]);
        exit(0);
    }

    if ($cmd === 'reset-owner') {
        // Esquece o número/LID descobertos, para o webhook ter de descobrir de novo
        $db->update('whatsapp_instances', ['owner_phone' => null, 'owner_lid' => null], 'id = ?', [(int) $data['instanceId']]);
        echo 'ok';
        exit(0);
    }

    if ($cmd === 'teardown') {
        foreach (['group1', 'group2'] as $k) {
            $id = (int) ($data[$k] ?? 0);
            if (!$id) continue;
            try { $db->delete('whatsapp_messages', 'contact_id = ?', [$id]); } catch (Throwable $e) {}
            try { $db->delete('whatsapp_contacts', 'id = ?', [$id]); } catch (Throwable $e) {}
        }
        if (!empty($data['instanceId'])) {
            try { $db->delete('whatsapp_messages', 'instance_id = ?', [(int) $data['instanceId']]); } catch (Throwable $e) {}
            try { $db->delete('whatsapp_contacts', 'instance_id = ?', [(int) $data['instanceId']]); } catch (Throwable $e) {}
            try { $db->delete('whatsapp_instances', 'id = ?', [(int) $data['instanceId']]); } catch (Throwable $e) {}
        }
        if (!empty($data['userId'])) {
            foreach (['user_activity_log' => 'user_id', 'user_login_history' => 'user_id', 'activity_logs' => 'user_id'] as $table => $col) {
                try { $db->delete($table, "{$col} = ?", [(int) $data['userId']]); } catch (Throwable $e) {}
            }
            $db->delete('users', 'id = ?', [(int) $data['userId']]);
        }
        echo 'ok';
        exit(0);
    }

    throw new RuntimeException('comando desconhecido: ' . $cmd);
} catch (Throwable $e) {
    fwrite(STDERR, 'ERRO: ' . $e->getMessage());
    exit(1);
}
