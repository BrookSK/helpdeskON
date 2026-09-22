-- Migration 129: diferenciar demandas criadas via link externo (/solicitacaoexterna)
-- e preservar o nome de quem realmente solicitou.
--
-- Contexto: demandas externas são registradas com client_id = dono do PIN (a
-- conta da equipe que compartilhou o link), pois o solicitante externo não é um
-- usuário cadastrado. Até aqui, o nome do solicitante só ficava no cabeçalho da
-- descrição, então a coluna "Cliente" mostrava o nome do dono do PIN.
--
-- Estas colunas permitem marcar a demanda como externa e guardar o nome do
-- solicitante num campo próprio, para exibi-lo no lugar do "Cliente".

-- Flag: 1 = demanda criada via link externo; 0 = demanda interna comum.
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'is_external');
SET @s := IF(@c = 0,
    'ALTER TABLE tickets ADD COLUMN is_external TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''1 = demanda criada via link externo (demanda #239)''',
    'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Nome do solicitante externo (texto livre digitado no formulário externo).
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'external_requester_name');
SET @s := IF(@c = 0,
    'ALTER TABLE tickets ADD COLUMN external_requester_name VARCHAR(150) DEFAULT NULL COMMENT ''Nome de quem solicitou pela via externa (demanda #239)''',
    'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
