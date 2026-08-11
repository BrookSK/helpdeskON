-- Migration 032: Campos de referência ao CX Hub no card de planejamento
-- Permite vincular demanda CX Hub, branch e PR ao card.

ALTER TABLE planning_cards
    ADD COLUMN cx_hub_number VARCHAR(50) DEFAULT NULL COMMENT 'Número da demanda no CX Hub',
    ADD COLUMN cx_hub_name VARCHAR(255) DEFAULT NULL COMMENT 'Nome/título da demanda no CX Hub',
    ADD COLUMN branch_name VARCHAR(255) DEFAULT NULL COMMENT 'Nome da branch de desenvolvimento',
    ADD COLUMN pr_number VARCHAR(50) DEFAULT NULL COMMENT 'Número do Pull Request';
