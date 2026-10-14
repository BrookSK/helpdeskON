-- Migration 144: Relacionamento entre demandas (ticket <-> ticket).
--
-- Permite ligar o histórico: Chamado de Suporte -> Incidente -> Demanda de
-- Correção. Hoje só existia o vínculo ticket <-> planning_card; esta tabela
-- cria o vínculo ticket a ticket, com um tipo de relação.
--
-- Modelagem: aresta direcionada (source_ticket_id -> target_ticket_id) com um
-- `relation_type`. Ex.: um ticket de Suporte aponta para a Demanda de Correção
-- com relation_type = 'correcao'. A UI mostra os dois lados.
--
-- Idempotente: cria a tabela só se não existir (CREATE TABLE IF NOT EXISTS).

CREATE TABLE IF NOT EXISTS ticket_relations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    source_ticket_id INT NOT NULL,
    target_ticket_id INT NOT NULL,
    relation_type ENUM('suporte','incidente','correcao','relacionado') NOT NULL DEFAULT 'relacionado'
        COMMENT 'Natureza do vinculo entre as demandas',
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_ticket_relation (source_ticket_id, target_ticket_id, relation_type),
    INDEX idx_tr_source (source_ticket_id),
    INDEX idx_tr_target (target_ticket_id),
    CONSTRAINT fk_tr_source FOREIGN KEY (source_ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    CONSTRAINT fk_tr_target FOREIGN KEY (target_ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    CONSTRAINT fk_tr_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
