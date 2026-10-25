<?php

/**
 * Portal do cliente (change 02, D7): contratos assinados com o PDF, cobranças
 * com situação e link de pagamento, e projetos com o fim da garantia. Tudo
 * filtrado pela empresa ativa do usuário logado.
 */
class PortalController extends Controller
{
    private $portal;

    public function __construct()
    {
        $this->portal = new ClientPortal();
    }

    public function index()
    {
        $this->requireModule('client_portal');
        $user = $this->currentUser();
        $companyId = (int)$this->activeCompanyId();
        $this->view('client/portal', [
            'user' => $user,
            'currentPage' => 'portal',
            'hasCompany' => $companyId > 0,
            'contracts' => $companyId ? $this->portal->contracts($companyId) : [],
            'charges' => $companyId ? $this->portal->charges($companyId) : [],
            'projects' => $companyId ? $this->portal->projects($companyId) : [],
        ]);
    }

    /** PDF do contrato assinado. Contrato de outra empresa responde 404. */
    public function contract($id = null)
    {
        $this->requireModule('client_portal');
        $companyId = (int)$this->activeCompanyId();
        $contract = ($companyId && $id) ? $this->portal->findContract($companyId, (int)$id) : null;
        ContractController::sendSignedPdf($contract);
    }
}
