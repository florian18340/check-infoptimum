<?php
exit;
date_default_timezone_set('Europe/Paris');
if (date('N') > 6 || (int)date('H') < 8 || (int)date('H') >= 19) exit;

require_once 'config.php';
require_once 'models/MonitoredUrl.php';
require_once 'models/User.php';
require_once 'services/StockChecker.php';
require_once 'services/EmailService.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) { die("DB Error"); }

// Configuration du Proxy
$proxyConfig = [
    'host' => $proxy_host ?? '',
    'port' => $proxy_port ?? '',
    'user' => $proxy_user ?? '',
    'pass' => $proxy_pass ?? '',
];

// Configuration Email
$emailConfig = [
    'smtp_host' => $smtp_host ?? '',
    'smtp_port' => $smtp_port ?? 587,
    'smtp_user' => $smtp_user ?? '',
    'smtp_pass' => $smtp_pass ?? '',
    'smtp_from' => $smtp_from ?? 'no-reply@check-infoptimum.local',
    'smtp_secure' => $smtp_secure ?? 'tls'
];

$urlModel = new MonitoredUrl($pdo);
$checker = new StockChecker($proxyConfig);
$emailService = new EmailService($emailConfig);

$stmt = $pdo->query("SELECT m.*, u.email as user_email, u.notification_email FROM monitored_urls m JOIN users u ON m.user_id = u.id");
$urls = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "[" . date('Y-m-d H:i:s') . "] Début de la vérification (" . count($urls) . " URLs)...\n";

foreach ($urls as $url) {
    $newStatus = $checker->check($url['url']);
    echo "Vérification de : " . $url['url'] . "... Statut : $newStatus\n";
    
    $targetEmail = !empty($url['notification_email']) ? $url['notification_email'] : $url['user_email'];

    if ($newStatus === 'available' && $url['last_status'] !== 'available') {
        echo " -> ENVOI EMAIL STOCK à " . $targetEmail . "\n";
        $emailService->sendStockNotification($targetEmail, $url['url']);
    }

    if ($newStatus === 'error' && $url['last_status'] !== 'error') {
        echo " -> ENVOI EMAIL ERREUR à " . $targetEmail . "\n";
        $emailService->sendErrorNotification($targetEmail, $url['url']);
    }

    $urlModel->updateStatus($url['id'], $newStatus);
    sleep(rand(5, 15));
}

echo "Fin de la vérification.\n";
?>