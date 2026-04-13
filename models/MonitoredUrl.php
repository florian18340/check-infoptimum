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

    public function findAllByUserId($userId) {
        $stmt = $this->pdo->prepare("SELECT * FROM monitored_urls WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function delete($id, $userId) {
        $stmt = $this->pdo->prepare("DELETE FROM monitored_urls WHERE id = ? AND user_id = ?");
        return $stmt->execute([$id, $userId]);
    }

    public function updateStatus($id, $status) {
        // On met à jour le statut ET la date de dernière vérification
        $stmt = $this->pdo->prepare("UPDATE monitored_urls SET last_status = ?, last_check = NOW() WHERE id = ?");
        return $stmt->execute([$status, $id]);
    }
}
?>