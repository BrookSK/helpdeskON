<?php

/**
 * Regras puras (sem banco/HTTP) da RÉGUA DE HOMOLOGAÇÃO de 48 horas.
 *
 * Quando a demanda entra em homologação, o cliente tem 48h para testar e
 * aprovar/recusar. A comunicação segue 3 contatos:
 *   1º contato  (0h)   : a entrega está disponível para homologação;
 *   2º contato  (24h)  : sem retorno, solicitar validação + previsão de publicação;
 *   3º contato  (~42h) : próximo do fim, publicação ocorrerá nas próximas 6h.
 * Ao fim das 48h sem manifestação, a entrega pode seguir para produção.
 *
 * Esta classe apenas DECIDE, a partir do tempo decorrido e de quais contatos já
 * foram feitos, qual é a próxima ação (enviar contato N ou liberar). O envio em
 * si e a persistência ficam fora (CronController), para manter a regra testável.
 *
 * Segue o padrão de SequenceWindowRules/AgendaRules (fonte única de verdade).
 */
class HomologacaoRules
{
    /** Janela total de homologação, em horas. */
    public const WINDOW_HOURS = 48;

    /** Momentos (em horas desde o início) de cada contato da régua. */
    public const CONTACT1_HOUR = 0;   // imediato ao entrar em homologação
    public const CONTACT2_HOUR = 24;  // 24h sem retorno
    public const CONTACT3_HOUR = 42;  // ~6h antes do fim das 48h

    /** Ações possíveis decididas pela régua. */
    public const ACTION_NONE = 'none';           // nada a fazer agora
    public const ACTION_CONTACT1 = 'contact1';   // enviar 1º contato
    public const ACTION_CONTACT2 = 'contact2';   // enviar 2º contato
    public const ACTION_CONTACT3 = 'contact3';   // enviar 3º contato
    public const ACTION_RELEASE = 'release';     // liberar para produção (48h)

    /**
     * Decide a próxima ação da régua de homologação.
     *
     * @param float $hoursElapsed horas decorridas desde homolog_started_at
     * @param array $sent  flags do que já foi feito:
     *   ['contact1'=>bool,'contact2'=>bool,'contact3'=>bool,'released'=>bool]
     * @return string uma das constantes ACTION_*
     *
     * Precedência: liberar (48h) tem prioridade; depois o contato de maior ordem
     * cujo horário já chegou e que ainda não foi enviado. Assim, se o cron atrasou
     * e já passou de 42h, enviamos direto o 3º contato (não "corremos atrás" dos
     * anteriores já ultrapassados — mas também não pulamos um pendente anterior
     * cujo horário já passou: enviamos o mais recente devido).
     */
    public static function nextAction(float $hoursElapsed, array $sent): string
    {
        $c1 = !empty($sent['contact1']);
        $c2 = !empty($sent['contact2']);
        $c3 = !empty($sent['contact3']);
        $released = !empty($sent['released']);

        // Fim da janela: libera para produção (uma única vez).
        if ($hoursElapsed >= self::WINDOW_HOURS) {
            return $released ? self::ACTION_NONE : self::ACTION_RELEASE;
        }

        // Dentro da janela: envia o contato devido de maior ordem ainda pendente.
        if ($hoursElapsed >= self::CONTACT3_HOUR && !$c3) {
            return self::ACTION_CONTACT3;
        }
        if ($hoursElapsed >= self::CONTACT2_HOUR && !$c2) {
            return self::ACTION_CONTACT2;
        }
        if ($hoursElapsed >= self::CONTACT1_HOUR && !$c1) {
            return self::ACTION_CONTACT1;
        }
        return self::ACTION_NONE;
    }

    /** Horas decorridas entre dois instantes (now - started). Nunca negativo. */
    public static function hoursElapsed(string $startedAt, ?string $now = null): float
    {
        $start = strtotime($startedAt);
        $ref = $now !== null ? strtotime($now) : time();
        if ($start === false || $ref === false || $ref <= $start) {
            return 0.0;
        }
        return ($ref - $start) / 3600;
    }

    /** O prazo de 48h expirou? */
    public static function windowExpired(string $startedAt, ?string $now = null): bool
    {
        return self::hoursElapsed($startedAt, $now) >= self::WINDOW_HOURS;
    }

    /**
     * Monta a mensagem de cada contato da régua (texto puro, testável).
     *
     * @param string      $action   ACTION_CONTACT1|2|3
     * @param array       $data      ticket_number, title, previsao (opcional)
     */
    public static function contactMessage(string $action, array $data): string
    {
        $num = $data['ticket_number'] ?? '?';
        $title = $data['title'] ?? '';
        $previsao = trim((string)($data['previsao'] ?? ''));

        switch ($action) {
            case self::ACTION_CONTACT1:
                return "Olá! A entrega da demanda #{$num} \"{$title}\" está disponível para homologação. "
                    . "Você tem até 48h para testar e aprovar ou recusar pelo sistema.";
            case self::ACTION_CONTACT2:
                $msg = "Olá! A demanda #{$num} \"{$title}\" continua aguardando sua validação em homologação. "
                    . "Pedimos que valide o quanto antes.";
                if ($previsao !== '') {
                    $msg .= " Previsão de publicação: {$previsao}.";
                }
                return $msg;
            case self::ACTION_CONTACT3:
                return "Atenção: a demanda #{$num} \"{$title}\" está próxima do fim do prazo de homologação. "
                    . "Sem manifestação, a publicação ocorrerá nas próximas 6 horas.";
            default:
                return '';
        }
    }
}
