<?php

/**
 * Regras puras (sem banco/HTTP) das mensagens de ENTREGA do provisionamento ao
 * cliente ("já configuramos seu ambiente; é assim que você usa o Helpdesk").
 *
 * Isola o texto (WhatsApp/e-mail) e a montagem do link de nova demanda, para
 * ficar testável. O envio em si (WhatsApp/e-mail) fica no controller.
 */
class DeliveryNotice
{
    /**
     * Escolhe o melhor contato do cliente a partir das linhas de users da empresa
     * (getUsers ordena is_company_owner DESC). Normaliza telefone e valida e-mail.
     *
     * @param array<int,array> $companyUsers
     * @return array{name:string,phone:?string,email:?string,found:bool}
     */
    public static function pickRecipient(array $companyUsers): array
    {
        $out = ['name' => 'Cliente', 'phone' => null, 'email' => null, 'found' => false];
        if (empty($companyUsers)) return $out;
        $u = $companyUsers[0]; // dono primeiro
        $out['found'] = true;
        $name = trim((string)($u['name'] ?? ''));
        $out['name'] = $name !== '' ? $name : 'Cliente';
        $phone = preg_replace('/\D+/', '', (string)($u['phone'] ?? ''));
        $out['phone'] = ($phone !== '' && strlen($phone) >= 10) ? $phone : null;
        $email = trim((string)($u['email'] ?? ''));
        $out['email'] = filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
        return $out;
    }

    /** URL da página de nova demanda (onde o cliente abre chamados). */
    public static function newTicketUrl(string $base): string
    {
        return rtrim($base, '/') . '/tickets/create';
    }

    /**
     * Mensagem de WhatsApp ensinando o cliente a usar o sistema. $stagingUrl é
     * opcional (link de homologação do provisionamento).
     */
    public static function clientWhatsapp(string $name, string $loginUrl, ?string $stagingUrl, ?string $company): string
    {
        $msg = "Olá, {$name}!\n\n";
        if ($company) $msg .= "*{$company}*\n";
        $msg .= "Seu ambiente já está configurado e começamos a criar as demandas do seu escopo. ";
        $msg .= "Para acompanhar, acesse o sistema com os acessos que já te enviamos:\n{$loginUrl}\n\n";
        if ($stagingUrl) $msg .= "Ambiente de homologação do seu projeto:\n{$stagingUrl}\n\n";
        $msg .= "Para abrir uma nova demanda, entre em \"Nova demanda\": você pode gravar um áudio "
              . "ou escrever o que precisa. Lá também vê o cronograma e o andamento das demandas. "
              . "Qualquer dúvida, é só chamar.";
        return $msg;
    }

    /** Assunto do e-mail de entrega. */
    public static function emailSubject(?string $company): string
    {
        return 'Seu ambiente está pronto' . ($company ? " — {$company}" : '');
    }

    /** Corpo (HTML interno) do e-mail de entrega. */
    public static function emailBody(string $name, string $loginUrl, ?string $stagingUrl, ?string $newTicketUrl): string
    {
        $safeName = htmlspecialchars($name);
        $safeLogin = htmlspecialchars($loginUrl);
        $html = "<p>Olá, <strong>{$safeName}</strong>!</p>";
        $html .= "<p>Seu ambiente já está configurado e começamos a criar as demandas do seu escopo técnico.</p>";
        $html .= "<p>Acesse o sistema com os acessos que já enviamos: <a href='{$safeLogin}'>{$safeLogin}</a></p>";
        if ($stagingUrl) {
            $safeStaging = htmlspecialchars($stagingUrl);
            $html .= "<p>Ambiente de homologação do seu projeto: <a href='{$safeStaging}'>{$safeStaging}</a></p>";
        }
        $html .= "<p><strong>Como usar:</strong> para abrir uma nova demanda, acesse ";
        if ($newTicketUrl) {
            $safeTicket = htmlspecialchars($newTicketUrl);
            $html .= "<a href='{$safeTicket}'>Nova demanda</a>";
        } else {
            $html .= "\"Nova demanda\"";
        }
        $html .= ". Você pode gravar um áudio ou escrever o que precisa. Na tela também vê o cronograma e o andamento das demandas.</p>";
        $html .= "<p>Qualquer dúvida, estamos à disposição.</p>";
        return $html;
    }
}
