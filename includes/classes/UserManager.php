<?php

/**
 * Classe UserManager
 * Gestion complète des utilisateurs du système
 */
class UserManager
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Créer un nouvel utilisateur
     */
    public function create(array $data, ?int $createdBy = null): int
    {
        $sql = "INSERT INTO users (username, email, password_hash, nom, prenom, role, is_active, must_change_password, created_by)
                VALUES (:username, :email, :password_hash, :nom, :prenom, :role, :is_active, :must_change_password, :created_by)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':username' => $data['username'],
            ':email' => $data['email'],
            ':password_hash' => $data['password_hash'] ?? (isset($data['password']) ? password_hash($data['password'], PASSWORD_DEFAULT) : ''),
            ':nom' => $data['nom'],
            ':prenom' => $data['prenom'],
            ':role' => $data['role'],
            ':is_active' => $data['is_active'] ? 1 : 0,
            ':must_change_password' => $data['must_change_password'] ? 1 : 0,
            ':created_by' => $createdBy !== null ? $createdBy : null
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Récupérer un utilisateur par ID
     */
    public function getById(int $id): ?array
    {
        $sql = "SELECT u.*,
                creator.username as created_by_username,
                (SELECT COUNT(*) FROM audit_logs WHERE user_id = u.id) as nb_logs
                FROM users u
                LEFT JOIN users creator ON u.created_by = creator.id
                WHERE u.id = ?";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        $result = $stmt->fetch();

        return $result ?: null;
    }

    /**
     * Récupérer tous les utilisateurs avec filtres
     */
    public function getAll(array $filters = []): array
    {
        $sql = "SELECT u.*,
                (SELECT COUNT(*) FROM audit_logs WHERE user_id = u.id) as nb_logs,
                DATEDIFF(NOW(), u.last_login_at) as jours_depuis_connexion
                FROM users u
                WHERE 1=1";

        $params = [];

        if (!empty($filters['role'])) {
            $sql .= " AND u.role = :role";
            $params[':role'] = $filters['role'];
        }

        if (!empty($filters['is_active'])) {
            $sql .= " AND u.is_active = :is_active";
            $params[':is_active'] = $filters['is_active'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (u.username LIKE :search OR u.email LIKE :search OR u.nom LIKE :search OR u.prenom LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $sql .= " ORDER BY u.created_at DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Mettre à jour un utilisateur
     */
    public function update(int $id, array $data, int $updatedBy): bool
    {
        try {
            $sql = "UPDATE users SET
                username = ?,
                email = ?,
                nom = ?,
                prenom = ?,
                role = ?,
                is_active = ?,
                updated_by = ?
                WHERE id = ?";

            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([
                $data['username'],
                $data['email'],
                $data['nom'],
                $data['prenom'],
                $data['role'],
                $data['is_active'] ?? true,
                $updatedBy,
                $id
            ]);
        } catch (PDOException $e) {
            error_log("Erreur mise à jour utilisateur: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Changer le mot de passe
     */
    public function changePassword(int $id, string $newPassword, bool $mustChange = false): bool
    {
        try {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);

            $sql = "UPDATE users SET 
                    password_hash = ?,
                    must_change_password = ?,
                    password_changed_at = NOW(),
                    failed_login_attempts = 0,
                    locked_until = NULL
                    WHERE id = ?";

            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$hash, $mustChange, $id]);
        } catch (PDOException $e) {
            error_log("Erreur changement mot de passe: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Activer/Désactiver un utilisateur
     */
    public function toggleActive(int $id, bool $isActive): bool
    {
        try {
            $sql = "UPDATE users SET is_active = ? WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$isActive, $id]);
        } catch (PDOException $e) {
            error_log("Erreur toggle active: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Déverrouiller un compte
     */
    public function unlock(int $id): bool
    {
        try {
            $sql = "UPDATE users SET 
                    locked_until = NULL,
                    failed_login_attempts = 0
                    WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            error_log("Erreur unlock: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Vérifier si username existe
     */
    public function usernameExists(string $username, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) as nb FROM users WHERE username = ?";
        $params = [$username];

        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetch()['nb'] > 0;
    }

    /**
     * Vérifier si email existe
     */
    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) as nb FROM users WHERE email = ?";
        $params = [$email];

        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetch()['nb'] > 0;
    }

    /**
     * Récupérer l'historique d'activité
     */
    public function getActivityHistory(int $userId, int $limit = 50): array
    {
        $sql = "SELECT * FROM audit_logs
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT ?";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Statistiques des utilisateurs
     */
    public function getStatistiques(): array
    {
        $stats = [];

        // Total utilisateurs
        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM users");
        $stats['total'] = (int)$stmt->fetch()['total'];

        // Par rôle
        $stmt = $this->pdo->query("
            SELECT role, COUNT(*) as nb
            FROM users
            GROUP BY role
        ");
        $stats['par_role'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        // Actifs vs Inactifs
        $stmt = $this->pdo->query("
            SELECT 
                SUM(CASE WHEN is_active = TRUE THEN 1 ELSE 0 END) as actifs,
                SUM(CASE WHEN is_active = FALSE THEN 1 ELSE 0 END) as inactifs
            FROM users
        ");
        $activite = $stmt->fetch();
        $stats['actifs'] = (int)$activite['actifs'];
        $stats['inactifs'] = (int)$activite['inactifs'];

        // Connectés dernières 24h
        $stmt = $this->pdo->query("
            SELECT COUNT(*) as nb 
            FROM users 
            WHERE last_login_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stats['connectes_24h'] = (int)$stmt->fetch()['nb'];

        // Comptes verrouillés
        $stmt = $this->pdo->query("
            SELECT COUNT(*) as nb 
            FROM users 
            WHERE locked_until IS NOT NULL AND locked_until > NOW()
        ");
        $stats['verrouilles'] = (int)$stmt->fetch()['nb'];

        return $stats;
    }

    /**
     * Générer un mot de passe aléatoire sécurisé
     */
    public static function generatePassword(int $length = 12): string
    {
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $numbers = '0123456789';
        $special = '!@#$%&*-_+=';

        $all = $uppercase . $lowercase . $numbers . $special;

        $password = '';

        // Au moins 1 de chaque type
        $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
        $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];
        $password .= $special[random_int(0, strlen($special) - 1)];

        // Compléter
        for ($i = 4; $i < $length; $i++) {
            $password .= $all[random_int(0, strlen($all) - 1)];
        }

        // Mélanger
        return str_shuffle($password);
    }

    /**
     * Vérifier la force d'un mot de passe
     */
    public static function checkPasswordStrength(string $password): array
    {
        $strength = 0;
        $feedback = [];

        if (strlen($password) >= 8) {
            $strength++;
        } else {
            $feedback[] = "Minimum 8 caractères";
        }

        if (preg_match('/[a-z]/', $password)) {
            $strength++;
        } else {
            $feedback[] = "Au moins une minuscule";
        }

        if (preg_match('/[A-Z]/', $password)) {
            $strength++;
        } else {
            $feedback[] = "Au moins une majuscule";
        }

        if (preg_match('/[0-9]/', $password)) {
            $strength++;
        } else {
            $feedback[] = "Au moins un chiffre";
        }

        if (preg_match('/[^a-zA-Z0-9]/', $password)) {
            $strength++;
        } else {
            $feedback[] = "Au moins un caractère spécial";
        }

        return [
            'score' => $strength,
            'level' => $strength < 3 ? 'faible' : ($strength < 4 ? 'moyen' : 'fort'),
            'feedback' => $feedback
        ];
    }
}
