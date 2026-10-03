<?php

/**
 * Catálogo de serviços pré-cadastrados usados ao montar propostas (Fase 3).
 * Model fino sobre Database, no padrão do projeto.
 */
class ServiceCatalog
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById($id)
    {
        return $this->db->fetch("SELECT * FROM service_catalog WHERE id = ?", [$id]);
    }

    /** Lista serviços. Por padrão só os ativos (para seleção na proposta). */
    public function getAll($onlyActive = false)
    {
        $sql = "SELECT * FROM service_catalog";
        if ($onlyActive) $sql .= " WHERE active = 1";
        $sql .= " ORDER BY name";
        return $this->db->fetchAll($sql);
    }

    public function create($data)
    {
        return $this->db->insert('service_catalog', $data);
    }

    public function update($id, $data)
    {
        return $this->db->update('service_catalog', $data, 'id = ?', [$id]);
    }

    /** Alterna ativo/inativo (não deletamos para preservar histórico das propostas). */
    public function toggleActive($id)
    {
        $s = $this->findById($id);
        if (!$s) return false;
        $new = ((int)$s['active'] === 1) ? 0 : 1;
        return $this->db->update('service_catalog', ['active' => $new], 'id = ?', [$id]);
    }

    public function delete($id)
    {
        return $this->db->delete('service_catalog', 'id = ?', [$id]);
    }
}
