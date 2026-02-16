<?php
// Script d'installation de la base de données
// À exécuter une fois pour initialiser ou mettre à jour la structure de la base de données.

require_once 'config.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connexion à la base de données réussie.\n";
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage() . "\nAssurez-vous que la base de données '$dbname' existe.\n");
}

// 1. Table users
echo "Vérification de la table 'users'...\n";
$pdo->exec("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    notification_email VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Migration : notification_email (si la table existait déjà sans cette colonne)
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN notification_email VARCHAR(255) NULL");
    echo " -> Colonne 'notification_email' ajoutée.\n";
} catch (PDOException $e) {
    // La colonne existe probablement déjà
}

// 2. Table monitored_urls
echo "Vérification de la table 'monitored_urls'...\n";
$pdo->exec("CREATE TABLE IF NOT EXISTS monitored_urls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    url VARCHAR(255) NOT NULL,
    last_status ENUM('available', 'out_of_stock', 'error', 'unknown') DEFAULT 'unknown',
    last_check TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

// Migration : user_id (si la table existait déjà sans cette colonne)
try {
    $pdo->exec("ALTER TABLE monitored_urls ADD COLUMN user_id INT NOT NULL DEFAULT 1");
    echo " -> Colonne 'user_id' ajoutée.\n";
} catch (PDOException $e) {
    // La colonne existe probablement déjà
}

// 3. Utilisateur par défaut
$stmt = $pdo->query("SELECT COUNT(*) FROM users");
if ($stmt->fetchColumn() == 0) {
    $defaultEmail = 'admin@example.com';
    $defaultPass = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
    $stmt->execute([$defaultEmail, $defaultPass]);
    echo "Utilisateur par défaut créé (admin@example.com / admin123).\n";
}

echo "Installation / Mise à jour terminée avec succès.\n";
?>