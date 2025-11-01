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

$dpMgr = new DetentionProvisoireManager($pdo);
$detenuMgr = new DetenuManager($pdo);

// Récupérer l'ID
$detentionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$detention = $dpMgr->getById($detentionId);

if (!$detention) {
    header('Location: detentions_provisoires.php');
    exit();
}

// Récupérer les détails complets
$stmtDetails = $pdo->prepare("
    SELECT dp.*, 
           d.*, 
           i.libelle as infraction, i.categorie, i.gravite,
           l.nom as lieu, l.adresse as lieu_adresse,
           g.libelle as grade, g.code as grade_code,
           u.nom as unite
    FROM detentions_provisoires dp
    INNER JOIN detenus d ON dp.detenu_id = d.id
    LEFT JOIN infractions i ON dp.infraction_presume_id = i.id
    LEFT JOIN lieux_detention l ON dp.lieu_detention_id = l.id
    LEFT JOIN grades g ON d.grade_id = g.id
    LEFT JOIN unites u ON d.unite_id = u.id
    WHERE dp.id = :id
");
$stmtDetails->execute([':id' => $detentionId]);
$details = $stmtDetails->fetch();

// Historique des périodes de détention
$stmtPeriodes = $pdo->prepare("
    SELECT p.*, l.nom as lieu
    FROM periodes_detention p
    LEFT JOIN lieux_detention l ON p.lieu_detention_id = l.id
    WHERE p.detenu_id = :detenu_id
    ORDER BY p.date_debut DESC
");
$stmtPeriodes->execute([':detenu_id' => $detention['detenu_id']]);
$periodes = $stmtPeriodes->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Détails Détention Provisoire - Système Militaire</title>
    <meta content="width=device-width, initial-scale=1.0, shrink-to-fit=no" name="viewport" />
    <?php include '../../requires/link.php'; ?>
    <style>
    .info-card {
        border-left: 4px solid #177dff;
        transition: all 0.3s;
    }

    .info-card:hover {
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }

    .alert-depassee {
        background: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%);
        color: white;
        border: none;
    }

    .timeline {
        position: relative;
        padding-left: 30px;
    }

    .timeline::before {
        content: '';
        position: absolute;
        left: 10px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: linear-gradient(180deg, #177dff 0%, transparent 100%);
    }

    .timeline-item {
        position: relative;
        margin-bottom: 25px;
    }

    .timeline-item::before {
        content: '';
        position: absolute;
        left: -26px;
        top: 5px;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: #177dff;
        border: 3px solid white;
        box-shadow: 0 0 0 3px #177dff33;
    }

    .gauge {
        width: 200px;
        height: 200px;
        margin: 0 auto;
    }

    .action-btn {
        width: 100%;
        margin-bottom: 10px;
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
                    <div class="page-header">
                        <h3 class="fw-bold mb-3">
                            <i class="fas fa-file-alt me-2"></i>Détention Provisoire
                        </h3>
                        <ul class="breadcrumbs mb-3">
                            <li class="nav-home">
                                <a href="../dash/dashboard.php"><i class="fas fa-home"></i></a>
                            </li>
                            <li class="separator"><i class="fas fa-chevron-right"></i></li>
                            <li class="nav-item">
                                <a href="detentions_provisoires.php">Détentions Provisoires</a>
                            </li>
                            <li class="separator"><i class="fas fa-chevron-right"></i></li>
                            <li class="nav-item">Détails</li>
                        </ul>
                    </div>

                    <!-- Alerte si dépassée -->
                    <?php if ($detention['jours_restants'] < 0): ?>
                    <div class="alert alert-depassee">
                        <h4 class="alert-heading">
                            <i class="fas fa-exclamation-triangle me-2"></i>DÉTENTION DÉPASSÉE
                        </h4>
                        <p class="mb-0">
                            Cette détention provisoire a dépassé la durée légale maximale de
                            <strong><?= abs($detention['jours_restants']) ?> jours</strong>.
                            <br>Une libération immédiate ou un jugement est requis.
                        </p>
                    </div>
                    <?php endif; ?>

                    <div class="row">
                        <!-- Colonne gauche: Informations -->
                        <div class="col-md-8">
                            <!-- Informations générales -->
                            <div class="card info-card mb-3">
                                <div class="card-header bg-primary text-white">
                                    <h4 class="card-title mb-0">
                                        <i class="fas fa-info-circle me-2"></i>Informations Générales
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6 class="fw-bold">N° Dossier</h6>
                                            <p><?= htmlspecialchars($detention['numero_dossier']) ?></p>

                                            <h6 class="fw-bold">Détenu</h6>
                                            <p>
                                                <?= htmlspecialchars($detention['detenu']) ?><br>
                                                <small class="text-muted">
                                                    Matricule: <?= htmlspecialchars($detention['matricule']) ?><br>
                                                    Grade: <?= htmlspecialchars($details['grade']) ?><br>
                                                    Unité: <?= htmlspecialchars($details['unite']) ?>
                                                </small>
                                            </p>

                                            <h6 class="fw-bold">Infraction Présumée</h6>
                                            <p>
                                                <?= htmlspecialchars($detention['infraction_presume']) ?>
                                                <span
                                                    class="badge badge-<?= $detention['categorie_infraction'] === 'CRIME' ? 'danger' : ($detention['categorie_infraction'] === 'DELIT' ? 'warning' : 'info') ?>">
                                                    <?= $detention['categorie_infraction'] ?>
                                                </span>
                                            </p>
                                        </div>
                                        <div class="col-md-6">
                                            <h6 class="fw-bold">Lieu de Détention</h6>
                                            <p>
                                                <?= htmlspecialchars($detention['lieu_detention']) ?><br>
                                                <?php if ($details['lieu_adresse']): ?>
                                                <small
                                                    class="text-muted"><?= htmlspecialchars($details['lieu_adresse']) ?></small>
                                                <?php endif; ?>
                                            </p>

                                            <h6 class="fw-bold">Statut</h6>
                                            <p>
                                                <span
                                                    class="badge badge-<?= $detention['statut'] === 'EN_COURS' ? 'warning' : 'secondary' ?> badge-lg">
                                                    <?= $detention['statut'] ?>
                                                </span>
                                            </p>

                                            <h6 class="fw-bold">Niveau d'Alerte</h6>
                                            <p>
                                                <span
                                                    class="badge badge-<?= $detention['niveau_alerte'] === 'DEPASSEE' ? 'danger' : ($detention['niveau_alerte'] === 'CRITIQUE' ? 'warning' : 'info') ?> badge-lg">
                                                    <?= $detention['niveau_alerte'] ?>
                                                </span>
                                            </p>
                                        </div>
                                    </div>

                                    <?php if ($details['infraction_details']): ?>
                                    <hr>
                                    <h6 class="fw-bold">Détails des Faits</h6>
                                    <p><?= nl2br(htmlspecialchars($details['infraction_details'])) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Timeline procédurale -->
                            <div class="card info-card mb-3">
                                <div class="card-header">
                                    <h4 class="card-title mb-0">
                                        <i class="fas fa-history me-2"></i>Chronologie Procédurale
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <div class="timeline">
                                        <?php if ($details['date_faits']): ?>
                                        <div class="timeline-item">
                                            <small class="text-muted">Date des Faits</small><br>
                                            <strong><?= date('d/m/Y', strtotime($details['date_faits'])) ?></strong>
                                            <?php if ($details['lieu_faits']): ?>
                                            <br><small>à <?= htmlspecialchars($details['lieu_faits']) ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>

                                        <div class="timeline-item">
                                            <small class="text-muted">Date d'Arrestation</small><br>
                                            <strong><?= date('d/m/Y', strtotime($details['date_arrestation'])) ?></strong>
                                        </div>

                                        <?php if ($details['date_oip']): ?>
                                        <div class="timeline-item">
                                            <small class="text-muted">Ouverture Information Préliminaire
                                                (OIP)</small><br>
                                            <strong><?= date('d/m/Y', strtotime($details['date_oip'])) ?></strong>
                                        </div>
                                        <?php endif; ?>

                                        <?php if ($details['date_mandat_depot']): ?>
                                        <div class="timeline-item">
                                            <small class="text-muted">Mandat de Dépôt</small><br>
                                            <strong><?= date('d/m/Y', strtotime($details['date_mandat_depot'])) ?></strong>
                                            <?php if ($details['numero_mandat']): ?>
                                            <br><small>N° <?= htmlspecialchars($details['numero_mandat']) ?></small>
                                            <?php endif; ?>
                                            <?php if ($details['autorite_mandante']): ?>
                                            <br><small>par
                                                <?= htmlspecialchars($details['autorite_mandante']) ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>

                                        <div class="timeline-item">
                                            <small class="text-muted">Début Détention Provisoire</small><br>
                                            <strong><?= date('d/m/Y', strtotime($detention['date_debut_detention'])) ?></strong>
                                            <br><small class="text-info">Il y a
                                                <?= $detention['jours_detention_actuel'] ?> jours
                                                (<?= $detention['mois_detention_actuel'] ?> mois)</small>
                                        </div>

                                        <div class="timeline-item">
                                            <small class="text-muted">Date Limite Légale</small><br>
                                            <strong
                                                class="text-<?= $detention['jours_restants'] < 0 ? 'danger' : 'primary' ?>">
                                                <?= date('d/m/Y', strtotime($detention['date_limite_legale'])) ?>
                                            </strong>
                                            <br><small>(Max <?= $detention['duree_max_mois'] ?> mois)</small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Observations -->
                            <?php if ($details['observations'] || $details['motif_detention']): ?>
                            <div class="card info-card mb-3">
                                <div class="card-header">
                                    <h4 class="card-title mb-0">
                                        <i class="fas fa-comment me-2"></i>Observations
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <?php if ($details['motif_detention']): ?>
                                    <h6 class="fw-bold">Motif de la Détention</h6>
                                    <p><?= nl2br(htmlspecialchars($details['motif_detention'])) ?></p>
                                    <?php endif; ?>

                                    <?php if ($details['observations']): ?>
                                    <h6 class="fw-bold">Observations</h6>
                                    <p><?= nl2br(htmlspecialchars($details['observations'])) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Historique des périodes -->
                            <?php if (!empty($periodes)): ?>
                            <div class="card info-card">
                                <div class="card-header">
                                    <h4 class="card-title mb-0">
                                        <i class="fas fa-map-marker-alt me-2"></i>Historique des Périodes de Détention
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Type</th>
                                                    <th>Début</th>
                                                    <th>Fin</th>
                                                    <th>Durée</th>
                                                    <th>Lieu</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($periodes as $periode): ?>
                                                <tr>
                                                    <td><?= $periode['type'] ?></td>
                                                    <td><?= date('d/m/Y', strtotime($periode['date_debut'])) ?></td>
                                                    <td><?= $periode['date_fin'] ? date('d/m/Y', strtotime($periode['date_fin'])) : '<span class="badge badge-warning">En cours</span>' ?>
                                                    </td>
                                                    <td><?= $periode['duree_jours'] ?? '-' ?> jours</td>
                                                    <td><?= htmlspecialchars($periode['lieu']) ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Colonne droite: Actions et Stats -->
                        <div class="col-md-4">
                            <!-- Progression -->
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-chart-pie me-2"></i>Progression
                                    </h5>
                                </div>
                                <div class="card-body text-center">
                                    <?php
                                    $pct = min(100, $detention['pourcentage_duree']);
                                    $color = $pct >= 100 ? 'danger' : ($pct >= 90 ? 'warning' : ($pct >= 75 ? 'info' : 'success'));
                                    ?>

                                    <div class="mb-4">
                                        <h1 class="text-<?= $color ?>"><?= round($pct) ?>%</h1>
                                        <p class="text-muted">de la durée max écoulée</p>
                                    </div>

                                    <div class="progress" style="height: 30px;">
                                        <div class="progress-bar bg-<?= $color ?> progress-bar-striped progress-bar-animated"
                                            style="width: <?= $pct ?>%">
                                        </div>
                                    </div>

                                    <div class="mt-4">
                                        <?php if ($detention['jours_restants'] >= 0): ?>
                                        <h3
                                            class="text-<?= $detention['jours_restants'] <= 7 ? 'danger' : 'primary' ?>">
                                            <?= $detention['jours_restants'] ?>
                                        </h3>
                                        <p class="text-muted">jours restants</p>
                                        <?php else: ?>
                                        <h3 class="text-danger">
                                            <?= abs($detention['jours_restants']) ?>
                                        </h3>
                                        <p class="text-danger">jours de dépassement</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="card mb-3">
                                <div class="card-header bg-warning text-white">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-cogs me-2"></i>Actions
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php if ($detention['jours_restants'] <= 0): ?>
                                    <a href="liberer_detention_provisoire.php?id=<?= $detentionId ?>"
                                        class="btn btn-danger action-btn">
                                        <i class="fas fa-door-open me-2"></i>Libérer Immédiatement
                                    </a>
                                    <?php endif; ?>

                                    <a href="convertir_condamnation.php?detention_id=<?= $detentionId ?>"
                                        class="btn btn-success action-btn">
                                        <i class="fas fa-gavel me-2"></i>Convertir en Condamnation
                                    </a>

                                    <a href="liberer_detention_provisoire.php?id=<?= $detentionId ?>"
                                        class="btn btn-info action-btn">
                                        <i class="fas fa-door-open me-2"></i>Libérer (Non-lieu)
                                    </a>

                                    <hr>

                                    <a href="modifier_detention_provisoire.php?id=<?= $detentionId ?>"
                                        class="btn btn-warning action-btn">
                                        <i class="fas fa-edit me-2"></i>Modifier
                                    </a>

                                    <a href="detentions_provisoires.php" class="btn btn-secondary action-btn">
                                        <i class="fas fa-arrow-left me-2"></i>Retour à la Liste
                                    </a>
                                </div>
                            </div>

                            <!-- Informations rapides -->
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-info me-2"></i>Informations Rapides
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Catégorie:</span>
                                        <strong><?= $detention['categorie_infraction'] ?></strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Durée max:</span>
                                        <strong><?= $detention['duree_max_mois'] ?> mois</strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Durée écoulée:</span>
                                        <strong><?= $detention['jours_detention_actuel'] ?> jours</strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Gravité:</span>
                                        <strong><?= $details['gravite'] ?>/10</strong>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span>Enregistré le:</span>
                                        <strong><?= date('d/m/Y', strtotime($detention['created_at'])) ?></strong>
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
</body>

</html>