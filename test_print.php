<?php
$loginUrl = "https://www.bourges.infoptimum.com/identifiez-vous.php";

echo "<pre>";
echo "--- ANALYSE DE LA PAGE DE CONNEXION ---\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $loginUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
    'Accept-Language: fr-FR,fr;q=0.9,en-US;q=0.8,en;q=0.7',
    'Connection: keep-alive',
    'Upgrade-Insecure-Requests: 1'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Code HTTP GET : $httpCode\n\n";

if (preg_match_all('/<form[^>]*>(.*?)<\/form>/is', $response, $matches)) {
    foreach ($matches[0] as $index => $formHtml) {
        if (stripos($formHtml, 'password') !== false || stripos($formHtml, 'passe') !== false || stripos($formHtml, 'identifiez-vous') !== false) {
            echo "=== FORMULAIRE DE CONNEXION POTENTIEL TROUVÉ ===\n\n";
            
            preg_match('/<form([^>]*)>/i', $formHtml, $formTag);
            echo "Balise form : \n" . htmlspecialchars($formTag[0]) . "\n\n";
            
            if (preg_match_all('/<input([^>]*)>/i', $formHtml, $inputs)) {
                echo "Champs input :\n";
                foreach ($inputs[0] as $input) {
                    echo htmlspecialchars($input) . "\n";
                }
            }
            
            if (preg_match_all('/<button([^>]*)>(.*?)<\/button>/i', $formHtml, $buttons)) {
                echo "\nBoutons :\n";
                foreach ($buttons[0] as $button) {
                    echo htmlspecialchars($button) . "\n";
                }
            }
        }
    }
} else {
    echo "Aucun formulaire trouvé.";
}

echo "</pre>";
?>