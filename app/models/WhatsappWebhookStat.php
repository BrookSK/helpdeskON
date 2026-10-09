<?php

/**
 * Contador DIÁRIO por webhook (volume). Em produção não gravamos cada requisição
 * recebida (seriam milhares/dia) — mantemos apenas este resumo leve: uma linha
 * por (webhook, data) com recebidas/enviadas/falhas, incrementada de forma
 * atômica. Model fino sobre Database.
 */
class WhatsappWebhookStat
{
    private $db;

    /** Campos que podem ser incrementados. */
    private const COUNTERS = ['received', 'sent', 'failed'];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Incrementa, de forma atômica, um contador do dia de hoje para o webhook.
     * Best-effort: nunca lança (um erro de métrica não pode quebrar o envio).
     *
     * @param int    $webhookId
     * @param string $field  'received' | 'sent' | 'failed'
     * @param int    $by     quanto somar (default 1)
     */
    public function bump(int $webhookId, string $field, int $by = 1): void
    {
        if (!in_array($field, self::COUNTERS, true) || $by <= 0) {
            return;
        }
        try {
            // UPSERT atômico: cria a linha do dia ou soma ao contador existente.
            $this->db->query(
                "INSERT INTO whatsapp_webhook_stats (webhook_id, stat_date, {$field})
                 VALUES (?, CURDATE(), ?)
                 ON DUPLICATE KEY UPDATE {$field} = {$field} + VALUES({$field})",
                [$webhookId, $by]
            );
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::warning('[WebhookStat] bump falhou', ['webhook' => $webhookId, 'field' => $field, 'error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Retorna os contadores dos últimos $days dias (inclui hoje), mais recentes
     * primeiro, e o total do período. Para exibir o volume na tela.
     *
     * @return array{days: array<int,array{stat_date:string,received:int,sent:int,failed:int}>, totals: array{received:int,sent:int,failed:int}}
     */
    public function getStats(int $webhookId, int $days = 7): array
    {
        $days = max(1, min(90, $days));
        try {
            $rows = $this->db->fetchAll(
                "SELECT stat_date, received, sent, failed
                   FROM whatsapp_webhook_stats
                  WHERE webhook_id = ? AND stat_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                  ORDER BY stat_date DESC",
                [$webhookId, $days - 1]
            );
        } catch (\Throwable $e) {
            $rows = [];
        }

        $totals = ['received' => 0, 'sent' => 0, 'failed' => 0];
        foreach ($rows as &$r) {
            $r['received'] = (int) $r['received'];
            $r['sent']     = (int) $r['sent'];
            $r['failed']   = (int) $r['failed'];
            $totals['received'] += $r['received'];
            $totals['sent']     += $r['sent'];
            $totals['failed']   += $r['failed'];
        }
        unset($r);

        return ['days' => $rows, 'totals' => $totals];
    }

    /** Contadores só de hoje (atalho para o resumo do topo da tela). */
    public function today(int $webhookId): array
    {
        try {
            $row = $this->db->fetch(
                "SELECT received, sent, failed FROM whatsapp_webhook_stats
                  WHERE webhook_id = ? AND stat_date = CURDATE()",
                [$webhookId]
            );
        } catch (\Throwable $e) {
            $row = null;
        }
        return [
            'received' => (int) ($row['received'] ?? 0),
            'sent'     => (int) ($row['sent'] ?? 0),
            'failed'   => (int) ($row['failed'] ?? 0),
        ];
    }
}
