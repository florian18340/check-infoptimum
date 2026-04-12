<?php
require_once 'config.php';

$url = "https://www.bourges.infoptimum.com/vente-privee-spray-nettoyant-17ml-rechargeable-pour-lunettes-7196.html";
$loginUrl = "https://www.bourges.infoptimum.com/identifiez-vous.php";
$cookieFile = __DIR__ . '/test_cookies.txt';

echo "--- TEST D'IMPRESSION (SANS PROXY - SIMULATION MOZILLA CLASSIQUE) ---\n";

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

$stmt = $pdo->query("SELECT * FROM infoptimum_accounts ORDER BY RAND LIMIT 1");
$account = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$account) {
    die("ERREUR : Aucun compte Infoptimum trouvé.\n");
}

$infoptimum_email = $account['email'];
$infoptimum_pass = $account['password'];

echo "1. Tentative de connexion avec le compte : $infoptimum_email\n";

if (file_exists($cookieFile)) {
    unlink($cookieFile);
}

// Les en-têtes trop "complets" peuvent déclencher certains WAF s'ils ne correspondent pas
// exactement à l'empreinte TLS de cURL. On repasse sur quelque chose de plus basique.
$headers = [
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:122.0) Gecko/20100101 Firefox/122.0',
    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
    'Accept-Language: fr,fr-FR;q=0.8,en-US;q=0.5,en;q=0.3',
    'Connection: keep-alive',
    'Upgrade-Insecure-Requests: 1',
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_ENCODING, ''); // Accepter la compression

// 1. GET sur la page de connexion
echo "1. GET sur la page de connexion...\n";
curl_setopt($ch, CURLOPT_URL, $loginUrl);
curl_setopt($ch, CURLOPT_POST, false);
$html = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($httpCode == 403) {
     echo "-> ECHEC DES LE GET ! Le serveur bloque cette requete HTTP. L'IP est bannie ou le WAF bloque cURL.\n";
     die();
}

$postData = [
    'email' => $infoptimum_email,
    'pass' => $infoptimum_pass,
    'action' => 'ident',
    'submit' => 'Valider'
];

if (preg_match('/<form[^>]*>(.*?)<\/form>/is', $html, $formMatch)) {
    if (preg_match_all('/<input[^>]*type=["\']hidden["\'][^>]*name=["\'](.*?)["\'][^>]*value=["\'](.*?)["\'][^>]*>/is', $formMatch[1], $matches)) {
        $hiddenFields = array_combine($matches[1], $matches[2]);
        $postData = array_merge($postData, $hiddenFields);
        echo "Champs cachés trouvés et ajoutés.\n";
    }
}

// 2. POST connexion
echo "2. POST connexion...\n";
$postHeaders = $headers;
$postHeaders[] = 'Referer: ' . $loginUrl;

curl_setopt($ch, CURLOPT_URL, $loginUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $postHeaders);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData)); 

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (stripos($response, 'Déconnexion') !== false || stripos($response, 'Mon compte') !== false) {
    echo "-> Connexion RÉUSSIE !\n\n";
} else {
    echo "-> ÉCHEC. Code: $httpCode. Access Forbidden ?\n";
    echo substr(strip_tags($response), 0, 500);
    die();
}

// ... Suite du script
echo "3. Accès à la page de la vente privée...\n";
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, false);
$response = curl_exec($ch);

echo "\n4. Recherche du bouton IMPRIMER...\n";

if (preg_match_all('/<form[^>]*action=["\']([^"\']*)["\'][^>]*>.*?(Imprimez|Ajouter|Panier)/is', $response, $matches)) {
    echo "-> Formulaire d'impression TROUVÉ (Action: " . $matches[1][0] . ")\n";
    
    preg_match_all('/<input[^>]*name=["\'](.*?)["\'][^>]*value=["\'](.*?)["\'][^>]*>/is', $matches[0][0], $inputs);
    $data = [];
    if (!empty($inputs[1])) {
        $data = array_combine($inputs[1], $inputs[2]);
    }
    
    echo "\n4. Simulation du clic sur Imprimer...\n";
    curl_setopt($ch, CURLOPT_URL, $matches[1][0]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    
    $printResponse = curl_exec($ch);
    $printCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    echo "Code HTTP de l'impression : $printCode\n";
    if (stripos($printResponse, 'Le produit a été ajouté') !== false || stripos($printResponse, 'Panier') !== false || stripos($printResponse, 'Imprim') !== false) {
        echo "-> Impression VALIDÉE par le serveur !\n";
    } else {
        echo "-> Résultat incertain. Extrait de la réponse :\n";
        echo substr(strip_tags($printResponse), 0, 300) . "\n";
    }
    
} elseif (preg_match_all('/<a[^>]*href=["\']([^"\']*(?:imprime|coupon|panier)[^"\']*)["\'][^>]*>/i', $response, $matches)) {
    echo "-> Lien d'impression TROUVÉ : " . $matches[1][0] . "\n";
    
    $actionUrl = "https://www.bourges.infoptimum.com/" . ltrim($matches[1][0], '/');
    curl_setopt($ch, CURLOPT_URL, $actionUrl);
    curl_setopt($ch, CURLOPT_POST, false);
    $printResponse = curl_exec($ch);
    $printCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    echo "Code HTTP du clic : $printCode\n";
} else {
    echo "-> AUCUN bouton d'impression trouvé sur la page.\n";
}
curl_close($ch);
?>