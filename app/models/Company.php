<?php

class Company
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById($id)
    {
        return $this->db->fetch("SELECT * FROM companies WHERE id = ?", [$id]);
    }

    public function getAll()
    {
        return $this->db->fetchAll("SELECT * FROM companies ORDER BY name");
    }

    public function create($data)
    {
        return $this->db->insert('companies', $data);
    }

    public function update($id, $data)
    {
        return $this->db->update('companies', $data, 'id = ?', [$id]);
    }

    public function delete($id)
    {
        return $this->db->delete('companies', 'id = ?', [$id]);
    }

    public function getUsers($companyId)
    {
        return $this->db->fetchAll(
            "SELECT * FROM users WHERE company_id = ? ORDER BY is_company_owner DESC, name",
            [$companyId]
        );
    }
}
