<?php

/**
 * Apresentação comercial (esteira comercial — etapa anterior à Proposta).
 *
 * Área interna (módulo 'presentations'): listar, criar a partir do modelo,
 * editar seções, publicar/despublicar o link, enviar ao cliente e avançar
 * para a Proposta. Área PÚBLICA (sem login, por token): o cliente vê a
 * apresentação enquanto ela estiver publicada.
 */
class PresentationController extends Controller
{
    private $model;

    public function __construct()
    {
        $this->model = new Presentation();
    }

    // ================= Área interna =================

    public function index()
    {
        $this->requireModule('presentations');
        $filters = [];
        if (!empty($_GET['status']) && in_array($_GET['status'], PresentationRules::STATUSES, true)) {
            $filters['status'] = $_GET['status'];
        }
        $leads = [];
        try { $leads = (new WhatsappContact())->getLeadsForSelect(); } catch (\Throwable $e) { $leads = []; }
        $this->view('commercial/presentations', [
            'user' => $this->currentUser(),
            'presentations' => $this->model->getAll($filters),
            'leads' => $leads,
        ]);
    }

    /** Tela de edição da apresentação (seções + pré-visualização). */
    public function edit($id = null)
    {
        $this->requireModule('presentations');
        $presentation = $id ? $this->model->findById($id) : null;
        if (!$presentation) $this->redirect('presentation');

        $this->view('commercial/presentation_form', [
            'user' => $this->currentUser(),
            'presentation' => $presentation,
            'sections' => $this->model->sections($presentation),
            'sectionTypes' => PresentationRules::SECTION_TYPES,
            'publicLink' => $this->publicLink($presentation),
        ]);
    }

    /** Cria uma apresentação a partir do modelo (POST, JSON). */
    public function store()
    {
        $this->requireModule('presentations');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);
        $user = $this->currentUser();

        $title = trim($_POST['title'] ?? '');
        if ($title === '') $this->json(['error' => 'Informe o título da apresentação.'], 400);

        $id = $this->model->create([
            'title'        => $title,
            'contact_id'   => !empty($_POST['contact_id']) ? (int)$_POST['contact_id'] : null,
            'client_name'  => trim($_POST['client_name'] ?? '') ?: null,
            'client_email' => trim($_POST['client_email'] ?? '') ?: null,
            'client_phone' => trim($_POST['client_phone'] ?? '') ?: null,
            'status'       => PresentationRules::STATUS_DRAFT,
            'created_by'   => $user['id'],
        ]);
        $this->json(['success' => true, 'id' => $id]);
    }

    /** Salva dados do cliente + seções (POST JSON). */
    public function save($id = null)
    {
        $this->requireModule('presentations');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        $presentation = $this->model->findById($id);
        if (!$presentation) $this->json(['error' => 'Apresentação não encontrada'], 404);

        $body = json_decode(file_get_contents('php://input'), true);
        if (!is_array($body)) $this->json(['error' => 'Conteúdo inválido'], 400);

        $title = trim($body['title'] ?? $presentation['title']);
        if ($title === '') $this->json(['error' => 'Informe o título.'], 400);
        $sections = PresentationRules::sanitizeSections($body['sections'] ?? []);
        if (empty($sections)) $this->json(['error' => 'A apresentação precisa de ao menos uma seção.'], 400);

        $this->model->update($id, [
            'title'        => $title,
            'client_name'  => trim($body['client_name'] ?? '') ?: null,
            'client_email' => trim($body['client_email'] ?? '') ?: null,
            'client_phone' => trim($body['client_phone'] ?? '') ?: null,
            'content'      => PresentationRules::encode($sections),
        ]);

        if (!empty($body['save_as_template'])) {
            $this->model->saveTemplate($sections);
        }
        $this->json(['success' => true]);
    }

    /** Ativa o link público e devolve a URL compartilhável. */
    public function publish($id = null)
    {
        $this->requireModule('presentations');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        $presentation = $this->model->findById($id);
        if (!$presentation) $this->json(['error' => 'Apresentação não encontrada'], 404);

        $this->markPublished($presentation);
        $this->json(['success' => true, 'link' => $this->publicLink($presentation)]);
    }

    /** Desativa o link público (o cliente passa a ver "indisponível"). */
    public function unpublish($id = null)
    {
        $this->requireModule('presentations');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        if (!$this->model->findById($id)) $this->json(['error' => 'Apresentação não encontrada'], 404);

        $this->model->update($id, ['status' => PresentationRules::STATUS_DRAFT]);
        $this->json(['success' => true]);
    }

    /** Publica (se preciso) e envia o link ao cliente por WhatsApp + e-mail. */
    public function send($id = null)
    {
        $this->requireModule('presentations');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        $presentation = $this->model->findById($id);
        if (!$presentation) $this->json(['error' => 'Apresentação não encontrada'], 404);

        $this->markPublished($presentation);
        $link = $this->publicLink($presentation);
        $delivery = $this->deliverToClient($presentation, $link);
        if ($delivery['sent_whats'] || $delivery['sent_email']) {
            $this->model->update($id, ['sent_at' => date('Y-m-d H:i:s')]);
        }
        $this->json(array_merge(['success' => true, 'link' => $link], $delivery));
    }

    /** Avança na esteira: cria a Proposta com os dados do cliente desta apresentação. */
    public function toProposal($id = null)
    {
        $this->requireModule('presentations');
        $this->requireModule('proposals');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        $presentation = $this->model->findById($id);
        if (!$presentation) $this->json(['error' => 'Apresentação não encontrada'], 404);

        if (!empty($presentation['proposal_id'])) {
            $this->json(['success' => true, 'id' => (int)$presentation['proposal_id']]);
        }

        $user = $this->currentUser();
        $proposal = new Proposal();
        $proposalId = $proposal->create([
            'public_token' => $proposal->generateToken(),
            'title'        => $presentation['title'],
            'contact_id'   => $presentation['contact_id'] ?: null,
            'client_name'  => $presentation['client_name'],
            'client_email' => $presentation['client_email'],
            'client_phone' => $presentation['client_phone'],
            'status'       => ProposalRules::STATUS_DRAFT,
            'total'        => 0,
            'created_by'   => $user['id'],
        ]);
        $proposal->addEvent($proposalId, $user['id'], 'created', 'Proposta criada a partir da apresentação #' . (int)$id);
        $this->model->update($id, ['proposal_id' => $proposalId]);
        $this->json(['success' => true, 'id' => (int)$proposalId]);
    }

    public function delete($id = null)
    {
        $this->requireModule('presentations');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        $this->model->delete($id);
        $this->json(['success' => true]);
    }

    /** Pré-visualização interna (funciona mesmo com o link desativado). */
    public function preview($id = null)
    {
        $this->requireModule('presentations');
        $presentation = $id ? $this->model->findById($id) : null;
        if (!$presentation) $this->redirect('presentation');
        $this->renderPublic($presentation, true);
    }

    // ================= Área pública (sem login, por token) =================

    /**
     * Página pública (link enviado ao cliente). Nome 'show' (não 'view') para
     * não colidir com Controller::view().
     */
    public function show($token = null)
    {
        $token = $this->tokenFromUrl($token);
        $presentation = $token ? $this->model->findByToken($token) : null;
        if (!PresentationRules::isPublished($presentation)) {
            $this->renderSimple('Apresentação indisponível', 'Este link de apresentação não está disponível no momento.');
            return;
        }
        if (empty($_SESSION['user_id'])) {
            $this->model->registerView($presentation['id']);
        }
        $this->renderPublic($presentation, false);
    }

    // ================= Helpers =================

    /**
     * Na pré-visualização interna, as seções ocultas aparecem esmaecidas (para
     * quem edita entender o que o cliente não verá); no link público, somem.
     */
    private function renderPublic(array $presentation, bool $isPreview): void
    {
        $vars = [
            'cliente' => trim((string)($presentation['client_name'] ?? '')) ?: 'sua empresa',
            'empresa' => trim((string) Config::get('app_name')) ?: 'ON Solutions',
        ];
        $sections = $this->model->sections($presentation);
        if (!$isPreview) {
            $sections = array_filter($sections, fn($s) => !empty($s['visible']));
        }
        $this->view('commercial/presentation_public', [
            'presentation' => $presentation,
            'sections' => $sections,
            'vars' => $vars,
            'isPreview' => $isPreview,
        ]);
    }

    private function markPublished(array $presentation): void
    {
        if (PresentationRules::isPublished($presentation)) return;
        $this->model->update($presentation['id'], [
            'status' => PresentationRules::STATUS_PUBLISHED,
            'published_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** Envia o link ao cliente (WhatsApp + e-mail). Nunca interrompe. */
    private function deliverToClient(array $presentation, string $link): array
    {
        $out = ['sent_whats' => 0, 'sent_email' => 0, 'no_contact' => true];
        $c = ProposalDelivery::clientContact($presentation);
        $company = trim((string) Config::get('app_name')) ?: null;

        if (!empty($c['phone'])) {
            $out['no_contact'] = false;
            $msg = PresentationRules::clientWhatsapp($c['name'], $link, $company);
            try { if (WhatsappNotifier::sendToPhone($c['phone'], $msg, $c['name'])) $out['sent_whats']++; }
            catch (\Throwable $e) { /* não interrompe */ }
        }
        if (!empty($c['email'])) {
            $out['no_contact'] = false;
            $subject = PresentationRules::clientEmailSubject($company);
            $html = Mailer::template($subject, PresentationRules::clientEmailBody($c['name'], $link));
            try { if (Mailer::send($c['email'], $subject, $html)) $out['sent_email']++; }
            catch (\Throwable $e) { /* não interrompe */ }
        }
        return $out;
    }

    private function publicLink(array $presentation): string
    {
        $base = rtrim((string) Config::get('app_public_url'), '/') ?: rtrim(baseUrl(''), '/');
        return $base . '/presentation/show/' . $presentation['public_token'];
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
        echo "<!DOCTYPE html><html lang='pt-BR'><head><meta charset='UTF-8'>"
            . "<meta name='viewport' content='width=device-width, initial-scale=1'>"
            . "<title>{$t}</title></head><body style='font-family:system-ui;background:#0f1020;color:#e8eaf1;'>"
            . "<div style='max-width:440px;margin:14vh auto;background:#1a1c2e;border-radius:16px;padding:32px;text-align:center;'>"
            . "<h3>{$t}</h3><p style='color:#9aa2c0;'>{$m}</p></div></body></html>";
        exit;
    }
}
