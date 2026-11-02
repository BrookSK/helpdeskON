-- Migration 169: lançamento vinculado a uma pessoa da equipe.
-- user_id = usuário do sistema a quem o lançamento se refere (ex.: salário
-- do Isaac). Opcional. Excluir o usuário não apaga o lançamento, só
-- desvincula (ON DELETE SET NULL).
--
-- Se rodar duas vezes, o MySQL responde "#1060 Duplicate column name
-- 'user_id'": isso só quer dizer que já foi aplicada.

ALTER TABLE fin_transactions
    ADD COLUMN user_id INT NULL COMMENT 'Pessoa da equipe vinculada (ex.: salario)' AFTER account_id,
    ADD KEY idx_fint_user (user_id),
    ADD CONSTRAINT fk_fint_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;
