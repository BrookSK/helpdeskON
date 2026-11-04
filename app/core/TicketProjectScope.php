<?php

/**
 * Projeto e serviço do chamado (change 02, D7).
 *
 * O chamado pode apontar o projeto do cliente e um item da proposta aceita
 * desse projeto. Para o cliente, um projeto ativo único vem sozinho e a trava
 * de garantia (ProjectRules::canOpenTicket) bloqueia a abertura. Para a equipe,
 * a trava vira só aviso.
 */
class TicketProjectScope
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** Projetos da empresa que ainda recebem chamados (não cancelados nem encerrados). */
    public function projectsForCompany(?int $companyId): array
    {
        if (!$companyId) return [];
        return $this->db->fetchAll(
            "SELECT * FROM projects WHERE company_id = ? AND status NOT IN ('cancelled', 'closed') ORDER BY name ASC, id ASC",
            [$companyId]
        );
    }

    /** Itens da proposta aceita do projeto (serviços contratados). */
    public function servicesForProject(array $project): array
    {
        if (empty($project['proposal_id'])) return [];
        return $this->db->fetchAll(
            "SELECT id, description, billing FROM proposal_items WHERE proposal_id = ? ORDER BY position ASC, id ASC",
            [(int)$project['proposal_id']]
        );
    }

    /**
     * Projetos e serviços para o formulário, por empresa:
     * [company_id => [['id','name','blocked','reason','services'=>[...]]]].
     */
    public function formOptions(array $companyIds): array
    {
        $out = [];
        foreach (array_unique(array_filter(array_map('intval', $companyIds))) as $cid) {
            foreach ($this->projectsForCompany($cid) as $p) {
                $out[$cid][] = [
                    'id' => (int)$p['id'], 'name' => $p['name'],
                    'blocked' => !ProjectRules::canOpenTicket($p), 'reason' => ProjectRules::blockReason($p),
                    'services' => array_map(fn($s) => ['id' => (int)$s['id'], 'description' => $s['description']], $this->servicesForProject($p)),
                ];
            }
        }
        return $out;
    }

    /**
     * Decide o projeto e o serviço do chamado.
     *
     * @param bool $isClient quem abre é o cliente (trava) ou a equipe (aviso)
     * @param int|null $companyId empresa do chamado (a ativa do cliente ou a do solicitante escolhido)
     * @return array{ok:bool,error?:string,project_id:?int,proposal_item_id:?int,warning:?string}
     */
    public function resolve(bool $isClient, ?int $companyId, $projectId, $itemId): array
    {
        $out = ['ok' => true, 'project_id' => null, 'proposal_item_id' => null, 'warning' => null];
        $projects = $this->projectsForCompany($companyId);
        $projectId = (int)$projectId;

        $project = null;
        if ($projectId > 0) {
            foreach ($projects as $p) {
                if ((int)$p['id'] === $projectId) { $project = $p; break; }
            }
            if (!$project) return ['ok' => false, 'error' => 'Projeto inválido para esta empresa.'] + $out;
        } elseif (count($projects) === 1) {
            $project = $projects[0];
        }
        if (!$project) return $out;

        $reason = ProjectRules::blockReason($project);
        if ($reason !== null) {
            if ($isClient) {
                return ['ok' => false, 'error' => $reason . ' Para abrir chamados deste projeto, é preciso contratar o suporte. Fale com a equipe.'] + $out;
            }
            $out['warning'] = 'Atenção: o projeto "' . $project['name'] . '" está fora da cobertura (' . $reason . ')';
        }
        $out['project_id'] = (int)$project['id'];

        $itemId = (int)$itemId;
        if ($itemId > 0) {
            $valid = in_array($itemId, array_map('intval', array_column($this->servicesForProject($project), 'id')), true);
            if (!$valid) return ['ok' => false, 'error' => 'Serviço inválido para este projeto.'] + $out;
            $out['proposal_item_id'] = $itemId;
        }
        return $out;
    }
}
