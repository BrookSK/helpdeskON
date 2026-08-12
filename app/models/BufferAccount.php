<?php

class BufferAccount
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function all($onlyActive = true)
    {
        $sql = "SELECT * FROM buffer_accounts";
        if ($onlyActive) $sql .= " WHERE is_active = 1";
        $sql .= " ORDER BY id ASC";
        return $this->db->fetchAll($sql);
    }

    public function findById($id)
    {
        return $this->db->fetch("SELECT * FROM buffer_accounts WHERE id = ?", [$id]);
    }

    public function findByKey($apiKey)
    {
        return $this->db->fetch("SELECT * FROM buffer_accounts WHERE api_key = ?", [$apiKey]);
    }

    public function create($data)
    {
        return $this->db->insert('buffer_accounts', $data);
    }

    public function update($id, $data)
    {
        return $this->db->update('buffer_accounts', $data, 'id = ?', [$id]);
    }

    public function delete($id)
    {
        return $this->db->delete('buffer_accounts', 'id = ?', [$id]);
    }

    /**
     * Migra a chave única antiga (setting buffer_api_key) para a tabela, se ainda não existir.
     * Garante retrocompatibilidade sem exigir reconfiguração.
     */
    public function ensureLegacyKeyImported()
    {
        $legacy = Config::get('buffer_api_key');
        if (!empty($legacy) && !$this->findByKey($legacy)) {
            $this->create(['label' => 'Conta principal', 'api_key' => $legacy]);
        }
    }
}
