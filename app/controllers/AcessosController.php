<?php

class AcessosController extends Controller
{
    private $model;

    public function __construct()
    {
        $this->model = new SystemAccess();
    }

    // ------------------------------------------------------------------ //
    //  index — tela principal (cards agrupados por categoria)             //
    // ------------------------------------------------------------------ //

    public function index()
    {
        $this->requireModule('acessos');
        $user      = $this->currentUser();
        $accesses  = $this->model->getAllByUser($user['id']);
        $companies = (new Company())->getAll();

        $this->view('admin/acessos', [
            'user'      => $user,
            'accesses'  => $accesses,
            'companies' => $companies,
        ]);
    }

    // ------------------------------------------------------------------ //
    //  store — criar novo acesso (POST AJAX)                              //
    // ------------------------------------------------------------------ //

    public function store()
    {
        $this->requireModule('acessos');
        $this->requireAjax();
        $this->requireCsrf();

        $user = $this->currentUser();

        $title    = trim($_POST['title']      ?? '');
        $category = trim($_POST['category']   ?? 'Geral');
        $url      = trim($_POST['url']        ?? '');
        $username = trim($_POST['username']   ?? '');
        $password = $_POST['password']        ?? '';
        $notes    = trim($_POST['notes']      ?? '');
        $companyId = $_POST['company_id']     ?? null;

        if (empty($title)) {
            $this->json(['error' => 'O título é obrigatório.'], 422);
        }

        $id = $this->model->create([
            'title'      => $title,
            'category'   => $category,
            'url'        => $url,
            'username'   => $username,
            'password'   => $password,
            'notes'      => $notes,
            'company_id' => $companyId,
        ], $user['id']);

        $this->json(['success' => true, 'id' => $id]);
    }

    // ------------------------------------------------------------------ //
    //  get — retorna dados de um acesso para preencher o form (GET AJAX)  //
    // ------------------------------------------------------------------ //

    public function get($id = null)
    {
        $this->requireModule('acessos');
        $this->requireAjax();

        $user = $this->currentUser();
        $row  = $this->model->findById((int)$id, $user['id']);

        if (!$row) {
            $this->json(['error' => 'Acesso não encontrado.'], 404);
        }

        // Nunca devolve a senha neste endpoint — só via /reveal
        unset($row['password_enc']);
        $this->json($row);
    }

    // ------------------------------------------------------------------ //
    //  update — atualizar acesso existente (POST AJAX)                    //
    // ------------------------------------------------------------------ //

    public function update($id = null)
    {
        $this->requireModule('acessos');
        $this->requireAjax();
        $this->requireCsrf();

        $user = $this->currentUser();

        $title    = trim($_POST['title']      ?? '');
        $category = trim($_POST['category']   ?? 'Geral');
        $url      = trim($_POST['url']        ?? '');
        $username = trim($_POST['username']   ?? '');
        $password = $_POST['password']        ?? '';
        $notes    = trim($_POST['notes']      ?? '');
        $companyId = $_POST['company_id']     ?? null;

        if (empty($title)) {
            $this->json(['error' => 'O título é obrigatório.'], 422);
        }

        $ok = $this->model->update((int)$id, [
            'title'      => $title,
            'category'   => $category,
            'url'        => $url,
            'username'   => $username,
            'password'   => $password,
            'notes'      => $notes,
            'company_id' => $companyId,
        ], $user['id']);

        if (!$ok) {
            $this->json(['error' => 'Acesso não encontrado ou sem permissão.'], 404);
        }

        $this->json(['success' => true]);
    }

    // ------------------------------------------------------------------ //
    //  delete — excluir acesso (POST AJAX)                                //
    // ------------------------------------------------------------------ //

    public function delete($id = null)
    {
        $this->requireModule('acessos');
        $this->requireAjax();
        $this->requireCsrf();

        $user = $this->currentUser();
        $ok   = $this->model->delete((int)$id, $user['id']);

        if (!$ok) {
            $this->json(['error' => 'Acesso não encontrado ou sem permissão.'], 404);
        }

        $this->json(['success' => true]);
    }

    // ------------------------------------------------------------------ //
    //  reveal — retorna a senha decriptada por 1 req (GET AJAX seguro)   //
    // ------------------------------------------------------------------ //

    public function reveal($id = null)
    {
        $this->requireModule('acessos');
        $this->requireAjax();

        $user     = $this->currentUser();
        $password = $this->model->getPassword((int)$id, $user['id']);

        if ($password === null) {
            $this->json(['error' => 'Acesso não encontrado ou sem senha cadastrada.'], 404);
        }

        $this->json(['password' => $password]);
    }

    // ------------------------------------------------------------------ //
    //  Helpers privados                                                    //
    // ------------------------------------------------------------------ //

    private function requireAjax(): void
    {
        if (!$this->isAjax()) {
            $this->redirect('acessos');
        }
    }

    private function requireCsrf(): void
    {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!verify_csrf($token)) {
            $this->json(['error' => 'Sessão expirada. Recarregue a página.'], 419);
        }
    }
}
