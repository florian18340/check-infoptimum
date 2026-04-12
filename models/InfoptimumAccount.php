<?php
class InfoptimumAccount {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function create($userId, $email, $password) {
        $stmt = $this->pdo->prepare("INSERT INTO infoptimum_accounts (user_id, email, password) VALUES (?, ?, ?)");
        return $stmt->execute([$userId, $email, $password]);
    }

    public function findAllByUserId($userId) {
        $stmt = $this->pdo->prepare("SELECT * FROM infoptimum_accounts WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function delete($id, $userId) {
        $stmt = $this->pdo->prepare("DELETE FROM infoptimum_accounts WHERE id = ? AND user_id = ?");
        return $stmt->execute([$id, $userId]);
    }

    // Récupérer les comptes qui n'ont pas encore commandé pour une URL donnée
    public function findAvailableForUrl($urlId) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM infoptimum_accounts a
            WHERE a.is_active = 1
            AND a.id NOT IN (SELECT account_id FROM order_history WHERE url_id = ?)
        ");
        $stmt->execute([$urlId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function markAsOrdered($accountId, $urlId, $status = 'success') {
        $stmt = $this->pdo->prepare("INSERT INTO order_history (account_id, url_id, status) VALUES (?, ?, ?)");
        return $stmt->execute([$accountId, $urlId, $status]);
    }
}
?>