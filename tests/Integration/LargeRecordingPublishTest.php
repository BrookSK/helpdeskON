<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use VideocallController;

/**
 * Sem FFmpeg, a publicação injeta a duração no WebM lendo o arquivo. Uma
 * gravação de ~2 GB estourava a memória do PHP antes de ir para o banco, em
 * toda execução do cron, e a gravação nunca aparecia.
 */
final class LargeRecordingPublishTest extends TestCase
{
    private string $file = '';

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste (helpdesk_on_test) não configurado.');
        }
        $this->file = tempnam(sys_get_temp_dir(), 'rec_big_');
        // Arquivo esparso de 80 MiB: tamanho real sem ocupar o disco.
        $fp = fopen($this->file, 'wb');
        ftruncate($fp, 80 * 1024 * 1024);
        fclose($fp);
    }

    protected function tearDown(): void
    {
        if ($this->file !== '') @unlink($this->file);
    }

    public function testArquivoGrandeNaoECarregadoNaMemoria(): void
    {
        $m = new ReflectionMethod(VideocallController::class, 'injectWebmDuration');
        $controller = new VideocallController();

        memory_reset_peak_usage();
        $before = memory_get_usage();
        $out = $m->invoke($controller, $this->file);
        $peak = memory_get_peak_usage() - $before;

        $this->assertNull($out);
        $this->assertLessThan(16 * 1024 * 1024, $peak, 'o arquivo grande não pode ser lido inteiro na memória');
        $this->assertSame(80 * 1024 * 1024, filesize($this->file), 'o arquivo fica intacto');
    }
}
