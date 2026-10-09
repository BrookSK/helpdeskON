<?php

/**
 * Regras PURAS (sem banco/HTTP) para os webhooks de ENTRADA do WhatsApp.
 *
 * Um sistema externo faz POST com um JSON arbitrário. Estas regras extraem os
 * campos relevantes (telefone, nome, e-mail, mensagem) a partir de um
 * MAPEAMENTO configurado pelo usuário (nomes de campo, com suporte a
 * dot-notation para objetos aninhados) e montam a mensagem final a partir de um
 * TEMPLATE. Tudo determinístico e testável por unidade.
 *
 * Variáveis suportadas no template: {{nome}} {{telefone}} {{email}} {{mensagem}}
 */
class WebhookRules
{
    /**
     * Lê um campo do payload por caminho, com suporte a dot-notation.
     *
     * Ex.: extractField(['data' => ['phone' => '119...']], 'data.phone') => '119...'
     * Caminho vazio ou inexistente retorna null. Arrays/objetos intermediários
     * ausentes não quebram (retorna null).
     *
     * @param array  $payload JSON já decodificado como array associativo
     * @param ?string $path    caminho do campo (ex.: 'phone' ou 'data.contato.telefone')
     * @return mixed valor encontrado (escalar ou array) ou null
     */
    public static function extractField(array $payload, ?string $path)
    {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }
        $node = $payload;
        foreach (explode('.', $path) as $segment) {
            $segment = trim($segment);
            if ($segment === '') {
                return null;
            }
            if (is_array($node) && array_key_exists($segment, $node)) {
                $node = $node[$segment];
            } else {
                return null;
            }
        }
        return $node;
    }

    /**
     * Extrai a lista de telefones de um valor que pode vir de várias formas:
     *  - string única: "11999998888"
     *  - string com vários números separados por vírgula/;/espaço/pipe
     *  - array de strings: ["11999998888", "11988887777"]
     *  - array de objetos com uma chave de telefone (ex.: [{phone:"..."}]) —
     *    nesse caso NÃO adivinhamos a chave; só aceitamos escalares no array.
     *
     * Retorna telefones NORMALIZADOS (só dígitos, com DDI 55 quando aplicável) e
     * sem duplicatas. Entradas inválidas são descartadas.
     *
     * @param mixed $value
     * @return string[]
     */
    public static function extractPhones($value): array
    {
        $candidates = [];

        if (is_array($value)) {
            foreach ($value as $item) {
                if (is_scalar($item)) {
                    $candidates[] = (string) $item;
                }
            }
        } elseif (is_scalar($value)) {
            // Permite vários números numa string só, separados por , ; | ou espaço.
            $candidates = preg_split('/[,;|\s]+/', (string) $value) ?: [];
        }

        $out = [];
        foreach ($candidates as $raw) {
            $norm = self::normalizePhone($raw);
            if ($norm !== null && !in_array($norm, $out, true)) {
                $out[] = $norm;
            }
        }
        return $out;
    }

    /**
     * Normaliza um telefone brasileiro para apenas dígitos, adicionando o DDI 55
     * quando o número tem 10 ou 11 dígitos (DDD + número) e ainda não começa com
     * 55. Retorna null quando não há dígitos suficientes para ser um telefone
     * válido (mínimo 10 dígitos nacionais).
     *
     * @param mixed $raw
     * @return string|null
     */
    public static function normalizePhone($raw): ?string
    {
        if (!is_scalar($raw)) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', (string) $raw);
        if ($digits === '' || strlen($digits) < 10) {
            return null;
        }
        // 10 (fixo) ou 11 (celular) dígitos sem DDI -> prefixa 55.
        if ((strlen($digits) === 10 || strlen($digits) === 11) && !str_starts_with($digits, '55')) {
            $digits = '55' . $digits;
        }
        // Limite defensivo (E.164 tem no máx. 15 dígitos).
        if (strlen($digits) > 15) {
            return null;
        }
        return $digits;
    }

    /**
     * Normaliza um valor escalar para string "limpa" (trim). Arrays/objetos e
     * não-escalares viram string vazia. Útil para nome/e-mail/mensagem.
     *
     * @param mixed $value
     */
    public static function scalarToString($value): string
    {
        if (is_scalar($value)) {
            return trim((string) $value);
        }
        return '';
    }

    /**
     * Monta a mensagem final.
     *
     * Se houver $template, substitui as variáveis {{nome}}, {{telefone}},
     * {{email}} e {{mensagem}} (case-insensitive, com ou sem espaços internos:
     * {{ nome }} também funciona). Se NÃO houver template, usa $vars['mensagem']
     * diretamente (campo message_field do webhook).
     *
     * Variáveis não informadas viram string vazia (não deixa "{{nome}}" cru).
     *
     * @param ?string $template
     * @param array{nome?:string,telefone?:string,email?:string,mensagem?:string} $vars
     */
    public static function renderMessage(?string $template, array $vars): string
    {
        $template = (string) $template;
        if (trim($template) === '') {
            // Sem template: a própria mensagem recebida é o conteúdo.
            return trim((string) ($vars['mensagem'] ?? ''));
        }

        $map = [
            'nome'     => (string) ($vars['nome'] ?? ''),
            'telefone' => (string) ($vars['telefone'] ?? ''),
            'email'    => (string) ($vars['email'] ?? ''),
            'mensagem' => (string) ($vars['mensagem'] ?? ''),
        ];

        return preg_replace_callback(
            '/\{\{\s*([a-zA-Z_]+)\s*\}\}/',
            function ($m) use ($map) {
                $key = strtolower($m[1]);
                return array_key_exists($key, $map) ? $map[$key] : $m[0];
            },
            $template
        );
    }

    /**
     * Interpreta o payload inteiro conforme o mapeamento de um webhook e devolve
     * os dados prontos para envio. Método PURO: não envia nada, só extrai e monta.
     *
     * $mapping aceita as chaves: phone_field, name_field, email_field,
     * message_field, message_template.
     *
     * Retorno:
     *  [
     *    'phones'  => string[]   // telefones normalizados (pode ser vazio)
     *    'name'    => string
     *    'email'   => string
     *    'message' => string     // mensagem final (template aplicado ou campo)
     *  ]
     *
     * @param array $payload
     * @param array $mapping
     * @return array{phones:string[],name:string,email:string,message:string}
     */
    public static function interpret(array $payload, array $mapping): array
    {
        $phones = self::extractPhones(self::extractField($payload, $mapping['phone_field'] ?? 'phone'));
        $name   = self::scalarToString(self::extractField($payload, $mapping['name_field'] ?? null));
        $email  = self::scalarToString(self::extractField($payload, $mapping['email_field'] ?? null));
        $rawMsg = self::scalarToString(self::extractField($payload, $mapping['message_field'] ?? null));

        $message = self::renderMessage($mapping['message_template'] ?? null, [
            'nome'     => $name,
            'telefone' => $phones[0] ?? '',
            'email'    => $email,
            'mensagem' => $rawMsg,
        ]);

        return [
            'phones'  => $phones,
            'name'    => $name,
            'email'   => $email,
            'message' => trim($message),
        ];
    }

    /**
     * Uma interpretação é "enviável" quando tem ao menos um telefone e uma
     * mensagem não vazia. Caso contrário a request deve ser marcada como
     * 'skipped' (nada a enviar).
     *
     * @param array{phones:string[],message:string} $interpreted
     */
    public static function isSendable(array $interpreted): bool
    {
        return !empty($interpreted['phones']) && trim((string) ($interpreted['message'] ?? '')) !== '';
    }
}
