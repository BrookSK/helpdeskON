<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use DeliveryNotice;

/**
 * Testes das regras puras da notificação de ENTREGA do provisionamento ao
 * cliente (ensinar a usar o sistema).
 */
final class DeliveryNoticeTest extends TestCase
{
    public function testPickRecipientVazio(): void
    {
        $r = DeliveryNotice::pickRecipient([]);
        $this->assertFalse($r['found']);
        $this->assertSame('Cliente', $r['name']);
    }

    public function testPickRecipientDonoPrimeiro(): void
    {
        $users = [
            ['name' => 'Dono', 'phone' => '+55 (41) 99988-7766', 'email' => 'dono@x.com'],
            ['name' => 'Outro', 'phone' => '4100000000', 'email' => 'o@x.com'],
        ];
        $r = DeliveryNotice::pickRecipient($users);
        $this->assertTrue($r['found']);
        $this->assertSame('Dono', $r['name']);
        $this->assertSame('5541999887766', $r['phone']);
        $this->assertSame('dono@x.com', $r['email']);
    }

    public function testPickRecipientTelefoneCurtoEEmailInvalido(): void
    {
        $r = DeliveryNotice::pickRecipient([['name' => '', 'phone' => '123', 'email' => 'nao']]);
        $this->assertSame('Cliente', $r['name']);
        $this->assertNull($r['phone']);
        $this->assertNull($r['email']);
    }

    public function testNewTicketUrl(): void
    {
        $this->assertSame('https://app/tickets/create', DeliveryNotice::newTicketUrl('https://app/'));
        $this->assertSame('https://app/tickets/create', DeliveryNotice::newTicketUrl('https://app'));
    }

    public function testClientWhatsappContemLinks(): void
    {
        $msg = DeliveryNotice::clientWhatsapp('João', 'https://app', 'https://stg', 'ON');
        $this->assertStringContainsString('João', $msg);
        $this->assertStringContainsString('https://app', $msg);
        $this->assertStringContainsString('https://stg', $msg);
        $this->assertStringContainsString('ON', $msg);
        $this->assertStringContainsStringIgnoringCase('nova demanda', $msg);
    }

    public function testClientWhatsappSemStagingNaoQuebra(): void
    {
        $msg = DeliveryNotice::clientWhatsapp('Ana', 'https://app', null, null);
        $this->assertStringContainsString('Ana', $msg);
        $this->assertStringContainsString('https://app', $msg);
    }

    public function testEmailBodyEscapaEContemLinks(): void
    {
        $body = DeliveryNotice::emailBody('<b>x</b>', 'https://app', 'https://stg', 'https://app/tickets/create');
        $this->assertStringContainsString('https://app', $body);
        $this->assertStringContainsString('https://stg', $body);
        $this->assertStringContainsString('https://app/tickets/create', $body);
        $this->assertStringNotContainsString('<b>x</b>', $body);
    }
}
