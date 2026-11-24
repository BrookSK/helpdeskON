<?php
/**
 * Fixture do E2E "Templates formatados + layout do e-mail" (helper, não é teste).
 *
 *   php tests-e2e/email-templates-fixture.php setup
 *       Cria: super_admin (senha aleatória), um template de e-mail formatado e
 *       uma sequência com um bloco "Enviar e-mail". Imprime JSON.
 *   php tests-e2e/email-templates-fixture.php template <nome>
 *       JSON do template com esse nome (ou null).
 *   php tests-e2e/email-templates-fixture.php sequence <id>
 *       JSON do grafo salvo da sequência.
 *   php tests-e2e/email-templates-fixture.php teardown '<json>'
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
        $email = "e2e_tpl_{$u}@example.test";
        $adminId = (int) $db->insert('users', [
            'name' => "E2E Templates {$u}", 'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT), 'role' => 'super_admin', 'is_active' => 1,
        ]);
        $tplId = (int) $db->insert('message_templates', [
            'channel' => 'email', 'name' => "E2E Modelo {$u}", 'subject' => 'Oi {{primeiro_nome}}',
            'body' => '<p><strong>Negrito do modelo</strong> e <em>itálico</em></p><ul><li>Primeiro ponto</li></ul>',
            'created_by' => $adminId,
        ]);
        // Conta de e-mail vinculada (a tela de Prospecção só abre o formulário com conta).
        $accModel = new EmailAccount();
        $accountId = (int) $accModel->create([
            'email' => "conta_{$u}@example.test", 'display_name' => "Conta E2E {$u}",
            'smtp_host' => 'smtp.example.test', 'smtp_port' => 587, 'smtp_encryption' => 'tls',
            'smtp_username' => "conta_{$u}", 'smtp_password' => 'x', 'created_by' => $adminId,
        ]);
        $accModel->setLinkedUsers($accountId, [$adminId]);
        $graph = ['start' => 'n1', 'nodes' => [[
            'id' => 'n1', 'type' => 'send', 'x' => 60, 'y' => 60,
            'data' => ['subject' => 'Assunto antigo', 'body' => 'Texto antigo'],
        ]]];
        $seqId = (int) $db->insert('email_sequences', [
            'name' => "E2E Seq {$u}", 'graph' => json_encode($graph, JSON_UNESCAPED_UNICODE),
            'is_active' => 0, 'created_by' => $adminId,
        ]);
        echo json_encode(['u' => $u, 'templateId' => $tplId, 'sequenceId' => $seqId, 'accountId' => $accountId,
            'admin' => ['id' => $adminId, 'email' => $email, 'password' => $password]]);
        exit(0);
    }

    if ($cmd === 'template') {
        $row = $db->fetch("SELECT id, channel, name, subject, body FROM message_templates WHERE name = ? ORDER BY id DESC LIMIT 1", [$argv[2] ?? '']);
        echo json_encode($row ?: null, JSON_UNESCAPED_UNICODE);
        exit(0);
    }

    if ($cmd === 'sequence') {
        $row = $db->fetch("SELECT graph FROM email_sequences WHERE id = ?", [(int) ($argv[2] ?? 0)]);
        echo $row ? $row['graph'] : 'null';
        exit(0);
    }

    if ($cmd === 'teardown') {
        $data = json_decode($argv[2] ?? '', true);
        if (!is_array($data)) throw new RuntimeException('json inválido');
        $u = preg_replace('/[^a-f0-9]/', '', (string) $data['u']);
        $db->query("DELETE FROM message_templates WHERE name LIKE ?", ["%{$u}%"]);
        if (!empty($data['sequenceId'])) $db->delete('email_sequences', 'id = ?', [(int) $data['sequenceId']]);
        if (!empty($data['accountId'])) {
            try { $db->delete('email_account_users', 'email_account_id = ?', [(int) $data['accountId']]); } catch (Throwable $e) {}
            $db->delete('email_accounts', 'id = ?', [(int) $data['accountId']]);
        }
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
