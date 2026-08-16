-- Tabela de participantes internos da reunião (M:N entre agenda_meetings e users)
CREATE TABLE IF NOT EXISTS agenda_meeting_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    meeting_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (meeting_id) REFERENCES agenda_meetings(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_participant (meeting_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
