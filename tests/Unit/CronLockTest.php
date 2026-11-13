<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use CronLock;

/**
 * Trava de execução única das rotinas agendadas (fila de gravações).
 * Usa um diretório temporário próprio; não toca em storage/locks.
 */
final class CronLockTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cronlock_' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . DIRECTORY_SEPARATOR . '*') ?: [] as $f) @unlink($f);
        @rmdir($this->dir);
    }

    public function testSegundaTentativaFicaSemTravaEnquantoAPrimeiraSegura(): void
    {
        $a = CronLock::tryAcquire('fila', $this->dir);
        $this->assertInstanceOf(CronLock::class, $a);
        $this->assertNull(CronLock::tryAcquire('fila', $this->dir));
        $this->assertNull(CronLock::tryAcquire('fila', $this->dir), 'continua ocupada');
        $a->release();
    }

    public function testDepoisDeSoltarOutraExecucaoConsegue(): void
    {
        $a = CronLock::tryAcquire('fila', $this->dir);
        $a->release();
        $a->release(); // soltar duas vezes não quebra
        $b = CronLock::tryAcquire('fila', $this->dir);
        $this->assertInstanceOf(CronLock::class, $b);
        $b->release();
    }

    public function testObjetoDescartadoSoltaATrava(): void
    {
        $a = CronLock::tryAcquire('fila', $this->dir);
        $this->assertNotNull($a);
        unset($a);
        $this->assertNotNull(CronLock::tryAcquire('fila', $this->dir));
    }

    public function testNomesDiferentesNaoSeBloqueiam(): void
    {
        $a = CronLock::tryAcquire('fila-a', $this->dir);
        $b = CronLock::tryAcquire('fila-b', $this->dir);
        $this->assertNotNull($a);
        $this->assertNotNull($b);
    }

    public function testCriaODiretorioQuandoNaoExiste(): void
    {
        $this->assertDirectoryDoesNotExist($this->dir);
        $a = CronLock::tryAcquire('fila', $this->dir);
        $this->assertNotNull($a);
        $this->assertFileExists($this->dir . DIRECTORY_SEPARATOR . 'fila.lock');
    }

    public function testNomeComCaminhoOuVazioERecusado(): void
    {
        foreach (['', '../fila', 'a/b', 'a\\b', 'Fila', 'fila.lock', str_repeat('a', 65)] as $nome) {
            $this->assertFalse(CronLock::isValidName($nome), "nome aceito: {$nome}");
        }
        $this->assertTrue(CronLock::isValidName(CronLock::RECORDING_QUEUE));
        $this->expectException(\InvalidArgumentException::class);
        CronLock::tryAcquire('../fora', $this->dir);
    }
}
