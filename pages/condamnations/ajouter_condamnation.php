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
$mode = $_POST['mode'] ?? 'CONVERSION'; // CONVERSION (par défaut) ou DIRECTE

// Détenu pré-sélectionné ?
$detenuId = isset($_GET['detenu_id']) ? (int)$_GET['detenu_id'] : null;
$detenu = null;
if ($detenuId) {
    $detenu = $detenuMgr->getById($detenuId);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try { CSRF::verify(); } catch (Exception $e) { $errors[] = 'Session expirée. Veuillez recharger la page.'; }
    // Validation
    $required = ['numero_dossier', 'detenu_id', 'infraction_id', 'date_jugement', 'peine_valeur', 'peine_unite'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            $errors[] = "Le champ " . str_replace('_', ' ', ucfirst($field)) . " est obligatoire.";
        }
    }

    // Spécifique au mode DIRECTE: lieu obligatoire, pas d'OIP/mandat requis
    if (($_POST['mode'] ?? '') === 'DIRECTE') {
        if (empty($_POST['lieu_detention_id'])) {
            $errors[] = "Le lieu de détention est obligatoire en condamnation directe.";
        }
    }

    // Vérifier si le numéro de dossier existe
    if (!empty($_POST['numero_dossier'])) {
        if ($condamnationMgr->numeroDossierExists($_POST['numero_dossier'])) {
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
            'detenu_id' => (int)$_POST['detenu_id'],
            'infraction_id' => (int)$_POST['infraction_id'],
            'infraction_details' => trim($_POST['infraction_details']) ?: null,
            'date_infraction' => $_POST['date_infraction'] ?: null,
            'lieu_infraction' => trim($_POST['lieu_infraction']) ?: null,
            'date_oip' => ($_POST['mode'] ?? '') === 'DIRECTE' ? null : ($_POST['date_oip'] ?: null),
            'date_omlp' => ($_POST['mode'] ?? '') === 'DIRECTE' ? null : ($_POST['date_omlp'] ?: null),
            'date_jugement' => $_POST['date_jugement'],
            'numero_jugement' => trim($_POST['numero_jugement']) ?: null,
            'tribunal' => trim($_POST['tribunal']) ?: null,
            'date_mandat_depot' => ($_POST['mode'] ?? '') === 'DIRECTE' ? null : ($_POST['date_mandat_depot'] ?: null),
            'date_liberation_mandat' => ($_POST['mode'] ?? '') === 'DIRECTE' ? null : ($_POST['date_liberation_mandat'] ?: null),
            'peine_valeur' => (int)$_POST['peine_valeur'],
            'peine_unite' => $_POST['peine_unite'],
            'lieu_detention_id' => !empty($_POST['lieu_detention_id']) ? (int)$_POST['lieu_detention_id'] : null,
            'date_debut_execution' => (($_POST['mode'] ?? '') === 'DIRECTE')
                ? ($_POST['date_jugement'] ?: null)
                : ($_POST['date_debut_execution'] ?: null),
            'observations' => trim($_POST['observations'])
                ?: (($_POST['mode'] ?? '') === 'DIRECTE' ? 'CONDAMNATION DIRECTE (sans detention provisoire prealable)' : null),
            'statut' => 'EN_COURS',
            'is_principale' => isset($_POST['is_principale']) ? true : false
        ];

        $condamnationId = $condamnationMgr->create($data, Auth::id());

        if ($condamnationId) {
            log_activity($pdo, Auth::id(), 'Ajout condamnation', "Nouvelle condamnation ID: $condamnationId");
            $success = "Condamnation ajoutée avec succès ! Les dates de libération ont été calculées automatiquement.";
            header("refresh:2;url=voir_condamnation.php?id=$condamnationId");
        } else {
            $errors[] = "Erreur lors de l'ajout de la condamnation.";
        }
    }
}

// Données pour les selects
$detenus = ($mode === 'DIRECTE')
    ? $detenuMgr->getAll([])
    : $detenuMgr->getAll(['statut' => 'DETENTION_PROVISOIRE']);
$infractions = $refMgr->getAllInfractions();
$lieux = $refMgr->getAllLieuxDetention();
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Ajouter une Condamnation - Système Militaire</title>
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
                            <i class="fas fa-gavel me-2"></i>Ajouter une Condamnation
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
                        <?= CSRF::field() ?>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Mode de création</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="mode" id="mode_conversion" value="CONVERSION" <?= $mode !== 'DIRECTE' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="mode_conversion">Conversion DP → Condamnation (avec déduction)</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="mode" id="mode_directe" value="DIRECTE" <?= $mode === 'DIRECTE' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="mode_directe">Condamnation directe (sans DP, 0 jour déduit)</label>
                            </div>
                            <small class="text-muted">Changer le mode peut modifier les champs requis.</small>
                        </div>
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
                                            <input type="text" name="numero_dossier" class="form-control"
                                                placeholder="Ex: DOS-2025-001" required
                                                value="<?= htmlspecialchars($_POST['numero_dossier'] ?? '') ?>">
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Détenu <span class="text-danger">*</span></label>
                                            <select name="detenu_id" class="form-select" required id="detenu-select">
                                                <option value="">Sélectionner un détenu</option>
                                                <?php foreach ($detenus as $d): ?>
                                                <option value="<?= $d['id'] ?>"
                                                    data-matricule="<?= htmlspecialchars($d['matricule']) ?>"
                                                    data-grade="<?= htmlspecialchars($d['grade_code']) ?>"
                                                    <?= ($detenu && $detenu['id'] == $d['id']) || ($_POST['detenu_id'] ?? '') == $d['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($d['nom_complet']) ?> -
                                                    <?= htmlspecialchars($d['matricule']) ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <small class="text-muted">
                                                <?= $mode === 'DIRECTE' ? 'Tous les détenus actifs' : 'Seuls les détenus en détention provisoire' ?>
                                                sont affichés
                                            </small>
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
                                                        <?= ($_POST['infraction_id'] ?? '') == $inf['id'] ? 'selected' : '' ?>>
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
                                            <textarea name="infraction_details" class="form-control" rows="3"
                                                placeholder="Circonstances, détails complémentaires..."><?= htmlspecialchars($_POST['infraction_details'] ?? '') ?></textarea>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Date de l'Infraction</label>
                                                <input type="date" name="date_infraction" class="form-control"
                                                    value="<?= htmlspecialchars($_POST['date_infraction'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Lieu de l'Infraction</label>
                                                <input type="text" name="lieu_infraction" class="form-control"
                                                    value="<?= htmlspecialchars($_POST['lieu_infraction'] ?? '') ?>">
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
                                        <div class="row dp-only">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Date OIP</label>
                                                <input type="date" name="date_oip" class="form-control"
                                                    value="<?= htmlspecialchars($_POST['date_oip'] ?? '') ?>">
                                                <small class="text-muted">Ouverture Information Préliminaire</small>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Date OMLP</label>
                                                <input type="date" name="date_omlp" class="form-control"
                                                    value="<?= htmlspecialchars($_POST['date_omlp'] ?? '') ?>">
                                                <small class="text-muted">Ordonnance Mise en Liberté Provisoire</small>
                                            </div>
                                        </div>

                                        <div class="row dp-only">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Date Mandat de Dépôt</label>
                                                <input type="date" name="date_mandat_depot" class="form-control"
                                                    value="<?= htmlspecialchars($_POST['date_mandat_depot'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Date Libération Mandat</label>
                                                <input type="date" name="date_liberation_mandat" class="form-control"
                                                    value="<?= htmlspecialchars($_POST['date_liberation_mandat'] ?? '') ?>">
                                            </div>
                                        </div>

                                        <div class="alert alert-info dp-only">
                                            <i class="fas fa-info-circle me-2"></i>
                                            Les jours de détention provisoire seront automatiquement déduits de la peine
                                        </div>
                                        <div class="alert alert-warning direct-only" style="display:none;">
                                            <i class="fas fa-info-circle me-2"></i>
                                            Condamnation directe: pas de détention provisoire, 0 jour déduit. La date de début d'exécution sera celle du jugement.
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
                                            <label class="form-label">Date de Jugement</label>
                                            <input type="date" name="date_jugement" class="form-control"
                                                value="<?= htmlspecialchars($_POST['date_jugement'] ?? '') ?>">
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">N° Jugement</label>
                                            <input type="text" name="numero_jugement" class="form-control"
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
                                                <label class="form-label">Durée</label>
                                                <input type="number" name="peine_valeur" class="form-control" min="1"
                                                    value="<?= htmlspecialchars($_POST['peine_valeur'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Unité</label>
                                                <select name="peine_unite" class="form-select">
                                                    <option value="JOUR"
                                                        <?= ($_POST['peine_unite'] ?? '') === 'JOUR' ? 'selected' : '' ?>>
                                                        Jour(s)</option>
                                                    <option value="MOIS"
                                                        <?= ($_POST['peine_unite'] ?? '') === 'MOIS' ? 'selected' : '' ?>>
                                                        Mois</option>
                                                    <option value="ANNEE"
                                                        <?= ($_POST['peine_unite'] ?? 'ANNEE') === 'ANNEE' ? 'selected' : '' ?>>
                                                        Année(s)</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="alert alert-success">
                                            <i class="fas fa-calculator me-2"></i>
                                            <strong>Calcul automatique :</strong>
                                            <ul class="mb-0 mt-2">
                                                <li>Date de libération théorique</li>
                                                <li>Date de libération effective (avec déduction DP)</li>
                                                <li>Jours de détention provisoire</li>
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
                                                    <?= ($_POST['lieu_detention_id'] ?? '') == $lieu['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($lieu['nom']) ?> (<?= $lieu['type'] ?>)
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="mb-3 direct-only" style="display:none;">
                                            <label class="form-label">Date Début Exécution (auto)</label>
                                            <input type="date" class="form-control" value="<?= htmlspecialchars($_POST['date_jugement'] ?? '') ?>" disabled>
                                            <small class="text-muted">En condamnation directe, la date de début = date du jugement.</small>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Observations</label>
                                            <textarea name="observations" class="form-control" rows="3"
                                                placeholder="Notes complémentaires..."><?= htmlspecialchars($_POST['observations'] ?? '') ?></textarea>
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

                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-body text-end">
                                        <a href="condamnations.php" class="btn btn-secondary me-2">
                                            <i class="fas fa-times me-2"></i>Annuler
                                        </a>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>Enregistrer la Condamnation
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
    $(document).ready(function() {
        function syncModeUI() {
            const mode = $('input[name="mode"]:checked').val();
            if (mode === 'DIRECTE') {
                $('.dp-only').hide();
                $('.direct-only').show();
            } else {
                $('.dp-only').show();
                $('.direct-only').hide();
            }
        }

        $('input[name="mode"]').on('change', function() {
            syncModeUI();
            // Optionnel: soumettre pour rafraîchir la liste des détenus
            // $(this).closest('form').submit();
        });

        syncModeUI();
        // Info détenu sélectionné
        $('#detenu-select').change(function() {
            var option = $(this).find('option:selected');
            var matricule = option.data('matricule');
            var grade = option.data('grade');

            if (matricule) {
                console.log('Détenu sélectionné:', matricule, grade);
            }
        });
    });
    </script>
</body>

</html>