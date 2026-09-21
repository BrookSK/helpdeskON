-- Migration 127: Cronograma do cliente no card de planejamento
-- Datas de início e fim do cronograma comunicado ao cliente. São separadas
-- de start_date/end_date (range interno de desenvolvimento) para permitir
-- divulgar ao cliente um prazo próprio, sem expor o planejamento interno.
-- Execute manualmente no MySQL.

ALTER TABLE planning_cards
    ADD COLUMN client_start_date DATE DEFAULT NULL COMMENT 'Início do cronograma exibido ao cliente' AFTER end_date,
    ADD COLUMN client_end_date DATE DEFAULT NULL COMMENT 'Fim do cronograma exibido ao cliente' AFTER client_start_date;
