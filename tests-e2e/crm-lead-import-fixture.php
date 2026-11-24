<?php
/**
 * Fixture do E2E "CRM: dados do lead no card + importar planilha" (helper, não é teste).
 *
 *   php tests-e2e/crm-lead-import-fixture.php setup
 *       Cria: super_admin (senha aleatória), board com coluna "Novo", e um lead
 *       com cargo/empresa/e-mail/LinkedIn e card de "Prospecção automática".
 *       Imprime JSON.
 *   php tests-e2e/crm-lead-import-fixture.php contact <email>
 *       JSON do lead (dono, cargo, empresa, origem do card) ou null.
 *   php tests-e2e/crm-lead-import-fixture.php teardown '<json>'
 *       Remove tudo o que o setup e a importação criaram (leads @<u>.example.test).
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
        $email = "e2e_crmimp_{$u}@example.test";
        $adminId = (int) $db->insert('users', [
            'name' => "E2E CRM Import {$u}", 'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT), 'role' => 'super_admin', 'is_active' => 1,
        ]);
        $board = new CrmBoard();
        $boardId = (int) $board->create(['name' => "E2E Board {$u}", 'created_by' => $adminId]);
        $columnId = (int) $board->createColumn(['board_id' => $boardId, 'name' => 'Novo']);

        $contactId = (int) (new LeadResolver())->resolve([
            'name' => "Carla Auto {$u}", 'email' => "carla@{$u}.example.test", 'phone' => '5599' . substr((string) hexdec($u), 0, 8),
            'company' => 'Clínica Sorriso', 'job_title' => 'Diretora Clínica',
            'linkedin_url' => "https://www.linkedin.com/in/carla-{$u}",
            'source' => 'apollo', 'assigned_to' => $adminId,
            'briefing' => ['notes' => 'Cargo: Diretora Clínica | Empresa: Clínica Sorriso'],
        ], $adminId);
        if (!$contactId) throw new RuntimeException('sem instância de WhatsApp no banco local');
        // Engajamento: 1 abertura + 1 resposta = 6 pontos => quente.
        (new LeadScoreService())->add($contactId, LeadScoreService::W_OPEN, 'abertura de e-mail', $adminId);
        (new LeadScoreService())->add($contactId, LeadScoreService::W_REPLY, 'resposta recebida', $adminId);
        $cardId = (int) $board->createCard([
            'column_id' => $columnId, 'title' => "Carla Auto {$u}", 'contact_id' => $contactId,
            'created_by' => $adminId, 'assigned_to' => $adminId,
            'origin' => LeadOriginRules::AUTO, 'origin_detail' => 'Captação Site Operacional',
        ]);

        echo json_encode([
            'u' => $u, 'boardId' => $boardId, 'columnId' => $columnId, 'contactId' => $contactId, 'cardId' => $cardId,
            'admin' => ['id' => $adminId, 'email' => $email, 'password' => $password],
        ]);
        exit(0);
    }

    if ($cmd === 'contact') {
        $row = $db->fetch(
            "SELECT wc.id, wc.contact_name, wc.assigned_to, wc.job_title, wc.company_name, wc.phone,
                    (SELECT COUNT(*) FROM crm_cards c WHERE c.contact_id = wc.id) AS cards,
                    (SELECT c.origin FROM crm_cards c WHERE c.contact_id = wc.id ORDER BY c.id LIMIT 1) AS origin,
                    (SELECT c.column_id FROM crm_cards c WHERE c.contact_id = wc.id ORDER BY c.id LIMIT 1) AS column_id,
                    (SELECT b.lead_source FROM commercial_briefings b WHERE b.contact_id = wc.id LIMIT 1) AS lead_source
             FROM whatsapp_contacts wc WHERE wc.lead_email = ? LIMIT 1",
            [mb_strtolower($argv[2] ?? '')]
        );
        echo json_encode($row ?: null);
        exit(0);
    }

    if ($cmd === 'teardown') {
        $data = json_decode($argv[2] ?? '', true);
        if (!is_array($data)) throw new RuntimeException('json inválido');
        $u = preg_replace('/[^a-f0-9]/', '', (string) $data['u']);
        $ids = array_map(fn($r) => (int) $r['id'], $db->fetchAll(
            "SELECT id FROM whatsapp_contacts WHERE lead_email LIKE ? OR contact_name LIKE ?",
            ["%@{$u}.example.test", "%{$u}%"]
        ));
        if (!empty($data['contactId'])) $ids[] = (int) $data['contactId'];
        foreach (array_unique($ids) as $id) {
            foreach ($db->fetchAll("SELECT id FROM crm_cards WHERE contact_id = ?", [$id]) as $c) {
                try { $db->delete('crm_card_activities', 'card_id = ?', [(int) $c['id']]); } catch (Throwable $e) {}
                $db->delete('crm_cards', 'id = ?', [(int) $c['id']]);
            }
            foreach (['lead_timeline', 'lead_score', 'commercial_briefings', 'sequence_participants'] as $t) {
                try { $db->delete($t, 'contact_id = ?', [$id]); } catch (Throwable $e) {}
            }
            $db->delete('whatsapp_contacts', 'id = ?', [$id]);
        }
        if (!empty($data['boardId'])) {
            foreach ($db->fetchAll("SELECT c.id FROM crm_cards c JOIN crm_columns col ON col.id = c.column_id WHERE col.board_id = ?", [(int) $data['boardId']]) as $c) {
                try { $db->delete('crm_card_activities', 'card_id = ?', [(int) $c['id']]); } catch (Throwable $e) {}
                $db->delete('crm_cards', 'id = ?', [(int) $c['id']]);
            }
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
