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
        $this->assertSame(8000, $ai->calls[0]['options']['max_tokens']);
        $this->assertSame('gpt-4o-mini', $ai->calls[0]['options']['model']);
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

        $json = (new MeetingMinutesGenerator($ai))->generate($transcript, ['guests' => 'Carlos']);

        $this->assertCount(3, $ai->calls);
        $this->assertStringContainsString('fim', $ai->calls[1]['messages'][1]['content']);
        // As notas de cada parte também sabem quem é cliente, para atribuir as tarefas.
        $this->assertStringContainsString('Convidados externos (cliente): Carlos', $ai->calls[0]['messages'][1]['content']);
        $final = $ai->calls[2]['messages'][1]['content'];
        $this->assertStringContainsString('notas parte 1', $final);
        $this->assertStringContainsString('notas parte 2', $final);
        $this->assertSame(['fechar'], json_decode($json, true)['decisoes']);
    }

    public function testCabecalhoSeparaNossaEquipeDoCliente(): void
    {
        $ai = new FakeChat();
        (new MeetingMinutesGenerator($ai))->generate('[00:01] oi', [
            'organizer' => 'Ana', 'participants' => 'Ana, Bruno', 'guests' => 'Carlos, Dora',
        ]);
        $user = $ai->calls[0]['messages'][1]['content'];
        $this->assertStringContainsString('Responsável pela reunião (nossa equipe): Ana', $user);
        $this->assertStringContainsString('Participantes da nossa equipe: Ana, Bruno', $user);
        $this->assertStringContainsString('Convidados externos (cliente): Carlos, Dora', $user);

        // Chaves vazias não geram linha.
        $ai = new FakeChat();
        (new MeetingMinutesGenerator($ai))->generate('[00:01] oi', ['title' => 'X', 'organizer' => '', 'guests' => '']);
        $this->assertStringNotContainsString('Convidados externos', $ai->calls[0]['messages'][1]['content']);
        $this->assertStringNotContainsString('Responsável pela reunião', $ai->calls[0]['messages'][1]['content']);
    }

    public function testJsonCortadoNoTetoDeTokensTentaDeNovoUmaVez(): void
    {
        $ai = new FakeChat();
        $ai->replies = [
            ['success' => true, 'content' => '{"resumo":"long', 'raw' => ['choices' => [['finish_reason' => 'length']]]],
            ['success' => true, 'content' => '{"resumo":"curta","proximos_passos":["Ana (Equipe): enviar proposta. Prazo: não definido."]}'],
        ];
        $json = (new MeetingMinutesGenerator($ai))->generate('[00:01] oi');

        $this->assertCount(2, $ai->calls);
        $this->assertSame(['Ana (Equipe): enviar proposta. Prazo: não definido.'], json_decode($json, true)['proximos_passos']);

        // Segunda resposta também cortada: desiste (sem laço) e devolve vazio.
        $cut = ['success' => true, 'content' => '{"resumo":"x', 'raw' => ['choices' => [['finish_reason' => 'length']]]];
        $ai = new FakeChat();
        $ai->replies = [$cut, $cut, ['success' => true, 'content' => '{"resumo":"nunca"}']];
        $this->assertSame('', (new MeetingMinutesGenerator($ai))->generate('[00:01] oi'));
        $this->assertCount(2, $ai->calls);
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
