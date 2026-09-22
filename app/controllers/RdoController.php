<?php

/**
 * RDO — Relatório Diário. Registro e acompanhamento das atividades diárias.
 *
 * Visibilidade (RdoRules::canViewReportOf):
 *   - super_admin vê o de todos;
 *   - qualquer outro papel (inclusive developer) vê só o próprio.
 * O acesso ao MÓDULO é liberado a toda a equipe interna (Permissions 'rdo').
 */
class RdoController extends Controller
{
    /** @var DailyReport */
    private $model;

    public function __construct()
    {
        $this->model = new DailyReport();
    }

    /** Aplica o escopo do dono aos filtros quando o papel não tem visão global. */
    private function scopeFilters(array $filters): array
    {
        $user = $this->currentUser();
        if (!RdoRules::hasGlobalView($user['role'] ?? null)) {
            $filters['user_id'] = $user['id'];
        } elseif (!empty($_GET['user_id'])) {
            // super_admin pode filtrar por uma pessoa específica.
            $filters['user_id'] = (int) $_GET['user_id'];
        }
        return $filters;
    }

    /** Garante que o usuário pode ver/editar o relatório; encerra com 403/404 se não. */
    private function requireOwnedOrGlobal($report)
    {
        if (!$report) {
            $this->json(['error' => 'Relatório não encontrado'], 404);
        }
        $user = $this->currentUser();
        if (!RdoRules::canViewReportOf($user['role'] ?? null, $user['id'], $report['user_id'])) {
            $this->json(['error' => 'Sem permissão'], 403);
        }
    }

    // Página principal
    public function index()
    {
        $this->requireModule('rdo');
        $user = $this->currentUser();

        $team = [];
        if (RdoRules::hasGlobalView($user['role'])) {
            // super_admin pode filtrar por pessoa: lista a equipe interna.
            $userModel = new User();
            $team = $userModel->getByRoles(['super_admin', 'developer', 'attendant', 'analyst', 'comercial', 'marketing', 'whatsapp_agent']);
        }

        $this->view('rdo/index', [
            'user' => $user,
            'currentPage' => 'rdo',
            'isGlobal' => RdoRules::hasGlobalView($user['role']),
            'team' => $team,
            'statuses' => RdoRules::STATUSES,
            'statusLabels' => RdoRules::STATUS_LABELS,
        ]);
    }

    // API: listagem (JSON) com filtros + cards de resumo
    public function list()
    {
        $this->requireModule('rdo');

        $filters = [];
        if (!empty($_GET['search'])) $filters['search'] = trim($_GET['search']);
        if (!empty($_GET['date'])) $filters['date'] = $_GET['date'];
        if (!empty($_GET['date_from'])) $filters['date_from'] = $_GET['date_from'];
        if (!empty($_GET['date_to'])) $filters['date_to'] = $_GET['date_to'];
        if (!empty($_GET['status'])) $filters['status'] = $_GET['status'];
        if (isset($_GET['has_occurrence']) && $_GET['has_occurrence'] !== '') {
            $filters['has_occurrence'] = (int) $_GET['has_occurrence'];
        }

        $filters = $this->scopeFilters($filters);

        $this->json([
            'items' => $this->model->getList($filters),
            'stats' => $this->model->getStats($filters),
        ]);
    }

    // API: um relatório com anexos e colaboradores
    public function get($id = null)
    {
        $this->requireModule('rdo');
        if (!$id) $this->json(['error' => 'ID não informado'], 400);

        $report = $this->model->findById($id);
        $this->requireOwnedOrGlobal($report);

        $report['attachments'] = $this->model->getAttachments($id);
        $report['collaborators'] = $this->model->getCollaborators($id);
        $this->json(['item' => $report]);
    }

    // API: criar
    public function create()
    {
        $this->requireModule('rdo');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Método inválido'], 405);
        }
        $user = $this->currentUser();

        $reportDate = $_POST['report_date'] ?? date('Y-m-d');
        // valida formato AAAA-MM-DD; senão usa hoje
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $reportDate)) {
            $reportDate = date('Y-m-d');
        }
        $occurrences = trim($_POST['occurrences'] ?? '');
        $data = [
            'user_id' => $user['id'], // o dono é SEMPRE quem cria (não dá para forjar)
            'report_date' => $reportDate,
            'title' => trim($_POST['title'] ?? '') ?: null,
            'activities' => trim($_POST['activities'] ?? '') ?: null,
            'occurrences' => $occurrences ?: null,
            'has_occurrence' => RdoRules::deriveHasOccurrence($occurrences),
            'status' => RdoRules::normalizeStatus($_POST['status'] ?? '', 'em_andamento'),
            'transcription' => trim($_POST['transcription'] ?? '') ?: null,
        ];
        $id = $this->model->create($data);

        // Colaboradores (opcional): arrays paralelos name[]/kind[]/notes[]
        $this->saveCollaboratorsFromPost($id);

        $this->json(['success' => true, 'id' => $id]);
    }

    // API: atualizar
    public function update($id = null)
    {
        $this->requireModule('rdo');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) {
            $this->json(['error' => 'Requisição inválida'], 400);
        }
        $report = $this->model->findById($id);
        $this->requireOwnedOrGlobal($report);

        $data = [];
        if (isset($_POST['report_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['report_date'])) {
            $data['report_date'] = $_POST['report_date'];
        }
        if (array_key_exists('title', $_POST)) $data['title'] = trim($_POST['title']) ?: null;
        if (array_key_exists('activities', $_POST)) $data['activities'] = trim($_POST['activities']) ?: null;
        if (array_key_exists('occurrences', $_POST)) {
            $occ = trim($_POST['occurrences']);
            $data['occurrences'] = $occ ?: null;
            $data['has_occurrence'] = RdoRules::deriveHasOccurrence($occ);
        }
        if (isset($_POST['status'])) $data['status'] = RdoRules::normalizeStatus($_POST['status'], $report['status']);
        if (array_key_exists('transcription', $_POST)) $data['transcription'] = trim($_POST['transcription']) ?: null;

        if ($data) $this->model->update($id, $data);

        if (isset($_POST['collaborator_name'])) {
            $this->saveCollaboratorsFromPost($id, true);
        }

        $this->json(['success' => true]);
    }

    // API: excluir
    public function delete($id = null)
    {
        $this->requireModule('rdo');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) {
            $this->json(['error' => 'Requisição inválida'], 400);
        }
        $report = $this->model->findById($id);
        $this->requireOwnedOrGlobal($report);

        // Remove arquivos físicos dos anexos antes de apagar (FK ON DELETE CASCADE
        // limpa as linhas, mas não os arquivos).
        foreach ($this->model->getAttachments($id) as $att) {
            $full = PUBLIC_PATH . '/' . $att['file_path'];
            if (is_file($full)) @unlink($full);
        }
        $this->model->delete($id);
        $this->json(['success' => true]);
    }

    // API: upload de anexo (áudio/imagem/arquivo)
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

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $fileName = uniqid('rdo_') . '_' . time() . ($ext ? '.' . strtolower($ext) : '');
        $uploadDir = 'uploads/rdo';
        $fullDir = PUBLIC_PATH . '/' . $uploadDir;
        if (!is_dir($fullDir)) mkdir($fullDir, 0755, true);

        $filePath = $uploadDir . '/' . $fileName;
        if (!move_uploaded_file($file['tmp_name'], PUBLIC_PATH . '/' . $filePath)) {
            $this->json(['error' => 'Erro ao salvar arquivo'], 500);
        }

        $attId = $this->model->addAttachment([
            'report_id' => $id,
            'user_id' => $this->currentUser()['id'],
            'file_name' => $file['name'],
            'file_path' => $filePath,
            'file_type' => $file['type'],
            'file_size' => $file['size'],
        ]);

        $this->json([
            'success' => true,
            'attachment' => [
                'id' => $attId,
                'file_name' => $file['name'],
                'file_path' => $filePath,
                'file_type' => $file['type'],
            ],
        ]);
    }

    // API: excluir anexo
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

    // API: transcrever áudio (base64) e organizar as atividades do dia
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

        $input = json_decode(file_get_contents('php://input'), true);
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

        // Organiza em título + atividades + ocorrências.
        $organized = ['title' => '', 'activities' => $transcription, 'occurrences' => ''];
        $res = $ai->chat([
            ['role' => 'system', 'content' => 'Você organiza relatórios diários de trabalho. Responda APENAS JSON válido.'],
            ['role' => 'user', 'content' =>
                "A partir da transcrição abaixo do relato de um dia de trabalho, retorne um JSON com: "
                . "title (resumo curto do dia), activities (descrição detalhada e organizada das atividades), "
                . "occurrences (impedimentos/ocorrências relatados, ou string vazia se não houver).\n\n"
                . "Transcrição: \"{$transcription}\""
            ],
        ], ['temperature' => 0.3, 'response_format' => ['type' => 'json_object']]);

        if (!empty($res['success'])) {
            $parsed = json_decode($res['content'], true);
            if (is_array($parsed)) {
                $organized = [
                    'title' => trim((string) ($parsed['title'] ?? '')),
                    'activities' => trim((string) ($parsed['activities'] ?? $transcription)),
                    'occurrences' => trim((string) ($parsed['occurrences'] ?? '')),
                ];
            }
        }

        $this->json([
            'success' => true,
            'transcription' => $transcription,
            'organized' => $organized,
        ]);
    }

    /**
     * Lê arrays paralelos collaborator_name[]/collaborator_kind[]/
     * collaborator_notes[] do POST e persiste. Em update ($replace=true),
     * substitui todos; na criação, apenas adiciona.
     */
    private function saveCollaboratorsFromPost($reportId, $replace = false)
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
                'kind' => $kinds[$i] ?? 'colaborador',
                'notes' => $notes[$i] ?? null,
            ];
        }

        if ($replace) {
            $this->model->replaceCollaborators($reportId, $collaborators);
        } else {
            foreach ($collaborators as $c) {
                $this->model->addCollaborator([
                    'report_id' => $reportId,
                    'collaborator_name' => trim($c['collaborator_name']),
                    'kind' => RdoRules::normalizeCollaboratorKind($c['kind']),
                    'notes' => isset($c['notes']) ? (trim((string) $c['notes']) ?: null) : null,
                ]);
            }
        }
    }
}
