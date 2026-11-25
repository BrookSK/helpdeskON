<?php
/**
 * Regras puras do e-mail DEVOLVIDO (bounce / "Delivery Status Notification").
 *
 * Quando um e-mail de prospecção não é entregue, o servidor (ex.: Gmail,
 * Outlook) manda de volta uma notificação de falha. Esta classe decide:
 *  - se a mensagem recebida é uma notificação de falha de entrega;
 *  - para qual endereço o e-mail não foi entregue;
 *  - se é DEFINITIVA (endereço não existe → o lead sai das sequências e o
 *    e-mail fica bloqueado) ou TEMPORÁRIA (caixa cheia, bloqueio por política,
 *    atraso → só registra, o lead continua).
 *
 * Só é definitiva quando há certeza de que o ENDEREÇO é inválido. Bloqueio por
 * spam/política (5.7.x) não prova que o e-mail está errado, então não remove.
 */
class EmailBounceRules
{
    /** Remetentes típicos de notificação de falha de entrega. */
    const DAEMON_SENDERS = ['mailer-daemon', 'postmaster', 'mail delivery subsystem', 'mail delivery system', 'microsoftexchange'];

    /** Assuntos típicos (pt/en). "Delay"/"atraso" também conta, mas é temporário. */
    const SUBJECT_PATTERNS = [
        'delivery status notification', 'undeliverable', 'undelivered mail', 'mail delivery failed',
        'returned mail', 'delivery failure', 'failure notice', 'mail delivery failure',
        'não foi possível entregar', 'nao foi possivel entregar', 'não entregue', 'nao entregue',
        'falha na entrega', 'mensagem não entregue', 'mensagem nao entregue', 'delivery has failed',
    ];

    /** Frases que indicam endereço inexistente/inválido (definitivo). */
    const HARD_PHRASES = [
        'address not found', "address couldn't be found", 'address could not be found', 'does not exist',
        "doesn't exist", 'user unknown', 'unknown user', 'no such user', 'no such recipient', 'no such mailbox',
        'recipient not found', 'recipient address rejected', 'invalid recipient', 'invalid address',
        'mailbox not found', 'mailbox unavailable', 'mailbox does not exist', 'account has been disabled',
        'account is disabled', 'user not found', 'recipient unknown', 'domain name not found',
        'domain not found', 'dns error', 'host or domain name not found', "couldn't be delivered to",
        'não existe', 'nao existe', 'endereço não encontrado', 'endereco nao encontrado',
        'usuário desconhecido', 'usuario desconhecido', 'destinatário não encontrado', 'destinatario nao encontrado',
    ];

    /** Frases de falha temporária (não remove o lead). */
    const SOFT_PHRASES = [
        'mailbox full', 'mailbox is full', 'quota', 'over quota', 'storage', 'temporarily', 'temporary',
        'try again', 'will retry', 'delayed', 'delay', 'caixa cheia', 'caixa de correio cheia',
        'tentaremos novamente', 'atraso',
    ];

    /** Códigos de status que comprovam endereço/domínio inválido. */
    const HARD_STATUS = ['5.1.1', '5.1.2', '5.1.3', '5.1.6', '5.1.10', '5.2.1', '5.4.4', '5.4.1'];

    /**
     * A mensagem é uma notificação de falha de entrega?
     * @param array $m from, from_email, subject, content_type (strings)
     */
    public static function isBounce(array $m): bool
    {
        $ctype = strtolower((string) ($m['content_type'] ?? ''));
        if (strpos($ctype, 'report-type=delivery-status') !== false) return true;

        $from = strtolower(($m['from'] ?? '') . ' ' . ($m['from_email'] ?? ''));
        $fromDaemon = false;
        foreach (self::DAEMON_SENDERS as $s) {
            if (strpos($from, $s) !== false) { $fromDaemon = true; break; }
        }

        $subject = mb_strtolower(trim((string) ($m['subject'] ?? '')));
        $subjectBounce = false;
        foreach (self::SUBJECT_PATTERNS as $p) {
            if ($subject !== '' && mb_strpos($subject, $p) !== false) { $subjectBounce = true; break; }
        }

        // Remetente do sistema de e-mail OU assunto de falha. Um "postmaster"
        // escrevendo sobre outra coisa não tem o assunto e nem é DSN; exigir um dos
        // dois sinais fortes já evita confundir com e-mail comum.
        return $fromDaemon || $subjectBounce;
    }

    /**
     * Lê a notificação e devolve o que foi possível descobrir.
     *
     * @param array  $m    cabeçalhos (subject, x_failed_recipients, in_reply_to, references)
     * @param string $text corpo da notificação (texto + partes de status e o cabeçalho original)
     * @return array {
     *   recipients: string[] endereços que falharam (vindos de campos específicos),
     *   mentioned:  string[] todos os e-mails citados no corpo (o serviço só usa os que nós enviamos),
     *   token:      ?string  token do nosso envio (Message-ID original citado),
     *   status:     ?string  código como "5.1.1",
     *   hard:       bool     definitivo (endereço inválido),
     *   reason:     string   motivo em português, para mostrar ao usuário
     * }
     */
    public static function parse(array $m, string $text): array
    {
        $recipients = [];
        $add = function ($email) use (&$recipients) {
            $e = self::cleanEmail($email);
            if ($e !== null && !in_array($e, $recipients, true)) $recipients[] = $e;
        };

        foreach (preg_split('/[,;\s]+/', (string) ($m['x_failed_recipients'] ?? '')) as $e) $add($e);

        $patterns = [
            '/(?:Final|Original)-Recipient:\s*(?:rfc822;)?\s*<?([^\s<>;]+@[^\s<>;]+)>?/i',
            "/wasn(?:'|’)?t delivered to\s*<?([^\s<>]+@[^\s<>]+?)>?\s+because/i",
            '/delivery to the following recipients? (?:failed|has failed)[^\n]*\n\s*<?([^\s<>]+@[^\s<>]+)>?/i',
            "/couldn(?:'|’)?t be delivered to\s*<?([^\s<>]+@[^\s<>]+?)>?[\s.,]/i",
            '/n[ãa]o (?:foi|pôde ser|pode ser) entregue (?:para|a)\s*<?([^\s<>]+@[^\s<>]+?)>?[\s.,]/iu',
            '/^\s*<([^\s<>]+@[^\s<>]+)>:\s/m', // Postfix: "<x@y>: host ... said: 550 ..."
        ];
        foreach ($patterns as $re) {
            if (preg_match_all($re, $text, $mm)) foreach ($mm[1] as $e) $add($e);
        }

        $mentioned = [];
        if (preg_match_all('/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/', $text, $mm)) {
            foreach ($mm[0] as $e) {
                $e = self::cleanEmail($e);
                if ($e !== null && !in_array($e, $mentioned, true)) $mentioned[] = $e;
            }
        }

        // Token do nosso envio: o cabeçalho original vem citado no corpo.
        $token = EmailReplyRules::findTokenInReply([
            'in_reply_to' => $m['in_reply_to'] ?? '', 'references' => $m['references'] ?? '',
        ]) ?? EmailReplyRules::extractToken($text);

        $status = null;
        if (preg_match('/^\s*Status:\s*([245]\.\d{1,3}\.\d{1,3})/mi', $text, $sm)) {
            $status = $sm[1];
        } elseif (preg_match('/(?<![\d.])([45]\.\d{1,2}\.\d{1,3})(?![\d.])/', $text, $sm)) {
            $status = $sm[1];
        }

        [$hard, $reason] = self::classify($status, (string) ($m['subject'] ?? ''), $text);

        return [
            'recipients' => $recipients,
            'mentioned' => $mentioned,
            'token' => $token,
            'status' => $status,
            'hard' => $hard,
            'reason' => $reason,
        ];
    }

    /** @return array [bool $hard, string $reason] */
    public static function classify(?string $status, string $subject, string $text): array
    {
        $low = mb_strtolower($subject . "\n" . $text);
        $code = $status ? ' (' . $status . ')' : '';

        if ($status !== null) {
            if ($status[0] === '4') return [false, 'Falha temporária na entrega' . $code . '. O lead continua.'];
            if (in_array($status, self::HARD_STATUS, true)) {
                return [true, (strpos($status, '5.1.2') === 0 || $status === '5.4.4')
                    ? 'Domínio do e-mail não existe' . $code
                    : 'Endereço de e-mail não existe' . $code];
            }
            if (strpos($status, '5.2.2') === 0) return [false, 'Caixa de entrada do lead cheia' . $code . '. O lead continua.'];
            if (strpos($status, '5.7.') === 0) return [false, 'Servidor do lead recusou por política/spam' . $code . '. O e-mail pode estar certo; o lead continua.'];
        }

        // Atraso não é falha definitiva, mesmo com frases de "não encontrado" no corpo.
        if (preg_match('/\((?:delay|atraso)\)|delivery delayed|entrega atrasada|still trying|ainda tentando/i', $low)) {
            return [false, 'Entrega atrasada' . $code . '. O servidor ainda vai tentar; o lead continua.'];
        }
        foreach (self::HARD_PHRASES as $p) {
            if (mb_strpos($low, $p) !== false) return [true, 'Endereço de e-mail não existe' . $code];
        }
        foreach (self::SOFT_PHRASES as $p) {
            if (mb_strpos($low, $p) !== false) return [false, 'Falha temporária na entrega' . $code . '. O lead continua.'];
        }
        return [false, 'Falha na entrega sem motivo claro' . $code . '. O lead continua; confira o e-mail na Caixa de entrada.'];
    }

    /**
     * Trocar o e-mail do lead libera o bloqueio de "e-mail devolvido"? Só quando o
     * endereço muda de fato (o bloqueio era do endereço antigo).
     */
    public static function shouldClearBounce(?string $oldEmail, ?string $newEmail): bool
    {
        $old = mb_strtolower(trim((string) $oldEmail));
        $new = mb_strtolower(trim((string) $newEmail));
        return $new !== '' && $new !== $old;
    }

    /** Normaliza um endereço (minúsculas, sem <> e pontuação). Ignora remetentes do sistema. */
    public static function cleanEmail($email): ?string
    {
        $e = mb_strtolower(trim((string) $email, " \t\r\n<>\"'.,;:()[]"));
        $e = preg_replace('/^(rfc822;|mailto:)\s*/', '', $e);
        if (!filter_var($e, FILTER_VALIDATE_EMAIL)) return null;
        foreach (self::DAEMON_SENDERS as $s) {
            if (strpos($e, str_replace(' ', '', $s)) === 0) return null;
        }
        return $e;
    }
}
