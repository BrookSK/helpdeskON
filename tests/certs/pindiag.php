<?php
// SOMENTE LEITURA. Diagnostica por que o login por PIN do cliente falha.
// Mostra o client_pin salvo e simula a mesma consulta do login (findByClientPin).
$host='127.0.0.1'; $port='3306'; $user='root'; $pass=''; $db='helpdesk_on';
function out($m){ echo $m."\n"; }
$pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);

out("--- clientes com PIN (role=client) ---");
$rows = $pdo->query("SELECT id, name, role, is_active, client_pin FROM users WHERE role='client' AND client_pin IS NOT NULL ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    $pin = $r['client_pin'];
    out("id={$r['id']} ativo={$r['is_active']} pin='{$pin}' len=" . strlen($pin) . " hex=" . bin2hex($pin) . " nome=" . substr($r['name'],0,28));
}
out("total com PIN: " . count($rows));

// Simula EXATAMENTE a query do login (User::findByClientPin) para alguns PINs.
out("\n--- simulacao do login findByClientPin ---");
$testPins = $pdo->query("SELECT DISTINCT client_pin FROM users WHERE role='client' AND client_pin IS NOT NULL LIMIT 5")->fetchAll(PDO::FETCH_COLUMN);
foreach ($testPins as $tp) {
    $st = $pdo->prepare("SELECT id, name FROM users WHERE client_pin = ? AND role = 'client' AND is_active = 1 LIMIT 1");
    $st->execute([$tp]);
    $hit = $st->fetch(PDO::FETCH_ASSOC);
    out("PIN '{$tp}' -> " . ($hit ? "ACHOU id={$hit['id']} ({$hit['name']})" : "NAO ACHOU"));
}

// Verifica se há algum usuario NAO-client com o mesmo pin (poderia confundir se a query fosse ampla)
out("\n--- usuarios por papel que tem client_pin preenchido ---");
$byRole = $pdo->query("SELECT role, COUNT(*) c FROM users WHERE client_pin IS NOT NULL GROUP BY role")->fetchAll(PDO::FETCH_ASSOC);
foreach ($byRole as $b) { out("  role={$b['role']} count={$b['c']}"); }
