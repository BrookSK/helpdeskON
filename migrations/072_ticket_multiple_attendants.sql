-- Permite atribuir múltiplos atendentes a uma demanda.
-- O campo tickets.attendant_id é mantido como atendente principal (compatibilidade).
-- Os demais atendentes ficam registrados nesta tabela de junção.
CREATE TABLE IF NOT EXISTS ticket_attendants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ticket_attendant (ticket_id, user_id),
    CONSTRAINT fk_ta_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    CONSTRAINT fk_ta_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Popular a junção com os atendentes já atribuídos individualmente.
INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT id, attendant_id FROM tickets WHERE attendant_id IS NOT NULL;
