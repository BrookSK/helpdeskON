-- Migration 173: comissão automática do CRM no Controle Financeiro.
--
-- Quando um card do CRM é fechado, o sistema lança a comissão de cada
-- comercial como "a pagar" em fin_transactions, vencendo junto com o salário
-- da pessoa:
--   crm_card_id     = card que gerou a comissão (excluir o card só desvincula);
--   commission_role = 'closing' (quem fechou) ou 'prospection' (quem trouxe o
--                     lead, quando outra pessoa fechou).
-- Lançamentos manuais continuam com as duas colunas vazias.
--
-- Precisa da 169 (fin_transactions.user_id) já aplicada.
-- Se rodar duas vezes, o banco responde "#1060 Duplicate column name
-- 'crm_card_id'": isso só quer dizer que já foi aplicada.

ALTER TABLE fin_transactions
    ADD COLUMN crm_card_id INT NULL COMMENT 'Card do CRM que gerou a comissão',
    ADD COLUMN commission_role ENUM('closing','prospection') NULL COMMENT 'Comissão de fechamento ou de prospecção',
    ADD KEY idx_fint_crm_card (crm_card_id),
    ADD CONSTRAINT fk_fint_crm_card FOREIGN KEY (crm_card_id) REFERENCES crm_cards(id) ON DELETE SET NULL;
