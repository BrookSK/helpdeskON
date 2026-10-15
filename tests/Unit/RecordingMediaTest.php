<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecordingMedia;

/**
 * Testes da parte PURA de RecordingMedia (parsing da saída do FFmpeg e decisão
 * de validade). Não executa o binário — valida a lógica que interpreta a saída,
 * que é onde moram os bugs do diagnóstico (duração mal lida, vídeo inválido
 * publicado como sucesso, erro de integridade ignorado).
 */
final class RecordingMediaTest extends TestCase
{
    private function validOutput(): string
    {
        return <<<TXT
Input #0, matroska,webm, from 'rec.webm':
  Duration: 00:12:34.56, start: 0.000000, bitrate: 1200 kb/s
    Stream #0:0(eng): Video: vp9 (Profile 0), yuv420p(tv), 1920x1080, SAR 1:1 DAR 16:9
    Stream #0:1(eng): Audio: opus, 48000 Hz, stereo, fltp
TXT;
    }

    public function testParseDuration(): void
    {
        $this->assertEqualsWithDelta(754.56, RecordingMedia::parseDurationSeconds($this->validOutput()), 0.001);
        $this->assertSame(0.0, RecordingMedia::parseDurationSeconds('sem duracao aqui'));
        $this->assertEqualsWithDelta(3661.0, RecordingMedia::parseDurationSeconds('Duration: 01:01:01.00,'), 0.001);
    }

    public function testDeteccaoDeStreams(): void
    {
        $out = $this->validOutput();
        $this->assertTrue(RecordingMedia::hasVideoStream($out));
        $this->assertTrue(RecordingMedia::hasAudioStream($out));

        $soVideo = "Duration: 00:00:06.00,\n    Stream #0:0: Video: vp9, 1280x720";
        $this->assertTrue(RecordingMedia::hasVideoStream($soVideo));
        $this->assertFalse(RecordingMedia::hasAudioStream($soVideo));
    }

    public function testErroDeIntegridade(): void
    {
        $corrupt = "Duration: 00:10:00.00,\n Stream #0:0: Video: vp9\n[matroska] EBML header parsing failed\nInvalid data found when processing input";
        $this->assertTrue(RecordingMedia::hasIntegrityError($corrupt));
        $this->assertFalse(RecordingMedia::hasIntegrityError($this->validOutput()));
    }

    public function testValidadeExigeDuracaoVideoEAudio(): void
    {
        // Caso bom.
        $this->assertTrue(RecordingMedia::isValidFromOutput($this->validOutput()));

        // Sem duração -> inválido (o "término prematuro" do incidente).
        $semDur = "Stream #0:0: Video: vp9\n Stream #0:1: Audio: opus";
        $this->assertFalse(RecordingMedia::isValidFromOutput($semDur));

        // Com erro de integridade -> inválido mesmo que decodifique algo.
        $corrupt = "Duration: 00:10:00.00,\n Stream #0:0: Video: vp9\n Stream #0:1: Audio: opus\nInvalid data found when processing input";
        $this->assertFalse(RecordingMedia::isValidFromOutput($corrupt));

        // Só vídeo, exigindo áudio -> inválido; sem exigir áudio -> válido.
        $soVideo = "Duration: 00:00:06.00,\n Stream #0:0: Video: vp9, 1280x720";
        $this->assertFalse(RecordingMedia::isValidFromOutput($soVideo, true));
        $this->assertTrue(RecordingMedia::isValidFromOutput($soVideo, false));
    }
}
