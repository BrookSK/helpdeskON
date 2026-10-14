<?php

/**
 * Remux e validação de mídia das gravações de videochamada (protocolo v2).
 *
 * Conforme o diagnóstico de 14/10/2026: a montagem dos chunks produz um WebM
 * concatenado cuja timeline precisa ser normalizada por remux (FFmpeg) para que
 * player, seek e transcrição vejam a reunião inteira — não só o trecho descrito
 * pelo header do primeiro pedaço. A validação exige áudio, vídeo, duração
 * positiva e ausência dos erros de integridade conhecidos; a saída de
 * diagnóstico do FFmpeg é capturada corretamente (stdout+stderr).
 *
 * Divergência consciente do documento: aqui o remux NÃO é condição para existir
 * a gravação. Quando o FFmpeg está ausente, a aplicação ainda entrega o arquivo
 * montado com o Duration injetado pelo parser EBML (RecordingMedia::injectDuration
 * fica a cargo do controller, que já possui injectWebmDuration). Isso evita que a
 * ausência temporária de FFmpeg derrube toda a gravação em produção. Com FFmpeg
 * disponível (caso dos ambientes atuais), o remux é sempre aplicado.
 *
 * A parte PURA (parsing da duração, deteção de erro na saída do ffmpeg, decisão
 * de validade) é estática e testável sem binário. A parte que executa o processo
 * usa as mesmas regras de localização do binário já adotadas pelo controller.
 */
class RecordingMedia
{
    /**
     * Padrões de erro de integridade do FFmpeg que invalidam o remux.
     * Rejeitamos a publicação quando a saída contém qualquer um deles.
     */
    public const INTEGRITY_ERROR_PATTERNS = [
        'Invalid data found when processing input',
        'could not find corrupt',
        'Error splitting the input into NAL units',
        'Truncating packet of size',
        'EBML header parsing failed',
        'invalid as first byte of an EBML number',
    ];

    // =====================================================================
    // Parte PURA (sem processo) — testável por unidade
    // =====================================================================

    /**
     * Extrai a duração (segundos) da saída de diagnóstico do FFmpeg/ffprobe.
     * Procura "Duration: HH:MM:SS.ss". Retorna 0.0 quando não encontra.
     */
    public static function parseDurationSeconds(string $ffmpegOutput): float
    {
        if (preg_match('/Duration:\s*(\d+):(\d+):(\d+\.?\d*)/', $ffmpegOutput, $m)) {
            return ((int)$m[1]) * 3600 + ((int)$m[2]) * 60 + (float)$m[3];
        }
        return 0.0;
    }

    /** A saída do FFmpeg indica stream de vídeo? */
    public static function hasVideoStream(string $ffmpegOutput): bool
    {
        return (bool) preg_match('/Stream #\d+:\d+.*: Video:/', $ffmpegOutput);
    }

    /** A saída do FFmpeg indica stream de áudio? */
    public static function hasAudioStream(string $ffmpegOutput): bool
    {
        return (bool) preg_match('/Stream #\d+:\d+.*: Audio:/', $ffmpegOutput);
    }

    /** A saída contém algum erro de integridade conhecido? */
    public static function hasIntegrityError(string $ffmpegOutput): bool
    {
        foreach (self::INTEGRITY_ERROR_PATTERNS as $p) {
            if (stripos($ffmpegOutput, $p) !== false) return true;
        }
        return false;
    }

    /**
     * A mídia é válida a partir da saída de diagnóstico do FFmpeg?
     * Exige: duração positiva, vídeo, áudio e ausência de erro de integridade.
     *
     * @param bool $requireAudio quando false, não exige stream de áudio (ex.: só tela)
     */
    public static function isValidFromOutput(string $probeOutput, bool $requireAudio = true): bool
    {
        if (self::hasIntegrityError($probeOutput)) return false;
        if (self::parseDurationSeconds($probeOutput) <= 0) return false;
        if (!self::hasVideoStream($probeOutput)) return false;
        if ($requireAudio && !self::hasAudioStream($probeOutput)) return false;
        return true;
    }

    // =====================================================================
    // Parte com processo (executa FFmpeg) — usada pelo controller
    // =====================================================================

    /**
     * Localiza o binário do FFmpeg (Config ffmpeg_path ou PATH). Null se não há
     * função de shell habilitada ou o binário não é encontrado. Espelha a lógica
     * de VideocallController::ffmpegBin para manter uma única fonte de verdade.
     */
    public static function ffmpegBin(): ?string
    {
        if (!self::shellEnabled()) return null;
        if (class_exists('Config')) {
            $cfg = trim((string) Config::get('ffmpeg_path'));
            if ($cfg !== '' && @is_file($cfg)) return $cfg;
        }
        $probe = self::runShell('ffmpeg -version');
        if ($probe !== null && stripos($probe, 'ffmpeg version') !== false) return 'ffmpeg';
        return null;
    }

    public static function shellEnabled(): bool
    {
        return self::fnEnabled('shell_exec') || self::fnEnabled('exec') || self::fnEnabled('proc_open');
    }

    private static function fnEnabled(string $fn): bool
    {
        if (!function_exists($fn)) return false;
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        return !in_array($fn, $disabled, true);
    }

    /** Executa um comando e devolve stdout+stderr, ou null se não houver shell. */
    public static function runShell(string $cmd): ?string
    {
        if (self::fnEnabled('shell_exec')) {
            $out = @shell_exec($cmd . ' 2>&1');
            return $out === null ? null : (string) $out;
        }
        if (self::fnEnabled('exec')) {
            $lines = [];
            @exec($cmd . ' 2>&1', $lines);
            return implode("\n", $lines);
        }
        return null;
    }

    /** Lê o diagnóstico do FFmpeg para um arquivo (equivale a `ffmpeg -i file`). */
    public static function probe(string $ffmpeg, string $file): ?string
    {
        return self::runShell(escapeshellarg($ffmpeg) . ' -i ' . escapeshellarg($file));
    }

    /**
     * Remuxa $src para um WebM com timeline normalizada em $dest (copy de codecs,
     * sem reencode). Retorna a duração em segundos quando o remux produz um
     * arquivo VÁLIDO (passa em isValidFromOutput); retorna null quando o FFmpeg
     * não está disponível ou o resultado não é válido — nesse caso o controller
     * mantém o arquivo montado e aplica o fallback de Duration (parser EBML).
     *
     * NÃO publica um vídeo inválido como sucesso: se a validação falhar, remove
     * o $dest remuxado e devolve null.
     *
     * @param bool $requireAudio exige stream de áudio para considerar válido
     */
    public static function remux(string $src, string $dest, bool $requireAudio = true): ?float
    {
        $ffmpeg = self::ffmpegBin();
        if ($ffmpeg === null) return null;
        if (!is_file($src) || filesize($src) < 1) return null;

        $cmd = escapeshellarg($ffmpeg) . ' -y -fflags +genpts -i ' . escapeshellarg($src)
            . ' -c copy -map 0 ' . escapeshellarg($dest);
        $out = self::runShell($cmd);
        if ($out === null || !is_file($dest) || filesize($dest) < 1) {
            if (is_file($dest)) @unlink($dest);
            return null;
        }

        // Confere o RESULTADO (não a saída do comando de remux, que pode ser
        // ruidosa): lê o diagnóstico do arquivo gerado.
        $probe = self::probe($ffmpeg, $dest);
        if ($probe === null || !self::isValidFromOutput($probe, $requireAudio)) {
            @unlink($dest);
            return null;
        }
        return self::parseDurationSeconds($probe);
    }
}
