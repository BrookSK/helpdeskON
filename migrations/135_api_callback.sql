-- Callback de status da API v1 de demandas/chamados (mão dupla).
--
-- Contexto: a API v1 (POST /api/v1/tickets) permite que um sistema externo
-- (ex.: Punta Cana) ABRA chamados no helpdeskON. Este recurso adiciona o
-- retorno: quando o STATUS de um chamado criado via API muda, o helpdeskON
-- faz um POST para uma URL de callback cadastrada por empresa, avisando o
-- sistema externo. O disparo é assíncrono (fila + cron), para não travar a
-- operação interna caso o sistema externo esteja lento/fora do ar.
--
-- Decisões (alinhadas com o produto):
--  - Callback é POR EMPRESA -> a URL fica em api_keys (relação 1:1 com empresa),
--    não na tabela settings (que é global).
--  - Só o evento de MUDANÇA DE STATUS dispara callback nesta versão.
--  - Sem assinatura HMAC: o tráfego só SAI do LRV para o sistema externo.

-- 1) URL de callback e liga/desliga por empresa (na chave de integração).
ALTER TABLE api_keys
    ADD COLUMN callback_url VARCHAR(500) NULL AFTER api_key,
    ADD COLUMN callback_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER callback_url;

-- 2) Fila de callbacks a serem entregues. Mesmo padrão da webhook_queue (007):
--    processada em FIFO por um cron, com tentativas/retry e mensagem de erro.
--
--    payload: JSON completo já montado (enviado como corpo do POST).
--    O ticket_id/company_id ficam desnormalizados para auditoria e para permitir
--    consultas/depuração sem precisar reparsear o payload.
CREATE TABLE IF NOT EXISTS api_callback_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    ticket_id INT NOT NULL,
    callback_url VARCHAR(500) NOT NULL,
    payload TEXT NOT NULL COMMENT 'Corpo JSON do POST (event, id, external_ref, status, previous_status, changed_at)',
    status ENUM('pending', 'sent', 'failed') NOT NULL DEFAULT 'pending',
    attempts INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at DATETIME NULL,
    last_http_code INT NULL,
    error_message VARCHAR(255) NULL,
    INDEX idx_api_callback_queue_status (status, created_at),
    INDEX idx_api_callback_queue_ticket (ticket_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
