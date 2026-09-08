-- =====================================================================
-- 102_ai_agent_turns.sql
-- ---------------------------------------------------------------------
-- Suporte ao bloco "Atendente IA (FAQ)" (type=ai_agent) no construtor de
-- sequências. Esse bloco responde dúvidas do lead sobre a empresa em loop
-- (janela de escuta) até classificar a intenção: agendar / atendente / rejeitar.
--
--   * ai_agent_turns : contador de interações do atendente IA para o participante.
--     Usado como trava de segurança (max_turns) para não ficar preso no loop
--     de dúvidas indefinidamente — ao atingir o limite, escala para atendente humano.
--
-- Idempotente (usa information_schema, como nas migrations 090/100).
-- =====================================================================

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sequence_participants' AND COLUMN_NAME='ai_agent_turns');
SET @s := IF(@c=0,'ALTER TABLE sequence_participants ADD COLUMN ai_agent_turns INT NOT NULL DEFAULT 0','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
