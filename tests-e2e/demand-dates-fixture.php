<?php
/**
 * Fixture do E2E "datas da demanda definidas pela equipe" (helper, não é teste).
 *
 * Uso:
 *   php tests-e2e/demand-dates-fixture.php setup
 *       Cria no banco local: empresa, usuário client vinculado a ela e um
 *       super_admin (senhas aleatórias). Imprime JSON com os dados.
 *   php tests-e2e/demand-dates-fixture.php dates <ticketId>
 *       Imprime JSON com client_id e as três datas gravadas da demanda.
 *   php tests-e2e/demand-dates-fixture.php teardown '<json do setup>'
 *       Remove as demandas criadas pelos usuários do setup e tudo o que o setup criou.
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
        $companyId = (int) $db->insert('companies', ['name' => "E2E Datas {$u}"]);
        $mk = function (string $role, ?int $company) use ($db, $u) {
            $password = 'E2e!' . bin2hex(random_bytes(6));
            $email = "e2e_dd_{$role}_{$u}@example.test";
            $row = [
                'name' => "E2E Datas {$role} {$u}",
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'role' => $role,
                'is_active' => 1,
            ];
            if ($company) $row['company_id'] = $company;
            return ['id' => (int) $db->insert('users', $row), 'email' => $email, 'password' => $password];
        };
        echo json_encode([
            'companyId' => $companyId,
            'client' => $mk('client', $companyId),
            'admin' => $mk('super_admin', null),
        ]);
        exit(0);
    }

    if ($cmd === 'dates') {
        $row = $db->fetch(
            "SELECT client_id, data_inicio, data_termino, data_prevista_entrega FROM tickets WHERE id = ?",
            [(int) ($argv[2] ?? 0)]
        );
        echo json_encode($row ?: null);
        exit(0);
    }

    if ($cmd === 'teardown') {
        $data = json_decode($argv[2] ?? '', true);
        if (!is_array($data)) throw new RuntimeException('json inválido');
        $userIds = array_filter([(int) ($data['client']['id'] ?? 0), (int) ($data['admin']['id'] ?? 0)]);
        foreach ($userIds as $uid) {
            $tickets = $db->fetchAll("SELECT id FROM tickets WHERE client_id = ?", [$uid]);
            foreach ($tickets as $t) {
                $tid = (int) $t['id'];
                foreach (['planning_cards', 'notifications', 'ticket_attendants', 'ticket_messages', 'attachments'] as $table) {
                    try { $db->delete($table, 'ticket_id = ?', [$tid]); } catch (Throwable $e) {}
                }
                $db->delete('tickets', 'id = ?', [$tid]);
            }
        }
        foreach ($userIds as $uid) {
            foreach (['notifications', 'user_activity_log', 'user_login_history', 'activity_logs'] as $table) {
                try { $db->delete($table, 'user_id = ?', [$uid]); } catch (Throwable $e) {}
            }
            try { $db->delete('planning_cards', 'created_by = ?', [$uid]); } catch (Throwable $e) {}
            $db->delete('users', 'id = ?', [$uid]);
        }
        if (!empty($data['companyId'])) {
            $db->delete('companies', 'id = ?', [(int) $data['companyId']]);
        }
        echo 'ok';
        exit(0);
    }

    throw new RuntimeException('comando desconhecido: ' . $cmd);
} catch (Throwable $e) {
    fwrite(STDERR, 'ERRO: ' . $e->getMessage());
    exit(1);
}
