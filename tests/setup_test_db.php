<?php
/**
 * Utilitário: (re)cria o banco de teste helpdesk_on_test replicando o SCHEMA
 * (somente estrutura, sem dados) do banco local helpdesk_on.
 *
 * Ajustes aplicados SOMENTE no banco de teste:
 *  - Restaura AUTO_INCREMENT nas PKs inteiras de coluna única (a origem está
 *    sem AUTO_INCREMENT nas colunas id, herança do dump importado).
 *
 * Uso:
 *   C:\php\php.exe tests/setup_test_db.php
 *
 * Escreve o progresso em tests/setup_test_db.log (útil quando o terminal não
 * mostra o stdout de forma confiável).
 *
 * ATENÇÃO: este script APAGA e recria o helpdesk_on_test.
 */

$host = '127.0.0.1';
$port = '3306';
$user = 'root';
$pass = '';
$src  = 'helpdesk_on';
$dst  = 'helpdesk_on_test';

function logline(string $msg): void
{
    echo $msg . PHP_EOL;
}

function connect(string $host, string $port, string $user, string $pass, ?string $db = null): PDO
{
    $dsn = "mysql:host={$host};port={$port}" . ($db ? ";dbname={$db}" : "") . ";charset=utf8mb4";
    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5,
    ]);
}

try {
    $root = connect($host, $port, $user, $pass);
    logline('conectado ao servidor');
} catch (Throwable $e) {
    logline('ERRO conexao: ' . $e->getMessage());
    exit(1);
}

// Evita travar indefinidamente em locks de metadados deixados por conexões
// órfãs: mata outras conexões ao banco de teste e reduz o tempo de espera.
// Reduz o tempo de espera por locks para evitar travas longas.
try {
    $root->exec("SET SESSION lock_wait_timeout = 10");
} catch (Throwable $e) {
    // ignora se o servidor não aceitar
}

$root->exec("DROP DATABASE IF EXISTS `{$dst}`");
$root->exec("CREATE DATABASE `{$dst}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
logline("banco {$dst} recriado");

$srcPdo = connect($host, $port, $user, $pass, $src);
$dstPdo = connect($host, $port, $user, $pass, $dst);
$dstPdo->exec("SET FOREIGN_KEY_CHECKS=0");

$tables = $srcPdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM);
logline('tabelas na origem: ' . count($tables));

$count = 0;
$errors = [];

// Passo 1: criar todas as tabelas (estrutura exata da origem)
foreach ($tables as $row) {
    $table = $row[0];
    try {
        $create = $srcPdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM);
        $ddl = $create[1] ?? null;
        if ($ddl) {
            $dstPdo->exec($ddl);
            $count++;
        }
    } catch (Throwable $e) {
        $errors[] = "CREATE {$table}: " . $e->getMessage();
    }
}
logline("tabelas criadas: {$count}");

// Passo 2: restaurar AUTO_INCREMENT nas PKs inteiras de coluna única.
// Com FOREIGN_KEY_CHECKS=0, o ALTER não é bloqueado por FKs de outras tabelas.
$autoinc = 0;
foreach ($tables as $row) {
    $table = $row[0];
    try {
        $pk = $dstPdo->query("SHOW KEYS FROM `{$table}` WHERE Key_name = 'PRIMARY'")->fetchAll(PDO::FETCH_ASSOC);
        if (count($pk) !== 1) {
            continue;
        }
        $col = $pk[0]['Column_name'];
        $colInfo = $dstPdo->query("SHOW COLUMNS FROM `{$table}` LIKE " . $dstPdo->quote($col))->fetch(PDO::FETCH_ASSOC);
        if (!$colInfo) {
            continue;
        }
        if (str_contains(strtolower($colInfo['Type']), 'int')
            && !str_contains(strtolower($colInfo['Extra'] ?? ''), 'auto_increment')) {
            $dstPdo->exec("ALTER TABLE `{$table}` MODIFY `{$col}` {$colInfo['Type']} NOT NULL AUTO_INCREMENT");
            $autoinc++;
        }
    } catch (Throwable $e) {
        $errors[] = "AUTOINC {$table}: " . $e->getMessage();
    }
}
$dstPdo->exec("SET FOREIGN_KEY_CHECKS=1");
logline("AUTO_INCREMENT aplicado: {$autoinc}");

if ($errors) {
    logline('ERROS (' . count($errors) . '):');
    foreach ($errors as $er) {
        logline(' - ' . $er);
    }
    exit(1);
}
logline('OK: banco de teste pronto');
