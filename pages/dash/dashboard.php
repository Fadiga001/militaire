<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit();
}

require_once '../../includes/db.php';
require_once '../../includes/classes/autoload.php';
require_once '../../includes/logs.php';

// Récupère l'utilisateur connecté
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
$name = $user ? htmlspecialchars($user['nom'] . ' ' . $user['prenom']) : '';
$role = $user['role'] ?? '';

// Initialiser les managers
$detenuMgr = new DetenuManager($pdo);
$condamnationMgr = new CondamnationManager($pdo);

// ==========================
// STATISTIQUES PRINCIPALES
// ==========================
try {
    // Statistiques détenus
    $statsD = $detenuMgr->getStatistiques();
    $totalDetenus = $statsD['total'] ?? 0;
    $multrecidivistes = $statsD['multrecidivistes'] ?? 0;
    $parStatut = $statsD['par_statut'] ?? [];

    $nbCondamnes = $parStatut['CONDAMNE'] ?? 0;
    $nbDetentionProvisoire = $parStatut['DETENTION_PROVISOIRE'] ?? 0;
    $nbLibres = $parStatut['LIBRE'] ?? 0;
    $nbEvades = $parStatut['EVADE'] ?? 0;

    // Statistiques condamnations
    $statsC = $condamnationMgr->getStatistiques();
    $condamnationsActives = $statsC['actives'] ?? 0;
    $liberationsImminentes = $statsC['liberations_imminentes'] ?? 0;

    // Libérations critiques (7 jours)
    $stmt = $pdo->query("
        SELECT COUNT(*) as nb 
        FROM condamnations 
        WHERE statut = 'EN_COURS' 
        AND DATEDIFF(date_liberation_effective, NOW()) BETWEEN 0 AND 7
        AND is_deleted = FALSE
    ");
    $liberationsCritiques = (int)$stmt->fetch()['nb'];

    // Détenus par grade (top 5)
    $parGrade = array_slice($statsD['par_grade'] ?? [], 0, 5);

    // Détenus par unité (top 8)
    $parUnite = array_slice($statsD['par_unite'] ?? [], 0, 8);

    // Condamnations par infraction (top 10)
    $parInfraction = $statsC['par_infraction'] ?? [];

    // Évolution mensuelle (6 derniers mois)
    $stmt = $pdo->query("
        SELECT DATE_FORMAT(created_at, '%Y-%m') as mois, 
               COUNT(*) as nb
        FROM detenus
        WHERE is_deleted = FALSE
        AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY mois
        ORDER BY mois
    ");
    $evolutionMensuelle = $stmt->fetchAll();
    $labelsMois = [];
    $dataMois = [];
    foreach ($evolutionMensuelle as $row) {
        $labelsMois[] = date('M Y', strtotime($row['mois'] . '-01'));
        $dataMois[] = (int)$row['nb'];
    }

    // Alertes libérations imminentes
    $alertesLiberations = $condamnationMgr->getAll([
        'statut' => 'EN_COURS',
        'alerte' => true,
        'limit' => 10
    ]);

    // Détenus récents (derniers 5)
    $detenusRecents = $detenuMgr->getAll(['limit' => 5]);

    // Distribution par catégorie d'infraction
    $stmt = $pdo->query("
        SELECT i.categorie, COUNT(*) as nb
        FROM condamnations c
        JOIN infractions i ON c.infraction_id = i.id
        WHERE c.statut = 'EN_COURS' AND c.is_deleted = FALSE
        GROUP BY i.categorie
    ");
    $parCategorie = $stmt->fetchAll();
} catch (Exception $ex) {
    error_log("Erreur dashboard: " . $ex->getMessage());
    $totalDetenus = 0;
    $nbCondamnes = 0;
    $nbDetentionProvisoire = 0;
    $multrecidivistes = 0;
    $liberationsImminentes = 0;
    $liberationsCritiques = 0;
    $parGrade = [];
    $parUnite = [];
    $parInfraction = [];
    $labelsMois = [];
    $dataMois = [];
    $alertesLiberations = [];
    $detenusRecents = [];
    $parCategorie = [];
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Dashboard - Gestion des Détenus Militaires</title>
    <meta content="width=device-width, initial-scale=1.0, shrink-to-fit=no" name="viewport" />
    <?php include '../../requires/link.php'; ?>
    <style>
    .card-stats {
        transition: transform 0.3s, box-shadow 0.3s;
    }

    .card-stats:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
    }

    .icon-big {
        width: 60px;
        height: 60px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 15px;
    }

    .bubble-shadow-small {
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    }

    .badge-alerte {
        font-size: 11px;
        padding: 4px 8px;
    }

    .alerte-CRITIQUE {
        background: #dc3545;
    }

    .alerte-URGENT {
        background: #fd7e14;
    }

    .alerte-ATTENTION {
        background: #ffc107;
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

    .stat-number {
        font-size: 2.5rem;
        font-weight: 700;
        line-height: 1;
    }

    .progress-thin {
        height: 8px;
        border-radius: 10px;
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
                                <i class="fas fa-tachometer-alt me-2"></i>Tableau de Bord
                            </h3>
                            <h6 class="op-7 mb-2">
                                <i class="fas fa-user-shield me-2"></i>Bienvenue, <?= $name ?>
                            </h6>
                        </div>
                        <div class="ms-md-auto py-2 py-md-0">
                            <button class="btn btn-primary btn-round" onclick="window.location.reload()">
                                <i class="fas fa-sync-alt me-2"></i>Actualiser
                            </button>
                        </div>
                    </div>

                    <!-- Statistiques principales -->
                    <div class="row g-4 mb-4">
                        <div class="col-sm-6 col-md-3">
                            <div class="card card-stats card-round">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-icon">
                                            <div class="icon-big text-center icon-primary bubble-shadow-small">
                                                <i class="fas fa-users"></i>
                                            </div>
                                        </div>
                                        <div class="col col-stats ms-3 ms-sm-0">
                                            <div class="numbers">
                                                <p class="card-category">Total Détenus</p>
                                                <h4 class="card-title"><?= number_format($totalDetenus) ?></h4>
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
                                                <i class="fas fa-gavel"></i>
                                            </div>
                                        </div>
                                        <div class="col col-stats ms-3 ms-sm-0">
                                            <div class="numbers">
                                                <p class="card-category">Condamnés</p>
                                                <h4 class="card-title"><?= number_format($nbCondamnes) ?></h4>
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
                                                <p class="card-category">Détention Provisoire</p>
                                                <h4 class="card-title"><?= number_format($nbDetentionProvisoire) ?></h4>
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
                                                <p class="card-category">Libérations Critiques</p>
                                                <h4 class="card-title"><?= number_format($liberationsCritiques) ?></h4>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Alertes & Graphiques -->
                    <div class="row g-4 mb-4">
                        <!-- Alertes Libérations -->
                        <div class="col-md-6">
                            <div class="card card-round h-100">
                                <div class="card-header">
                                    <div class="card-head-row">
                                        <div class="card-title">
                                            <i class="fas fa-bell text-danger me-2"></i>
                                            Libérations Imminentes (30 jours)
                                        </div>
                                        <div class="card-tools">
                                            <span class="badge badge-danger"><?= count($alertesLiberations) ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($alertesLiberations)): ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-check-circle fa-3x mb-3"></i>
                                        <p>Aucune libération imminente</p>
                                    </div>
                                    <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Détenu</th>
                                                    <th>Date</th>
                                                    <th>Jours</th>
                                                    <th>Alerte</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($alertesLiberations as $alert): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?= htmlspecialchars($alert['detenu']) ?></strong><br>
                                                        <small
                                                            class="text-muted"><?= htmlspecialchars($alert['matricule']) ?></small>
                                                    </td>
                                                    <td><?= date('d/m/Y', strtotime($alert['date_liberation_effective'])) ?>
                                                    </td>
                                                    <td>
                                                        <strong><?= max(0, (int)$alert['jours_restants']) ?></strong>
                                                    </td>
                                                    <td>
                                                        <span
                                                            class="badge badge-alerte alerte-<?= $alert['alerte_niveau'] ?>">
                                                            <?= $alert['alerte_niveau'] ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="card-footer">
                                    <a href="../condamnations/condamnations.php" class="btn btn-primary btn-sm">
                                        <i class="fas fa-list me-2"></i>Voir toutes les condamnations
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Détenus récents -->
                        <div class="col-md-6">
                            <div class="card card-round h-100">
                                <div class="card-header">
                                    <div class="card-head-row">
                                        <div class="card-title">
                                            <i class="fas fa-user-plus text-primary me-2"></i>
                                            Détenus Récents
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Matricule</th>
                                                    <th>Nom</th>
                                                    <th>Grade</th>
                                                    <th>Statut</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($detenusRecents as $det): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($det['matricule']) ?></td>
                                                    <td>
                                                        <strong><?= htmlspecialchars($det['nom_complet']) ?></strong>
                                                    </td>
                                                    <td>
                                                        <span class="badge badge-info">
                                                            <?= htmlspecialchars($det['grade_code']) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php
                                                            $statutColor = [
                                                                'CONDAMNE' => 'danger',
                                                                'DETENTION_PROVISOIRE' => 'warning',
                                                                'LIBRE' => 'success',
                                                                'EVADE' => 'dark'
                                                            ];
                                                            $color = $statutColor[$det['statut_actuel']] ?? 'secondary';
                                                            ?>
                                                        <span class="badge badge-<?= $color ?>">
                                                            <?= $det['statut_actuel'] ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <a href="../detenus/detenus.php" class="btn btn-primary btn-sm">
                                        <i class="fas fa-list me-2"></i>Voir tous les détenus
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Graphiques -->
                    <div class="row g-4 mb-4">
                        <!-- Évolution mensuelle -->
                        <div class="col-lg-8">
                            <div class="card card-round">
                                <div class="card-header">
                                    <div class="card-title">
                                        <i class="fas fa-chart-line me-2"></i>
                                        Évolution des Détenus (6 derniers mois)
                                    </div>
                                </div>
                                <div class="card-body">
                                    <canvas id="evolutionChart" height="100"></canvas>
                                </div>
                            </div>
                        </div>

                        <!-- Répartition par catégorie -->
                        <div class="col-lg-4">
                            <div class="card card-round">
                                <div class="card-header">
                                    <div class="card-title">
                                        <i class="fas fa-chart-pie me-2"></i>
                                        Par Catégorie d'Infraction
                                    </div>
                                </div>
                                <div class="card-body">
                                    <canvas id="categorieChart" height="180"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Statistiques détaillées -->
                    <div class="row g-4">
                        <!-- Par Grade -->
                        <div class="col-md-6">
                            <div class="card card-round">
                                <div class="card-header">
                                    <div class="card-title">
                                        <i class="fas fa-star me-2"></i>
                                        Répartition par Grade (Top 5)
                                    </div>
                                </div>
                                <div class="card-body">
                                    <?php foreach ($parGrade as $grade): ?>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span><?= htmlspecialchars($grade['libelle']) ?></span>
                                            <strong><?= (int)$grade['nb'] ?></strong>
                                        </div>
                                        <div class="progress progress-thin">
                                            <?php
                                                $percent = $totalDetenus > 0 ? ($grade['nb'] / $totalDetenus * 100) : 0;
                                                ?>
                                            <div class="progress-bar bg-primary" style="width: <?= $percent ?>%"></div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Par Infraction -->
                        <div class="col-md-6">
                            <div class="card card-round">
                                <div class="card-header">
                                    <div class="card-title">
                                        <i class="fas fa-balance-scale me-2"></i>
                                        Infractions Courantes (Top 5)
                                    </div>
                                </div>
                                <div class="card-body">
                                    <?php
                                    $topInfractions = array_slice($parInfraction, 0, 5);
                                    $maxInfraction = !empty($topInfractions) ? max(array_column($topInfractions, 'nb')) : 1;
                                    foreach ($topInfractions as $inf):
                                    ?>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span><?= htmlspecialchars($inf['libelle']) ?></span>
                                            <strong><?= (int)$inf['nb'] ?></strong>
                                        </div>
                                        <div class="progress progress-thin">
                                            <?php
                                                $percent = ($inf['nb'] / $maxInfraction * 100);
                                                ?>
                                            <div class="progress-bar bg-danger" style="width: <?= $percent ?>%"></div>
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

    <!-- Scripts -->
    <script src="../../assets/js/core/jquery-3.7.1.min.js"></script>
    <script src="../../assets/js/core/popper.min.js"></script>
    <script src="../../assets/js/core/bootstrap.min.js"></script>
    <script src="../../assets/js/plugin/chart.js/chart.min.js"></script>
    <script src="../../assets/js/kaiadmin.min.js"></script>

    <script>
    // Graphique évolution mensuelle
    (function() {
        var ctx = document.getElementById('evolutionChart');
        if (ctx) {
            new Chart(ctx.getContext('2d'), {
                type: 'line',
                data: {
                    labels: <?= json_encode($labelsMois) ?>,
                    datasets: [{
                        label: 'Nouveaux détenus',
                        data: <?= json_encode($dataMois) ?>,
                        borderColor: '#177dff',
                        backgroundColor: 'rgba(23, 125, 255, 0.2)',
                        borderWidth: 3,
                        tension: 0.4,
                        fill: true,
                        pointRadius: 5,
                        pointHoverRadius: 7
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }
    })();

    // Graphique par catégorie
    (function() {
        var ctx = document.getElementById('categorieChart');
        if (ctx) {
            var labels = <?= json_encode(array_column($parCategorie, 'categorie')) ?>;
            var data = <?= json_encode(array_column($parCategorie, 'nb')) ?>;

            new Chart(ctx.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: ['#dc3545', '#fd7e14', '#ffc107'],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 15,
                                font: {
                                    size: 12
                                }
                            }
                        }
                    }
                }
            });
        }
    })();
    </script>
</body>

</html>