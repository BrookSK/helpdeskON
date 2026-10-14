<?php

/**
 * Projetos & Garantia (Fase 9) + ponte lead->cliente. Módulo 'projects'
 * (comercial + full-access).
 *
 * Projetos: entrega, garantia de 90 dias (tipo 'zero'), aviso de 15 dias antes
 * do fim e bloqueio de chamados pós-garantia sem contrato de suporte.
 * Conversão: registra a ponte lead->empresa/usuário (LeadConversion), fechando a
 * rastreabilidade da esteira.
 */
class ProjectController extends Controller
{
    private $projects;

    public function __construct()
    {
        $this->projects = new Project();
    }

    public function index()
    {
        $this->requireModule('projects');
        $user = $this->currentUser();
        $items = $this->projects->getAll();
        // Anota aviso de garantia próxima do fim para a listagem.
        foreach ($items as &$p) {
            $p['warn_warranty'] = ProjectRules::shouldWarnWarrantyEnding($p['warranty_ends_at'] ?? null);
            $p['warranty_days_left'] = ProjectRules::warrantyDaysLeft($p['warranty_ends_at'] ?? null);
        }
        unset($p);
        $this->view('commercial/projects', ['user' => $user, 'items' => $items]);
    }

    public function edit($id = null)
    {
        $this->requireModule('projects');
        if (!$id) $this->redirect('project');
        $p = $this->projects->findById($id);
        if (!$p) $this->redirect('project');
        $user = $this->currentUser();
        $this->view('commercial/project_detail', [
            'user' => $user,
            'project' => $p,
            'events' => $this->projects->getEvents($id),
            'warrantyDaysLeft' => ProjectRules::warrantyDaysLeft($p['warranty_ends_at'] ?? null),
            'warnWarranty' => ProjectRules::shouldWarnWarrantyEnding($p['warranty_ends_at'] ?? null),
            'canOpenTicket' => ProjectRules::canOpenTicket($p),
            'blockReason' => ProjectRules::blockReason($p),
        ]);
    }

    public function store()
    {
        $this->requireModule('projects');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);
        $user = $this->currentUser();
        $name = trim($_POST['name'] ?? '');
        if ($name === '') $this->json(['error' => 'Informe o nome do projeto.'], 400);
        $id = $this->projects->create([
            'company_id' => !empty($_POST['company_id']) ? (int)$_POST['company_id'] : null,
            'onboarding_id' => !empty($_POST['onboarding_id']) ? (int)$_POST['onboarding_id'] : null,
            'name' => $name,
            'contract_type' => $_POST['contract_type'] ?? 'outro',
            'warranty_days' => isset($_POST['warranty_days']) && $_POST['warranty_days'] !== '' ? (int)$_POST['warranty_days'] : ProjectRules::DEFAULT_WARRANTY_DAYS,
            'created_by' => $user['id'],
        ]);
        $this->projects->addEvent($id, $user['id'], 'created', 'Projeto criado');
        $this->json(['success' => true, 'id' => $id]);
    }

    /** Marca o projeto como entregue (calcula a garantia). */
    public function deliver($id = null)
    {
        $this->requireModule('projects');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        $user = $this->currentUser();
        if (!$this->projects->markDelivered((int)$id, $user['id'])) {
            $this->json(['error' => 'Projeto não encontrado.'], 404);
        }
        $this->json(['success' => true]);
    }

    /** Ativa/desativa o contrato de suporte (libera chamados pós-garantia). */
    public function toggleSupport($id = null)
    {
        $this->requireModule('projects');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        $user = $this->currentUser();
        $active = !empty($_POST['active']);
        if (!$this->projects->setSupportContract((int)$id, $active, $user['id'])) {
            $this->json(['error' => 'Projeto não encontrado.'], 404);
        }
        $this->json(['success' => true]);
    }

    // ================= Fluxo de Entrega =================

    /** Contato principal (dono) do cliente de um projeto, p/ notificação. */
    private function clientContact(array $project): array
    {
        $out = ['name' => null, 'phone' => null, 'email' => null];
        if (empty($project['company_id'])) return $out;
        $users = (new Company())->getUsers((int)$project['company_id']);
        $owner = $users[0] ?? null; // getUsers ordena is_company_owner DESC
        if ($owner) {
            $out['name']  = $owner['name']  ?? null;
            $out['phone'] = $owner['phone'] ?? null;
            $out['email'] = $owner['email'] ?? null;
        }
        return $out;
    }

    /**
     * Registra a publicação em produção e notifica o cliente (WhatsApp + e-mail).
     * POST /project/publish/{id}
     */
    public function publish($id = null)
    {
        $this->requireModule('projects');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        $user = $this->currentUser();
        $p = $this->projects->findById($id);
        if (!$p) $this->json(['error' => 'Projeto não encontrado.'], 404);
        if (!ProjectRules::canPublish($p)) {
            $this->json(['error' => 'Projeto não está em um estado que permita publicação.'], 409);
        }

        $this->projects->markPublished((int)$id, $user['id']);

        // Notifica o cliente (best-effort; não interrompe em falha).
        $c = $this->clientContact($p);
        $msg = "Olá" . (!empty($c['name']) ? ", {$c['name']}" : '') . "! O projeto \"{$p['name']}\" "
             . "foi publicado em produção. A partir de agora, a garantia está vigente. "
             . "Qualquer dúvida, estamos à disposição.";
        if (!empty($c['phone'])) {
            try { WhatsappNotifier::sendToPhone($c['phone'], $msg, $c['name']); } catch (\Throwable $e) {}
        }
        if (!empty($c['email'])) {
            try { Mailer::send($c['email'], 'Seu projeto foi publicado em produção', Mailer::template('Projeto publicado', '<p>' . htmlspecialchars($msg) . '</p>')); } catch (\Throwable $e) {}
        }
        $this->json(['success' => true]);
    }

    /**
     * Registra a entrega da documentação/manual ao cliente.
     * POST /project/markDocumentation/{id}  (campo opcional: manual_url)
     */
    public function markDocumentation($id = null)
    {
        $this->requireModule('projects');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        $user = $this->currentUser();
        if (!$this->projects->markDocumentation((int)$id, $_POST['manual_url'] ?? null, $user['id'])) {
            $this->json(['error' => 'Projeto não encontrado.'], 404);
        }
        $this->json(['success' => true]);
    }

    /**
     * Vincula uma reunião de entrega (agenda_meetings) ao projeto.
     * POST /project/linkMeeting/{id}  (campos: meeting_id OU meeting_at)
     */
    public function linkMeeting($id = null)
    {
        $this->requireModule('projects');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        $user = $this->currentUser();
        if (!$this->projects->findById($id)) $this->json(['error' => 'Projeto não encontrado.'], 404);

        $meetingId = isset($_POST['meeting_id']) && $_POST['meeting_id'] !== '' ? (int)$_POST['meeting_id'] : 0;
        $meetingAt = null;

        if ($meetingId > 0) {
            // Confirma que a reunião existe e pega a data dela.
            $m = Database::getInstance()->fetch("SELECT id, meeting_at FROM agenda_meetings WHERE id = ?", [$meetingId]);
            if (!$m) $this->json(['error' => 'Reunião não encontrada.'], 404);
            $meetingAt = $m['meeting_at'] ?? null;
        } elseif (!empty($_POST['meeting_at']) && preg_match('/^\d{4}-\d{2}-\d{2}([ T]\d{2}:\d{2}(:\d{2})?)?$/', $_POST['meeting_at'])) {
            // Permite registrar manualmente a data da reunião, sem FK.
            $meetingAt = str_replace('T', ' ', $_POST['meeting_at']);
        } else {
            $this->json(['error' => 'Informe a reunião (meeting_id) ou a data (meeting_at).'], 400);
        }

        $this->projects->linkDeliveryMeeting((int)$id, $meetingId, $meetingAt, $user['id']);
        $this->json(['success' => true]);
    }

    /**
     * Gera o link público de aceite e envia ao cliente (WhatsApp + e-mail).
     * POST /project/generateAcceptanceLink/{id}
     */
    public function generateAcceptanceLink($id = null)
    {
        $this->requireModule('projects');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        $user = $this->currentUser();
        $p = $this->projects->findById($id);
        if (!$p) $this->json(['error' => 'Projeto não encontrado.'], 404);

        $token = $this->projects->setAcceptanceToken((int)$id, $user['id']);
        if ($token === null) $this->json(['error' => 'Não foi possível gerar o link.'], 500);

        $base = rtrim((string) Config::get('app_public_url'), '/') ?: rtrim(baseUrl(''), '/');
        $link = $base . '/project/accept/' . $token;

        $c = $this->clientContact($p);
        $msg = "Olá" . (!empty($c['name']) ? ", {$c['name']}" : '') . "! A entrega do projeto \"{$p['name']}\" "
             . "está pronta para o seu aceite formal. Acesse o link e confirme com o seu PIN: {$link}";
        if (!empty($c['phone'])) {
            try { WhatsappNotifier::sendToPhone($c['phone'], $msg, $c['name']); } catch (\Throwable $e) {}
        }
        if (!empty($c['email'])) {
            try { Mailer::send($c['email'], 'Aceite da entrega do seu projeto', Mailer::template('Aceite da entrega', '<p>' . htmlspecialchars($msg) . "</p><p><a href='{$link}'>Confirmar aceite</a></p>")); } catch (\Throwable $e) {}
        }
        $this->json(['success' => true, 'link' => $link]);
    }

    // ================= Área pública: aceite do cliente =================

    /** Extrai o token do último segmento da URL (ou do parâmetro recebido). */
    private function tokenFromUrl($token)
    {
        if ($token) return trim((string)$token);
        $url = $_GET['url'] ?? '';
        $parts = array_values(array_filter(explode('/', $url)));
        return $parts ? trim(end($parts)) : '';
    }

    /** Página simples de mensagem (link inválido). */
    private function renderSimpleMessage($title, $message)
    {
        $t = htmlspecialchars($title, ENT_QUOTES);
        $m = htmlspecialchars($message, ENT_QUOTES);
        http_response_code(200);
        echo "<!DOCTYPE html><html lang='pt-BR'><head><meta charset='UTF-8'>"
            . "<meta name='viewport' content='width=device-width, initial-scale=1'>"
            . "<title>{$t}</title></head><body style='font-family:system-ui;background:#0f1020;color:#e8eaf1;'>"
            . "<div style='max-width:440px;margin:14vh auto;background:#1a1c2e;border-radius:16px;padding:32px;text-align:center;'>"
            . "<h3>{$t}</h3><p style='color:#9aa2c0;'>{$m}</p></div></body></html>";
        exit;
    }

    /**
     * Página pública de aceite do projeto (sem login, por token).
     * GET /project/accept/{token}
     */
    public function accept($token = null)
    {
        $token = $this->tokenFromUrl($token);
        $project = $token ? $this->projects->findByAcceptanceToken($token) : null;
        if (!$project) { $this->renderSimpleMessage('Link indisponível', 'Este link de aceite não é válido.'); return; }

        $this->view('external/project_accept', [
            'project'    => $project,
            'token'      => $token,
            'alreadyAccepted' => ProjectRules::clientAccepted($project),
        ]);
    }

    /**
     * Confirma o aceite do cliente (POST público). Autentica pelo client_pin.
     * POST /project/confirmAccept/{token}  (campo: pin)
     */
    public function confirmAccept($token = null)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);
        $token = $this->tokenFromUrl($token);
        $project = $token ? $this->projects->findByAcceptanceToken($token) : null;
        if (!$project) $this->json(['error' => 'Projeto não encontrado.'], 404);
        if (ProjectRules::clientAccepted($project)) {
            $this->json(['error' => 'Esta entrega já foi aceita.'], 409);
        }

        $pin = $_POST['pin'] ?? '';
        if (!ClientPinRules::isValidFormat($pin)) {
            $this->json(['error' => 'PIN inválido. Informe os 4 dígitos.'], 400);
        }
        $client = (new User())->findByClientPin($pin);
        if (!$client) $this->json(['error' => 'PIN não reconhecido.'], 403);

        // O PIN precisa pertencer a um usuário da MESMA empresa do projeto.
        if (!empty($project['company_id']) && (int)($client['company_id'] ?? 0) !== (int)$project['company_id']) {
            $this->json(['error' => 'Este PIN não corresponde ao cliente deste projeto.'], 403);
        }

        $this->projects->registerClientAcceptance((int)$project['id'], (int)$client['id']);
        $this->json(['success' => true]);
    }

    // ================= Ponte lead -> cliente =================

    /**
     * Converte um lead (whatsapp_contacts) em cliente: cria/vincula empresa e
     * usuário, registra a ponte e envia o convite de primeiro acesso.
     */
    public function convertLead()
    {
        $this->requireModule('projects');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);
        $user = $this->currentUser();

        $contactId = (int)($_POST['contact_id'] ?? 0);
        $contact = $contactId ? Database::getInstance()->fetch("SELECT * FROM whatsapp_contacts WHERE id = ?", [$contactId]) : null;
        if (!$contact) $this->json(['error' => 'Lead não encontrado.'], 404);

        $lead = [
            'contact_id' => $contactId,
            'name' => $contact['name'] ?? null,
            'company_name' => trim($_POST['company_name'] ?? '') ?: ($contact['name'] ?? null),
            'email' => trim($_POST['email'] ?? '') ?: ($contact['email'] ?? ''),
            'phone' => $contact['phone'] ?? null,
            'proposal_id' => !empty($_POST['proposal_id']) ? (int)$_POST['proposal_id'] : null,
            'contract_id' => !empty($_POST['contract_id']) ? (int)$_POST['contract_id'] : null,
            'onboarding_id' => !empty($_POST['onboarding_id']) ? (int)$_POST['onboarding_id'] : null,
        ];
        $result = (new LeadConversion())->convert($lead, $user['id']);

        // Convite de primeiro acesso (fora do model para manter o model sem rede).
        if (!empty($result['created_user']) && !empty($result['user_id'])) {
            try { (new User())->sendFirstAccessInvite($result['user_id']); } catch (\Throwable $e) {}
        }
        $this->json(['success' => true] + $result);
    }

    // ================= PIN do cliente (gestão interna) =================

    /** Gera/renova o PIN de login simplificado de um usuário CLIENTE. */
    public function generateClientPin($userId = null)
    {
        $this->requireModule('projects');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$userId) $this->json(['error' => 'Requisição inválida'], 400);
        $u = (new User())->findById($userId);
        if (!$u) $this->json(['error' => 'Usuário não encontrado.'], 404);
        if (!ClientPinRules::roleCanUseClientPin($u['role'] ?? null)) {
            $this->json(['error' => 'PIN de cliente só para usuários do tipo cliente.'], 409);
        }
        $pin = (new User())->setClientPin((int)$userId, null);
        if ($pin === null) $this->json(['error' => 'Não foi possível gerar o PIN.'], 500);
        $this->json(['success' => true, 'pin' => $pin]);
    }
}
