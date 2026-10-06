<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ClientAccessRules;

/**
 * Testes unitários das regras puras da criação automática do ACESSO DO CLIENTE
 * no onboarding (login + PIN). Cobre: qual etapa dispara, escolha do contato de
 * destino (prioridade/normalização) e o texto das mensagens.
 */
final class ClientAccessRulesTest extends TestCase
{
    public function testStepTriggersAccess(): void
    {
        $this->assertTrue(ClientAccessRules::stepTriggersAccess('client_access'));
        $this->assertFalse(ClientAccessRules::stepTriggersAccess('kickoff'));
        $this->assertFalse(ClientAccessRules::stepTriggersAccess(null));
        $this->assertFalse(ClientAccessRules::stepTriggersAccess(''));
    }

    public function testPickRecipientVazio(): void
    {
        $r = ClientAccessRules::pickRecipient([]);
        $this->assertFalse($r['found']);
        $this->assertSame('Cliente', $r['name']);
        $this->assertNull($r['phone']);
        $this->assertNull($r['email']);
    }

    public function testPickRecipientPriorizaPrimario(): void
    {
        $contacts = [
            ['name' => 'Secundário', 'email' => 'sec@x.com', 'phone' => '4199990000', 'is_primary' => 0],
            ['name' => 'Principal',  'email' => 'main@x.com', 'phone' => '4188887777', 'is_primary' => 1],
        ];
        $r = ClientAccessRules::pickRecipient($contacts);
        $this->assertTrue($r['found']);
        $this->assertSame('Principal', $r['name']);
        $this->assertSame('main@x.com', $r['email']);
        $this->assertSame('4188887777', $r['phone']);
    }

    public function testPickRecipientCaiNoPrimeiroSemPrimario(): void
    {
        $contacts = [
            ['name' => 'Um', 'email' => 'um@x.com', 'phone' => '4199990000'],
            ['name' => 'Dois', 'email' => 'dois@x.com', 'phone' => '4188887777'],
        ];
        $r = ClientAccessRules::pickRecipient($contacts);
        $this->assertSame('Um', $r['name']);
    }

    public function testPickRecipientNormalizaTelefoneEValidaEmail(): void
    {
        $contacts = [
            ['name' => '', 'email' => 'nao-email', 'phone' => '+55 (41) 99988-7766', 'is_primary' => 1],
        ];
        $r = ClientAccessRules::pickRecipient($contacts);
        $this->assertSame('Cliente', $r['name']);           // nome vazio -> fallback
        $this->assertSame('5541999887766', $r['phone']);    // só dígitos
        $this->assertNull($r['email']);                     // e-mail inválido -> null
    }

    public function testPickRecipientDescartaTelefoneCurto(): void
    {
        $contacts = [['name' => 'X', 'email' => 'x@x.com', 'phone' => '12345', 'is_primary' => 1]];
        $r = ClientAccessRules::pickRecipient($contacts);
        $this->assertNull($r['phone']); // < 10 dígitos
    }

    public function testClientWhatsappContemPinEEmpresa(): void
    {
        $msg = ClientAccessRules::clientWhatsapp('João', '4821', 'ON Solutions', 'https://app/x');
        $this->assertStringContainsString('João', $msg);
        $this->assertStringContainsString('4821', $msg);
        $this->assertStringContainsString('ON Solutions', $msg);
        $this->assertStringContainsString('https://app/x', $msg);
    }

    public function testClientWhatsappSemEmpresaNemUrlNaoQuebra(): void
    {
        $msg = ClientAccessRules::clientWhatsapp('Ana', '0001', null, null);
        $this->assertStringContainsString('Ana', $msg);
        $this->assertStringContainsString('0001', $msg);
    }

    public function testClientEmailSubject(): void
    {
        $this->assertSame('Seu acesso foi criado — ON', ClientAccessRules::clientEmailSubject('ON'));
        $this->assertSame('Seu acesso foi criado', ClientAccessRules::clientEmailSubject(null));
    }

    public function testClientEmailBodyEscapaEContemPin(): void
    {
        $body = ClientAccessRules::clientEmailBody('<b>Hack</b>', '9999', 'https://app/x');
        $this->assertStringContainsString('9999', $body);
        $this->assertStringContainsString('https://app/x', $body);
        // Nome deve estar escapado (sem a tag <b> crua).
        $this->assertStringNotContainsString('<b>Hack</b>', $body);
        $this->assertStringContainsString('&lt;b&gt;Hack', $body);
    }

    public function testTeamNotice(): void
    {
        $this->assertStringContainsString('criado', ClientAccessRules::teamNotice('Cliente X', true));
        $this->assertStringContainsString('atualizado', ClientAccessRules::teamNotice('Cliente X', false));
        $this->assertStringContainsString('Cliente X', ClientAccessRules::teamNotice('Cliente X', true));
    }
}
