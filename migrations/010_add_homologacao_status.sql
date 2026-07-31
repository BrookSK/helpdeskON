-- Migration 010: Adicionar status 'em_revisao_interna' e 'em_homologacao' aos tickets e planning_cards
-- A coluna status é ENUM no MySQL, então PRECISA de ALTER TABLE para aceitar os novos valores.

-- Atualizar ENUM da tabela tickets
ALTER TABLE tickets MODIFY COLUMN status ENUM('open', 'in_progress', 'em_revisao_interna', 'waiting_client', 'em_homologacao', 'completed', 'denied', 'archived') NOT NULL DEFAULT 'open';

-- Atualizar ENUM da tabela planning_cards
ALTER TABLE planning_cards MODIFY COLUMN status ENUM('open', 'in_progress', 'em_revisao_interna', 'waiting_client', 'em_homologacao', 'completed', 'denied', 'archived') NOT NULL DEFAULT 'open';

-- Nota sobre o fluxo:
-- open -> in_progress -> em_revisao_interna -> em_homologacao -> completed
-- O status 'em_revisao_interna' indica que o dev terminou e o PR está aguardando revisão/aprovação interna.
-- O status 'em_homologacao' indica que passou pela revisão, foi deployado no ambiente de homologação
-- e está aguardando o cliente testar/validar antes de ir para produção (completed).
