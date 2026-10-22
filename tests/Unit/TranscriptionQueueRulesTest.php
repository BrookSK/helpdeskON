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

    public function testErroTruncadoEm500(): void
    {
        $this->assertSame(500, mb_strlen(TranscriptionQueueRules::truncateError(str_repeat('é', 600))));
        $this->assertSame('a b', TranscriptionQueueRules::truncateError("  a\n  b "));
    }
}
