<?php
// SOMENTE LEITURA. Verifica o usuario "Lucas Campagna": papel, ativo, client_pin.
$pdo = new PDO("mysql:host=127.0.0.1;port=3306;dbname=helpdesk_on;charset=utf8mb4",'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
function out($m){ echo $m."\n"; }
$rows = $pdo->query("SELECT id, name, role, is_active, client_pin, external_pin FROM users WHERE name LIKE '%Lucas Camp%' OR name LIKE '%Campagna%' OR name LIKE '%Campanha%'")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    out("id={$r['id']} role={$r['role']} ativo={$r['is_active']} client_pin='" . ($r['client_pin']??'NULL') . "' external_pin='" . ($r['external_pin']??'NULL') . "' nome={$r['name']}");
}
out("total: " . count($rows));
