<?php

/**
 * Leitor de e-mails via IMAP usando a extensão php-imap.
 * Conecta a uma conta e busca/le mensagens da caixa de entrada.
 */
class ImapReader
{
    private $connection = null;
    private $host;
    private $port;
    private $encryption;
    private $username;
    private $password;

    public function __construct($account)
    {
        $this->host = $account['imap_host'];
        $this->port = (int)($account['imap_port'] ?? 993);
        $this->encryption = $account['imap_encryption'] ?? 'ssl';
        $this->username = $account['smtp_username'];
        $this->password = EmailAccount::decryptPassword($account['smtp_password']);
    }

    /**
     * Conecta ao servidor IMAP.
     * @return bool|string true em sucesso, string de erro em falha
     */
    public function connect()
    {
        if (!function_exists('imap_open')) {
            return 'Extensão PHP IMAP não está instalada no servidor.';
        }

        $mailbox = $this->buildMailboxString();

        // Suprimir warnings do imap_open
        imap_timeout(IMAP_OPENTIMEOUT, 10);
        $this->connection = @imap_open($mailbox, $this->username, $this->password, 0, 1);

        if (!$this->connection) {
            $error = imap_last_error();
            return 'Falha ao conectar IMAP: ' . ($error ?: 'Erro desconhecido');
        }

        return true;
    }

    /**
     * Busca e-mails da caixa de entrada (INBOX).
     * @param int $limit Quantidade máxima de e-mails
     * @param int $offset Offset (para paginação simples)
     * @param string|null $search Critério de busca IMAP (ex: 'FROM "fulano"')
     * @return array Lista de e-mails com metadados
     */
    public function fetchMessages($limit = 30, $offset = 0, $search = null)
    {
        if (!$this->connection) return [];

        // Buscar UIDs
        if ($search) {
            $uids = imap_search($this->connection, $search, SE_UID);
        } else {
            $uids = imap_search($this->connection, 'ALL', SE_UID);
        }

        if (!$uids) return [];

        // Ordenar do mais recente para o mais antigo
        rsort($uids);

        // Aplicar paginação
        $uids = array_slice($uids, $offset, $limit);

        $messages = [];
        foreach ($uids as $uid) {
            $header = @imap_fetchheader($this->connection, $uid, FT_UID);
            $headerInfo = @imap_headerinfo($this->connection, imap_msgno($this->connection, $uid));
            $overview = @imap_fetch_overview($this->connection, (string)$uid, FT_UID);

            if (!$headerInfo || empty($overview)) continue;

            $ov = $overview[0];
            $messages[] = [
                'uid' => $uid,
                'msgno' => $ov->msgno ?? null,
                'subject' => isset($ov->subject) ? $this->decodeMime($ov->subject) : '(Sem assunto)',
                'from' => isset($headerInfo->from[0]) ? $this->formatAddress($headerInfo->from[0]) : '—',
                'from_email' => isset($headerInfo->from[0]) ? ($headerInfo->from[0]->mailbox . '@' . $headerInfo->from[0]->host) : '',
                'to' => isset($headerInfo->to[0]) ? $this->formatAddress($headerInfo->to[0]) : '—',
                'date' => isset($ov->date) ? date('Y-m-d H:i:s', strtotime($ov->date)) : null,
                'seen' => isset($ov->seen) && $ov->seen,
                'flagged' => isset($ov->flagged) && $ov->flagged,
                'size' => $ov->size ?? 0,
                'has_attachments' => $this->hasAttachments($uid),
            ];
        }

        return $messages;
    }

    /**
     * Busca mensagens recebidas de um remetente específico (por endereço).
     * @return array Lista resumida (uid, subject, from, from_email, date)
     */
    public function searchFrom($email, $limit = 50)
    {
        if (!$this->connection) return [];

        $uids = imap_search($this->connection, 'FROM "' . addslashes($email) . '"', SE_UID);
        if (!$uids) return [];
        rsort($uids);
        $uids = array_slice($uids, 0, $limit);

        $messages = [];
        foreach ($uids as $uid) {
            $headerInfo = @imap_headerinfo($this->connection, imap_msgno($this->connection, $uid));
            $overview = @imap_fetch_overview($this->connection, (string)$uid, FT_UID);
            if (!$headerInfo || empty($overview)) continue;
            $ov = $overview[0];
            $messages[] = [
                'uid' => $uid,
                'subject' => isset($ov->subject) ? $this->decodeMime($ov->subject) : '(Sem assunto)',
                'from' => isset($headerInfo->from[0]) ? $this->formatAddress($headerInfo->from[0]) : '—',
                'from_email' => isset($headerInfo->from[0]) ? ($headerInfo->from[0]->mailbox . '@' . $headerInfo->from[0]->host) : '',
                'date' => isset($ov->date) ? date('Y-m-d H:i:s', strtotime($ov->date)) : null,
            ];
        }
        return $messages;
    }

    /**
     * Retorna o total de e-mails na caixa.
     */
    public function getTotal($search = null)
    {
        if (!$this->connection) return 0;

        if ($search) {
            $uids = imap_search($this->connection, $search, SE_UID);
            return $uids ? count($uids) : 0;
        }

        $check = imap_check($this->connection);
        return $check ? $check->Nmsgs : 0;
    }

    /**
     * Lê o corpo completo de um e-mail por UID.
     * @return array Com subject, from, to, cc, date, body_html, body_text, attachments
     */
    public function readMessage($uid)
    {
        if (!$this->connection) return null;

        $msgno = imap_msgno($this->connection, $uid);
        if (!$msgno) return null;

        $headerInfo = imap_headerinfo($this->connection, $msgno);
        $structure = imap_fetchstructure($this->connection, $uid, FT_UID);

        $result = [
            'uid' => $uid,
            'subject' => isset($headerInfo->subject) ? $this->decodeMime($headerInfo->subject) : '(Sem assunto)',
            'from' => isset($headerInfo->from[0]) ? $this->formatAddress($headerInfo->from[0]) : '—',
            'from_email' => isset($headerInfo->from[0]) ? ($headerInfo->from[0]->mailbox . '@' . $headerInfo->from[0]->host) : '',
            'to' => $this->formatAddressList($headerInfo->to ?? []),
            'cc' => $this->formatAddressList($headerInfo->cc ?? []),
            'date' => isset($headerInfo->date) ? date('Y-m-d H:i:s', strtotime($headerInfo->date)) : null,
            'body_html' => '',
            'body_text' => '',
            'attachments' => [],
        ];

        // Extrair corpo e anexos
        $this->extractParts($structure, $uid, $result);

        // Marcar como lido
        imap_setflag_full($this->connection, (string)$uid, '\\Seen', ST_UID);

        return $result;
    }

    /**
     * Exclui um e-mail (move para a lixeira quando possível, senão marca \Deleted e expunge).
     * @return bool
     */
    public function deleteMessage($uid)
    {
        if (!$this->connection) return false;

        // Tenta mover para a pasta de lixeira mais comum
        $trash = $this->findMailbox(['Trash', 'Lixeira', 'Deleted', 'Deleted Items', 'Papeleira']);
        if ($trash) {
            $moved = @imap_mail_move($this->connection, (string)$uid, $trash, CP_UID);
            if ($moved) {
                imap_expunge($this->connection);
                return true;
            }
        }

        // Fallback: marca como apagado e expurga
        $ok = @imap_delete($this->connection, (string)$uid, FT_UID);
        if ($ok) imap_expunge($this->connection);
        return (bool)$ok;
    }

    /**
     * Arquiva um e-mail movendo-o para a pasta de arquivo.
     * @return bool|string true, ou string de erro amigável.
     */
    public function archiveMessage($uid)
    {
        if (!$this->connection) return false;

        $archive = $this->findMailbox(['Archive', 'Arquivo', 'Arquivados', 'All Mail', '[Gmail]/All Mail', 'Todos']);
        if (!$archive) {
            // Cria uma pasta "Archive" se não existir
            $ref = '{' . $this->host . ':' . $this->port . '}';
            if (@imap_createmailbox($this->connection, imap_utf7_encode($ref . 'Archive'))) {
                @imap_subscribe($this->connection, imap_utf7_encode($ref . 'Archive'));
                $archive = 'Archive';
            }
        }
        if (!$archive) return 'Não foi possível localizar/criar a pasta de arquivo nesta conta.';

        $moved = @imap_mail_move($this->connection, (string)$uid, $archive, CP_UID);
        if ($moved) {
            imap_expunge($this->connection);
            return true;
        }
        return 'Falha ao arquivar o e-mail.';
    }

    /**
     * Procura, entre as caixas existentes, a primeira cujo nome bata com a lista de candidatos.
     * @return string|null nome da pasta (sem o prefixo do servidor) ou null
     */
    private function findMailbox(array $candidates)
    {
        $ref = '{' . $this->host . ':' . $this->port . '}';
        $list = @imap_list($this->connection, $ref, '*');
        if (!$list) return null;

        foreach ($list as $box) {
            $name = str_replace($ref, '', $box);
            $decoded = imap_utf7_decode($name);
            foreach ($candidates as $cand) {
                if (strcasecmp($decoded, $cand) === 0 || strcasecmp($name, $cand) === 0) {
                    return $name;
                }
            }
        }
        return null;
    }

    /**
     * Fecha a conexão IMAP.
     */
    public function disconnect()
    {
        if ($this->connection) {
            imap_close($this->connection);
            $this->connection = null;
        }
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    // --- Métodos privados ---

    private function buildMailboxString($folder = 'INBOX')
    {
        $flags = '';
        if ($this->encryption === 'ssl') {
            $flags = '/imap/ssl/novalidate-cert';
        } elseif ($this->encryption === 'tls') {
            $flags = '/imap/tls/novalidate-cert';
        } else {
            $flags = '/imap/notls';
        }

        return '{' . $this->host . ':' . $this->port . $flags . '}' . $folder;
    }

    private function extractParts($structure, $uid, &$result, $partNumber = '')
    {
        // Mensagem simples (não multipart)
        if (empty($structure->parts)) {
            $body = $this->fetchPart($uid, $partNumber ?: '1');
            $body = $this->decodePart($body, $structure->encoding ?? 0);
            $body = $this->convertCharset($body, $structure);

            if ($structure->subtype === 'HTML') {
                $result['body_html'] = $body;
            } else {
                $result['body_text'] = $body;
            }
            return;
        }

        // Multipart
        foreach ($structure->parts as $i => $part) {
            $num = $partNumber ? ($partNumber . '.' . ($i + 1)) : (string)($i + 1);

            // Anexo
            $disposition = '';
            if (isset($part->disposition)) {
                $disposition = strtolower($part->disposition);
            }

            $filename = $this->getPartFilename($part);

            if ($disposition === 'attachment' || ($filename && $part->type !== 0)) {
                $result['attachments'][] = [
                    'filename' => $filename ?: ('attachment_' . ($i + 1)),
                    'size' => $part->bytes ?? 0,
                    'part' => $num,
                    'type' => $part->type ?? 0,
                    'subtype' => $part->subtype ?? '',
                ];
            } elseif ($part->type === 0) {
                // Texto (plain ou html)
                $body = $this->fetchPart($uid, $num);
                $body = $this->decodePart($body, $part->encoding ?? 0);
                $body = $this->convertCharset($body, $part);

                if (strtoupper($part->subtype ?? '') === 'HTML') {
                    $result['body_html'] .= $body;
                } else {
                    $result['body_text'] .= $body;
                }
            } elseif ($part->type === 1) {
                // Multipart aninhado
                $this->extractParts($part, $uid, $result, $num);
            }
        }
    }

    private function fetchPart($uid, $partNumber)
    {
        return imap_fetchbody($this->connection, $uid, $partNumber, FT_UID);
    }

    private function decodePart($data, $encoding)
    {
        switch ($encoding) {
            case 0: // 7BIT
            case 1: // 8BIT
                return $data;
            case 2: // BINARY
                return $data;
            case 3: // BASE64
                return base64_decode($data);
            case 4: // QUOTED-PRINTABLE
                return quoted_printable_decode($data);
            default:
                return $data;
        }
    }

    private function convertCharset($text, $part)
    {
        $charset = 'UTF-8';
        if (!empty($part->parameters)) {
            foreach ($part->parameters as $param) {
                if (strtolower($param->attribute) === 'charset') {
                    $charset = strtoupper($param->value);
                    break;
                }
            }
        }

        if ($charset && $charset !== 'UTF-8' && $charset !== 'US-ASCII') {
            $converted = @iconv($charset, 'UTF-8//IGNORE', $text);
            if ($converted !== false) return $converted;
        }

        return $text;
    }

    private function getPartFilename($part)
    {
        $filename = null;

        // Verifica dparameters (Content-Disposition)
        if (!empty($part->dparameters)) {
            foreach ($part->dparameters as $param) {
                if (strtolower($param->attribute) === 'filename') {
                    $filename = $this->decodeMime($param->value);
                    break;
                }
            }
        }

        // Verifica parameters (Content-Type)
        if (!$filename && !empty($part->parameters)) {
            foreach ($part->parameters as $param) {
                if (strtolower($param->attribute) === 'name') {
                    $filename = $this->decodeMime($param->value);
                    break;
                }
            }
        }

        return $filename;
    }

    private function hasAttachments($uid)
    {
        $structure = @imap_fetchstructure($this->connection, $uid, FT_UID);
        if (!$structure || empty($structure->parts)) return false;

        foreach ($structure->parts as $part) {
            if (isset($part->disposition) && strtolower($part->disposition) === 'attachment') {
                return true;
            }
            if ($this->getPartFilename($part) && $part->type !== 0) {
                return true;
            }
        }
        return false;
    }

    private function decodeMime($text)
    {
        $elements = imap_mime_header_decode($text);
        $decoded = '';
        foreach ($elements as $el) {
            $charset = ($el->charset && $el->charset !== 'default') ? $el->charset : 'UTF-8';
            $part = @iconv($charset, 'UTF-8//IGNORE', $el->text);
            $decoded .= ($part !== false) ? $part : $el->text;
        }
        return $decoded;
    }

    private function formatAddress($addr)
    {
        $name = isset($addr->personal) ? $this->decodeMime($addr->personal) : '';
        $email = ($addr->mailbox ?? '') . '@' . ($addr->host ?? '');
        return $name ? "{$name} <{$email}>" : $email;
    }

    private function formatAddressList($list)
    {
        if (empty($list)) return '';
        $addresses = [];
        foreach ($list as $addr) {
            $addresses[] = $this->formatAddress($addr);
        }
        return implode(', ', $addresses);
    }
}
