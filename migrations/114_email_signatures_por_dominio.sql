-- =====================================================================
-- 114_email_signatures_por_dominio.sql
-- ---------------------------------------------------------------------
-- Assinatura de e-mail POR DOMÍNIO (configurada uma única vez em Configurações,
-- não por usuário). Cada domínio (ex.: onsolutionsbrasil.com.br e lrvweb.com.br)
-- tem uma assinatura com os MESMOS campos da assinatura padrão do sistema:
-- logo, empresa (nome em negrito), linha de especialidades, e-mail, site e tagline.
--
-- Quando um e-mail é disparado por uma conta, o motor casa o DOMÍNIO do remetente
-- com a assinatura correspondente. Sem correspondência, usa a assinatura padrão.
--
-- Tabela nova + seed dos dois domínios atuais. Idempotente.
-- =====================================================================

CREATE TABLE IF NOT EXISTS email_signatures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    domain VARCHAR(191) NOT NULL COMMENT 'domínio do remetente, ex.: lrvweb.com.br',
    company VARCHAR(150) DEFAULT NULL,        -- nome em negrito
    specialties VARCHAR(200) DEFAULT NULL,    -- linha "Tecnologia • Desenvolvimento • Automação"
    contact_email VARCHAR(200) DEFAULT NULL,
    site VARCHAR(200) DEFAULT NULL,
    tagline VARCHAR(255) DEFAULT NULL,        -- linha final
    logo VARCHAR(255) DEFAULT NULL,           -- caminho da logo (upload); vazio = usa logo do sistema
    color VARCHAR(20) DEFAULT '#00997D',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_domain (domain)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed: ON Solutions (usa a logo do sistema por padrão — logo vazio).
INSERT INTO email_signatures (domain, company, specialties, contact_email, site, tagline, logo, color)
SELECT 'onsolutionsbrasil.com.br', 'ON Solutions Brasil', 'Tecnologia • Desenvolvimento • Automação',
       'contato@onsolutionsbrasil.com.br', 'www.onsolutionsbrasil.com.br',
       'Soluções inteligentes para transformar processos e negócios.', NULL, '#00997D'
WHERE NOT EXISTS (SELECT 1 FROM email_signatures WHERE domain = 'onsolutionsbrasil.com.br');

-- Seed: LRV Web (logo a configurar via upload em Configurações).
INSERT INTO email_signatures (domain, company, specialties, contact_email, site, tagline, logo, color)
SELECT 'lrvweb.com.br', 'LRV Web', 'Sites • Sistemas • Soluções digitais',
       'contato@lrvweb.com.br', 'www.lrvweb.com.br',
       'Presença digital que gera resultado.', NULL, '#0d6efd'
WHERE NOT EXISTS (SELECT 1 FROM email_signatures WHERE domain = 'lrvweb.com.br');
