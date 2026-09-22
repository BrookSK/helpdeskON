<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use TicketsController;

/**
 * Testes unitários da montagem da mensagem de convite de acesso externo
 * (envio do link por WhatsApp). buildInviteMessage é "puro" (só dados ->
 * string), então não toca em banco/rede.
 */
final class ExternalInviteMessageTest extends TestCase
{
    private function controller(): TicketsController
    {
        // O construtor instancia models que só acessam o banco quando usados.
        return new TicketsController();
    }

    public function testMensagemContemLink(): void
    {
        $msg = $this->controller()->buildInviteMessage('Maria', 'Julia', 'https://exemplo.test/solicitacaoexterna');
        $this->assertStringContainsString('https://exemplo.test/solicitacaoexterna', $msg);
    }

    public function testMensagemSaudaClientePeloNome(): void
    {
        $msg = $this->controller()->buildInviteMessage('Maria', 'Julia', 'https://x.test/link');
        $this->assertStringContainsString('Olá, Maria!', $msg);
    }

    public function testSemNomeUsaSaudacaoGenerica(): void
    {
        $msg = $this->controller()->buildInviteMessage('', 'Julia', 'https://x.test/link');
        $this->assertStringContainsString('Olá!', $msg);
        $this->assertStringNotContainsString('Olá, !', $msg);
    }

    public function testMencionaOAtendenteQuandoInformado(): void
    {
        $msg = $this->controller()->buildInviteMessage('Maria', 'Julia', 'https://x.test/link');
        $this->assertStringContainsString('Julia', $msg);
    }

    public function testNaoIncluiPinNoLink(): void
    {
        // Decisão de escopo: o link é puro, sem PIN embutido na URL.
        $msg = $this->controller()->buildInviteMessage('Maria', 'Julia', 'https://exemplo.test/solicitacaoexterna');
        $this->assertStringNotContainsString('pin=', $msg);
    }

    public function testSemPinNaoMencionaOValorDoPin(): void
    {
        // Por padrão (sem o 4º argumento) o PIN NÃO aparece na mensagem.
        $msg = $this->controller()->buildInviteMessage('Maria', 'Julia', 'https://x.test/link');
        $this->assertStringNotContainsString('PIN:', $msg);
    }

    public function testComPinIncluiOValorNaMensagem(): void
    {
        // Quando o atendente opta por enviar o PIN junto, ele aparece no texto.
        $msg = $this->controller()->buildInviteMessage('Maria', 'Julia', 'https://x.test/link', '4821');
        $this->assertStringContainsString('PIN: 4821', $msg);
    }

    public function testPinVazioMantemComportamentoPadrao(): void
    {
        // PIN vazio/espacos é tratado como "não enviar PIN".
        $msg = $this->controller()->buildInviteMessage('Maria', 'Julia', 'https://x.test/link', '   ');
        $this->assertStringNotContainsString('PIN:', $msg);
    }
}
