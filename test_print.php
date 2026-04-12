<?php
$loginUrl = "https://www.bourges.infoptimum.com/identifiez-vous.php";

echo "<pre>";
echo "--- TEST DE PROXY WEBSHARE (TENTATIVE WAF BYPASS) ---\n\n";

$proxy_host = 'p.webshare.io'; 
$proxy_port = '80'; 
$proxy_user = 'uoyujbsn-rotate'; 
$proxy_pass = 'jtprxdma17l9'; 

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $loginUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

// Tenter de forcer HTTP/2 pour paraître plus moderne
if (defined('CURL_HTTP_VERSION_2_0')) {
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2_0);
}

// User-Agent très commun
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36');

// --- CONFIGURATION DU PROXY ---
curl_setopt($ch, CURLOPT_PROXY, "http://$proxy_host:$proxy_port");
curl_setopt($ch, CURLOPT_PROXYUSERPWD, "$proxy_user:$proxy_pass");
curl_setopt($ch, CURLOPT_PROXYAUTH, CURLAUTH_BASIC);

// En-têtes strictement identiques à un vrai navigateur (évite la détection de cURL)
$headers = [
    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
    'Accept-Language: fr-FR,fr;q=0.9,en-US;q=0.8,en;q=0.7',
    'Cache-Control: max-age=0',
    'Sec-Ch-Ua: "Not A(Brand";v="99", "Google Chrome";v="121", "Chromium";v="121"',
    'Sec-Ch-Ua-Mobile: ?0',
    'Sec-Ch-Ua-Platform: "Windows"',
    'Sec-Fetch-Dest: document',
    'Sec-Fetch-Mode: navigate',
    'Sec-Fetch-Site: none',
    'Sec-Fetch-User: ?1',
    'Upgrade-Insecure-Requests: 1'
];

curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

// Au lieu de l'encodage automatique vide, on demande explicitement les formats modernes
curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate, br');

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
    echo "ERREUR 407 : Authentification Proxy requise.\n";
} else {
    echo "ÉCHEC. Le code HTTP est $httpCode.\n";
    echo "Extrait de la réponse : \n" . htmlspecialchars(substr($response, 0, 500));
}

echo "</pre>";
?>