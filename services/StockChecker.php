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
        
        $loginUrl = "https://www.bourges.infoptimum.com/identification.html";
        $ch = curl_init($loginUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['email' => $this->infoptimum_email, 'pass' => $this->infoptimum_pass, 'action' => 'ident']));
        $response = curl_exec($ch);
        curl_close($ch);
        
        return (stripos($response, 'Déconnexion') !== false);
    }

    private function autoPrint($html) {
        if (preg_match('/<form[^>]*id=["\']form_add_cart["\'][^>]*action=["\'](.*?)["\'][^>]*>(.*?)<\/form>/is', $html, $m)) {
            $action = $m[1];
            preg_match_all('/<input[^>]*name=["\'](.*?)["\'][^>]*value=["\'](.*?)["\'][^>]*>/is', $m[2], $inputs);
            $data = array_combine($inputs[1], $inputs[2]);

            $ch = curl_init($action);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_exec($ch);
            curl_close($ch);
            return true;
        }
        return false;
    }

    public function check($url) {
        $this->loginInfoptimum();
        return $this->performCheck($url, true);
    }

    public function checkOnly($url) {
        return $this->performCheck($url, false);
    }

    private function performCheck($url, $shouldPrint) {
        $ch = curl_init(trim($url));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
        $html = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code != 200) return 'error';

        $status = 'out_of_stock';
        if (preg_match('/<span[^>]*class=["\']s24["\'][^>]*>.*?(\d+).*?<\/span>/is', $html, $matches)) {
            $status = (intval($matches[1]) > 0) ? 'available' : 'out_of_stock';
        }

        if ($status === 'available' && $shouldPrint) {
            $this->autoPrint($html);
        }

        return $status;
    }
}
?>