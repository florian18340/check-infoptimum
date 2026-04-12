<?php
require_once 'config.php';

$url = "https://www.bourges.infoptimum.com/vente-privee-8-seances-de-1h30-de-preparation-mentale-7054.html";
$loginUrl = "https://www.bourges.infoptimum.com/identifiez-vous2.php"; 
$refererUrl = "https://www.bourges.infoptimum.com/identifiez-vous.php";
$cookieFile = __DIR__ . '/test_cookies.txt';

echo "--- TEST D'IMPRESSION (AVEC TOKEN) ---\n<br>\n";

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
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['email' => $infoptimum_email, 'mdp' => $infoptimum_pass, 'action' => 'ident'])); 
curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($headers, ['Content-Type: application/x-www-form-urlencoded', 'Referer: ' . $refererUrl, 'Origin: https://www.bourges.infoptimum.com']));
$response = curl_exec($ch);
$effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL); 

if (stripos($effectiveUrl, 'mon-compte.php') === false) {
    echo "-> ÉCHEC de la connexion.\n<br>\n";
    die();
}
echo "-> Connexion RÉUSSIE !\n<br>\n<br>\n";

// --- ETAPE 2: Charger la page de la vente pour trouver le token ---
echo "2. Accès à la page de la vente pour trouver le token...\n<br>\n";
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
$venteHtml = curl_exec($ch);

$impressionUrl = '';
$token = '';

if (preg_match('/<form[^>]*action=["\']([^"\']*(?:vente-privee-impression|panier)[^"\']*)["\'][^>]*>(.*?)<\/form>/is', $venteHtml, $formMatch)) {
    $impressionUrl = $formMatch[1];
    
    if (preg_match('/<input[^>]*type=["\']hidden["\'][^>]*name=["\']token["\'][^>]*value=["\'](.*?)["\'][^>]*>/i', $formMatch[2], $tokenMatch)) {
        $token = $tokenMatch[1];
        echo "-> Token trouvé : $token\n<br>\n";
    } else {
        echo "-> Formulaire trouvé, mais pas de champ 'token'.\n<br>\n";
    }
} else {
    die("Impossible de trouver le formulaire d'impression sur la page.");
}

// --- ETAPE 3: Simuler le clic avec le token ---
if (!empty($impressionUrl) && !empty($token)) {
    // L'URL d'impression est souvent relative, on la reconstruit
    $fullImpressionUrl = "https://www.bourges.infoptimum.com/" . ltrim($impressionUrl, '/');
    
    // On ajoute le token aux données à envoyer
    $postData = http_build_query(['token' => $token]);
    
    echo "3. Simulation du clic sur : $fullImpressionUrl avec le token...\n<br>\n";

    curl_setopt($ch, CURLOPT_URL, $fullImpressionUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($headers, ['Content-Type: application/x-www-form-urlencoded', 'Referer: ' . $url, 'Origin: https://www.bourges.infoptimum.com']));

    $printResponse = curl_exec($ch);
    $printHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    echo "Code HTTP de l'impression : $printHttpCode\n<br>\n";

    if ($printHttpCode == 200 || $printHttpCode == 302) {
        echo "<strong>-> Impression potentiellement VALIDÉE par le serveur !</strong>\n<br>\n";
    } else {
        echo "-> ÉCHEC de l'impression.\n<br>\n";
    }
} else {
    echo "-> ÉCHEC : Impossible de simuler le clic sans URL d'action ou token.\n<br>\n";
}

curl_close($ch);
?>