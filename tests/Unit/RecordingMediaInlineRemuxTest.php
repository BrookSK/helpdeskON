<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecordingMedia;

/** Quando o remux roda na hora e quando fica para o cron. */
final class RecordingMediaInlineRemuxTest extends TestCase
{
    public function testWebRemuxaSoArquivoPequeno(): void
    {
        $this->assertTrue(RecordingMedia::canRemuxInline(5 * 1048576, 'fpm-fcgi'));
        $this->assertTrue(RecordingMedia::canRemuxInline(RecordingMedia::INLINE_REMUX_MAX_BYTES, 'fpm-fcgi'));
        // Reunião de horas (1,5 GB) ficaria minutos na requisição de quem parou a gravação.
        $this->assertFalse(RecordingMedia::canRemuxInline(RecordingMedia::INLINE_REMUX_MAX_BYTES + 1, 'fpm-fcgi'));
        $this->assertFalse(RecordingMedia::canRemuxInline(1500 * 1048576, 'apache2handler'));
        $this->assertFalse(RecordingMedia::canRemuxInline(0, 'fpm-fcgi'));
    }

    public function testCronRemuxaQualquerTamanho(): void
    {
        $this->assertTrue(RecordingMedia::canRemuxInline(1500 * 1048576, 'cli'));
        $this->assertTrue(RecordingMedia::canRemuxInline(1, 'cli'));
    }
}
