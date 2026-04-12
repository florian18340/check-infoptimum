<?php
// --- SCRIPT DE DEBUG POUR EXTRAIRE LE FORMULAIRE DE CONNEXION ---

$loginUrl = "https://www.bourges.infoptimum.com/identifiez-vous.php";

echo "<pre>";
echo "--- ANALYSE BRUTE DE LA PAGE DE CONNEXION ---\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $loginUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36');

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Code HTTP de la page de login : $httpCode\n\n";

if ($httpCode == 200) {
    echo "HTML de la page (pour analyse manuelle) :\n\n";
    echo htmlspecialchars($response);
} else {
    echo "Impossible de charger la page de connexion. Le serveur bloque peut-être l'IP (Code: $httpCode).";
}

echo "</pre>";
?>