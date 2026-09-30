<?php

/**
 * Regras de negócio puras (sem banco/HTTP) da JANELA DE ENVIO das sequências.
 *
 * Fonte única e testável para responder "posso enviar AGORA?" e "quando é o
 * próximo horário válido?", considerando:
 *   - dias da semana permitidos (days_of_week, CSV ISO-8601 "1..7", 1=segunda);
 *   - fim de semana (send_weekends) como fallback quando days_of_week é vazio;
 *   - janela de horário (window_start / window_end).
 *
 * Motivação (bug de produção): apenas o bloco de E-MAIL respeitava a janela; os
 * blocos de WhatsApp/Responder/Agendamento enviavam a qualquer hora (inclusive
 * de madrugada e fim de semana), violando o que estava configurado na campanha.
 * Centralizar a decisão aqui evita que cada bloco reimplemente (e divirja) a
 * verificação — e deixa a regra coberta por teste unitário.
 *
 * Todos os métodos recebem os valores JÁ resolvidos da sequência (strings de
 * horário "HH:MM:SS", CSV de dias, flag de fim de semana) e um timestamp "agora"
 * opcional (para teste determinístico).
 */
class SequenceWindowRules
{
    /**
     * Normaliza o CSV de dias em uma lista de inteiros 1..7 (ISO-8601).
     * Valores fora de 1..7 são descartados. Retorna [] quando vazio (= todos).
     */
    public static function parseDays($daysCsv): array
    {
        $daysCsv = trim((string) $daysCsv);
        if ($daysCsv === '') return [];
        $out = [];
        foreach (explode(',', $daysCsv) as $d) {
            $n = (int) trim($d);
            if ($n >= 1 && $n <= 7) $out[$n] = $n;
        }
        return array_values($out);
    }

    /**
     * Um dia (ISO-8601 1..7) é permitido para envio?
     *
     * Precedência:
     *   1) Se days_of_week estiver definido, ele MANDA (ignora send_weekends).
     *   2) Sem days_of_week: cai na regra antiga — sábado/domingo só se
     *      $sendWeekends; dias úteis sempre liberados.
     */
    public static function isDayAllowed(int $isoDow, $daysCsv, bool $sendWeekends): bool
    {
        $days = self::parseDays($daysCsv);
        if (!empty($days)) {
            return in_array($isoDow, $days, true);
        }
        // Fallback histórico: 6=sábado, 7=domingo.
        if (($isoDow === 6 || $isoDow === 7) && !$sendWeekends) return false;
        return true;
    }

    /**
     * O horário "HH:MM:SS" está dentro de [start, end]?
     * Comparação lexicográfica de strings de horário zero-padded (segura).
     */
    public static function isTimeWithin(string $now, string $start, string $end): bool
    {
        return $now >= $start && $now <= $end;
    }

    /**
     * Pode enviar AGORA? Combina dia permitido + horário dentro da janela.
     *
     * @param string $windowStart "HH:MM:SS"
     * @param string $windowEnd   "HH:MM:SS"
     * @param string $daysCsv     CSV ISO-8601 ("" = todos)
     * @param bool   $sendWeekends
     * @param int|null $nowTs     timestamp de referência (null = time())
     */
    public static function canSendNow(string $windowStart, string $windowEnd, $daysCsv, bool $sendWeekends, ?int $nowTs = null): bool
    {
        $nowTs = $nowTs ?? time();
        $isoDow = (int) date('N', $nowTs);
        if (!self::isDayAllowed($isoDow, $daysCsv, $sendWeekends)) return false;
        return self::isTimeWithin(date('H:i:s', $nowTs), $windowStart, $windowEnd);
    }

    /**
     * Próximo instante válido de envio (timestamp), a partir de "agora".
     *
     * Procura o próximo momento que satisfaça dia permitido + dentro da janela:
     *   - hoje, se ainda não chegou o window_start e o dia é permitido;
     *   - senão, o window_start do próximo dia permitido (varre até 8 dias).
     *
     * Retorna sempre um timestamp >= now. Se nada for encontrado em 8 dias
     * (configuração impossível), devolve now + 1h como salvaguarda.
     */
    public static function nextSendTime(string $windowStart, string $windowEnd, $daysCsv, bool $sendWeekends, ?int $nowTs = null): int
    {
        $nowTs = $nowTs ?? time();

        // Se já pode enviar agora, o próximo instante é agora.
        if (self::canSendNow($windowStart, $windowEnd, $daysCsv, $sendWeekends, $nowTs)) {
            return $nowTs;
        }

        for ($offset = 0; $offset <= 8; $offset++) {
            $dayTs = strtotime("+{$offset} day", $nowTs);
            $isoDow = (int) date('N', $dayTs);
            if (!self::isDayAllowed($isoDow, $daysCsv, $sendWeekends)) continue;

            $startTs = strtotime(date('Y-m-d', $dayTs) . ' ' . $windowStart);
            if ($startTs === false) continue;

            if ($offset === 0) {
                // Hoje é dia permitido: se ainda não chegou o início da janela,
                // agenda para o início de hoje. Se já passou do fim, segue pro
                // próximo dia permitido.
                if ($nowTs < $startTs) return $startTs;
                continue;
            }
            return $startTs;
        }

        // Salvaguarda: configuração sem nenhum dia válido.
        return $nowTs + 3600;
    }
}
