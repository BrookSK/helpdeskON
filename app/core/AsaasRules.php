<?php

/**
 * Regras puras (sem rede) da integração Asaas (Fase 5).
 *
 * Base URL por ambiente, montagem de payload de cobrança e interpretação do
 * webhook (evento -> ação interna). A chamada HTTP real fica em AsaasApi.
 */
class AsaasRules
{
    public const BASE_PROD = 'https://api.asaas.com/v3';
    public const BASE_SANDBOX = 'https://api-sandbox.asaas.com/v3';

    public static function baseUrl(bool $sandbox): string
    {
        return $sandbox ? self::BASE_SANDBOX : self::BASE_PROD;
    }

    /** Mapeia nosso método para o billingType do Asaas. */
    public static function billingType(?string $method): string
    {
        switch ($method) {
            case 'pix': return 'PIX';
            case 'boleto': return 'BOLETO';
            case 'cartao': return 'CREDIT_CARD';
            default: return 'UNDEFINED';
        }
    }

    /**
     * Payload de criação de uma cobrança avulsa no Asaas.
     * @param string $customerId id do cliente no Asaas
     */
    public static function chargePayload(string $customerId, float $value, ?string $dueDate, ?string $method, ?string $description): array
    {
        $p = [
            'customer' => $customerId,
            'billingType' => self::billingType($method),
            'value' => round($value, 2),
        ];
        if ($dueDate !== null && trim($dueDate) !== '') $p['dueDate'] = $dueDate;
        if ($description !== null && trim($description) !== '') $p['description'] = $description;
        return $p;
    }

    /**
     * Payload de assinatura (cobrança recorrente) no Asaas.
     * @param string $cycle 'monthly'|'yearly' -> MONTHLY|YEARLY
     */
    public static function subscriptionPayload(string $customerId, float $value, string $cycle, ?string $nextDueDate, ?string $method, ?string $description): array
    {
        $p = [
            'customer' => $customerId,
            'billingType' => self::billingType($method),
            'value' => round($value, 2),
            'cycle' => $cycle === 'yearly' ? 'YEARLY' : 'MONTHLY',
        ];
        if ($nextDueDate !== null && trim($nextDueDate) !== '') $p['nextDueDate'] = $nextDueDate;
        if ($description !== null && trim($description) !== '') $p['description'] = $description;
        return $p;
    }

    /**
     * Interpreta o evento do webhook Asaas -> ação interna:
     *  - 'paid'    : pagamento confirmado/recebido
     *  - 'overdue' : vencida
     *  - 'ignore'  : demais eventos
     */
    public static function interpretEvent(?string $event): string
    {
        $e = strtoupper(trim((string)$event));
        if (in_array($e, ['PAYMENT_CONFIRMED', 'PAYMENT_RECEIVED', 'PAYMENT_RECEIVED_IN_CASH'], true)) return 'paid';
        if ($e === 'PAYMENT_OVERDUE') return 'overdue';
        return 'ignore';
    }

    public static function isConfigured(?string $token): bool
    {
        return is_string($token) && trim($token) !== '';
    }
}
