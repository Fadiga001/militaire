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

// Message de succès
$successMessage = $_SESSION['success_message'] ?? '';
unset($_SESSION['success_message']);

// Filtres
$priorite = $_GET['priorite'] ?? '';
$categorie = $_GET['categorie'] ?? '';
$search = $_GET['search'] ?? '';

$filters = [];
if ($priorite) $filters['priorite'] = $priorite;
if ($categorie) $filters['categorie'] = $categorie;
if ($search) $filters['search'] = $search;

// Récupérer les données
$detentions = $dpMgr->getAll($filters);
$stats = $dpMgr->getStatistiques();
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Détentions Provisoires - Système Militaire</title>
    <meta content="width=device-width, initial-scale=1.0, shrink-to-fit=no" name="viewport" />
    <?php include '../../requires/link.php'; ?>
    <style>
    .card-detention {
        transition: all 0.3s;
        cursor: pointer;
    }

    .card-detention:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
    }

    .niveau-DEPASSEE {
        border-left: 5px solid #dc3545;
        background: linear-gradient(90deg, rgba(220, 53, 69, 0.1) 0%, transparent 100%);
    }

    .niveau-CRITIQUE {
        border-left: 5px solid #fd7e14;
        background: linear-gradient(90deg, rgba(253, 126, 20, 0.1) 0%, transparent 100%);
    }

    .niveau-URGENT {
        border-left: 5px solid #ffc107;
        background: linear-gradient(90deg, rgba(255, 193, 7, 0.1) 0%, transparent 100%);
    }

    .niveau-ATTENTION {
        border-left: 5px solid #17a2b8;
        background: linear-gradient(90deg, rgba(23, 162, 184, 0.1) 0%, transparent 100%);
    }

    .progress-container {
        position: relative;
        margin: 15px 0;
    }

    .badge-pulse {
        animation: pulse 2s infinite;
    }

    @keyframes pulse {

        0%,
        100% {
            opacity: 1;
        }

        50% {
            opacity: 0.6;
        }
    }

    .stat-icon {
        font-size: 3rem;
        opacity: 0.2;
        position: absolute;
        right: 15px;
        top: 15px;
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
                                <i class="fas fa-clock me-2"></i>Détentions Provisoires
                            </h3>
                            <h6 class="op-7 mb-2">
                                <?= count($detentions) ?> détention(s) en cours
                            </h6>
                        </div>
                        <div class="ms-md-auto py-2 py-md-0">
                            <a href="ajouter_detention_provisoire.php" class="btn btn-primary btn-round">
                                <i class="fas fa-plus me-2"></i>Nouvelle Détention Provisoire
                            </a>
                            <button onclick="window.print()" class="btn btn-secondary btn-round">
                                <i class="fas fa-print me-2"></i>Imprimer
                            </button>
                        </div>
                    </div>

                    <?php if ($successMessage): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i>
                        <?= htmlspecialchars($successMessage) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <!-- Alertes Critiques -->
                    <?php if ($stats['depassees'] > 0): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <h5 class="alert-heading">
                            <i class="fas fa-exclamation-triangle me-2"></i>ALERTE CRITIQUE
                        </h5>
                        <strong><?= $stats['depassees'] ?></strong> détention(s) provisoire(s) a/ont dépassé la durée
                        légale maximale !
                        Libération obligatoire requise.
                        <hr>
                        <a href="?priorite=DEPASSEE" class="btn btn-sm btn-danger">
                            <i class="fas fa-eye me-1"></i>Voir les cas
                        </a>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <?php if ($stats['critiques'] > 0): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-clock me-2"></i>
                        <strong><?= $stats['critiques'] ?></strong> détention(s) critique(s) (≤ 3 jours restants)
                        <a href="?priorite=CRITIQUE" class="ms-2">Voir</a>
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
                                                <i class="fas fa-users"></i>
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

                        <div class="col-sm-6 col-lg-3">
                            <div class="card card-stats card-round">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-icon">
                                            <div class="icon-big text-center icon-danger bubble-shadow-small">
                                                <i class="fas fa-exclamation-circle"></i>
                                            </div>
                                        </div>
                                        <div class="col col-stats ms-3 ms-sm-0">
                                            <div class="numbers">
                                                <p class="card-category">Dépassées</p>
                                                <h4 class="card-title"><?= $stats['depassees'] ?></h4>
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
                                                <i class="fas fa-clock"></i>
                                            </div>
                                        </div>
                                        <div class="col col-stats ms-3 ms-sm-0">
                                            <div class="numbers">
                                                <p class="card-category">Critiques</p>
                                                <h4 class="card-title"><?= $stats['critiques'] ?></h4>
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
                                                <i class="fas fa-calendar"></i>
                                            </div>
                                        </div>
                                        <div class="col col-stats ms-3 ms-sm-0">
                                            <div class="numbers">
                                                <p class="card-category">Durée Moy.</p>
                                                <h4 class="card-title"><?= round($stats['duree_moyenne_jours']) ?>j</h4>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filtres -->
                    <div class="row mb-4 no-print">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <form method="GET" class="row g-3">
                                        <div class="col-md-3">
                                            <label class="form-label">
                                                <i class="fas fa-filter me-2"></i>Priorité
                                            </label>
                                            <select name="priorite" class="form-select" onchange="this.form.submit()">
                                                <option value="">Toutes</option>
                                                <option value="DEPASSEE"
                                                    <?= $priorite === 'DEPASSEE' ? 'selected' : '' ?>>
                                                    Dépassées
                                                </option>
                                                <option value="CRITIQUE"
                                                    <?= $priorite === 'CRITIQUE' ? 'selected' : '' ?>>
                                                    Critiques (≤ 3j)
                                                </option>
                                                <option value="URGENT" <?= $priorite === 'URGENT' ? 'selected' : '' ?>>
                                                    Urgentes (≤ 7j)
                                                </option>
                                                <option value="ATTENTION"
                                                    <?= $priorite === 'ATTENTION' ? 'selected' : '' ?>>
                                                    Attention (≤ 30j)
                                                </option>
                                            </select>
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label">
                                                <i class="fas fa-balance-scale me-2"></i>Catégorie
                                            </label>
                                            <select name="categorie" class="form-select" onchange="this.form.submit()">
                                                <option value="">Toutes</option>
                                                <option value="CRIME" <?= $categorie === 'CRIME' ? 'selected' : '' ?>>
                                                    Crime (24 mois)
                                                </option>
                                                <option value="DELIT" <?= $categorie === 'DELIT' ? 'selected' : '' ?>>
                                                    Délit (18 mois)
                                                </option>
                                                <option value="CONTRAVENTION"
                                                    <?= $categorie === 'CONTRAVENTION' ? 'selected' : '' ?>>
                                                    Contravention (6 mois)
                                                </option>
                                            </select>
                                        </div>

                                        <div class="col-md-4">
                                            <label class="form-label">
                                                <i class="fas fa-search me-2"></i>Recherche
                                            </label>
                                            <input type="text" name="search" class="form-control"
                                                placeholder="Nom, matricule, n° dossier..."
                                                value="<?= htmlspecialchars($search) ?>">
                                        </div>

                                        <div class="col-md-2">
                                            <label class="form-label">&nbsp;</label>
                                            <div class="d-grid gap-2">
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="fas fa-search"></i>
                                                </button>
                                                <a href="detentions_provisoires.php" class="btn btn-secondary">
                                                    <i class="fas fa-redo"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Liste des détentions -->
                    <?php if (empty($detentions)): ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                            <h5>Aucune détention provisoire trouvée</h5>
                            <p class="text-muted">Tous les détenus ont été jugés ou libérés</p>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="row">
                        <?php foreach ($detentions as $detention): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card card-detention niveau-<?= $detention['niveau_alerte'] ?>"
                                onclick="window.location.href='details_detention_provisoire.php?id=<?= $detention['id'] ?>'">
                                <div class="card-body position-relative">
                                    <!-- Badge priorité -->
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <h5 class="card-title mb-0">
                                            <?= htmlspecialchars($detention['detenu']) ?>
                                        </h5>
                                        <span
                                            class="badge bg-<?= $detention['niveau_alerte'] === 'DEPASSEE' ? 'danger' : ($detention['niveau_alerte'] === 'CRITIQUE' ? 'warning' : 'info') ?> badge-pulse">
                                            <?= $detention['priorite'] ?>
                                        </span>
                                    </div>

                                    <!-- Infos détenu -->
                                    <p class="text-muted small mb-3">
                                        <i
                                            class="fas fa-id-card me-1"></i><?= htmlspecialchars($detention['matricule']) ?><br>
                                        <i class="fas fa-star me-1"></i><?= htmlspecialchars($detention['grade']) ?><br>
                                        <i
                                            class="fas fa-folder me-1"></i><?= htmlspecialchars($detention['numero_dossier']) ?>
                                    </p>

                                    <!-- Infraction -->
                                    <div class="mb-3">
                                        <strong><?= htmlspecialchars($detention['infraction_presume']) ?></strong>
                                        <span
                                            class="badge badge-<?= $detention['categorie_infraction'] === 'CRIME' ? 'danger' : ($detention['categorie_infraction'] === 'DELIT' ? 'warning' : 'info') ?> ms-2">
                                            <?= $detention['categorie_infraction'] ?>
                                        </span>
                                    </div>

                                    <!-- Dates -->
                                    <div class="mb-3 small">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span>Début:</span>
                                            <strong><?= date('d/m/Y', strtotime($detention['date_debut_detention'])) ?></strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-1">
                                            <span>Limite:</span>
                                            <strong
                                                class="text-<?= $detention['jours_restants'] < 0 ? 'danger' : 'primary' ?>">
                                                <?= date('d/m/Y', strtotime($detention['date_limite_legale'])) ?>
                                            </strong>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span>Durée écoulée:</span>
                                            <strong><?= $detention['jours_detention_actuel'] ?> jours
                                                (<?= $detention['mois_detention_actuel'] ?> mois)</strong>
                                        </div>
                                    </div>

                                    <!-- Barre de progression -->
                                    <div class="progress-container">
                                        <div class="d-flex justify-content-between mb-1 small">
                                            <span>Progression</span>
                                            <strong><?= round($detention['pourcentage_duree']) ?>%</strong>
                                        </div>
                                        <div class="progress" style="height: 25px;">
                                            <?php
                                                    $pct = min(100, $detention['pourcentage_duree']);
                                                    $color = $pct >= 100 ? 'danger' : ($pct >= 90 ? 'warning' : ($pct >= 75 ? 'info' : 'success'));
                                                    ?>
                                            <div class="progress-bar bg-<?= $color ?> progress-bar-striped progress-bar-animated"
                                                style="width: <?= $pct ?>%">
                                                <?= round($pct) ?>%
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Jours restants -->
                                    <?php if ($detention['jours_restants'] < 0): ?>
                                    <div class="alert alert-danger mt-3 mb-0">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        <strong>DÉPASSÉE de <?= abs($detention['jours_restants']) ?> jours</strong>
                                    </div>
                                    <?php else: ?>
                                    <div
                                        class="alert alert-<?= $detention['jours_restants'] <= 3 ? 'danger' : ($detention['jours_restants'] <= 7 ? 'warning' : 'info') ?> mt-3 mb-0">
                                        <strong><?= $detention['jours_restants'] ?> jours restants</strong>
                                    </div>
                                    <?php endif; ?>

                                    <!-- Lieu -->
                                    <div class="mt-2 text-muted small">
                                        <i class="fas fa-map-marker-alt me-1"></i>
                                        <?= htmlspecialchars($detention['lieu_detention']) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php include '../../requires/script.php'; ?>
    <script>
    // Auto-dismiss success alert
    setTimeout(function() {
        $('.alert-success').fadeOut();
    }, 5000);
    </script>
</body>

</html>