<?php

class DashboardController extends Controller
{
    public function index()
    {
        $this->requireLogin();
        $user = $this->currentUser();
        $ticketModel = new Ticket();
        $messageModel = new TicketMessage();

        $counts = $ticketModel->countByStatus($user['id'], $user['role']);
        $unreadMessages = $messageModel->getUnreadCount($user['id']);

        $data = [
            'user' => $user,
            'counts' => $counts,
            'unreadMessages' => $unreadMessages,
        ];

        if ($user['role'] === 'client') {
            $data['tickets'] = $ticketModel->getByClient($user['id']);
            $this->view('client/dashboard', $data);
        } elseif ($user['role'] === 'attendant') {
            $data['tickets'] = $ticketModel->getByAttendant($user['id']);
            $this->view('attendant/dashboard', $data);
        } else {
            $data['tickets'] = $ticketModel->getAll();
            $userModel = new User();
            $data['totalClients'] = count($userModel->getClients());
            $data['totalAttendants'] = count($userModel->getAttendants());
            $this->view('admin/dashboard', $data);
        }
    }
}
