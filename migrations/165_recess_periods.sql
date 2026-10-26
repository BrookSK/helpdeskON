-- Migration 165: Períodos de recesso do Planejamento.
--
-- Feature: datas em que ninguém trabalha (ex.: recesso de fim de ano,
-- 21/12 a 04/01). Quando o cálculo AUTOMÁTICO de datas de um card (cascata de
-- atividade predecessora) cair dentro de um recesso, o card é deslocado para
-- depois dele; cards que ATRAVESSAM um recesso têm a duração estendida para
-- "pular" os dias parados. A criação/edição MANUAL de datas não é bloqueada
-- (apenas sinalizada no front) — só o recálculo automático respeita o recesso.
--
-- Escopo: GLOBAL por padrão (company_id NULL = vale para todas as empresas).
-- A coluna company_id já fica preparada para, no futuro, permitir recesso
-- específico de uma empresa sem nova migration.
--
-- IDEMPOTENTE: só cria a tabela/índices se ainda não existirem. Sem "USE":
-- rode com o banco alvo já selecionado. Após rodar no helpdesk_on local,
-- recrie o banco de teste com tests/setup_test_db.php.

-- Tabela principal dos períodos de recesso.
CREATE TABLE IF NOT EXISTS recess_periods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL COMMENT 'Rótulo do recesso (ex.: Recesso de fim de ano)',
    start_date DATE NOT NULL COMMENT 'Primeiro dia do recesso (inclusive)',
    end_date DATE NOT NULL COMMENT 'Último dia do recesso (inclusive)',
    company_id INT NULL COMMENT 'NULL = recesso global (todas as empresas), senao especifico da empresa',
    created_by INT NULL COMMENT 'Usuário que cadastrou',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_recess_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_recess_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Índice para a cascata/calendário buscarem recessos por faixa de datas.
SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'recess_periods' AND INDEX_NAME = 'idx_recess_range');
SET @s := IF(@i = 0,
    'ALTER TABLE recess_periods ADD INDEX idx_recess_range (start_date, end_date)',
    'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Índice auxiliar por empresa (consultas de recesso específico).
SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'recess_periods' AND INDEX_NAME = 'idx_recess_company');
SET @s := IF(@i = 0,
    'ALTER TABLE recess_periods ADD INDEX idx_recess_company (company_id)',
    'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
