<?php
require_once 'config.php';

$url = "https://www.bourges.infoptimum.com/vente-privee-8-seances-de-1h30-de-preparation-mentale-7054.html";
$loginUrl = "https://www.bourges.infoptimum.com/identifiez-vous.php"; 
$refererUrl = "https://www.bourges.infoptimum.com/identifiez-vous.php";
$cookieFile = __DIR__ . '/test_cookies.txt';

echo "--- TEST D'IMPRESSION SUR LA VENTE SPÉCIFIQUE ---\n<br>\n";

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

// On récupère le premier compte disponible
$stmt = $pdo->query("SELECT * FROM infoptimum_accounts LIMIT 1");
$account = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$account) {
    die("ERREUR : Aucun compte Infoptimum trouvé dans la table 'infoptimum_accounts'.\n");
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

// Pour éviter un problème de cache DNS ou d'encodage
curl_setopt($ch, CURLOPT_ENCODING, ''); 

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

echo "1b. GET initial sur la page de connexion...\n<br>\n";
curl_setopt($ch, CURLOPT_URL, $refererUrl);
curl_setopt($ch, CURLOPT_POST, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_exec($ch);

echo "2. POST connexion sur identifiez-vous.php (pas vous2 !)...\n<br>\n";

$postData = http_build_query([
    'email' => $infoptimum_email,
    'pass' => $infoptimum_pass,
    'action' => 'ident',
    'submit' => 'Valider'
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

// On affiche TOUT ce qu'on reçoit pour comprendre ce qui cloche
echo "Code HTTP retourné : $httpCode \n<br>\n";
echo "URL finale après redirection éventuelle : $effectiveUrl \n<br>\n";

// Si on trouve une balise <title>, c'est mieux que d'afficher 300 chars bruts
if (preg_match('/<title>(.*?)<\/title>/is', $response, $title)) {
     echo "Titre de la page chargée : " . htmlspecialchars(trim($title[1])) . "\n<br>\n";
} else {
     echo "Extrait de la page reçue (300 premiers caractères) : \n<br>\n";
     echo htmlspecialchars(substr(strip_tags($response), 0, 300)) . "\n<br>\n<br>\n";
}

// On vérifie une connexion réussie
if (stripos($effectiveUrl, 'mon-compte.php') !== false || stripos($response, 'Déconnexion') !== false || stripos($response, 'Mes alertes') !== false || stripos($response, 'Devenir membre') === false) {
    echo "-> Connexion potentiellement RÉUSSIE ! (Vérifions le HTML de la vente)\n<br>\n<br>\n";
} else {
    echo "-> ÉCHEC. La session n'a pas été créée.\n<br>\n";
    // On ne s'arrête plus ici, on continue pour voir ce qu'affiche la vente
}

echo "3. Accès à la page de la vente privée...\n<br>\n";
echo "URL : <a href='$url' target='_blank'>$url</a>\n<br>\n";

curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, false);
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

echo "\n4. Recherche du bouton IMPRIMER...\n<br>\n";

// Recherche spécifique pour cette page si l'expression régulière précédente échouait
if (preg_match_all('/<form[^>]*action=["\']([^"\']*)["\'][^>]*>.*?(Imprimez|Ajouter|Panier)/is', $response, $matches)) {
    echo "-> Formulaire d'impression TROUVÉ (Action: " . htmlspecialchars($matches[1][0]) . ")\n<br>\n";
    
    preg_match_all('/<input[^>]*name=["\'](.*?)["\'][^>]*value=["\'](.*?)["\'][^>]*>/is', $matches[0][0], $inputs);
    $data = [];
    if (!empty($inputs[1])) {
        $data = array_combine($inputs[1], $inputs[2]);
    }
    
    echo "\n5. Simulation du clic sur Imprimer...\n<br>\n";
    curl_setopt($ch, CURLOPT_URL, $matches[1][0]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    
    $printHeaders = $getHeaders;
    $printHeaders[] = 'Origin: https://www.bourges.infoptimum.com';
    $printHeaders[] = 'Content-Type: application/x-www-form-urlencoded';
    curl_setopt($ch, CURLOPT_HTTPHEADER, $printHeaders);
    
    $printResponse = curl_exec($ch);
    $printCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    echo "Code HTTP de l'impression : $printCode\n<br>\n";
    if (stripos($printResponse, 'Le produit a été ajouté') !== false || stripos($printResponse, 'Panier') !== false || stripos($printResponse, 'Imprim') !== false || $printCode == 302 || $printCode == 200) {
        echo "<strong>-> Impression VALIDÉE par le serveur !</strong>\n<br>\n";
    } else {
        echo "-> Résultat incertain. Extrait de la réponse :\n<br>\n";
        echo htmlspecialchars(substr(strip_tags($printResponse), 0, 300)) . "\n<br>\n";
    }
    
} elseif (preg_match_all('/<a[^>]*href=["\']([^"\']*(?:imprime|coupon|panier)[^"\']*)["\'][^>]*>/i', $response, $matches)) {
    echo "-> Lien d'impression TROUVÉ : " . htmlspecialchars($matches[1][0]) . "\n<br>\n";
    
    $actionUrl = "https://www.bourges.infoptimum.com/" . ltrim($matches[1][0], '/');
    curl_setopt($ch, CURLOPT_URL, $actionUrl);
    curl_setopt($ch, CURLOPT_POST, false);
    $printResponse = curl_exec($ch);
    $printCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    echo "Code HTTP du clic : $printCode\n<br>\n";
} else {
    echo "-> <strong>AUCUN bouton d'impression trouvé sur la page.</strong> La vente est peut-être épuisée, ou ce compte a déjà imprimé le coupon.\n<br>\n";
    echo "Recherche brute d'un formulaire contenant 'Ajouter' ou 'Imprimer':\n<br>";
    if (preg_match_all('/<form[^>]*>.*?<\/form>/is', $response, $forms)) {
        foreach ($forms[0] as $f) {
            if (stripos($f, 'imprime') !== false || stripos($f, 'ajouter') !== false || stripos($f, 'panier') !== false) {
                echo htmlspecialchars($f) . "<br>";
            }
        }
    }
}
curl_close($ch);
?>