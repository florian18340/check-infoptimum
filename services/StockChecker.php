<?php
class StockChecker {
    private $logFile;
    private $cookieFile;

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
        return mt_rand(1, 255) . "." . mt_rand(1, 255) . "." . mt_rand(1, 255) . "." . mt_rand(1, 255);
    }

    private function getRandomUserAgent() {
        $agents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/119.0',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Mobile/15E148 Safari/604.1'
        ];
        return $agents[array_rand($agents)];
    }

    public function check($url) {
        $this->log("Vérification de l'URL : $url");
        $randomIp = $this->generateRandomIp();
        $this->log("IP simulée : $randomIp");
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, trim($url));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
        
        $headers = [
            'User-Agent: ' . $this->getRandomUserAgent(),
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
            'Accept-Language: fr-FR,fr;q=0.9,en-US;q=0.8,en;q=0.7',
            'Referer: https://www.bourges.infoptimum.com/',
            'Connection: keep-alive',
            'Upgrade-Insecure-Requests: 1',
            // --- SIMULATION D'IP ---
            'X-Forwarded-For: ' . $randomIp,
            'X-Real-IP: ' . $randomIp,
            'Client-IP: ' . $randomIp,
            'Via: 1.1 ' . $randomIp
        ];
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        
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
            $this->log("ÉCHEC : Accès refusé ($httpCode) ou contenu vide.");
            return 'error';
        }

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