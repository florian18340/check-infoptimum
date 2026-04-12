<?php
exit;
date_default_timezone_set('Europe/Paris');
if (date('N') > 6 || (int)date('H') < 8 || (int)date('H') >= 19) exit;

require_once 'config.php';
require_once 'models/MonitoredUrl.php';
require_once 'models/User.php';
require_once 'models/InfoptimumAccount.php';
require_once 'services/StockChecker.php';
require_once 'services/EmailService.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) { die("DB Error"); }

$urlModel = new MonitoredUrl($pdo);
$accountModel = new InfoptimumAccount($pdo);
$checker = new StockChecker();
$emailService = new EmailService([
    'smtp_host' => $smtp_host ?? '',
    'smtp_port' => $smtp_port ?? 587,
    'smtp_user' => $smtp_user ?? '',
    'smtp_pass' => $smtp_pass ?? '',
    'smtp_from' => $smtp_from ?? 'no-reply@check-infoptimum.local',
    'smtp_secure' => $smtp_secure ?? 'tls'
]);

$stmt = $pdo->query("SELECT m.*, u.email as user_email, u.notification_email FROM monitored_urls m JOIN users u ON m.user_id = u.id");
$urls = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($urls as $url) {
    // 1. Vérification Globale (Anonyme)
    $globalStatus = $checker->checkOnly($url['url']);
    $finalStatus = $globalStatus;
    
    // 2. Si du stock est dispo globalement, on tente avec les comptes un par un
    if ($globalStatus === 'available') {
        $availableAccounts = $accountModel->findAvailableForUrl($url['id']);
        
        foreach ($availableAccounts as $account) {
            $checker->setCredentials($account['email'], $account['password']);
            $accountStatus = $checker->check($url['url']);
            
            if ($accountStatus === 'available_and_printed') {
                // L'impression a réussi ! On enregistre pour ne plus utiliser ce compte sur cette vente
                $accountModel->markAsOrdered($account['id'], $url['id']);
                $finalStatus = 'available'; // Statut global pour l'UI
                break; // On arrête la boucle des comptes, la commande est passée
            } elseif ($accountStatus === 'out_of_stock_for_account') {
                // Ce compte ne peut pas imprimer (déjà utilisé, quota atteint, etc.)
                // On l'enregistre comme "failed" pour ne pas réessayer inutilement la prochaine fois
                $accountModel->markAsOrdered($account['id'], $url['id'], 'failed');
                // On continue la boucle pour essayer avec le compte suivant
                continue; 
            }
        }
        
        // Si on a épuisé tous les comptes sans succès (tous en "failed")
        if (empty($availableAccounts)) {
             $finalStatus = 'available'; // Il y a du stock, mais aucun compte pour commander
        }
    }

    $targetEmail = !empty($url['notification_email']) ? $url['notification_email'] : $url['user_email'];

    if ($finalStatus === 'available' && $url['last_status'] !== 'available') {
        $emailService->sendStockNotification($targetEmail, $url['url']);
    }

    if ($finalStatus === 'error' && $url['last_status'] !== 'error') {
        $emailService->sendErrorNotification($targetEmail, $url['url']);
    }

    $urlModel->updateStatus($url['id'], $finalStatus);
    sleep(rand(2, 5));
}
?>