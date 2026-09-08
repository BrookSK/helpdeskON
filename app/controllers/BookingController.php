<?php

/**
 * Agendamento PÚBLICO (sem login) de reuniões online.
 *
 * Fluxo:
 *   GET  /booking/{token}                 → página pública com dados pré-preenchidos
 *   GET  /booking/slots/{token}?date=Y-m-d→ horários disponíveis do dia (JSON)
 *   POST /booking/confirm/{token}         → cria a reunião, gera Meet e notifica
 *
 * O token é criado pelo bloco "Agendamento" da sequência (SequenceEngine) e
 * vincula o lead (contact_id) ao responsável (assigned_to).
 */
class BookingController extends Controller
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** Lê as regras de agendamento configuradas em Settings (com defaults). */
    private function cfg()
    {
        $start = trim((string) Config::get('booking_work_start')) ?: '09:00';
        $end   = trim((string) Config::get('booking_work_end')) ?: '18:00';
        $days  = trim((string) Config::get('booking_days_of_week')) ?: '1,2,3,4,5';
        return [
            'min_advance_days' => max(0, (int) (Config::get('booking_min_advance_days') ?? 1)),
            'work_start_min'   => $this->hmToMin($start),
            'work_end_min'     => $this->hmToMin($end),
            'slot_min'         => max(10, (int) (Config::get('booking_slot_minutes') ?? 30)),
            'days'             => array_filter(array_map('intval', explode(',', $days))),
            'duration_min'     => max(15, (int) (Config::get('booking_duration_min') ?? 45)),
            'link_expiry_days' => max(1, (int) (Config::get('booking_link_expiry_days') ?? 30)),
            'notify_hours'     => max(0, (int) (Config::get('booking_notify_hours_before') ?? 24)),
        ];
    }

    private function hmToMin($hm)
    {
        if (!preg_match('/^(\d{1,2}):(\d{2})$/', trim($hm), $m)) return 9 * 60;
        return ((int)$m[1]) * 60 + (int)$m[2];
    }

    /** Página pública de agendamento. Aceita /booking/{token} (token vem na URL). */
    public function index($token = null)
    {
        // O roteador trata /booking/{token} como método; recupera o token da URL.
        if (!$token) {
            $parts = explode('/', trim((string)($_GET['url'] ?? ''), '/'));
            $token = $parts[1] ?? null;
        }
        $link = $this->findLink($token);
        if (!$link) {
            $this->renderMessage('Link inválido', 'Este link de agendamento não é válido ou expirou.');
            return;
        }
        if ($link['status'] === 'booked') {
            $meeting = $link['meeting_id'] ? (new AgendaMeeting())->findById($link['meeting_id']) : null;
            $this->view('booking/done', ['link' => $link, 'meeting' => $meeting, 'already' => true]);
            return;
        }

        $contact = $link['contact_id'] ? $this->db->fetch(
            "SELECT id, contact_name, push_name, lead_email, phone FROM whatsapp_contacts WHERE id = ?",
            [$link['contact_id']]
        ) : null;

        $company = null;
        if ($contact) {
            $bf = $this->db->fetch("SELECT notes FROM commercial_briefings WHERE contact_id = ? LIMIT 1", [$contact['id']]);
            if ($bf && preg_match('/Empresa:\s*([^|]+)/i', (string)$bf['notes'], $m)) {
                $company = trim($m[1]);
            }
        }

        $cfg = $this->cfg();
        $minDate = date('Y-m-d', strtotime('+' . $cfg['min_advance_days'] . ' days', strtotime(date('Y-m-d'))));

        $this->view('booking/index', [
            'link' => $link,
            'contact' => $contact,
            'company' => $company,
            'prefName' => $contact['contact_name'] ?? ($contact['push_name'] ?? ''),
            'prefEmail' => $contact['lead_email'] ?? '',
            'prefPhone' => $contact['phone'] ?? '',
            'minDate' => $minDate,
            'allowedDays' => array_values($cfg['days']),
        ]);
    }

    /** JSON: horários livres de um dia (respeita reuniões já marcadas do responsável). */
    public function slots($token = null)
    {
        $link = $this->findLink($token);
        if (!$link) $this->json(['error' => 'Link inválido'], 404);

        $date = $_GET['date'] ?? date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $this->json(['error' => 'Data inválida'], 400);

        $cfg = $this->cfg();

        // Antecedência mínima: data precisa ser >= hoje + N dias
        $minDate = strtotime('+' . $cfg['min_advance_days'] . ' days', strtotime(date('Y-m-d')));
        if (strtotime($date) < $minDate) $this->json(['slots' => [], 'reason' => 'advance']);

        // Dia da semana permitido? (ISO: 1=seg ... 7=dom)
        $dow = (int) date('N', strtotime($date));
        if (!empty($cfg['days']) && !in_array($dow, $cfg['days'], true)) {
            $this->json(['slots' => [], 'reason' => 'weekday']);
        }

        $duration = (int)($link['duration_min'] ?? $cfg['duration_min']);

        // Reuniões já marcadas para o responsável nesse dia (evita conflito)
        $busy = [];
        if (!empty($link['assigned_to'])) {
            $rows = $this->db->fetchAll(
                "SELECT meeting_at FROM agenda_meetings
                 WHERE assigned_to = ? AND DATE(meeting_at) = ? AND status NOT IN ('cancelada')",
                [$link['assigned_to'], $date]
            );
            foreach ($rows as $r) $busy[date('H:i', strtotime($r['meeting_at']))] = true;
        }

        $slots = [];
        $isToday = ($date === date('Y-m-d'));
        for ($min = $cfg['work_start_min']; $min < $cfg['work_end_min']; $min += $cfg['slot_min']) {
            $hhmm = sprintf('%02d:%02d', intdiv($min, 60), $min % 60);
            if ($isToday && strtotime($date . ' ' . $hhmm) <= time() + 3600) continue;
            if (!empty($busy[$hhmm])) continue;
            $slots[] = $hhmm;
        }
        $this->json(['slots' => $slots, 'duration' => $duration]);
    }

    /** Confirma o agendamento: cria a reunião, gera o Meet e notifica. */
    public function confirm($token = null)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);
        $link = $this->findLink($token);
        if (!$link) $this->json(['error' => 'Link inválido'], 404);
        if ($link['status'] === 'booked') $this->json(['error' => 'Este agendamento já foi realizado.'], 409);

        $date = $_POST['date'] ?? '';
        $time = $_POST['time'] ?? '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}$/', $time)) {
            $this->json(['error' => 'Escolha uma data e um horário válidos.'], 400);
        }
        $meetingAt = $date . ' ' . $time . ':00';
        if (strtotime($meetingAt) < time()) $this->json(['error' => 'Escolha um horário futuro.'], 400);

        // Revalida as regras de agendamento (antecedência e dia da semana)
        $cfg = $this->cfg();
        $minDate = strtotime('+' . $cfg['min_advance_days'] . ' days', strtotime(date('Y-m-d')));
        if (strtotime($date) < $minDate) $this->json(['error' => 'Escolha uma data respeitando a antecedência mínima.'], 400);
        $dow = (int) date('N', strtotime($date));
        if (!empty($cfg['days']) && !in_array($dow, $cfg['days'], true)) {
            $this->json(['error' => 'Este dia da semana não está disponível para agendamento.'], 400);
        }

        $name  = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = preg_replace('/\D/', '', $_POST['phone'] ?? '');
        if ($name === '') $this->json(['error' => 'Informe seu nome.'], 400);
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $this->json(['error' => 'E-mail inválido.'], 400);

        $agenda = new AgendaMeeting();
        $title = $link['title'] ?: ('Reunião com ' . $name);

        // Cria a reunião já agendada
        $meetingId = $agenda->create([
            'title' => $title,
            'meeting_type' => 'comercial',
            'contact_id' => $link['contact_id'] ?: null,
            'client_name' => $name,
            'client_phone' => $phone ?: null,
            'client_email' => $email ?: null,
            'assigned_to' => $link['assigned_to'] ?: null,
            'created_by' => $link['assigned_to'] ?: $this->firstSuperAdminId(),
            'urgency' => 'media',
            'status' => 'agendada',
            'meeting_at' => $meetingAt,
            'notes' => 'Agendado pelo próprio lead via link público.',
            'booking_token' => $link['token'],
        ]);

        // Atualiza dados do lead (e-mail/telefone) se estavam vazios
        if ($link['contact_id']) {
            $upd = [];
            if ($email !== '') $upd['lead_email'] = mb_strtolower($email);
            if ($phone !== '') $upd['phone'] = $phone;
            if ($upd) { try { $this->db->update('whatsapp_contacts', $upd, 'id = ?', [$link['contact_id']]); } catch (\Throwable $e) {} }
        }

        // Gera o link do Google Meet e envia as notificações (e-mail + WhatsApp)
        $meetLink = $this->createMeetAndNotify($meetingId, $link, $name, $email, $phone, $meetingAt, $title);

        // Marca o link como usado
        $this->db->update('agenda_booking_links', [
            'status' => 'booked',
            'meeting_id' => $meetingId,
            'used_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$link['id']]);

        // Timeline + move o card para "Reunião" se o lead tiver card
        if ($link['contact_id']) {
            try {
                (new LeadTimelineService())->add($link['contact_id'], 'note',
                    'Reunião agendada pelo lead para ' . date('d/m/Y H:i', strtotime($meetingAt)) . ($meetLink ? ' — ' . $meetLink : ''),
                    ['channel' => 'booking']);
            } catch (\Throwable $e) {}

            // Analytics (Camada 1): marca o marco mais importante — AGENDOU reunião —
            // ANTES de encerrar as sequências (o stop apaga o vínculo ativo).
            try { (new SequenceEngine())->analyticsScheduled($link['contact_id'], $meetingAt); } catch (\Throwable $e) {}

            // Reunião agendada: ENCERRA todas as sequências ativas do lead para não
            // continuar enviando mensagens da cadência/triagem (evita o "looping").
            try { (new SequenceEngine())->stopForContact($link['contact_id'], 'booked'); } catch (\Throwable $e) {}

            $this->moveContactCard($link['contact_id'], 'Reunião');
        }

        $this->json([
            'success' => true,
            'meeting_at' => $meetingAt,
            'meet_link' => $meetLink,
        ]);
    }

    // ---------------- Helpers ----------------

    private function findLink($token)
    {
        $token = trim((string)$token);
        if ($token === '') return null;
        $link = $this->db->fetch("SELECT * FROM agenda_booking_links WHERE token = ? LIMIT 1", [$token]);
        if (!$link) return null;
        if (!empty($link['expires_at']) && strtotime($link['expires_at']) < time() && $link['status'] === 'pending') {
            $this->db->update('agenda_booking_links', ['status' => 'expired'], 'id = ?', [$link['id']]);
            $link['status'] = 'expired';
        }
        return $link;
    }

    private function firstSuperAdminId()
    {
        $r = $this->db->fetch("SELECT id FROM users WHERE role='super_admin' AND is_active=1 ORDER BY id ASC LIMIT 1");
        return $r ? (int)$r['id'] : null;
    }

    /** Cria o evento no Google (Meet) e dispara e-mail + WhatsApp de confirmação. */
    private function createMeetAndNotify($meetingId, $link, $name, $email, $phone, $meetingAt, $title)
    {
        $agenda = new AgendaMeeting();
        $meetLink = null;

        // E-mails: responsável + super admin
        $attendees = [];
        $ownerEmail = null;
        if (!empty($link['assigned_to'])) {
            $u = (new User())->findById($link['assigned_to']);
            if (!empty($u['email'])) { $ownerEmail = $u['email']; $attendees[] = $u['email']; }
        }
        $admin = $this->db->fetch("SELECT email FROM users WHERE role='super_admin' AND is_active=1 AND email<>'' ORDER BY id ASC LIMIT 1");
        if (!empty($admin['email'])) $attendees[] = $admin['email'];
        if ($email !== '') $attendees[] = $email;
        $attendees = array_values(array_unique(array_filter($attendees)));

        try {
            $google = new GoogleCalendarApi();
            if ($google->isConfigured()) {
                $res = $google->createEvent([
                    'title' => 'Reunião: ' . $title,
                    'description' => "Reunião agendada pelo lead {$name} via link público.",
                    'start' => $meetingAt,
                    'durationMin' => (int)($link['duration_min'] ?? 45),
                    'timezone' => 'America/Sao_Paulo',
                    'attendees' => $attendees,
                ]);
                if (!empty($res['success'])) {
                    $meetLink = $res['meet_link'];
                    $agenda->update($meetingId, ['google_event_id' => $res['event_id'], 'meet_link' => $meetLink]);
                }
            }
        } catch (\Throwable $e) { /* segue sem link do Meet */ }

        $whenFmt = date('d/m/Y \à\s H:i', strtotime($meetingAt));

        // E-mail para o lead + responsável + admin
        $emailBody = Mailer::template('Reunião confirmada',
            "<p>Olá, <strong>" . htmlspecialchars($name) . "</strong>!</p>
             <p>Sua reunião com a ON Solutions Brasil foi confirmada:</p>
             <p style='margin:6px 0;'><strong>Assunto:</strong> " . htmlspecialchars($title) . "</p>
             <p style='margin:6px 0;'><strong>Data:</strong> {$whenFmt}</p>"
             . ($meetLink ? "<p style='text-align:center;margin:24px 0;'>
                    <a href='{$meetLink}' style='background:#00BFA6;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:600;display:inline-block;'>Entrar na reunião (Google Meet)</a></p>
                    <p style='font-size:0.8rem;color:#888;word-break:break-all;'>Link: {$meetLink}</p>" : "")
             . "<p>Até breve!</p>");
        foreach (array_values(array_unique(array_filter([$email, $ownerEmail, $admin['email'] ?? null]))) as $to) {
            try { Mailer::send($to, 'Reunião confirmada — ' . $title, $emailBody); } catch (\Throwable $e) {}
        }

        // WhatsApp para o lead
        if ($phone !== '') {
            $first = trim(explode(' ', trim($name))[0] ?? $name);
            $waMsg = "✅ *Reunião confirmada!*\n\n"
                . "Olá, {$first}! Seu horário com a ON Solutions Brasil está confirmado.\n\n"
                . "🗓️ *Assunto:* {$title}\n"
                . "🕒 *Data:* {$whenFmt}\n"
                . ($meetLink ? "🔗 *Link da reunião (Google Meet):*\n{$meetLink}\n" : "")
                . "\nVocê receberá um lembrete antes do horário. Até breve!";
            try { WhatsappNotifier::sendToPhone($phone, $waMsg, $name); } catch (\Throwable $e) {}
        }

        // WhatsApp/e-mail interno para o responsável avisando do novo agendamento
        if (!empty($link['assigned_to'])) {
            $u = (new User())->findById($link['assigned_to']);
            if (!empty($u['phone'])) {
                $waOwner = "📅 *Novo agendamento*\n\n{$name} agendou uma reunião.\n*Data:* {$whenFmt}\n" . ($meetLink ? "*Meet:* {$meetLink}" : '');
                try { WhatsappNotifier::sendToPhone($u['phone'], $waOwner, $u['name']); } catch (\Throwable $e) {}
            }
        }

        return $meetLink;
    }

    /** Move o card do lead para a coluna informada (no board de Prospecção), se existir. */
    private function moveContactCard($contactId, $columnName)
    {
        try {
            $card = $this->db->fetch(
                "SELECT cc.id, col.board_id FROM crm_cards cc
                 JOIN crm_columns col ON cc.column_id = col.id
                 WHERE cc.contact_id = ? ORDER BY cc.id DESC LIMIT 1", [$contactId]);
            if (!$card) return;
            $col = $this->db->fetch(
                "SELECT id FROM crm_columns WHERE board_id = ? AND name = ? ORDER BY position ASC LIMIT 1",
                [$card['board_id'], $columnName]);
            if ($col) (new CrmBoard())->moveCard($card['id'], (int)$col['id'], 0);
        } catch (\Throwable $e) { /* ignora */ }
    }

    private function renderMessage($title, $msg)
    {
        $this->view('booking/message', ['msgTitle' => $title, 'msgText' => $msg]);
    }
}
