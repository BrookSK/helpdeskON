<?php

/**
 * Regras puras (sem rede) de notificação da Proposta (esteira comercial).
 *
 * Isola o que é testável: normalização de contatos do cliente, montagem das
 * mensagens de envio (link ao cliente) e dos avisos à equipe (aceite/recusa).
 * O disparo real (WhatsApp/e-mail) fica no controller, não testável no dev.
 */
class ProposalDelivery
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

    /**
     * Dados do cliente a partir da linha da proposta (snapshot client_* ou dados
     * do contato do CRM). Retorna ['name','email','phone'] (email/phone podem ser null).
     */
    public static function clientContact(array $proposal): array
    {
        return [
            'name'  => trim((string)($proposal['client_name'] ?? ($proposal['crm_contact_name'] ?? ''))) ?: 'Cliente',
            'email' => self::normalizeEmail($proposal['client_email'] ?? ($proposal['crm_contact_email'] ?? null)),
            'phone' => self::normalizePhone($proposal['client_phone'] ?? ($proposal['crm_contact_phone'] ?? null)),
        ];
    }

    /** Mensagem de WhatsApp enviada ao CLIENTE com o link da proposta. */
    public static function clientWhatsapp(string $name, string $link, ?string $title, ?string $company): string
    {
        $saud = $name !== '' ? "Olá, {$name}!" : 'Olá!';
        $assunto = $title ? " \"{$title}\"" : '';
        $msg = "{$saud}\n\n"
            . ($company ? "*{$company}*\n" : '')
            . "Preparamos sua proposta{$assunto}. Você pode visualizar, aceitar ou pedir ajustes pelo link abaixo:\n"
            . $link;
        return $msg;
    }

    public static function clientEmailSubject(?string $title): string
    {
        return 'Sua proposta' . ($title ? " — {$title}" : '') . ' está pronta';
    }

    /** Corpo HTML do e-mail ao cliente (usar com Mailer::template no controller). */
    public static function clientEmailBody(string $name, string $link, ?string $title): string
    {
        $saud = $name !== '' ? htmlspecialchars($name) : 'Olá';
        $t = $title ? htmlspecialchars($title) : 'sua proposta';
        return "<p>Olá, <strong>{$saud}</strong>!</p>"
            . "<p>Preparamos <strong>{$t}</strong>. Clique no botão abaixo para visualizar, aceitar ou pedir ajustes:</p>"
            . "<p style='text-align:center;margin:25px 0;'>"
            . "<a href='{$link}' style='background:#00BFA6;color:#fff;padding:12px 30px;border-radius:8px;text-decoration:none;font-weight:600;display:inline-block;'>Ver proposta</a>"
            . "</p>"
            . "<p style='font-size:0.8rem;color:#888;word-break:break-all;'>Link direto: {$link}</p>";
    }

    /** Aviso à EQUIPE (WhatsApp do grupo) quando o cliente responde. */
    public static function teamWhatsapp(string $event, ?string $title, ?string $reason = null): string
    {
        $t = $title ? " \"{$title}\"" : '';
        if ($event === 'accepted') {
            return "✅ *Proposta aceita*\nO cliente aceitou a proposta{$t}. Hora de gerar o contrato.";
        }
        if ($event === 'rejected') {
            $m = "❌ *Proposta recusada*\nO cliente recusou a proposta{$t}.";
            if ($reason) $m .= "\n*Motivo:* {$reason}";
            return $m;
        }
        return "Proposta{$t}: atualização.";
    }
}
