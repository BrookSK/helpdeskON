<?php

/**
 * Contratação de prestadores (Fase 8). Módulo 'providers', RESTRITO a
 * super_admin/developer (full-access) — é gestão interna/RH, sensível.
 *
 * Reaproveita a lógica da esteira: estados (prospect->proposal->contract->active
 * ->terminated), eventos e checklist. Regra de ouro no encerramento: só encerra
 * com TODOS os acessos revogados (ProviderRules::canTerminate).
 */
class ProviderController extends Controller
{
    private $providers;

    public function __construct()
    {
        $this->providers = new Provider();
    }

    public function index()
    {
        $this->requireModule('providers');
        $user = $this->currentUser();
        $this->view('commercial/providers', [
            'user' => $user,
            'items' => $this->providers->getAll(),
        ]);
    }

    public function edit($id = null)
    {
        $this->requireModule('providers');
        if (!$id) $this->redirect('provider');
        $p = $this->providers->findById($id);
        if (!$p) $this->redirect('provider');
        $user = $this->currentUser();
        $documents = $this->providers->getDocuments($id);
        $this->view('commercial/provider_detail', [
            'user' => $user,
            'provider' => $p,
            'accesses' => $this->providers->getAccesses($id),
            'documents' => $documents,
            'docChecklist' => ProviderProposalRules::documentChecklist($p['engagement_type'] ?? null, $documents),
            'events' => $this->providers->getEvents($id),
            'pendingAccesses' => $this->providers->pendingAccesses($id),
            'defaultAccessLabels' => ProviderRules::defaultAccessLabels(),
            'revisions' => $this->providers->getRevisions($id),
            'pendingRevision' => $this->providers->pendingRevision($id),
            'canApproveRevision' => ProviderRevisionRules::roleCanApprove($user['role'] ?? null),
            'proposalLink' => !empty($p['public_token']) ? $this->publicBase() . '/provider/showProposal/' . $p['public_token'] : null,
        ]);
    }

    public function store()
    {
        $this->requireModule('providers');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);
        $user = $this->currentUser();

        $name = trim($_POST['name'] ?? '');
        if ($name === '') $this->json(['error' => 'Informe o nome do prestador.'], 400);
        $id = $this->providers->create([
            'name' => $name,
            'email' => trim($_POST['email'] ?? '') ?: null,
            'phone' => trim($_POST['phone'] ?? '') ?: null,
            'document' => trim($_POST['document'] ?? '') ?: null,
            'role_title' => trim($_POST['role_title'] ?? '') ?: null,
            'engagement_type' => $_POST['engagement_type'] ?? 'pj',
            'work_model' => $_POST['work_model'] ?? null,
            'pay_type' => $_POST['pay_type'] ?? null,
            'pay_amount' => isset($_POST['pay_amount']) && $_POST['pay_amount'] !== '' ? CrmRules::parseMoneyBR($_POST['pay_amount']) : null,
            'workload' => trim($_POST['workload'] ?? '') ?: null,
            'pay_term' => trim($_POST['pay_term'] ?? '') ?: null,
            'payment_method' => trim($_POST['payment_method'] ?? '') ?: null,
            'availability' => trim($_POST['availability'] ?? '') ?: null,
            'scope' => trim($_POST['scope'] ?? '') ?: null,
            'out_of_scope' => trim($_POST['out_of_scope'] ?? '') ?: null,
            'notes' => trim($_POST['notes'] ?? '') ?: null,
            'created_by' => $user['id'],
        ]);
        $this->providers->addEvent($id, $user['id'], 'created', 'Prestador cadastrado');
        $this->json(['success' => true, 'id' => $id]);
    }

    public function save($id = null)
    {
        $this->requireModule('providers');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        $p = $this->providers->findById($id);
        if (!$p) $this->json(['error' => 'Prestador não encontrado.'], 404);

        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'email' => trim($_POST['email'] ?? '') ?: null,
            'phone' => trim($_POST['phone'] ?? '') ?: null,
            'document' => trim($_POST['document'] ?? '') ?: null,
            'role_title' => trim($_POST['role_title'] ?? '') ?: null,
            'engagement_type' => $_POST['engagement_type'] ?? 'pj',
            'work_model' => $_POST['work_model'] ?? null,
            'workload' => trim($_POST['workload'] ?? '') ?: null,
            'pay_term' => trim($_POST['pay_term'] ?? '') ?: null,
            'payment_method' => trim($_POST['payment_method'] ?? '') ?: null,
            'availability' => trim($_POST['availability'] ?? '') ?: null,
            'scope' => trim($_POST['scope'] ?? '') ?: null,
            'out_of_scope' => trim($_POST['out_of_scope'] ?? '') ?: null,
            'notes' => trim($_POST['notes'] ?? '') ?: null,
        ];

        // O VALOR (pay_type/pay_amount) só é editável livremente antes do contrato
        // (prospect/proposal). Depois disso, mudar valor exige REVISÃO aprovada
        // por um gestor (ProviderController::requestRevision) — não aceita edição
        // direta para não burlar a aprovação.
        $valorEditavel = in_array($p['status'], [ProviderRules::STATUS_PROSPECT, ProviderRules::STATUS_PROPOSAL], true);
        if ($valorEditavel) {
            $data['pay_type'] = $_POST['pay_type'] ?? null;
            $data['pay_amount'] = isset($_POST['pay_amount']) && $_POST['pay_amount'] !== '' ? CrmRules::parseMoneyBR($_POST['pay_amount']) : null;
        }

        $this->providers->update((int)$id, $data);
        $this->json(['success' => true, 'valor_bloqueado' => !$valorEditavel]);
    }

    /** Avança/retrocede o status validando a transição. */
    public function status($id = null)
    {
        $this->requireModule('providers');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        $user = $this->currentUser();
        $to = trim($_POST['status'] ?? '');
        if (!$this->providers->changeStatus((int)$id, $to, $user['id'])) {
            $this->json(['error' => 'Transição de status inválida.'], 409);
        }
        $this->json(['success' => true]);
    }

    // ================= Acessos =================

    public function addAccess($id = null)
    {
        $this->requireModule('providers');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        $user = $this->currentUser();
        if (!$this->providers->findById($id)) $this->json(['error' => 'Prestador não encontrado.'], 404);
        $label = trim($_POST['access_label'] ?? '');
        if ($label === '') $this->json(['error' => 'Informe o acesso.'], 400);
        $aid = $this->providers->addAccess((int)$id, $label, trim($_POST['details'] ?? '') ?: null, true);
        $this->providers->addEvent((int)$id, $user['id'], 'access_granted', 'Acesso concedido: ' . $label);
        $this->json(['success' => true, 'id' => $aid]);
    }

    public function revokeAccess($accessId = null)
    {
        $this->requireModule('providers');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$accessId) $this->json(['error' => 'Requisição inválida'], 400);
        $user = $this->currentUser();
        $this->providers->revokeAccess((int)$accessId, $user['id']);
        $this->json(['success' => true]);
    }

    public function revokeAll($id = null)
    {
        $this->requireModule('providers');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        $user = $this->currentUser();
        $n = $this->providers->revokeAllAccesses((int)$id, $user['id']);
        $this->json(['success' => true, 'revoked' => $n]);
    }

    // ================= Encerramento =================

    /**
     * Encerra o prestador. Exige motivo e que TODOS os acessos estejam revogados
     * (regra de ouro). Desativa o usuário vinculado.
     */
    public function terminate($id = null)
    {
        $this->requireModule('providers');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        $user = $this->currentUser();
        $reason = trim($_POST['reason'] ?? '');
        if ($reason === '') $this->json(['error' => 'Informe o motivo do encerramento.'], 400);
        if (!$this->providers->terminate((int)$id, $reason, $user['id'])) {
            $pending = $this->providers->pendingAccesses((int)$id);
            $this->json([
                'error' => 'Para encerrar, revogue todos os acessos primeiro (mesmo dia).',
                'pending' => $pending,
            ], 409);
        }
        // Revoga o acesso ao repositório (quando houver username vinculado) — a
        // revogação técnica é via LRV Cloud; o checklist já exige tudo revogado.
        $this->json(['success' => true]);
    }

    // ================= Documentos (com tipo CLT/PJ) =================

    /** Registra um documento do prestador (opcionalmente com doc_type do checklist). */
    public function addDocument($id = null)
    {
        $this->requireModule('providers');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        $user = $this->currentUser();
        if (!$this->providers->findById($id)) $this->json(['error' => 'Prestador não encontrado.'], 404);
        $label = trim($_POST['doc_label'] ?? '');
        if ($label === '') $this->json(['error' => 'Informe o documento.'], 400);
        $docId = $this->providers->addDocument(
            (int)$id,
            $label,
            trim($_POST['file_path'] ?? '') ?: null,
            trim($_POST['notes'] ?? '') ?: null,
            trim($_POST['doc_type'] ?? '') ?: null
        );
        $this->providers->addEvent((int)$id, $user['id'], 'document_added', 'Documento: ' . $label);
        $this->json(['success' => true, 'id' => $docId]);
    }

    // ================= Proposta por link =================

    /** Gera o link da proposta e envia ao prestador (WhatsApp + e-mail). */
    public function sendProposal($id = null)
    {
        $this->requireModule('providers');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        $user = $this->currentUser();
        $p = $this->providers->findById($id);
        if (!$p) $this->json(['error' => 'Prestador não encontrado.'], 404);

        $token = $this->providers->ensureToken((int)$id);
        $link = $this->publicBase() . '/provider/showProposal/' . $token;
        $this->providers->markProposalSent((int)$id, $user['id']);

        $company = trim((string) Config::get('app_name')) ?: null;
        $out = ['sent_whats' => 0, 'sent_email' => 0, 'link' => $link, 'no_contact' => true];

        $phone = preg_replace('/\D+/', '', (string)($p['phone'] ?? ''));
        if ($phone !== '' && strlen($phone) >= 10) {
            $out['no_contact'] = false;
            $msg = ProviderProposalRules::proposalWhatsapp($p['name'] ?? '', $link, $p['role_title'] ?? null, $company);
            try { if (WhatsappNotifier::sendToPhone($phone, $msg, $p['name'] ?? null)) $out['sent_whats']++; } catch (\Throwable $e) {}
        }
        $email = filter_var(trim((string)($p['email'] ?? '')), FILTER_VALIDATE_EMAIL) ? trim((string)$p['email']) : null;
        if ($email) {
            $out['no_contact'] = false;
            $subject = ProviderProposalRules::proposalEmailSubject($company);
            $html = Mailer::template($subject, ProviderProposalRules::proposalEmailBody($p['name'] ?? '', $link, $p['role_title'] ?? null));
            try { if (Mailer::send($email, $subject, $html)) $out['sent_email']++; } catch (\Throwable $e) {}
        }
        $this->json(['success' => true] + $out);
    }

    /** Página pública da proposta do prestador (sem login, por token). */
    public function showProposal($token = null)
    {
        $token = $this->tokenFromUrl($token);
        $p = $token ? $this->providers->findByToken($token) : null;
        if (!$p) { $this->renderSimple('Proposta indisponível', 'Este link de proposta não é válido.'); return; }
        $this->view('commercial/provider_proposal_public', [
            'provider' => $p,
            'canDecide' => in_array($p['status'], [ProviderRules::STATUS_PROPOSAL], true) && empty($p['proposal_responded_at']),
        ]);
    }

    /** Prestador aceita a proposta (POST público). */
    public function acceptProposal($token = null)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);
        $token = $this->tokenFromUrl($token);
        $p = $token ? $this->providers->findByToken($token) : null;
        if (!$p) $this->json(['error' => 'Proposta não encontrada'], 404);
        if (!empty($p['proposal_responded_at'])) $this->json(['error' => 'Esta proposta já foi respondida.'], 409);

        $this->providers->acceptProposal((int)$p['id']);
        $this->notifyTeam((int)$p['created_by'], 'Prestador aceitou a proposta', "O prestador \"{$p['name']}\" aceitou a proposta. Gere o contrato para assinatura.");
        $this->json(['success' => true]);
    }

    /** Prestador recusa a proposta (POST público) — motivo obrigatório. */
    public function rejectProposal($token = null)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);
        $token = $this->tokenFromUrl($token);
        $p = $token ? $this->providers->findByToken($token) : null;
        if (!$p) $this->json(['error' => 'Proposta não encontrada'], 404);
        if (!empty($p['proposal_responded_at'])) $this->json(['error' => 'Esta proposta já foi respondida.'], 409);

        $reason = ProviderProposalRules::sanitizeRejectReason($_POST['reason'] ?? '');
        if ($reason === null) $this->json(['error' => 'Informe o motivo da recusa.'], 400);
        $this->providers->rejectProposal((int)$p['id'], $reason);
        $this->notifyTeam((int)$p['created_by'], 'Prestador recusou a proposta', "O prestador \"{$p['name']}\" recusou. Motivo: {$reason}");
        $this->json(['success' => true]);
    }

    // ================= Assinatura (ClickSign) =================

    /**
     * Gera o contrato do prestador em PDF e envia à ClickSign para assinatura.
     * Reaproveita ClickSignApi/PdfGenerator (mesmo fluxo do contrato do cliente).
     * Só quando o prestador aceitou a proposta (status 'contract').
     */
    public function sendForSignature($id = null)
    {
        $this->requireModule('providers');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        $user = $this->currentUser();
        $p = $this->providers->findById($id);
        if (!$p) $this->json(['error' => 'Prestador não encontrado.'], 404);
        if ($p['status'] !== ProviderRules::STATUS_CONTRACT) {
            $this->json(['error' => 'O prestador precisa ter aceitado a proposta antes de assinar.'], 409);
        }
        $email = filter_var(trim((string)($p['email'] ?? '')), FILTER_VALIDATE_EMAIL) ? trim((string)$p['email']) : null;
        if (!$email) $this->json(['error' => 'Informe um e-mail válido do prestador para a assinatura.'], 400);

        $api = new ClickSignApi();
        if (!$api->isConfigured()) $this->json(['error' => 'Integração ClickSign não configurada.'], 400);
        if (!PdfGenerator::isAvailable()) $this->json(['error' => 'Gerador de PDF não instalado no servidor.'], 500);

        $token = $this->providers->ensureToken((int)$id);
        try {
            $pdf = PdfGenerator::fromHtml($this->buildProviderContractHtml($p));
        } catch (\Throwable $e) {
            $this->json(['error' => 'Falha ao gerar o PDF do contrato: ' . $e->getMessage()], 500);
        }
        $dataUri = 'data:application/pdf;base64,' . base64_encode($pdf);
        $path = '/helpdeskon/prestador-' . $p['id'] . '-' . substr($token, 0, 8) . '.pdf';
        $doc = $api->createDocument($path, $dataUri);
        if (empty($doc['success']) || empty($doc['data']['document']['key'])) {
            $this->json(['error' => 'Falha ao criar o documento na ClickSign: ' . ($doc['error'] ?? 'desconhecido')], 502);
        }
        $docKey = $doc['data']['document']['key'];
        $base = ClickSignRules::baseUrl($api->isSandbox());

        $signer = $api->createSigner($email, $p['name'] ?: 'Prestador', $p['phone'] ?? null);
        if (empty($signer['success']) || empty($signer['data']['signer']['key'])) {
            $this->json(['error' => 'Falha ao criar signatário: ' . ($signer['error'] ?? '?')], 502);
        }
        $sk = $signer['data']['signer']['key'];
        $list = $api->addSigner($docKey, $sk, 'sign');
        $rk = $list['data']['list']['request_signature_key'] ?? null;
        $this->providers->setSignatureKeys((int)$id, $docKey, $rk);

        $emailOk = false; $waOk = false;
        if ($rk) {
            $notif = $api->notifySigner($rk, 'Olá! Segue o contrato de prestação de serviço para sua assinatura.');
            $emailOk = !empty($notif['success']);
            $signUrl = $base . '/sign/' . $rk;
            $phone = preg_replace('/\D+/', '', (string)($p['phone'] ?? ''));
            if ($phone !== '' && strlen($phone) >= 10) {
                $wa = "Olá, " . ($p['name'] ?? '') . "! Seu contrato está pronto para assinatura: " . $signUrl;
                try { $waOk = (bool) WhatsappNotifier::sendToPhone($phone, $wa, $p['name'] ?? null); } catch (\Throwable $e) {}
            }
        }
        $this->providers->addEvent((int)$id, $user['id'], 'signature_sent', 'Contrato enviado para assinatura (ClickSign)');
        $this->json(['success' => true, 'notified' => $emailOk, 'sent_whats' => $waOk, 'sign_url' => $rk ? ($base . '/sign/' . $rk) : null]);
    }

    /** Webhook da ClickSign para contratos de PRESTADOR (sem login). */
    public function clicksignWebhook()
    {
        $raw = file_get_contents('php://input');
        $secret = (string) Config::get('clicksign_webhook_secret');
        $sig = $_SERVER['HTTP_CONTENT_HMAC'] ?? ($_SERVER['HTTP_X_CLICKSIGN_SIGNATURE'] ?? ($_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? ''));
        if ($secret !== '' && !ClickSignRules::verifyWebhookSignature($raw, $sig, $secret)) {
            http_response_code(401); echo json_encode(['error' => 'assinatura inválida']); return;
        }
        $payload = json_decode($raw, true);
        $eventName = $payload['event']['name'] ?? ($payload['event'] ?? null);
        $docKey = $payload['document']['key'] ?? ($payload['data']['document']['key'] ?? null);
        $action = ClickSignRules::interpretEvent(is_string($eventName) ? $eventName : null);

        if ($docKey && $action === 'signed') {
            $p = $this->providers->findByClickSignDocKey($docKey);
            if ($p && empty($p['signed_at'])) {
                $this->providers->markSigned((int)$p['id']);
                $this->onProviderSigned((int)$p['id']);
            }
        }
        http_response_code(200); echo json_encode(['ok' => true]);
    }

    /**
     * Pós-assinatura do prestador: cria o acesso ao sistema (usuário + PIN),
     * registra o card de pendência (checklist de acessos padrão), notifica e
     * ensina a usar. Idempotente o suficiente para o webhook.
     */
    private function onProviderSigned(int $providerId): void
    {
        $p = $this->providers->findById($providerId);
        if (!$p) return;

        // 1) Cria o usuário de acesso do prestador (se ainda não há e há e-mail).
        $email = filter_var(trim((string)($p['email'] ?? '')), FILTER_VALIDATE_EMAIL) ? trim((string)$p['email']) : null;
        if (empty($p['user_id']) && $email) {
            try {
                $role = ProviderAccessRules::roleForProvider($p['role_title'] ?? null, $p['engagement_type'] ?? null);
                $userId = (new User())->createForProvider($p['name'] ?? 'Prestador', $email, $p['phone'] ?? null, $role);
                if ($userId) {
                    $this->providers->update($providerId, ['user_id' => $userId]);
                    try { (new User())->sendFirstAccessInvite($userId); } catch (\Throwable $e) {}
                    $pin = (new User())->setClientPin($userId, null);
                    $p['user_id'] = $userId;
                    // WhatsApp com o PIN + orientação.
                    $phone = preg_replace('/\D+/', '', (string)($p['phone'] ?? ''));
                    if ($phone !== '' && strlen($phone) >= 10 && $pin) {
                        $base = $this->publicBase();
                        $wa = "Olá, " . ($p['name'] ?? '') . "! Seu acesso foi criado. Enviamos o link de senha por e-mail; seu PIN de acesso é *{$pin}*. Acesse: {$base}";
                        try { WhatsappNotifier::sendToPhone($phone, $wa, $p['name'] ?? null); } catch (\Throwable $e) {}
                    }
                }
            } catch (\Throwable $e) { if (class_exists('Logger')) Logger::error('criar acesso prestador falhou', ['provider' => $providerId, 'error' => $e->getMessage()]); }
        }

        // 2) Card de pendência: registra o checklist de acessos a conceder.
        foreach (ProviderRules::defaultAccessLabels() as $label) {
            try { $this->providers->addAccess($providerId, $label, 'Pendência pós-assinatura (conceder)', false); } catch (\Throwable $e) {}
        }
        $this->providers->addEvent($providerId, null, 'signed_followup', 'Pós-assinatura: acesso criado e pendências de acesso registradas.');

        // 2b) Contas a pagar: gera os lançamentos conforme o tipo de pagamento
        //     (mensal/hora/projeto). Idempotente (replaceForProvider só mexe nos
        //     pendentes) e nunca interrompe o fluxo em falha.
        try {
            $plan = PayableRules::buildPlanForProvider($p);
            if (!empty($plan)) {
                $n = (new Payable())->replaceForProvider($providerId, $plan, $p['created_by'] ?? null);
                $this->providers->addEvent($providerId, null, 'payables_generated',
                    "Contas a pagar: {$n} lançamento(s) gerado(s) a partir do contrato.");
            }
        } catch (\Throwable $e) {
            if (class_exists('Logger')) Logger::error('gerar contas a pagar do prestador falhou', ['provider' => $providerId, 'error' => $e->getMessage()]);
        }

        // 3) Notifica a equipe (criador) + grupo.
        $this->notifyTeam((int)($p['created_by'] ?? 0), 'Prestador assinou o contrato',
            "O prestador \"{$p['name']}\" assinou. Acesso criado e pendências de acesso abertas.");
        try { WhatsappNotifier::sendToDefaultGroup("Prestador \"{$p['name']}\" assinou o contrato. Acesso criado."); } catch (\Throwable $e) {}
    }

    /** Monta o HTML simples do contrato do prestador para o PDF da ClickSign. */
    private function buildProviderContractHtml(array $p): string
    {
        $company = htmlspecialchars((string)(Config::get('app_name') ?: 'Empresa'));
        $nome = htmlspecialchars((string)($p['name'] ?? ''));
        $funcao = htmlspecialchars((string)($p['role_title'] ?? '-'));
        $tipo = htmlspecialchars((string)($p['engagement_type'] ?? '-'));
        $valor = $p['pay_amount'] !== null ? ('R$ ' . number_format((float)$p['pay_amount'], 2, ',', '.')) : '-';
        $payType = htmlspecialchars((string)($p['pay_type'] ?? '-'));
        $prazo = htmlspecialchars((string)($p['pay_term'] ?? '-'));
        $forma = htmlspecialchars((string)($p['payment_method'] ?? '-'));
        $jornada = htmlspecialchars((string)($p['workload'] ?? '-'));
        $modelo = htmlspecialchars((string)($p['work_model'] ?? '-'));
        $escopo = nl2br(htmlspecialchars((string)($p['scope'] ?? '-')));
        $fora = nl2br(htmlspecialchars((string)($p['out_of_scope'] ?? '-')));
        return "<h1>Contrato de Prestação de Serviço</h1>"
            . "<p><strong>Contratante:</strong> {$company}</p>"
            . "<p><strong>Prestador:</strong> {$nome} — {$funcao} ({$tipo})</p>"
            . "<h3>Condições</h3>"
            . "<p>Remuneração: {$valor} ({$payType}). Prazo/condições: {$prazo}. Forma de pagamento: {$forma}.</p>"
            . "<p>Jornada: {$jornada}. Modelo de trabalho: {$modelo}.</p>"
            . "<h3>Escopo de atuação</h3><p>{$escopo}</p>"
            . "<h3>O que não faz parte das atribuições</h3><p>{$fora}</p>";
    }

    // ================= Revisão de valor (aprovação do gestor) =================

    /** Solicita uma revisão de valor (fica pendente até o gestor aprovar). */
    public function requestRevision($id = null)
    {
        $this->requireModule('providers');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        $user = $this->currentUser();
        $p = $this->providers->findById($id);
        if (!$p) $this->json(['error' => 'Prestador não encontrado.'], 404);
        if ($this->providers->pendingRevision((int)$id)) {
            $this->json(['error' => 'Já existe uma revisão pendente para este prestador.'], 409);
        }
        $newType = $_POST['new_pay_type'] ?? null;
        $newAmount = isset($_POST['new_pay_amount']) && $_POST['new_pay_amount'] !== '' ? CrmRules::parseMoneyBR($_POST['new_pay_amount']) : null;
        $reason = $_POST['reason'] ?? '';
        $v = ProviderRevisionRules::validateRequest($newType, $newAmount, $reason, $p['pay_type'] ?? null, $p['pay_amount'] ?? null);
        if (!$v['ok']) $this->json(['error' => $v['error']], 400);

        $revId = $this->providers->createRevision((int)$id, [
            'new_pay_type' => $newType,
            'new_pay_amount' => $newAmount,
            'reason' => $reason,
            'requested_by' => $user['id'],
        ]);
        $this->json(['success' => true, 'id' => $revId]);
    }

    /** Gestor aprova a revisão (aplica o novo valor). */
    public function approveRevision($revisionId = null)
    {
        $this->requireModule('providers');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$revisionId) $this->json(['error' => 'Requisição inválida'], 400);
        $user = $this->currentUser();
        if (!ProviderRevisionRules::roleCanApprove($user['role'] ?? null)) {
            $this->json(['error' => 'Apenas um gestor pode aprovar a revisão.'], 403);
        }
        if (!$this->providers->approveRevision((int)$revisionId, $user['id'], trim($_POST['notes'] ?? '') ?: null)) {
            $this->json(['error' => 'Revisão não encontrada ou já avaliada.'], 409);
        }
        $this->json(['success' => true]);
    }

    /** Gestor recusa a revisão. */
    public function rejectRevision($revisionId = null)
    {
        $this->requireModule('providers');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$revisionId) $this->json(['error' => 'Requisição inválida'], 400);
        $user = $this->currentUser();
        if (!ProviderRevisionRules::roleCanApprove($user['role'] ?? null)) {
            $this->json(['error' => 'Apenas um gestor pode recusar a revisão.'], 403);
        }
        if (!$this->providers->rejectRevision((int)$revisionId, $user['id'], trim($_POST['notes'] ?? '') ?: null)) {
            $this->json(['error' => 'Revisão não encontrada ou já avaliada.'], 409);
        }
        $this->json(['success' => true]);
    }

    // ================= Helpers =================

    private function notifyTeam($userId, string $title, string $message): void
    {
        $userId = (int)$userId;
        if ($userId <= 0) return;
        try {
            Database::getInstance()->insert('notifications', [
                'user_id' => $userId, 'title' => $title, 'message' => $message, 'type' => 'system',
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
