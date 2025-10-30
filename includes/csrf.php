<?php

/**
 * Protection CSRF - Cross-Site Request Forgery
 * À inclure dans tous les formulaires
 */

class CSRF
{
    /**
     * Générer un token CSRF unique
     */
    public static function generateToken(): string
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Valider un token CSRF
     */
    public static function validateToken(?string $token): bool
    {
        if (!isset($_SESSION['csrf_token']) || !$token) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Générer un champ HTML hidden avec le token
     */
    public static function field(): string
    {
        $token = self::generateToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }

    /**
     * Vérifier et valider le token depuis POST/GET
     */
    public static function verify(): void
    {
        $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? null;

        if (!self::validateToken($token)) {
            http_response_code(403);
            die('Invalid CSRF token. Your session may have expired.');
        }
    }
}

// Utilisation dans les formulaires:
/*
<!-- Ajouter dans le formulaire -->
<form method="POST">
    <?= CSRF::field() ?>
<!-- ... autres champs ... -->
</form>

// Valider au début du traitement POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
CSRF::verify();
// ... traitement ...
}
*/