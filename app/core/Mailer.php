<?php

class Mailer
{
    /**
     * Envia email usando SMTP configurado no sistema.
     * Usa fsockopen para conexão SMTP direta (sem dependências externas).
     */
    public static function send($to, $subject, $htmlBody)
    {
        $host = Config::get('smtp_host');
        $port = Config::get('smtp_port', '587');
        $user = Config::get('smtp_username');
        $pass = Config::get('smtp_password');
        $encryption = Config::get('smtp_encryption', 'tls');
        $fromName = Config::get('smtp_from_name', 'ON Solutions Helpdesk');
        $fromEmail = Config::get('smtp_from_email');

        if (empty($host) || empty($fromEmail)) {
            return false;
        }

        // Tentar enviar via SMTP nativo com fsockopen
        try {
            $prefix = ($encryption === 'ssl') ? 'ssl://' : '';
            $socket = @fsockopen($prefix . $host, (int)$port, $errno, $errstr, 10);

            if (!$socket) {
                // Fallback para mail() nativo
                return self::sendViaMail($to, $subject, $htmlBody, $fromName, $fromEmail);
            }

            $response = fgets($socket, 512);
            if (substr($response, 0, 3) !== '220') {
                fclose($socket);
                return self::sendViaMail($to, $subject, $htmlBody, $fromName, $fromEmail);
            }

            // EHLO
            fwrite($socket, "EHLO localhost\r\n");
            self::readResponse($socket);

            // STARTTLS se necessário
            if ($encryption === 'tls') {
                fwrite($socket, "STARTTLS\r\n");
                self::readResponse($socket);
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                fwrite($socket, "EHLO localhost\r\n");
                self::readResponse($socket);
            }

            // AUTH LOGIN
            if ($user && $pass) {
                fwrite($socket, "AUTH LOGIN\r\n");
                self::readResponse($socket);
                fwrite($socket, base64_encode($user) . "\r\n");
                self::readResponse($socket);
                fwrite($socket, base64_encode($pass) . "\r\n");
                $authResponse = self::readResponse($socket);
                if (substr($authResponse, 0, 3) !== '235') {
                    fclose($socket);
                    return false;
                }
            }

            // MAIL FROM
            fwrite($socket, "MAIL FROM:<{$fromEmail}>\r\n");
            self::readResponse($socket);

            // RCPT TO
            fwrite($socket, "RCPT TO:<{$to}>\r\n");
            self::readResponse($socket);

            // DATA
            fwrite($socket, "DATA\r\n");
            self::readResponse($socket);

            // Headers e body
            $headers = "From: {$fromName} <{$fromEmail}>\r\n";
            $headers .= "To: {$to}\r\n";
            $headers .= "Subject: {$subject}\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "Date: " . date('r') . "\r\n";
            $headers .= "\r\n";
            $headers .= $htmlBody . "\r\n";

            fwrite($socket, $headers . ".\r\n");
            $dataResponse = self::readResponse($socket);

            fwrite($socket, "QUIT\r\n");
            fclose($socket);

            return substr($dataResponse, 0, 3) === '250';

        } catch (Exception $e) {
            return self::sendViaMail($to, $subject, $htmlBody, $fromName, $fromEmail);
        }
    }

    private static function readResponse($socket)
    {
        $response = '';
        while ($line = fgets($socket, 512)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') break;
        }
        return $response;
    }

    private static function sendViaMail($to, $subject, $htmlBody, $fromName, $fromEmail)
    {
        $headers = implode("\r\n", [
            "MIME-Version: 1.0",
            "Content-Type: text/html; charset=UTF-8",
            "From: {$fromName} <{$fromEmail}>",
            "Reply-To: {$fromEmail}",
        ]);
        return @mail($to, $subject, $htmlBody, $headers);
    }

    /**
     * Gera o HTML padrão de email do sistema
     */
    public static function template($title, $bodyContent)
    {
        return "
        <html>
        <body style='font-family:Inter,Arial,sans-serif;background:#f5f7fa;padding:20px;margin:0;'>
            <div style='max-width:560px;margin:0 auto;background:#fff;border-radius:12px;padding:30px;box-shadow:0 2px 10px rgba(0,0,0,0.05);'>
                <div style='text-align:center;margin-bottom:20px;'>
                    <span style='color:#00BFA6;font-weight:700;font-size:1.4rem;'>ON</span>
                    <span style='font-weight:300;font-size:1.1rem;color:#333;'> Solutions</span>
                    <div style='font-size:0.8rem;color:#999;margin-top:4px;'>Helpdesk</div>
                </div>
                <h2 style='color:#333;font-size:1.2rem;margin-bottom:15px;'>{$title}</h2>
                <div style='color:#555;line-height:1.7;font-size:0.9rem;'>
                    {$bodyContent}
                </div>
                <hr style='border:none;border-top:1px solid #eee;margin:25px 0 15px;'>
                <p style='color:#aaa;font-size:0.75rem;text-align:center;margin:0;'>ON Solutions Helpdesk &copy; " . date('Y') . "</p>
            </div>
        </body>
        </html>";
    }
}
