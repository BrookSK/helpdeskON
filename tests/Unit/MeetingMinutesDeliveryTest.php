<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use MeetingMinutesDelivery;

/**
 * Testes unitários das regras puras de ENVIO da minuta (Fase 2 — tarefa #7).
 * Cobre destinatários (dedup, descarte de vazios, papel) e montagem das
 * mensagens de WhatsApp e e-mail com o link público.
 */
final class MeetingMinutesDeliveryTest extends TestCase
{
    public function testNormalizeEmailEPhone(): void
    {
        $this->assertSame('a@b.com', MeetingMinutesDelivery::normalizeEmail('  A@B.com '));
        $this->assertNull(MeetingMinutesDelivery::normalizeEmail('invalido'));
        $this->assertNull(MeetingMinutesDelivery::normalizeEmail(''));
        $this->assertSame('5541999887766', MeetingMinutesDelivery::normalizePhone('+55 (41) 99988-7766'));
        $this->assertNull(MeetingMinutesDelivery::normalizePhone('   '));
        $this->assertNull(MeetingMinutesDelivery::normalizePhone(null));
    }

    public function testBuildRecipientsClientePrimeiroEDescartaSemCanal(): void
    {
        $participants = [
            ['name' => 'Vacari', 'email' => 'vacari@on.com', 'phone' => '4199990001'],
            ['name' => 'SemContato', 'email' => '', 'phone' => ''], // descartado
        ];
        $client = ['name' => 'Cliente X', 'email' => 'cliente@x.com', 'phone' => '4188887777'];

        $r = MeetingMinutesDelivery::buildRecipients($participants, $client);
        $this->assertCount(2, $r);
        // Cliente vem primeiro e com role 'client'.
        $this->assertSame('client', $r[0]['role']);
        $this->assertSame('cliente@x.com', $r[0]['email']);
        $this->assertSame('team', $r[1]['role']);
        $this->assertSame('Vacari', $r[1]['name']);
    }

    public function testBuildRecipientsDeduplicaPorEmailEPorTelefone(): void
    {
        $participants = [
            ['name' => 'A', 'email' => 'dup@on.com', 'phone' => '41999'],
            ['name' => 'A2', 'email' => 'DUP@on.com', 'phone' => '42000'],   // mesmo e-mail (case) -> dedup
            ['name' => 'B', 'email' => 'b@on.com', 'phone' => '41999'],      // mesmo telefone -> dedup
            ['name' => 'C', 'email' => 'c@on.com', 'phone' => '43000'],      // único
        ];
        $r = MeetingMinutesDelivery::buildRecipients($participants, []);
        $emails = array_column($r, 'email');
        $this->assertContains('dup@on.com', $emails);
        $this->assertContains('c@on.com', $emails);
        // A2 (e-mail duplicado) e B (telefone duplicado) não entram.
        $this->assertNotContains('b@on.com', $emails);
        $this->assertCount(2, $r);
    }

    public function testBuildRecipientsSemNadaRetornaVazio(): void
    {
        $this->assertSame([], MeetingMinutesDelivery::buildRecipients([], []));
    }

    public function testWhatsappMessageContemLinkSaudacaoEAssunto(): void
    {
        $msg = MeetingMinutesDelivery::whatsappMessage('Lucas', 'https://x/minutesView/abc', 'Reunião com Cliente', 'ON Solutions');
        $this->assertStringContainsString('Lucas', $msg);
        $this->assertStringContainsString('https://x/minutesView/abc', $msg);
        $this->assertStringContainsString('Reunião com Cliente', $msg);
        $this->assertStringContainsString('ON Solutions', $msg);
    }

    public function testWhatsappMessageSemNomeNemTituloNaoQuebra(): void
    {
        $msg = MeetingMinutesDelivery::whatsappMessage('', 'https://x/m', null, null);
        $this->assertStringContainsString('https://x/m', $msg);
        $this->assertStringContainsStringIgnoringCase('minuta', $msg);
    }

    public function testEmailSubjectEBody(): void
    {
        $this->assertSame('Minuta da Reunião X', MeetingMinutesDelivery::emailSubject('Reunião X'));
        $this->assertSame('Minuta da reunião', MeetingMinutesDelivery::emailSubject(null));

        $body = MeetingMinutesDelivery::emailBody('Cliente', 'https://x/minutesView/tok', 'Reunião X');
        $this->assertStringContainsString('https://x/minutesView/tok', $body);
        $this->assertStringContainsString('Cliente', $body);
        $this->assertStringContainsString('<a href=', $body);
    }
}
