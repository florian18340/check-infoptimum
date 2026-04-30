<?php
// Active l'affichage des erreurs pour le débogage
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/models/MonitoredUrl.php';

$secret_key_for_workers = $worker_secret_key ?? 'change-this-default-key';

// CORRECTION : On lit les paramètres depuis $_GET
if (($_GET['secret'] ?? '') !== $secret_key_for_workers) {
    http_response_code(403);
    die('Authentication failed');
}

if (isset($_GET['id']) && isset($_GET['status'])) {
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $urlModel = new MonitoredUrl($pdo);
        $success = $urlModel->updateStatus($_GET['id'], $_GET['status']);
        
        if ($success) {
            echo 'OK';
        } else {
            http_response_code(500);
            die('DB Update failed');
        }
        
    } catch (PDOException $e) {
        http_response_code(500);
        die('DB Error: ' . $e->getMessage());
    }
} else {
    http_response_code(400);
    die('Missing parameters');
}
?>