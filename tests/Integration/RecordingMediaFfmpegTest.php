<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use RecordingMedia;
use Config;
use Database;

/**
 * Localização do FFmpeg sem configuração: a produção não tem FFmpeg no
 * sistema, então o binário estático em storage/ffmpeg/ffmpeg precisa ser
 * achado sozinho (remux na publicação = vídeo com duração e avanço).
 *
 * O caso com binário real só roda com FFMPEG_TEST_BIN definido.
 */
final class RecordingMediaFfmpegTest extends TestCase
{
    private Database $db;
    private ?string $savedPath = null;
    private string $dir;

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste (helpdesk_on_test) não configurado.');
        }
        $this->db = Database::getInstance();
        $row = $this->db->fetch("SELECT setting_value FROM settings WHERE setting_key = 'ffmpeg_path'");
        $this->savedPath = $row ? (string)$row['setting_value'] : null;
        $this->db->query("DELETE FROM settings WHERE setting_key = 'ffmpeg_path'");
        Config::reload();
        $this->dir = sys_get_temp_dir() . '/rmff_' . bin2hex(random_bytes(6));
        @mkdir($this->dir, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->db->query("DELETE FROM settings WHERE setting_key = 'ffmpeg_path'");
        if ($this->savedPath !== null) Config::set('ffmpeg_path', $this->savedPath);
        Config::reload();
        foreach (glob($this->dir . '/*') ?: [] as $f) @unlink($f);
        @rmdir($this->dir);
    }

    public function testCaminhoDoProjetoEsperado(): void
    {
        $this->assertSame('storage/ffmpeg/ffmpeg', RecordingMedia::BUNDLED_FFMPEG);
    }

    public function testArquivoQueNaoEFfmpegNaoEUsado(): void
    {
        // Ex.: download interrompido ou descompactação que falhou.
        $fake = $this->dir . '/ffmpeg';
        file_put_contents($fake, 'nao sou o ffmpeg');
        $this->assertNotSame($fake, RecordingMedia::ffmpegBin($fake));
    }

    public function testBinarioInexistenteNaoEUsado(): void
    {
        $missing = $this->dir . '/nao-existe';
        $this->assertNotSame($missing, RecordingMedia::ffmpegBin($missing));
    }

    public function testFfmpegDoProjetoEAchadoSemConfiguracao(): void
    {
        $ff = (string) getenv('FFMPEG_TEST_BIN');
        if ($ff === '' || !is_file($ff)) $this->markTestSkipped('FFMPEG_TEST_BIN não definido.');
        $this->assertSame($ff, RecordingMedia::ffmpegBin($ff));
    }

    public function testConfiguracaoTemPrioridadeSobreOBinarioDoProjeto(): void
    {
        $configured = $this->dir . '/configurado';
        file_put_contents($configured, 'x');
        Config::set('ffmpeg_path', $configured);
        Config::reload();
        $this->assertSame($configured, RecordingMedia::ffmpegBin($this->dir . '/outro'));
    }
}
