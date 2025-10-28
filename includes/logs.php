<?php

if (!function_exists('log_activity')) {
    /**
     * Insère une activité dans la table logs_activites
     */
    function log_activity(PDO $pdo, int $userId, string $action, ?string $details = null): void
    {
        try {
            $stmt = $pdo->prepare('INSERT INTO logs_activites (id_utilisateur, action, details) VALUES (:uid, :action, :details)');
            $stmt->execute([
                ':uid' => $userId,
                ':action' => $action,
                ':details' => $details,
            ]);
        } catch (Throwable $e) {
            // Ne pas bloquer le flux applicatif si le log échoue
        }
    }
}





