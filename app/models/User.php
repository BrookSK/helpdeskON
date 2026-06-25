<?php

class User
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById($id)
    {
        return $this->db->fetch("SELECT * FROM users WHERE id = ?", [$id]);
    }

    public function findByEmail($email)
    {
        return $this->db->fetch("SELECT * FROM users WHERE email = ?", [$email]);
    }

    public function getAll($role = null)
    {
        if ($role) {
            return $this->db->fetchAll("SELECT * FROM users WHERE role = ? ORDER BY name", [$role]);
        }
        return $this->db->fetchAll("SELECT * FROM users ORDER BY name");
    }

    public function getClients()
    {
        return $this->db->fetchAll("SELECT * FROM users WHERE role = 'client' ORDER BY name");
    }

    public function getAttendants()
    {
        return $this->db->fetchAll("SELECT * FROM users WHERE role = 'attendant' ORDER BY name");
    }

    public function create($data)
    {
        $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        return $this->db->insert('users', $data);
    }

    public function update($id, $data)
    {
        if (isset($data['password']) && !empty($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        } else {
            unset($data['password']);
        }
        return $this->db->update('users', $data, 'id = ?', [$id]);
    }

    public function delete($id)
    {
        return $this->db->delete('users', 'id = ?', [$id]);
    }

    public function authenticate($email, $password)
    {
        $user = $this->findByEmail($email);
        if ($user && password_verify($password, $user['password']) && $user['is_active']) {
            return $user;
        }
        return false;
    }

    public function toggleActive($id)
    {
        $user = $this->findById($id);
        $newStatus = $user['is_active'] ? 0 : 1;
        return $this->db->update('users', ['is_active' => $newStatus], 'id = ?', [$id]);
    }
}
