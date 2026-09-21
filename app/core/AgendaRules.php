<?php

/**
 * Regras de negócio puras (sem banco/HTTP) do módulo Agenda.
 *
 * Extraídas do AgendaController para ficarem testáveis por unidade e servirem
 * de fonte única de verdade para validação de enums, convidados externos e
 * transições de status. O controller passa a usar estes métodos, evitando
 * divergência entre o que é validado e o que é testado.
 */
class AgendaRules
{
    /** Tipos de reunião aceitos. */
    public const MEETING_TYPES = ['comercial', 'operacional', 'externo'];

    /** Urgências aceitas. */
    public const URGENCIES = ['baixa', 'media', 'alta', 'urgente'];

    /** Temperaturas de lead aceitas. */
    public const TEMPERATURES = ['frio', 'morno', 'quente'];

    /** Visibilidades de sala de vídeo do sistema. */
    public const VIDEO_VISIBILITIES = ['public', 'private'];

    /**
     * Status de reunião (ordem de fluxo do Kanban). Mantido em sincronia com
     * AgendaMeeting::$statuses.
     */
    public const STATUSES = ['a_agendar', 'agendada', 'confirmada', 'realizada', 'convertida', 'remarcada', 'cancelada'];

    /**
     * Normaliza o tipo de reunião: retorna o valor se válido, senão 'comercial'.
     */
    public static function normalizeMeetingType($value): string
    {
        return in_array($value, self::MEETING_TYPES, true) ? $value : 'comercial';
    }

    /**
     * Normaliza a urgência: retorna o valor se válido, senão 'media'.
     */
    public static function normalizeUrgency($value): string
    {
        return in_array($value, self::URGENCIES, true) ? $value : 'media';
    }

    /**
     * Normaliza a temperatura: retorna o valor se válido, senão null.
     */
    public static function normalizeTemperature($value): ?string
    {
        return in_array($value, self::TEMPERATURES, true) ? $value : null;
    }

    /**
     * Normaliza o status: retorna o valor se válido, senão 'a_agendar'.
     */
    public static function normalizeStatus($value): string
    {
        return in_array($value, self::STATUSES, true) ? $value : 'a_agendar';
    }

    /**
     * Normaliza a visibilidade da sala de vídeo: 'private' apenas quando
     * explicitamente 'private'; qualquer outra coisa vira 'public'.
     */
    public static function normalizeVideoVisibility($value): string
    {
        return $value === 'private' ? 'private' : 'public';
    }

    /**
     * Limita o teto de participantes da sala de vídeo ao intervalo seguro [2, 15].
     */
    public static function clampVideoMaxParticipants($value): int
    {
        $n = (int) $value;
        if ($n < 2) return 2;
        if ($n > 15) return 15;
        return $n;
    }

    /**
     * Um valor de status é válido para persistência?
     */
    public static function isValidStatus($value): bool
    {
        return in_array($value, self::STATUSES, true);
    }

    /**
     * Converte a data vinda do input datetime-local (com "T") para o formato
     * DATETIME do MySQL. Retorna null se vazio/branco.
     */
    public static function normalizeMeetingAt($value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') return null;
        return str_replace('T', ' ', $value);
    }

    /**
     * Valida e normaliza uma lista de convidados externos.
     *
     * Aceita arrays paralelos de nomes, e-mails e telefones. Cada convidado
     * precisa de NOME e ao menos um canal de contato (e-mail OU telefone).
     * E-mails inválidos são descartados (viram ''), o que pode invalidar o
     * convidado se o telefone também estiver vazio. Telefone é reduzido a
     * dígitos.
     *
     * @param array|string $names
     * @param array|string $emails
     * @param array|string $phones
     * @return array<int, array{name:string, email:string, phone:string}>
     */
    public static function parseExternalGuests($names, $emails, $phones): array
    {
        if (!is_array($names))  $names  = [$names];
        if (!is_array($emails)) $emails = [$emails];
        if (!is_array($phones)) $phones = [$phones];

        $guests = [];
        $count = max(count($names), count($emails), count($phones));
        for ($i = 0; $i < $count; $i++) {
            $name  = trim((string) ($names[$i]  ?? ''));
            $email = trim((string) ($emails[$i] ?? ''));
            $phone = preg_replace('/\D/', '', trim((string) ($phones[$i] ?? '')));

            // Descarta linhas totalmente vazias.
            if ($name === '' && $email === '' && $phone === '') continue;
            // Precisa de nome e ao menos um canal de contato.
            if ($name === '' || ($email === '' && $phone === '')) continue;
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $email = '';
            // Após invalidar o e-mail, o convidado pode ter ficado sem canal.
            if ($email === '' && $phone === '') continue;

            $guests[] = ['name' => $name, 'email' => $email, 'phone' => $phone];
        }
        return $guests;
    }
}
