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
$detenuMgr = new DetenuManager($pdo);
$refMgr = new ReferenceManager($pdo);

$errors = [];
$success = '';

// Détenu pré-sélectionné ?
$detenuId = isset($_GET['detenu_id']) ? (int)$_GET['detenu_id'] : null;
$detenu = null;
if ($detenuId) {
    $detenu = $detenuMgr->getById($detenuId);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        CSRF::verify();
    } catch (Exception $e) {
        $errors[] = 'Session expirée. Veuillez recharger la page.';
    }

    // Validation des champs requis
    $required = [
        'numero_dossier',
        'detenu_id',
        'infraction_id',
        'date_jugement',
        'peine_valeur',
        'peine_unite',
        'lieu_detention_id' // OBLIGATOIRE en mode direct
    ];

    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            $errors[] = "Le champ " . str_replace('_', ' ', ucfirst($field)) . " est obligatoire.";
        }
    }

    // Vérifier que le détenu n'a PAS de DP en cours
    if (!empty($_POST['detenu_id'])) {
        $checkDP = $pdo->prepare("
            SELECT COUNT(*) as nb 
            FROM detentions_provisoires 
            WHERE detenu_id = ? AND statut = 'EN_COURS'
        ");
        $checkDP->execute([$_POST['detenu_id']]);
        if ($checkDP->fetch()['nb'] > 0) {
            $errors[] = "⚠️ Ce détenu a une détention provisoire en cours. Veuillez utiliser le module 'Conversion DP → Condamnation' à la place.";
        }
    }

    // Vérifier unicité numéro dossier
    if (!empty($_POST['numero_dossier'])) {
        if ($condamnationMgr->numeroDossierExists($_POST['numero_dossier'])) {
            $errors[] = "Ce numéro de dossier existe déjà.";
        }
    }

    // Validation cohérence dates
    if (!empty($_POST['date_infraction']) && !empty($_POST['date_jugement'])) {
        if (strtotime($_POST['date_infraction']) > strtotime($_POST['date_jugement'])) {
            $errors[] = "La date de l'infraction ne peut pas être postérieure à la date de jugement.";
        }
    }

    if (empty($errors)) {
        $data = [
            'numero_dossier' => trim($_POST['numero_dossier']),
            'detenu_id' => (int)$_POST['detenu_id'],
            'infraction_id' => (int)$_POST['infraction_id'],
            'infraction_details' => trim($_POST['infraction_details']) ?: null,
            'date_infraction' => $_POST['date_infraction'] ?: null,
            'lieu_infraction' => trim($_POST['lieu_infraction']) ?: null,
            'date_jugement' => $_POST['date_jugement'],
            'numero_jugement' => trim($_POST['numero_jugement']) ?: null,
            'tribunal' => trim($_POST['tribunal']) ?: null,
            'peine_valeur' => (int)$_POST['peine_valeur'],
            'peine_unite' => $_POST['peine_unite'],
            'lieu_detention_id' => (int)$_POST['lieu_detention_id'],
            'observations' => trim($_POST['observations']) ?: 'CONDAMNATION DIRECTE - Sans détention provisoire préalable',
            'is_principale' => isset($_POST['is_principale']) ? true : false
        ];

        try {
            $condamnationId = $condamnationMgr->create($data, Auth::id());

            if ($condamnationId) {
                log_activity($pdo, Auth::id(), 'Ajout condamnation directe', "Nouvelle condamnation ID: $condamnationId - Dossier: {$data['numero_dossier']}");
                $success = "✅ Condamnation directe ajoutée avec succès ! Les dates de libération ont été calculées automatiquement (sans déduction de DP).";
                header("refresh:3;url=voir_condamnation.php?id=$condamnationId");
            }
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }
}

// Récupérer UNIQUEMENT les détenus avec statut_actuel NULL
// (nouveaux détenus jamais condamnés, jamais en DP)
$detenus = $detenuMgr->getAll([
    'statut_null' => true, // Nouveau filtre
    'sans_dp_en_cours' => true // Double sécurité
]);

$infractions = $refMgr->getAllInfractions();
$lieux = $refMgr->getAllLieuxDetention();
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Ajouter une Condamnation Directe - Système Militaire</title>
    <meta content="width=device-width, initial-scale=1.0, shrink-to-fit=no" name="viewport" />
    <?php include '../../requires/link.php'; ?>
    <style>
        .mode-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .required-badge {
            background: #dc3545;
            color: white;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 3px;
            margin-left: 5px;
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
                            <i class="fas fa-gavel me-2"></i>Ajouter une Condamnation Directe
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
                            <li class="nav-item">Ajouter</li>
                        </ul>
                    </div>

                    <!-- Info mode DIRECTE -->
                    <div class="mode-info">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h4 class="mb-2">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Mode: CONDAMNATION DIRECTE
                                </h4>
                                <p class="mb-0">
                                    Ce module gère uniquement les condamnations <strong>sans détention provisoire
                                        préalable</strong>.
                                    Le détenu doit être jugé et condamné directement, sans passer par une phase de
                                    détention provisoire.
                                </p>
                            </div>
                            <div class="col-md-4 text-end">
                                <div class="badge badge-light badge-lg">
                                    <i class="fas fa-clock me-2"></i>
                                    0 jour déduit
                                </div>
                            </div>
                        </div>
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

                    <form method="POST">
                        <?= CSRF::field() ?>

                        <div class="row">
                            <!-- Informations de base -->
                            <div class="col-lg-6">
                                <div class="card">
                                    <div class="card-header bg-primary text-white">
                                        <h4 class="card-title mb-0">
                                            <i class="fas fa-info-circle me-2"></i>Informations de Base
                                        </h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">
                                                N° Dossier
                                                <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" name="numero_dossier" class="form-control"
                                                placeholder="Ex: DOS-2025-001" required
                                                value="<?= htmlspecialchars($_POST['numero_dossier'] ?? '') ?>">
                                            <small class="text-muted">Format recommandé: DOS-ANNÉE-XXX</small>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">
                                                Détenu
                                                <span class="text-danger">*</span>
                                            </label>
                                            <select name="detenu_id" class="form-select" required id="detenu-select">
                                                <option value="">Sélectionner un détenu</option>
                                                <?php foreach ($detenus as $d): ?>
                                                    <option value="<?= $d['id'] ?>"
                                                        data-matricule="<?= htmlspecialchars($d['matricule']) ?>"
                                                        data-grade="<?= htmlspecialchars($d['grade_code'] ?? '') ?>"
                                                        data-statut="<?= htmlspecialchars($d['statut_actuel']) ?>"
                                                        <?= ($detenu && $detenu['id'] == $d['id']) || ($_POST['detenu_id'] ?? '') == $d['id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($d['nom_complet']) ?> -
                                                        <?= htmlspecialchars($d['matricule']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <small class="text-muted">
                                                <i class="fas fa-exclamation-triangle text-warning me-1"></i>
                                                Seuls les détenus <strong>sans DP en cours</strong> sont affichés
                                            </small>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">
                                                Infraction
                                                <span class="text-danger">*</span>
                                            </label>
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
                                                                <?= ($_POST['infraction_id'] ?? '') == $inf['id'] ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($inf['libelle']) ?>
                                                                (Gravité: <?= $inf['gravite'] ?>/10)
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </optgroup>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Détails de l'infraction</label>
                                            <textarea name="infraction_details" class="form-control" rows="3"
                                                placeholder="Circonstances, détails complémentaires..."><?= htmlspecialchars($_POST['infraction_details'] ?? '') ?></textarea>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Date de l'Infraction</label>
                                                <input type="date" name="date_infraction" class="form-control"
                                                    max="<?= date('Y-m-d') ?>"
                                                    value="<?= htmlspecialchars($_POST['date_infraction'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Lieu de l'Infraction</label>
                                                <input type="text" name="lieu_infraction" class="form-control"
                                                    placeholder="Ex: Abidjan, Bouaké..."
                                                    value="<?= htmlspecialchars($_POST['lieu_infraction'] ?? '') ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Jugement et Peine -->
                            <div class="col-lg-6">
                                <div class="card">
                                    <div class="card-header bg-success text-white">
                                        <h4 class="card-title mb-0">
                                            <i class="fas fa-gavel me-2"></i>Jugement
                                        </h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">
                                                Date de Jugement
                                                <span class="text-danger">*</span>
                                            </label>
                                            <input type="date" name="date_jugement" class="form-control" required
                                                max="<?= date('Y-m-d') ?>"
                                                value="<?= htmlspecialchars($_POST['date_jugement'] ?? '') ?>">
                                            <small class="text-muted">
                                                Cette date sera aussi la date de début d'exécution
                                            </small>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">N° Jugement</label>
                                            <input type="text" name="numero_jugement" class="form-control"
                                                placeholder="Ex: JUG-2025-001"
                                                value="<?= htmlspecialchars($_POST['numero_jugement'] ?? '') ?>">
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Tribunal</label>
                                            <input type="text" name="tribunal" class="form-control"
                                                placeholder="Ex: Tribunal Militaire d'Abidjan"
                                                value="<?= htmlspecialchars($_POST['tribunal'] ?? '') ?>">
                                        </div>

                                        <hr>

                                        <h5 class="mb-3">
                                            <i class="fas fa-clock me-2"></i>Peine Prononcée
                                        </h5>

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">
                                                    Durée
                                                    <span class="text-danger">*</span>
                                                </label>
                                                <input type="number" name="peine_valeur" class="form-control" min="1"
                                                    required
                                                    value="<?= htmlspecialchars($_POST['peine_valeur'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">
                                                    Unité
                                                    <span class="text-danger">*</span>
                                                </label>
                                                <select name="peine_unite" class="form-select" required>
                                                    <option value="JOUR"
                                                        <?= ($_POST['peine_unite'] ?? '') === 'JOUR' ? 'selected' : '' ?>>
                                                        Jour(s)
                                                    </option>
                                                    <option value="MOIS"
                                                        <?= ($_POST['peine_unite'] ?? '') === 'MOIS' ? 'selected' : '' ?>>
                                                        Mois
                                                    </option>
                                                    <option value="ANNEE"
                                                        <?= ($_POST['peine_unite'] ?? 'ANNEE') === 'ANNEE' ? 'selected' : '' ?>>
                                                        Année(s)
                                                    </option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="alert alert-warning">
                                            <i class="fas fa-info-circle me-2"></i>
                                            <strong>Condamnation directe:</strong>
                                            <ul class="mb-0 mt-2">
                                                <li>Aucune détention provisoire préalable</li>
                                                <li>0 jour déduit de la peine</li>
                                                <li>Date de libération = date jugement + peine totale</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Exécution -->
                        <div class="row mt-3">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header bg-info text-white">
                                        <h4 class="card-title mb-0">
                                            <i class="fas fa-map-marker-alt me-2"></i>Exécution de la Peine
                                        </h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">
                                                    Lieu de Détention
                                                    <span class="text-danger">*</span>
                                                    <span class="required-badge">OBLIGATOIRE</span>
                                                </label>
                                                <select name="lieu_detention_id" class="form-select" required>
                                                    <option value="">Sélectionner un lieu</option>
                                                    <?php foreach ($lieux as $lieu): ?>
                                                        <option value="<?= $lieu['id'] ?>"
                                                            <?= ($_POST['lieu_detention_id'] ?? '') == $lieu['id'] ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($lieu['nom']) ?>
                                                            (<?= htmlspecialchars($lieu['type']) ?>) -
                                                            <?= htmlspecialchars($lieu['ville'] ?? '') ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <small class="text-muted">
                                                    Le lieu de détention est obligatoire pour une condamnation directe
                                                </small>
                                            </div>

                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Date Début Exécution (calculée)</label>
                                                <input type="text" class="form-control"
                                                    value="Automatique = Date de jugement" disabled>
                                                <small class="text-muted">
                                                    La date de début d'exécution sera automatiquement la date du
                                                    jugement
                                                </small>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Observations</label>
                                            <textarea name="observations" class="form-control" rows="3"
                                                placeholder="Notes complémentaires sur la condamnation..."><?= htmlspecialchars($_POST['observations'] ?? '') ?></textarea>
                                            <small class="text-muted">
                                                Si laissé vide, la mention "CONDAMNATION DIRECTE - Sans détention
                                                provisoire préalable" sera ajoutée automatiquement
                                            </small>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="is_principale"
                                                value="1" id="principale" checked>
                                            <label class="form-check-label" for="principale">
                                                Condamnation principale
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <small class="text-muted">
                                                    <i class="fas fa-info-circle me-2"></i>
                                                    Les calculs de dates seront effectués automatiquement (sans
                                                    déduction DP)
                                                </small>
                                            </div>
                                            <div>
                                                <a href="condamnations.php" class="btn btn-secondary me-2">
                                                    <i class="fas fa-times me-2"></i>Annuler
                                                </a>
                                                <button type="submit" class="btn btn-primary btn-lg">
                                                    <i class="fas fa-save me-2"></i>Enregistrer la Condamnation Directe
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
    <script>
        $(document).ready(function() {
            // Afficher info détenu sélectionné
            $('#detenu-select').change(function() {
                var option = $(this).find('option:selected');
                var matricule = option.data('matricule');
                var grade = option.data('grade');
                var statut = option.data('statut');

                if (matricule) {
                    console.log('Détenu sélectionné:', {
                        matricule: matricule,
                        grade: grade,
                        statut: statut
                    });
                }
            });

            // Validation formulaire
            $('form').submit(function(e) {
                var peineValeur = parseInt($('input[name="peine_valeur"]').val());
                if (peineValeur < 1) {
                    e.preventDefault();
                    alert('La durée de la peine doit être supérieure à 0');
                    return false;
                }

                var lieuDetention = $('select[name="lieu_detention_id"]').val();
                if (!lieuDetention) {
                    e.preventDefault();
                    alert('Le lieu de détention est obligatoire pour une condamnation directe');
                    return false;
                }

                return true;
            });
        });
    </script>
</body>

</html>