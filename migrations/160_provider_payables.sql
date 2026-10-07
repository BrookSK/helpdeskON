-- Migration 160: Contas a pagar dos prestadores (payables)
-- Guia de Contratacao de Prestadores, passo 7 ("apos a assinatura"): gerar os
-- lancamentos no CONTAS A PAGAR (mensal, por hora ou por parcelas). Ate aqui o
-- Financeiro so tratava recebiveis de clientes; esta tabela registra o que a
-- empresa deve ao prestador.
--
-- Idempotente: so cria a tabela se nao existir (padrao CREATE TABLE IF NOT EXISTS,
-- igual as migrations 117/150).

CREATE TABLE IF NOT EXISTS payables (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    provider_id      INT NOT NULL,
    kind             ENUM('mensal','hora','parcela') NOT NULL,
    description      VARCHAR(255) NULL,
    amount           DECIMAL(12,2) NOT NULL DEFAULT 0,
    due_date         DATE NULL,
    recurring_cycle  ENUM('monthly','yearly') NULL COMMENT 'Preenchido quando kind=mensal',
    installment_no   INT NULL COMMENT 'Numero da parcela quando kind=parcela',
    installment_total INT NULL COMMENT 'Total de parcelas quando kind=parcela',
    status           ENUM('pending','paid','cancelled') NOT NULL DEFAULT 'pending',
    paid_at          DATETIME NULL,
    created_by       INT NULL,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_payables_provider (provider_id),
    INDEX idx_payables_status (status),
    INDEX idx_payables_due (due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
