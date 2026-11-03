-- Migration 171: pagamento que mexe ou não no saldo da conta.
--
-- affects_balance = 1: o pagamento soma/desconta do saldo da conta.
-- affects_balance = 0: pago, mas o valor já estava dentro do saldo inicial
--                      (ex.: lançado depois, como "já pago").
--
-- Regra nova: lançar já como pago = NÃO mexe no saldo (padrão), salvo se
-- escolher "Já pago e mexe no saldo". Pagar depois (botão ✓, fatura,
-- parcela, previsto) mexe no saldo.
--
-- AJUSTE DOS DADOS: o saldo inicial das contas foi informado com o saldo de
-- HOJE, então tudo que já está pago até agora já está dentro dele. Por isso,
-- todos os pagamentos existentes passam a NÃO mexer no saldo.
-- Depois de rodar, "Saldo atual" de cada conta = saldo inicial + o que for
-- pago daqui pra frente.
--
-- Se rodar duas vezes, o ALTER responde "#1060 Duplicate column name
-- 'affects_balance'": já foi aplicada. NÃO rode os UPDATEs de novo depois de
-- já ter pago coisas pelo sistema (eles zerariam esses pagamentos também).

ALTER TABLE fin_transactions
    ADD COLUMN affects_balance TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = pagamento mexe no saldo da conta' AFTER paid_at;

ALTER TABLE fin_installments
    ADD COLUMN affects_balance TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = pagamento mexe no saldo da conta' AFTER paid_at;

UPDATE fin_transactions SET affects_balance = 0 WHERE status = 'paid';
UPDATE fin_installments SET affects_balance = 0 WHERE status = 'paid';

-- Conferência: quantos pagamentos ficaram fora do saldo.
SELECT
  (SELECT COUNT(*) FROM fin_transactions WHERE status = 'paid' AND affects_balance = 0) AS lancamentos_fora_do_saldo,
  (SELECT COUNT(*) FROM fin_installments WHERE status = 'paid' AND affects_balance = 0) AS parcelas_fora_do_saldo;
