-- =====================================================================
-- RESET do Controle Financeiro (aba super_admin) — "começar do zero".
-- ---------------------------------------------------------------------
-- DESTRUTIVO E IRREVERSÍVEL. Rode no phpMyAdmin (produção: banco
-- "helpdesk_on"), aba SQL. FAÇA UM EXPORT das tabelas fin_* ANTES
-- (Exportar) para ter rollback.
--
-- Afeta as tabelas do módulo "Financial" (Controle Financeiro):
--   fin_installments  -> parcelas das compras parceladas
--   fin_commissions   -> comissões (aba Comissões)
--   fin_transactions  -> lançamentos (aba Lançamentos; manuais + Asaas)
--   fin_credit_cards  -> cartões (aba Cartões)
--
-- PRESERVA de propósito:
--   finance_accounts  -> a CONEXÃO/credencial das contas Asaas (aba Asaas).
--                        Assim você não precisa reconfigurar a integração.
--
-- ⚠️ ATENÇÃO SOBRE O ASAAS: a maioria dos lançamentos tem origem "Asaas
-- LRV Web" (source='asaas'), importados da integração. Ao apagar
-- fin_transactions, o controle de duplicidade (asaas_payment_id) é zerado;
-- então, na PRÓXIMA sincronização com o Asaas, essas cobranças serão
-- REIMPORTADAS. Isso costuma ser o desejado ao "recomeçar limpo", mas
-- esteja ciente: a aba de Lançamentos voltará a popular com os dados do
-- Asaas assim que a importação rodar de novo.
--
-- ESCOPO DESTE SCRIPT = Opção 3 (zera lançamentos + parcelas + comissões +
-- cartões; mantém a conexão Asaas). Veja no fim variações comentadas para
-- as Opções 1, 2 e 4.
-- =====================================================================

-- A ordem respeita as FKs. fin_installments e fin_commissions têm
-- ON DELETE CASCADE a partir de fin_transactions, mas apagamos explicitamente
-- primeiro para deixar o efeito claro e não depender só da cascata.

-- Desliga a checagem de FK durante o reset (religa ao final).
SET FOREIGN_KEY_CHECKS = 0;

-- 1) Parcelas (dependem de fin_transactions).
DELETE FROM fin_installments;

-- 2) Comissões (dependem de fin_transactions).
DELETE FROM fin_commissions;

-- 3) Lançamentos (manuais + importados do Asaas).
DELETE FROM fin_transactions;

-- 4) Cartões de crédito.
DELETE FROM fin_credit_cards;

-- Reinicia os contadores de id (começa do 1 de novo).
ALTER TABLE fin_installments AUTO_INCREMENT = 1;
ALTER TABLE fin_commissions  AUTO_INCREMENT = 1;
ALTER TABLE fin_transactions AUTO_INCREMENT = 1;
ALTER TABLE fin_credit_cards AUTO_INCREMENT = 1;

SET FOREIGN_KEY_CHECKS = 1;

-- Conferência (rode junto): tudo deve retornar 0.
SELECT
  (SELECT COUNT(*) FROM fin_transactions) AS lancamentos,
  (SELECT COUNT(*) FROM fin_installments) AS parcelas,
  (SELECT COUNT(*) FROM fin_commissions)  AS comissoes,
  (SELECT COUNT(*) FROM fin_credit_cards) AS cartoes,
  (SELECT COUNT(*) FROM finance_accounts) AS contas_asaas_preservadas;

-- =====================================================================
-- VARIAÇÕES (use no lugar dos DELETEs acima conforme sua escolha):
--
-- Opção 1 — Zerar só MANUAIS (mantém os importados do Asaas):
--   DELETE FROM fin_installments WHERE transaction_id IN
--       (SELECT id FROM fin_transactions WHERE source = 'manual');
--   DELETE FROM fin_commissions  WHERE transaction_id IN
--       (SELECT id FROM fin_transactions WHERE source = 'manual');
--   DELETE FROM fin_transactions WHERE source = 'manual';
--   (não mexe em fin_credit_cards)
--
-- Opção 2 — Zerar TODOS os lançamentos + parcelas + comissões, mas MANTER
--   os cartões: use os DELETEs 1, 2 e 3 acima e NÃO rode o DELETE 4.
--
-- Opção 4 — Zerar TUDO, inclusive a conexão Asaas (reconfigurar do zero):
--   além dos 4 DELETEs acima, adicione:
--   DELETE FROM finance_accounts;
--   ALTER TABLE finance_accounts AUTO_INCREMENT = 1;
--   (⚠️ você precisará recadastrar a(s) conta(s) Asaas depois)
-- =====================================================================
