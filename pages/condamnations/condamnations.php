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

$condamnationMgr = new CondamnationManager($pdo);
$refMgr = new ReferenceManager($pdo);

$message = '';
$messageType = '';

// Gestion de la libération
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'liberer' && isset($_POST['condamnation_id'], $_POST['motif'])) {
        $condamnationId = (int)$_POST['condamnation_id'];
        $motif = trim($_POST['motif']);
        
        if ($condamnationMgr->liberer($condamnationId, $motif, $_SESSION['user_id'])) {
            log_activity($pdo, $_SESSION['user_id'], 'Libération condamné', "Condamnation ID: $condamnationId");
            $message = "Libération effectuée avec succès.";
            $messageType = "success";
        } else {
            $message = "Erreur lors de la libération.";
            $messageType = "danger";
        }
    }
}

// Filtres
$filters = [];
if (!empty($_GET['statut'])) {
    $filters['statut'] = $_GET['statut'];
}
if (!empty($_GET['infraction_id'])) {
    $filters['infraction_id'] = (int)$_GET['infraction_id'];
}
if (!empty($_GET['lieu_id'])) {
    $filters['lieu_id'] = (int)$_GET['lieu_id'];
}
if (!empty($_GET['alerte'])) {
    $filters['alerte'] = true;
}

// Récupérer les condamnations
$condamnations = $condamnationMgr->getAll($filters);

// Données pour les filtres
$infractions = $refMgr->getAllInfractions();
$lieux = $refMgr->getAllLieuxDetention();
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Gestion des Condamnations - Système Militaire</title>
    <meta content="width=device-width, initial-scale=1.0, shrink-to-fit=no" name="viewport" />
    <?php include '../../requires/link.php'; ?>
    <style>
    .badge-alerte {
        font-size: 11px;
        padding: 5px 10px;
    }

    .alerte-CRITIQUE {
        background: #dc3545;
    }

    .alerte-URGENT {
        background: #fd7e14;
    }

    .alerte-ATTENTION {
        background: #ffc107;
        color: #000;
    }

    .alerte-A_SUIVRE {
        background: #17a2b8;
    }

    .alerte-NORMAL {
        background: #28a745;
    }

    .alerte-LIBERABLE {
        background: #6c757d;
    }

    .filter-card {
        background: #f8f9fa;
        border-left: 4px solid #dc3545;
    }

    .jours-restants {
        font-weight: 700;
        font-size: 1.1em;
    }

    .jours-negatif {
        color: #dc3545;
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
                    <!-- Header -->
                    <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4">
                        <div>
                            <h3 class="fw-bold mb-3">
                                <i class="fas fa-gavel me-2"></i>Gestion des Condamnations
                            </h3>
                            <h6 class="op-7 mb-2">
                                <?= count($condamnations) ?> condamnation(s) trouvée(s)
                            </h6>
                        </div>
                        <div class="ms-md-auto py-2 py-md-0">
                            <a href="ajouter_condamnation.php" class="btn btn-primary btn-round">
                                <i class="fas fa-plus me-2"></i>Nouvelle Condamnation
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

                    <!-- Filtres -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card filter-card">
                                <div class="card-body">
                                    <form method="GET" action="" class="row g-3">
                                        <div class="col-md-3">
                                            <label class="form-label">
                                                <i class="fas fa-flag me-2"></i>Statut
                                            </label>
                                            <select name="statut" class="form-select">
                                                <option value="">Tous</option>
                                                <option value="EN_COURS"
                                                    <?= ($_GET['statut'] ?? '') === 'EN_COURS' ? 'selected' : '' ?>>
                                                    En cours
                                                </option>
                                                <option value="TERMINEE"
                                                    <?= ($_GET['statut'] ?? '') === 'TERMINEE' ? 'selected' : '' ?>>
                                                    Terminée
                                                </option>
                                                <option value="SUSPENDUE"
                                                    <?= ($_GET['statut'] ?? '') === 'SUSPENDUE' ? 'selected' : '' ?>>
                                                    Suspendue
                                                </option>
                                                <option value="ANNULEE"
                                                    <?= ($_GET['statut'] ?? '') === 'ANNULEE' ? 'selected' : '' ?>>
                                                    Annulée
                                                </option>
                                            </select>
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label">
                                                <i class="fas fa-balance-scale me-2"></i>Infraction
                                            </label>
                                            <select name="infraction_id" class="form-select">
                                                <option value="">Toutes</option>
                                                <?php foreach ($infractions as $inf): ?>
                                                <option value="<?= $inf['id'] ?>"
                                                    <?= ($_GET['infraction_id'] ?? '') == $inf['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($inf['libelle']) ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label">
                                                <i class="fas fa-map-marker-alt me-2"></i>Lieu de Détention
                                            </label>
                                            <select name="lieu_id" class="form-select">
                                                <option value="">Tous</option>
                                                <?php foreach ($lieux as $lieu): ?>
                                                <option value="<?= $lieu['id'] ?>"
                                                    <?= ($_GET['lieu_id'] ?? '') == $lieu['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($lieu['nom']) ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label d-block">&nbsp;</label>
                                            <button type="submit" class="btn btn-primary w-100">
                                                <i class="fas fa-filter me-2"></i>Filtrer
                                            </button>
                                        </div>

                                        <div class="col-12">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="alerte" value="1"
                                                    id="alerte" <?= !empty($_GET['alerte']) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="alerte">
                                                    <i class="fas fa-bell text-danger me-2"></i>
                                                    Libérations imminentes uniquement (30 jours)
                                                </label>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Liste des condamnations -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table id="condamnations-table" class="table table-hover table-striped">
                                            <thead>
                                                <tr>
                                                    <th>N° Dossier</th>
                                                    <th>Détenu</th>
                                                    <th>Infraction</th>
                                                    <th>Date Jugement</th>
                                                    <th>Peine</th>
                                                    <th>Libération</th>
                                                    <th>Jours Restants</th>
                                                    <th>Alerte</th>
                                                    <th>Statut</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($condamnations)): ?>
                                                <tr>
                                                    <td colspan="10" class="text-center text-muted py-4">
                                                        <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                                        Aucune condamnation trouvée
                                                    </td>
                                                </tr>
                                                <?php else: ?>
                                                <?php foreach ($condamnations as $cond): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?= htmlspecialchars($cond['numero_dossier']) ?></strong>
                                                    </td>
                                                    <td>
                                                        <strong><?= htmlspecialchars($cond['detenu']) ?></strong><br>
                                                        <small
                                                            class="text-muted"><?= htmlspecialchars($cond['matricule']) ?></small>
                                                    </td>
                                                    <td>
                                                        <?= htmlspecialchars($cond['infraction']) ?>
                                                        <br>
                                                        <span class="badge badge-<?= 
                                                                    $cond['categorie'] === 'CRIME' ? 'danger' : 
                                                                    ($cond['categorie'] === 'DELIT' ? 'warning' : 'info') 
                                                                ?>">
                                                            <?= htmlspecialchars($cond['categorie']) ?>
                                                        </span>
                                                    </td>
                                                    <td><?= date('d/m/Y', strtotime($cond['date_jugement'])) ?></td>
                                                    <td>
                                                        <span class="badge badge-primary">
                                                            <?= htmlspecialchars($cond['peine']) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if ($cond['date_liberation_effective']): ?>
                                                        <?= date('d/m/Y', strtotime($cond['date_liberation_effective'])) ?>
                                                        <?php else: ?>
                                                        <span class="text-muted">N/A</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                                $jours = (int)$cond['jours_restants'];
                                                                $class = $jours < 0 ? 'jours-negatif' : '';
                                                                ?>
                                                        <span class="jours-restants <?= $class ?>">
                                                            <?= $jours < 0 ? 'DÉPASSÉ' : $jours ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span
                                                            class="badge badge-alerte alerte-<?= $cond['alerte_niveau'] ?>">
                                                            <?= str_replace('_', ' ', $cond['alerte_niveau']) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                                $statutBadge = [
                                                                    'EN_COURS' => 'warning',
                                                                    'TERMINEE' => 'success',
                                                                    'SUSPENDUE' => 'info',
                                                                    'ANNULEE' => 'secondary'
                                                                ];
                                                                $badge = $statutBadge[$cond['statut']] ?? 'secondary';
                                                                ?>
                                                        <span class="badge badge-<?= $badge ?>">
                                                            <?= str_replace('_', ' ', $cond['statut']) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group" role="group">
                                                            <a href="voir_condamnation.php?id=<?= $cond['id'] ?>"
                                                                class="btn btn-sm btn-info" title="Voir détails">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                            <?php if ($cond['statut'] === 'EN_COURS'): ?>
                                                            <a href="modifier_condamnation.php?id=<?= $cond['id'] ?>"
                                                                class="btn btn-sm btn-warning" title="Modifier">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <?php if ($role === 'ADMIN' && $jours <= 0): ?>
                                                            <button type="button" class="btn btn-sm btn-success"
                                                                onclick="confirmLiberation(<?= $cond['id'] ?>, '<?= htmlspecialchars($cond['detenu']) ?>')"
                                                                title="Libérer">
                                                                <i class="fas fa-door-open"></i>
                                                            </button>
                                                            <?php endif; ?>
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

    <!-- Modal de libération -->
    <div class="modal fade" id="liberationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-door-open me-2"></i>
                        Libération du Condamné
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <p>Confirmer la libération de :</p>
                        <p class="text-center">
                            <strong id="detenuName" class="h5"></strong>
                        </p>

                        <div class="mb-3">
                            <label class="form-label">Motif de libération <span class="text-danger">*</span></label>
                            <select name="motif" class="form-select" required>
                                <option value="">Sélectionner un motif</option>
                                <option value="Fin de peine">Fin de peine</option>
                                <option value="Remise de peine">Remise de peine</option>
                                <option value="Grâce présidentielle">Grâce présidentielle</option>
                                <option value="Amnistie">Amnistie</option>
                                <option value="Acquittement en appel">Acquittement en appel</option>
                                <option value="Autre">Autre</option>
                            </select>
                        </div>

                        <div class="alert alert-warning">
                            <i class="fas fa-info-circle me-2"></i>
                            Cette action mettra fin à la condamnation et changera le statut du détenu.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <input type="hidden" name="action" value="liberer">
                        <input type="hidden" name="condamnation_id" id="condamnationIdToLiberate">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            Annuler
                        </button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-door-open me-2"></i>Confirmer la Libération
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include '../../requires/script.php'; ?>
    <script>
    function confirmLiberation(id, name) {
        document.getElementById('condamnationIdToLiberate').value = id;
        document.getElementById('detenuName').textContent = name;
        var modal = new bootstrap.Modal(document.getElementById('liberationModal'));
        modal.show();
    }

    $(document).ready(function() {
        $('#condamnations-table').DataTable({
            pageLength: 25,
            order: [
                [6, 'asc']
            ], // Trier par jours restants
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/fr-FR.json'
            }
        });
    });
    </script>
</body>

</html>