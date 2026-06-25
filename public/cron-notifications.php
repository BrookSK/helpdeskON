<?php
/**
 * CRON: Enviar notificações por email
 * Configure no crontab: * * * * * php /caminho/para/public/cron-notifications.php
 */

session_start();

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('PUBLIC_PATH', __DIR__);
define('UPLOADS_PATH', PUBLIC_PATH . '/uploads');

require_once APP_PATH . '/core/helpers.php';
require_once APP_PATH . '/core/Database.php';
require_once APP_PATH . '/core/Config.php';

// Buscar notificações não enviadas
$db = Database::getInstance();
$notifications = $db->fetchAll(
    "SELECT n.*, u.email as user_email, u.name as user_name 
     FROM notifications n 
     INNER JOIN users u ON n.user_id = u.id 
     WHERE n.sent_at IS NULL AND n.type = 'system'
     LIMIT 50"
);

if (empty($notifications)) {
    echo "Nenhuma notificação pendente.\n";
    exit;
}

$smtpHost = Config::get('smtp_host');
$fromEmail = Config::get('smtp_from_email');
$fromName = Config::get('smtp_from_name', 'ON Solutions Helpdesk');

if (empty($smtpHost) || empty($fromEmail)) {
    echo "SMTP não configurado. Marcando notificações como enviadas.\n";
    foreach ($notifications as $n) {
        $db->update('notifications', ['sent_at' => date('Y-m-d H:i:s')], 'id = ?', [$n['id']]);
    }
    exit;
}

foreach ($notifications as $n) {
    $subject = $n['title'];
    $body = $n['message'];

    $headers = [
        "MIME-Version: 1.0",
        "Content-Type: text/html; charset=UTF-8",
        "From: {$fromName} <{$fromEmail}>",
        "Reply-To: {$fromEmail}",
    ];

    $htmlBody = "
    <html>
    <body style='font-family:Inter,Arial,sans-serif;background:#f5f7fa;padding:20px;'>
        <div style='max-width:600px;margin:0 auto;background:#fff;border-radius:12px;padding:30px;box-shadow:0 2px 10px rgba(0,0,0,0.05);'>
            <div style='text-align:center;margin-bottom:20px;'>
                <span style='color:#00BFA6;font-weight:700;font-size:1.5rem;'>ON</span>
                <span style='font-weight:300;font-size:1.2rem;'> Solutions</span>
            </div>
            <h2 style='color:#333;'>{$subject}</h2>
            <p style='color:#666;line-height:1.6;'>" . nl2br(htmlspecialchars($body)) . "</p>
            <hr style='border:none;border-top:1px solid #eee;margin:20px 0;'>
            <p style='color:#999;font-size:0.8rem;text-align:center;'>ON Solutions Helpdesk</p>
        </div>
    </body>
    </html>";

    $sent = @mail($n['user_email'], $subject, $htmlBody, implode("\r\n", $headers));

    if ($sent) {
        $db->update('notifications', ['sent_at' => date('Y-m-d H:i:s'), 'type' => 'email'], 'id = ?', [$n['id']]);
        echo "Email enviado para {$n['user_email']}: {$subject}\n";
    } else {
        echo "Falha ao enviar para {$n['user_email']}\n";
    }
}

echo "Processamento concluído.\n";
