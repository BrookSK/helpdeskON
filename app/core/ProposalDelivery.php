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

    /**
     * Legenda do documento PDF enviado ao CLIENTE pelo WhatsApp. O PDF vai como
     * anexo e já traz, dentro dele, o botão para aceitar/recusar/pedir ajustes.
     */
    public static function clientWhatsapp(string $name, ?string $title, ?string $company): string
    {
        $saud = $name !== '' ? "Olá, {$name}!" : 'Olá!';
        $assunto = $title ? " \"{$title}\"" : '';
        return "{$saud}\n\n"
            . ($company ? "*{$company}*\n" : '')
            . "Segue em anexo sua proposta{$assunto}. Dentro do PDF, no final, há o botão para você revisar e responder (aceitar, recusar ou pedir ajustes).";
    }

    public static function clientEmailSubject(?string $title): string
    {
        return 'Sua proposta' . ($title ? " — {$title}" : '') . ' está pronta';
    }

    /**
     * Corpo HTML do e-mail ao cliente. O PDF vai como ANEXO; dentro dele há o
     * botão para revisar e responder. Sem links de PDF no corpo.
     */
    public static function clientEmailBody(string $name, ?string $title): string
    {
        $saud = $name !== '' ? htmlspecialchars($name) : 'Olá';
        $t = $title ? htmlspecialchars($title) : 'sua proposta';
        return "<p>Olá, <strong>{$saud}</strong>!</p>"
            . "<p>Segue em anexo <strong>{$t}</strong> em PDF.</p>"
            . "<p>Dentro do PDF, no final, há um botão para você revisar a proposta e responder: aceitar, recusar ou pedir ajustes.</p>";
    }

    /** Aviso à EQUIPE (WhatsApp do grupo) quando o cliente responde. */
    public static function teamWhatsapp(string $event, ?string $title, ?string $reason = null): string
    {
        $t = $title ? " \"{$title}\"" : '';
        if ($event === 'accepted') {
            $extra = $reason ? "\n{$reason}" : '';
            return "✅ *Proposta aceita*\nO cliente aceitou a proposta{$t}.{$extra}\nHora de gerar o contrato.";
        }
        if ($event === 'rejected') {
            $m = "❌ *Proposta recusada*\nO cliente recusou a proposta{$t}.";
            if ($reason) $m .= "\n*Motivo:* {$reason}";
            return $m;
        }
        if ($event === 'change') {
            $m = "✏️ *Solicitação de alteração*\nO cliente pediu ajustes na proposta{$t}.";
            if ($reason) $m .= "\n*Pedido:* {$reason}";
            return $m;
        }
        return "Proposta{$t}: atualização.";
    }
}
