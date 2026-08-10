<?php

class DashboardController extends Controller
{
    public function index()
    {
        $this->requireLogin();
        $user = $this->currentUser();
        $ticketModel = new Ticket();
        $messageModel = new TicketMessage();

        $unreadMessages = $messageModel->getUnreadCount($user['id']);

        if ($user['role'] === 'client') {
            $fullUser = (new User())->findById($user['id']);
            if ($fullUser['is_company_owner'] && $fullUser['company_id']) {
                // Responsável da empresa: vê todos os tickets da empresa (inclusive concluídos)
                $data = [
                    'user' => $user,
                    'counts' => $ticketModel->countByCompany($fullUser['company_id']),
                    'unreadMessages' => $unreadMessages,
                    'tickets' => $ticketModel->getByCompany($fullUser['company_id']),
                ];
            } else {
                $data = [
                    'user' => $user,
                    'counts' => $ticketModel->countByStatus($user['id'], $user['role']),
                    'unreadMessages' => $unreadMessages,
                    'tickets' => $ticketModel->getByClient($user['id']),
                ];
            }
            $this->view('client/dashboard', $data);
            return;
        }

        $counts = $ticketModel->countByStatus($user['id'], $user['role']);
        $data = [
            'user' => $user,
            'counts' => $counts,
            'unreadMessages' => $unreadMessages,
        ];

        if (in_array($user['role'], ['attendant', 'developer', 'analyst'])) {
            $data['tickets'] = $ticketModel->getByAttendant($user['id']);
            $this->view('attendant/dashboard', $data);
        } else {
            $data['tickets'] = $ticketModel->getAll();
            $userModel = new User();
            $data['totalClients'] = count($userModel->getClients());
            $data['totalAttendants'] = count($userModel->getAttendants());
            $data['overdueCards'] = (new PlanningCard())->getOverdue(10);
            $this->view('admin/dashboard', $data);
        }
    }
}
