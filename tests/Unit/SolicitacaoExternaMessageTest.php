<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use SolicitacaoexternaController;

/**
 * Testes unitários da montagem da mensagem de WhatsApp da demanda externa
 * (demanda #239). buildWhatsappMessage é "puro" (só dados -> string), então
 * não toca em banco/rede.
 */
final class SolicitacaoExternaMessageTest extends TestCase
{
    private function controller(): SolicitacaoexternaController
    {
        // O construtor instancia User(), que não acessa o banco até ser usado.
        return new SolicitacaoexternaController();
    }

    public function testMensagemContemNumeroTituloEPrioridade(): void
    {
        $msg = $this->controller()->buildWhatsappMessage([
            'ticket_number' => 42,
            'title' => 'Ajustar layout do site',
            'priority' => 'high',
            'requester_name' => 'Maria',
        ]);

        $this->assertStringContainsString('#42', $msg);
        $this->assertStringContainsString('Ajustar layout do site', $msg);
        $this->assertStringContainsString('Alta', $msg);       // label da prioridade high
        $this->assertStringContainsString('Maria', $msg);
    }

    public function testMensagemIncluiEmpresaQuandoInformada(): void
    {
        $msg = $this->controller()->buildWhatsappMessage([
            'ticket_number' => 1,
            'title' => 'Demanda',
            'priority' => 'medium',
            'requester_name' => 'João',
            'requester_company' => 'ACME',
        ]);

        $this->assertStringContainsString('João (ACME)', $msg);
    }

    public function testMensagemIncluiLinkDoCardQuandoPresente(): void
    {
        $msg = $this->controller()->buildWhatsappMessage([
            'ticket_number' => 7,
            'title' => 'Demanda',
            'priority' => 'low',
            'requester_name' => 'Ana',
            'card_url' => 'https://exemplo.test/tickets/show/7',
        ]);

        $this->assertStringContainsString('https://exemplo.test/tickets/show/7', $msg);
    }

    public function testPrioridadeInvalidaUsaPadraoMedia(): void
    {
        $msg = $this->controller()->buildWhatsappMessage([
            'ticket_number' => 9,
            'title' => 'Demanda',
            'priority' => 'inexistente',
            'requester_name' => 'Rui',
        ]);

        $this->assertStringContainsString('Média', $msg);
    }

    public function testSemNomeUsaNaoInformado(): void
    {
        $msg = $this->controller()->buildWhatsappMessage([
            'ticket_number' => 3,
            'title' => 'Demanda',
            'priority' => 'medium',
            'requester_name' => '',
        ]);

        $this->assertStringContainsString('Não informado', $msg);
    }
}
