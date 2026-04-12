<?php
date_default_timezone_set('Europe/Paris');
if (date('N') > 6 || (int)date('H') < 8 || (int)date('H') >= 19) exit;

require_once 'config.php';
require_once 'models/MonitoredUrl.php';
require_once 'models/User.php';
require_once 'models/InfoptimumAccount.php';
require_once 'services/StockChecker.php';
require_once 'services/EmailService.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) { die("DB Error"); }

$urlModel = new MonitoredUrl($pdo);
$accountModel = new InfoptimumAccount($pdo);
$checker = new StockChecker();
$emailService = new EmailService([
    'smtp_host' => $smtp_host ?? '',
    'smtp_port' => $smtp_port ?? 587,
    'smtp_user' => $smtp_user ?? '',
    'smtp_pass' => $smtp_pass ?? '',
    'smtp_from' => $smtp_from ?? 'no-reply@check-infoptimum.local',
    'smtp_secure' => $smtp_secure ?? 'tls'
]);

$stmt = $pdo->query("SELECT m.*, u.email as user_email, u.notification_email FROM monitored_urls m JOIN users u ON m.user_id = u.id");
$urls = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($urls as $url) {
    $availableAccounts = $accountModel->findAvailableForUrl($url['id']);
    
    if (empty($availableAccounts)) {
        $newStatus = $checker->checkOnly($url['url']);
    } else {
        $account = $availableAccounts[0];
        $checker->setCredentials($account['email'], $account['password']);
        $newStatus = $checker->check($url['url']);
        
        if ($newStatus === 'available_and_printed') {
            $accountModel->markAsOrdered($account['id'], $url['id']);
            $newStatus = 'available'; // Remettre à 'available' pour l'affichage
        }
    }

    $targetEmail = !empty($url['notification_email']) ? $url['notification_email'] : $url['user_email'];

    if ($newStatus === 'available' && $url['last_status'] !== 'available') {
        $emailService->sendStockNotification($targetEmail, $url['url']);
    }

    if ($newStatus === 'error' && $url['last_status'] !== 'error') {
        $emailService->sendErrorNotification($targetEmail, $url['url']);
    }

    $urlModel->updateStatus($url['id'], $newStatus);
    sleep(rand(2, 5));
}
?>