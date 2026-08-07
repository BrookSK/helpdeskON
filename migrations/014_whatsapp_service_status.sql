-- Migration: Adicionar status de atendimento aos contatos do WhatsApp
ALTER TABLE whatsapp_contacts 
ADD COLUMN service_status ENUM('em_atendimento','aguardando','concluido','novo') DEFAULT 'novo' 
AFTER is_archived;
