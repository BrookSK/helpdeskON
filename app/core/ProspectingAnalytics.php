<?php

/**
 * ProspectingAnalytics — Camada 1 (medição) da prospecção autorregulada.
 *
 * Responsável por:
 *   1) Registrar CADA mensagem real enviada (texto + atributos) — o "estímulo".
 *   2) Manter o desfecho de cada lead no funil — o "placar".
 *   3) Calcular o ranking de mensagens por taxa de reunião — o "veredito".
 *
 * Tudo é tolerante a falhas: se as tabelas ainda não existirem (migration 106
 * não aplicada), os métodos simplesmente não fazem nada — nunca quebram o envio.
 */
class ProspectingAnalytics
{
    private $db;
    private static $ready = null; // cache: tabelas existem?

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** Verifica (com cache) se as tabelas de analytics existem. */
    private function ready()
    {
        if (self::$ready !== null) return self::$ready;
        try {
            $r = $this->db->fetch(
                "SELECT COUNT(*) t FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME IN ('prospecting_message_log','prospecting_lead_outcome')"
            );
            self::$ready = $r && (int)$r['t'] >= 2;
        } catch (\Throwable $e) {
            self::$ready = false;
        }
        return self::$ready;
    }

    // =================================================================
    // 1) Registro da mensagem enviada (o estímulo)
    // =================================================================

    /**
     * Registra uma mensagem enviada e extrai automaticamente seus atributos.
     * @param array $data contact_id, sequence_id, participant_id, node_id,
     *              channel ('email'|'whatsapp'), ab_variant, subject, body
     */
    public function logMessage(array $data)
    {
        if (!$this->ready()) return;
        try {
            $subject = (string)($data['subject'] ?? '');
            $body = (string)($data['body'] ?? '');
            $plain = trim($subject . "\n" . $this->stripHtml($body));
            $attrs = $this->extractAttributes($plain);

            $this->db->insert('prospecting_message_log', [
                'contact_id'       => (int)($data['contact_id'] ?? 0),
                'sequence_id'      => $data['sequence_id'] ?? null,
                'participant_id'   => $data['participant_id'] ?? null,
                'node_id'          => $data['node_id'] ?? null,
                'channel'          => in_array($data['channel'] ?? '', ['email', 'whatsapp'], true) ? $data['channel'] : 'email',
                'ab_variant'       => $data['ab_variant'] ?? null,
                'subject'          => $subject !== '' ? mb_substr($subject, 0, 250) : null,
                'body'             => $this->stripHtml($body),
                'len_chars'        => mb_strlen($plain),
                'has_number'       => $attrs['has_number'],
                'has_question'     => $attrs['has_question'],
                'has_link'         => $attrs['has_link'],
                'has_social_proof' => $attrs['has_social_proof'],
                'cta_type'         => $attrs['cta_type'],
                'tone'             => $attrs['tone'],
                'attributes_json'  => json_encode($attrs, JSON_UNESCAPED_UNICODE),
                'sent_at'          => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            Logger::error('ProspectingAnalytics logMessage', ['error' => $e->getMessage()]);
        }
    }

    /** Analisa o texto e infere os atributos que ajudam a entender o "porquê". */
    private function extractAttributes($text)
    {
        $t = mb_strtolower($text);
        $hasNumber = preg_match('/\d/', $t) ? 1 : 0;
        $hasQuestion = (strpos($text, '?') !== false) ? 1 : 0;
        $hasLink = preg_match('/https?:\/\//i', $text) ? 1 : 0;

        // Prova social / casos de cliente
        $socialTerms = ['cliente', 'empresas como', 'ajudamos', 'resultado', 'caso de', 'referência', 'parecid'];
        $hasSocial = 0;
        foreach ($socialTerms as $w) { if (strpos($t, $w) !== false) { $hasSocial = 1; break; } }

        // Tipo de CTA
        $cta = 'none';
        if (preg_match('/(agend|reuni|conversa|call|bate-papo|marcar|hor[áa]rio)/', $t)) $cta = 'meeting';
        elseif ($hasQuestion) $cta = 'question';
        elseif (preg_match('/(material|apresenta[çc][ãa]o|planilha|pdf|conte[úu]do|estudo)/', $t)) $cta = 'material';

        // Tom (heurística simples)
        $tone = preg_match('/(prezad|cordialmente|atenciosamente|venho por meio)/', $t) ? 'formal' : 'informal';

        return [
            'has_number' => $hasNumber,
            'has_question' => $hasQuestion,
            'has_link' => $hasLink,
            'has_social_proof' => $hasSocial,
            'cta_type' => $cta,
            'tone' => $tone,
        ];
    }

    private function stripHtml($html)
    {
        $text = preg_replace('/<\s*br\s*\/?>/i', "\n", (string)$html);
        $text = preg_replace('/<\/(p|div|li)>/i', "\n", $text);
        $text = strip_tags($text);
        return trim(html_entity_decode($text, ENT_QUOTES, 'UTF-8'));
    }

    // =================================================================
    // 2) Desfecho por lead (o placar / funil)
    // =================================================================

    /**
     * Garante que exista o registro de outcome do participante, criando-o
     * (com o contexto do lead) na primeira vez. Idempotente.
     */
    public function ensureOutcome($participant, $extra = [])
    {
        if (!$this->ready()) return;
        try {
            $pid = (int)($participant['id'] ?? 0);
            if (!$pid) return;
            $exists = $this->db->fetch("SELECT id FROM prospecting_lead_outcome WHERE participant_id = ?", [$pid]);
            if ($exists) {
                if (!empty($extra)) $this->db->update('prospecting_lead_outcome', $extra, 'participant_id = ?', [$pid]);
                return;
            }
            $contactId = (int)$participant['contact_id'];
            $ctx = $this->leadContext($contactId);
            $this->db->insert('prospecting_lead_outcome', array_merge([
                'contact_id'        => $contactId,
                'sequence_id'       => $participant['sequence_id'] ?? null,
                'participant_id'    => $pid,
                'ab_variant'        => $participant['ab_variant'] ?? null,
                'lead_title'        => $ctx['title'],
                'lead_industry'     => $ctx['industry'],
                'lead_company_size' => $ctx['company_size'],
                'lead_region'       => $ctx['region'],
                'stage'             => 'sent',
                'sent_at'           => date('Y-m-d H:i:s'),
            ], $extra));
        } catch (\Throwable $e) {
            Logger::error('ProspectingAnalytics ensureOutcome', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Atualiza o desfecho do lead avançando o estágio do funil. Só avança (nunca
     * regride), para o funil refletir o ponto mais avançado atingido.
     * @param string $stage sent|opened|replied|interested|scheduled|attended|won|lost
     */
    public function markStage($participant, $stage, $fields = [])
    {
        if (!$this->ready()) return;
        try {
            $this->ensureOutcome($participant);
            $pid = (int)($participant['id'] ?? 0);
            if (!$pid) return;

            $order = ['sent' => 0, 'opened' => 1, 'replied' => 2, 'interested' => 3, 'scheduled' => 4, 'attended' => 5, 'won' => 6, 'lost' => 3];
            $cur = $this->db->fetch("SELECT stage FROM prospecting_lead_outcome WHERE participant_id = ?", [$pid]);
            $curStage = $cur['stage'] ?? 'sent';

            $upd = $fields;
            // 'lost' é terminal lateral: sempre pode marcar. Demais só avançam.
            if ($stage === 'lost' || ($order[$stage] ?? 0) >= ($order[$curStage] ?? 0)) {
                $upd['stage'] = $stage;
            }
            if (!empty($upd)) $this->db->update('prospecting_lead_outcome', $upd, 'participant_id = ?', [$pid]);
        } catch (\Throwable $e) {
            Logger::error('ProspectingAnalytics markStage', ['error' => $e->getMessage()]);
        }
    }

    /** Contexto do lead (cargo/setor/porte/região) a partir do CRM/briefing. */
    private function leadContext($contactId)
    {
        $out = ['title' => null, 'industry' => null, 'company_size' => null, 'region' => null];
        try {
            $c = $this->db->fetch("SELECT contact_name, lead_title, company_name, city, state FROM whatsapp_contacts WHERE id = ?", [$contactId]);
            if ($c) {
                $out['title'] = $c['lead_title'] ?? null;
                $out['region'] = trim(($c['city'] ?? '') . (($c['city'] ?? '') && ($c['state'] ?? '') ? '/' : '') . ($c['state'] ?? '')) ?: null;
            }
        } catch (\Throwable $e) { /* colunas podem variar */ }
        try {
            $b = $this->db->fetch("SELECT industry, company_size, company_sector FROM commercial_briefings WHERE contact_id = ? LIMIT 1", [$contactId]);
            if ($b) {
                $out['industry'] = $b['industry'] ?? ($b['company_sector'] ?? null);
                $out['company_size'] = $b['company_size'] ?? null;
            }
        } catch (\Throwable $e) { /* tabela/colunas podem variar */ }
        return $out;
    }

    // =================================================================
    // 3) Relatórios (o veredito)
    // =================================================================

    /**
     * Volume de mensagens por canal no período, contando direto das tabelas reais
     * (email_messages / whatsapp_messages) — fonte confiável, independente do
     * analytics. Separa e-mail (enviados/respondidos) e WhatsApp (enviados/recebidos).
     */
    public function messageVolume($days = 90)
    {
        $since = date('Y-m-d 00:00:00', strtotime("-{$days} days"));
        $out = [
            'email_sent' => 0, 'email_replied' => 0, 'email_reply_rate' => 0,
            'wa_sent' => 0, 'wa_received' => 0, 'wa_reply_rate' => 0,
        ];
        try {
            // E-mails de sequência enviados e quantos foram respondidos.
            $e = $this->db->fetch(
                "SELECT
                    SUM(CASE WHEN direction='outbound' THEN 1 ELSE 0 END) AS sent,
                    SUM(CASE WHEN direction='outbound' AND replied_at IS NOT NULL THEN 1 ELSE 0 END) AS replied
                 FROM email_messages
                 WHERE origin='sequence' AND COALESCE(sent_at, created_at) >= ?",
                [$since]
            );
            if ($e) {
                $out['email_sent'] = (int)$e['sent'];
                $out['email_replied'] = (int)$e['replied'];
                $out['email_reply_rate'] = $out['email_sent'] ? round($out['email_replied'] / $out['email_sent'] * 100, 1) : 0;
            }
        } catch (\Throwable $ex) { /* ignore */ }

        try {
            // WhatsApp: enviados pela prospecção (from_me=1) e recebidos (from_me=0),
            // apenas de contatos que participam/participaram de alguma sequência.
            $w = $this->db->fetch(
                "SELECT
                    SUM(CASE WHEN wm.from_me=1 THEN 1 ELSE 0 END) AS sent,
                    SUM(CASE WHEN wm.from_me=0 THEN 1 ELSE 0 END) AS received
                 FROM whatsapp_messages wm
                 WHERE wm.timestamp >= ?
                   AND wm.contact_id IN (SELECT DISTINCT contact_id FROM sequence_participants)",
                [$since]
            );
            if ($w) {
                $out['wa_sent'] = (int)$w['sent'];
                $out['wa_received'] = (int)$w['received'];
                $out['wa_reply_rate'] = $out['wa_sent'] ? round($out['wa_received'] / $out['wa_sent'] * 100, 1) : 0;
            }
        } catch (\Throwable $ex) { /* ignore */ }

        return $out;
    }

    /**
     * Funil consolidado no período: quantos leads em cada estágio + taxas.
     */
    public function funnel($days = 90)
    {
        if (!$this->ready()) return null;
        try {
            $since = date('Y-m-d 00:00:00', strtotime("-{$days} days"));
            $r = $this->db->fetch(
                "SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN replied_at IS NOT NULL THEN 1 ELSE 0 END) AS replied,
                    SUM(CASE WHEN interest='positive' THEN 1 ELSE 0 END) AS interested,
                    SUM(CASE WHEN scheduled_at IS NOT NULL THEN 1 ELSE 0 END) AS scheduled,
                    SUM(CASE WHEN attended_at IS NOT NULL THEN 1 ELSE 0 END) AS attended,
                    SUM(CASE WHEN won_at IS NOT NULL THEN 1 ELSE 0 END) AS won
                 FROM prospecting_lead_outcome
                 WHERE created_at >= ?",
                [$since]
            );
            $total = (int)($r['total'] ?? 0);
            $rate = fn($n) => $total ? round($n / $total * 100, 1) : 0;
            return [
                'total' => $total,
                'replied' => (int)$r['replied'],
                'interested' => (int)$r['interested'],
                'scheduled' => (int)$r['scheduled'],
                'attended' => (int)$r['attended'],
                'won' => (int)$r['won'],
                'reply_rate' => $rate((int)$r['replied']),
                'interest_rate' => $rate((int)$r['interested']),
                'meeting_rate' => $rate((int)$r['scheduled']),
            ];
        } catch (\Throwable $e) {
            Logger::error('ProspectingAnalytics funnel', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Ranking de mensagens por taxa de reunião agendada. Junta o log de mensagens
     * (estímulo + atributos) com o desfecho dos leads que as receberam, agrupando
     * por sequência+bloco+variante. Ordena pela taxa de reunião (o que importa).
     */
    public function messageRanking($days = 90, $minSent = 1)
    {
        if (!$this->ready()) return [];
        try {
            $since = date('Y-m-d 00:00:00', strtotime("-{$days} days"));
            // Métricas de desfecho por (sequência, variante).
            $rows = $this->db->fetchAll(
                "SELECT o.sequence_id, o.ab_variant,
                        s.name AS sequence_name,
                        COUNT(*) AS sent,
                        SUM(CASE WHEN o.replied_at IS NOT NULL THEN 1 ELSE 0 END) AS replied,
                        SUM(CASE WHEN o.interest='positive' THEN 1 ELSE 0 END) AS interested,
                        SUM(CASE WHEN o.scheduled_at IS NOT NULL THEN 1 ELSE 0 END) AS scheduled
                 FROM prospecting_lead_outcome o
                 LEFT JOIN email_sequences s ON s.id = o.sequence_id
                 WHERE o.created_at >= ?
                 GROUP BY o.sequence_id, o.ab_variant, s.name
                 HAVING sent >= ?
                 ORDER BY (scheduled / GREATEST(sent,1)) DESC, replied DESC",
                [$since, $minSent]
            );
            $out = [];
            foreach ($rows as $r) {
                $sent = (int)$r['sent'];
                $variant = $r['ab_variant'] ?: '—';
                // Amostra do texto e atributos predominantes dessa variante.
                $sample = $this->variantSample((int)$r['sequence_id'], $r['ab_variant']);
                $out[] = [
                    'sequence_id' => (int)$r['sequence_id'],
                    'sequence_name' => $r['sequence_name'] ?: ('Seq #' . $r['sequence_id']),
                    'variant' => $variant,
                    'sent' => $sent,
                    'replied' => (int)$r['replied'],
                    'interested' => (int)$r['interested'],
                    'scheduled' => (int)$r['scheduled'],
                    'reply_rate' => $sent ? round($r['replied'] / $sent * 100, 1) : 0,
                    'meeting_rate' => $sent ? round($r['scheduled'] / $sent * 100, 1) : 0,
                    'sample_subject' => $sample['subject'] ?? null,
                    'sample_body' => $sample['body'] ?? null,
                    'attributes' => $sample['attributes'] ?? null,
                ];
            }
            return $out;
        } catch (\Throwable $e) {
            Logger::error('ProspectingAnalytics messageRanking', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Ranking por TEMPLATE/MENSAGEM e canal: cruza cada mensagem enviada com o
     * desfecho do lead que a recebeu, mostrando interações positivas x negativas.
     * Identidade do template: assunto (e-mail) ou início do corpo (WhatsApp).
     * @param string|null $channel 'email' | 'whatsapp' | null (ambos)
     */
    public function templateRanking($days = 90, $channel = null)
    {
        if (!$this->ready()) return [];
        try {
            $since = date('Y-m-d 00:00:00', strtotime("-{$days} days"));
            // Chave do template: assunto no e-mail; primeiros 80 chars do corpo no WhatsApp.
            $tplKey = "CASE WHEN l.channel='email' THEN COALESCE(NULLIF(l.subject,''), LEFT(l.body,80))
                            ELSE LEFT(l.body,80) END";
            $params = [$since];
            $chSql = '';
            if (in_array($channel, ['email', 'whatsapp'], true)) { $chSql = " AND l.channel = ?"; $params[] = $channel; }

            // Desfecho consolidado POR CONTATO (não por participante): o lead pode
            // ter vários participantes (cadência + triagem). A classificação
            // negativa/positiva costuma ocorrer no participante de triagem, mas as
            // mensagens estão no da cadência. Por isso agregamos por contact_id:
            //   negativo se QUALQUER participante do contato terminou negativo;
            //   agendou/positivo idem. Assim a negativa aparece nas mensagens do lead.
            $rows = $this->db->fetchAll(
                "SELECT l.channel,
                        $tplKey AS tpl,
                        COUNT(*) AS sent,
                        SUM(CASE WHEN co.replied = 1 THEN 1 ELSE 0 END) AS replied,
                        SUM(CASE WHEN co.negative = 0 AND co.positive = 1 THEN 1 ELSE 0 END) AS positive,
                        SUM(CASE WHEN co.negative = 1 THEN 1 ELSE 0 END) AS negative,
                        SUM(CASE WHEN co.scheduled = 1 THEN 1 ELSE 0 END) AS scheduled,
                        MAX(l.subject) AS subject,
                        MAX(l.body) AS body
                 FROM prospecting_message_log l
                 LEFT JOIN (
                     SELECT contact_id,
                            MAX(CASE WHEN replied_at IS NOT NULL THEN 1 ELSE 0 END) AS replied,
                            MAX(CASE WHEN interest='positive' THEN 1 ELSE 0 END) AS positive,
                            MAX(CASE WHEN interest='negative' THEN 1 ELSE 0 END) AS negative,
                            MAX(CASE WHEN scheduled_at IS NOT NULL THEN 1 ELSE 0 END) AS scheduled
                     FROM prospecting_lead_outcome
                     GROUP BY contact_id
                 ) co ON co.contact_id = l.contact_id
                 WHERE l.sent_at >= ? $chSql
                 GROUP BY l.channel, tpl
                 HAVING sent >= 1
                 ORDER BY negative DESC, positive DESC, scheduled DESC, replied DESC, sent DESC",
                $params
            );
            $out = [];
            foreach ($rows as $r) {
                $sent = (int)$r['sent'];
                $out[] = [
                    'channel' => $r['channel'],
                    'title' => ($r['channel'] === 'email' && $r['subject']) ? $r['subject'] : mb_substr((string)$r['body'], 0, 80),
                    'sample' => mb_substr((string)$r['body'], 0, 160),
                    'sent' => $sent,
                    'replied' => (int)$r['replied'],
                    'positive' => (int)$r['positive'],
                    'negative' => (int)$r['negative'],
                    'scheduled' => (int)$r['scheduled'],
                    'reply_rate' => $sent ? round($r['replied'] / $sent * 100, 1) : 0,
                    'positive_rate' => $sent ? round($r['positive'] / $sent * 100, 1) : 0,
                ];
            }
            return $out;
        } catch (\Throwable $e) {
            Logger::error('ProspectingAnalytics templateRanking', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /** Amostra do texto + atributos predominantes de uma variante de sequência. */
    private function variantSample($sequenceId, $variant)
    {
        try {
            $where = "sequence_id = ?"; $params = [$sequenceId];
            if ($variant) { $where .= " AND ab_variant = ?"; $params[] = $variant; }
            else { $where .= " AND ab_variant IS NULL"; }
            $m = $this->db->fetch(
                "SELECT subject, body, cta_type, tone, has_number, has_social_proof, len_chars
                 FROM prospecting_message_log WHERE $where ORDER BY id DESC LIMIT 1",
                $params
            );
            if (!$m) return [];
            return [
                'subject' => $m['subject'],
                'body' => $m['body'] ? mb_substr($m['body'], 0, 240) : null,
                'attributes' => [
                    'cta' => $m['cta_type'],
                    'tom' => $m['tone'],
                    'numero' => (int)$m['has_number'] ? 'sim' : 'não',
                    'prova_social' => (int)$m['has_social_proof'] ? 'sim' : 'não',
                    'tamanho' => (int)$m['len_chars'] . ' car.',
                ],
            ];
        } catch (\Throwable $e) {
            return [];
        }
    }
}

