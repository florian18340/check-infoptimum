<?php
// Script à exécuter via une tâche CRON (ex: toutes les 5 minutes)
// Commande : php /chemin/vers/check-infoptimum/cron.php

require_once 'config.php';
require_once 'models/MonitoredUrl.php';
require_once 'models/User.php';
require_once 'services/StockChecker.php';
require_once 'services/EmailService.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion DB : " . $e->getMessage());
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
// On doit modifier la requête pour récupérer aussi le notification_email
$stmt = $pdo->query("SELECT m.*, u.email, u.notification_email FROM monitored_urls m JOIN users u ON m.user_id = u.id");
$urls = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Début de la vérification (" . count($urls) . " URLs)...\n";

foreach ($urls as $item) {
    echo "Vérification de : " . $item['url'] . "... ";
    $newStatus = $checker->check($item['url']);
    echo "Statut : $newStatus\n";
    
    if ($newStatus === 'available' && $item['last_status'] !== 'available') {
        // Utiliser l'email de notification s'il est défini, sinon l'email du compte
        $targetEmail = !empty($item['notification_email']) ? $item['notification_email'] : $item['email'];
        
        echo " -> ENVOI EMAIL à " . $targetEmail . "\n";
        $emailService->sendStockNotification($targetEmail, $item['url']);
    }

    $urlModel->updateStatus($item['id'], $newStatus);
}

echo "Fin de la vérification.\n";
?>