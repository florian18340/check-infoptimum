<?php
$url = "https://www.bourges.infoptimum.com/vente-privee-carte-cadeau-intermarchedune-valeur-de-50e--5555.html";
$cookieFile = __DIR__ . '/cookies.txt';

echo "--- TEST DE CONNEXION AVANCÉ (COOKIES + REFERER) ---\n";
echo "URL : $url\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

// Gestion des cookies
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);

$headers = [
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
    'Accept-Language: fr-FR,fr;q=0.9,en-US;q=0.8,en;q=0.7',
    'Referer: https://www.bourges.infoptimum.com/',
    'Connection: keep-alive',
    'Upgrade-Insecure-Requests: 1'
];
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_ENCODING, ''); // Gère la compression automatiquement

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "Code HTTP : " . $httpCode . "\n";
if ($error) echo "Erreur cURL : " . $error . "\n";

echo "\n--- RÉPONSE DU SERVEUR (Extrait) ---\n";
echo htmlspecialchars(substr($response, 0, 1500));
echo "\n--- FIN ---\n";
?>