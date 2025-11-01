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

// Récupérer l'ID
$detentionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$detention = $dpMgr->getById($detentionId);

if (!$detention || $detention['statut'] !== 'EN_COURS') {
    $_SESSION['error_message'] = "Détention provisoire non trouvée ou déjà terminée.";
    header('Location: detentions_provisoires.php');
    exit();
}

$errors = [];
$success = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        CSRF::verify();

        $validated = Validator::make($_POST)
            ->required('motif', 'Le motif est requis')
            ->min('motif', 10, 'Le motif doit contenir au moins 10 caractères')
            ->validate();

        if ($dpMgr->liberer($detentionId, $validated['motif'], $_SESSION['user_id'])) {
            $_SESSION['success_message'] = "Détenu libéré avec succès.";
            header('Location: detentions_provisoires.php');
            exit();
        } else {
            $errors['general'] = "Erreur lors de la libération.";
        }
    } catch (ValidationException $e) {
        $errors = json_decode($e->getMessage(), true);
    } catch (Exception $e) {
        $errors['general'] = "Une erreur s'est produite: " . $e->getMessage();
        error_log($e->getMessage());
    }
}

// Récupérer les détails complets
$stmtDetails = $pdo->prepare("
    SELECT dp.*, d.*, i.libelle as infraction, g.libelle as grade, u.nom as unite
    FROM detentions_provisoires dp
    INNER JOIN detenus d ON dp.detenu_id = d.id
    LEFT JOIN infractions i ON dp.infraction_presume_id = i.id
    LEFT JOIN grades g ON d.grade_id = g.id
    LEFT JOIN unites u ON d.unite_id = u.id
    WHERE dp.id = :id
");
$stmtDetails->execute([':id' => $detentionId]);
$details = $stmtDetails->fetch();

// Motifs prédéfinis
$motifsPredefinis = [
    'Fin de la durée maximale légale de détention provisoire',
    'Non-lieu prononcé par le juge d\'instruction',
    'Relaxe prononcée',
    'Ordonnance de mise en liberté provisoire (OMLP)',
    'Décision de classement sans suite',
    'Insuffisance de charges',
    'Autre motif (préciser ci-dessous)'
];
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Libération Détention Provisoire - Système Militaire</title>
    <meta content="width=device-width, initial-scale=1.0, shrink-to-fit=no" name="viewport" />
    <?php include '../../requires/link.php'; ?>
    <style>
    .warning-box {
        background: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%);
        color: white;
        padding: 30px;
        border-radius: 15px;
        margin-bottom: 30px;
    }

    .info-table td {
        padding: 10px;
        border-bottom: 1px solid #eee;
    }

    .info-table td:first-child {
        font-weight: bold;
        width: 40%;
    }

    .motif-option {
        cursor: pointer;
        padding: 15px;
        border: 2px solid #e9ecef;
        border-radius: 8px;
        margin-bottom: 10px;
        transition: all 0.3s;
    }

    .motif-option:hover {
        border-color: #177dff;
        background-color: #f8f9fa;
    }

    .motif-option.selected {
        border-color: #177dff;
        background-color: #e7f3ff;
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
                            <i class="fas fa-door-open me-2"></i>Libération de Détention Provisoire
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
                            <li class="nav-item">Libération</li>
                        </ul>
                    </div>

                    <?php if (isset($errors['general'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?= htmlspecialchars($errors['general']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <?php if ($detention['jours_restants'] < 0): ?>
                    <div class="warning-box">
                        <h3><i class="fas fa-exclamation-triangle me-2"></i>LIBÉRATION OBLIGATOIRE</h3>
                        <p class="mb-0">
                            Cette détention provisoire a dépassé la durée maximale légale de
                            <strong><?= abs($detention['jours_restants']) ?> jours</strong>.
                            <br>La libération est obligatoire selon la loi.
                        </p>
                    </div>
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header bg-danger text-white">
                                    <h4 class="card-title mb-0">
                                        <i class="fas fa-user-times me-2"></i>Formulaire de Libération
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <!-- Informations du détenu -->
                                    <div class="alert alert-info">
                                        <h5>Détenu à libérer</h5>
                                        <table class="info-table w-100">
                                            <tr>
                                                <td>Nom complet:</td>
                                                <td><?= htmlspecialchars($detention['detenu']) ?></td>
                                            </tr>
                                            <tr>
                                                <td>Matricule:</td>
                                                <td><?= htmlspecialchars($detention['matricule']) ?></td>
                                            </tr>
                                            <tr>
                                                <td>Grade:</td>
                                                <td><?= htmlspecialchars($details['grade']) ?></td>
                                            </tr>
                                            <tr>
                                                <td>Unité:</td>
                                                <td><?= htmlspecialchars($details['unite']) ?></td>
                                            </tr>
                                            <tr>
                                                <td>N° Dossier:</td>
                                                <td><?= htmlspecialchars($detention['numero_dossier']) ?></td>
                                            </tr>
                                            <tr>
                                                <td>Infraction:</td>
                                                <td><?= htmlspecialchars($details['infraction']) ?></td>
                                            </tr>
                                            <tr>
                                                <td>Durée détention:</td>
                                                <td><strong><?= $detention['jours_detention_actuel'] ?> jours</strong>
                                                    (<?= $detention['mois_detention_actuel'] ?> mois)</td>
                                            </tr>
                                            <?php if ($detention['jours_restants'] < 0): ?>
                                            <tr class="table-danger">
                                                <td>Dépassement:</td>
                                                <td><strong class="text-danger"><?= abs($detention['jours_restants']) ?>
                                                        jours</strong></td>
                                            </tr>
                                            <?php endif; ?>
                                        </table>
                                    </div>

                                    <form method="POST" action="" id="liberationForm">
                                        <?= CSRF::field() ?>

                                        <!-- Sélection du motif -->
                                        <div class="form-group mb-4">
                                            <label class="form-label fw-bold">
                                                <i class="fas fa-clipboard-list me-2"></i>Motif de Libération <span
                                                    class="text-danger">*</span>
                                            </label>
                                            <p class="text-muted small">Sélectionnez un motif prédéfini ou saisissez un
                                                motif personnalisé</p>

                                            <div id="motifsContainer">
                                                <?php foreach ($motifsPredefinis as $index => $motif): ?>
                                                <div class="motif-option" data-motif="<?= htmlspecialchars($motif) ?>">
                                                    <i class="fas fa-circle me-2"></i>
                                                    <?= htmlspecialchars($motif) ?>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>

                                        <!-- Zone de texte pour le motif -->
                                        <div class="form-group mb-4">
                                            <label for="motif" class="form-label fw-bold">
                                                Motif Détaillé <span class="text-danger">*</span>
                                            </label>
                                            <textarea
                                                class="form-control <?= isset($errors['motif']) ? 'is-invalid' : '' ?>"
                                                id="motif" name="motif" rows="5" required
                                                placeholder="Décrivez le motif de la libération..."><?= htmlspecialchars($_POST['motif'] ?? ($detention['jours_restants'] < 0 ? 'Fin de la durée maximale légale de détention provisoire' : '')) ?></textarea>
                                            <?php if (isset($errors['motif'])): ?>
                                            <div class="invalid-feedback">
                                                <?= htmlspecialchars($errors['motif'][0] ?? $errors['motif']) ?>
                                            </div>
                                            <?php endif; ?>
                                            <small class="form-text text-muted">
                                                Minimum 10 caractères. Soyez précis et mentionnez les références légales
                                                si applicable.
                                            </small>
                                        </div>

                                        <!-- Confirmation -->
                                        <div class="form-group mb-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="confirmation"
                                                    required>
                                                <label class="form-check-label fw-bold" for="confirmation">
                                                    Je confirme que je souhaite libérer ce détenu de la détention
                                                    provisoire
                                                </label>
                                            </div>
                                        </div>

                                        <div class="alert alert-warning">
                                            <h6><i class="fas fa-info-circle me-2"></i>Conséquences de la libération
                                            </h6>
                                            <ul class="mb-0">
                                                <li>Le détenu sera libéré immédiatement</li>
                                                <li>Son statut passera à "LIBRE"</li>
                                                <li>La période de détention sera clôturée</li>
                                                <li>Cette action est <strong>irréversible</strong></li>
                                            </ul>
                                        </div>

                                        <!-- Boutons -->
                                        <div class="form-group mt-4">
                                            <button type="submit" class="btn btn-danger btn-lg" id="btnLiberer">
                                                <i class="fas fa-door-open me-2"></i>Confirmer la Libération
                                            </button>
                                            <a href="details_detention_provisoire.php?id=<?= $detentionId ?>"
                                                class="btn btn-secondary btn-lg">
                                                <i class="fas fa-times me-2"></i>Annuler
                                            </a>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Colonne droite: Aide -->
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header bg-info text-white">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-question-circle me-2"></i>Information
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <h6 class="fw-bold">Cas de Libération</h6>
                                    <ul class="small">
                                        <li>Fin de la durée max légale</li>
                                        <li>Non-lieu</li>
                                        <li>Relaxe</li>
                                        <li>OMLP (Ordonnance de Mise en Liberté Provisoire)</li>
                                        <li>Classement sans suite</li>
                                        <li>Insuffisance de charges</li>
                                    </ul>

                                    <h6 class="fw-bold mt-3">Durées Maximales</h6>
                                    <ul class="small">
                                        <li><strong>Crime:</strong> 24 mois</li>
                                        <li><strong>Délit:</strong> 18 mois</li>
                                        <li><strong>Contravention:</strong> 6 mois</li>
                                    </ul>

                                    <div class="alert alert-danger mt-3">
                                        <small>
                                            <i class="fas fa-exclamation-triangle me-1"></i>
                                            Au-delà de ces durées, la libération est <strong>obligatoire</strong> selon
                                            la loi.
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <div class="card mt-3">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-clock me-2"></i>Chronologie
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="small">Début:</span>
                                        <strong
                                            class="small"><?= date('d/m/Y', strtotime($detention['date_debut_detention'])) ?></strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="small">Limite:</span>
                                        <strong
                                            class="small text-<?= $detention['jours_restants'] < 0 ? 'danger' : 'primary' ?>">
                                            <?= date('d/m/Y', strtotime($detention['date_limite_legale'])) ?>
                                        </strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="small">Durée écoulée:</span>
                                        <strong class="small"><?= $detention['jours_detention_actuel'] ?> jours</strong>
                                    </div>
                                    <?php if ($detention['jours_restants'] >= 0): ?>
                                    <div class="d-flex justify-content-between">
                                        <span class="small">Jours restants:</span>
                                        <strong class="small text-info"><?= $detention['jours_restants'] ?>
                                            jours</strong>
                                    </div>
                                    <?php else: ?>
                                    <div class="d-flex justify-content-between">
                                        <span class="small">Dépassement:</span>
                                        <strong class="small text-danger"><?= abs($detention['jours_restants']) ?>
                                            jours</strong>
                                    </div>
                                    <?php endif; ?>
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
        // Gestion de la sélection des motifs prédéfinis
        $('.motif-option').on('click', function() {
            $('.motif-option').removeClass('selected');
            $(this).addClass('selected');

            const motif = $(this).data('motif');
            $('#motif').val(motif);
        });

        // Validation du formulaire
        $('#liberationForm').on('submit', function(e) {
            const motif = $('#motif').val().trim();
            const confirmation = $('#confirmation').is(':checked');

            if (motif.length < 10) {
                e.preventDefault();
                alert('Le motif doit contenir au moins 10 caractères.');
                return false;
            }

            if (!confirmation) {
                e.preventDefault();
                alert('Veuillez confirmer la libération.');
                return false;
            }

            // Confirmation finale
            const detenu = "<?= htmlspecialchars($detention['detenu']) ?>";
            if (!confirm(
                    `ATTENTION\n\nVous allez libérer ${detenu}.\n\nCette action est irréversible.\n\nConfirmez-vous ?`
                )) {
                e.preventDefault();
                return false;
            }

            // Désactiver le bouton pour éviter double soumission
            $('#btnLiberer').prop('disabled', true).html(
                '<i class="fas fa-spinner fa-spin me-2"></i>Libération en cours...');
        });
    });
    </script>
</body>

</html>