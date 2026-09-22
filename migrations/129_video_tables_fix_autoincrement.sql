-- Migration 129: Corrige AUTO_INCREMENT das PKs das tabelas de vídeo.
-- O schema importado deixou as colunas id das tabelas video_* como INT PRIMARY
-- KEY SEM AUTO_INCREMENT. Isso fez a criação de uma segunda sala falhar com
-- "Duplicate entry '0' for key 'video_rooms.PRIMARY'" (o insert grava id=0).
--
-- Mesmo problema (e mesma solução) da migration 128 para planning_cards.
--
-- Passos por tabela:
--   1) Remapear qualquer linha com id=0 para MAX(id)+1 (se existir).
--   2) Restaurar AUTO_INCREMENT na coluna id.
--
-- Execute manualmente no MySQL. Idempotente o suficiente para rodar uma vez.

USE helpdesk_on;

-- ================= video_rooms =================
UPDATE video_rooms
SET id = (SELECT next_id FROM (SELECT COALESCE(MAX(id),0) + 1 AS next_id FROM video_rooms) AS t)
WHERE id = 0;
ALTER TABLE video_rooms MODIFY id INT NOT NULL AUTO_INCREMENT;

-- ================= video_room_participants =================
UPDATE video_room_participants
SET id = (SELECT next_id FROM (SELECT COALESCE(MAX(id),0) + 1 AS next_id FROM video_room_participants) AS t)
WHERE id = 0;
ALTER TABLE video_room_participants MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT;

-- ================= video_room_signals =================
UPDATE video_room_signals
SET id = (SELECT next_id FROM (SELECT COALESCE(MAX(id),0) + 1 AS next_id FROM video_room_signals) AS t)
WHERE id = 0;
ALTER TABLE video_room_signals MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT;

-- ================= video_recordings =================
UPDATE video_recordings
SET id = (SELECT next_id FROM (SELECT COALESCE(MAX(id),0) + 1 AS next_id FROM video_recordings) AS t)
WHERE id = 0;
ALTER TABLE video_recordings MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT;

-- ================= video_room_admins =================
UPDATE video_room_admins
SET id = (SELECT next_id FROM (SELECT COALESCE(MAX(id),0) + 1 AS next_id FROM video_room_admins) AS t)
WHERE id = 0;
ALTER TABLE video_room_admins MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT;

-- ================= video_room_join_requests =================
UPDATE video_room_join_requests
SET id = (SELECT next_id FROM (SELECT COALESCE(MAX(id),0) + 1 AS next_id FROM video_room_join_requests) AS t)
WHERE id = 0;
ALTER TABLE video_room_join_requests MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT;
