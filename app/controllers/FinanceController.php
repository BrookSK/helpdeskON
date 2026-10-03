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
        $id = $this->accounts->create([
            'name' => $name,
            'purpose' => $purpose,
            'asaas_token' => trim($_POST['asaas_token'] ?? '') ?: null,
            'sandbox' => !empty($_POST['sandbox']) ? 1 : 0,
            'active' => 1,
            'created_by' => $user['id'],
        ]);
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

        // Valor total: da proposta vinculada, se houver.
        $total = 0.0;
        if (!empty($contract['proposal_id'])) {
            $p = (new Proposal())->findById((int)$contract['proposal_id']);
            if ($p) $total = (float)$p['total'];
        }
        $id = $this->projects->createFromContract($contract, $total, $user['id']);
        $this->json(['success' => true, 'id' => $id]);
    }

    public function edit($id = null)
    {
        $this->requireModule('finance');
        if (!$id) $this->redirect('finance');
        $project = $this->projects->findById($id);
        if (!$project) $this->redirect('finance');
        $user = $this->currentUser();
        $this->view('commercial/finance_project', [
            'user' => $user,
            'project' => $project,
            'charges' => $this->projects->getCharges($id),
            'accounts' => $this->accounts->getAll(true),
            'canOnboard' => $this->projects->canStartOnboarding($id),
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

        $this->projects->replaceCharges($id, $plan, $accountByKind);
        if (isset($body['total'])) $this->projects->update($id, ['total_value' => FinanceRules::money($body['total'])]);

        $this->json(['success' => true, 'charges' => $this->projects->getCharges($id)]);
    }

    /** Marca manualmente uma cobrança como paga (quando não há Asaas no fluxo). */
    public function markPaid($chargeId = null)
    {
        $this->requireModule('finance');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$chargeId) $this->json(['error' => 'Requisição inválida'], 400);
        $destravou = $this->projects->markChargePaid((int)$chargeId);
        $this->json(['success' => true, 'onboarding_unlocked' => $destravou]);
    }

    // ================= Webhook Asaas (sem login) =================

    /**
     * Recebe eventos do Asaas. Localiza a cobrança por asaas_charge_id e, quando
     * confirmado o pagamento, marca como paga (pode destravar o onboarding).
     * Autenticação por token simples na query (?token=) comparado a Settings.
     */
    public function asaasWebhook()
    {
        // Validação leve por token (Asaas permite header/secret; aqui query token).
        $expected = (string) Config::get('asaas_webhook_token');
        $got = $_GET['token'] ?? ($_SERVER['HTTP_ASAAS_ACCESS_TOKEN'] ?? '');
        if ($expected !== '' && !hash_equals($expected, (string)$got)) {
            http_response_code(401);
            echo json_encode(['error' => 'token inválido']);
            return;
        }

        $raw = file_get_contents('php://input');
        $payload = json_decode($raw, true);
        $event = $payload['event'] ?? null;
        $asaasChargeId = $payload['payment']['id'] ?? ($payload['payment_id'] ?? null);
        $action = AsaasRules::interpretEvent(is_string($event) ? $event : null);

        if ($asaasChargeId && $action === 'paid') {
            $charge = $this->projects->findChargeByAsaasId($asaasChargeId);
            if ($charge && $charge['status'] !== 'paid') {
                $this->projects->markChargePaid((int)$charge['id']);
            }
        } elseif ($asaasChargeId && $action === 'overdue') {
            $charge = $this->projects->findChargeByAsaasId($asaasChargeId);
            if ($charge && $charge['status'] === 'pending') {
                Database::getInstance()->update('finance_charges', ['status' => 'overdue'], 'id = ?', [(int)$charge['id']]);
            }
        }
        http_response_code(200);
        echo json_encode(['ok' => true]);
    }
}
