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

// Gestion actions (Admin uniquement)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $role === 'ADMIN') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'delete' && isset($_POST['unite_id'])) {
            $uniteId = (int)$_POST['unite_id'];

            // Vérifier si l'unité est utilisée
            $stmt = $pdo->prepare("SELECT COUNT(*) as nb FROM detenus WHERE unite_id = ? AND is_deleted = FALSE");
            $stmt->execute([$uniteId]);
            $nbDetenus = (int)$stmt->fetch()['nb'];

            if ($nbDetenus > 0) {
                $message = "Impossible de supprimer cette unité : $nbDetenus détenu(s) l'utilisent.";
                $messageType = "danger";
            } else {
                $stmt = $pdo->prepare("UPDATE unites SET is_active = FALSE WHERE id = ?");
                if ($stmt->execute([$uniteId])) {
                    log_activity($pdo, $_SESSION['user_id'], 'Désactivation unité', "Unité ID: $uniteId");
                    $message = "Unité désactivée avec succès.";
                    $messageType = "success";
                }
            }
        }

        if ($_POST['action'] === 'activate' && isset($_POST['unite_id'])) {
            $uniteId = (int)$_POST['unite_id'];
            $stmt = $pdo->prepare("UPDATE unites SET is_active = TRUE WHERE id = ?");
            if ($stmt->execute([$uniteId])) {
                log_activity($pdo, $_SESSION['user_id'], 'Activation unité', "Unité ID: $uniteId");
                $message = "Unité activée avec succès.";
                $messageType = "success";
            }
        }
    }
}

// Filtres
$type = $_GET['type'] ?? '';
$unites = $refMgr->getAllUnites($type ?: null);

// Ajouter le nombre de détenus pour chaque unité
foreach ($unites as &$unite) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as nb FROM detenus WHERE unite_id = ? AND is_deleted = FALSE");
    $stmt->execute([$unite['id']]);
    $unite['nb_detenus'] = (int)$stmt->fetch()['nb'];
}

// Statistiques
$stats = [
    'total' => count($refMgr->getAllUnites()),
    'armees' => count($refMgr->getAllUnites('ARMEE')),
    'gendarmeries' => count($refMgr->getAllUnites('GENDARMERIE')),
    'polices' => count($refMgr->getAllUnites('POLICE')),
    'utilises' => count(array_filter($unites, fn($u) => $u['nb_detenus'] > 0))
];
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Gestion des Unités - Système Militaire</title>
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
                    <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4">
                        <div>
                            <h3 class="fw-bold mb-3">
                                <i class="fas fa-building me-2"></i>Gestion des Unités Militaires
                            </h3>
                            <h6 class="op-7 mb-2"><?= count($unites) ?> unité(s) trouvée(s)</h6>
                        </div>
                        <?php if ($role === 'ADMIN'): ?>
                            <div class="ms-md-auto py-2 py-md-0">
                                <a href="ajouter_unite.php" class="btn btn-primary btn-round">
                                    <i class="fas fa-plus me-2"></i>Nouvelle Unité
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($message): ?>
                        <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
                            <?= htmlspecialchars($message) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Statistiques -->
                    <div class="row mb-4">
                        <div class="col-sm-6 col-lg-3">
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
                                                <p class="card-category">Total Unités</p>
                                                <h4 class="card-title"><?= $stats['total'] ?></h4>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="card card-stats card-round">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-icon">
                                            <div class="icon-big text-center icon-success bubble-shadow-small">
                                                <i class="fas fa-shield-alt"></i>
                                            </div>
                                        </div>
                                        <div class="col col-stats ms-3 ms-sm-0">
                                            <div class="numbers">
                                                <p class="card-category">Armée</p>
                                                <h4 class="card-title"><?= $stats['armees'] ?></h4>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="card card-stats card-round">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-icon">
                                            <div class="icon-big text-center icon-warning bubble-shadow-small">
                                                <i class="fas fa-user-shield"></i>
                                            </div>
                                        </div>
                                        <div class="col col-stats ms-3 ms-sm-0">
                                            <div class="numbers">
                                                <p class="card-category">Gendarmerie</p>
                                                <h4 class="card-title"><?= $stats['gendarmeries'] ?></h4>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="card card-stats card-round">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-icon">
                                            <div class="icon-big text-center icon-info bubble-shadow-small">
                                                <i class="fas fa-users"></i>
                                            </div>
                                        </div>
                                        <div class="col col-stats ms-3 ms-sm-0">
                                            <div class="numbers">
                                                <p class="card-category">Utilisées</p>
                                                <h4 class="card-title"><?= $stats['utilises'] ?></h4>
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
                                        <div class="col-md-4">
                                            <label class="form-label">
                                                <i class="fas fa-filter me-2"></i>Type d'Unité
                                            </label>
                                            <select name="type" class="form-select" onchange="this.form.submit()">
                                                <option value="">Tous les types</option>
                                                <option value="ARMEE" <?= $type === 'ARMEE' ? 'selected' : '' ?>>Armée
                                                </option>
                                                <option value="GENDARMERIE"
                                                    <?= $type === 'GENDARMERIE' ? 'selected' : '' ?>>Gendarmerie
                                                </option>
                                                <option value="POLICE" <?= $type === 'POLICE' ? 'selected' : '' ?>>
                                                    Police</option>
                                                <option value="AUTRES" <?= $type === 'AUTRES' ? 'selected' : '' ?>>
                                                    Autres</option>
                                            </select>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Liste des unités -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table id="unites-table" class="table table-hover table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Code</th>
                                                    <th>Nom</th>
                                                    <th>Type</th>
                                                    <th>Localisation</th>
                                                    <th>Détenus</th>
                                                    <th>Statut</th>
                                                    <?php if ($role === 'ADMIN'): ?>
                                                        <th>Actions</th>
                                                    <?php endif; ?>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($unites)): ?>
                                                    <tr>
                                                        <td colspan="7" class="text-center text-muted py-4">
                                                            <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                                            Aucune unité trouvée
                                                        </td>
                                                    </tr>
                                                <?php else: ?>
                                                    <?php foreach ($unites as $unite): ?>
                                                        <tr>
                                                            <td><strong><?= htmlspecialchars($unite['code']) ?></strong></td>
                                                            <td><?= htmlspecialchars($unite['nom']) ?></td>
                                                            <td>
                                                                <span class="badge badge-<?=
                                                                                            $unite['type'] === 'ARMEE' ? 'success' : ($unite['type'] === 'GENDARMERIE' ? 'warning' : ($unite['type'] === 'POLICE' ? 'info' : 'secondary'))
                                                                                            ?>">
                                                                    <?= htmlspecialchars($unite['type']) ?>
                                                                </span>
                                                            </td>
                                                            <td><?= htmlspecialchars($unite['localisation'] ?? 'N/A') ?></td>
                                                            <td>
                                                                <span class="badge badge-primary">
                                                                    <?= $unite['nb_detenus'] ?> détenu(s)
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <span
                                                                    class="badge badge-<?= $unite['is_active'] ? 'success' : 'secondary' ?>">
                                                                    <?= $unite['is_active'] ? 'Active' : 'Inactive' ?>
                                                                </span>
                                                            </td>
                                                            <?php if ($role === 'ADMIN'): ?>
                                                                <td>
                                                                    <div class="btn-group" role="group">
                                                                        <a href="modifier_unite.php?id=<?= $unite['id'] ?>"
                                                                            class="btn btn-sm btn-warning" title="Modifier">
                                                                            <i class="fas fa-edit"></i>
                                                                        </a>
                                                                        <?php if ($unite['is_active']): ?>
                                                                            <?php if ($unite['nb_detenus'] === 0): ?>
                                                                                <button type="button" class="btn btn-sm btn-danger"
                                                                                    onclick="toggleUnite(<?= $unite['id'] ?>, 'delete', '<?= htmlspecialchars($unite['nom']) ?>')"
                                                                                    title="Désactiver">
                                                                                    <i class="fas fa-ban"></i>
                                                                                </button>
                                                                            <?php else: ?>
                                                                                <button type="button" class="btn btn-sm btn-secondary"
                                                                                    disabled
                                                                                    title="Unité utilisée - Impossible de désactiver">
                                                                                    <i class="fas fa-lock"></i>
                                                                                </button>
                                                                            <?php endif; ?>
                                                                        <?php else: ?>
                                                                            <button type="button" class="btn btn-sm btn-success"
                                                                                onclick="toggleUnite(<?= $unite['id'] ?>, 'activate', '<?= htmlspecialchars($unite['nom']) ?>')"
                                                                                title="Activer">
                                                                                <i class="fas fa-check"></i>
                                                                            </button>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </td>
                                                            <?php endif; ?>
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

    <!-- Formulaire caché -->
    <form id="toggleForm" method="POST" style="display:none;">
        <input type="hidden" name="action" id="toggleAction">
        <input type="hidden" name="unite_id" id="toggleUniteId">
    </form>

    <?php include '../../requires/script.php'; ?>
    <script>
        function toggleUnite(id, action, nom) {
            const actionText = action === 'delete' ? 'désactiver' : 'activer';
            if (confirm(`Voulez-vous vraiment ${actionText} l'unité "${nom}" ?`)) {
                document.getElementById('toggleAction').value = action;
                document.getElementById('toggleUniteId').value = id;
                document.getElementById('toggleForm').submit();
            }
        }

        $(document).ready(function() {
            $('#unites-table').DataTable({
                pageLength: 25,
                order: [
                    [1, 'asc']
                ], // Trier par nom
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/fr-FR.json'
                }
            });
        });
    </script>
</body>

</html>