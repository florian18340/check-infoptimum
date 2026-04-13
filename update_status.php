<?php
// Active l'affichage des erreurs pour le débogage
error_reporting(E_ALL);
ini_set('display_errors', 1);

$logFile = __DIR__ . '/update.log';
$logMessage = date('Y-m-d H:i:s') . " - Requête reçue.\n";
file_put_contents($logFile, $logMessage, FILE_APPEND);

require_once 'config.php';
require_once 'models/MonitoredUrl.php';

$secret_key_for_workers = $worker_secret_key ?? 'change-this-default-key';

if (($_POST['secret'] ?? '') !== $secret_key_for_workers) {
    $logMessage = date('Y-m-d H:i:s') . " - ECHEC : Clé secrète invalide.\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    http_response_code(403);
    die('Authentication failed');
}

if (isset($_POST['id']) && isset($_POST['status'])) {
    $logMessage = date('Y-m-d H:i:s') . " - Données reçues : ID=" . $_POST['id'] . ", Statut=" . $_POST['status'] . "\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $urlModel = new MonitoredUrl($pdo);
        $success = $urlModel->updateStatus($_POST['id'], $_POST['status']);
        
        if ($success) {
            $logMessage = date('Y-m-d H:i:s') . " - SUCCES : Base de données mise à jour.\n";
            file_put_contents($logFile, $logMessage, FILE_APPEND);
            echo 'OK';
        } else {
            $logMessage = date('Y-m-d H:i:s') . " - ECHEC : La requête de mise à jour a échoué.\n";
            file_put_contents($logFile, $logMessage, FILE_APPEND);
            http_response_code(500);
            die('DB Update failed');
        }
        
    } catch (PDOException $e) {
        $logMessage = date('Y-m-d H:i:s') . " - ECHEC : Erreur PDO : " . $e->getMessage() . "\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);
        http_response_code(500);
        die('DB Error');
    }
} else {
    $logMessage = date('Y-m-d H:i:s') . " - ECHEC : Données POST 'id' ou 'status' manquantes.\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    http_response_code(400);
    die('Missing parameters');
}
?>