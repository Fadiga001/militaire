<?php

/**
 * Classe utilitaire pour l'authentification
 * Centralise la gestion des utilisateurs connectés
 */

class Auth
{
    private static $user = null;

    /**
     * Démarrer une session sécurisée
     */
    public static function startSecureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start([
                'cookie_lifetime' => 0,
                'cookie_httponly' => true,
                'cookie_secure' => isset($_SERVER['HTTPS']), // true si HTTPS
                'cookie_samesite' => 'Strict',
                'use_strict_mode' => true
            ]);
        }
    }

    /**
     * Vérifier si l'utilisateur est connecté
     */
    public static function check(): bool
    {
        self::startSecureSession();
        return isset($_SESSION['user_id']);
    }

    /**
     * Exiger authentification (rediriger si non connecté)
     */
    public static function requireAuth(string $redirectTo = '../../index.php'): void
    {
        if (!self::check()) {
            header("Location: $redirectTo");
            exit();
        }
    }

    /**
     * Récupérer l'utilisateur connecté
     */
    public static function user(): ?array
    {
        if (self::$user === null && self::check()) {
            global $pdo;
            $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? AND is_active = TRUE');
            $stmt->execute([$_SESSION['user_id']]);
            self::$user = $stmt->fetch() ?: null;
        }
        return self::$user;
    }

    /**
     * ID de l'utilisateur connecté
     */
    public static function id(): ?int
    {
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Nom complet de l'utilisateur
     */
    public static function name(): string
    {
        $user = self::user();
        return $user ? htmlspecialchars($user['nom'] . ' ' . $user['prenom']) : 'Invité';
    }

    /**
     * Rôle de l'utilisateur
     */
    public static function role(): ?string
    {
        return $_SESSION['user_role'] ?? null;
    }

    /**
     * Vérifier si l'utilisateur est admin
     */
    public static function isAdmin(): bool
    {
        return self::role() === 'ADMIN';
    }

    /**
     * Connexion utilisateur
     */
    public static function login(int $userId, string $username, string $role): void
    {
        self::startSecureSession();

        // Régénérer l'ID de session (protection session fixation)
        session_regenerate_id(true);

        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $username;
        $_SESSION['user_role'] = $role;
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
    }

    /**
     * Déconnexion utilisateur
     */
    public static function logout(): void
    {
        self::startSecureSession();

        $_SESSION = [];

        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }

        session_destroy();
    }

    /**
     * Vérifier timeout de session (30 minutes)
     */
    public static function checkTimeout(int $timeoutMinutes = 30): bool
    {
        if (isset($_SESSION['last_activity'])) {
            $elapsed = time() - $_SESSION['last_activity'];
            if ($elapsed > ($timeoutMinutes * 60)) {
                self::logout();
                return false;
            }
        }
        $_SESSION['last_activity'] = time();
        return true;
    }
}

// Utilisation dans les pages:
/*
// Démarrer session sécurisée (au lieu de session_start())
Auth::startSecureSession();

// Exiger authentification
Auth::requireAuth();

// Récupérer infos utilisateur
$name = Auth::name();
$userId = Auth::id();
$isAdmin = Auth::isAdmin();

// Vérifier timeout
if (!Auth::checkTimeout(30)) {
    header('Location: ../../index.php?timeout=1');
    exit();
}
*/