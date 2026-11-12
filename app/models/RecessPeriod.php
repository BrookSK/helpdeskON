<?php

/**
 * Períodos de recesso do Planejamento (datas em que ninguém trabalha).
 *
 * Usado pela cascata de atividade predecessora (PlanningRules) para deslocar
 * automaticamente cards cujo cálculo cairia dentro de um recesso, e pelo
 * calendário para desenhar as faixas de recesso.
 *
 * Escopo: company_id NULL = recesso GLOBAL (todas as empresas). Quando um card
 * tem empresa, consideram-se os recessos globais + os da própria empresa.
 */
class RecessPeriod
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** Todos os períodos, ordenados por início (para telas de gestão). */
    public function all()
    {
        return $this->db->fetchAll(
            "SELECT rp.*, co.name AS company_name
             FROM recess_periods rp
             LEFT JOIN companies co ON rp.company_id = co.id
             ORDER BY rp.start_date ASC, rp.id ASC"
        );
    }

    public function findById($id)
    {
        return $this->db->fetch("SELECT * FROM recess_periods WHERE id = ?", [$id]);
    }

    /**
     * Períodos aplicáveis a um card de uma dada empresa: os GLOBAIS
     * (company_id IS NULL) mais os específicos daquela empresa (se houver).
     * Passar $companyId = null retorna apenas os globais.
     *
     * Retorna linhas com start_date/end_date (DATE), prontas para alimentar
     * PlanningRules::recalcDependentDatesWithRecess / shiftOutOfRecess.
     */
    public function getApplicable($companyId = null)
    {
        if ($companyId === null) {
            return $this->db->fetchAll(
                "SELECT * FROM recess_periods WHERE company_id IS NULL ORDER BY start_date ASC"
            );
        }
        return $this->db->fetchAll(
            "SELECT * FROM recess_periods
             WHERE company_id IS NULL OR company_id = ?
             ORDER BY start_date ASC",
            [(int) $companyId]
        );
    }

    /**
     * Períodos que intersectam uma faixa [start, end] — usado pelo calendário
     * para pintar só o que aparece no mês/semana visível. Inclui globais e,
     * opcionalmente, os da empresa filtrada.
     */
    public function getForRange($start, $end, $companyId = null)
    {
        $sql = "SELECT rp.*, co.name AS company_name
                FROM recess_periods rp
                LEFT JOIN companies co ON rp.company_id = co.id
                WHERE rp.start_date <= ? AND rp.end_date >= ?";
        $params = [$end, $start];
        if ($companyId !== null) {
            $sql .= " AND (rp.company_id IS NULL OR rp.company_id = ?)";
            $params[] = (int) $companyId;
        }
        $sql .= " ORDER BY rp.start_date ASC";
        return $this->db->fetchAll($sql, $params);
    }

    public function create($data)
    {
        return $this->db->insert('recess_periods', $data);
    }

    public function update($id, $data)
    {
        return $this->db->update('recess_periods', $data, 'id = ?', [$id]);
    }

    public function delete($id)
    {
        return $this->db->delete('recess_periods', 'id = ?', [$id]);
    }
}
