<?php
class StockChecker {
    private $logFile;
    private $cookieFile;
    private $proxyConfig;
    private $maxRetries = 2; // 2 tentatives pour aller plus vite
    private $retryDelay = 3;

    public function __construct($proxyConfig = []) {
        $this->logFile = __DIR__ . '/../checker.log';
        $this->cookieFile = __DIR__ . '/../cookies.txt';
        $this->proxyConfig = $proxyConfig;
        // Vider le log à chaque exécution du cron pour ne pas qu'il grossisse indéfiniment
        if (php_sapi_name() == "cli") { // Uniquement si lancé en ligne de commande (cron)
            @file_put_contents($this->logFile, "--- Log du " . date('Y-m-d H:i:s') . " ---\n");
        }
    }

    private function log($message) {
        @file_put_contents($this->logFile, date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
    }

    public function check($url) {
        $this->log("Vérification de l'URL : $url");
        
        for ($i = 0; $i < $this->maxRetries; $i++) {
            if (file_exists($this->cookieFile)) {
                @unlink($this->cookieFile);
            }

            $ch = curl_init(trim($url));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_ENCODING, ''); 
            curl_setopt($ch, CURLOPT_TIMEOUT, 25);
            
            if (!empty($this->proxyConfig['host'])) {
                $this->log(" -> Tentative " . ($i + 1) . " via proxy " . $this->proxyConfig['host']);
                curl_setopt($ch, CURLOPT_PROXY, $this->proxyConfig['host']);
                curl_setopt($ch, CURLOPT_PROXYPORT, $this->proxyConfig['port']);
                if (!empty($this->proxyConfig['user'])) {
                    curl_setopt($ch, CURLOPT_PROXYUSERPWD, $this->proxyConfig['user'] . ':' . $this->proxyConfig['pass']);
                    curl_setopt($ch, CURLOPT_PROXYAUTH, CURLAUTH_BASIC);
                }
            } else {
                 $this->log(" -> Tentative " . ($i + 1) . " en direct");
            }
            
            $headers = [
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:122.0) Gecko/20100101 Firefox/122.0',
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Accept-Language: fr,fr-FR;q=0.8,en-US;q=0.5,en;q=0.3',
                'Connection: keep-alive',
                'Upgrade-Insecure-Requests: 1',
            ];
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            
            $html = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($httpCode == 200 && !empty($html)) {
                $this->log(" -> SUCCES (Code 200)");
                
                // --- Détection du stock ---
                if (preg_match('/<span[^>]*class=["\']s24["\'][^>]*>.*?(\d+).*?<\/span>/is', $html, $matches)) {
                    $stock = intval($matches[1]);
                    return ($stock > 0) ? 'available' : 'out_of_stock';
                }
                if (stripos($html, 'Victime de son succès') !== false || stripos($html, 'id="produit-epuise"') !== false) {
                    return 'out_of_stock';
                }
                if (stripos($html, 'images/vp-imprime-coupon.png') !== false || stripos($html, 'Imprimez votre coupon') !== false || stripos($html, 'Ajouter au panier') !== false) {
                    return 'available';
                }
                return 'unknown';
            }
            
            $this->log(" -> ECHEC (Code: $httpCode, Erreur cURL: " . ($curlError ?: 'aucune') . ")");
            if ($i < $this->maxRetries - 1) {
                sleep($this->retryDelay);
            }
        }
        
        $this->log("Toutes les tentatives ont échoué pour l'URL : $url");
        return 'error';
    }
}
?>