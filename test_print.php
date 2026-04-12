<?php
require_once 'config.php';

$url = "https://www.bourges.infoptimum.com/vente-privee-spray-nettoyant-17ml-rechargeable-pour-lunettes-7196.html";
$loginUrl = "https://www.bourges.infoptimum.com/identifiez-vous.php";
$cookieFile = __DIR__ . '/test_cookies.txt';

echo "--- EXTRACTION BRUTE PAGE DE LOGIN ---\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $loginUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36');

$response = curl_exec($ch);
curl_close($ch);

// On va extraire tous les formulaires pour voir à quoi ils ressemblent
echo "\n--- FORMULAIRES TROUVES ---\n";
if (preg_match_all('/<form[^>]*>(.*?)<\/form>/is', $response, $matches)) {
    foreach ($matches[0] as $i => $form) {
        if (stripos($form, 'password') !== false || stripos($form, 'passe') !== false) {
             echo "\nFormulaire de connexion probable trouvé (Extrait) :\n";
             
             // Extraction des balises form pour voir l'action
             preg_match('/<form([^>]*)>/is', $form, $formTag);
             echo "Balise FORM : " . $formTag[0] . "\n";
             
             // Extraction de tous les inputs
             if (preg_match_all('/<input([^>]*)>/is', $form, $inputs)) {
                 echo "Inputs contenus :\n";
                 foreach($inputs[0] as $input) {
                     echo "  - " . trim($input) . "\n";
                 }
             }
        }
    }
} else {
    echo "Aucun formulaire trouvé sur la page !\n";
}

echo "\n--- AFFICHAGE DU TITRE DE LA PAGE ---\n";
if (preg_match('/<title>(.*?)<\/title>/is', $response, $title)) {
    echo "Titre : " . trim($title[1]) . "\n";
}
?>