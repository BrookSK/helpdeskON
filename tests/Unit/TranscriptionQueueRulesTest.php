<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use TranscriptionQueueRules;

final class TranscriptionQueueRulesTest extends TestCase
{
    public function testFalhaVoltaParaFilaAteATerceiraTentativa(): void
    {
        $this->assertSame('pending', TranscriptionQueueRules::statusAfterFailure(1));
        $this->assertSame('pending', TranscriptionQueueRules::statusAfterFailure(2));
        $this->assertSame('error', TranscriptionQueueRules::statusAfterFailure(3));
    }

    public function testReservaPresaDepoisDeDezMinutos(): void
    {
        $now = strtotime('2026-10-22 10:00:00');
        $this->assertFalse(TranscriptionQueueRules::isStaleLock('2026-10-22 09:55:00', $now));
        $this->assertTrue(TranscriptionQueueRules::isStaleLock('2026-10-22 09:50:00', $now));
        $this->assertTrue(TranscriptionQueueRules::isStaleLock(null, $now));
    }

    public function testEstadoDaGravacaoPelosSegmentos(): void
    {
        $this->assertSame('none', TranscriptionQueueRules::recordingStatus([]));
        $this->assertSame('queued', TranscriptionQueueRules::recordingStatus(['pending', 'pending']));
        $this->assertSame('processing', TranscriptionQueueRules::recordingStatus(['done', 'pending']));
        $this->assertSame('processing', TranscriptionQueueRules::recordingStatus(['processing', 'done']));
        $this->assertSame('error', TranscriptionQueueRules::recordingStatus(['done', 'error']));
        $this->assertSame('done', TranscriptionQueueRules::recordingStatus(['done', 'done']));
    }

    public function testJuncaoDeTresSegmentosComDeslocamento(): void
    {
        // Segmentos de 10, 10 e 4 minutos, fora de ordem.
        $r = TranscriptionQueueRules::merge([
            ['seq' => 3, 'start_sec' => 1200, 'text' => 'terceiro', 'segments' => [['start' => 5, 'end' => 9, 'text' => 'terceiro']]],
            ['seq' => 1, 'start_sec' => 0, 'text' => 'primeiro', 'segments' => [['start' => 1, 'end' => 3, 'text' => 'primeiro']]],
            ['seq' => 2, 'start_sec' => 600, 'text' => 'segundo', 'segments' => [['start' => 2, 'end' => 4, 'text' => 'segundo']]],
        ]);
        $this->assertSame(['primeiro', 'segundo', 'terceiro'], array_column($r['segments'], 'text'));
        $this->assertSame(1205.0, $r['segments'][2]['start']);
        $this->assertSame(602.0, $r['segments'][1]['start']);
        $this->assertSame("primeiro\nsegundo\nterceiro", $r['text']);
        $this->assertSame("[00:01] primeiro\n[10:02] segundo\n[20:05] terceiro", TranscriptionQueueRules::transcriptText($r['segments']));
    }

    public function testSegmentoSemTrechosUsaOTextoNoInicio(): void
    {
        $r = TranscriptionQueueRules::merge([['seq' => 1, 'start_sec' => 300, 'text' => 'só texto']]);
        $this->assertSame([['start' => 300.0, 'end' => 300.0, 'text' => 'só texto']], $r['segments']);
    }

    public function testLimpezaRemoveSilencioERepeticao(): void
    {
        $out = TranscriptionQueueRules::cleanSegments([
            ['start' => 2, 'end' => 3, 'text' => 'oi'],
            ['start' => 0, 'end' => 1, 'text' => '...'],
            ['start' => 2.4, 'end' => 3, 'text' => 'oi'],
        ]);
        $this->assertSame([['start' => 2.0, 'end' => 3.0, 'text' => 'oi']], $out);
    }

    public function testLimpezaTiraAlucinacaoDeReuniaoLonga(): void
    {
        $out = TranscriptionQueueRules::cleanSegments([
            // Silêncio que o Whisper preencheu: alta chance de não haver fala e baixa confiança.
            ['start' => 0, 'end' => 1, 'text' => 'E aí', 'no_speech_prob' => 0.8, 'avg_logprob' => -1.2],
            // Fala real com baixa confiança continua.
            ['start' => 1, 'end' => 2, 'text' => 'Bom dia', 'no_speech_prob' => 0.2, 'avg_logprob' => -1.3],
            // A mesma frase em sequência vira um trecho só, até o fim da última.
            ['start' => 10, 'end' => 11, 'text' => 'Eu peguei.'],
            ['start' => 15, 'end' => 16, 'text' => 'eu peguei'],
            ['start' => 20, 'end' => 21, 'text' => 'Eu peguei.'],
            ['start' => 30, 'end' => 35, 'text' => 'Então, dia tal, dia tal, dia tal, dia tal, dia tal'],
        ]);
        $this->assertSame([
            ['start' => 1.0, 'end' => 2.0, 'text' => 'Bom dia'],
            ['start' => 10.0, 'end' => 21.0, 'text' => 'Eu peguei.'],
            ['start' => 30.0, 'end' => 35.0, 'text' => 'Então, dia tal'],
        ], $out);
    }

    public function testLoopEncolheMasRepeticaoNaturalFica(): void
    {
        $this->assertSame('Tal.', TranscriptionQueueRules::collapseLoops('Tal, tal, tal, tal, tal, tal, tal, tal.'));
        $this->assertSame('eu acho que vai', TranscriptionQueueRules::collapseLoops('eu acho que eu acho que eu acho que eu acho que vai'));
        $this->assertSame('não, não, não', TranscriptionQueueRules::collapseLoops('não, não, não'));
        $this->assertSame('talvez tal', TranscriptionQueueRules::collapseLoops('talvez tal tal tal tal'));
    }

    public function testErroTruncadoEm500(): void
    {
        $this->assertSame(500, mb_strlen(TranscriptionQueueRules::truncateError(str_repeat('é', 600))));
        $this->assertSame('a b', TranscriptionQueueRules::truncateError("  a\n  b "));
    }

    public function testLoteInteiroTerminaAntesDaReservaVencer(): void
    {
        // Se o lote passar da janela de reserva, outra execução do cron devolve e
        // pega os mesmos segmentos, e eles são transcritos (e cobrados) duas vezes.
        $pior = TranscriptionQueueRules::BATCH_SIZE * TranscriptionQueueRules::SEGMENT_TIMEOUT_SEC;
        $this->assertLessThan(TranscriptionQueueRules::STALE_LOCK_MINUTES * 60, $pior);
    }
}
