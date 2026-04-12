<?php
// Ce fichier de test a été réactivé temporairement.
require_once 'config.php';

$url = "https://www.bourges.infoptimum.com/vente-privee-spray-nettoyant-17ml-rechargeable-pour-lunettes-7196.html";
$loginUrl = "https://www.bourges.infoptimum.com/identifiez-vous.php";
$cookieFile = __DIR__ . '/test_cookies.txt';

echo "--- TEST D'IMPRESSION AVEC COMPTES EN BASE DE DONNÉES ---\n";

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
    die("ERREUR : Aucun compte Infoptimum trouvé dans la table 'infoptimum_accounts'. Ajoutez-en un depuis l'interface web (Paramètres).\n");
}

$infoptimum_email = $account['email'];
$infoptimum_pass = $account['password'];

echo "1. Tentative de connexion avec le compte : $infoptimum_email\n";

if (file_exists($cookieFile)) {
    unlink($cookieFile);
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $loginUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');

$postData = [
    'email' => $infoptimum_email,
    'pass' => $infoptimum_pass,
    'action' => 'ident',
    'submit' => 'Valider'
];
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($httpCode == 200 && (stripos($response, 'Déconnexion') !== false || stripos($response, 'Mon compte') !== false || stripos($response, 'Mes alertes') !== false)) {
    echo "-> Connexion RÉUSSIE !\n\n";
} else {
    echo "-> ÉCHEC de la connexion (Code $httpCode). Les identifiants sont-ils corrects ?\n";
    echo substr(strip_tags($response), 0, 500) . "\n\n";
    die();
}

echo "2. Accès à la page de la vente privée...\n";
echo "URL : $url\n";

curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, false);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

echo "Code HTTP : " . $httpCode . "\n";

echo "\n3. Recherche du bouton IMPRIMER...\n";

if (preg_match_all('/<form[^>]*action=["\']([^"\']*)["\'][^>]*>.*?(Imprimez|Ajouter|Panier)/is', $response, $matches)) {
    echo "-> Formulaire d'impression TROUVÉ (Action: " . $matches[1][0] . ")\n";
    
    preg_match_all('/<input[^>]*name=["\'](.*?)["\'][^>]*value=["\'](.*?)["\'][^>]*>/is', $matches[0][0], $inputs);
    $data = [];
    if (!empty($inputs[1])) {
        $data = array_combine($inputs[1], $inputs[2]);
        echo "Données à envoyer :\n";
        print_r($data);
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
    
    echo "\n4. Simulation du clic sur le lien...\n";
    $actionUrl = "https://www.bourges.infoptimum.com/" . ltrim($matches[1][0], '/');
    curl_setopt($ch, CURLOPT_URL, $actionUrl);
    curl_setopt($ch, CURLOPT_POST, false);
    $printResponse = curl_exec($ch);
    $printCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    echo "Code HTTP du clic : $printCode\n";
    
} else {
    echo "-> AUCUN bouton d'impression trouvé sur la page.\n";
    echo "Est-ce que le produit est en stock ? Avez-vous déjà imprimé ce coupon avec ce compte ?\n";
}

curl_close($ch);
?>