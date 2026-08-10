-- Migration: Adicionar papel 'whatsapp_agent' ao sistema
-- Este papel tem acesso a: WhatsApp Chat, CRM, Demandas, Planejamento, Documentos, Notificações, Conta
-- Restrito às empresas vinculadas ao usuário

ALTER TABLE users 
MODIFY COLUMN role ENUM('super_admin', 'attendant', 'client', 'whatsapp_agent') NOT NULL DEFAULT 'client';
