<?php

/**
 * Contas a pagar dos prestadores (Guia de Contratação de Prestadores).
 * Acesso pelo módulo 'providers' (mesma área que gerencia o prestador).
 * Lançamentos são gerados automaticamente na assinatura do contrato
 * (ProviderController::onProviderSigned) e aqui a equipe os consulta/baixa.
 */
class PayableController extends Controller
{
    private $model;

    public function __construct()
    {
        $this->model = new Payable();
    }

    /** Lista os lançamentos de um prestador (JSON). */
    public function byProvider($providerId = null)
    {
        $this->requireModule('providers');
        if (!$providerId) $this->json(['error' => 'Prestador não informado.'], 400);
        $items = $this->model->getByProvider((int)$providerId);
        $this->json([
            'items'         => $items,
            'pending_total' => $this->model->pendingTotal((int)$providerId),
        ]);
    }

    /** Marca um lançamento como pago (POST). */
    public function pay($id = null)
    {
        $this->requireModule('providers');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        if (!$this->model->markPaid((int)$id)) $this->json(['error' => 'Lançamento não encontrado.'], 404);
        $this->json(['success' => true]);
    }

    /** Cancela um lançamento (POST). */
    public function cancel($id = null)
    {
        $this->requireModule('providers');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        if (!$this->model->cancel((int)$id)) $this->json(['error' => 'Lançamento não encontrado.'], 404);
        $this->json(['success' => true]);
    }

    /** Cria um lançamento avulso (POST) — ex.: fechamento de horas do período. */
    public function store($providerId = null)
    {
        $this->requireModule('providers');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$providerId) $this->json(['error' => 'Requisição inválida'], 400);

        $kind = PayableRules::normalizeKind($_POST['kind'] ?? null);
        if ($kind === null) $this->json(['error' => 'Tipo inválido (mensal, hora ou parcela).'], 400);

        $user = $this->currentUser();
        $id = $this->model->create([
            'provider_id'     => (int)$providerId,
            'kind'            => $kind,
            'description'     => trim($_POST['description'] ?? '') ?: null,
            'amount'          => PayableRules::money($_POST['amount'] ?? 0),
            'due_date'        => trim($_POST['due_date'] ?? '') ?: null,
            'recurring_cycle' => $kind === PayableRules::KIND_MENSAL ? 'monthly' : null,
            'status'          => PayableRules::STATUS_PENDING,
            'created_by'      => $user['id'] ?? null,
        ]);
        $this->json(['success' => true, 'id' => $id]);
    }
}
