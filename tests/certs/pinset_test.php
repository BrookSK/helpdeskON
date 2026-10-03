<?php
// SOMENTE TESTE em memória do fluxo: simula setClientPin num id de teste e
// verifica se persiste, depois RESTAURA o valor original (não deixa lixo).
putenv('APP_ENV=local'); define('APP_ENV','local');
define('BASE_PATH', dirname(dirname(__DIR__))); define('APP_PATH', BASE_PATH.'/app');
spl_autoload_register(function($c){ foreach([APP_PATH.'/core/',APP_PATH.'/models/'] as $p){ $f=$p.$c.'.php'; if(file_exists($f)){require_once $f;return;} } });
require_once APP_PATH.'/core/helpers.php'; require BASE_PATH.'/vendor/autoload.php';
function out($m){ echo $m."\n"; }

// Usa o Database do app, mas forçando helpdesk_on (local). Config::get pode
// apontar para outro banco; aqui conectamos direto para o teste ser fiel ao local.
$pdo = new PDO("mysql:host=127.0.0.1;port=3306;dbname=helpdesk_on;charset=utf8mb4",'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);

// Escolhe um cliente de teste (id=4 'Teste Testando').
$id = 4;
$orig = $pdo->query("SELECT client_pin FROM users WHERE id=$id")->fetchColumn();
out("original client_pin do id=$id: '" . $orig . "'");

// Simula o que o controller faz: valida formato + unicidade + grava.
$novo = '9271';
out("tentando definir PIN '$novo'...");
$valid = (bool)preg_match('/^\d{4}$/', $novo);
out("formato valido (4 digitos): " . ($valid?'sim':'nao'));
$dup = $pdo->prepare("SELECT id FROM users WHERE client_pin=? AND id<>? LIMIT 1");
$dup->execute([$novo, $id]);
$dupHit = $dup->fetch(PDO::FETCH_ASSOC);
out("ja existe em outro usuario: " . ($dupHit ? ("SIM (id=".$dupHit['id'].")") : 'nao'));

if ($valid && !$dupHit) {
    $pdo->prepare("UPDATE users SET client_pin=? WHERE id=?")->execute([$novo, $id]);
    $after = $pdo->query("SELECT client_pin FROM users WHERE id=$id")->fetchColumn();
    out("apos UPDATE, client_pin='" . $after . "' (esperado '$novo')");
    // Simula login
    $lg = $pdo->prepare("SELECT id FROM users WHERE client_pin=? AND role='client' AND is_active=1 LIMIT 1");
    $lg->execute([$novo]);
    out("login com '$novo' -> " . ($lg->fetchColumn() ? 'ACHOU' : 'NAO ACHOU'));
}

// RESTAURA o valor original para nao bagunçar seus dados.
$pdo->prepare("UPDATE users SET client_pin=? WHERE id=?")->execute([$orig, $id]);
out("restaurado para '$orig'");
