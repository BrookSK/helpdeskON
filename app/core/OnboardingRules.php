<?php

/**
 * Regras puras (sem banco/HTTP) do Onboarding (Fase 6).
 *
 * Define as etapas padrão (checklist), os estados, a regra de conclusão (todas
 * as etapas obrigatórias concluídas), e os bloqueios: o onboarding só começa
 * depois da ENTRADA PAGA (regra do financeiro) e uma etapa obrigatória não pode
 * ser marcada como concluída sem cumprir o requisito.
 */
class OnboardingRules
{
    public const STATUS_BLOCKED = 'blocked';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_DONE = 'done';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUSES = [self::STATUS_BLOCKED, self::STATUS_IN_PROGRESS, self::STATUS_DONE, self::STATUS_CANCELLED];

    public const STEP_STATUS = ['pending', 'in_progress', 'done', 'blocked'];

    /** Modos de pipeline. */
    public const PIPELINE_CX = 'esteira_cx';
    public const PIPELINE_OUT = 'fora_esteira';

    /**
     * Etapas padrão do onboarding (ordem). Cada: step_key, title, required.
     * As obrigatórias (required=1) travam a conclusão do onboarding até estarem
     * 'done' — espelha a reunião: servidor, armazenamento, acessos.
     *
     * Cada etapa inclui também 'default_role': o PAPEL responsável padrão
     * (sugerido) daquela etapa, conforme o guia. Ver STEP_DEFAULT_ROLE.
     *
     * @return array<int,array{step_key:string,title:string,required:int,default_role:?string}>
     */
    public static function defaultSteps(): array
    {
        $steps = [
            ['step_key' => 'tech_responsible', 'title' => 'Definir responsável técnico', 'required' => 1],
            // Guia de Onboarding: pontos focais, kickoff e apresentação dos fluxos
            // são indispensáveis para concluir o onboarding (required=1).
            ['step_key' => 'focal_points',     'title' => 'Cadastrar pontos focais', 'required' => 1],
            ['step_key' => 'kickoff',          'title' => 'Reunião de onboarding (kickoff)', 'required' => 1],
            ['step_key' => 'service_scope',    'title' => 'Revisar catálogo de serviço / orçamento por módulo', 'required' => 0],
            ['step_key' => 'tech_survey',      'title' => 'Levantamento técnico', 'required' => 0],
            ['step_key' => 'pipeline_decision','title' => 'Definir: projeto do zero vs entra na esteira (CX)', 'required' => 1],
            ['step_key' => 'environment',      'title' => 'Configuração de ambiente', 'required' => 1],
            ['step_key' => 'server',           'title' => 'Servidor configurado', 'required' => 1],
            ['step_key' => 'storage',          'title' => 'Armazenamento configurado', 'required' => 1],
            ['step_key' => 'credentials',      'title' => 'Credenciais necessárias disponíveis', 'required' => 1],
            ['step_key' => 'client_access',    'title' => 'Acesso do cliente criado (login + PIN)', 'required' => 0],
            ['step_key' => 'flows_presented',  'title' => 'Apresentação dos fluxos de atendimento', 'required' => 1],
        ];
        // Anexa o papel responsável padrão (sugerido) de cada etapa.
        foreach ($steps as &$s) {
            $s['default_role'] = self::defaultRoleForStep($s['step_key']);
        }
        unset($s);
        return $steps;
    }

    /**
     * Responsável PADRÃO (papel) de cada etapa do onboarding, conforme o guia:
     *   - Atendente: cadastro de pontos focais, kickoff, apresentação dos fluxos.
     *   - Responsável técnico: definição do técnico, levantamento, acesso do cliente.
     *   - Analista: ambiente, servidor, armazenamento, credenciais.
     *   - Líder/Gestor: revisão de catálogo/orçamento e decisão de pipeline.
     *
     * É um PAPEL sugerido (não um users.id). A atribuição do usuário real
     * continua manual; isto só pré-define quem é o dono natural de cada etapa.
     *
     * @var array<string,string>
     */
    public const STEP_DEFAULT_ROLE = [
        'tech_responsible'  => 'technical',
        'focal_points'      => 'attendant',
        'kickoff'           => 'attendant',
        'service_scope'     => 'manager',
        'tech_survey'       => 'technical',
        'pipeline_decision' => 'manager',
        'environment'       => 'analyst',
        'server'            => 'analyst',
        'storage'           => 'analyst',
        'credentials'       => 'analyst',
        'client_access'     => 'technical',
        'flows_presented'   => 'attendant',
    ];

    /** Rótulos amigáveis dos papéis padrão de etapa. */
    public const STEP_ROLE_LABELS = [
        'attendant' => 'Atendente',
        'technical' => 'Responsável técnico',
        'analyst'   => 'Analista',
        'manager'   => 'Líder / Gestor',
        'client'    => 'Cliente',
    ];

    /**
     * Papel padrão sugerido para uma etapa (pela step_key), ou null se a etapa
     * não tiver responsável padrão definido.
     */
    public static function defaultRoleForStep(?string $stepKey): ?string
    {
        if ($stepKey === null) return null;
        return self::STEP_DEFAULT_ROLE[$stepKey] ?? null;
    }

    /** Rótulo amigável do papel padrão de uma etapa (ou '—' se não houver). */
    public static function defaultRoleLabelForStep(?string $stepKey): string
    {
        $role = self::defaultRoleForStep($stepKey);
        return $role !== null ? (self::STEP_ROLE_LABELS[$role] ?? $role) : '—';
    }

    public static function normalizeStatus($v): string
    {
        return in_array($v, self::STATUSES, true) ? $v : self::STATUS_BLOCKED;
    }

    public static function normalizeStepStatus($v): string
    {
        return in_array($v, self::STEP_STATUS, true) ? $v : 'pending';
    }

    public static function normalizePipeline($v): ?string
    {
        return in_array($v, [self::PIPELINE_CX, self::PIPELINE_OUT], true) ? $v : null;
    }

    /** Tipos de projeto (coluna onboardings.project_type). */
    public const PROJECT_TYPES = ['zero', 'esteira', 'manutencao', 'outro'];

    public static function normalizeProjectType($v): ?string
    {
        return in_array($v, self::PROJECT_TYPES, true) ? $v : null;
    }

    /**
     * Deriva o pipeline a partir do tipo de projeto quando não informado
     * explicitamente: 'esteira' => esteira_cx; 'zero'/'manutencao'/'outro' =>
     * fora_esteira. Retorna null se o tipo for desconhecido.
     */
    public static function pipelineFromProjectType(?string $projectType): ?string
    {
        $t = self::normalizeProjectType($projectType);
        if ($t === null) return null;
        return $t === 'esteira' ? self::PIPELINE_CX : self::PIPELINE_OUT;
    }

    /**
     * O onboarding pode INICIAR? Precisa da entrada paga no financeiro.
     * Recebe o resultado de FinanceProject::canStartOnboarding (bool) ou null
     * (sem projeto financeiro vinculado -> bloqueado por segurança).
     */
    public static function canStart(?bool $entryPaid): bool
    {
        return $entryPaid === true;
    }

    /**
     * Uma etapa pode ser marcada como 'done'? Se for obrigatória, exige que o
     * requisito esteja satisfeito (requirementMet). Etapas não obrigatórias
     * podem ser concluídas livremente.
     */
    public static function canCompleteStep(array $step, bool $requirementMet = true): bool
    {
        if ((int)($step['required'] ?? 0) === 1) {
            return $requirementMet;
        }
        return true;
    }

    /**
     * O onboarding pode ser CONCLUÍDO? Todas as etapas obrigatórias precisam
     * estar 'done'. Sem etapas obrigatórias, basta não haver pendência obrigatória.
     *
     * @param array<int,array> $steps linhas de onboarding_steps
     */
    public static function canFinish(array $steps): bool
    {
        $required = array_filter($steps, fn($s) => (int)($s['required'] ?? 0) === 1);
        if (empty($required)) {
            // Sem obrigatórias: conclui se não há nenhuma etapa pendente/bloqueada.
            foreach ($steps as $s) {
                if (in_array($s['status'] ?? '', ['pending', 'blocked', 'in_progress'], true)) return false;
            }
            return true;
        }
        foreach ($required as $s) {
            if (($s['status'] ?? '') !== 'done') return false;
        }
        return true;
    }

    /** Lista das etapas obrigatórias ainda não concluídas (motivo do bloqueio). */
    public static function pendingRequired(array $steps): array
    {
        $out = [];
        foreach ($steps as $s) {
            if ((int)($s['required'] ?? 0) === 1 && ($s['status'] ?? '') !== 'done') {
                $out[] = $s['title'] ?? ($s['step_key'] ?? 'etapa');
            }
        }
        return $out;
    }
}
