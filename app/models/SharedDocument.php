<?php

class SharedDocument
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getByCompany($companyId)
    {
        return $this->db->fetchAll(
            "SELECT d.*, u.name as uploaded_by
             FROM shared_documents d
             LEFT JOIN users u ON d.user_id = u.id
             WHERE d.company_id = ? OR d.visibility = 'all'
             ORDER BY d.created_at DESC",
            [$companyId]
        );
    }

    public function getForTeam()
    {
        return $this->db->fetchAll(
            "SELECT d.*, u.name as uploaded_by, c.name as company_name
             FROM shared_documents d
             LEFT JOIN users u ON d.user_id = u.id
             LEFT JOIN companies c ON d.company_id = c.id
             ORDER BY d.created_at DESC"
        );
    }

    public function getForClient($companyId, $userId)
    {
        // Mostra: docs da empresa dele, docs com visibilidade 'all', ou docs que ele mesmo enviou
        if ($companyId) {
            return $this->db->fetchAll(
                "SELECT d.*, u.name as uploaded_by
                 FROM shared_documents d
                 LEFT JOIN users u ON d.user_id = u.id
                 WHERE d.company_id = ? OR d.visibility = 'all' OR d.user_id = ?
                 ORDER BY d.created_at DESC",
                [$companyId, $userId]
            );
        }
        // Se não tem empresa, mostra só os dele e os de visibilidade 'all'
        return $this->db->fetchAll(
            "SELECT d.*, u.name as uploaded_by
             FROM shared_documents d
             LEFT JOIN users u ON d.user_id = u.id
             WHERE d.visibility = 'all' OR d.user_id = ?
             ORDER BY d.created_at DESC",
            [$userId]
        );
    }

    public function findById($id)
    {
        return $this->db->fetch(
            "SELECT d.*, u.name as uploaded_by
             FROM shared_documents d
             LEFT JOIN users u ON d.user_id = u.id
             WHERE d.id = ?",
            [$id]
        );
    }

    public function create($data)
    {
        return $this->db->insert('shared_documents', $data);
    }

    public function delete($id)
    {
        $doc = $this->findById($id);
        if ($doc) {
            $filePath = PUBLIC_PATH . '/' . $doc['file_path'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            return $this->db->delete('shared_documents', 'id = ?', [$id]);
        }
        return false;
    }

    public function upload($file, $userId, $companyId, $title, $description, $visibility)
    {
        $allowedTypes = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain', 'text/csv',
        ];

        if (!in_array($file['type'], $allowedTypes)) {
            return ['error' => 'Tipo de arquivo não permitido.'];
        }

        $maxSize = 20 * 1024 * 1024; // 20MB
        if ($file['size'] > $maxSize) {
            return ['error' => 'Arquivo muito grande. Máximo: 20MB'];
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $fileName = uniqid('doc_') . '.' . $ext;
        $uploadDir = 'uploads/documents';
        $fullDir = PUBLIC_PATH . '/' . $uploadDir;

        if (!is_dir($fullDir)) {
            mkdir($fullDir, 0755, true);
        }

        $filePath = $uploadDir . '/' . $fileName;
        if (move_uploaded_file($file['tmp_name'], PUBLIC_PATH . '/' . $filePath)) {
            $id = $this->create([
                'company_id' => $companyId,
                'user_id' => $userId,
                'title' => $title,
                'description' => $description,
                'file_name' => $file['name'],
                'file_path' => $filePath,
                'file_type' => $file['type'],
                'file_size' => $file['size'],
                'visibility' => $visibility,
            ]);
            return ['success' => true, 'id' => $id];
        }

        return ['error' => 'Erro ao fazer upload do arquivo.'];
    }
}
