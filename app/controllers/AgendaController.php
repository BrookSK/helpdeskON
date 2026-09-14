<?php

class AgendaController extends Controller
{
    private $accessRoles = ['super_admin', 'comercial'];
    private $model;
    private $contactModel;

    public function __construct()
    {
        $this->model = new AgendaMeeting();
        $this->contactModel = new WhatsappContact();
    }

    // Página principal — Kanban + Calendário
    public function index()
    {
        $this->requireRole($this->accessRoles);
        $user = $this->currentUser();

        $filters = [];
        // Comercial vê só suas reuniões; super_admin e marketing veem tudo
        if ($user['role'] === 'comercial' && empty($_GET['show_all'])) {
            $filters['assigned_to'] = $user['id'];
        }

        $grouped = $this->model->getGroupedByStatus($filters);

        $userModel = new User();
        $team = $userModel->getByRoles(['super_admin', 'comercial', 'marketing']);
        $leads = $this->contactModel->getLeadsForSelect();

        // Empresas já cadastradas (para seleção Empresa → Contato na reunião).
        // Reutiliza o relacionamento existente companies → users (users.company_id).
        $companies = (new Company())->getAll();

        // Todos os usuários internos (exceto clientes) para o multi-select de participantes
        $allInternalRoles = ['super_admin', 'attendant', 'developer', 'analyst', 'comercial', 'marketing', 'whatsapp_agent'];
        $participants = $userModel->getGroupedByRole($allInternalRoles);

        $this->view('agenda/index', [
            'user' => $user,
            'grouped' => $grouped,
            'team' => $team,
            'leads' => $leads,
            'companies' => $companies,
            'participants' => $participants,
            'isAdmin' => $user['role'] === 'super_admin',
        ]);
    }

    // API: reuniões para o calendário (JSON)
    public function calendar()
    {
        $this->requireRole($this->accessRoles);
        $user = $this->currentUser();

        $start = ($_GET['start'] ?? date('Y-m-01')) . ' 00:00:00';
        $end = ($_GET['end'] ?? date('Y-m-t')) . ' 23:59:59';

        $filters = [];
        if ($user['role'] === 'comercial' && empty($_GET['show_all'])) {
            $filters['assigned_to'] = $user['id'];
        }

        $meetings = $this->model->getForCalendar($start, $end, $filters);
        $events = array_map(function ($m) {
            return [
                'id' => $m['id'],
                'title' => $m['title'],
                'meeting_at' => $m['meeting_at'],
                'status' => $m['status'],
                'urgency' => $m['urgency'],
                'temperature' => $m['temperature'],
                'assigned_name' => $m['assigned_name'] ?? 'Sem responsável',
                'client' => $m['crm_contact_name'] ?? $m['client_name'],
            ];
        }, $meetings);

        $this->json(['events' => $events]);
    }

    // API: obter uma reunião + briefing do cliente
    public function get($id = null)
    {
        $this->requireRole($this->accessRoles);
        if (!$id) $this->json(['error' => 'ID não informado'], 400);

        $meeting = $this->model->findById($id);
        if (!$meeting) $this->json(['error' => 'Reunião não encontrada'], 404);

        $briefing = null;
        if (!empty($meeting['contact_id'])) {
            $briefing = $this->contactModel->getBriefing($meeting['contact_id']);
        }
        $meeting['briefing'] = $briefing;
        $meeting['participants'] = $this->model->getParticipants($id);
        $this->json(['meeting' => $meeting]);
    }

    // API: contatos vinculados a uma empresa (para o fluxo Empresa → Contato).
    // Reutiliza o relacionamento existente companies → users (users.company_id),
    // via Company::getUsers(). Não cria/duplica contatos nem novos vínculos.
    public function companyContacts($companyId = null)
    {
        $this->requireRole($this->accessRoles);
        if (!$companyId) $this->json(['error' => 'Empresa não informada'], 400);

        $users = (new Company())->getUsers(intval($companyId));
        $contacts = array_map(function ($u) {
            return [
                'id'    => (int)$u['id'],
                'name'  => $u['name'],
                'email' => $u['email'] ?? null,
                'phone' => $u['phone'] ?? null,
            ];
        }, $users);

        $this->json(['contacts' => $contacts]);
    }

    // API: briefing de um lead (ao selecionar o cliente no formulário)
    public function briefing($contactId = null)
    {
        $this->requireRole($this->accessRoles);
        if (!$contactId) $this->json(['error' => 'ID não informado'], 400);
        $contact = $this->contactModel->findById($contactId);
        $briefing = $this->contactModel->getBriefing($contactId);
        $this->json(['contact' => $contact, 'briefing' => $briefing]);
    }

    // API: criar reunião
    public function create()
    {
        $this->requireRole($this->accessRoles);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);

        $user = $this->currentUser();
        $title = trim($_POST['title'] ?? '');
        if ($title === '') $this->json(['error' => 'Título obrigatório'], 400);

        // Tipo da reunião: comercial (fluxo atual), operacional (interna, só participantes)
        // ou externo (convidados que NÃO fazem parte do sistema — demanda #210).
        $meetingType = in_array($_POST['meeting_type'] ?? '', ['comercial', 'operacional', 'externo']) ? $_POST['meeting_type'] : 'comercial';
        $isOperational = $meetingType === 'operacional';
        $isExternal = $meetingType === 'externo';

        $contactId = !empty($_POST['contact_id']) ? intval($_POST['contact_id']) : null;

        // Cliente novo (manual): cria o lead no CRM para ficar disponível depois (só reunião comercial)
        if (!$isOperational && !$isExternal && !$contactId && !empty($_POST['new_client_name'])) {
            $contactId = $this->contactModel->createManualLead(
                trim($_POST['new_client_name']),
                trim($_POST['new_client_phone'] ?? ''),
                $user['id']
            );
        }

        // Em reunião comercial o cliente pode vir do CRM (contact_id) ou de uma empresa
        // (Empresa → Contato), que preenche client_name/phone/email como snapshot.
        $hasClientSnapshot = trim($_POST['client_name'] ?? '') !== '';
        if (!$isOperational && !$isExternal && !$contactId && !$hasClientSnapshot) {
            $this->json(['error' => 'Selecione um cliente ou cadastre um novo.'], 400);
        }

        // Convite externo: valida os dados dos convidados externos informados.
        $externalGuests = [];
        if ($isExternal) {
            // Garante que as colunas/enum do convite externo existam (idempotente).
            $this->ensureExternalInviteSchema();
            $externalGuests = $this->parseExternalGuests();
            if (empty($externalGuests)) {
                $this->json(['error' => 'Informe ao menos um convidado externo (nome + e-mail ou telefone).'], 400);
            }
        }

        $data = [
            'title' => $title,
            'meeting_type' => $meetingType,
            'contact_id' => $contactId,
            'client_name' => trim($_POST['client_name'] ?? '') ?: (trim($_POST['new_client_name'] ?? '') ?: null),
            'client_phone' => trim($_POST['client_phone'] ?? '') ?: (trim($_POST['new_client_phone'] ?? '') ?: null),
            'assigned_to' => !empty($_POST['assigned_to']) ? intval($_POST['assigned_to']) : $user['id'],
            'created_by' => $user['id'],
            'urgency' => in_array($_POST['urgency'] ?? '', ['baixa','media','alta','urgente']) ? $_POST['urgency'] : 'media',
            'temperature' => in_array($_POST['temperature'] ?? '', ['frio','morno','quente']) ? $_POST['temperature'] : null,
            'status' => in_array($_POST['status'] ?? '', AgendaMeeting::$statuses) ? $_POST['status'] : 'a_agendar',
            'meeting_at' => !empty($_POST['meeting_at']) ? str_replace('T', ' ', $_POST['meeting_at']) : null,
            'notes' => trim($_POST['notes'] ?? '') ?: null,
        ];

        // Reunião operacional: sem cliente CRM, briefing ou e-mail do cliente.
        // Mantém título, notas (descrição), data/horário, participantes e o link do Meet (se gerado).
        if ($isOperational) {
            $data['contact_id'] = null;
            $data['client_name'] = null;
            $data['client_phone'] = null;
            $data['client_email'] = null;
            $data['temperature'] = null;
            $data['closed_by'] = null;
            // Preserva o link do Meet gerado no modal, se houver
            $preEventId = trim($_POST['google_event_id'] ?? '') ?: null;
            $preMeetLink = trim($_POST['meet_link'] ?? '') ?: null;
            if ($preEventId) $data['google_event_id'] = $preEventId;
            if ($preMeetLink) $data['meet_link'] = $preMeetLink;
        } elseif ($isExternal) {
            // Convite externo: sem cliente CRM/briefing. Guarda os convidados externos
            // (JSON), a preferência "Registrar agendamento" e o link do Meet (se gerado).
            $data['contact_id'] = null;
            $data['client_name'] = null;
            $data['client_phone'] = null;
            $data['client_email'] = null;
            $data['temperature'] = null;
            $data['closed_by'] = null;
            $data['external_guests'] = json_encode($externalGuests, JSON_UNESCAPED_UNICODE);
            $data['register_google'] = !empty($_POST['register_google']) ? 1 : 0;
            // Preserva o link do Meet gerado no modal, se houver
            $preEventId = trim($_POST['google_event_id'] ?? '') ?: null;
            $preMeetLink = trim($_POST['meet_link'] ?? '') ?: null;
            if ($preEventId) $data['google_event_id'] = $preEventId;
            if ($preMeetLink) $data['meet_link'] = $preMeetLink;
        } else {
            // Se convertida, salva quem fechou
            if ($data['status'] === 'convertida' && !empty($_POST['closed_by'])) {
                $data['closed_by'] = intval($_POST['closed_by']);
            }

            $data['client_email'] = trim($_POST['client_email'] ?? '') ?: null;
            // Se o link do Meet já foi gerado no modal, reaproveita (evita criar evento duplicado)
            $preEventId = trim($_POST['google_event_id'] ?? '') ?: null;
            $preMeetLink = trim($_POST['meet_link'] ?? '') ?: null;
            if ($preEventId) $data['google_event_id'] = $preEventId;
            if ($preMeetLink) $data['meet_link'] = $preMeetLink;
        }

        try {
            $id = $this->model->create($data);
        } catch (\Throwable $e) {
            // Causa mais comum: migration 115 (colunas do convite externo / valor
            // 'externo' do enum meeting_type) ainda não aplicada no banco.
            $hint = '';
            if ($isExternal) {
                $hint = ' Verifique se a migration 115_agenda_external_invite.sql foi executada no banco.';
            }
            $this->json(['error' => 'Não foi possível salvar a reunião: ' . $e->getMessage() . $hint], 500);
        }

        // Salva participantes da equipe
        $participantIds = array_filter(array_map('intval', $_POST['participants'] ?? []));
        if (!empty($participantIds)) {
            $this->model->setParticipants($id, $participantIds);
        }

        // Salva/atualiza o briefing do cliente (só reunião comercial com contato vinculado)
        if (!$isOperational && $contactId) {
            $this->saveBriefingFromPost($contactId, $user['id']);
        }

        // Notifica o responsável se for outra pessoa
        if ($data['assigned_to'] && $data['assigned_to'] != $user['id']) {
            $this->notify($data['assigned_to'], 'Nova reunião na agenda', "{$user['name']} agendou \"{$title}\" com você.");
        }

        if ($isOperational) {
            // Reunião operacional: notifica os participantes por WhatsApp + e-mail
            $this->notifyParticipants($id);
        } elseif ($isExternal) {
            // Convite externo: gera (se pedido) o link do Google Agenda e envia o
            // convite padronizado aos convidados externos por e-mail + WhatsApp.
            $this->prepareExternalGoogleLink($id);
            $this->notifyExternalGuests($id);
            // Também avisa a equipe interna selecionada, se houver.
            $this->notifyParticipants($id);
        } elseif (!empty($data['meeting_at'])) {
            // Integração Google Agenda/Meet + convites ao cliente (email + WhatsApp)
            // Se o evento já foi criado no modal (link gerado), só envia os convites; senão cria agora
            $this->createGoogleEventAndInvites($id, !$preEventId);
            // Notifica também a equipe (participantes internos) por WhatsApp + e-mail
            $this->notifyParticipants($id);
        }

        $this->json(['success' => true, 'meeting' => $this->model->findById($id)]);
    }

    /**
     * Notifica os participantes internos de uma reunião (operacional) por WhatsApp e e-mail.
     * Retorna um resumo com as quantidades enviadas.
     */
    private function notifyParticipants($meetingId)
    {
        $meeting = $this->model->findById($meetingId);
        if (!$meeting) return ['email' => 0, 'whatsapp' => 0];

        $participants = $this->model->getParticipantContacts($meetingId);
        if (empty($participants)) return ['email' => 0, 'whatsapp' => 0];

        $whenFmt = !empty($meeting['meeting_at'])
            ? date('d/m/Y \à\s H:i', strtotime($meeting['meeting_at']))
            : 'a definir';
        $desc = trim($meeting['notes'] ?? '');
        $meetLink = trim($meeting['meet_link'] ?? '');

        $sentEmail = 0;
        $sentWhats = 0;

        foreach ($participants as $p) {
            // E-mail
            if (!empty($p['email'])) {
                $emailBody = Mailer::template(
                    'Convite de Reunião',
                    "<p>Olá, <strong>" . htmlspecialchars($p['name']) . "</strong>!</p>
                     <p>Você foi incluído em uma reunião:</p>
                     <p style='margin:6px 0;'><strong>Assunto:</strong> " . htmlspecialchars($meeting['title']) . "</p>
                     <p style='margin:6px 0;'><strong>Data:</strong> {$whenFmt}</p>"
                     . ($desc !== '' ? "<p style='margin:6px 0;'><strong>Descrição:</strong> " . nl2br(htmlspecialchars($desc)) . "</p>" : "")
                     . ($meetLink !== '' ? "<p style='text-align:center;margin:24px 0;'>
                            <a href='{$meetLink}' style='background:#00BFA6;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:600;display:inline-block;'>
                                Entrar na reunião (Google Meet)
                            </a></p>
                            <p style='font-size:0.8rem;color:#888;word-break:break-all;'>Link: {$meetLink}</p>" : "")
                     . "<p>Nos vemos lá!</p>"
                );
                try {
                    if (Mailer::send($p['email'], 'Convite de Reunião — ' . $meeting['title'], $emailBody)) $sentEmail++;
                } catch (\Throwable $e) { /* ignora */ }
            }

            // WhatsApp
            if (!empty($p['phone'])) {
                $dateFmt = !empty($meeting['meeting_at']) ? date('d/m/Y', strtotime($meeting['meeting_at'])) : 'a definir';
                $timeFmt = !empty($meeting['meeting_at']) ? date('H\hi', strtotime($meeting['meeting_at'])) : 'a definir';
                $waMsg = "📅 *Reunião agendada*\n\n"
                    . "Olá, equipe! 👋\n\n"
                    . "A reunião *{$meeting['title']}* está confirmada.\n\n"
                    . "📅 *Data:* {$dateFmt}\n"
                    . "🕐 *Horário:* {$timeFmt}"
                    . ($meetLink !== '' ? "\n🔗 *Link da reunião:* {$meetLink}" : "")
                    . "\n\nContamos com a participação de todos os envolvidos.";
                try {
                    if (WhatsappNotifier::sendToPhone($p['phone'], $waMsg, $p['name'])) $sentWhats++;
                } catch (\Throwable $e) { /* ignora */ }
            }
        }

        try {
            $this->model->update($meetingId, ['notifications_sent_at' => date('Y-m-d H:i:s')]);
        } catch (\Throwable $e) { /* ignora */ }

        return ['email' => $sentEmail, 'whatsapp' => $sentWhats];
    }

    // ===================================================================
    // Convite externo 
    // ===================================================================

    /**
     * Auto-correção de schema para o convite externo (equivalente à migration
     * 115_agenda_external_invite.sql). Cria apenas o que estiver faltando, usando
     * o mesmo padrão de checagem via information_schema já usado no projeto
     * (ex.: CrmController::ensureCampaignSchema). Idempotente e silencioso: falhas
     * são logadas, mas não interrompem o fluxo. Assim o convite externo funciona
     * mesmo que a migration ainda não tenha sido rodada manualmente no banco.
     */
    private function ensureExternalInviteSchema()
    {
        $db = Database::getInstance();

        // 1) Garante o valor 'externo' no ENUM meeting_type.
        try {
            $col = $db->fetch(
                "SELECT COLUMN_TYPE ct FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'agenda_meetings' AND COLUMN_NAME = 'meeting_type'"
            );
            if ($col && strpos((string)$col['ct'], "'externo'") === false) {
                $db->query("ALTER TABLE agenda_meetings
                    MODIFY COLUMN meeting_type ENUM('comercial','operacional','externo') NOT NULL DEFAULT 'comercial'");
                if (class_exists('Logger')) Logger::info('ensureExternalInviteSchema: enum meeting_type atualizado');
            }
        } catch (\Throwable $e) {
            if (class_exists('Logger')) Logger::error('ensureExternalInviteSchema enum falhou', ['error' => $e->getMessage()]);
        }

        // 2) Colunas novas => definição do ADD COLUMN se faltarem.
        $columns = [
            'external_guests'      => "ADD COLUMN external_guests LONGTEXT NULL",
            'register_google'      => "ADD COLUMN register_google TINYINT(1) NOT NULL DEFAULT 0",
            'google_calendar_link' => "ADD COLUMN google_calendar_link VARCHAR(1000) NULL",
        ];
        foreach ($columns as $name => $ddl) {
            try {
                $exists = $db->fetch(
                    "SELECT 1 FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'agenda_meetings' AND COLUMN_NAME = ?",
                    [$name]
                );
                if (!$exists) {
                    // Nome de coluna vem de uma lista fixa (não do usuário) — sem risco de injeção.
                    $db->query("ALTER TABLE agenda_meetings {$ddl}");
                    if (class_exists('Logger')) Logger::info('ensureExternalInviteSchema: coluna criada', ['column' => $name]);
                }
            } catch (\Throwable $e) {
                if (class_exists('Logger')) Logger::error('ensureExternalInviteSchema falhou', ['column' => $name, 'error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Lê e sanitiza os convidados externos enviados no formulário.
     * Aceita arrays paralelos (external_name[], external_email[], external_phone[]).
     * Cada convidado precisa de nome e (e-mail OU telefone) para ser válido.
     *
     * @return array Lista de ['name'=>, 'email'=>, 'phone'=>]
     */
    private function parseExternalGuests()
    {
        $names  = $_POST['external_name']  ?? [];
        $emails = $_POST['external_email'] ?? [];
        $phones = $_POST['external_phone'] ?? [];

        if (!is_array($names))  $names  = [$names];
        if (!is_array($emails)) $emails = [$emails];
        if (!is_array($phones)) $phones = [$phones];

        $guests = [];
        $count = max(count($names), count($emails), count($phones));
        for ($i = 0; $i < $count; $i++) {
            $name  = trim($names[$i]  ?? '');
            $email = trim($emails[$i] ?? '');
            $phone = preg_replace('/\D/', '', trim($phones[$i] ?? ''));

            // Descarta linhas totalmente vazias.
            if ($name === '' && $email === '' && $phone === '') continue;
            // Precisa de nome e ao menos um canal de contato.
            if ($name === '' || ($email === '' && $phone === '')) continue;
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $email = '';

            $guests[] = ['name' => $name, 'email' => $email, 'phone' => $phone];
        }
        return $guests;
    }

    /**
     * Decodifica os convidados externos persistidos (coluna external_guests em JSON).
     */
    private function decodeExternalGuests($meeting)
    {
        $raw = $meeting['external_guests'] ?? '';
        if (empty($raw)) return [];
        $list = json_decode($raw, true);
        return is_array($list) ? $list : [];
    }

    /**
     * "Registrar agendamento": gera o link para o cliente adicionar o evento ao
     * próprio Google Agenda e o persiste. Não depende da integração OAuth do
     * Google — é um link de template público. Só gera quando a opção foi marcada
     * e há data/horário definidos.
     */
    private function prepareExternalGoogleLink($meetingId)
    {
        $meeting = $this->model->findById($meetingId);
        if (!$meeting) return null;

        if (empty($meeting['register_google']) || empty($meeting['meeting_at'])) {
            return $meeting['google_calendar_link'] ?? null;
        }

        $desc = trim($meeting['notes'] ?? '');
        $meetLink = trim($meeting['meet_link'] ?? '');
        if ($meetLink !== '') {
            $desc = trim($desc . "\n\nLink da reunião: " . $meetLink);
        }

        $link = GoogleCalendarApi::templateLink([
            'title' => $meeting['title'],
            'description' => $desc,
            'start' => $meeting['meeting_at'],
            'durationMin' => 60,
            'timezone' => 'America/Sao_Paulo',
        ]);

        if ($link) {
            try { $this->model->update($meetingId, ['google_calendar_link' => $link]); } catch (\Throwable $e) { /* ignora */ }
        }
        return $link;
    }

    /**
     * Envia o convite padronizado (e-mail + WhatsApp) aos convidados externos,
     * incluindo — quando disponível — o link do Meet e o link para registrar o
     * agendamento no Google Agenda do próprio cliente.
     *
     * @return array ['email'=>N, 'whatsapp'=>N]
     */
    private function notifyExternalGuests($meetingId)
    {
        $meeting = $this->model->findById($meetingId);
        if (!$meeting) return ['email' => 0, 'whatsapp' => 0];

        $guests = $this->decodeExternalGuests($meeting);
        if (empty($guests)) return ['email' => 0, 'whatsapp' => 0];

        $whenFmt = !empty($meeting['meeting_at'])
            ? date('d/m/Y \à\s H:i', strtotime($meeting['meeting_at']))
            : 'a definir';
        $desc = trim($meeting['notes'] ?? '');
        $meetLink = trim($meeting['meet_link'] ?? '');
        $calendarLink = trim($meeting['google_calendar_link'] ?? '');

        $sentEmail = 0;
        $sentWhats = 0;

        foreach ($guests as $g) {
            $name  = trim($g['name'] ?? '') ?: 'Convidado';
            $email = trim($g['email'] ?? '');
            $phone = preg_replace('/\D/', '', trim($g['phone'] ?? ''));

            // ----- E-mail padronizado -----
            if ($email !== '') {
                $emailBody = Mailer::template(
                    'Convite de Reunião',
                    "<p>Olá, <strong>" . htmlspecialchars($name) . "</strong>!</p>
                     <p>Você está sendo convidado(a) para uma reunião:</p>
                     <p style='margin:6px 0;'><strong>Assunto:</strong> " . htmlspecialchars($meeting['title']) . "</p>
                     <p style='margin:6px 0;'><strong>Data:</strong> {$whenFmt}</p>"
                     . ($desc !== '' ? "<p style='margin:6px 0;'><strong>Descrição:</strong> " . nl2br(htmlspecialchars($desc)) . "</p>" : "")
                     . ($meetLink !== '' ? "<p style='text-align:center;margin:24px 0 8px;'>
                            <a href='{$meetLink}' style='background:#00BFA6;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:600;display:inline-block;'>
                                Entrar na reunião (Google Meet)
                            </a></p>
                            <p style='font-size:0.8rem;color:#888;word-break:break-all;text-align:center;'>Link: {$meetLink}</p>" : "")
                     . ($calendarLink !== '' ? "<p style='text-align:center;margin:16px 0 8px;'>
                            <a href='{$calendarLink}' style='background:#4285F4;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:600;display:inline-block;'>
                                Adicionar ao Google Agenda
                            </a></p>
                            <p style='font-size:0.8rem;color:#888;text-align:center;'>Salve este compromisso na sua agenda com um clique.</p>" : "")
                     . "<p>Nos vemos lá!</p>"
                );
                try {
                    if (Mailer::send($email, 'Convite de Reunião — ' . $meeting['title'], $emailBody)) $sentEmail++;
                } catch (\Throwable $e) { /* ignora */ }
            }

            // ----- WhatsApp padronizado -----
            if ($phone !== '') {
                $dateFmt = !empty($meeting['meeting_at']) ? date('d/m/Y', strtotime($meeting['meeting_at'])) : 'a definir';
                $timeFmt = !empty($meeting['meeting_at']) ? date('H\hi', strtotime($meeting['meeting_at'])) : 'a definir';
                $waMsg = "📅 *Convite de Reunião*\n\n"
                    . "Olá, " . $name . "! 👋\n\n"
                    . "Você está sendo convidado(a) para a reunião *{$meeting['title']}*.\n\n"
                    . "📅 *Data:* {$dateFmt}\n"
                    . "🕐 *Horário:* {$timeFmt}"
                    . ($meetLink !== '' ? "\n🔗 *Link da reunião:* {$meetLink}" : "")
                    . ($calendarLink !== '' ? "\n🗓️ *Adicionar ao Google Agenda:* {$calendarLink}" : "")
                    . "\n\nContamos com a sua presença!";
                try {
                    if (WhatsappNotifier::sendToPhone($phone, $waMsg, $name)) $sentWhats++;
                } catch (\Throwable $e) { /* ignora */ }
            }
        }

        try {
            $this->model->update($meetingId, ['notifications_sent_at' => date('Y-m-d H:i:s')]);
        } catch (\Throwable $e) { /* ignora */ }

        return ['email' => $sentEmail, 'whatsapp' => $sentWhats];
    }

    /**
     * API: reenvia as notificações (WhatsApp + e-mail) aos participantes da reunião.
     * Para reuniões comerciais reaproveita o fluxo de convites (Google/Meet + cliente).
     */
    public function resendNotifications($id = null)
    {
        $this->requireRole($this->accessRoles);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);

        $meeting = $this->model->findById($id);
        if (!$meeting) $this->json(['error' => 'Reunião não encontrada'], 404);

        $type = $meeting['meeting_type'] ?? 'comercial';
        if ($type === 'operacional') {
            $result = $this->notifyParticipants($id);
            $this->json([
                'success' => true,
                'message' => "Notificações reenviadas: {$result['email']} e-mail(s), {$result['whatsapp']} WhatsApp.",
            ]);
        } elseif ($type === 'externo') {
            // Convite externo: regenera o link do Google Agenda (se aplicável) e
            // reenvia o convite padronizado aos convidados externos.
            $this->prepareExternalGoogleLink($id);
            $result = $this->notifyExternalGuests($id);
            $this->notifyParticipants($id);
            $this->json([
                'success' => true,
                'message' => "Convites reenviados: {$result['email']} e-mail(s), {$result['whatsapp']} WhatsApp.",
            ]);
        } else {
            // Reunião comercial: reenvia convites sem recriar o evento no Google
            $this->createGoogleEventAndInvites($id, false);
            $this->json(['success' => true, 'message' => 'Convites reenviados.']);
        }
    }

    /**
     * Cria o evento no Google Agenda (com Meet), salva o link e envia os convites
     * por e-mail (super admin + cliente) e WhatsApp (cliente).
     */
    private function createGoogleEventAndInvites($meetingId, $createEvent = true)
    {
        $meeting = $this->model->findById($meetingId);
        if (!$meeting) return;

        $clientName = $meeting['crm_contact_name'] ?? $meeting['client_name'] ?? 'Cliente';
        $clientEmail = $meeting['client_email'] ?? null;
        $clientPhone = $meeting['crm_contact_phone'] ?? $meeting['client_phone'] ?? null;
        $meetingAt = $meeting['meeting_at'];

        // E-mail do super admin (primeiro super_admin ativo)
        $db = Database::getInstance();
        $admin = $db->fetch("SELECT name, email FROM users WHERE role = 'super_admin' AND is_active = 1 AND email <> '' ORDER BY id ASC LIMIT 1");
        $adminEmail = $admin['email'] ?? null;

        // Reaproveita o link já gerado (no modal), se houver
        $meetLink = $meeting['meet_link'] ?? null;

        // Emails dos participantes internos da reunião
        $participantEmails = $this->model->getParticipantEmails($meetingId);

        // 1) Cria o evento no Google (se configurado e ainda não criado)
        $google = new GoogleCalendarApi();
        if ($createEvent && empty($meeting['google_event_id']) && $google->isConfigured()) {
            $attendees = array_values(array_unique(array_filter(
                array_merge([$adminEmail, $clientEmail], $participantEmails)
            )));
            $res = $google->createEvent([
                'title' => 'Reunião: ' . $meeting['title'],
                'description' => "Reunião comercial com {$clientName}." . ($meeting['notes'] ? "\n\n" . $meeting['notes'] : ''),
                'start' => $meetingAt,
                'durationMin' => 60,
                'timezone' => 'America/Sao_Paulo',
                'attendees' => $attendees,
            ]);
            if (!empty($res['success'])) {
                $meetLink = $res['meet_link'];
                $this->model->update($meetingId, [
                    'google_event_id' => $res['event_id'],
                    'meet_link' => $meetLink,
                ]);
            }
        }

        $whenFmt = date('d/m/Y \à\s H:i', strtotime($meetingAt));

        // 2) E-mail personalizado (super admin + cliente)
        $emailBody = Mailer::template(
            'Convite de Reunião',
            "<p>Olá!</p>
             <p>Uma reunião foi agendada:</p>
             <p style='margin:6px 0;'><strong>Assunto:</strong> " . htmlspecialchars($meeting['title']) . "</p>
             <p style='margin:6px 0;'><strong>Cliente:</strong> " . htmlspecialchars($clientName) . "</p>
             <p style='margin:6px 0;'><strong>Data:</strong> {$whenFmt}</p>"
             . ($meetLink ? "<p style='text-align:center;margin:24px 0;'>
                    <a href='{$meetLink}' style='background:#00BFA6;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:600;display:inline-block;'>
                        Entrar na reunião (Google Meet)
                    </a></p>
                    <p style='font-size:0.8rem;color:#888;word-break:break-all;'>Link: {$meetLink}</p>" : "")
             . "<p>Nos vemos lá!</p>"
        );
        foreach (array_filter([$adminEmail, $clientEmail]) as $to) {
            try { Mailer::send($to, 'Convite de Reunião — ' . $meeting['title'], $emailBody); } catch (\Throwable $e) {}
        }

        // 3) WhatsApp para o cliente
        if (!empty($clientPhone)) {
            $dateFmt = date('d/m/Y', strtotime($meetingAt));
            $timeFmt = date('H\hi', strtotime($meetingAt));
            $waMsg = "Olá, tudo bem?\n\n"
                . "Eu sou a Carla e faço parte da equipe da ON Solutions.\n"
                . "Estou entrando em contato para informar que sua reunião {$meeting['title']} está confirmada.\n\n"
                . "Será um prazer contar com a sua presença.\n\n"
                . "📅 Data: {$dateFmt}\n"
                . "🕐 Horário: {$timeFmt}"
                . ($meetLink ? "\n🔗 Link da reunião: {$meetLink}" : "");
            try { WhatsappNotifier::sendToPhone($clientPhone, $waMsg, $clientName); } catch (\Throwable $e) {}
        }
    }

    // API: atualizar reunião
    public function update($id = null)
    {
        $this->requireRole($this->accessRoles);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);

        $meeting = $this->model->findById($id);
        if (!$meeting) $this->json(['error' => 'Reunião não encontrada'], 404);

        $user = $this->currentUser();
        $meetingType = $meeting['meeting_type'] ?? 'comercial';
        $isOperational = $meetingType === 'operacional';
        $isExternal = $meetingType === 'externo';
        $data = [];
        if (isset($_POST['title'])) $data['title'] = trim($_POST['title']);
        if (isset($_POST['assigned_to'])) $data['assigned_to'] = $_POST['assigned_to'] ?: null;
        if (isset($_POST['urgency']) && in_array($_POST['urgency'], ['baixa','media','alta','urgente'])) $data['urgency'] = $_POST['urgency'];
        if (isset($_POST['temperature'])) $data['temperature'] = in_array($_POST['temperature'], ['frio','morno','quente']) ? $_POST['temperature'] : null;
        if (isset($_POST['status']) && in_array($_POST['status'], AgendaMeeting::$statuses)) $data['status'] = $_POST['status'];
        if (isset($_POST['meeting_at'])) $data['meeting_at'] = $_POST['meeting_at'] ? str_replace('T', ' ', $_POST['meeting_at']) : null;
        if (isset($_POST['notes'])) $data['notes'] = trim($_POST['notes']) ?: null;
        if (isset($_POST['client_email'])) $data['client_email'] = trim($_POST['client_email']) ?: null;

        // Se convertida, salva quem fechou; se saiu de convertida, limpa
        if (isset($data['status'])) {
            if ($data['status'] === 'convertida' && !empty($_POST['closed_by'])) {
                $data['closed_by'] = intval($_POST['closed_by']);
            } elseif ($data['status'] !== 'convertida') {
                $data['closed_by'] = null;
            }
        }

        if (!empty($data)) $this->model->update($id, $data);

        // Atualiza participantes da equipe
        if (isset($_POST['participants'])) {
            $participantIds = array_filter(array_map('intval', $_POST['participants']));
            $this->model->setParticipants($id, $participantIds);
        }

        // Reunião operacional: não mexe em briefing/Google. Só (re)notifica participantes
        // quando o usuário pediu explicitamente (checkbox de reenvio).
        if ($isOperational) {
            if (!empty($_POST['resend_notifications'])) {
                $this->notifyParticipants($id);
            }
            $this->json(['success' => true, 'meeting' => $this->model->findById($id)]);
            return;
        }

        // Convite externo: atualiza os convidados externos e a preferência de
        // registro no Google Agenda; não usa briefing nem o fluxo comercial.
        if ($isExternal) {
            // Garante que as colunas/enum do convite externo existam (idempotente).
            $this->ensureExternalInviteSchema();
            if (isset($_POST['external_name']) || isset($_POST['external_email']) || isset($_POST['external_phone'])) {
                $guests = $this->parseExternalGuests();
                $this->model->update($id, ['external_guests' => json_encode($guests, JSON_UNESCAPED_UNICODE)]);
            }
            if (isset($_POST['register_google'])) {
                $this->model->update($id, ['register_google' => !empty($_POST['register_google']) ? 1 : 0]);
            }
            // (Re)gera o link do Google Agenda conforme a preferência atual.
            $this->prepareExternalGoogleLink($id);
            if (!empty($_POST['resend_notifications'])) {
                $this->notifyExternalGuests($id);
                $this->notifyParticipants($id);
            }
            $this->json(['success' => true, 'meeting' => $this->model->findById($id)]);
            return;
        }

        // Atualiza o briefing do cliente vinculado
        if (!empty($meeting['contact_id'])) {
            $this->saveBriefingFromPost($meeting['contact_id'], $user['id']);
        }

        // Se mudou a data e já existe evento no Google, atualiza o horário
        if (isset($data['meeting_at']) && !empty($meeting['google_event_id'])) {
            try {
                $g = new GoogleCalendarApi();
                if ($g->isConfigured()) $g->updateEventTime($meeting['google_event_id'], $data['meeting_at'], 60);
            } catch (\Throwable $e) { /* ignora */ }
        }
        // Se não havia evento e agora tem data, cria (e envia convites)
        if (empty($meeting['google_event_id']) && !empty($data['meeting_at'])) {
            $this->createGoogleEventAndInvites($id);
        }

        // Se o status mudou para "cancelada" e há evento no Google, remove o evento se solicitado
        if (isset($data['status']) && $data['status'] === 'cancelada' && !empty($meeting['google_event_id'])) {
            if (!empty($_POST['delete_google_event'])) {
                $notify = !empty($_POST['notify_participants']);
                try {
                    $g = new GoogleCalendarApi();
                    if ($g->isConfigured()) {
                        $g->deleteEvent($meeting['google_event_id'], $notify);
                    }
                    $this->model->update($id, ['google_event_id' => null, 'meet_link' => null]);
                } catch (\Throwable $e) { /* ignora */ }
            }
        }

        $this->json(['success' => true, 'meeting' => $this->model->findById($id)]);
    }

    // API: verifica se a integração Google está configurada/funcionando
    public function googleStatus()
    {
        $this->requireRole($this->accessRoles);
        $google = new GoogleCalendarApi();
        $this->json(['configured' => $google->isConfigured()]);
    }

    // Dashboard de Performance Comercial
    public function dashboard()
    {
        $this->requireRole(['super_admin', 'comercial']);
        $user = $this->currentUser();

        // Filtros de período (padrão: mês atual)
        $startDate = $_GET['start'] ?? date('Y-m-01');
        $endDate = $_GET['end'] ?? date('Y-m-t');

        // Se for comercial, vê apenas os próprios dados
        $filterUserId = null;
        if ($user['role'] !== 'super_admin') {
            // Apenas super_admin vê todos; demais veem só o próprio
            $filterUserId = $user['id'];
        } elseif (!empty($_GET['user_id'])) {
            $filterUserId = intval($_GET['user_id']);
        }

        // Métricas de reuniões por usuário
        $meetingStats = $this->model->getPerformanceStats($startDate, $endDate, $filterUserId);
        $uniqueContacts = $this->model->getUniqueContactsByUser($startDate, $endDate, $filterUserId);

        // Métricas de mensagens WhatsApp
        $msgModel = new WhatsappMessage();
        $messageStats = $msgModel->getMessageStatsByUser($startDate, $endDate, $filterUserId);
        $responseStats = $msgModel->getContactResponseStats($startDate, $endDate, $filterUserId);

        // Métricas de e-mail prospecção
        $emailModel = new EmailProspection();
        $emailStats = $emailModel->getStatsByUser($startDate, $endDate, $filterUserId);

        // Métricas de fechamento (próprio vs terceiros)
        $closingStats = $this->model->getClosingStats($startDate, $endDate, $filterUserId);

        // Série mensal (gráfico)
        $trend = $this->model->getMonthlyTrend(6, $filterUserId);

        // Lista de usuários comerciais (para filtro no admin)
        $userModel = new User();
        $comerciais = $userModel->getByRoles(['super_admin', 'comercial', 'marketing']);

        // Monta dados consolidados por usuário para a tabela comparativa
        $tableData = [];
        $allUserIds = array_unique(array_merge(
            array_keys($meetingStats),
            array_keys($messageStats),
            array_keys($responseStats),
            array_keys($emailStats)
        ));

        // Buscar nomes de todos os usuários envolvidos para evitar "Usuário #ID"
        $userNames = [];
        if (!empty($allUserIds)) {
            $db = Database::getInstance();
            $placeholders = implode(',', array_fill(0, count($allUserIds), '?'));
            $nameRows = $db->fetchAll("SELECT id, name FROM users WHERE id IN ($placeholders)", array_values($allUserIds));
            foreach ($nameRows as $nr) {
                $userNames[$nr['id']] = $nr['name'];
            }
        }

        foreach ($allUserIds as $uid) {
            $ms = $meetingStats[$uid] ?? [];
            $msg = $messageStats[$uid] ?? ['sent' => 0, 'received' => 0, 'contacts_messaged' => 0];
            $resp = $responseStats[$uid] ?? ['contacted' => 0, 'replied' => 0, 'no_reply' => 0];
            $em = $emailStats[$uid] ?? ['sent' => 0, 'failed' => 0, 'total' => 0, 'unique_contacts' => 0];
            $cl = $closingStats[$uid] ?? ['closed_self' => 0, 'closed_by_others' => 0, 'closed_for_others' => 0];
            $tableData[] = [
                'user_id' => $uid,
                'user_name' => $ms['user_name'] ?? $userNames[$uid] ?? 'Usuário #' . $uid,
                'total_meetings' => $ms['total'] ?? 0,
                'agendada' => ($ms['agendada'] ?? 0) + ($ms['a_agendar'] ?? 0),
                'confirmada' => $ms['confirmada'] ?? 0,
                'realizada' => $ms['realizada'] ?? 0,
                'convertida' => $ms['convertida'] ?? 0,
                'remarcada' => $ms['remarcada'] ?? 0,
                'cancelada' => $ms['cancelada'] ?? 0,
                'closed_self' => $cl['closed_self'],
                'closed_by_others' => $cl['closed_by_others'],
                'closed_for_others' => $cl['closed_for_others'],
                'unique_contacts' => $uniqueContacts[$uid] ?? 0,
                'messages_sent' => $msg['sent'],
                'messages_received' => $msg['received'],
                'contacts_messaged' => $msg['contacts_messaged'],
                'contacts_contacted' => $resp['contacted'],
                'contacts_replied' => $resp['replied'],
                'contacts_no_reply' => $resp['no_reply'],
                'emails_sent' => $em['sent'],
                'emails_failed' => $em['failed'],
                'emails_total' => $em['total'],
                'emails_unique_contacts' => $em['unique_contacts'],
            ];
        }

        // Ordena por total de conversões (desc)
        usort($tableData, fn($a, $b) => $b['convertida'] <=> $a['convertida']);

        $this->view('agenda/dashboard', [
            'user' => $user,
            'tableData' => $tableData,
            'trend' => $trend,
            'comerciais' => $comerciais,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'filterUserId' => $filterUserId,
            'isAdmin' => $user['role'] === 'super_admin',
        ]);
    }

    // API: gerar o link do Google Meet ANTES de salvar (garante o link)
    public function generateMeet()
    {
        $this->requireRole($this->accessRoles);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);

        $google = new GoogleCalendarApi();
        if (!$google->isConfigured()) {
            $this->json(['error' => 'Google Agenda não configurado. Adicione as credenciais em Configurações.'], 400);
        }

        $title = trim($_POST['title'] ?? '') ?: 'Reunião';
        $meetingAt = !empty($_POST['meeting_at']) ? str_replace('T', ' ', $_POST['meeting_at']) : null;
        if (!$meetingAt) $this->json(['error' => 'Informe a data e o horário da reunião.'], 400);

        $user = $this->currentUser();
        $db = Database::getInstance();
        $admin = $db->fetch("SELECT email FROM users WHERE role = 'super_admin' AND is_active = 1 AND email <> '' ORDER BY id ASC LIMIT 1");
        $attendees = array_filter([$admin['email'] ?? null, trim($_POST['client_email'] ?? '')]);

        // Emails dos participantes selecionados no modal
        $participantIds = array_filter(array_map('intval', $_POST['participants'] ?? []));
        if (!empty($participantIds)) {
            $userModel = new User();
            foreach ($participantIds as $pid) {
                $pu = $userModel->findById($pid);
                if (!empty($pu['email'])) $attendees[] = $pu['email'];
            }
        }
        $attendees = array_values(array_unique(array_filter($attendees)));

        $res = $google->createEvent([
            'title' => 'Reunião: ' . $title,
            'description' => trim($_POST['notes'] ?? ''),
            'start' => $meetingAt,
            'durationMin' => 60,
            'timezone' => 'America/Sao_Paulo',
            'attendees' => $attendees,
        ]);

        if (empty($res['success'])) {
            $this->json(['error' => $res['error'] ?? 'Falha ao gerar o link do Meet.'], 400);
        }

        // Se estiver editando uma reunião existente, já vincula o evento
        $meetingId = !empty($_POST['meeting_id']) ? intval($_POST['meeting_id']) : null;
        if ($meetingId && $this->model->findById($meetingId)) {
            $this->model->update($meetingId, ['google_event_id' => $res['event_id'], 'meet_link' => $res['meet_link']]);
        }

        $this->json([
            'success' => true,
            'event_id' => $res['event_id'],
            'meet_link' => $res['meet_link'],
            'html_link' => $res['html_link'] ?? null,
        ]);
    }

    // API: mudar status (drag-and-drop no Kanban)
    public function updateStatus($id = null)
    {
        $this->requireRole($this->accessRoles);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);

        $status = $_POST['status'] ?? '';
        if (!in_array($status, AgendaMeeting::$statuses)) $this->json(['error' => 'Status inválido'], 400);

        // Não permite arrastar para "convertida" sem informar quem fechou — deve usar o modal
        if ($status === 'convertida') {
            $this->json(['error' => 'Para marcar como convertida, abra a reunião e informe quem fechou o negócio.'], 400);
        }

        $this->model->updateStatus($id, $status, intval($_POST['position'] ?? 0));
        $this->json(['success' => true]);
    }

    // API: excluir reunião
    public function delete($id = null)
    {
        $this->requireRole($this->accessRoles);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);

        $meeting = $this->model->findById($id);
        if (!$meeting) $this->json(['error' => 'Reunião não encontrada'], 404);

        // Se há evento no Google Calendar e o usuário pediu para remover
        if (!empty($meeting['google_event_id']) && !empty($_POST['delete_google_event'])) {
            $notify = !empty($_POST['notify_participants']);
            try {
                $google = new GoogleCalendarApi();
                if ($google->isConfigured()) {
                    $google->deleteEvent($meeting['google_event_id'], $notify);
                }
            } catch (\Throwable $e) { /* ignora erro na remoção do evento */ }
        }

        $this->model->delete($id);
        $this->json(['success' => true]);
    }

    // ===== Helpers =====
    private function saveBriefingFromPost($contactId, $userId)
    {
        // Só grava se algum campo de briefing foi enviado
        $keys = ['need', 'main_pain', 'current_solution', 'expected_goal', 'urgency', 'investment_range',
                 'decision_level', 'lead_temperature', 'lead_source', 'main_objection', 'next_step', 'notes'];
        $data = [];
        foreach ($keys as $k) {
            if (isset($_POST['bf_' . $k])) $data[$k] = trim($_POST['bf_' . $k]) ?: null;
        }
        if (empty($data)) return;
        // lead_temperature precisa ser válido ou null
        if (isset($data['lead_temperature']) && !in_array($data['lead_temperature'], ['frio','morno','quente'])) {
            $data['lead_temperature'] = null;
        }
        $this->contactModel->saveBriefing($contactId, $data, $userId);
    }

    private function notify($userId, $title, $message)
    {
        try {
            Database::getInstance()->insert('notifications', [
                'user_id' => $userId, 'title' => $title, 'message' => $message, 'type' => 'system',
            ]);
        } catch (\Throwable $e) { /* ignora */ }
    }
}
