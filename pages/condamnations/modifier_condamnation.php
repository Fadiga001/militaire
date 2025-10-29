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

$condamnationMgr = new CondamnationManager($pdo);
$refMgr = new ReferenceManager($pdo);

// Récupérer la condamnation
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: condamnations.php');
    exit();
}

$condamnationId = (int)$_GET['id'];
$condamnation = $condamnationMgr->getById($condamnationId);

if (!$condamnation || $condamnation['statut'] !== 'EN_COURS') {
    header('Location: condamnations.php');
    exit();
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validation
    $required = ['numero_dossier', 'infraction_id', 'date_jugement', 'peine_valeur', 'peine_unite'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            $errors[] = "Le champ " . str_replace('_', ' ', ucfirst($field)) . " est obligatoire.";
        }
    }

    // Vérifier si le numéro de dossier existe (excluant la condamnation actuelle)
    if (!empty($_POST['numero_dossier'])) {
        if ($condamnationMgr->numeroDossierExists($_POST['numero_dossier'], $condamnationId)) {
            $errors[] = "Ce numéro de dossier existe déjà.";
        }
    }

    // Validation des dates
    if (!empty($_POST['date_oip']) && !empty($_POST['date_jugement'])) {
        if (strtotime($_POST['date_oip']) > strtotime($_POST['date_jugement'])) {
            $errors[] = "La date OIP ne peut pas être postérieure à la date de jugement.";
        }
    }

    if (empty($errors)) {
        $data = [
            'numero_dossier' => trim($_POST['numero_dossier']),
            'infraction_id' => (int)$_POST['infraction_id'],
            'infraction_details' => trim($_POST['infraction_details']) ?: null,
            'date_infraction' => $_POST['date_infraction'] ?: null,
            'lieu_infraction' => trim($_POST['lieu_infraction']) ?: null,
            'date_oip' => $_POST['date_oip'] ?: null,
            'date_omlp' => $_POST['date_omlp'] ?: null,
            'date_jugement' => $_POST['date_jugement'],
            'numero_jugement' => trim($_POST['numero_jugement']) ?: null,
            'tribunal' => trim($_POST['tribunal']) ?: null,
            'date_mandat_depot' => $_POST['date_mandat_depot'] ?: null,
            'date_liberation_mandat' => $_POST['date_liberation_mandat'] ?: null,
            'peine_valeur' => (int)$_POST['peine_valeur'],
            'peine_unite' => $_POST['peine_unite'],
            'lieu_detention_id' => !empty($_POST['lieu_detention_id']) ? (int)$_POST['lieu_detention_id'] : null,
            'observations' => trim($_POST['observations']) ?: null
        ];

        $updated = $condamnationMgr->update($condamnationId, $data, $_SESSION['user_id']);

        if ($updated) {
            log_activity(
                $pdo,
                $_SESSION['user_id'],
                'Modification condamnation',
                "Condamnation ID: $condamnationId - " . $data['numero_dossier']
            );

            $success = "Condamnation modifiée avec succès ! Les dates ont été recalculées automatiquement.";

            // Recharger les données
            $condamnation = $condamnationMgr->getById($condamnationId);

            header("refresh:2;url=voir_condamnation.php?id=$condamnationId");
        } else {
            $errors[] = "Erreur lors de la modification de la condamnation.";
        }
    }
}

$infractions = $refMgr->getAllInfractions();
$lieux = $refMgr->getAllLieuxDetention();
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Modifier Condamnation - <?= htmlspecialchars($condamnation['numero_dossier']) ?></title>
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
                    <div class="page-header">
                        <h3 class="fw-bold mb-3">
                            <i class="fas fa-edit me-2"></i>Modifier la Condamnation
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
                            <li class="nav-item">Modifier</li>
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
                            <p class="mb-0"><i class="fas fa-spinner fa-spin me-2"></i>Redirection en cours...</p>
                        </div>
                    <?php endif; ?>

                    <!-- Info condamnation -->
                    <div class="alert alert-info">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h5 class="mb-2">
                                    <i class="fas fa-folder me-2"></i>
                                    <?= htmlspecialchars($condamnation['numero_dossier']) ?>
                                </h5>
                                <p class="mb-1">
                                    <strong>Détenu:</strong> <?= htmlspecialchars($condamnation['detenu_nom']) ?>
                                    <span class="mx-2">|</span>
                                    <strong>Matricule:</strong>
                                    <?= htmlspecialchars($condamnation['detenu_matricule']) ?>
                                </p>
                                <p class="mb-0">
                                    <strong>Statut:</strong>
                                    <span class="badge badge-warning">EN COURS</span>
                                </p>
                            </div>
                            <div class="col-md-4 text-end">
                                <p class="mb-1"><strong>Jours restants</strong></p>
                                <h3 class="mb-0"><?= max(0, (int)$condamnation['jours_restants']) ?></h3>
                            </div>
                        </div>
                    </div>

                    <form method="POST">
                        <div class="row">
                            <!-- Informations de base -->
                            <div class="col-lg-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h4 class="card-title">
                                            <i class="fas fa-info-circle me-2"></i>Informations de Base
                                        </h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">N° Dossier <span
                                                    class="text-danger">*</span></label>
                                            <input type="text" name="numero_dossier" class="form-control" required
                                                value="<?= htmlspecialchars($condamnation['numero_dossier']) ?>">
                                        </div>

                                        <div class="alert alert-secondary">
                                            <strong>Détenu:</strong>
                                            <?= htmlspecialchars($condamnation['detenu_nom']) ?>
                                            <br><small class="text-muted">Le détenu ne peut pas être modifié après
                                                création</small>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Infraction <span
                                                    class="text-danger">*</span></label>
                                            <select name="infraction_id" class="form-select" required>
                                                <option value="">Sélectionner une infraction</option>
                                                <?php
                                                $categories = ['CRIME' => 'Crimes', 'DELIT' => 'Délits', 'CONTRAVENTION' => 'Contraventions'];
                                                foreach ($categories as $cat => $label):
                                                ?>
                                                    <optgroup label="<?= $label ?>">
                                                        <?php
                                                        $infractionsCat = array_filter($infractions, fn($i) => $i['categorie'] === $cat);
                                                        foreach ($infractionsCat as $inf):
                                                        ?>
                                                            <option value="<?= $inf['id'] ?>"
                                                                <?= $condamnation['infraction_id'] == $inf['id'] ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($inf['libelle']) ?> (Gravité:
                                                                <?= $inf['gravite'] ?>/10)
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </optgroup>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Détails de l'infraction</label>
                                            <textarea name="infraction_details" class="form-control"
                                                rows="3"><?= htmlspecialchars($condamnation['infraction_details'] ?? '') ?></textarea>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Date de l'Infraction</label>
                                                <input type="date" name="date_infraction" class="form-control"
                                                    value="<?= htmlspecialchars($condamnation['date_infraction'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Lieu de l'Infraction</label>
                                                <input type="text" name="lieu_infraction" class="form-control"
                                                    value="<?= htmlspecialchars($condamnation['lieu_infraction'] ?? '') ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Procédure judiciaire -->
                                <div class="card mt-3">
                                    <div class="card-header">
                                        <h4 class="card-title">
                                            <i class="fas fa-balance-scale me-2"></i>Procédure Judiciaire
                                        </h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Date OIP</label>
                                                <input type="date" name="date_oip" class="form-control"
                                                    value="<?= htmlspecialchars($condamnation['date_oip'] ?? '') ?>">
                                                <small class="text-muted">Ouverture Information Préliminaire</small>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Date OMLP</label>
                                                <input type="date" name="date_omlp" class="form-control"
                                                    value="<?= htmlspecialchars($condamnation['date_omlp'] ?? '') ?>">
                                                <small class="text-muted">Ordonnance Mise en Liberté Provisoire</small>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Date Mandat de Dépôt</label>
                                                <input type="date" name="date_mandat_depot" class="form-control"
                                                    value="<?= htmlspecialchars($condamnation['date_mandat_depot'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Date Libération Mandat</label>
                                                <input type="date" name="date_liberation_mandat" class="form-control"
                                                    value="<?= htmlspecialchars($condamnation['date_liberation_mandat'] ?? '') ?>">
                                            </div>
                                        </div>

                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle me-2"></i>
                                            <strong>Actuel:</strong>
                                            <?= (int)$condamnation['jours_detention_provisoire_total'] ?> jours de DP
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Jugement et Peine -->
                            <div class="col-lg-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h4 class="card-title">
                                            <i class="fas fa-gavel me-2"></i>Jugement
                                        </h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">Date de Jugement <span
                                                    class="text-danger">*</span></label>
                                            <input type="date" name="date_jugement" class="form-control" required
                                                value="<?= htmlspecialchars($condamnation['date_jugement']) ?>">
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">N° Jugement</label>
                                            <input type="text" name="numero_jugement" class="form-control"
                                                value="<?= htmlspecialchars($condamnation['numero_jugement'] ?? '') ?>">
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Tribunal</label>
                                            <input type="text" name="tribunal" class="form-control"
                                                value="<?= htmlspecialchars($condamnation['tribunal'] ?? '') ?>">
                                        </div>

                                        <hr>

                                        <h5 class="mb-3">
                                            <i class="fas fa-clock me-2"></i>Peine Prononcée
                                        </h5>

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Durée <span
                                                        class="text-danger">*</span></label>
                                                <input type="number" name="peine_valeur" class="form-control" min="1"
                                                    required value="<?= (int)$condamnation['peine_valeur'] ?>">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Unité <span
                                                        class="text-danger">*</span></label>
                                                <select name="peine_unite" class="form-select" required>
                                                    <option value="JOUR"
                                                        <?= $condamnation['peine_unite'] === 'JOUR' ? 'selected' : '' ?>>
                                                        Jour(s)</option>
                                                    <option value="MOIS"
                                                        <?= $condamnation['peine_unite'] === 'MOIS' ? 'selected' : '' ?>>
                                                        Mois</option>
                                                    <option value="ANNEE"
                                                        <?= $condamnation['peine_unite'] === 'ANNEE' ? 'selected' : '' ?>>
                                                        Année(s)</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="alert alert-warning">
                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                            <strong>Attention:</strong> Modifier la peine recalculera automatiquement
                                            les dates de libération
                                        </div>

                                        <div class="alert alert-success">
                                            <strong>Dates actuelles:</strong>
                                            <ul class="mb-0 mt-2">
                                                <li>Théorique:
                                                    <?= date('d/m/Y', strtotime($condamnation['date_liberation_theorique'])) ?>
                                                </li>
                                                <li>Effective:
                                                    <?= date('d/m/Y', strtotime($condamnation['date_liberation_effective'])) ?>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>

                                <!-- Exécution -->
                                <div class="card mt-3">
                                    <div class="card-header">
                                        <h4 class="card-title">
                                            <i class="fas fa-map-marker-alt me-2"></i>Exécution de la Peine
                                        </h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">Lieu de Détention</label>
                                            <select name="lieu_detention_id" class="form-select">
                                                <option value="">Sélectionner un lieu</option>
                                                <?php foreach ($lieux as $lieu): ?>
                                                    <option value="<?= $lieu['id'] ?>"
                                                        <?= $condamnation['lieu_detention_id'] == $lieu['id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($lieu['nom']) ?> (<?= $lieu['type'] ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <?php if ($condamnation['lieu_detention_nom']): ?>
                                                <small class="text-muted">
                                                    Actuel: <?= htmlspecialchars($condamnation['lieu_detention_nom']) ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Observations</label>
                                            <textarea name="observations" class="form-control"
                                                rows="3"><?= htmlspecialchars($condamnation['observations'] ?? '') ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <small class="text-muted">
                                                    <i class="fas fa-info-circle me-2"></i>
                                                    Les dates de libération seront recalculées automatiquement
                                                </small>
                                            </div>
                                            <div>
                                                <a href="voir_condamnation.php?id=<?= $condamnation['id'] ?>"
                                                    class="btn btn-secondary me-2">
                                                    <i class="fas fa-times me-2"></i>Annuler
                                                </a>
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="fas fa-save me-2"></i>Enregistrer les Modifications
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php include '../../requires/script.php'; ?>
</body>

</html>