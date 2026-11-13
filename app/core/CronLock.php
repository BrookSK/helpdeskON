<?php

/**
 * Trava de execução única para rotinas agendadas (flock sem espera).
 *
 * Usada pela fila de gravações/transcrição: o cron roda a cada minuto, mas
 * publicar uma reunião longa leva vários minutos. Sem a trava, as execuções se
 * acumulavam, cada uma montando o vídeo de novo e segurando uma conexão com o
 * banco, até o MariaDB recusar conexões ("Too many connections").
 *
 * O arquivo da trava fica dentro do projeto (storage/locks), e não em /tmp:
 * o terminal SSH do Plesk roda em chroot e enxerga outro /tmp, mas o arquivo
 * do projeto é o mesmo para o SSH, a tarefa agendada e as requisições HTTP.
 *
 * O sistema operacional solta o flock quando o processo termina, inclusive se
 * ele for morto (kill -9). Não existe trava "presa" para limpar à mão.
 */
class CronLock
{
    /**
     * Trava da fila de gravações: publicar sessões abandonadas + transcrição.
     * Compartilhada por cron_transcribe.php e pelas rotas /cron que fazem o mesmo.
     */
    public const RECORDING_QUEUE = 'recording-queue';

    /** @var resource|null */
    private $handle;

    /** @param resource $handle */
    private function __construct($handle)
    {
        $this->handle = $handle;
    }

    /** Nome aceito para o arquivo da trava (sem caminho). */
    public static function isValidName(string $name): bool
    {
        return (bool) preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/', $name);
    }

    /** Diretório padrão das travas: <projeto>/storage/locks. */
    public static function defaultDir(): string
    {
        return BASE_PATH . '/storage/locks';
    }

    /**
     * Tenta pegar a trava sem esperar.
     *
     * @return self|null a trava (mantenha a referência enquanto trabalha) ou
     *                   null quando outra execução já está com ela
     * @throws InvalidArgumentException nome inválido
     * @throws RuntimeException não foi possível abrir o arquivo da trava
     */
    public static function tryAcquire(string $name, ?string $dir = null): ?self
    {
        if (!self::isValidName($name)) {
            throw new InvalidArgumentException('cron_lock_invalid_name');
        }
        $dir = $dir ?: self::defaultDir();
        if (!is_dir($dir)) @mkdir($dir, 0775, true);

        $fh = @fopen($dir . DIRECTORY_SEPARATOR . $name . '.lock', 'c');
        if ($fh === false) {
            throw new RuntimeException('cron_lock_open_failed');
        }
        if (!flock($fh, LOCK_EX | LOCK_NB)) {
            fclose($fh);
            return null;
        }
        // Só diagnóstico: quem está com a trava e desde quando.
        @ftruncate($fh, 0);
        @fwrite($fh, getmypid() . ' ' . date('Y-m-d H:i:s') . "\n");
        @fflush($fh);
        return new self($fh);
    }

    public function release(): void
    {
        if ($this->handle) {
            @flock($this->handle, LOCK_UN);
            @fclose($this->handle);
            $this->handle = null;
        }
    }

    public function __destruct()
    {
        $this->release();
    }
}
