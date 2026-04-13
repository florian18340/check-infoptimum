<?php
class StockChecker {
    private $logFile;
    private $proxyConfig;

    public function __construct($proxyConfig = []) {
        $this->logFile = __DIR__ . '/../checker.log';
        $this->proxyConfig = $proxyConfig;
    }

    private function log($message) {
        @file_put_contents($this->logFile, date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
    }

    public function check($url) {
        $this->log("Vérification (file_get_contents) de l'URL : $url");

        $headers = "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:122.0) Gecko/20100101 Firefox/122.0\r\n" .
                   "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8\r\n" .
                   "Accept-Language: fr,fr-FR;q=0.8,en-US;q=0.5,en;q=0.3\r\n";

        $options = [
            'http' => [
                'method' => 'GET',
                'timeout' => 20,
                'follow_location' => 1,
                'ignore_errors' => true
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ];

        // --- Intégration du Proxy ---
        if (!empty($this->proxyConfig['host'])) {
            $this->log("Utilisation du proxy : " . $this->proxyConfig['host']);
            
            $proxyUrl = 'tcp://' . $this->proxyConfig['host'] . ':' . $this->proxyConfig['port'];
            $options['http']['proxy'] = $proxyUrl;
            
            if (!empty($this->proxyConfig['user'])) {
                $auth = base64_encode($this->proxyConfig['user'] . ':' . $this->proxyConfig['pass']);
                $headers .= "Proxy-Authorization: Basic $auth\r\n";
            }
        }
        
        $options['http']['header'] = $headers;
        $context = stream_context_create($options);
        $html = @file_get_contents(trim($url), false, $context);

        $http_status = $http_response_header[0] ?? 'HTTP/1.1 404 Not Found';
        if (strpos($http_status, '200 OK') === false) {
            $this->log("Erreur HTTP : " . $http_status);
            return 'error';
        }

        if ($html === false) {
            $this->log("Erreur : file_get_contents a échoué.");
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