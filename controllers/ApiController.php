<?php
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/MonitoredUrl.php';
require_once __DIR__ . '/../models/InfoptimumAccount.php';
require_once __DIR__ . '/../services/StockChecker.php';
require_once __DIR__ . '/../services/EmailService.php';

class ApiController {
    private $pdo;
    private $emailConfig;

    public function __construct($pdo, $emailConfig) {
        $this->pdo = $pdo;
        $this->emailConfig = $emailConfig;
    }

    public function handleRequest() {
        $action = $_GET['action'] ?? '';
        
        if ($action === 'login') { $this->login(); return; }
        if ($action === 'register') { $this->register(); return; }
        if ($action === 'logout') { $this->logout(); return; }

        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Non autorisé']);
            return;
        }

        switch ($action) {
            case 'list': $this->listUrls(); break;
            case 'add': $this->addUrl(); break;
            case 'delete': $this->deleteUrl(); break;
            case 'check_all': $this->checkAll(); break;
            case 'get_user_info': $this->getUserInfo(); break;
            case 'update_notification_email': $this->updateNotificationEmail(); break;
            case 'list_accounts': $this->listAccounts(); break;
            case 'add_account': $this->addAccount(); break;
            case 'delete_account': $this->deleteAccount(); break;
            default: echo json_encode(['error' => 'Action inconnue']);
        }
    }

    private function login() {
        $data = json_decode(file_get_contents('php://input'), true);
        $userModel = new User($this->pdo);
        $user = $userModel->findByEmail($data['email'] ?? '');
        if ($user && password_verify($data['password'] ?? '', $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Identifiants incorrects']);
        }
    }

    private function register() {
        $data = json_decode(file_get_contents('php://input'), true);
        $userModel = new User($this->pdo);
        if ($userModel->exists($data['email'] ?? '')) {
            echo json_encode(['success' => false, 'message' => 'Email utilisé']);
            return;
        }
        $userId = $userModel->create($data['email'] ?? '', $data['password'] ?? '');
        if ($userId) { $_SESSION['user_id'] = $userId; echo json_encode(['success' => true]); }
        else { echo json_encode(['success' => false]); }
    }

    private function logout() { session_destroy(); echo json_encode(['success' => true]); }

    private function listUrls() {
        $urlModel = new MonitoredUrl($this->pdo);
        echo json_encode($urlModel->findAllByUserId($_SESSION['user_id']));
    }

    private function addUrl() {
        $data = json_decode(file_get_contents('php://input'), true);
        if (filter_var($data['url'] ?? '', FILTER_VALIDATE_URL)) {
            $urlModel = new MonitoredUrl($this->pdo);
            $urlModel->create($_SESSION['user_id'], $data['url']);
            echo json_encode(['success' => true]);
        } else { echo json_encode(['success' => false]); }
    }

    private function deleteUrl() {
        $data = json_decode(file_get_contents('php://input'), true);
        $urlModel = new MonitoredUrl($this->pdo);
        echo json_encode(['success' => $urlModel->delete($data['id'] ?? 0, $_SESSION['user_id'])]);
    }

    private function getUserInfo() {
        $userModel = new User($this->pdo);
        echo json_encode($userModel->findById($_SESSION['user_id']));
    }

    private function updateNotificationEmail() {
        $data = json_decode(file_get_contents('php://input'), true);
        $userModel = new User($this->pdo);
        echo json_encode(['success' => $userModel->updateNotificationEmail($_SESSION['user_id'], $data['email'] ?? '')]);
    }

    private function listAccounts() {
        $accountModel = new InfoptimumAccount($this->pdo);
        echo json_encode($accountModel->findAllByUserId($_SESSION['user_id']));
    }

    private function addAccount() {
        $data = json_decode(file_get_contents('php://input'), true);
        $accountModel = new InfoptimumAccount($this->pdo);
        if (!empty($data['email']) && !empty($data['password'])) {
            $accountModel->create($_SESSION['user_id'], $data['email'], $data['password']);
            echo json_encode(['success' => true]);
        } else { echo json_encode(['success' => false]); }
    }

    private function deleteAccount() {
        $data = json_decode(file_get_contents('php://input'), true);
        $accountModel = new InfoptimumAccount($this->pdo);
        echo json_encode(['success' => $accountModel->delete($data['id'] ?? 0, $_SESSION['user_id'])]);
    }

    private function checkAll() {
        set_time_limit(0); // Ignorer le temps d'exécution maximum pour cette tâche

        $urlModel = new MonitoredUrl($this->pdo);
        $accountModel = new InfoptimumAccount($this->pdo);
        $checker = new StockChecker();
        $emailService = new EmailService($this->emailConfig);
        
        $urls = $urlModel->findAllByUserId($_SESSION['user_id']);
        $userInfo = (new User($this->pdo))->findById($_SESSION['user_id']);
        $notifEmail = !empty($userInfo['notification_email']) ? $userInfo['notification_email'] : $userInfo['email'];

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

            if ($finalStatus === 'available' && $url['last_status'] !== 'available') {
                $emailService->sendStockNotification($notifEmail, $url['url']);
            }
            $urlModel->updateStatus($url['id'], $finalStatus);
            sleep(1);
        }
        echo json_encode(['success' => true]);
    }
}
?>