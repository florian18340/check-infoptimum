<?php
class StockChecker {
    private $logFile;
    private $cookieFile;
    private $proxyConfig;

    public function __construct($proxyConfig = []) {
        $this->logFile = __DIR__ . '/../checker.log';
        $this->cookieFile = __DIR__ . '/../cookies.txt';
        $this->proxyConfig = $proxyConfig;
    }

    private function log($message) {
        @file_put_contents($this->logFile, date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
    }

    public function check($url) {
        $this->log("Verification (anonyme) de l'URL : $url");
        
        if (file_exists($this->cookieFile)) {
            @unlink($this->cookieFile);
        }

        $ch = curl_init(trim($url));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_ENCODING, ''); 
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        
        // --- Intégration du Proxy ---
        if (!empty($this->proxyConfig['host'])) {
            $this->log("Utilisation du proxy : " . $this->proxyConfig['host']);
            curl_setopt($ch, CURLOPT_PROXY, $this->proxyConfig['host']);
            curl_setopt($ch, CURLOPT_PROXYPORT, $this->proxyConfig['port']);
            if (!empty($this->proxyConfig['user'])) {
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, $this->proxyConfig['user'] . ':' . $this->proxyConfig['pass']);
                curl_setopt($ch, CURLOPT_PROXYAUTH, CURLAUTH_BASIC);
            }
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
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $this->log("ERREUR cURL : $error");
        }

        if ($code != 200) {
            $this->log("Erreur HTTP lors de la verification ($code)");
            return 'error';
        }

        // --- Détection du stock ---
        if (preg_match('/<span[^>]*class=["\']s24["\'][^>]*>.*?(\d+).*?<\/span>/is', $html, $matches)) {
            $stock = intval($matches[1]);
            $this->log("Stock trouvé (s24) : $stock");
            return ($stock > 0) ? 'available' : 'out_of_stock';
        }

        if (stripos($html, 'Victime de son succès') !== false || stripos($html, 'id="produit-epuise"') !== false) {
            $this->log("Marqueur de rupture trouvé.");
            return 'out_of_stock';
        }

        if (stripos($html, 'images/vp-imprime-coupon.png') !== false || stripos($html, 'Imprimez votre coupon') !== false || stripos($html, 'Ajouter au panier') !== false) {
            $this->log("Marqueur de disponibilité trouvé.");
            return 'available';
        }
        
        $this->log("Aucun marqueur clair trouvé, statut inconnu.");
        return 'unknown';
    }
}
?>