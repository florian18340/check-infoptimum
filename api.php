<?php
session_start();
header('Content-Type: application/json');

// Chargement de la configuration
require_once 'config.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die(json_encode(['error' => 'Erreur de connexion à la base de données: ' . $e->getMessage()]));
}

// Création des tables
$pdo->exec("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS monitored_urls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    url VARCHAR(255) NOT NULL,
    last_status ENUM('available', 'out_of_stock', 'error', 'unknown') DEFAULT 'unknown',
    last_check TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Création d'un utilisateur par défaut si aucun n'existe
$stmt = $pdo->query("SELECT COUNT(*) FROM users");
if ($stmt->fetchColumn() == 0) {
    $defaultEmail = 'admin@example.com';
    $defaultPass = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
    $stmt->execute([$defaultEmail, $defaultPass]);
}

$action = $_GET['action'] ?? '';

// Gestion du Login (Public)
if ($action === 'login') {
    $data = json_decode(file_get_contents('php://input'), true);
    $email = $data['email'] ?? '';
    $pass = $data['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($pass, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Identifiants incorrects']);
    }
    exit;
}

// Gestion du Logout (Public)
if ($action === 'logout') {
    session_destroy();
    echo json_encode(['success' => true]);
    exit;
}

// Vérification de l'authentification pour toutes les autres actions
// Exception pour le cron si on veut l'appeler en ligne de commande ou via un token secret (ici simplifié)
// Pour l'instant, on protège tout le reste par session.
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

if ($action === 'list') {
    $stmt = $pdo->query("SELECT * FROM monitored_urls ORDER BY created_at DESC");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

if ($action === 'add') {
    $data = json_decode(file_get_contents('php://input'), true);
    $url = $data['url'] ?? '';
    
    if (filter_var($url, FILTER_VALIDATE_URL)) {
        $stmt = $pdo->prepare("INSERT INTO monitored_urls (url) VALUES (?)");
        $stmt->execute([$url]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'URL invalide']);
    }
    exit;
}

if ($action === 'delete') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? 0;
    
    $stmt = $pdo->prepare("DELETE FROM monitored_urls WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'check_all') {
    $stmt = $pdo->query("SELECT * FROM monitored_urls");
    $urls = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($urls as $item) {
        $status = checkStock($item['url']);
        $updateStmt = $pdo->prepare("UPDATE monitored_urls SET last_status = ?, last_check = NOW() WHERE id = ?");
        $updateStmt->execute([$status, $item['id']]);
    }
    
    echo json_encode(['success' => true]);
    exit;
}

function checkStock($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
    
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode != 200 || !$html) {
        return 'error';
    }
    
    if (stripos($html, 'Epuisé') !== false || 
        stripos($html, 'Rupture de stock') !== false ||
        stripos($html, 'Victime de son succès') !== false) {
        return 'out_of_stock';
    }
    
    if (stripos($html, 'Ajouter au panier') !== false || 
        stripos($html, 'Commander') !== false) {
        return 'available';
    }
    
    return 'unknown';
}
?>