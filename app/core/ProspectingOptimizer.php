<?php

/**
 * ProspectingOptimizer — Camada 2 (IA sugere) da prospecção autorregulada.
 *
 * A cada N respostas recebidas (padrão 6, configurável em optimizer_min_replies),
 * analisa o desempenho por sequência e pede à IA uma NOVA variante de copy,
 * baseada na mensagem vencedora + nas objeções reais dos leads.
 *
 * A sugestão entra como RASCUNHO (pending) em prospecting_copy_suggestion e só
 * vai ao ar após aprovação humana. Nunca publica sozinha.
 *
 * Tolerante a falhas: se as tabelas/config não existirem, não faz nada.
 */
class ProspectingOptimizer
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    private function ready()
    {
        try {
            $r = $this->db->fetch(
                "SELECT COUNT(*) t FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME IN ('prospecting_lead_outcome','prospecting_copy_suggestion')"
            );
            return $r && (int)$r['t'] >= 2;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Verifica todas as sequências e, para as que atingiram +N respostas novas
     * desde a última análise, gera uma sugestão de copy. Chamado pelo cron.
     * @return array métricas ['analyzed'=>, 'suggested'=>]
     */
    public function runDue()
    {
        $out = ['analyzed' => 0, 'suggested' => 0];
        if (!$this->ready()) return $out;
        if ((int)(Config::get('optimizer_enabled') ?? 1) !== 1) return $out;

        $minReplies = max(1, (int)(Config::get('optimizer_min_replies') ?? 6));

        // Respostas acumuladas por sequência (base do gatilho).
        $rows = $this->db->fetchAll(
            "SELECT sequence_id, COUNT(*) AS replies
             FROM prospecting_lead_outcome
             WHERE sequence_id IS NOT NULL AND replied_at IS NOT NULL
             GROUP BY sequence_id"
        );

        foreach ($rows as $r) {
            $seqId = (int)$r['sequence_id'];
            $replies = (int)$r['replies'];
            $lastKey = 'optimizer_last_replies_' . $seqId;
            $last = (int)(Config::get($lastKey) ?? 0);

            // Dispara a cada +N respostas novas (a cada 6, por padrão).
            if (($replies - $last) < $minReplies) continue;

            $out['analyzed']++;
            $sug = $this->analyzeAndSuggest($seqId, $replies);
            if ($sug) {
                $out['suggested']++;
                // Marca o patamar de respostas analisado para só reanalisar após +N.
                Config::set($lastKey, (string)$replies);
            }
        }
        return $out;
    }

    /**
     * Analisa uma sequência: escolhe a variante vencedora (por taxa de reunião),
     * reúne objeções reais e pede à IA uma nova variante. Grava como rascunho.
     * @return bool true se gerou uma sugestão
     */
    public function analyzeAndSuggest($sequenceId, $repliesCount = 0)
    {
        if (!$this->ready()) return false;

        $apiKey = trim((string) Config::get('openai_api_key'));
        if ($apiKey === '') return false;

        // Desempenho por variante (taxa de reunião é o critério de vitória).
        $variants = $this->db->fetchAll(
            "SELECT ab_variant,
                    COUNT(*) AS sent,
                    SUM(CASE WHEN replied_at IS NOT NULL THEN 1 ELSE 0 END) AS replied,
                    SUM(CASE WHEN interest='positive' THEN 1 ELSE 0 END) AS interested,
                    SUM(CASE WHEN scheduled_at IS NOT NULL THEN 1 ELSE 0 END) AS scheduled
             FROM prospecting_lead_outcome
             WHERE sequence_id = ?
             GROUP BY ab_variant
             ORDER BY (scheduled / GREATEST(COUNT(*),1)) DESC, replied DESC",
            [$sequenceId]
        );
        if (empty($variants)) return false;

        $winner = $variants[0];
        $winnerVariant = $winner['ab_variant'] ?: null;
        $winnerRate = (int)$winner['sent'] > 0 ? round($winner['scheduled'] / $winner['sent'] * 100, 2) : 0;

        // Texto vencedor (amostra real enviada).
        $sample = $this->winnerSample($sequenceId, $winnerVariant);
        if (empty($sample['body'])) return false;
        $channel = $sample['channel'] ?: 'email';

        // Objeções reais dos leads que recusaram.
        $objections = $this->topObjections($sequenceId);
        // Trechos de respostas POSITIVAS (o que agradou).
        $positives = $this->positiveReplies($sequenceId);

        // RAG (Camada 3): recupera casos reais que converteram, parecidos com a
        // mensagem vencedora, para ancorar a nova copy em exemplos que deram certo.
        $ragBlock = '';
        try {
            $query = trim(($sample['subject'] ? $sample['subject'] . "\n" : '') . $sample['body']);
            $ragBlock = (new ProspectingRag())->contextBlock($query, 3, $sequenceId);
        } catch (\Throwable $e) { /* RAG é opcional */ }

        $suggestion = $this->askAi($apiKey, [
            'channel' => $channel,
            'winner_subject' => $sample['subject'],
            'winner_body' => $sample['body'],
            'winner_rate' => $winnerRate,
            'objections' => $objections,
            'positives' => $positives,
            'rag' => $ragBlock,
        ]);
        if (!$suggestion || empty($suggestion['body'])) return false;

        try {
            $this->db->insert('prospecting_copy_suggestion', [
                'sequence_id' => $sequenceId,
                'node_id' => $sample['node_id'] ?? null,
                'channel' => $channel,
                'based_on_variant' => $winnerVariant,
                'suggested_subject' => $suggestion['subject'] ?? null,
                'suggested_body' => $suggestion['body'],
                'rationale' => $suggestion['rationale'] ?? null,
                'sample_size' => (int)$repliesCount,
                'winner_meeting_rate' => $winnerRate,
                'top_objections' => $objections ? mb_substr(implode(' | ', $objections), 0, 490) : null,
                'status' => 'pending',
            ]);
            return true;
        } catch (\Throwable $e) {
            Logger::error('ProspectingOptimizer insert', ['seq' => $sequenceId, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /** Amostra da mensagem vencedora (texto real + canal + bloco). */
    private function winnerSample($sequenceId, $variant)
    {
        try {
            $where = "sequence_id = ?"; $params = [$sequenceId];
            if ($variant) { $where .= " AND ab_variant = ?"; $params[] = $variant; }
            $m = $this->db->fetch(
                "SELECT subject, body, channel, node_id FROM prospecting_message_log
                 WHERE $where ORDER BY id DESC LIMIT 1", $params);
            return $m ?: ['subject' => null, 'body' => null, 'channel' => 'email', 'node_id' => null];
        } catch (\Throwable $e) {
            return ['subject' => null, 'body' => null, 'channel' => 'email', 'node_id' => null];
        }
    }

    /** As objeções mais comuns dos leads que recusaram. */
    private function topObjections($sequenceId)
    {
        try {
            $rows = $this->db->fetchAll(
                "SELECT objection, COUNT(*) c FROM prospecting_lead_outcome
                 WHERE sequence_id = ? AND interest='negative' AND objection IS NOT NULL AND objection <> ''
                 GROUP BY objection ORDER BY c DESC LIMIT 5", [$sequenceId]);
            return array_map(fn($r) => $r['objection'], $rows);
        } catch (\Throwable $e) { return []; }
    }

    /** Trechos de respostas positivas (o que os interessados disseram). */
    private function positiveReplies($sequenceId)
    {
        try {
            $rows = $this->db->fetchAll(
                "SELECT reply_text FROM prospecting_lead_outcome
                 WHERE sequence_id = ? AND interest='positive' AND reply_text IS NOT NULL AND reply_text <> ''
                 ORDER BY interest_at DESC LIMIT 5", [$sequenceId]);
            return array_map(fn($r) => mb_substr($r['reply_text'], 0, 200), $rows);
        } catch (\Throwable $e) { return []; }
    }

    /** Pede à IA uma nova variante. Retorna ['subject','body','rationale'] ou null. */
    private function askAi($apiKey, $ctx)
    {
        $model = trim((string) Config::get('openai_model')) ?: 'gpt-4o-mini';
        $isEmail = ($ctx['channel'] === 'email');

        $system = "Você é um especialista em copywriting de prospecção fria B2B (ON Solutions Brasil), "
            . "focado em MAXIMIZAR AGENDAMENTO DE REUNIÕES. Em português do Brasil. "
            . "Receba a mensagem que MELHOR converteu, as objeções que derrubaram leads e o que os interessados disseram. "
            . "Escreva UMA nova variante melhor: mantenha o que funcionou, ataque as objeções de forma sutil, seja "
            . ($isEmail ? "objetivo (e-mail curto, com assunto forte)" : "curto e natural para WhatsApp (sem assunto)") . ". "
            . "Não invente preços/prazos. Responda SOMENTE com JSON válido: "
            . '{"subject":"' . ($isEmail ? 'assunto' : '') . '","body":"texto da mensagem","rationale":"por que esta versão tende a converter mais"}.';

        $user = "MENSAGEM VENCEDORA (taxa de reunião " . $ctx['winner_rate'] . "%):\n"
            . ($ctx['winner_subject'] ? ("Assunto: " . $ctx['winner_subject'] . "\n") : "")
            . $ctx['winner_body'] . "\n\n"
            . "OBJEÇÕES MAIS COMUNS (leads que recusaram):\n- " . (empty($ctx['objections']) ? '(nenhuma registrada)' : implode("\n- ", $ctx['objections'])) . "\n\n"
            . "O QUE OS INTERESSADOS DISSERAM:\n- " . (empty($ctx['positives']) ? '(nenhum registrado)' : implode("\n- ", $ctx['positives']))
            . (!empty($ctx['rag']) ? ("\n\n" . $ctx['rag']) : '');

        try {
            $ch = curl_init('https://api.openai.com/v1/chat/completions');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $apiKey, 'Content-Type: application/json'],
                CURLOPT_POSTFIELDS => json_encode([
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $user],
                    ],
                    'temperature' => 0.7,
                    'max_tokens' => 700,
                    'response_format' => ['type' => 'json_object'],
                ], JSON_UNESCAPED_UNICODE),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 60,
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($httpCode >= 400 || !$response) return null;
            $body = json_decode($response, true);
            $content = trim((string)($body['choices'][0]['message']['content'] ?? ''));
            $parsed = json_decode($content, true);
            if (!is_array($parsed) || empty($parsed['body'])) return null;
            return [
                'subject' => isset($parsed['subject']) ? trim((string)$parsed['subject']) : null,
                'body' => trim((string)$parsed['body']),
                'rationale' => isset($parsed['rationale']) ? trim((string)$parsed['rationale']) : null,
            ];
        } catch (\Throwable $e) {
            Logger::error('ProspectingOptimizer askAi', ['error' => $e->getMessage()]);
            return null;
        }
    }

    // =================================================================
    // Sugestões (para a interface de aprovação)
    // =================================================================

    /** Lista sugestões, mais recentes primeiro. */
    public function listSuggestions($status = null, $limit = 50)
    {
        if (!$this->ready()) return [];
        try {
            $sql = "SELECT sg.*, s.name AS sequence_name
                    FROM prospecting_copy_suggestion sg
                    LEFT JOIN email_sequences s ON s.id = sg.sequence_id";
            $params = [];
            if ($status) { $sql .= " WHERE sg.status = ?"; $params[] = $status; }
            $sql .= " ORDER BY sg.created_at DESC LIMIT " . (int)$limit;
            return $this->db->fetchAll($sql, $params);
        } catch (\Throwable $e) { return []; }
    }

    /**
     * Aprova ou rejeita uma sugestão. Ao APROVAR, publica a copy como VARIANTE B
     * ativa no bloco da sequência (A/B), para começar a ser testada contra a
     * campeã atual — fechando o loop. Rejeitar apenas registra.
     */
    public function review($id, $approve, $userId = null)
    {
        if (!$this->ready()) return false;
        try {
            $sug = $this->db->fetch("SELECT * FROM prospecting_copy_suggestion WHERE id = ?", [(int)$id]);
            if (!$sug) return false;

            $published = false;
            if ($approve) {
                $published = $this->publishAsVariantB($sug);
            }

            $this->db->update('prospecting_copy_suggestion', [
                'status' => $approve ? 'approved' : 'rejected',
                'reviewed_by' => $userId,
                'reviewed_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [(int)$id]);
            return ['ok' => true, 'published' => $published];
        } catch (\Throwable $e) {
            Logger::error('ProspectingOptimizer review', ['id' => $id, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Publica a sugestão como VARIANTE B do bloco de envio na sequência: liga o
     * teste A/B e grava subject_b/body_b no nó do grafo. Se a sugestão não indicar
     * o nó, escolhe o primeiro bloco do canal correspondente (send/whatsapp).
     * @return bool true se conseguiu gravar
     */
    private function publishAsVariantB($sug)
    {
        try {
            $seqId = (int)($sug['sequence_id'] ?? 0);
            if (!$seqId) return false;
            $seq = $this->db->fetch("SELECT id, graph FROM email_sequences WHERE id = ?", [$seqId]);
            if (!$seq || empty($seq['graph'])) return false;

            $graph = json_decode($seq['graph'], true);
            if (empty($graph['nodes']) || !is_array($graph['nodes'])) return false;

            $targetType = ($sug['channel'] === 'whatsapp') ? 'whatsapp' : 'send';
            $nodeId = $sug['node_id'] ?: null;
            // node_id pode ser uma lista (blocos agrupados) — usa o primeiro.
            if ($nodeId && strpos($nodeId, ',') !== false) $nodeId = trim(explode(',', $nodeId)[0]);

            $done = false;
            foreach ($graph['nodes'] as &$n) {
                $isTarget = $nodeId ? (($n['id'] ?? null) === $nodeId) : (($n['type'] ?? '') === $targetType);
                if (!$isTarget) continue;
                if (($n['type'] ?? '') !== $targetType) continue; // segurança de tipo

                $n['data'] = $n['data'] ?? [];
                $n['data']['ab_enabled'] = 1;
                $n['data']['body_b'] = $sug['suggested_body'];
                if ($targetType === 'send' && !empty($sug['suggested_subject'])) {
                    $n['data']['subject_b'] = $sug['suggested_subject'];
                }
                // Ao definir texto inline como B, remove template_b para o inline valer.
                unset($n['data']['template_id_b']);
                $done = true;
                break; // publica no primeiro bloco compatível
            }
            unset($n);

            if (!$done) return false;
            $this->db->update('email_sequences', ['graph' => json_encode($graph, JSON_UNESCAPED_UNICODE)], 'id = ?', [$seqId]);
            return true;
        } catch (\Throwable $e) {
            Logger::error('ProspectingOptimizer publish', ['error' => $e->getMessage()]);
            return false;
        }
    }
}
