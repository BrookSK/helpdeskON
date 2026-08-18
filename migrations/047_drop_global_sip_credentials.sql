-- ============================================
-- Remove as credenciais SIP GLOBAIS do webphone
-- ============================================
-- O ramal e a senha SIP passam a ser individuais por usuário (tabela users).
-- Mantém apenas a config global de servidor (ws_server, domínio, ice_servers).
-- Isso evita conflito de registro (vários usuários no mesmo ramal global).

DELETE FROM settings WHERE setting_key IN ('nvoip_sip_user', 'nvoip_sip_password', 'nvoip_webphone_api_key');
