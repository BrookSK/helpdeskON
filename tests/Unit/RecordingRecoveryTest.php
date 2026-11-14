<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecordingRecovery;

/** Partes puras da recuperação de gravação (recover_recording.php). */
final class RecordingRecoveryTest extends TestCase
{
    public function testTokenAPartirDoArquivoDoLinkOuDoProprioToken(): void
    {
        $this->assertSame('abcDEF123456', RecordingRecovery::tokenFromArg('rec_141_abcDEF123456.webm'));
        $this->assertSame('abcDEF123456', RecordingRecovery::tokenFromArg('public/uploads/recordings/rec_141_abcDEF123456.webm'));
        $this->assertSame('abcDEF123456', RecordingRecovery::tokenFromArg('https://helpdesk.x.com.br/videocall/recording/abcDEF123456'));
        $this->assertSame('abcDEF123456', RecordingRecovery::tokenFromArg('  abcDEF123456 '));
    }

    public function testTokenInvalidoNaoEAceito(): void
    {
        foreach (['', 'rec_141_.webm', '../etc/passwd', 'abc', "abc123456'; DROP", 'rec_141_abc123456.webm.bak'] as $arg) {
            $this->assertNull(RecordingRecovery::tokenFromArg($arg), "aceitou: {$arg}");
        }
    }

    public function testListaDoFfmpegViraTrechosEmOrdem(): void
    {
        $csv = "seg_000002.webm,60.000000,120.000000\r\nseg_000001.webm,0.000000,60.000000\nseg_000003.webm,120.000000,151.480000\n";
        $list = RecordingRecovery::parseSegmentList($csv);
        $this->assertSame(['seg_000001.webm', 'seg_000002.webm', 'seg_000003.webm'], array_column($list, 'file'));
        $this->assertSame([0.0, 60.0, 120.0], array_column($list, 'start'));
        $this->assertSame(151.48, $list[2]['end']);
    }

    public function testListaIgnoraLinhasRuinsENomesForaDoPadrao(): void
    {
        $csv = implode("\n", [
            '',
            'lixo',
            '../fora.webm,0,60',
            'seg_000001.wav,0,60',
            'seg_000001.webm,abc,60',
            'seg_000002.webm,60,60',   // duração zero
            'seg_000003.mp3,120,180',
        ]);
        $list = RecordingRecovery::parseSegmentList($csv);
        $this->assertSame([['file' => 'seg_000003.mp3', 'start' => 120.0, 'end' => 180.0]], $list);
        $this->assertSame([], RecordingRecovery::parseSegmentList(''));
    }

    public function testComandoDeExtracaoCortaEmUmMinutoSemLerOTeclado(): void
    {
        $fmt = RecordingRecovery::AUDIO_FORMATS[0];
        $cmd = RecordingRecovery::extractAudioCommand('/bin/ff mpeg', '/v/rec 1.webm', '/a/dir/', $fmt);
        $this->assertStringContainsString(escapeshellarg('/bin/ff mpeg') . ' -nostdin', $cmd);
        $this->assertStringContainsString(' -i ' . escapeshellarg('/v/rec 1.webm'), $cmd);
        $this->assertStringContainsString('-f segment -segment_time 60', $cmd);
        $this->assertStringContainsString('-c:a libopus', $cmd);
        $this->assertStringContainsString(escapeshellarg('/a/dir/list.csv'), $cmd);
        $this->assertStringEndsWith(escapeshellarg('/a/dir') . '/seg_%06d.webm', $cmd);
        $this->assertStringContainsString('-segment_start_number 1', $cmd);
    }

    public function testFormatosTemMimeAceitoPelaFila(): void
    {
        foreach (RecordingRecovery::AUDIO_FORMATS as $fmt) {
            $this->assertStringStartsWith('audio/', $fmt['mime']);
            $this->assertMatchesRegularExpression('/^(webm|mp3)$/', $fmt['ext']);
        }
    }

    public function testDuracaoFormatada(): void
    {
        $this->assertSame('0:01:53', RecordingRecovery::formatDuration(113));
        $this->assertSame('2:41:07', RecordingRecovery::formatDuration(9667.4));
        $this->assertSame('0:00:00', RecordingRecovery::formatDuration(0));
    }
}
