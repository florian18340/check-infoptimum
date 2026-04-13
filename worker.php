<?php
// Script à installer sur les serveurs distants
// --- Worker Script Autonome ---
// Ce fichier est le seul à déployer sur vos serveurs secondaires.

// --- CONFIGURATION DU WORKER ---
$main_server_url = 'https://infoptimum.minibudget.fr'; 
$secret_key = 'QtCw5dXV47sf8VUx3WyqCrL558yxnv9kDthP39T86PZDG7k486'; 
// --- FIN CONFIGURATION ---

// --- CLASSE STOCKCHECKER INTÉGRÉE ---
class StockChecker {
    private function log($message) {
        // Le worker n'a pas besoin de logger sur le disque, il affiche à l'écran.
        // On pourrait aussi envoyer les logs au serveur principal si besoin.
    }

    public function check($url) {
        $html = @file_get_contents(trim($url));

        if ($html === false) {
            return 'error';
        }

        if (preg_match('/<span[^>]*class=["\']s24["\'][^>]*>.*?(\d+).*?<\/span>/is', $html, $matches)) {
            $stock = intval($matches[1]);
            return ($stock > 0) ? 'available' : 'out_of_stock';
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


// 1. Récupérer la liste des URLs à vérifier
$api_url = $main_server_url . '/worker_api.php?secret=' . urlencode($secret_key);
$urls_to_check_json = @file_get_contents($api_url);

if ($urls_to_check_json === false) {
    die("Erreur : Impossible de contacter l'API du serveur principal.");
}

$urls_to_check = json_decode($urls_to_check_json, true);

if (!is_array($urls_to_check) || isset($urls_to_check['error'])) {
    die("Erreur : Réponse de l'API invalide. Message : " . ($urls_to_check['error'] ?? 'inconnu'));
}

echo "Liste de " . count($urls_to_check) . " URLs récupérée.\n";

$checker = new StockChecker();

foreach ($urls_to_check as $url_info) {
    $url = $url_info['url'];
    $new_status = $checker->check($url);
    
    echo "Vérification de $url ... Statut : $new_status\n";

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
    
    sleep(rand(5, 10));
}

echo "Travail terminé.\n";
?>