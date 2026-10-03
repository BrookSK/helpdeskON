<?php

/**
 * Regras puras (sem rede) de notificação do Contrato (esteira comercial).
 *
 * Espelha ProposalDelivery: normaliza contato do cliente, monta a mensagem de
 * envio (link para aprovar) e os avisos à equipe (aprovado / ajuste / assinado).
 * O disparo real (WhatsApp/e-mail) fica no controller.
 */
class ContractDelivery
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

    /** Dados do cliente a partir da linha do contrato. */
    public static function clientContact(array $contract): array
    {
        return [
            'name'  => trim((string)($contract['client_name'] ?? '')) ?: 'Cliente',
            'email' => self::normalizeEmail($contract['client_email'] ?? null),
            'phone' => self::normalizePhone($contract['client_phone'] ?? null),
        ];
    }

    /** WhatsApp ao CLIENTE com o link para aprovar/revisar o contrato. */
    public static function clientWhatsapp(string $name, string $link, ?string $title, ?string $company): string
    {
        $saud = $name !== '' ? "Olá, {$name}!" : 'Olá!';
        $assunto = $title ? " \"{$title}\"" : '';
        return "{$saud}\n\n"
            . ($company ? "*{$company}*\n" : '')
            . "Seu contrato{$assunto} está pronto para revisão. Você pode ler e aprovar (ou pedir ajustes) pelo link abaixo:\n"
            . $link;
    }

    public static function clientEmailSubject(?string $title): string
    {
        return 'Seu contrato' . ($title ? " — {$title}" : '') . ' está pronto para revisão';
    }

    /** Corpo HTML do e-mail ao cliente (usar com Mailer::template). */
    public static function clientEmailBody(string $name, string $link, ?string $title): string
    {
        $saud = $name !== '' ? htmlspecialchars($name) : 'Olá';
        $t = $title ? htmlspecialchars($title) : 'seu contrato';
        return "<p>Olá, <strong>{$saud}</strong>!</p>"
            . "<p>O contrato <strong>{$t}</strong> está pronto para sua revisão. Clique no botão abaixo para ler e aprovar (ou pedir ajustes):</p>"
            . "<p style='text-align:center;margin:25px 0;'>"
            . "<a href='{$link}' style='background:#00BFA6;color:#fff;padding:12px 30px;border-radius:8px;text-decoration:none;font-weight:600;display:inline-block;'>Revisar contrato</a>"
            . "</p>"
            . "<p style='font-size:0.8rem;color:#888;word-break:break-all;'>Link direto: {$link}</p>";
    }

    /** Aviso à EQUIPE quando há evento do contrato (approved/rejected/signed). */
    public static function teamWhatsapp(string $event, ?string $title, ?string $reason = null): string
    {
        $t = $title ? " \"{$title}\"" : '';
        switch ($event) {
            case 'approved':
                return "✅ *Contrato aprovado*\nO cliente aprovou o contrato{$t}. Pronto para enviar à assinatura.";
            case 'rejected':
                $m = "✏️ *Contrato: ajuste solicitado*\nO cliente pediu ajustes no contrato{$t}.";
                if ($reason) $m .= "\n*Motivo:* {$reason}";
                return $m;
            case 'signed':
                return "✍️ *Contrato assinado*\nO contrato{$t} foi assinado. Siga para o financeiro.";
            default:
                return "Contrato{$t}: atualização.";
        }
    }
}
