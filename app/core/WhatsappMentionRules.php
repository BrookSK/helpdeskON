<?php

/**
 * Regras PURAS (sem banco/HTTP) das menções (@) em grupos de WhatsApp.
 *
 * Duas pontas:
 *  - RECEBIDAS: descobrir se uma mensagem do grupo marcou o NOSSO número (a
 *    instância conectada). O WhatsApp manda a lista de marcados em
 *    contextInfo.mentionedJid. Nos grupos novos os membros são identificados
 *    por LID ("1560...@lid"), sem telefone; por isso comparamos tanto o
 *    telefone da instância (owner_phone) quanto o LID dela (owner_lid).
 *  - ENVIADAS: montar a lista de participantes para o autocomplete do @ e
 *    validar os JIDs que o navegador manda marcar, antes de repassar à
 *    Evolution (POST /message/sendText com "mentioned").
 *
 * Formato real observado na Evolution v2 (GET /group/participants):
 *   {"participants":[{"id":"156079530967127@lid",
 *                     "phoneNumber":"5517988093160@s.whatsapp.net",
 *                     "admin":"superadmin","name":null}, ...]}
 */
class WhatsappMentionRules
{
    /** Limite de pessoas marcadas numa única mensagem (proteção de entrada). */
    public const MAX_MENTIONS = 50;

    // =========================================================
    // JIDs
    // =========================================================

    /** Parte antes do "@", sem o sufixo de aparelho (":12"). */
    public static function jidUser(string $jid): string
    {
        $user = explode('@', trim($jid), 2)[0];
        return explode(':', $user)[0];
    }

    /** Domínio do JID em minúsculas ("lid", "s.whatsapp.net", "g.us"...). '' se não houver. */
    public static function jidDomain(string $jid): string
    {
        $parts = explode('@', trim($jid), 2);
        return strtolower($parts[1] ?? '');
    }

    /** JID de telefone (ou número puro)? LID, grupo e broadcast não são telefone. */
    private static function isPhoneJid(string $jid): bool
    {
        return in_array(self::jidDomain($jid), ['', 's.whatsapp.net', 'c.us'], true);
    }

    /**
     * Chave do telefone para comparar com/sem o 9º dígito (DDI+DDD+últimos 8).
     * Aceita JID de telefone ou dígitos. '' para LID/grupo ou vazio.
     */
    public static function phoneKeyOf(?string $phoneOrJid): string
    {
        $v = trim((string) $phoneOrJid);
        if ($v === '') return '';
        if (strpos($v, '@') !== false) {
            if (!self::isPhoneJid($v)) return '';
            $digits = WhatsappGroupExportRules::phoneFromJid($v);
        } else {
            $digits = preg_replace('/\D/', '', explode(':', $v)[0]);
        }
        if (strlen($digits) < 8) return '';
        return WhatsappGroupExportRules::phoneKey($digits);
    }

    // =========================================================
    // RECEBIDAS: a mensagem marcou o nosso número?
    // =========================================================

    /**
     * JIDs marcados numa mensagem do webhook (payload do Baileys/Evolution).
     * Procura contextInfo.mentionedJid no topo (Evolution v2), dentro do tipo
     * da mensagem (extendedTextMessage, imageMessage, videoMessage...) e em
     * mensagens embrulhadas (ephemeralMessage/viewOnceMessage/
     * documentWithCaptionMessage -> message -> tipo). Sem duplicatas.
     */
    public static function extractMentionedJids(array $msg): array
    {
        $found = [];
        $collect = function ($ctx) use (&$found) {
            if (!is_array($ctx)) return;
            foreach ((array) ($ctx['mentionedJid'] ?? []) as $jid) {
                if (!is_string($jid)) continue;
                $jid = trim($jid);
                if ($jid !== '') $found[$jid] = true;
            }
        };
        $scanMessage = function ($message, int $depth) use (&$scanMessage, $collect) {
            if (!is_array($message) || $depth > 3) return;
            $collect($message['contextInfo'] ?? null);
            foreach ($message as $part) {
                if (!is_array($part)) continue;
                $collect($part['contextInfo'] ?? null);
                if (isset($part['message']) && is_array($part['message'])) {
                    $scanMessage($part['message'], $depth + 1);
                }
            }
        };

        $collect($msg['contextInfo'] ?? null);
        $scanMessage($msg['message'] ?? null, 0);

        return array_keys($found);
    }

    /**
     * Algum dos JIDs marcados é o nosso número?
     *  - "<lid>@lid"            -> compara com o LID da instância;
     *  - telefone (@s.whatsapp.net, @c.us ou dígitos) -> compara pelo phoneKey
     *    (casa com e sem o 9º dígito).
     */
    public static function mentionsOwner(array $mentionedJids, ?string $ownerPhone, ?string $ownerLid): bool
    {
        $ownKey = self::phoneKeyOf($ownerPhone);
        $ownLid = trim((string) $ownerLid) !== '' ? self::jidUser((string) $ownerLid) : '';
        if ($ownKey === '' && $ownLid === '') return false;

        foreach ($mentionedJids as $jid) {
            if (!is_string($jid) || trim($jid) === '') continue;
            $domain = self::jidDomain($jid);
            if ($domain === 'lid') {
                if ($ownLid !== '' && self::jidUser($jid) === $ownLid) return true;
                continue;
            }
            if (self::isPhoneJid($jid) && $ownKey !== '' && self::phoneKeyOf($jid) === $ownKey) {
                return true;
            }
        }
        return false;
    }

    /**
     * Reserva para quando o payload não trouxer mentionedJid: o texto tem
     * "@<nosso LID>" ou "@<nosso telefone>". Tokens com 14+ dígitos só são
     * comparados ao LID (telefone tem no máximo 13), para não confundir os dois.
     */
    public static function textMentionsOwner(string $text, ?string $ownerPhone, ?string $ownerLid): bool
    {
        if ($text === '' || strpos($text, '@') === false) return false;
        if (!preg_match_all('/(?<![\w@])@(\d{8,20})(?!\d)/', $text, $m)) return false;

        $ownKey = self::phoneKeyOf($ownerPhone);
        $ownLid = trim((string) $ownerLid) !== '' ? self::jidUser((string) $ownerLid) : '';
        foreach ($m[1] as $token) {
            if ($ownLid !== '' && $token === $ownLid) return true;
            if ($ownKey !== '' && strlen($token) <= 13 && self::phoneKeyOf($token) === $ownKey) return true;
        }
        return false;
    }

    /** Há menção por LID e ainda não sabemos o nosso LID? (precisa consultar o grupo) */
    public static function needsOwnerLid(array $mentionedJids, ?string $ownerLid): bool
    {
        if (trim((string) $ownerLid) !== '') return false;
        foreach ($mentionedJids as $jid) {
            if (is_string($jid) && self::jidDomain($jid) === 'lid') return true;
        }
        return false;
    }

    // =========================================================
    // Identidade da instância (nosso número / nosso LID)
    // =========================================================

    /**
     * Telefone (dígitos) da instância a partir de /instance/fetchInstances.
     * v2: [{"name":"x","ownerJid":"5517...@s.whatsapp.net"}]
     * v1: [{"instance":{"instanceName":"x","owner":"5517...@s.whatsapp.net"}}]
     * Com várias instâncias na resposta, usa a de nome $instanceName.
     * '' quando não encontrar.
     */
    public static function ownerPhoneFromInstanceInfo($response, string $instanceName): string
    {
        if (!is_array($response) || !empty($response['error'])) return '';
        $list = isset($response[0]) || $response === [] ? $response : [$response];

        $candidates = [];
        foreach ($list as $row) {
            if (!is_array($row)) continue;
            $inner = (isset($row['instance']) && is_array($row['instance'])) ? $row['instance'] : $row;
            $name = (string) ($inner['name'] ?? $inner['instanceName'] ?? $row['name'] ?? '');
            $owner = (string) ($inner['ownerJid'] ?? $inner['owner'] ?? $inner['wuid'] ?? $row['ownerJid'] ?? '');
            $candidates[] = ['name' => $name, 'owner' => $owner];
        }

        foreach ($candidates as $c) {
            if ($instanceName !== '' && $c['name'] === $instanceName) {
                return self::phoneDigits($c['owner']);
            }
        }
        // Resposta filtrada (?instanceName=) costuma vir com uma só instância.
        if (count($candidates) === 1 && ($candidates[0]['name'] === '' || $instanceName === '')) {
            return self::phoneDigits($candidates[0]['owner']);
        }
        return '';
    }

    /** Dígitos do telefone de um JID de telefone (ou número). '' para LID/grupo. */
    public static function phoneDigits(?string $jidOrPhone): string
    {
        $v = trim((string) $jidOrPhone);
        if ($v === '') return '';
        if (strpos($v, '@') !== false) {
            return WhatsappGroupExportRules::phoneFromJid($v);
        }
        $digits = preg_replace('/\D/', '', explode(':', $v)[0]);
        return strlen($digits) >= 8 ? $digits : '';
    }

    /** Lista de participantes de /group/participants (embrulhada ou direta). */
    public static function participantsList($response): array
    {
        if (!is_array($response) || !empty($response['error'])) return [];
        if (isset($response['participants']) && is_array($response['participants'])) {
            $response = $response['participants'];
        }
        $out = [];
        foreach ($response as $p) {
            if (is_string($p)) $p = ['id' => $p];
            if (is_array($p) && !empty($p['id']) && is_string($p['id'])) $out[] = $p;
        }
        return $out;
    }

    /**
     * Acha o NOSSO LID na lista de participantes: o membro cujo telefone
     * (phoneNumber/jid/id de telefone) é o da instância. Retorna "<lid>@lid"
     * ou '' se não achar.
     */
    public static function findOwnLidInParticipants($response, ?string $ownerPhone): string
    {
        $ownKey = self::phoneKeyOf($ownerPhone);
        if ($ownKey === '') return '';
        foreach (self::participantsList($response) as $p) {
            $phones = [$p['phoneNumber'] ?? null, $p['jid'] ?? null, $p['id'] ?? null];
            $isMe = false;
            foreach ($phones as $ph) {
                if (is_string($ph) && self::phoneKeyOf($ph) === $ownKey) { $isMe = true; break; }
            }
            if (!$isMe) continue;
            foreach ([$p['id'] ?? null, $p['lid'] ?? null] as $cand) {
                if (is_string($cand) && self::jidDomain($cand) === 'lid') {
                    return self::jidUser($cand) . '@lid';
                }
            }
        }
        return '';
    }

    // =========================================================
    // ENVIADAS: autocomplete e validação
    // =========================================================

    /**
     * Monta a lista do autocomplete do @ (sem o nosso próprio número).
     *
     * @param mixed  $participantsResponse resposta de /group/participants ou lista [['id','name'],...]
     * @param array  $nameByJid     nome visto nas mensagens do grupo, por JID completo (sender_name)
     * @param array  $nameByPhoneKey nome salvo no CRM/chat, por phoneKey (contact_name/push_name)
     * @return array ['participants' => [[jid, token, name, phone, has_name]], 'self_tokens' => [...]]
     *   - jid:   endereço usado no grupo (vai em "mentioned");
     *   - token: o que vai no texto depois do "@" (parte do JID antes do "@");
     *   - name:  rótulo para a pessoa (CRM > perfil > histórico > telefone).
     */
    public static function buildMentionCandidates($participantsResponse, array $nameByJid, array $nameByPhoneKey, ?string $ownerPhone, ?string $ownerLid): array
    {
        $ownKey = self::phoneKeyOf($ownerPhone);
        $ownLid = trim((string) $ownerLid) !== '' ? self::jidUser((string) $ownerLid) : '';

        $selfTokens = [];
        if ($ownLid !== '') $selfTokens[$ownLid] = true;
        $ownDigits = self::phoneDigits($ownerPhone);
        if ($ownDigits !== '') $selfTokens[$ownDigits] = true;

        $people = [];
        $seen = [];
        foreach (self::participantsList($participantsResponse) as $p) {
            $jid = trim($p['id']);
            $domain = self::jidDomain($jid);
            if (!in_array($domain, ['lid', 's.whatsapp.net', 'c.us'], true)) continue;
            $token = self::jidUser($jid);
            if ($token === '' || !ctype_digit($token)) continue;
            if (isset($seen[$token])) continue;
            $seen[$token] = true;

            $phone = '';
            foreach ([$p['phoneNumber'] ?? null, $p['jid'] ?? null, $jid] as $cand) {
                if (is_string($cand) && ($d = self::phoneDigits($cand)) !== '' && self::isPhoneJid($cand)) { $phone = $d; break; }
            }
            $lidOfP = $domain === 'lid' ? $token : (is_string($p['lid'] ?? null) ? self::jidUser($p['lid']) : '');

            $isMe = ($ownLid !== '' && $lidOfP === $ownLid)
                || ($ownKey !== '' && $phone !== '' && self::phoneKeyOf($phone) === $ownKey);
            if ($isMe) {
                $selfTokens[$token] = true;
                continue;
            }

            $phoneKey = $phone !== '' ? self::phoneKeyOf($phone) : '';
            $name = self::firstNonEmpty([
                $phoneKey !== '' ? ($nameByPhoneKey[$phoneKey] ?? '') : '',
                $p['name'] ?? '',
                $p['notify'] ?? '',
                $p['pushName'] ?? '',
                $nameByJid[$jid] ?? '',
                $nameByJid[$token . '@' . $domain] ?? '',
            ]);
            // O nome não pode carregar quebra de linha nem "@" (vira rótulo no texto).
            $name = trim(preg_replace('/[\r\n\t@]+/u', ' ', $name));
            $name = preg_replace('/\s{2,}/u', ' ', $name);

            $people[] = [
                'jid' => $token . '@' . ($domain === 'c.us' ? 's.whatsapp.net' : $domain),
                'token' => $token,
                'name' => $name !== '' ? $name : ($phone !== '' ? WhatsappGroupExportRules::formatPhone($phone) : $token),
                'phone' => $phone !== '' ? WhatsappGroupExportRules::formatPhone($phone) : '',
                'has_name' => $name !== '',
            ];
        }

        // Com nome primeiro (A-Z), depois os que só têm número.
        usort($people, function ($a, $b) {
            if ($a['has_name'] !== $b['has_name']) return $a['has_name'] ? -1 : 1;
            return strcasecmp(self::fold($a['name']), self::fold($b['name']));
        });

        return ['participants' => $people, 'self_tokens' => array_map('strval', array_keys($selfTokens))];
    }

    /**
     * Valida os JIDs que o navegador pediu para marcar (entrada não confiável):
     *  - só pessoas ("<dígitos>@s.whatsapp.net" ou "<dígitos>@lid");
     *  - só quem aparece no texto como "@<token>" (sem marcação "fantasma");
     *  - sem duplicatas, no máximo MAX_MENTIONS.
     *
     * @param mixed $jids array ou JSON (string) vindo do POST
     */
    public static function sanitizeOutgoingMentions(string $text, $jids): array
    {
        if (is_string($jids)) {
            $decoded = json_decode($jids, true);
            $jids = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($jids) || $text === '') return [];

        $out = [];
        foreach ($jids as $jid) {
            if (!is_string($jid)) continue;
            $jid = trim($jid);
            if (!preg_match('/^(\d{5,20})@(s\.whatsapp\.net|lid)$/', $jid, $m)) continue;
            if (!preg_match('/(?<![\w@])@' . $m[1] . '(?!\d)/', $text)) continue;
            $out[$jid] = true;
            if (count($out) >= self::MAX_MENTIONS) break;
        }
        return array_keys($out);
    }

    // =========================================================
    // auxiliares
    // =========================================================

    private static function firstNonEmpty(array $values): string
    {
        foreach ($values as $v) {
            if (!is_scalar($v)) continue;
            $v = trim((string) $v);
            if ($v !== '') return $v;
        }
        return '';
    }

    /** Minúsculas e sem acento, para ordenar nomes. */
    private static function fold(string $s): string
    {
        // Mapa fixo (iconv//TRANSLIT varia entre Windows e Linux)
        return strtr(mb_strtolower($s, 'UTF-8'), [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
        ]);
    }
}
