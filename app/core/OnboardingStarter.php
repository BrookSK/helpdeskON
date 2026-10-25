<?php

/**
 * Cria o onboarding quando a entrada do projeto financeiro fica paga (change
 * 02, D6). Chamado pelo webhook do Asaas, pelo "marcar paga" e pelo botão
 * manual. Um projeto financeiro tem um único onboarding: a criação passa por
 * um lock nomeado no banco para que duas baixas ao mesmo tempo não dupliquem.
 */
class OnboardingStarter
{
    private $db;
    private $projects;
    private $onboardings;

    public function __construct(?FinanceProject $projects = null, ?Onboarding $onboardings = null)
    {
        $this->db = Database::getInstance();
        $this->projects = $projects ?? new FinanceProject();
        $this->onboardings = $onboardings ?? new Onboarding();
    }

    /**
     * Onboarding do projeto financeiro: o existente ou um novo. Null quando a
     * entrada ainda não foi paga ou o projeto não existe.
     *
     * @return array{id:int,created:bool}|null
     */
    public function ensureForFinanceProject(int $projectId, ?int $userId = null): ?array
    {
        $project = $this->projects->findById($projectId);
        if (!$project || !$this->projects->canStartOnboarding($projectId)) return null;

        $lock = 'hd-onboarding-fp-' . $projectId;
        $got = (int)($this->db->fetch("SELECT GET_LOCK(?, 10) AS l", [$lock])['l'] ?? 0);
        if ($got !== 1) throw new \RuntimeException('Não foi possível travar a criação do onboarding.');
        try {
            $existing = $this->db->fetch("SELECT id FROM onboardings WHERE project_id = ? ORDER BY id ASC LIMIT 1", [$projectId]);
            if ($existing) return ['id' => (int)$existing['id'], 'created' => false];

            $contract = !empty($project['contract_id']) ? (new Contract())->findById((int)$project['contract_id']) : null;
            $creator = $userId ?: (!empty($project['created_by']) ? (int)$project['created_by'] : null);
            $id = (int)$this->onboardings->createFromProject($project, $contract ?: null, $creator);
            $this->onboardings->addEvent($id, $userId, 'created',
                'Onboarding criado com a entrada paga do financeiro #' . $projectId . ($userId ? '' : ' (automático)'));
            $this->projects->addEvent($projectId, null, $userId, 'onboarding', 'Onboarding #' . $id . ' criado');
            return ['id' => $id, 'created' => true];
        } finally {
            $this->db->query("SELECT RELEASE_LOCK(?)", [$lock]);
        }
    }
}
