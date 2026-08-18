-- Migration 058: Sistema de comissões dual (prospecção vs fechamento)
-- - commission_prospection_percent: % quando o profissional trouxe o lead mas OUTRA pessoa fechou
-- - commission_closing_percent: % quando o profissional trouxe o lead E ele mesmo fechou
-- - closed_by: quem efetivamente fechou o negócio (na agenda e no CRM)

USE helpdesk_on;

-- Novos campos de comissão no usuário
ALTER TABLE users
    ADD COLUMN commission_prospection_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00
        COMMENT 'Comissão (%) quando trouxe o lead mas outro fechou' AFTER commission_percent,
    ADD COLUMN commission_closing_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00
        COMMENT 'Comissão (%) quando trouxe o lead E fechou ele mesmo' AFTER commission_prospection_percent;

-- Migrar o valor antigo de commission_percent para commission_closing_percent (preservar dados)
UPDATE users SET commission_closing_percent = commission_percent WHERE commission_percent > 0;

-- Campo closed_by na agenda (quem fechou a reunião convertida)
ALTER TABLE agenda_meetings
    ADD COLUMN closed_by INT NULL COMMENT 'Quem efetivamente fechou o negócio' AFTER assigned_to,
    ADD FOREIGN KEY (closed_by) REFERENCES users(id) ON DELETE SET NULL;

-- Campo closed_by nos cards do CRM (quem fechou; complementa converted_by que já existe)
-- O converted_by já existe e indica quem marcou como convertido no sistema.
-- Vamos adicionar um campo prospected_by para saber quem trouxe o lead originalmente.
ALTER TABLE crm_cards
    ADD COLUMN prospected_by INT NULL COMMENT 'Quem prospectou/trouxe o lead originalmente' AFTER converted_by,
    ADD FOREIGN KEY (prospected_by) REFERENCES users(id) ON DELETE SET NULL;
