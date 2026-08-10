-- Migration 016: Novos papéis (Desenvolvedor/Analista), responsável técnico nos tickets
-- e suporte a link de definição de senha para primeiro acesso.
-- Execute manualmente no MySQL

USE helpdesk_on;

-- Adicionar papéis Desenvolvedor e Analista
ALTER TABLE users
MODIFY COLUMN role ENUM('super_admin', 'attendant', 'client', 'whatsapp_agent', 'developer', 'analyst') NOT NULL DEFAULT 'client';

-- Responsável técnico da demanda (quem executa/entrega tecnicamente)
ALTER TABLE tickets ADD COLUMN technical_responsible_id INT NULL AFTER attendant_id;
ALTER TABLE tickets ADD FOREIGN KEY (technical_responsible_id) REFERENCES users(id) ON DELETE SET NULL;

-- Responsável técnico também no card de planejamento (opcional, para o kanban)
ALTER TABLE planning_cards ADD COLUMN technical_responsible_id INT NULL AFTER assigned_to;
ALTER TABLE planning_cards ADD FOREIGN KEY (technical_responsible_id) REFERENCES users(id) ON DELETE SET NULL;

-- Marca tokens de reset usados para primeiro acesso (auto-login após definir senha)
ALTER TABLE password_resets ADD COLUMN is_first_access TINYINT(1) NOT NULL DEFAULT 0 AFTER token;
