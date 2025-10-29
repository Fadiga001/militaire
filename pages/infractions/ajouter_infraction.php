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

$refMgr = new ReferenceManager($pdo);

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validation
    $required = ['code', 'libelle', 'categorie', 'gravite'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            $errors[] = "Le champ " . ucfirst($field) . " est obligatoire.";
        }
    }

    // Vérifier si le code existe déjà
    if (!empty($_POST['code'])) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as nb FROM infractions WHERE code = ?");
        $stmt->execute([$_POST['code']]);
        if ((int)$stmt->fetch()['nb'] > 0) {
            $errors[] = "Ce code d'infraction existe déjà.";
        }
    }

    // Valider la gravité
    if (!empty($_POST['gravite'])) {
        $gravite = (int)$_POST['gravite'];
        if ($gravite < 1 || $gravite > 10) {
            $errors[] = "La gravité doit être comprise entre 1 et 10.";
        }
    }

    if (empty($errors)) {
        $data = [
            'code' => strtoupper(trim($_POST['code'])),
            'libelle' => trim($_POST['libelle']),
            'categorie' => $_POST['categorie'],
            'gravite' => (int)$_POST['gravite'],
            'duree_detention_provisoire_mois' => !empty($_POST['duree_dp']) ? (int)$_POST['duree_dp'] : null,
            'description' => trim($_POST['description']) ?: null
        ];

        $infractionId = $refMgr->createInfraction($data);
        
        if ($infractionId) {
            log_activity($pdo, $_SESSION['user_id'], 'Ajout infraction', "Nouvelle infraction: " . $data['libelle']);
            $success = "Infraction ajoutée avec succès !";
            header("refresh:2;url=infractions.php");
        } else {
            $errors[] = "Erreur lors de l'ajout de l'infraction.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Ajouter une Infraction - Système Militaire</title>
    <meta content="width=device-width, initial-scale=1.0, shrink-to-fit=no" name="viewport" />
    <?php include '../../requires/link.php'; ?>
    <style>
    .gravite-preview {
        display: inline-block;
        width: 40px;
        height: 40px;
        line-height: 40px;
        text-align: center;
        border-radius: 50%;
        font-weight: bold;
        color: white;
        font-size: 18px;
    }

    .gravite-1,
    .gravite-2,
    .gravite-3 {
        background: #28a745;
    }

    .gravite-4,
    .gravite-5,
    .gravite-6 {
        background: #ffc107;
        color: #000;
    }

    .gravite-7,
    .gravite-8 {
        background: #fd7e14;
    }

    .gravite-9,
    .gravite-10 {
        background: #dc3545;
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
                            <i class="fas fa-plus me-2"></i>Ajouter une Infraction
                        </h3>
                        <ul class="breadcrumbs mb-3">
                            <li class="nav-home">
                                <a href="../dash/dashboard.php"><i class="fas fa-home"></i></a>
                            </li>
                            <li class="separator"><i class="fas fa-arrow-right"></i></li>
                            <li class="nav-item">
                                <a href="infractions.php">Infractions</a>
                            </li>
                            <li class="separator"><i class="fas fa-arrow-right"></i></li>
                            <li class="nav-item">Ajouter</li>
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

                    <form method="POST">
                        <div class="row">
                            <div class="col-lg-8">
                                <div class="card">
                                    <div class="card-header">
                                        <h4 class="card-title">
                                            <i class="fas fa-info-circle me-2"></i>Informations de l'Infraction
                                        </h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Code <span
                                                        class="text-danger">*</span></label>
                                                <input type="text" name="code" class="form-control"
                                                    placeholder="Ex: DESERTION, VOL, INSUBORDINATION" required
                                                    style="text-transform: uppercase;"
                                                    value="<?= htmlspecialchars($_POST['code'] ?? '') ?>">
                                                <small class="text-muted">Code unique en majuscules</small>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Catégorie <span
                                                        class="text-danger">*</span></label>
                                                <select name="categorie" class="form-select" required>
                                                    <option value="">Sélectionner une catégorie</option>
                                                    <option value="CRIME"
                                                        <?= ($_POST['categorie'] ?? '') === 'CRIME' ? 'selected' : '' ?>>
                                                        Crime
                                                    </option>
                                                    <option value="DELIT"
                                                        <?= ($_POST['categorie'] ?? '') === 'DELIT' ? 'selected' : '' ?>>
                                                        Délit
                                                    </option>
                                                    <option value="CONTRAVENTION"
                                                        <?= ($_POST['categorie'] ?? '') === 'CONTRAVENTION' ? 'selected' : '' ?>>
                                                        Contravention
                                                    </option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Libellé <span class="text-danger">*</span></label>
                                            <input type="text" name="libelle" class="form-control"
                                                placeholder="Ex: Désertion en temps de paix" required
                                                value="<?= htmlspecialchars($_POST['libelle'] ?? '') ?>">
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">
                                                    Niveau de Gravité <span class="text-danger">*</span>
                                                    <span id="gravite-preview" class="ms-2"></span>
                                                </label>
                                                <input type="range" name="gravite" id="gravite-slider"
                                                    class="form-range" min="1" max="10" value="5" required>
                                                <div class="d-flex justify-content-between">
                                                    <small class="text-muted">1 (Faible)</small>
                                                    <small class="text-muted">10 (Très grave)</small>
                                                </div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Durée Max Détention Provisoire</label>
                                                <div class="input-group">
                                                    <input type="number" name="duree_dp" class="form-control" min="0"
                                                        placeholder="Ex: 6"
                                                        value="<?= htmlspecialchars($_POST['duree_dp'] ?? '') ?>">
                                                    <span class="input-group-text">mois</span>
                                                </div>
                                                <small class="text-muted">Optionnel</small>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Description / Détails</label>
                                            <textarea name="description" class="form-control" rows="4"
                                                placeholder="Description détaillée de l'infraction, circonstances aggravantes, etc."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-4">
                                <!-- Aide catégories -->
                                <div class="card">
                                    <div class="card-header bg-primary text-white">
                                        <h4 class="card-title mb-0">
                                            <i class="fas fa-info-circle me-2"></i>Guide des Catégories
                                        </h4>
                                    </div>
                                    <div class="card-body">
                                        <h6 class="text-danger">
                                            <i class="fas fa-exclamation-triangle me-2"></i>Crime
                                        </h6>
                                        <p class="small mb-3">
                                            Infractions les plus graves : meurtre, trahison, mutinerie, désertion en
                                            temps de guerre, etc.
                                        </p>

                                        <h6 class="text-warning">
                                            <i class="fas fa-gavel me-2"></i>Délit
                                        </h6>
                                        <p class="small mb-3">
                                            Infractions de gravité moyenne : vol, coups et blessures, insubordination,
                                            etc.
                                        </p>

                                        <h6 class="text-info">
                                            <i class="fas fa-file-alt me-2"></i>Contravention
                                        </h6>
                                        <p class="small mb-0">
                                            Infractions mineures : absence irrégulière, retard, manquement à la tenue,
                                            etc.
                                        </p>
                                    </div>
                                </div>

                                <!-- Aide gravité -->
                                <div class="card mt-3">
                                    <div class="card-header bg-info text-white">
                                        <h4 class="card-title mb-0">
                                            <i class="fas fa-thermometer-half me-2"></i>Échelle de Gravité
                                        </h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-2">
                                            <span class="gravite-preview" style="background: #28a745;">1-3</span>
                                            <span class="ms-2">Faible</span>
                                        </div>
                                        <div class="mb-2">
                                            <span class="gravite-preview"
                                                style="background: #ffc107; color: #000;">4-6</span>
                                            <span class="ms-2">Moyenne</span>
                                        </div>
                                        <div class="mb-2">
                                            <span class="gravite-preview" style="background: #fd7e14;">7-8</span>
                                            <span class="ms-2">Élevée</span>
                                        </div>
                                        <div class="mb-0">
                                            <span class="gravite-preview" style="background: #dc3545;">9-10</span>
                                            <span class="ms-2">Très grave</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Exemples -->
                                <div class="card mt-3">
                                    <div class="card-header bg-success text-white">
                                        <h4 class="card-title mb-0">
                                            <i class="fas fa-lightbulb me-2"></i>Exemples
                                        </h4>
                                    </div>
                                    <div class="card-body">
                                        <ul class="list-unstyled mb-0">
                                            <li class="mb-2">
                                                <strong>Crime (9/10) :</strong><br>
                                                <small>Désertion en temps de guerre</small>
                                            </li>
                                            <li class="mb-2">
                                                <strong>Délit (6/10) :</strong><br>
                                                <small>Insubordination envers un supérieur</small>
                                            </li>
                                            <li class="mb-0">
                                                <strong>Contravention (3/10) :</strong><br>
                                                <small>Absence irrégulière de moins de 24h</small>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-body text-end">
                                        <a href="infractions.php" class="btn btn-secondary me-2">
                                            <i class="fas fa-times me-2"></i>Annuler
                                        </a>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>Enregistrer l'Infraction
                                        </button>
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
    <script>
    // Prévisualisation gravité
    const slider = document.getElementById('gravite-slider');
    const preview = document.getElementById('gravite-preview');

    function updateGravitePreview(value) {
        preview.textContent = value;
        preview.className = 'gravite-preview gravite-' + value;
    }

    slider.addEventListener('input', function() {
        updateGravitePreview(this.value);
    });

    // Initialisation
    updateGravitePreview(slider.value);

    // Convertir code en majuscules automatiquement
    document.querySelector('input[name="code"]').addEventListener('input', function() {
        this.value = this.value.toUpperCase().replace(/[^A-Z0-9_]/g, '');
    });
    </script>
</body>

</html>