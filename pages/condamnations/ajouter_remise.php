<?php
require_once '../../includes/db.php';
require_once '../../includes/classes/autoload.php';
require_once '../../includes/auth.php';
require_once '../../includes/csrf.php';
require_once '../../includes/logs.php';

Auth::requireAuth('../../index.php');

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([Auth::id()]);
$user = $stmt->fetch();
$name = $user ? htmlspecialchars($user['nom'] . ' ' . $user['prenom']) : '';

$condamnationMgr = new CondamnationManager($pdo);

// Récupérer la condamnation
if (!isset($_GET['condamnation_id']) || !is_numeric($_GET['condamnation_id'])) {
    header('Location: condamnations.php');
    exit();
}

$condamnationId = (int)$_GET['condamnation_id'];
$condamnation = $condamnationMgr->getById($condamnationId);

if (!$condamnation || $condamnation['statut'] !== 'EN_COURS') {
    header('Location: condamnations.php');
    exit();
}

// Récupérer les remises existantes
$remisesExistantes = $condamnationMgr->getRemises($condamnationId);
$totalRemisesExistantes = array_sum(array_column($remisesExistantes, 'jours_remis'));

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        CSRF::verify();
    } catch (Exception $e) {
        $errors[] = 'Session expirée. Veuillez recharger la page.';
    }

    // Validation
    $required = ['type', 'motif', 'jours_remis', 'date_decision'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            $errors[] = "Le champ " . str_replace('_', ' ', ucfirst($field)) . " est obligatoire.";
        }
    }

    // Vérifier que le nombre de jours est positif
    if (!empty($_POST['jours_remis']) && (int)$_POST['jours_remis'] <= 0) {
        $errors[] = "Le nombre de jours doit être supérieur à 0.";
    }

    // CALCUL SIMPLIFIÉ: Peine totale (sans déduction DP)
    $joursRemis = (int)($_POST['jours_remis'] ?? 0);
    $peineTotal = (int)$condamnation['peine_jours_total'];
    $peineNette = $peineTotal; // Pas de DP à déduire !

    if (($totalRemisesExistantes + $joursRemis) > $peineNette) {
        $errors[] = "Le total des remises (" . ($totalRemisesExistantes + $joursRemis) . " jours) ne peut pas excéder la peine totale ($peineNette jours).";
    }

    // Vérifier date cohérente
    if (!empty($_POST['date_decision']) && !empty($condamnation['date_jugement'])) {
        if (strtotime($_POST['date_decision']) < strtotime($condamnation['date_jugement'])) {
            $errors[] = "La date de décision ne peut pas précéder la date de jugement (" . date('d/m/Y', strtotime($condamnation['date_jugement'])) . ").";
        }
    }

    if (empty($errors)) {
        $data = [
            'type' => $_POST['type'],
            'motif' => trim($_POST['motif']),
            'jours_remis' => $joursRemis,
            'date_decision' => $_POST['date_decision'],
            'reference_decision' => trim($_POST['reference_decision']) ?: null,
            'autorite_decision' => trim($_POST['autorite_decision']) ?: null
        ];

        try {
            $remiseId = $condamnationMgr->addRemise($condamnationId, $data, Auth::id());

            if ($remiseId) {
                log_activity(
                    $pdo,
                    Auth::id(),
                    'Ajout remise de peine',
                    "Condamnation ID: $condamnationId - Remise: $joursRemis jours"
                );

                $success = "✅ Remise de peine ajoutée avec succès ! La date de libération a été recalculée (sans déduction DP).";
                header("refresh:2;url=voir_condamnation.php?id=$condamnationId");
            }
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }
}

// Calculs pour affichage (SIMPLIFIÉS - pas de DP)
$peineJoursTotal = (int)$condamnation['peine_jours_total'];
$peineNette = $peineJoursTotal; // Pas de DP
$remisesDisponibles = $peineNette - $totalRemisesExistantes;
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Ajouter une Remise de Peine - <?= htmlspecialchars($condamnation['numero_dossier']) ?></title>
    <meta content="width=device-width, initial-scale=1.0, shrink-to-fit=no" name="viewport" />
    <?php include '../../requires/link.php'; ?>
    <style>
        .calcul-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .calcul-box h3 {
            font-size: 2rem;
            margin: 0;
        }

        .info-stat {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #28a745;
            margin-bottom: 15px;
        }

        .mode-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 5px 15px;
            border-radius: 5px;
            font-size: 12px;
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
                            <i class="fas fa-gift me-2"></i>Ajouter une Remise de Peine
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
                            <li class="nav-item">
                                <a href="voir_condamnation.php?id=<?= $condamnation['id'] ?>">
                                    <?= htmlspecialchars($condamnation['numero_dossier']) ?>
                                </a>
                            </li>
                            <li class="separator"><i class="fas fa-arrow-right"></i></li>
                            <li class="nav-item">Remise de Peine</li>
                        </ul>
                    </div>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <strong><i class="fas fa-exclamation-circle me-2"></i>Erreurs :</strong>
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <strong><i class="fas fa-check-circle me-2"></i></strong>
                            <?= htmlspecialchars($success) ?>
                            <p class="mb-0 mt-2"><i class="fas fa-spinner fa-spin me-2"></i>Redirection en cours...</p>
                        </div>
                    <?php endif; ?>

                    <div class="row">
                        <!-- Formulaire -->
                        <div class="col-lg-8">
                            <!-- Info condamnation -->
                            <div class="alert alert-info">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h5 class="mb-2">
                                            <i class="fas fa-folder me-2"></i>
                                            <?= htmlspecialchars($condamnation['numero_dossier']) ?>
                                            <span class="mode-badge ms-2">
                                                <i class="fas fa-bolt me-1"></i>DIRECTE
                                            </span>
                                        </h5>
                                        <p class="mb-1">
                                            <strong>Détenu:</strong>
                                            <?= htmlspecialchars($condamnation['detenu_nom']) ?>
                                        </p>
                                        <p class="mb-1">
                                            <strong>Infraction:</strong>
                                            <?= htmlspecialchars($condamnation['infraction_libelle']) ?>
                                        </p>
                                        <p class="mb-0">
                                            <strong>Peine:</strong> <?= (int)$condamnation['peine_valeur'] ?>
                                            <?= strtolower($condamnation['peine_unite']) ?>(s)
                                            (<?= $peineJoursTotal ?> jours)
                                        </p>
                                    </div>
                                    <div class="text-end">
                                        <small class="text-muted d-block">⚠️ Condamnation Directe</small>
                                        <small class="text-muted">0 jour DP déduit</small>
                                    </div>
                                </div>
                            </div>

                            <form method="POST">
                                <?= CSRF::field() ?>
                                <div class="card">
                                    <div class="card-header bg-warning">
                                        <h4 class="card-title mb-0">
                                            <i class="fas fa-gift me-2"></i>Informations de la Remise
                                        </h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">
                                                Type de Remise
                                                <span class="text-danger">*</span>
                                            </label>
                                            <select name="type" class="form-select" required>
                                                <option value="">Sélectionner un type</option>
                                                <option value="REMISE_GRACIEUSE">Remise Gracieuse</option>
                                                <option value="REDUCTION_BONNE_CONDUITE">Réduction pour Bonne Conduite
                                                </option>
                                                <option value="AMNISTIE">Amnistie</option>
                                                <option value="AUTRES">Autres</option>
                                            </select>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">
                                                Motif
                                                <span class="text-danger">*</span>
                                            </label>
                                            <textarea name="motif" class="form-control" rows="3" required
                                                placeholder="Décrivez les raisons de la remise de peine..."></textarea>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">
                                                    Jours Remis
                                                    <span class="text-danger">*</span>
                                                </label>
                                                <input type="number" name="jours_remis" class="form-control" min="1"
                                                    max="<?= $remisesDisponibles ?>" required
                                                    placeholder="Nombre de jours">
                                                <small class="text-muted">
                                                    Maximum disponible: <?= $remisesDisponibles ?> jours
                                                </small>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">
                                                    Date de Décision
                                                    <span class="text-danger">*</span>
                                                </label>
                                                <input type="date" name="date_decision" class="form-control" required
                                                    min="<?= $condamnation['date_jugement'] ?>"
                                                    max="<?= date('Y-m-d') ?>">
                                                <small class="text-muted">
                                                    Min: <?= date('d/m/Y', strtotime($condamnation['date_jugement'])) ?>
                                                </small>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Référence de la Décision</label>
                                            <input type="text" name="reference_decision" class="form-control"
                                                placeholder="Ex: Décret N°2025-001">
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Autorité de Décision</label>
                                            <input type="text" name="autorite_decision" class="form-control"
                                                placeholder="Ex: Président de la République, Tribunal Militaire...">
                                        </div>

                                        <div class="alert alert-warning">
                                            <i class="fas fa-info-circle me-2"></i>
                                            <strong>Important:</strong> La date de libération effective sera
                                            automatiquement recalculée.
                                            <br>
                                            <small class="text-muted">
                                                Formule: Date libération = Date jugement + Peine - Remises (pas de DP
                                                pour condamnation directe)
                                            </small>
                                        </div>
                                    </div>
                                </div>

                                <div class="card mt-3">
                                    <div class="card-body text-end">
                                        <a href="voir_condamnation.php?id=<?= $condamnation['id'] ?>"
                                            class="btn btn-secondary me-2">
                                            <i class="fas fa-times me-2"></i>Annuler
                                        </a>
                                        <button type="submit" class="btn btn-success btn-lg">
                                            <i class="fas fa-save me-2"></i>Ajouter la Remise
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- Statistiques -->
                        <div class="col-lg-4">
                            <div class="calcul-box text-center">
                                <h3><?= $remisesDisponibles ?></h3>
                                <p class="mb-0">Jours Disponibles pour Remise</p>
                            </div>

                            <div class="card">
                                <div class="card-header bg-primary text-white">
                                    <h4 class="card-title mb-0">
                                        <i class="fas fa-calculator me-2"></i>Calculs de Peine
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <div class="info-stat">
                                        <p class="text-muted mb-1">Peine Totale Prononcée</p>
                                        <h5 class="mb-0"><?= $peineJoursTotal ?> jours</h5>
                                    </div>

                                    <!-- PAS de section DP (condamnation directe) -->
                                    <div class="alert alert-info">
                                        <i class="fas fa-bolt me-2"></i>
                                        <strong>Condamnation Directe</strong>
                                        <br>
                                        <small>⚠️ Détention Provisoire: 0 jour (non applicable)</small>
                                    </div>

                                    <div class="info-stat" style="border-color: #28a745;">
                                        <p class="text-muted mb-1">Remises Existantes</p>
                                        <h5 class="mb-0">- <?= $totalRemisesExistantes ?> jours</h5>
                                    </div>

                                    <hr>

                                    <div class="info-stat" style="border-color: #177dff;">
                                        <p class="text-muted mb-1">Peine Nette Restante</p>
                                        <h4 class="mb-0 text-primary"><?= $remisesDisponibles ?> jours</h4>
                                    </div>

                                    <div class="alert alert-secondary mt-3">
                                        <strong>Formule:</strong>
                                        <p class="mb-0 mt-2">
                                            <?= $peineJoursTotal ?> (peine)
                                            <br>- <?= $totalRemisesExistantes ?> (remises)
                                            <br>- 0 (DP)
                                            <br>= <strong><?= $remisesDisponibles ?> jours</strong>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Remises existantes -->
                            <?php if (!empty($remisesExistantes)): ?>
                                <div class="card mt-3">
                                    <div class="card-header bg-warning">
                                        <h4 class="card-title mb-0">
                                            <i class="fas fa-list me-2"></i>Remises Existantes
                                        </h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="list-group">
                                            <?php foreach ($remisesExistantes as $remise): ?>
                                                <div class="list-group-item">
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <div>
                                                            <h6 class="mb-1"><?= htmlspecialchars($remise['type']) ?></h6>
                                                            <small class="text-muted">
                                                                <?= date('d/m/Y', strtotime($remise['date_decision'])) ?>
                                                            </small>
                                                        </div>
                                                        <span class="badge badge-success">
                                                            -<?= (int)$remise['jours_remis'] ?> j
                                                        </span>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Dates actuelles -->
                            <div class="card mt-3">
                                <div class="card-header bg-success text-white">
                                    <h4 class="card-title mb-0">
                                        <i class="fas fa-calendar me-2"></i>Dates Actuelles
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted mb-1">Libération Théorique</p>
                                    <p class="h6 mb-3">
                                        <?= date('d/m/Y', strtotime($condamnation['date_liberation_theorique'])) ?>
                                    </p>

                                    <p class="text-muted mb-1">Libération Effective</p>
                                    <p class="h5 mb-3 text-success">
                                        <?= date('d/m/Y', strtotime($condamnation['date_liberation_effective'])) ?>
                                    </p>

                                    <p class="text-muted mb-1">Jours Restants</p>
                                    <p class="h4 mb-0 text-primary">
                                        <?= max(0, (int)$condamnation['jours_restants']) ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../../requires/script.php'; ?>
    <script>
        $(document).ready(function() {
            // Validation max jours
            $('input[name="jours_remis"]').on('input', function() {
                var joursRemis = parseInt($(this).val()) || 0;
                var maxDisponible = <?= $remisesDisponibles ?>;

                if (joursRemis > maxDisponible) {
                    $(this).val(maxDisponible);
                    alert('Le nombre de jours ne peut pas dépasser ' + maxDisponible + ' jours');
                }
            });
        });
    </script>
</body>

</html>