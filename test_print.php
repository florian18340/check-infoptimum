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
curl_setopt($ch, CURLOPT_ENCODING, ''); 

$headers = [
    'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36',
    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
    'Accept-Language: fr-FR,fr;q=0.9,en-US;q=0.8,en;q=0.7',
    'Cache-Control: max-age=0',
    'Connection: keep-alive',
    'Origin: https://www.bourges.infoptimum.com',
    'Referer: ' . $refererUrl,
    'Upgrade-Insecure-Requests: 1'
];

echo "1b. GET initial sur la page de connexion...\n<br>\n";
curl_setopt($ch, CURLOPT_URL, $refererUrl);
curl_setopt($ch, CURLOPT_POST, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
$html = curl_exec($ch);

// Extraction des champs cachés du formulaire de login
$hiddenFields = [];
if (preg_match('/<form[^>]*>(.*?)<\/form>/is', $html, $formMatch)) {
    if (preg_match_all('/<input[^>]*type=["\']hidden["\'][^>]*name=["\'](.*?)["\'][^>]*value=["\'](.*?)["\'][^>]*>/is', $formMatch[1], $matches)) {
        $hiddenFields = array_combine($matches[1], $matches[2]);
        echo "-> Champs cachés trouvés et ajoutés : " . print_r($hiddenFields, true) . "\n<br>\n";
    }
}

echo "2. POST connexion sur identifiez-vous.php...\n<br>\n";

$postData = array_merge([
    'email' => $infoptimum_email,
    'pass' => $infoptimum_pass,
    'action' => 'ident',
    'submit' => 'Valider'
], $hiddenFields); // On ajoute les champs cachés

curl_setopt($ch, CURLOPT_URL, $loginUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData)); 

$postHeaders = $headers;
$postHeaders[] = 'Content-Type: application/x-www-form-urlencoded';
curl_setopt($ch, CURLOPT_HTTPHEADER, $postHeaders);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL); 

echo "Code HTTP retourné : $httpCode \n<br>\n";

if (stripos($effectiveUrl, 'mon-compte.php') !== false || stripos($response, 'Déconnexion') !== false || stripos($response, 'Mes alertes') !== false) {
    echo "-> Connexion RÉUSSIE !\n<br>\n<br>\n";
} else {
    echo "-> ÉCHEC de la connexion.\n<br>\n";
    if (preg_match('/<div[^>]*class=["\'][^"\']*erreur[^"\']*["\'][^>]*>(.*?)<\/div>/is', $response, $errMatch)) {
        echo "<strong>Message d'erreur : " . htmlspecialchars(strip_tags($errMatch[1])) . "</strong><br>\n";
    } else {
        // Afficher l'erreur pour voir si c'est un problème de champ (ex: "Veuillez remplir tous les champs")
        echo "Extrait HTML (recherche erreur) : <br>\n";
        echo htmlspecialchars(substr(strip_tags($response), 0, 500)) . "<br>\n";
    }
    die();
}

echo "3. Accès à la page de la vente privée...\n<br>\n";
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); // On enlève le Content-Type
$response = curl_exec($ch);

echo "\n4. Recherche du bouton IMPRIMER...\n<br>\n";

// AJOUT: on cherche aussi "Imprimer"
if (preg_match_all('/<form[^>]*action=["\']([^"\']*)["\'][^>]*>(?:(?!<\/form>).)*?(Imprimez|Ajouter|Panier|Imprimer).*?<\/form>/is', $response, $matches)) {
    $found = false;
    foreach ($matches[1] as $index => $actionUrl) {
        if (stripos($actionUrl, 'recherche') === false) {
            echo "-> Formulaire d'impression TROUVÉ (Action: " . htmlspecialchars($actionUrl) . ")\n<br>\n";
            
            preg_match_all('/<input[^>]*name=["\'](.*?)["\'][^>]*value=["\'](.*?)["\'][^>]*>/is', $matches[0][$index], $inputs);
            $data = [];
            if (!empty($inputs[1])) {
                $data = array_combine($inputs[1], $inputs[2]);
            }
            
            echo "\n5. Simulation du clic sur Imprimer...\n<br>\n";
            curl_setopt($ch, CURLOPT_URL, $actionUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            
            $printHeaders = $headers;
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
            $found = true;
            break;
        }
    }
    if (!$found) echo "-> Seul le formulaire de recherche a été détecté.\n<br>\n";
} elseif (preg_match_all('/<a[^>]*href=["\']([^"\']*(?:imprime|coupon|panier)[^"\']*)["\'][^>]*>/i', $response, $matches)) {
    echo "-> Lien d'impression TROUVÉ : " . htmlspecialchars($matches[1][0]) . "\n<br>\n";
    
    $actionUrl = "https://www.bourges.infoptimum.com/" . ltrim($matches[1][0], '/');
    curl_setopt($ch, CURLOPT_URL, $actionUrl);
    curl_setopt($ch, CURLOPT_POST, false);
    $printResponse = curl_exec($ch);
    $printCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    echo "Code HTTP du clic : $printCode\n<br>\n";
} else {
    echo "-> <strong>AUCUN bouton d'impression trouvé sur la page.</strong>\n<br>\n";
}
curl_close($ch);
?>