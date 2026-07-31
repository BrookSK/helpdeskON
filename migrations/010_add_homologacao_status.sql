-- Migration 010: Adicionar status 'em_homologacao' aos tickets e planning_cards
-- O status é armazenado como VARCHAR, então não precisa alterar a coluna.
-- Apenas atualizar o ENUM se o banco usar ENUM (MySQL), ou nada se for VARCHAR.

-- Se a coluna status for ENUM, executar:
-- ALTER TABLE tickets MODIFY COLUMN status ENUM('open','in_progress','waiting_client','em_homologacao','completed','denied','archived') DEFAULT 'open';
-- ALTER TABLE planning_cards MODIFY COLUMN status ENUM('open','in_progress','waiting_client','em_homologacao','completed','denied','archived') DEFAULT 'open';

-- Se a coluna status for VARCHAR (caso atual), nenhuma alteração de schema é necessária.
-- O novo valor 'em_homologacao' será aceito automaticamente.

-- Nota: Este arquivo serve como documentação da mudança.
-- O status 'em_homologacao' representa que a demanda está em ambiente de homologação
-- aguardando testes/validação do cliente antes de ir para produção (concluído).
