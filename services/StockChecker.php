<?php
class StockChecker {
    private $logFile;

    public function __construct() {
        $this->logFile = __DIR__ . '/../checker.log';
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
        
        // --- NOUVEAUX EN-TÊTES POUR ÉVITER LE 403 ---
        $headers = [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
            'Accept-Language: fr-FR,fr;q=0.9,en-US;q=0.8,en;q=0.7',
            'Cache-Control: max-age=0',
            'Connection: keep-alive',
            'Upgrade-Insecure-Requests: 1',
            'Sec-Fetch-Dest: document',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: none',
            'Sec-Fetch-User: ?1'
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        // Options SSL pour le local
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            $this->log("ERREUR cURL : $error");
        }
        
        $this->log("Code HTTP reçu : $httpCode");

        if ($httpCode != 200 || !$html) {
            $this->log("ÉCHEC : Accès refusé (403) ou contenu vide.");
            return 'error';
        }

        // --- DÉTECTION DU STOCK ---
        if (preg_match('/<span[^>]*class=["\']s24["\'][^>]*>.*?(\d+).*?<\/span>/is', $html, $matches)) {
            $stock = intval($matches[1]);
            $this->log("Stock trouvé (s24) : $stock");
            return ($stock > 0) ? 'available' : 'out_of_stock';
        }

        if (stripos($html, 'Victime de son succès') !== false || 
            stripos($html, 'Epuisé') !== false ||
            stripos($html, 'id="produit-epuise"') !== false) {
            $this->log("Marqueur de rupture trouvé.");
            return 'out_of_stock';
        }

        if (stripos($html, 'images/vp-imprime-coupon.png') !== false || stripos($html, 'id="form_add_cart"') !== false) {
             $this->log("Marqueur de disponibilité trouvé.");
             return 'available';
        }

        $this->log("RÉSULTAT : Inconnu");
        return 'unknown';
    }
}
?>