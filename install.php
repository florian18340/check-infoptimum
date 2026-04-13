<?php
require_once 'config.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<h3>Connexion à la base de données réussie.</h3>";
} catch (PDOException $e) {
    die("<h3 style='color:red;'>Erreur de connexion : " . $e->getMessage() . "</h3>");
}

function executeQuery($pdo, $sql, $description) {
    try {
        $pdo->exec($sql);
        echo "<p style='color:green;'>SUCCESS : $description</p>";
    } catch (PDOException $e) {
        echo "<p style='color:red;'>ERROR : $description<br>Détail : " . $e->getMessage() . "</p>";
    }
}

// Suppression des tables inutiles
executeQuery($pdo, "DROP TABLE IF EXISTS order_history", "Suppression table 'order_history'");
executeQuery($pdo, "DROP TABLE IF EXISTS infoptimum_accounts", "Suppression table 'infoptimum_accounts'");

// 1. Table users
executeQuery($pdo, "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    notification_email VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)", "Création table 'users'");

// 2. Table monitored_urls
executeQuery($pdo, "CREATE TABLE IF NOT EXISTS monitored_urls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    url VARCHAR(255) NOT NULL,
    last_status ENUM('available', 'out_of_stock', 'error', 'unknown') DEFAULT 'unknown',
    last_check TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)", "Création table 'monitored_urls'");

// Migrations de colonnes
echo "<h4>Vérification des migrations...</h4>";
executeQuery($pdo, "ALTER TABLE users ADD COLUMN IF NOT EXISTS notification_email VARCHAR(255) NULL", "Migration 'notification_email'");
executeQuery($pdo, "ALTER TABLE monitored_urls ADD COLUMN IF NOT EXISTS user_id INT NOT NULL DEFAULT 1", "Migration 'user_id'");

// Ajout des clés étrangères
echo "<h4>Vérification des clés étrangères...</h4>";
try {
    $pdo->exec("ALTER TABLE monitored_urls ADD CONSTRAINT fk_url_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE");
    echo "<p style='color:green;'>Clé étrangère fk_url_user ajoutée.</p>";
} catch (Exception $e) { echo "<p>Clé fk_url_user déjà présente ou ignorée.</p>"; }

// Utilisateur par défaut
$stmt = $pdo->query("SELECT COUNT(*) FROM users");
if ($stmt->fetchColumn() == 0) {
    $pdo->prepare("INSERT INTO users (email, password) VALUES (?, ?)")->execute(['admin@example.com', password_hash('admin123', PASSWORD_DEFAULT)]);
    echo "<p>Utilisateur admin par défaut créé.</p>";
}

echo "<h3>Installation terminée.</h3>";
?>