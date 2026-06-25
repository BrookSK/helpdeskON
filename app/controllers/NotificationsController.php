<?php

class NotificationsController extends Controller
{
    public function index()
    {
        $this->requireLogin();
        $user = $this->currentUser();
        $db = Database::getInstance();

        $notifications = $db->fetchAll(
            "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50",
            [$user['id']]
        );

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
        $notifications = $db->fetchAll(
            "SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 10",
            [$_SESSION['user_id']]
        );
        $count = count($notifications);
        $this->json(['count' => $count, 'notifications' => $notifications]);
    }
}
