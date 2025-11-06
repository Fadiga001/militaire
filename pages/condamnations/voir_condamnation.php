<?php
require_once '../../includes/db.php';
require_once '../../includes/classes/autoload.php';
require_once '../../includes/auth.php';
require_once '../../includes/csrf.php';

Auth::requireAuth('../../index.php');

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([Auth::id()]);
$user = $stmt->fetch();
$name = $user ? htmlspecialchars($user['nom'] . ' ' . $user['prenom']) : '';
$role = $user['role'] ?? '';

$condamnationMgr = new CondamnationManager($pdo);

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: condamnations.php');
    exit();
}

$condamnationId = (int)$_GET['id'];

// Action admin: recalculer la date de libération (forcer)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'recalc') {
    try {
        CSRF::verify();
        if (($user['role'] ?? '') !== 'ADMIN') {
            throw new Exception('Action réservée aux administrateurs');
        }

        // Recalcul: date_liberation_effective = date_theorique - total_remises
        // (PAS de DP car condamnation directe)
        $recalc = $pdo->prepare("
            UPDATE condamnations c
            LEFT JOIN (
                SELECT condamnation_id, COALESCE(SUM(jours_remis), 0) AS total_remises
                FROM remises_peine
                WHERE condamnation_id = :id
                GROUP BY condamnation_id
            ) r ON r.condamnation_id = c.id
            SET c.date_liberation_effective = DATE_SUB(
                c.date_liberation_theorique,
                INTERVAL COALESCE(r.total_remises, 0) DAY
            )
            WHERE c.id = :id
        ");
        $recalc->execute([':id' => $condamnationId]);

        // Log d'audit
        $audit = $pdo->prepare("
            INSERT INTO audit_logs (user_id, action, entity_type, entity_id, new_values)
            VALUES (:user_id, 'RECALCUL_LIBERATION_FORCE', 'CONDAMNATION', :entity_id, :payload)
        ");
        $audit->execute([
            ':user_id' => Auth::id(),
            ':entity_id' => $condamnationId,
            ':payload' => json_encode([
                'source' => 'voir_condamnation.php',
                'mode' => 'DIRECTE',
                'jours_dp' => 0
            ], JSON_UNESCAPED_UNICODE)
        ]);

        header('Location: voir_condamnation.php?id=' . $condamnationId . '&success=recalc');
        exit();
    } catch (Exception $e) {
        // Silencieux
    }
}

$condamnation = $condamnationMgr->getById($condamnationId);

if (!$condamnation) {
    header('Location: condamnations.php');
    exit();
}

// Récupérer les remises de peine
$remises = $condamnationMgr->getRemises($condamnationId);
$totalRemises = array_sum(array_column($remises, 'jours_remis'));

// Calculs simplifiés (pas de DP)
$peineJoursTotal = (int)$condamnation['peine_jours_total'];
$joursRestants = (int)$condamnation['jours_restants'];
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Condamnation <?= htmlspecialchars($condamnation['numero_dossier']) ?></title>
    <meta content="width=device-width, initial-scale=1.0, shrink-to-fit=no" name="viewport" />
    <?php include '../../requires/link.php'; ?>
    <style>
        .info-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }

        .info-box h2 {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0;
        }

        .info-box p {
            margin: 5px 0 0 0;
            opacity: 0.9;
        }

        .alerte-CRITIQUE {
            background: #dc3545;
            color: white;
        }

        .alerte-URGENT {
            background: #fd7e14;
            color: white;
        }

        .alerte-ATTENTION {
            background: #ffc107;
            color: #000;
        }

        .alerte-A_SUIVRE {
            background: #17a2b8;
            color: white;
        }

        .alerte-NORMAL {
            background: #28a745;
            color: white;
        }

        .alerte-LIBERABLE {
            background: #6c757d;
            color: white;
        }

        .timeline-remise {
            border-left: 3px solid #28a745;
            padding-left: 20px;
        }

        .timeline-item {
            position: relative;
            padding-bottom: 20px;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -26px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #28a745;
            border: 2px solid #fff;
            box-shadow: 0 0 0 2px #28a745;
        }

        .calcul-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #177dff;
        }

        .mode-direct-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
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
                            <i class="fas fa-gavel me-2"></i>Détails de la Condamnation Directe
                        </h3>
                        <ul class="breadcrumbs mb-3">
                            <li class="nav-home">
                                <a href="../dash/dashboard.php"><i class="fas fa-home"></i></a>
                            </li>
                            <li class="separator"><i class="fas fa-arrow-right"></i></li>
                            <li class="nav-item">
                                <a href="condamnations.php">Condamnations</a>
                            </li>
                            <li class="separator"><i class="fas fa-arrow-right"></i></li>
                            <li class="nav-item"><?= htmlspecialchars($condamnation['numero_dossier']) ?></li>
                        </ul>
                    </div>

                    <?php if (isset($_GET['success']) && $_GET['success'] === 'recalc'): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle me-2"></i>
                            <strong>Recalcul effectué avec succès !</strong>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Badge Mode Directe -->
                    <div class="mode-direct-badge">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h5 class="mb-1">
                                    <i class="fas fa-bolt me-2"></i>
                                    CONDAMNATION DIRECTE
                                </h5>
                                <p class="mb-0">
                                    Sans détention provisoire préalable • 0 jour déduit • Calcul simplifié
                                </p>
                            </div>
                            <div class="col-md-4 text-end">
                                <div class="badge badge-light badge-lg">
                                    <i class="fas fa-calendar-check me-2"></i>
                                    Date libération = Jugement + Peine - Remises
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Actions rapides -->
                    <div class="d-flex justify-content-end mb-3">
                        <?php if ($condamnation['statut'] === 'EN_COURS'): ?>
                            <a href="modifier_condamnation.php?id=<?= $condamnation['id'] ?>" class="btn btn-warning me-2">
                                <i class="fas fa-edit me-2"></i>Modifier
                            </a>
                            <a href="ajouter_remise.php?condamnation_id=<?= $condamnation['id'] ?>"
                                class="btn btn-success me-2">
                                <i class="fas fa-gift me-2"></i>Ajouter Remise
                            </a>
                            <?php if ($role === 'ADMIN'): ?>
                                <form method="POST" class="d-inline">
                                    <?= CSRF::field() ?>
                                    <input type="hidden" name="action" value="recalc">
                                    <button type="submit" class="btn btn-info me-2"
                                        onclick="return confirm('Recalculer la date de libération ?')">
                                        <i class="fas fa-sync-alt me-2"></i>Recalculer Libération
                                    </button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                        <a href="condamnations.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Retour
                        </a>
                    </div>

                    <!-- Alerte libération -->
                    <?php if ($condamnation['statut'] === 'EN_COURS'): ?>
                        <div class="alert alerte-<?= $condamnation['alerte_niveau'] ?> mb-4">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="mb-0">
                                        <i class="fas fa-bell me-2"></i>
                                        Niveau d'alerte: <?= str_replace('_', ' ', $condamnation['alerte_niveau']) ?>
                                    </h5>
                                    <p class="mb-0">
                                        <?php
                                        if ($joursRestants < 0) {
                                            echo "⚠️ Libération dépassée de " . abs($joursRestants) . " jour(s)";
                                        } else {
                                            echo "Libération dans $joursRestants jour(s)";
                                        }
                                        ?>
                                    </p>
                                </div>
                                <?php if ($role === 'ADMIN' && $joursRestants <= 0): ?>
                                    <a href="condamnations.php" class="btn btn-light">
                                        <i class="fas fa-door-open me-2"></i>Libérer
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="row">
                        <!-- Informations principales -->
                        <div class="col-lg-8">
                            <!-- Dossier -->
                            <div class="card">
                                <div class="card-header bg-primary text-white">
                                    <h4 class="card-title mb-0">
                                        <i class="fas fa-folder me-2"></i>Informations du Dossier
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <p class="text-muted mb-1">N° Dossier</p>
                                            <p class="h5"><?= htmlspecialchars($condamnation['numero_dossier']) ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <p class="text-muted mb-1">Statut</p>
                                            <p>
                                                <?php
                                                $statutBadge = [
                                                    'EN_COURS' => 'warning',
                                                    'TERMINEE' => 'success',
                                                    'SUSPENDUE' => 'info',
                                                    'ANNULEE' => 'secondary'
                                                ];
                                                $badge = $statutBadge[$condamnation['statut']] ?? 'secondary';
                                                ?>
                                                <span class="badge badge-<?= $badge ?> badge-lg">
                                                    <?= str_replace('_', ' ', $condamnation['statut']) ?>
                                                </span>
                                            </p>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <p class="text-muted mb-1">Détenu</p>
                                            <p class="h5">
                                                <a
                                                    href="../detenus/voir_detenu.php?id=<?= $condamnation['detenu_id'] ?>">
                                                    <?= htmlspecialchars($condamnation['detenu_nom']) ?>
                                                </a>
                                            </p>
                                            <small class="text-muted">
                                                <?= htmlspecialchars($condamnation['detenu_matricule']) ?> •
                                                <?= htmlspecialchars($condamnation['detenu_grade'] ?? 'N/A') ?>
                                            </small>
                                        </div>
                                        <div class="col-md-6">
                                            <p class="text-muted mb-1">Infraction</p>
                                            <p class="h5"><?= htmlspecialchars($condamnation['infraction_libelle']) ?>
                                            </p>
                                            <span class="badge badge-<?=
                                                                        $condamnation['infraction_categorie'] === 'CRIME' ? 'danger' : ($condamnation['infraction_categorie'] === 'DELIT' ? 'warning' : 'info')
                                                                        ?>">
                                                <?= htmlspecialchars($condamnation['infraction_categorie']) ?>
                                            </span>
                                        </div>
                                    </div>

                                    <?php if ($condamnation['infraction_details']): ?>
                                        <div class="alert alert-info">
                                            <strong>Détails:</strong><br>
                                            <?= nl2br(htmlspecialchars($condamnation['infraction_details'])) ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <p class="text-muted mb-1">Date de l'Infraction</p>
                                            <p><?= $condamnation['date_infraction'] ? date('d/m/Y', strtotime($condamnation['date_infraction'])) : 'N/A' ?>
                                            </p>
                                        </div>
                                        <div class="col-md-6">
                                            <p class="text-muted mb-1">Lieu de l'Infraction</p>
                                            <p><?= htmlspecialchars($condamnation['lieu_infraction'] ?? 'N/A') ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Jugement -->
                            <div class="card mt-3">
                                <div class="card-header bg-success text-white">
                                    <h4 class="card-title mb-0">
                                        <i class="fas fa-gavel me-2"></i>Jugement
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <p class="text-muted mb-1">Date de Jugement</p>
                                            <p class="h5">
                                                <?= date('d/m/Y', strtotime($condamnation['date_jugement'])) ?></p>
                                            <small class="text-muted">
                                                <i class="fas fa-info-circle me-1"></i>
                                                Date de début d'exécution
                                            </small>
                                        </div>
                                        <div class="col-md-6">
                                            <p class="text-muted mb-1">N° Jugement</p>
                                            <p><?= htmlspecialchars($condamnation['numero_jugement'] ?? 'N/A') ?></p>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <p class="text-muted mb-1">Tribunal</p>
                                        <p><?= htmlspecialchars($condamnation['tribunal'] ?? 'N/A') ?></p>
                                    </div>

                                    <div class="calcul-section">
                                        <h5><i class="fas fa-clock me-2"></i>Peine Prononcée</h5>
                                        <p class="h3 text-primary mb-0">
                                            <?= (int)$condamnation['peine_valeur'] ?>
                                            <?= strtolower($condamnation['peine_unite']) ?>(s)
                                        </p>
                                        <small class="text-muted">
                                            Soit <?= $peineJoursTotal ?> jours au total
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <!-- Remises de peine -->
                            <?php if (!empty($remises)): ?>
                                <div class="card mt-3">
                                    <div class="card-header bg-warning">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h4 class="card-title mb-0">
                                                <i class="fas fa-gift me-2"></i>Remises de Peine
                                            </h4>
                                            <span class="badge badge-dark badge-lg">
                                                Total: <?= $totalRemises ?> jours
                                            </span>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="timeline-remise">
                                            <?php foreach ($remises as $remise): ?>
                                                <div class="timeline-item">
                                                    <div class="d-flex justify-content-between">
                                                        <div>
                                                            <h6 class="fw-bold"><?= htmlspecialchars($remise['type']) ?></h6>
                                                            <p class="text-muted mb-1">
                                                                <?= nl2br(htmlspecialchars($remise['motif'])) ?></p>
                                                            <p class="mb-0">
                                                                <small class="text-muted">
                                                                    <?= date('d/m/Y', strtotime($remise['date_decision'])) ?>
                                                                    <?php if ($remise['autorite_decision']): ?>
                                                                        - <?= htmlspecialchars($remise['autorite_decision']) ?>
                                                                    <?php endif; ?>
                                                                </small>
                                                            </p>
                                                        </div>
                                                        <div>
                                                            <span class="badge badge-success badge-lg">
                                                                -<?= (int)$remise['jours_remis'] ?> jours
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Calculs de libération -->
                        <div class="col-lg-4">
                            <!-- Dates clés -->
                            <div class="card">
                                <div class="card-header bg-primary text-white">
                                    <h4 class="card-title mb-0">
                                        <i class="fas fa-calculator me-2"></i>Calculs de Libération
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <div class="mb-4">
                                        <p class="text-muted mb-2">Date Théorique</p>
                                        <p class="h5">
                                            <?= date('d/m/Y', strtotime($condamnation['date_liberation_theorique'])) ?>
                                        </p>
                                        <small class="text-muted">Jugement + Peine totale</small>
                                    </div>

                                    <div class="mb-4">
                                        <p class="text-muted mb-2">Date Effective</p>
                                        <p class="h4 text-success">
                                            <?= date('d/m/Y', strtotime($condamnation['date_liberation_effective'])) ?>
                                        </p>
                                        <small class="text-muted">Théorique - Remises</small>
                                    </div>

                                    <hr>

                                    <div class="info-box mb-3">
                                        <h2><?= max(0, $joursRestants) ?></h2>
                                        <p>Jours Restants</p>
                                    </div>

                                    <div class="calcul-section">
                                        <h6>Formule de calcul:</h6>
                                        <ul class="mb-0">
                                            <li><strong>Peine totale:</strong> <?= $peineJoursTotal ?> jours</li>
                                            <?php if ($totalRemises > 0): ?>
                                                <li><strong>Remises:</strong> -<?= $totalRemises ?> jours</li>
                                            <?php endif; ?>
                                            <li class="text-muted"><strong>⚠️ DP déduite:</strong> 0 jour (directe)</li>
                                            <li class="mt-2"><strong class="text-primary">Total:</strong>
                                                <?= $peineJoursTotal - $totalRemises ?> jours</li>
                                        </ul>
                                    </div>

                                    <?php if (isset($condamnation['progression_pourcent'])): ?>
                                        <div class="mt-3">
                                            <p class="text-muted mb-1">Progression</p>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar bg-primary"
                                                    style="width: <?= min(100, $condamnation['progression_pourcent']) ?>%">
                                                    <?= number_format($condamnation['progression_pourcent'], 1) ?>%
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Lieu de détention -->
                            <?php if ($condamnation['lieu_detention_nom']): ?>
                                <div class="card mt-3">
                                    <div class="card-header bg-info text-white">
                                        <h4 class="card-title mb-0">
                                            <i class="fas fa-map-marker-alt me-2"></i>Détention
                                        </h4>
                                    </div>
                                    <div class="card-body">
                                        <p class="text-muted mb-1">Lieu</p>
                                        <p class="h5"><?= htmlspecialchars($condamnation['lieu_detention_nom']) ?></p>
                                        <?php if ($condamnation['lieu_detention_ville']): ?>
                                            <small class="text-muted">
                                                <i class="fas fa-map-pin me-1"></i>
                                                <?= htmlspecialchars($condamnation['lieu_detention_ville']) ?>
                                            </small>
                                        <?php endif; ?>

                                        <?php if ($condamnation['date_debut_execution']): ?>
                                            <p class="text-muted mb-1 mt-3">Début Exécution</p>
                                            <p><?= date('d/m/Y', strtotime($condamnation['date_debut_execution'])) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Observations -->
                            <?php if ($condamnation['observations']): ?>
                                <div class="card mt-3">
                                    <div class="card-header">
                                        <h4 class="card-title mb-0">
                                            <i class="fas fa-comment me-2"></i>Observations
                                        </h4>
                                    </div>
                                    <div class="card-body">
                                        <?= nl2br(htmlspecialchars($condamnation['observations'])) ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../../requires/script.php'; ?>
</body>

</html>