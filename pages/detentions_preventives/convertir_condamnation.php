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
$detentionId = isset($_GET['detention_id']) ? (int)$_GET['detention_id'] : 0;
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
            ->required('date_jugement', 'La date de jugement est requise')
            ->date('date_jugement')
            ->required('peine_valeur', 'La durée de la peine est requise')
            ->integer('peine_valeur')
            ->required('peine_unite', 'L\'unité de la peine est requise')
            ->in('peine_unite', ['JOUR', 'MOIS', 'ANNEE'])
            ->validate();

        // Vérifier que la date de jugement n'est pas antérieure au début de la détention
        if (strtotime($validated['date_jugement']) < strtotime($detention['date_debut_detention'])) {
            $errors['date_jugement'] = "La date de jugement ne peut pas être antérieure au début de la détention.";
        }

        if (empty($errors)) {
            $condamnationId = $dpMgr->convertirEnCondamnation($detentionId, [
                'date_jugement' => $validated['date_jugement'],
                'numero_jugement' => $_POST['numero_jugement'] ?? null,
                'tribunal' => $_POST['tribunal'] ?? null,
                'peine_valeur' => (int)$validated['peine_valeur'],
                'peine_unite' => $validated['peine_unite']
            ], $_SESSION['user_id']);

            if ($condamnationId) {
                $_SESSION['success_message'] = "Condamnation créée avec succès. Le détenu passe en statut CONDAMNÉ.";
                header("Location: ../condamnations/voir_condamnation.php?id=$condamnationId");
                exit();
            } else {
                $errors['general'] = "Erreur lors de la conversion.";
            }
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
    SELECT dp.*, d.*, i.libelle as infraction, i.categorie, g.libelle as grade, u.nom as unite, l.nom as lieu
    FROM detentions_provisoires dp
    INNER JOIN detenus d ON dp.detenu_id = d.id
    LEFT JOIN infractions i ON dp.infraction_presume_id = i.id
    LEFT JOIN grades g ON d.grade_id = g.id
    LEFT JOIN unites u ON d.unite_id = u.id
    LEFT JOIN lieux_detention l ON dp.lieu_detention_id = l.id
    WHERE dp.id = :id
");
$stmtDetails->execute([':id' => $detentionId]);
$details = $stmtDetails->fetch();
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Convertir en Condamnation - Système Militaire</title>
    <meta content="width=device-width, initial-scale=1.0, shrink-to-fit=no" name="viewport" />
    <?php include '../../requires/link.php'; ?>
    <style>
    .success-box {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 30px;
        border-radius: 15px;
        margin-bottom: 30px;
    }

    .info-badge {
        display: inline-block;
        padding: 8px 15px;
        border-radius: 20px;
        background: #e3f2fd;
        color: #1976d2;
        font-weight: bold;
        margin: 5px;
    }

    .deduction-info {
        background: #fff3cd;
        border-left: 4px solid #ffc107;
        padding: 15px;
        border-radius: 5px;
        margin: 15px 0;
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
                            <i class="fas fa-gavel me-2"></i>Conversion en Condamnation
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
                            <li class="nav-item">Conversion</li>
                        </ul>
                    </div>

                    <?php if (isset($errors['general'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?= htmlspecialchars($errors['general']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <div class="success-box">
                        <h4><i class="fas fa-check-circle me-2"></i>Passage de Détention Provisoire à Condamnation</h4>
                        <p class="mb-0">
                            Suite au jugement, le détenu va passer du statut <strong>DÉTENTION PROVISOIRE</strong> au
                            statut <strong>CONDAMNÉ</strong>.
                            <br>Les jours de détention provisoire seront automatiquement déduits de la peine prononcée.
                        </p>
                    </div>

                    <div class="row">
                        <div class="col-md-8">
                            <!-- Informations du détenu -->
                            <div class="card mb-3">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-user me-2"></i>Détenu Concerné
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6><?= htmlspecialchars($detention['detenu']) ?></h6>
                                            <p class="mb-2">
                                                <strong>Matricule:</strong>
                                                <?= htmlspecialchars($detention['matricule']) ?><br>
                                                <strong>Grade:</strong> <?= htmlspecialchars($details['grade']) ?><br>
                                                <strong>Unité:</strong> <?= htmlspecialchars($details['unite']) ?>
                                            </p>
                                        </div>
                                        <div class="col-md-6">
                                            <p class="mb-2">
                                                <strong>N° Dossier:</strong>
                                                <?= htmlspecialchars($detention['numero_dossier']) ?><br>
                                                <strong>Infraction:</strong>
                                                <?= htmlspecialchars($details['infraction']) ?>
                                                <span
                                                    class="badge badge-<?= $details['categorie'] === 'CRIME' ? 'danger' : 'warning' ?>">
                                                    <?= $details['categorie'] ?>
                                                </span><br>
                                                <strong>Lieu:</strong> <?= htmlspecialchars($details['lieu']) ?>
                                            </p>
                                        </div>
                                    </div>

                                    <div class="deduction-info">
                                        <h6><i class="fas fa-calculator me-2"></i>Déduction Automatique</h6>
                                        <p class="mb-0">
                                            Le détenu a passé <strong><?= $detention['jours_detention_actuel'] ?>
                                                jours</strong>
                                            (<?= $detention['mois_detention_actuel'] ?> mois) en détention provisoire.
                                            <br>Ces jours seront <strong>automatiquement déduits</strong> de la peine
                                            prononcée.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Formulaire de jugement -->
                            <div class="card">
                                <div class="card-header bg-success text-white">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-gavel me-2"></i>Informations du Jugement
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST" action="" id="conversionForm">
                                        <?= CSRF::field() ?>

                                        <!-- Date de jugement -->
                                        <div class="form-group mb-3">
                                            <label for="date_jugement" class="form-label">
                                                Date du Jugement <span class="text-danger">*</span>
                                            </label>
                                            <input type="date"
                                                class="form-control <?= isset($errors['date_jugement']) ? 'is-invalid' : '' ?>"
                                                id="date_jugement" name="date_jugement"
                                                value="<?= htmlspecialchars($_POST['date_jugement'] ?? date('Y-m-d')) ?>"
                                                min="<?= $detention['date_debut_detention'] ?>"
                                                max="<?= date('Y-m-d') ?>" required>
                                            <?php if (isset($errors['date_jugement'])): ?>
                                            <div class="invalid-feedback">
                                                <?= htmlspecialchars($errors['date_jugement'][0] ?? $errors['date_jugement']) ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Numéro et tribunal -->
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group mb-3">
                                                    <label for="numero_jugement" class="form-label">
                                                        Numéro du Jugement
                                                    </label>
                                                    <input type="text" class="form-control" id="numero_jugement"
                                                        name="numero_jugement"
                                                        value="<?= htmlspecialchars($_POST['numero_jugement'] ?? '') ?>"
                                                        placeholder="Ex: JM-2025-045">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group mb-3">
                                                    <label for="tribunal" class="form-label">
                                                        Tribunal
                                                    </label>
                                                    <input type="text" class="form-control" id="tribunal"
                                                        name="tribunal"
                                                        value="<?= htmlspecialchars($_POST['tribunal'] ?? 'Tribunal Militaire') ?>"
                                                        placeholder="Ex: Tribunal Militaire d'Abidjan">
                                                </div>
                                            </div>
                                        </div>

                                        <hr class="my-4">

                                        <!-- Peine prononcée -->
                                        <h5 class="mb-3"><i class="fas fa-balance-scale me-2"></i>Peine Prononcée</h5>

                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group mb-3">
                                                    <label for="peine_valeur" class="form-label">
                                                        Durée <span class="text-danger">*</span>
                                                    </label>
                                                    <input type="number"
                                                        class="form-control <?= isset($errors['peine_valeur']) ? 'is-invalid' : '' ?>"
                                                        id="peine_valeur" name="peine_valeur"
                                                        value="<?= htmlspecialchars($_POST['peine_valeur'] ?? '') ?>"
                                                        min="1" required>
                                                    <?php if (isset($errors['peine_valeur'])): ?>
                                                    <div class="invalid-feedback">
                                                        <?= htmlspecialchars($errors['peine_valeur'][0] ?? $errors['peine_valeur']) ?>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group mb-3">
                                                    <label for="peine_unite" class="form-label">
                                                        Unité <span class="text-danger">*</span>
                                                    </label>
                                                    <select
                                                        class="form-select <?= isset($errors['peine_unite']) ? 'is-invalid' : '' ?>"
                                                        id="peine_unite" name="peine_unite" required>
                                                        <option value="">-- Sélectionner --</option>
                                                        <option value="JOUR"
                                                            <?= ($_POST['peine_unite'] ?? '') === 'JOUR' ? 'selected' : '' ?>>
                                                            Jour(s)
                                                        </option>
                                                        <option value="MOIS"
                                                            <?= ($_POST['peine_unite'] ?? '') === 'MOIS' ? 'selected' : '' ?>>
                                                            Mois
                                                        </option>
                                                        <option value="ANNEE"
                                                            <?= ($_POST['peine_unite'] ?? '') === 'ANNEE' ? 'selected' : '' ?>>
                                                            Année(s)
                                                        </option>
                                                    </select>
                                                    <?php if (isset($errors['peine_unite'])): ?>
                                                    <div class="invalid-feedback">
                                                        <?= htmlspecialchars($errors['peine_unite'][0] ?? $errors['peine_unite']) ?>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Calcul automatique -->
                                        <div id="calculPeine" class="alert alert-info" style="display:none;">
                                            <h6><i class="fas fa-calculator me-2"></i>Calcul Automatique</h6>
                                            <div id="calculDetails"></div>
                                        </div>

                                        <!-- Confirmation -->
                                        <div class="form-group mb-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="confirmation"
                                                    required>
                                                <label class="form-check-label fw-bold" for="confirmation">
                                                    Je confirme que le jugement a été prononcé et que les informations
                                                    sont correctes
                                                </label>
                                            </div>
                                        </div>

                                        <div class="alert alert-warning">
                                            <h6><i class="fas fa-info-circle me-2"></i>Ce qui va se passer</h6>
                                            <ul class="mb-0">
                                                <li>La détention provisoire sera clôturée</li>
                                                <li>Une condamnation sera créée avec le même numéro de dossier</li>
                                                <li>Le statut du détenu passera à <strong>CONDAMNÉ</strong></li>
                                                <li>Les <?= $detention['jours_detention_actuel'] ?> jours de détention
                                                    provisoire seront déduits automatiquement</li>
                                                <li>Une nouvelle période de détention sera créée (exécution de peine)
                                                </li>
                                            </ul>
                                        </div>

                                        <!-- Boutons -->
                                        <div class="form-group mt-4">
                                            <button type="submit" class="btn btn-success btn-lg" id="btnConvertir">
                                                <i class="fas fa-gavel me-2"></i>Créer la Condamnation
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

                        <!-- Colonne droite: Résumé -->
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header bg-info text-white">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-info-circle me-2"></i>Résumé
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <h6 class="fw-bold">Détention Provisoire</h6>
                                    <div class="info-badge">
                                        <?= $detention['jours_detention_actuel'] ?> jours
                                    </div>
                                    <div class="info-badge">
                                        <?= $detention['mois_detention_actuel'] ?> mois
                                    </div>

                                    <h6 class="fw-bold mt-3">Dates Clés</h6>
                                    <p class="small mb-2">
                                        <strong>Début DP:</strong><br>
                                        <?= date('d/m/Y', strtotime($detention['date_debut_detention'])) ?>
                                    </p>
                                    <p class="small mb-2">
                                        <strong>Aujourd'hui:</strong><br>
                                        <?= date('d/m/Y') ?>
                                    </p>

                                    <h6 class="fw-bold mt-3">Infraction</h6>
                                    <p class="small mb-0">
                                        <?= htmlspecialchars($details['infraction']) ?>
                                        <br>
                                        <span
                                            class="badge badge-<?= $details['categorie'] === 'CRIME' ? 'danger' : 'warning' ?>">
                                            <?= $details['categorie'] ?>
                                        </span>
                                    </p>
                                </div>
                            </div>

                            <div class="card mt-3">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-book me-2"></i>Références Légales
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <p class="small">
                                        Selon le Code de Justice Militaire, les jours de détention provisoire sont
                                        obligatoirement
                                        déduits de la peine prononcée.
                                    </p>
                                    <p class="small mb-0">
                                        La conversion en condamnation marque le passage de la phase préventive à la
                                        phase d'exécution de peine.
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
        const joursDP = <?= $detention['jours_detention_actuel'] ?>;

        // Calculer la peine effective
        function calculerPeine() {
            const valeur = parseInt($('#peine_valeur').val()) || 0;
            const unite = $('#peine_unite').val();

            if (valeur > 0 && unite) {
                let joursTotal = 0;
                switch (unite) {
                    case 'JOUR':
                        joursTotal = valeur;
                        break;
                    case 'MOIS':
                        joursTotal = valeur * 30;
                        break;
                    case 'ANNEE':
                        joursTotal = valeur * 365;
                        break;
                }

                const joursEffectifs = Math.max(0, joursTotal - joursDP);
                const moisEffectifs = Math.floor(joursEffectifs / 30);
                const joursRestants = joursEffectifs % 30;

                let html =
                    `
                        <p class="mb-2"><strong>Peine prononcée:</strong> ${valeur} ${unite}(s) = ${joursTotal} jours</p>
                        <p class="mb-2"><strong>Déduction DP:</strong> ${joursDP} jours (${<?= $detention['mois_detention_actuel'] ?>} mois)</p>
                        <hr>
                        <p class="mb-0"><strong class="text-success">Peine effective à purger:</strong> ${joursEffectifs} jours`;

                if (moisEffectifs > 0) {
                    html += ` (${moisEffectifs} mois`;
                    if (joursRestants > 0) html += ` et ${joursRestants} jours`;
                    html += ')';
                }
                html += '</p>';

                $('#calculDetails').html(html);
                $('#calculPeine').slideDown();
            } else {
                $('#calculPeine').slideUp();
            }
        }

        $('#peine_valeur, #peine_unite').on('change', calculerPeine);

        // Validation
        $('#conversionForm').on('submit', function(e) {
            const confirmation = $('#confirmation').is(':checked');
            if (!confirmation) {
                e.preventDefault();
                alert('Veuillez confirmer la conversion.');
                return false;
            }

            const detenu = "<?= htmlspecialchars($detention['detenu']) ?>";
            if (!confirm(
                    `CONFIRMATION\n\nVous allez convertir la détention provisoire de ${detenu} en condamnation.\n\nContinuer ?`
                )) {
                e.preventDefault();
                return false;
            }

            $('#btnConvertir').prop('disabled', true).html(
                '<i class="fas fa-spinner fa-spin me-2"></i>Conversion en cours...');
        });
    });
    </script>
</body>

</html>