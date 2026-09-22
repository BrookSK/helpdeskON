<?php
/**
 * Runner simples de migração para o helpdeskON.
 *
 * Aplica um arquivo .sql de migrations/ usando a MESMA detecção de ambiente do
 * projeto (config/database.php). Ou seja: rodar localmente aplica no banco
 * local; rodar no servidor (beta/produção) aplica no banco daquele ambiente.
 *
 * Uso (a partir da raiz do projeto):
 *   C:\php\php.exe scripts/migrate.php 129_tickets_external_requester.sql
 *
 * Sem argumento, usa a migração 129 (a mais recente adicionada).
 *
 * As migrations do projeto são idempotentes (checam information_schema antes de
 * alterar), então rodar de novo não causa erro.
 */

// Constantes que o config/database.php e o Database esperam.
define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');

$file = $argv[1] ?? '129_tickets_external_requester.sql';
$path = BASE_PATH . '/migrations/' . $file;

if (!is_file($path)) {
    fwrite(STDERR, "Migração não encontrada: {$path}\n");
    exit(1);
}

$config = require BASE_PATH . '/config/database.php';

echo "Ambiente/banco alvo: {$config['database']} @ {$config['host']}:{$config['port']}\n";
echo "Aplicando: {$file}\n";

try {
    $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset=utf8mb4";
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, "Erro de conexão: " . $e->getMessage() . "\n");
    exit(1);
}

$sql = file_get_contents($path);

// Executa statement a statement (o arquivo usa PREPARE/EXECUTE/DEALLOCATE, que
// precisam rodar em sequência). Divide pelos ';' de fim de linha, ignorando
// linhas em branco e comentários "--".
$statements = [];
$buffer = '';
foreach (preg_split('/\r?\n/', $sql) as $line) {
    $trim = trim($line);
    if ($trim === '' || str_starts_with($trim, '--')) {
        continue;
    }
    $buffer .= $line . "\n";
    if (str_ends_with($trim, ';')) {
        $statements[] = trim($buffer);
        $buffer = '';
    }
}
if (trim($buffer) !== '') {
    $statements[] = trim($buffer);
}

$ok = 0;
foreach ($statements as $stmt) {
    try {
        $pdo->exec($stmt);
        $ok++;
    } catch (PDOException $e) {
        fwrite(STDERR, "Falha no statement:\n{$stmt}\n-> " . $e->getMessage() . "\n");
        exit(1);
    }
}

echo "OK: {$ok} statement(s) executado(s).\n";

// Verificação: lista as colunas relevantes da tabela tickets (quando aplicável).
try {
    $cols = $pdo->query(
        "SELECT COLUMN_NAME FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tickets'
           AND COLUMN_NAME IN ('is_external','external_requester_name')
         ORDER BY COLUMN_NAME"
    )->fetchAll(PDO::FETCH_COLUMN);
    if ($cols) {
        echo "Colunas presentes em tickets: " . implode(', ', $cols) . "\n";
    }
} catch (\Throwable $e) {
    // verificação é informativa; não falha a migração
}

echo "Concluído.\n";
