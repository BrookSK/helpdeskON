<?php

class NotificationsController extends Controller
{
    public function index()
    {
        $this->requireLogin();
        $user = $this->currentUser();
        $db = Database::getInstance();

        // Para atendentes, filtrar notificações de tickets de empresas sem acesso
        if ($user['role'] === 'attendant') {
            $allowedCompanies = PlanningCard::getUserAllowedCompanies($user['id'], $user['role']);
            if ($allowedCompanies !== null) {
                $realIds = array_filter($allowedCompanies, fn($id) => $id > 0);
                if (!empty($realIds)) {
                    $placeholders = implode(',', array_fill(0, count($realIds), '?'));
                    $notifications = $db->fetchAll(
                        "SELECT n.* FROM notifications n
                         LEFT JOIN tickets t ON n.ticket_id = t.id
                         LEFT JOIN users cu ON t.client_id = cu.id
                         WHERE n.user_id = ?
                           AND (n.ticket_id IS NULL OR cu.company_id IS NULL OR cu.company_id IN ($placeholders))
                         ORDER BY n.created_at DESC LIMIT 50",
                        array_merge([$user['id']], $realIds)
                    );
                } else {
                    // Nenhuma empresa — só notificações sem ticket ou de tickets sem empresa
                    $notifications = $db->fetchAll(
                        "SELECT n.* FROM notifications n
                         LEFT JOIN tickets t ON n.ticket_id = t.id
                         LEFT JOIN users cu ON t.client_id = cu.id
                         WHERE n.user_id = ?
                           AND (n.ticket_id IS NULL OR cu.company_id IS NULL)
                         ORDER BY n.created_at DESC LIMIT 50",
                        [$user['id']]
                    );
                }
            } else {
                $notifications = $db->fetchAll(
                    "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50",
                    [$user['id']]
                );
            }
        } else {
            $notifications = $db->fetchAll(
                "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50",
                [$user['id']]
            );
        }

        $this->view('notifications/index', ['user' => $user, 'notifications' => $notifications]);
    }

    public function markRead($id = null)
    {
        $this->requireLogin();
        if ($id) {
            $db = Database::getInstance();
            $db->update('notifications', ['is_read' => 1], 'id = ? AND user_id = ?', [$id, $_SESSION['user_id']]);
        }
        $this->json(['success' => true]);
    }

    public function markAllRead()
    {
        $this->requireLogin();
        $db = Database::getInstance();
        $db->query("UPDATE notifications SET is_read = 1 WHERE user_id = ?", [$_SESSION['user_id']]);
        $this->json(['success' => true]);
    }

    public function getUnread()
    {
        $this->requireLogin();
        $db = Database::getInstance();
        $userId = $_SESSION['user_id'];
        $role = $_SESSION['user_role'] ?? '';

        if ($role === 'attendant') {
            $allowedCompanies = PlanningCard::getUserAllowedCompanies($userId, 'attendant');
            if ($allowedCompanies !== null) {
                $realIds = array_filter($allowedCompanies, fn($id) => $id > 0);
                if (!empty($realIds)) {
                    $placeholders = implode(',', array_fill(0, count($realIds), '?'));
                    $notifications = $db->fetchAll(
                        "SELECT n.* FROM notifications n
                         LEFT JOIN tickets t ON n.ticket_id = t.id
                         LEFT JOIN users cu ON t.client_id = cu.id
                         WHERE n.user_id = ? AND n.is_read = 0
                           AND (n.ticket_id IS NULL OR cu.company_id IS NULL OR cu.company_id IN ($placeholders))
                         ORDER BY n.created_at DESC LIMIT 10",
                        array_merge([$userId], $realIds)
                    );
                } else {
                    $notifications = $db->fetchAll(
                        "SELECT n.* FROM notifications n
                         LEFT JOIN tickets t ON n.ticket_id = t.id
                         LEFT JOIN users cu ON t.client_id = cu.id
                         WHERE n.user_id = ? AND n.is_read = 0
                           AND (n.ticket_id IS NULL OR cu.company_id IS NULL)
                         ORDER BY n.created_at DESC LIMIT 10",
                        [$userId]
                    );
                }
            } else {
                $notifications = $db->fetchAll(
                    "SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 10",
                    [$userId]
                );
            }
        } else {
            $notifications = $db->fetchAll(
                "SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 10",
                [$userId]
            );
        }

        $count = count($notifications);
        $this->json(['count' => $count, 'notifications' => $notifications]);
    }
}
