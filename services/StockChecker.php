<?php
class StockChecker {
    private $logFile;
    private $cookieFile;
    private $maxRetries = 2;
    private $infoptimum_email;
    private $infoptimum_pass;

    public function __construct() {
        $this->logFile = __DIR__ . '/../checker.log';
        $this->cookieFile = __DIR__ . '/../cookies.txt';
    }

    public function setCredentials($email, $pass) {
        $this->infoptimum_email = $email;
        $this->infoptimum_pass = $pass;
    }

    private function log($message) {
        @file_put_contents($this->logFile, date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
    }

    private function loginInfoptimum() {
        if (empty($this->infoptimum_email)) return false;
        
        $this->log("Tentative de connexion à Infoptimum avec : " . $this->infoptimum_email);
        
        // Utilisation de la nouvelle URL de connexion
        $loginUrl = "https://www.bourges.infoptimum.com/identifiez-vous.php";
        
        $ch = curl_init($loginUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        // Initialisation propre du cookie
        if (file_exists($this->cookieFile)) { @unlink($this->cookieFile); }
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
        
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        
        curl_setopt($ch, CURLOPT_POST, true);
        // Les noms des champs POST dépendent du formulaire réel d'Infoptimum.
        // Généralement : email/login, password/pass, et peut-être un champ caché.
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'email' => $this->infoptimum_email, 
            'pass' => $this->infoptimum_pass, 
            'action' => 'ident',
            'submit' => 'Valider' // Parfois nécessaire
        ]));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // Vérification de la réussite de la connexion
        // On cherche un élément typique de l'espace membre
        if ($httpCode == 200 && (stripos($response, 'Déconnexion') !== false || stripos($response, 'Mon compte') !== false || stripos($response, 'Mes alertes') !== false)) {
            $this->log("Connexion Infoptimum REUSSIE pour " . $this->infoptimum_email);
            return true;
        }
        
        $this->log("Echec de la connexion Infoptimum pour " . $this->infoptimum_email . " (Code: $httpCode)");
        return false;
    }

    private function autoPrint($html) {
        $this->log("Tentative d'impression / ajout au panier...");
        if (preg_match('/<form[^>]*id=["\']form_add_cart["\'][^>]*action=["\'](.*?)["\'][^>]*>(.*?)<\/form>/is', $html, $m)) {
            $action = $m[1];
            preg_match_all('/<input[^>]*name=["\'](.*?)["\'][^>]*value=["\'](.*?)["\'][^>]*>/is', $m[2], $inputs);
            $data = array_combine($inputs[1], $inputs[2]);

            $this->log("Soumission du formulaire d'impression vers : $action");
            $ch = curl_init($action);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
            curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
            $response = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $this->log("Résultat de l'impression (Code HTTP: $code)");
            if (stripos($response, 'Le produit a été ajouté') !== false || stripos($response, 'Panier') !== false || $code == 302 || $code == 200) {
                 $this->log("Impression potentiellement REUSSIE !");
                 return true;
            }
        } else {
             $this->log("Formulaire d'impression INTROUVABLE dans le HTML.");
        }
        return false;
    }

    public function check($url) {
        $isLoggedIn = $this->loginInfoptimum();
        return $this->performCheck($url, $isLoggedIn);
    }

    public function checkOnly($url) {
        return $this->performCheck($url, false);
    }

    private function performCheck($url, $shouldPrint) {
        $this->log("Verification URL : $url");
        $ch = curl_init(trim($url));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        // Utiliser le cookie si on s'est connecté
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        $html = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code != 200) {
            $this->log("Erreur HTTP lors de la verification ($code)");
            return 'error';
        }

        $status = 'out_of_stock';
        if (preg_match('/<span[^>]*class=["\']s24["\'][^>]*>.*?(\d+).*?<\/span>/is', $html, $matches)) {
            $status = (intval($matches[1]) > 0) ? 'available' : 'out_of_stock';
        } elseif (stripos($html, 'Victime de son succès') !== false || stripos($html, 'id="produit-epuise"') !== false) {
            $status = 'out_of_stock';
        } elseif (stripos($html, 'images/vp-imprime-coupon.png') !== false) {
            $status = 'available';
        }

        $this->log("Statut detecté : $status");

        if ($status === 'available' && $shouldPrint) {
            $this->autoPrint($html);
        } elseif ($shouldPrint && $status !== 'available') {
            $this->log("Stock indisponible, on n'imprime pas.");
        }

        return $status;
    }
}
?>