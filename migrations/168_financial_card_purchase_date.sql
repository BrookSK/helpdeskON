-- Migration 168: data da compra no cartão.
-- Compra no cartão passa a ter duas datas:
--   purchase_date = quando a compra foi feita;
--   due_date      = vencimento da fatura em que ela caiu (mês do caixa).
-- O filtro por mês e os relatórios usam due_date, então uma compra de
-- setembro que cai na fatura de outubro aparece em outubro.
-- Lançamentos fora do cartão ficam com purchase_date NULL.
--
-- Se rodar duas vezes, o MySQL responde "#1060 Duplicate column name
-- 'purchase_date'": isso só quer dizer que já foi aplicada.

ALTER TABLE fin_transactions
    ADD COLUMN purchase_date DATE NULL COMMENT 'Data da compra no cartao (due_date = vencimento da fatura)' AFTER due_date;
