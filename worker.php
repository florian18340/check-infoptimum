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

function curl_request($url, $post_data = null, $cookie_file) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:122.0) Gecko/20100101 Firefox/122.0');
    
    // Gestion des cookies pour la session
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);

    if ($post_data !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
    }

    $response = curl_exec($ch);
    $error = curl_error($ch);
    
    if ($error) {
        curl_close($ch);
        die("Erreur cURL : " . $error);
    }
    curl_close($ch);
    return $response;
}

$cookieFile = __DIR__ . '/worker_cookie.txt';
if (file_exists($cookieFile)) {
    unlink($cookieFile);
}

sleep(rand(1, 5));

// 1. Faire une requête initiale pour obtenir les cookies de session/protection
curl_request($main_server_url, null, $cookieFile);

// 2. Récupérer la liste des URLs à vérifier avec cURL et les cookies
$api_url = $main_server_url . '/worker_api.php?secret=' . urlencode($secret_key);
$urls_to_check_json = curl_request($api_url, null, $cookieFile);

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

    echo " -> Notification du serveur principal...\n";
    
    $update_url = $main_server_url . '/update_status.php';
    $post_data = [
        'secret' => $secret_key,
        'id' => $url_info['id'],
        'status' => $new_status
    ];
    
    $result = curl_request($update_url, $post_data, $cookieFile);
    
    if (trim($result) !== 'OK') {
        echo "   -> ECHEC de la notification. Réponse du serveur : " . ($result ?: '[vide]') . "\n";
    } else {
        echo "   -> SUCCES de la notification.\n";
    }
    
    sleep(rand(15, 30));
}

echo "Travail terminé.\n";
unlink($cookieFile); // Nettoyer le fichier de cookie à la fin
?>