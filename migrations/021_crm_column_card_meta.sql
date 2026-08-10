-- Migration 021: Etiqueta e status opcionais em colunas e cards do CRM
-- Execute manualmente no MySQL

USE helpdesk_on;

-- Colunas do CRM podem ter etiqueta e status vinculados
ALTER TABLE crm_columns ADD COLUMN label_id INT NULL AFTER color;
ALTER TABLE crm_columns ADD COLUMN status ENUM('novo','em_atendimento','aguardando','concluido','perdido') NULL AFTER label_id;
ALTER TABLE crm_columns ADD FOREIGN KEY (label_id) REFERENCES whatsapp_labels(id) ON DELETE SET NULL;

-- Cards do CRM podem ter etiqueta e status vinculados
ALTER TABLE crm_cards ADD COLUMN label_id INT NULL AFTER value;
ALTER TABLE crm_cards ADD COLUMN status ENUM('novo','em_atendimento','aguardando','concluido','perdido') NULL AFTER label_id;
ALTER TABLE crm_cards ADD FOREIGN KEY (label_id) REFERENCES whatsapp_labels(id) ON DELETE SET NULL;
