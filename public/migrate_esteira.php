<?php
/**
 * Aplicador das migrations da esteira comercial + reuniões (142–154).
 *
 * Uso ÚNICO: aplicar as tabelas/colunas novas num banco que ainda não as tem
 * (ex.: beta/produção). Todas as migrations são idempotentes (CREATE TABLE IF
 * NOT EXISTS / ADD COLUMN com checagem), então rodar mais de uma vez é seguro.
 *
 * Proteção: exige ?token= igual ao Settings 'cron_token' (ou à constante abaixo
 * se o cron_token não estiver definido). Sem token válido, não executa.
 *
 * Como usar (no navegador ou curl), no ambiente alvo:
 *   https://SEU_DOMINIO/migrate_esteira.php?token=SEU_CRON_TOKEN
 *
 * Depois de confirmar que rodou (tudo "OK"), REMOVA este arquivo do servidor.
 */

header('Content-Type: text/plain; charset=utf-8');

date_default_timezone_set('America/Sao_Paulo');
define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');

require_once APP_PATH . '/core/Database.php';
require_once APP_PATH . '/core/Config.php';

// ── Proteção por token ───────────────────────────────────────────────────────
$expected = '';
try { $expected = (string) Config::get('cron_token'); } catch (\Throwable $e) { $expected = ''; }
$got = $_GET['token'] ?? '';
if ($expected === '' || !hash_equals($expected, (string) $got)) {
    http_response_code(403);
    echo "Acesso negado. Informe ?token= igual ao 'cron_token' das Configuracoes.\n";
    echo "Dica: defina um cron_token em Configuracoes antes de rodar este script.\n";
    exit;
}

// ── Migrations a aplicar, em ordem ───────────────────────────────────────────
$migrations = [
    '142_video_rooms_auto_record.sql',
    '143_video_recordings_minutes.sql',
    '144_video_recordings_minutes_sent.sql',
    '145_commercial_proposals.sql',
    '146_commercial_contracts.sql',
    '147_commercial_finance.sql',
    '148_commercial_onboarding.sql',
    '149_commercial_provisioning.sql',
    '150_providers.sql',
    '151_commercial_projects_pins.sql',
    '152_ticket_scope_homologacao.sql',
    '153_ticket_support_fields.sql',
    '154_ticket_relations.sql',
];

$db = Database::getInstance();
$pdo = $db->getConnection();
$cfg = require BASE_PATH . '/config/database.php';
echo "Banco em uso: " . ($cfg['database'] ?? '?') . "\n";
echo "========================================\n";

$totalErros = 0;
foreach ($migrations as $file) {
    $path = BASE_PATH . '/migrations/' . $file;
    if (!is_file($path)) { echo "[PULADA] {$file} (arquivo nao encontrado)\n"; continue; }

    $sql = file_get_contents($path);
    // Remove linhas de comentario e divide por ';' (as migrations nao usam ';'
    // dentro de literais apos a correcao da 149).
    $lines = preg_split('/\r?\n/', $sql);
    $clean = [];
    foreach ($lines as $l) { if (str_starts_with(ltrim($l), '--')) continue; $clean[] = $l; }
    $statements = array_filter(array_map('trim', explode(';', implode("\n", $clean))), fn($s) => $s !== '');

    $erros = 0;
    foreach ($statements as $st) {
        try {
            // exec() (não prepare) — necessário para DDL e para blocos
            // SET @.../PREPARE/EXECUTE/DEALLOCATE usados nas migrations idempotentes.
            $pdo->exec($st);
        } catch (\Throwable $e) {
            // "already exists" / "Duplicate column" sao esperados em re-execucao.
            $msg = $e->getMessage();
            if (stripos($msg, 'already exists') !== false || stripos($msg, 'Duplicate column') !== false || stripos($msg, 'Duplicate key name') !== false) {
                continue;
            }
            $erros++;
            echo "  [ERRO] {$file}: {$msg}\n";
        }
    }
    $totalErros += $erros;
    echo ($erros === 0 ? "[OK] " : "[COM ERROS] ") . $file . "\n";
}

echo "========================================\n";
echo $totalErros === 0 ? "CONCLUIDO SEM ERROS.\n" : "CONCLUIDO COM {$totalErros} erro(s) — veja acima.\n";
echo "IMPORTANTE: remova este arquivo (public/migrate_esteira.php) do servidor depois.\n";
