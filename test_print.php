<?php
require_once 'config.php';

$url = "https://www.bourges.infoptimum.com/vente-privee-8-seances-de-1h30-de-preparation-mentale-7054.html";
$loginUrl = "https://www.bourges.infoptimum.com/identifiez-vous2.php"; 
$refererUrl = "https://www.bourges.infoptimum.com/identifiez-vous.php";
$cookieFile = __DIR__ . '/test_cookies.txt';

echo "--- TEST DE CONNEXION AVEC GESTION DE SESSION ---\n<br>\n";

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

// --- ETAPE 1: GET pour initialiser la session ---
echo "1b. GET initial sur $refererUrl pour obtenir le cookie de session...\n<br>\n";
curl_setopt($ch, CURLOPT_URL, $refererUrl);
curl_setopt($ch, CURLOPT_POST, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
$html = curl_exec($ch);

// --- ETAPE 2: POST sur le script de traitement ---
echo "2. POST connexion sur $loginUrl...\n<br>\n";

$postData = http_build_query([
    'email' => $infoptimum_email,
    'pass' => $infoptimum_pass,
    'action' => 'ident',
    'submit' => 'Valider'
]);

$postHeaders = $headers;
$postHeaders[] = 'Origin: https://www.bourges.infoptimum.com';
$postHeaders[] = 'Referer: ' . $refererUrl;
$postHeaders[] = 'Content-Type: application/x-www-form-urlencoded';

curl_setopt($ch, CURLOPT_URL, $loginUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData); 
curl_setopt($ch, CURLOPT_HTTPHEADER, $postHeaders);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL); 

// --- ETAPE 3: Vérification de la session ---
echo "3. Vérification de la session en accédant à mon-compte.php...\n<br>\n";
curl_setopt($ch, CURLOPT_URL, "https://www.bourges.infoptimum.com/mon-compte.php");
curl_setopt($ch, CURLOPT_POST, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); // Headers GET normaux
$accountHtml = curl_exec($ch);
$accountEffectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

if (stripos($accountEffectiveUrl, 'identifiez-vous') === false) {
    echo "-> Connexion RÉUSSIE ! Le cookie de session est valide.\n<br>\n<br>\n";
} else {
    echo "-> ÉCHEC. La session n'a pas été créée. Redirigé vers la page de login.\n<br>\n";
    die();
}

// --- ETAPE 4: Accès à la vente privée ---
echo "4. Accès à la page de la vente privée...\n<br>\n";
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, false);
$response = curl_exec($ch);

echo "\n5. Recherche du bouton IMPRIMER...\n<br>\n";

if (preg_match_all('/<form[^>]*action=["\']([^"\']*)["\'][^>]*>(?:(?!<\/form>).)*?(Imprimez|Ajouter|Panier).*?<\/form>/is', $response, $matches)) {
    $found = false;
    foreach ($matches[1] as $index => $actionUrl) {
        if (stripos($actionUrl, 'recherche') === false) {
            echo "-> Formulaire d'impression TROUVÉ (Action: " . htmlspecialchars($actionUrl) . ")\n<br>\n";
            
            preg_match_all('/<input[^>]*name=["\'](.*?)["\'][^>]*value=["\'](.*?)["\'][^>]*>/is', $matches[0][$index], $inputs);
            $data = [];
            if (!empty($inputs[1])) {
                $data = array_combine($inputs[1], $inputs[2]);
            }
            
            echo "\n6. Simulation du clic sur Imprimer...\n<br>\n";
            curl_setopt($ch, CURLOPT_URL, $actionUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            
            $printHeaders = $headers;
            $printHeaders[] = 'Origin: https://www.bourges.infoptimum.com';
            $printHeaders[] = 'Content-Type: application/x-www-form-urlencoded';
            curl_setopt($ch, CURLOPT_HTTPHEADER, $printHeaders);
            
            $printResponse = curl_exec($ch);
            $printCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            echo "Code HTTP de l'impression : $printCode\n<br>\n";
            if (stripos($printResponse, 'Le produit a été ajouté') !== false || stripos($printResponse, 'Panier') !== false || stripos($printResponse, 'Imprim') !== false || $printCode == 302 || $printCode == 200) {
                echo "<strong>-> Impression VALIDÉE par le serveur !</strong>\n<br>\n";
            } else {
                echo "-> Résultat incertain.\n<br>\n";
            }
            $found = true;
            break;
        }
    }
    if (!$found) echo "-> Seul le formulaire de recherche a été détecté.\n<br>\n";
} else {
    echo "-> <strong>AUCUN bouton d'impression trouvé sur la page.</strong>\n<br>\n";
}
curl_close($ch);
?>