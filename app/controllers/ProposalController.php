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
        try { $leads = (new WhatsappContact())->getLeadsForSelect(); } catch (\Throwable $e) { $leads = []; }
        $this->view('commercial/proposals', [
            'user' => $user,
            'proposals' => $proposals,
            'statuses' => ProposalRules::STATUSES,
            'leads' => $leads,
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
        $this->view('commercial/proposal_form', [
            'user' => $user,
            'proposal' => $proposal,
            'items' => $this->model->getItems($id),
            'events' => $this->model->getEvents($id),
            'services' => (new ServiceCatalog())->getAll(true),
            'contractTypes' => ProposalRules::CONTRACT_TYPES,
            'contractTemplates' => (new ContractTemplate())->getAll(true),
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

        $id = $this->model->create([
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
        ]);
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
            'validity_date' => isset($body['validity_date']) ? (trim($body['validity_date']) ?: null) : $proposal['validity_date'],
        ];
        if ($fields['title'] === '') $this->json(['error' => 'Informe o título.'], 400);
        $this->model->update($id, $fields);

        $items = is_array($body['items'] ?? null) ? $body['items'] : [];
        $total = $this->model->replaceItems($id, $items);
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

        // Se ainda está em elaboração, promove para "montada" antes de enviar.
        if ($proposal['status'] === ProposalRules::STATUS_DRAFT) {
            $this->model->changeStatus($id, ProposalRules::STATUS_READY, $user['id']);
        }
        $this->model->changeStatus($id, ProposalRules::STATUS_SENT, $user['id'], ['sent_at' => date('Y-m-d H:i:s')]);
        $link = $this->publicBase() . '/proposal/show/' . $proposal['public_token'];

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
     * Página pública da proposta (link enviado ao cliente).
     * Nome 'show' (não 'view') para não colidir com Controller::view(), que
     * renderiza templates e é usado internamente aqui.
     */
    public function show($token = null)
    {
        $token = $this->tokenFromUrl($token);
        $proposal = $token ? $this->model->findByToken($token) : null;
        if (!$proposal) { $this->renderSimple('Proposta indisponível', 'Este link de proposta não é válido.'); return; }

        // Primeira abertura após envio: marca como "aguardando retorno".
        if ($proposal['status'] === ProposalRules::STATUS_SENT) {
            $this->model->changeStatus($proposal['id'], ProposalRules::STATUS_AWAITING, null);
        }
        $this->view('commercial/proposal_public', [
            'proposal' => $proposal,
            'items' => $this->model->getItems($proposal['id']),
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

        $this->model->changeStatus($proposal['id'], ProposalRules::STATUS_ACCEPTED, null, ['responded_at' => date('Y-m-d H:i:s')]);
        $this->model->addEvent($proposal['id'], null, 'accepted', 'Cliente aceitou a proposta');
        $this->notifyTeam($proposal, 'Proposta aceita', "O cliente aceitou a proposta \"{$proposal['title']}\".");
        // Aviso à equipe pelo WhatsApp do grupo (complementar ao sino).
        try { WhatsappNotifier::sendToDefaultGroup(ProposalDelivery::teamWhatsapp('accepted', $proposal['title'] ?? null)); }
        catch (\Throwable $e) { /* não interrompe */ }
        $this->json(['success' => true]);
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

        $this->model->changeStatus($proposal['id'], ProposalRules::STATUS_REJECTED, null, [
            'responded_at' => date('Y-m-d H:i:s'),
            'reject_reason' => $reason,
        ]);
        $this->model->addEvent($proposal['id'], null, 'rejected', 'Cliente recusou. Motivo: ' . $reason);
        $this->notifyTeam($proposal, 'Proposta recusada', "O cliente recusou a proposta \"{$proposal['title']}\". Motivo: {$reason}");
        // Aviso à equipe pelo WhatsApp do grupo (complementar ao sino).
        try { WhatsappNotifier::sendToDefaultGroup(ProposalDelivery::teamWhatsapp('rejected', $proposal['title'] ?? null, $reason)); }
        catch (\Throwable $e) { /* não interrompe */ }
        $this->json(['success' => true]);
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
