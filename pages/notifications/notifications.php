<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit();
}

require_once '../../includes/db.php';
require_once '../../includes/logs.php';

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
$name = $user ? htmlspecialchars($user['nom'] . ' ' . $user['prenom']) : '';

// Marquer comme lue
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $notifId = (int)$_GET['mark_read'];
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE, read_at = NOW(), read_by = ? WHERE id = ?");
    $stmt->execute([$_SESSION['user_id'], $notifId]);
    header('Location: notifications.php');
    exit();
}

// Filtres
$type = $_GET['type'] ?? '';
$urgence = $_GET['urgence'] ?? '';
$statut = $_GET['statut'] ?? '';

$sql = "SELECT n.*, d.nom_complet as detenu_nom, d.matricule as detenu_matricule
        FROM notifications n
        LEFT JOIN detenus d ON n.entity_type = 'DETENU' AND n.entity_id = d.id
        WHERE n.is_active = TRUE";

$params = [];
if ($type) {
    $sql .= " AND n.type = :type";
    $params[':type'] = $type;
}
if ($urgence) {
    $sql .= " AND n.urgence = :urgence";
    $params[':urgence'] = $urgence;
}
if ($statut === 'lue') {
    $sql .= " AND n.is_read = TRUE";
} elseif ($statut === 'non_lue') {
    $sql .= " AND n.is_read = FALSE";
}

$sql .= " ORDER BY n.is_read ASC, n.urgence DESC, n.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$notifications = $stmt->fetchAll();

// Stats
$stats = [
    'total' => count($notifications),
    'non_lues' => count(array_filter($notifications, fn($n) => !$n['is_read'])),
    'critiques' => count(array_filter($notifications, fn($n) => $n['urgence'] === 'CRITICAL')),
    'high' => count(array_filter($notifications, fn($n) => $n['urgence'] === 'HIGH'))
];
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Notifications - Système Militaire</title>
    <meta content="width=device-width, initial-scale=1.0, shrink-to-fit=no" name="viewport" />
    <?php include '../../requires/link.php'; ?>
    <style>
    .notif-item {
        transition: all 0.3s;
        border-left: 4px solid transparent;
    }

    .notif-item:hover {
        background: #f8f9fa;
    }

    .notif-item.unread {
        background: #e7f3ff;
        border-left-color: #177dff;
    }

    .urgence-CRITICAL {
        background: #dc3545;
        color: white;
    }

    .urgence-HIGH {
        background: #fd7e14;
        color: white;
    }

    .urgence-MEDIUM {
        background: #ffc107;
    }

    .urgence-LOW {
        background: #17a2b8;
        color: white;
    }

    .urgence-INFO {
        background: #6c757d;
        color: white;
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
                    <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4">
                        <div>
                            <h3 class="fw-bold mb-3"><i class="fas fa-bell me-2"></i>Notifications</h3>
                            <h6 class="op-7 mb-2"><?= $stats['non_lues'] ?> notification(s) non lue(s)</h6>
                        </div>
                    </div>

                    <!-- Stats -->
                    <div class="row mb-4">
                        <div class="col-sm-6 col-md-3">
                            <div class="card card-stats card-round">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-icon">
                                            <div class="icon-big text-center icon-primary bubble-shadow-small">
                                                <i class="fas fa-bell"></i>
                                            </div>
                                        </div>
                                        <div class="col col-stats ms-3 ms-sm-0">
                                            <div class="numbers">
                                                <p class="card-category">Total</p>
                                                <h4 class="card-title"><?= $stats['total'] ?></h4>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <div class="card card-stats card-round">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-icon">
                                            <div class="icon-big text-center icon-info bubble-shadow-small">
                                                <i class="fas fa-envelope"></i>
                                            </div>
                                        </div>
                                        <div class="col col-stats ms-3 ms-sm-0">
                                            <div class="numbers">
                                                <p class="card-category">Non Lues</p>
                                                <h4 class="card-title"><?= $stats['non_lues'] ?></h4>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <div class="card card-stats card-round">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-icon">
                                            <div class="icon-big text-center icon-danger bubble-shadow-small">
                                                <i class="fas fa-exclamation-triangle"></i>
                                            </div>
                                        </div>
                                        <div class="col col-stats ms-3 ms-sm-0">
                                            <div class="numbers">
                                                <p class="card-category">Critiques</p>
                                                <h4 class="card-title"><?= $stats['critiques'] ?></h4>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <div class="card card-stats card-round">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-icon">
                                            <div class="icon-big text-center icon-warning bubble-shadow-small">
                                                <i class="fas fa-clock"></i>
                                            </div>
                                        </div>
                                        <div class="col col-stats ms-3 ms-sm-0">
                                            <div class="numbers">
                                                <p class="card-category">Urgentes</p>
                                                <h4 class="card-title"><?= $stats['high'] ?></h4>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filtres -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <form method="GET" class="row g-3">
                                        <div class="col-md-3">
                                            <label class="form-label">Type</label>
                                            <select name="type" class="form-select" onchange="this.form.submit()">
                                                <option value="">Tous</option>
                                                <option value="LIBERATION_IMMINENTE"
                                                    <?= $type === 'LIBERATION_IMMINENTE' ? 'selected' : '' ?>>Libération
                                                    Imminente</option>
                                                <option value="FIN_DETENTION_PROVISOIRE"
                                                    <?= $type === 'FIN_DETENTION_PROVISOIRE' ? 'selected' : '' ?>>Fin
                                                    Détention Provisoire</option>
                                                <option value="DOCUMENT_MANQUANT"
                                                    <?= $type === 'DOCUMENT_MANQUANT' ? 'selected' : '' ?>>Document
                                                    Manquant</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Urgence</label>
                                            <select name="urgence" class="form-select" onchange="this.form.submit()">
                                                <option value="">Toutes</option>
                                                <option value="CRITICAL">Critique</option>
                                                <option value="HIGH">Haute</option>
                                                <option value="MEDIUM">Moyenne</option>
                                                <option value="LOW">Basse</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Statut</label>
                                            <select name="statut" class="form-select" onchange="this.form.submit()">
                                                <option value="">Toutes</option>
                                                <option value="non_lue">Non lues</option>
                                                <option value="lue">Lues</option>
                                            </select>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Liste -->
                    <div class="row">
                        <div class="col-12">
                            <?php if (empty($notifications)): ?>
                            <div class="card">
                                <div class="card-body text-center py-5">
                                    <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                                    <h4>Aucune notification</h4>
                                    <p class="text-muted">Vous êtes à jour !</p>
                                </div>
                            </div>
                            <?php else: ?>
                            <?php foreach ($notifications as $notif): ?>
                            <div class="card notif-item <?= !$notif['is_read'] ? 'unread' : '' ?> mb-3">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <div class="d-flex align-items-center mb-2">
                                                <span class="badge urgence-<?= $notif['urgence'] ?> me-2">
                                                    <?= $notif['urgence'] ?>
                                                </span>
                                                <span class="badge badge-secondary me-2">
                                                    <?= str_replace('_', ' ', $notif['type']) ?>
                                                </span>
                                                <small class="text-muted">
                                                    <?= date('d/m/Y H:i', strtotime($notif['created_at'])) ?>
                                                </small>
                                            </div>
                                            <h5 class="mb-2"><?= htmlspecialchars($notif['titre']) ?></h5>
                                            <p class="mb-2"><?= nl2br(htmlspecialchars($notif['message'])) ?></p>
                                            <?php if ($notif['detenu_nom']): ?>
                                            <p class="mb-0">
                                                <strong>Détenu:</strong> <?= htmlspecialchars($notif['detenu_nom']) ?>
                                                <span
                                                    class="text-muted">(<?= htmlspecialchars($notif['detenu_matricule']) ?>)</span>
                                            </p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="ms-3">
                                            <?php if (!$notif['is_read']): ?>
                                            <a href="?mark_read=<?= $notif['id'] ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-check"></i> Marquer lue
                                            </a>
                                            <?php else: ?>
                                            <span class="badge badge-success"><i class="fas fa-check"></i> Lue</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include '../../requires/script.php'; ?>
</body>

</html>