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
    public const MEETING_TYPES = ['comercial', 'operacional', 'externo', 'interno'];

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
     * Normaliza o flag de gravação automática: 1 somente quando o valor for
     * '1', 1 ou true (checkbox marcado); qualquer outra coisa retorna 0.
     * Só faz sentido para reuniões operacionais/internas, mas a normalização
     * é genérica — a restrição de tipo fica na view/controller.
     */
    public static function normalizeAutoRecord($value): int
    {
        return ($value === '1' || $value === 1 || $value === true) ? 1 : 0;
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

    /**
     * Valida e normaliza a lista de CLIENTES de uma reunião comercial.
     *
     * Suporta múltiplos clientes por reunião via arrays paralelos. Cada
     * cliente pode vir do CRM (contact_id) e/ou trazer um snapshot
     * (nome/telefone/e-mail) quando a origem foi "Empresa → Contato".
     *
     * Regra de validade: um cliente é mantido quando tem contact_id (lead do
     * CRM, que já carrega os dados) OU um nome (snapshot). Linhas totalmente
     * vazias são descartadas. E-mails inválidos são limpos (viram ''); o
     * telefone é reduzido a dígitos. O contact_id só é aceito como inteiro > 0.
     *
     * Deduplica por contact_id (quando presente) e, no snapshot, por
     * (nome+e-mail+telefone) normalizados, preservando a ordem de chegada —
     * a primeira ocorrência vence (posição 0 = cliente principal).
     *
     * @param array|string $contactIds
     * @param array|string $names
     * @param array|string $emails
     * @param array|string $phones
     * @return array<int, array{contact_id:?int, name:string, email:string, phone:string}>
     */
    public static function parseClients($contactIds, $names, $emails, $phones): array
    {
        if (!is_array($contactIds)) $contactIds = [$contactIds];
        if (!is_array($names))      $names      = [$names];
        if (!is_array($emails))     $emails     = [$emails];
        if (!is_array($phones))     $phones     = [$phones];

        $clients = [];
        $seenContacts = [];
        $seenSnapshots = [];
        $count = max(count($contactIds), count($names), count($emails), count($phones));

        for ($i = 0; $i < $count; $i++) {
            $cid   = (int) ($contactIds[$i] ?? 0);
            $cid   = $cid > 0 ? $cid : null;
            $name  = trim((string) ($names[$i]  ?? ''));
            $email = trim((string) ($emails[$i] ?? ''));
            $phone = preg_replace('/\D/', '', trim((string) ($phones[$i] ?? '')));

            // Descarta linhas totalmente vazias.
            if ($cid === null && $name === '' && $email === '' && $phone === '') continue;
            // Precisa de vínculo de CRM OU de um nome (snapshot).
            if ($cid === null && $name === '') continue;
            // E-mail inválido é limpo (não invalida o cliente: nome/contact_id bastam).
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $email = '';

            // Deduplicação.
            if ($cid !== null) {
                if (isset($seenContacts[$cid])) continue;
                $seenContacts[$cid] = true;
            } else {
                $key = mb_strtolower($name) . '|' . mb_strtolower($email) . '|' . $phone;
                if (isset($seenSnapshots[$key])) continue;
                $seenSnapshots[$key] = true;
            }

            $clients[] = [
                'contact_id' => $cid,
                'name'  => $name,
                'email' => $email,
                'phone' => $phone,
            ];
        }
        return $clients;
    }

    /** Opções de reunião possíveis (seletor do modal). */
    public const MEETING_MODES = ['', 'meet', 'room'];

    /**
     * Normaliza a opção de reunião escolhida no modal:
     *   '' = Nenhuma, 'meet' = Google Meet, 'room' = Sala de vídeo do sistema.
     *
     * Para compatibilidade com versões antigas do modal (que não enviavam o
     * modo), deduz pelo link já gerado: se há link e ele é da sala nativa →
     * 'room'; se há link e não é nativo → 'meet'; sem link → '' (Nenhuma).
     *
     * @param string|null $mode            Valor cru de meeting_mode do POST.
     * @param string|null $meetLink        Link já gerado (meet_link), se houver.
     * @param bool        $linkIsNativeRoom O link aponta para a sala nativa?
     */
    public static function resolveMeetingMode($mode, $meetLink, bool $linkIsNativeRoom): string
    {
        $mode = (string) $mode;
        if (in_array($mode, ['meet', 'room'], true)) {
            return $mode;
        }
        // Modo ausente/!= conhecido: deduz pelo link.
        $meetLink = trim((string) $meetLink);
        if ($meetLink !== '') {
            return $linkIsNativeRoom ? 'room' : 'meet';
        }
        return '';
    }

    /**
     * Deve criar um NOVO evento no Google (com Meet) ao salvar a reunião?
     *
     * Só quando a opção é "Google Meet" E ainda não há um evento criado
     * (google_event_id vazio). Para "Sala de vídeo do sistema" ('room') ou
     * "Nenhuma" ('') nunca cria — evitando gerar Meet indevido e sobrescrever
     * o link da sala do sistema.
     *
     * @param string      $mode             Modo normalizado (via resolveMeetingMode).
     * @param string|null $existingEventId  google_event_id já presente, se houver.
     */
    public static function shouldCreateGoogleEvent(string $mode, $existingEventId): bool
    {
        if ($mode !== 'meet') return false;
        return trim((string) $existingEventId) === '';
    }
}
