-- Migration 012: Adicionar status 'aprovado_producao' aos tickets e planning_cards
-- A coluna status é ENUM no MySQL, então PRECISA de ALTER TABLE para aceitar o novo valor.

-- Atualizar ENUM da tabela tickets
ALTER TABLE tickets MODIFY COLUMN status ENUM('open', 'in_progress', 'em_revisao_interna', 'waiting_client', 'em_homologacao', 'aprovado_producao', 'completed', 'denied', 'archived') NOT NULL DEFAULT 'open';

-- Atualizar ENUM da tabela planning_cards
ALTER TABLE planning_cards MODIFY COLUMN status ENUM('open', 'in_progress', 'em_revisao_interna', 'waiting_client', 'em_homologacao', 'aprovado_producao', 'completed', 'denied', 'archived') NOT NULL DEFAULT 'open';

-- Nota sobre o fluxo:
-- open -> in_progress -> em_revisao_interna -> em_homologacao -> aprovado_producao -> completed
-- O status 'aprovado_producao' indica que o cliente testou em homologação, aprovou,
-- e agora está pronto para montar o pacote e subir para produção.
