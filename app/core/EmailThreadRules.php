<?php
/**
 * Regras puras do histórico de e-mails do lead (aba "E-mails" do card no CRM).
 *
 * Junta o que o sistema registrou de e-mail com o lead:
 *  - email_messages: envios (sequência e envio manual) e respostas recebidas;
 *  - email_prospections: respostas enviadas pela Caixa de entrada. Os envios que
 *    o EmailMessageService já espelha ali são descartados (mesmo assunto e
 *    mesmo horário), para não aparecer o mesmo e-mail duas vezes.
 * e agrupa tudo em conversas (assunto sem "Re:"/"Fwd:" + cabeçalhos de resposta).
 */
class EmailThreadRules
{
    /** Diferença máxima (s) entre um envio e o espelho dele em email_prospections. */
    const MIRROR_WINDOW = 180;

    /** Tamanho do resumo mostrado antes de abrir o e-mail completo. */
    const PREVIEW_LEN = 220;

    /** Texto máximo guardado de uma resposta recebida. */
    const REPLY_MAX = 20000;

    // ------------------------------------------------------------------
    // Texto da resposta
    // ------------------------------------------------------------------

    /**
     * Texto que o lead escreveu, sem a citação do e-mail anterior ("Em ...
     * escreveu:", linhas com ">", "-----Mensagem original-----", cabeçalho do
     * Outlook "De:/Enviado:"). Mantém as quebras de linha.
     * Se tudo for citação, devolve o texto inteiro.
     */
    public static function replyText(string $text, int $maxLen = self::REPLY_MAX): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $lines = explode("\n", $text);
        $out = [];
        $count = count($lines);
        for ($i = 0; $i < $count; $i++) {
            $t = trim($lines[$i]);
            if (self::isQuoteStart($t, trim($lines[$i + 1] ?? ''))) break;
            $out[] = rtrim($lines[$i]);
        }
        $clean = self::tidy(implode("\n", $out));
        if ($clean === '') $clean = self::tidy($text);
        return mb_substr($clean, 0, $maxLen);
    }

    /** A linha começa a citação do e-mail anterior? */
    private static function isQuoteStart(string $line, string $next): bool
    {
        if ($line === '') return false;
        if (strpos($line, '>') === 0) return true;
        if (preg_match('/^-{2,}\s*(original message|mensagem original|forwarded message|mensagem encaminhada)\s*-*$/iu', $line)) return true;
        if (preg_match('/^_{8,}$/', $line)) return true; // separador do Outlook
        if (preg_match('/^(on|em)\s.+\s(wrote|escreveu)\s*:$/iu', $line)) return true;
        // Gmail quebra "Em qua., 24 de nov. de 2026 às 10:00, Fulano <x@y>" e "escreveu:" em duas linhas.
        if (mb_strlen($line) < 220 && preg_match('/^(on|em)\s.+/iu', $line) && preg_match('/^(.*\s)?(wrote|escreveu)\s*:$/iu', $next)) return true;
        // Outlook: "De: Fulano" seguido de "Enviado:"/"Sent:"/"Data:"
        if (preg_match('/^(de|from)\s*:/iu', $line) && preg_match('/^(enviad[oa](\s+em)?|sent|data|date)\s*:/iu', $next)) return true;
        return false;
    }

    /** Converte HTML de e-mail em texto, mantendo parágrafos e marcando citações. */
    public static function htmlToText(string $html): string
    {
        $html = preg_replace('#<(script|style|head|title)\b[^>]*>.*?</\1>#is', '', $html);
        $html = preg_replace('#<blockquote\b[^>]*>#i', "\n> ", $html);
        $html = preg_replace('#<br\s*/?>#i', "\n", $html);
        $html = preg_replace('#</(p|div|li|tr|h[1-6]|blockquote|table)>#i', "\n", $html);
        $html = preg_replace('#<li\b[^>]*>#i', '• ', $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t\x{00A0}]+/u', ' ', $text);
        return self::tidy($text);
    }

    /** Apara as linhas e junta 3+ quebras seguidas numa linha em branco só. */
    private static function tidy(string $text): string
    {
        $lines = array_map('trim', explode("\n", str_replace(["\r\n", "\r"], "\n", $text)));
        $text = implode("\n", $lines);
        return trim(preg_replace("/\n{3,}/", "\n\n", $text));
    }

    /** Resumo em uma linha (para a lista, antes de abrir o e-mail). */
    public static function preview(string $text, int $len = self::PREVIEW_LEN): string
    {
        $one = trim(preg_replace('/\s+/u', ' ', $text));
        return mb_strlen($one) > $len ? rtrim(mb_substr($one, 0, $len - 1)) . '…' : $one;
    }

    /** Assunto sem "Re:", "RES:", "Fwd:", "ENC:"... (para juntar a conversa). */
    public static function normalizeSubject(?string $subject): string
    {
        $s = trim((string) $subject);
        do {
            $prev = $s;
            $s = preg_replace('/^\s*(re|res|resp|fw|fwd|enc|tr|aw|wg)\s*(\[\d+\])?\s*:\s*/iu', '', $s);
        } while ($s !== $prev);
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($s)));
    }

    // ------------------------------------------------------------------
    // Junção das fontes
    // ------------------------------------------------------------------

    /**
     * Unifica as linhas de email_messages e email_prospections num formato só.
     *
     * @param array $messages     linhas de email_messages (+ sender_name, sequence_name)
     * @param array $prospections linhas de email_prospections (+ sender_name)
     * @return array itens (ver item()) em ordem cronológica
     */
    public static function merge(array $messages, array $prospections): array
    {
        $items = [];
        $outbound = [];
        foreach ($messages as $m) {
            $item = self::fromMessage($m);
            $items[] = $item;
            if ($item['direction'] === 'out') $outbound[] = $item;
        }
        foreach ($prospections as $p) {
            if (($p['status'] ?? '') === 'draft') continue;
            $item = self::fromProspection($p);
            if (self::isMirror($item, $outbound)) continue;
            $items[] = $item;
        }
        usort($items, function ($a, $b) {
            return [strtotime((string) $a['at']) ?: 0, $a['key']] <=> [strtotime((string) $b['at']) ?: 0, $b['key']];
        });
        return $items;
    }

    /** O registro de email_prospections é só o espelho de um envio já listado? */
    public static function isMirror(array $item, array $outbound): bool
    {
        $subject = self::normalizeSubject($item['subject']);
        $t = strtotime((string) $item['at']) ?: 0;
        foreach ($outbound as $o) {
            if (self::normalizeSubject($o['subject']) !== $subject) continue;
            $ot = strtotime((string) $o['at']) ?: 0;
            if (abs($t - $ot) <= self::MIRROR_WINDOW) return true;
        }
        return false;
    }

    private static function fromMessage(array $m): array
    {
        $in = ($m['direction'] ?? 'outbound') === 'inbound';
        if ($in) {
            $text = trim((string) ($m['body'] ?? ''));
            if ($text === '') $text = trim((string) ($m['reply_snippet'] ?? ''));
            $at = ($m['received_at'] ?? null) ?: ($m['created_at'] ?? null);
            return self::item([
                'key' => 'm' . (int) $m['id'],
                'direction' => 'in',
                'source' => 'lead',
                'subject' => $m['subject'] ?? '',
                'at' => $at,
                'status' => 'received',
                'from' => $m['recipient_email'] ?? null,
                'body_text' => $text,
                'preview' => self::preview($text),
                'message_id' => $m['message_id'] ?? null,
                'in_reply_to' => $m['in_reply_to'] ?? null,
                'references' => $m['references_header'] ?? null,
            ]);
        }
        $html = (string) ($m['body'] ?? '');
        $source = ($m['origin'] ?? 'manual') === 'sequence' ? 'sequence' : 'manual';
        return self::item([
            'key' => 'm' . (int) $m['id'],
            'direction' => 'out',
            'source' => $source,
            'subject' => $m['subject'] ?? '',
            'at' => ($m['sent_at'] ?? null) ?: ($m['created_at'] ?? null),
            'status' => $m['status'] ?? 'sent',
            'error' => $m['error_message'] ?? null,
            'sender' => $m['sender_name'] ?? null,
            'sequence' => $source === 'sequence' ? ($m['sequence_name'] ?? null) : null,
            'to' => $m['recipient_email'] ?? null,
            'opens' => (int) ($m['open_count'] ?? 0),
            'clicks' => (int) ($m['click_count'] ?? 0),
            'first_open_at' => $m['first_open_at'] ?? null,
            'replied_at' => $m['replied_at'] ?? null,
            'bounced_at' => $m['bounced_at'] ?? null,
            'body_html' => $html !== '' ? $html : null,
            'preview' => $html !== '' ? self::preview(self::htmlToText($html)) : '',
            'message_id' => $m['message_id'] ?? null,
        ]);
    }

    private static function fromProspection(array $p): array
    {
        $html = (string) ($p['body'] ?? '');
        $subject = (string) ($p['subject'] ?? '');
        return self::item([
            'key' => 'p' . (int) $p['id'],
            'direction' => 'out',
            // Assunto "Re: ..." = resposta pela Caixa de entrada; senão envio avulso.
            'source' => preg_match('/^\s*(re|res|resp)\s*(\[\d+\])?\s*:/iu', $subject) ? 'inbox_reply' : 'manual',
            'subject' => $subject,
            'at' => ($p['sent_at'] ?? null) ?: ($p['created_at'] ?? null),
            'status' => $p['status'] ?? 'sent',
            'error' => $p['error_message'] ?? null,
            'sender' => $p['sender_name'] ?? null,
            'to' => $p['recipient_email'] ?? null,
            'body_html' => $html !== '' ? $html : null,
            'preview' => $html !== '' ? self::preview(self::htmlToText($html)) : '',
        ]);
    }

    /** Item com todas as chaves (o front não precisa testar existência). */
    private static function item(array $v): array
    {
        return array_merge([
            'key' => '', 'direction' => 'out', 'source' => 'manual', 'subject' => '', 'at' => null,
            'status' => 'sent', 'error' => null, 'sender' => null, 'sequence' => null, 'to' => null, 'from' => null,
            'opens' => 0, 'clicks' => 0, 'first_open_at' => null, 'replied_at' => null, 'bounced_at' => null,
            'body_html' => null, 'body_text' => null, 'preview' => '',
            'message_id' => null, 'in_reply_to' => null, 'references' => null,
        ], $v);
    }

    // ------------------------------------------------------------------
    // Conversas
    // ------------------------------------------------------------------

    /**
     * Agrupa os itens em conversas. A resposta do lead entra na conversa do
     * e-mail que ela responde (In-Reply-To/References = Message-ID do envio);
     * sem esse vínculo, junta pelo assunto sem "Re:".
     *
     * @param array $items saída de merge() (ordem cronológica)
     * @return array ['summary' => [...], 'conversations' => [...]] (conversa mais recente primeiro)
     */
    public static function conversations(array $items): array
    {
        $convs = [];
        $byMessageId = [];
        $bySubject = [];

        foreach ($items as $it) {
            $idx = null;
            if ($it['direction'] === 'in') {
                $refs = trim(($it['in_reply_to'] ?? '') . ' ' . ($it['references'] ?? ''));
                if ($refs !== '' && preg_match_all('/<[^>]+>|\S+@\S+/', $refs, $mm)) {
                    foreach (array_reverse($mm[0]) as $ref) {
                        $k = self::messageKey($ref);
                        if (isset($byMessageId[$k])) { $idx = $byMessageId[$k]; break; }
                    }
                }
            }
            $subjectKey = self::normalizeSubject($it['subject']);
            if ($idx === null && $subjectKey !== '' && isset($bySubject[$subjectKey])) $idx = $bySubject[$subjectKey];
            if ($idx === null) {
                $idx = count($convs);
                $convs[] = ['subject' => trim((string) $it['subject']) !== '' ? $it['subject'] : '(sem assunto)', 'items' => []];
            }
            $convs[$idx]['items'][] = $it;
            if ($subjectKey !== '') $bySubject[$subjectKey] = $idx;
            if (!empty($it['message_id'])) $byMessageId[self::messageKey($it['message_id'])] = $idx;
        }

        $sent = 0; $failed = 0; $bounced = 0; $replies = 0; $lastReply = null; $lastAt = null;
        foreach ($convs as &$c) {
            $c['count'] = count($c['items']);
            $c['last_at'] = end($c['items'])['at'];
            $hasIn = false; $legacyReply = null;
            foreach ($c['items'] as &$it) {
                if ($it['direction'] === 'in') {
                    $hasIn = true; $replies++;
                    if (!$lastReply || strtotime((string) $it['at']) > strtotime((string) $lastReply)) $lastReply = $it['at'];
                } else {
                    if ($it['status'] === 'failed') $failed++; elseif ($it['status'] === 'sent') $sent++;
                    if (!empty($it['bounced_at'])) $bounced++;
                    if (!empty($it['replied_at']) && !$legacyReply) $legacyReply = $it['replied_at'];
                }
                unset($it['message_id'], $it['in_reply_to'], $it['references']);
            }
            unset($it);
            // Resposta marcada no envio, mas sem o texto guardado (detecção antiga).
            $c['reply_without_text_at'] = (!$hasIn && $legacyReply) ? $legacyReply : null;
            if ($c['reply_without_text_at']) {
                $replies++;
                if (!$lastReply || strtotime((string) $legacyReply) > strtotime((string) $lastReply)) $lastReply = $legacyReply;
            }
            $c['has_reply'] = $hasIn || (bool) $c['reply_without_text_at'];
            if (!$lastAt || strtotime((string) $c['last_at']) > strtotime((string) $lastAt)) $lastAt = $c['last_at'];
        }
        unset($c);

        usort($convs, fn($a, $b) => (strtotime((string) $b['last_at']) ?: 0) <=> (strtotime((string) $a['last_at']) ?: 0));

        return [
            'summary' => [
                'total' => count($items),
                'sent' => $sent,
                'failed' => $failed,
                'replies' => $replies,
                'last_reply_at' => $lastReply,
                'last_at' => $lastAt,
                'bounced' => $bounced,
            ],
            'conversations' => $convs,
        ];
    }

    /** Message-ID comparável: sem <>, espaços e maiúsculas. */
    private static function messageKey(string $id): string
    {
        return mb_strtolower(trim($id, " \t\r\n<>"));
    }
}
