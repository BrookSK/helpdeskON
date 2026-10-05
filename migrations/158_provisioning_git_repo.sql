-- Migration 158: Referências do repositório Git no provisionamento.
--
-- Complementa a tabela provisionings (migration 149) para guardar o repositório
-- criado via API LRV Cloud (POST /git/repositories):
--   lrv_repo_id → id do repositório retornado pela API (p/ conceder acesso/deploy).
--   repo_url    → URL web (html_url) do repositório, para exibição.
--
-- O clone_url retornado é guardado em git_repo (coluna já existente), pois é o
-- que alimenta a criação da aplicação (POST /applications/git).
--
-- Idempotente (padrão PREPARE/EXECUTE das migrations 151/152/157). Execute
-- manualmente no MySQL com o BANCO ALVO JÁ SELECIONADO (local: helpdesk_on;
-- beta: helpdesk_on_beta). As verificações usam DATABASE() (o banco atual), por
-- isso NÃO há "USE" fixo aqui: assim a mesma migration roda em qualquer ambiente
-- sem erro de permissão (ex.: usuário do beta não acessa 'helpdesk_on').

-- lrv_repo_id
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'provisionings' AND COLUMN_NAME = 'lrv_repo_id');
SET @s := IF(@c = 0, "ALTER TABLE provisionings ADD COLUMN lrv_repo_id VARCHAR(64) NULL COMMENT 'ID do repositorio Git criado via API LRV Cloud' AFTER lrv_client_id", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- repo_url
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'provisionings' AND COLUMN_NAME = 'repo_url');
SET @s := IF(@c = 0, "ALTER TABLE provisionings ADD COLUMN repo_url VARCHAR(300) NULL COMMENT 'URL web (html_url) do repositorio Git' AFTER git_repo", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
