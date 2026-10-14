<?php

/**
 * Signatários da empresa (lado contratada) reutilizáveis para contratos.
 * O usuário cadastra os representantes que podem assinar; ao enviar um contrato
 * para assinatura, escolhe qual(is) assina(m) junto com o cliente.
 */
class ContractSigner
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById($id)
    {
        return $this->db->fetch("SELECT * FROM contract_signers WHERE id = ?", [$id]);
    }

    public function getAll($onlyActive = false)
    {
        $sql = "SELECT * FROM contract_signers";
        if ($onlyActive) $sql .= " WHERE active = 1";
        $sql .= " ORDER BY is_default DESC, name";
        try { return $this->db->fetchAll($sql); } catch (\Throwable $e) { return []; }
    }

    /** Busca vários signatários por lista de ids (para o envio). */
    public function findByIds(array $ids)
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (empty($ids)) return [];
        $ph = implode(',', array_fill(0, count($ids), '?'));
        try {
            return $this->db->fetchAll("SELECT * FROM contract_signers WHERE id IN ($ph) AND active = 1", $ids);
        } catch (\Throwable $e) { return []; }
    }

    public function create($data)
    {
        return $this->db->insert('contract_signers', [
            'name' => trim($data['name'] ?? ''),
            'email' => trim($data['email'] ?? ''),
            'phone' => trim($data['phone'] ?? '') ?: null,
            'role_label' => trim($data['role_label'] ?? '') ?: null,
            'is_default' => !empty($data['is_default']) ? 1 : 0,
            'active' => 1,
            'created_by' => $data['created_by'] ?? null,
        ]);
    }

    public function update($id, $data)
    {
        $fields = [
            'name' => trim($data['name'] ?? ''),
            'email' => trim($data['email'] ?? ''),
            'phone' => trim($data['phone'] ?? '') ?: null,
            'role_label' => trim($data['role_label'] ?? '') ?: null,
            'is_default' => !empty($data['is_default']) ? 1 : 0,
        ];
        return $this->db->update('contract_signers', $fields, 'id = ?', [$id]);
    }

    public function toggleActive($id)
    {
        $s = $this->findById($id);
        if (!$s) return false;
        return $this->db->update('contract_signers', ['active' => ((int)$s['active'] === 1) ? 0 : 1], 'id = ?', [$id]);
    }
}
