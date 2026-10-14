<?php

/**
 * Provisionamento de infra no LRV Cloud (Fase 7 — fora da esteira). Módulo
 * 'provisioning' (comercial + full-access).
 *
 * Para cada etapa, tenta a API quando ela é 'auto'; se a API não tem o endpoint
 * (gaps conhecidos) a etapa fica como pendência MANUAL rastreável — nunca marca
 * como feita sem ter acontecido. Eventos do LRV Cloud chegam por webhook.
 */
class ProvisioningController extends Controller
{
    private $prov;
    private $onboardings;

    public function __construct()
    {
        $this->prov = new Provisioning();
        $this->onboardings = new Onboarding();
    }

    public function index()
    {
        $this->requireModule('provisioning');
        $user = $this->currentUser();
        $this->view('commercial/provisioning', [
            'user' => $user,
            'items' => $this->prov->getAll(),
        ]);
    }

    public function edit($id = null)
    {
        $this->requireModule('provisioning');
        if (!$id) $this->redirect('provisioning');
        $p = $this->prov->findById($id);
        if (!$p) $this->redirect('provisioning');
        $user = $this->currentUser();
        $steps = $this->prov->getSteps($id);
        $this->view('commercial/provisioning_detail', [
            'user' => $user,
            'provisioning' => $p,
            'steps' => $steps,
            'events' => $this->prov->getEvents($id),
            'pendingRequired' => ProvisioningRules::pendingRequired($steps),
            'manualSteps' => ProvisioningRules::manualSteps($steps),
            'lrvConfigured' => LrvCloudApi::fromConfig()->isConfigured(),
        ]);
    }

    /** Cria o provisionamento a partir de um onboarding (preferencialmente concluído). */
    public function fromOnboarding()
    {
        $this->requireModule('provisioning');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);
        $user = $this->currentUser();

        $onboardingId = (int)($_POST['onboarding_id'] ?? 0);
        $onb = $onboardingId ? $this->onboardings->findById($onboardingId) : null;
        if (!$onb) $this->json(['error' => 'Onboarding não encontrado.'], 404);

        // Pipeline: 'esteira_cx' (projeto entra na esteira do CX) ou 'fora_esteira'
        // (projeto do zero, infra automática pela API LRV). Default fora_esteira.
        $pipeline = ProvisioningRules::normalizePipeline($_POST['pipeline'] ?? null) ?? ProvisioningRules::PIPELINE_OUT;

        $id = $this->prov->createFromOnboarding($onb, $user['id'], $pipeline);
        $this->json(['success' => true, 'id' => $id, 'pipeline' => $pipeline]);
    }

    public function start($id = null)
    {
        $this->requireModule('provisioning');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        $user = $this->currentUser();
        if (!$this->prov->start($id, $user['id'])) $this->json(['error' => 'Provisionamento não encontrado.'], 404);
        $this->json(['success' => true]);
    }

    /**
     * Executa uma etapa. Se 'auto' e a API tem o endpoint, chama o LRV Cloud e
     * conclui com o retorno; se a API não tem o endpoint (available=false),
     * registra pendência manual (bloqueia a etapa com o motivo). Se 'manual',
     * conclui pela ação do responsável.
     */
    public function runStep($stepId = null)
    {
        $this->requireModule('provisioning');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$stepId) $this->json(['error' => 'Requisição inválida'], 400);
        $user = $this->currentUser();
        $step = $this->prov->findStep($stepId);
        if (!$step) $this->json(['error' => 'Etapa não encontrada.'], 404);

        $mode = ProvisioningRules::normalizeMode($step['mode'] ?? 'manual');

        if ($mode === 'manual') {
            // Conclusão manual pelo responsável (sem API).
            $this->prov->completeStep((int)$stepId, false, $user['id']);
            // Ao concluir a ENTREGA, avisa o cliente (link + como usar o sistema).
            $delivery = null;
            if (($step['step_key'] ?? '') === 'deliver') {
                $prov = $this->prov->findById((int)$step['provisioning_id']);
                if ($prov) $delivery = $this->notifyClientDelivery($prov);
            }
            $this->json(['success' => true, 'mode' => 'manual'] + ($delivery ? ['delivery' => $delivery] : []));
        }

        // Etapa 'auto': tenta a API conforme a etapa.
        $api = LrvCloudApi::fromConfig();
        if (!$api->isConfigured()) {
            $this->json(['error' => 'LRV Cloud sem API key configurada (Settings lrv_cloud_api_key).'], 409);
        }
        $prov = $this->prov->findById((int)$step['provisioning_id']);
        $result = $this->callApiForStep($api, $step['step_key'], $prov);

        if (($result['available'] ?? true) === false) {
            // Gap de API: vira pendência manual rastreável, não finge sucesso.
            $this->prov->blockStep((int)$stepId, $result['error'] ?? 'Endpoint indisponível na API.', $user['id']);
            $this->json(['success' => false, 'manual_pending' => true, 'reason' => $result['error'] ?? 'Endpoint indisponível na API.'], 409);
        }
        if (!($result['success'] ?? false)) {
            $this->json(['error' => $result['error'] ?? 'Falha na chamada à API.'], 502);
        }
        // Sucesso real da API: extrai o id retornado e encadeia as referências
        // externas no provisionamento (client -> vps -> app -> staging).
        $data = $result['data']['data'] ?? [];
        $ref = isset($data['id']) ? (string)$data['id'] : null;
        $this->persistExternalRefs((int)$step['provisioning_id'], $step['step_key'], $data);
        $this->prov->completeStep((int)$stepId, true, $user['id'], $ref);
        $this->json(['success' => true, 'mode' => 'auto', 'external_ref' => $ref]);
    }

    /**
     * Monta o payload de cada etapa com os dados do provisionamento/empresa e os
     * parâmetros opcionais vindos do POST (plano, repositório, etc.), e chama o
     * endpoint correspondente da API LRV Cloud (v1.1).
     */
    private function callApiForStep(LrvCloudApi $api, string $stepKey, ?array $prov): array
    {
        $prov = $prov ?: [];
        $company = !empty($prov['company_id'])
            ? Database::getInstance()->fetch("SELECT * FROM companies WHERE id = ?", [(int)$prov['company_id']])
            : null;
        $p = fn($k, $d = null) => (isset($_POST[$k]) && $_POST[$k] !== '') ? $_POST[$k] : $d;

        switch ($stepKey) {
            case 'create_client':
                return $api->createClient([
                    'name' => $company['name'] ?? ('Projeto #' . (int)($prov['id'] ?? 0)),
                    'email' => $company['email'] ?? $p('email'),
                    'document' => $company['document'] ?? $p('document'),
                    'phone' => $company['phone'] ?? $p('phone'),
                    'external_ref' => 'prov-' . (int)($prov['id'] ?? 0),
                ]);
            case 'create_repo':
                return $api->createRepository(array_filter([
                    'name' => $p('repo_name', $this->defaultRepoName($company, $prov)),
                    'private' => true,
                    'org' => $p('repo_org'),
                    'description' => $p('repo_description', ($company['name'] ?? 'Projeto') . ' — helpdeskON'),
                    'external_ref' => 'prov-' . (int)($prov['id'] ?? 0),
                    'client_id' => !empty($prov['lrv_client_id']) ? (int)$prov['lrv_client_id'] : null,
                ], fn($v) => $v !== null && $v !== ''));
            case 'grant_dev_access':
                if (empty($prov['lrv_repo_id'])) {
                    return ['success' => false, 'available' => true, 'error' => 'Crie o repositório antes de conceder acesso aos devs.'];
                }
                $usernames = $p('dev_usernames');
                if (is_string($usernames)) {
                    $usernames = array_values(array_filter(array_map('trim', preg_split('/[\s,;]+/', $usernames))));
                }
                if (empty($usernames) || !is_array($usernames)) {
                    return ['success' => false, 'available' => true, 'error' => 'Informe os usernames do GitHub dos desenvolvedores (campo "dev_usernames").'];
                }
                $permission = $p('dev_permission', 'push');
                return $api->addRepositoryCollaborators((int)$prov['lrv_repo_id'], $usernames, (string)$permission);
            case 'create_vps':
                $plan = $p('plan');
                if ($plan === null) {
                    return ['success' => false, 'available' => true, 'error' => 'Informe o plano da VPS (campo "plan").'];
                }
                return $api->createVps(array_filter([
                    'plan' => is_numeric($plan) ? (int)$plan : $plan,
                    'client_id' => !empty($prov['lrv_client_id']) ? (int)$prov['lrv_client_id'] : null,
                    'hostname' => $p('hostname'),
                    'region' => $p('region'),
                    'external_ref' => 'prov-' . (int)($prov['id'] ?? 0),
                ], fn($v) => $v !== null));
            case 'create_database':
                if (empty($prov['lrv_vps_id'])) {
                    return ['success' => false, 'available' => true, 'error' => 'Provisione a VPS antes de criar o banco.'];
                }
                return $api->createDatabase([
                    'vps_id' => (int)$prov['lrv_vps_id'],
                    'db_name' => $p('db_name', 'db_prov' . (int)($prov['id'] ?? 0)),
                    'db_type' => $p('db_type', 'mysql'),
                ]);
            case 'create_app':
                if (empty($prov['lrv_vps_id'])) {
                    return ['success' => false, 'available' => true, 'error' => 'Provisione a VPS antes de criar a aplicação.'];
                }
                // Prioriza o repositório criado automaticamente (clone_url salvo
                // em git_repo pelo passo create_repo); permite override no POST.
                $repo = $p('git_repo', $prov['git_repo'] ?? null);
                if (!$repo) {
                    return ['success' => false, 'available' => true, 'error' => 'Informe o repositório Git (campo "git_repo") ou crie o repositório antes.'];
                }
                return $api->createApplication(array_filter([
                    'vps_id' => (int)$prov['lrv_vps_id'],
                    'name' => $p('name', ($company['name'] ?? 'app') . '-' . (int)($prov['id'] ?? 0)),
                    'git_repo' => $repo,
                    'git_branch' => $p('git_branch', $prov['git_branch'] ?? 'main'),
                    'runtime' => $p('runtime', 'php'),
                    'domain' => $p('domain'),
                    'staging_subdomain' => true, // homologação com SSL automático
                ], fn($v) => $v !== null));
            case 'deploy':
                if (empty($prov['lrv_app_id'])) {
                    return ['success' => false, 'available' => true, 'error' => 'Crie a aplicação antes de disparar o deploy.'];
                }
                return $api->deployApplication((string)$prov['lrv_app_id']);
            case 'staging':
                return $api->createStaging((string)($prov['lrv_app_id'] ?? ''));
            default:
                return ['success' => false, 'available' => true, 'error' => 'Etapa sem ação automática.'];
        }
    }

    /**
     * Salva no provisionamento as referências externas retornadas pela API,
     * encadeando os passos (client_id -> vps_id -> app_id -> staging_url).
     */
    private function persistExternalRefs(int $provId, string $stepKey, array $data): void
    {
        $upd = [];
        if ($stepKey === 'create_client' && isset($data['id'])) $upd['lrv_client_id'] = (string)$data['id'];
        if ($stepKey === 'create_repo') {
            if (isset($data['id'])) $upd['lrv_repo_id'] = (string)$data['id'];
            // clone_url alimenta o git_repo usado em create_app; guarda a URL web também.
            if (!empty($data['clone_url'])) $upd['git_repo'] = (string)$data['clone_url'];
            if (!empty($data['html_url'])) $upd['repo_url'] = (string)$data['html_url'];
        }
        if ($stepKey === 'create_vps' && isset($data['id'])) $upd['lrv_vps_id'] = (string)$data['id'];
        if ($stepKey === 'create_app') {
            if (isset($data['id'])) $upd['lrv_app_id'] = (string)$data['id'];
            if (!empty($data['staging_url'])) $upd['staging_url'] = (string)$data['staging_url'];
        }
        if (($stepKey === 'deploy' || $stepKey === 'staging') && !empty($data['staging_url'])) {
            $upd['staging_url'] = (string)$data['staging_url'];
        }
        if (!empty($upd)) $this->prov->update($provId, $upd);
    }

    /**
     * Nome padrão do repositório a partir do nome da empresa (slug) + id do
     * provisionamento, respeitando o formato aceito pela API (letras, números,
     * ponto, hífen, underline; começa com letra).
     */
    private function defaultRepoName(?array $company, ?array $prov): string
    {
        $base = strtolower((string)($company['name'] ?? 'projeto'));
        $base = preg_replace('/[^a-z0-9]+/', '-', $base);     // separadores -> hífen
        $base = trim((string)$base, '-');
        if ($base === '' || !preg_match('/^[a-z]/', $base)) $base = 'proj-' . $base;
        $base = substr($base, 0, 80);
        return $base . '-' . (int)($prov['id'] ?? 0);
    }

    /**
     * Avisa o cliente (dono da empresa) que o ambiente está configurado e ensina
     * a usar o sistema (link de acesso + homologação + nova demanda). WhatsApp +
     * e-mail. Nunca interrompe. Retorna um resumo do envio.
     */
    private function notifyClientDelivery(array $prov): array
    {
        $out = ['sent_whats' => 0, 'sent_email' => 0, 'no_contact' => true];
        if (empty($prov['company_id'])) return $out;

        $users = (new Company())->getUsers((int)$prov['company_id']);
        $rcpt = DeliveryNotice::pickRecipient($users);
        if (!$rcpt['found']) return $out;

        $base = rtrim((string) Config::get('app_public_url'), '/') ?: rtrim(baseUrl(''), '/');
        $loginUrl = $base;
        $stagingUrl = trim((string)($prov['staging_url'] ?? '')) ?: null;
        $newTicketUrl = DeliveryNotice::newTicketUrl($base);
        $company = trim((string) Config::get('app_name')) ?: null;

        if (!empty($rcpt['phone'])) {
            $out['no_contact'] = false;
            $wa = DeliveryNotice::clientWhatsapp($rcpt['name'], $loginUrl, $stagingUrl, $company);
            try { if (WhatsappNotifier::sendToPhone($rcpt['phone'], $wa, $rcpt['name'])) $out['sent_whats']++; }
            catch (\Throwable $e) { /* não interrompe */ }
        }
        if (!empty($rcpt['email'])) {
            $out['no_contact'] = false;
            $subject = DeliveryNotice::emailSubject($company);
            $html = Mailer::template($subject, DeliveryNotice::emailBody($rcpt['name'], $loginUrl, $stagingUrl, $newTicketUrl));
            try { if (Mailer::send($rcpt['email'], $subject, $html)) $out['sent_email']++; }
            catch (\Throwable $e) { /* não interrompe */ }
        }
        $this->prov->addEvent((int)$prov['id'], null, 'client_notified', 'Cliente avisado da entrega (acesso + como usar).');
        return $out;
    }

    public function finish($id = null)
    {
        $this->requireModule('provisioning');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        $user = $this->currentUser();
        if (!$this->prov->finish($id, $user['id'])) {
            $this->json(['error' => 'Etapas obrigatórias pendentes.', 'pending' => $this->prov->pendingRequired($id)], 409);
        }
        $this->json(['success' => true]);
    }

    // ================= Webhook LRV Cloud (sem login) =================

    /**
     * Recebe eventos do LRV Cloud (hosting.created, application.installed,
     * domain.added). Valida HMAC se o secret estiver configurado e conclui a
     * etapa correspondente do provisionamento referenciado por ?id=.
     */
    public function lrvWebhook()
    {
        $raw = file_get_contents('php://input');
        $secret = (string) Config::get('lrv_cloud_webhook_secret');
        $sig = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '';
        if ($secret !== '' && !ProvisioningRules::verifyWebhookSignature($raw, $sig, $secret)) {
            http_response_code(401);
            echo json_encode(['error' => 'assinatura inválida']);
            return;
        }

        $payload = json_decode($raw, true);
        $event = $payload['event'] ?? ($_SERVER['HTTP_X_WEBHOOK_EVENT'] ?? null);
        $provisioningId = (int)($_GET['id'] ?? ($payload['provisioning_id'] ?? 0));
        $map = ProvisioningRules::interpretEvent(is_string($event) ? $event : null);

        if ($provisioningId && $map['step_key'] && $map['done']) {
            $step = $this->prov->findStepByKey($provisioningId, $map['step_key']);
            if ($step && $step['status'] !== 'done') {
                $ref = isset($payload['data']['id']) ? (string)$payload['data']['id'] : null;
                // Webhook é confirmação real da API -> apiOk=true.
                $this->prov->completeStep((int)$step['id'], true, null, $ref);
                $this->prov->addEvent($provisioningId, null, 'webhook', 'Evento LRV Cloud: ' . $event);
            }
        }
        http_response_code(200);
        echo json_encode(['ok' => true]);
    }
}
