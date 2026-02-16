<?php
session_start();
header('Content-Type: application/json');

require_once 'config.php';
require_once 'models/User.php';
require_once 'models/MonitoredUrl.php';
require_once 'services/StockChecker.php';
require_once 'services/EmailService.php';
require_once 'controllers/ApiController.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die(json_encode(['error' => 'Erreur de connexion à la base de données: ' . $e->getMessage()]));
}

// Configuration pour le service d'email
$emailConfig = [
    'smtp_host' => $smtp_host ?? '',
    'smtp_port' => $smtp_port ?? 587,
    'smtp_user' => $smtp_user ?? '',
    'smtp_pass' => $smtp_pass ?? '',
    'smtp_from' => $smtp_from ?? 'no-reply@check-infoptimum.local',
    'smtp_secure' => $smtp_secure ?? 'tls'
];

// Initialisation du contrôleur
$controller = new ApiController($pdo, $emailConfig);
$controller->handleRequest();
?>