<?php
/**
 * Fixture do E2E "Sequência: seleção múltipla, copiar/colar e duplicar" (helper, não é teste).
 *
 *   php tests-e2e/sequence-multiselect-fixture.php setup
 *       Cria: super_admin (senha aleatória) e duas sequências inativas:
 *       A (e-mail → aguardar → condição, ligados) e B (um bloco). Imprime JSON.
 *   php tests-e2e/sequence-multiselect-fixture.php sequences <u>
 *       JSON das sequências com <u> no nome (id, nome, ativa, grafo, nº de leads).
 *   php tests-e2e/sequence-multiselect-fixture.php teardown '<json>'
 *       Remove as sequências com <u> no nome (inclusive as cópias) e o usuário.
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
        $email = "e2e_seqsel_{$u}@example.test";
        $adminId = (int) $db->insert('users', [
            'name' => "E2E Seq Sel {$u}", 'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT), 'role' => 'super_admin', 'is_active' => 1,
        ]);
        $graphA = ['start' => 'a1', 'nodes' => [
            ['id' => 'a1', 'type' => 'send', 'x' => 80, 'y' => 80, 'data' => ['subject' => 'Primeiro contato', 'body' => '<p><strong>Oi</strong></p>'], 'next' => 'a2'],
            ['id' => 'a2', 'type' => 'wait', 'x' => 80, 'y' => 240, 'data' => ['amount' => 2, 'unit' => 'days'], 'next' => 'a3'],
            ['id' => 'a3', 'type' => 'condition', 'x' => 80, 'y' => 400, 'data' => ['kind' => 'replied']],
        ]];
        $graphB = ['start' => 'b1', 'nodes' => [
            ['id' => 'b1', 'type' => 'whatsapp', 'x' => 80, 'y' => 80, 'data' => ['body' => 'Olá']],
        ]];
        $seqA = (int) $db->insert('email_sequences', [
            'name' => "E2E SeqSel A {$u}", 'graph' => json_encode($graphA, JSON_UNESCAPED_UNICODE),
            'is_active' => 0, 'daily_limit' => 33, 'created_by' => $adminId,
        ]);
        $seqB = (int) $db->insert('email_sequences', [
            'name' => "E2E SeqSel B {$u}", 'graph' => json_encode($graphB, JSON_UNESCAPED_UNICODE),
            'is_active' => 0, 'created_by' => $adminId,
        ]);
        echo json_encode(['u' => $u, 'seqA' => $seqA, 'seqB' => $seqB,
            'admin' => ['id' => $adminId, 'email' => $email, 'password' => $password]]);
        exit(0);
    }

    if ($cmd === 'sequences') {
        $u = preg_replace('/[^a-f0-9]/', '', (string) ($argv[2] ?? ''));
        $rows = $db->fetchAll(
            "SELECT s.id, s.name, s.is_active, s.daily_limit, s.graph,
                    (SELECT COUNT(*) FROM sequence_participants p WHERE p.sequence_id = s.id) AS leads
             FROM email_sequences s WHERE s.name LIKE ? ORDER BY s.id",
            ["%{$u}%"]
        );
        foreach ($rows as &$r) { $r['graph'] = json_decode($r['graph'] ?? 'null', true); }
        echo json_encode($rows, JSON_UNESCAPED_UNICODE);
        exit(0);
    }

    if ($cmd === 'teardown') {
        $data = json_decode($argv[2] ?? '', true);
        if (!is_array($data)) throw new RuntimeException('json inválido');
        $u = preg_replace('/[^a-f0-9]/', '', (string) $data['u']);
        if ($u === '') throw new RuntimeException('sem u');
        foreach ($db->fetchAll("SELECT id FROM email_sequences WHERE name LIKE ?", ["%{$u}%"]) as $s) {
            try { $db->delete('sequence_participants', 'sequence_id = ?', [(int) $s['id']]); } catch (Throwable $e) {}
            $db->delete('email_sequences', 'id = ?', [(int) $s['id']]);
        }
        $adminId = (int) ($data['admin']['id'] ?? 0);
        if ($adminId) {
            foreach (['notifications', 'user_activity_log', 'user_login_history', 'activity_logs'] as $t) {
                try { $db->delete($t, 'user_id = ?', [$adminId]); } catch (Throwable $e) {}
            }
            $db->query("UPDATE email_sequences SET created_by = NULL WHERE created_by = ?", [$adminId]);
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
