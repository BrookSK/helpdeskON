<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use EmailReplyRules;

/**
 * Testes unitários da detecção de resposta por e-mail (EmailReplyRules).
 *
 * Cobre o problema real: a caixa recebe muitos e-mails não relacionados. Só deve
 * contar como resposta quando a mensagem CASA com um envio nosso (token embutido
 * no Message-ID/Reply-To e devolvido em In-Reply-To/References/To). Auto-respostas,
 * envios em massa e bounces têm que ser descartados como ruído.
 */
final class EmailReplyRulesTest extends TestCase
{
    private string $token = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';

    // ---- geração do identificador de envio ----

    public function testBuildMessageIdEmbuteToken(): void
    {
        $mid = EmailReplyRules::buildMessageId($this->token, 'empresa.com');
        $this->assertSame('<seq-' . $this->token . '@empresa.com>', $mid);
        // E o token é recuperável do próprio Message-ID.
        $this->assertSame($this->token, EmailReplyRules::extractToken($mid));
    }

    public function testBuildMessageIdSemDominioUsaLocalhost(): void
    {
        $mid = EmailReplyRules::buildMessageId($this->token, '');
        $this->assertSame('<seq-' . $this->token . '@localhost>', $mid);
    }

    public function testBuildReplyToAdicionaTagComToken(): void
    {
        $rt = EmailReplyRules::buildReplyTo('contato@empresa.com', $this->token);
        $this->assertSame('contato+seq-' . $this->token . '@empresa.com', $rt);
        $this->assertSame($this->token, EmailReplyRules::extractToken($rt));
    }

    public function testBuildReplyToRecusaEmailInvalidoOuComMais(): void
    {
        $this->assertNull(EmailReplyRules::buildReplyTo('invalido', $this->token));
        // Local-part que já tem "+" não empilha outra tag.
        $this->assertNull(EmailReplyRules::buildReplyTo('contato+x@empresa.com', $this->token));
    }

    // ---- extração de token de cabeçalhos ----

    public function testExtractTokenDeVariosHeaders(): void
    {
        $this->assertSame($this->token, EmailReplyRules::extractToken('<seq-' . $this->token . '@x.com>'));
        $this->assertSame($this->token, EmailReplyRules::extractToken('foo <seq-' . $this->token . '@x.com> bar'));
        $this->assertNull(EmailReplyRules::extractToken('<sem-token@x.com>'));
        $this->assertNull(EmailReplyRules::extractToken(null));
        $this->assertNull(EmailReplyRules::extractToken(''));
    }

    public function testFindTokenPrefereInReplyToDepoisReferencesDepoisTo(): void
    {
        $headers = [
            'in_reply_to' => '<seq-' . $this->token . '@x.com>',
            'references' => '<outro@x.com>',
            'to' => 'contato+seq-deadbeef@x.com',
        ];
        $this->assertSame($this->token, EmailReplyRules::findTokenInReply($headers));

        // Sem In-Reply-To, cai no References.
        $headers2 = ['references' => '<a@x.com> <seq-' . $this->token . '@x.com>'];
        $this->assertSame($this->token, EmailReplyRules::findTokenInReply($headers2));

        // Sem nada de threading, cai no +tag do To.
        $headers3 = ['to' => 'contato+seq-' . $this->token . '@empresa.com'];
        $this->assertSame($this->token, EmailReplyRules::findTokenInReply($headers3));
    }

    // ---- filtro de ruído ----

    public function testAutoSubmittedEhRuido(): void
    {
        $this->assertTrue(EmailReplyRules::isNoise(['auto_submitted' => 'auto-replied']));
        $this->assertTrue(EmailReplyRules::isNoise(['auto_submitted' => 'auto-generated']));
        // "no" NÃO é ruído (mensagem humana comum declara Auto-Submitted: no).
        $this->assertFalse(EmailReplyRules::isNoise(['auto_submitted' => 'no']));
    }

    public function testPrecedenceBulkEhRuido(): void
    {
        $this->assertTrue(EmailReplyRules::isNoise(['precedence' => 'bulk']));
        $this->assertTrue(EmailReplyRules::isNoise(['precedence' => 'list']));
    }

    public function testBounceEDeliveryStatusEhRuido(): void
    {
        $this->assertTrue(EmailReplyRules::isNoise(['from' => 'MAILER-DAEMON@x.com']));
        $this->assertTrue(EmailReplyRules::isNoise(['from' => 'postmaster@x.com']));
        $this->assertTrue(EmailReplyRules::isNoise(['content_type' => 'multipart/report; report-type=delivery-status']));
        $this->assertTrue(EmailReplyRules::isNoise(['subject' => 'Delivery Status Notification (Failure)']));
    }

    public function testOutOfOfficeEhRuido(): void
    {
        $this->assertTrue(EmailReplyRules::isNoise(['subject' => 'Automatic reply: fora do escritório']));
        $this->assertTrue(EmailReplyRules::isNoise(['subject' => 'Ausência temporária']));
        $this->assertTrue(EmailReplyRules::isNoise(['x_autoreply' => 'yes']));
    }

    public function testRespostaHumanaNaoEhRuido(): void
    {
        $this->assertFalse(EmailReplyRules::isNoise([
            'from' => 'joao@empresa.com',
            'subject' => 'Re: Apresentação ON Solutions',
            'auto_submitted' => 'no',
        ]));
    }

    // ---- classificação completa ----

    public function testClassifyMatchQuandoTokenPresente(): void
    {
        $v = EmailReplyRules::classify([
            'from' => 'joao@empresa.com',
            'subject' => 'Re: proposta',
            'in_reply_to' => '<seq-' . $this->token . '@x.com>',
        ]);
        $this->assertSame(EmailReplyRules::MATCH, $v['result']);
        $this->assertSame($this->token, $v['token']);
    }

    public function testClassifyNoiseTemPrioridadeSobreMatch(): void
    {
        // Auto-resposta que cita nosso Message-ID: ainda assim é ruído (não dispara triagem).
        $v = EmailReplyRules::classify([
            'auto_submitted' => 'auto-replied',
            'in_reply_to' => '<seq-' . $this->token . '@x.com>',
            'subject' => 'Automatic reply',
        ]);
        $this->assertSame(EmailReplyRules::NOISE, $v['result']);
        $this->assertNull($v['token']);
    }

    public function testClassifyUnmatchedQuandoSemAncora(): void
    {
        $v = EmailReplyRules::classify([
            'from' => 'newsletter@outra.com',
            'subject' => 'Novidades da semana',
        ]);
        $this->assertSame(EmailReplyRules::UNMATCHED, $v['result']);
        $this->assertNull($v['token']);
    }
}
