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

    /**
     * Diagnóstico da integração ClickSign (só super_admin). Mostra, sem revelar
     * o token, se está configurado, para qual ambiente aponta e o resultado de
     * uma chamada de teste (GET /documents) — útil para entender erros 401/403.
     * Acesse: /contract/clicksignDiag
     */
    public function clicksignDiag()
    {
        $this->requireRole(['super_admin']);
        header('Content-Type: text/plain; charset=utf-8');

        $token = (string) Config::get('clicksign_access_token');
        $sandboxCfg = ((string) Config::get('clicksign_sandbox')) === '1';
        $isHmlg = (stripos($token, '_hmlg_') !== false || stripos($token, '_test_') !== false);
        $sandboxEfetivo = $sandboxCfg || $isHmlg;

        echo "=== Diagnóstico ClickSign ===\n";
        echo "Token configurado: " . ($token !== '' ? ('sim (' . strlen($token) . ' caracteres, começa com "' . substr($token, 0, 4) . '…")') : 'NÃO') . "\n";
        echo "Checkbox sandbox nas Settings: " . ($sandboxCfg ? 'marcado' : 'desmarcado') . "\n";
        echo "Token parece de homologação (_hmlg_/_test_): " . ($isHmlg ? 'sim' : 'não') . "\n";
        echo "Ambiente efetivo usado: " . ($sandboxEfetivo ? 'SANDBOX (sandbox.clicksign.com)' : 'PRODUÇÃO (app.clicksign.com)') . "\n";
        echo "Webhook secret configurado: " . (((string) Config::get('clicksign_webhook_secret')) !== '' ? 'sim' : 'não') . "\n\n";

        if ($token === '') {
            echo "AÇÃO: cole o Access Token em Configurações → ClickSign e salve.\n";
            return;
        }

        // Chamada de teste: lista documentos (GET). Mostra o que a ClickSign responde.
        $base = $sandboxEfetivo ? ClickSignRules::BASE_SANDBOX : ClickSignRules::BASE_PROD;
        $url = $base . '/api/v1/documents?access_token=' . rawurlencode($token);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json'],
        ]);
        $raw = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        echo "Teste GET /api/v1/documents -> HTTP {$http}\n";
        if ($raw === false) { echo "Erro de conexão: {$err}\n"; return; }
        echo "Resposta (início):\n" . substr((string)$raw, 0, 600) . "\n\n";

        if ($http >= 200 && $http < 300) {
            echo "RESULTADO: credencial OK neste ambiente. Se criar documento ainda falhar com\n";
            echo "'e-mail do usuário da API não configurada', defina o usuário da API no painel\n";
            echo "da ClickSign DESTE MESMO ambiente (Configurações → API → E-mail do usuário da API).\n";
        } elseif ($http === 401 || $http === 403) {
            echo "RESULTADO: a ClickSign recusou a credencial NESTE ambiente. Verifique se o token\n";
            echo "é deste ambiente (produção x sandbox) e se o 'E-mail do usuário da API' está salvo\n";
            echo "na conta deste ambiente.\n";
        }
    }

    // ================= Modelos de contrato (CRUD) =================

    /** Lista/gerencia os modelos de contrato reutilizáveis. */
    public function templates()
    {
        $this->requireModule('contracts');
        $user = $this->currentUser();
        $this->view('commercial/contract_templates', [
            'user' => $user,
            'templates' => (new ContractTemplate())->getAll(false),
        ]);
    }

    /** Tela de edição do corpo de um modelo (novo quando sem id). */
    public function editTemplate($id = null)
    {
        $this->requireModule('contracts');
        $user = $this->currentUser();
        $template = $id ? (new ContractTemplate())->findById($id) : null;
        $this->view('commercial/contract_template_form', [
            'user' => $user,
            'template' => $template,
            'vars' => ContractTemplateVars::catalog(),
        ]);
    }

    /** Cria ou atualiza um modelo (POST). */
    public function saveTemplate($id = null)
    {
        $this->requireModule('contracts');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);

        $raw = file_get_contents('php://input');
        $body = json_decode($raw, true);
        if (!is_array($body)) $body = $_POST;

        $name = trim($body['name'] ?? '');
        if ($name === '') $this->json(['error' => 'Informe o nome do modelo.'], 400);
        $data = [
            'name' => $name,
            'body' => (string)($body['body'] ?? ''),
            'active' => !empty($body['active']) ? 1 : 1, // nasce ativo
        ];
        $model = new ContractTemplate();
        if ($id) {
            $model->update((int)$id, ['name' => $data['name'], 'body' => $data['body']]);
            $this->json(['success' => true, 'id' => (int)$id]);
        }
        $newId = $model->create($data);
        $this->json(['success' => true, 'id' => $newId]);
    }

    /** Ativa/desativa um modelo. */
    public function toggleTemplate($id = null)
    {
        $this->requireModule('contracts');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        (new ContractTemplate())->toggleActive((int)$id);
        $this->json(['success' => true]);
    }

    // ================= Signatários da empresa (CRUD) =================

    /** Lista/gerencia os signatários da empresa (lado contratada). */
    public function signers()
    {
        $this->requireModule('contracts');
        $this->view('commercial/contract_signers', [
            'user' => $this->currentUser(),
            'signers' => (new ContractSigner())->getAll(false),
        ]);
    }

    /** Cria um signatário da empresa (POST). */
    public function storeSigner()
    {
        $this->requireModule('contracts');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);
        $user = $this->currentUser();
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->json(['error' => 'Informe nome e um e-mail válido.'], 400);
        }
        $id = (new ContractSigner())->create([
            'name' => $name, 'email' => $email,
            'phone' => $_POST['phone'] ?? null,
            'role_label' => $_POST['role_label'] ?? null,
            'is_default' => !empty($_POST['is_default']),
            'created_by' => $user['id'],
        ]);
        $this->json(['success' => true, 'id' => $id]);
    }

    /** Atualiza um signatário da empresa (POST). */
    public function updateSigner($id = null)
    {
        $this->requireModule('contracts');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->json(['error' => 'Informe nome e um e-mail válido.'], 400);
        }
        (new ContractSigner())->update((int)$id, [
            'name' => $name, 'email' => $email,
            'phone' => $_POST['phone'] ?? null,
            'role_label' => $_POST['role_label'] ?? null,
            'is_default' => !empty($_POST['is_default']),
        ]);
        $this->json(['success' => true]);
    }

    /** Ativa/desativa um signatário da empresa. */
    public function toggleSigner($id = null)
    {
        $this->requireModule('contracts');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        (new ContractSigner())->toggleActive((int)$id);
        $this->json(['success' => true]);
    }

    /**
     * Renderiza um modelo com as variáveis já preenchidas pelos dados do
     * contrato atual (POST: contract_id, template_id). Usado pelo botão
     * "Carregar de um modelo" no editor do contrato.
     */
    public function renderTemplate()
    {
        $this->requireModule('contracts');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);

        $templateId = (int)($_POST['template_id'] ?? 0);
        $contractId = (int)($_POST['contract_id'] ?? 0);
        $tpl = $templateId ? (new ContractTemplate())->findById($templateId) : null;
        if (!$tpl) $this->json(['error' => 'Modelo não encontrado.'], 404);

        $contract = $contractId ? $this->model->findById($contractId) : null;
        // Monta os valores a partir do contrato (que já tem os dados do cliente).
        $prestador = (string) Config::get('app_name');
        $empresaCliente = null;
        if ($contract && !empty($contract['company_id'])) {
            $co = Database::getInstance()->fetch("SELECT name FROM companies WHERE id = ?", [(int)$contract['company_id']]);
            $empresaCliente = $co['name'] ?? null;
        }
        // O contrato guarda client_name/email/phone e proposal_id; usamos a
        // proposta vinculada p/ total/validade/tipo quando houver.
        $proposalLike = [
            'client_name'  => $contract['client_name'] ?? '',
            'client_email' => $contract['client_email'] ?? '',
            'client_phone' => $contract['client_phone'] ?? '',
            'title'        => $contract['title'] ?? '',
        ];
        if ($contract && !empty($contract['proposal_id'])) {
            $p = (new Proposal())->findById((int)$contract['proposal_id']);
            if ($p) {
                $proposalLike['title'] = $p['title'] ?? $proposalLike['title'];
                $proposalLike['total'] = $p['total'] ?? 0;
                $proposalLike['validity_date'] = $p['validity_date'] ?? null;
                $proposalLike['contract_type'] = $p['contract_type'] ?? null;
            }
        }
        $values = ContractTemplateVars::valuesFromProposal($proposalLike, $prestador, $empresaCliente);
        $rendered = ContractTemplateVars::render((string)($tpl['body'] ?? ''), $values);
        $this->json(['success' => true, 'body' => $rendered]);
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
            // Modelos ativos para o botão "Carregar de um modelo" (só com corpo editável).
            'templates' => (new ContractTemplate())->getAll(true),
            // Signatários da empresa (para escolher quem assina junto com o cliente).
            'companySigners' => (new ContractSigner())->getAll(true),
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

        // Reenvio após ajuste: 'client_rejected' volta a 'draft' antes de ir para
        // 'client_review' (a máquina de estados não permite o salto direto).
        if ($contract['status'] === ContractRules::STATUS_CLIENT_REJECTED) {
            $this->model->changeStatus($id, ContractRules::STATUS_DRAFT, $user['id']);
        }

        if (!$this->model->changeStatus($id, ContractRules::STATUS_CLIENT_REVIEW, $user['id'])) {
            $this->json(['error' => 'Não é possível enviar para aprovação neste estado.'], 409);
        }
        $link = $this->publicBase() . '/contract/show/' . $contract['public_token'];

        // Envia o link ao CLIENTE por WhatsApp + e-mail (quando houver contato).
        $delivery = $this->deliverToClient($contract, $link);

        $this->json([
            'success' => true,
            'link' => $link,
            'sent_whats' => $delivery['sent_whats'],
            'sent_email' => $delivery['sent_email'],
            'no_contact' => $delivery['no_contact'],
        ]);
    }

    /**
     * Envia o link do contrato ao cliente (WhatsApp + e-mail). Nunca interrompe.
     */
    private function deliverToClient(array $contract, string $link): array
    {
        $out = ['sent_whats' => 0, 'sent_email' => 0, 'no_contact' => true];
        $c = ContractDelivery::clientContact($contract);
        $company = trim((string) Config::get('app_name')) ?: null;
        $title = $contract['title'] ?? null;

        if (!empty($c['phone'])) {
            $out['no_contact'] = false;
            $msg = ContractDelivery::clientWhatsapp($c['name'], $link, $title, $company);
            try { if (WhatsappNotifier::sendToPhone($c['phone'], $msg, $c['name'])) $out['sent_whats']++; }
            catch (\Throwable $e) { /* não interrompe */ }
        }
        if (!empty($c['email'])) {
            $out['no_contact'] = false;
            $subject = ContractDelivery::clientEmailSubject($title);
            $html = Mailer::template($subject, ContractDelivery::clientEmailBody($c['name'], $link, $title));
            try { if (Mailer::send($c['email'], $subject, $html)) $out['sent_email']++; }
            catch (\Throwable $e) { /* não interrompe */ }
        }
        return $out;
    }

    /**
     * Avisa a equipe quando há evento do cliente/assinatura: sino (criador) +
     * WhatsApp pessoal do criador + WhatsApp do grupo. Nunca interrompe.
     */
    private function notifyTeamResponse(array $contract, string $event, string $sinoTitle, string $sinoMsg, ?string $reason = null): void
    {
        $this->notifyTeam($contract, $sinoTitle, $sinoMsg);
        $wa = ContractDelivery::teamWhatsapp($event, $contract['title'] ?? null, $reason);
        try {
            if (!empty($contract['created_by'])) {
                $creator = (new User())->findById((int)$contract['created_by']);
                if ($creator && !empty($creator['phone'])) {
                    WhatsappNotifier::sendToPhone($creator['phone'], $wa, $creator['name'] ?? null);
                }
            }
        } catch (\Throwable $e) { /* não interrompe */ }
        try { WhatsappNotifier::sendToDefaultGroup($wa); } catch (\Throwable $e) { /* não interrompe */ }
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

        // 1) Gera o PDF do contrato (a ClickSign aceita PDF/DOC/imagem/TXT — não HTML)
        //    e envia como data URI base64.
        if (!PdfGenerator::isAvailable()) {
            $this->json(['error' => 'Gerador de PDF não instalado no servidor. Rode "composer require dompdf/dompdf" e tente novamente.'], 500);
        }
        try {
            $pdf = PdfGenerator::fromHtml((string)($contract['body'] ?? ''));
        } catch (\Throwable $e) {
            $this->json(['error' => 'Falha ao gerar o PDF do contrato: ' . $e->getMessage()], 500);
        }
        $dataUri = 'data:application/pdf;base64,' . base64_encode($pdf);
        $path = '/helpdeskon/contrato-' . $contract['id'] . '-' . substr($contract['public_token'], 0, 8) . '.pdf';
        $doc = $api->createDocument($path, $dataUri);
        if (empty($doc['success']) || empty($doc['data']['document']['key'])) {
            $this->json(['error' => 'Falha ao criar o documento na ClickSign: ' . ($doc['error'] ?? 'desconhecido')], 502);
        }
        $docKey = $doc['data']['document']['key'];
        $base = ClickSignRules::baseUrl($api->isSandbox());

        // Monta a lista de signatários: o CLIENTE + os signatários da empresa
        // escolhidos (POST company_signers[]). Cada um é criado e vinculado.
        $toSign = [];
        $toSign[] = [
            'email' => (string)$contract['client_email'],
            'name'  => $contract['client_name'] ?: 'Cliente',
            'phone' => $contract['client_phone'] ?? null,
            'role'  => 'cliente',
        ];
        $companyIds = $_POST['company_signers'] ?? [];
        if (!is_array($companyIds)) $companyIds = array_filter(explode(',', (string)$companyIds));
        foreach ((new ContractSigner())->findByIds($companyIds) as $cs) {
            $toSign[] = ['email' => $cs['email'], 'name' => $cs['name'], 'phone' => $cs['phone'] ?? null, 'role' => 'empresa'];
        }

        $clientReqKey = null;      // guardado para enviar o link ao cliente por WhatsApp
        $clientSignerKey = null;
        $clientWhatsOk = false;    // WhatsApp (nosso) entregue ao cliente?
        $notifiedCount = 0;
        $fails = [];
        $detalhes = [];            // detalhe por signatário (p/ histórico e retorno)
        $company = trim((string) Config::get('app_name')) ?: null;

        foreach ($toSign as $sg) {
            $tag = $sg['name'] . ' (' . $sg['role'] . ')';
            if (empty($sg['email'])) { $fails[] = $tag . ': sem e-mail'; $detalhes[] = $tag . ': SEM E-MAIL'; continue; }

            $signer = $api->createSigner($sg['email'], $sg['name'] ?: 'Signatário', $sg['phone'] ?? null);
            if (empty($signer['success']) || empty($signer['data']['signer']['key'])) {
                $msg = $signer['error'] ?? 'falha ao criar signatário';
                $fails[] = $tag . ': ' . $msg; $detalhes[] = $tag . ': ERRO criar signatário (' . $msg . ')';
                continue;
            }
            $sk = $signer['data']['signer']['key'];
            $list = $api->addSigner($docKey, $sk, 'sign');
            $rk = $list['data']['list']['request_signature_key'] ?? null;
            if (!$rk) {
                $fails[] = $tag . ': ' . ($list['error'] ?? 'falha ao vincular ao documento');
                $detalhes[] = $tag . ': ERRO vincular (' . ($list['error'] ?? '?') . ')';
                continue;
            }

            // Notificação por e-mail (ClickSign).
            $notif = $api->notifySigner($rk, 'Olá! Segue o contrato "' . ($contract['title'] ?? '') . '" para sua assinatura.');
            $emailOk = !empty($notif['success']);
            if ($emailOk) $notifiedCount++;

            // WhatsApp com o link de assinatura (nosso sistema) — para QUALQUER
            // signatário que tenha telefone (cliente e empresa).
            $signUrl = $base . '/sign/' . $rk;
            $waOk = false;
            if (!empty($sg['phone'])) {
                $wa = "Olá" . ($sg['name'] ? ', ' . $sg['name'] : '') . "!\n\n"
                    . ($company ? "*{$company}*\n" : '')
                    . "O contrato \"" . ($contract['title'] ?? '') . "\" está pronto para sua assinatura. Assine pelo link:\n" . $signUrl;
                try { $waOk = (bool) WhatsappNotifier::sendToPhone($sg['phone'], $wa, $sg['name'] ?? null); }
                catch (\Throwable $e) { /* não interrompe */ }
            }
            $detalhes[] = $tag . ': e-mail ' . ($emailOk ? 'OK' : 'FALHOU') . ($emailOk ? '' : ' (' . ($notif['error'] ?? '?') . ')')
                        . ', whats ' . (!empty($sg['phone']) ? ($waOk ? 'OK' : 'FALHOU') : 'sem telefone');

            if ($sg['role'] === 'cliente') { $clientReqKey = $rk; $clientSignerKey = $sk; $clientWhatsOk = $waOk; }
        }

        $this->model->changeStatus($id, ContractRules::STATUS_AWAITING_SIGNATURE, $user['id'], [
            'clicksign_doc_key' => $docKey,
            'clicksign_signer_key' => $clientSignerKey,
            'clicksign_request_key' => $clientReqKey,
            'sent_signature_at' => date('Y-m-d H:i:s'),
        ]);
        $this->model->addEvent($id, $user['id'], 'signature_sent',
            'Enviado para assinatura (' . count($toSign) . ' signatário(s)). ' . implode(' | ', $detalhes));

        // O WhatsApp ao cliente (e aos signatários da empresa) já foi enviado
        // dentro do loop acima; aqui só expomos o resultado no retorno.
        $clientSignUrl = $clientReqKey ? ($base . '/sign/' . $clientReqKey) : null;

        $this->json([
            'success' => true,
            'signers' => count($toSign),
            'notified' => $notifiedCount,
            'sent_whats' => $clientWhatsOk,
            'sign_url' => $clientSignUrl,
            'fails' => $fails,
            'detalhes' => $detalhes,
        ]);
    }

    // ================= Área pública (cliente, por token) =================

    /**
     * Página pública do contrato (link ao cliente). Nome 'show' (não 'view')
     * para não colidir com Controller::view(), usado internamente aqui.
     */
    public function show($token = null)
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
        $this->notifyTeamResponse($contract, 'approved', 'Contrato aprovado',
            "O cliente aprovou o contrato \"{$contract['title']}\". Pronto para enviar à assinatura.");
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
        $this->notifyTeamResponse($contract, 'rejected', 'Contrato: ajuste solicitado',
            "O cliente pediu ajustes no contrato \"{$contract['title']}\": {$reason}", $reason);
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
                // Emenda automática contrato -> financeiro: ao assinar, já cria o
                // projeto financeiro (se ainda não existir). O onboarding só começa
                // depois da ENTRADA PAGA (regra do financeiro), então aqui paramos
                // no financeiro, que é o próximo passo real do fluxo.
                $financeId = $this->ensureFinanceProject($contract);
                $extra = $financeId ? " Projeto financeiro #{$financeId} criado — defina o plano de pagamento." : ' Siga para o financeiro.';
                $this->notifyTeamResponse($contract, 'signed', 'Contrato assinado',
                    "O contrato \"{$contract['title']}\" foi assinado." . $extra);
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

    /**
     * Garante o projeto financeiro do contrato assinado (idempotente). Retorna o
     * id do projeto (novo ou existente) ou null em falha. O total vem da proposta
     * vinculada, quando houver. Nunca interrompe o webhook.
     */
    private function ensureFinanceProject(array $contract): ?int
    {
        try {
            $fp = new FinanceProject();
            $existing = $fp->findByContract((int)$contract['id']);
            if ($existing) return (int)$existing['id'];

            $total = 0.0;
            if (!empty($contract['proposal_id'])) {
                $p = (new Proposal())->findById((int)$contract['proposal_id']);
                if ($p) $total = (float)$p['total'];
            }
            // created_by: usa o criador do contrato (webhook não tem usuário logado).
            $userId = !empty($contract['created_by']) ? (int)$contract['created_by'] : null;
            return (int) $fp->createFromContract($contract, $total, $userId);
        } catch (\Throwable $e) {
            if (class_exists('Logger')) Logger::error('ensureFinanceProject falhou', ['contract' => $contract['id'] ?? null, 'error' => $e->getMessage()]);
            return null;
        }
    }

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
