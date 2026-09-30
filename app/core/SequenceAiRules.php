<?php

/**
 * Regras de negócio puras (sem banco/HTTP) da IA (ChatGPT) nas sequências.
 *
 * Fonte única e testável para decidir qual SAÍDA um bloco de IA deve tomar a
 * partir da classificação de intenção do lead. Extraídas do SequenceEngine
 * (doAi/doAiAgent + step) para evitar divergência entre o comportamento e o
 * que é testado.
 *
 * REGRA DE NEGÓCIO (definida com o time, prospecção fria):
 * Só quem demonstra INTERESSE CLARO segue para o AGENDAMENTO (saída SIM).
 * Quem recusa, fica indeciso até o fim, ou esgota o limite de interações sem
 * interesse claro é ENCERRADO com a tag "sem interesse" (saída NÃO) — e não
 * pode voltar a ser prospectado. Isso evita insistir com quem não quis
 * (comportamento observado em produção: leads "sem interesse" recebendo
 * agendamento e novas mensagens).
 *
 * IMPORTANTE — mudança deliberada de comportamento: uma versão anterior enviava
 * indecisão/limite/erro para o AGENDAMENTO (SIM). Isso foi revertido a pedido do
 * negócio; a expectativa agora é a inversa (só interesse claro agenda). Falha
 * TÉCNICA da IA (sem chave/HTTP/exception) NÃO decide o lead: não avança
 * (mantém no nó para reprocessar), pois não é recusa do lead nem interesse.
 */
class SequenceAiRules
{
    /** Intenções possíveis classificadas pela IA. */
    public const INTENT_YES = 'yes';         // quer avançar/agendar
    public const INTENT_NO = 'no';           // recusa explícita
    public const INTENT_UNCLEAR = 'unclear'; // ainda tirando dúvidas/indeciso

    /** Ações resultantes que o motor da sequência deve executar. */
    public const ACTION_ADVANCE_YES = 'advance_yes'; // segue pela saída SIM (agendamento)
    public const ACTION_ADVANCE_NO = 'advance_no';   // segue pela saída NÃO (encerra + tag)
    public const ACTION_LISTEN = 'listen';           // permanece no nó aguardando o lead
    public const ACTION_HOLD = 'hold';               // não decide (erro técnico): reprocessa depois

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
     * Regras (só interesse CLARO agenda):
     *  - Interesse claro (yes)           -> agenda (advance_yes).
     *  - Recusa explícita (no)           -> encerra + tag (advance_no).
     *  - Bloco INATIVO (só classifica)   -> sem loop: 'unclear' encerra
     *                                       (advance_no). Sem interesse claro
     *                                       não vira agendamento.
     *  - Bloco ATIVO + 'unclear':
     *      * ainda dentro do limite      -> continua tirando dúvidas (listen);
     *      * limite de interações batido -> encerra + tag (advance_no): o lead
     *                                       nunca demonstrou interesse claro.
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

        // Interesse claro sempre agenda.
        if ($intent === self::INTENT_YES) return self::ACTION_ADVANCE_YES;

        // Recusa explícita sempre encerra, em qualquer modo.
        if ($intent === self::INTENT_NO) return self::ACTION_ADVANCE_NO;

        // A partir daqui, intent = unclear (indecisão/dúvida).
        if (!$agentActive) {
            // Modo classificação (uma passada): sem interesse claro, encerra e
            // marca "sem interesse". Não empurra indeciso para o agendamento.
            return self::ACTION_ADVANCE_NO;
        }

        // Modo ATIVO: enquanto há espaço no ciclo, continua tirando as dúvidas do
        // lead (lead com pergunta é lead engajado — merece resposta).
        if ($turns < $maxTurns) return self::ACTION_LISTEN;

        // Limite de interações atingido SEM interesse claro: encerra + tag. Já
        // demos várias chances de o lead demonstrar interesse e ele não o fez.
        return self::ACTION_ADVANCE_NO;
    }

    /**
     * Decide a ação do bloco IA (ChatGPT) em modo DECISÃO simples, a partir do
     * booleano de decisão retornado pela IA. Mantido separado do agentAction
     * para deixar explícito que aqui a IA responde apenas SIM/NÃO (sem loop).
     *
     * decision=true  -> SIM (agendamento); decision=false -> NÃO (encerra + tag).
     */
    public static function decisionAction(bool $decision): string
    {
        return $decision ? self::ACTION_ADVANCE_YES : self::ACTION_ADVANCE_NO;
    }
}
