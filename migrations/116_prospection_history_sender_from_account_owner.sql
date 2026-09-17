-- =====================================================================
-- 116_prospection_history_sender_from_account_owner.sql
-- ---------------------------------------------------------------------
-- CORREÇÃO do REMETENTE no Histórico de Prospecção.
--
-- PROBLEMA: envios automáticos de SEQUÊNCIAS não têm usuário logado. O código
-- (EmailMessageService::send) gravava o espelho em email_prospections usando o
-- PRIMEIRO super_admin ativo como user_id. Resultado: a coluna REMETENTE
-- aparecia sempre como "Super Admin", mesmo quando a CONTA de envio pertencia a
-- outra pessoa (ex.: julia@lrvweb.com.br).
--
-- CORREÇÃO NO CÓDIGO: agora o REMETENTE é resolvido pelo DONO da conta de envio
-- (email_account_users -> created_by -> super_admin como último recurso).
--
-- ESTE BACKFILL: reatribui o user_id dos registros históricos para o dono da
-- conta de envio, apenas quando:
--   - o user_id atual é um super_admin (o fallback antigo), E
--   - a conta de envio tem um dono real identificável (vínculo ou created_by)
--     que NÃO é super_admin.
-- Assim, envios manuais legítimos feitos por um super_admin não são alterados.
--
-- Idempotente: rodar de novo não muda mais nada, pois após o UPDATE o user_id
-- deixa de ser super_admin (ou já bate com o dono resolvido).
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1) Reatribui para o USUÁRIO VINCULADO à conta (email_account_users).
--    Prioridade máxima: relação explícita conta -> usuário.
-- ---------------------------------------------------------------------
UPDATE email_prospections ep
JOIN users cur ON cur.id = ep.user_id AND cur.role = 'super_admin'
JOIN (
    SELECT eau.email_account_id, MIN(eau.user_id) AS owner_id
    FROM email_account_users eau
    JOIN users ou ON ou.id = eau.user_id
    WHERE ou.role <> 'super_admin'
    GROUP BY eau.email_account_id
) owner ON owner.email_account_id = ep.email_account_id
SET ep.user_id = owner.owner_id
WHERE ep.user_id <> owner.owner_id;

-- ---------------------------------------------------------------------
-- 2) Para as contas SEM vínculo em email_account_users, usa quem criou a
--    conta (email_accounts.created_by), desde que não seja super_admin.
-- ---------------------------------------------------------------------
UPDATE email_prospections ep
JOIN users cur ON cur.id = ep.user_id AND cur.role = 'super_admin'
JOIN email_accounts ea ON ea.id = ep.email_account_id
JOIN users creator ON creator.id = ea.created_by AND creator.role <> 'super_admin'
LEFT JOIN email_account_users eau ON eau.email_account_id = ep.email_account_id
SET ep.user_id = ea.created_by
WHERE eau.email_account_id IS NULL
  AND ep.user_id <> ea.created_by;
