<?php
require_once 'config.php';

$url = "https://www.bourges.infoptimum.com/vente-privee-spray-nettoyant-17ml-rechargeable-pour-lunettes-7196.html";
$loginUrl = "https://www.bourges.infoptimum.com/identifiez-vous.php";
$cookieFile = __DIR__ . '/test_cookies.txt';

echo "--- TEST DE CONNEXION AVANCE ---\n";

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

// 1. Initialiser cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');

// 2. Tenter de se connecter avec une simple requête URL-encoded
$postData = [
    'email' => $infoptimum_email,
    'pass' => $infoptimum_pass,
    'action' => 'ident',
    'submit' => 'Valider'
];

echo "1. Tentative avec format classique url-encoded...\n";
curl_setopt($ch, CURLOPT_URL, $loginUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($httpCode == 200 && (stripos($response, 'Déconnexion') !== false || stripos($response, 'Mon compte') !== false)) {
    echo "-> Connexion RÉUSSIE (Methode 1)!\n";
} else {
    echo "-> ECHEC Methode 1 (Code $httpCode)\n\n";

    // 3. Tenter de se connecter avec Multipart
    echo "2. Tentative avec format multipart/form-data...\n";
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData); // Passer un array = multipart
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($httpCode == 200 && (stripos($response, 'Déconnexion') !== false || stripos($response, 'Mon compte') !== false)) {
        echo "-> Connexion RÉUSSIE (Methode 2)!\n";
    } else {
        echo "-> ECHEC Methode 2 (Code $httpCode)\n\n";
        
        echo "Le serveur reçoit bien nos requêtes (Code 200) mais l'authentification échoue.\n";
        echo "Il est fort probable que le champ du mot de passe ne s'appelle pas 'pass'.\n";
        echo "Extrait de la page retournée :\n";
        echo substr(strip_tags($response), 0, 500);
        die();
    }
}

// ... Suite du test ...
echo "\n3. GET Vente Privée...\n";
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, false);
$response = curl_exec($ch);

if (preg_match_all('/<form[^>]*action=["\']([^"\']*)["\'][^>]*>.*?(Imprimez|Ajouter|Panier)/is', $response, $matches)) {
    echo "-> Formulaire d'impression TROUVÉ (Action: " . $matches[1][0] . ")\n";
    preg_match_all('/<input[^>]*name=["\'](.*?)["\'][^>]*value=["\'](.*?)["\'][^>]*>/is', $matches[0][0], $inputs);
    $data = [];
    if (!empty($inputs[1])) {
        $data = array_combine($inputs[1], $inputs[2]);
    }
    
    echo "4. Simulation Impression...\n";
    curl_setopt($ch, CURLOPT_URL, $matches[1][0]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    
    $printResponse = curl_exec($ch);
    $printCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (stripos($printResponse, 'Le produit a été ajouté') !== false || stripos($printResponse, 'Panier') !== false) {
        echo "-> Impression VALIDÉE !\n";
    } else {
        echo "-> Résultat incertain.\n";
    }
} else {
    echo "-> AUCUN bouton d'impression trouvé.\n";
}
curl_close($ch);
?>