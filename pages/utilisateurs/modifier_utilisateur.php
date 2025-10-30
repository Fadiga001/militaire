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

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: utilisateurs.php');
    exit();
}

$userId = (int)$_GET['id'];
$targetUser = $userMgr->getById($userId);

if (!$targetUser) {
    header('Location: utilisateurs.php');
    exit();
}

// Empêcher de modifier son propre rôle
$isSelf = ($userId === $_SESSION['user_id']);

$errors = [];
$success = '';

// Réinitialisation mot de passe
if (isset($_POST['reset_password'])) {
    $newPassword = UserManager::generatePassword(12);
    if ($userMgr->changePassword($userId, $newPassword, true)) {
        log_activity($pdo, $_SESSION['user_id'], 'Réinitialisation mot de passe', "User ID: $userId");
        $success = "Mot de passe réinitialisé : <code>$newPassword</code>";
    }
}

// Modification informations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['reset_password'])) {
    $required = ['username', 'email', 'nom', 'prenom', 'role'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            $errors[] = "Le champ " . ucfirst(str_replace('_', ' ', $field)) . " est obligatoire.";
        }
    }

    if (!empty($_POST['username'])) {
        if ($userMgr->usernameExists($_POST['username'], $userId)) {
            $errors[] = "Ce nom d'utilisateur existe déjà.";
        }
    }

    if (!empty($_POST['email'])) {
        if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Format d'email invalide.";
        } elseif ($userMgr->emailExists($_POST['email'], $userId)) {
            $errors[] = "Cet email est déjà utilisé.";
        }
    }

    if (empty($errors)) {
        $data = [
            'username' => trim($_POST['username']),
            'email' => trim($_POST['email']),
            'nom' => trim($_POST['nom']),
            'prenom' => trim($_POST['prenom']),
            'role' => $isSelf ? $targetUser['role'] : $_POST['role'], // Ne pas changer son propre rôle
            'is_active' => isset($_POST['is_active'])
        ];

        if ($userMgr->update($userId, $data, $_SESSION['user_id'])) {
            log_activity($pdo, $_SESSION['user_id'], 'Modification utilisateur', "User ID: $userId");
            $success = "Utilisateur modifié avec succès !";
            $targetUser = $userMgr->getById($userId);
        } else {
            $errors[] = "Erreur lors de la modification.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Modifier Utilisateur - <?= htmlspecialchars($targetUser['username']) ?></title>
    <meta content="width=device-width, initial-scale=1.0, shrink-to-fit=no" name="viewport" />
    <?php include '../../requires/link.php'; ?>
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
                            <i class="fas fa-user-edit me-2"></i>Modifier l'Utilisateur
                        </h3>
                        <ul class="breadcrumbs mb-3">
                            <li class="nav-home"><a href="../dash/dashboard.php"><i class="fas fa-home"></i></a></li>
                            <li class="separator"><i class="fas fa-arrow-right"></i></li>
                            <li class="nav-item"><a href="utilisateurs.php">Utilisateurs</a></li>
                            <li class="separator"><i class="fas fa-arrow-right"></i></li>
                            <li class="nav-item"><?= htmlspecialchars($targetUser['username']) ?></li>
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
                            <?= $success ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($isSelf): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Note :</strong> Vous modifiez votre propre compte. Vous ne pouvez pas changer votre
                            rôle.
                        </div>
                    <?php endif; ?>

                    <!-- Info actuelle -->
                    <div class="alert alert-info">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h5 class="mb-2">
                                    <strong>@<?= htmlspecialchars($targetUser['username']) ?></strong> -
                                    <?= htmlspecialchars($targetUser['nom'] . ' ' . $targetUser['prenom']) ?>
                                </h5>
                                <p class="mb-0">
                                    <span
                                        class="badge badge-<?= $targetUser['role'] === 'ADMIN' ? 'danger' : 'primary' ?>">
                                        <?= $targetUser['role'] ?>
                                    </span>
                                    <span
                                        class="badge badge-<?= $targetUser['is_active'] ? 'success' : 'secondary' ?> ms-2">
                                        <?= $targetUser['is_active'] ? 'Actif' : 'Inactif' ?>
                                    </span>
                                </p>
                            </div>
                            <div class="col-md-4 text-end">
                                <p class="mb-0"><small class="text-muted">Créé le</small></p>
                                <strong><?= date('d/m/Y', strtotime($targetUser['created_at'])) ?></strong>
                            </div>
                        </div>
                    </div>

                    <form method="POST">
                        <div class="row">
                            <div class="col-lg-8">
                                <div class="card">
                                    <div class="card-header">
                                        <h4 class="card-title">
                                            <i class="fas fa-user me-2"></i>Informations de l'Utilisateur
                                        </h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">Nom d'utilisateur <span
                                                    class="text-danger">*</span></label>
                                            <input type="text" name="username" class="form-control" required
                                                value="<?= htmlspecialchars($targetUser['username']) ?>">
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Email <span class="text-danger">*</span></label>
                                            <input type="email" name="email" class="form-control" required
                                                value="<?= htmlspecialchars($targetUser['email']) ?>">
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Nom <span class="text-danger">*</span></label>
                                                <input type="text" name="nom" class="form-control" required
                                                    value="<?= htmlspecialchars($targetUser['nom']) ?>">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Prénom(s) <span
                                                        class="text-danger">*</span></label>
                                                <input type="text" name="prenom" class="form-control" required
                                                    value="<?= htmlspecialchars($targetUser['prenom']) ?>">
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Rôle <span class="text-danger">*</span></label>
                                            <select name="role" class="form-select" required
                                                <?= $isSelf ? 'disabled' : '' ?>>
                                                <option value="ADMIN"
                                                    <?= $targetUser['role'] === 'ADMIN' ? 'selected' : '' ?>>
                                                    Administrateur
                                                </option>
                                                <option value="USER"
                                                    <?= $targetUser['role'] === 'USER' ? 'selected' : '' ?>>
                                                    Utilisateur
                                                </option>
                                                <option value="READONLY"
                                                    <?= $targetUser['role'] === 'READONLY' ? 'selected' : '' ?>>
                                                    Lecture seule
                                                </option>
                                            </select>
                                            <?php if ($isSelf): ?>
                                                <small class="text-muted">Vous ne pouvez pas modifier votre propre
                                                    rôle</small>
                                            <?php endif; ?>
                                        </div>

                                        <hr>

                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="is_active"
                                                id="is_active" <?= $targetUser['is_active'] ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="is_active">
                                                <strong>Compte actif</strong>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-4">
                                <!-- Actions rapides -->
                                <div class="card">
                                    <div class="card-header bg-warning text-white">
                                        <h4 class="card-title mb-0">
                                            <i class="fas fa-tools me-2"></i>Actions Rapides
                                        </h4>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST" class="mb-3">
                                            <button type="submit" name="reset_password" class="btn btn-warning w-100"
                                                onclick="return confirm('Confirmer la réinitialisation du mot de passe ?')">
                                                <i class="fas fa-key me-2"></i>Réinitialiser le mot de passe
                                            </button>
                                        </form>

                                        <?php if ($targetUser['locked_until'] && strtotime($targetUser['locked_until']) > time()): ?>
                                            <form method="POST" action="utilisateurs.php">
                                                <input type="hidden" name="action" value="unlock">
                                                <input type="hidden" name="user_id" value="<?= $userId ?>">
                                                <button type="submit" class="btn btn-success w-100">
                                                    <i class="fas fa-unlock me-2"></i>Déverrouiller le compte
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <a href="voir_utilisateur.php?id=<?= $userId ?>"
                                            class="btn btn-info w-100 mt-2">
                                            <i class="fas fa-eye me-2"></i>Voir le profil complet
                                        </a>
                                    </div>
                                </div>

                                <!-- Statistiques -->
                                <div class="card mt-3">
                                    <div class="card-header bg-primary text-white">
                                        <h4 class="card-title mb-0">
                                            <i class="fas fa-chart-bar me-2"></i>Statistiques
                                        </h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="text-muted">Dernière connexion</label>
                                            <p class="mb-0">
                                                <?php if ($targetUser['last_login_at']): ?>
                                                    <?= date('d/m/Y H:i', strtotime($targetUser['last_login_at'])) ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Jamais</span>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                        <div class="mb-3">
                                            <label class="text-muted">Activités enregistrées</label>
                                            <h4 class="mb-0"><?= (int)$targetUser['nb_logs'] ?></h4>
                                        </div>
                                        <div>
                                            <label class="text-muted">Tentatives échouées</label>
                                            <p class="mb-0">
                                                <span
                                                    class="badge badge-<?= $targetUser['failed_login_attempts'] > 0 ? 'danger' : 'success' ?>">
                                                    <?= (int)$targetUser['failed_login_attempts'] ?> / 5
                                                </span>
                                            </p>
                                        </div>
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
                                            <i class="fas fa-save me-2"></i>Enregistrer les Modifications
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
</body>

</html>