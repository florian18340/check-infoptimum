<?php
// --- Worker Script Autonome ---
// Ce fichier est le seul à déployer sur vos serveurs secondaires.

// --- CONFIGURATION DU WORKER ---
$main_server_url = 'https://infoptimum.minibudget.fr'; 
$secret_key = 'QtCw5dXV47sf8VUx3WyqCrL558yxnv9kDthP39T86PZDG7k486'; 
$report_email = 'florian.mancieri@gmail.com'; 
// --- FIN CONFIGURATION ---

// --- Initialisation du rapport ---
$errors = [];
$checked_count = 0;

// Sécurité
if (($_GET['secret'] ?? '') !== $secret_key) {
    $errors[] = "Clé secrète invalide ou manquante.";
}

// --- CLASSE STOCKCHECKER INTÉGRÉE ---
class StockChecker {
    public function check($url) {
        $html = @file_get_contents(trim($url));
        if ($html === false) return 'error';
        if (preg_match('/<span[^>]*class=["\']s24["\'][^>]*>.*?(\d+).*?<\/span>/is', $html, $matches)) {
            return (intval($matches[1]) > 0) ? 'available' : 'out_of_stock';
        }
        if (stripos($html, 'Victime de son succès') !== false || stripos($html, 'id="produit-epuise"') !== false) {
            return 'out_of_stock';
        }
        if (stripos($html, 'images/vp-imprime-coupon.png') !== false || stripos($html, 'Imprimez votre coupon') !== false || stripos($html, 'Ajouter au panier') !== false) {
            return 'available';
        }
        return 'unknown';
    }
}
// --- FIN DE LA CLASSE ---

if (empty($errors)) {
    $api_url = $main_server_url . '/worker_api.php?secret=' . urlencode($secret_key);
    $urls_to_check_json = @file_get_contents($api_url);

    if ($urls_to_check_json === false) {
        $errors[] = "Impossible de contacter l'API du serveur principal.";
    } else {
        $urls_to_check = json_decode($urls_to_check_json, true);

        if (!is_array($urls_to_check) || isset($urls_to_check['error'])) {
            $errors[] = "Réponse de l'API invalide. Message : " . ($urls_to_check['error'] ?? 'inconnu');
        } else {
            $checker = new StockChecker();
            foreach ($urls_to_check as $url_info) {
                $new_status = $checker->check($url_info['url']);
                $checked_count++;
                
                if ($new_status !== $url_info['last_status']) {
                    $update_url = $main_server_url . '/update_status.php';
                    $options = [
                        'http' => [
                            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                            'method'  => 'POST',
                            'content' => http_build_query([
                                'secret' => $secret_key,
                                'id' => $url_info['id'],
                                'status' => $new_status
                            ])
                        ]
                    ];
                    $context  = stream_context_create($options);
                    @file_get_contents($update_url, false, $context);
                }
                sleep(rand(5, 10));
            }
        }
    }
}

// Envoi de l'email de rapport
if (!empty($report_email)) {
    $server_ip = $_SERVER['SERVER_ADDR'] ?? 'inconnue';
    $status_subject = empty($errors) ? 'SUCCES' : 'ERREUR';
    $subject = "[Check-Infoptimum] Rapport du Worker " . $server_ip . " - " . $status_subject;
    
    $message = "Rapport d'exécution du worker hébergé sur l'IP " . $server_ip . ".\n\n";
    $message .= "Statut final : " . $status_subject . "\n";
    $message .= "Heure : " . date('Y-m-d H:i:s') . "\n";
    $message .= "Nombre d'URLs vérifiées : " . $checked_count . "\n\n";
    
    if (!empty($errors)) {
        $message .= "Détail des erreurs :\n";
        foreach ($errors as $error) {
            $message .= "- " . $error . "\n";
        }
    } else {
        $message .= "Le worker a terminé son exécution sans rencontrer d'erreur.\n";
    }
    
    $headers = 'From: no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'worker.local');
    
    @mail($report_email, $subject, $message, $headers);
}

// Affichage final pour le cron
if (!empty($errors)) {
    echo "Travail terminé avec des erreurs :\n";
    print_r($errors);
} else {
    echo "Travail terminé. " . $checked_count . " URLs vérifiées.";
}
?>