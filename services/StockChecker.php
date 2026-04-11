<?php
class StockChecker {
    private $logFile;
    private $cookieFile;
    private $maxRetries = 2; // Réduit à 2 pour être plus rapide
    private $retryDelay = 3;

    public function __construct() {
        $this->logFile = __DIR__ . '/../checker.log';
        $this->cookieFile = __DIR__ . '/../cookies.txt';
        if (!file_exists($this->logFile)) {
            @file_put_contents($this->logFile, "--- Initialisation du Log Checker ---\n");
        }
    }

    private function log($message) {
        @file_put_contents($this->logFile, date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
    }

    private function generateRandomIp() {
        // Générer une IP qui ressemble à une IP résidentielle française (plages communes)
        $ranges = [
            '92.184.' . mt_rand(0, 255) . '.' . mt_rand(0, 255), // Orange
            '176.128.' . mt_rand(0, 255) . '.' . mt_rand(0, 255), // SFR
            '82.64.' . mt_rand(0, 255) . '.' . mt_rand(0, 255),   // Free
        ];
        return $ranges[array_rand($ranges)];
    }

    private function getRandomUserAgent() {
        $agents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        ];
        return $agents[array_rand($agents)];
    }

    public function check($url) {
        $this->log("Vérification : $url");
        
        for ($i = 0; $i < $this->maxRetries; $i++) {
            $randomIp = $this->generateRandomIp();
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, trim($url));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            
            // On réinitialise les cookies à chaque tentative pour éviter les sessions bloquées
            if (file_exists($this->cookieFile)) { @unlink($this->cookieFile); }
            curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
            
            $headers = [
                'User-Agent: ' . $this->getRandomUserAgent(),
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Accept-Language: fr,fr-FR;q=0.8,en-US;q=0.5,en;q=0.3',
                'Referer: https://www.bourges.infoptimum.com/',
                'X-Forwarded-For: ' . $randomIp,
                'Connection: keep-alive'
            ];
            
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_ENCODING, '');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            
            $html = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode == 200 && !empty($html)) {
                // Analyse du stock (s24)
                if (preg_match('/<span[^>]*class=["\']s24["\'][^>]*>.*?(\d+).*?<\/span>/is', $html, $matches)) {
                    $stock = intval($matches[1]);
                    $this->log("Succès (Tentative ".($i+1).") - Stock: $stock");
                    return ($stock > 0) ? 'available' : 'out_of_stock';
                }
                
                // Fallback rupture
                if (stripos($html, 'Victime de son succès') !== false || stripos($html, 'id="produit-epuise"') !== false) {
                    return 'out_of_stock';
                }

                // Fallback dispo
                if (stripos($html, 'images/vp-imprime-coupon.png') !== false) {
                    return 'available';
                }

                return 'unknown';
            }
            
            $this->log("Échec tentative ".($i+1)." (Code: $httpCode)");
            if ($i < $this->maxRetries - 1) sleep($this->retryDelay);
        }
        
        return 'error';
    }
}
?>