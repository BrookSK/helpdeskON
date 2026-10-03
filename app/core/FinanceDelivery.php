<?php

/**
 * Regras puras (sem rede) de notificação do Financeiro (esteira comercial).
 *
 * Monta o resumo do plano de cobranças (entrada + parcelas) e as mensagens de
 * aviso ao cliente. O disparo real (WhatsApp/e-mail) fica no controller.
 */
class FinanceDelivery
{
    public static function normalizeEmail(?string $email): ?string
    {
        $e = trim((string)$email);
        return filter_var($e, FILTER_VALIDATE_EMAIL) ? $e : null;
    }

    public static function normalizePhone(?string $phone): ?string
    {
        $d = preg_replace('/\D+/', '', (string)$phone);
        return ($d !== '' && strlen($d) >= 10) ? $d : null;
    }

    /** Formata um valor em reais (R$ 1.234,56). */
    public static function money($v): string
    {
        return 'R$ ' . number_format((float)$v, 2, ',', '.');
    }

    private static function kindLabel(string $kind): string
    {
        return [
            'entry' => 'Entrada',
            'installment' => 'Parcela',
            'recurring' => 'Recorrente',
        ][$kind] ?? 'Cobrança';
    }

    private static function fmtDate(?string $d): string
    {
        $ts = $d ? strtotime($d) : false;
        return $ts ? date('d/m/Y', $ts) : 'a definir';
    }

    /**
     * Resumo textual do plano de cobranças, para WhatsApp/e-mail.
     * @param array<int,array> $charges linhas de finance_charges
     */
    public static function planSummaryLines(array $charges): array
    {
        $lines = [];
        foreach ($charges as $c) {
            $kind = $c['kind'] ?? 'installment';
            $label = self::kindLabel($kind);
            if ($kind === 'installment' && !empty($c['installment_no'])) {
                $label .= ' ' . (int)$c['installment_no'] . (!empty($c['installment_total']) ? '/' . (int)$c['installment_total'] : '');
            }
            $lines[] = $label . ': ' . self::money($c['amount'] ?? 0) . ' — vence ' . self::fmtDate($c['due_date'] ?? null);
        }
        return $lines;
    }

    public static function total(array $charges): float
    {
        $sum = 0.0;
        foreach ($charges as $c) $sum += (float)($c['amount'] ?? 0);
        return round($sum, 2);
    }

    /** WhatsApp ao cliente com o resumo do plano de pagamento. */
    public static function clientWhatsapp(string $name, array $charges, ?string $company): string
    {
        $saud = $name !== '' ? "Olá, {$name}!" : 'Olá!';
        $msg = "{$saud}\n\n"
            . ($company ? "*{$company}*\n" : '')
            . "Seu plano de pagamento está pronto:\n\n"
            . implode("\n", array_map(fn($l) => '• ' . $l, self::planSummaryLines($charges)))
            . "\n\n*Total:* " . self::money(self::total($charges))
            . "\n\nAs cobranças serão enviadas pelos canais combinados. Qualquer dúvida, estamos à disposição.";
        return $msg;
    }

    public static function clientEmailSubject(?string $company): string
    {
        return 'Seu plano de pagamento' . ($company ? " — {$company}" : '');
    }

    /** Corpo HTML do e-mail ao cliente (usar com Mailer::template). */
    public static function clientEmailBody(string $name, array $charges): string
    {
        $saud = $name !== '' ? htmlspecialchars($name) : 'Olá';
        $rows = '';
        foreach (self::planSummaryLines($charges) as $l) {
            $rows .= '<li>' . htmlspecialchars($l) . '</li>';
        }
        return "<p>Olá, <strong>{$saud}</strong>!</p>"
            . "<p>Seu plano de pagamento está pronto:</p>"
            . "<ul>{$rows}</ul>"
            . "<p><strong>Total: " . htmlspecialchars(self::money(self::total($charges))) . "</strong></p>"
            . "<p>As cobranças serão enviadas pelos canais combinados. Qualquer dúvida, estamos à disposição.</p>";
    }
}
