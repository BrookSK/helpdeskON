<?php
/**
 * Fixture do E2E "exportar contatos dos grupos" (helper, não é teste).
 *
 * Uso:
 *   php tests-e2e/group-export-fixture.php setup <portaFakeEvolution>
 *       Cria no banco local: usuário attendant (senha aleatória), instância de
 *       WhatsApp apontando para a Evolution FALSA do teste e um contato do CRM
 *       (para validar o enriquecimento do nome). Imprime JSON com os ids.
 *   php tests-e2e/group-export-fixture.php teardown '<json do setup>'
 *       Remove tudo o que o setup criou.
 *   php tests-e2e/group-export-fixture.php read-xlsx <arquivo.xlsx>
 *       Imprime as linhas da planilha como JSON (lista de listas).
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
    if ($cmd === 'setup') {
        $port = (int) ($argv[2] ?? 0);
        if ($port <= 0) throw new RuntimeException('porta inválida');
        $db = Database::getInstance();
        $u = bin2hex(random_bytes(4));
        $password = 'E2e!' . bin2hex(random_bytes(6));
        $email = "e2e_export_{$u}@example.test";

        $userId = (int) $db->insert('users', [
            'name' => 'E2E Export ' . $u,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'role' => 'attendant',
            'is_active' => 1,
        ]);
        $instanceId = (int) $db->insert('whatsapp_instances', [
            'instance_name' => "e2e_export_{$u}",
            'display_name' => "E2E Fake {$u}",
            'api_url' => "http://127.0.0.1:{$port}",
            'api_key' => 'e2e-key',
            'user_id' => $userId,
        ]);
        // Contato já salvo no CRM com o mesmo número (sem o 9º dígito) de um
        // participante: o nome do CRM deve prevalecer na planilha.
        $contactId = (int) $db->insert('whatsapp_contacts', [
            'instance_id' => $instanceId,
            'remote_jid' => '551187650001@s.whatsapp.net',
            'phone' => '551187650001',
            'contact_name' => 'Cliente CRM E2E',
            'lead_email' => 'cliente.e2e@example.test',
            'is_group' => 0,
        ]);

        echo json_encode([
            'userId' => $userId, 'email' => $email, 'password' => $password,
            'instanceId' => $instanceId, 'instanceName' => "e2e_export_{$u}",
            'contactIds' => [$contactId],
        ]);
        exit(0);
    }

    if ($cmd === 'teardown') {
        $data = json_decode($argv[2] ?? '', true);
        if (!is_array($data)) throw new RuntimeException('json inválido');
        $db = Database::getInstance();
        foreach ((array) ($data['contactIds'] ?? []) as $id) {
            try { $db->delete('whatsapp_messages', 'contact_id = ?', [(int) $id]); } catch (Throwable $e) {}
            try { $db->delete('whatsapp_contacts', 'id = ?', [(int) $id]); } catch (Throwable $e) {}
        }
        if (!empty($data['instanceId'])) {
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

    if ($cmd === 'read-xlsx') {
        $zip = new ZipArchive();
        if ($zip->open($argv[2] ?? '') !== true) throw new RuntimeException('não é um xlsx válido');
        $xml = simplexml_load_string($zip->getFromName('xl/worksheets/sheet1.xml'));
        $zip->close();
        if ($xml === false) throw new RuntimeException('sheet1.xml inválido');
        $rows = [];
        foreach ($xml->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $c) $cells[] = (string) $c->is->t;
            $rows[] = $cells;
        }
        echo json_encode($rows, JSON_UNESCAPED_UNICODE);
        exit(0);
    }

    throw new RuntimeException('comando desconhecido: ' . $cmd);
} catch (Throwable $e) {
    fwrite(STDERR, 'ERRO: ' . $e->getMessage());
    exit(1);
}
