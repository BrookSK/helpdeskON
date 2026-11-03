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

        if (!FinancialRules::validMonth($filters['month'])) $filters['month'] = date('Y-m');

        try {
            $transactions = $this->transactions->getAll($filters);
        } catch (\Throwable $e) {
            $transactions = [];
        }

        // Recorrentes (salário, assinaturas...): ocorrências futuras ainda não
        // gravadas aparecem como PREVISTAS no mês delas.
        $monthEnd = date('Y-m-t', strtotime($filters['month'] . '-01'));
        $projected = FinancialRules::projectRecurring($this->transactions->recurringRows(), $monthEnd);
        $statusFilter = $filters['status'] ?? '';
        foreach ($projected as $p) {
            if (substr($p['due_date'], 0, 7) !== $filters['month']) continue;
            if (!empty($filters['kind']) && $p['kind'] !== $filters['kind']) continue;
            if (!empty($filters['source']) && $p['source'] !== $filters['source']) continue;
            if ($statusFilter !== '' && $statusFilter !== 'pending') continue;
            $transactions[] = $p;
        }
        usort($transactions, fn($a, $b) => strcmp((string)$b['due_date'], (string)$a['due_date']) ?: ((int)$b['id'] <=> (int)$a['id']));

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

        // Faturas por cartão (compras à vista + parcelas) e total pendente de
        // cada cartão (uso do limite).
        $invoices = $this->transactions->invoicesByCard();
        $cardPending = [];
        foreach ($invoices as $cid => $months) {
            $cardPending[$cid] = round(array_sum(array_column($months, 'pending')), 2);
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

        // Resumo do topo em regime de caixa: pago/recebido pela data do
        // pagamento, pendências até o fim do mês (atrasadas inclusas), e saldo
        // real das contas ativas.
        $accountsBalance = 0.0;
        foreach ($bankAccounts as $ba) {
            if ((int)($ba['active'] ?? 1) === 1) $accountsBalance += (float)($ba['balance'] ?? 0);
        }
        // O previsto dos recorrentes também entra no "a pagar/a receber" do mês.
        $summary = FinancialRules::cashSummary(
            array_merge($this->transactions->cashMovements(), $projected),
            $filters['month'],
            $accountsBalance
        );

        $this->view('financial/index', [
            'user'         => $user,
            'currentPage'  => 'financial_admin',
            'transactions' => $transactions,
            'summary'      => $summary,
            'cards'        => $this->cards->getAll(false),
            'cardPending'  => $cardPending,
            'invoices'     => $invoices,
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
        if ($data['user_id'] !== null && !$this->isTeamUser((int)$data['user_id'])) {
            $this->json(['error' => 'A pessoa escolhida não é da equipe.'], 400);
        }
        // Sem pessoa escolhida, não grava a coluna: funciona mesmo antes da migration 169.
        if ($data['user_id'] === null) unset($data['user_id']);

        // Compra no cartão: a data informada é a da compra; o vencimento é o
        // da fatura em que ela cai. Sem conta escolhida, usa a conta do cartão
        // (de onde a fatura é paga).
        $dueDates = null;
        if (!empty($data['card_id'])) {
            $card = $this->cards->findById((int)$data['card_id']);
            if (!$card) $this->json(['error' => 'Cartão não encontrado.'], 400);
            $this->applyCardCycle($data, $card, (string)$data['due_date']);
            if ((int)$data['installments'] > 1) {
                $dueDates = CardInvoiceRules::installmentDueDates(
                    (string)$data['purchase_date'],
                    $this->cardDay($card['closing_day'] ?? null),
                    $this->cardDay($card['due_day'] ?? null),
                    (int)$data['installments']
                );
            }
        }

        // Empréstimo com data: gera também o movimento inicial (o dinheiro que
        // entrou ou saiu no dia do empréstimo), no sentido contrário das parcelas.
        $initial = FinancialRules::loanInitialMovement($data, $_POST['loan_principal'] ?? '', date('Y-m-d'));
        if ($initial !== null) {
            if (!array_key_exists('user_id', $data)) unset($initial['user_id']);
            $initial['source'] = 'manual';
            $initial['created_by'] = $user['id'];
        }

        $pdo = Database::getInstance()->getConnection();
        $initialId = null;
        try {
            $pdo->beginTransaction();
            $id = $this->transactions->createWithInstallments($data, $dueDates);
            if ($initial !== null) $initialId = $this->transactions->create($initial);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $this->json(['error' => $this->schemaErrorHint($e, 'o lançamento', 168)], 500);
        }
        $this->json(['success' => true, 'id' => $id, 'initial_id' => $initialId]);
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

    /**
     * Edita um lançamento. Só grava os campos liberados por
     * FinancialRules::editableFields (Asaas, parcelado e pago têm restrições).
     * Mudar data ou cartão de uma compra no cartão recalcula a fatura.
     */
    public function update($id = null)
    {
        $this->requireSuperAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        $tx = $this->transactions->findById((int)$id);
        if (!$tx) $this->json(['error' => 'Lançamento não encontrado.'], 404);

        $allowed = FinancialRules::editableFields($tx);
        $data = [];

        if (in_array('category_id', $allowed, true) && array_key_exists('category_id', $_POST)) {
            $data['category_id'] = $_POST['category_id'] !== '' ? (int)$_POST['category_id'] : null;
        }
        if (in_array('account_id', $allowed, true) && array_key_exists('account_id', $_POST)) {
            $data['account_id'] = $_POST['account_id'] !== '' ? (int)$_POST['account_id'] : null;
        }
        if (in_array('user_id', $allowed, true) && array_key_exists('user_id', $_POST)) {
            $newUser = $_POST['user_id'] !== '' ? (int)$_POST['user_id'] : null;
            if ($newUser !== null && !$this->isTeamUser($newUser)) {
                $this->json(['error' => 'A pessoa escolhida não é da equipe.'], 400);
            }
            // Só grava se mudou (evita tocar a coluna quando a 169 está pendente).
            $current = isset($tx['user_id']) ? (int)$tx['user_id'] : null;
            if ($newUser !== $current) $data['user_id'] = $newUser;
        }
        if (in_array('description', $allowed, true) && array_key_exists('description', $_POST)) {
            $data['description'] = mb_substr(trim((string)$_POST['description']), 0, 255) ?: null;
        }
        if (in_array('amount', $allowed, true) && array_key_exists('amount', $_POST)) {
            $amount = FinanceRules::money($_POST['amount']);
            if ($amount <= 0) $this->json(['error' => 'Informe um valor maior que zero.'], 400);
            $data['amount'] = $amount;
        }

        // Data de referência atual: no cartão é a data da compra (pode estar
        // vazia em lançamentos antigos); fora do cartão é o vencimento.
        $currentCard = !empty($tx['card_id']) ? (int)$tx['card_id'] : null;
        $currentRef = $currentCard !== null ? ($tx['purchase_date'] ?? null) : ($tx['due_date'] ?? null);

        $postedDate = trim((string)($_POST['date'] ?? ''));
        if ($postedDate !== '') {
            if (strtotime($postedDate) === false) $this->json(['error' => 'Data inválida.'], 400);
            $postedDate = date('Y-m-d', strtotime($postedDate));
        }
        $postedCard = (array_key_exists('card_id', $_POST) && $_POST['card_id'] !== '') ? (int)$_POST['card_id'] : null;

        $dateChanged = in_array('date', $allowed, true) && $postedDate !== '' && $postedDate !== $currentRef;
        $cardChanged = in_array('card_id', $allowed, true) && array_key_exists('card_id', $_POST) && $postedCard !== $currentCard;
        if ($dateChanged || $cardChanged) {
            $cardId = $cardChanged ? $postedCard : $currentCard;
            $baseDate = $dateChanged
                ? $postedDate
                : (string)($tx['purchase_date'] ?? $tx['due_date'] ?? date('Y-m-d'));

            $data['card_id'] = $cardId;
            if ($cardId !== null) {
                $card = $this->cards->findById($cardId);
                if (!$card) $this->json(['error' => 'Cartão não encontrado.'], 400);
                $keepAccount = array_key_exists('account_id', $data) ? $data['account_id'] : ($tx['account_id'] ?? null);
                $data['account_id'] = $keepAccount;
                $this->applyCardCycle($data, $card, $baseDate);
            } else {
                $data['due_date'] = $baseDate;
                if (array_key_exists('purchase_date', $tx)) $data['purchase_date'] = null;
            }
        }

        try {
            $this->transactions->update((int)$id, $data);
        } catch (\Throwable $e) {
            $this->json(['error' => $this->schemaErrorHint($e, 'o lançamento', 168)], 500);
        }
        $this->json(['success' => true, 'due_date' => $data['due_date'] ?? $tx['due_date']]);
    }

    /** Paga a fatura de um cartão num mês: POST financial/payInvoice/{cardId}/{YYYY-MM}. */
    public function payInvoice($cardId = null, $ym = null)
    {
        $this->requireSuperAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$cardId || !FinancialRules::validMonth($ym)) {
            $this->json(['error' => 'Requisição inválida'], 400);
        }
        if (!$this->cards->findById((int)$cardId)) $this->json(['error' => 'Cartão não encontrado.'], 404);
        $paid = $this->transactions->payInvoice((int)$cardId, (string)$ym);
        $this->json(['success' => true, 'paid' => $paid]);
    }

    /**
     * Paga uma ocorrência prevista de um recorrente:
     * POST financial/payProjected/{idDaOcorrenciaBase}/{YYYY-MM-DD}.
     */
    public function payProjected($fromId = null, $date = null)
    {
        $this->requireSuperAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$fromId || !is_string($date)
            || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            $this->json(['error' => 'Requisição inválida'], 400);
        }
        try {
            $id = $this->transactions->payProjected((int)$fromId, $date);
        } catch (\Throwable $e) {
            $this->json(['error' => $this->schemaErrorHint($e, 'o pagamento', 169)], 500);
        }
        if ($id === null) $this->json(['error' => 'Essa ocorrência não é válida ou já foi lançada. Recarregue a página.'], 409);
        $this->json(['success' => true, 'id' => $id]);
    }

    /** Quita uma parcela específica: POST financial/payInstallment/{installmentId}. */
    public function payInstallment($id = null)
    {
        $this->requireSuperAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        $done = $this->transactions->payInstallment((int)$id);
        $this->json(['success' => true, 'fully_paid' => $done]);
    }

    /** Estorna uma parcela específica: POST financial/unpayInstallment/{installmentId}. */
    public function unpayInstallment($id = null)
    {
        $this->requireSuperAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        $this->transactions->unpayInstallment((int)$id);
        $this->json(['success' => true]);
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

    /** Edita nome, banco e saldo inicial de uma conta bancária. */
    public function accountUpdate($id = null)
    {
        $this->requireSuperAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        if (!$this->bankAccounts->findById((int)$id)) $this->json(['error' => 'Conta não encontrada.'], 404);

        $validation = FinancialAccountRules::validateBankAccount($_POST);
        if (!$validation['ok']) $this->json(['error' => implode(' ', $validation['errors'])], 400);
        $data = $validation['data'];
        unset($data['active']); // editar não reativa/inativa a conta

        try {
            $this->bankAccounts->update((int)$id, $data);
        } catch (\Throwable $e) {
            $this->json(['error' => $this->schemaErrorHint($e, 'a conta', 167)], 500);
        }
        $this->json(['success' => true]);
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

    /** Usuário existe e é da equipe (não é cliente)? */
    private function isTeamUser(int $userId): bool
    {
        try {
            $u = Database::getInstance()->fetch("SELECT role FROM users WHERE id = ?", [$userId]);
        } catch (\Throwable $e) {
            return false;
        }
        return $u && ($u['role'] ?? '') !== 'client';
    }

    /** Dia do cartão (fechamento/vencimento) como int ou null. */
    private function cardDay($v): ?int
    {
        return ($v === null || $v === '') ? null : (int)$v;
    }

    /**
     * Aplica o ciclo da fatura num lançamento de cartão: purchase_date = data
     * da compra; due_date = vencimento da fatura. Sem conta definida, usa a
     * conta do cartão.
     */
    private function applyCardCycle(array &$data, array $card, string $purchaseDate): void
    {
        $inv = CardInvoiceRules::invoiceFor(
            $purchaseDate,
            $this->cardDay($card['closing_day'] ?? null),
            $this->cardDay($card['due_day'] ?? null)
        );
        $data['purchase_date'] = date('Y-m-d', strtotime($purchaseDate));
        $data['due_date'] = $inv['due_date'];
        if (empty($data['account_id']) && !empty($card['account_id'])) {
            $data['account_id'] = (int)$card['account_id'];
        }
    }

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
            return "Não foi possível salvar {$what}: uma coluna nova não existe neste banco. Rode as migrations pendentes do financeiro (167b, 168 e 169). (Detalhe: {$msg})";
        }
        if (stripos($msg, 'foreign key') !== false || stripos($msg, '1452') !== false) {
            return "Não foi possível salvar {$what}: referência inválida (conta/categoria inexistente). (Detalhe: {$msg})";
        }
        return "Não foi possível salvar {$what}. (Detalhe: {$msg})";
    }
}