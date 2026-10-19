<?php

/**
 * Regras PURAS (sem banco/HTTP) da exportação dos contatos (participantes) dos
 * grupos de WhatsApp para planilha.
 *
 * Entradas:
 *  - resposta bruta de /group/fetchAllGroups/{instancia}?getParticipants=true
 *    (Evolution API v2), que pode vir como lista direta ou em ['groups' => [...]];
 *  - mapa local de contatos (telefone => dados já salvos no CRM/chat);
 *  - perfis buscados na API (/chat/fetchProfile), opcionais.
 *
 * Saída: cabeçalhos + linhas prontas para a planilha (uma linha por
 * participante POR grupo — a mesma pessoa em 2 grupos aparece 2 vezes, uma com
 * cada grupo, que é o formato pedido "Grupo - Nome - Telefone - ...").
 */
class WhatsappGroupExportRules
{
    /** Cabeçalhos da planilha, na ordem das colunas de buildRow(). */
    public static function headers(): array
    {
        return [
            'Grupo',
            'Nome',
            'Telefone',
            'Admin do grupo',
            'E-mail',
            'Site',
            'Instagram',
            'Facebook',
            'LinkedIn',
            'Outros links',
            'Recado (status)',
            'Descrição do perfil',
            'Categoria (Business)',
            'Endereço (Business)',
            'WhatsApp Business',
            'Instância',
            'ID WhatsApp',
        ];
    }

    /**
     * Normaliza a resposta de fetchAllGroups em
     * [['jid' => ..., 'subject' => ..., 'participants' => [['jid','phone','admin','name'], ...]], ...]
     * Grupos sem JID são ignorados. Participantes duplicados no mesmo grupo
     * (mesmo telefone/JID) entram uma vez só.
     *
     * @param mixed      $apiResponse resposta de /group/fetchAllGroups ou formato local
     * @param array|null $onlyJids    se informado (lista de JIDs de grupo), apenas
     *                                esses grupos entram no resultado. [] ou null = todos.
     */
    public static function parseGroups($apiResponse, ?array $onlyJids = null): array
    {
        if (!is_array($apiResponse) || !empty($apiResponse['error'])) {
            return [];
        }
        $list = $apiResponse;
        if (isset($apiResponse['groups']) && is_array($apiResponse['groups'])) {
            $list = $apiResponse['groups'];
        }

        // Conjunto de JIDs permitidos (normalizados) para busca O(1).
        $allow = null;
        if (is_array($onlyJids) && $onlyJids !== []) {
            $allow = [];
            foreach ($onlyJids as $j) {
                $j = self::normalizeGroupJid((string) $j);
                if ($j !== '') $allow[$j] = true;
            }
            if ($allow === []) $allow = null; // só valores inválidos: trata como "todos"
        }

        $groups = [];
        foreach ($list as $grp) {
            if (!is_array($grp)) continue;
            $jid = (string) ($grp['id'] ?? $grp['jid'] ?? $grp['remoteJid'] ?? '');
            if ($jid === '') continue;
            if ($allow !== null && !isset($allow[self::normalizeGroupJid($jid)])) continue;
            $subject = trim((string) ($grp['subject'] ?? $grp['name'] ?? ''));

            $participants = [];
            $seen = [];
            foreach ((array) ($grp['participants'] ?? []) as $p) {
                if (is_string($p)) $p = ['id' => $p];
                if (!is_array($p)) continue;
                $norm = self::parseParticipant($p);
                if ($norm['jid'] === '' && $norm['phone'] === '') continue;
                $key = $norm['phone'] !== '' ? 'p:' . $norm['phone'] : 'j:' . $norm['jid'];
                if (isset($seen[$key])) continue;
                $seen[$key] = true;
                $participants[] = $norm;
            }

            $groups[] = [
                'jid' => $jid,
                'subject' => $subject !== '' ? $subject : self::stripJid($jid),
                'participants' => $participants,
            ];
        }

        usort($groups, fn($a, $b) => strcasecmp($a['subject'], $b['subject']));
        return $groups;
    }

    /**
     * Normaliza um participante. O WhatsApp pode identificar o membro por
     * "@lid" (ID anônimo, sem telefone); nas versões novas da Evolution o
     * telefone real vem em phoneNumber/jid. Só consideramos telefone o que vier
     * de um JID "@s.whatsapp.net"/"@c.us" ou um número puro.
     */
    public static function parseParticipant(array $p): array
    {
        $candidates = [
            $p['phoneNumber'] ?? null,
            $p['jid'] ?? null,
            $p['id'] ?? null,
            $p['lid'] ?? null,
        ];

        $phone = '';
        $jid = '';
        foreach ($candidates as $c) {
            if (!is_string($c) || $c === '') continue;
            if ($jid === '') $jid = $c;
            $digits = self::phoneFromJid($c);
            if ($digits !== '') {
                $phone = $digits;
                $jid = $c;
                break;
            }
        }

        $admin = $p['admin'] ?? null;
        $isAdmin = in_array($admin, ['admin', 'superadmin'], true) || $admin === true;

        $name = trim((string) ($p['name'] ?? $p['pushName'] ?? $p['notify'] ?? ''));

        return [
            'jid' => $jid,
            'phone' => $phone,
            'admin' => $isAdmin ? ($admin === 'superadmin' ? 'Criador' : 'Sim') : 'Não',
            'name' => $name,
        ];
    }

    /**
     * Telefone (só dígitos) de um JID de pessoa. Retorna '' para @lid,
     * @g.us, @broadcast ou valores sem dígitos suficientes.
     */
    public static function phoneFromJid(string $jid): string
    {
        $jid = trim($jid);
        if ($jid === '') return '';
        if (strpos($jid, '@') !== false) {
            [$user, $domain] = explode('@', $jid, 2);
            if (!in_array(strtolower($domain), ['s.whatsapp.net', 'c.us'], true)) {
                return '';
            }
            $user = explode(':', $user)[0]; // remove sufixo de dispositivo (5511...:12)
        } else {
            $user = $jid;
        }
        $digits = preg_replace('/\D/', '', $user);
        return strlen($digits) >= 8 ? $digits : '';
    }

    /** Remove o sufixo @... de um JID. */
    public static function stripJid(string $jid): string
    {
        return preg_replace('/@.*$/', '', $jid);
    }

    /**
     * Normaliza um JID de grupo para comparação: só os dígitos antes do "@".
     * Assim "120363...@g.us" e "120363..." casam. Vazio se não houver dígitos.
     */
    public static function normalizeGroupJid(string $jid): string
    {
        $jid = trim($jid);
        if ($jid === '') return '';
        $user = explode('@', $jid)[0];
        return preg_replace('/\D/', '', $user);
    }

    /**
     * Formata telefone para leitura: 5511987654321 => +55 (11) 98765-4321.
     * Números fora do padrão brasileiro saem como +<dígitos>.
     */
    public static function formatPhone(string $digits): string
    {
        $digits = preg_replace('/\D/', '', $digits);
        if ($digits === '') return '';
        if (str_starts_with($digits, '55') && in_array(strlen($digits), [12, 13], true)) {
            $ddd = substr($digits, 2, 2);
            $num = substr($digits, 4);
            $split = strlen($num) - 4;
            return '+55 (' . $ddd . ') ' . substr($num, 0, $split) . '-' . substr($num, $split);
        }
        return '+' . $digits;
    }

    /**
     * Chave para casar o mesmo telefone com/sem o 9º dígito: DDI+DDD+últimos 8.
     * Ex.: 5511987654321 e 551187654321 => "551187654321".
     */
    public static function phoneKey(string $digits): string
    {
        $digits = preg_replace('/\D/', '', $digits);
        if (strlen($digits) < 8) return $digits;
        if (str_starts_with($digits, '55') && strlen($digits) >= 12) {
            return substr($digits, 0, 4) . substr($digits, -8);
        }
        return $digits;
    }

    /**
     * Normaliza a resposta de /chat/fetchProfile (ou fetchBusinessProfile).
     * Campos ausentes viram '' / [].
     */
    public static function parseProfile($resp): array
    {
        $out = [
            'name' => '', 'status' => '', 'description' => '', 'email' => '',
            'category' => '', 'address' => '', 'websites' => [], 'is_business' => null,
        ];
        if (!is_array($resp) || !empty($resp['error'])) return $out;
        // Algumas versões embrulham em 'profile' ou 'data'
        if (isset($resp['profile']) && is_array($resp['profile'])) $resp = $resp['profile'];
        elseif (isset($resp['data']) && is_array($resp['data'])) $resp = $resp['data'];

        $out['name'] = self::str($resp['name'] ?? $resp['pushName'] ?? '');
        $status = $resp['status'] ?? '';
        if (is_array($status)) $status = $status['status'] ?? '';
        $out['status'] = self::str($status);
        $out['description'] = self::str($resp['description'] ?? '');
        $out['email'] = self::str($resp['email'] ?? '');
        $cat = $resp['category'] ?? '';
        if (is_array($cat)) $cat = implode(', ', array_filter(array_map(fn($c) => is_array($c) ? ($c['localized_display_name'] ?? $c['name'] ?? '') : (string) $c, $cat)));
        $out['category'] = self::str($cat);
        $out['address'] = self::str($resp['address'] ?? '');

        $sites = $resp['website'] ?? $resp['websites'] ?? [];
        if (is_string($sites)) $sites = [$sites];
        foreach ((array) $sites as $s) {
            if (is_string($s) && trim($s) !== '') $out['websites'][] = trim($s);
        }
        if (array_key_exists('isBusiness', $resp)) $out['is_business'] = (bool) $resp['isBusiness'];
        elseif ($out['category'] !== '' || $out['address'] !== '' || $out['websites']) $out['is_business'] = true;

        return $out;
    }

    /**
     * Extrai e classifica links de URLs explícitas e de textos livres
     * (recado, descrição). Retorna ['site','instagram','facebook','linkedin','outros'],
     * cada um com os links únicos separados por " | ".
     * "@usuario" solto num texto que mencione Instagram/insta vira link do Instagram.
     */
    public static function extractLinks(array $urls, array $texts = []): array
    {
        $found = [];
        foreach ($urls as $u) {
            if (is_string($u) && trim($u) !== '') $found[] = trim($u);
        }
        foreach ($texts as $t) {
            if (!is_string($t) || $t === '') continue;
            if (preg_match_all('~\b((?:https?://|www\.)[^\s<>"\']+|(?:instagram\.com|instagr\.am|facebook\.com|fb\.com|linkedin\.com|wa\.me|t\.me|tiktok\.com|youtube\.com|youtu\.be|linktr\.ee)/[^\s<>"\']+)~iu', $t, $m)) {
                foreach ($m[1] as $u) $found[] = $u;
            }
            // Instagram citado como @usuario (ex.: "Insta: @minhaloja")
            if (preg_match('~\b(insta(?:gram)?|ig)\b~iu', $t)
                && preg_match_all('~(?<![\w.@])@([A-Za-z0-9._]{2,30})\b~u', $t, $m)) {
                foreach ($m[1] as $handle) $found[] = 'https://instagram.com/' . rtrim($handle, '.');
            }
        }

        $buckets = ['site' => [], 'instagram' => [], 'facebook' => [], 'linkedin' => [], 'outros' => []];
        foreach ($found as $url) {
            $url = rtrim($url, '.,;:!?)]}');
            if ($url === '') continue;
            $norm = preg_match('~^https?://~i', $url) ? $url : 'https://' . $url;
            $host = strtolower((string) parse_url($norm, PHP_URL_HOST));
            $host = preg_replace('/^(www\.|m\.)/', '', $host);
            if ($host === '' || strpos($host, '.') === false) continue;

            if (in_array($host, ['instagram.com', 'instagr.am'], true)) $b = 'instagram';
            elseif (in_array($host, ['facebook.com', 'fb.com', 'fb.me'], true)) $b = 'facebook';
            elseif ($host === 'linkedin.com' || str_ends_with($host, '.linkedin.com')) $b = 'linkedin';
            elseif (in_array($host, ['wa.me', 't.me', 'tiktok.com', 'youtube.com', 'youtu.be', 'linktr.ee', 'x.com', 'twitter.com'], true)) $b = 'outros';
            else $b = 'site';

            $dedupeKey = strtolower(rtrim(preg_replace('~^https?://(www\.)?~i', '', $norm), '/'));
            if (!isset($buckets[$b][$dedupeKey])) $buckets[$b][$dedupeKey] = $norm;
        }

        $out = [];
        foreach ($buckets as $k => $v) $out[$k] = implode(' | ', array_values($v));
        return $out;
    }

    /**
     * Monta uma linha da planilha.
     *
     * @param array $group       ['subject', 'jid']
     * @param array $participant saída de parseParticipant()
     * @param array $local       dados locais do contato (contact_name, push_name,
     *                           lead_email, linkedin_url, lead_source_url, sender_name) ou []
     * @param array $profile     saída de parseProfile() ou []
     * @param string $instanceName nome de exibição da instância
     */
    public static function buildRow(array $group, array $participant, array $local, array $profile, string $instanceName = ''): array
    {
        $profile = $profile + self::parseProfile([]);

        // Prioridade do nome: o que o usuário salvou no CRM > nome do perfil >
        // pushName do grupo/mensagens.
        $name = self::firstNonEmpty([
            $local['contact_name'] ?? '',
            $profile['name'],
            $local['push_name'] ?? '',
            $participant['name'] ?? '',
            $local['sender_name'] ?? '',
        ]);

        $phone = $participant['phone'] !== '' ? self::formatPhone($participant['phone']) : '';

        $links = self::extractLinks(
            array_merge($profile['websites'], [$local['lead_source_url'] ?? '', $local['linkedin_url'] ?? '']),
            [$profile['status'], $profile['description']]
        );

        $email = self::firstNonEmpty([$profile['email'], $local['lead_email'] ?? '']);
        $business = $profile['is_business'] === null ? '' : ($profile['is_business'] ? 'Sim' : 'Não');

        return [
            $group['subject'] ?? '',
            $name,
            $phone !== '' ? $phone : 'Oculto pelo WhatsApp',
            $participant['admin'] ?? 'Não',
            $email,
            $links['site'],
            $links['instagram'],
            $links['facebook'],
            $links['linkedin'],
            $links['outros'],
            $profile['status'],
            $profile['description'],
            $profile['category'],
            $profile['address'],
            $business,
            $instanceName,
            $participant['jid'] ?? '',
        ];
    }

    /**
     * Neutraliza injeção de fórmula para CSV (valores começando com = + - @
     * TAB ou CR viram texto com apóstrofo). Telefones "+55 ..." também são
     * protegidos, o que é o desejado (não viram fórmula/número).
     */
    public static function csvSafe($value): string
    {
        $v = (string) $value;
        if ($v !== '' && strpbrk($v[0], "=+-@\t\r") !== false) {
            return "'" . $v;
        }
        return $v;
    }

    /** Nome do arquivo de exportação. */
    public static function fileName(string $ext, ?int $timestamp = null): string
    {
        return 'contatos_grupos_whatsapp_' . date('Y-m-d_His', $timestamp ?? time()) . '.' . $ext;
    }

    private static function firstNonEmpty(array $values): string
    {
        foreach ($values as $v) {
            $v = trim((string) $v);
            if ($v !== '') return $v;
        }
        return '';
    }

    private static function str($v): string
    {
        return is_scalar($v) ? trim((string) $v) : '';
    }
}
