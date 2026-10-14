<?php

/**
 * Propostas/orçamentos comerciais (Fase 3 — esteira comercial).
 *
 * Área interna (módulo 'proposals'): listar, criar, editar itens, mudar status,
 * enviar ao cliente. Área PÚBLICA (sem login, por token): o cliente vê a proposta
 * e aceita/recusa (recusa exige motivo).
 */
class ProposalController extends Controller
{
    private $model;

    public function __construct()
    {
        $this->model = new Proposal();
    }

    // ================= Área interna =================

    public function index()
    {
        $this->requireModule('proposals');
        $user = $this->currentUser();
        $filters = [];
        if (!empty($_GET['status']) && ProposalRules::isValidStatus($_GET['status'])) {
            $filters['status'] = $_GET['status'];
        }
        $proposals = $this->model->getAll($filters);
        $leads = [];
        $companies = [];
        try { $leads = (new WhatsappContact())->getLeadsForSelect(); } catch (\Throwable $e) { $leads = []; }
        try { $companies = (new Company())->getAll(); } catch (\Throwable $e) { $companies = []; }
        $this->view('commercial/proposals', [
            'user' => $user,
            'proposals' => $proposals,
            'statuses' => ProposalRules::STATUSES,
            'leads' => $leads,
            'companies' => $companies,
        ]);
    }

    /** Tela de edição (montagem) de uma proposta. */
    public function edit($id = null)
    {
        $this->requireModule('proposals');
        if (!$id) $this->redirect('proposals');
        $proposal = $this->model->findById($id);
        if (!$proposal) { $this->redirect('proposals'); }

        $user = $this->currentUser();
        $leads = [];
        $companies = [];
        try { $leads = (new WhatsappContact())->getLeadsForSelect(); } catch (\Throwable $e) { $leads = []; }
        try { $companies = (new Company())->getAll(); } catch (\Throwable $e) { $companies = []; }
        $this->view('commercial/proposal_form', [
            'user' => $user,
            'proposal' => $proposal,
            'items' => $this->model->getItems($id),
            'events' => $this->model->getEvents($id),
            'services' => (new ServiceCatalog())->getAll(true),
            'contractTypes' => ProposalRules::CONTRACT_TYPES,
            'contractTemplates' => (new ContractTemplate())->getAll(true),
            'leads' => $leads,
            'companies' => $companies,
            'content' => ProposalRules::decodeContent($proposal['content'] ?? null),
            'publicLink' => $this->pdfLink($proposal),
        ]);
    }

    /** Cria uma proposta (POST, JSON). Pode nascer vinculada a um lead/card. */
    public function store()
    {
        $this->requireModule('proposals');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);
        $user = $this->currentUser();

        $title = trim($_POST['title'] ?? '');
        if ($title === '') $this->json(['error' => 'Informe o título da proposta.'], 400);

        $data = [
            'public_token'  => $this->model->generateToken(),
            'title'         => $title,
            'contact_id'    => !empty($_POST['contact_id']) ? (int)$_POST['contact_id'] : null,
            'card_id'       => !empty($_POST['card_id']) ? (int)$_POST['card_id'] : null,
            'company_id'    => !empty($_POST['company_id']) ? (int)$_POST['company_id'] : null,
            'client_name'   => trim($_POST['client_name'] ?? '') ?: null,
            'client_email'  => trim($_POST['client_email'] ?? '') ?: null,
            'client_phone'  => trim($_POST['client_phone'] ?? '') ?: null,
            'contract_type' => ProposalRules::normalizeContractType($_POST['contract_type'] ?? ''),
            'observations'  => trim($_POST['observations'] ?? '') ?: null,
            'validity_date' => trim($_POST['validity_date'] ?? '') ?: null,
            'status'        => ProposalRules::STATUS_DRAFT,
            'total'         => 0,
            'created_by'    => $user['id'],
        ];
        try {
            $id = $this->model->create($data + ['content' => json_encode(ProposalRules::defaultContent(), JSON_UNESCAPED_UNICODE)]);
        } catch (\Throwable $e) {
            $id = $this->model->create($data);
        }
        $this->model->addEvent($id, $user['id'], 'created', 'Proposta criada');
        $this->json(['success' => true, 'id' => $id]);
    }

    /** Salva os dados + itens da proposta (POST, JSON). Recalcula o total. */
    public function save($id = null)
    {
        $this->requireModule('proposals');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        $proposal = $this->model->findById($id);
        if (!$proposal) $this->json(['error' => 'Proposta não encontrada'], 404);
        if (ProposalRules::isTerminal($proposal['status'])) {
            $this->json(['error' => 'Proposta encerrada não pode ser editada.'], 409);
        }
        $user = $this->currentUser();

        $raw = file_get_contents('php://input');
        $body = json_decode($raw, true);
        if (!is_array($body)) $body = $_POST;

        $fields = [
            'title'         => trim($body['title'] ?? $proposal['title']),
            'client_name'   => isset($body['client_name']) ? (trim($body['client_name']) ?: null) : $proposal['client_name'],
            'client_email'  => isset($body['client_email']) ? (trim($body['client_email']) ?: null) : $proposal['client_email'],
            'client_phone'  => isset($body['client_phone']) ? (trim($body['client_phone']) ?: null) : $proposal['client_phone'],
            'contract_type' => ProposalRules::normalizeContractType($body['contract_type'] ?? $proposal['contract_type']),
            'observations'  => isset($body['observations']) ? (trim($body['observations']) ?: null) : $proposal['observations'],
            'validity_date' => array_key_exists('validity_date', $body) ? ProposalRules::normalizeDate($body['validity_date']) : ($proposal['validity_date'] ?? null),
            'dev_start'     => array_key_exists('dev_start', $body) ? ProposalRules::normalizeDate($body['dev_start']) : ($proposal['dev_start'] ?? null),
            'dev_end'       => array_key_exists('dev_end', $body) ? ProposalRules::normalizeDate($body['dev_end']) : ($proposal['dev_end'] ?? null),
        ];
        if (array_key_exists('company_id', $body)) {
            $fields['company_id'] = !empty($body['company_id']) ? (int)$body['company_id'] : null;
        }
        if (array_key_exists('contact_id', $body)) {
            $fields['contact_id'] = !empty($body['contact_id']) ? (int)$body['contact_id'] : null;
        }
        if ($fields['title'] === '') $this->json(['error' => 'Informe o título.'], 400);
        if (!ProposalRules::scheduleOrderOk($fields['dev_start'], $fields['dev_end'])) {
            $this->json(['error' => 'O fim do período de desenvolvimento não pode ser anterior ao início.'], 400);
        }

        $items = is_array($body['items'] ?? null) ? $body['items'] : [];
        foreach ($items as $it) {
            if (!is_array($it)) continue;
            if (!ProposalRules::scheduleOrderOk($it['schedule_start'] ?? null, $it['schedule_end'] ?? null)) {
                $name = trim((string)($it['description'] ?? 'serviço'));
                $this->json(['error' => 'No cronograma de "' . $name . '", a data final não pode ser anterior à inicial.'], 400);
            }
        }

        if (array_key_exists('content', $body)) {
            $content = array_merge(
                ProposalRules::decodeContent($proposal['content'] ?? null),
                ProposalRules::normalizeContent($body['content'])
            );
            $fields['content'] = json_encode($content, JSON_UNESCAPED_UNICODE);
        }
        $fields['fixed_snapshot'] = json_encode($this->fixedSnapshot(), JSON_UNESCAPED_UNICODE);
        try {
            $this->model->update($id, $fields);
            $total = $this->model->replaceItems($id, $items);
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            if (stripos($msg, 'Unknown column') !== false || stripos($msg, "doesn't exist") !== false) {
                $this->json(['error' => 'Falta aplicar a migration 162_proposal_document.sql neste banco.'], 500);
            }
            $this->json(['error' => 'Não foi possível salvar a proposta.'], 500);
        }
        $this->model->addEvent($id, $user['id'], 'edited', 'Proposta editada');

        $this->json(['success' => true, 'total' => $total]);
    }

    /** Muda o status (ready/sent/awaiting/cancelled) com validação de transição. */
    public function status($id = null)
    {
        $this->requireModule('proposals');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        $proposal = $this->model->findById($id);
        if (!$proposal) $this->json(['error' => 'Proposta não encontrada'], 404);
        $user = $this->currentUser();

        $new = ProposalRules::normalizeStatus($_POST['status'] ?? '');
        $extra = [];
        if ($new === ProposalRules::STATUS_SENT) $extra['sent_at'] = date('Y-m-d H:i:s');

        if (!$this->model->changeStatus($id, $new, $user['id'], $extra)) {
            $this->json(['error' => 'Transição de status não permitida.'], 409);
        }
        $this->json(['success' => true, 'status' => $new]);
    }

    /** Marca como enviada e devolve o link público (para enviar ao cliente). */
    public function send($id = null)
    {
        $this->requireModule('proposals');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        $proposal = $this->model->findById($id);
        if (!$proposal) $this->json(['error' => 'Proposta não encontrada'], 404);
        $user = $this->currentUser();

        // O cliente recebe o PDF: sem gerador no servidor, não envia nada.
        try {
            $this->buildPdf($proposal);
        } catch (\Throwable $e) {
            $this->json(['error' => 'Não foi possível gerar o PDF da proposta: ' . $e->getMessage()], 500);
        }

        // Se ainda está em elaboração, promove para "montada" antes de enviar.
        if ($proposal['status'] === ProposalRules::STATUS_DRAFT) {
            $this->model->changeStatus($id, ProposalRules::STATUS_READY, $user['id']);
        }
        $this->model->changeStatus($id, ProposalRules::STATUS_SENT, $user['id'], ['sent_at' => date('Y-m-d H:i:s')]);
        $link = $this->pdfLink($proposal);

        // Envia o link ao CLIENTE por WhatsApp + e-mail (quando houver contato).
        $delivery = $this->deliverToClient($proposal, $link);

        $this->json([
            'success' => true,
            'link' => $link,
            'sent_whats' => $delivery['sent_whats'],
            'sent_email' => $delivery['sent_email'],
            'no_contact' => $delivery['no_contact'],
        ]);
    }

    /**
     * Envia o link da proposta ao cliente (WhatsApp + e-mail). Nunca interrompe:
     * canais são complementares. Retorna o que foi enviado.
     */
    private function deliverToClient(array $proposal, string $link): array
    {
        $out = ['sent_whats' => 0, 'sent_email' => 0, 'no_contact' => true];
        $c = ProposalDelivery::clientContact($proposal);
        $company = trim((string) Config::get('app_name')) ?: null;
        $title = $proposal['title'] ?? null;

        if (!empty($c['phone'])) {
            $out['no_contact'] = false;
            $msg = ProposalDelivery::clientWhatsapp($c['name'], $link, $title, $company);
            try { if (WhatsappNotifier::sendToPhone($c['phone'], $msg, $c['name'])) $out['sent_whats']++; }
            catch (\Throwable $e) { /* não interrompe */ }
        }
        if (!empty($c['email'])) {
            $out['no_contact'] = false;
            $subject = ProposalDelivery::clientEmailSubject($title);
            $html = Mailer::template($subject, ProposalDelivery::clientEmailBody($c['name'], $link, $title));
            try { if (Mailer::send($c['email'], $subject, $html)) $out['sent_email']++; }
            catch (\Throwable $e) { /* não interrompe */ }
        }
        return $out;
    }

    // ================= Área pública (sem login, por token) =================

    /**
     * PDF da proposta (link enviado ao cliente). Abre no visualizador do
     * navegador; a última página traz o link de aceite.
     */
    public function file($token = null)
    {
        $token = preg_replace('/\.pdf$/i', '', (string)$this->tokenFromUrl($token));
        $proposal = $token ? $this->model->findByToken($token) : null;
        if (!$proposal) { $this->renderSimple('Proposta indisponível', 'Este link de proposta não é válido.'); return; }

        if ($proposal['status'] === ProposalRules::STATUS_SENT) {
            $this->model->changeStatus($proposal['id'], ProposalRules::STATUS_AWAITING, null);
        }
        $this->outputPdf($proposal);
    }

    /** Prévia interna do PDF, exatamente como o cliente vai receber. */
    public function pdf($id = null)
    {
        $this->requireModule('proposals');
        $proposal = $id ? $this->model->findById($id) : null;
        if (!$proposal) $this->redirect('proposal');
        $this->outputPdf($proposal);
    }

    /**
     * Página de aceite/recusa (link que está dentro do PDF).
     * Nome 'show' (não 'view') para não colidir com Controller::view().
     */
    public function show($token = null)
    {
        $token = $this->tokenFromUrl($token);
        $proposal = $token ? $this->model->findByToken($token) : null;
        if (!$proposal) { $this->renderSimple('Proposta indisponível', 'Este link de proposta não é válido.'); return; }

        if ($proposal['status'] === ProposalRules::STATUS_SENT) {
            $this->model->changeStatus($proposal['id'], ProposalRules::STATUS_AWAITING, null);
            $proposal['status'] = ProposalRules::STATUS_AWAITING;
        }
        $items = $this->model->getItems($proposal['id']);
        $this->view('commercial/proposal_public', [
            'proposal' => $proposal,
            'totals' => ProposalRules::totals($items),
            'pdfLink' => $this->pdfLink($proposal),
            'isTerminal' => ProposalRules::isTerminal($proposal['status']),
        ]);
    }

    /** Cliente aceita a proposta (POST público). */
    public function accept($token = null)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);
        $token = $this->tokenFromUrl($token);
        $proposal = $token ? $this->model->findByToken($token) : null;
        if (!$proposal) $this->json(['error' => 'Proposta não encontrada'], 404);
        if (ProposalRules::isTerminal($proposal['status'])) {
            $this->json(['error' => 'Esta proposta já foi finalizada.'], 409);
        }

        $respondedAt = date('Y-m-d H:i:s');
        $this->markSentForResponse($proposal);
        if (!$this->model->changeStatus($proposal['id'], ProposalRules::STATUS_ACCEPTED, null, ['responded_at' => $respondedAt])) {
            $this->json(['error' => 'Não foi possível registrar o aceite. Tente novamente.'], 409);
        }
        $this->model->addEvent($proposal['id'], null, 'accepted', 'Cliente aceitou a proposta');
        $this->notifyTeamResponse($proposal, 'accepted');
        $this->json(['success' => true, 'status' => ProposalRules::STATUS_ACCEPTED, 'responded_at' => date('d/m/Y \à\s H:i', strtotime($respondedAt))]);
    }

    /**
     * O cliente pode responder pelo link mesmo antes do envio formal (link copiado
     * do painel). A proposta passa por "enviada" para que aceite/recusa sejam
     * transições válidas.
     */
    private function markSentForResponse(array $proposal): void
    {
        $id = (int)$proposal['id'];
        if ($proposal['status'] === ProposalRules::STATUS_DRAFT) {
            $this->model->changeStatus($id, ProposalRules::STATUS_READY, null);
            $proposal['status'] = ProposalRules::STATUS_READY;
        }
        if ($proposal['status'] === ProposalRules::STATUS_READY) {
            $extra = empty($proposal['sent_at']) ? ['sent_at' => date('Y-m-d H:i:s')] : [];
            $this->model->changeStatus($id, ProposalRules::STATUS_SENT, null, $extra);
        }
    }

    /** Cliente recusa a proposta (POST público) — motivo obrigatório. */
    public function reject($token = null)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);
        $token = $this->tokenFromUrl($token);
        $proposal = $token ? $this->model->findByToken($token) : null;
        if (!$proposal) $this->json(['error' => 'Proposta não encontrada'], 404);
        if (ProposalRules::isTerminal($proposal['status'])) {
            $this->json(['error' => 'Esta proposta já foi finalizada.'], 409);
        }

        $reason = ProposalRules::sanitizeRejectReason($_POST['reason'] ?? '');
        if ($reason === null) $this->json(['error' => 'Informe o motivo da recusa.'], 400);

        $this->markSentForResponse($proposal);
        if (!$this->model->changeStatus($proposal['id'], ProposalRules::STATUS_REJECTED, null, [
            'responded_at' => date('Y-m-d H:i:s'),
            'reject_reason' => $reason,
        ])) {
            $this->json(['error' => 'Não foi possível registrar a recusa. Tente novamente.'], 409);
        }
        $this->model->addEvent($proposal['id'], null, 'rejected', 'Cliente recusou. Motivo: ' . $reason);
        $this->notifyTeamResponse($proposal, 'rejected', $reason);

        // Volta o card do lead para "Análise interna" (refazer a proposta) — não é
        // perda, é retrabalho. Nunca interrompe a resposta ao cliente.
        if (!empty($proposal['contact_id'])) {
            try {
                (new CrmBoard())->moveContactToColumn((int)$proposal['contact_id'], CrmRules::COLUMN_INTERNAL_ANALYSIS);
            } catch (\Throwable $e) { /* não interrompe */ }
        }
        $this->json(['success' => true]);
    }

    /** Texto fixo da proposta (editável, igual para todas). */
    public function blocks()
    {
        $this->requireModule('proposals');
        $user = $this->currentUser();
        $blocks = [];
        $missing = false;
        try {
            $blocks = (new ProposalBlock())->getAll();
        } catch (\Throwable $e) {
            $missing = true;
            $blocks = ProposalRules::defaultBlocks();
        }
        $this->view('commercial/proposal_blocks', [
            'user' => $user,
            'blocks' => $blocks,
            'missing' => $missing,
        ]);
    }

    /** Salva o texto fixo (POST JSON). */
    public function saveBlocks()
    {
        $this->requireModule('proposals');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);
        $raw = file_get_contents('php://input');
        $body = json_decode($raw, true);
        if (!is_array($body)) $this->json(['error' => 'Requisição inválida'], 400);
        try {
            (new ProposalBlock())->saveAll(is_array($body['blocks'] ?? null) ? $body['blocks'] : []);
        } catch (\Throwable $e) {
            $this->json(['error' => 'Não foi possível salvar. Aplique a migration 162_proposal_document.'], 500);
        }
        $this->json(['success' => true]);
    }

    /**
     * Cópia do texto fixo para gravar na proposta. Se a tabela ainda não
     * existir, usa o texto padrão para o PDF não sair vazio.
     */
    private function fixedSnapshot(): array
    {
        try {
            return (new ProposalBlock())->snapshot();
        } catch (\Throwable $e) {
            $out = [];
            foreach (ProposalRules::defaultBlocks() as $b) {
                $out[] = ['block_key' => $b['block_key'], 'title' => $b['title'], 'body' => $b['body']];
            }
            return $out;
        }
    }

    private function pdfLink(array $proposal): string
    {
        return $this->publicBase() . '/proposal/file/' . $proposal['public_token'];
    }

    private function outputPdf(array $proposal): void
    {
        try {
            $pdf = $this->buildPdf($proposal);
        } catch (\Throwable $e) {
            $this->renderSimple('PDF indisponível', 'Não foi possível gerar o PDF da proposta: ' . $e->getMessage());
            return;
        }
        $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string)$proposal['title']) ?: '')), '-');
        $name = 'proposta-' . (int)$proposal['id'] . ($slug !== '' ? '-' . substr($slug, 0, 60) : '') . '.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $name . '"');
        header('Content-Length: ' . strlen($pdf));
        header('Cache-Control: private, no-store');
        echo $pdf;
        exit;
    }

    /** Monta o HTML da proposta (texto fixo + dados variáveis) e converte em PDF. */
    private function buildPdf(array $proposal): string
    {
        $items = $this->model->getItems($proposal['id']);
        $current = ProposalRules::decodeBlocks(json_encode($this->fixedSnapshot()));
        $blocks = ProposalRules::decodeBlocks($proposal['fixed_snapshot'] ?? null) ?: $current;
        foreach ($current as $key => $block) {
            if (!isset($blocks[$key]) || ProposalRules::isLegacyBlock($key, $blocks[$key]['body'])) $blocks[$key] = $block;
        }
        $content = ProposalRules::decodeContent($proposal['content'] ?? null);
        $timeline = ProposalRules::timeline($items, $proposal['dev_start'] ?? null, $proposal['dev_end'] ?? null);
        $totals = ProposalRules::totals($items);
        $projection = ProposalRules::projection($items, $timeline['start'], $timeline['end'], $content['expected_return'] ?? null);

        $brl = fn($v) => 'R$ ' . number_format((float)$v, 2, ',', '.');
        $br = fn($d) => $d ? date('d/m/Y', strtotime($d)) : 'a definir';
        $vars = [
            'cliente' => (string)($proposal['client_name'] ?? ''),
            'total' => $brl($totals['year']),
            'total_pontual' => $brl($totals['once']),
            'total_mensal' => $brl($totals['monthly']),
            'total_12_meses' => $brl($totals['year']),
            'condicao_pagamento' => ($content['payment_method'] ?? '') !== '' ? $content['payment_method'] : 'a combinar',
            'validade' => $br($proposal['validity_date'] ?? null),
            'inicio' => $br($timeline['start']),
            'fim' => $br($timeline['end']),
        ];

        $public = defined('PUBLIC_PATH') ? PUBLIC_PATH : BASE_PATH . '/public';
        return ProposalPdf::render([
            'proposal' => $proposal,
            'items' => $items,
            'blocks' => $blocks,
            'content' => $content,
            'timeline' => $timeline,
            'totals' => $totals,
            'projection' => $projection,
            'vars' => $vars,
            'acceptLink' => $this->publicBase() . '/proposal/show/' . $proposal['public_token'],
        ], $public);
    }

    // ================= Helpers =================

    /** Notifica o criador da proposta (reaproveita a tabela notifications). */
    private function notifyTeam($proposal, $title, $message)
    {
        if (empty($proposal['created_by'])) return;
        try {
            Database::getInstance()->insert('notifications', [
                'user_id' => (int)$proposal['created_by'],
                'title' => $title,
                'message' => $message,
                'type' => 'system',
            ]);
        } catch (\Throwable $e) { /* não interrompe a resposta ao cliente */ }
    }

    /**
     * Avisa a equipe quando o cliente responde: sino (criador) + WhatsApp pessoal
     * do criador (se tiver telefone) + WhatsApp do grupo padrão. Nunca interrompe.
     */
    private function notifyTeamResponse(array $proposal, string $event, ?string $reason = null): void
    {
        $title = $proposal['title'] ?? null;
        $sinoTitle = $event === 'accepted' ? 'Proposta aceita' : 'Proposta recusada';
        $sinoMsg = $event === 'accepted'
            ? "O cliente aceitou a proposta \"{$title}\"."
            : "O cliente recusou a proposta \"{$title}\"." . ($reason ? " Motivo: {$reason}" : '');
        $this->notifyTeam($proposal, $sinoTitle, $sinoMsg);

        $wa = ProposalDelivery::teamWhatsapp($event, $title, $reason);

        // WhatsApp pessoal do criador (se tiver telefone cadastrado).
        try {
            if (!empty($proposal['created_by'])) {
                $creator = (new User())->findById((int)$proposal['created_by']);
                if ($creator && !empty($creator['phone'])) {
                    WhatsappNotifier::sendToPhone($creator['phone'], $wa, $creator['name'] ?? null);
                }
            }
        } catch (\Throwable $e) { /* não interrompe */ }

        // WhatsApp do grupo da equipe (se habilitado nas Settings).
        try { WhatsappNotifier::sendToDefaultGroup($wa); } catch (\Throwable $e) { /* não interrompe */ }
    }

    private function publicBase(): string
    {
        return rtrim((string) Config::get('app_public_url'), '/') ?: rtrim(baseUrl(''), '/');
    }

    /** Extrai o token do último segmento da URL (ou do parâmetro recebido). */
    private function tokenFromUrl($token)
    {
        if ($token) return trim((string)$token);
        $url = $_GET['url'] ?? '';
        $parts = array_values(array_filter(explode('/', $url)));
        return $parts ? trim(end($parts)) : '';
    }

    /** Página simples de mensagem (link inválido). */
    private function renderSimple($title, $message)
    {
        $t = htmlspecialchars($title, ENT_QUOTES);
        $m = htmlspecialchars($message, ENT_QUOTES);
        http_response_code(200);
        echo "<!DOCTYPE html><html lang='pt-BR'><head><meta charset='UTF-8'>"
            . "<meta name='viewport' content='width=device-width, initial-scale=1'>"
            . "<title>{$t}</title></head><body style='font-family:system-ui;background:#0f1020;color:#e8eaf1;'>"
            . "<div style='max-width:440px;margin:14vh auto;background:#1a1c2e;border-radius:16px;padding:32px;text-align:center;'>"
            . "<h3>{$t}</h3><p style='color:#9aa2c0;'>{$m}</p></div></body></html>";
        exit;
    }
}
