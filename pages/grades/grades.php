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
        if ($_POST['action'] === 'delete' && isset($_POST['grade_id'])) {
            $gradeId = (int)$_POST['grade_id'];

            // Vérifier si le grade est utilisé
            $stmt = $pdo->prepare("SELECT COUNT(*) as nb FROM detenus WHERE grade_id = ? AND is_deleted = FALSE");
            $stmt->execute([$gradeId]);
            $nbDetenus = (int)$stmt->fetch()['nb'];

            if ($nbDetenus > 0) {
                $message = "Impossible de supprimer ce grade : $nbDetenus détenu(s) l'utilisent.";
                $messageType = "danger";
            } else {
                $stmt = $pdo->prepare("UPDATE grades SET is_active = FALSE WHERE id = ?");
                if ($stmt->execute([$gradeId])) {
                    log_activity($pdo, $_SESSION['user_id'], 'Désactivation grade', "Grade ID: $gradeId");
                    $message = "Grade désactivé avec succès.";
                    $messageType = "success";
                }
            }
        }

        if ($_POST['action'] === 'activate' && isset($_POST['grade_id'])) {
            $gradeId = (int)$_POST['grade_id'];
            $stmt = $pdo->prepare("UPDATE grades SET is_active = TRUE WHERE id = ?");
            if ($stmt->execute([$gradeId])) {
                log_activity($pdo, $_SESSION['user_id'], 'Activation grade', "Grade ID: $gradeId");
                $message = "Grade activé avec succès.";
                $messageType = "success";
            }
        }
    }
}

// Récupérer tous les grades
$grades = $refMgr->getAllGrades();

// Ajouter le nombre de détenus pour chaque grade
foreach ($grades as &$grade) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as nb FROM detenus WHERE grade_id = ? AND is_deleted = FALSE");
    $stmt->execute([$grade['id']]);
    $grade['nb_detenus'] = (int)$stmt->fetch()['nb'];
}

// Statistiques
$stats = [
    'total' => count($grades),
    'actifs' => count(array_filter($grades, fn($g) => $g['is_active'])),
    'utilises' => count(array_filter($grades, fn($g) => $g['nb_detenus'] > 0))
];
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Gestion des Grades - Système Militaire</title>
    <meta content="width=device-width, initial-scale=1.0, shrink-to-fit=no" name="viewport" />
    <?php include '../../requires/link.php'; ?>
    <style>
        .hierarchie-badge {
            display: inline-block;
            width: 35px;
            height: 35px;
            line-height: 35px;
            text-align: center;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: bold;
        }

        .grade-card {
            transition: all 0.3s;
            border-left: 4px solid transparent;
        }

        .grade-card:hover {
            border-left-color: #177dff;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
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
                                <i class="fas fa-star me-2"></i>Gestion des Grades Militaires
                            </h3>
                            <h6 class="op-7 mb-2"><?= count($grades) ?> grade(s) au total</h6>
                        </div>
                        <?php if ($role === 'ADMIN'): ?>
                            <div class="ms-md-auto py-2 py-md-0">
                                <a href="ajouter_grade.php" class="btn btn-primary btn-round">
                                    <i class="fas fa-plus me-2"></i>Nouveau Grade
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
                        <div class="col-sm-6 col-md-4">
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
                                                <p class="card-category">Total Grades</p>
                                                <h4 class="card-title"><?= $stats['total'] ?></h4>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <div class="card card-stats card-round">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-icon">
                                            <div class="icon-big text-center icon-success bubble-shadow-small">
                                                <i class="fas fa-check-circle"></i>
                                            </div>
                                        </div>
                                        <div class="col col-stats ms-3 ms-sm-0">
                                            <div class="numbers">
                                                <p class="card-category">Grades Actifs</p>
                                                <h4 class="card-title"><?= $stats['actifs'] ?></h4>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-4">
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
                                                <p class="card-category">Grades Utilisés</p>
                                                <h4 class="card-title"><?= $stats['utilises'] ?></h4>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Info hiérarchie -->
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Hiérarchie militaire :</strong> Les grades sont classés par ordre hiérarchique croissant
                        (1 = grade le plus bas, 20 = grade le plus élevé)
                    </div>

                    <!-- Liste des grades -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table id="grades-table" class="table table-hover table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Hiérarchie</th>
                                                    <th>Code</th>
                                                    <th>Libellé</th>
                                                    <th>Détenus</th>
                                                    <th>Statut</th>
                                                    <th>Date Création</th>
                                                    <?php if ($role === 'ADMIN'): ?>
                                                        <th>Actions</th>
                                                    <?php endif; ?>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($grades as $grade): ?>
                                                    <tr>
                                                        <td>
                                                            <span class="hierarchie-badge">
                                                                <?= (int)$grade['hierarchie'] ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <strong><?= htmlspecialchars($grade['code']) ?></strong>
                                                        </td>
                                                        <td><?= htmlspecialchars($grade['libelle']) ?></td>
                                                        <td>
                                                            <span class="badge badge-primary">
                                                                <?= $grade['nb_detenus'] ?> détenu(s)
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span
                                                                class="badge badge-<?= $grade['is_active'] ? 'success' : 'secondary' ?>">
                                                                <?= $grade['is_active'] ? 'Actif' : 'Inactif' ?>
                                                            </span>
                                                        </td>
                                                        <td><?= date('d/m/Y', strtotime($grade['created_at'])) ?></td>
                                                        <?php if ($role === 'ADMIN'): ?>
                                                            <td>
                                                                <div class="btn-group" role="group">
                                                                    <a href="modifier_grade.php?id=<?= $grade['id'] ?>"
                                                                        class="btn btn-sm btn-warning" title="Modifier">
                                                                        <i class="fas fa-edit"></i>
                                                                    </a>
                                                                    <?php if ($grade['is_active']): ?>
                                                                        <?php if ($grade['nb_detenus'] === 0): ?>
                                                                            <button type="button" class="btn btn-sm btn-danger"
                                                                                onclick="toggleGrade(<?= $grade['id'] ?>, 'delete', '<?= htmlspecialchars($grade['libelle']) ?>')"
                                                                                title="Désactiver">
                                                                                <i class="fas fa-ban"></i>
                                                                            </button>
                                                                        <?php else: ?>
                                                                            <button type="button" class="btn btn-sm btn-secondary"
                                                                                disabled
                                                                                title="Grade utilisé - Impossible de désactiver">
                                                                                <i class="fas fa-lock"></i>
                                                                            </button>
                                                                        <?php endif; ?>
                                                                    <?php else: ?>
                                                                        <button type="button" class="btn btn-sm btn-success"
                                                                            onclick="toggleGrade(<?= $grade['id'] ?>, 'activate', '<?= htmlspecialchars($grade['libelle']) ?>')"
                                                                            title="Activer">
                                                                            <i class="fas fa-check"></i>
                                                                        </button>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </td>
                                                        <?php endif; ?>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Hiérarchie visuelle -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="card-title">
                                        <i class="fas fa-sitemap me-2"></i>Hiérarchie Militaire Visuelle
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <?php
                                        $gradesActifs = array_filter($grades, fn($g) => $g['is_active']);
                                        usort($gradesActifs, fn($a, $b) => $b['hierarchie'] - $a['hierarchie']);

                                        foreach ($gradesActifs as $grade):
                                        ?>
                                            <div class="col-sm-6 col-md-4 col-lg-3 mb-3">
                                                <div class="card grade-card">
                                                    <div class="card-body">
                                                        <div class="d-flex align-items-center">
                                                            <span class="hierarchie-badge me-3">
                                                                <?= (int)$grade['hierarchie'] ?>
                                                            </span>
                                                            <div>
                                                                <h6 class="mb-0"><?= htmlspecialchars($grade['code']) ?>
                                                                </h6>
                                                                <small
                                                                    class="text-muted"><?= htmlspecialchars($grade['libelle']) ?></small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
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
        <input type="hidden" name="grade_id" id="toggleGradeId">
    </form>

    <?php include '../../requires/script.php'; ?>
    <script>
        function toggleGrade(id, action, libelle) {
            const actionText = action === 'delete' ? 'désactiver' : 'activer';
            if (confirm(`Voulez-vous vraiment ${actionText} le grade "${libelle}" ?`)) {
                document.getElementById('toggleAction').value = action;
                document.getElementById('toggleGradeId').value = id;
                document.getElementById('toggleForm').submit();
            }
        }

        $(document).ready(function() {
            $('#grades-table').DataTable({
                pageLength: 25,
                order: [
                    [0, 'asc']
                ], // Trier par hiérarchie
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/fr-FR.json'
                }
            });
        });
    </script>
</body>

</html>