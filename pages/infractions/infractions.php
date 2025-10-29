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
    if ($_POST['action'] === 'delete' && isset($_POST['infraction_id']) && $role === 'ADMIN') {
        $infractionId = (int)$_POST['infraction_id'];
        
        if ($refMgr->deleteInfraction($infractionId)) {
            log_activity($pdo, $_SESSION['user_id'], 'Désactivation infraction', "ID: $infractionId");
            $message = "Infraction désactivée avec succès.";
            $messageType = "success";
        } else {
            $message = "Erreur lors de la désactivation.";
            $messageType = "danger";
        }
    }
}

// Filtres
$categorie = $_GET['categorie'] ?? '';
$infractions = $refMgr->getAllInfractions($categorie ?: null);

// Statistiques
$stats = [
    'total' => count($refMgr->getAllInfractions()),
    'crimes' => count($refMgr->getAllInfractions('CRIME')),
    'delits' => count($refMgr->getAllInfractions('DELIT')),
    'contraventions' => count($refMgr->getAllInfractions('CONTRAVENTION'))
];
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Gestion des Infractions - Système Militaire</title>
    <meta content="width=device-width, initial-scale=1.0, shrink-to-fit=no" name="viewport" />
    <?php include '../../requires/link.php'; ?>
    <style>
    .gravite-badge {
        display: inline-block;
        width: 30px;
        height: 30px;
        line-height: 30px;
        text-align: center;
        border-radius: 50%;
        font-weight: bold;
        color: white;
    }

    .gravite-1,
    .gravite-2,
    .gravite-3 {
        background: #28a745;
    }

    .gravite-4,
    .gravite-5,
    .gravite-6 {
        background: #ffc107;
        color: #000;
    }

    .gravite-7,
    .gravite-8 {
        background: #fd7e14;
    }

    .gravite-9,
    .gravite-10 {
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
                                <i class="fas fa-balance-scale me-2"></i>Gestion des Infractions
                            </h3>
                            <h6 class="op-7 mb-2">
                                <?= count($infractions) ?> infraction(s) trouvée(s)
                            </h6>
                        </div>
                        <div class="ms-md-auto py-2 py-md-0">
                            <a href="ajouter_infraction.php" class="btn btn-primary btn-round">
                                <i class="fas fa-plus me-2"></i>Nouvelle Infraction
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
                                                <i class="fas fa-list"></i>
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
                                            <div class="icon-big text-center icon-danger bubble-shadow-small">
                                                <i class="fas fa-exclamation-triangle"></i>
                                            </div>
                                        </div>
                                        <div class="col col-stats ms-3 ms-sm-0">
                                            <div class="numbers">
                                                <p class="card-category">Crimes</p>
                                                <h4 class="card-title"><?= $stats['crimes'] ?></h4>
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
                                                <i class="fas fa-gavel"></i>
                                            </div>
                                        </div>
                                        <div class="col col-stats ms-3 ms-sm-0">
                                            <div class="numbers">
                                                <p class="card-category">Délits</p>
                                                <h4 class="card-title"><?= $stats['delits'] ?></h4>
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
                                                <i class="fas fa-file-alt"></i>
                                            </div>
                                        </div>
                                        <div class="col col-stats ms-3 ms-sm-0">
                                            <div class="numbers">
                                                <p class="card-category">Contraventions</p>
                                                <h4 class="card-title"><?= $stats['contraventions'] ?></h4>
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
                                                <i class="fas fa-filter me-2"></i>Catégorie
                                            </label>
                                            <select name="categorie" class="form-select" onchange="this.form.submit()">
                                                <option value="">Toutes les catégories</option>
                                                <option value="CRIME" <?= $categorie === 'CRIME' ? 'selected' : '' ?>>
                                                    Crimes</option>
                                                <option value="DELIT" <?= $categorie === 'DELIT' ? 'selected' : '' ?>>
                                                    Délits</option>
                                                <option value="CONTRAVENTION"
                                                    <?= $categorie === 'CONTRAVENTION' ? 'selected' : '' ?>>
                                                    Contraventions</option>
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
                                        <table id="infractions-table" class="table table-hover table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Code</th>
                                                    <th>Libellé</th>
                                                    <th>Catégorie</th>
                                                    <th>Gravité</th>
                                                    <th>Durée DP Max</th>
                                                    <th>Statut</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($infractions)): ?>
                                                <tr>
                                                    <td colspan="7" class="text-center text-muted py-4">
                                                        <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                                        Aucune infraction trouvée
                                                    </td>
                                                </tr>
                                                <?php else: ?>
                                                <?php foreach ($infractions as $inf): ?>
                                                <tr>
                                                    <td><strong><?= htmlspecialchars($inf['code']) ?></strong></td>
                                                    <td><?= htmlspecialchars($inf['libelle']) ?></td>
                                                    <td>
                                                        <span class="badge badge-<?= 
                                                                    $inf['categorie'] === 'CRIME' ? 'danger' : 
                                                                    ($inf['categorie'] === 'DELIT' ? 'warning' : 'info') 
                                                                ?>">
                                                            <?= htmlspecialchars($inf['categorie']) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="gravite-badge gravite-<?= (int)$inf['gravite'] ?>">
                                                            <?= (int)$inf['gravite'] ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?= $inf['duree_detention_provisoire_mois'] ? 
                                                                    (int)$inf['duree_detention_provisoire_mois'] . ' mois' : 'N/A' ?>
                                                    </td>
                                                    <td>
                                                        <span
                                                            class="badge badge-<?= $inf['is_active'] ? 'success' : 'secondary' ?>">
                                                            <?= $inf['is_active'] ? 'Active' : 'Inactive' ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group" role="group">
                                                            <a href="modifier_infraction.php?id=<?= $inf['id'] ?>"
                                                                class="btn btn-sm btn-warning" title="Modifier">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <?php if ($role === 'ADMIN' && $inf['is_active']): ?>
                                                            <button type="button" class="btn btn-sm btn-danger"
                                                                onclick="confirmDelete(<?= $inf['id'] ?>, '<?= htmlspecialchars($inf['libelle']) ?>')"
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
                        <i class="fas fa-ban me-2"></i>Désactiver l'infraction
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Êtes-vous sûr de vouloir désactiver :</p>
                    <p class="text-center">
                        <strong id="infractionName" class="h5"></strong>
                    </p>
                    <p class="text-warning">
                        <i class="fas fa-info-circle me-2"></i>
                        Cette infraction ne sera plus disponible pour les nouvelles condamnations.
                    </p>
                </div>
                <div class="modal-footer">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="infraction_id" id="infractionIdToDelete">
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
        document.getElementById('infractionIdToDelete').value = id;
        document.getElementById('infractionName').textContent = name;
        var modal = new bootstrap.Modal(document.getElementById('deleteModal'));
        modal.show();
    }

    $(document).ready(function() {
        $('#infractions-table').DataTable({
            pageLength: 25,
            order: [
                [3, 'desc']
            ], // Trier par gravité
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/fr-FR.json'
            }
        });
    });
    </script>
</body>

</html>