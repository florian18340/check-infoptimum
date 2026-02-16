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

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Cet email est déjà utilisé']);
        exit;
    }

    $hashedPass = password_hash($pass, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
    if ($stmt->execute([$email, $hashedPass])) {
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
    $stmt = $pdo->prepare("SELECT m.*, u.email FROM monitored_urls m JOIN users u ON m.user_id = u.id WHERE m.user_id = ?");
    $stmt->execute([$userId]);
    $urls = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($urls as $item) {
        $newStatus = checkStock($item['url']);
        
        if ($newStatus === 'available' && $item['last_status'] !== 'available') {
            sendSmtpEmail($item['email'], "Stock Disponible ! - Infoptimum", "Le produit est disponible : " . $item['url']);
        }

        $updateStmt = $pdo->prepare("UPDATE monitored_urls SET last_status = ?, last_check = NOW() WHERE id = ?");
        $updateStmt->execute([$newStatus, $item['id']]);
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
    
    // Critère 1 : Présence de l'image spécifique "vp-imprime-coupon.png"
    if (stripos($html, 'images/vp-imprime-coupon.png') !== false) {
        return 'available';
    }

    // Critère 2 : Analyse du nombre d'offres restantes
    // Phrase cible : "Nombre d'offre(s) restante(s)" suivi du nombre
    // On utilise une regex qui cherche la phrase, ignore les caractères non-numériques (comme les espaces ou ":")
    // et capture le premier nombre qui suit.
    if (preg_match('/Nombre d\'offre\(s\) restante\(s\)[^0-9]*(\d+)/i', $html, $matches)) {
        if (intval($matches[1]) > 0) {
            return 'available';
        } else {
            return 'out_of_stock';
        }
    }

    // Fallback : Anciens critères (au cas où la structure change légèrement)
    if (stripos($html, 'id="produit-epuise"') !== false || 
        stripos($html, 'Victime de son succès') !== false || 
        stripos($html, 'Epuisé') !== false) {
        return 'out_of_stock';
    }
    
    if (stripos($html, 'id="form_add_cart"') !== false || 
        stripos($html, 'Ajouter au panier') !== false) {
        return 'available';
    }
    
    return 'unknown';
}

function sendSmtpEmail($to, $subject, $body) {
    global $smtp_host, $smtp_port, $smtp_user, $smtp_pass, $smtp_from, $smtp_secure;

    if (empty($smtp_host)) {
        mail($to, $subject, $body, "From: $smtp_from");
        return;
    }

    try {
        $socket = fsockopen(($smtp_secure === 'ssl' ? 'ssl://' : '') . $smtp_host, $smtp_port, $errno, $errstr, 10);
        if (!$socket) {
            error_log("SMTP Connect Error: $errstr ($errno)");
            return;
        }

        // Fonction pour lire la réponse du serveur
        $read = function($socket) {
            $response = '';
            while ($str = fgets($socket, 515)) {
                $response .= $str;
                if (substr($str, 3, 1) == ' ') break;
            }
            return $response;
        };

        $read($socket); // Banner

        fputs($socket, "EHLO " . $_SERVER['SERVER_NAME'] . "\r\n");
        $read($socket);

        if ($smtp_secure === 'tls') {
            fputs($socket, "STARTTLS\r\n");
            $read($socket);
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            fputs($socket, "EHLO " . $_SERVER['SERVER_NAME'] . "\r\n");
            $read($socket);
        }

        if (!empty($smtp_user) && !empty($smtp_pass)) {
            fputs($socket, "AUTH LOGIN\r\n");
            $read($socket);
            fputs($socket, base64_encode($smtp_user) . "\r\n");
            $read($socket);
            fputs($socket, base64_encode($smtp_pass) . "\r\n");
            $read($socket);
        }

        fputs($socket, "MAIL FROM: <$smtp_from>\r\n");
        $read($socket);
        fputs($socket, "RCPT TO: <$to>\r\n");
        $read($socket);
        fputs($socket, "DATA\r\n");
        $read($socket);

        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/plain; charset=utf-8\r\n";
        $headers .= "From: $smtp_from\r\n";
        $headers .= "To: $to\r\n";
        $headers .= "Subject: $subject\r\n";

        fputs($socket, "$headers\r\n$body\r\n.\r\n");
        $read($socket);
        
        fputs($socket, "QUIT\r\n");
        fclose($socket);

    } catch (Exception $e) {
        error_log("SMTP Error: " . $e->getMessage());
    }
}
?>