-- ============================================
-- Webphone nativo (WebRTC/SIP over WSS) da Nvoip
-- ============================================
-- Dados de conexão conforme documentação oficial Nvoip (WSS):
--   Servidor WebSocket: wss://app.nvoip.com.br:7443
--   Domínio SIP:        app.nvoip.com.br
--   Usuário/Auth user:  número do ramal
--   Senha:              senha SIP do ramal (SECRETO)
--
-- A senha SIP é secreta e é entregue apenas ao usuário autenticado, sob demanda,
-- por um endpoint do backend (não fica exposta no HTML).

INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('nvoip_sip_user', ''),
('nvoip_sip_password', ''),
('nvoip_ws_server', 'wss://app.nvoip.com.br:7443'),
('nvoip_sip_domain', 'app.nvoip.com.br');
