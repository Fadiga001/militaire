<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit();
}

require_once '../../includes/db.php';
require_once '../../includes/logs.php';

// Récupérer l'utilisateur connecté
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$usr = $stmt->fetch();
$name = $usr ? htmlspecialchars($usr['nom'] . ' ' . $usr['prenom']) : '';

$errors = [];
$success = '';

// Modification des informations personnelles
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_info'])) {
    $required = ['nom', 'prenom', 'email'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            $errors[] = "Le champ " . ucfirst($field) . " est obligatoire.";
        }
    }

    // Vérifier email unique (sauf pour soi-même)
    if (!empty($_POST['email'])) {
        if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "L'adresse email n'est pas valide.";
        }
        $stmt = $pdo->prepare("SELECT COUNT(*) as nb FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$_POST['email'], $_SESSION['user_id']]);
        if ((int)$stmt->fetch()['nb'] > 0) {
            $errors[] = "Cet email est déjà utilisé par un autre utilisateur.";
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("UPDATE users SET nom = ?, prenom = ?, email = ? WHERE id = ?");
        if ($stmt->execute([
            trim($_POST['nom']),
            trim($_POST['prenom']),
            trim($_POST['email']),
            $_SESSION['user_id']
        ])) {
            log_activity($pdo, $_SESSION['user_id'], 'Modification profil', 'Informations personnelles mises à jour');
            $success = "Profil mis à jour avec succès !";

            // Recharger les données
            $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $usr = $stmt->fetch();
            $_SESSION['nom_complet'] = $usr['nom'] . ' ' . $usr['prenom'];
        } else {
            $errors[] = "Erreur lors de la mise à jour du profil.";
        }
    }
}

// Changement de mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $required = ['old_password', 'new_password', 'confirm_password'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            $errors[] = "Tous les champs du mot de passe sont obligatoires.";
            break;
        }
    }

    if (empty($errors)) {
        // Vérifier ancien mot de passe
        if (!password_verify($_POST['old_password'], $usr['password_hash'])) {
            $errors[] = "L'ancien mot de passe est incorrect.";
        }

        // Vérifier correspondance
        if ($_POST['new_password'] !== $_POST['confirm_password']) {
            $errors[] = "Les nouveaux mots de passe ne correspondent pas.";
        }

        // Vérifier force du mot de passe
        $pwd = $_POST['new_password'];
        if (
            strlen($pwd) < 8 ||
            !preg_match('/[A-Z]/', $pwd) ||
            !preg_match('/[a-z]/', $pwd) ||
            !preg_match('/[0-9]/', $pwd) ||
            !preg_match('/[^a-zA-Z0-9]/', $pwd)
        ) {
            $errors[] = "Le nouveau mot de passe doit contenir au moins 8 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial.";
        }

        if (empty($errors)) {
            $hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                UPDATE users 
                SET password_hash = ?, 
                    password_changed_at = NOW(), 
                    must_change_password = FALSE 
                WHERE id = ?
            ");

            if ($stmt->execute([$hash, $_SESSION['user_id']])) {
                log_activity($pdo, $_SESSION['user_id'], 'Changement mot de passe', 'Mot de passe modifié par l\'utilisateur');
                $success = "Mot de passe modifié avec succès !";
            } else {
                $errors[] = "Erreur lors du changement de mot de passe.";
            }
        }
    }
}

// Statistiques de l'utilisateur
$statsStmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_actions,
        COUNT(DISTINCT DATE(created_at)) as jours_actifs
    FROM audit_logs
    WHERE user_id = ?
");
$statsStmt->execute([$_SESSION['user_id']]);
$stats = $statsStmt->fetch();

// Dernières connexions
$connexionsStmt = $pdo->prepare("
    SELECT created_at, ip_address
    FROM audit_logs
    WHERE user_id = ? AND action = 'Connexion réussie'
    ORDER BY created_at DESC
    LIMIT 5
");
$connexionsStmt->execute([$_SESSION['user_id']]);
$connexions = $connexionsStmt->fetchAll();

// Dernières actions
$actionsStmt = $pdo->prepare("
    SELECT action, created_at
    FROM audit_logs
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 10
");
$actionsStmt->execute([$_SESSION['user_id']]);
$actions = $actionsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Mon Profil - Système Militaire</title>
    <meta content="width=device-width, initial-scale=1.0, shrink-to-fit=no" name="viewport" />
    <?php include '../../requires/link.php'; ?>
    <style>
        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            border-radius: 15px;
            margin-bottom: 30px;
        }

        .profile-avatar {
            width: 100px;
            height: 100px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: #667eea;
            margin: 0 auto 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .stat-card {
            text-align: center;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
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
                            <i class="fas fa-user-circle me-2"></i>Mon Profil
                        </h3>
                        <ul class="breadcrumbs mb-3">
                            <li class="nav-home"><a href="../dash/dashboard.php"><i class="fas fa-home"></i></a></li>
                            <li class="separator"><i class="fas fa-arrow-right"></i></li>
                            <li class="nav-item">Mon Profil</li>
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
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Header profil -->
                    <div class="profile-header">
                        <div class="row align-items-center">
                            <div class="col-md-3">
                                <div class="profile-avatar">
                                    <i class="fas fa-user"></i>
                                </div>
                            </div>
                            <div class="col-md-9">
                                <h2 class="mb-2"><?= htmlspecialchars($usr['nom'] . ' ' . $usr['prenom']) ?></h2>
                                <p class="mb-2 opacity-75">@<?= htmlspecialchars($usr['username']) ?></p>
                                <div>
                                    <span
                                        class="badge badge-lg badge-<?= $usr['role'] === 'ADMIN' ? 'danger' : ($usr['role'] === 'USER' ? 'primary' : 'secondary') ?>">
                                        <?= $usr['role'] ?>
                                    </span>
                                    <span
                                        class="badge badge-lg badge-<?= $usr['is_active'] ? 'success' : 'secondary' ?> ms-2">
                                        <?= $usr['is_active'] ? 'Actif' : 'Inactif' ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Statistiques -->
                    <div class="row mb-4">
                        <div class="col-sm-6 col-md-3">
                            <div class="stat-card">
                                <i class="fas fa-calendar-check fa-2x text-primary mb-2"></i>
                                <h4 class="mb-0"><?= number_format($stats['jours_actifs']) ?></h4>
                                <p class="text-muted mb-0">Jours actifs</p>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <div class="stat-card">
                                <i class="fas fa-tasks fa-2x text-success mb-2"></i>
                                <h4 class="mb-0"><?= number_format($stats['total_actions']) ?></h4>
                                <p class="text-muted mb-0">Actions effectuées</p>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <div class="stat-card">
                                <i class="fas fa-clock fa-2x text-info mb-2"></i>
                                <h4 class="mb-0">
                                    <?= $usr['last_login_at'] ? date('d/m/Y', strtotime($usr['last_login_at'])) : 'N/A' ?>
                                </h4>
                                <p class="text-muted mb-0">Dernière connexion</p>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <div class="stat-card">
                                <i class="fas fa-user-clock fa-2x text-warning mb-2"></i>
                                <h4 class="mb-0"><?= date('d/m/Y', strtotime($usr['created_at'])) ?></h4>
                                <p class="text-muted mb-0">Membre depuis</p>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Informations personnelles -->
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="card-title">
                                        <i class="fas fa-id-card me-2"></i>Informations Personnelles
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <form method="POST">
                                        <div class="mb-3">
                                            <label class="form-label">Nom d'utilisateur</label>
                                            <input type="text" class="form-control"
                                                value="<?= htmlspecialchars($usr['username']) ?>" disabled>
                                            <small class="text-muted">Le nom d'utilisateur ne peut pas être
                                                modifié</small>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Nom <span class="text-danger">*</span></label>
                                            <input type="text" name="nom" class="form-control" required
                                                value="<?= htmlspecialchars($usr['nom']) ?>">
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Prénom(s) <span
                                                    class="text-danger">*</span></label>
                                            <input type="text" name="prenom" class="form-control" required
                                                value="<?= htmlspecialchars($usr['prenom']) ?>">
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Email <span class="text-danger">*</span></label>
                                            <input type="email" name="email" class="form-control" required
                                                value="<?= htmlspecialchars($usr['email']) ?>">
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Rôle</label>
                                            <input type="text" class="form-control" value="<?= $usr['role'] ?>"
                                                disabled>
                                            <small class="text-muted">Le rôle est géré par les administrateurs</small>
                                        </div>

                                        <div class="text-end">
                                            <button type="submit" name="update_info" class="btn btn-primary">
                                                <i class="fas fa-save me-2"></i>Enregistrer les modifications
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Changement mot de passe -->
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="card-title">
                                        <i class="fas fa-key me-2"></i>Changer le Mot de Passe
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <form method="POST">
                                        <div class="mb-3">
                                            <label class="form-label">Ancien mot de passe <span
                                                    class="text-danger">*</span></label>
                                            <input type="password" name="old_password" class="form-control" required>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Nouveau mot de passe <span
                                                    class="text-danger">*</span></label>
                                            <input type="password" name="new_password" class="form-control" required>
                                            <small class="text-muted">Min. 8 caractères, 1 maj., 1 min., 1 chiffre, 1
                                                spécial</small>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Confirmer le nouveau mot de passe <span
                                                    class="text-danger">*</span></label>
                                            <input type="password" name="confirm_password" class="form-control"
                                                required>
                                        </div>

                                        <?php if ($usr['must_change_password']): ?>
                                            <div class="alert alert-warning">
                                                <i class="fas fa-exclamation-triangle me-2"></i>
                                                Vous devez changer votre mot de passe
                                            </div>
                                        <?php endif; ?>

                                        <div class="alert alert-info">
                                            <strong>Règles de sécurité :</strong>
                                            <ul class="mb-0 mt-2">
                                                <li>Minimum 8 caractères</li>
                                                <li>Au moins une majuscule (A-Z)</li>
                                                <li>Au moins une minuscule (a-z)</li>
                                                <li>Au moins un chiffre (0-9)</li>
                                                <li>Au moins un caractère spécial (!@#$...)</li>
                                            </ul>
                                        </div>

                                        <div class="text-end">
                                            <button type="submit" name="change_password" class="btn btn-warning">
                                                <i class="fas fa-lock me-2"></i>Changer le mot de passe
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <!-- Dernières connexions -->
                            <div class="card mt-3">
                                <div class="card-header">
                                    <h4 class="card-title">
                                        <i class="fas fa-history me-2"></i>Dernières Connexions
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($connexions)): ?>
                                        <p class="text-muted text-center">Aucune connexion enregistrée</p>
                                    <?php else: ?>
                                        <ul class="list-unstyled mb-0">
                                            <?php foreach ($connexions as $conn): ?>
                                                <li class="mb-2">
                                                    <i class="fas fa-sign-in-alt text-primary me-2"></i>
                                                    <strong><?= date('d/m/Y H:i', strtotime($conn['created_at'])) ?></strong>
                                                    <?php if ($conn['ip_address']): ?>
                                                        <br><small class="text-muted ms-4">IP:
                                                            <?= htmlspecialchars($conn['ip_address']) ?></small>
                                                    <?php endif; ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Dernières actions -->
                    <?php if (!empty($actions)): ?>
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h4 class="card-title">
                                            <i class="fas fa-list me-2"></i>Mes Dernières Actions
                                        </h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Action</th>
                                                        <th>Date/Heure</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($actions as $action): ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars($action['action']) ?></td>
                                                            <td><?= date('d/m/Y H:i:s', strtotime($action['created_at'])) ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php include '../../requires/script.php'; ?>
</body>

</html>