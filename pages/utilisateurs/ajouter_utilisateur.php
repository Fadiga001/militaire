<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'ADMIN') {
    header('Location: ../../index.php');
    exit();
}

require_once '../../includes/db.php';
require_once '../../includes/classes/autoload.php';
require_once '../../includes/logs.php';

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
$name = $user ? htmlspecialchars($user['nom'] . ' ' . $user['prenom']) : '';

$userMgr = new UserManager($pdo);

$errors = [];
$success = '';
$generatedPassword = '';

// Génération automatique de mot de passe
if (isset($_POST['generate_password'])) {
    $generatedPassword = UserManager::generatePassword(12);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['generate_password'])) {
    // Validation
    $required = ['username', 'email', 'password', 'password_confirm', 'nom', 'prenom', 'role'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            $errors[] = "Le champ " . ucfirst(str_replace('_', ' ', $field)) . " est obligatoire.";
        }
    }

    // Vérifier username unique
    if (!empty($_POST['username'])) {
        if ($userMgr->usernameExists($_POST['username'])) {
            $errors[] = "Ce nom d'utilisateur existe déjà.";
        }
    }

    // Vérifier email unique
    if (!empty($_POST['email'])) {
        if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Format d'email invalide.";
        } elseif ($userMgr->emailExists($_POST['email'])) {
            $errors[] = "Cet email est déjà utilisé.";
        }
    }

    // Vérifier mots de passe
    if (!empty($_POST['password']) && !empty($_POST['password_confirm'])) {
        if ($_POST['password'] !== $_POST['password_confirm']) {
            $errors[] = "Les mots de passe ne correspondent pas.";
        } else {
            $strength = UserManager::checkPasswordStrength($_POST['password']);
            if ($strength['score'] < 4) {
                $errors[] = "Mot de passe trop faible. " . implode(', ', $strength['feedback']);
            }
        }
    }

    if (empty($errors)) {
        $data = [
            'username' => trim($_POST['username']),
            'email' => trim($_POST['email']),
            'password' => $_POST['password'],
            'nom' => trim($_POST['nom']),
            'prenom' => trim($_POST['prenom']),
            'role' => $_POST['role'],
            'is_active' => isset($_POST['is_active']),
            'must_change_password' => isset($_POST['must_change_password'])
        ];

        $userId = $userMgr->create($data, $_SESSION['user_id']);

        if ($userId) {
            log_activity($pdo, $_SESSION['user_id'], 'Création utilisateur', "Nouvel utilisateur: {$data['username']} (ID: $userId)");
            $success = "Utilisateur créé avec succès !";
            header("refresh:2;url=voir_utilisateur.php?id=$userId");
        } else {
            $errors[] = "Erreur lors de la création de l'utilisateur.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Ajouter un Utilisateur - Système Militaire</title>
    <meta content="width=device-width, initial-scale=1.0, shrink-to-fit=no" name="viewport" />
    <?php include '../../requires/link.php'; ?>
    <style>
    .password-strength {
        height: 5px;
        border-radius: 3px;
        margin-top: 5px;
        transition: all 0.3s;
    }

    .strength-weak {
        background: #dc3545;
        width: 33%;
    }

    .strength-medium {
        background: #ffc107;
        width: 66%;
    }

    .strength-strong {
        background: #28a745;
        width: 100%;
    }
    </style>
</head>

<body>
    <div class="wrapper">
        <?php include '../../requires/sidebar.php'; ?>
        <div class="main-panel">
            <?php include '../../requires/main-header.php'; ?>
            <div class="container">
                <div class="page-inner">
                    <div class="page-header">
                        <h3 class="fw-bold mb-3">
                            <i class="fas fa-user-plus me-2"></i>Ajouter un Utilisateur
                        </h3>
                        <ul class="breadcrumbs mb-3">
                            <li class="nav-home"><a href="../dash/dashboard.php"><i class="fas fa-home"></i></a></li>
                            <li class="separator"><i class="fas fa-arrow-right"></i></li>
                            <li class="nav-item"><a href="utilisateurs.php">Utilisateurs</a></li>
                            <li class="separator"><i class="fas fa-arrow-right"></i></li>
                            <li class="nav-item">Ajouter</li>
                        </ul>
                    </div>

                    <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <strong><i class="fas fa-exclamation-circle me-2"></i>Erreurs :</strong>
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <strong><i class="fas fa-check-circle me-2"></i></strong>
                        <?= htmlspecialchars($success) ?>
                        <p class="mb-0"><i class="fas fa-spinner fa-spin me-2"></i>Redirection en cours...</p>
                    </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="row">
                            <!-- Informations de connexion -->
                            <div class="col-lg-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h4 class="card-title">
                                            <i class="fas fa-key me-2"></i>Informations de Connexion
                                        </h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">Nom d'utilisateur <span
                                                    class="text-danger">*</span></label>
                                            <input type="text" name="username" class="form-control"
                                                placeholder="Ex: jdupont" required
                                                value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                                            <small class="text-muted">Utilisé pour se connecter (pas d'espaces,
                                                caractères spéciaux)</small>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Adresse Email <span
                                                    class="text-danger">*</span></label>
                                            <input type="email" name="email" class="form-control"
                                                placeholder="Ex: jean.dupont@armee.ci" required
                                                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Mot de passe <span
                                                    class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <input type="password" name="password" id="password"
                                                    class="form-control" placeholder="Minimum 8 caractères" required
                                                    value="<?= htmlspecialchars($generatedPassword) ?>">
                                                <button type="button" class="btn btn-outline-secondary"
                                                    onclick="togglePassword('password')">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                            <div id="password-strength" class="password-strength"></div>
                                            <small id="password-feedback" class="text-muted"></small>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Confirmer le mot de passe <span
                                                    class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <input type="password" name="password_confirm" id="password_confirm"
                                                    class="form-control" placeholder="Retapez le mot de passe" required
                                                    value="<?= htmlspecialchars($generatedPassword) ?>">
                                                <button type="button" class="btn btn-outline-secondary"
                                                    onclick="togglePassword('password_confirm')">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                        </div>

                                        <div class="d-grid">
                                            <button type="submit" name="generate_password" class="btn btn-info">
                                                <i class="fas fa-random me-2"></i>Générer un mot de passe sécurisé
                                            </button>
                                        </div>

                                        <?php if ($generatedPassword): ?>
                                        <div class="alert alert-success mt-3">
                                            <strong>Mot de passe généré :</strong><br>
                                            <code class="fs-5"><?= htmlspecialchars($generatedPassword) ?></code>
                                            <button type="button" class="btn btn-sm btn-success float-end"
                                                onclick="copyToClipboard('<?= htmlspecialchars($generatedPassword) ?>')">
                                                <i class="fas fa-copy"></i> Copier
                                            </button>
                                            <br><small class="text-muted">Notez-le ou communiquez-le à l'utilisateur de
                                                manière sécurisée</small>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Informations personnelles -->
                            <div class="col-lg-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h4 class="card-title">
                                            <i class="fas fa-user me-2"></i>Informations Personnelles
                                        </h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Nom <span class="text-danger">*</span></label>
                                                <input type="text" name="nom" class="form-control" required
                                                    value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Prénom(s) <span
                                                        class="text-danger">*</span></label>
                                                <input type="text" name="prenom" class="form-control" required
                                                    value="<?= htmlspecialchars($_POST['prenom'] ?? '') ?>">
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Rôle <span class="text-danger">*</span></label>
                                            <select name="role" class="form-select" required>
                                                <option value="">Sélectionner un rôle</option>
                                                <option value="ADMIN"
                                                    <?= ($_POST['role'] ?? '') === 'ADMIN' ? 'selected' : '' ?>>
                                                    Administrateur (Accès complet)
                                                </option>
                                                <option value="USER"
                                                    <?= ($_POST['role'] ?? '') === 'USER' ? 'selected' : '' ?>>
                                                    Utilisateur (Lecture/Écriture)
                                                </option>
                                                <option value="READONLY"
                                                    <?= ($_POST['role'] ?? '') === 'READONLY' ? 'selected' : '' ?>>
                                                    Lecture seule (Consultation uniquement)
                                                </option>
                                            </select>
                                        </div>

                                        <hr>

                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" name="is_active"
                                                id="is_active" checked>
                                            <label class="form-check-label" for="is_active">
                                                <strong>Compte actif</strong>
                                                <br><small class="text-muted">L'utilisateur peut se connecter
                                                    immédiatement</small>
                                            </label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="must_change_password"
                                                id="must_change_password" checked>
                                            <label class="form-check-label" for="must_change_password">
                                                <strong>Forcer changement de mot de passe</strong>
                                                <br><small class="text-muted">L'utilisateur devra changer son mot de
                                                    passe à la première connexion</small>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <!-- Info rôles -->
                                <div class="card mt-3">
                                    <div class="card-header bg-info text-white">
                                        <h4 class="card-title mb-0">
                                            <i class="fas fa-info-circle me-2"></i>Permissions par Rôle
                                        </h4>
                                    </div>
                                    <div class="card-body">
                                        <h6 class="text-danger"><i class="fas fa-crown me-2"></i>Administrateur</h6>
                                        <ul class="small mb-3">
                                            <li>Accès complet à toutes les fonctionnalités</li>
                                            <li>Gestion des utilisateurs</li>
                                            <li>Suppression et modification sans restriction</li>
                                            <li>Accès aux logs d'audit</li>
                                        </ul>

                                        <h6 class="text-primary"><i class="fas fa-user me-2"></i>Utilisateur</h6>
                                        <ul class="small mb-3">
                                            <li>Ajout, modification de détenus/condamnations</li>
                                            <li>Consultation de toutes les données</li>
                                            <li>Pas d'accès à l'administration</li>
                                        </ul>

                                        <h6 class="text-info"><i class="fas fa-eye me-2"></i>Lecture seule</h6>
                                        <ul class="small mb-0">
                                            <li>Consultation uniquement</li>
                                            <li>Aucune modification possible</li>
                                            <li>Génération de rapports</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-body text-end">
                                        <a href="utilisateurs.php" class="btn btn-secondary me-2">
                                            <i class="fas fa-times me-2"></i>Annuler
                                        </a>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>Créer l'Utilisateur
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php include '../../requires/script.php'; ?>
    <script>
    // Toggle password visibility
    function togglePassword(fieldId) {
        const field = document.getElementById(fieldId);
        field.type = field.type === 'password' ? 'text' : 'password';
    }

    // Copy to clipboard
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(() => {
            alert('Mot de passe copié dans le presse-papier !');
        });
    }

    // Password strength checker
    document.getElementById('password')?.addEventListener('input', function() {
        const password = this.value;
        const strengthBar = document.getElementById('password-strength');
        const feedback = document.getElementById('password-feedback');

        if (!password) {
            strengthBar.className = 'password-strength';
            feedback.textContent = '';
            return;
        }

        let score = 0;
        const checks = [];

        if (password.length >= 8) score++;
        else checks.push('8 caractères min');

        if (/[a-z]/.test(password)) score++;
        else checks.push('1 minuscule');

        if (/[A-Z]/.test(password)) score++;
        else checks.push('1 majuscule');

        if (/[0-9]/.test(password)) score++;
        else checks.push('1 chiffre');

        if (/[^a-zA-Z0-9]/.test(password)) score++;
        else checks.push('1 caractère spécial');

        strengthBar.className = 'password-strength ' + (
            score < 3 ? 'strength-weak' :
            score < 5 ? 'strength-medium' : 'strength-strong'
        );

        feedback.textContent = checks.length > 0 ?
            'Manque: ' + checks.join(', ') :
            '✓ Mot de passe fort';
        feedback.className = 'text-' + (score < 3 ? 'danger' : score < 5 ? 'warning' : 'success');
    });

    // Lowercase username automatically
    document.querySelector('input[name="username"]')?.addEventListener('input', function() {
        this.value = this.value.toLowerCase().replace(/[^a-z0-9._-]/g, '');
    });
    </script>
</body>

</html>