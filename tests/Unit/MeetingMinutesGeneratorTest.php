<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use MeetingMinutesGenerator;
use MeetingMinutesRules;

/** IA falsa: guarda as mensagens e devolve respostas em ordem. */
final class FakeChat
{
    public array $calls = [];
    public array $replies = [];

    public function isConfigured(): bool { return true; }

    public function chat(array $messages, array $options = []): array
    {
        $this->calls[] = ['messages' => $messages, 'options' => $options];
        return array_shift($this->replies) ?? ['success' => true, 'content' => '{"resumo":"ok"}'];
    }
}

final class MeetingMinutesGeneratorTest extends TestCase
{
    public function testTranscricaoLongaChegaInteiraAoPrompt(): void
    {
        $ai = new FakeChat();
        $transcript = str_repeat("[00:01] conversa inicial\n", 3000) . '[1:59:00] DECISÃO FINAL: fechar em dezembro';
        $this->assertGreaterThan(48000, mb_strlen($transcript));

        $json = (new MeetingMinutesGenerator($ai))->generate($transcript, ['title' => 'Descoberta']);

        $this->assertCount(1, $ai->calls);
        $user = $ai->calls[0]['messages'][1]['content'];
        $this->assertStringContainsString('DECISÃO FINAL: fechar em dezembro', $user);
        $this->assertStringContainsString('Reunião: Descoberta', $user);
        $this->assertSame(3000, $ai->calls[0]['options']['max_tokens']);
        $this->assertSame('ok', json_decode($json, true)['resumo']);
        $this->assertSame([], json_decode($json, true)['orcamento']);
    }

    public function testAcimaDoLimiteGeraNotasPorParteEAtaDasNotas(): void
    {
        $ai = new FakeChat();
        $ai->replies = [
            ['success' => true, 'content' => 'notas parte 1'],
            ['success' => true, 'content' => 'notas parte 2'],
            ['success' => true, 'content' => '{"resumo":"final","decisoes":["fechar"]}'],
        ];
        $linha = str_repeat('x', 1000) . "\n";
        $transcript = str_repeat($linha, (int) ceil(MeetingMinutesRules::SPLIT_LIMIT / 1001) + 10) . 'fim';

        $json = (new MeetingMinutesGenerator($ai))->generate($transcript);

        $this->assertCount(3, $ai->calls);
        $this->assertStringContainsString('fim', $ai->calls[1]['messages'][1]['content']);
        $final = $ai->calls[2]['messages'][1]['content'];
        $this->assertStringContainsString('notas parte 1', $final);
        $this->assertStringContainsString('notas parte 2', $final);
        $this->assertSame(['fechar'], json_decode($json, true)['decisoes']);
    }

    public function testRespostaInvalidaOuFalhaDevolveVazio(): void
    {
        $ai = new FakeChat();
        $ai->replies = [['success' => true, 'content' => 'texto solto']];
        $this->assertSame('', (new MeetingMinutesGenerator($ai))->generate('[00:01] oi'));
        $ai->replies = [['success' => false, 'error' => 'HTTP 500']];
        $this->assertSame('', (new MeetingMinutesGenerator($ai))->generate('[00:01] oi'));
    }
}
