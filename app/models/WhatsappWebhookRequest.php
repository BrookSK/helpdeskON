<?php

/**
 * Registro/fila das requisições recebidas em um webhook de entrada do WhatsApp.
 * Cada POST externo vira uma linha; o processamento (envio) atualiza o status.
 * Model fino sobre Database, no padrão do projeto.
 */
class WhatsappWebhookRequest
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById($id)
    {
        return $this->db->fetch("SELECT * FROM whatsapp_webhook_requests WHERE id = ?", [$id]);
    }

    /** Cria o registro da request recebida. Retorna o id inserido. */
    public function create($data)
    {
        return $this->db->insert('whatsapp_webhook_requests', $data);
    }

    public function updateStatus($id, array $data)
    {
        return $this->db->update('whatsapp_webhook_requests', $data, 'id = ?', [$id]);
    }

    /**
     * Lista as requests de um webhook para exibição ao vivo. Suporta $afterId
     * para o polling incremental (só traz o que chegou depois) e um $limit para
     * a carga inicial. Mais recentes primeiro quando $afterId é 0.
     */
    public function getByWebhook($webhookId, $afterId = 0, $limit = 50)
    {
        $afterId = (int) $afterId;
        if ($afterId > 0) {
            // Incremental: tudo que chegou depois do último id visto (ordem crescente).
            return $this->db->fetchAll(
                "SELECT * FROM whatsapp_webhook_requests
                  WHERE webhook_id = ? AND id > ?
                  ORDER BY id ASC",
                [$webhookId, $afterId]
            );
        }
        // Carga inicial: as mais recentes (ordem decrescente, limitada).
        return $this->db->fetchAll(
            "SELECT * FROM whatsapp_webhook_requests
              WHERE webhook_id = ?
              ORDER BY id DESC
              LIMIT " . (int) $limit,
            [$webhookId]
        );
    }

    /**
     * Lotes pendentes de processamento (status received/queued) para o cron de
     * envio. Mais antigas primeiro, com limite de tentativas.
     */
    public function pendingForProcessing($limit = 20, $maxAttempts = 3)
    {
        return $this->db->fetchAll(
            "SELECT * FROM whatsapp_webhook_requests
              WHERE status IN ('received','queued') AND attempts < ?
              ORDER BY id ASC
              LIMIT " . (int) $limit,
            [$maxAttempts]
        );
    }
}
