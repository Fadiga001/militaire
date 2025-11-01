<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit();
}

require_once '../../includes/db.php';
require_once '../../includes/classes/autoload.php';

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
$name = $user ? htmlspecialchars($user['nom'] . ' ' . $user['prenom']) : '';
$role = $user['role'] ?? '';

$detenuMgr = new DetenuManager($pdo);
$condamnationMgr = new CondamnationManager($pdo);

// Période d'analyse
$periode = $_GET['periode'] ?? '12'; // Mois

// Statistiques détaillées des détenus
$statsDetenu = $detenuMgr->getStatistiques();

// Répartition par âge
$stmtAge = $pdo->query("
    SELECT 
        CASE 
            WHEN TIMESTAMPDIFF(YEAR, date_naissance, NOW()) < 25 THEN '< 25 ans'
            WHEN TIMESTAMPDIFF(YEAR, date_naissance, NOW()) BETWEEN 25 AND 34 THEN '25-34 ans'
            WHEN TIMESTAMPDIFF(YEAR, date_naissance, NOW()) BETWEEN 35 AND 44 THEN '35-44 ans'
            WHEN TIMESTAMPDIFF(YEAR, date_naissance, NOW()) BETWEEN 45 AND 54 THEN '45-54 ans'
            ELSE '55+ ans'
        END as tranche_age,
        COUNT(*) as nb
    FROM detenus
    WHERE is_deleted = FALSE AND date_naissance IS NOT NULL
    GROUP BY tranche_age
    ORDER BY tranche_age
");
$repartitionAge = $stmtAge->fetchAll();

// Répartition par sexe
$stmtSexe = $pdo->query("
    SELECT sexe, COUNT(*) as nb
    FROM detenus
    WHERE is_deleted = FALSE
    GROUP BY sexe
");
$repartitionSexe = $stmtSexe->fetchAll(PDO::FETCH_KEY_PAIR);

// Évolution des entrées (12 derniers mois)
$stmtEvolution = $pdo->prepare("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as mois,
        COUNT(*) as entrees,
        SUM(CASE WHEN statut_actuel = 'LIBERE' THEN 1 ELSE 0 END) as sorties
    FROM detenus
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL :periode MONTH)
    AND is_deleted = FALSE
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY mois ASC
");
$stmtEvolution->execute([':periode' => (int)$periode]);
$evolutionData = $stmtEvolution->fetchAll();

// Top 10 infractions
$stmtInfractions = $pdo->query("
    SELECT i.libelle, i.categorie, i.gravite, COUNT(*) as nb,
           ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM condamnations WHERE is_deleted = FALSE)), 1) as pourcentage
    FROM condamnations c
    JOIN infractions i ON c.infraction_id = i.id
    WHERE c.is_deleted = FALSE
    GROUP BY i.id, i.libelle, i.categorie, i.gravite
    ORDER BY nb DESC
    LIMIT 10
");
$topInfractions = $stmtInfractions->fetchAll();

// Durées de peine
$stmtDurees = $pdo->query("
    SELECT 
        CASE 
            WHEN peine_unite = 'JOURS' AND peine_valeur <= 90 THEN '< 3 mois'
            WHEN (peine_unite = 'JOURS' AND peine_valeur <= 180) OR (peine_unite = 'MOIS' AND peine_valeur <= 6) THEN '3-6 mois'
            WHEN (peine_unite = 'JOURS' AND peine_valeur <= 365) OR (peine_unite = 'MOIS' AND peine_valeur <= 12) THEN '6-12 mois'
            WHEN (peine_unite = 'MOIS' AND peine_valeur <= 24) OR (peine_unite = 'ANS' AND peine_valeur <= 2) THEN '1-2 ans'
            WHEN (peine_unite = 'MOIS' AND peine_valeur <= 60) OR (peine_unite = 'ANS' AND peine_valeur <= 5) THEN '2-5 ans'
            ELSE '5+ ans'
        END as duree,
        COUNT(*) as nb
    FROM condamnations
    WHERE is_deleted = FALSE
    GROUP BY duree
    ORDER BY 
        CASE duree
            WHEN '< 3 mois' THEN 1
            WHEN '3-6 mois' THEN 2
            WHEN '6-12 mois' THEN 3
            WHEN '1-2 ans' THEN 4
            WHEN '2-5 ans' THEN 5
            ELSE 6
        END
");
$repartitionDurees = $stmtDurees->fetchAll();

// Taux de récidive par infraction
$stmtRecidive = $pdo->query("
    SELECT i.libelle, 
           COUNT(DISTINCT d.id) as total_detenus,
           SUM(CASE WHEN d.is_multrecidiviste = TRUE THEN 1 ELSE 0 END) as recidivistes,
           ROUND((SUM(CASE WHEN d.is_multrecidiviste = TRUE THEN 1 ELSE 0 END) * 100.0 / COUNT(DISTINCT d.id)), 1) as taux_recidive
    FROM condamnations c
    JOIN detenus d ON c.detenu_id = d.id
    JOIN infractions i ON c.infraction_id = i.id
    WHERE c.is_deleted = FALSE AND d.is_deleted = FALSE
    GROUP BY i.id, i.libelle
    HAVING COUNT(DISTINCT d.id) >= 3
    ORDER BY taux_recidive DESC
    LIMIT 10
");
$recidiveData = $stmtRecidive->fetchAll();

// Performance par lieu de détention
$stmtLieux = $pdo->query("
    SELECT l.nom, l.capacite,
           COUNT(DISTINCT p.detenu_id) as nb_detenus,
           ROUND((COUNT(DISTINCT p.detenu_id) / l.capacite) * 100, 1) as taux_occupation,
           AVG(DATEDIFF(COALESCE(p.date_fin, NOW()), p.date_debut)) as duree_moyenne_sejour
    FROM lieux_detention l
    LEFT JOIN periodes_detention p ON l.id = p.lieu_detention_id
    WHERE l.is_active = TRUE
    GROUP BY l.id, l.nom, l.capacite
    ORDER BY nb_detenus DESC
");
$performanceLieux = $stmtLieux->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Statistiques Détaillées - Système Militaire</title>
    <meta content="width=device-width, initial-scale=1.0, shrink-to-fit=no" name="viewport" />
    <?php include '../../requires/link.php'; ?>
    <style>
    .stat-box {
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 20px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }

    .chart-container {
        position: relative;
        height: 350px;
        margin-bottom: 20px;
    }

    .progress-thin {
        height: 8px;
    }

    @media print {
        .no-print {
            display: none !important;
        }

        .card {
            page-break-inside: avoid;
        }
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
                    <!-- En-tête -->
                    <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4">
                        <div>
                            <h3 class="fw-bold mb-3">
                                <i class="fas fa-chart-pie me-2"></i>Statistiques Détaillées
                            </h3>
                            <h6 class="op-7 mb-2">
                                Analyse approfondie sur <?= $periode ?> mois
                            </h6>
                        </div>
                        <div class="ms-md-auto py-2 py-md-0 no-print">
                            <div class="btn-group">
                                <a href="?periode=6"
                                    class="btn btn-<?= $periode == '6' ? 'primary' : 'outline-primary' ?>">6
                                    mois</a>
                                <a href="?periode=12"
                                    class="btn btn-<?= $periode == '12' ? 'primary' : 'outline-primary' ?>">12
                                    mois</a>
                                <a href="?periode=24"
                                    class="btn btn-<?= $periode == '24' ? 'primary' : 'outline-primary' ?>">24
                                    mois</a>
                            </div>
                            <button onclick="window.print()" class="btn btn-secondary btn-round ms-2">
                                <i class="fas fa-print me-2"></i>Imprimer
                            </button>
                        </div>
                    </div>

                    <!-- Vue d'ensemble -->
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <div class="stat-box">
                                <div class="row text-center">
                                    <div class="col-md-3">
                                        <h2 class="mb-0"><?= $statsDetenu['total'] ?></h2>
                                        <p class="mb-0">Détenus Total</p>
                                    </div>
                                    <div class="col-md-3">
                                        <h2 class="mb-0"><?= $statsDetenu['multrecidivistes'] ?></h2>
                                        <p class="mb-0">Multirécidivistes</p>
                                    </div>
                                    <div class="col-md-3">
                                        <h2 class="mb-0"><?= count($topInfractions) ?></h2>
                                        <p class="mb-0">Types d'Infractions</p>
                                    </div>
                                    <div class="col-md-3">
                                        <h2 class="mb-0"><?= count($performanceLieux) ?></h2>
                                        <p class="mb-0">Lieux de Détention</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Graphiques Démographiques -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="card-title">
                                        <i class="fas fa-venus-mars me-2"></i>Répartition par Sexe
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="sexeChart"></canvas>
                                    </div>
                                    <div class="mt-3">
                                        <?php
                                        $totalSexe = array_sum($repartitionSexe);
                                        foreach ($repartitionSexe as $sexe => $nb):
                                            $pct = $totalSexe > 0 ? round(($nb / $totalSexe) * 100, 1) : 0;
                                        ?>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span><?= $sexe === 'M' ? 'Masculin' : 'Féminin' ?></span>
                                            <strong><?= $nb ?> (<?= $pct ?>%)</strong>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="card-title">
                                        <i class="fas fa-birthday-cake me-2"></i>Répartition par Âge
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="ageChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Évolution et Infractions -->
                    <div class="row mt-4">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="card-title">
                                        <i class="fas fa-chart-line me-2"></i>Évolution des Entrées/Sorties
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="evolutionChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Top Infractions -->
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="card-title">
                                        <i class="fas fa-balance-scale me-2"></i>Top 10 Infractions
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Infraction</th>
                                                    <th>Catégorie</th>
                                                    <th>Nb Cas</th>
                                                    <th>%</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($topInfractions as $index => $infraction): ?>
                                                <tr>
                                                    <td><?= $index + 1 ?></td>
                                                    <td><?= htmlspecialchars($infraction['libelle']) ?></td>
                                                    <td>
                                                        <span
                                                            class="badge badge-<?= $infraction['categorie'] === 'CRIME' ? 'danger' : ($infraction['categorie'] === 'DELIT' ? 'warning' : 'info') ?>">
                                                            <?= htmlspecialchars($infraction['categorie']) ?>
                                                        </span>
                                                    </td>
                                                    <td><strong><?= $infraction['nb'] ?></strong></td>
                                                    <td><?= $infraction['pourcentage'] ?>%</td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="card-title">
                                        <i class="fas fa-hourglass-half me-2"></i>Répartition des Durées de Peine
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="dureesChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Taux de Récidive -->
                    <div class="row mt-4">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="card-title">
                                        <i class="fas fa-redo me-2"></i>Taux de Récidive par Infraction
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <?php foreach ($recidiveData as $recidive): ?>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between mb-2">
                                            <span><?= htmlspecialchars($recidive['libelle']) ?></span>
                                            <span>
                                                <strong><?= $recidive['recidivistes'] ?></strong> /
                                                <?= $recidive['total_detenus'] ?>
                                                <span
                                                    class="badge badge-<?= $recidive['taux_recidive'] > 50 ? 'danger' : ($recidive['taux_recidive'] > 30 ? 'warning' : 'success') ?>">
                                                    <?= $recidive['taux_recidive'] ?>%
                                                </span>
                                            </span>
                                        </div>
                                        <div class="progress progress-thin">
                                            <div class="progress-bar bg-<?= $recidive['taux_recidive'] > 50 ? 'danger' : ($recidive['taux_recidive'] > 30 ? 'warning' : 'success') ?>"
                                                style="width: <?= $recidive['taux_recidive'] ?>%"></div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Performance des Lieux -->
                    <div class="row mt-4">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="card-title">
                                        <i class="fas fa-building me-2"></i>Performance des Lieux de Détention
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Lieu</th>
                                                    <th>Capacité</th>
                                                    <th>Détenus</th>
                                                    <th>Taux Occupation</th>
                                                    <th>Durée Moy. Séjour</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($performanceLieux as $lieu): ?>
                                                <tr>
                                                    <td><strong><?= htmlspecialchars($lieu['nom']) ?></strong></td>
                                                    <td><?= $lieu['capacite'] ?></td>
                                                    <td><?= $lieu['nb_detenus'] ?></td>
                                                    <td>
                                                        <div class="progress" style="height: 20px; width: 150px;">
                                                            <div class="progress-bar bg-<?= $lieu['taux_occupation'] > 90 ? 'danger' : ($lieu['taux_occupation'] > 75 ? 'warning' : 'success') ?>"
                                                                style="width: <?= min($lieu['taux_occupation'], 100) ?>%">
                                                                <?= $lieu['taux_occupation'] ?>%
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <?= round($lieu['duree_moyenne_sejour'] ?? 0) ?> jours
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
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

    <?php include '../../requires/script.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    // Graphique Sexe
    const sexeCtx = document.getElementById('sexeChart').getContext('2d');
    new Chart(sexeCtx, {
        type: 'pie',
        data: {
            labels: ['Masculin', 'Féminin'],
            datasets: [{
                data: [<?= $repartitionSexe['M'] ?? 0 ?>, <?= $repartitionSexe['F'] ?? 0 ?>],
                backgroundColor: ['#177dff', '#f3545d']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });

    // Graphique Âge
    const ageCtx = document.getElementById('ageChart').getContext('2d');
    new Chart(ageCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($repartitionAge, 'tranche_age')) ?>,
            datasets: [{
                label: 'Nombre de détenus',
                data: <?= json_encode(array_column($repartitionAge, 'nb')) ?>,
                backgroundColor: '#177dff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    // Graphique Évolution
    const evolutionCtx = document.getElementById('evolutionChart').getContext('2d');
    new Chart(evolutionCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode(array_column($evolutionData, 'mois')) ?>,
            datasets: [{
                    label: 'Entrées',
                    data: <?= json_encode(array_column($evolutionData, 'entrees')) ?>,
                    borderColor: '#177dff',
                    backgroundColor: 'rgba(23, 125, 255, 0.1)',
                    tension: 0.4,
                    fill: true
                },
                {
                    label: 'Sorties',
                    data: <?= json_encode(array_column($evolutionData, 'sorties')) ?>,
                    borderColor: '#1cc88a',
                    backgroundColor: 'rgba(28, 200, 138, 0.1)',
                    tension: 0.4,
                    fill: true
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                intersect: false,
                mode: 'index'
            }
        }
    });

    // Graphique Durées
    const dureesCtx = document.getElementById('dureesChart').getContext('2d');
    new Chart(dureesCtx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_column($repartitionDurees, 'duree')) ?>,
            datasets: [{
                data: <?= json_encode(array_column($repartitionDurees, 'nb')) ?>,
                backgroundColor: [
                    '#1cc88a',
                    '#36b9cc',
                    '#177dff',
                    '#fdaf4b',
                    '#f3545d',
                    '#6861ce'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right'
                }
            }
        }
    });
    </script>
</body>

</html>