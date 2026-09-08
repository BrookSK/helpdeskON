<?php

/**
 * ProspectingRag — Camada 3 (memória / RAG) da prospecção autorregulada.
 *
 * Indexa episódios (mensagem → resposta → desfecho) como embeddings e recupera
 * os casos MAIS PARECIDOS que deram certo, para dar contexto à geração de copy
 * (Camada 2) e às respostas da triagem.
 *
 * A similaridade (cosseno) é calculada em PHP. Tolerante a falhas: se a tabela
 * ou a chave da OpenAI não existirem, os métodos não fazem nada.
 */
class ProspectingRag
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    private function ready()
    {
        if ((int)(Config::get('rag_enabled') ?? 1) !== 1) return false;
        try {
            $r = $this->db->fetch(
                "SELECT COUNT(*) t FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'prospecting_rag_episode'"
            );
            return $r && (int)$r['t'] >= 1;
        } catch (\Throwable $e) {
            return false;
        }
    }

    // =================================================================
    // Indexação
    // =================================================================

    /**
     * Indexa (ou reindexa) os episódios ainda sem embedding a partir do desfecho
     * dos leads. Processa em lotes pequenos (barato). Chamado pelo cron.
     * @return int nº de episódios indexados nesta passada
     */
    public function indexPending($limit = 20)
    {
        if (!$this->ready()) return 0;
        $apiKey = trim((string) Config::get('openai_api_key'));
        if ($apiKey === '') return 0;

        try {
            // Leads com desfecho relevante (responderam) ainda não indexados.
            $rows = $this->db->fetchAll(
                "SELECT o.* FROM prospecting_lead_outcome o
                 WHERE o.replied_at IS NOT NULL
                   AND o.participant_id NOT IN (SELECT participant_id FROM prospecting_rag_episode WHERE participant_id IS NOT NULL)
                 ORDER BY o.updated_at DESC
                 LIMIT " . (int)$limit
            );
            if (empty($rows)) return 0;

            $model = trim((string) Config::get('rag_embed_model')) ?: 'text-embedding-3-small';
            $done = 0;
            foreach ($rows as $o) {
                $msg = $this->lastMessageText((int)$o['participant_id'], (int)$o['contact_id']);
                $success = (!empty($o['scheduled_at']) || $o['interest'] === 'positive') ? 1 : 0;
                $outcome = !empty($o['scheduled_at']) ? 'scheduled' : ($o['interest'] === 'positive' ? 'interested' : 'lost');

                $summary = $this->buildSummary($o, $msg);
                $embedding = $this->embed($apiKey, $model, $summary);
                if ($embedding === null) continue;

                $this->db->insert('prospecting_rag_episode', [
                    'contact_id' => $o['contact_id'],
                    'sequence_id' => $o['sequence_id'],
                    'participant_id' => $o['participant_id'],
                    'channel' => $o['first_channel'] ?: ($o['reply_channel'] ?: null),
                    'lead_title' => $o['lead_title'],
                    'lead_industry' => $o['lead_industry'],
                    'lead_company_size' => $o['lead_company_size'],
                    'message_text' => $msg ? mb_substr($msg, 0, 4000) : null,
                    'reply_text' => $o['reply_text'] ? mb_substr($o['reply_text'], 0, 2000) : null,
                    'outcome' => $outcome,
                    'success' => $success,
                    'summary' => $summary,
                    'embedding' => json_encode($embedding),
                    'embed_model' => $model,
                ]);
                $done++;
            }
            return $done;
        } catch (\Throwable $e) {
            Logger::error('ProspectingRag indexPending', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    private function buildSummary($o, $msg)
    {
        $parts = [];
        if (!empty($o['lead_title'])) $parts[] = 'Cargo: ' . $o['lead_title'];
        if (!empty($o['lead_industry'])) $parts[] = 'Setor: ' . $o['lead_industry'];
        if (!empty($o['lead_company_size'])) $parts[] = 'Porte: ' . $o['lead_company_size'];
        if ($msg) $parts[] = 'Mensagem enviada: ' . mb_substr($msg, 0, 800);
        if (!empty($o['reply_text'])) $parts[] = 'Resposta do lead: ' . mb_substr($o['reply_text'], 0, 500);
        $result = !empty($o['scheduled_at']) ? 'agendou reunião' : ($o['interest'] === 'positive' ? 'demonstrou interesse' : ($o['interest'] === 'negative' ? 'sem interesse' : 'sem desfecho claro'));
        $parts[] = 'Desfecho: ' . $result;
        return implode("\n", $parts);
    }

    private function lastMessageText($participantId, $contactId)
    {
        try {
            $m = $this->db->fetch(
                "SELECT COALESCE(subject,'') s, body FROM prospecting_message_log
                 WHERE participant_id = ? ORDER BY id DESC LIMIT 1", [$participantId]);
            if ($m) return trim(($m['s'] ? $m['s'] . "\n" : '') . ($m['body'] ?? ''));
        } catch (\Throwable $e) {}
        return null;
    }

    // =================================================================
    // Recuperação
    // =================================================================

    /**
     * Recupera os episódios de SUCESSO mais parecidos com uma consulta textual.
     * @return array lista de episódios [{message_text, reply_text, lead_*, score}]
     */
    public function retrieveSuccess($queryText, $topK = 3, $sequenceId = null)
    {
        if (!$this->ready()) return [];
        $apiKey = trim((string) Config::get('openai_api_key'));
        if ($apiKey === '') return [];

        try {
            $model = trim((string) Config::get('rag_embed_model')) ?: 'text-embedding-3-small';
            $qvec = $this->embed($apiKey, $model, $queryText);
            if ($qvec === null) return [];

            $sql = "SELECT * FROM prospecting_rag_episode WHERE success = 1 AND embedding IS NOT NULL";
            $params = [];
            if ($sequenceId) { $sql .= " AND sequence_id = ?"; $params[] = $sequenceId; }
            $sql .= " ORDER BY id DESC LIMIT 300"; // teto de candidatos p/ comparar em memória
            $rows = $this->db->fetchAll($sql, $params);
            if (empty($rows)) return [];

            $scored = [];
            foreach ($rows as $r) {
                $vec = json_decode($r['embedding'] ?? '[]', true);
                if (!is_array($vec) || empty($vec)) continue;
                $r['score'] = $this->cosine($qvec, $vec);
                $scored[] = $r;
            }
            usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
            return array_slice($scored, 0, max(1, (int)$topK));
        } catch (\Throwable $e) {
            Logger::error('ProspectingRag retrieveSuccess', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Monta um bloco de texto com os melhores casos, pronto para injetar no prompt.
     */
    public function contextBlock($queryText, $topK = 3, $sequenceId = null)
    {
        $eps = $this->retrieveSuccess($queryText, $topK, $sequenceId);
        if (empty($eps)) return '';
        $lines = ["EXEMPLOS REAIS QUE CONVERTERAM (use como referência de estilo e abordagem):"];
        $i = 1;
        foreach ($eps as $e) {
            $perfil = trim(($e['lead_title'] ?? '') . ' ' . ($e['lead_industry'] ?? ''));
            $lines[] = "\n[Caso {$i}] " . ($perfil !== '' ? "Perfil: {$perfil}. " : '')
                . "Mensagem: " . mb_substr((string)$e['message_text'], 0, 400)
                . ($e['reply_text'] ? " | Resposta positiva: " . mb_substr((string)$e['reply_text'], 0, 200) : '')
                . " | Desfecho: " . ($e['outcome'] ?? '');
            $i++;
        }
        return implode("\n", $lines);
    }

    // =================================================================
    // OpenAI embeddings + similaridade
    // =================================================================

    private function embed($apiKey, $model, $text)
    {
        $text = trim((string)$text);
        if ($text === '') return null;
        try {
            $ch = curl_init('https://api.openai.com/v1/embeddings');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $apiKey, 'Content-Type: application/json'],
                CURLOPT_POSTFIELDS => json_encode(['model' => $model, 'input' => mb_substr($text, 0, 6000)], JSON_UNESCAPED_UNICODE),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($httpCode >= 400 || !$response) return null;
            $body = json_decode($response, true);
            $vec = $body['data'][0]['embedding'] ?? null;
            return (is_array($vec) && !empty($vec)) ? $vec : null;
        } catch (\Throwable $e) {
            Logger::error('ProspectingRag embed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /** Similaridade do cosseno entre dois vetores. */
    private function cosine($a, $b)
    {
        $n = min(count($a), count($b));
        if ($n === 0) return 0.0;
        $dot = 0.0; $na = 0.0; $nb = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $dot += $a[$i] * $b[$i];
            $na += $a[$i] * $a[$i];
            $nb += $b[$i] * $b[$i];
        }
        if ($na == 0.0 || $nb == 0.0) return 0.0;
        return $dot / (sqrt($na) * sqrt($nb));
    }
}
