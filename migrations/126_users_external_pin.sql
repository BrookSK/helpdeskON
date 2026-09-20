-- Migration 126: PIN de acesso externo por usuário da equipe (demanda #239)
-- Permite que um membro da equipe tenha um PIN de 4 dígitos, usado por clientes
-- na página /solicitacaoexterna para criar demandas em nome desse usuário.
-- O PIN é único entre todos os usuários.

-- Coluna external_pin (4 dígitos numéricos como texto para preservar zeros à esquerda).
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'external_pin');
SET @s := IF(@c = 0,
    'ALTER TABLE users ADD COLUMN external_pin VARCHAR(4) DEFAULT NULL COMMENT ''PIN de 4 dígitos para acesso externo (demanda #239)''',
    'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Índice único no PIN (ignora NULLs, então vários usuários sem PIN convivem).
SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'uk_users_external_pin');
SET @s := IF(@i = 0,
    'ALTER TABLE users ADD UNIQUE KEY uk_users_external_pin (external_pin)',
    'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
