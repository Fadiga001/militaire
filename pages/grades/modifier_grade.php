<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit();
}

require_once '../../includes/db.php';
require_once '../../includes/classes/autoload.php';
require_once '../../includes/logs.php';
require_once '../../includes/csrf.php';
require_once '../../includes/validator.php';

// Vérification du rôle admin
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
$name = $user ? htmlspecialchars($user['nom'] . ' ' . $user['prenom']) : '';
$role = $user['role'] ?? '';

if ($role !== 'ADMIN') {
    header('Location: grades.php');
    exit();
}

$refMgr = new ReferenceManager($pdo);
$errors = [];
$success = '';

// Récupérer l'ID du grade
$gradeId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$grade = $refMgr->getGradeById($gradeId);

if (!$grade) {
    header('Location: grades.php');
    exit();
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        CSRF::verify();

        $validated = Validator::make($_POST)
            ->required('code', 'Le code est requis')
            ->max('code', 10, 'Le code ne doit pas dépasser 10 caractères')
            ->required('libelle', 'Le libellé est requis')
            ->max('libelle', 100, 'Le libellé ne doit pas dépasser 100 caractères')
            ->required('hierarchie', 'La hiérarchie est requise')
            ->integer('hierarchie', 'La hiérarchie doit être un nombre entier')
            ->validate();

        // Vérifier que le code n'existe pas déjà (sauf pour ce grade)
        $stmt = $pdo->prepare("SELECT COUNT(*) as nb FROM grades WHERE code = ? AND id != ?");
        $stmt->execute([$validated['code'], $gradeId]);
        if ((int)$stmt->fetch()['nb'] > 0) {
            $errors['code'] = "Ce code existe déjà.";
        }

        // Vérifier que la hiérarchie n'existe pas déjà (sauf pour ce grade)
        $stmt = $pdo->prepare("SELECT COUNT(*) as nb FROM grades WHERE hierarchie = ? AND id != ?");
        $stmt->execute([$validated['hierarchie'], $gradeId]);
        if ((int)$stmt->fetch()['nb'] > 0) {
            $errors['hierarchie'] = "Cette position hiérarchique est déjà utilisée.";
        }

        if (empty($errors)) {
            $sql = "UPDATE grades SET
                    code = :code,
                    libelle = :libelle,
                    hierarchie = :hierarchie
                    WHERE id = :id";

            $stmt = $pdo->prepare($sql);
            $success = $stmt->execute([
                ':code' => strtoupper($validated['code']),
                ':libelle' => $validated['libelle'],
                ':hierarchie' => (int)$validated['hierarchie'],
                ':id' => $gradeId
            ]);

            if ($success) {
                log_activity($pdo, $_SESSION['user_id'], 'Modification grade', "Grade: {$validated['libelle']} (ID: $gradeId)");
                $success = "Grade modifié avec succès.";

                // Recharger le grade
                $grade = $refMgr->getGradeById($gradeId);
            }
        }
    } catch (ValidationException $e) {
        $errors = json_decode($e->getMessage(), true);
    } catch (Exception $e) {
        $errors['general'] = "Une erreur s'est produite lors de la modification.";
        error_log($e->getMessage());
    }
}

// Vérifier si le grade est utilisé
$stmt = $pdo->prepare("SELECT COUNT(*) as nb FROM detenus WHERE grade_id = ? AND is_deleted = FALSE");
$stmt->execute([$gradeId]);
$nbDetenus = (int)$stmt->fetch()['nb'];
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Modifier un Grade - Système Militaire</title>
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
                            <i class="fas fa-edit me-2"></i>Modifier un Grade
                        </h3>
                        <ul class="breadcrumbs mb-3">
                            <li class="nav-home">
                                <a href="../dash/dashboard.php"><i class="fas fa-home"></i></a>
                            </li>
                            <li class="separator"><i class="fas fa-chevron-right"></i></li>
                            <li class="nav-item">
                                <a href="grades.php">Grades</a>
                            </li>
                            <li class="separator"><i class="fas fa-chevron-right"></i></li>
                            <li class="nav-item">Modifier</li>
                        </ul>
                    </div>

                    <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i>
                        <?= htmlspecialchars($success) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <?php if (isset($errors['general'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?= htmlspecialchars($errors['general']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <?php if ($nbDetenus > 0): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Information :</strong> Ce grade est actuellement utilisé par
                        <strong><?= $nbDetenus ?></strong> détenu(s).
                        Les modifications seront appliquées à tous les détenus ayant ce grade.
                    </div>
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="card-title">Informations du Grade</h4>
                                </div>
                                <div class="card-body">
                                    <form method="POST" action="">
                                        <?= CSRF::field() ?>

                                        <!-- Code -->
                                        <div class="form-group mb-3">
                                            <label for="code" class="form-label">
                                                Code du Grade <span class="text-danger">*</span>
                                            </label>
                                            <input type="text"
                                                class="form-control <?= isset($errors['code']) ? 'is-invalid' : '' ?>"
                                                id="code" name="code" value="<?= htmlspecialchars($grade['code']) ?>"
                                                maxlength="10" style="text-transform: uppercase;" required>
                                            <?php if (isset($errors['code'])): ?>
                                            <div class="invalid-feedback">
                                                <?= htmlspecialchars($errors['code'][0] ?? $errors['code']) ?>
                                            </div>
                                            <?php endif; ?>
                                            <small class="form-text text-muted">
                                                <i class="fas fa-info-circle"></i>
                                                Ex: CPL, SGT, LTN (max 10 caractères)
                                            </small>
                                        </div>

                                        <!-- Libellé -->
                                        <div class="form-group mb-3">
                                            <label for="libelle" class="form-label">
                                                Libellé du Grade <span class="text-danger">*</span>
                                            </label>
                                            <input type="text"
                                                class="form-control <?= isset($errors['libelle']) ? 'is-invalid' : '' ?>"
                                                id="libelle" name="libelle"
                                                value="<?= htmlspecialchars($grade['libelle']) ?>" maxlength="100"
                                                required>
                                            <?php if (isset($errors['libelle'])): ?>
                                            <div class="invalid-feedback">
                                                <?= htmlspecialchars($errors['libelle'][0] ?? $errors['libelle']) ?>
                                            </div>
                                            <?php endif; ?>
                                            <small class="form-text text-muted">
                                                <i class="fas fa-info-circle"></i>
                                                Ex: Caporal, Sergent, Lieutenant (max 100 caractères)
                                            </small>
                                        </div>

                                        <!-- Hiérarchie -->
                                        <div class="form-group mb-3">
                                            <label for="hierarchie" class="form-label">
                                                Position Hiérarchique <span class="text-danger">*</span>
                                            </label>
                                            <input type="number"
                                                class="form-control <?= isset($errors['hierarchie']) ? 'is-invalid' : '' ?>"
                                                id="hierarchie" name="hierarchie"
                                                value="<?= (int)$grade['hierarchie'] ?>" min="1" max="100" required>
                                            <?php if (isset($errors['hierarchie'])): ?>
                                            <div class="invalid-feedback">
                                                <?= htmlspecialchars($errors['hierarchie'][0] ?? $errors['hierarchie']) ?>
                                            </div>
                                            <?php endif; ?>
                                            <small class="form-text text-muted">
                                                <i class="fas fa-info-circle"></i>
                                                1 = grade le plus bas, 20 = grade le plus élevé
                                            </small>
                                        </div>

                                        <!-- Informations supplémentaires -->
                                        <div class="alert alert-light border">
                                            <h6 class="mb-3"><i class="fas fa-info-circle me-2"></i>Informations</h6>
                                            <ul class="mb-0">
                                                <li>Date de création :
                                                    <strong><?= date('d/m/Y à H:i', strtotime($grade['created_at'])) ?></strong>
                                                </li>
                                                <li>Statut : <span
                                                        class="badge badge-<?= $grade['is_active'] ? 'success' : 'secondary' ?>">
                                                        <?= $grade['is_active'] ? 'Actif' : 'Inactif' ?>
                                                    </span></li>
                                                <li>Détenus utilisant ce grade : <strong><?= $nbDetenus ?></strong></li>
                                            </ul>
                                        </div>

                                        <!-- Boutons -->
                                        <div class="form-group mt-4">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-save me-2"></i>Enregistrer les modifications
                                            </button>
                                            <a href="grades.php" class="btn btn-secondary">
                                                <i class="fas fa-times me-2"></i>Annuler
                                            </a>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Colonne droite: Aide -->
                        <div class="col-md-4">
                            <div class="card card-info">
                                <div class="card-header">
                                    <h4 class="card-title">
                                        <i class="fas fa-question-circle me-2"></i>Aide
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <h6 class="fw-bold">Structure Hiérarchique</h6>
                                    <p class="small">
                                        Les grades militaires suivent une hiérarchie stricte.
                                        Assurez-vous que la position hiérarchique correspond
                                        à l'ordre réel des grades dans l'armée.
                                    </p>

                                    <h6 class="fw-bold mt-3">Code du Grade</h6>
                                    <p class="small">
                                        Le code doit être court (3-4 lettres) et unique.
                                        Il sera utilisé dans les rapports et affichages compacts.
                                    </p>

                                    <h6 class="fw-bold mt-3">Impact des Modifications</h6>
                                    <p class="small">
                                        Les modifications seront immédiatement appliquées à tous
                                        les détenus ayant ce grade. Vérifiez bien avant de valider.
                                    </p>

                                    <div class="alert alert-warning mt-3">
                                        <small>
                                            <i class="fas fa-exclamation-triangle me-1"></i>
                                            <strong>Attention :</strong> Modifier la position hiérarchique
                                            peut affecter l'ordre d'affichage des grades dans tout le système.
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <!-- Statistiques du grade -->
                            <div class="card card-secondary mt-3">
                                <div class="card-header">
                                    <h4 class="card-title">
                                        <i class="fas fa-chart-bar me-2"></i>Statistiques
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <div class="d-flex justify-content-between mb-3">
                                        <span>Détenus actifs</span>
                                        <strong><?= $nbDetenus ?></strong>
                                    </div>

                                    <?php if ($nbDetenus > 0): ?>
                                    <a href="../detenus/detenus.php?grade_id=<?= $gradeId ?>"
                                        class="btn btn-sm btn-outline-primary w-100">
                                        <i class="fas fa-eye me-1"></i>Voir les détenus
                                    </a>
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
    // Convertir le code en majuscules automatiquement
    document.getElementById('code').addEventListener('input', function(e) {
        this.value = this.value.toUpperCase();
    });

    // Confirmation si le grade est utilisé
    <?php if ($nbDetenus > 0): ?>
    document.querySelector('form').addEventListener('submit', function(e) {
        if (!confirm(
                'Ce grade est utilisé par <?= $nbDetenus ?> détenu(s).\n\nLes modifications seront appliquées à tous.\n\nVoulez-vous continuer ?'
            )) {
            e.preventDefault();
        }
    });
    <?php endif; ?>

    // Auto-dismiss des alertes après 5 secondes
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert-success');
        alerts.forEach(alert => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
    </script>
</body>

</html>