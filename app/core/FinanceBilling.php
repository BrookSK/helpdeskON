<?php

/**
 * Emissão no Asaas e baixa pelo webhook (change 02, D5).
 *
 * Toda emissão é um clique humano. A parcela é reservada no banco antes da
 * chamada (issuing_at), leva a externalReference "hd-charge-<id>" e, antes de
 * criar, o sistema procura no Asaas uma cobrança com essa referência: assim um
 * estouro de tempo seguido de novo clique não cria a cobrança duas vezes. A
 * mensalidade vira assinatura ("hd-sub-<projeto>").
 *
 * A API é injetável para os testes: fábrica que recebe a linha da conta
 * (finance_accounts) e devolve um objeto com os métodos de AsaasApi.
 */
class FinanceBilling
{
    /** Reserva mais velha que isso é de uma tentativa que caiu e pode ser retomada. */
    public const STALE_RESERVATION_SECONDS = 120;

    private $db;
    private $projects;
    private $accounts;
    private $apiFactory;

    public function __construct(?FinanceProject $projects = null, ?callable $apiFactory = null)
    {
        $this->db = Database::getInstance();
        $this->projects = $projects ?? new FinanceProject();
        $this->accounts = new FinanceAccount();
        $this->apiFactory = $apiFactory ?? fn(array $account) => AsaasApi::fromAccount($account);
    }

    /**
     * Emite a cobrança no Asaas: parcela avulsa ou, para a mensalidade, a
     * assinatura. Devolve ['ok', 'code', 'error'] e, no sucesso, a cobrança.
     */
    public function issue(int $chargeId, ?int $userId = null): array
    {
        $charge = $this->projects->findChargeById($chargeId);
        if (!$charge) return $this->fail(404, 'Cobrança não encontrada.');
        if ($charge['status'] !== 'pending') return $this->fail(409, 'Só cobranças pendentes podem ser emitidas.');
        if (!empty($charge['asaas_charge_id']) || !empty($charge['asaas_subscription_id'])) {
            return $this->fail(409, 'Esta cobrança já foi emitida no Asaas.');
        }
        if (empty($charge['due_date'])) return $this->fail(400, 'Informe o vencimento antes de emitir.');
        $account = !empty($charge['account_id']) ? $this->accounts->findById((int)$charge['account_id']) : null;
        if (!$account || (int)($account['active'] ?? 1) !== 1) return $this->fail(400, 'Escolha uma conta Asaas ativa para esta cobrança.');
        $api = ($this->apiFactory)($account);
        if (!$api->isConfigured()) return $this->fail(400, 'A conta Asaas "' . $account['name'] . '" não tem token configurado.');
        $project = $this->projects->findById((int)$charge['project_id']);

        if (!$this->reserve($chargeId)) return $this->fail(409, 'Esta cobrança já está sendo emitida. Aguarde e recarregue a tela.');

        try {
            $customer = $this->ensureCustomer($project, $account, $api);
            if (!$customer['ok']) return $this->release($chargeId, 502, $customer['error']);

            return $charge['kind'] === 'recurring'
                ? $this->issueSubscription($charge, $project, $customer['id'], $api, $account, $userId)
                : $this->issueCharge($charge, $project, $customer['id'], $api, $account, $userId);
        } catch (\Throwable $e) {
            return $this->release($chargeId, 500, 'Falha inesperada ao emitir: ' . $e->getMessage());
        }
    }

    private function issueCharge(array $charge, array $project, string $customerId, $api, array $account, ?int $userId): array
    {
        $id = (int)$charge['id'];
        $ref = AsaasRules::chargeReference($id);
        // Tentativa anterior que estourou o tempo pode ter criado a cobrança.
        $found = $api->findPaymentByReference($ref);
        $payment = !empty($found['success']) ? AsaasRules::firstListed($found['data'] ?? null) : null;
        if (!$payment) {
            $created = $api->createCharge($customerId, (float)$charge['amount'], $charge['due_date'], $charge['method'],
                $this->description($charge, $project), $ref);
            if (empty($created['success']) || empty($created['data']['id'])) {
                return $this->release($id, 502, 'O Asaas recusou a cobrança: ' . ($created['error'] ?? 'resposta sem id'));
            }
            $payment = $created['data'];
        }
        $this->db->update('finance_charges', [
            'asaas_charge_id' => (string)$payment['id'],
            'invoice_url' => isset($payment['invoiceUrl']) ? mb_substr((string)$payment['invoiceUrl'], 0, 500) : null,
            'bank_slip_url' => isset($payment['bankSlipUrl']) ? mb_substr((string)$payment['bankSlipUrl'], 0, 500) : null,
            'issued_at' => date('Y-m-d H:i:s'), 'issuing_at' => null, 'issue_error' => null,
        ], 'id = ?', [$id]);
        $this->projects->addEvent((int)$project['id'], $id, $userId, 'issued',
            ($charge['description'] ?: 'Cobrança') . ' emitida no Asaas (' . $payment['id'] . ', conta ' . $account['name']
            . ($api->isSandbox() ? ', SANDBOX' : ', PRODUÇÃO') . ')');
        return ['ok' => true, 'code' => 200, 'charge' => $this->projects->findChargeById($id)];
    }

    private function issueSubscription(array $charge, array $project, string $customerId, $api, array $account, ?int $userId): array
    {
        $id = (int)$charge['id'];
        if (!empty($project['asaas_subscription_id'])) {
            return $this->release($id, 409, 'O projeto já tem assinatura no Asaas (' . $project['asaas_subscription_id'] . ').');
        }
        $ref = AsaasRules::subscriptionReference((int)$project['id']);
        $found = $api->findSubscriptionByReference($ref);
        $sub = !empty($found['success']) ? AsaasRules::firstListed($found['data'] ?? null) : null;
        if (!$sub) {
            $created = $api->createSubscription($customerId, (float)$charge['amount'], $charge['recurring_cycle'] ?: 'monthly',
                $charge['recurring_start'] ?: $charge['due_date'], $charge['method'], $this->description($charge, $project), $ref);
            if (empty($created['success']) || empty($created['data']['id'])) {
                return $this->release($id, 502, 'O Asaas recusou a assinatura: ' . ($created['error'] ?? 'resposta sem id'));
            }
            $sub = $created['data'];
        }
        $subId = (string)$sub['id'];
        $this->projects->update((int)$project['id'], ['asaas_subscription_id' => $subId]);
        $this->db->update('finance_charges', [
            'asaas_subscription_id' => $subId, 'issued_at' => date('Y-m-d H:i:s'), 'issuing_at' => null, 'issue_error' => null,
        ], 'id = ?', [$id]);
        $this->projects->addEvent((int)$project['id'], $id, $userId, 'issued',
            'Assinatura mensal de R$ ' . number_format((float)$charge['amount'], 2, ',', '.') . ' criada no Asaas (' . $subId . ', conta ' . $account['name']
            . ($api->isSandbox() ? ', SANDBOX' : ', PRODUÇÃO') . ')');
        return ['ok' => true, 'code' => 200, 'charge' => $this->projects->findChargeById($id)];
    }

    /**
     * Cliente no Asaas pelo CPF/CNPJ do contrato: reaproveita o do projeto (na
     * mesma conta), depois procura na conta e só então cria.
     */
    private function ensureCustomer(array $project, array $account, $api): array
    {
        if (!empty($project['asaas_customer_id']) && (int)$project['asaas_customer_account_id'] === (int)$account['id']) {
            return ['ok' => true, 'id' => (string)$project['asaas_customer_id']];
        }
        $contract = !empty($project['contract_id']) ? (new Contract())->findById((int)$project['contract_id']) : null;
        $document = (string)($contract['client_document'] ?? '');
        if ($document === '' && !empty($project['company_id'])) {
            $co = $this->db->fetch("SELECT document FROM companies WHERE id = ?", [(int)$project['company_id']]);
            $document = (string)($co['document'] ?? '');
        }
        if (!ContractRules::hasClientDocument($document)) {
            return ['ok' => false, 'error' => 'O contrato não tem CPF/CNPJ do cliente. Informe o documento no contrato.'];
        }
        $found = $api->findCustomerByDocument($document);
        if (empty($found['success'])) return ['ok' => false, 'error' => 'Falha ao procurar o cliente no Asaas: ' . ($found['error'] ?? '')];
        $customer = AsaasRules::firstListed($found['data'] ?? null);
        if (!$customer) {
            $created = $api->createCustomer((string)($contract['client_name'] ?? $project['title']), $document,
                $contract['client_email'] ?? null, $contract['client_phone'] ?? null, 'hd-project-' . $project['id']);
            if (empty($created['success']) || empty($created['data']['id'])) {
                return ['ok' => false, 'error' => 'O Asaas recusou o cliente: ' . ($created['error'] ?? 'resposta sem id')];
            }
            $customer = $created['data'];
            $this->projects->addEvent((int)$project['id'], null, null, 'customer', 'Cliente criado no Asaas (' . $customer['id'] . ')');
        }
        $this->projects->update((int)$project['id'], [
            'asaas_customer_id' => (string)$customer['id'], 'asaas_customer_account_id' => (int)$account['id'],
        ]);
        return ['ok' => true, 'id' => (string)$customer['id']];
    }

    /** Reserva atômica: só um clique passa. Reserva velha (tentativa que caiu) pode ser retomada. */
    private function reserve(int $chargeId): bool
    {
        return $this->db->query(
            "UPDATE finance_charges SET issuing_at = NOW(), issue_error = NULL
             WHERE id = ? AND status = 'pending' AND asaas_charge_id IS NULL AND asaas_subscription_id IS NULL
               AND (issuing_at IS NULL OR issuing_at < NOW() - INTERVAL " . self::STALE_RESERVATION_SECONDS . " SECOND)",
            [$chargeId]
        )->rowCount() === 1;
    }

    /** Libera a reserva, guarda o erro para a tela e devolve a falha. */
    private function release(int $chargeId, int $code, string $error): array
    {
        $this->db->update('finance_charges', ['issuing_at' => null, 'issue_error' => mb_substr($error, 0, 500)], 'id = ?', [$chargeId]);
        return $this->fail($code, $error);
    }

    private function fail(int $code, string $error): array
    {
        return ['ok' => false, 'code' => $code, 'error' => $error];
    }

    private function description(array $charge, array $project): string
    {
        return mb_substr(trim(($charge['description'] ?: 'Cobrança') . ' — ' . preg_replace('/^Financeiro — /u', '', (string)$project['title'])), 0, 200);
    }

    // ================= Webhook =================

    /**
     * Trata um evento do webhook do Asaas (já autenticado pelo token). A baixa é
     * idempotente; pagamento de assinatura sem linha local cria a mensalidade
     * paga no projeto da assinatura; a entrada paga cria o onboarding.
     *
     * @return array{action:string,charge_id:?int,unlocked:bool,onboarding_id:?int}
     */
    public function handleWebhook(array $payload): array
    {
        $out = ['action' => 'ignore', 'charge_id' => null, 'unlocked' => false, 'onboarding_id' => null];
        $payment = is_array($payload['payment'] ?? null) ? $payload['payment'] : [];
        $asaasId = (string)($payment['id'] ?? '');
        $action = AsaasRules::interpretEvent(is_string($payload['event'] ?? null) ? $payload['event'] : null);
        if ($asaasId === '' || $action === 'ignore') return $out;
        $out['action'] = $action;

        $charge = $this->projects->findChargeByAsaasId($asaasId);
        if (!$charge) $charge = $this->linkByReference($payment);
        if (!$charge && $action === 'paid' && !empty($payment['subscription'])) {
            $charge = $this->subscriptionCharge($payment);
        }
        if (!$charge) return $out;
        $out['charge_id'] = (int)$charge['id'];

        if ($action === 'paid') {
            $out['unlocked'] = $this->projects->markChargePaid((int)$charge['id'], null, 'asaas');
            if ($out['unlocked']) {
                $onb = (new OnboardingStarter($this->projects))->ensureForFinanceProject((int)$charge['project_id']);
                $out['onboarding_id'] = $onb['id'] ?? null;
            }
        } elseif ($action === 'overdue' && $charge['status'] === 'pending') {
            $this->db->update('finance_charges', ['status' => 'overdue'], 'id = ?', [(int)$charge['id']]);
            $this->projects->addEvent((int)$charge['project_id'], (int)$charge['id'], null, 'overdue', ($charge['description'] ?: 'Cobrança') . ' vencida');
        }
        return $out;
    }

    /**
     * Cobrança criada no Asaas cuja resposta não chegou (estouro de tempo): a
     * externalReference aponta a parcela local, que é ligada agora.
     */
    private function linkByReference(array $payment): ?array
    {
        $localId = AsaasRules::chargeIdFromReference($payment['externalReference'] ?? null);
        if (!$localId) return null;
        $this->db->query(
            "UPDATE finance_charges SET asaas_charge_id = ?, issuing_at = NULL, issued_at = COALESCE(issued_at, NOW()),
                invoice_url = COALESCE(invoice_url, ?)
             WHERE id = ? AND asaas_charge_id IS NULL",
            [(string)$payment['id'], isset($payment['invoiceUrl']) ? mb_substr((string)$payment['invoiceUrl'], 0, 500) : null, $localId]
        );
        return $this->projects->findChargeByAsaasId((string)$payment['id']);
    }

    /** Mensalidade paga vinda da assinatura: cria a linha recorrente (chave única por asaas_charge_id). */
    private function subscriptionCharge(array $payment): ?array
    {
        $project = $this->db->fetch("SELECT * FROM finance_projects WHERE asaas_subscription_id = ? LIMIT 1", [(string)$payment['subscription']]);
        if (!$project) return null;
        $template = $this->db->fetch(
            "SELECT account_id, method FROM finance_charges WHERE project_id = ? AND kind = 'recurring' AND asaas_subscription_id = ? LIMIT 1",
            [(int)$project['id'], (string)$payment['subscription']]
        );
        $due = isset($payment['dueDate']) ? date('Y-m-d', strtotime((string)$payment['dueDate'])) : null;
        try {
            $this->projects->addCharge([
                'project_id' => (int)$project['id'], 'account_id' => $template['account_id'] ?? null, 'kind' => 'recurring',
                'description' => 'Mensalidade' . ($due ? ' ' . date('m/Y', strtotime($due)) : ''),
                'amount' => round((float)($payment['value'] ?? 0), 2), 'due_date' => $due,
                'method' => $template['method'] ?? null, 'recurring_cycle' => 'monthly', 'status' => 'pending',
                'asaas_charge_id' => (string)$payment['id'], 'asaas_subscription_id' => (string)$payment['subscription'],
                'invoice_url' => isset($payment['invoiceUrl']) ? mb_substr((string)$payment['invoiceUrl'], 0, 500) : null,
                'issued_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\PDOException $e) {
            // Evento repetido ao mesmo tempo: a chave única já criou a linha.
            if ($e->getCode() !== '23000') throw $e;
        }
        return $this->projects->findChargeByAsaasId((string)$payment['id']);
    }
}
