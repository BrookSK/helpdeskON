-- Adiciona campos de ramal SIP por usuário (Nvoip webphone)
-- Cada operador pode ter seu próprio ramal, evitando conflito de registro.
ALTER TABLE users
    ADD COLUMN nvoip_sip_user VARCHAR(50) DEFAULT NULL AFTER phone,
    ADD COLUMN nvoip_sip_password VARCHAR(255) DEFAULT NULL AFTER nvoip_sip_user;
