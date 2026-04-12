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
            // Nouveaux endpoints pour les comptes Infoptimum
            case 'list_accounts': $this->listAccounts(); break;
            case 'add_account': $this->addAccount(); break;
            case 'delete_account': $this->deleteAccount(); break;
            default: echo json_encode(['error' => 'Action inconnue']);
        }
    }

    // ... (Login, Register, Logout, etc. restent identiques)

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

    // --- GESTION DES COMPTES INFOPTIMUM ---

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
        // Ignorer la limite de temps pour cette requête car elle peut être longue avec des "sleep"
        set_time_limit(0);

        $urlModel = new MonitoredUrl($this->pdo);
        $accountModel = new InfoptimumAccount($this->pdo);
        $checker = new StockChecker();
        $emailService = new EmailService($this->emailConfig);
        
        $urls = $urlModel->findAllByUserId($_SESSION['user_id']);
        $userInfo = (new User($this->pdo))->findById($_SESSION['user_id']);
        $notifEmail = !empty($userInfo['notification_email']) ? $userInfo['notification_email'] : $userInfo['email'];

        foreach ($urls as $url) {
            // Pour chaque URL, on vérifie s'il y a des comptes qui peuvent encore commander
            $availableAccounts = $accountModel->findAvailableForUrl($url['id']);
            
            if (empty($availableAccounts)) {
                $newStatus = $checker->checkOnly($url['url']); // Simple vérification sans commande
            } else {
                // S'il reste des comptes, on prend le premier pour la vérification + commande
                $account = $availableAccounts[0];
                $checker->setCredentials($account['email'], $account['password']);
                $newStatus = $checker->check($url['url']);
                
                // On vérifie le résultat réel de l'impression
                if ($newStatus === 'available_and_printed') {
                    $accountModel->markAsOrdered($account['id'], $url['id']);
                    $newStatus = 'available'; // On remet au statut standard pour l'affichage
                }
            }

            if ($newStatus === 'available' && $url['last_status'] !== 'available') {
                $emailService->sendStockNotification($notifEmail, $url['url']);
            }
            $urlModel->updateStatus($url['id'], $newStatus);
            // On ajoute une pause d'une seconde entre chaque vérification quand c'est fait via le front
            sleep(1);
        }
        echo json_encode(['success' => true]);
    }
}
?>