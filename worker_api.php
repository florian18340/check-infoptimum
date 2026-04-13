<?php
header('Content-Type: application/json');
require_once 'config.php';

// Clé secrète pour s'assurer que seuls vos workers peuvent demander la liste
// IMPORTANT : Changez cette clé dans votre vrai config.php
$secret_key_for_workers = $worker_secret_key ?? 'QtCw5dXV47sf8VUx3WyqCrL558yxnv9kDthP39T86PZDG7k486';

if (($_GET['secret'] ?? '') !== $secret_key_for_workers) {
    http_response_code(403);
    die(json_encode(['error' => 'Authentication failed']));
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(['error' => 'DB Connection failed']));
}

// On fournit aux workers toutes les URLs avec leur ID et leur dernier statut connu
$stmt = $pdo->query("SELECT id, url, last_status FROM monitored_urls");
$urls = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($urls);
?>