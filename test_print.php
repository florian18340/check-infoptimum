<?php
require_once 'config.php';

$url = "https://www.bourges.infoptimum.com/vente-privee-8-seances-de-1h30-de-preparation-mentale-7054.html";
$loginUrl = "https://www.bourges.infoptimum.com/identifiez-vous2.php"; 
$refererUrl = "https://www.bourges.infoptimum.com/identifiez-vous.php";
$cookieFile = __DIR__ . '/test_cookies.txt';

echo "--- TEST DE CONNEXION AVEC GESTION DE SESSION (MULTI-CHAMPS) ---\n<br>\n";

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

$headers = [
    'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36',
    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
    'Accept-Language: fr-FR,fr;q=0.9,en-US;q=0.8,en;q=0.7',
    'Connection: keep-alive',
    'Upgrade-Insecure-Requests: 1'
];

$postHeaders = $headers;
$postHeaders[] = 'Origin: https://www.bourges.infoptimum.com';
$postHeaders[] = 'Referer: ' . $refererUrl;
$postHeaders[] = 'Content-Type: application/x-www-form-urlencoded';

$fieldCombinations = [
    ['email' => $infoptimum_email, 'pass' => $infoptimum_pass],
    ['email' => $infoptimum_email, 'password' => $infoptimum_pass],
    ['login' => $infoptimum_email, 'pass' => $infoptimum_pass],
    ['login' => $infoptimum_email, 'password' => $infoptimum_pass],
    ['email' => $infoptimum_email, 'mdp' => $infoptimum_pass],
];

$isLoggedIn = false;

foreach ($fieldCombinations as $index => $fields) {
    echo "<hr><strong>Tentative " . ($index + 1) . " avec les champs : " . implode(', ', array_keys($fields)) . "</strong>\n<br>\n";
    
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

    // GET initial
    curl_setopt($ch, CURLOPT_URL, $refererUrl);
    curl_setopt($ch, CURLOPT_POST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_exec($ch);

    // POST connexion
    $postData = http_build_query(array_merge($fields, ['action' => 'ident', 'submit' => 'Valider']));
    
    curl_setopt($ch, CURLOPT_URL, $loginUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData); 
    curl_setopt($ch, CURLOPT_HTTPHEADER, $postHeaders);
    curl_exec($ch);

    // Vérification
    curl_setopt($ch, CURLOPT_URL, "https://www.bourges.infoptimum.com/mon-compte.php");
    curl_setopt($ch, CURLOPT_POST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $accountHtml = curl_exec($ch);
    $accountEffectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

    if (stripos($accountEffectiveUrl, 'identifiez-vous') === false) {
        echo "-> Connexion RÉUSSIE ! Les bons champs sont : " . implode(', ', array_keys($fields)) . "\n<br>\n";
        $isLoggedIn = true;
        break; // On a trouvé, on arrête la boucle
    } else {
        echo "-> ÉCHEC.\n<br>\n";
    }
    curl_close($ch);
}

if (!$isLoggedIn) {
    die("Toutes les combinaisons de champs ont échoué. Le problème est ailleurs (peut-être un champ caché manquant ou une protection plus complexe).");
}

// ... Suite du script d'impression ...
$ch = curl_init(); // On réinitialise cURL pour la suite
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); 
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile); // On garde les cookies de la session réussie
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_ENCODING, ''); 
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

echo "\n<br><strong>--- IMPRESSION ---</strong>\n<br>\n";
echo "Accès à la page de la vente privée...\n<br>\n";
curl_setopt($ch, CURLOPT_URL, $url);
$response = curl_exec($ch);

if (preg_match_all('/<form[^>]*action=["\']([^"\']*)["\'][^>]*>(?:(?!<\/form>).)*?(Imprimez|Ajouter|Panier).*?<\/form>/is', $response, $matches)) {
    // ... (le reste du code d'impression)
} else {
    echo "-> <strong>AUCUN bouton d'impression trouvé sur la page.</strong>\n<br>\n";
}
curl_close($ch);
?>