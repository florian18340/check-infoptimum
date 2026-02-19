<?php
class StockChecker {
    public function check($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
        
        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode != 200 || !$html) {
            return 'error';
        }

        // --- NOUVELLE LOGIQUE ---
        // Priorité 1: Vérifier les marqueurs de RUPTURE DE STOCK en premier.
        
        // Marqueur le plus fiable de rupture.
        if (stripos($html, 'id="produit-epuise"') !== false || 
            stripos($html, 'Victime de son succès') !== false ||
            stripos($html, 'cette vente privée est terminée') !== false) {
            return 'out_of_stock';
        }

        // Vérifier si le nombre d'offres est explicitement à zéro.
        if (preg_match('/Nombre d\'offre\(s\) restante\(s\)[^0-9]*(\d+)/i', $html, $matches)) {
            if (intval($matches[1]) === 0) {
                return 'out_of_stock';
            }
        }

        // Priorité 2: Si aucun marqueur de rupture n'est trouvé, chercher les marqueurs de DISPONIBILITÉ.

        // Marqueur le plus fiable de disponibilité.
        if (stripos($html, 'images/vp-imprime-coupon.png') !== false) {
            return 'available';
        }

        // Vérifier si le nombre d'offres est supérieur à zéro.
        if (isset($matches) && intval($matches[1]) > 0) {
            return 'available';
        }

        // Fallback : si le formulaire d'ajout au panier est présent.
        if (stripos($html, 'id="form_add_cart"') !== false) {
            return 'available';
        }
        
        // Si aucune condition n'est remplie, le statut est incertain.
        return 'unknown';
    }
}
?>