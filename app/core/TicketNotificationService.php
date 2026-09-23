<?php

/**
 * Serviço de notificação de NOVO ticket, compartilhado entre a criação interna
 * (TicketsController::store) e a criação via API (ApiController).
 *
 * A lógica aqui é uma extração fiel de TicketsController::sendNewTicketNotification()
 * (e do triggerWebhook/priorityLabelText que ele usa), para que ambos os fluxos
 * disparem exatamente as mesmas notificações:
 *  - notificação no sistema para atendentes com acesso à empresa do ticket;
 *  - notificação no sistema para todos os super_admin ativos;
 *  - webhook (se habilitado nas configurações).
 *
 * Importante: NÃO depende de $_SESSION nem de estado do controller, então pode
 * ser chamado tanto por uma requisição autenticada por sessão quanto por uma
 * requisição de API autenticada por X-Api-Key.
 */
class TicketNotificationService
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Dispara as notificações de criação de um novo ticket.
     * Mesmo comportamento de TicketsController::sendNewTicketNotification().
     */
    public function notifyNewTicket(int $ticketId): void
    {
        $ticket = (new Ticket())->findById($ticketId);
        if (!$ticket) {
            return;
        }

        $notificationTitle = "Nova demanda: {$ticket['title']}";
        $notificationMessage = "O cliente {$ticket['client_name']} abriu uma nova demanda.";

        // Descobrir empresa do cliente
        $clientUser = $this->db->fetch("SELECT company_id FROM users WHERE id = ?", [$ticket['client_id']]);
        $ticketCompanyId = $clientUser['company_id'] ?? null;

        // Notificar atendentes que têm acesso a essa empresa
        $userModel = new User();
        $attendants = $userModel->getAttendants();

        foreach ($attendants as $att) {
            $allowedCompanies = PlanningCard::getUserAllowedCompanies($att['id'], 'attendant');
            if ($allowedCompanies !== null && $ticketCompanyId) {
                if (!in_array($ticketCompanyId, $allowedCompanies)) {
                    continue; // atendente sem acesso a esta empresa
                }
            }

            $this->db->insert('notifications', [
                'user_id' => $att['id'],
                'ticket_id' => $ticketId,
                'title' => $notificationTitle,
                'message' => $notificationMessage,
                'type' => 'system',
            ]);
        }

        // Notificar super admins (sempre veem tudo)
        $admins = $this->db->fetchAll("SELECT id FROM users WHERE role = 'super_admin' AND is_active = 1");
        foreach ($admins as $admin) {
            $this->db->insert('notifications', [
                'user_id' => $admin['id'],
                'ticket_id' => $ticketId,
                'title' => $notificationTitle,
                'message' => $notificationMessage,
                'type' => 'system',
            ]);
        }

        // Webhook
        $this->triggerWebhook($notificationMessage, '', $ticket);
    }

    /**
     * Dispara o webhook configurado (mesma lógica de TicketsController::triggerWebhook).
     * Não faz nada se o webhook estiver desabilitado ou sem URL.
     */
    private function triggerWebhook(string $message, string $phone = '', array $ticketData = []): void
    {
        $webhookEnabled = Config::get('webhook_enabled');
        if (!$webhookEnabled) {
            return;
        }

        $webhookUrl = Config::get('webhook_url');
        if (empty($webhookUrl)) {
            return;
        }

        $phonesRaw = Config::get('webhook_phones') ?: Config::get('webhook_phone') ?: $phone;
        $namesRaw = Config::get('webhook_names') ?: Config::get('webhook_name') ?: 'Admin';
        $template = Config::get('webhook_message_template') ?: '';

        $phones = array_map('trim', explode(',', $phonesRaw));
        $names = array_map('trim', explode(',', $namesRaw));

        $formattedMessage = $message;
        if (!empty($template) && !empty($ticketData)) {
            $formattedMessage = str_replace(
                ['{ticket_id}', '{ticket_title}', '{client_name}', '{priority}', '{category}', '{date}', '{message}'],
                [
                    $ticketData['id'] ?? '',
                    $ticketData['title'] ?? '',
                    $ticketData['client_name'] ?? '',
                    $this->priorityLabelText($ticketData['priority'] ?? 'medium'),
                    $ticketData['category'] ?? 'Não definida',
                    date('d/m/Y H:i'),
                    $message,
                ],
                $template
            );
        }

        foreach ($phones as $index => $phoneNumber) {
            $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
            if (empty($phoneNumber)) continue;

            $recipientName = $names[$index] ?? ($names[0] ?? 'Admin');
            $finalMessage = str_replace('{name}', $recipientName, $formattedMessage);

            $payload = json_encode([
                'phone' => $phoneNumber,
                'name' => $recipientName,
                'message' => $finalMessage,
            ]);

            $ch = curl_init($webhookUrl);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
            ]);
            curl_exec($ch);
            curl_close($ch);
        }
    }

    private function priorityLabelText($priority): string
    {
        $labels = ['low' => 'Baixa', 'medium' => 'Média', 'high' => 'Alta', 'urgent' => 'Urgente'];
        return $labels[$priority] ?? $priority;
    }
}
