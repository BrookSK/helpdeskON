<?php

/**
 * RDO — Relatório Diário. Registro e acompanhamento das atividades diárias.
 *
 * Visibilidade (RdoRules::canViewReportOf):
 *   - super_admin vê o de todos;
 *   - qualquer outro papel (inclusive developer) vê só o próprio.
 * O acesso ao MÓDULO é liberado a toda a equipe interna (Permissions 'rdo').
 *
 * Prazo e bloqueio:
 *   - Relatório do dia: pode ser criado/editado até o horário limite (config rdo_deadline_time).
 *   - Após o prazo → is_locked=1; edição direta recusada (423); usuário solicita
 *     desbloqueio via POST /rdo/requestUnlock/{id}.
 *   - Relatório de dia anterior → exige aprovação: edição vai para draft pendente.
 *
 * Novos endpoints:
 *   GET  /rdo/pendingReviews       → lista pendências para o admin.
 *   POST /rdo/approveEdit/{id}     → aprova draft e aplica ao relatório.
 *   POST /rdo/rejectEdit/{id}      → recusa draft (relatório original intacto).
 *   POST /rdo/requestUnlock/{id}   → solicita desbloqueio ao admin.
 */
class RdoController extends Controller
{
    /** @var DailyReport */
    private $model;

    public function __construct()
    {
        $this->model = new DailyReport();
    }

    // =========================================================================
    // Helpers privados
    // =========================================================================

    /** Aplica o escopo do dono aos filtros quando o papel não tem visão global. */
    private function scopeFilters(array $filters): array
    {
        $user = $this->currentUser();
        if (!RdoRules::hasGlobalView($user['role'] ?? null)) {
            $filters['user_id'] = $user['id'];
        } elseif (!empty($_GET['user_id'])) {
            $filters['user_id'] = (int) $_GET['user_id'];
        }
        return $filters;
    }

    /**
     * Resolve o projeto/obra (company_id) a partir do POST: normaliza via regra
     * pura e confirma que a empresa existe. Empresa inexistente/inválida vira null.
     */
    private function resolveCompanyId($value): ?int
    {
        $id = RdoRules::normalizeCompanyId($value);
        if ($id === null) {
            return null;
        }
        return (new Company())->findById($id) ? $id : null;
    }

    /** Garante que o usuário pode ver/editar o relatório; encerra com 403/404 se não. */
    private function requireOwnedOrGlobal($report): void
    {
        if (!$report) {
            $this->json(['error' => 'Relatório não encontrado'], 404);
        }
        $user = $this->currentUser();
        if (!RdoRules::canViewReportOf($user['role'] ?? null, $user['id'], $report['user_id'])) {
            $this->json(['error' => 'Sem permissão'], 403);
        }
    }

    /** Garante que o usuário atual pode aprovar revisões (super_admin). */
    private function requireReviewer(): void
    {
        $user = $this->currentUser();
        if (!RdoRules::canReview($user['role'] ?? null)) {
            $this->json(['error' => 'Sem permissão para aprovar revisões'], 403);
        }
    }

    /**
     * Retorna o horário limite configurado (ex.: '19:00:00').
     * Lê da tabela settings via RdoRules::getDeadline().
     */
    private function deadline(): string
    {
        return RdoRules::getDeadline();
    }

    /**
     * Lê campos editáveis de $_POST e monta o array de dados para create/update.
     * Campos ausentes no POST não são incluídos (update parcial seguro).
     * $include: lista de campos a processar (null = todos).
     */
    private function postToFields(?array $include = null): array
    {
        $data = [];

        $all = [
            'report_date', 'title', 'activities', 'occurrences',
            'pending_tasks', 'next_day_plan', 'status', 'transcription', 'company_id',
        ];
        $fields = $include ?? $all;

        if (in_array('report_date', $fields) && isset($_POST['report_date'])) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['report_date'])) {
                $data['report_date'] = $_POST['report_date'];
            }
        }
        if (in_array('title', $fields) && array_key_exists('title', $_POST)) {
            $data['title'] = trim($_POST['title']) ?: null;
        }
        if (in_array('activities', $fields) && array_key_exists('activities', $_POST)) {
            $data['activities'] = trim($_POST['activities']) ?: null;
        }
        if (in_array('occurrences', $fields) && array_key_exists('occurrences', $_POST)) {
            $occ = trim($_POST['occurrences']);
            $data['occurrences']   = $occ ?: null;
            $data['has_occurrence'] = RdoRules::deriveHasOccurrence($occ);
        }
        if (in_array('pending_tasks', $fields) && array_key_exists('pending_tasks', $_POST)) {
            $data['pending_tasks'] = trim($_POST['pending_tasks']) ?: null;
        }
        if (in_array('next_day_plan', $fields) && array_key_exists('next_day_plan', $_POST)) {
            $data['next_day_plan'] = trim($_POST['next_day_plan']) ?: null;
        }
        if (in_array('status', $fields) && isset($_POST['status'])) {
            $data['status'] = RdoRules::normalizeStatus($_POST['status'], 'em_andamento');
        }
        if (in_array('transcription', $fields) && array_key_exists('transcription', $_POST)) {
            $data['transcription'] = trim($_POST['transcription']) ?: null;
        }
        if (in_array('company_id', $fields) && array_key_exists('company_id', $_POST)) {
            $data['company_id'] = $this->resolveCompanyId($_POST['company_id']);
        }

        return $data;
    }

    /**
     * Grava entrada no histórico do relatório.
     */
    private function logHistory(int $reportId, int $userId, string $action, array $report = [], ?int $reviewId = null, ?string $notes = null): void
    {
        $this->model->addHistory([
            'report_id'     => $reportId,
            'changed_by'    => $userId,
            'action'        => $action,
            'snapshot_json' => $report ? RdoRules::buildSnapshot($report) : null,
            'review_id'     => $reviewId,
            'notes'         => $notes,
        ]);
    }

    /**
     * Se não houver mais revisões pendentes para o relatório, reseta review_status.
     */
    private function resetReviewStatusIfClear(int $reportId): void
    {
        if (!$this->model->hasPendingReviews($reportId)) {
            $this->model->update($reportId, ['review_status' => 'none']);
        }
    }

    /**
     * Lê arrays paralelos collaborator_name[]/collaborator_kind[]/
     * collaborator_notes[] do POST e persiste. Em update ($replace=true),
     * substitui todos; na criação, apenas adiciona.
     */
    private function saveCollaboratorsFromPost(int $reportId, bool $replace = false): void
    {
        $names = $_POST['collaborator_name'] ?? [];
        if (!is_array($names)) $names = [$names];
        $kinds = $_POST['collaborator_kind'] ?? [];
        if (!is_array($kinds)) $kinds = [$kinds];
        $notes = $_POST['collaborator_notes'] ?? [];
        if (!is_array($notes)) $notes = [$notes];

        $collaborators = [];
        foreach ($names as $i => $name) {
            if (trim((string) $name) === '') continue;
            $collaborators[] = [
                'collaborator_name' => $name,
                'kind'              => $kinds[$i] ?? 'colaborador',
                'notes'             => $notes[$i] ?? null,
            ];
        }

        if ($replace) {
            $this->model->replaceCollaborators($reportId, $collaborators);
        } else {
            foreach ($collaborators as $c) {
                $this->model->addCollaborator([
                    'report_id'         => $reportId,
                    'collaborator_name' => trim($c['collaborator_name']),
                    'kind'              => RdoRules::normalizeCollaboratorKind($c['kind']),
                    'notes'             => isset($c['notes']) ? (trim((string) $c['notes']) ?: null) : null,
                ]);
            }
        }
    }

    // =========================================================================
    // Página principal
    // =========================================================================

    public function index()
    {
        $this->requireModule('rdo');
        $user = $this->currentUser();

        $team = [];
        if (RdoRules::hasGlobalView($user['role'])) {
            $userModel = new User();
            $team = $userModel->getByRoles(['super_admin', 'developer', 'attendant', 'analyst', 'comercial', 'marketing', 'whatsapp_agent']);
        }

        $companies = (new Company())->getAll();

        // Conta pendências para badge na aba (apenas super_admin): revisões
        // reais + dias úteis sem relatório (ausências).
        $pendingCount = 0;
        if (RdoRules::canReview($user['role'])) {
            $pendingCount = count($this->model->getAllPendingReviews())
                          + count($this->collectMissingAbsences());
        }

        $this->view('rdo/index', [
            'user'         => $user,
            'currentPage'  => 'rdo',
            'isGlobal'     => RdoRules::hasGlobalView($user['role']),
            'isReviewer'   => RdoRules::canReview($user['role']),
            'team'         => $team,
            'companies'    => $companies,
            'statuses'     => RdoRules::STATUSES,
            'statusLabels' => RdoRules::STATUS_LABELS,
            'pendingCount' => $pendingCount,
            'deadline'     => RdoRules::getDeadline(),
        ]);
    }

    // =========================================================================
    // API: listagem + stats
    // =========================================================================

    public function list()
    {
        $this->requireModule('rdo');

        $filters = [];
        if (!empty($_GET['search']))      $filters['search']       = trim($_GET['search']);
        if (!empty($_GET['date']))        $filters['date']         = $_GET['date'];
        if (!empty($_GET['date_from']))   $filters['date_from']    = $_GET['date_from'];
        if (!empty($_GET['date_to']))     $filters['date_to']      = $_GET['date_to'];
        if (!empty($_GET['status']))      $filters['status']       = $_GET['status'];
        if (!empty($_GET['company_id']))  $filters['company_id']   = (int) $_GET['company_id'];
        if (!empty($_GET['review_status'])) $filters['review_status'] = $_GET['review_status'];
        if (isset($_GET['has_occurrence']) && $_GET['has_occurrence'] !== '') {
            $filters['has_occurrence'] = (int) $_GET['has_occurrence'];
        }

        $filters = $this->scopeFilters($filters);

        $this->json([
            'items' => $this->model->getList($filters),
            'stats' => $this->model->getStats($filters),
        ]);
    }

    // =========================================================================
    // API: um relatório com anexos, colaboradores e histórico
    // =========================================================================

    public function get($id = null)
    {
        $this->requireModule('rdo');
        if (!$id) $this->json(['error' => 'ID não informado'], 400);

        $report = $this->model->findById($id);
        $this->requireOwnedOrGlobal($report);

        $report['attachments']   = $this->model->getAttachments($id);
        $report['collaborators'] = $this->model->getCollaborators($id);
        $report['history']       = $this->model->getHistory((int) $id);
        $this->json(['item' => $report]);
    }

    // =========================================================================
    // API: criar
    // =========================================================================

    public function create()
    {
        $this->requireModule('rdo');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Método inválido'], 405);
        }
        $user = $this->currentUser();

        // Data do relatório: valida formato AAAA-MM-DD; senão usa hoje.
        $reportDate = $_POST['report_date'] ?? date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $reportDate)) {
            $reportDate = date('Y-m-d');
        }

        $occurrences = trim($_POST['occurrences'] ?? '');
        $data = [
            'user_id'       => $user['id'],   // dono é SEMPRE quem cria
            'company_id'    => $this->resolveCompanyId($_POST['company_id'] ?? null),
            'report_date'   => $reportDate,
            'title'         => trim($_POST['title'] ?? '') ?: null,
            'activities'    => trim($_POST['activities'] ?? '') ?: null,
            'occurrences'   => $occurrences ?: null,
            'has_occurrence'=> RdoRules::deriveHasOccurrence($occurrences),
            'pending_tasks' => trim($_POST['pending_tasks'] ?? '') ?: null,
            'next_day_plan' => trim($_POST['next_day_plan'] ?? '') ?: null,
            'status'        => RdoRules::normalizeStatus($_POST['status'] ?? '', 'em_andamento'),
            'transcription' => trim($_POST['transcription'] ?? '') ?: null,
            'submitted_at'  => date('Y-m-d H:i:s'),
            'is_locked'     => 0,
            'lock_reason'   => null,
            'review_status' => 'none',
        ];

        // Verifica se o preenchimento tardio exige pendência de revisão.
        $today        = date('Y-m-d');
        $nowTime      = date('H:i:s');
        $deadlineTime = $this->deadline();

        $reviewType = RdoRules::reviewTypeForCreate($reportDate, $nowTime, $today, $deadlineTime);
        if ($reviewType !== null) {
            $data['review_status'] = 'pending_review';
        }

        $id = $this->model->create($data);

        // Colaboradores
        $this->saveCollaboratorsFromPost((int) $id);

        // Cria a pendência de revisão se necessário
        if ($reviewType !== null) {
            $this->model->createReview([
                'report_id'    => $id,
                'type'         => $reviewType,
                'requested_by' => $user['id'],
                'payload_json' => null,
                'notes'        => null,
            ]);
        }

        // Histórico
        $created = $this->model->findById($id);
        $this->logHistory((int) $id, (int) $user['id'], 'created', $created ?: []);

        $this->json(['success' => true, 'id' => $id, 'pending_review' => $reviewType !== null]);
    }

    // =========================================================================
    // API: atualizar
    // =========================================================================

    public function update($id = null)
    {
        $this->requireModule('rdo');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) {
            $this->json(['error' => 'Requisição inválida'], 400);
        }
        $report = $this->model->findById($id);
        $this->requireOwnedOrGlobal($report);

        $user         = $this->currentUser();
        $today        = date('Y-m-d');
        $nowTime      = date('H:i:s');
        $deadlineTime = $this->deadline();

        // ── Relatório de dia anterior ou bloqueado: fluxo de aprovação ──────
        if (RdoRules::requiresApprovalForEdit(
            $report['report_date'],
            (int) ($report['is_locked'] ?? 0),
            $today
        )) {
            // Monta payload com os novos valores propostos.
            $proposed = $this->postToFields();
            if (empty($proposed) && !isset($_POST['collaborator_name'])) {
                $this->json(['error' => 'Nenhum campo informado para alteração'], 400);
            }

            // Snapshot dos valores atuais + propostos lado a lado para o admin.
            $payload = [
                'current'  => json_decode(RdoRules::buildSnapshot($report), true),
                'proposed' => $proposed,
            ];

            $reviewId = $this->model->createReview([
                'report_id'    => (int) $id,
                'type'         => 'edit_request',
                'requested_by' => (int) $user['id'],
                'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            ]);

            $this->model->update((int) $id, ['review_status' => 'pending_review']);

            $this->logHistory(
                (int) $id,
                (int) $user['id'],
                'submitted',
                $report,
                $reviewId,
                'Edição enviada para aprovação'
            );

            $this->json(['success' => true, 'pending' => true, 'message' => 'Alteração enviada para aprovação do administrador.']);
            return;
        }

        // ── Relatório dentro do prazo: edição direta ─────────────────────────
        // Verifica se o horário ainda permite edição direta (mesmo dia).
        if (!RdoRules::isWithinDeadline($report['report_date'], $nowTime, $today, $deadlineTime)) {
            // Passou do horário — bloqueia e informa.
            $this->model->update((int) $id, [
                'is_locked'   => 1,
                'lock_reason' => 'deadline',
                'review_status' => ($report['review_status'] === 'pending_review') ? 'pending_review' : 'none',
            ]);
            $this->logHistory((int) $id, (int) $user['id'], 'locked', $report, null, 'Bloqueado por prazo ao tentar editar');
            $this->json([
                'error'  => 'O prazo de preenchimento deste relatório já encerrou. Solicite liberação ao administrador.',
                'locked' => true,
            ], 423);
            return;
        }

        // Aplica edição direta.
        $data = $this->postToFields();
        if ($data) {
            $this->model->update((int) $id, $data);
        }
        if (isset($_POST['collaborator_name'])) {
            $this->saveCollaboratorsFromPost((int) $id, true);
        }

        $updated = $this->model->findById($id);
        $this->logHistory((int) $id, (int) $user['id'], 'updated', $updated ?: []);

        $this->json(['success' => true, 'pending' => false]);
    }

    // =========================================================================
    // API: excluir
    // =========================================================================

    public function delete($id = null)
    {
        $this->requireModule('rdo');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) {
            $this->json(['error' => 'Requisição inválida'], 400);
        }
        $report = $this->model->findById($id);
        $this->requireOwnedOrGlobal($report);

        foreach ($this->model->getAttachments($id) as $att) {
            $full = PUBLIC_PATH . '/' . $att['file_path'];
            if (is_file($full)) @unlink($full);
        }
        $this->model->delete($id);
        $this->json(['success' => true]);
    }

    // =========================================================================
    // API: upload de anexo
    // =========================================================================

    public function upload($id = null)
    {
        $this->requireModule('rdo');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) {
            $this->json(['error' => 'Requisição inválida'], 400);
        }
        $report = $this->model->findById($id);
        $this->requireOwnedOrGlobal($report);

        if (empty($_FILES['file']['name']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $this->json(['error' => 'Nenhum arquivo enviado'], 400);
        }
        $file = $_FILES['file'];
        if ($file['size'] > 25 * 1024 * 1024) {
            $this->json(['error' => 'Arquivo muito grande (máx 25MB)'], 400);
        }

        $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
        $fileName = uniqid('rdo_') . '_' . time() . ($ext ? '.' . strtolower($ext) : '');
        $uploadDir = 'uploads/rdo';
        $fullDir   = PUBLIC_PATH . '/' . $uploadDir;
        if (!is_dir($fullDir)) mkdir($fullDir, 0755, true);

        $filePath = $uploadDir . '/' . $fileName;
        if (!move_uploaded_file($file['tmp_name'], PUBLIC_PATH . '/' . $filePath)) {
            $this->json(['error' => 'Erro ao salvar arquivo'], 500);
        }

        $attId = $this->model->addAttachment([
            'report_id' => $id,
            'user_id'   => $this->currentUser()['id'],
            'file_name' => $file['name'],
            'file_path' => $filePath,
            'file_type' => $file['type'],
            'file_size' => $file['size'],
        ]);

        $this->json([
            'success'    => true,
            'attachment' => [
                'id'        => $attId,
                'file_name' => $file['name'],
                'file_path' => $filePath,
                'file_type' => $file['type'],
            ],
        ]);
    }

    // =========================================================================
    // API: excluir anexo
    // =========================================================================

    public function deleteAttachment($attId = null)
    {
        $this->requireModule('rdo');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$attId) {
            $this->json(['error' => 'Requisição inválida'], 400);
        }
        $att = $this->model->findAttachment($attId);
        if (!$att) $this->json(['error' => 'Anexo não encontrado'], 404);

        $report = $this->model->findById($att['report_id']);
        $this->requireOwnedOrGlobal($report);

        $full = PUBLIC_PATH . '/' . $att['file_path'];
        if (is_file($full)) @unlink($full);
        $this->model->deleteAttachment($attId);
        $this->json(['success' => true]);
    }

    // =========================================================================
    // API: transcrever áudio (base64) e organizar as atividades do dia
    // =========================================================================

    public function transcribe()
    {
        $this->requireModule('rdo');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Método não permitido'], 405);
        }

        $ai = new OpenAiClient();
        if (!$ai->isConfigured()) {
            $this->json(['error' => 'Chave da API OpenAI não configurada.'], 400);
        }

        $input     = json_decode(file_get_contents('php://input'), true);
        $audioData = $input['audio'] ?? '';
        if (empty($audioData)) {
            $this->json(['error' => 'Áudio não recebido.'], 400);
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'rdo_audio_') . '.webm';
        file_put_contents($tempFile, base64_decode($audioData));

        $t = $ai->transcribe($tempFile, ['language' => 'pt']);
        @unlink($tempFile);
        if (empty($t['success'])) {
            $this->json(['error' => $t['error'] ?: 'Erro na transcrição do áudio.'], 500);
        }
        $transcription = $t['text'];

        $organized = ['title' => '', 'activities' => $transcription, 'occurrences' => '', 'pending_tasks' => '', 'next_day_plan' => ''];
        $res = $ai->chat([
            ['role' => 'system', 'content' => 'Você organiza relatórios diários de trabalho. Responda APENAS JSON válido.'],
            ['role' => 'user', 'content' =>
                "A partir da transcrição abaixo do relato de um dia de trabalho, retorne um JSON com: "
                . "title (resumo curto do dia), activities (atividades realizadas, detalhadas), "
                . "occurrences (impedimentos/bloqueios relatados, ou string vazia), "
                . "pending_tasks (o que ficou pendente/em andamento, ou string vazia), "
                . "next_day_plan (plano para o próximo dia, ou string vazia).\n\n"
                . "Transcrição: \"{$transcription}\""
            ],
        ], ['temperature' => 0.3, 'response_format' => ['type' => 'json_object']]);

        if (!empty($res['success'])) {
            $parsed = json_decode($res['content'], true);
            if (is_array($parsed)) {
                $organized = [
                    'title'         => trim((string) ($parsed['title']         ?? '')),
                    'activities'    => trim((string) ($parsed['activities']    ?? $transcription)),
                    'occurrences'   => trim((string) ($parsed['occurrences']   ?? '')),
                    'pending_tasks' => trim((string) ($parsed['pending_tasks'] ?? '')),
                    'next_day_plan' => trim((string) ($parsed['next_day_plan'] ?? '')),
                ];
            }
        }

        $this->json([
            'success'       => true,
            'transcription' => $transcription,
            'organized'     => $organized,
        ]);
    }

    // =========================================================================
    // API: solicitar desbloqueio (POST /rdo/requestUnlock/{id})
    // =========================================================================

    /**
     * O profissional solicita ao admin que desbloqueie o relatório para edição.
     * Cria uma revisão do tipo 'unlock_request' e mantém is_locked=1 até aprovação.
     */
    public function requestUnlock($id = null)
    {
        $this->requireModule('rdo');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) {
            $this->json(['error' => 'Requisição inválida'], 400);
        }
        $report = $this->model->findById($id);
        $this->requireOwnedOrGlobal($report);

        if (!(int) ($report['is_locked'] ?? 0)) {
            $this->json(['error' => 'Este relatório não está bloqueado.'], 400);
        }

        // Evita criar solicitações duplicadas
        $pending = $this->model->getPendingReviewsForReport((int) $id);
        foreach ($pending as $p) {
            if ($p['type'] === 'unlock_request') {
                $this->json(['success' => true, 'message' => 'Já existe uma solicitação de desbloqueio pendente.']);
                return;
            }
        }

        $user = $this->currentUser();
        $reviewId = $this->model->createReview([
            'report_id'    => (int) $id,
            'type'         => 'unlock_request',
            'requested_by' => (int) $user['id'],
        ]);
        $this->model->update((int) $id, ['review_status' => 'pending_review']);

        $this->logHistory((int) $id, (int) $user['id'], 'submitted', $report, $reviewId, 'Solicitação de desbloqueio');

        $this->json(['success' => true, 'message' => 'Solicitação de desbloqueio enviada ao administrador.']);
    }

    // =========================================================================
    // API: listar pendências de revisão (GET /rdo/pendingReviews) — admin
    // =========================================================================

    public function pendingReviews()
    {
        $this->requireModule('rdo');
        $this->requireReviewer();

        $reviews = $this->model->getAllPendingReviews();

        // Enriquece cada revisão do tipo edit_request com o diff decodificado.
        foreach ($reviews as &$r) {
            if ($r['type'] === 'edit_request' && !empty($r['payload_json'])) {
                $r['payload'] = json_decode($r['payload_json'], true);
            } else {
                $r['payload'] = null;
            }
        }
        unset($r);

        // Acrescenta as ausências (dias úteis sem relatório) como itens
        // informativos do tipo 'missing_report'. Não têm report_id nem ação.
        $reviews = array_merge($reviews, $this->collectMissingAbsences());

        $this->json(['reviews' => $reviews, 'total' => count($reviews)]);
    }

    /**
     * Calcula as ausências: para cada profissional com acesso ao RDO, os dias
     * ÚTEIS (seg–sex) dos últimos RdoRules::MISSING_SCAN_DAYS dias que não têm
     * relatório. Não depende do cron de notificação — é uma varredura sob
     * demanda, então nenhum dia "escapa" se o cron não tiver rodado.
     *
     * Retorna uma lista de itens no mesmo formato das revisões, com:
     *   type='missing_report', owner_name, report_date, e payload=null.
     *
     * @return array<int,array<string,mixed>>
     */
    private function collectMissingAbsences(): array
    {
        $today = date('Y-m-d');
        $from  = date('Y-m-d', strtotime('-' . RdoRules::MISSING_SCAN_DAYS . ' days'));
        $to    = $today;

        $businessDays = RdoRules::businessDaysInRange($from, $to);
        if (empty($businessDays)) {
            return [];
        }

        $roles         = Permissions::rolesForModule('rdo');
        $professionals = $this->model->getRdoProfessionals($roles);

        $items = [];
        foreach ($professionals as $pro) {
            $filled  = $this->model->getReportDatesForUser((int) $pro['id'], $from, $to);
            $missing = RdoRules::missingDates($businessDays, $filled);
            foreach ($missing as $date) {
                $items[] = [
                    'id'          => null,
                    'type'        => 'missing_report',
                    'report_id'   => null,
                    'owner_name'  => $pro['name'],
                    'report_date' => $date,
                    'payload'     => null,
                ];
            }
        }

        return $items;
    }

    // =========================================================================
    // API: aprovar edição (POST /rdo/approveEdit/{reviewId}) — admin
    // =========================================================================

    /**
     * Aprova uma revisão pendente:
     *  - edit_request  → aplica os campos propostos ao relatório original.
     *  - unlock_request ou late_fill/post_deadline → desbloqueia / reconhece.
     */
    public function approveEdit($reviewId = null)
    {
        $this->requireModule('rdo');
        $this->requireReviewer();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$reviewId) {
            $this->json(['error' => 'Requisição inválida'], 400);
        }

        $review = $this->model->findReview((int) $reviewId);
        if (!$review) $this->json(['error' => 'Revisão não encontrada'], 404);
        if ($review['status'] !== 'pending') $this->json(['error' => 'Esta revisão já foi resolvida.'], 400);

        $admin    = $this->currentUser();
        $reportId = (int) $review['report_id'];
        $report   = $this->model->findById($reportId);
        if (!$report) $this->json(['error' => 'Relatório não encontrado'], 404);

        $notes = trim($_POST['notes'] ?? '');

        // Marca a revisão como aprovada
        $this->model->updateReview((int) $reviewId, [
            'status'      => 'approved',
            'reviewed_by' => (int) $admin['id'],
            'reviewed_at' => date('Y-m-d H:i:s'),
            'notes'       => $notes ?: null,
        ]);

        $updateData = [];

        if ($review['type'] === 'edit_request' && !empty($review['payload_json'])) {
            // Aplica os campos propostos ao relatório.
            $payload  = json_decode($review['payload_json'], true);
            $proposed = $payload['proposed'] ?? [];
            if (!empty($proposed)) {
                // Garante que has_occurrence acompanha occurrences quando presente.
                if (isset($proposed['occurrences'])) {
                    $proposed['has_occurrence'] = RdoRules::deriveHasOccurrence($proposed['occurrences']);
                }
                $updateData = array_merge($updateData, $proposed);
            }
        }

        if ($review['type'] === 'unlock_request') {
            // Desbloqueia o relatório.
            $updateData['is_locked']   = 0;
            $updateData['lock_reason'] = null;
        }

        if (!empty($updateData)) {
            $this->model->update($reportId, $updateData);
        }

        // Reseta review_status se não houver mais pendências.
        $this->resetReviewStatusIfClear($reportId);

        $updatedReport = $this->model->findById($reportId);
        $this->logHistory($reportId, (int) $admin['id'], 'approved', $updatedReport ?: [], (int) $reviewId, $notes ?: null);

        $this->json(['success' => true, 'message' => 'Revisão aprovada.']);
    }

    // =========================================================================
    // API: recusar edição (POST /rdo/rejectEdit/{reviewId}) — admin
    // =========================================================================

    /**
     * Recusa uma revisão pendente:
     *  - edit_request  → descarta o draft; relatório original permanece intacto.
     *  - unlock_request → mantém o bloqueio.
     *  - late_fill/post_deadline → registra a recusa; relatório permanece como está.
     */
    public function rejectEdit($reviewId = null)
    {
        $this->requireModule('rdo');
        $this->requireReviewer();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$reviewId) {
            $this->json(['error' => 'Requisição inválida'], 400);
        }

        $review = $this->model->findReview((int) $reviewId);
        if (!$review) $this->json(['error' => 'Revisão não encontrada'], 404);
        if ($review['status'] !== 'pending') $this->json(['error' => 'Esta revisão já foi resolvida.'], 400);

        $admin    = $this->currentUser();
        $reportId = (int) $review['report_id'];
        $report   = $this->model->findById($reportId);
        if (!$report) $this->json(['error' => 'Relatório não encontrado'], 404);

        $notes = trim($_POST['notes'] ?? '');

        $this->model->updateReview((int) $reviewId, [
            'status'      => 'rejected',
            'reviewed_by' => (int) $admin['id'],
            'reviewed_at' => date('Y-m-d H:i:s'),
            'notes'       => $notes ?: null,
        ]);

        // Reseta review_status se não houver mais pendências.
        $this->resetReviewStatusIfClear($reportId);

        $this->logHistory($reportId, (int) $admin['id'], 'rejected', $report, (int) $reviewId, $notes ?: 'Revisão recusada');

        $this->json(['success' => true, 'message' => 'Revisão recusada.']);
    }
}
