-- Ajuste de terminologia: o vínculo do RDO passa a ser exibido como
-- "Cliente/empresa" (antes "Projeto/obra"). O dado continua sendo a empresa
-- cadastrada (companies) — muda apenas o rótulo/COMMENT, não a estrutura.

ALTER TABLE daily_reports
    MODIFY COLUMN company_id INT NULL COMMENT 'Cliente/empresa (companies)';
