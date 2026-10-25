<?php
/**
 * Dados do E2E do fluxo aceite até atendimento (change 02) no banco LOCAL.
 * Cada comando imprime JSON no stdout.
 *
 *   php tests-e2e/commercial-flow-fixture.php setup
 *   php tests-e2e/commercial-flow-fixture.php set-status <proposalId> <status>
 *   php tests-e2e/commercial-flow-fixture.php proposal <proposalId>
 *   php tests-e2e/commercial-flow-fixture.php approve-contract <contractId>
 *   php tests-e2e/commercial-flow-fixture.php bare-contract
 *   php tests-e2e/commercial-flow-fixture.php contract <contractId>
 *   php tests-e2e/commercial-flow-fixture.php finance <financeProjectId>
 *   php tests-e2e/commercial-flow-fixture.php asaas-pay <chargeId>
 *   php tests-e2e/commercial-flow-fixture.php credentials <onboardingId>
 *   php tests-e2e/commercial-flow-fixture.php portal
 *   php tests-e2e/commercial-flow-fixture.php cleanup
 *
 * O token do sandbox do Asaas vem do .env do repositório principal e vai só
 * para finance_accounts do banco local. Nunca é impresso. O estado (ids
 * criados e configurações originais) fica em tests-e2e/.artifacts e sai no
 * cleanup, junto com tudo o que foi criado.
 */

$_SERVER['HTTP_HOST'] = '127.0.0.1';
define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('PUBLIC_PATH', BASE_PATH . '/public');
date_default_timezone_set('America/Sao_Paulo');
spl_autoload_register(function ($class) {
    foreach (['core', 'controllers', 'models'] as $d) {
        $f = APP_PATH . "/{$d}/{$class}.php";
        if (is_file($f)) { require_once $f; return; }
    }
});

$cfg = require BASE_PATH . '/config/database.php';
if (($cfg['host'] ?? '') !== '127.0.0.1' || ($cfg['database'] ?? '') !== 'helpdesk_on') {
    fwrite(STDERR, "fixture só roda no banco local\n");
    exit(1);
}

$db = Database::getInstance();
$cmd = $argv[1] ?? '';
$arg = $argv[2] ?? '';
const STATE_FILE = BASE_PATH . '/tests-e2e/.artifacts/commercial-flow-state.json';

function out($data): void { echo json_encode($data, JSON_UNESCAPED_UNICODE); }
function state(): array { return is_file(STATE_FILE) ? (json_decode((string)file_get_contents(STATE_FILE), true) ?: []) : []; }
function saveState(array $s): void
{
    if (!is_dir(dirname(STATE_FILE))) mkdir(dirname(STATE_FILE), 0777, true);
    file_put_contents(STATE_FILE, json_encode($s));
}

/** CPF válido aleatório (dígitos verificadores certos), aceito pelo sandbox. */
function randomCpf(): string
{
    $n = [];
    for ($i = 0; $i < 9; $i++) $n[] = random_int(0, 9);
    if (count(array_unique($n)) === 1) $n[0] = ($n[0] + 1) % 10;
    foreach ([10, 11] as $w) {
        $sum = 0;
        foreach ($n as $i => $d) $sum += $d * ($w - $i);
        $r = ($sum * 10) % 11;
        $n[] = $r === 10 ? 0 : $r;
    }
    return implode('', $n);
}

/** Token do sandbox lido do .env (worktree ou repositório principal). Nunca sai do processo. */
function sandboxToken(): string
{
    foreach ([BASE_PATH . '/.env', dirname(BASE_PATH, 3) . '/.env'] as $file) {
        if (!is_file($file)) continue;
        foreach (file($file, FILE_IGNORE_NEW_LINES) as $line) {
            if (preg_match('/^\s*ASAAS_SANDBOX_TOKEN\s*=\s*(.*)$/', $line, $m)) {
                $v = trim($m[1]);
                if (strlen($v) >= 2 && ($v[0] === "'" || $v[0] === '"') && substr($v, -1) === $v[0]) $v = substr($v, 1, -1);
                if ($v !== '') return $v;
            }
        }
    }
    return '';
}

function asaas(string $token, string $method, string $path, ?array $data = null): array
{
    $ch = curl_init(AsaasRules::baseUrl(true) . '/' . ltrim($path, '/'));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => AsaasRules::headers($token),
    ]);
    if ($data !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $raw = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    return ['http' => $http, 'body' => json_decode((string)$raw, true) ?: []];
}

function newCompany(Database $db, array &$s, string $name, array $over = []): int
{
    $id = (int)$db->insert('companies', array_merge(['name' => $name], $over));
    $s['companies'][] = $id;
    return $id;
}

function newUser(Database $db, array &$s, string $role, array $over = []): array
{
    $email = 'e2e_parte2_' . $role . '_' . uniqid() . '@example.test';
    $password = 'E2e-' . bin2hex(random_bytes(6));
    $id = (int)$db->insert('users', array_merge([
        'name' => 'E2E ' . $role, 'email' => $email, 'password' => password_hash($password, PASSWORD_BCRYPT),
        'role' => $role, 'is_active' => 1,
    ], $over));
    $s['users'][] = $id;
    return ['id' => $id, 'email' => $email, 'password' => $password];
}

/** PDF mínimo para o contrato assinado do portal. */
function storeSignedPdf(Database $db, int $contractId): void
{
    $dir = BASE_PATH . '/storage/contracts/' . $contractId;
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    file_put_contents($dir . '/assinado-e2e.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n");
    $db->update('contracts', ['signed_file_path' => 'contracts/' . $contractId . '/assinado-e2e.pdf', 'signed_file_status' => 'stored', 'signed_via' => 'upload'], 'id = ?', [$contractId]);
}

switch ($cmd) {
    case 'setup':
        $s = ['companies' => [], 'users' => [], 'accounts' => [], 'settings' => []];
        $staff = newUser($db, $s, 'super_admin');
        $s['staff'] = $staff['id'];

        // Configurações do fluxo: guarda o valor original para restaurar.
        foreach (['credentials_key' => bin2hex(random_bytes(32)), 'asaas_webhook_token' => 'e2e-' . bin2hex(random_bytes(12))] as $k => $v) {
            $row = $db->fetch('SELECT setting_value FROM settings WHERE setting_key = ?', [$k]);
            $s['settings'][$k] = $row ? $row['setting_value'] : null;
            if (!$row || trim((string)$row['setting_value']) === '' || $k === 'asaas_webhook_token') Config::set($k, $v);
        }

        $token = sandboxToken();
        if ($token === '') { fwrite(STDERR, "ASAAS_SANDBOX_TOKEN ausente no .env\n"); exit(1); }
        $s['accounts'][] = (int)$db->insert('finance_accounts', [
            'name' => 'E2E Sandbox', 'purpose' => 'parcela', 'asaas_token' => $token, 'sandbox' => 1, 'active' => 1, 'created_by' => $staff['id'],
        ]);

        $u = uniqid();
        $pid = (int)$db->insert('proposals', [
            'public_token' => bin2hex(random_bytes(16)), 'title' => "Loja E2E {$u}", 'client_name' => 'Cliente E2E',
            'client_email' => "cliente_{$u}@example.test", 'status' => 'sent', 'contract_type' => 'dev_suporte',
            'validity_date' => date('Y-m-d', strtotime('+15 days')), 'total' => 8400, 'created_by' => $staff['id'], 'revision_no' => 1,
        ]);
        $db->update('proposals', ['root_proposal_id' => $pid], 'id = ?', [$pid]);
        foreach ([['Loja virtual', 8000, 'once'], ['Suporte mensal', 400, 'monthly']] as $i => [$d, $a, $b]) {
            $db->insert('proposal_items', ['proposal_id' => $pid, 'description' => $d, 'amount' => $a, 'billing' => $b, 'position' => $i]);
        }
        $s['root'] = $pid;
        saveState($s);
        out(['user' => $staff, 'proposal' => ['id' => $pid, 'token' => $db->fetch('SELECT public_token FROM proposals WHERE id = ?', [$pid])['public_token']],
            'webhookToken' => Config::get('asaas_webhook_token'), 'cpf' => randomCpf()]);
        break;

    case 'set-status':
        $db->update('proposals', ['status' => $argv[3] ?? 'sent'], 'id = ?', [(int)$arg]);
        out(['ok' => true]);
        break;

    case 'proposal':
        $p = $db->fetch('SELECT id, status, revision_no, root_proposal_id, superseded_by, public_token, accepted_plan, accepted_name, accepted_ip, accepted_user_agent, accepted_revision, responded_at FROM proposals WHERE id = ?', [(int)$arg]);
        $p['revisions'] = (int)$db->fetch('SELECT COUNT(*) n FROM proposals WHERE root_proposal_id = ?', [(int)$p['root_proposal_id']])['n'];
        out($p);
        break;

    case 'approve-contract':
        $db->update('contracts', ['status' => 'approved'], 'id = ?', [(int)$arg]);
        out(['ok' => true]);
        break;

    case 'bare-contract':
        $s = state();
        $id = (int)$db->insert('contracts', [
            'public_token' => bin2hex(random_bytes(16)), 'title' => 'Contrato E2E sem documento', 'client_name' => 'Cliente E2E',
            'client_email' => 'cliente-e2e@example.test', 'status' => 'approved', 'created_by' => $s['staff'],
        ]);
        out(['id' => $id]);
        break;

    case 'contract':
        $c = $db->fetch('SELECT id, status, client_document, signed_via, signed_file_status, signed_file_path FROM contracts WHERE id = ?', [(int)$arg]);
        $c['file_exists'] = !empty($c['signed_file_path']) && is_file(BASE_PATH . '/storage/' . $c['signed_file_path']);
        $c['finance'] = array_map('intval', array_column($db->fetchAll('SELECT id FROM finance_projects WHERE contract_id = ?', [(int)$arg]), 'id'));
        out($c);
        break;

    case 'finance':
        out([
            'charges' => $db->fetchAll('SELECT id, kind, amount, due_date, status, method, account_id, asaas_charge_id, invoice_url, issue_error FROM finance_charges WHERE project_id = ? ORDER BY id', [(int)$arg]),
            'onboardings' => array_map('intval', array_column($db->fetchAll('SELECT id FROM onboardings WHERE project_id = ?', [(int)$arg]), 'id')),
        ]);
        break;

    case 'asaas-pay':
        // Simula o pagamento no sandbox e devolve o payload real do Asaas no
        // formato do webhook, para o teste postar no endpoint local.
        $c = $db->fetch('SELECT c.asaas_charge_id, c.amount, a.asaas_token FROM finance_charges c JOIN finance_accounts a ON a.id = c.account_id WHERE c.id = ?', [(int)$arg]);
        $token = (string)($c['asaas_token'] ?? '');
        $payId = (string)($c['asaas_charge_id'] ?? '');
        if ($token === '' || $payId === '') { fwrite(STDERR, "cobrança sem emissão\n"); exit(1); }
        $paid = asaas($token, 'POST', "payments/{$payId}/receiveInCash", ['paymentDate' => date('Y-m-d'), 'value' => (float)$c['amount'], 'notifyCustomer' => false]);
        $payment = asaas($token, 'GET', "payments/{$payId}");
        out(['paid_http' => $paid['http'], 'payload' => ['event' => 'PAYMENT_RECEIVED', 'payment' => $payment['body']]]);
        break;

    case 'credentials':
        $rows = $db->fetchAll('SELECT id, service_label, username, url, secret_encrypted FROM client_credentials WHERE onboarding_id = ?', [(int)$arg]);
        out(array_map(function ($r) {
            $plain = (new ClientCredential())->revealSecret((int)$r['id']);
            return ['service_label' => $r['service_label'], 'username' => $r['username'], 'url' => $r['url'],
                'stored_plain' => strpos((string)$r['secret_encrypted'], 'E2E-segredo') !== false, 'decrypted' => $plain];
        }, $rows));
        break;

    case 'portal':
        $s = state();
        $u = uniqid();
        $a = newCompany($db, $s, "E2E Empresa A {$u}");
        $b = newCompany($db, $s, "E2E Empresa B {$u}");
        $client = newUser($db, $s, 'client', ['company_id' => $a]);
        $contracts = [];
        foreach (['a' => $a, 'b' => $b] as $k => $cid) {
            $id = (int)$db->insert('contracts', [
                'public_token' => bin2hex(random_bytes(16)), 'title' => "Contrato E2E {$k} {$u}", 'client_name' => 'Cliente', 'client_email' => 'c@example.test',
                'status' => 'signed', 'signed_at' => date('Y-m-d H:i:s'), 'company_id' => $cid, 'created_by' => $s['staff'],
            ]);
            storeSignedPdf($db, $id);
            $contracts[$k] = $id;
            $fp = (int)$db->insert('finance_projects', ['title' => "Financeiro — E2E {$k}", 'total_value' => 1000, 'status' => 'open', 'company_id' => $cid, 'contract_id' => $id, 'created_by' => $s['staff']]);
            $db->insert('finance_charges', ['project_id' => $fp, 'kind' => 'entry', 'description' => "Entrada E2E {$k}", 'amount' => 500, 'due_date' => date('Y-m-d', strtotime('+5 days')),
                'status' => 'pending', 'invoice_url' => 'https://sandbox.asaas.com/i/e2e' . $k]);
        }
        $project = (int)$db->insert('projects', [
            'company_id' => $a, 'name' => "Projeto E2E vencido {$u}", 'contract_type' => 'zero', 'status' => 'warranty', 'warranty_days' => 90,
            'warranty_ends_at' => date('Y-m-d H:i:s', strtotime('-2 days')), 'support_contract' => 0, 'created_by' => $s['staff'],
        ]);
        saveState($s);
        out(['client' => $client, 'companyA' => $a, 'companyB' => $b, 'contractA' => $contracts['a'], 'contractB' => $contracts['b'], 'project' => $project]);
        break;

    case 'cleanup':
        $s = state();
        if (!$s) { out(['ok' => true, 'empty' => true]); break; }
        $users = array_map('intval', $s['users'] ?? []);
        $companies = array_map('intval', $s['companies'] ?? []);
        $in = fn(array $ids) => $ids ? implode(',', array_map('intval', $ids)) : '0';
        $ids = fn(string $sql) => array_map('intval', array_column($db->fetchAll($sql), 'id'));

        $proposals = $ids("SELECT id FROM proposals WHERE created_by IN ({$in($users)}) OR root_proposal_id = " . (int)($s['root'] ?? 0));
        $contracts = $ids("SELECT id FROM contracts WHERE created_by IN ({$in($users)}) OR proposal_id IN ({$in($proposals)}) OR company_id IN ({$in($companies)})");
        $finance = $ids("SELECT id FROM finance_projects WHERE created_by IN ({$in($users)}) OR contract_id IN ({$in($contracts)}) OR company_id IN ({$in($companies)})");
        $onbs = $ids("SELECT id FROM onboardings WHERE project_id IN ({$in($finance)}) OR contract_id IN ({$in($contracts)})");
        $projects = $ids("SELECT id FROM projects WHERE onboarding_id IN ({$in($onbs)}) OR company_id IN ({$in($companies)}) OR created_by IN ({$in($users)})");
        $tickets = $ids("SELECT id FROM tickets WHERE project_id IN ({$in($projects)}) OR client_id IN ({$in($users)})");

        $db->query('SET FOREIGN_KEY_CHECKS=0');
        $del = function (string $sql) use ($db) { try { $db->query($sql); } catch (\Throwable $e) {} };
        foreach (['ticket_messages', 'ticket_attachments', 'ticket_history'] as $t) $del("DELETE FROM {$t} WHERE ticket_id IN ({$in($tickets)})");
        $del("DELETE FROM tickets WHERE id IN ({$in($tickets)})");
        $del("DELETE FROM project_events WHERE project_id IN ({$in($projects)})");
        $del("DELETE FROM projects WHERE id IN ({$in($projects)})");
        foreach (['onboarding_events', 'onboarding_steps', 'onboarding_credential_links', 'client_credentials', 'client_contacts', 'provisionings', 'lead_company_links'] as $t) {
            $del("DELETE FROM {$t} WHERE onboarding_id IN ({$in($onbs)})");
        }
        $del("DELETE FROM onboardings WHERE id IN ({$in($onbs)})");
        $del("DELETE FROM finance_events WHERE project_id IN ({$in($finance)})");
        $del("DELETE FROM finance_charges WHERE project_id IN ({$in($finance)})");
        $del("DELETE FROM finance_projects WHERE id IN ({$in($finance)})");
        $del("DELETE FROM contract_events WHERE contract_id IN ({$in($contracts)})");
        $del("DELETE FROM contracts WHERE id IN ({$in($contracts)})");
        foreach ($contracts as $c) {
            foreach (glob(BASE_PATH . '/storage/contracts/' . $c . '/*') ?: [] as $f) @unlink($f);
            @rmdir(BASE_PATH . '/storage/contracts/' . $c);
        }
        foreach (['proposal_items', 'proposal_events'] as $t) $del("DELETE FROM {$t} WHERE proposal_id IN ({$in($proposals)})");
        $del("DELETE FROM proposals WHERE id IN ({$in($proposals)})");
        $del("DELETE FROM finance_accounts WHERE id IN ({$in($s['accounts'] ?? [])})");
        $del("DELETE FROM client_credentials WHERE company_id IN ({$in($companies)})");
        $del("DELETE FROM user_company_access WHERE company_id IN ({$in($companies)}) OR user_id IN ({$in($users)})");
        $del("DELETE FROM notifications WHERE user_id IN ({$in($users)})");
        $del("DELETE FROM user_activity_log WHERE user_id IN ({$in($users)})");
        $del("DELETE FROM users WHERE id IN ({$in($users)})");
        $del("DELETE FROM companies WHERE id IN ({$in($companies)})");
        $db->query('SET FOREIGN_KEY_CHECKS=1');

        foreach ($s['settings'] ?? [] as $k => $orig) {
            if ($orig === null) $db->delete('settings', 'setting_key = ?', [$k]);
            else $db->update('settings', ['setting_value' => $orig], 'setting_key = ?', [$k]);
        }
        @unlink(STATE_FILE);
        out(['ok' => true, 'proposals' => count($proposals), 'contracts' => count($contracts), 'finance' => count($finance), 'onboardings' => count($onbs), 'projects' => count($projects)]);
        break;

    default:
        fwrite(STDERR, "comando desconhecido\n");
        exit(1);
}
