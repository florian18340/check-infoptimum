<?php
class StockChecker {
    private $logFile;

    public function __construct() {
        $this->logFile = __DIR__ . '/../checker.log';
    }

    private function log($message) {
        @file_put_contents($this->logFile, date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
    }

    public function check($url) {
        $this->log("Vérification (file_get_contents simple) de l'URL : $url");

        // On utilise la méthode la plus simple possible, comme dans test_print.php
        $html = @file_get_contents(trim($url));

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