<?php
class Worker {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function create($userId, $url) {
        $stmt = $this->pdo->prepare("INSERT INTO workers (user_id, url) VALUES (?, ?)");
        return $stmt->execute([$userId, $url]);
    }

    public function findAllByUserId($userId) {
        $stmt = $this->pdo->prepare("SELECT * FROM workers WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function findAll() {
        $stmt = $this->pdo->query("SELECT * FROM workers");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function delete($id, $userId) {
        $stmt = $this->pdo->prepare("DELETE FROM workers WHERE id = ? AND user_id = ?");
        return $stmt->execute([$id, $userId]);
    }
}
?>