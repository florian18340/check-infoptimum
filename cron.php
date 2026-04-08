<?php
/**
 * Script à exécuter via une tâche CRON (ex: toutes les 5 minutes)
 * Commande : php /chemin/vers/check-infoptimum/cron.php
 */
exit;
// Définition de chemins absolus pour éviter les problèmes de CRON
$dir = __DIR__;
$configFile = $dir . '/config.php';

if (!file_exists($configFile)) {
    die("Erreur : Le fichier de configuration 'config.php' est manquant à la racine du projet.\nVeuillez copier 'config.php.example' vers 'config.php' et le configurer.\n");
}

require_once $configFile;
require_once $dir . '/models/MonitoredUrl.php';
require_once $dir . '/models/User.php';
require_once $dir . '/services/StockChecker.php';
require_once $dir . '/services/EmailService.php';

// Initialisation des variables de DB avec fallback (évite les erreurs d'analyse statique)
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
$checker = new StockChecker();
$emailService = new EmailService($emailConfig);

// Récupérer TOUTES les URLs surveillées avec les infos utilisateurs
$stmt = $pdo->query("SELECT m.*, u.email, u.notification_email FROM monitored_urls m JOIN users u ON m.user_id = u.id");
$urls = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "[" . date('Y-m-d H:i:s') . "] Début de la vérification (" . count($urls) . " URLs)...\n";

foreach ($urls as $item) {
    echo "Vérification de : " . $item['url'] . "... ";
    $newStatus = $checker->check($item['url']);
    echo "Statut : $newStatus\n";
    
    // Détermination de l'adresse de notification
    $targetEmail = !empty($item['notification_email']) ? $item['notification_email'] : $item['email'];

    // Cas 1 : Le stock devient disponible
    if ($newStatus === 'available' && $item['last_status'] !== 'available') {
        echo " -> ENVOI EMAIL STOCK à " . $targetEmail . "\n";
        $emailService->sendStockNotification($targetEmail, $item['url']);
    }

    // Cas 2 : Le système rencontre une erreur
    if ($newStatus === 'error' && $item['last_status'] !== 'error') {
        echo " -> ENVOI EMAIL ERREUR à " . $targetEmail . "\n";
        $emailService->sendErrorNotification($targetEmail, $item['url']);
    }

    $urlModel->updateStatus($item['id'], $newStatus);
}

echo "Fin de la vérification.\n";
?>