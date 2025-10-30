<?php
session_start();

// Si déjà connecté, rediriger vers le dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: pages/dash/dashboard.php');
    exit();
}

require_once 'includes/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = "Tous les champs sont obligatoires.";
    } else {
        try {
            // Récupérer l'utilisateur
            $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? AND is_active = TRUE');
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user) {
                // Vérifier si le compte est verrouillé
                if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                    $error = "Votre compte est temporairement verrouillé. Réessayez plus tard.";
                } elseif (password_verify($password, $user['password_hash'])) {
                    // Connexion réussie
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['nom_complet'] = $user['nom'] . ' ' . $user['prenom'];

                    // Réinitialiser les tentatives échouées
                    $updateStmt = $pdo->prepare('UPDATE users SET failed_login_attempts = 0, locked_until = NULL, last_login_at = NOW(), last_login_ip = ? WHERE id = ?');
                    $updateStmt->execute([$_SERVER['REMOTE_ADDR'], $user['id']]);

                    // Log de l'activité
                    require_once 'includes/logs.php';
                    log_activity($pdo, $user['id'], 'Connexion réussie', 'IP: ' . $_SERVER['REMOTE_ADDR']);

                    header('Location: pages/dash/dashboard.php');
                    exit();
                } else {
                    // Mot de passe incorrect
                    $attempts = $user['failed_login_attempts'] + 1;

                    if ($attempts >= 5) {
                        // Verrouiller le compte pour 30 minutes
                        $lockUntil = date('Y-m-d H:i:s', strtotime('+30 minutes'));
                        $updateStmt = $pdo->prepare('UPDATE users SET failed_login_attempts = ?, locked_until = ? WHERE id = ?');
                        $updateStmt->execute([$attempts, $lockUntil, $user['id']]);
                        $error = "Trop de tentatives échouées. Votre compte a été verrouillé pour 30 minutes.";
                    } else {
                        $updateStmt = $pdo->prepare('UPDATE users SET failed_login_attempts = ? WHERE id = ?');
                        $updateStmt->execute([$attempts, $user['id']]);
                        $error = "Identifiants incorrects. Tentative " . $attempts . "/5.";
                    }
                }
            } else {
                $error = "Identifiants incorrects.";
            }
        } catch (PDOException $e) {
            $error = "Erreur de connexion. Veuillez réessayer.";
            error_log($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Gestion des Détenus Militaires</title>
    <link rel="icon" href="#" type="image/x-icon" />
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    body {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        font-family: 'Public Sans', sans-serif;
    }

    .login-container {
        background: white;
        border-radius: 20px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        overflow: hidden;
        max-width: 900px;
        width: 100%;
        display: flex;
    }

    .login-left {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 60px 40px;
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
    }

    .login-left h2 {
        font-size: 28px;
        font-weight: 700;
        margin-bottom: 20px;
        text-align: center;
    }

    .login-left p {
        text-align: center;
        opacity: 0.9;
        margin-bottom: 30px;
    }

    .login-left i {
        font-size: 80px;
        margin-bottom: 30px;
        opacity: 0.9;
    }

    .login-right {
        padding: 60px 40px;
        flex: 1;
    }

    .login-right h3 {
        font-size: 26px;
        font-weight: 700;
        color: #2c3e50;
        margin-bottom: 10px;
    }

    .login-right p {
        color: #7f8c8d;
        margin-bottom: 30px;
    }

    .form-group {
        margin-bottom: 25px;
    }

    .form-group label {
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 8px;
    }

    .form-control {
        height: 50px;
        border-radius: 10px;
        border: 2px solid #e9ecef;
        padding: 12px 20px;
        font-size: 15px;
        transition: all 0.3s;
    }

    .form-control:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
    }

    .btn-login {
        height: 50px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 16px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        transition: transform 0.3s;
    }

    .btn-login:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
    }

    .alert {
        border-radius: 10px;
        border: none;
    }

    @media (max-width: 768px) {
        .login-container {
            flex-direction: column;
        }

        .login-left {
            padding: 40px 20px;
        }

        .login-right {
            padding: 40px 20px;
        }
    }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="login-left">
            <i class="fas fa-shield-alt"></i>
            <h2>Système de Gestion des Détenus Militaires</h2>
            <p>Plateforme sécurisée de gestion et de suivi des détenus militaires</p>
        </div>
        <div class="login-right">
            <h3>Connexion</h3>
            <p>Veuillez vous identifier pour accéder au système</p>

            <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">
                        <i class="fas fa-user me-2"></i>Nom d'utilisateur
                    </label>
                    <input type="text" name="username" id="username" class="form-control"
                        placeholder="Entrez votre nom d'utilisateur" required autofocus>
                </div>

                <div class="form-group">
                    <label for="password">
                        <i class="fas fa-lock me-2"></i>Mot de passe
                    </label>
                    <input type="password" name="password" id="password" class="form-control"
                        placeholder="Entrez votre mot de passe" required>
                </div>

                <button type="submit" class="btn btn-primary btn-login w-100">
                    <i class="fas fa-sign-in-alt me-2"></i>Se connecter
                </button>
            </form>

            <div class="text-center mt-4">
                <small class="text-muted">
                    <i class="fas fa-info-circle me-1"></i>
                    Accès réservé au personnel autorisé uniquement
                </small>
            </div>
        </div>
    </div>

    <script src="assets/js/core/jquery-3.7.1.min.js"></script>
    <script src="assets/js/core/bootstrap.min.js"></script>
</body>

</html>