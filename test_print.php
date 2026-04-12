<?php
require_once 'config.php';

$url = "https://www.bourges.infoptimum.com/vente-privee-spray-nettoyant-17ml-rechargeable-pour-lunettes-7196.html";
$loginUrl = "https://www.bourges.infoptimum.com/identifiez-vous.php";
$cookieFile = __DIR__ . '/test_cookies.txt';

echo "--- TEST D'IMPRESSION (COMPTE REEL) ---\n";

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

if (file_exists($cookieFile)) {
    unlink($cookieFile);
}

// En-têtes pour paraître très humain
$headers = [
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',
    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
    'Accept-Language: fr-FR,fr;q=0.9,en-US;q=0.8,en;q=0.7',
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

// -------------------------------------------------------------------------
// ETAPE 1 : RECUPERER LES CHAMPS EXACTS DU FORMULAIRE DE CONNEXION
// -------------------------------------------------------------------------
echo "1. GET identifiez-vous.php...\n";
curl_setopt($ch, CURLOPT_URL, $loginUrl);
curl_setopt($ch, CURLOPT_POST, false);
$loginPageHtml = curl_exec($ch);

$postData = [
    'email' => $infoptimum_email,
    'pass' => $infoptimum_pass,
    'action' => 'ident', // action par défaut sur beaucoup de vieux scripts
    'valider' => 'Me connecter', // On essaie un submit classique
];

// Si on trouve un formulaire avec un name="action" spécifique, on l'ajoute
if (preg_match('/<form[^>]*id=["\']form_identification["\'][^>]*>/is', $loginPageHtml, $formMatch)) {
    echo "Formulaire de connexion trouvé.\n";
    if (preg_match_all('/<input[^>]*type=["\']hidden["\'][^>]*name=["\'](.*?)["\'][^>]*value=["\'](.*?)["\'][^>]*>/is', $loginPageHtml, $matches)) {
        $hiddenFields = array_combine($matches[1], $matches[2]);
        echo "Champs cachés ajoutés : " . print_r($hiddenFields, true) . "\n";
        $postData = array_merge($postData, $hiddenFields);
    }
}

// -------------------------------------------------------------------------
// ETAPE 2 : POST POUR SE CONNECTER AVEC MULTIPART (souvent requis pour éviter les 403)
// -------------------------------------------------------------------------
echo "2. POST connexion avec : $infoptimum_email\n";

$postHeaders = $headers;
$postHeaders[] = 'Referer: ' . $loginUrl;
$postHeaders[] = 'Origin: https://www.bourges.infoptimum.com';

curl_setopt($ch, CURLOPT_URL, $loginUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $postHeaders);

// Astuce : Certains WAF bloquent "application/x-www-form-urlencoded" fait par curl. 
// Passer un tableau à CURLOPT_POSTFIELDS force cURL à utiliser "multipart/form-data" qui passe souvent mieux.
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData); 

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($httpCode == 200 && (stripos($response, 'Déconnexion') !== false || stripos($response, 'Mon compte') !== false || stripos($response, 'Mes alertes') !== false)) {
    echo "-> Connexion RÉUSSIE !\n\n";
} else {
    echo "-> ÉCHEC de la connexion (Code $httpCode).\n";
    if (stripos($response, 'Access Forbidden') !== false) {
         echo "ERREUR 403 DÉTECTÉE.\n";
    }
    die();
}

// ... Suite du script 
echo "3. Accès à la page de la vente privée...\n";
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); // Retour aux headers normaux
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

echo "Code HTTP vente : $httpCode\n";

echo "\n4. Recherche du bouton IMPRIMER...\n";
if (preg_match_all('/<form[^>]*action=["\']([^"\']*)["\'][^>]*>.*?(Imprimez|Ajouter|Panier)/is', $response, $matches)) {
    echo "-> Formulaire d'impression TROUVÉ (Action: " . $matches[1][0] . ")\n";
    preg_match_all('/<input[^>]*name=["\'](.*?)["\'][^>]*value=["\'](.*?)["\'][^>]*>/is', $matches[0][0], $inputs);
    $data = [];
    if (!empty($inputs[1])) {
        $data = array_combine($inputs[1], $inputs[2]);
    }
    
    echo "5. Simulation du clic sur Imprimer...\n";
    curl_setopt($ch, CURLOPT_URL, $matches[1][0]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data)); // Le submit de base marche bien en form-urlencoded
    
    $printResponse = curl_exec($ch);
    $printCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    echo "Code HTTP de l'impression : $printCode\n";
    if (stripos($printResponse, 'Le produit a été ajouté') !== false || stripos($printResponse, 'Panier') !== false || stripos($printResponse, 'Imprim') !== false) {
        echo "-> Impression VALIDÉE par le serveur !\n";
    } else {
        echo "-> Résultat incertain.\n";
    }
} else {
    echo "-> AUCUN bouton d'impression trouvé sur la page.\n";
}

curl_close($ch);
?>