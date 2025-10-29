<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit();
}

require_once '../../includes/db.php';
require_once '../../includes/classes/autoload.php';
require_once '../../includes/logs.php';

// Récupérer l'utilisateur
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
$name = $user ? htmlspecialchars($user['nom'] . ' ' . $user['prenom']) : '';
$role = $user['role'] ?? '';

// Initialiser les managers
$detenuMgr = new DetenuManager($pdo);
$refMgr = new ReferenceManager($pdo);

// Gestion de la suppression
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'delete' && isset($_POST['detenu_id'])) {
        $detenuId = (int)$_POST['detenu_id'];

        if ($detenuMgr->delete($detenuId, $_SESSION['user_id'])) {
            log_activity($pdo, $_SESSION['user_id'], 'Suppression détenu', "ID: $detenuId");
            $message = "Détenu supprimé avec succès.";
            $messageType = "success";
        } else {
            $message = "Erreur lors de la suppression.";
            $messageType = "danger";
        }
    }
}

// Filtres
$filters = [];
if (!empty($_GET['statut'])) {
    $filters['statut'] = $_GET['statut'];
}
if (!empty($_GET['grade_id'])) {
    $filters['grade_id'] = (int)$_GET['grade_id'];
}
if (!empty($_GET['unite_id'])) {
    $filters['unite_id'] = (int)$_GET['unite_id'];
}
if (!empty($_GET['multrecidiviste'])) {
    $filters['multrecidiviste'] = true;
}
if (!empty($_GET['search'])) {
    $filters['search'] = $_GET['search'];
}

// Récupérer les détenus
$detenus = $detenuMgr->getAll($filters);

// Données pour les filtres
$grades = $refMgr->getAllGrades();
$unites = $refMgr->getAllUnites();
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Gestion des Détenus - Système Militaire</title>
    <meta content="width=device-width, initial-scale=1.0, shrink-to-fit=no" name="viewport" />
    <?php include '../../requires/link.php'; ?>
    <style>
    .badge-statut {
        padding: 6px 12px;
        font-size: 11px;
    }

    .photo-thumbnail {
        width: 40px;
        height: 40px;
        object-fit: cover;
        border-radius: 5px;
    }

    .filter-card {
        background: #f8f9fa;
        border-left: 4px solid #177dff;
    }

    .action-btn {
        padding: 5px 10px;
        font-size: 12px;
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
                                <i class="fas fa-users me-2"></i>Gestion des Détenus
                            </h3>
                            <h6 class="op-7 mb-2">
                                <?= count($detenus) ?> détenu(s) trouvé(s)
                            </h6>
                        </div>
                        <div class="ms-md-auto py-2 py-md-0">
                            <a href="ajouter_detenu.php" class="btn btn-primary btn-round">
                                <i class="fas fa-plus me-2"></i>Nouveau Détenu
                            </a>
                        </div>
                    </div>

                    <?php if ($message): ?>
                    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
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
                                                <i class="fas fa-search me-2"></i>Recherche
                                            </label>
                                            <input type="text" name="search" class="form-control"
                                                placeholder="Nom, matricule..."
                                                value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                                        </div>

                                        <div class="col-md-2">
                                            <label class="form-label">
                                                <i class="fas fa-flag me-2"></i>Statut
                                            </label>
                                            <select name="statut" class="form-select">
                                                <option value="">Tous</option>
                                                <option value="CONDAMNE"
                                                    <?= ($_GET['statut'] ?? '') === 'CONDAMNE' ? 'selected' : '' ?>>
                                                    Condamné
                                                </option>
                                                <option value="DETENTION_PROVISOIRE"
                                                    <?= ($_GET['statut'] ?? '') === 'DETENTION_PROVISOIRE' ? 'selected' : '' ?>>
                                                    Détention Provisoire
                                                </option>
                                                <option value="LIBRE"
                                                    <?= ($_GET['statut'] ?? '') === 'LIBRE' ? 'selected' : '' ?>>
                                                    Libre
                                                </option>
                                                <option value="EVADE"
                                                    <?= ($_GET['statut'] ?? '') === 'EVADE' ? 'selected' : '' ?>>
                                                    Évadé
                                                </option>
                                            </select>
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label">
                                                <i class="fas fa-star me-2"></i>Grade
                                            </label>
                                            <select name="grade_id" class="form-select">
                                                <option value="">Tous les grades</option>
                                                <?php foreach ($grades as $grade): ?>
                                                <option value="<?= $grade['id'] ?>"
                                                    <?= ($_GET['grade_id'] ?? '') == $grade['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($grade['libelle']) ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label">
                                                <i class="fas fa-building me-2"></i>Unité
                                            </label>
                                            <select name="unite_id" class="form-select">
                                                <option value="">Toutes les unités</option>
                                                <?php foreach ($unites as $unite): ?>
                                                <option value="<?= $unite['id'] ?>"
                                                    <?= ($_GET['unite_id'] ?? '') == $unite['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($unite['nom']) ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="col-md-1">
                                            <label class="form-label d-block">&nbsp;</label>
                                            <button type="submit" class="btn btn-primary w-100">
                                                <i class="fas fa-filter"></i>
                                            </button>
                                        </div>

                                        <div class="col-12">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="multrecidiviste"
                                                    value="1" id="multrecidiviste"
                                                    <?= !empty($_GET['multrecidiviste']) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="multrecidiviste">
                                                    <i class="fas fa-exclamation-triangle text-danger me-2"></i>
                                                    Multirécidivistes uniquement
                                                </label>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Liste des détenus -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table id="detenus-table" class="table table-hover table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Photo</th>
                                                    <th>Matricule</th>
                                                    <th>Nom Complet</th>
                                                    <th>Grade</th>
                                                    <th>Unité</th>
                                                    <th>Âge</th>
                                                    <th>Statut</th>
                                                    <th>Condamnations</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($detenus)): ?>
                                                <tr>
                                                    <td colspan="9" class="text-center text-muted py-4">
                                                        <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                                        Aucun détenu trouvé
                                                    </td>
                                                </tr>
                                                <?php else: ?>
                                                <?php foreach ($detenus as $detenu): ?>
                                                <tr>
                                                    <td>
                                                        <?php if (!empty($detenu['photo_path'])): ?>
                                                        <img src="../../<?= htmlspecialchars($detenu['photo_path']) ?>"
                                                            class="photo-thumbnail" alt="Photo">
                                                        <?php else: ?>
                                                        <div
                                                            class="photo-thumbnail bg-secondary d-flex align-items-center justify-content-center text-white">
                                                            <i class="fas fa-user"></i>
                                                        </div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <strong><?= htmlspecialchars($detenu['matricule']) ?></strong>
                                                    </td>
                                                    <td>
                                                        <strong><?= htmlspecialchars($detenu['nom_complet']) ?></strong>
                                                        <?php if ($detenu['is_multrecidiviste']): ?>
                                                        <span class="badge badge-danger ms-2" title="Multirécidiviste">
                                                            <i class="fas fa-exclamation-triangle"></i>
                                                        </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge badge-info">
                                                            <?= htmlspecialchars($detenu['grade_code']) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <small><?= htmlspecialchars($detenu['unite_code']) ?></small>
                                                    </td>
                                                    <td><?= (int)$detenu['age'] ?> ans</td>
                                                    <td>
                                                        <?php
                                                                $statutColors = [
                                                                    'CONDAMNE' => 'danger',
                                                                    'DETENTION_PROVISOIRE' => 'warning',
                                                                    'LIBRE' => 'success',
                                                                    'EVADE' => 'dark',
                                                                    'DECEDE' => 'secondary'
                                                                ];
                                                                $color = $statutColors[$detenu['statut_actuel']] ?? 'secondary';
                                                                ?>
                                                        <span class="badge badge-statut badge-<?= $color ?>">
                                                            <?= str_replace('_', ' ', $detenu['statut_actuel']) ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge badge-primary">
                                                            <?= (int)$detenu['nombre_condamnations'] ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group" role="group">
                                                            <a href="voir_detenu.php?id=<?= $detenu['id'] ?>"
                                                                class="btn btn-sm btn-info action-btn"
                                                                title="Voir détails">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                            <a href="modifier_detenu.php?id=<?= $detenu['id'] ?>"
                                                                class="btn btn-sm btn-warning action-btn"
                                                                title="Modifier">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <?php if ($role === 'ADMIN'): ?>
                                                            <button type="button"
                                                                class="btn btn-sm btn-danger action-btn"
                                                                onclick="confirmDelete(<?= $detenu['id'] ?>, '<?= htmlspecialchars($detenu['nom_complet']) ?>')"
                                                                title="Supprimer">
                                                                <i class="fas fa-trash"></i>
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

    <!-- Modal de confirmation de suppression -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Confirmer la suppression
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Êtes-vous sûr de vouloir supprimer le détenu :</p>
                    <p class="text-center">
                        <strong id="detenuName" class="h5"></strong>
                    </p>
                    <p class="text-danger">
                        <i class="fas fa-info-circle me-2"></i>
                        Cette action est irréversible.
                    </p>
                </div>
                <div class="modal-footer">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="detenu_id" id="detenuIdToDelete">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            Annuler
                        </button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash me-2"></i>Supprimer
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php include '../../requires/script.php'; ?>
    <script>
    function confirmDelete(id, name) {
        document.getElementById('detenuIdToDelete').value = id;
        document.getElementById('detenuName').textContent = name;
        var modal = new bootstrap.Modal(document.getElementById('deleteModal'));
        modal.show();
    }

    $(document).ready(function() {
        $('#detenus-table').DataTable({
            pageLength: 25,
            order: [
                [1, 'desc']
            ],
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/fr-FR.json'
            }
        });
    });
    </script>
</body>

</html>