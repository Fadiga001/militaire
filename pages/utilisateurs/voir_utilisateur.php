<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'ADMIN') {
    header('Location: ../../index.php');
    exit();
}

require_once '../../includes/db.php';

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$currentUser = $stmt->fetch();
$name = $currentUser ? htmlspecialchars($currentUser['nom'] . ' ' . $currentUser['prenom']) : '';

// Récupérer l'utilisateur
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: utilisateurs.php');
    exit();
}

$userId = (int)$_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$usr = $stmt->fetch();

if (!$usr) {
    header('Location: utilisateurs.php');
    exit();
}

$isCurrentUser = $userId === $_SESSION['user_id'];
$isLocked = $usr['locked_until'] && strtotime($usr['locked_until']) > time();

// Statistiques
$statsStmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_actions,
        COUNT(DISTINCT DATE(created_at)) as jours_actifs,
        MAX(created_at) as derniere_action
    FROM audit_logs
    WHERE user_id = ?
");
$statsStmt->execute([$userId]);
$stats = $statsStmt->fetch();

// Compter les connexions
$connexionsStmt = $pdo->prepare("
    SELECT COUNT(*) as nb_connexions
    FROM audit_logs
    WHERE user_id = ? AND action = 'Connexion réussie'
");
$connexionsStmt->execute([$userId]);
$nbConnexions = (int)$connexionsStmt->fetch()['nb_connexions'];

// Dernières connexions
$lastConnexionsStmt = $pdo->prepare("
    SELECT created_at, ip_address, user_agent
    FROM audit_logs
    WHERE user_id = ? AND action = 'Connexion réussie'
    ORDER BY created_at DESC
    LIMIT 10
");
$lastConnexionsStmt->execute([$userId]);
$connexions = $lastConnexionsStmt->fetchAll();

// Dernières actions (30 derniers jours)
$actionsStmt = $pdo->prepare("
    SELECT *
    FROM audit_logs
    WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ORDER BY created_at DESC
    LIMIT 50
");
$actionsStmt->execute([$userId]);
$actions = $actionsStmt->fetchAll();

// Actions par type
$actionsTypesStmt = $pdo->prepare("
    SELECT action, COUNT(*) as nb
    FROM audit_logs
    WHERE user_id = ?
    GROUP BY action
    ORDER BY nb DESC
    LIMIT 10
");
$actionsTypesStmt->execute([$userId]);
$actionsTypes = $actionsTypesStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Utilisateur - <?= htmlspecialchars($usr['nom'] . ' ' . $usr['prenom']) ?></title>
    <meta content="width=device-width, initial-scale=1.0, shrink-to-fit=no" name="viewport" />
    <?php include '../../requires/link.php'; ?>
    <style>
    .profile-card {
        position: relative;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 30px;
        border-radius: 15px;
        margin-bottom: 20px;
    }

    .profile-avatar {
        width: 120px;
        height: 120px;
        background: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 3rem;
        color: #667eea;
        margin: 0 auto 20px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    }

    .stat-box {
        background: white;
        padding: 20px;
        border-radius: 10px;
        text-align: center;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .timeline {
        position: relative;
        padding-left: 30px;
    }

    .timeline::before {
        content: '';
        position: absolute;
        left: 8px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #dee2e6;
    }

    .timeline-item {
        position: relative;
        padding-bottom: 20px;
    }

    .timeline-item::before {
        content: '';
        position: absolute;
        left: -26px;
        top: 5px;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: #177dff;
        border: 2px solid #fff;
        box-shadow: 0 0 0 2px #177dff;
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
                            <i class="fas fa-user me-2"></i>Profil Utilisateur
                        </h3>
                        <ul class="breadcrumbs mb-3">
                            <li class="nav-home"><a href="../dash/dashboard.php"><i class="fas fa-home"></i></a></li>
                            <li class="separator"><i class="fas fa-arrow-right"></i></li>
                            <li class="nav-item"><a href="utilisateurs.php">Utilisateurs</a></li>
                            <li class="separator"><i class="fas fa-arrow-right"></i></li>
                            <li class="nav-item">Détails</li>
                        </ul>
                    </div>

                    <!-- Actions rapides -->
                    <div class="d-flex justify-content-end mb-3">
                        <?php if (!$isCurrentUser): ?>
                        <a href="modifier_utilisateur.php?id=<?= $usr['id'] ?>" class="btn btn-warning me-2">
                            <i class="fas fa-edit me-2"></i>Modifier
                        </a>
                        <?php endif; ?>
                        <a href="utilisateurs.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Retour
                        </a>
                    </div>

                    <div class="row">
                        <!-- Profil principal -->
                        <div class="col-lg-4">
                            <div class="profile-card">
                                <div class="profile-avatar">
                                    <i class="fas fa-user"></i>
                                </div>
                                <h3 class="text-center mb-2"><?= htmlspecialchars($usr['nom'] . ' ' . $usr['prenom']) ?>
                                </h3>
                                <p class="text-center mb-3 opacity-75">@<?= htmlspecialchars($usr['username']) ?></p>

                                <div class="text-center">
                                    <span
                                        class="badge badge-lg badge-<?= $usr['role'] === 'ADMIN' ? 'danger' : ($usr['role'] === 'USER' ? 'primary' : 'secondary') ?> me-2">
                                        <?= $usr['role'] ?>
                                    </span>
                                    <?php if ($isLocked): ?>
                                    <span class="badge badge-lg badge-danger">
                                        <i class="fas fa-lock me-1"></i>Verrouillé
                                    </span>
                                    <?php elseif ($usr['is_active']): ?>
                                    <span class="badge badge-lg badge-success">Actif</span>
                                    <?php else: ?>
                                    <span class="badge badge-lg badge-secondary">Inactif</span>
                                    <?php endif; ?>

                                    <?php if ($isCurrentUser): ?>
                                    <span class="badge badge-lg badge-info ms-2">Vous</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="card">
                                <div class="card-header">
                                    <h4 class="card-title"><i class="fas fa-info-circle me-2"></i>Informations</h4>
                                </div>
                                <div class="card-body">
                                    <p class="mb-2">
                                        <i class="fas fa-envelope text-primary me-2"></i>
                                        <strong>Email:</strong><br>
                                        <?= htmlspecialchars($usr['email']) ?>
                                    </p>

                                    <hr>

                                    <p class="mb-2">
                                        <i class="fas fa-calendar text-success me-2"></i>
                                        <strong>Membre depuis:</strong><br>
                                        <?= date('d/m/Y', strtotime($usr['created_at'])) ?>
                                    </p>

                                    <?php if ($usr['last_login_at']): ?>
                                    <p class="mb-2">
                                        <i class="fas fa-sign-in-alt text-info me-2"></i>
                                        <strong>Dernière connexion:</strong><br>
                                        <?= date('d/m/Y H:i', strtotime($usr['last_login_at'])) ?>
                                        <?php if ($usr['last_login_ip']): ?>
                                        <br><small class="text-muted">IP:
                                            <?= htmlspecialchars($usr['last_login_ip']) ?></small>
                                        <?php endif; ?>
                                    </p>
                                    <?php endif; ?>

                                    <?php if ($usr['password_changed_at']): ?>
                                    <p class="mb-0">
                                        <i class="fas fa-key text-warning me-2"></i>
                                        <strong>MDP changé le:</strong><br>
                                        <?= date('d/m/Y', strtotime($usr['password_changed_at'])) ?>
                                    </p>
                                    <?php endif; ?>

                                    <?php if ($usr['must_change_password']): ?>
                                    <div class="alert alert-warning mt-3 mb-0">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        Doit changer son mot de passe
                                    </div>
                                    <?php endif; ?>

                                    <?php if ($isLocked): ?>
                                    <div class="alert alert-danger mt-3 mb-0">
                                        <i class="fas fa-lock me-2"></i>
                                        <strong>Compte verrouillé jusqu'au:</strong><br>
                                        <?= date('d/m/Y H:i', strtotime($usr['locked_until'])) ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Statistiques et historique -->
                        <div class="col-lg-8">
                            <!-- Statistiques -->
                            <div class="row mb-4">
                                <div class="col-sm-6 col-md-3">
                                    <div class="stat-box">
                                        <i class="fas fa-sign-in-alt fa-2x text-primary mb-2"></i>
                                        <h3 class="mb-0"><?= number_format($nbConnexions) ?></h3>
                                        <p class="text-muted mb-0">Connexions</p>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-3">
                                    <div class="stat-box">
                                        <i class="fas fa-tasks fa-2x text-success mb-2"></i>
                                        <h3 class="mb-0"><?= number_format($stats['total_actions']) ?></h3>
                                        <p class="text-muted mb-0">Actions</p>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-3">
                                    <div class="stat-box">
                                        <i class="fas fa-calendar-day fa-2x text-info mb-2"></i>
                                        <h3 class="mb-0"><?= number_format($stats['jours_actifs']) ?></h3>
                                        <p class="text-muted mb-0">Jours actifs</p>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-3">
                                    <div class="stat-box">
                                        <i class="fas fa-exclamation-triangle fa-2x text-warning mb-2"></i>
                                        <h3 class="mb-0"><?= (int)$usr['failed_login_attempts'] ?></h3>
                                        <p class="text-muted mb-0">Échecs login</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Dernières connexions -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h4 class="card-title">
                                        <i class="fas fa-history me-2"></i>Dernières Connexions
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($connexions)): ?>
                                    <p class="text-muted text-center py-3">Aucune connexion enregistrée</p>
                                    <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Date/Heure</th>
                                                    <th>Adresse IP</th>
                                                    <th>Navigateur</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($connexions as $conn): ?>
                                                <tr>
                                                    <td><?= date('d/m/Y H:i:s', strtotime($conn['created_at'])) ?></td>
                                                    <td><?= htmlspecialchars($conn['ip_address'] ?? 'N/A') ?></td>
                                                    <td><small><?= htmlspecialchars(substr($conn['user_agent'] ?? 'N/A', 0, 50)) ?></small>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Actions par type -->
                            <?php if (!empty($actionsTypes)): ?>
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h4 class="card-title">
                                        <i class="fas fa-chart-bar me-2"></i>Actions par Type
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <?php foreach ($actionsTypes as $actionType): ?>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span><?= htmlspecialchars($actionType['action']) ?></span>
                                            <strong><?= number_format($actionType['nb']) ?></strong>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <?php 
                                            $maxActions = (int)$actionsTypes[0]['nb'];
                                            $percent = $maxActions > 0 ? ($actionType['nb'] / $maxActions * 100) : 0;
                                            ?>
                                            <div class="progress-bar bg-primary" style="width: <?= $percent ?>%"></div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Historique des actions -->
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="card-title">
                                        <i class="fas fa-list me-2"></i>Historique des Actions (30 derniers jours)
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($actions)): ?>
                                    <p class="text-muted text-center py-3">Aucune action enregistrée</p>
                                    <?php else: ?>
                                    <div class="timeline">
                                        <?php foreach ($actions as $action): ?>
                                        <div class="timeline-item">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <h6 class="mb-1">
                                                        <strong><?= htmlspecialchars($action['action']) ?></strong>
                                                    </h6>
                                                    <?php if ($action['entity_type']): ?>
                                                    <p class="text-muted mb-1">
                                                        <small>
                                                            <?= htmlspecialchars($action['entity_type']) ?>
                                                            <?php if ($action['entity_id']): ?>
                                                            #<?= (int)$action['entity_id'] ?>
                                                            <?php endif; ?>
                                                        </small>
                                                    </p>
                                                    <?php endif; ?>
                                                    <small class="text-muted">
                                                        <?= date('d/m/Y H:i:s', strtotime($action['created_at'])) ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include '../../requires/script.php'; ?>
</body>

</html>