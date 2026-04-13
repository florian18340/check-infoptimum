<?php
// --- Worker Script Autonome ---
// Ce fichier est le seul à déployer sur vos serveurs secondaires.

// --- CONFIGURATION DU WORKER ---
$main_server_url = 'https://infoptimum.minibudget.fr'; 
$secret_key = 'QtCw5dXV47sf8VUx3WyqCrL558yxnv9kDthP39T86PZDG7k486'; 
// --- FIN CONFIGURATION ---

// --- CLASSE STOCKCHECKER INTÉGRÉE ---
class StockChecker {
    public function check($url) {
        $html = @file_get_contents(trim($url));
        if ($html === false) return 'error';

        if (preg_match('/<span[^>]*class=["\']s24["\'][^>]*>.*?(\d+).*?<\/span>/is', $html, $matches)) {
            return (intval($matches[1]) > 0) ? 'available' : 'out_of_stock';
        }
        if (stripos($html, 'Victime de son succès') !== false || stripos($html, 'id="produit-epuise"') !== false) {
            return 'out_of_stock';
        }
        if (stripos($html, 'images/vp-imprime-coupon.png') !== false || stripos($html, 'Imprimez votre coupon') !== false || stripos($html, 'Ajouter au panier') !== false) {
            return 'available';
        }
        return 'unknown';
    }
}
// --- FIN DE LA CLASSE ---

sleep(rand(1, 5));

$api_url = $main_server_url . '/worker_api.php?secret=' . urlencode($secret_key);
$urls_to_check_json = @file_get_contents($api_url);

if ($urls_to_check_json === false) {
    die("Erreur : Impossible de contacter l'API du serveur principal.");
}

$urls_to_check = json_decode($urls_to_check_json, true);

if (!is_array($urls_to_check) || isset($urls_to_check['error'])) {
    die("Erreur : Réponse de l'API invalide.");
}

echo "Liste de " . count($urls_to_check) . " URLs récupérée.\n";

$checker = new StockChecker();

foreach ($urls_to_check as $url_info) {
    $url = $url_info['url'];
    $new_status = $checker->check($url);
    
    echo "Vérification de $url ... Statut : $new_status\n";

    echo " -> Notification du serveur principal...\n";
    
    $update_url = $main_server_url . '/update_status.php';
    
    // CORRECTION : Ajout d'un User-Agent à la requête de mise à jour pour tromper le WAF
    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n" .
                         "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:122.0) Gecko/20100101 Firefox/122.0\r\n",
            'method'  => 'POST',
            'content' => http_build_query([
                'secret' => $secret_key,
                'id' => $url_info['id'],
                'status' => $new_status
            ])
        ]
    ];
    $context  = stream_context_create($options);
    $result = @file_get_contents($update_url, false, $context);
    
    if ($result === false) {
        echo "   -> ECHEC de la notification. Le serveur principal bloque peut-être la requête.\n";
    }

    sleep(rand(15, 30));
}

echo "Travail terminé.\n";
?>