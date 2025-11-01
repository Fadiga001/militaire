<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'ADMIN') {
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
    $required = ['code', 'libelle', 'hierarchie'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            $errors[] = "Le champ " . ucfirst($field) . " est obligatoire.";
        }
    }

    // Vérifier code unique
    if (!empty($_POST['code'])) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as nb FROM grades WHERE code = ?");
        $stmt->execute([strtoupper(trim($_POST['code']))]);
        if ((int)$stmt->fetch()['nb'] > 0) {
            $errors[] = "Ce code de grade existe déjà.";
        }
    }

    // Vérifier hiérarchie unique
    if (!empty($_POST['hierarchie'])) {
        $hierarchie = (int)$_POST['hierarchie'];
        if ($hierarchie < 1 || $hierarchie > 100) {
            $errors[] = "La hiérarchie doit être entre 1 et 100.";
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) as nb FROM grades WHERE hierarchie = ?");
        $stmt->execute([$hierarchie]);
        if ((int)$stmt->fetch()['nb'] > 0) {
            $errors[] = "Ce niveau de hiérarchie est déjà utilisé par un autre grade.";
        }
    }

    if (empty($errors)) {
        $data = [
            'code' => strtoupper(trim($_POST['code'])),
            'libelle' => trim($_POST['libelle']),
            'hierarchie' => (int)$_POST['hierarchie']
        ];

        $gradeId = $refMgr->createGrade($data);

        if ($gradeId) {
            log_activity($pdo, $_SESSION['user_id'], 'Ajout grade', "Nouveau grade: " . $data['libelle']);
            $success = "Grade ajouté avec succès !";
            header("refresh:2;url=grades.php");
        } else {
            $errors[] = "Erreur lors de l'ajout du grade.";
        }
    }
}

// Récupérer tous les grades pour afficher les hiérarchies prises
$gradesExistants = $refMgr->getAllGrades();
$hierarchiesPrises = array_column($gradesExistants, 'hierarchie');
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Ajouter un Grade - Système Militaire</title>
    <meta content="width=device-width, initial-scale=1.0, shrink-to-fit=no" name="viewport" />
    <?php include '../../requires/link.php'; ?>
    <style>
    .hierarchie-preview {
        display: inline-block;
        width: 50px;
        height: 50px;
        line-height: 50px;
        text-align: center;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        font-weight: bold;
        font-size: 1.5rem;
    }

    .grade-example {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        border-left: 4px solid #177dff;
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
                            <i class="fas fa-plus me-2"></i>Ajouter un Grade Militaire
                        </h3>
                        <ul class="breadcrumbs mb-3">
                            <li class="nav-home"><a href="../dash/dashboard.php"><i class="fas fa-home"></i></a></li>
                            <li class="separator"><i class="fas fa-arrow-right"></i></li>
                            <li class="nav-item"><a href="grades.php">Grades</a></li>
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
                        <p class="mb-0"><i class="fas fa-spinner fa-spin me-2"></i>Redirection...</p>
                    </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="row">
                            <div class="col-lg-8">
                                <div class="card">
                                    <div class="card-header">
                                        <h4 class="card-title"><i class="fas fa-info-circle me-2"></i>Informations du
                                            Grade</h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Code <span
                                                        class="text-danger">*</span></label>
                                                <input type="text" name="code" class="form-control"
                                                    placeholder="Ex: CPL, SGT, LTN" required
                                                    style="text-transform: uppercase;" maxlength="10"
                                                    value="<?= htmlspecialchars($_POST['code'] ?? '') ?>">
                                                <small class="text-muted">Code court en majuscules (max 10
                                                    caractères)</small>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">
                                                    Hiérarchie <span class="text-danger">*</span>
                                                    <span id="hierarchie-preview" class="ms-2"></span>
                                                </label>
                                                <input type="number" name="hierarchie" id="hierarchie"
                                                    class="form-control" min="1" max="100" required
                                                    value="<?= htmlspecialchars($_POST['hierarchie'] ?? '') ?>">
                                                <small class="text-muted">Niveau hiérarchique (1 = grade le plus
                                                    bas)</small>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Libellé Complet <span
                                                    class="text-danger">*</span></label>
                                            <input type="text" name="libelle" class="form-control"
                                                placeholder="Ex: Caporal, Sergent, Lieutenant" required
                                                value="<?= htmlspecialchars($_POST['libelle'] ?? '') ?>">
                                        </div>

                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle me-2"></i>
                                            <strong>Important :</strong> Le niveau de hiérarchie doit être unique.
                                            Chaque grade a un niveau différent dans l'ordre hiérarchique militaire.
                                        </div>

                                        <?php if (!empty($hierarchiesPrises)): ?>
                                        <div class="grade-example">
                                            <strong>Hiérarchies déjà utilisées :</strong>
                                            <div class="mt-2">
                                                <?php
                                                    sort($hierarchiesPrises);
                                                    foreach ($hierarchiesPrises as $h):
                                                    ?>
                                                <span class="badge badge-secondary me-1"><?= $h ?></span>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-4">
                                <div class="card">
                                    <div class="card-header bg-primary text-white">
                                        <h4 class="card-title mb-0"><i class="fas fa-lightbulb me-2"></i>Exemples de
                                            Grades</h4>
                                    </div>
                                    <div class="card-body">
                                        <h6 class="text-primary">Sous-officiers (1-9)</h6>
                                        <ul class="list-unstyled mb-3">
                                            <li class="mb-2">
                                                <strong>1-2:</strong> Soldat 2ème/1ère Classe<br>
                                                <small class="text-muted">S2C, S1C</small>
                                            </li>
                                            <li class="mb-2">
                                                <strong>3-4:</strong> Caporal, Caporal-Chef<br>
                                                <small class="text-muted">CPL, CCH</small>
                                            </li>
                                            <li class="mb-2">
                                                <strong>5-6:</strong> Sergent, Sergent-Chef<br>
                                                <small class="text-muted">SGT, SCH</small>
                                            </li>
                                            <li class="mb-0">
                                                <strong>7-9:</strong> Adjudant, Adjudant-Chef, Major<br>
                                                <small class="text-muted">ADJ, ADC, MDL</small>
                                            </li>
                                        </ul>

                                        <h6 class="text-success">Officiers subalternes (10-13)</h6>
                                        <ul class="list-unstyled mb-3">
                                            <li class="mb-2">
                                                <strong>10:</strong> Aspirant<br>
                                                <small class="text-muted">ASP</small>
                                            </li>
                                            <li class="mb-2">
                                                <strong>11-12:</strong> Sous-Lieutenant, Lieutenant<br>
                                                <small class="text-muted">SLT, LTN</small>
                                            </li>
                                            <li class="mb-0">
                                                <strong>13:</strong> Capitaine<br>
                                                <small class="text-muted">CPT</small>
                                            </li>
                                        </ul>

                                        <h6 class="text-warning">Officiers supérieurs (14-16)</h6>
                                        <ul class="list-unstyled mb-3">
                                            <li class="mb-2">
                                                <strong>14:</strong> Commandant<br>
                                                <small class="text-muted">CDT</small>
                                            </li>
                                            <li class="mb-2">
                                                <strong>15:</strong> Lieutenant-Colonel<br>
                                                <small class="text-muted">LCL</small>
                                            </li>
                                            <li class="mb-0">
                                                <strong>16:</strong> Colonel<br>
                                                <small class="text-muted">COL</small>
                                            </li>
                                        </ul>

                                        <h6 class="text-danger">Officiers généraux (17-20)</h6>
                                        <ul class="list-unstyled mb-0">
                                            <li class="mb-2">
                                                <strong>17-20:</strong> Généraux<br>
                                                <small class="text-muted">GBR, GDV, GCA, GAR</small>
                                            </li>
                                        </ul>
                                    </div>
                                </div>

                                <div class="card mt-3">
                                    <div class="card-header bg-info text-white">
                                        <h4 class="card-title mb-0"><i class="fas fa-shield-alt me-2"></i>Bonnes
                                            Pratiques</h4>
                                    </div>
                                    <div class="card-body">
                                        <ul class="list-unstyled mb-0">
                                            <li class="mb-2">
                                                <i class="fas fa-check text-success me-2"></i>
                                                Code court et reconnaissable
                                            </li>
                                            <li class="mb-2">
                                                <i class="fas fa-check text-success me-2"></i>
                                                Libellé complet et officiel
                                            </li>
                                            <li class="mb-2">
                                                <i class="fas fa-check text-success me-2"></i>
                                                Hiérarchie respectée
                                            </li>
                                            <li class="mb-0">
                                                <i class="fas fa-check text-success me-2"></i>
                                                Pas de doublon dans les niveaux
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
                                        <a href="grades.php" class="btn btn-secondary me-2">
                                            <i class="fas fa-times me-2"></i>Annuler
                                        </a>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>Enregistrer le Grade
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
    // Prévisualisation hiérarchie
    document.getElementById('hierarchie').addEventListener('input', function() {
        const value = this.value;
        const preview = document.getElementById('hierarchie-preview');
        if (value) {
            preview.innerHTML = '<span class="hierarchie-preview">' + value + '</span>';
        } else {
            preview.innerHTML = '';
        }
    });

    // Convertir code en majuscules
    document.querySelector('input[name="code"]').addEventListener('input', function() {
        this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
    });

    // Initialiser la prévisualisation si valeur existe
    window.addEventListener('load', function() {
        const hierarchieInput = document.getElementById('hierarchie');
        if (hierarchieInput.value) {
            hierarchieInput.dispatchEvent(new Event('input'));
        }
    });
    </script>
</body>

</html>