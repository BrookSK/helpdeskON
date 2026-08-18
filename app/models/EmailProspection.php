<?php

class EmailProspection
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById($id)
    {
        return $this->db->fetch(
            "SELECT ep.*, u.name as user_name, ea.email as account_email, ea.display_name as account_name,
                    wc.contact_name as lead_name, wc.phone as lead_phone
             FROM email_prospections ep
             LEFT JOIN users u ON ep.user_id = u.id
             LEFT JOIN email_accounts ea ON ep.email_account_id = ea.id
             LEFT JOIN whatsapp_contacts wc ON ep.contact_id = wc.id
             WHERE ep.id = ?",
            [$id]
        );
    }

    /**
     * Histórico de envios com filtros.
     */
    public function getAll($filters = [])
    {
        $sql = "SELECT ep.*, u.name as user_name, ea.email as account_email,
                       wc.contact_name as lead_name
                FROM email_prospections ep
                LEFT JOIN users u ON ep.user_id = u.id
                LEFT JOIN email_accounts ea ON ep.email_account_id = ea.id
                LEFT JOIN whatsapp_contacts wc ON ep.contact_id = wc.id
                WHERE 1=1";
        $params = [];

        if (!empty($filters['user_id'])) {
            $sql .= " AND ep.user_id = ?";
            $params[] = $filters['user_id'];
        }
        if (!empty($filters['email_account_id'])) {
            $sql .= " AND ep.email_account_id = ?";
            $params[] = $filters['email_account_id'];
        }
        if (!empty($filters['contact_id'])) {
            $sql .= " AND ep.contact_id = ?";
            $params[] = $filters['contact_id'];
        }
        if (!empty($filters['status'])) {
            $sql .= " AND ep.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['start_date'])) {
            $sql .= " AND ep.sent_at >= ?";
            $params[] = $filters['start_date'] . ' 00:00:00';
        }
        if (!empty($filters['end_date'])) {
            $sql .= " AND ep.sent_at <= ?";
            $params[] = $filters['end_date'] . ' 23:59:59';
        }

        $sql .= " ORDER BY ep.created_at DESC";
        return $this->db->fetchAll($sql, $params);
    }

    public function create($data)
    {
        return $this->db->insert('email_prospections', $data);
    }

    public function update($id, $data)
    {
        return $this->db->update('email_prospections', $data, 'id = ?', [$id]);
    }

    public function delete($id)
    {
        return $this->db->delete('email_prospections', 'id = ?', [$id]);
    }

    // --- Métricas para o Dashboard de Performance ---

    /**
     * Contagem de e-mails enviados por usuário em um período.
     * Retorna: [user_id => ['sent' => X, 'failed' => Y, 'total' => Z, 'unique_contacts' => W], ...]
     */
    public function getStatsByUser($startDate = null, $endDate = null, $userId = null)
    {
        $sql = "SELECT ep.user_id,
                       SUM(CASE WHEN ep.status = 'sent' THEN 1 ELSE 0 END) AS sent,
                       SUM(CASE WHEN ep.status = 'failed' THEN 1 ELSE 0 END) AS failed,
                       COUNT(*) AS total,
                       COUNT(DISTINCT ep.contact_id) AS unique_contacts
                FROM email_prospections ep
                WHERE ep.status IN ('sent','failed')";
        $params = [];

        if ($startDate) {
            $sql .= " AND ep.sent_at >= ?";
            $params[] = $startDate . ' 00:00:00';
        }
        if ($endDate) {
            $sql .= " AND ep.sent_at <= ?";
            $params[] = $endDate . ' 23:59:59';
        }
        if ($userId) {
            $sql .= " AND ep.user_id = ?";
            $params[] = $userId;
        }

        $sql .= " GROUP BY ep.user_id";
        $rows = $this->db->fetchAll($sql, $params);

        $result = [];
        foreach ($rows as $r) {
            $result[$r['user_id']] = [
                'sent' => (int)$r['sent'],
                'failed' => (int)$r['failed'],
                'total' => (int)$r['total'],
                'unique_contacts' => (int)$r['unique_contacts'],
            ];
        }
        return $result;
    }

    /**
     * Série mensal de e-mails enviados (últimos N meses), opcionalmente por usuário.
     */
    public function getMonthlyTrend($months = 6, $userId = null)
    {
        $result = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $ym = date('Y-m', strtotime("-{$i} months"));
            $sql = "SELECT COUNT(*) as total,
                           SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent
                    FROM email_prospections
                    WHERE DATE_FORMAT(sent_at, '%Y-%m') = ?";
            $params = [$ym];
            if ($userId) {
                $sql .= " AND user_id = ?";
                $params[] = $userId;
            }
            $row = $this->db->fetch($sql, $params);
            $result[] = [
                'month' => $ym,
                'label' => date('m/Y', strtotime($ym . '-01')),
                'sent' => (int)($row['sent'] ?? 0),
                'total' => (int)($row['total'] ?? 0),
            ];
        }
        return $result;
    }

    /**
     * Envia o e-mail via SMTP usando os dados da conta.
     * Retorna true em caso de sucesso, ou string de erro.
     */
    public function sendEmail($account, $to, $subject, $htmlBody, $cc = null, $bcc = null, $attachments = [])
    {
        $host = $account['smtp_host'];
        $port = (int)$account['smtp_port'];
        $encryption = $account['smtp_encryption'];
        $user = $account['smtp_username'];
        $pass = EmailAccount::decryptPassword($account['smtp_password']);
        $fromName = $account['display_name'] ?: $account['email'];
        $fromEmail = $account['email'];

        try {
            $prefix = ($encryption === 'ssl') ? 'ssl://' : '';
            $socket = @fsockopen($prefix . $host, $port, $errno, $errstr, 10);

            if (!$socket) {
                return "Não foi possível conectar ao servidor SMTP: {$errstr}";
            }

            $response = fgets($socket, 512);
            if (substr($response, 0, 3) !== '220') {
                fclose($socket);
                return "Resposta inesperada do servidor: {$response}";
            }

            // EHLO
            fwrite($socket, "EHLO localhost\r\n");
            $this->readSmtpResponse($socket);

            // STARTTLS
            if ($encryption === 'tls') {
                fwrite($socket, "STARTTLS\r\n");
                $this->readSmtpResponse($socket);
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                fwrite($socket, "EHLO localhost\r\n");
                $this->readSmtpResponse($socket);
            }

            // AUTH LOGIN
            if ($user && $pass) {
                fwrite($socket, "AUTH LOGIN\r\n");
                $this->readSmtpResponse($socket);
                fwrite($socket, base64_encode($user) . "\r\n");
                $this->readSmtpResponse($socket);
                fwrite($socket, base64_encode($pass) . "\r\n");
                $authResponse = $this->readSmtpResponse($socket);
                if (substr($authResponse, 0, 3) !== '235') {
                    fclose($socket);
                    return "Falha na autenticação SMTP.";
                }
            }

            // MAIL FROM
            fwrite($socket, "MAIL FROM:<{$fromEmail}>\r\n");
            $this->readSmtpResponse($socket);

            // RCPT TO (destinatário principal)
            fwrite($socket, "RCPT TO:<{$to}>\r\n");
            $this->readSmtpResponse($socket);

            // RCPT TO (CC)
            $ccList = $cc ? array_filter(array_map('trim', explode(',', $cc))) : [];
            foreach ($ccList as $ccAddr) {
                if (filter_var($ccAddr, FILTER_VALIDATE_EMAIL)) {
                    fwrite($socket, "RCPT TO:<{$ccAddr}>\r\n");
                    $this->readSmtpResponse($socket);
                }
            }

            // RCPT TO (BCC)
            $bccList = $bcc ? array_filter(array_map('trim', explode(',', $bcc))) : [];
            foreach ($bccList as $bccAddr) {
                if (filter_var($bccAddr, FILTER_VALIDATE_EMAIL)) {
                    fwrite($socket, "RCPT TO:<{$bccAddr}>\r\n");
                    $this->readSmtpResponse($socket);
                }
            }

            // DATA
            fwrite($socket, "DATA\r\n");
            $this->readSmtpResponse($socket);

            // Construir headers e body
            $boundary = '----=_Part_' . md5(uniqid());
            $headers = "From: {$fromName} <{$fromEmail}>\r\n";
            $headers .= "To: {$to}\r\n";
            if (!empty($ccList)) {
                $headers .= "Cc: " . implode(', ', $ccList) . "\r\n";
            }
            $headers .= "Subject: {$subject}\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Date: " . date('r') . "\r\n";
            $headers .= "Message-ID: <" . uniqid('prosp_') . "@" . parse_url($host, PHP_URL_HOST) . ">\r\n";

            if (!empty($attachments)) {
                $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";
                $headers .= "\r\n";
                $headers .= "--{$boundary}\r\n";
                $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
                $headers .= "Content-Transfer-Encoding: base64\r\n";
                $headers .= "\r\n";
                $headers .= chunk_split(base64_encode($htmlBody)) . "\r\n";

                foreach ($attachments as $att) {
                    if (!file_exists($att['path'])) continue;
                    $headers .= "--{$boundary}\r\n";
                    $headers .= "Content-Type: application/octet-stream; name=\"" . basename($att['name'] ?? $att['path']) . "\"\r\n";
                    $headers .= "Content-Transfer-Encoding: base64\r\n";
                    $headers .= "Content-Disposition: attachment; filename=\"" . basename($att['name'] ?? $att['path']) . "\"\r\n";
                    $headers .= "\r\n";
                    $headers .= chunk_split(base64_encode(file_get_contents($att['path']))) . "\r\n";
                }
                $headers .= "--{$boundary}--\r\n";
            } else {
                $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
                $headers .= "\r\n";
                $headers .= $htmlBody . "\r\n";
            }

            fwrite($socket, $headers . ".\r\n");
            $dataResponse = $this->readSmtpResponse($socket);

            fwrite($socket, "QUIT\r\n");
            fclose($socket);

            if (substr($dataResponse, 0, 3) === '250') {
                return true;
            }
            return "Erro no envio: {$dataResponse}";

        } catch (\Throwable $e) {
            return "Exceção: " . $e->getMessage();
        }
    }

    private function readSmtpResponse($socket)
    {
        $response = '';
        while ($line = fgets($socket, 512)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') break;
        }
        return $response;
    }
}
