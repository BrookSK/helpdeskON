<?php

/**
 * Logger centralizado.
 *
 * Escreve em dois destinos:
 *  1) error_log() do PHP — capturado pelo painel de logs do servidor (Plesk).
 *  2) Arquivo próprio em logs/app-error.log — para debug detalhado.
 *
 * Nunca registrar segredos (tokens, credenciais, Authorization headers).
 */
class Logger
{
    /** Caminho do arquivo de log da aplicação. */
    private static function file()
    {
        $dir = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)) . '/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir . '/app-error.log';
    }

    /**
     * Registra uma mensagem de log.
     * @param string $level ERROR|WARNING|INFO
     * @param string $message
     * @param array  $context dados extras (serão sanitizados)
     */
    public static function log($level, $message, array $context = [])
    {
        $context = self::sanitize($context);
        $line = sprintf(
            '[%s] [%s] %s%s',
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $message,
            !empty($context) ? ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ''
        );

        // 1) error_log do PHP (aparece no painel de logs do servidor)
        error_log($line);

        // 2) arquivo próprio da aplicação
        @file_put_contents(self::file(), $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    public static function error($message, array $context = [])
    {
        self::log('ERROR', $message, $context);
    }

    public static function warning($message, array $context = [])
    {
        self::log('WARNING', $message, $context);
    }

    public static function info($message, array $context = [])
    {
        self::log('INFO', $message, $context);
    }

    /**
     * Registra uma exceção capturada.
     */
    public static function exception(\Throwable $e, array $context = [])
    {
        self::log('ERROR', get_class($e) . ': ' . $e->getMessage(), array_merge($context, [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]));
    }

    /**
     * Remove chaves sensíveis do contexto antes de gravar.
     */
    private static function sanitize(array $context)
    {
        $sensitive = ['password', 'secret', 'token', 'access_token', 'refresh_token',
                      'authorization', 'client_credential', 'client_secret', 'api_key',
                      'credential', 'code_verifier'];
        foreach ($context as $k => $v) {
            $lk = strtolower((string) $k);
            foreach ($sensitive as $s) {
                if (strpos($lk, $s) !== false) {
                    $context[$k] = '***';
                    break;
                }
            }
            if (is_array($v)) $context[$k] = self::sanitize($v);
        }
        return $context;
    }

    /**
     * Registra os handlers globais de erro/exceção/shutdown.
     * Deve ser chamado uma vez no bootstrap.
     */
    public static function register()
    {
        // Direciona os erros do PHP para o error_log (capturado pelo painel)
        @ini_set('log_errors', '1');
        @ini_set('display_errors', '0'); // não expor erros ao usuário final

        set_error_handler(function ($severity, $message, $file, $line) {
            // Respeita o error_reporting atual (ex.: @ suprimido)
            if (!(error_reporting() & $severity)) {
                return false;
            }
            Logger::log('ERROR', $message, ['file' => $file, 'line' => $line, 'severity' => $severity]);
            return false; // deixa o handler padrão do PHP também atuar
        });

        set_exception_handler(function (\Throwable $e) {
            Logger::exception($e);
            http_response_code(500);
            // Resposta genérica — sem detalhes internos
            if (!headers_sent()) {
                header('Content-Type: text/plain; charset=utf-8');
            }
            echo 'Ocorreu um erro interno. A equipe foi notificada.';
        });

        register_shutdown_function(function () {
            $err = error_get_last();
            if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                Logger::log('ERROR', 'FATAL: ' . $err['message'], ['file' => $err['file'], 'line' => $err['line']]);
            }
        });
    }
}
