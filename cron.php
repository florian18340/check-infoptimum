<?php
/**
 * Script à exécuter via une tâche CRON (ex: toutes les 5 minutes)
 * Commande : php /chemin/vers/check-infoptimum/cron.php
 */

// --- RÉGULATION DU CRON ---
// Fuseau horaire pour être sûr du calcul de l'heure
date_default_timezone_set('Europe/Paris');

$currentDay = date('N'); // 1 (lundi) à 7 (dimanche)
$currentHour = (int)date('H'); // 0 à 23

// Vérifier si nous sommes entre lundi (1) et vendredi (5)
if ($currentDay > 5) {
    echo "[" . date('Y-m-d H:i:s') . "] CRON suspendu : nous sommes le week-end.\n";
    exit;
}

// Vérifier si l'heure est comprise entre 08:00 et 19:00 (inclus)
if ($currentHour < 8 || $currentHour >= 19) {
    echo "[" . date('Y-m-d H:i:s') . "] CRON suspendu : hors des horaires d'ouverture (08h-19h).\n";
    exit;
}

// --- INITIALISATION ---
$dir = __DIR__;
$configFile = $dir . '/config.php';

if (!file_exists($configFile)) {
    die("Erreur : Le fichier de configuration 'config.php' est manquant.\n");
}

require_once $configFile;
require_once $dir . '/models/MonitoredUrl.php';
require_once $dir . '/models/User.php';
require_once $dir . '/services/StockChecker.php';
require_once $dir . '/services/EmailService.php';

$dbHost = $host ?? 'localhost';
$dbName = $dbname ?? 'infoptimum_stock';
$dbUser = $username ?? 'root';
$dbPass = $password ?? '';

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion DB : " . $e->getMessage() . "\n");
}

$emailConfig = [
    'smtp_host' => $smtp_host ?? '',
    'smtp_port' => $smtp_port ?? 587,
    'smtp_user' => $smtp_user ?? '',
    'smtp_pass' => $smtp_pass ?? '',
    'smtp_from' => $smtp_from ?? 'no-reply@check-infoptimum.local',
    'smtp_secure' => $smtp_secure ?? 'tls'
];

$urlModel = new MonitoredUrl($pdo);
$checker = new StockChecker();
$emailService = new EmailService($emailConfig);

$stmt = $pdo->query("SELECT m.*, u.email, u.notification_email FROM monitored_urls m JOIN users u ON m.user_id = u.id");
$urls = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "[" . date('Y-m-d H:i:s') . "] Début de la vérification (" . count($urls) . " URLs)...\n";

foreach ($urls as $item) {
    echo "Vérification de : " . $item['url'] . "... ";
    $newStatus = $checker->check($item['url']);
    echo "Statut : $newStatus\n";
    
    $targetEmail = !empty($item['notification_email']) ? $item['notification_email'] : $item['email'];

    if ($newStatus === 'available' && $item['last_status'] !== 'available') {
        echo " -> ENVOI EMAIL STOCK à " . $targetEmail . "\n";
        $emailService->sendStockNotification($targetEmail, $item['url']);
    }

    if ($newStatus === 'error' && $item['last_status'] !== 'error') {
        echo " -> ENVOI EMAIL ERREUR à " . $targetEmail . "\n";
        $emailService->sendErrorNotification($targetEmail, $item['url']);
    }

    $urlModel->updateStatus($item['id'], $newStatus);
}

echo "Fin de la vérification.\n";
?>