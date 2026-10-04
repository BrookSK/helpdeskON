<?php

/**
 * Regras puras (sem banco/HTTP) da APROVAÇÃO DE ESCOPO técnico pelo cliente.
 *
 * Após a análise/estimativa, a equipe define o escopo técnico (o que será feito,
 * o que não será, como será executado e a estimativa) e envia ao cliente, que
 * aprova ou recusa (com motivo). Em caso de recusa, a demanda volta para ajustes.
 *
 * Centraliza, de forma testável:
 *  - o status em que o cliente decide sobre o escopo;
 *  - as transições válidas de decisão do cliente sobre o escopo;
 *  - a validação do escopo e do motivo de recusa.
 *
 * Segue o padrão de TicketAccess (classe pura, fonte única de verdade).
 */
class ScopeRules
{
    /** Status em que o escopo aguarda a decisão do cliente. */
    public const STATUS_AGUARDANDO = 'aguardando_aprovacao_escopo';

    /** Status para onde a demanda vai quando o cliente APROVA o escopo. */
    public const STATUS_APROVADO = 'in_progress';

    /** Status para onde a demanda volta quando o cliente RECUSA o escopo. */
    public const STATUS_RECUSADO = 'in_progress';

    /**
     * O escopo está completo o suficiente para ser enviado ao cliente?
     * Exigimos ao menos "o que será feito" (incluído); os demais são recomendados.
     */
    public static function isScopeComplete(array $scope): bool
    {
        $incluido = trim((string)($scope['escopo_incluido'] ?? ''));
        return $incluido !== '';
    }

    /** O cliente pode decidir sobre o escopo quando a demanda está aguardando. */
    public static function clientCanDecideScope(?string $currentStatus): bool
    {
        return $currentStatus === self::STATUS_AGUARDANDO;
    }

    /**
     * Valida o motivo de recusa do escopo. A recusa EXIGE justificativa.
     * Retorna o motivo saneado (trim) ou null se inválido.
     */
    public static function sanitizeRejectionReason($reason): ?string
    {
        if (!is_string($reason)) {
            return null;
        }
        $r = trim($reason);
        return $r !== '' ? $r : null;
    }

    /**
     * Resolve a transição de decisão de escopo do cliente.
     *
     * @param string      $decision 'approve' ou 'reject'
     * @param string|null $reason   motivo (obrigatório quando reject)
     * @return array{ok:bool,status:?string,reason:?string,error:?string}
     */
    public static function resolveDecision(string $decision, $reason = null): array
    {
        if ($decision === 'approve') {
            return ['ok' => true, 'status' => self::STATUS_APROVADO, 'reason' => null, 'error' => null];
        }
        if ($decision === 'reject') {
            $clean = self::sanitizeRejectionReason($reason);
            if ($clean === null) {
                return ['ok' => false, 'status' => null, 'reason' => null, 'error' => 'Informe o motivo da recusa do escopo.'];
            }
            return ['ok' => true, 'status' => self::STATUS_RECUSADO, 'reason' => $clean, 'error' => null];
        }
        return ['ok' => false, 'status' => null, 'reason' => null, 'error' => 'Decisão inválida.'];
    }
}
