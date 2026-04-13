<?php
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/MonitoredUrl.php';
require_once __DIR__ . '/../services/StockChecker.php';
require_once __DIR__ . '/../services/EmailService.php';

class ApiController {
    private $pdo;
    private $emailConfig;
    private $proxyConfig;

    public function __construct($pdo, $emailConfig, $proxyConfig) {
        $this->pdo = $pdo;
        $this->emailConfig = $emailConfig;
        $this->proxyConfig = $proxyConfig;
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

    private function checkAll() {
        set_time_limit(0);

        $urlModel = new MonitoredUrl($this->pdo);
        $checker = new StockChecker($this->proxyConfig);
        
        $urls = $urlModel->findAllByUserId($_SESSION['user_id']);

        foreach ($urls as $url) {
            $newStatus = $checker->check($url['url']);
            $urlModel->updateStatus($url['id'], $newStatus);
            sleep(1);
        }
        echo json_encode(['success' => true]);
    }
}
?>