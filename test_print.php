<?php
$loginUrl = "https://www.bourges.infoptimum.com/identifiez-vous.php";

echo "<pre>";
echo "--- TEST WAF (INFOPTIMUM) AVEC PROXY OXYLABS / BRIGHTDATA (SI BESOIN) ---\n\n";

// Le pare-feu d'Infoptimum (WAF) bloque l'accès car l'IP du serveur (minibudget.fr)
// ou l'IP de Webshare (qui est un proxy de datacenter très connu) sont sur liste noire.

// Un test simple sans proxy avec le mode furtif pour confirmer le blocage de l'IP du serveur :
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $loginUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

if (defined('CURL_HTTP_VERSION_2_0')) {
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2_0);
}

curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36');

$headers = [
    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
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
curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate, br');

echo "Tentative de connexion en DIRECT (sans proxy)...\n";
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Code HTTP : $httpCode\n\n";

if ($httpCode == 200 && !empty($response)) {
    echo "SUCCÈS ! La connexion directe fonctionne.\n";
} else {
    echo "ÉCHEC en connexion directe. Le serveur d'Infoptimum bloque cette IP.\n";
}

echo "\n--- CONCLUSION ---\n";
echo "L'erreur 403 signifie que le WAF d'Infoptimum bloque activement les requêtes provenant de proxies Datacenter (comme Webshare) et l'IP de votre serveur web.\n\n";
echo "POUR RÉSOUDRE CELA, VOUS DEVEZ :\n";
echo "1. Utiliser des Proxies Résidentiels (IP de box internet réelles, ex: Smartproxy, BrightData) au lieu de proxies Datacenter.\n";
echo "2. Ou, encore mieux et gratuit : Héberger ce script PHP directement chez vous sur un ordinateur ou un Raspberry Pi (ou une VM sur votre Freebox Delta) pour utiliser votre propre IP résidentielle.\n";

echo "</pre>";
?>