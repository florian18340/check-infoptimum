<?php
require_once 'config.php';

$url = "https://www.bourges.infoptimum.com/vente-privee-spray-nettoyant-17ml-rechargeable-pour-lunettes-7196.html";
$loginUrl = "https://www.bourges.infoptimum.com/identifiez-vous2.php"; 
$refererUrl = "https://www.bourges.infoptimum.com/identifiez-vous.php";
$cookieFile = __DIR__ . '/test_cookies.txt';

echo "--- TEST D'IMPRESSION (POST DIRECT) ---\n";

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
    die("ERREUR : Aucun compte Infoptimum trouvé dans la table 'infoptimum_accounts'.\n");
}

$infoptimum_email = $account['email'];
$infoptimum_pass = $account['password'];

echo "1. Tentative de connexion avec le compte : $infoptimum_email\n";

if (file_exists($cookieFile)) {
    unlink($cookieFile);
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); 
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$headers = [
    'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36',
    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
    'Accept-Language: fr-FR,fr;q=0.9,en-US;q=0.8,en;q=0.7',
    'Cache-Control: max-age=0',
    'Connection: keep-alive',
    'Origin: https://www.bourges.infoptimum.com',
    'Referer: ' . $refererUrl,
    'Sec-Ch-Ua: "Chromium";v="146", "Not-A.Brand";v="24", "Google Chrome";v="146"',
    'Sec-Ch-Ua-Mobile: ?0',
    'Sec-Ch-Ua-Platform: "macOS"',
    'Sec-Fetch-Dest: document',
    'Sec-Fetch-Mode: navigate',
    'Sec-Fetch-Site: same-origin',
    'Sec-Fetch-User: ?1',
    'Upgrade-Insecure-Requests: 1'
];

echo "2. POST connexion sur identifiez-vous2.php...\n";

// Les données exactes que vous avez copié de la console
// Si ce n'est ni 'email' ni 'pass', ce qui est très probable puisqu'on a eu 200 sans connexion,
// testons avec le format le plus standard. On peut aussi tenter 'identifiant' ou 'login'.
$postData = http_build_query([
    'email' => $infoptimum_email,
    'pass' => $infoptimum_pass 
]);

curl_setopt($ch, CURLOPT_URL, $loginUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData); 

$postHeaders = $headers;
$postHeaders[] = 'Content-Type: application/x-www-form-urlencoded';
curl_setopt($ch, CURLOPT_HTTPHEADER, $postHeaders);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL); 

if (stripos($response, 'Déconnexion') !== false || stripos($response, 'Mon compte') !== false) {
    echo "-> Connexion RÉUSSIE (avec pass) !\n\n";
} else {
    // Si ça rate avec 'pass', on tente avec 'mdp'
    echo "-> ÉCHEC avec 'pass'. Deuxième tentative POST avec le champ 'mdp'...\n";
    
    // Il faut re-récupérer un nouveau cookie vierge
    if (file_exists($cookieFile)) { unlink($cookieFile); }
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    
    $postData = http_build_query([
        'email' => $infoptimum_email,
        'mdp' => $infoptimum_pass
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData); 
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

    if (stripos($response, 'Déconnexion') !== false || stripos($response, 'Mon compte') !== false) {
        echo "-> Connexion RÉUSSIE (avec mdp) !\n\n";
    } else {
        echo "-> ÉCHEC final. Code HTTP : $httpCode. URL : $effectiveUrl\n";
        echo "La requête a fonctionné (pas de 403) mais les identifiants ont été rejetés.\n";
        echo "Extrait:\n";
        echo substr(strip_tags($response), 0, 500);
        die();
    }
}

// ... Suite du script
echo "3. Accès à la page de la vente privée...\n";
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, false);
// On enlève Content-Type et Origin pour une requête GET classique
$getHeaders = [
    'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36',
    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
    'Accept-Language: fr-FR,fr;q=0.9,en-US;q=0.8,en;q=0.7',
    'Connection: keep-alive',
    'Referer: ' . $refererUrl,
    'Upgrade-Insecure-Requests: 1'
];
curl_setopt($ch, CURLOPT_HTTPHEADER, $getHeaders);

$response = curl_exec($ch);

echo "\n4. Recherche du bouton IMPRIMER...\n";

if (preg_match_all('/<form[^>]*action=["\']([^"\']*)["\'][^>]*>.*?(Imprimez|Ajouter|Panier)/is', $response, $matches)) {
    echo "-> Formulaire d'impression TROUVÉ (Action: " . $matches[1][0] . ")\n";
    
    preg_match_all('/<input[^>]*name=["\'](.*?)["\'][^>]*value=["\'](.*?)["\'][^>]*>/is', $matches[0][0], $inputs);
    $data = [];
    if (!empty($inputs[1])) {
        $data = array_combine($inputs[1], $inputs[2]);
    }
    
    echo "\n5. Simulation du clic sur Imprimer...\n";
    curl_setopt($ch, CURLOPT_URL, $matches[1][0]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    
    $printHeaders = $getHeaders;
    $printHeaders[] = 'Origin: https://www.bourges.infoptimum.com';
    $printHeaders[] = 'Content-Type: application/x-www-form-urlencoded';
    curl_setopt($ch, CURLOPT_HTTPHEADER, $printHeaders);
    
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