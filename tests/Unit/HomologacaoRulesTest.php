<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use HomologacaoRules;

/**
 * Testes unitários da régua de homologação de 48h (HomologacaoRules).
 * Cobrem a decisão da próxima ação em função do tempo decorrido e do que já foi
 * enviado, incluindo limites (0h, 24h, 42h, 48h), atrasos do cron e idempotência.
 */
final class HomologacaoRulesTest extends TestCase
{
    private function nothingSent(): array
    {
        return ['contact1' => false, 'contact2' => false, 'contact3' => false, 'released' => false];
    }

    public function testPrimeiroContatoAoEntrarEmHomologacao(): void
    {
        // 0h, nada enviado -> envia o 1º contato.
        $this->assertSame(HomologacaoRules::ACTION_CONTACT1, HomologacaoRules::nextAction(0.0, $this->nothingSent()));
    }

    public function testNaoReenviaContato1SeJaEnviado(): void
    {
        $sent = $this->nothingSent();
        $sent['contact1'] = true;
        // 1h depois, já enviado o 1 e ainda não chegou a hora do 2 -> nada.
        $this->assertSame(HomologacaoRules::ACTION_NONE, HomologacaoRules::nextAction(1.0, $sent));
    }

    public function testSegundoContatoApos24h(): void
    {
        $sent = $this->nothingSent();
        $sent['contact1'] = true;
        $this->assertSame(HomologacaoRules::ACTION_CONTACT2, HomologacaoRules::nextAction(24.0, $sent));
        // Exatamente no limite de 24h já dispara.
        $this->assertSame(HomologacaoRules::ACTION_CONTACT2, HomologacaoRules::nextAction(24.5, $sent));
    }

    public function testTerceiroContatoApos42h(): void
    {
        $sent = $this->nothingSent();
        $sent['contact1'] = true;
        $sent['contact2'] = true;
        $this->assertSame(HomologacaoRules::ACTION_CONTACT3, HomologacaoRules::nextAction(42.0, $sent));
        $this->assertSame(HomologacaoRules::ACTION_CONTACT3, HomologacaoRules::nextAction(47.9, $sent));
    }

    public function testLiberaProducaoAos48h(): void
    {
        $sent = $this->nothingSent();
        $sent['contact1'] = true;
        $sent['contact2'] = true;
        $sent['contact3'] = true;
        $this->assertSame(HomologacaoRules::ACTION_RELEASE, HomologacaoRules::nextAction(48.0, $sent));
        $this->assertSame(HomologacaoRules::ACTION_RELEASE, HomologacaoRules::nextAction(60.0, $sent));
    }

    public function testNaoLiberaDuasVezes(): void
    {
        $sent = ['contact1' => true, 'contact2' => true, 'contact3' => true, 'released' => true];
        $this->assertSame(HomologacaoRules::ACTION_NONE, HomologacaoRules::nextAction(50.0, $sent));
    }

    public function testCronAtrasadoEnviaOContatoDevidoMaisRecente(): void
    {
        // Cron ficou parado e já passou de 42h sem ter enviado nenhum contato:
        // envia o 3º (mais recente devido), sem "correr atrás" dos anteriores.
        $this->assertSame(HomologacaoRules::ACTION_CONTACT3, HomologacaoRules::nextAction(43.0, $this->nothingSent()));
    }

    public function testCronAtrasadoDentroDaJanelaEnviaContato2SeAindaNaoFoi(): void
    {
        // Passou de 24h, 1 já foi, 2 não -> envia 2 mesmo que o cron tenha atrasado.
        $sent = $this->nothingSent();
        $sent['contact1'] = true;
        $this->assertSame(HomologacaoRules::ACTION_CONTACT2, HomologacaoRules::nextAction(30.0, $sent));
    }

    public function testHorasDecorridasNuncaNegativa(): void
    {
        // "agora" antes do início -> 0.
        $this->assertSame(0.0, HomologacaoRules::hoursElapsed('2026-01-10 10:00:00', '2026-01-10 09:00:00'));
        $this->assertEqualsWithDelta(2.0, HomologacaoRules::hoursElapsed('2026-01-10 10:00:00', '2026-01-10 12:00:00'), 0.001);
    }

    public function testWindowExpired(): void
    {
        $this->assertFalse(HomologacaoRules::windowExpired('2026-01-10 10:00:00', '2026-01-11 09:00:00')); // 23h
        $this->assertTrue(HomologacaoRules::windowExpired('2026-01-10 10:00:00', '2026-01-12 11:00:00'));  // 49h
    }

    public function testMensagemDeContatoContemNumeroETitulo(): void
    {
        $data = ['ticket_number' => 42, 'title' => 'Ajuste no login', 'previsao' => '15/01/2026'];
        $m1 = HomologacaoRules::contactMessage(HomologacaoRules::ACTION_CONTACT1, $data);
        $this->assertStringContainsString('#42', $m1);
        $this->assertStringContainsString('Ajuste no login', $m1);

        $m2 = HomologacaoRules::contactMessage(HomologacaoRules::ACTION_CONTACT2, $data);
        $this->assertStringContainsString('15/01/2026', $m2);

        $m3 = HomologacaoRules::contactMessage(HomologacaoRules::ACTION_CONTACT3, $data);
        $this->assertStringContainsString('6 horas', $m3);

        // Ação sem mensagem associada -> string vazia.
        $this->assertSame('', HomologacaoRules::contactMessage(HomologacaoRules::ACTION_RELEASE, $data));
    }
}
