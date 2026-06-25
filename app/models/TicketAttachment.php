<?php

class TicketAttachment
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getByTicket($ticketId)
    {
        return $this->db->fetchAll(
            "SELECT a.*, u.name as user_name
             FROM ticket_attachments a
             LEFT JOIN users u ON a.user_id = u.id
             WHERE a.ticket_id = ?
             ORDER BY a.created_at DESC",
            [$ticketId]
        );
    }

    public function create($data)
    {
        return $this->db->insert('ticket_attachments', $data);
    }

    public function delete($id)
    {
        $attachment = $this->db->fetch("SELECT * FROM ticket_attachments WHERE id = ?", [$id]);
        if ($attachment) {
            $filePath = PUBLIC_PATH . '/' . $attachment['file_path'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            return $this->db->delete('ticket_attachments', 'id = ?', [$id]);
        }
        return false;
    }

    public function upload($file, $ticketId, $userId)
    {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        
        if (!in_array($file['type'], $allowedTypes)) {
            return ['error' => 'Tipo de arquivo não permitido.'];
        }

        $maxSize = 10 * 1024 * 1024; // 10MB
        if ($file['size'] > $maxSize) {
            return ['error' => 'Arquivo muito grande. Máximo: 10MB'];
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $fileName = uniqid('attach_') . '.' . $ext;
        $uploadDir = 'uploads/tickets/' . $ticketId;
        $fullDir = PUBLIC_PATH . '/' . $uploadDir;

        if (!is_dir($fullDir)) {
            mkdir($fullDir, 0755, true);
        }

        $filePath = $uploadDir . '/' . $fileName;
        if (move_uploaded_file($file['tmp_name'], PUBLIC_PATH . '/' . $filePath)) {
            $id = $this->create([
                'ticket_id' => $ticketId,
                'user_id' => $userId,
                'file_name' => $file['name'],
                'file_path' => $filePath,
                'file_type' => $file['type'],
                'file_size' => $file['size'],
            ]);
            return ['success' => true, 'id' => $id, 'path' => $filePath];
        }

        return ['error' => 'Erro ao fazer upload do arquivo.'];
    }
}
