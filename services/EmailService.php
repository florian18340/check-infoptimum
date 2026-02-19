<?php
class EmailService {
    private $config;
    private $logFile = __DIR__ . '/../email.log';

    public function __construct($config) {
        $this->config = $config;
        // Vider le log au début pour ne garder que les infos de la dernière exécution
        file_put_contents($this->logFile, "--- Log Email Service ---\n");
    }

    private function log($message) {
        file_put_contents($this->logFile, date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
    }

    public function sendStockNotification($to, $url) {
        $subject = "Stock Disponible ! - Infoptimum";
        $body = "Bonne nouvelle !\n\nLe produit que vous surveillez est de nouveau en stock :\n$url\n\nFoncez avant qu'il ne soit trop tard !";
        
        $this->log("Tentative d'envoi à : $to");
        $this->sendSmtpEmail($to, $subject, $body);
    }

    private function sendSmtpEmail($to, $subject, $body) {
        $host = $this->config['smtp_host'];
        $port = $this->config['smtp_port'];
        $user = $this->config['smtp_user'];
        $pass = $this->config['smtp_pass'];
        $from = $this->config['smtp_from'];
        $secure = $this->config['smtp_secure'];

        if (empty($host)) {
            $this->log("Aucun hôte SMTP configuré. Utilisation de mail()...");
            if(mail($to, $subject, $body, "From: $from")) {
                $this->log("mail() a retourné true.");
            } else {
                $this->log("mail() a retourné false.");
            }
            return;
        }

        $this->log("Connexion SMTP à $host:$port...");
        $socket = fsockopen(($secure === 'ssl' ? 'ssl://' : '') . $host, $port, $errno, $errstr, 10);
        if (!$socket) {
            $this->log("ERREUR de connexion SMTP : $errstr ($errno)");
            return;
        }
        $this->log("Socket ouvert.");

        $read = function($socket, $logCallback) {
            $response = '';
            while ($str = fgets($socket, 515)) {
                $response .= $str;
                if (substr($str, 3, 1) == ' ') break;
            }
            $logCallback("S: " . trim($response));
            return $response;
        };

        $write = function($socket, $data, $logCallback) {
            $logCallback("C: " . trim($data));
            fputs($socket, $data);
        };

        $read($socket, [$this, 'log']); // Banner

        $write($socket, "EHLO " . $_SERVER['SERVER_NAME'] . "\r\n", [$this, 'log']);
        $read($socket, [$this, 'log']);

        if ($secure === 'tls') {
            $write($socket, "STARTTLS\r\n", [$this, 'log']);
            $read($socket, [$this, 'log']);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                $this->log("ERREUR : Impossible de démarrer TLS.");
                fclose($socket);
                return;
            }
            $this->log("TLS démarré avec succès.");
            $write($socket, "EHLO " . $_SERVER['SERVER_NAME'] . "\r\n", [$this, 'log']);
            $read($socket, [$this, 'log']);
        }

        if (!empty($user) && !empty($pass)) {
            $write($socket, "AUTH LOGIN\r\n", [$this, 'log']);
            $read($socket, [$this, 'log']);
            $write($socket, base64_encode($user) . "\r\n", [$this, 'log']);
            $read($socket, [$this, 'log']);
            $write($socket, base64_encode($pass) . "\r\n", [$this, 'log']);
            $response = $read($socket, [$this, 'log']);
            if (strpos($response, '235') !== 0) {
                $this->log("ERREUR d'authentification.");
                fclose($socket);
                return;
            }
            $this->log("Authentification réussie.");
        }

        $write($socket, "MAIL FROM: <$from>\r\n", [$this, 'log']);
        $read($socket, [$this, 'log']);
        $write($socket, "RCPT TO: <$to>\r\n", [$this, 'log']);
        $read($socket, [$this, 'log']);
        $write($socket, "DATA\r\n", [$this, 'log']);
        $read($socket, [$this, 'log']);

        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/plain; charset=utf-8\r\n";
        $headers .= "From: $from\r\n";
        $headers .= "To: $to\r\n";
        $headers .= "Subject: $subject\r\n";

        $write($socket, "$headers\r\n$body\r\n.\r\n", [$this, 'log']);
        $read($socket, [$this, 'log']);
        
        $write($socket, "QUIT\r\n", [$this, 'log']);
        fclose($socket);
        $this->log("Connexion fermée. Email envoyé (en théorie).");
    }
}
?>