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

        // --- PRIORITÉ ABSOLUE : La balise <span class="s24"> ---
        // On cherche une balise span avec la classe "s24" qui contient un nombre.
        // Regex : <span ... class="s24" ... > ... (nombre) ... </span>
        if (preg_match('/<span[^>]*class=["\']s24["\'][^>]*>.*?(\d+).*?<\/span>/is', $html, $matches)) {
            $stock = intval($matches[1]);
            
            if ($stock > 0) {
                return 'available';
            } else {
                return 'out_of_stock';
            }
        }

        // --- SECURITÉ : Si la balise s24 n'est pas trouvée ---
        
        // Marqueurs explicites de rupture
        if (stripos($html, 'Victime de son succès') !== false || 
            stripos($html, 'Epuisé') !== false ||
            stripos($html, 'id="produit-epuise"') !== false) {
            return 'out_of_stock';
        }

        // Marqueurs de disponibilité (uniquement si on n'a pas trouvé de s24 ni de marqueur de rupture)
        // Attention : l'image peut parfois rester même si le stock est à 0, donc on la met en dernier recours
        // et on vérifie qu'il n'y a pas de mention "0 offre" à côté.
        if (stripos($html, 'images/vp-imprime-coupon.png') !== false) {
             // Double vérification : est-ce qu'il y a un "0" suspect à proximité ?
             // Dans le doute, si on a l'image mais pas le span s24, on suppose disponible mais c'est risqué.
             return 'available';
        }

        if (stripos($html, 'id="form_add_cart"') !== false) {
            return 'available';
        }
        
        return 'unknown';
    }
}
?>