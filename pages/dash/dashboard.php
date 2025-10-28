<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit();
}
require_once '../../includes/db.php';

// Récupère l'utilisateur connecté
$stmt = $pdo->prepare('SELECT * FROM utilisateurs WHERE id_utilisateur = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
$name = $user ? htmlspecialchars($user['nom']) : '';
$role = isset($user['role']) ? $user['role'] : '';

// ==========================
// Données Dashboard Paiements
// ==========================
try {
    // Année académique en cours
    $anneeStmt = $pdo->prepare("SELECT id_annee, libelle FROM annees_academiques WHERE statut = 'en cours' ORDER BY date_debut DESC LIMIT 1");
    $anneeStmt->execute();
    $annee = $anneeStmt->fetch();
    $currentAnneeId = $annee['id_annee'] ?? null;
    $currentAnneeLibelle = $annee['libelle'] ?? '';

    // Montant total encaissé (validé = avec reçu)
    $totalStmt = $pdo->prepare("SELECT COALESCE(SUM(p.montant),0) as total
        FROM paiements p
        INNER JOIN recus r ON r.id_paiement = p.id_paiement
        WHERE (:anneeId IS NULL OR p.id_annee = :anneeId)");
    $totalStmt->execute([':anneeId' => $currentAnneeId]);
    $totalEncaisse = (float)($totalStmt->fetch()['total'] ?? 0);

    // Paiements validés aujourd'hui
    $todayStmt = $pdo->prepare("SELECT COUNT(*) as nb
        FROM recus r
        INNER JOIN paiements p ON p.id_paiement = r.id_paiement
        WHERE DATE(r.date_generation) = CURDATE()
        AND (:anneeId IS NULL OR p.id_annee = :anneeId)");
    $todayStmt->execute([':anneeId' => $currentAnneeId]);
    $nbValidesJour = (int)($todayStmt->fetch()['nb'] ?? 0);

    // Paiements validés sur le mois en cours
    $moisStmt = $pdo->prepare("SELECT COUNT(*) as nb
        FROM recus r
        INNER JOIN paiements p ON p.id_paiement = r.id_paiement
        WHERE YEAR(r.date_generation) = YEAR(CURDATE())
        AND MONTH(r.date_generation) = MONTH(CURDATE())
        AND (:anneeId IS NULL OR p.id_annee = :anneeId)");
    $moisStmt->execute([':anneeId' => $currentAnneeId]);
    $nbValidesMois = (int)($moisStmt->fetch()['nb'] ?? 0);

    // Courbe des paiements mensuels (validés) sur l'année civile courante
    $monthlyStmt = $pdo->prepare("SELECT DATE_FORMAT(r.date_generation,'%Y-%m') as ym, SUM(p.montant) as total
        FROM recus r
        INNER JOIN paiements p ON p.id_paiement = r.id_paiement
        WHERE YEAR(r.date_generation) = YEAR(CURDATE())
        AND (:anneeId IS NULL OR p.id_annee = :anneeId)
        GROUP BY ym
        ORDER BY ym");
    $monthlyStmt->execute([':anneeId' => $currentAnneeId]);
    $monthlyData = $monthlyStmt->fetchAll();
    $labelsMonthly = [];
    $dataMonthly = [];
    foreach ($monthlyData as $row) {
        $labelsMonthly[] = $row['ym'];
        $dataMonthly[] = (float)$row['total'];
    }

    // Histogramme par filière (sommes validées par filière)
    $filiereStmt = $pdo->prepare("SELECT COALESCE(f.nom_filiere,'Inconnue') as filiere, SUM(p.montant) as total
        FROM recus r
        INNER JOIN paiements p ON p.id_paiement = r.id_paiement
        INNER JOIN etudiants e ON e.id_etudiant = p.id_etudiant
        LEFT JOIN filieres f ON f.id_filiere = e.id_filiere
        WHERE (:anneeId IS NULL OR p.id_annee = :anneeId)
        GROUP BY filiere
        ORDER BY total DESC");
    $filiereStmt->execute([':anneeId' => $currentAnneeId]);
    $filiereData = $filiereStmt->fetchAll();
    $labelsFiliere = [];
    $dataFiliere = [];
    foreach ($filiereData as $row) {
        $labelsFiliere[] = $row['filiere'];
        $dataFiliere[] = (float)$row['total'];
    }

    // Alertes: Étudiants ayant payé mais pas validés (pas de reçu)
    $nonValidesStmt = $pdo->prepare("SELECT p.id_paiement, p.ref_transaction, p.montant, p.date_paiement, e.matricule, e.nom, e.prenom
        FROM paiements p
        INNER JOIN etudiants e ON e.id_etudiant = p.id_etudiant
        LEFT JOIN recus r ON r.id_paiement = p.id_paiement
        WHERE r.id_recu IS NULL AND (:anneeId IS NULL OR p.id_annee = :anneeId)
        ORDER BY p.date_paiement DESC
        LIMIT 10");
    $nonValidesStmt->execute([':anneeId' => $currentAnneeId]);
    $alertsNonValides = $nonValidesStmt->fetchAll();

    // Alertes: Paiements suspects (doublons de référence)
    $doublonRefStmt = $pdo->prepare("SELECT ref_transaction, COUNT(*) as nb, SUM(montant) as total
        FROM paiements
        WHERE (:anneeId IS NULL OR id_annee = :anneeId)
        GROUP BY ref_transaction
        HAVING COUNT(*) > 1
        ORDER BY nb DESC
        LIMIT 10");
    $doublonRefStmt->execute([':anneeId' => $currentAnneeId]);
    $alertsDoublonsRef = $doublonRefStmt->fetchAll();

    // Alertes: même étudiant, même montant, même jour (hors reçu)
    $doublonEtudStmt = $pdo->prepare("SELECT id_etudiant, DATE(date_paiement) as jour, montant, COUNT(*) as nb
        FROM paiements
        WHERE (:anneeId IS NULL OR id_annee = :anneeId)
        GROUP BY id_etudiant, jour, montant
        HAVING COUNT(*) > 1
        ORDER BY nb DESC
        LIMIT 10");
    $doublonEtudStmt->execute([':anneeId' => $currentAnneeId]);
    $alertsDoublonsEtud = $doublonEtudStmt->fetchAll();
} catch (Exception $ex) {
    // En cas d'erreur SQL, on sécurise des valeurs par défaut
    $totalEncaisse = 0;
    $nbValidesJour = 0;
    $nbValidesMois = 0;
    $labelsMonthly = [];
    $dataMonthly = [];
    $labelsFiliere = [];
    $dataFiliere = [];
    $alertsNonValides = [];
    $alertsDoublonsRef = [];
    $alertsDoublonsEtud = [];
}

?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Dashboard - eReçu Agitel</title>
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
                            <h3 class="fw-bold mb-3">Dashboard</h3>
                            <h6 class="op-7 mb-2">Bienvenue, <?= $name; ?></h6>
                        </div>
                    </div>
                    <div class="row g-4">
                        <?php if ($role === 'admin' || $role === 'super_admin'): ?>
                            <div class="col-sm-6 col-md-3">
                                <div class="card card-stats card-round">
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col-icon">
                                                <div class="icon-big text-center icon-success bubble-shadow-small"
                                                    title="Total encaissé">
                                                    <i class="fas fa-coins"></i>
                                                </div>
                                            </div>
                                            <div class="col col-stats ms-3 ms-sm-0">
                                                <div class="numbers">
                                                    <p class="card-category">Total encaissé
                                                        <?= $currentAnneeLibelle ? '(' . htmlspecialchars($currentAnneeLibelle) . ')' : '' ?>
                                                    </p>
                                                    <h4 class="card-title"><?= number_format($totalEncaisse, 0, ',', ' ') ?>
                                                        FCFA</h4>
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
                                                <div class="icon-big text-center icon-primary bubble-shadow-small"
                                                    title="Validés aujourd'hui">
                                                    <i class="fas fa-calendar-day"></i>
                                                </div>
                                            </div>
                                            <div class="col col-stats ms-3 ms-sm-0">
                                                <div class="numbers">
                                                    <p class="card-category">Validés aujourd'hui</p>
                                                    <h4 class="card-title"><?= number_format($nbValidesJour) ?></h4>
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
                                                <div class="icon-big text-center icon-info bubble-shadow-small"
                                                    title="Validés ce mois">
                                                    <i class="fas fa-calendar-alt"></i>
                                                </div>
                                            </div>
                                            <div class="col col-stats ms-3 ms-sm-0">
                                                <div class="numbers">
                                                    <p class="card-category">Validés ce mois</p>
                                                    <h4 class="card-title"><?= number_format($nbValidesMois) ?></h4>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Graphiques -->
                        <div class="col-12 col-lg-8">
                            <div class="card">
                                <div class="card-header">
                                    <div class="card-title">Courbe des paiements (mensuel)</div>
                                </div>
                                <div class="card-body">
                                    <canvas id="paiementsMensuelsChart" height="120"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-lg-4">
                            <div class="card">
                                <div class="card-header">
                                    <div class="card-title">Répartition par filière</div>
                                </div>
                                <div class="card-body">
                                    <canvas id="filiereChart" height="260"></canvas>
                                </div>
                            </div>
                        </div>

                        <!-- Alertes -->
                        <div class="col-12 col-lg-6">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <div class="card-title mb-0">Alertes: Payés non validés</div>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($alertsNonValides)): ?>
                                        <p class="text-muted mb-0">Aucune alerte.</p>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Matricule</th>
                                                        <th>Nom</th>
                                                        <th>Réf</th>
                                                        <th>Montant</th>
                                                        <th>Date</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($alertsNonValides as $a): ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars($a['matricule']) ?></td>
                                                            <td><?= htmlspecialchars($a['nom'] . ' ' . $a['prenom']) ?></td>
                                                            <td><?= htmlspecialchars($a['ref_transaction']) ?></td>
                                                            <td><?= number_format((float)$a['montant'], 0, ',', ' ') ?></td>
                                                            <td><?= htmlspecialchars($a['date_paiement']) ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-lg-6">
                            <div class="card">
                                <div class="card-header">
                                    <div class="card-title">Alertes: Paiements suspects</div>
                                </div>
                                <div class="card-body">
                                    <h6 class="mb-2">Doublons de référence</h6>
                                    <?php if (empty($alertsDoublonsRef)): ?>
                                        <p class="text-muted">Aucun doublon de référence.</p>
                                    <?php else: ?>
                                        <ul class="list-group mb-3">
                                            <?php foreach ($alertsDoublonsRef as $d): ?>
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    <span>Réf <?= htmlspecialchars($d['ref_transaction']) ?>
                                                        (x<?= (int)$d['nb'] ?>)</span>
                                                    <span><?= number_format((float)$d['total'], 0, ',', ' ') ?> FCFA</span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>

                                    <h6 class="mb-2">Même étudiant / montant / jour</h6>
                                    <?php if (empty($alertsDoublonsEtud)): ?>
                                        <p class="text-muted mb-0">Aucun cluster suspect.</p>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>ID Étudiant</th>
                                                        <th>Jour</th>
                                                        <th>Montant</th>
                                                        <th>Occurrences</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($alertsDoublonsEtud as $d): ?>
                                                        <tr>
                                                            <td><?= (int)$d['id_etudiant'] ?></td>
                                                            <td><?= htmlspecialchars($d['jour']) ?></td>
                                                            <td><?= number_format((float)$d['montant'], 0, ',', ' ') ?></td>
                                                            <td><?= (int)$d['nb'] ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <!--   Core JS Files   -->
    <script src="../../assets/js/core/jquery-3.7.1.min.js"></script>
    <script src="../../assets/js/core/popper.min.js"></script>
    <script src="../../assets/js/core/bootstrap.min.js"></script>
    <script src="../../assets/js/plugin/chart.js/chart.min.js"></script>
    <script src="../../assets/js/kaiadmin.min.js"></script>
    <script>
        // Courbe paiements mensuels
        (function() {
            var el = document.getElementById('paiementsMensuelsChart');
            if (el) {
                new Chart(el.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: <?= json_encode($labelsMonthly ?? []) ?>,
                        datasets: [{
                            label: 'Montant validé (FCFA)',
                            data: <?= json_encode($dataMonthly ?? []) ?>,
                            borderColor: '#177dff',
                            backgroundColor: 'rgba(23,125,255,0.1)',
                            tension: 0.25,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
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

        // Histogramme par filière
        (function() {
            var el = document.getElementById('filiereChart');
            if (el) {
                new Chart(el.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: <?= json_encode($labelsFiliere ?? []) ?>,
                        datasets: [{
                            label: 'Montant (FCFA)',
                            data: <?= json_encode($dataFiliere ?? []) ?>,
                            backgroundColor: '#4caf50'
                        }]
                    },
                    options: {
                        responsive: true,
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
    </script>
</body>

</html>