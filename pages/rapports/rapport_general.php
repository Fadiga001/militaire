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

// Récupérer les statistiques générales
$detenuMgr = new DetenuManager($pdo);
$condamnationMgr = new CondamnationManager($pdo);
$refMgr = new ReferenceManager($pdo);

// Stats détenus
$statsDetenu = $detenuMgr->getStatistiques();

// Stats condamnations
$statsCondamnation = $condamnationMgr->getStatistiques();

// Libérations imminentes (30 jours)
$liberationsImminentes = $condamnationMgr->getAll(['alerte' => true, 'limit' => 10]);

// Répartition par lieu de détention
$stmtLieux = $pdo->query("
    SELECT l.nom, COUNT(DISTINCT p.detenu_id) as nb_detenus, l.capacite,
           ROUND((COUNT(DISTINCT p.detenu_id) / l.capacite) * 100, 1) as taux_occupation
    FROM lieux_detention l
    LEFT JOIN periodes_detention p ON l.id = p.lieu_detention_id AND p.date_fin IS NULL
    WHERE l.is_active = TRUE
    GROUP BY l.id, l.nom, l.capacite
    ORDER BY nb_detenus DESC
");
$lieuxStats = $stmtLieux->fetchAll();

// Évolution mensuelle (6 derniers mois)
$stmtEvolution = $pdo->query("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as mois,
        COUNT(*) as nb_detenus
    FROM detenus
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    AND is_deleted = FALSE
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY mois ASC
");
$evolutionData = $stmtEvolution->fetchAll();

// Top 5 infractions
$stmtInfractions = $pdo->query("
    SELECT i.libelle, i.categorie, COUNT(*) as nb
    FROM condamnations c
    JOIN infractions i ON c.infraction_id = i.id
    WHERE c.statut = 'EN_COURS' AND c.is_deleted = FALSE
    GROUP BY i.id, i.libelle, i.categorie
    ORDER BY nb DESC
    LIMIT 5
");
$topInfractions = $stmtInfractions->fetchAll();

// Durée moyenne de détention
$stmtDuree = $pdo->query("
    SELECT 
        AVG(DATEDIFF(COALESCE(date_liberation_effective, NOW()), date_debut_execution)) as duree_moyenne
    FROM condamnations
    WHERE date_debut_execution IS NOT NULL
    AND is_deleted = FALSE
");
$dureeMoyenne = round((float)$stmtDuree->fetch()['duree_moyenne'] ?? 0);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Rapport Général - Système Militaire</title>
    <meta content="width=device-width, initial-scale=1.0, shrink-to-fit=no" name="viewport" />
    <?php include '../../requires/link.php'; ?>
    <style>
    .stat-card {
        border-left: 4px solid #177dff;
        transition: all 0.3s;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    }

    .progress-bar-animated {
        animation: progress-animation 1.5s ease-in-out;
    }

    @keyframes progress-animation {
        from {
            width: 0;
        }
    }

    .chart-container {
        position: relative;
        height: 300px;
    }

    .alert-level-CRITIQUE {
        background: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%);
        color: white;
    }

    .alert-level-URGENT {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        color: white;
    }

    .alert-level-ATTENTION {
        background: linear-gradient(135deg, #ffc837 0%, #ff8008 100%);
        color: white;
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
                                <i class="fas fa-file-alt me-2"></i>Rapport Général
                            </h3>
                            <h6 class="op-7 mb-2">
                                Vue d'ensemble du système au <?= date('d/m/Y à H:i') ?>
                            </h6>
                        </div>
                        <div class="ms-md-auto py-2 py-md-0 no-print">
                            <button onclick="window.print()" class="btn btn-primary btn-round">
                                <i class="fas fa-print me-2"></i>Imprimer
                            </button>
                            <button onclick="exportToPDF()" class="btn btn-success btn-round">
                                <i class="fas fa-file-pdf me-2"></i>Exporter PDF
                            </button>
                        </div>
                    </div>

                    <!-- Statistiques Principales -->
                    <div class="row mb-4">
                        <div class="col-sm-6 col-md-3">
                            <div class="card card-stats card-round stat-card">
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
                                                <h4 class="card-title"><?= $statsDetenu['total'] ?></h4>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-sm-6 col-md-3">
                            <div class="card card-stats card-round stat-card">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-icon">
                                            <div class="icon-big text-center icon-warning bubble-shadow-small">
                                                <i class="fas fa-gavel"></i>
                                            </div>
                                        </div>
                                        <div class="col col-stats ms-3 ms-sm-0">
                                            <div class="numbers">
                                                <p class="card-category">Condamnations Actives</p>
                                                <h4 class="card-title"><?= $statsCondamnation['actives'] ?></h4>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-sm-6 col-md-3">
                            <div class="card card-stats card-round stat-card">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-icon">
                                            <div class="icon-big text-center icon-danger bubble-shadow-small">
                                                <i class="fas fa-exclamation-triangle"></i>
                                            </div>
                                        </div>
                                        <div class="col col-stats ms-3 ms-sm-0">
                                            <div class="numbers">
                                                <p class="card-category">Libérations Imminentes</p>
                                                <h4 class="card-title">
                                                    <?= $statsCondamnation['liberations_imminentes'] ?>
                                                </h4>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-sm-6 col-md-3">
                            <div class="card card-stats card-round stat-card">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-icon">
                                            <div class="icon-big text-center icon-info bubble-shadow-small">
                                                <i class="fas fa-user-times"></i>
                                            </div>
                                        </div>
                                        <div class="col col-stats ms-3 ms-sm-0">
                                            <div class="numbers">
                                                <p class="card-category">Multirécidivistes</p>
                                                <h4 class="card-title"><?= $statsDetenu['multrecidivistes'] ?></h4>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Graphiques et Tableaux -->
                    <div class="row">
                        <!-- Répartition par Statut -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="card-title">
                                        <i class="fas fa-chart-pie me-2"></i>Répartition par Statut
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="statutChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Top 5 Infractions -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="card-title">
                                        <i class="fas fa-balance-scale me-2"></i>Top 5 Infractions
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="infractionsChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Évolution et Occupation -->
                    <div class="row mt-4">
                        <!-- Évolution Mensuelle -->
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="card-title">
                                        <i class="fas fa-chart-line me-2"></i>Évolution sur 6 mois
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="evolutionChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Indicateurs Clés -->
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="card-title">
                                        <i class="fas fa-info-circle me-2"></i>Indicateurs Clés
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <div class="mb-4">
                                        <h6 class="fw-bold">Durée Moyenne de Détention</h6>
                                        <h3 class="text-primary"><?= $dureeMoyenne ?> jours</h3>
                                        <small class="text-muted">
                                            <i class="fas fa-calendar-alt me-1"></i>
                                            Environ <?= round($dureeMoyenne / 30) ?> mois
                                        </small>
                                    </div>

                                    <div class="mb-4">
                                        <h6 class="fw-bold">Taux de Multirécidive</h6>
                                        <?php
                                        $tauxRecidive = $statsDetenu['total'] > 0
                                            ? round(($statsDetenu['multrecidivistes'] / $statsDetenu['total']) * 100, 1)
                                            : 0;
                                        ?>
                                        <h3 class="text-warning"><?= $tauxRecidive ?>%</h3>
                                        <small class="text-muted">
                                            <?= $statsDetenu['multrecidivistes'] ?> sur <?= $statsDetenu['total'] ?>
                                            détenus
                                        </small>
                                    </div>

                                    <div>
                                        <h6 class="fw-bold">Lieux de Détention Actifs</h6>
                                        <h3 class="text-info"><?= count($lieuxStats) ?></h3>
                                        <small class="text-muted">
                                            <i class="fas fa-map-marker-alt me-1"></i>
                                            Répartis sur le territoire
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Occupation des Lieux -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="card-title">
                                        <i class="fas fa-building me-2"></i>Taux d'Occupation des Lieux de Détention
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <?php foreach ($lieuxStats as $lieu): ?>
                                    <div class="mb-4">
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="fw-bold"><?= htmlspecialchars($lieu['nom']) ?></span>
                                            <span>
                                                <strong><?= $lieu['nb_detenus'] ?></strong> /
                                                <?= $lieu['capacite'] ?>
                                                <span
                                                    class="badge badge-<?= $lieu['taux_occupation'] > 90 ? 'danger' : ($lieu['taux_occupation'] > 75 ? 'warning' : 'success') ?>">
                                                    <?= $lieu['taux_occupation'] ?>%
                                                </span>
                                            </span>
                                        </div>
                                        <div class="progress" style="height: 25px;">
                                            <div class="progress-bar progress-bar-striped progress-bar-animated bg-<?= $lieu['taux_occupation'] > 90 ? 'danger' : ($lieu['taux_occupation'] > 75 ? 'warning' : 'success') ?>"
                                                role="progressbar"
                                                style="width: <?= min($lieu['taux_occupation'], 100) ?>%"
                                                aria-valuenow="<?= $lieu['taux_occupation'] ?>" aria-valuemin="0"
                                                aria-valuemax="100">
                                                <?= $lieu['taux_occupation'] ?>%
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Libérations Imminentes -->
                    <?php if (!empty($liberationsImminentes)): ?>
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="card-title">
                                        <i class="fas fa-calendar-check me-2"></i>Libérations Prévues (30 prochains
                                        jours)
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Détenu</th>
                                                    <th>Infraction</th>
                                                    <th>Date Libération</th>
                                                    <th>Jours Restants</th>
                                                    <th>Priorité</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($liberationsImminentes as $liberation): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?= htmlspecialchars($liberation['detenu']) ?></strong><br>
                                                        <small
                                                            class="text-muted"><?= htmlspecialchars($liberation['matricule']) ?></small>
                                                    </td>
                                                    <td><?= htmlspecialchars($liberation['infraction']) ?></td>
                                                    <td>
                                                        <?= date('d/m/Y', strtotime($liberation['date_liberation_effective'])) ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge badge-info">
                                                            <?= max(0, $liberation['jours_restants']) ?> jours
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span
                                                            class="badge alert-level-<?= $liberation['alerte_niveau'] ?>">
                                                            <?= $liberation['alerte_niveau'] ?>
                                                        </span>
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
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php include '../../requires/script.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    // Graphique Répartition par Statut
    const statutCtx = document.getElementById('statutChart').getContext('2d');
    new Chart(statutCtx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_keys($statsDetenu['par_statut'] ?? [])) ?>,
            datasets: [{
                data: <?= json_encode(array_values($statsDetenu['par_statut'] ?? [])) ?>,
                backgroundColor: [
                    '#177dff',
                    '#f3545d',
                    '#fdaf4b',
                    '#1572e8',
                    '#6861ce'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });

    // Graphique Top Infractions
    const infractionsCtx = document.getElementById('infractionsChart').getContext('2d');
    new Chart(infractionsCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($topInfractions, 'libelle')) ?>,
            datasets: [{
                label: 'Nombre de cas',
                data: <?= json_encode(array_column($topInfractions, 'nb')) ?>,
                backgroundColor: '#177dff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
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

    // Graphique Évolution
    const evolutionCtx = document.getElementById('evolutionChart').getContext('2d');
    new Chart(evolutionCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode(array_column($evolutionData, 'mois')) ?>,
            datasets: [{
                label: 'Nouveaux détenus',
                data: <?= json_encode(array_column($evolutionData, 'nb_detenus')) ?>,
                borderColor: '#177dff',
                backgroundColor: 'rgba(23, 125, 255, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'bottom'
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    function exportToPDF() {
        alert('Fonctionnalité d\'export PDF à implémenter avec une bibliothèque comme jsPDF ou html2pdf');
    }
    </script>
</body>

</html>