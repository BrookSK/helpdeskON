<?php

/**
 * Regras de negócio puras (sem banco/HTTP) da IA (ChatGPT) nas sequências.
 *
 * Fonte única e testável para decidir qual SAÍDA um bloco de IA deve tomar a
 * partir da classificação de intenção do lead. Extraídas do SequenceEngine
 * (doAi/doAiAgent + step) para evitar divergência entre o comportamento e o
 * que é testado.
 *
 * Motivação (bug relatado): a IA apresentava a resposta mas, em vários casos,
 * ENCERRAVA o atendimento sem chegar à etapa de AGENDAMENTO. Isso acontecia
 * porque "indecisão" (unclear), o limite de interações e falhas técnicas eram
 * todos tratados como NÃO (recusa) — a saída que encerra. A regra correta é:
 * SÓ uma recusa EXPLÍCITA do lead encerra; ambiguidade, dúvida ou limite de
 * interações atingido com o lead ainda engajado devem seguir para o caminho de
 * agendamento (SIM), nunca encerrar.
 */
class SequenceAiRules
{
    /** Intenções possíveis classificadas pela IA. */
    public const INTENT_YES = 'yes';         // quer avançar/agendar
    public const INTENT_NO = 'no';           // recusa explícita
    public const INTENT_UNCLEAR = 'unclear'; // ainda tirando dúvidas/indeciso

    /** Ações resultantes que o motor da sequência deve executar. */
    public const ACTION_ADVANCE_YES = 'advance_yes'; // segue pela saída SIM (agendamento)
    public const ACTION_ADVANCE_NO = 'advance_no';   // segue pela saída NÃO (encerra)
    public const ACTION_LISTEN = 'listen';           // permanece no nó aguardando o lead

    /**
     * Normaliza a intenção crua vinda da IA para um dos três valores válidos.
     * Qualquer valor desconhecido/ausente é tratado como 'unclear' (nunca como
     * recusa): a dúvida do modelo não deve, por si só, encerrar o atendimento.
     */
    public static function normalizeIntent($intent): string
    {
        $intent = is_string($intent) ? strtolower(trim($intent)) : '';
        if ($intent === self::INTENT_YES) return self::INTENT_YES;
        if ($intent === self::INTENT_NO) return self::INTENT_NO;
        return self::INTENT_UNCLEAR;
    }

    /**
     * Decide a ação do ATENDENTE IA (FAQ / bloco ai_agent) a partir da intenção
     * classificada, do estado do bloco e do contador de interações.
     *
     * Regras:
     *  - Recusa explícita (no)           -> encerra (advance_no).
     *  - Interesse claro (yes)           -> agenda (advance_yes).
     *  - Bloco INATIVO (só classifica)   -> sem loop: 'unclear' NÃO encerra;
     *                                       vira SIM (segue para o agendamento).
     *                                       Só encerra com recusa explícita.
     *  - Bloco ATIVO + 'unclear':
     *      * ainda dentro do limite      -> continua no ciclo (listen);
     *      * limite de interações batido -> NÃO encerra por cansaço: segue para
     *                                       o agendamento (advance_yes), pois o
     *                                       lead nunca recusou.
     *
     * @param string $intent      Intenção crua ou normalizada (yes|no|unclear).
     * @param bool   $agentActive Bloco em modo ATIVO (loop de dúvidas) x INATIVO.
     * @param int    $turns       Interações já feitas neste bloco.
     * @param int    $maxTurns    Teto de interações (>= 1).
     * @return string ACTION_ADVANCE_YES | ACTION_ADVANCE_NO | ACTION_LISTEN
     */
    public static function agentAction($intent, bool $agentActive, int $turns = 0, int $maxTurns = 6): string
    {
        $intent = self::normalizeIntent($intent);
        $maxTurns = max(1, $maxTurns);

        // Recusa explícita sempre encerra, em qualquer modo.
        if ($intent === self::INTENT_NO) return self::ACTION_ADVANCE_NO;

        // Interesse claro sempre agenda.
        if ($intent === self::INTENT_YES) return self::ACTION_ADVANCE_YES;

        // A partir daqui, intent = unclear (indecisão/dúvida).
        if (!$agentActive) {
            // Modo classificação (uma passada): indecisão NÃO encerra — segue
            // para o agendamento. Só a recusa explícita (tratada acima) encerra.
            return self::ACTION_ADVANCE_YES;
        }

        // Modo ATIVO: se ainda há espaço no ciclo, continua tirando dúvidas.
        if ($turns < $maxTurns) return self::ACTION_LISTEN;

        // Limite de interações atingido com o lead ainda engajado (sem recusa):
        // não encerra — encaminha para o agendamento.
        return self::ACTION_ADVANCE_YES;
    }

    /**
     * Decide a ação do bloco IA (ChatGPT) em modo DECISÃO simples, a partir do
     * booleano de decisão retornado pela IA. Mantido separado do agentAction
     * para deixar explícito que aqui a IA responde apenas SIM/NÃO (sem loop).
     *
     * decision=true  -> SIM (agendamento); decision=false -> NÃO (encerra).
     */
    public static function decisionAction(bool $decision): string
    {
        return $decision ? self::ACTION_ADVANCE_YES : self::ACTION_ADVANCE_NO;
    }
}
