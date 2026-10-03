<?php

/**
 * CRUD do catálogo de serviços pré-cadastrados (Fase 3 — esteira comercial).
 * Acesso pelo módulo 'service_catalog' (super_admin/developer/comercial).
 */
class ServiceCatalogController extends Controller
{
    private $model;

    public function __construct()
    {
        $this->model = new ServiceCatalog();
    }

    public function index()
    {
        $this->requireModule('service_catalog');
        $user = $this->currentUser();
        $services = $this->model->getAll(false);
        $this->view('commercial/services', ['user' => $user, 'services' => $services]);
    }

    /** Cria um serviço (POST, JSON). */
    public function store()
    {
        $this->requireModule('service_catalog');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);
        $user = $this->currentUser();

        $data = $this->readServiceInput();
        if ($data['name'] === '') $this->json(['error' => 'Informe o nome do serviço.'], 400);

        $id = $this->model->create($data + ['created_by' => $user['id']]);
        $this->json(['success' => true, 'id' => $id]);
    }

    /** Atualiza um serviço (POST, JSON). */
    public function update($id = null)
    {
        $this->requireModule('service_catalog');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        if (!$this->model->findById($id)) $this->json(['error' => 'Serviço não encontrado'], 404);

        $data = $this->readServiceInput();
        if ($data['name'] === '') $this->json(['error' => 'Informe o nome do serviço.'], 400);

        $this->model->update($id, $data);
        $this->json(['success' => true]);
    }

    /** Ativa/inativa um serviço (POST). Preserva histórico das propostas. */
    public function toggle($id = null)
    {
        $this->requireModule('service_catalog');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        $this->model->toggleActive($id);
        $this->json(['success' => true]);
    }

    /** Lê e normaliza os campos do formulário de serviço. */
    private function readServiceInput(): array
    {
        return [
            'name'        => trim($_POST['name'] ?? ''),
            'description' => trim($_POST['description'] ?? '') ?: null,
            'est_hours'   => ($_POST['est_hours'] ?? '') !== '' ? (float)$_POST['est_hours'] : null,
            'hourly_rate' => ($_POST['hourly_rate'] ?? '') !== '' ? (float)$_POST['hourly_rate'] : null,
            'is_hosting'  => !empty($_POST['is_hosting']) ? 1 : 0,
            'active'      => isset($_POST['active']) ? (!empty($_POST['active']) ? 1 : 0) : 1,
        ];
    }
}
