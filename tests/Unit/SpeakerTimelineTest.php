<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use SpeakerTimeline;
use TranscriptionQueueRules;

/** Quem falou em cada trecho da transcrição (regras puras). */
final class SpeakerTimelineTest extends TestCase
{
    public function testSanitizeAceitaJsonEArray(): void
    {
        $json = '[{"s":1.234,"e":5,"n":"Lucas Vacari"},{"s":6,"e":9.5,"n":"Narciso"}]';
        $this->assertSame([
            ['s' => 1.23, 'e' => 5.0, 'n' => 'Lucas Vacari'],
            ['s' => 6.0, 'e' => 9.5, 'n' => 'Narciso'],
        ], SpeakerTimeline::sanitize($json));
        $this->assertSame([['s' => 0.0, 'e' => 1.0, 'n' => 'A']], SpeakerTimeline::sanitize([['s' => 0, 'e' => 1, 'n' => 'A']]));
    }

    public function testSanitizeDescartaOQueNaoEIntervaloValido(): void
    {
        $raw = [
            ['s' => 5, 'e' => 5, 'n' => 'zero'],          // sem duração
            ['s' => 9, 'e' => 3, 'n' => 'invertido'],
            ['s' => 'x', 'e' => 3, 'n' => 'texto'],
            ['s' => 1, 'e' => 2, 'n' => '   '],           // sem nome
            ['s' => 1, 'e' => 90000, 'n' => 'longo'],     // além de 24 h
            'lixo',
            ['s' => -3, 'e' => 2, 'n' => 'negativo'],     // início vira 0
        ];
        $this->assertSame([['s' => 0.0, 'e' => 2.0, 'n' => 'negativo']], SpeakerTimeline::sanitize($raw));
        foreach (['', 'nao json', '{"s":1}', 'null', '123'] as $bad) {
            $this->assertSame([], SpeakerTimeline::sanitize($bad), "aceitou: {$bad}");
        }
        $this->assertSame([], SpeakerTimeline::sanitize(null));
    }

    public function testNomeLimpoELimitado(): void
    {
        $this->assertSame('Renato convidado', SpeakerTimeline::cleanName("  Renato \n\t convidado\x07 "));
        $this->assertSame(SpeakerTimeline::MAX_NAME_LEN, mb_strlen(SpeakerTimeline::cleanName(str_repeat('é', 200))));
        $this->assertSame('', SpeakerTimeline::cleanName(['array']));
    }

    public function testSanitizeLimitaAQuantidade(): void
    {
        $raw = [];
        for ($i = 0; $i < 10; $i++) $raw[] = ['s' => $i, 'e' => $i + 0.5, 'n' => 'A'];
        $this->assertCount(3, SpeakerTimeline::sanitize($raw, 3));
    }

    public function testCombineJuntaOrdenaESemRepetir(): void
    {
        $seg1 = [['s' => 10, 'e' => 12, 'n' => 'B'], ['s' => 1, 'e' => 3, 'n' => 'A']];
        $seg2 = [['s' => 1, 'e' => 3, 'n' => 'A'], ['s' => 70, 'e' => 75, 'n' => 'C']]; // reenvio do mesmo intervalo
        $all = SpeakerTimeline::combine([$seg1, [], $seg2, 'lixo']);
        $this->assertSame(['A', 'B', 'C'], array_column($all, 'n'));
        $this->assertSame([1.0, 10.0, 70.0], array_column($all, 's'));
    }

    public function testQuemMaisFalouNoTrechoLevaONome(): void
    {
        $tl = [['s' => 0, 'e' => 4, 'n' => 'Lucas'], ['s' => 4, 'e' => 10, 'n' => 'Narciso']];
        $this->assertSame('Narciso', SpeakerTimeline::speakerFor(3, 9, $tl));
        $this->assertSame('Lucas', SpeakerTimeline::speakerFor(0.5, 3.5, $tl));
    }

    public function testFalasSomadasDaMesmaPessoa(): void
    {
        // Lucas fala em dois pedaços (3 s no total); Narciso em um (2,5 s).
        $tl = [['s' => 0, 'e' => 1.5, 'n' => 'Lucas'], ['s' => 1.5, 'e' => 4, 'n' => 'Narciso'], ['s' => 4, 'e' => 5.5, 'n' => 'Lucas']];
        $this->assertSame('Lucas', SpeakerTimeline::speakerFor(0, 5.5, $tl));
    }

    public function testEmpateFicaComQuemComecouPrimeiro(): void
    {
        $tl = [['s' => 2, 'e' => 4, 'n' => 'B'], ['s' => 0, 'e' => 2, 'n' => 'A']];
        $this->assertSame('A', SpeakerTimeline::speakerFor(0, 4, $tl));
    }

    public function testSemNinguemDentroUsaOMaisProximoAteUmSegundo(): void
    {
        $tl = [['s' => 0, 'e' => 5, 'n' => 'Lucas'], ['s' => 20, 'e' => 25, 'n' => 'Narciso']];
        $this->assertSame('Lucas', SpeakerTimeline::speakerFor(5.6, 6.2, $tl), 'fala logo depois do verde apagar');
        $this->assertSame('Narciso', SpeakerTimeline::speakerFor(19.2, 19.8, $tl), 'fala logo antes do verde acender');
        $this->assertNull(SpeakerTimeline::speakerFor(10, 12, $tl), 'longe de todos: sem nome');
        // Trecho sem duração dentro de um intervalo.
        $this->assertSame('Narciso', SpeakerTimeline::speakerFor(22, 22, $tl));
    }

    public function testAssignPoeONomeSoOndeSabe(): void
    {
        $tl = [['s' => 0, 'e' => 10, 'n' => 'Lucas Vacari']];
        $segs = [
            ['start' => 1, 'end' => 4, 'text' => 'bom dia'],
            ['start' => 30, 'end' => 32, 'text' => 'sem ninguém falando'],
        ];
        $out = SpeakerTimeline::assign($segs, $tl);
        $this->assertSame('Lucas Vacari', $out[0]['speaker']);
        $this->assertArrayNotHasKey('speaker', $out[1]);
        $this->assertSame('bom dia', $out[0]['text']);
        // Sem linha do tempo (gravação antiga): nada muda.
        $this->assertSame($segs, SpeakerTimeline::assign($segs, []));
    }

    public function testTranscricaoComNomeNaLinha(): void
    {
        $segs = [
            ['start' => 1, 'end' => 3, 'text' => 'Bom dia', 'speaker' => 'Lucas Vacari'],
            ['start' => 65, 'end' => 70, 'text' => 'Mandem a proposta', 'speaker' => 'Narciso'],
            ['start' => 3700, 'end' => 3702, 'text' => 'sem nome'],
        ];
        $this->assertSame(
            "[00:01] Lucas Vacari: Bom dia\n[01:05] Narciso: Mandem a proposta\n[1:01:40] sem nome",
            TranscriptionQueueRules::transcriptText($segs)
        );
    }
}
