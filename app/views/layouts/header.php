<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape($pageTitle ?? 'ON Solutions Helpdesk') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #00BFA6;
            --primary-dark: #00997D;
            --primary-light: #B2F2E8;
            --primary-50: #E0F7F4;
            --sidebar-bg: #1a1a2e;
            --sidebar-text: #e0e0e0;
        }
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f5f7fa;
        }
        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        .btn-outline-primary {
            color: var(--primary);
            border-color: var(--primary);
        }
        .btn-outline-primary:hover {
            background-color: var(--primary);
            border-color: var(--primary);
            color: #fff;
        }
        .bg-primary {
            background-color: var(--primary) !important;
        }
        .text-primary {
            color: var(--primary) !important;
        }
        .sidebar {
            width: 260px;
            min-height: 100vh;
            background: var(--sidebar-bg);
            position: fixed;
            left: 0;
            top: 0;
            z-index: 1000;
            transition: all 0.3s;
        }
        .sidebar .nav-link {
            color: var(--sidebar-text);
            padding: 12px 20px;
            border-radius: 8px;
            margin: 2px 10px;
            transition: all 0.2s;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: rgba(0, 191, 166, 0.15);
            color: var(--primary);
        }
        .sidebar .nav-link i {
            width: 24px;
            margin-right: 10px;
        }
        .main-content {
            margin-left: 260px;
            padding: 20px 30px;
        }
        .top-bar {
            background: #fff;
            border-radius: 12px;
            padding: 15px 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .stat-card {
            border-left: 4px solid var(--primary);
        }
        .badge-status {
            font-size: 0.75rem;
            padding: 5px 10px;
            border-radius: 20px;
        }
        .badge-open { background: #e3f2fd; color: #1565c0; }
        .badge-in_progress { background: #fff3e0; color: #e65100; }
        .badge-waiting_client { background: #fce4ec; color: #c62828; }
        .badge-completed { background: #e8f5e9; color: #2e7d32; }
        .badge-denied { background: #fbe9e7; color: #d84315; }
        .badge-archived { background: #eceff1; color: #546e7a; }
        .priority-low { color: #4caf50; }
        .priority-medium { color: #ff9800; }
        .priority-high { color: #f44336; }
        .priority-urgent { color: #9c27b0; }
        .chat-container {
            height: 400px;
            overflow-y: auto;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            padding: 15px;
            background: #fafafa;
        }
        .chat-message {
            margin-bottom: 15px;
            display: flex;
        }
        .chat-message.mine {
            justify-content: flex-end;
        }
        .chat-bubble {
            max-width: 70%;
            padding: 10px 15px;
            border-radius: 12px;
            font-size: 0.9rem;
        }
        .chat-message.mine .chat-bubble {
            background: var(--primary);
            color: #fff;
            border-bottom-right-radius: 4px;
        }
        .chat-message.other .chat-bubble {
            background: #e8e8e8;
            color: #333;
            border-bottom-left-radius: 4px;
        }
        .chat-sender {
            font-size: 0.75rem;
            font-weight: 600;
            margin-bottom: 3px;
        }
        .chat-time {
            font-size: 0.7rem;
            opacity: 0.7;
            margin-top: 3px;
        }
        .kanban-column {
            min-height: 200px;
            background: #f8f9fa;
            border-radius: 12px;
            padding: 15px;
        }
        .kanban-card {
            background: #fff;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            cursor: pointer;
            transition: transform 0.2s;
        }
        .kanban-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #f44336;
            color: #fff;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 0.65rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .logo-text {
            color: var(--primary);
            font-weight: 700;
            font-size: 1.3rem;
        }
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
