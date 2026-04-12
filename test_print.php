<?php
$loginUrl = "https://www.bourges.infoptimum.com/identifiez-vous.php";

echo "<pre>";
echo "--- TEST DE PROXY WEBSHARE (IP DIRECTE) ---\n\n";

// Si le nom d'hôte pose problème ou si Webshare exige une authentification par IP (et non par mot de passe)
// Connectez-vous à votre dashboard Webshare, et essayez de trouver une adresse IP directe (ex: 185.199.229.156)
// ou le port alternatif (souvent 80). Si vous avez autorisé votre IP serveur, le password n'est parfois plus nécessaire.

$proxy_host = 'p.webshare.io'; 
$proxy_port = '80'; 
$proxy_user = 'uoyujbsn'; 
$proxy_pass = 'jtprxdma17l9'; 

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $loginUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');

// --- CONFIGURATION DU PROXY (Méthode complète) ---
curl_setopt($ch, CURLOPT_PROXY, "http://$proxy_host:$proxy_port");
curl_setopt($ch, CURLOPT_PROXYUSERPWD, "$proxy_user:$proxy_pass");
curl_setopt($ch, CURLOPT_PROXYAUTH, CURLAUTH_BASIC);

curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
    'Accept-Language: fr-FR,fr;q=0.9,en-US;q=0.8,en;q=0.7',
    'Connection: keep-alive',
    'Upgrade-Insecure-Requests: 1'
]);

echo "Tentative de connexion à Infoptimum via le proxy (Port $proxy_port)...\n";

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "Code HTTP GET : $httpCode\n\n";

if ($error) {
    echo "ERREUR cURL avec le proxy : $error\n";
}

if ($httpCode == 200 && !empty($response)) {
    echo "SUCCÈS ! Le proxy a permis de contourner le blocage 403.\n\n";
    if (preg_match('/<title>(.*?)<\/title>/is', $response, $title)) {
        echo "Titre de la page chargée : " . htmlspecialchars(trim($title[1])) . "\n";
    }
} elseif ($httpCode == 407) {
    echo "ERREUR 407 : Authentification Proxy requise. Le serveur proxy a rejeté l'identifiant/mot de passe.\n";
} else {
    echo "ÉCHEC. Le proxy n'a pas résolu le problème ou est mal configuré.\n";
    echo "Extrait de la réponse : \n" . htmlspecialchars(substr($response, 0, 500));
}

echo "</pre>";
?>