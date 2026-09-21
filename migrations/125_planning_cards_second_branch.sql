-- Migration 125: Segunda branch no card de planejamento
-- Permite registrar uma segunda branch de desenvolvimento no mesmo card.

ALTER TABLE planning_cards
    ADD COLUMN branch_name_2 VARCHAR(255) DEFAULT NULL COMMENT 'Nome da segunda branch de desenvolvimento' AFTER branch_name;
