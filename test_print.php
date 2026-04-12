<?php
$url = "https://www.bourges.infoptimum.com/vente-privee-1ere-seance-de-2h30-pour-perdre-du-poids-grace-a-lhypno-nutrition-7053.html";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');

$html = curl_exec($ch);
curl_close($ch);

echo "--- RECHERCHE DU BOUTON IMPRIMER ---\n";
// On cherche le texte "Imprimer" ou des liens/formulaires suspects
if (preg_match_all('/<a[^>]*href=["\'](.*?)["\'][^>]*>.*?Imprimer.*?<\/a>/is', $html, $matches)) {
    echo "Liens d'impression trouvés :\n";
    print_r($matches[1]);
}

if (preg_match_all('/<form[^>]*action=["\'](.*?)["\'][^>]*>.*?Imprimer.*?<\/form>/is', $html, $matches)) {
    echo "Formulaires d'impression trouvés :\n";
    print_r($matches[1]);
}

// On cherche aussi le bouton "Ajouter au panier" car l'utilisateur parlait de stock avant
if (preg_match_all('/<form[^>]*id=["\']form_add_cart["\'][^>]*action=["\'](.*?)["\'][^>]*>/is', $html, $matches)) {
    echo "Formulaire d'ajout au panier trouvé :\n";
    print_r($matches[1]);
}

echo "\n--- ANALYSE DES INPUTS DU FORMULAIRE PANIER ---\n";
if (preg_match('/<form[^>]*id=["\']form_add_cart["\'][^>]*>(.*?)<\/form>/is', $html, $formContent)) {
    preg_match_all('/<input[^>]*name=["\'](.*?)["\'][^>]*value=["\'](.*?)["\'][^>]*>/is', $formContent[1], $inputs);
    print_r(array_combine($inputs[1], $inputs[2]));
}
?>