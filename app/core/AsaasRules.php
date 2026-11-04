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

    /** Cabeçalhos da API. O Asaas recusa requisição sem User-Agent. */
    public static function headers(string $token): array
    {
        return [
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: HelpdeskON',
            'access_token: ' . $token,
        ];
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
    public static function chargePayload(string $customerId, float $value, ?string $dueDate, ?string $method, ?string $description, ?string $externalReference = null): array
    {
        $p = [
            'customer' => $customerId,
            'billingType' => self::billingType($method),
            'value' => round($value, 2),
        ];
        if ($dueDate !== null && trim($dueDate) !== '') $p['dueDate'] = $dueDate;
        if ($description !== null && trim($description) !== '') $p['description'] = $description;
        if ($externalReference !== null && $externalReference !== '') $p['externalReference'] = $externalReference;
        return $p;
    }

    /**
     * Payload de assinatura (cobrança recorrente) no Asaas.
     * @param string $cycle 'monthly'|'yearly' -> MONTHLY|YEARLY
     */
    public static function subscriptionPayload(string $customerId, float $value, string $cycle, ?string $nextDueDate, ?string $method, ?string $description, ?string $externalReference = null): array
    {
        $p = [
            'customer' => $customerId,
            'billingType' => self::billingType($method),
            'value' => round($value, 2),
            'cycle' => $cycle === 'yearly' ? 'YEARLY' : 'MONTHLY',
        ];
        if ($nextDueDate !== null && trim($nextDueDate) !== '') $p['nextDueDate'] = $nextDueDate;
        if ($description !== null && trim($description) !== '') $p['description'] = $description;
        if ($externalReference !== null && $externalReference !== '') $p['externalReference'] = $externalReference;
        return $p;
    }

    /** Só os dígitos do CPF/CNPJ (o Asaas busca e grava assim). */
    public static function documentDigits(?string $document): string
    {
        return preg_replace('/\D/', '', (string)$document);
    }

    /** Payload de criação do cliente no Asaas. Campos vazios ficam de fora. */
    public static function customerPayload(string $name, string $document, ?string $email = null, ?string $phone = null, ?string $externalReference = null): array
    {
        $p = ['name' => mb_substr(trim($name) !== '' ? trim($name) : 'Cliente', 0, 100), 'cpfCnpj' => self::documentDigits($document)];
        if ($email !== null && filter_var(trim($email), FILTER_VALIDATE_EMAIL)) $p['email'] = trim($email);
        $digits = preg_replace('/\D/', '', (string)$phone);
        if (strlen($digits) >= 12 && strpos($digits, '55') === 0) $digits = substr($digits, 2);
        if (strlen($digits) >= 10 && strlen($digits) <= 11) $p['mobilePhone'] = $digits;
        if ($externalReference !== null && $externalReference !== '') $p['externalReference'] = $externalReference;
        $p['notificationDisabled'] = true;
        return $p;
    }

    /** Referência que liga a cobrança do Asaas à parcela local. */
    public static function chargeReference(int $chargeId): string
    {
        return 'hd-charge-' . $chargeId;
    }

    /** Referência que liga a assinatura do Asaas ao projeto financeiro. */
    public static function subscriptionReference(int $projectId): string
    {
        return 'hd-sub-' . $projectId;
    }

    /** Id da parcela local a partir da externalReference, ou null. */
    public static function chargeIdFromReference(?string $ref): ?int
    {
        return preg_match('/^hd-charge-(\d+)$/', (string)$ref, $m) ? (int)$m[1] : null;
    }

    /** Primeiro item de uma listagem do Asaas ({data: [...]}), ou null. */
    public static function firstListed(?array $data): ?array
    {
        $list = $data['data'] ?? null;
        return is_array($list) && isset($list[0]) && is_array($list[0]) ? $list[0] : null;
    }

    /** Mensagem legível de um erro do Asaas ({errors: [{description}]}). */
    public static function errorMessage(?array $data, ?int $http = null): string
    {
        $parts = [];
        foreach ((array)($data['errors'] ?? []) as $e) {
            $d = is_array($e) ? trim((string)($e['description'] ?? ($e['code'] ?? ''))) : trim((string)$e);
            if ($d !== '') $parts[] = $d;
        }
        if ($parts) return implode('; ', $parts);
        return 'Asaas HTTP ' . ($http ?? 0);
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
