<?php
class EmailService {
    private $config;
    private $logFile = __DIR__ . '/../email.log';

    public function __construct($config) {
        $this->config = $config;
        if (!file_exists($this->logFile)) {
            @file_put_contents($this->logFile, "--- Initialisation du Log Email Service ---\n");
        }
    }

    private function log($message) {
        @file_put_contents($this->logFile, date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
    }

    public function sendStockNotification($to, $url) {
        $subject = "Stock Disponible ! - Infoptimum";
        $body = "Bonne nouvelle !\n\nLe produit que vous surveillez est de nouveau en stock :\n$url\n\nFoncez avant qu'il ne soit trop tard !";
        
        $this->log("Tentative d'envoi notification stock à : $to");
        $this->sendSmtpEmail($to, $subject, $body);
    }

    public function sendErrorNotification($to, $url) {
        $subject = "Erreur de surveillance - Infoptimum";
        $body = "Attention !\n\nLe système n'a pas pu vérifier le stock pour l'URL suivante :\n$url\n\nCela peut être dû à un blocage du serveur ou à un changement de structure de la page. Veuillez vérifier manuellement.";
        
        $this->log("Tentative d'envoi notification erreur à : $to");
        $this->sendSmtpEmail($to, $subject, $body);
    }

    private function sendSmtpEmail($to, $subject, $body) {
        $host = $this->config['smtp_host'] ?? '';
        $port = $this->config['smtp_port'] ?? 587;
        $user = $this->config['smtp_user'] ?? '';
        $pass = $this->config['smtp_pass'] ?? '';
        $from = $this->config['smtp_from'] ?? 'no-reply@check-infoptimum.local';
        $secure = $this->config['smtp_secure'] ?? 'tls';

        if (empty($host)) {
            $this->log("Aucun hôte SMTP configuré. Utilisation de mail()...");
            mail($to, $subject, $body, "From: $from");
            return;
        }

        try {
            $socket = @fsockopen(($secure === 'ssl' ? 'ssl://' : '') . $host, $port, $errno, $errstr, 10);
            if (!$socket) {
                $this->log("ERREUR de connexion SMTP : $errstr ($errno)");
                return;
            }

            $read = function($socket) {
                $response = '';
                while ($str = fgets($socket, 515)) {
                    $response .= $str;
                    if (substr($str, 3, 1) == ' ') break;
                }
                return $response;
            };

            $read($socket); // Banner

            fputs($socket, "EHLO " . $_SERVER['SERVER_NAME'] . "\r\n");
            $read($socket);

            if ($secure === 'tls') {
                fputs($socket, "STARTTLS\r\n");
                $read($socket);
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                fputs($socket, "EHLO " . $_SERVER['SERVER_NAME'] . "\r\n");
                $read($socket);
            }

            if (!empty($user) && !empty($pass)) {
                fputs($socket, "AUTH LOGIN\r\n");
                $read($socket);
                fputs($socket, base64_encode($user) . "\r\n");
                $read($socket);
                fputs($socket, base64_encode($pass) . "\r\n");
                $read($socket);
            }

            fputs($socket, "MAIL FROM: <$from>\r\n");
            $read($socket);
            fputs($socket, "RCPT TO: <$to>\r\n");
            $read($socket);
            fputs($socket, "DATA\r\n");
            $read($socket);

            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type: text/plain; charset=utf-8\r\n";
            $headers .= "From: $from\r\n";
            $headers .= "To: $to\r\n";
            $headers .= "Subject: $subject\r\n";

            fputs($socket, "$headers\r\n$body\r\n.\r\n");
            $read($socket);
            
            fputs($socket, "QUIT\r\n");
            fclose($socket);
            $this->log("Email envoyé avec succès.");

        } catch (Exception $e) {
            $this->log("SMTP Error Exception: " . $e->getMessage());
        }
    }
}
?>