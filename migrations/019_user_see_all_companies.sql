-- Migration 019: Opção "sempre ver todas as empresas" para usuários da equipe
-- Quando marcada, o usuário vê todas as empresas (inclusive as criadas no futuro),
-- sem depender da seleção manual em user_company_access.
-- Execute manualmente no MySQL

USE helpdesk_on;

ALTER TABLE users ADD COLUMN see_all_companies TINYINT(1) NOT NULL DEFAULT 0 AFTER is_company_owner;
