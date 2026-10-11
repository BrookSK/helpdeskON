-- Migration 156: Controle Financeiro v2.
-- - Recorrência (pagamentos/ganhos que se repetem: monthly/yearly, com fim opcional)
-- - Data do empréstimo (quando pegou/concedeu) além do vencimento das parcelas
-- - Comissões: vinculado a um ganho (projeto fechado), pra quem, quanto,
--   em quantas parcelas, controle de pagamento parcela a parcela.
-- Obs.: roda após a 155 (fin_transactions já deve existir).

ALTER TABLE fin_transactions
    ADD COLUMN recurring_cycle ENUM('monthly','yearly') NULL COMMENT 'Ciclo quando recorrente' AFTER installments,
    ADD COLUMN recurring_until  DATE NULL COMMENT 'Última ocorrência do recorrente (null = indefinido)' AFTER recurring_cycle,
    ADD COLUMN loan_date       DATE NULL COMMENT 'Data do empréstimo (quando pegou/concedeu)' AFTER due_date;

CREATE TABLE IF NOT EXISTS fin_commissions (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id  INT NOT NULL COMMENT 'Ganho de origem (projeto/venda)',
    user_id         INT NULL COMMENT 'Usuário que recebe (comercial/etc.)',
    recipient       VARCHAR(120) NULL COMMENT 'Nome do recebedor quando fora do sistema',
    amount          DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'Valor total da comissão',
    installments     INT NOT NULL DEFAULT 1 COMMENT 'Em quantas vezes será paga',
    installments_paid INT NOT NULL DEFAULT 0 COMMENT 'Parcelas já quitadas',
    first_due_date  DATE NULL COMMENT 'Vencimento da 1ª parcela',
    next_due_date   DATE NULL COMMENT 'Próximo vencimento (atualiza a cada parcela paga)',
    status          ENUM('pending','paid','cancelled') NOT NULL DEFAULT 'pending',
    paid_at         DATETIME NULL COMMENT 'Quando quitou a última parcela',
    note            VARCHAR(255) NULL COMMENT 'Observação (ex.: 10% sobre o projeto X)',
    created_by      INT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_fcom_trans (transaction_id),
    KEY idx_fcom_user (user_id),
    KEY idx_fcom_status (status),
    FOREIGN KEY (transaction_id) REFERENCES fin_transactions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;