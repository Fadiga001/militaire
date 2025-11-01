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

// Filtres
$periode = $_GET['periode'] ?? '30';
$lieu_id = $_GET['lieu_id'] ?? '';
$alerte = $_GET['alerte'] ?? '';

// Construction de la requête
$sql = "SELECT c.*,
        d.nom_complet as detenu, d.matricule, d.grade_id, d.unite_id,
        g.libelle as grade, g.code as grade_code,
        u.nom as unite,
        i.libelle as infraction, i.categorie,
        l.nom as lieu_detention,
        DATEDIFF(c.date_liberation_effective, NOW()) as jours_restants,
        CASE 
            WHEN c.date_liberation_effective < NOW() THEN 'LIBERABLE'
            WHEN DATEDIFF(c.date_liberation_effective, NOW()) <= 1 THEN 'CRITIQUE'
            WHEN DATEDIFF(c.date_liberation_effective, NOW()) <= 7 THEN 'URGENT'
            WHEN DATEDIFF(c.date_liberation_effective, NOW()) <= 14 THEN 'ATTENTION'
            WHEN DATEDIFF(c.date_liberation_effective, NOW()) <= 30 THEN 'A_SUIVRE'
            ELSE 'NORMAL'
        END as alerte_niveau
        FROM condamnations c
        INNER JOIN detenus d ON c.detenu_id = d.id
        LEFT JOIN grades g ON d.grade_id = g.id
        LEFT JOIN unites u ON d.unite_id = u.id
        LEFT JOIN infractions i ON c.infraction_id = i.id
        LEFT JOIN lieux_detention l ON c.lieu_detention_id = l.id
        WHERE c.statut = 'EN_COURS' 
        AND c.is_deleted = FALSE
        AND c.date_liberation_effective IS NOT NULL";

$params = [];

// Filtre période
if ($periode !== 'all') {
    $sql .= " AND DATEDIFF(c.date_liberation_effective, NOW()) <= :periode";
    $params[':periode'] = (int)$periode;
}

// Filtre lieu
if ($lieu_id) {
    $sql .= " AND c.lieu_detention_id = :lieu_id";
    $params[':lieu_id'] = $lieu_id;
}

// Filtre niveau alerte
if ($alerte) {
    switch ($alerte) {
        case 'LIBERABLE':
            $sql .= " AND c.date_liberation_effective < NOW()";
            break;
        case 'CRITIQUE':
            $sql .= " AND DATEDIFF(c.date_liberation_effective, NOW()) <= 1";
            break;
        case 'URGENT':
            $sql .= " AND DATEDIFF(c.date_liberation_effective, NOW()) <= 7";
            break;
        case 'ATTENTION':
            $sql .= " AND DATEDIFF(c.date_liberation_effective, NOW()) <= 14";
            break;
        case 'A_SUIVRE':
            $sql .= " AND DATEDIFF(c.date_liberation_effective, NOW()) <= 30";
            break;
    }
}

$sql .= " ORDER BY c.date_liberation_effective ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$liberations = $stmt->fetchAll();

// Récupérer la liste des lieux pour le filtre
$refMgr = new ReferenceManager($pdo);
$lieux = $refMgr->getAllLieuxDetention();

// Statistiques
$stats = [
    'total' => count($liberations),
    'liberable' => count(array_filter($liberations, fn($l) => $l['alerte_niveau'] === 'LIBERABLE')),
    'critique' => count(array_filter($liberations, fn($l) => $l['alerte_niveau'] === 'CRITIQUE')),
    'urgent' => count(array_filter($liberations, fn($l) => $l['alerte_niveau'] === 'URGENT')),
    'attention' => count(array_filter($liberations, fn($l) => $l['alerte_niveau'] === 'ATTENTION'))
];
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Libérations Prévues - Système Militaire</title>
    <meta content="width=device-width, initial-scale=1.0, shrink-to-fit=no" name="viewport" />
    <?php include '../../requires/link.php'; ?>
    <style>
    .alert-level-LIBERABLE {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
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

    .alert-level-A_SUIVRE {
        background: linear-gradient(135deg, #30cfd0 0%, #330867 100%);
        color: white;
    }

    .liberation-card {
        border-left: 5px solid;
        transition: all 0.3s;
    }

    .liberation-card:hover {
        transform: translateX(5px);
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
    }

    .liberation-card.niveau-LIBERABLE {
        border-left-color: #667eea;
    }

    .liberation-card.niveau-CRITIQUE {
        border-left-color: #ff416c;
    }

    .liberation-card.niveau-URGENT {
        border-left-color: #f093fb;
    }

    .liberation-card.niveau-ATTENTION {
        border-left-color: #ffc837;
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
        margin-bottom: 20px;
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
    }

    @media print {
        .no-print {
            display: none !important;
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
                                <i class="fas fa-calendar-check me-2"></i>Libérations Prévues
                            </h3>
                            <h6 class="op-7 mb-2">
                                <?= $stats['total'] ?> libération(s) trouvée(s)
                            </h6>
                        </div>
                        <div class="ms-md-auto py-2 py-md-0 no-print">
                            <button onclick="window.print()" class="btn btn-primary btn-round">
                                <i class="fas fa-print me-2"></i>Imprimer
                            </button>
                            <a href="export_liberations.php?<?= http_build_query($_GET) ?>"
                                class="btn btn-success btn-round">
                                <i class="fas fa-file-excel me-2"></i>Exporter Excel
                            </a>
                        </div>
                    </div>

                    <!-- Statistiques -->
                    <div class="row mb-4">
                        <div class="col-sm-6 col-lg-3">
                            <div class="card card-stats card-round">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-icon">
                                            <div class="icon-big text-center icon-secondary bubble-shadow-small">
                                                <i class="fas fa-door-open"></i>
                                            </div>
                                        </div>
                                        <div class="col col-stats ms-3 ms-sm-0">
                                            <div class="numbers">
                                                <p class="card-category">Libérables</p>
                                                <h4 class="card-title"><?= $stats['liberable'] ?></h4>
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
                                                <p class="card-category">Critique (≤1j)</p>
                                                <h4 class="card-title"><?= $stats['critique'] ?></h4>
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
                                                <p class="card-category">Urgent (≤7j)</p>
                                                <h4 class="card-title"><?= $stats['urgent'] ?></h4>
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
                                                <i class="fas fa-calendar-alt"></i>
                                            </div>
                                        </div>
                                        <div class="col col-stats ms-3 ms-sm-0">
                                            <div class="numbers">
                                                <p class="card-category">Attention (≤14j)</p>
                                                <h4 class="card-title"><?= $stats['attention'] ?></h4>
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
                                                <i class="fas fa-calendar me-2"></i>Période
                                            </label>
                                            <select name="periode" class="form-select" onchange="this.form.submit()">
                                                <option value="7" <?= $periode == '7' ? 'selected' : '' ?>>7 prochains
                                                    jours</option>
                                                <option value="14" <?= $periode == '14' ? 'selected' : '' ?>>14
                                                    prochains jours</option>
                                                <option value="30" <?= $periode == '30' ? 'selected' : '' ?>>30
                                                    prochains jours</option>
                                                <option value="60" <?= $periode == '60' ? 'selected' : '' ?>>60
                                                    prochains jours</option>
                                                <option value="90" <?= $periode == '90' ? 'selected' : '' ?>>90
                                                    prochains jours</option>
                                                <option value="all" <?= $periode == 'all' ? 'selected' : '' ?>>Toutes
                                                </option>
                                            </select>
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label">
                                                <i class="fas fa-map-marker-alt me-2"></i>Lieu de Détention
                                            </label>
                                            <select name="lieu_id" class="form-select" onchange="this.form.submit()">
                                                <option value="">Tous les lieux</option>
                                                <?php foreach ($lieux as $lieu): ?>
                                                <option value="<?= $lieu['id'] ?>"
                                                    <?= $lieu_id == $lieu['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($lieu['nom']) ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label">
                                                <i class="fas fa-exclamation-triangle me-2"></i>Niveau d'Alerte
                                            </label>
                                            <select name="alerte" class="form-select" onchange="this.form.submit()">
                                                <option value="">Tous les niveaux</option>
                                                <option value="LIBERABLE"
                                                    <?= $alerte == 'LIBERABLE' ? 'selected' : '' ?>>
                                                    Libérable</option>
                                                <option value="CRITIQUE" <?= $alerte == 'CRITIQUE' ? 'selected' : '' ?>>
                                                    Critique</option>
                                                <option value="URGENT" <?= $alerte == 'URGENT' ? 'selected' : '' ?>>
                                                    Urgent</option>
                                                <option value="ATTENTION"
                                                    <?= $alerte == 'ATTENTION' ? 'selected' : '' ?>>
                                                    Attention</option>
                                                <option value="A_SUIVRE" <?= $alerte == 'A_SUIVRE' ? 'selected' : '' ?>>
                                                    À
                                                    Suivre</option>
                                            </select>
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label">&nbsp;</label>
                                            <a href="rapport_liberations.php" class="btn btn-secondary w-100">
                                                <i class="fas fa-redo me-2"></i>Réinitialiser
                                            </a>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Liste des libérations -->
                    <?php if (empty($liberations)): ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">Aucune libération prévue selon les critères sélectionnés</h5>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table id="liberations-table" class="table table-hover table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Détenu</th>
                                                    <th>Grade/Unité</th>
                                                    <th>Infraction</th>
                                                    <th>Lieu</th>
                                                    <th>Date Libération</th>
                                                    <th>Jours Restants</th>
                                                    <th>Alerte</th>
                                                    <th class="no-print">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($liberations as $liberation): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?= htmlspecialchars($liberation['detenu']) ?></strong><br>
                                                        <small
                                                            class="text-muted"><?= htmlspecialchars($liberation['matricule']) ?></small>
                                                    </td>
                                                    <td>
                                                        <span
                                                            class="badge badge-secondary"><?= htmlspecialchars($liberation['grade_code']) ?></span><br>
                                                        <small><?= htmlspecialchars($liberation['unite']) ?></small>
                                                    </td>
                                                    <td>
                                                        <?= htmlspecialchars($liberation['infraction']) ?><br>
                                                        <span
                                                            class="badge badge-<?= $liberation['categorie'] === 'CRIME' ? 'danger' : ($liberation['categorie'] === 'DELIT' ? 'warning' : 'info') ?>">
                                                            <?= htmlspecialchars($liberation['categorie']) ?>
                                                        </span>
                                                    </td>
                                                    <td><?= htmlspecialchars($liberation['lieu_detention'] ?? 'N/A') ?>
                                                    </td>
                                                    <td>
                                                        <strong><?= date('d/m/Y', strtotime($liberation['date_liberation_effective'])) ?></strong>
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
                                                    <td class="no-print">
                                                        <a href="../condamnations/details_condamnation.php?id=<?= $liberation['id'] ?>"
                                                            class="btn btn-sm btn-info" title="Détails">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <?php if ($liberation['jours_restants'] <= 0): ?>
                                                        <button class="btn btn-sm btn-success"
                                                            onclick="liberer(<?= $liberation['id'] ?>)" title="Libérer">
                                                            <i class="fas fa-door-open"></i>
                                                        </button>
                                                        <?php endif; ?>
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
    <script>
    $(document).ready(function() {
        $('#liberations-table').DataTable({
            pageLength: 25,
            order: [
                [4, 'asc']
            ], // Trier par date de libération
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/fr-FR.json'
            }
        });
    });

    function liberer(condamnationId) {
        if (confirm('Voulez-vous vraiment procéder à la libération de ce détenu ?')) {
            window.location.href = `../condamnations/liberer.php?id=${condamnationId}`;
        }
    }
    </script>
</body>

</html>