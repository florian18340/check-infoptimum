<?php
// Active l'affichage des erreurs pour le débogage
error_reporting(E_ALL);
ini_set('display_errors', 1);

$logFile = __DIR__ . '/update.log';
@file_put_contents($logFile, "--- Log du " . date('Y-m-d H:i:s') . " ---\n", FILE_APPEND);

function write_log($message) {
    global $logFile;
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
}

write_log("Requête reçue.");

require_once __DIR__ . '/config.php';
write_log("config.php chargé.");

require_once __DIR__ . '/models/MonitoredUrl.php';
write_log("MonitoredUrl.php chargé.");

$secret_key_for_workers = $worker_secret_key ?? 'change-this-default-key';

if (($_POST['secret'] ?? '') !== $secret_key_for_workers) {
    write_log("ECHEC : Clé secrète invalide.");
    http_response_code(403);
    die('Authentication failed');
}
write_log("Clé secrète validée.");

if (isset($_POST['id']) && isset($_POST['status'])) {
    write_log("Données reçues : ID=" . $_POST['id'] . ", Statut=" . $_POST['status']);
    
    try {
        write_log("Tentative de connexion à la base de données...");
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        write_log("Connexion DB réussie.");
        
        write_log("Instanciation de MonitoredUrl...");
        $urlModel = new MonitoredUrl($pdo);
        write_log("Modèle instancié. Tentative de mise à jour...");
        
        $success = $urlModel->updateStatus($_POST['id'], $_POST['status']);
        
        if ($success) {
            write_log("SUCCES : La requête de mise à jour a été exécutée.");
            echo 'OK';
        } else {
            write_log("ECHEC : La méthode updateStatus a retourné false.");
            http_response_code(500);
            die('DB Update failed');
        }
        
    } catch (PDOException $e) {
        write_log("ECHEC : Erreur PDO : " . $e->getMessage());
        http_response_code(500);
        die('DB Error');
    }
} else {
    write_log("ECHEC : Données POST 'id' ou 'status' manquantes.");
    http_response_code(400);
    die('Missing parameters');
}
?>