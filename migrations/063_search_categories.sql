-- Migration 063: Categorias monitoradas (99Freelas).
-- As categorias são descobertas automaticamente durante a coleta e ficam
-- disponíveis nas Configurações para ativar/desativar. A tela de Oportunidades
-- só oferece e exibe as categorias ativas.

CREATE TABLE IF NOT EXISTS search_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed com as categorias conhecidas do 99Freelas (ativas por padrão).
-- Novas categorias encontradas na coleta são adicionadas automaticamente.
INSERT IGNORE INTO search_categories (name, active) VALUES
('Análise de Dados & Estatística', 1),
('Apresentação', 1),
('Banco de Dados', 1),
('Comercial', 1),
('Contabilidade', 1),
('Criação & Integração com IA', 1),
('Desenvolvimento de Games', 1),
('Desenvolvimento Desktop', 1),
('Desenvolvimento Mobile', 1),
('Desenvolvimento Web', 1),
('Design de Interiores', 1),
('Design Gráfico', 1),
('Diagramação', 1),
('Edição de Imagens', 1),
('Engenharia Civil', 1),
('Engenharia Mecânica', 1),
('Gestão de Mídias Sociais', 1),
('Locução & Narração', 1),
('Logotipos', 1),
('Marketing Digital', 1),
('Negócios & Finanças', 1),
('Redação & Conteúdo', 1),
('Suporte Administrativo', 1),
('Tradução', 1),
('Vendas', 1);
