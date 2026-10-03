<?php

/**
 * Contratos comerciais (Fase 4 — esteira comercial).
 *
 * Interno (módulo 'contracts'): listar, criar a partir de proposta aceita (com
 * modelo), editar corpo, enviar ao cliente para aprovar, enviar para assinatura
 * (ClickSign). Público (por token): o cliente APROVA o contrato ou pede ajuste
 * (motivo). Webhook (sem login): a ClickSign confirma a assinatura.
 */
class ContractController extends Controller
{
    private $model;

    public function __construct()
    {
        $this->model = new Contract();
    }

    // ================= Área interna =================

    public function index()
    {
        $this->requireModule('contracts');
        $user = $this->currentUser();
        $filters = [];
        if (!empty($_GET['status']) && ContractRules::isValidStatus($_GET['status'])) {
            $filters['status'] = $_GET['status'];
        }
        $contracts = $this->model->getAll($filters);
        $this->view('commercial/contracts', ['user' => $user, 'contracts' => $contracts, 'statuses' => ContractRules::STATUSES]);
    }

    public function edit($id = null)
    {
        $this->requireModule('contracts');
        if (!$id) $this->redirect('contracts');
        $contract = $this->model->findById($id);
        if (!$contract) $this->redirect('contracts');
        $user = $this->currentUser();
        $this->view('commercial/contract_form', [
            'user' => $user,
            'contract' => $contract,
            'events' => $this->model->getEvents($id),
            'canEdit' => ContractRules::canEditBody($contract['status']),
            'canSign' => ContractRules::canSendToSignature($contract['status']),
        ]);
    }

    /**
     * Cria um contrato a partir de uma proposta ACEITA, usando um modelo.
     * POST: proposal_id, template_id.
     */
    public function fromProposal()
    {
        $this->requireModule('contracts');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);
        $user = $this->currentUser();

        $proposalId = (int)($_POST['proposal_id'] ?? 0);
        $proposal = $proposalId ? (new Proposal())->findById($proposalId) : null;
        if (!$proposal) $this->json(['error' => 'Proposta não encontrada.'], 404);
        if ($proposal['status'] !== ProposalRules::STATUS_ACCEPTED) {
            $this->json(['error' => 'O contrato só pode ser gerado a partir de uma proposta aceita.'], 409);
        }

        $template = null;
        if (!empty($_POST['template_id'])) {
            $template = (new ContractTemplate())->findById((int)$_POST['template_id']);
        }

        $id = $this->model->createFromProposal($proposal, $template, $user['id']);
        $this->model->addEvent($id, $user['id'], 'created', 'Contrato criado a partir da proposta #' . $proposalId);
        $this->json(['success' => true, 'id' => $id]);
    }

    /** Salva o corpo/dados do contrato (POST, JSON). Só se editável. */
    public function save($id = null)
    {
        $this->requireModule('contracts');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        $contract = $this->model->findById($id);
        if (!$contract) $this->json(['error' => 'Contrato não encontrado'], 404);
        if (!ContractRules::canEditBody($contract['status'])) {
            $this->json(['error' => 'Este contrato não pode mais ser editado.'], 409);
        }
        $user = $this->currentUser();

        $raw = file_get_contents('php://input');
        $body = json_decode($raw, true);
        if (!is_array($body)) $body = $_POST;

        $this->model->update($id, [
            'title' => trim($body['title'] ?? $contract['title']) ?: $contract['title'],
            'body'  => (string)($body['body'] ?? $contract['body']),
            'client_name'  => isset($body['client_name']) ? (trim($body['client_name']) ?: null) : $contract['client_name'],
            'client_email' => isset($body['client_email']) ? (trim($body['client_email']) ?: null) : $contract['client_email'],
            'client_phone' => isset($body['client_phone']) ? (trim($body['client_phone']) ?: null) : $contract['client_phone'],
        ]);
        $this->model->addEvent($id, $user['id'], 'edited', 'Contrato editado');
        $this->json(['success' => true]);
    }

    /** Envia o contrato ao cliente para APROVAÇÃO (status client_review). */
    public function sendForReview($id = null)
    {
        $this->requireModule('contracts');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        $contract = $this->model->findById($id);
        if (!$contract) $this->json(['error' => 'Contrato não encontrado'], 404);
        $user = $this->currentUser();

        if (!$this->model->changeStatus($id, ContractRules::STATUS_CLIENT_REVIEW, $user['id'])) {
            $this->json(['error' => 'Não é possível enviar para aprovação neste estado.'], 409);
        }
        $link = $this->publicBase() . '/contract/view/' . $contract['public_token'];
        $this->json(['success' => true, 'link' => $link]);
    }

    /**
     * Envia o contrato para ASSINATURA na ClickSign (status awaiting_signature).
     * Só quando o cliente já aprovou. Cria documento + signatário + lista.
     */
    public function sendForSignature($id = null)
    {
        $this->requireModule('contracts');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        $contract = $this->model->findById($id);
        if (!$contract) $this->json(['error' => 'Contrato não encontrado'], 404);
        if (!ContractRules::canSendToSignature($contract['status'])) {
            $this->json(['error' => 'O contrato precisa estar aprovado pelo cliente antes de assinar.'], 409);
        }
        if (empty($contract['client_email'])) {
            $this->json(['error' => 'Informe o e-mail do cliente para a assinatura.'], 400);
        }
        $user = $this->currentUser();

        $api = new ClickSignApi();
        if (!$api->isConfigured()) {
            $this->json(['error' => 'Integração ClickSign não configurada (defina clicksign_access_token em Configurações).'], 400);
        }

        // 1) Cria o documento na ClickSign a partir do corpo (HTML -> base64 data URI).
        $html = '<html><meta charset="utf-8"><body>' . ($contract['body'] ?? '') . '</body></html>';
        $dataUri = 'data:text/html;base64,' . base64_encode($html);
        $path = '/helpdeskon/contrato-' . $contract['id'] . '-' . substr($contract['public_token'], 0, 8) . '.html';
        $doc = $api->createDocument($path, $dataUri);
        if (empty($doc['success']) || empty($doc['data']['document']['key'])) {
            $this->json(['error' => 'Falha ao criar o documento na ClickSign: ' . ($doc['error'] ?? 'desconhecido')], 502);
        }
        $docKey = $doc['data']['document']['key'];

        // 2) Cria o signatário (cliente) e 3) vincula ao documento.
        $signer = $api->createSigner($contract['client_email'], $contract['client_name'] ?: 'Cliente', $contract['client_phone'] ?? null);
        if (empty($signer['success']) || empty($signer['data']['signer']['key'])) {
            $this->json(['error' => 'Falha ao criar o signatário na ClickSign.'], 502);
        }
        $signerKey = $signer['data']['signer']['key'];
        $list = $api->addSigner($docKey, $signerKey, 'sign');
        $reqKey = $list['data']['list']['request_signature_key'] ?? null;

        $this->model->changeStatus($id, ContractRules::STATUS_AWAITING_SIGNATURE, $user['id'], [
            'clicksign_doc_key' => $docKey,
            'clicksign_signer_key' => $signerKey,
            'clicksign_request_key' => $reqKey,
            'sent_signature_at' => date('Y-m-d H:i:s'),
        ]);
        $this->model->addEvent($id, $user['id'], 'signature_sent', 'Enviado para assinatura na ClickSign');
        $this->json(['success' => true]);
    }

    // ================= Área pública (cliente, por token) =================

    public function view($token = null)
    {
        $token = $this->tokenFromUrl($token);
        $contract = $token ? $this->model->findByToken($token) : null;
        if (!$contract) { $this->renderSimple('Contrato indisponível', 'Este link de contrato não é válido.'); return; }
        $this->view('commercial/contract_public', [
            'contract' => $contract,
            'canDecide' => ($contract['status'] === ContractRules::STATUS_CLIENT_REVIEW),
        ]);
    }

    /** Cliente aprova o conteúdo do contrato (POST público). */
    public function approve($token = null)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);
        $token = $this->tokenFromUrl($token);
        $contract = $token ? $this->model->findByToken($token) : null;
        if (!$contract) $this->json(['error' => 'Contrato não encontrado'], 404);
        if ($contract['status'] !== ContractRules::STATUS_CLIENT_REVIEW) {
            $this->json(['error' => 'Este contrato não está aguardando sua aprovação.'], 409);
        }
        $this->model->changeStatus($contract['id'], ContractRules::STATUS_APPROVED, null, ['approved_at' => date('Y-m-d H:i:s')]);
        $this->model->addEvent($contract['id'], null, 'approved', 'Cliente aprovou o contrato');
        $this->notifyTeam($contract, 'Contrato aprovado', "O cliente aprovou o contrato \"{$contract['title']}\". Pronto para enviar à assinatura.");
        $this->json(['success' => true]);
    }

    /** Cliente pede ajuste (POST público) — motivo obrigatório. */
    public function requestChanges($token = null)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);
        $token = $this->tokenFromUrl($token);
        $contract = $token ? $this->model->findByToken($token) : null;
        if (!$contract) $this->json(['error' => 'Contrato não encontrado'], 404);
        if ($contract['status'] !== ContractRules::STATUS_CLIENT_REVIEW) {
            $this->json(['error' => 'Este contrato não está aguardando sua revisão.'], 409);
        }
        $reason = ContractRules::sanitizeRejectReason($_POST['reason'] ?? '');
        if ($reason === null) $this->json(['error' => 'Informe o que precisa ser ajustado.'], 400);

        $this->model->changeStatus($contract['id'], ContractRules::STATUS_CLIENT_REJECTED, null, ['reject_reason' => $reason]);
        $this->model->addEvent($contract['id'], null, 'rejected', 'Cliente pediu ajuste: ' . $reason);
        $this->notifyTeam($contract, 'Contrato: ajuste solicitado', "O cliente pediu ajustes no contrato \"{$contract['title']}\": {$reason}");
        $this->json(['success' => true]);
    }

    // ================= Webhook ClickSign (sem login) =================

    /**
     * Recebe os eventos da ClickSign. Valida o HMAC e, quando o documento fecha
     * (assinatura concluída), marca o contrato como 'signed'. Idempotente.
     */
    public function clicksignWebhook()
    {
        $raw = file_get_contents('php://input');
        $secret = (string) Config::get('clicksign_webhook_secret');
        $sig = $_SERVER['HTTP_CONTENT_HMAC'] ?? ($_SERVER['HTTP_X_CLICKSIGN_SIGNATURE'] ?? ($_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? ''));

        // Se há secret configurado, a assinatura é obrigatória e deve bater.
        if ($secret !== '' && !ClickSignRules::verifyWebhookSignature($raw, $sig, $secret)) {
            http_response_code(401);
            echo json_encode(['error' => 'assinatura inválida']);
            return;
        }

        $payload = json_decode($raw, true);
        $eventName = $payload['event']['name'] ?? ($payload['event'] ?? null);
        $docKey = $payload['document']['key'] ?? ($payload['data']['document']['key'] ?? null);
        $action = ClickSignRules::interpretEvent(is_string($eventName) ? $eventName : null);

        if ($docKey && $action === 'signed') {
            $contract = $this->model->findByClickSignDocKey($docKey);
            if ($contract && $contract['status'] !== ContractRules::STATUS_SIGNED) {
                $this->model->changeStatus($contract['id'], ContractRules::STATUS_SIGNED, null, ['signed_at' => date('Y-m-d H:i:s')]);
                $this->model->addEvent($contract['id'], null, 'signed', 'Assinatura confirmada pela ClickSign');
                $this->notifyTeam($contract, 'Contrato assinado', "O contrato \"{$contract['title']}\" foi assinado. Siga para o financeiro.");
            }
        } elseif ($docKey && $action === 'cancelled') {
            $contract = $this->model->findByClickSignDocKey($docKey);
            if ($contract && !ContractRules::isTerminal($contract['status'])) {
                $this->model->changeStatus($contract['id'], ContractRules::STATUS_CANCELLED, null);
                $this->model->addEvent($contract['id'], null, 'cancelled', 'Documento cancelado na ClickSign');
            }
        }
        http_response_code(200);
        echo json_encode(['ok' => true]);
    }

    // ================= Helpers =================

    private function notifyTeam($contract, $title, $message)
    {
        if (empty($contract['created_by'])) return;
        try {
            Database::getInstance()->insert('notifications', [
                'user_id' => (int)$contract['created_by'], 'title' => $title, 'message' => $message, 'type' => 'system',
            ]);
        } catch (\Throwable $e) { /* não interrompe */ }
    }

    private function publicBase(): string
    {
        return rtrim((string) Config::get('app_public_url'), '/') ?: rtrim(baseUrl(''), '/');
    }

    private function tokenFromUrl($token)
    {
        if ($token) return trim((string)$token);
        $parts = array_values(array_filter(explode('/', $_GET['url'] ?? '')));
        return $parts ? trim(end($parts)) : '';
    }

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
