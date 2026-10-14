-- Migration 158: tipo (kind) do token em password_resets.
-- Permite reaproveitar a mesma tabela/fluxo de token para redefinir o PIN de
-- acesso (client_pin), além da senha. 'password' é o padrão (comportamento
-- atual intacto); 'pin' indica um link de definição/redefinição de PIN.

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'password_resets' AND COLUMN_NAME = 'kind');
SET @s := IF(@c = 0,
    'ALTER TABLE password_resets ADD COLUMN kind VARCHAR(20) NOT NULL DEFAULT ''password'' AFTER is_first_access',
    'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
