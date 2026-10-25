<?php

/**
 * Financeiro (Fase 5 — esteira comercial). Acesso restrito (módulo 'finance',
 * só super_admin/developer por padrão).
 *
 * Fluxo: contrato ASSINADO -> cria projeto financeiro -> define valor, entrada e
 * parcelas (plano calculado por FinanceRules) -> escolhe a conta Asaas por
 * cobrança -> (envio real ao Asaas = validação manual) -> webhook confirma
 * pagamento -> entrada paga DESTRAVA o onboarding.
 */
class FinanceController extends Controller
{
    private $projects;
    private $accounts;

    public function __construct()
    {
        $this->projects = new FinanceProject();
        $this->accounts = new FinanceAccount();
    }

    public function index()
    {
        $this->requireModule('finance');
        $user = $this->currentUser();
        $list = $this->projects->getAll();
        $this->view('commercial/finance', ['user' => $user, 'projects' => $list]);
    }

    /** Contas Asaas (CRUD simples). */
    public function accounts()
    {
        $this->requireModule('finance');
        $user = $this->currentUser();
        $this->view('commercial/finance_accounts', ['user' => $user, 'accounts' => $this->accounts->getAll(false)]);
    }

    public function storeAccount()
    {
        $this->requireModule('finance');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);
        $user = $this->currentUser();
        $name = trim($_POST['name'] ?? '');
        if ($name === '') $this->json(['error' => 'Informe o nome da conta.'], 400);
        $purpose = in_array($_POST['purpose'] ?? '', ['parcela','recorrente','outra'], true) ? $_POST['purpose'] : 'outra';
        try {
            $id = $this->accounts->create([
                'name' => $name,
                'purpose' => $purpose,
                'asaas_token' => trim($_POST['asaas_token'] ?? '') ?: null,
                'sandbox' => !empty($_POST['sandbox']) ? 1 : 0,
                'active' => 1,
                'created_by' => $user['id'],
            ]);
        } catch (\Throwable $e) {
            // Mensagem clara em vez de erro HTML genérico (ex.: tabela ainda não
            // criada neste banco — rodar as migrations da esteira).
            $msg = $e->getMessage();
            if (stripos($msg, "doesn't exist") !== false || stripos($msg, '42S02') !== false) {
                $this->json(['error' => 'As tabelas do Financeiro ainda não foram criadas neste ambiente. Rode as migrations da esteira (migrate_esteira.php) e tente de novo.'], 503);
            }
            $this->json(['error' => 'Não foi possível salvar a conta. Tente novamente.'], 500);
        }
        $this->json(['success' => true, 'id' => $id]);
    }

    /** Cria o projeto financeiro a partir de um contrato ASSINADO. */
    public function fromContract()
    {
        $this->requireModule('finance');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);
        $user = $this->currentUser();

        $contractId = (int)($_POST['contract_id'] ?? 0);
        $contract = $contractId ? (new Contract())->findById($contractId) : null;
        if (!$contract) $this->json(['error' => 'Contrato não encontrado.'], 404);
        if ($contract['status'] !== ContractRules::STATUS_SIGNED) {
            $this->json(['error' => 'O financeiro só inicia após o contrato ser assinado.'], 409);
        }

        // Mesmo caminho da assinatura: devolve o projeto que já existe.
        $r = (new ContractSigning())->onContractSigned($contractId, (string)($contract['signed_via'] ?: ContractSigning::ORIGIN_CLICKSIGN), (int)$user['id']);
        if (!$r['ok'] || !$r['finance_id']) $this->json(['error' => $r['error'] ?? 'Não foi possível abrir o financeiro.'], $r['code'] ?: 500);
        $this->json(['success' => true, 'id' => $r['finance_id']]);
    }

    public function edit($id = null)
    {
        $this->requireModule('finance');
        if (!$id) $this->redirect('finance');
        $project = $this->projects->findById($id);
        if (!$project) $this->redirect('finance');
        $user = $this->currentUser();
        $onboarding = Database::getInstance()->fetch("SELECT id FROM onboardings WHERE project_id = ? ORDER BY id ASC LIMIT 1", [(int)$id]);
        $this->view('commercial/finance_project', [
            'user' => $user,
            'project' => $project,
            'charges' => $this->projects->getCharges($id),
            'accounts' => $this->accounts->getAll(true),
            'canOnboard' => $this->projects->canStartOnboarding($id),
            'onboardingId' => $onboarding ? (int)$onboarding['id'] : null,
            'suggestion' => !empty($project['proposal_id']) ? $this->projects->suggestionForProposal((int)$project['proposal_id']) : null,
            'events' => $this->projects->getEvents($id),
        ]);
    }

    /**
     * Define o plano de cobranças (entrada + parcelas). POST JSON com os
     * parâmetros do fechamento; FinanceRules monta o plano e escolhe as contas.
     */
    public function savePlan($id = null)
    {
        $this->requireModule('finance');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        $project = $this->projects->findById($id);
        if (!$project) $this->json(['error' => 'Projeto não encontrado'], 404);

        $raw = file_get_contents('php://input');
        $body = json_decode($raw, true);
        if (!is_array($body)) $body = $_POST;

        $plan = FinanceRules::buildChargePlan([
            'total' => $body['total'] ?? $project['total_value'],
            'entry' => $body['entry'] ?? 0,
            'entry_due' => $body['entry_due'] ?? null,
            'entry_method' => $body['entry_method'] ?? null,
            'installments' => $body['installments'] ?? 0,
            'first_installment_due' => $body['first_installment_due'] ?? null,
            'installment_interval_days' => $body['installment_interval_days'] ?? 30,
            'installment_method' => $body['installment_method'] ?? null,
            'recurring_amount' => $body['recurring_amount'] ?? 0,
            'recurring_start' => $body['recurring_start'] ?? null,
            'recurring_method' => $body['recurring_method'] ?? null,
            'recurring_cycle' => 'monthly',
        ]);

        // Seleciona a conta Asaas por finalidade.
        $accs = $this->accounts->getAll(true);
        $accountByKind = [
            'entry' => FinanceRules::pickAccount($accs, 'parcela'),
            'installment' => FinanceRules::pickAccount($accs, 'parcela'),
            'recurring' => FinanceRules::pickAccount($accs, 'recorrente'),
        ];
        // Override manual por conta, se enviado.
        if (!empty($body['account_entry'])) $accountByKind['entry'] = (int)$body['account_entry'];
        if (!empty($body['account_installment'])) $accountByKind['installment'] = (int)$body['account_installment'];
        if (!empty($body['account_recurring'])) $accountByKind['recurring'] = (int)$body['account_recurring'];

        $user = $this->currentUser();
        $merge = $this->projects->replaceCharges($id, $plan, $accountByKind);
        $this->projects->addEvent((int)$id, null, (int)$user['id'], 'plan',
            "Plano salvo: {$merge['added']} cobrança(s) nova(s), {$merge['removed']} substituída(s), {$merge['kept']} mantida(s) (pagas ou emitidas)");
        if (isset($body['total'])) $this->projects->update($id, ['total_value' => FinanceRules::money($body['total'])]);

        $charges = $this->projects->getCharges($id);

        // Avisa o cliente sobre o plano de pagamento (WhatsApp + e-mail), quando
        // solicitado (notify=1) e houver contato. Nunca interrompe o salvamento.
        $delivery = ['sent_whats' => 0, 'sent_email' => 0, 'no_contact' => true];
        if (!empty($body['notify_client'])) {
            $delivery = $this->notifyClientPlan($project, $charges);
        }

        $this->json(['success' => true, 'charges' => $charges, 'delivery' => $delivery]);
    }

    /**
     * Resolve o contato do cliente (via contrato vinculado) e envia o resumo do
     * plano por WhatsApp + e-mail. Nunca interrompe.
     */
    private function notifyClientPlan(array $project, array $charges): array
    {
        $out = ['sent_whats' => 0, 'sent_email' => 0, 'no_contact' => true];
        // O contato do cliente vem do contrato que originou o projeto financeiro.
        $contract = !empty($project['contract_id']) ? (new Contract())->findById((int)$project['contract_id']) : null;
        $name  = trim((string)($contract['client_name'] ?? '')) ?: 'Cliente';
        $email = FinanceDelivery::normalizeEmail($contract['client_email'] ?? null);
        $phone = FinanceDelivery::normalizePhone($contract['client_phone'] ?? null);
        $company = trim((string) Config::get('app_name')) ?: null;

        if ($phone) {
            $out['no_contact'] = false;
            $msg = FinanceDelivery::clientWhatsapp($name, $charges, $company);
            try { if (WhatsappNotifier::sendToPhone($phone, $msg, $name)) $out['sent_whats']++; }
            catch (\Throwable $e) { /* não interrompe */ }
        }
        if ($email) {
            $out['no_contact'] = false;
            $subject = FinanceDelivery::clientEmailSubject($company);
            $html = Mailer::template($subject, FinanceDelivery::clientEmailBody($name, $charges));
            try { if (Mailer::send($email, $subject, $html)) $out['sent_email']++; }
            catch (\Throwable $e) { /* não interrompe */ }
        }
        return $out;
    }

    /** Marca manualmente uma cobrança como paga (quando não há Asaas no fluxo). */
    public function markPaid($chargeId = null)
    {
        $this->requireModule('finance');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$chargeId) $this->json(['error' => 'Requisição inválida'], 400);
        $user = $this->currentUser();
        $charge = $this->projects->findChargeById((int)$chargeId);
        if (!$charge) $this->json(['error' => 'Cobrança não encontrada.'], 404);
        $destravou = $this->projects->markChargePaid((int)$chargeId, (int)$user['id']);
        $onb = $destravou ? (new OnboardingStarter($this->projects))->ensureForFinanceProject((int)$charge['project_id'], (int)$user['id']) : null;
        $this->json(['success' => true, 'onboarding_unlocked' => $destravou, 'onboarding_id' => $onb['id'] ?? null]);
    }

    /** Emite a cobrança (ou a assinatura da mensalidade) no Asaas. Sempre um clique humano. */
    public function issue($chargeId = null)
    {
        $this->requireModule('finance');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$chargeId) $this->json(['error' => 'Requisição inválida'], 400);
        $user = $this->currentUser();
        $r = (new FinanceBilling($this->projects))->issue((int)$chargeId, (int)$user['id']);
        if (!$r['ok']) $this->json(['error' => $r['error']], $r['code']);
        $this->json(['success' => true, 'charge' => $r['charge']]);
    }

    // ================= Webhook Asaas (sem login) =================

    /**
     * Recebe eventos do Asaas. Localiza a cobrança por asaas_charge_id e, quando
     * confirmado o pagamento, marca como paga (pode destravar o onboarding).
     * Autenticação por token simples na query (?token=) comparado a Settings.
     */
    public function asaasWebhook()
    {
        // Token obrigatório: sem asaas_webhook_token configurado, recusa tudo.
        $expected = (string) Config::get('asaas_webhook_token');
        $got = (string)($_GET['token'] ?? ($_SERVER['HTTP_ASAAS_ACCESS_TOKEN'] ?? ''));
        if ($expected === '' || !hash_equals($expected, $got)) {
            http_response_code(401);
            echo json_encode(['error' => $expected === '' ? 'webhook não configurado' : 'token inválido']);
            return;
        }

        $payload = json_decode((string)file_get_contents('php://input'), true);
        $result = is_array($payload) ? (new FinanceBilling($this->projects))->handleWebhook($payload) : ['action' => 'ignore'];
        http_response_code(200);
        echo json_encode(['ok' => true, 'action' => $result['action']]);
    }
}
