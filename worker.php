<?php
// --- Worker Script ---
// Ce fichier est le seul à déployer sur vos serveurs secondaires.

// --- CONFIGURATION DU WORKER ---
// URL du serveur principal où se trouve la base de données et l'API
$main_server_url = 'https://infoptimum.minibudget.fr'; 
// La clé secrète que vous avez définie dans le config.php du serveur principal
$secret_key = 'y7z?ChmzK%Z3QHD]/csZ~45U5e+7{pkG:^@aa322#H752CjR2-';
// --- FIN CONFIGURATION ---

require_once 'services/StockChecker.php';

// 1. Récupérer la liste des URLs à vérifier depuis l'API du serveur principal
$api_url = $main_server_url . '/worker_api.php?secret=' . $secret_key;
$urls_to_check_json = @file_get_contents($api_url);

if ($urls_to_check_json === false) {
    die("Erreur : Impossible de contacter l'API du serveur principal à l'adresse : $api_url");
}

$urls_to_check = json_decode($urls_to_check_json, true);

if (!is_array($urls_to_check)) {
    die("Erreur : La réponse de l'API n'est pas un JSON valide.");
}

echo "Liste de " . count($urls_to_check) . " URLs récupérée depuis le serveur principal.\n";

$checker = new StockChecker();

foreach ($urls_to_check as $url_info) {
    $url = $url_info['url'];
    $new_status = $checker->check($url);
    
    echo "Vérification de $url ... Statut : $new_status\n";

    // Si le statut a changé, on notifie le serveur principal pour qu'il mette à jour la DB
    if ($new_status !== $url_info['last_status']) {
        echo " -> Statut changé ! Notification du serveur principal...\n";
        
        $update_url = $main_server_url . '/update_status.php';
        $options = [
            'http' => [
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query([
                    'secret' => $secret_key,
                    'id' => $url_info['id'],
                    'status' => $new_status
                ])
            ]
        ];
        $context  = stream_context_create($options);
        file_get_contents($update_url, false, $context);
    }
    
    // Pause pour ne pas surcharger
    sleep(rand(5, 10));
}

echo "Travail terminé.\n";
?>