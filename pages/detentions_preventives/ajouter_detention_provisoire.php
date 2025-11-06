<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit();
}

require_once '../../includes/db.php';
require_once '../../includes/classes/autoload.php';
require_once '../../includes/csrf.php';
require_once '../../includes/validator.php';

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
$name = $user ? htmlspecialchars($user['nom'] . ' ' . $user['prenom']) : '';
$role = $user['role'] ?? '';

$dpMgr = new DetentionProvisoireManager($pdo);
$detenuMgr = new DetenuManager($pdo);
$refMgr = new ReferenceManager($pdo);

$errors = [];
$success = '';

// Récupérer le détenu si ID fourni
$detenuId = isset($_GET['detenu_id']) ? (int)$_GET['detenu_id'] : 0;
$detenu = $detenuId ? $detenuMgr->getById($detenuId) : null;

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        CSRF::verify();

        $validated = Validator::make($_POST)
            ->required('numero_dossier', 'Le numéro de dossier est requis')
            ->required('detenu_id', 'Le détenu est requis')
            ->required('infraction_presume_id', 'L\'infraction est requise')
            ->required('date_arrestation', 'La date d\'arrestation est requise')
            ->date('date_arrestation')
            ->required('lieu_detention_id', 'Le lieu de détention est requis')
            ->validate();

        // Vérifier si le numéro de dossier existe déjà
        if ($dpMgr->numeroDossierExists($validated['numero_dossier'])) {
            $errors['numero_dossier'] = "Ce numéro de dossier existe déjà.";
        }

        // Vérifier si le détenu n'est pas déjà en détention provisoire
        $detentionsActuelles = $dpMgr->getByDetenu($validated['detenu_id']);
        $aDetentionEnCours = false;
        foreach ($detentionsActuelles as $dt) {
            if ($dt['statut'] === 'EN_COURS') {
                $aDetentionEnCours = true;
                break;
            }
        }

        if ($aDetentionEnCours) {
            $errors['general'] = "Ce détenu est déjà en détention provisoire.";
        }

        if (empty($errors)) {
            $detentionId = $dpMgr->create([
                'numero_dossier' => $validated['numero_dossier'],
                'detenu_id' => $validated['detenu_id'],
                'infraction_presume_id' => $validated['infraction_presume_id'],
                'infraction_details' => $_POST['infraction_details'] ?? null,
                'date_faits' => $_POST['date_faits'] ?? null,
                'lieu_faits' => $_POST['lieu_faits'] ?? null,
                'date_arrestation' => $validated['date_arrestation'],
                'date_oip' => $_POST['date_oip'] ?? null,
                'date_mandat_depot' => $_POST['date_mandat_depot'] ?? null,
                'numero_mandat' => $_POST['numero_mandat'] ?? null,
                'autorite_mandante' => $_POST['autorite_mandante'] ?? null,
                'motif_detention' => $_POST['motif_detention'] ?? null,
                'lieu_detention_id' => $validated['lieu_detention_id'],
                'observations' => $_POST['observations'] ?? null
            ], $_SESSION['user_id']);

            if ($detentionId) {
                $_SESSION['success_message'] = "Détention provisoire enregistrée avec succès.";
                header('Location: detentions_provisoires.php');
                exit();
            } else {
                $errors['general'] = "Erreur lors de l'enregistrement.";
            }
        }
    } catch (ValidationException $e) {
        $errors = json_decode($e->getMessage(), true);
    } catch (Exception $e) {
        $errors['general'] = "Une erreur s'est produite.";
        error_log($e->getMessage());
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
    <title>Nouvelle Détention Provisoire - Système Militaire</title>
    <meta content="width=device-width, initial-scale=1.0, shrink-to-fit=no" name="viewport" />
    <?php include '../../requires/link.php'; ?>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        .info-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .duree-info {
            display: none;
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
        }

        .duree-crime {
            background-color: #ffe5e5;
            border-left: 4px solid #dc3545;
        }

        .duree-delit {
            background-color: #fff8e1;
            border-left: 4px solid #ffc107;
        }

        .duree-contravention {
            background-color: #e3f2fd;
            border-left: 4px solid #2196f3;
        }

        .select2-container--default .select2-selection--single {
            height: 45px;
            padding: 8px;
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
                            <i class="fas fa-plus-circle me-2"></i>Nouvelle Détention Provisoire
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
                            <li class="nav-item">Ajouter</li>
                        </ul>
                    </div>

                    <?php if (isset($errors['general'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <?= htmlspecialchars($errors['general']) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-md-8">
                            <div class="info-box">
                                <h5><i class="fas fa-info-circle me-2"></i>Durées Maximales Légales</h5>
                                <ul class="mb-0">
                                    <li><strong>Crime :</strong> 24 mois maximum</li>
                                    <li><strong>Délit :</strong> 18 mois maximum</li>
                                    <li><strong>Contravention :</strong> 6 mois maximum</li>
                                </ul>
                            </div>

                            <div class="card">
                                <div class="card-header">
                                    <h4 class="card-title">Informations de la Détention Provisoire</h4>
                                </div>
                                <div class="card-body">
                                    <form method="POST" action="" id="detentionForm">
                                        <?= CSRF::field() ?>

                                        <!-- Numéro de dossier -->
                                        <div class="form-group mb-3">
                                            <label for="numero_dossier" class="form-label">
                                                Numéro de Dossier <span class="text-danger">*</span>
                                            </label>
                                            <input type="text"
                                                class="form-control <?= isset($errors['numero_dossier']) ? 'is-invalid' : '' ?>"
                                                id="numero_dossier" name="numero_dossier"
                                                value="<?= htmlspecialchars($_POST['numero_dossier'] ?? 'DP-' . date('Y') . '-') ?>"
                                                required>
                                            <?php if (isset($errors['numero_dossier'])): ?>
                                                <div class="invalid-feedback">
                                                    <?= htmlspecialchars($errors['numero_dossier'][0] ?? $errors['numero_dossier']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Détenu -->
                                        <div class="form-group mb-3">
                                            <label for="detenu_id" class="form-label">
                                                Détenu <span class="text-danger">*</span>
                                            </label>
                                            <select
                                                class="form-select select2 <?= isset($errors['detenu_id']) ? 'is-invalid' : '' ?>"
                                                id="detenu_id" name="detenu_id" required>
                                                <option value="">-- Sélectionner un détenu --</option>
                                                <?php foreach ($detenus as $d): ?>
                                                    <option value="<?= $d['id'] ?>"
                                                        <?= ($detenuId == $d['id'] || ($_POST['detenu_id'] ?? '') == $d['id']) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($d['matricule'] . ' - ' . $d['nom_complet']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <?php if (isset($errors['detenu_id'])): ?>
                                                <div class="invalid-feedback">
                                                    <?= htmlspecialchars($errors['detenu_id'][0] ?? $errors['detenu_id']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <hr class="my-4">

                                        <!-- Infraction présumée -->
                                        <div class="form-group mb-3">
                                            <label for="infraction_presume_id" class="form-label">
                                                Infraction Présumée <span class="text-danger">*</span>
                                            </label>
                                            <select
                                                class="form-select select2 <?= isset($errors['infraction_presume_id']) ? 'is-invalid' : '' ?>"
                                                id="infraction_presume_id" name="infraction_presume_id" required>
                                                <option value="">-- Sélectionner une infraction --</option>
                                                <?php foreach ($infractions as $infraction): ?>
                                                    <option value="<?= $infraction['id'] ?>"
                                                        data-categorie="<?= $infraction['categorie'] ?>"
                                                        <?= ($_POST['infraction_presume_id'] ?? '') == $infraction['id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($infraction['libelle']) ?>
                                                        (<?= $infraction['categorie'] ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <?php if (isset($errors['infraction_presume_id'])): ?>
                                                <div class="invalid-feedback">
                                                    <?= htmlspecialchars($errors['infraction_presume_id'][0] ?? $errors['infraction_presume_id']) ?>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Affichage durée max -->
                                            <div id="duree_info" class="duree-info"></div>
                                        </div>

                                        <!-- Détails infraction -->
                                        <div class="form-group mb-3">
                                            <label for="infraction_details" class="form-label">
                                                Détails des Faits Reprochés
                                            </label>
                                            <textarea class="form-control" id="infraction_details"
                                                name="infraction_details"
                                                rows="3"><?= htmlspecialchars($_POST['infraction_details'] ?? '') ?></textarea>
                                        </div>

                                        <!-- Date et lieu des faits -->
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group mb-3">
                                                    <label for="date_faits" class="form-label">Date des Faits</label>
                                                    <input type="date" class="form-control" id="date_faits"
                                                        name="date_faits"
                                                        value="<?= htmlspecialchars($_POST['date_faits'] ?? '') ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group mb-3">
                                                    <label for="lieu_faits" class="form-label">Lieu des Faits</label>
                                                    <input type="text" class="form-control" id="lieu_faits"
                                                        name="lieu_faits"
                                                        value="<?= htmlspecialchars($_POST['lieu_faits'] ?? '') ?>">
                                                </div>
                                            </div>
                                        </div>

                                        <hr class="my-4">

                                        <!-- Dates procédurales -->
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group mb-3">
                                                    <label for="date_arrestation" class="form-label">
                                                        Date d'Arrestation <span class="text-danger">*</span>
                                                    </label>
                                                    <input type="date" class="form-control" id="date_arrestation"
                                                        name="date_arrestation"
                                                        value="<?= htmlspecialchars($_POST['date_arrestation'] ?? date('Y-m-d')) ?>"
                                                        required>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group mb-3">
                                                    <label for="date_oip" class="form-label">
                                                        Date OIP (Ouv. Info. Prél.)
                                                    </label>
                                                    <input type="date" class="form-control" id="date_oip"
                                                        name="date_oip"
                                                        value="<?= htmlspecialchars($_POST['date_oip'] ?? '') ?>">
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Mandat de dépôt -->
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group mb-3">
                                                    <label for="date_mandat_depot" class="form-label">
                                                        Date Mandat de Dépôt
                                                    </label>
                                                    <input type="date" class="form-control" id="date_mandat_depot"
                                                        name="date_mandat_depot"
                                                        value="<?= htmlspecialchars($_POST['date_mandat_depot'] ?? '') ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group mb-3">
                                                    <label for="numero_mandat" class="form-label">
                                                        Numéro du Mandat
                                                    </label>
                                                    <input type="text" class="form-control" id="numero_mandat"
                                                        name="numero_mandat"
                                                        value="<?= htmlspecialchars($_POST['numero_mandat'] ?? '') ?>">
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Autorité -->
                                        <div class="form-group mb-3">
                                            <label for="autorite_mandante" class="form-label">
                                                Autorité Mandante
                                            </label>
                                            <input type="text" class="form-control" id="autorite_mandante"
                                                name="autorite_mandante"
                                                placeholder="Ex: Juge d'instruction, Procureur..."
                                                value="<?= htmlspecialchars($_POST['autorite_mandante'] ?? '') ?>">
                                        </div>

                                        <!-- Motif -->
                                        <div class="form-group mb-3">
                                            <label for="motif_detention" class="form-label">
                                                Motif de la Détention
                                            </label>
                                            <textarea class="form-control" id="motif_detention" name="motif_detention"
                                                rows="2"><?= htmlspecialchars($_POST['motif_detention'] ?? '') ?></textarea>
                                        </div>

                                        <hr class="my-4">

                                        <!-- Lieu de détention -->
                                        <div class="form-group mb-3">
                                            <label for="lieu_detention_id" class="form-label">
                                                Lieu de Détention <span class="text-danger">*</span>
                                            </label>
                                            <select
                                                class="form-select <?= isset($errors['lieu_detention_id']) ? 'is-invalid' : '' ?>"
                                                id="lieu_detention_id" name="lieu_detention_id" required>
                                                <option value="">-- Sélectionner un lieu --</option>
                                                <?php foreach ($lieux as $lieu): ?>
                                                    <option value="<?= $lieu['id'] ?>"
                                                        <?= ($_POST['lieu_detention_id'] ?? '') == $lieu['id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($lieu['nom']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <?php if (isset($errors['lieu_detention_id'])): ?>
                                                <div class="invalid-feedback">
                                                    <?= htmlspecialchars($errors['lieu_detention_id'][0] ?? $errors['lieu_detention_id']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Observations -->
                                        <div class="form-group mb-3">
                                            <label for="observations" class="form-label">Observations</label>
                                            <textarea class="form-control" id="observations" name="observations"
                                                rows="3"><?= htmlspecialchars($_POST['observations'] ?? '') ?></textarea>
                                        </div>

                                        <!-- Boutons -->
                                        <div class="form-group mt-4">
                                            <button type="submit" class="btn btn-primary btn-lg">
                                                <i class="fas fa-save me-2"></i>Enregistrer la Détention Provisoire
                                            </button>
                                            <a href="detentions_provisoires.php" class="btn btn-secondary btn-lg">
                                                <i class="fas fa-times me-2"></i>Annuler
                                            </a>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Colonne droite: Info -->
                        <div class="col-md-4">
                            <?php if ($detenu): ?>
                                <div class="card">
                                    <div class="card-header bg-primary text-white">
                                        <h5 class="card-title mb-0">
                                            <i class="fas fa-user me-2"></i>Détenu Sélectionné
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <h6><?= htmlspecialchars($detenu['nom_complet']) ?></h6>
                                        <p class="mb-2">
                                            <strong>Matricule:</strong> <?= htmlspecialchars($detenu['matricule']) ?><br>
                                            <strong>Grade:</strong> <?= htmlspecialchars($detenu['grade_libelle']) ?><br>
                                            <strong>Unité:</strong> <?= htmlspecialchars($detenu['unite_nom']) ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-info-circle me-2"></i>Aide
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <h6 class="fw-bold">Détention Provisoire</h6>
                                    <p class="small">
                                        La détention provisoire est une mesure exceptionnelle applicable avant jugement.
                                        Elle a des limites légales strictes.
                                    </p>

                                    <h6 class="fw-bold mt-3">Documents Requis</h6>
                                    <ul class="small">
                                        <li>Mandat de dépôt</li>
                                        <li>Procès-verbal d'arrestation</li>
                                        <li>Ordonnance d'OIP (si applicable)</li>
                                    </ul>

                                    <div class="alert alert-warning mt-3">
                                        <small>
                                            <i class="fas fa-exclamation-triangle me-1"></i>
                                            Le système calculera automatiquement la date limite selon la catégorie
                                            d'infraction.
                                        </small>
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
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialiser Select2
            $('.select2').select2({
                width: '100%'
            });

            // Afficher la durée max selon l'infraction
            $('#infraction_presume_id').on('change', function() {
                const selectedOption = $(this).find('option:selected');
                const categorie = selectedOption.data('categorie');
                const dureeInfo = $('#duree_info');

                if (categorie) {
                    let duree, classe, texte;

                    switch (categorie) {
                        case 'CRIME':
                            duree = 24;
                            classe = 'duree-crime';
                            texte = '24 mois (2 ans)';
                            break;
                        case 'DELIT':
                            duree = 18;
                            classe = 'duree-delit';
                            texte = '18 mois (1 an et demi)';
                            break;
                        default:
                            duree = 6;
                            classe = 'duree-contravention';
                            texte = '6 mois';
                    }

                    dureeInfo.removeClass('duree-crime duree-delit duree-contravention')
                        .addClass(classe)
                        .html(
                            `<strong><i class="fas fa-clock me-2"></i>Durée maximale de détention provisoire : ${texte}</strong>`
                        )
                        .slideDown();
                } else {
                    dureeInfo.slideUp();
                }
            });

            // Déclencher l'affichage si déjà sélectionné
            if ($('#infraction_presume_id').val()) {
                $('#infraction_presume_id').trigger('change');
            }

            // Validation du formulaire
            $('#detentionForm').on('submit', function(e) {
                const detenuId = $('#detenu_id').val();
                const infractionId = $('#infraction_presume_id').val();
                const dateArrestation = $('#date_arrestation').val();
                const lieuId = $('#lieu_detention_id').val();

                if (!detenuId || !infractionId || !dateArrestation || !lieuId) {
                    e.preventDefault();
                    alert('Veuillez remplir tous les champs obligatoires.');
                    return false;
                }
            });
        });
    </script>
</body>

</html>