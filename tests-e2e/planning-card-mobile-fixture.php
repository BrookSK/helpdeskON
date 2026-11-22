<?php
/**
 * Fixture do E2E "Planejamento no celular" (helper, não é teste).
 *
 * Uso:
 *   php tests-e2e/planning-card-mobile-fixture.php setup
 *       Cria no banco local: empresa, um super_admin (senha aleatória) e cards
 *       atribuídos a ele em status/prioridades/prazos diferentes. Imprime JSON.
 *   php tests-e2e/planning-card-mobile-fixture.php reset '<json do setup>'
 *       Volta os cards ao estado inicial (status/prioridade/atendente/prazo).
 *   php tests-e2e/planning-card-mobile-fixture.php card <cardId>
 *       Imprime JSON com status, priority e assigned_to gravados do card.
 *   php tests-e2e/planning-card-mobile-fixture.php teardown '<json do setup>'
 *       Remove os cards (e filhos), o usuário e a empresa criados no setup.
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

/** Estado inicial de cada card (chave => campos). */
function pmInitial(string $u): array
{
    $tomorrow = date('Y-m-d 18:00:00', strtotime('+1 day'));
    $yesterday = date('Y-m-d 10:00:00', strtotime('-1 day'));
    $todayLate = date('Y-m-d 23:59:00');
    return [
        'main'  => ['title' => "E2E Entrega de Processos documentados e Reunião de entrega {$u}", 'status' => 'in_progress', 'priority' => 'high', 'due_date' => $tomorrow],
        'late'  => ['title' => "E2E Ajustar permissões do painel {$u}", 'status' => 'open', 'priority' => 'medium', 'due_date' => $yesterday],
        'today' => ['title' => "E2E Revisar proposta comercial {$u}", 'status' => 'open', 'priority' => 'low', 'due_date' => $todayLate],
        'done'  => ['title' => "E2E Contrato assinado {$u}", 'status' => 'completed', 'priority' => 'urgent', 'due_date' => $yesterday],
    ];
}

$cmd = $argv[1] ?? '';

try {
    $db = Database::getInstance();

    if ($cmd === 'setup') {
        $u = bin2hex(random_bytes(4));
        $companyId = (int) $db->insert('companies', ['name' => "E2E Mobile {$u}"]);
        $password = 'E2e!' . bin2hex(random_bytes(6));
        $email = "e2e_pm_admin_{$u}@example.test";
        $adminId = (int) $db->insert('users', [
            'name' => "E2E Mobile admin {$u}",
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'role' => 'super_admin',
            'is_active' => 1,
        ]);
        $cards = [];
        foreach (pmInitial($u) as $key => $row) {
            $cards[$key] = (int) $db->insert('planning_cards', $row + [
                'company_id' => $companyId,
                'created_by' => $adminId,
                'assigned_to' => $adminId,
                'position' => 0,
            ]);
        }
        echo json_encode([
            'u' => $u,
            'companyId' => $companyId,
            'cards' => $cards,
            'cardId' => $cards['main'],
            'admin' => ['id' => $adminId, 'email' => $email, 'password' => $password],
        ]);
        exit(0);
    }

    if ($cmd === 'reset') {
        $data = json_decode($argv[2] ?? '', true);
        if (!is_array($data)) throw new RuntimeException('json inválido');
        foreach (pmInitial($data['u']) as $key => $row) {
            $id = (int) ($data['cards'][$key] ?? 0);
            if (!$id) continue;
            $db->update('planning_cards', $row + ['assigned_to' => (int) $data['admin']['id']], 'id = ?', [$id]);
            try { $db->delete('planning_card_history', 'card_id = ?', [$id]); } catch (Throwable $e) {}
        }
        echo 'ok';
        exit(0);
    }

    if ($cmd === 'card') {
        $row = $db->fetch("SELECT status, priority, assigned_to FROM planning_cards WHERE id = ?", [(int) ($argv[2] ?? 0)]);
        echo json_encode($row ?: null);
        exit(0);
    }

    if ($cmd === 'teardown') {
        $data = json_decode($argv[2] ?? '', true);
        if (!is_array($data)) throw new RuntimeException('json inválido');
        $adminId = (int) ($data['admin']['id'] ?? 0);
        foreach ((array) ($data['cards'] ?? []) as $cardId) {
            $cardId = (int) $cardId;
            foreach (['planning_card_history', 'planning_comments', 'planning_tasks', 'planning_attachments'] as $table) {
                try { $db->delete($table, 'card_id = ?', [$cardId]); } catch (Throwable $e) {}
            }
            $db->delete('planning_cards', 'id = ?', [$cardId]);
        }
        if ($adminId) {
            foreach (['notifications', 'user_activity_log', 'user_login_history', 'activity_logs'] as $table) {
                try { $db->delete($table, 'user_id = ?', [$adminId]); } catch (Throwable $e) {}
            }
            foreach (['planning_card_history', 'planning_comments'] as $table) {
                try { $db->delete($table, 'user_id = ?', [$adminId]); } catch (Throwable $e) {}
            }
            try { $db->delete('planning_cards', 'created_by = ?', [$adminId]); } catch (Throwable $e) {}
            $db->delete('users', 'id = ?', [$adminId]);
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
