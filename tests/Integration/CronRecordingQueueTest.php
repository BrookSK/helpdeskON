<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use CronController;
use Config;
use Database;

/**
 * Os crons agendados (runProspecting, finalizeRecordings) rodam a fila de
 * transcrição mesmo sem cron_token. Na produção não havia token e a fila
 * ficava parada em "Na fila de transcrição" sem erro.
 */
final class CronRecordingQueueTest extends TestCase
{
    private Database $db;
    /** @var array<string,?string> valores originais das configurações alteradas */
    private array $saved = [];

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste (helpdesk_on_test) não configurado.');
        }
        $this->db = Database::getInstance();
        // Sem token de cron e sem chave da OpenAI: nenhuma chamada externa.
        foreach (['cron_token', 'openai_api_key'] as $key) {
            $row = $this->db->fetch("SELECT setting_value FROM settings WHERE setting_key = ?", [$key]);
            $this->saved[$key] = $row ? (string)$row['setting_value'] : null;
            $this->db->query("DELETE FROM settings WHERE setting_key = ?", [$key]);
        }
        Config::reload();
    }

    protected function tearDown(): void
    {
        foreach ($this->saved as $key => $value) {
            if ($value !== null) Config::set($key, $value);
        }
        Config::reload();
    }

    public function testFilaDeTranscricaoRodaSemCronToken(): void
    {
        $m = new ReflectionMethod(CronController::class, 'processRecordingQueues');
        $out = $m->invoke(new CronController());

        $this->assertIsArray($out['transcription'], 'a fila de transcrição deve rodar sem cron_token');
        $this->assertArrayHasKey('processed', $out['transcription']);
    }
}
