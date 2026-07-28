-- Tabela de fila de webhooks para disparo sequencial via cron
CREATE TABLE IF NOT EXISTS webhook_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    phone VARCHAR(20) NOT NULL,
    name VARCHAR(100) NOT NULL DEFAULT '',
    message TEXT NOT NULL,
    status ENUM('pending', 'sent', 'failed') NOT NULL DEFAULT 'pending',
    attempts INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at DATETIME NULL,
    error_message VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Índice para buscar pendentes rapidamente
CREATE INDEX idx_webhook_queue_status ON webhook_queue(status, created_at);
