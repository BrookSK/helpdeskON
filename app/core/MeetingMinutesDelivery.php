<?php

/**
 * Regras puras (sem banco/HTTP/IA) do ENVIO da minuta/ata (Fase 2 — tarefa #7).
 *
 * Decisão de produto: a minuta NÃO é enviada como arquivo PDF anexado. Em vez
 * disso, geramos uma PÁGINA PÚBLICA imprimível (link por token) e enviamos esse
 * LINK por WhatsApp e por e-mail, para os participantes internos da reunião e
 * para o cliente. O destinatário abre o link, lê e imprime/salva como PDF.
 *
 * Esta classe centraliza, de forma testável:
 *  - montar a lista de destinatários (equipe + cliente), deduplicada e sem vazios;
 *  - montar a mensagem de WhatsApp e o assunto/corpo do e-mail com o link.
 *
 * Os dados chegam prontos (arrays simples) — quem lê do banco é o controller.
 */
class MeetingMinutesDelivery
{
    /**
     * Monta a lista de destinatários a partir dos participantes internos e do
     * "cliente" da reunião. Deduplica por e-mail e por telefone (normalizados),
     * descarta entradas sem nenhum canal de contato.
     *
     * @param array $participants  Lista de ['name','email','phone'] (equipe interna).
     * @param array $client        ['name','email','phone'] do cliente (ou vazio).
     * @return array<int,array{name:string,email:?string,phone:?string,role:string}>
     *   role: 'client' | 'team'.
     */
    public static function buildRecipients(array $participants, array $client = []): array
    {
        $items = [];
        // Cliente primeiro (prioridade de identidade), depois a equipe.
        if (!empty($client)) $items[] = ['role' => 'client'] + $client;
        foreach ($participants as $p) $items[] = ['role' => 'team'] + $p;
        return self::collect($items);
    }

    /**
     * Destinatários escolhidos na tela de envio: os da reunião que ficaram
     * marcados e os adicionados na hora. Mesma limpeza de buildRecipients;
     * papel desconhecido vira 'team'.
     *
     * @param array $list Lista de ['name','email','phone','role'?].
     */
    public static function chosenRecipients(array $list): array
    {
        $items = [];
        foreach ($list as $r) {
            if (!is_array($r)) continue;
            $r['role'] = ($r['role'] ?? '') === 'client' ? 'client' : 'team';
            $items[] = $r;
        }
        return self::collect($items);
    }

    /** Normaliza, descarta quem não tem canal e deduplica por e-mail ou telefone. */
    private static function collect(array $items): array
    {
        $out = [];
        $seenEmail = [];
        $seenPhone = [];
        foreach ($items as $r) {
            $name = trim((string)($r['name'] ?? '')) ?: 'Participante';
            $email = self::normalizeEmail($r['email'] ?? null);
            $phone = self::normalizePhone($r['phone'] ?? null);
            if ($email === null && $phone === null) continue; // sem canal: ignora

            // Dedup: se o e-mail OU o telefone já apareceu, não duplica.
            if ($email !== null && isset($seenEmail[$email])) continue;
            if ($phone !== null && isset($seenPhone[$phone])) continue;

            if ($email !== null) $seenEmail[$email] = true;
            if ($phone !== null) $seenPhone[$phone] = true;
            $out[] = ['name' => $name, 'email' => $email, 'phone' => $phone, 'role' => $r['role']];
        }
        return $out;
    }

    /** Normaliza e-mail: minúsculo, trim; retorna null se vazio/ inválido. */
    public static function normalizeEmail($email): ?string
    {
        $e = strtolower(trim((string)$email));
        if ($e === '') return null;
        return filter_var($e, FILTER_VALIDATE_EMAIL) ? $e : null;
    }

    /** Normaliza telefone: só dígitos; retorna null se não sobrar dígito. */
    public static function normalizePhone($phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string)$phone);
        return ($digits === '' || $digits === null) ? null : $digits;
    }

    /**
     * Mensagem de WhatsApp com o link da minuta. Curta e objetiva.
     *
     * @param string      $recipientName Nome de quem recebe (para a saudação).
     * @param string      $link          URL pública da minuta.
     * @param string|null $meetingTitle  Assunto da reunião (opcional).
     * @param string|null $companyName   Nome da empresa remetente (opcional).
     */
    public static function whatsappMessage(string $recipientName, string $link, ?string $meetingTitle = null, ?string $companyName = null): string
    {
        $hi = 'Olá' . (trim($recipientName) !== '' ? ', ' . trim($recipientName) : '') . '!';
        $assunto = ($meetingTitle !== null && trim($meetingTitle) !== '')
            ? (' da reunião "' . trim($meetingTitle) . '"')
            : ' da nossa reunião';
        $msg = $hi . "\n\nSegue a minuta (ata)" . $assunto . ". Você pode ler e imprimir pelo link:\n" . $link;
        if ($companyName !== null && trim($companyName) !== '') {
            $msg .= "\n\n" . trim($companyName);
        }
        return $msg;
    }

    /** Assunto do e-mail com a minuta. */
    public static function emailSubject(?string $meetingTitle = null): string
    {
        $t = ($meetingTitle !== null && trim($meetingTitle) !== '') ? trim($meetingTitle) : 'reunião';
        return 'Minuta da ' . $t;
    }

    /**
     * Corpo HTML do e-mail com o link da minuta. Simples (o Mailer aplica o
     * template/assinatura institucional por cima).
     */
    public static function emailBody(string $recipientName, string $link, ?string $meetingTitle = null): string
    {
        $nome = htmlspecialchars(trim($recipientName) !== '' ? trim($recipientName) : 'Olá', ENT_QUOTES);
        $assunto = ($meetingTitle !== null && trim($meetingTitle) !== '')
            ? (' da reunião <strong>' . htmlspecialchars(trim($meetingTitle), ENT_QUOTES) . '</strong>')
            : ' da nossa reunião';
        $linkEsc = htmlspecialchars($link, ENT_QUOTES);
        return '<p>Olá, ' . $nome . '!</p>'
            . '<p>Segue a minuta (ata)' . $assunto . '. Você pode ler e imprimir (ou salvar em PDF) pelo link abaixo:</p>'
            . '<p><a href="' . $linkEsc . '">' . $linkEsc . '</a></p>';
    }
}
