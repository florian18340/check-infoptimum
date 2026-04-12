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
        
        $loginUrl = "https://www.bourges.infoptimum.com/identifiez-vous2.php";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        if (file_exists($this->cookieFile)) { @unlink($this->cookieFile); }
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
        
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $headers = [
            'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
            'Accept-Language: fr-FR,fr;q=0.9,en-US;q=0.8,en;q=0.7',
            'Cache-Control: max-age=0',
            'Connection: keep-alive',
            'Origin: https://www.bourges.infoptimum.com',
            'Referer: https://www.bourges.infoptimum.com/identifiez-vous.php',
            'Sec-Ch-Ua: "Chromium";v="146", "Not-A.Brand";v="24", "Google Chrome";v="146"',
            'Sec-Ch-Ua-Mobile: ?0',
            'Sec-Ch-Ua-Platform: "macOS"',
            'Sec-Fetch-Dest: document',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: same-origin',
            'Sec-Fetch-User: ?1',
            'Upgrade-Insecure-Requests: 1',
            'Content-Type: application/x-www-form-urlencoded'
        ];
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'email' => $this->infoptimum_email, 
            'pass' => $this->infoptimum_pass
        ]));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);
        
        if (stripos($effectiveUrl, 'mon-compte.php') !== false || stripos($response, 'Déconnexion') !== false) {
            $this->log("Connexion Infoptimum REUSSIE pour " . $this->infoptimum_email);
            return true;
        }
        
        $this->log("Echec de la connexion Infoptimum pour " . $this->infoptimum_email . " (Code: $httpCode, URL: $effectiveUrl)");
        return false;
    }

    private function autoPrint($html) {
        $this->log("Tentative d'impression / ajout au panier...");
        
        if (preg_match('/<form[^>]*action=["\']([^"\']*)["\'][^>]*>.*?(Imprimez|Ajouter|Panier)/is', $html, $m)) {
            $action = $m[1];
            preg_match_all('/<input[^>]*name=["\'](.*?)["\'][^>]*value=["\'](.*?)["\'][^>]*>/is', $m[0], $inputs);
            $data = [];
            if (!empty($inputs[1])) {
                $data = array_combine($inputs[1], $inputs[2]);
            }

            $this->log("Soumission du formulaire d'impression vers : $action");
            $ch = curl_init($action);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
            curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
            curl_setopt($ch, CURLOPT_POST, true);
            if (!empty($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            }
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $headers = [
                'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36',
                'Origin: https://www.bourges.infoptimum.com',
                'Content-Type: application/x-www-form-urlencoded'
            ];
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            
            $response = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $this->log("Résultat de l'impression (Code HTTP: $code)");
            if (stripos($response, 'Le produit a été ajouté') !== false || stripos($response, 'Panier') !== false || stripos($response, 'Imprim') !== false || $code == 302 || $code == 200) {
                 $this->log("Impression potentiellement REUSSIE !");
                 return true;
            }
        } elseif (preg_match('/<a[^>]*href=["\']([^"\']*(?:imprime|coupon|panier)[^"\']*)["\'][^>]*>/i', $html, $m)) {
            $action = "https://www.bourges.infoptimum.com/" . ltrim($m[1], '/');
            $this->log("Suivi du lien d'impression : $action");
            
            $ch = curl_init($action);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
            curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $this->log("Lien cliqué (Code: $code)");
            return true;
        } else {
             $this->log("Formulaire ou lien d'impression INTROUVABLE dans le HTML.");
        }
        return false;
    }

    public function check($url) {
        $isLoggedIn = $this->loginInfoptimum();
        return $this->performCheck($url, $isLoggedIn);
    }

    public function checkOnly($url) {
        // Vider les cookies pour être 100% sûr de faire une requête anonyme
        if (file_exists($this->cookieFile)) { @unlink($this->cookieFile); }
        return $this->performCheck($url, false);
    }

    private function performCheck($url, $shouldPrint) {
        $this->log("Verification URL : $url (Avec connexion: " . ($shouldPrint ? "OUI" : "NON") . ")");
        $ch = curl_init(trim($url));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $headers = [
            'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
            'Accept-Language: fr-FR,fr;q=0.9,en-US;q=0.8,en;q=0.7',
            'Connection: keep-alive',
            'Upgrade-Insecure-Requests: 1'
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
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
        } elseif (stripos($html, 'images/vp-imprime-coupon.png') !== false || stripos($html, 'Imprimez votre coupon') !== false || stripos($html, 'Ajouter au panier') !== false) {
            $status = 'available';
        }

        $this->log("Statut detecté : $status");

        if ($status === 'available' && $shouldPrint) {
            $printSuccess = $this->autoPrint($html);
            if ($printSuccess) {
                return 'available_and_printed';
            } else {
                // S'il est disponible mais que l'impression échoue (pas de bouton), 
                // c'est sûrement que ce compte a déjà commandé.
                return 'out_of_stock_for_account'; 
            }
        } elseif ($shouldPrint && $status !== 'available') {
            $this->log("Stock indisponible pour ce compte, on n'imprime pas.");
            return 'out_of_stock_for_account';
        }

        return $status;
    }
}
?>