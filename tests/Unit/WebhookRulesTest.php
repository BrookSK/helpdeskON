<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use WebhookRules;

/**
 * Testes unitários das regras puras dos webhooks de ENTRADA do WhatsApp.
 *
 * Cobre a extração de campos do payload (incl. dot-notation), a extração/
 * normalização de telefones (string única, CSV, array), a renderização do
 * template, e a interpretação completa (interpret) + isSendable.
 */
final class WebhookRulesTest extends TestCase
{
    // ===== extractField =====

    public function testExtractFieldTopLevel(): void
    {
        $payload = ['phone' => '11999998888', 'name' => 'Fulano'];
        $this->assertSame('11999998888', WebhookRules::extractField($payload, 'phone'));
        $this->assertSame('Fulano', WebhookRules::extractField($payload, 'name'));
    }

    public function testExtractFieldDotNotation(): void
    {
        $payload = ['data' => ['contato' => ['telefone' => '1733334444']]];
        $this->assertSame('1733334444', WebhookRules::extractField($payload, 'data.contato.telefone'));
    }

    public function testExtractFieldAusenteOuVazioRetornaNull(): void
    {
        $payload = ['a' => ['b' => 1]];
        $this->assertNull(WebhookRules::extractField($payload, 'a.x'));
        $this->assertNull(WebhookRules::extractField($payload, 'inexistente'));
        $this->assertNull(WebhookRules::extractField($payload, ''));
        $this->assertNull(WebhookRules::extractField($payload, null));
    }

    // ===== normalizePhone =====

    public function testNormalizePhoneAdicionaDDI(): void
    {
        $this->assertSame('5511999998888', WebhookRules::normalizePhone('(11) 99999-8888')); // 11 díg -> +55
        $this->assertSame('551733334444', WebhookRules::normalizePhone('1733334444'));        // 10 díg -> +55
    }

    public function testNormalizePhoneMantemComDDI(): void
    {
        $this->assertSame('5511999998888', WebhookRules::normalizePhone('5511999998888'));
    }

    public function testNormalizePhoneInvalidoRetornaNull(): void
    {
        $this->assertNull(WebhookRules::normalizePhone('123'));      // poucos dígitos
        $this->assertNull(WebhookRules::normalizePhone(''));
        $this->assertNull(WebhookRules::normalizePhone('abc'));
        $this->assertNull(WebhookRules::normalizePhone(['x']));      // não-escalar
    }

    // ===== extractPhones =====

    public function testExtractPhonesStringUnica(): void
    {
        $this->assertSame(['5511999998888'], WebhookRules::extractPhones('11999998888'));
    }

    public function testExtractPhonesCsvEDelimitadores(): void
    {
        $out = WebhookRules::extractPhones('11999998888, 1733334444; 5521988887777');
        $this->assertSame(['5511999998888', '551733334444', '5521988887777'], $out);
    }

    public function testExtractPhonesArray(): void
    {
        $out = WebhookRules::extractPhones(['11999998888', '(17) 3333-4444']);
        $this->assertSame(['5511999998888', '551733334444'], $out);
    }

    public function testExtractPhonesRemoveDuplicatasEInvalidos(): void
    {
        $out = WebhookRules::extractPhones(['11999998888', '11999998888', 'xx', '123']);
        $this->assertSame(['5511999998888'], $out);
    }

    public function testExtractPhonesVazio(): void
    {
        $this->assertSame([], WebhookRules::extractPhones(null));
        $this->assertSame([], WebhookRules::extractPhones([]));
        $this->assertSame([], WebhookRules::extractPhones(''));
    }

    // ===== renderMessage =====

    public function testRenderMessageSubstituiVariaveis(): void
    {
        $tpl = 'Olá {{nome}}! Seu código é {{mensagem}}. Contato: {{email}}';
        $out = WebhookRules::renderMessage($tpl, [
            'nome' => 'Ana', 'mensagem' => '123', 'email' => 'a@b.com', 'telefone' => '5511999998888',
        ]);
        $this->assertSame('Olá Ana! Seu código é 123. Contato: a@b.com', $out);
    }

    public function testRenderMessageAceitaEspacosEMaiusculas(): void
    {
        $out = WebhookRules::renderMessage('Oi {{ NOME }}', ['nome' => 'Zé']);
        $this->assertSame('Oi Zé', $out);
    }

    public function testRenderMessageVariavelAusenteViraVazio(): void
    {
        $out = WebhookRules::renderMessage('Olá {{nome}}{{mensagem}}', ['nome' => 'Ana']);
        $this->assertSame('Olá Ana', $out);
    }

    public function testRenderMessageSemTemplateUsaMensagem(): void
    {
        $out = WebhookRules::renderMessage('', ['mensagem' => 'texto cru']);
        $this->assertSame('texto cru', $out);
        $out2 = WebhookRules::renderMessage(null, ['mensagem' => 'outro']);
        $this->assertSame('outro', $out2);
    }

    // ===== interpret + isSendable =====

    public function testInterpretComTemplate(): void
    {
        $payload = [
            'telefone' => '11999998888',
            'cliente'  => ['nome' => 'Maria', 'email' => 'maria@x.com'],
            'texto'    => 'pedido pronto',
        ];
        $mapping = [
            'phone_field'      => 'telefone',
            'name_field'       => 'cliente.nome',
            'email_field'      => 'cliente.email',
            'message_field'    => 'texto',
            'message_template' => 'Oi {{nome}}, {{mensagem}}',
        ];
        $r = WebhookRules::interpret($payload, $mapping);
        $this->assertSame(['5511999998888'], $r['phones']);
        $this->assertSame('Maria', $r['name']);
        $this->assertSame('maria@x.com', $r['email']);
        $this->assertSame('Oi Maria, pedido pronto', $r['message']);
        $this->assertTrue(WebhookRules::isSendable($r));
    }

    public function testInterpretSemTemplateUsaMessageField(): void
    {
        $payload = ['phone' => '11999998888', 'message' => 'mensagem direta'];
        $mapping = ['phone_field' => 'phone', 'message_field' => 'message'];
        $r = WebhookRules::interpret($payload, $mapping);
        $this->assertSame(['5511999998888'], $r['phones']);
        $this->assertSame('mensagem direta', $r['message']);
        $this->assertTrue(WebhookRules::isSendable($r));
    }

    public function testInterpretMultiplosTelefones(): void
    {
        $payload = ['phones' => ['11999998888', '1733334444'], 'message' => 'oi'];
        $mapping = ['phone_field' => 'phones', 'message_field' => 'message'];
        $r = WebhookRules::interpret($payload, $mapping);
        $this->assertSame(['5511999998888', '551733334444'], $r['phones']);
    }

    public function testIsSendableFalsoSemTelefoneOuMensagem(): void
    {
        // Sem telefone
        $this->assertFalse(WebhookRules::isSendable(['phones' => [], 'message' => 'oi']));
        // Sem mensagem
        $this->assertFalse(WebhookRules::isSendable(['phones' => ['5511999998888'], 'message' => '']));
        // Mensagem só espaços
        $this->assertFalse(WebhookRules::isSendable(['phones' => ['5511999998888'], 'message' => '   ']));
    }
}
