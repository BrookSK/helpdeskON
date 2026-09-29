-- RDO por projeto/obra: vincula o relatório diário a uma empresa (companies).
-- "Projeto/obra" no contexto da equipe = empresa cadastrada. Nullable para não
-- quebrar relatórios já existentes (que ficam "sem projeto").

ALTER TABLE daily_reports
    ADD COLUMN company_id INT NULL COMMENT 'Projeto/obra = empresa (companies)' AFTER user_id;

ALTER TABLE daily_reports
    ADD KEY idx_company (company_id);

ALTER TABLE daily_reports
    ADD CONSTRAINT fk_daily_reports_company
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL;
