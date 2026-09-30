<?php

/**
 * Regras de negócio puras (sem banco/HTTP/IMAP) da DETECÇÃO DE RESPOSTA por
 * e-mail na prospecção.
 *
 * Problema: a caixa de e-mail recebe muitas mensagens não relacionadas (o mesmo
 * lead pode mandar "obrigado", auto-resposta, ou um e-mail sobre outro assunto).
 * Casar resposta só por "veio do endereço do lead + data" gera falsos positivos
 * e dispara a triagem por IA à toa.
 *
 * Solução: carimbamos cada e-mail ENVIADO com um Message-ID único que embute o
 * track_token do envio (ex.: <seq-<token>@dominio>) e também um Reply-To com o
 * token como sub-endereço (+tag). Quando o lead responde, o cliente de e-mail
 * dele devolve esse identificador nos cabeçalhos In-Reply-To / References (e o
 * To/Reply-To carrega o +tag). Assim conseguimos casar a RESPOSTA com o ENVIO
 * de forma inequívoca — e ignorar tudo que não casa.
 *
 * Esta classe é a fonte única/testável dessa lógica: gerar o identificador,
 * extrair o token de cabeçalhos, e decidir se uma mensagem recebida é uma
 * resposta válida, ruído (auto-resposta/bulk/bounce) ou indefinida.
 */
class EmailReplyRules
{
    /** Prefixo do local-part do Message-ID de envio (facilita o reconhecimento). */
    public const MID_PREFIX = 'seq-';

    /** Resultados possíveis da classificação de uma mensagem recebida. */
    public const MATCH = 'match';       // resposta casada com um envio nosso (por token)
    public const NOISE = 'noise';       // auto-resposta / bulk / bounce → ignorar
    public const UNMATCHED = 'unmatched'; // sem âncora nossa → não tratar como resposta

    /**
     * Message-ID determinístico para um envio, embutindo o track_token.
     * Formato: <seq-{token}@{domain}>. O domínio serve só para validade do header.
     */
    public static function buildMessageId(string $token, string $domain): string
    {
        $token = self::sanitizeToken($token);
        $domain = trim($domain) !== '' ? trim($domain) : 'localhost';
        return '<' . self::MID_PREFIX . $token . '@' . $domain . '>';
    }

    /**
     * Endereço de Reply-To com o token como sub-endereço (+tag), quando o provedor
     * suporta. Ex.: contato@empresa.com + token → contato+seq-<token>@empresa.com.
     * Se o e-mail base for inválido, retorna null (não seta Reply-To).
     */
    public static function buildReplyTo(string $baseEmail, string $token): ?string
    {
        $baseEmail = trim($baseEmail);
        $at = strpos($baseEmail, '@');
        if ($at === false || !filter_var($baseEmail, FILTER_VALIDATE_EMAIL)) return null;
        $token = self::sanitizeToken($token);
        $local = substr($baseEmail, 0, $at);
        $domain = substr($baseEmail, $at + 1);
        // Não empilha +tag se o local-part já tiver um "+": mantém simples/limpo.
        if (strpos($local, '+') !== false) return null;
        return $local . '+' . self::MID_PREFIX . $token . '@' . $domain;
    }

    /**
     * Extrai o track_token de qualquer texto de cabeçalho (Message-ID, In-Reply-To,
     * References ou To/Reply-To com +tag). Procura o padrão "seq-{token}".
     * Retorna o token (hex) ou null se não encontrar.
     */
    public static function extractToken(?string $headerValue): ?string
    {
        if (!$headerValue) return null;
        // Casa "seq-" seguido de token hexadecimal (nosso token é bin2hex(16) = 32 hex,
        // mas aceitamos 8..64 para tolerar variações).
        if (preg_match('/' . preg_quote(self::MID_PREFIX, '/') . '([a-f0-9]{8,64})/i', $headerValue, $m)) {
            return strtolower($m[1]);
        }
        return null;
    }

    /**
     * Dado o conjunto de cabeçalhos de uma mensagem RECEBIDA, tenta achar o token
     * do nosso envio. Verifica, em ordem: In-Reply-To, References, To, Reply-To.
     * @param array $headers chaves: in_reply_to, references, to, reply_to (strings)
     */
    public static function findTokenInReply(array $headers): ?string
    {
        foreach (['in_reply_to', 'references', 'to', 'reply_to', 'delivered_to'] as $k) {
            $tok = self::extractToken($headers[$k] ?? null);
            if ($tok) return $tok;
        }
        return null;
    }

    /**
     * A mensagem recebida é RUÍDO (não deve virar resposta): auto-resposta,
     * envio em massa, notificação de entrega/erro (DSN/bounce)?
     *
     * @param array $headers chaves possíveis (strings, case-insensitive nos valores):
     *   auto_submitted, precedence, x_autoreply, x_autorespond, content_type,
     *   from, subject
     */
    public static function isNoise(array $headers): bool
    {
        $auto = strtolower(trim((string) ($headers['auto_submitted'] ?? '')));
        // Auto-Submitted: qualquer coisa != "no" indica mensagem automática (RFC 3834).
        if ($auto !== '' && $auto !== 'no') return true;

        $prec = strtolower(trim((string) ($headers['precedence'] ?? '')));
        if (in_array($prec, ['bulk', 'auto_reply', 'list', 'junk'], true)) return true;

        if (!empty($headers['x_autoreply']) || !empty($headers['x_autorespond'])) return true;

        // Notificações de entrega/erro (DSN / bounce).
        $ctype = strtolower(trim((string) ($headers['content_type'] ?? '')));
        if (strpos($ctype, 'report-type=delivery-status') !== false) return true;

        $from = strtolower(trim((string) ($headers['from'] ?? '')));
        foreach (['mailer-daemon', 'postmaster@', 'no-reply@', 'noreply@', 'donotreply@'] as $needle) {
            if ($from !== '' && strpos($from, $needle) !== false) return true;
        }

        $subject = strtolower(trim((string) ($headers['subject'] ?? '')));
        foreach (['auto-reply', 'automatic reply', 'ausência', 'ausencia', 'out of office',
                  'férias', 'ferias', 'delivery status notification', 'undelivered mail',
                  'mail delivery failed', 'returned mail'] as $needle) {
            if ($subject !== '' && strpos($subject, $needle) !== false) return true;
        }

        return false;
    }

    /**
     * Classifica uma mensagem recebida:
     *   - NOISE     → auto-resposta/bulk/bounce (ignorar sempre);
     *   - MATCH     → casou com um envio nosso (token presente nos cabeçalhos);
     *   - UNMATCHED → sem âncora nossa (não tratar como resposta de sequência).
     *
     * Retorna ['result' => ..., 'token' => ?string].
     *
     * Observação: ruído é checado ANTES do match — uma auto-resposta pode até
     * conter o nosso In-Reply-To (é resposta automática ao nosso e-mail), mas não
     * deve disparar a triagem.
     */
    public static function classify(array $headers): array
    {
        if (self::isNoise($headers)) {
            return ['result' => self::NOISE, 'token' => null];
        }
        $token = self::findTokenInReply($headers);
        if ($token !== null) {
            return ['result' => self::MATCH, 'token' => $token];
        }
        return ['result' => self::UNMATCHED, 'token' => null];
    }

    /** Mantém só caracteres seguros para um token dentro de header/endereço. */
    private static function sanitizeToken(string $token): string
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $token));
    }
}
