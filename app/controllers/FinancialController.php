<?php

/**
 * Controle Financeiro da empresa — aba EXCLUSIVA de super_admin.
 *
 * Diferente do FinanceController (esteira comercial, cobranças de contratos):
 * aqui é o caixa da empresa. Lançamentos manuais de ganhos, gastos, salários,
 * empréstimos e transferências; cartões de crédito com parcelamentos; e
 * importação das cobranças de TODAS as contas Asaas configuradas
 * (finance_accounts), consolidando tudo num painel (recebido, a receber,
 * pago, a pagar, saldo).
 */
class FinancialController extends Controller
{
    private $transactions;
    private $cards;
    private $asaasAccounts;
    private $commissions;
    private $bankAccounts;
    private $categories;

    public function __construct()
    {
        $this->transactions = new FinancialTransaction();
        $this->cards = new FinancialCard();
        $this->asaasAccounts = new FinanceAccount();
        $this->commissions = new FinancialCommission();
        $this->bankAccounts = new FinancialBankAccount();
        $this->categories = new FinancialCategory();
    }

    /** Painel principal (resumo + lançamentos + cartões + contas Asaas). */
    public function index()
    {
        $this->requireSuperAdmin();
        $user = $this->currentUser();

        $filters = [
            'kind'   => $_GET['kind'] ?? '',
            'status' => $_GET['status'] ?? '',
            'source' => $_GET['source'] ?? '',
            'month'  => $_GET['month'] ?? date('Y-m'),
        ];

        try {
            $transactions = $this->transactions->getAll($filters);
            $allForSummary = $this->transactions->getAll(['month' => $filters['month']]);
            $summary = FinancialRules::summary($allForSummary);
        } catch (\Throwable $e) {
            $transactions = [];
            $summary = FinancialRules::summary([]);
        }

        try {
            $commissions = $this->commissions->getAll();
            $commissionsSummary = FinancialRules::summaryCommissions($commissions);
        } catch (\Throwable $e) {
            $commissions = [];
            $commissionsSummary = ['to_pay' => 0.0, 'paid' => 0.0];
        }

        // Ganhos (origem possível de comissões) + usuários internos (recebedores).
        try {
            $gainOptions = array_values(array_filter($this->transactions->getAll(['kind' => 'ganho']), fn($t) => $t['source'] !== 'asaas'));
        } catch (\Throwable $e) {
            $gainOptions = [];
        }
        try {
            $users = Database::getInstance()->fetchAll("SELECT id, name, role FROM users WHERE role <> 'client' ORDER BY name LIMIT 200");
        } catch (\Throwable $e) {
            $users = [];
        }

        // Contas bancárias + saldo calculado, categorias cadastradas.
        try {
            $bankAccounts = $this->bankAccounts->getAll(false);
            $bankAccounts = FinancialAccountRules::summarizeAccounts($bankAccounts, $this->transactions->movementsByAccount());
        } catch (\Throwable $e) {
            $bankAccounts = [];
        }
        try {
            $categories = $this->categories->getAll(false);
        } catch (\Throwable $e) {
            $categories = [];
        }

        $this->view('financial/index', [
            'user'         => $user,
            'currentPage'  => 'financial_admin',
            'transactions' => $transactions,
            'summary'      => $summary,
            'cards'        => $this->cards->getAll(false),
            'cardPending'  => $this->transactions->pendingInstallmentsByCard(),
            'accounts'     => $this->asaasAccounts->getAll(false),
            'bankAccounts' => $bankAccounts,
            'categories'   => $categories,
            'filters'      => $filters,
            'commissions'  => $commissions,
            'commissionsSummary' => $commissionsSummary,
            'gainOptions'  => $gainOptions,
            'users'        => $users,
        ]);
    }

    /** Cria um lançamento manual (gera parcelas quando parcelado no cartão). */
    public function store()
    {
        $this->requireSuperAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);
        $user = $this->currentUser();

        $validation = FinancialRules::validateTransaction($_POST);
        if (!$validation['ok']) $this->json(['error' => implode(' ', $validation['errors'])], 400);
        $data = $validation['data'];
        $data['source'] = 'manual';
        $data['created_by'] = $user['id'];
        if ($data['status'] === 'paid') $data['paid_at'] = date('Y-m-d H:i:s');

        try {
            $id = $this->transactions->createWithInstallments($data);
        } catch (\Throwable $e) {
            $this->json(['error' => $this->schemaErrorHint($e, 'o lançamento', 167)], 500);
        }
        $this->json(['success' => true, 'id' => $id]);
    }

    /**
     * Paga/quita um lançamento. Com parcelas: quita a PRÓXIMA parcela
     * pendente (a transação fecha sozinha na última).
     */
    public function pay($id = null)
    {
        $this->requireSuperAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        $done = $this->transactions->markPaid((int)$id);
        $this->json(['success' => true, 'fully_paid' => $done]);
    }

    /** Estorna: transação (e parcelas) voltam a pendentes. */
    public function unpay($id = null)
    {
        $this->requireSuperAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        $this->transactions->markUnpaid((int)$id);
        $this->json(['success' => true]);
    }

    public function remove($id = null)
    {
        $this->requireSuperAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        $t = $this->transactions->findById((int)$id);
        if (!$t) $this->json(['error' => 'Lançamento não encontrado.'], 404);
        $this->transactions->remove((int)$id);
        $this->json(['success' => true]);
    }

    // ================= Cartões de crédito =================

    public function storeCard()
    {
        $this->requireSuperAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);
        $user = $this->currentUser();

        $name = trim($_POST['name'] ?? '');
        if ($name === '') $this->json(['error' => 'Informe o nome do cartão.'], 400);
        $limit = FinanceRules::money($_POST['limit'] ?? 0);
        $closing = (int)($_POST['closing_day'] ?? 0);
        $due = (int)($_POST['due_day'] ?? 0);
        $accountId = !empty($_POST['account_id']) ? (int)$_POST['account_id'] : null;

        try {
            $id = $this->cards->create([
                'name'        => $name,
                'limit'       => $limit,
                'closing_day' => ($closing >= 1 && $closing <= 31) ? $closing : null,
                'due_day'     => ($due >= 1 && $due <= 31) ? $due : null,
                'account_id'  => $accountId,
                'active'      => 1,
                'created_by'  => $user['id'],
            ]);
        } catch (\Throwable $e) {
            $this->json(['error' => $this->schemaErrorHint($e, 'o cartão', 167)], 500);
        }
        $this->json(['success' => true, 'id' => $id]);
    }

    public function cardToggle($id = null)
    {
        $this->requireSuperAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        $active = $this->cards->toggleActive((int)$id);
        $this->json(['success' => true, 'active' => $active]);
    }

    // ================= Comissões =================

    /**
     * Cria uma comissão vinculada a um ganho: pra quem (usuário ou nome),
     * quanto (valor fixo ou % sobre o ganho) e em quantas parcelas.
     */
    public function commissionStore()
    {
        $this->requireSuperAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);
        $user = $this->currentUser();

        $tx = $this->transactions->findById((int)($_POST['transaction_id'] ?? 0));
        if (!$tx || $tx['kind'] !== 'ganho' || $tx['direction'] !== 'in') {
            $this->json(['error' => 'A comissão deve estar vinculada a um ganho (entrada).'], 400);
        }

        $validation = FinancialRules::validateCommission($_POST, (float)$tx['amount']);
        if (!$validation['ok']) $this->json(['error' => implode(' ', $validation['errors'])], 400);
        $data = $validation['data'];
        $data['status'] = 'pending';
        $data['created_by'] = $user['id'];

        try {
            $id = $this->commissions->create($data);
        } catch (\Throwable $e) {
            $this->json(['error' => 'Não foi possível salvar a comissão. Rode a migration 156.'], 500);
        }
        $this->json(['success' => true, 'id' => $id]);
    }

    /** Paga a próxima parcela da comissão. */
    public function commissionPay($id = null)
    {
        $this->requireSuperAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        $done = $this->commissions->markPaid((int)$id);
        $this->json(['success' => true, 'fully_paid' => $done]);
    }

    public function commissionRemove($id = null)
    {
        $this->requireSuperAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        $this->commissions->remove((int)$id);
        $this->json(['success' => true]);
    }

    // ================= Contas bancárias =================

    public function storeAccount()
    {
        $this->requireSuperAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);
        $user = $this->currentUser();

        $validation = FinancialAccountRules::validateBankAccount($_POST);
        if (!$validation['ok']) $this->json(['error' => implode(' ', $validation['errors'])], 400);
        $data = $validation['data'];
        $data['created_by'] = $user['id'];

        try {
            $id = $this->bankAccounts->create($data);
        } catch (\Throwable $e) {
            $this->json(['error' => $this->schemaErrorHint($e, 'a conta', 167)], 500);
        }
        $this->json(['success' => true, 'id' => $id]);
    }

    public function accountToggle($id = null)
    {
        $this->requireSuperAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        $active = $this->bankAccounts->toggleActive((int)$id);
        $this->json(['success' => true, 'active' => $active]);
    }

    public function accountRemove($id = null)
    {
        $this->requireSuperAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        $this->bankAccounts->remove((int)$id);
        $this->json(['success' => true]);
    }

    // ================= Categorias =================

    public function storeCategory()
    {
        $this->requireSuperAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);
        $user = $this->currentUser();

        $validation = FinancialAccountRules::validateCategory($_POST);
        if (!$validation['ok']) $this->json(['error' => implode(' ', $validation['errors'])], 400);
        $data = $validation['data'];
        $data['created_by'] = $user['id'];

        try {
            $id = $this->categories->create($data);
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            if (stripos($msg, 'Duplicate') !== false || stripos($msg, '23000') !== false) {
                $this->json(['error' => 'Já existe uma categoria com esse nome e tipo.'], 409);
            }
            $this->json(['error' => $this->schemaErrorHint($e, 'a categoria', 167)], 500);
        }
        $this->json(['success' => true, 'id' => $id]);
    }

    public function categoryToggle($id = null)
    {
        $this->requireSuperAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        $active = $this->categories->toggleActive((int)$id);
        $this->json(['success' => true, 'active' => $active]);
    }

    public function categoryRemove($id = null)
    {
        $this->requireSuperAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        $this->categories->remove((int)$id);
        $this->json(['success' => true]);
    }

    // ================= Importação Asaas =================

    /**
     * Puxa as cobranças de TODAS as contas Asaas ativas com token e importa
     * como lançamentos (kind=ganho, source=asaas). Dedupe por asaas_payment_id
     * — re-sincronizar é seguro.
     */
    public function asaasSync()
    {
        $this->requireSuperAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);
        $user = $this->currentUser();

        $accounts = $this->asaasAccounts->getAll(true);
        $imported = 0;
        $skipped = 0;
        $failed = [];

        foreach ($accounts as $acc) {
            if (!AsaasRules::isConfigured($acc['asaas_token'] ?? null)) {
                $failed[] = $acc['name'] . ': sem token configurado';
                continue;
            }
            $api = AsaasApi::fromAccount($acc);
            $resp = $api->listPayments(['limit' => 100]);
            if (empty($resp['success'])) {
                $failed[] = $acc['name'] . ': ' . ($resp['error'] ?? 'erro na chamada');
                continue;
            }
            $payments = $resp['data']['data'] ?? [];
            if (!is_array($payments)) continue;
            foreach ($payments as $payment) {
                $row = FinancialRules::importFromAsaasPayment($payment, (int)$acc['id']);
                if ($row === null) { $skipped++; continue; }
                if ($this->transactions->existsByAsaasId($row['asaas_payment_id'])) { $skipped++; continue; }
                $row['created_by'] = $user['id'];
                try {
                    $this->transactions->create($row);
                    $imported++;
                } catch (\Throwable $e) {
                    $skipped++;
                }
            }
        }

        $this->json([
            'success'   => true,
            'imported'  => $imported,
            'skipped'   => $skipped,
            'accounts'  => count($accounts),
            'failed'    => $failed,
        ]);
    }

    // ================= Helpers =================

    /**
     * Mensagem de erro útil a partir de uma exceção de banco ao salvar.
     * Em vez de mascarar tudo como "rode a migration X", identifica a causa
     * provável (tabela ausente, coluna ausente, FK inválida) e inclui o
     * detalhe do driver para facilitar o diagnóstico em produção.
     *
     * @param string $what rótulo do que se tentava salvar (ex.: 'o cartão')
     * @param int    $mig  nº da migration que cria/ajusta o schema relacionado
     */
    private function schemaErrorHint(\Throwable $e, string $what, int $mig): string
    {
        $msg = $e->getMessage();

        if (stripos($msg, "doesn't exist") !== false || stripos($msg, '42S02') !== false) {
            return "Não foi possível salvar {$what}: uma tabela do Controle Financeiro não existe neste banco. Rode a migration {$mig}. (Detalhe: {$msg})";
        }
        if (stripos($msg, 'Unknown column') !== false || stripos($msg, '42S22') !== false) {
            return "Não foi possível salvar {$what}: uma coluna nova não existe neste banco (migration {$mig} aplicada só pela metade). Rode a 167b (idempotente). (Detalhe: {$msg})";
        }
        if (stripos($msg, 'foreign key') !== false || stripos($msg, '1452') !== false) {
            return "Não foi possível salvar {$what}: referência inválida (conta/categoria inexistente). (Detalhe: {$msg})";
        }
        return "Não foi possível salvar {$what}. (Detalhe: {$msg})";
    }
}