-- Migration 170: corrige o "repetir até" dos recorrentes já gravados.
-- A tela manda só o mês (ex.: 2027-03) e o sistema gravava dia 1º (2027-03-01).
-- Com vencimento depois do dia 1, a ocorrência do próprio mês final ficava de
-- fora. O certo é valer o mês inteiro: último dia do mês.
-- Só mexe em datas no dia 1º (as que vieram da tela). Pode rodar mais de uma
-- vez sem efeito colateral.

UPDATE fin_transactions
SET recurring_until = LAST_DAY(recurring_until)
WHERE recurring_until IS NOT NULL
  AND DAY(recurring_until) = 1;
