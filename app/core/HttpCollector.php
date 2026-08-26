<?php

/**
 * Camada genérica de coleta HTTP (GET) para captação de leads.
 *
 * - timeout configurável (default 20s)
 * - segue redirects
 * - User-Agent de navegador real + Accept-Language pt-BR
 * - retries com backoff
 * - rate limit por host (~1 req a cada 1,5s) — NÃO opcional
 * - retorna estrutura padronizada com log estruturado
 *
 * Sem navegador. Apenas cURL.
 */
class HttpCollector
{
    private $timeout;
    private $maxRetries;
    private $rateLimitMs;
    private static $lastRequestByHost = [];

    public function __construct($options = [])
    {
        $this->timeout = $options['timeout'] ?? 20;
        $this->maxRetries = $options['max_retries'] ?? 2;
        $this->rateLimitMs = $options['rate_limit_ms'] ?? 1500;
    }

    /**
     * Executa um GET com rate limit, retries e headers de navegador.
     * @return array {status, contentType, finalUrl, bytes, durationMs, body, error}
     */
    public function get($url)
    {
        $host = parse_url($url, PHP_URL_HOST) ?: 'default';
        $this->applyRateLimit($host);

        $attempt = 0;
        $lastError = null;

        while ($attempt <= $this->maxRetries) {
            $attempt++;
            $result = $this->doRequest($url);

            // Sucesso (2xx) retorna imediatamente
            if ($result['status'] >= 200 && $result['status'] < 300 && $result['error'] === null) {
                $this->log($url, $result, $attempt);
                return $result;
            }

            // 429/5xx: vale tentar de novo com backoff. 4xx (exceto 429): não insiste.
            $status = $result['status'];
            $retryable = ($status === 0 || $status === 429 || $status >= 500);
            $lastError = $result['error'] ?: ('HTTP ' . $status);

            if (!$retryable || $attempt > $this->maxRetries) {
                $this->log($url, $result, $attempt);
                return $result;
            }

            // Backoff exponencial simples (1s, 2s, 4s...)
            $backoff = (int) (pow(2, $attempt - 1) * 1000);
            usleep($backoff * 1000);
            $this->applyRateLimit($host);
        }

        return [
            'status' => 0, 'contentType' => null, 'finalUrl' => $url,
            'bytes' => 0, 'durationMs' => 0, 'body' => '', 'error' => $lastError,
        ];
    }

    private function doRequest($url)
    {
        $start = microtime(true);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_ENCODING => '', // aceita gzip/deflate
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: pt-BR,pt;q=0.9,en;q=0.8',
                'Cache-Control: no-cache',
            ],
        ]);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $curlErr = curl_error($ch);
        curl_close($ch);

        $durationMs = (int) round((microtime(true) - $start) * 1000);

        return [
            'status' => $status,
            'contentType' => $contentType,
            'finalUrl' => $finalUrl ?: $url,
            'bytes' => $body === false ? 0 : strlen($body),
            'durationMs' => $durationMs,
            'body' => $body === false ? '' : $body,
            'error' => ($body === false || $status === 0) ? ($curlErr ?: 'Falha de conexão') : null,
        ];
    }

    /** Garante o intervalo mínimo entre requisições ao mesmo host. */
    private function applyRateLimit($host)
    {
        $last = self::$lastRequestByHost[$host] ?? 0;
        $elapsedMs = (microtime(true) * 1000) - $last;
        if ($last > 0 && $elapsedMs < $this->rateLimitMs) {
            usleep((int) (($this->rateLimitMs - $elapsedMs) * 1000));
        }
        self::$lastRequestByHost[$host] = microtime(true) * 1000;
    }

    private function log($url, $result, $attempt)
    {
        Logger::info('HttpCollector GET', [
            'url' => $url,
            'status' => $result['status'],
            'bytes' => $result['bytes'],
            'ms' => $result['durationMs'],
            'attempt' => $attempt,
            'error' => $result['error'],
        ]);
    }
}
