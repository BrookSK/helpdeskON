-- Migration 011: Tabela de observações internas dos tickets
-- Visível apenas para super_admin e atendentes, não para clientes.

CREATE TABLE IF NOT EXISTS ticket_internal_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    user_id INT NOT NULL,
    note TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_internal_notes_ticket ON ticket_internal_notes(ticket_id, created_at);
