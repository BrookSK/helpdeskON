<?php
/**
 * Fixture do E2E "Duplicar campanha + intervalo entre WhatsApps" (helper, não é teste).
 *
 *   php tests-e2e/campaign-duplicate-fixture.php setup
 *       Cria: super_admin (senha aleatória), uma sequência de WhatsApp com
 *       intervalo 3 a 12 min e uma campanha Apollo ativa ligada a ela, com
 *       filtros preenchidos (cargos, nº de funcionários, nichos). Imprime JSON.
 *   php tests-e2e/campaign-duplicate-fixture.php campaigns <sufixo>
 *       JSON das campanhas com esse sufixo no nome.
 *   php tests-e2e/campaign-duplicate-fixture.php teardown '<json>'
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
        $email = "e2e_campdup_{$u}@example.test";
        $adminId = (int) $db->insert('users', [
            'name' => "E2E Campanha {$u}", 'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT), 'role' => 'super_admin', 'is_active' => 1,
        ]);
        $seq = ['name' => "E2E Fluxo WA {$u}", 'graph' => json_encode(['start' => null, 'nodes' => []]), 'is_active' => 0, 'created_by' => $adminId];
        if (SchemaInfo::hasColumn('email_sequences', 'channel_type')) $seq['channel_type'] = 'whatsapp';
        if (SchemaInfo::hasColumn('email_sequences', 'wa_gap_min_minutes')) { $seq['wa_gap_min_minutes'] = 3; $seq['wa_gap_max_minutes'] = 12; }
        $seqId = (int) $db->insert('email_sequences', $seq);
        $campId = (int) $db->insert('apollo_campaigns', [
            'name' => "E2E Captação Site 11-50 {$u}", 'is_active' => 1, 'lead_source' => 'apollo', 'sequence_id' => $seqId,
            'daily_target' => 20, 'min_score' => 60, 'search_per_page' => 40, 'search_page' => 3,
            'window_start' => '09:00:00', 'window_end' => '17:00:00', 'days_of_week' => '1,2,3,4,5',
            'reveal_email' => 1, 'reveal_phone' => 1,
            'search_filters' => json_encode(['person_titles' => ['CEO', 'Diretor'], 'organization_num_employees_ranges' => ['11,50'], 'niches' => ['engenharia']], JSON_UNESCAPED_UNICODE),
            'icp_rules' => json_encode(['seniorities' => ['owner'], 'require_website' => true, 'employee_min' => 11, 'employee_max' => 50]),
            'created_by' => $adminId,
        ]);
        echo json_encode(['u' => $u, 'sequenceId' => $seqId, 'campaignId' => $campId,
            'admin' => ['id' => $adminId, 'email' => $email, 'password' => $password]]);
        exit(0);
    }

    if ($cmd === 'campaigns') {
        $u = preg_replace('/[^a-f0-9]/', '', (string) ($argv[2] ?? ''));
        $rows = $db->fetchAll("SELECT id, name, is_active, sequence_id, daily_target, min_score, search_per_page, search_page,
                window_start, window_end, days_of_week, reveal_email, reveal_phone, search_filters, icp_rules
            FROM apollo_campaigns WHERE name LIKE ? ORDER BY id", ["%{$u}%"]);
        echo json_encode($rows, JSON_UNESCAPED_UNICODE);
        exit(0);
    }

    if ($cmd === 'teardown') {
        $data = json_decode($argv[2] ?? '', true);
        if (!is_array($data)) throw new RuntimeException('json inválido');
        $u = preg_replace('/[^a-f0-9]/', '', (string) $data['u']);
        if ($u === '') throw new RuntimeException('sufixo vazio');
        $db->query("DELETE FROM apollo_campaigns WHERE name LIKE ?", ["%{$u}%"]);
        if (!empty($data['sequenceId'])) $db->delete('email_sequences', 'id = ?', [(int) $data['sequenceId']]);
        $adminId = (int) ($data['admin']['id'] ?? 0);
        if ($adminId) {
            foreach (['notifications', 'user_activity_log', 'user_login_history', 'activity_logs'] as $t) {
                try { $db->delete($t, 'user_id = ?', [$adminId]); } catch (Throwable $e) {}
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
