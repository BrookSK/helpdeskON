<?php
/**
 * Fixture do E2E "Acompanhamento da campanha de prospecção" (helper, não é teste).
 *
 * Uso:
 *   php tests-e2e/prospecting-status-fixture.php setup
 *       Cria no banco local: um super_admin (senha aleatória), uma sequência e
 *       três campanhas com histórico de execuções:
 *         - noResults: ativa, roda o dia todo, 8 execuções seguidas com
 *           "0 candidatos" (o caso do print da cliente);
 *         - working:   ativa, 2 leads captados hoje;
 *         - offHours:  ativa, mas só capta em um dia que não é hoje.
 *       Imprime JSON.
 *   php tests-e2e/prospecting-status-fixture.php teardown '<json do setup>'
 *       Remove tudo o que o setup criou.
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
        $email = "e2e_prosp_{$u}@example.test";
        $adminId = (int) $db->insert('users', [
            'name' => "E2E Prospecção {$u}",
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'role' => 'super_admin',
            'is_active' => 1,
        ]);
        $seqId = (int) $db->insert('email_sequences', ['name' => "E2E Fluxo {$u}"]);

        $base = [
            'is_active' => 1, 'lead_source' => 'apollo', 'sequence_id' => $seqId,
            'daily_target' => 50, 'min_score' => 50, 'search_page' => 1,
            'window_start' => '00:00:00', 'window_end' => '23:59:59', 'days_of_week' => '1,2,3,4,5,6,7',
            'search_filters' => json_encode(['person_titles' => ['CEO', 'Diretor'], 'organization_num_employees_ranges' => ['11,50'], 'q_keywords' => 'logística'], JSON_UNESCAPED_UNICODE),
            'icp_rules' => json_encode(['seniorities' => ['owner', 'c_suite'], 'require_website' => true]),
            'created_by' => $adminId,
        ];
        $tomorrowDow = (int) date('N', strtotime('+1 day'));
        $camps = [
            'noResults' => (int) $db->insert('apollo_campaigns', ['name' => "E2E Sem resultados {$u}"] + $base),
            'working'   => (int) $db->insert('apollo_campaigns', ['name' => "E2E Funcionando {$u}"] + $base),
            'offHours'  => (int) $db->insert('apollo_campaigns', ['name' => "E2E Fora do horário {$u}", 'days_of_week' => (string) $tomorrowDow, 'window_start' => '08:00:00', 'window_end' => '18:00:00'] + $base),
        ];

        $log = function ($campId, $action, $detail, $ts, $extra = []) use ($db) {
            $db->insert('apollo_prospecting_log', [
                'campaign_id' => $campId, 'action' => $action, 'detail' => $detail,
                'created_at' => date('Y-m-d H:i:s', $ts),
            ] + $extra);
        };
        $done = fn($camp, array $m) => json_encode(array_merge([
            'campaign' => $camp, 'name' => 'x', 'source' => 'apollo', 'searched' => 0, 'analyzed' => 0,
            'duplicated' => 0, 'out_of_icp' => 0, 'low_score' => 0, 'selected' => 0, 'revealed_email' => 0,
            'reveal_failed' => 0, 'imported' => 0, 'enrolled' => 0, 'pages' => 1,
        ], $m), JSON_UNESCAPED_UNICODE);

        // Caso do print: a cada minuto, "0 candidatos (página 1)" + done vazio.
        $now = time();
        for ($i = 8; $i >= 1; $i--) {
            $ts = $now - $i * 60;
            $log($camps['noResults'], 'searched', '0 candidatos (página 1)', $ts);
            $log($camps['noResults'], 'done', $done($camps['noResults'], []), $ts);
        }

        // Funcionando: 2 leads captados agora há pouco.
        $contacts = [];
        foreach (['Ana E2E', 'Bruno E2E'] as $k => $n) {
            $contacts[] = $cid = (int) $db->insert('whatsapp_contacts', [
                'remote_jid' => "5599900{$k}" . substr($u, 0, 4) . '@s.whatsapp.net',
                'contact_name' => "{$n} {$u}",
                'lead_email' => strtolower(explode(' ', $n)[0]) . "_{$u}@example.test",
            ]);
            $ts = $now - 120 + $k;
            $log($camps['working'], 'reveal_email', '1 crédito(s)', $ts, ['credits' => 1]);
            $log($camps['working'], 'imported', "{$n} (novo → Super Admin)", $ts, ['contact_id' => $cid]);
            $log($camps['working'], 'enrolled', 'Apollo → sequência', $ts, ['contact_id' => $cid]);
        }
        $log($camps['working'], 'searched', '40 candidatos (página 1)', $now - 60);
        $log($camps['working'], 'done', $done($camps['working'], ['searched' => 40, 'analyzed' => 40, 'duplicated' => 30, 'low_score' => 8, 'selected' => 2, 'revealed_email' => 2, 'imported' => 2, 'enrolled' => 2]), $now - 60);

        echo json_encode([
            'u' => $u, 'sequenceId' => $seqId, 'campaigns' => $camps, 'contacts' => $contacts,
            'admin' => ['id' => $adminId, 'email' => $email, 'password' => $password],
        ]);
        exit(0);
    }

    if ($cmd === 'teardown') {
        $data = json_decode($argv[2] ?? '', true);
        if (!is_array($data)) throw new RuntimeException('json inválido');
        foreach ((array) ($data['campaigns'] ?? []) as $cid) {
            $db->delete('apollo_prospecting_log', 'campaign_id = ?', [(int) $cid]);
            $db->delete('apollo_campaigns', 'id = ?', [(int) $cid]);
        }
        foreach ((array) ($data['contacts'] ?? []) as $cid) {
            $db->delete('whatsapp_contacts', 'id = ?', [(int) $cid]);
        }
        if (!empty($data['sequenceId'])) $db->delete('email_sequences', 'id = ?', [(int) $data['sequenceId']]);
        $adminId = (int) ($data['admin']['id'] ?? 0);
        if ($adminId) {
            foreach (['notifications', 'user_activity_log', 'user_login_history', 'activity_logs'] as $table) {
                try { $db->delete($table, 'user_id = ?', [$adminId]); } catch (Throwable $e) {}
            }
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
