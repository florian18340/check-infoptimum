<?php
class EmailService {
    private $config;

    public function __construct($config) {
        $this->config = $config;
    }

    public function sendStockNotification($to, $url) {
        $subject = "Stock Disponible ! - Infoptimum";
        $body = "Bonne nouvelle !\n\nLe produit que vous surveillez est de nouveau en stock :\n$url\n\nFoncez avant qu'il ne soit trop tard !";
        
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
            mail($to, $subject, $body, "From: $from");
            return;
        }

        try {
            $socket = fsockopen(($secure === 'ssl' ? 'ssl://' : '') . $host, $port, $errno, $errstr, 10);
            if (!$socket) {
                error_log("SMTP Connect Error: $errstr ($errno)");
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

        } catch (Exception $e) {
            error_log("SMTP Error: " . $e->getMessage());
        }
    }
}
?>