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
    user_id INT NOT NULL,
    url VARCHAR(255) NOT NULL,
    last_status ENUM('available', 'out_of_stock', 'error', 'unknown') DEFAULT 'unknown',
    last_check TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

// Migration : Ajouter la colonne user_id si elle n'existe pas (pour les anciennes installations)
try {
    $pdo->exec("ALTER TABLE monitored_urls ADD COLUMN user_id INT NOT NULL DEFAULT 1");
    // On suppose que l'ID 1 est l'admin par défaut créé précédemment
} catch (PDOException $e) {
    // La colonne existe probablement déjà, on ignore
}


// Création d'un utilisateur par défaut si aucun n'existe
$stmt = $pdo->query("SELECT COUNT(*) FROM users");
if ($stmt->fetchColumn() == 0) {
    $defaultEmail = 'admin@example.com';
    $defaultPass = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
    $stmt->execute([$defaultEmail, $defaultPass]);
}

$action = $_GET['action'] ?? '';

// Gestion de l'inscription (Public)
if ($action === 'register') {
    $data = json_decode(file_get_contents('php://input'), true);
    $email = $data['email'] ?? '';
    $pass = $data['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Email invalide']);
        exit;
    }

    if (strlen($pass) < 6) {
        echo json_encode(['success' => false, 'message' => 'Le mot de passe doit faire au moins 6 caractères']);
        exit;
    }

    // Vérifier si l'email existe déjà
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Cet email est déjà utilisé']);
        exit;
    }

    // Création du compte
    $hashedPass = password_hash($pass, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
    if ($stmt->execute([$email, $hashedPass])) {
        // Connexion automatique après inscription
        $_SESSION['user_id'] = $pdo->lastInsertId();
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'inscription']);
    }
    exit;
}

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
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

$userId = $_SESSION['user_id'];

if ($action === 'list') {
    // On ne récupère que les URLs de l'utilisateur connecté
    $stmt = $pdo->prepare("SELECT * FROM monitored_urls WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$userId]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

if ($action === 'add') {
    $data = json_decode(file_get_contents('php://input'), true);
    $url = $data['url'] ?? '';
    
    if (filter_var($url, FILTER_VALIDATE_URL)) {
        $stmt = $pdo->prepare("INSERT INTO monitored_urls (user_id, url) VALUES (?, ?)");
        $stmt->execute([$userId, $url]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'URL invalide']);
    }
    exit;
}

if ($action === 'delete') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? 0;
    
    // On vérifie que l'URL appartient bien à l'utilisateur
    $stmt = $pdo->prepare("DELETE FROM monitored_urls WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $userId]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Impossible de supprimer cet élément']);
    }
    exit;
}

if ($action === 'check_all') {
    // On vérifie uniquement les URLs de l'utilisateur connecté
    $stmt = $pdo->prepare("SELECT * FROM monitored_urls WHERE user_id = ?");
    $stmt->execute([$userId]);
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