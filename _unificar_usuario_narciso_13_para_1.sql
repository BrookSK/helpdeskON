-- =====================================================================
-- UNIFICAR USUÁRIO — Lucas Narciso
-- Passa TUDO do usuário 13 (narciso1997@gmail.com) para o usuário 1
-- (admin@onsolutionsbrasil.com.br): cards (atendente, técnico, analista,
-- criador), demandas, atendentes das demandas, comentários, anexos, tarefas,
-- RDO, agenda, reuniões/gravações, financeiro, CRM, WhatsApp, e-mail,
-- notificações, histórico, acessos a empresas etc.
-- Não mexe: o cadastro do usuário 13 (você desativa depois), histórico de
-- login e pedidos de troca de senha.
-- Vínculos que o usuário 1 já tinha (ex.: os dois eram atendentes da mesma
-- demanda) não duplicam: fica só o do usuário 1.
-- Rodar de uma vez no phpMyAdmin (produção). Em transação. Pode rodar de
-- novo sem efeito. O primeiro SELECT mostra os 2 usuários: confira antes.
-- =====================================================================
SET NAMES utf8mb4;

SET @de := 13;

SET @para := 1;

SELECT id, name, email, role, is_active FROM users WHERE id IN (@de, @para);

START TRANSACTION;

-- Demandas em que o antigo é o "cliente" (criadas por ele sem escolher cliente):
-- passam para o novo com numeração continuando a dele, para não repetir número.
SET @num := (SELECT COALESCE(MAX(client_ticket_number), 0) FROM tickets WHERE client_id = @para);

UPDATE IGNORE tickets SET client_ticket_number = (@num := @num + 1), client_id = @para WHERE client_id = @de ORDER BY id;

-- agenda_booking_links.assigned_to
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'agenda_booking_links' AND COLUMN_NAME = 'assigned_to') > 0,
  'UPDATE IGNORE `agenda_booking_links` SET `assigned_to` = 1 WHERE `assigned_to` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- agenda_meeting_participants.user_id
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'agenda_meeting_participants' AND COLUMN_NAME = 'user_id') > 0,
  'UPDATE IGNORE `agenda_meeting_participants` SET `user_id` = 1 WHERE `user_id` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'agenda_meeting_participants' AND COLUMN_NAME = 'user_id') > 0,
  'DELETE FROM `agenda_meeting_participants` WHERE `user_id` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- agenda_meetings.assigned_to
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'agenda_meetings' AND COLUMN_NAME = 'assigned_to') > 0,
  'UPDATE IGNORE `agenda_meetings` SET `assigned_to` = 1 WHERE `assigned_to` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- agenda_meetings.closed_by
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'agenda_meetings' AND COLUMN_NAME = 'closed_by') > 0,
  'UPDATE IGNORE `agenda_meetings` SET `closed_by` = 1 WHERE `closed_by` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- agenda_meetings.created_by
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'agenda_meetings' AND COLUMN_NAME = 'created_by') > 0,
  'UPDATE IGNORE `agenda_meetings` SET `created_by` = 1 WHERE `created_by` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- api_keys.integration_user_id
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'api_keys' AND COLUMN_NAME = 'integration_user_id') > 0,
  'UPDATE IGNORE `api_keys` SET `integration_user_id` = 1 WHERE `integration_user_id` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- apollo_campaigns.assigned_to
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'apollo_campaigns' AND COLUMN_NAME = 'assigned_to') > 0,
  'UPDATE IGNORE `apollo_campaigns` SET `assigned_to` = 1 WHERE `assigned_to` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- apollo_campaigns.created_by
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'apollo_campaigns' AND COLUMN_NAME = 'created_by') > 0,
  'UPDATE IGNORE `apollo_campaigns` SET `created_by` = 1 WHERE `created_by` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- apollo_credit_usage.user_id
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'apollo_credit_usage' AND COLUMN_NAME = 'user_id') > 0,
  'UPDATE IGNORE `apollo_credit_usage` SET `user_id` = 1 WHERE `user_id` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- apollo_leads.created_by
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'apollo_leads' AND COLUMN_NAME = 'created_by') > 0,
  'UPDATE IGNORE `apollo_leads` SET `created_by` = 1 WHERE `created_by` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- apollo_leads.imported_by
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'apollo_leads' AND COLUMN_NAME = 'imported_by') > 0,
  'UPDATE IGNORE `apollo_leads` SET `imported_by` = 1 WHERE `imported_by` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- apollo_leads.phone_requested_by
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'apollo_leads' AND COLUMN_NAME = 'phone_requested_by') > 0,
  'UPDATE IGNORE `apollo_leads` SET `phone_requested_by` = 1 WHERE `phone_requested_by` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- buffer_posts.created_by
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'buffer_posts' AND COLUMN_NAME = 'created_by') > 0,
  'UPDATE IGNORE `buffer_posts` SET `created_by` = 1 WHERE `created_by` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- client_credentials.created_by
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'client_credentials' AND COLUMN_NAME = 'created_by') > 0,
  'UPDATE IGNORE `client_credentials` SET `created_by` = 1 WHERE `created_by` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- commercial_briefings.created_by
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commercial_briefings' AND COLUMN_NAME = 'created_by') > 0,
  'UPDATE IGNORE `commercial_briefings` SET `created_by` = 1 WHERE `created_by` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- contract_events.user_id
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contract_events' AND COLUMN_NAME = 'user_id') > 0,
  'UPDATE IGNORE `contract_events` SET `user_id` = 1 WHERE `user_id` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- contract_signers.created_by
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contract_signers' AND COLUMN_NAME = 'created_by') > 0,
  'UPDATE IGNORE `contract_signers` SET `created_by` = 1 WHERE `created_by` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- contract_templates.created_by
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contract_templates' AND COLUMN_NAME = 'created_by') > 0,
  'UPDATE IGNORE `contract_templates` SET `created_by` = 1 WHERE `created_by` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- contracts.created_by
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contracts' AND COLUMN_NAME = 'created_by') > 0,
  'UPDATE IGNORE `contracts` SET `created_by` = 1 WHERE `created_by` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- crm_boards.created_by
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'crm_boards' AND COLUMN_NAME = 'created_by') > 0,
  'UPDATE IGNORE `crm_boards` SET `created_by` = 1 WHERE `created_by` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- crm_card_activities.user_id
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'crm_card_activities' AND COLUMN_NAME = 'user_id') > 0,
  'UPDATE IGNORE `crm_card_activities` SET `user_id` = 1 WHERE `user_id` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- crm_cards.assigned_to
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'crm_cards' AND COLUMN_NAME = 'assigned_to') > 0,
  'UPDATE IGNORE `crm_cards` SET `assigned_to` = 1 WHERE `assigned_to` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- crm_cards.converted_by
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'crm_cards' AND COLUMN_NAME = 'converted_by') > 0,
  'UPDATE IGNORE `crm_cards` SET `converted_by` = 1 WHERE `converted_by` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- crm_cards.created_by
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'crm_cards' AND COLUMN_NAME = 'created_by') > 0,
  'UPDATE IGNORE `crm_cards` SET `created_by` = 1 WHERE `created_by` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- crm_cards.prospected_by
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'crm_cards' AND COLUMN_NAME = 'prospected_by') > 0,
  'UPDATE IGNORE `crm_cards` SET `prospected_by` = 1 WHERE `prospected_by` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- daily_report_attachments.user_id
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'daily_report_attachments' AND COLUMN_NAME = 'user_id') > 0,
  'UPDATE IGNORE `daily_report_attachments` SET `user_id` = 1 WHERE `user_id` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- daily_report_history.changed_by
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'daily_report_history' AND COLUMN_NAME = 'changed_by') > 0,
  'UPDATE IGNORE `daily_report_history` SET `changed_by` = 1 WHERE `changed_by` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- daily_report_missing_notifications.user_id
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'daily_report_missing_notifications' AND COLUMN_NAME = 'user_id') > 0,
  'UPDATE IGNORE `daily_report_missing_notifications` SET `user_id` = 1 WHERE `user_id` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- daily_report_reviews.requested_by
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'daily_report_reviews' AND COLUMN_NAME = 'requested_by') > 0,
  'UPDATE IGNORE `daily_report_reviews` SET `requested_by` = 1 WHERE `requested_by` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- daily_report_reviews.reviewed_by
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'daily_report_reviews' AND COLUMN_NAME = 'reviewed_by') > 0,
  'UPDATE IGNORE `daily_report_reviews` SET `reviewed_by` = 1 WHERE `reviewed_by` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- daily_reports.user_id
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'daily_reports' AND COLUMN_NAME = 'user_id') > 0,
  'UPDATE IGNORE `daily_reports` SET `user_id` = 1 WHERE `user_id` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- email_account_users.user_id
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'email_account_users' AND COLUMN_NAME = 'user_id') > 0,
  'UPDATE IGNORE `email_account_users` SET `user_id` = 1 WHERE `user_id` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'email_account_users' AND COLUMN_NAME = 'user_id') > 0,
  'DELETE FROM `email_account_users` WHERE `user_id` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- email_accounts.created_by
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'email_accounts' AND COLUMN_NAME = 'created_by') > 0,
  'UPDATE IGNORE `email_accounts` SET `created_by` = 1 WHERE `created_by` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- email_messages.sent_by
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'email_messages' AND COLUMN_NAME = 'sent_by') > 0,
  'UPDATE IGNORE `email_messages` SET `sent_by` = 1 WHERE `sent_by` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- email_prospections.user_id
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'email_prospections' AND COLUMN_NAME = 'user_id') > 0,
  'UPDATE IGNORE `email_prospections` SET `user_id` = 1 WHERE `user_id` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- email_sequences.created_by
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'email_sequences' AND COLUMN_NAME = 'created_by') > 0,
  'UPDATE IGNORE `email_sequences` SET `created_by` = 1 WHERE `created_by` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- fin_bank_accounts.created_by
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fin_bank_accounts' AND COLUMN_NAME = 'created_by') > 0,
  'UPDATE IGNORE `fin_bank_accounts` SET `created_by` = 1 WHERE `created_by` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- fin_categories.created_by
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fin_categories' AND COLUMN_NAME = 'created_by') > 0,
  'UPDATE IGNORE `fin_categories` SET `created_by` = 1 WHERE `created_by` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- fin_commissions.created_by
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fin_commissions' AND COLUMN_NAME = 'created_by') > 0,
  'UPDATE IGNORE `fin_commissions` SET `created_by` = 1 WHERE `created_by` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- fin_commissions.user_id
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fin_commissions' AND COLUMN_NAME = 'user_id') > 0,
  'UPDATE IGNORE `fin_commissions` SET `user_id` = 1 WHERE `user_id` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- fin_credit_cards.created_by
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fin_credit_cards' AND COLUMN_NAME = 'created_by') > 0,
  'UPDATE IGNORE `fin_credit_cards` SET `created_by` = 1 WHERE `created_by` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- fin_transactions.created_by
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fin_transactions' AND COLUMN_NAME = 'created_by') > 0,
  'UPDATE IGNORE `fin_transactions` SET `created_by` = 1 WHERE `created_by` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- fin_transactions.user_id
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fin_transactions' AND COLUMN_NAME = 'user_id') > 0,
  'UPDATE IGNORE `fin_transactions` SET `user_id` = 1 WHERE `user_id` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- finance_accounts.created_by
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'finance_accounts' AND COLUMN_NAME = 'created_by') > 0,
  'UPDATE IGNORE `finance_accounts` SET `created_by` = 1 WHERE `created_by` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- finance_projects.created_by
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'finance_projects' AND COLUMN_NAME = 'created_by') > 0,
  'UPDATE IGNORE `finance_projects` SET `created_by` = 1 WHERE `created_by` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- lead_company_links.converted_by
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lead_company_links' AND COLUMN_NAME = 'converted_by') > 0,
  'UPDATE IGNORE `lead_company_links` SET `converted_by` = 1 WHERE `converted_by` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- lead_company_links.user_id
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lead_company_links' AND COLUMN_NAME = 'user_id') > 0,
  'UPDATE IGNORE `lead_company_links` SET `user_id` = 1 WHERE `user_id` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- lead_timeline.user_id
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lead_timeline' AND COLUMN_NAME = 'user_id') > 0,
  'UPDATE IGNORE `lead_timeline` SET `user_id` = 1 WHERE `user_id` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- linkedin_tasks.assigned_to
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'linkedin_tasks' AND COLUMN_NAME = 'assigned_to') > 0,
  'UPDATE IGNORE `linkedin_tasks` SET `assigned_to` = 1 WHERE `assigned_to` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- linkedin_tasks.sent_by
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'linkedin_tasks' AND COLUMN_NAME = 'sent_by') > 0,
  'UPDATE IGNORE `linkedin_tasks` SET `sent_by` = 1 WHERE `sent_by` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- marketing_attachments.user_id
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'marketing_attachments' AND COLUMN_NAME = 'user_id') > 0,
  'UPDATE IGNORE `marketing_attachments` SET `user_id` = 1 WHERE `user_id` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- marketing_item_history.user_id
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'marketing_item_history' AND COLUMN_NAME = 'user_id') > 0,
  'UPDATE IGNORE `marketing_item_history` SET `user_id` = 1 WHERE `user_id` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- marketing_items.approver_id
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'marketing_items' AND COLUMN_NAME = 'approver_id') > 0,
  'UPDATE IGNORE `marketing_items` SET `approver_id` = 1 WHERE `approver_id` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- marketing_items.assigned_to
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'marketing_items' AND COLUMN_NAME = 'assigned_to') > 0,
  'UPDATE IGNORE `marketing_items` SET `assigned_to` = 1 WHERE `assigned_to` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- marketing_items.created_by
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'marketing_items' AND COLUMN_NAME = 'created_by') > 0,
  'UPDATE IGNORE `marketing_items` SET `created_by` = 1 WHERE `created_by` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- message_templates.created_by
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'message_templates' AND COLUMN_NAME = 'created_by') > 0,
  'UPDATE IGNORE `message_templates` SET `created_by` = 1 WHERE `created_by` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- notifications.user_id
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications' AND COLUMN_NAME = 'user_id') > 0,
  'UPDATE IGNORE `notifications` SET `user_id` = 1 WHERE `user_id` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- nvoip_calls.user_id
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nvoip_calls' AND COLUMN_NAME = 'user_id') > 0,
  'UPDATE IGNORE `nvoip_calls` SET `user_id` = 1 WHERE `user_id` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- onboarding_events.user_id
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'onboarding_events' AND COLUMN_NAME = 'user_id') > 0,
  'UPDATE IGNORE `onboarding_events` SET `user_id` = 1 WHERE `user_id` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- onboarding_steps.responsible_id
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'onboarding_steps' AND COLUMN_NAME = 'responsible_id') > 0,
  'UPDATE IGNORE `onboarding_steps` SET `responsible_id` = 1 WHERE `responsible_id` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- onboardings.created_by
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'onboardings' AND COLUMN_NAME = 'created_by') > 0,
  'UPDATE IGNORE `onboardings` SET `created_by` = 1 WHERE `created_by` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- onboardings.tech_responsible_id
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'onboardings' AND COLUMN_NAME = 'tech_responsible_id') > 0,
  'UPDATE IGNORE `onboardings` SET `tech_responsible_id` = 1 WHERE `tech_responsible_id` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- payables.created_by
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payables' AND COLUMN_NAME = 'created_by') > 0,
  'UPDATE IGNORE `payables` SET `created_by` = 1 WHERE `created_by` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- planning_attachments.user_id
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'planning_attachments' AND COLUMN_NAME = 'user_id') > 0,
  'UPDATE IGNORE `planning_attachments` SET `user_id` = 1 WHERE `user_id` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- planning_card_history.user_id
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'planning_card_history' AND COLUMN_NAME = 'user_id') > 0,
  'UPDATE IGNORE `planning_card_history` SET `user_id` = 1 WHERE `user_id` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- planning_cards.analyst_id
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'planning_cards' AND COLUMN_NAME = 'analyst_id') > 0,
  'UPDATE IGNORE `planning_cards` SET `analyst_id` = 1 WHERE `analyst_id` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- planning_cards.assigned_to
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'planning_cards' AND COLUMN_NAME = 'assigned_to') > 0,
  'UPDATE IGNORE `planning_cards` SET `assigned_to` = 1 WHERE `assigned_to` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- planning_cards.created_by
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'planning_cards' AND COLUMN_NAME = 'created_by') > 0,
  'UPDATE IGNORE `planning_cards` SET `created_by` = 1 WHERE `created_by` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- planning_cards.technical_responsible_id
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'planning_cards' AND COLUMN_NAME = 'technical_responsible_id') > 0,
  'UPDATE IGNORE `planning_cards` SET `technical_responsible_id` = 1 WHERE `technical_responsible_id` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- planning_comments.user_id
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'planning_comments' AND COLUMN_NAME = 'user_id') > 0,
  'UPDATE IGNORE `planning_comments` SET `user_id` = 1 WHERE `user_id` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- planning_task_images.user_id
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'planning_task_images' AND COLUMN_NAME = 'user_id') > 0,
  'UPDATE IGNORE `planning_task_images` SET `user_id` = 1 WHERE `user_id` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- planning_tasks.completed_by
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'planning_tasks' AND COLUMN_NAME = 'completed_by') > 0,
  'UPDATE IGNORE `planning_tasks` SET `completed_by` = 1 WHERE `completed_by` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- planning_tasks.created_by
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'planning_tasks' AND COLUMN_NAME = 'created_by') > 0,
  'UPDATE IGNORE `planning_tasks` SET `created_by` = 1 WHERE `created_by` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- presentations.created_by
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'presentations' AND COLUMN_NAME = 'created_by') > 0,
  'UPDATE IGNORE `presentations` SET `created_by` = 1 WHERE `created_by` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- project_events.user_id
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'project_events' AND COLUMN_NAME = 'user_id') > 0,
  'UPDATE IGNORE `project_events` SET `user_id` = 1 WHERE `user_id` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- projects.client_accepted_by
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'client_accepted_by') > 0,
  'UPDATE IGNORE `projects` SET `client_accepted_by` = 1 WHERE `client_accepted_by` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- projects.created_by
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'created_by') > 0,
  'UPDATE IGNORE `projects` SET `created_by` = 1 WHERE `created_by` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- proposal_events.user_id
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proposal_events' AND COLUMN_NAME = 'user_id') > 0,
  'UPDATE IGNORE `proposal_events` SET `user_id` = 1 WHERE `user_id` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- proposals.created_by
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proposals' AND COLUMN_NAME = 'created_by') > 0,
  'UPDATE IGNORE `proposals` SET `created_by` = 1 WHERE `created_by` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- prospecting_copy_suggestion.reviewed_by
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'prospecting_copy_suggestion' AND COLUMN_NAME = 'reviewed_by') > 0,
  'UPDATE IGNORE `prospecting_copy_suggestion` SET `reviewed_by` = 1 WHERE `reviewed_by` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- provider_events.user_id
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'provider_events' AND COLUMN_NAME = 'user_id') > 0,
  'UPDATE IGNORE `provider_events` SET `user_id` = 1 WHERE `user_id` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- provider_revisions.requested_by
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'provider_revisions' AND COLUMN_NAME = 'requested_by') > 0,
  'UPDATE IGNORE `provider_revisions` SET `requested_by` = 1 WHERE `requested_by` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- provider_revisions.reviewed_by
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'provider_revisions' AND COLUMN_NAME = 'reviewed_by') > 0,
  'UPDATE IGNORE `provider_revisions` SET `reviewed_by` = 1 WHERE `reviewed_by` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- providers.created_by
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'providers' AND COLUMN_NAME = 'created_by') > 0,
  'UPDATE IGNORE `providers` SET `created_by` = 1 WHERE `created_by` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- providers.user_id
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'providers' AND COLUMN_NAME = 'user_id') > 0,
  'UPDATE IGNORE `providers` SET `user_id` = 1 WHERE `user_id` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- provisioning_events.user_id
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'provisioning_events' AND COLUMN_NAME = 'user_id') > 0,
  'UPDATE IGNORE `provisioning_events` SET `user_id` = 1 WHERE `user_id` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- provisioning_steps.responsible_id
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'provisioning_steps' AND COLUMN_NAME = 'responsible_id') > 0,
  'UPDATE IGNORE `provisioning_steps` SET `responsible_id` = 1 WHERE `responsible_id` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- provisionings.created_by
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'provisionings' AND COLUMN_NAME = 'created_by') > 0,
  'UPDATE IGNORE `provisionings` SET `created_by` = 1 WHERE `created_by` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- recess_periods.created_by
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'recess_periods' AND COLUMN_NAME = 'created_by') > 0,
  'UPDATE IGNORE `recess_periods` SET `created_by` = 1 WHERE `created_by` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- sequence_participants.added_by
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sequence_participants' AND COLUMN_NAME = 'added_by') > 0,
  'UPDATE IGNORE `sequence_participants` SET `added_by` = 1 WHERE `added_by` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- service_catalog.created_by
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'service_catalog' AND COLUMN_NAME = 'created_by') > 0,
  'UPDATE IGNORE `service_catalog` SET `created_by` = 1 WHERE `created_by` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- shared_documents.user_id
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'shared_documents' AND COLUMN_NAME = 'user_id') > 0,
  'UPDATE IGNORE `shared_documents` SET `user_id` = 1 WHERE `user_id` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- ticket_attachments.user_id
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ticket_attachments' AND COLUMN_NAME = 'user_id') > 0,
  'UPDATE IGNORE `ticket_attachments` SET `user_id` = 1 WHERE `user_id` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- ticket_attendants.user_id
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ticket_attendants' AND COLUMN_NAME = 'user_id') > 0,
  'UPDATE IGNORE `ticket_attendants` SET `user_id` = 1 WHERE `user_id` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ticket_attendants' AND COLUMN_NAME = 'user_id') > 0,
  'DELETE FROM `ticket_attendants` WHERE `user_id` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- ticket_internal_notes.user_id
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ticket_internal_notes' AND COLUMN_NAME = 'user_id') > 0,
  'UPDATE IGNORE `ticket_internal_notes` SET `user_id` = 1 WHERE `user_id` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- ticket_messages.user_id
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ticket_messages' AND COLUMN_NAME = 'user_id') > 0,
  'UPDATE IGNORE `ticket_messages` SET `user_id` = 1 WHERE `user_id` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- ticket_relations.created_by
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ticket_relations' AND COLUMN_NAME = 'created_by') > 0,
  'UPDATE IGNORE `ticket_relations` SET `created_by` = 1 WHERE `created_by` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- tickets.attendant_id
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'attendant_id') > 0,
  'UPDATE IGNORE `tickets` SET `attendant_id` = 1 WHERE `attendant_id` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- tickets.technical_responsible_id
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'technical_responsible_id') > 0,
  'UPDATE IGNORE `tickets` SET `technical_responsible_id` = 1 WHERE `technical_responsible_id` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- user_activity_log.user_id
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_activity_log' AND COLUMN_NAME = 'user_id') > 0,
  'UPDATE IGNORE `user_activity_log` SET `user_id` = 1 WHERE `user_id` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- user_company_access.user_id
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_company_access' AND COLUMN_NAME = 'user_id') > 0,
  'UPDATE IGNORE `user_company_access` SET `user_id` = 1 WHERE `user_id` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_company_access' AND COLUMN_NAME = 'user_id') > 0,
  'DELETE FROM `user_company_access` WHERE `user_id` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- video_recordings.minutes_ack_by
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'video_recordings' AND COLUMN_NAME = 'minutes_ack_by') > 0,
  'UPDATE IGNORE `video_recordings` SET `minutes_ack_by` = 1 WHERE `minutes_ack_by` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- video_recordings.minutes_edited_by
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'video_recordings' AND COLUMN_NAME = 'minutes_edited_by') > 0,
  'UPDATE IGNORE `video_recordings` SET `minutes_edited_by` = 1 WHERE `minutes_edited_by` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- video_recordings.minutes_sent_by
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'video_recordings' AND COLUMN_NAME = 'minutes_sent_by') > 0,
  'UPDATE IGNORE `video_recordings` SET `minutes_sent_by` = 1 WHERE `minutes_sent_by` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- video_recordings.recorded_by
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'video_recordings' AND COLUMN_NAME = 'recorded_by') > 0,
  'UPDATE IGNORE `video_recordings` SET `recorded_by` = 1 WHERE `recorded_by` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- video_room_admins.user_id
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'video_room_admins' AND COLUMN_NAME = 'user_id') > 0,
  'UPDATE IGNORE `video_room_admins` SET `user_id` = 1 WHERE `user_id` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'video_room_admins' AND COLUMN_NAME = 'user_id') > 0,
  'DELETE FROM `video_room_admins` WHERE `user_id` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- video_room_join_requests.decided_by
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'video_room_join_requests' AND COLUMN_NAME = 'decided_by') > 0,
  'UPDATE IGNORE `video_room_join_requests` SET `decided_by` = 1 WHERE `decided_by` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- video_room_join_requests.user_id
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'video_room_join_requests' AND COLUMN_NAME = 'user_id') > 0,
  'UPDATE IGNORE `video_room_join_requests` SET `user_id` = 1 WHERE `user_id` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- video_room_participants.user_id
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'video_room_participants' AND COLUMN_NAME = 'user_id') > 0,
  'UPDATE IGNORE `video_room_participants` SET `user_id` = 1 WHERE `user_id` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- video_rooms.created_by
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'video_rooms' AND COLUMN_NAME = 'created_by') > 0,
  'UPDATE IGNORE `video_rooms` SET `created_by` = 1 WHERE `created_by` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- whatsapp_contacts.assigned_to
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'whatsapp_contacts' AND COLUMN_NAME = 'assigned_to') > 0,
  'UPDATE IGNORE `whatsapp_contacts` SET `assigned_to` = 1 WHERE `assigned_to` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- whatsapp_instances.user_id
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'whatsapp_instances' AND COLUMN_NAME = 'user_id') > 0,
  'UPDATE IGNORE `whatsapp_instances` SET `user_id` = 1 WHERE `user_id` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- whatsapp_labels.created_by
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'whatsapp_labels' AND COLUMN_NAME = 'created_by') > 0,
  'UPDATE IGNORE `whatsapp_labels` SET `created_by` = 1 WHERE `created_by` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- whatsapp_messages.sent_by
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'whatsapp_messages' AND COLUMN_NAME = 'sent_by') > 0,
  'UPDATE IGNORE `whatsapp_messages` SET `sent_by` = 1 WHERE `sent_by` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- whatsapp_quick_replies.created_by
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'whatsapp_quick_replies' AND COLUMN_NAME = 'created_by') > 0,
  'UPDATE IGNORE `whatsapp_quick_replies` SET `created_by` = 1 WHERE `created_by` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

-- whatsapp_webhooks.created_by
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'whatsapp_webhooks' AND COLUMN_NAME = 'created_by') > 0,
  'UPDATE IGNORE `whatsapp_webhooks` SET `created_by` = 1 WHERE `created_by` = 13', 'SELECT 1');

PREPARE x FROM @s;

EXECUTE x;

DEALLOCATE PREPARE x;

COMMIT;

-- ---------------------------------------------------------------------
-- Conferência: o que ainda está no usuário antigo. Esperado: nenhuma linha
-- (ou só apollo_credit_usage / daily_report_missing_notifications, que são
-- registros por dia que já existiam também no usuário novo).
-- ---------------------------------------------------------------------
SET SESSION group_concat_max_len = 1000000;

SET @q := (SELECT GROUP_CONCAT(CONCAT('SELECT ''', c.TABLE_NAME, '.', c.COLUMN_NAME, ''' AS coluna, COUNT(*) AS qtd FROM `', c.TABLE_NAME, '` WHERE `', c.COLUMN_NAME, '` = 13') SEPARATOR ' UNION ALL ')
  FROM information_schema.COLUMNS c
  JOIN information_schema.TABLES tb ON tb.TABLE_SCHEMA = c.TABLE_SCHEMA AND tb.TABLE_NAME = c.TABLE_NAME AND tb.TABLE_TYPE = 'BASE TABLE'
  WHERE c.TABLE_SCHEMA = DATABASE() AND c.TABLE_NAME <> 'users'
    AND c.DATA_TYPE IN ('int','bigint','smallint','mediumint')
    AND (c.COLUMN_NAME REGEXP '(user|_by$|^by_|assigned|attendant|responsible|analyst|technical|owner|author|agent|creator|approver|reviewer|seller|consultant|manager|operator|sender|recipient|participant|member|host|requester|developer|client_id$|admin)'
      OR EXISTS (SELECT 1 FROM information_schema.KEY_COLUMN_USAGE k WHERE k.TABLE_SCHEMA = c.TABLE_SCHEMA AND k.TABLE_NAME = c.TABLE_NAME AND k.COLUMN_NAME = c.COLUMN_NAME AND k.REFERENCED_TABLE_NAME = 'users'))
    AND CONCAT(c.TABLE_NAME, '.', c.COLUMN_NAME) NOT IN ('agenda_booking_links.sequence_participant_id','email_messages.sequence_participant_id','linkedin_tasks.participant_id','prospecting_lead_outcome.participant_id','prospecting_message_log.participant_id','prospecting_rag_episode.participant_id','sequence_executions.participant_id','sequence_participants.ai_agent_turns','video_rooms.max_participants','password_resets.user_id','user_login_history.user_id','user_login_history.impersonated_by','email_prospections_bkp_20260917.user_id'));

SET @q := CONCAT('SELECT * FROM (', @q, ') r WHERE qtd > 0 ORDER BY coluna');

PREPARE x FROM @q;

EXECUTE x;

DEALLOCATE PREPARE x;

SELECT u.id, u.email,
  (SELECT COUNT(*) FROM planning_cards pc WHERE pc.assigned_to = u.id OR pc.technical_responsible_id = u.id OR pc.analyst_id = u.id) AS cards,
  (SELECT COUNT(*) FROM tickets t WHERE t.attendant_id = u.id OR t.technical_responsible_id = u.id OR t.client_id = u.id) AS demandas,
  (SELECT COUNT(*) FROM ticket_attendants ta WHERE ta.user_id = u.id) AS atendente_em
FROM users u WHERE u.id IN (@de, @para);

