<?php
require_once 'config.php';

$url = "https://www.bourges.infoptimum.com/vente-privee-8-seances-de-1h30-de-preparation-mentale-7054.html";
$loginUrl = "https://www.bourges.infoptimum.com/identifiez-vous2.php"; 
$refererUrl = "https://www.bourges.infoptimum.com/identifiez-vous.php";
$cookieFile = __DIR__ . '/test_cookies.txt';

echo "--- TEST D'IMPRESSION (LIEN DIRECT) ---\n<br>\n";

try {
    $dbHost = $host ?? 'localhost';
    $dbName = $dbname ?? 'infoptimum_stock';
    $dbUser = $username ?? 'root';
    $dbPass = $password ?? '';
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion DB : " . $e->getMessage() . "\n");
}

$stmt = $pdo->query("SELECT * FROM infoptimum_accounts LIMIT 1");
$account = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$account) {
    die("ERREUR : Aucun compte Infoptimum trouvé.\n");
}

$infoptimum_email = $account['email'];
$infoptimum_pass = $account['password'];

echo "1. Tentative de connexion avec le compte : $infoptimum_email\n<br>\n";

if (file_exists($cookieFile)) {
    unlink($cookieFile);
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); 
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_ENCODING, ''); 

$headers = [
    'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36',
    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
    'Accept-Language: fr-FR,fr;q=0.9,en-US;q=0.8,en;q=0.7',
    'Connection: keep-alive',
    'Upgrade-Insecure-Requests: 1'
];

// --- ETAPE 1: Connexion ---
curl_setopt($ch, CURLOPT_URL, $loginUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'email' => $infoptimum_email,
    'mdp' => $infoptimum_pass,
    'action' => 'ident',
    'submit' => 'Valider'
])); 
curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($headers, ['Content-Type: application/x-www-form-urlencoded', 'Referer: ' . $refererUrl, 'Origin: https://www.bourges.infoptimum.com']));
$response = curl_exec($ch);
$effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL); 

if (stripos($effectiveUrl, 'mon-compte.php') === false) {
    echo "-> ÉCHEC de la connexion.\n<br>\n";
    die();
}
echo "-> Connexion RÉUSSIE !\n<br>\n<br>\n";

// --- ETAPE 2: Extraire l'ID de la vente ---
$matches = [];
if (preg_match('/-(\d+)\.html$/', $url, $matches)) {
    $venteId = $matches[1];
    echo "2. ID de la vente extrait : $venteId\n<br>\n";
} else {
    die("Impossible d'extraire l'ID de la vente depuis l'URL.");
}

// --- ETAPE 3: Simuler le clic sur le lien d'impression ---
$impressionUrl = "https://www.bourges.infoptimum.com/vente-privee-impression.php?ID=" . $venteId;
echo "3. Simulation du clic sur : <a href='$impressionUrl' target='_blank'>$impressionUrl</a>\n<br>\n";

curl_setopt($ch, CURLOPT_URL, $impressionUrl);
curl_setopt($ch, CURLOPT_POST, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); // Headers GET normaux
$printResponse = curl_exec($ch);
$printHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

echo "Code HTTP de l'impression : $printHttpCode\n<br>\n";

// Sur une impression réussie, le site redirige souvent ou affiche une page de confirmation.
// Un code 200 ou 302 est un bon signe.
if ($printHttpCode == 200 || $printHttpCode == 302) {
    echo "<strong>-> Impression potentiellement VALIDÉE par le serveur !</strong>\n<br>\n";
    echo "Extrait de la page d'impression : <br>\n";
    echo "<pre>" . htmlspecialchars(substr($printResponse, 0, 500)) . "...</pre>";
} else {
    echo "-> ÉCHEC de l'impression.\n<br>\n";
}

curl_close($ch);
?>