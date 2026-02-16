<?php
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/MonitoredUrl.php';
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
        
        // Actions publiques
        if ($action === 'login') {
            $this->login();
            return;
        }
        if ($action === 'register') {
            $this->register();
            return;
        }
        if ($action === 'logout') {
            $this->logout();
            return;
        }

        // Actions protégées
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Non autorisé']);
            return;
        }

        switch ($action) {
            case 'list':
                $this->listUrls();
                break;
            case 'add':
                $this->addUrl();
                break;
            case 'delete':
                $this->deleteUrl();
                break;
            case 'check_all':
                $this->checkAll();
                break;
            case 'get_user_info':
                $this->getUserInfo();
                break;
            case 'update_notification_email':
                $this->updateNotificationEmail();
                break;
            default:
                echo json_encode(['error' => 'Action inconnue']);
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
        $email = $data['email'] ?? '';
        $pass = $data['password'] ?? '';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Email invalide']);
            return;
        }

        if (strlen($pass) < 6) {
            echo json_encode(['success' => false, 'message' => 'Le mot de passe doit faire au moins 6 caractères']);
            return;
        }

        $userModel = new User($this->pdo);
        if ($userModel->exists($email)) {
            echo json_encode(['success' => false, 'message' => 'Cet email est déjà utilisé']);
            return;
        }

        $userId = $userModel->create($email, $pass);
        if ($userId) {
            $_SESSION['user_id'] = $userId;
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'inscription']);
        }
    }

    private function logout() {
        session_destroy();
        echo json_encode(['success' => true]);
    }

    private function listUrls() {
        $urlModel = new MonitoredUrl($this->pdo);
        echo json_encode($urlModel->findAllByUserId($_SESSION['user_id']));
    }

    private function addUrl() {
        $data = json_decode(file_get_contents('php://input'), true);
        $url = $data['url'] ?? '';
        
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            $urlModel = new MonitoredUrl($this->pdo);
            $urlModel->create($_SESSION['user_id'], $url);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'URL invalide']);
        }
    }

    private function deleteUrl() {
        $data = json_decode(file_get_contents('php://input'), true);
        $urlModel = new MonitoredUrl($this->pdo);
        
        if ($urlModel->delete($data['id'] ?? 0, $_SESSION['user_id'])) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Impossible de supprimer cet élément']);
        }
    }

    private function checkAll() {
        $urlModel = new MonitoredUrl($this->pdo);
        $checker = new StockChecker();
        $emailService = new EmailService($this->emailConfig);
        $userModel = new User($this->pdo);
        
        // Récupérer toutes les URLs de l'utilisateur connecté
        $urls = $urlModel->findAllByUserIdWithEmail($_SESSION['user_id']);
        
        // Récupérer l'email de notification de l'utilisateur
        $userInfo = $userModel->findById($_SESSION['user_id']);
        $notificationEmail = !empty($userInfo['notification_email']) ? $userInfo['notification_email'] : $userInfo['email'];

        foreach ($urls as $item) {
            $newStatus = $checker->check($item['url']);
            
            // Si le statut change vers "available"
            if ($newStatus === 'available' && $item['last_status'] !== 'available') {
                $emailService->sendStockNotification($notificationEmail, $item['url']);
            }

            // Mise à jour du statut en base
            $urlModel->updateStatus($item['id'], $newStatus);
        }
        
        echo json_encode(['success' => true]);
    }

    private function getUserInfo() {
        $userModel = new User($this->pdo);
        $user = $userModel->findById($_SESSION['user_id']);
        echo json_encode($user);
    }

    private function updateNotificationEmail() {
        $data = json_decode(file_get_contents('php://input'), true);
        $email = $data['email'] ?? '';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Email invalide']);
            return;
        }

        $userModel = new User($this->pdo);
        if ($userModel->updateNotificationEmail($_SESSION['user_id'], $email)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour']);
        }
    }
}
?>