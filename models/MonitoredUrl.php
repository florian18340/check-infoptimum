<?php
class MonitoredUrl {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function create($userId, $url) {
        $stmt = $this->pdo->prepare("INSERT INTO monitored_urls (user_id, url) VALUES (?, ?)");
        return $stmt->execute([$userId, $url]);
    }

    public function delete($id, $userId) {
        $stmt = $this->pdo->prepare("DELETE FROM monitored_urls WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        return $stmt->rowCount() > 0;
    }

    public function findAllByUserId($userId) {
        $stmt = $this->pdo->prepare("SELECT * FROM monitored_urls WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findAllByUserIdWithEmail($userId) {
        $stmt = $this->pdo->prepare("SELECT m.*, u.email FROM monitored_urls m JOIN users u ON m.user_id = u.id WHERE m.user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateStatus($id, $status) {
        $stmt = $this->pdo->prepare("UPDATE monitored_urls SET last_status = ?, last_check = NOW() WHERE id = ?");
        return $stmt->execute([$status, $id]);
    }
}
?>