<?php
class StockChecker {
    private $logFile;

    public function __construct() {
        // Définir le chemin absolu du fichier log à la racine du projet
        $this->logFile = __DIR__ . '/../checker.log';
        
        // S'assurer que le fichier est accessible en écriture
        if (!file_exists($this->logFile)) {
            @file_put_contents($this->logFile, "--- Initialisation du Log Checker ---\n");
        }
    }

    private function log($message) {
        @file_put_contents($this->logFile, date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
    }

    public function check($url) {
        $this->log("Vérification de l'URL : $url");
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
        
        // Options pour éviter les problèmes SSL sur certains environnements locaux
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        // Timeout pour éviter de bloquer le script trop longtemps
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            $this->log("ERREUR cURL : $error");
        }
        
        $this->log("Code HTTP : $httpCode");

        if ($httpCode != 200 || !$html) {
            $this->log("ÉCHEC : Code HTTP non-200 ou contenu vide.");
            return 'error';
        }

        // --- PRIORITÉ ABSOLUE : La balise <span class="s24"> ---
        if (preg_match('/<span[^>]*class=["\']s24["\'][^>]*>.*?(\d+).*?<\/span>/is', $html, $matches)) {
            $stock = intval($matches[1]);
            $this->log("Stock trouvé (s24) : $stock");
            
            if ($stock > 0) {
                return 'available';
            } else {
                return 'out_of_stock';
            }
        }

        // --- SECURITÉ : Si la balise s24 n'est pas trouvée ---
        
        if (stripos($html, 'Victime de son succès') !== false || 
            stripos($html, 'Epuisé') !== false ||
            stripos($html, 'id="produit-epuise"') !== false) {
            $this->log("Marqueur de rupture trouvé.");
            return 'out_of_stock';
        }

        if (stripos($html, 'images/vp-imprime-coupon.png') !== false) {
             $this->log("Image coupon trouvée.");
             return 'available';
        }

        if (stripos($html, 'id="form_add_cart"') !== false) {
            $this->log("Formulaire panier trouvé.");
            return 'available';
        }
        
        $this->log("RÉSULTAT : Inconnu");
        return 'unknown';
    }
}
?>