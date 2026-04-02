<?php
$url = "https://www.bourges.infoptimum.com/vente-privee-carte-cadeau-intermarchedune-valeur-de-50e--5555.html";

echo "--- TEST DE CONNEXION VERS INFOPTIMUM ---\n";
echo "URL : $url\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_VERBOSE, true); // Mode verbeux pour voir les détails
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$headers = [
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
    'Accept-Language: fr-FR,fr;q=0.9',
    'Connection: keep-alive'
];
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "\nCode HTTP : " . $httpCode . "\n";
if ($error) echo "Erreur cURL : " . $error . "\n";

echo "\n--- DEBUT DE LA REPONSE DU SERVEUR ---\n";
echo substr($response, 0, 1000); // Affiche les 1000 premiers caractères
echo "\n--- FIN DE LA REPONSE ---\n";
?>