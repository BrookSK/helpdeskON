<?php

/**
 * Regras puras (sem banco/HTTP) da criação automática do ACESSO DO CLIENTE no
 * onboarding (login + PIN).
 *
 * Quando a etapa 'client_access' é concluída, o sistema cria/garante o usuário
 * dono do cliente, gera o PIN e notifica. Esta classe isola as decisões
 * testáveis: qual etapa dispara, escolha do contato de destino e o texto das
 * mensagens (WhatsApp/e-mail) ao cliente e à equipe.
 */
class ClientAccessRules
{
    /** step_key da etapa de onboarding que dispara a criação do acesso. */
    public const TRIGGER_STEP = 'client_access';

    /** Esta etapa deve disparar a criação automática do acesso do cliente? */
    public static function stepTriggersAccess(?string $stepKey): bool
    {
        return $stepKey === self::TRIGGER_STEP;
    }

    /**
     * Escolhe o melhor contato (nome/telefone/e-mail) para receber os acessos,
     * a partir das linhas de client_contacts do onboarding. Prioriza is_primary;
     * cai para o primeiro contato. Normaliza telefone (só dígitos) e valida e-mail.
     *
     * @param array<int,array> $contacts
     * @return array{name:string,phone:?string,email:?string,found:bool}
     */
    public static function pickRecipient(array $contacts): array
    {
        $out = ['name' => 'Cliente', 'phone' => null, 'email' => null, 'found' => false];
        if (empty($contacts)) return $out;

        $chosen = $contacts[0];
        foreach ($contacts as $c) {
            if (!empty($c['is_primary'])) { $chosen = $c; break; }
        }
        $out['found'] = true;
        $name = trim((string)($chosen['name'] ?? ''));
        $out['name'] = $name !== '' ? $name : 'Cliente';

        $phone = preg_replace('/\D+/', '', (string)($chosen['phone'] ?? ''));
        $out['phone'] = ($phone !== '' && strlen($phone) >= 10) ? $phone : null;

        $email = trim((string)($chosen['email'] ?? ''));
        $out['email'] = filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;

        return $out;
    }

    /**
     * Mensagem de WhatsApp ao cliente com o PIN e a orientação de primeiro acesso.
     * O link de senha vai por e-mail (não expõe o token por WhatsApp).
     */
    public static function clientWhatsapp(string $name, string $pin, ?string $company, ?string $loginUrl = null): string
    {
        $msg = "Olá, {$name}!\n\n";
        if ($company) $msg .= "*{$company}*\n";
        $msg .= "Seu acesso ao sistema foi criado. ";
        $msg .= "Enviamos no seu e-mail o link para você definir a senha.\n\n";
        $msg .= "Para entrar rapidinho, use seu PIN de acesso: *{$pin}*";
        if ($loginUrl) $msg .= "\nAcesse: {$loginUrl}";
        $msg .= "\n\nQualquer dúvida, é só chamar.";
        return $msg;
    }

    /** Assunto do e-mail ao cliente. */
    public static function clientEmailSubject(?string $company): string
    {
        return 'Seu acesso foi criado' . ($company ? " — {$company}" : '');
    }

    /**
     * Corpo (HTML interno, sem o wrapper do Mailer::template) do e-mail ao
     * cliente informando o PIN. O link de senha é enviado em separado pelo
     * convite de primeiro acesso (sendFirstAccessInvite).
     */
    public static function clientEmailBody(string $name, string $pin, ?string $loginUrl = null): string
    {
        $safeName = htmlspecialchars($name);
        $html = "<p>Olá, <strong>{$safeName}</strong>!</p>";
        $html .= "<p>Seu acesso ao sistema foi criado. Em outro e-mail enviamos o link para você definir a sua senha.</p>";
        $html .= "<p>Para um acesso rápido, você também pode entrar com o seu <strong>PIN</strong>:</p>";
        $html .= "<p style='text-align:center;margin:20px 0;'>"
               . "<span style='display:inline-block;background:#00BFA6;color:#fff;padding:12px 28px;border-radius:8px;"
               . "font-weight:700;font-size:1.4rem;letter-spacing:4px;'>" . htmlspecialchars($pin) . "</span></p>";
        if ($loginUrl) {
            $safeUrl = htmlspecialchars($loginUrl);
            $html .= "<p style='text-align:center;'>Acesse: <a href='{$safeUrl}'>{$safeUrl}</a></p>";
        }
        $html .= "<p>Qualquer dúvida, estamos à disposição.</p>";
        return $html;
    }

    /** Aviso curto à equipe (sino + WhatsApp) de que o acesso do cliente foi criado. */
    public static function teamNotice(string $clientName, bool $createdUser): string
    {
        $verbo = $createdUser ? 'criado' : 'atualizado';
        return "Acesso do cliente {$verbo}: {$clientName}. Login (link de senha) e PIN enviados.";
    }
}
