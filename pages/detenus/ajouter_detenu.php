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

$detenuMgr = new DetenuManager($pdo);
$refMgr = new ReferenceManager($pdo);

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        CSRF::verify();
    } catch (Exception $e) {
        $errors[] = 'Session expirée. Veuillez recharger la page.';
    }
    // Validation
    $required = ['nom', 'prenoms', 'sexe', 'grade_id', 'unite_id'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            $errors[] = "Le champ " . ucfirst($field) . " est obligatoire.";
        }
    }

    // Note: le matricule interne ('matricule') est généré par trigger.
    // Le matricule_militaire n'est pas unique dans le schéma actuel → pas de vérification d'unicité ici.

    if (empty($errors)) {
        // Upload photo si fournie (vérifications strictes)
        $photoPath = null;
        if (!empty($_FILES['photo']['name'])) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
            $maxSize = 5 * 1024 * 1024; // 5MB

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $detected = $finfo->file($_FILES['photo']['tmp_name']);

            if (in_array($detected, $allowedTypes) && $_FILES['photo']['size'] <= $maxSize) {
                $uploadDir = '../../uploads/photos/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
                $random = bin2hex(random_bytes(6));
                $filename = 'detenu_' . time() . '_' . $random . '.' . $extension;
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $filename)) {
                    $photoPath = 'uploads/photos/' . $filename;
                }
            } else {
                $errors[] = "Photo invalide (JPG/PNG, max 5MB).";
            }
        }

        if (empty($errors)) {
            $data = [
                'nom' => trim($_POST['nom']),
                'prenoms' => trim($_POST['prenoms']),
                'date_naissance' => $_POST['date_naissance'] ?: null,
                'lieu_naissance' => trim($_POST['lieu_naissance']) ?: null,
                'nationalite' => $_POST['nationalite'] ?: 'Ivoirienne',
                'sexe' => $_POST['sexe'],
                'situation_matrimoniale' => $_POST['situation_matrimoniale'] ?: null,
                'nombre_enfants' => (int)($_POST['nombre_enfants'] ?? 0),
                'grade_id' => (int)$_POST['grade_id'],
                'unite_id' => (int)$_POST['unite_id'],
                'matricule_militaire' => trim($_POST['matricule_militaire']) ?: null,
                'date_incorporation' => $_POST['date_incorporation'] ?: null,
                'telephone' => trim($_POST['telephone']) ?: null,
                'email' => trim($_POST['email']) ?: null,
                'adresse' => trim($_POST['adresse']) ?: null,
                'personne_contact_nom' => trim($_POST['personne_contact_nom']) ?: null,
                'personne_contact_telephone' => trim($_POST['personne_contact_telephone']) ?: null,
                'personne_contact_relation' => trim($_POST['personne_contact_relation']) ?: null,
                'photo_path' => $photoPath,
                'statut_actuel' => $_POST['statut_actuel'] ?? NULL
            ];

            $detenuId = $detenuMgr->create($data, Auth::id());

            if ($detenuId) {
                log_activity($pdo, Auth::id(), 'Ajout détenu', "Nouveau détenu ID: $detenuId");
                $success = "Détenu ajouté avec succès !";
                // Redirection après 2 secondes
                header("refresh:2;url=voir_detenu.php?id=$detenuId");
            } else {
                $errors[] = "Erreur lors de l'ajout du détenu.";
            }
        }
    }
}
$refMgr->initializeDefaultData();
$grades = $refMgr->getAllGrades();
$unites = $refMgr->getAllUnites();


?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Ajouter un Détenu - Système Militaire</title>
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
                            <i class="fas fa-user-plus me-2"></i>Ajouter un Détenu
                        </h3>
                        <ul class="breadcrumbs mb-3">
                            <li class="nav-home">
                                <a href="../dash/dashboard.php"><i class="fas fa-home"></i></a>
                            </li>
                            <li class="separator"><i class="fas fa-arrow-right"></i></li>
                            <li class="nav-item">
                                <a href="detenus.php">Détenus</a>
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

                    <form method="POST" enctype="multipart/form-data">
                        <?= CSRF::field() ?>
                        <div class="row">
                            <!-- Informations personnelles -->
                            <div class="col-lg-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h4 class="card-title">
                                            <i class="fas fa-user me-2"></i>Informations Personnelles
                                        </h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Nom <span class="text-danger">*</span></label>
                                                <input type="text" name="nom" class="form-control" required
                                                    value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Prénoms <span
                                                        class="text-danger">*</span></label>
                                                <input type="text" name="prenoms" class="form-control" required
                                                    value="<?= htmlspecialchars($_POST['prenoms'] ?? '') ?>">
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Date de Naissance</label>
                                                <input type="date" name="date_naissance" class="form-control"
                                                    value="<?= htmlspecialchars($_POST['date_naissance'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Sexe <span
                                                        class="text-danger">*</span></label>
                                                <select name="sexe" class="form-select" required>
                                                    <option value="">Sélectionner</option>
                                                    <option value="M"
                                                        <?= ($_POST['sexe'] ?? '') === 'M' ? 'selected' : '' ?>>Masculin
                                                    </option>
                                                    <option value="F"
                                                        <?= ($_POST['sexe'] ?? '') === 'F' ? 'selected' : '' ?>>Féminin
                                                    </option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Lieu de Naissance</label>
                                            <input type="text" name="lieu_naissance" class="form-control"
                                                value="<?= htmlspecialchars($_POST['lieu_naissance'] ?? '') ?>">
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Nationalité</label>
                                            <input type="text" name="nationalite" class="form-control"
                                                value="<?= htmlspecialchars($_POST['nationalite'] ?? 'Ivoirienne') ?>">
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Situation Matrimoniale</label>
                                                <select name="situation_matrimoniale" class="form-select">
                                                    <option value="">Sélectionner</option>
                                                    <option value="CELIBATAIRE">Célibataire</option>
                                                    <option value="MARIE">Marié(e)</option>
                                                    <option value="DIVORCE">Divorcé(e)</option>
                                                    <option value="VEUF">Veuf(ve)</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Nombre d'Enfants</label>
                                                <input type="number" name="nombre_enfants" class="form-control" min="0"
                                                    value="<?= htmlspecialchars($_POST['nombre_enfants'] ?? '0') ?>">
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Photo</label>
                                            <input type="file" name="photo" class="form-control" accept="image/*">
                                            <small class="text-muted">Format : JPG, PNG (max 5MB)</small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Informations militaires -->
                            <div class="col-lg-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h4 class="card-title">
                                            <i class="fas fa-shield-alt me-2"></i>Informations Militaires
                                        </h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">Grade <span class="text-danger">*</span></label>
                                            <select name="grade_id" class="form-select" required>
                                                <option value="">Sélectionner un grade</option>
                                                <?php foreach ($grades as $grade): ?>
                                                <option value="<?= $grade['id'] ?>"
                                                    <?= ($_POST['grade_id'] ?? '') == $grade['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($grade['code'] . ' - ' . $grade['libelle']) ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Unité <span class="text-danger">*</span></label>
                                            <select name="unite_id" class="form-select" required>
                                                <option value="">Sélectionner une unité</option>
                                                <?php foreach ($unites as $unite): ?>
                                                <option value="<?= $unite['id'] ?>"
                                                    <?= ($_POST['unite_id'] ?? '') == $unite['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($unite['code'] . ' - ' . $unite['nom']) ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Matricule Militaire</label>
                                            <input type="text" name="matricule_militaire" class="form-control"
                                                value="<?= htmlspecialchars($_POST['matricule_militaire'] ?? '') ?>">
                                            <small class="text-muted">Le matricule détenu sera généré
                                                automatiquement</small>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Date d'Incorporation</label>
                                            <input type="date" name="date_incorporation" class="form-control"
                                                value="<?= htmlspecialchars($_POST['date_incorporation'] ?? '') ?>">
                                        </div>
                                    </div>
                                </div>

                                <!-- Contact -->
                                <div class="card mt-4">
                                    <div class="card-header">
                                        <h4 class="card-title">
                                            <i class="fas fa-phone me-2"></i>Coordonnées
                                        </h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">Téléphone</label>
                                            <input type="tel" name="telephone" class="form-control"
                                                value="<?= htmlspecialchars($_POST['telephone'] ?? '') ?>">
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Email</label>
                                            <input type="email" name="email" class="form-control"
                                                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Adresse</label>
                                            <textarea name="adresse" class="form-control"
                                                rows="2"><?= htmlspecialchars($_POST['adresse'] ?? '') ?></textarea>
                                        </div>

                                        <hr>
                                        <h5 class="mb-3">Personne à Contacter</h5>

                                        <div class="mb-3">
                                            <label class="form-label">Nom</label>
                                            <input type="text" name="personne_contact_nom" class="form-control"
                                                value="<?= htmlspecialchars($_POST['personne_contact_nom'] ?? '') ?>">
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Téléphone</label>
                                                <input type="tel" name="personne_contact_telephone" class="form-control"
                                                    value="<?= htmlspecialchars($_POST['personne_contact_telephone'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Relation</label>
                                                <input type="text" name="personne_contact_relation" class="form-control"
                                                    placeholder="Ex: Épouse, Père..."
                                                    value="<?= htmlspecialchars($_POST['personne_contact_relation'] ?? '') ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-body text-end">
                                        <a href="detenus.php" class="btn btn-secondary me-2">
                                            <i class="fas fa-times me-2"></i>Annuler
                                        </a>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>Enregistrer le Détenu
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
</body>

</html>