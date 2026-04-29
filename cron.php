<?php
require_once 'config.php';
require_once 'models/Worker.php';

echo "Lancement du cron principal...\n";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur DB : " . $e->getMessage() . "\n");
}

$workerModel = new Worker($pdo);
$workers = $workerModel->findAll();

if (empty($workers)) {
    die("Aucun worker configuré dans la base de données. Le cron ne peut rien faire.\n");
}

// On choisit un worker au hasard dans la liste
$randomWorker = $workers[array_rand($workers)];
$workerUrl = $randomWorker['url'];

echo "Worker choisi au hasard : $workerUrl\n";

// On appelle le worker pour qu'il fasse le travail
$secret_key = $worker_secret_key ?? 'change-this-default-key';
$urlToCall = $workerUrl . '?secret=' . urlencode($secret_key);

echo "Appel du worker...\n";
$response = @file_get_contents($urlToCall);

if ($response === false) {
    echo "ERREUR : Impossible de contacter le worker à l'adresse : $urlToCall\n";
} else {
    echo "Réponse du worker :\n";
    echo $response;
}

echo "\nCron principal terminé.\n";
?>