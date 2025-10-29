<?php
session_start();
if (!isset($_SESSION['user_id'])) {
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
$role = $user['role'] ?? '';

$refMgr = new ReferenceManager($pdo);

$message = '';
$messageType = '';

// Gestion suppression
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'delete' && isset($_POST['lieu_id']) && $role === 'ADMIN') {
        $lieuId = (int)$_POST['lieu_id'];

        if ($refMgr->deleteLieuDetention($lieuId)) {
            log_activity($pdo, $_SESSION['user_id'], 'Désactivation lieu détention', "ID: $lieuId");
            $message = "Lieu de détention désactivé avec succès.";
            $messageType = "success";
        } else {
            $message = "Erreur lors de la désactivation.";
            $messageType = "danger";
        }
    }
}

// Filtres
$type = $_GET['type'] ?? '';
$lieux = $refMgr->getAllLieuxDetention($type ?: null);

// Enrichir avec les capacités
foreach ($lieux as &$lieu) {
    $capacite = $refMgr->getCapaciteDisponible($lieu['id']);
    $lieu['nb_detenus'] = $capacite['nb_detenus'];
    $lieu['disponible'] = $capacite['disponible'];
    $lieu['taux_occupation'] = $capacite['taux_occupation'];
}

// Statistiques
$stats = [
    'total' => count($refMgr->getAllLieuxDetention()),
    'prisons_militaires' => count($refMgr->getAllLieuxDetention('PRISON_MILITAIRE')),
    'prisons_civiles' => count($refMgr->getAllLieuxDetention('PRISON_CIVILE')),
    'maisons_arret' => count($refMgr->getAllLieuxDetention('MAISON_ARRET'))
];

// Capacité totale
$capaciteTotal = 0;
$detenusTotal = 0;
$allLieux = $refMgr->getAllLieuxDetention();
foreach ($allLieux as $l) {
    $capaciteTotal += (int)($l['capacite'] ?? 0);
    $cap = $refMgr->getCapaciteDisponible($l['id']);
    $detenusTotal += $cap['nb_detenus'];
}
$tauxOccupationGlobal = $capaciteTotal > 0 ? round(($detenusTotal / $capaciteTotal) * 100, 1) : 0;
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Gestion des Lieux de Détention - Système Militaire</title>
    <meta content="width=device-width, initial-scale=1.0, shrink-to-fit=no" name="viewport" />
    <?php include '../../requires/link.php'; ?>
    <style>
    .occupation-bar {
        height: 20px;
        border-radius: 10px;
        overflow: hidden;
        background: #e9ecef;
    }

    .occupation-fill {
        height: 100%;
        transition: width 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        font-weight: bold;
        color: white;
    }

    .occupation-low {
        background: #28a745;
    }

    .occupation-medium {
        background: #ffc107;
    }

    .occupation-high {
        background: #fd7e14;
    }

    .occupation-full {
        background: #dc3545;
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
                            <h3 class="fw-bold mb-3">
                                <i class="fas fa-map-marker-alt me-2"></i>Gestion des Lieux de Détention
                            </h3>
                            <h6 class="op-7 mb-2">
                                <?= count($lieux) ?> lieu(x) trouvé(s)
                            </h6>
                        </div>
                        <div class="ms-md-auto py-2 py-md-0">
                            <a href="ajouter_lieu.php" class="btn btn-primary btn-round">
                                <i class="fas fa-plus me-2"></i>Nouveau Lieu
                            </a>
                        </div>
                    </div>

                    <?php if ($message): ?>
                    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
                        <strong><i class="fas fa-check-circle me-2"></i></strong>
                        <?= htmlspecialchars($message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <!-- Statistiques -->
                    <div class="row mb-4">
                        <div class="col-sm-6 col-md-3">
                            <div class="card card-stats card-round">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-icon">
                                            <div class="icon-big text-center icon-primary bubble-shadow-small">
                                                <i class="fas fa-building"></i>
                                            </div>
                                        </div>
                                        <div class="col col-stats ms-3 ms-sm-0">
                                            <div class="numbers">
                                                <p class="card-category">Total Lieux</p>
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
                                            <div class="icon-big text-center icon-success bubble-shadow-small">
                                                <i class="fas fa-users"></i>
                                            </div>
                                        </div>
                                        <div class="col col-stats ms-3 ms-sm-0">
                                            <div class="numbers">
                                                <p class="card-category">Capacité Totale</p>
                                                <h4 class="card-title"><?= number_format($capaciteTotal) ?></h4>
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
                                                <i class="fas fa-user-check"></i>
                                            </div>
                                        </div>
                                        <div class="col col-stats ms-3 ms-sm-0">
                                            <div class="numbers">
                                                <p class="card-category">Détenus Actuels</p>
                                                <h4 class="card-title"><?= number_format($detenusTotal) ?></h4>
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
                                            <div class="icon-big text-center icon-<?=
                                                                                    $tauxOccupationGlobal >= 90 ? 'danger' : ($tauxOccupationGlobal >= 70 ? 'warning' : 'info')
                                                                                    ?> bubble-shadow-small">
                                                <i class="fas fa-chart-pie"></i>
                                            </div>
                                        </div>
                                        <div class="col col-stats ms-3 ms-sm-0">
                                            <div class="numbers">
                                                <p class="card-category">Taux Occupation</p>
                                                <h4 class="card-title"><?= $tauxOccupationGlobal ?>%</h4>
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
                                    <form method="GET" action="" class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label">
                                                <i class="fas fa-filter me-2"></i>Type de Lieu
                                            </label>
                                            <select name="type" class="form-select" onchange="this.form.submit()">
                                                <option value="">Tous les types</option>
                                                <option value="PRISON_MILITAIRE"
                                                    <?= $type === 'PRISON_MILITAIRE' ? 'selected' : '' ?>>
                                                    Prison Militaire
                                                </option>
                                                <option value="PRISON_CIVILE"
                                                    <?= $type === 'PRISON_CIVILE' ? 'selected' : '' ?>>
                                                    Prison Civile
                                                </option>
                                                <option value="MAISON_ARRET"
                                                    <?= $type === 'MAISON_ARRET' ? 'selected' : '' ?>>
                                                    Maison d'Arrêt
                                                </option>
                                                <option value="AUTRES" <?= $type === 'AUTRES' ? 'selected' : '' ?>>
                                                    Autres
                                                </option>
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
                            <div class="card">
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table id="lieux-table" class="table table-hover table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Code</th>
                                                    <th>Nom</th>
                                                    <th>Type</th>
                                                    <th>Ville</th>
                                                    <th>Capacité</th>
                                                    <th>Détenus</th>
                                                    <th>Occupation</th>
                                                    <th>Statut</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($lieux)): ?>
                                                <tr>
                                                    <td colspan="9" class="text-center text-muted py-4">
                                                        <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                                        Aucun lieu de détention trouvé
                                                    </td>
                                                </tr>
                                                <?php else: ?>
                                                <?php foreach ($lieux as $lieu): ?>
                                                <tr>
                                                    <td><strong><?= htmlspecialchars($lieu['code']) ?></strong></td>
                                                    <td><?= htmlspecialchars($lieu['nom']) ?></td>
                                                    <td>
                                                        <span class="badge badge-<?=
                                                                                            $lieu['type'] === 'PRISON_MILITAIRE' ? 'primary' : ($lieu['type'] === 'PRISON_CIVILE' ? 'info' : ($lieu['type'] === 'MAISON_ARRET' ? 'warning' : 'secondary'))
                                                                                            ?>">
                                                            <?= str_replace('_', ' ', $lieu['type']) ?>
                                                        </span>
                                                    </td>
                                                    <td><?= htmlspecialchars($lieu['ville'] ?? 'N/A') ?></td>
                                                    <td class="text-center">
                                                        <strong><?= number_format($lieu['capacite'] ?? 0) ?></strong>
                                                    </td>
                                                    <td class="text-center">
                                                        <?= number_format($lieu['nb_detenus']) ?>
                                                    </td>
                                                    <td style="min-width: 150px;">
                                                        <?php
                                                                $taux = $lieu['taux_occupation'];
                                                                $classe = $taux >= 90 ? 'occupation-full' : ($taux >= 70 ? 'occupation-high' : ($taux >= 50 ? 'occupation-medium' : 'occupation-low'));
                                                                ?>
                                                        <div class="occupation-bar">
                                                            <div class="occupation-fill <?= $classe ?>"
                                                                style="width: <?= min(100, $taux) ?>%">
                                                                <?= $taux ?>%
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span
                                                            class="badge badge-<?= $lieu['is_active'] ? 'success' : 'secondary' ?>">
                                                            <?= $lieu['is_active'] ? 'Actif' : 'Inactif' ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group" role="group">
                                                            <a href="modifier_lieu.php?id=<?= $lieu['id'] ?>"
                                                                class="btn btn-sm btn-warning" title="Modifier">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <?php if ($role === 'ADMIN' && $lieu['is_active']): ?>
                                                            <button type="button" class="btn btn-sm btn-danger"
                                                                onclick="confirmDelete(<?= $lieu['id'] ?>, '<?= htmlspecialchars($lieu['nom']) ?>')"
                                                                title="Désactiver">
                                                                <i class="fas fa-ban"></i>
                                                            </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal suppression -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-ban me-2"></i>Désactiver le lieu
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Êtes-vous sûr de vouloir désactiver :</p>
                    <p class="text-center">
                        <strong id="lieuName" class="h5"></strong>
                    </p>
                    <p class="text-warning">
                        <i class="fas fa-info-circle me-2"></i>
                        Ce lieu ne sera plus disponible pour les nouvelles affectations.
                    </p>
                </div>
                <div class="modal-footer">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="lieu_id" id="lieuIdToDelete">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            Annuler
                        </button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-ban me-2"></i>Désactiver
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php include '../../requires/script.php'; ?>
    <script>
    function confirmDelete(id, name) {
        document.getElementById('lieuIdToDelete').value = id;
        document.getElementById('lieuName').textContent = name;
        var modal = new bootstrap.Modal(document.getElementById('deleteModal'));
        modal.show();
    }

    $(document).ready(function() {
        $('#lieux-table').DataTable({
            pageLength: 25,
            order: [
                [6, 'desc']
            ], // Trier par occupation
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/fr-FR.json'
            }
        });
    });
    </script>
</body>

</html>