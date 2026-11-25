<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use EmailBounceRules;

/**
 * E-mail devolvido (bounce): reconhecer a notificação, achar o endereço que
 * falhou e decidir se é definitivo (tira o lead) ou temporário (lead continua).
 */
final class EmailBounceRulesTest extends TestCase
{
    /** Notificação do Gmail igual à que a Evelyn recebeu (endereço não existe). */
    private function gmailNotFound(): array
    {
        $m = [
            'from' => 'Mail Delivery Subsystem <mailer-daemon@googlemail.com>', 'from_email' => 'mailer-daemon@googlemail.com',
            'subject' => 'Delivery Status Notification (Failure)', 'content_type' => 'multipart/report; report-type=delivery-status; boundary="x"',
            'x_failed_recipients' => 'cestari@clubmed.digital', 'in_reply_to' => '', 'references' => '',
        ];
        $text = "Address not found\nYour message wasn't delivered to cestari@clubmed.digital because the address couldn't be found, or is unable to receive mail.\n"
              . "The response was:\nThe email account that you tried to reach does not exist. Please try double-checking the recipient's email address. 550 5.1.1 https://support.google.com/mail/?p=NoSuchUser\n\n"
              . "Reporting-MTA: dns; googlemail.com\nFinal-Recipient: rfc822; cestari@clubmed.digital\nAction: failed\nStatus: 5.1.1\n"
              . "Remote-MTA: dns; mx.clubmed.digital. (203.0.113.10, the server for the domain clubmed.digital.)\n\n"
              . "From: Evelyn Faoth <evelyn.faoth@lrvweb.com.br>\nTo: cestari@clubmed.digital\nSubject: Proposta ON\n"
              . "Message-ID: <seq-0123456789abcdef0123456789abcdef@lrvweb.com.br>\n";
        return [$m, $text];
    }

    public function testGmailEnderecoNaoExisteEDefinitivo(): void
    {
        [$m, $text] = $this->gmailNotFound();
        $this->assertTrue(EmailBounceRules::isBounce($m));
        $p = EmailBounceRules::parse($m, $text);
        $this->assertSame(['cestari@clubmed.digital'], $p['recipients']);
        $this->assertSame('0123456789abcdef0123456789abcdef', $p['token'], 'acha o nosso envio pelo Message-ID citado');
        $this->assertSame('5.1.1', $p['status']);
        $this->assertTrue($p['hard']);
        $this->assertSame('Endereço de e-mail não existe (5.1.1)', $p['reason']);
        $this->assertContains('evelyn.faoth@lrvweb.com.br', $p['mentioned']);
        $this->assertNotContains('mailer-daemon@googlemail.com', $p['mentioned'], 'remetente do sistema nunca é candidato');
    }

    public function testOutlookEPostfixSemCabecalhoDeFalha(): void
    {
        $outlook = "Your message to joao@empresa.example couldn't be delivered.\njoao wasn't found at empresa.example.\n"
                 . "Remote Server returned '550 5.1.10 RESOLVER.ADR.RecipientNotFound; Recipient not found by SMTP address lookup'";
        $p = EmailBounceRules::parse(['subject' => 'Undeliverable: Proposta'], $outlook);
        $this->assertTrue($p['hard']);
        $this->assertSame('5.1.10', $p['status']);
        $this->assertSame([], $p['recipients']);
        $this->assertSame(['joao@empresa.example'], $p['mentioned'], 'sem campo de falha, o serviço usa os e-mails citados');

        $postfix = "This is the mail system at host mx.example.\n\n<ana@dominio.example>: host mx.dominio.example[198.51.100.7] said: 550 5.1.1 <ana@dominio.example>: Recipient address rejected: User unknown";
        $p = EmailBounceRules::parse(['subject' => 'Undelivered Mail Returned to Sender'], $postfix);
        $this->assertSame(['ana@dominio.example'], $p['recipients']);
        $this->assertTrue($p['hard']);
    }

    public function testDominioInexistenteSemCodigo(): void
    {
        $p = EmailBounceRules::parse(['subject' => 'Delivery Status Notification (Failure)'],
            "DNS Error: Domain name not found\nThe domain empresa-que-nao-existe.example couldn't be found.");
        $this->assertTrue($p['hard']);
        $this->assertNull($p['status']);
    }

    public function testTemporariosNaoTiramOLead(): void
    {
        $cases = [
            ['Delivery Status Notification (Delay)', "Message delivery to x@y.example delayed\nWill retry for 46 more hours.\nStatus: 4.4.1", 'Falha temporária'],
            ['Mail delivery failed', "550 5.2.2 The email account that you tried to reach is over quota.\nStatus: 5.2.2", 'Caixa de entrada do lead cheia'],
            ['Delivery Status Notification (Failure)', "Message blocked. 550 5.7.1 Our system has detected that this message is likely unsolicited mail.\nStatus: 5.7.1", 'política/spam'],
            ['Undeliverable', 'Delivery has failed to these recipients. Please try again later.', 'Falha temporária'],
            ['Delivery Status Notification (Delay)', "Address not found yet, still trying to deliver", 'Entrega atrasada'],
            ['Returned mail', 'Something went wrong.', 'sem motivo claro'],
        ];
        foreach ($cases as [$subject, $text, $expected]) {
            $p = EmailBounceRules::parse(['subject' => $subject], $text);
            $this->assertFalse($p['hard'], $subject . ' / ' . $text);
            $this->assertStringContainsString($expected, $p['reason']);
        }
    }

    public function testIpNaoViraCodigoDeStatus(): void
    {
        $p = EmailBounceRules::parse(['subject' => 'Undeliverable'], "Remote server 10.4.5.123 said: user unknown");
        $this->assertNull($p['status']);
        $this->assertTrue($p['hard']);
    }

    public function testReconheceSoNotificacaoDeFalha(): void
    {
        $this->assertTrue(EmailBounceRules::isBounce(['from' => 'postmaster@empresa.example', 'subject' => 'Undeliverable: Oi']));
        $this->assertTrue(EmailBounceRules::isBounce(['from' => 'MAILER-DAEMON@mx.example', 'subject' => 'x']));
        $this->assertTrue(EmailBounceRules::isBounce(['from' => 'sistema@x.example', 'subject' => 'Mensagem não entregue: Proposta']));
        $this->assertTrue(EmailBounceRules::isBounce(['from' => 'x@y.example', 'subject' => 'x', 'content_type' => 'multipart/report; report-type=delivery-status']));
        // Resposta comum do lead e auto-resposta de férias não são devolução
        $this->assertFalse(EmailBounceRules::isBounce(['from' => 'Carla <carla@clinica.example>', 'subject' => 'Re: Proposta ON']));
        $this->assertFalse(EmailBounceRules::isBounce(['from' => 'carla@clinica.example', 'subject' => 'Resposta automática: estou de férias']));
        $this->assertFalse(EmailBounceRules::isBounce([]));
    }

    public function testLimpezaDeEnderecoELiberarAoCorrigir(): void
    {
        $this->assertSame('ana@x.example', EmailBounceRules::cleanEmail(' <Ana@X.example>. '));
        $this->assertSame('ana@x.example', EmailBounceRules::cleanEmail('rfc822;ana@x.example'));
        $this->assertNull(EmailBounceRules::cleanEmail('mailer-daemon@googlemail.com'));
        $this->assertNull(EmailBounceRules::cleanEmail('postmaster@x.example'));
        $this->assertNull(EmailBounceRules::cleanEmail('nao-e-email'));

        $this->assertTrue(EmailBounceRules::shouldClearBounce('cestari@clubmed.digital', 'cestari@clubmed.com'));
        $this->assertFalse(EmailBounceRules::shouldClearBounce('Cestari@ClubMed.digital', 'cestari@clubmed.digital '), 'mesmo endereço continua bloqueado');
        $this->assertFalse(EmailBounceRules::shouldClearBounce('a@x.example', null), 'apagar o e-mail não libera');
    }
}
