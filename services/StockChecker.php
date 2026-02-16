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
        
        // Critère 1 : Présence de l'image spécifique "vp-imprime-coupon.png"
        if (stripos($html, 'images/vp-imprime-coupon.png') !== false) {
            return 'available';
        }

        // Critère 2 : Analyse du nombre d'offres restantes
        if (preg_match('/Nombre d\'offre\(s\) restante\(s\)[^0-9]*(\d+)/i', $html, $matches)) {
            if (intval($matches[1]) > 0) {
                return 'available';
            } else {
                return 'out_of_stock';
            }
        }

        // Fallback : Anciens critères
        if (stripos($html, 'id="produit-epuise"') !== false || 
            stripos($html, 'Victime de son succès') !== false || 
            stripos($html, 'Epuisé') !== false) {
            return 'out_of_stock';
        }
        
        if (stripos($html, 'id="form_add_cart"') !== false || 
            stripos($html, 'Ajouter au panier') !== false) {
            return 'available';
        }
        
        return 'unknown';
    }
}
?>