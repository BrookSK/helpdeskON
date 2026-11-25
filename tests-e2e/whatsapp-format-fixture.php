<?php
/**
 * Fixture do E2E "Formatação das mensagens de WhatsApp" (helper, não é teste).
 *
 *   php tests-e2e/whatsapp-format-fixture.php setup
 *       Cria: super_admin (senha aleatória), um template de WhatsApp formatado e
 *       uma sequência com um bloco "Enviar WhatsApp". Imprime JSON.
 *   php tests-e2e/whatsapp-format-fixture.php template <nome>
 *       JSON do template com esse nome (ou null).
 *   php tests-e2e/whatsapp-format-fixture.php teardown '<json>'
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
        $email = "e2e_wafmt_{$u}@example.test";
        $adminId = (int) $db->insert('users', [
            'name' => "E2E WA Format {$u}", 'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT), 'role' => 'super_admin', 'is_active' => 1,
        ]);
        $tplId = (int) $db->insert('message_templates', [
            'channel' => 'whatsapp', 'name' => "E2E WA Modelo {$u}", 'subject' => null,
            'body' => "Oi {{primeiro_nome}}, *proposta pronta* 🟢\n• Prazo _curto_\n• Preço ~antigo~ novo",
            'created_by' => $adminId,
        ]);
        $graph = ['start' => 'n1', 'nodes' => [[
            'id' => 'n1', 'type' => 'whatsapp', 'x' => 60, 'y' => 60,
            'data' => ['body' => 'Texto antigo'],
        ]]];
        $seqId = (int) $db->insert('email_sequences', [
            'name' => "E2E SeqWA {$u}", 'graph' => json_encode($graph, JSON_UNESCAPED_UNICODE),
            'is_active' => 0, 'created_by' => $adminId,
        ]);
        echo json_encode(['u' => $u, 'templateId' => $tplId, 'sequenceId' => $seqId,
            'admin' => ['id' => $adminId, 'email' => $email, 'password' => $password]]);
        exit(0);
    }

    if ($cmd === 'template') {
        $row = $db->fetch("SELECT id, channel, name, subject, body FROM message_templates WHERE name = ? ORDER BY id DESC LIMIT 1", [$argv[2] ?? '']);
        echo json_encode($row ?: null, JSON_UNESCAPED_UNICODE);
        exit(0);
    }

    if ($cmd === 'teardown') {
        $data = json_decode($argv[2] ?? '', true);
        if (!is_array($data)) throw new RuntimeException('json inválido');
        $u = preg_replace('/[^a-f0-9]/', '', (string) $data['u']);
        if ($u === '') throw new RuntimeException('sufixo vazio');
        $db->query("DELETE FROM message_templates WHERE name LIKE ?", ["%{$u}%"]);
        if (!empty($data['sequenceId'])) $db->delete('email_sequences', 'id = ?', [(int) $data['sequenceId']]);
        $adminId = (int) ($data['admin']['id'] ?? 0);
        if ($adminId) {
            foreach (['notifications', 'user_activity_log', 'user_login_history', 'activity_logs'] as $t) {
                try { $db->delete($t, 'user_id = ?', [$adminId]); } catch (Throwable $e) {}
            }
            $db->query("UPDATE message_templates SET created_by = NULL WHERE created_by = ?", [$adminId]);
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
