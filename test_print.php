<?php
$loginUrl = "https://www.bourges.infoptimum.com/identifiez-vous.php";

echo "<pre>";
echo "--- TEST DE CONNEXION AVEC IP SIMULÉE ---\n\n";

function generateRandomIp() {
    return mt_rand(1, 255) . "." . mt_rand(1, 255) . "." . mt_rand(1, 255) . "." . mt_rand(1, 255);
}

$randomIp = generateRandomIp();
echo "IP simulée pour cette requête : $randomIp\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $loginUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36');

$headers = [
    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
    'Accept-Language: fr-FR,fr;q=0.9,en-US;q=0.8,en;q=0.7',
    'Connection: keep-alive',
    'Upgrade-Insecure-Requests: 1',
    // Ajout des en-têtes de simulation d'IP
    'X-Forwarded-For: ' . $randomIp,
    'X-Real-IP: ' . $randomIp
];

curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate, br');

echo "Tentative de connexion en DIRECT (avec IP simulée)...\n";
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Code HTTP : $httpCode\n\n";

if ($httpCode == 200 && !empty($response)) {
    echo "SUCCÈS ! La connexion directe fonctionne.\n";
} else {
    echo "ÉCHEC en connexion directe. Le serveur d'Infoptimum bloque l'IP source de ce serveur (minibudget.fr).\n";
    echo "L'en-tête X-Forwarded-For a été ignoré par le pare-feu.\n";
}

echo "</pre>";
?>