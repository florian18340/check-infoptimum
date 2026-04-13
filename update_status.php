<?php
require_once 'config.php';
require_once 'models/MonitoredUrl.php';

$secret_key_for_workers = $worker_secret_key ?? 'change-this-default-key';

if (($_POST['secret'] ?? '') !== $secret_key_for_workers) {
    http_response_code(403);
    die('Authentication failed');
}

if (isset($_POST['id']) && isset($_POST['status'])) {
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $urlModel = new MonitoredUrl($pdo);
        $urlModel->updateStatus($_POST['id'], $_POST['status']);
        
        echo 'OK';
    } catch (PDOException $e) {
        http_response_code(500);
        die('DB Error');
    }
}
?>