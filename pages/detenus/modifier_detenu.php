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

$detenuMgr = new DetenuManager($pdo);
$refMgr = new ReferenceManager($pdo);

// Récupérer le détenu
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: detenus.php');
    exit();
}

$detenuId = (int)$_GET['id'];
$detenu = $detenuMgr->getById($detenuId);

if (!$detenu) {
    header('Location: detenus.php');
    exit();
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validation
    $required = ['nom', 'prenoms', 'sexe', 'grade_id', 'unite_id'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            $errors[] = "Le champ " . ucfirst($field) . " est obligatoire.";
        }
    }

    // Vérifier si le matricule militaire existe (excluant le détenu actuel)
    if (!empty($_POST['matricule_militaire'])) {
        if ($detenuMgr->matriculeExists($_POST['matricule_militaire'], $detenuId)) {
            $errors[] = "Ce matricule militaire existe déjà.";
        }
    }

    if (empty($errors)) {
        // Upload photo si fournie
        $photoPath = $detenu['photo_path']; // Garder l'ancienne par défaut

        if (!empty($_FILES['photo']['name'])) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
            if (in_array($_FILES['photo']['type'], $allowedTypes)) {
                $uploadDir = '../../uploads/photos/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                // Supprimer l'ancienne photo si elle existe
                if (!empty($detenu['photo_path']) && file_exists('../../' . $detenu['photo_path'])) {
                    unlink('../../' . $detenu['photo_path']);
                }

                $extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
                $filename = 'detenu_' . $detenuId . '_' . time() . '.' . $extension;

                if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $filename)) {
                    $photoPath = 'uploads/photos/' . $filename;

                    // Mettre à jour le chemin de la photo
                    $sql = "UPDATE detenus SET photo_path = :photo WHERE id = :id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([':photo' => $photoPath, ':id' => $detenuId]);
                }
            } else {
                $errors[] = "Format de photo invalide (JPG, PNG uniquement).";
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
                'personne_contact_relation' => trim($_POST['personne_contact_relation']) ?: null
            ];

            $updated = $detenuMgr->update($detenuId, $data, $_SESSION['user_id']);

            if ($updated) {
                // Log de l'activité
                log_activity(
                    $pdo,
                    $_SESSION['user_id'],
                    'Modification détenu',
                    "Détenu ID: $detenuId - " . $data['nom'] . ' ' . $data['prenoms']
                );

                $success = "Détenu modifié avec succès !";

                // Recharger les données
                $detenu = $detenuMgr->getById($detenuId);

                // Redirection après 2 secondes
                header("refresh:2;url=voir_detenu.php?id=$detenuId");
            } else {
                $errors[] = "Erreur lors de la modification du détenu.";
            }
        }
    }
}

$grades = $refMgr->getAllGrades();
$unites = $refMgr->getAllUnites();
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Modifier Détenu - <?= htmlspecialchars($detenu['nom_complet']) ?></title>
    <meta content="width=device-width, initial-scale=1.0, shrink-to-fit=no" name="viewport" />
    <?php include '../../requires/link.php'; ?>
    <style>
        .current-photo {
            max-width: 150px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
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
                            <i class="fas fa-user-edit me-2"></i>Modifier le Détenu
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
                            <li class="nav-item">
                                <a href="voir_detenu.php?id=<?= $detenu['id'] ?>">
                                    <?= htmlspecialchars($detenu['nom_complet']) ?>
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

                    <!-- Info détenu -->
                    <div class="alert alert-info">
                        <div class="d-flex align-items-center">
                            <div class="me-3">
                                <?php if (!empty($detenu['photo_path'])): ?>
                                    <img src="../../<?= htmlspecialchars($detenu['photo_path']) ?>" class="current-photo"
                                        alt="Photo actuelle">
                                <?php else: ?>
                                    <div style="width:80px;height:80px;"
                                        class="bg-secondary d-flex align-items-center justify-content-center text-white rounded">
                                        <i class="fas fa-user fa-2x"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div>
                                <h5 class="mb-1"><?= htmlspecialchars($detenu['nom_complet']) ?></h5>
                                <p class="mb-0">
                                    <strong>Matricule:</strong> <?= htmlspecialchars($detenu['matricule']) ?>
                                    <span class="mx-2">|</span>
                                    <strong>Grade:</strong> <?= htmlspecialchars($detenu['grade_libelle']) ?>
                                    <span class="mx-2">|</span>
                                    <strong>Statut:</strong>
                                    <?php
                                    $statutColors = [
                                        'CONDAMNE' => 'danger',
                                        'DETENTION_PROVISOIRE' => 'warning',
                                        'LIBRE' => 'success',
                                        'EVADE' => 'dark'
                                    ];
                                    $color = $statutColors[$detenu['statut_actuel']] ?? 'secondary';
                                    ?>
                                    <span class="badge badge-<?= $color ?>">
                                        <?= str_replace('_', ' ', $detenu['statut_actuel']) ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <form method="POST" enctype="multipart/form-data">
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
                                                    value="<?= htmlspecialchars($detenu['nom']) ?>">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Prénoms <span
                                                        class="text-danger">*</span></label>
                                                <input type="text" name="prenoms" class="form-control" required
                                                    value="<?= htmlspecialchars($detenu['prenoms']) ?>">
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Date de Naissance</label>
                                                <input type="date" name="date_naissance" class="form-control"
                                                    value="<?= htmlspecialchars($detenu['date_naissance'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Sexe <span
                                                        class="text-danger">*</span></label>
                                                <select name="sexe" class="form-select" required>
                                                    <option value="M" <?= $detenu['sexe'] === 'M' ? 'selected' : '' ?>>
                                                        Masculin</option>
                                                    <option value="F" <?= $detenu['sexe'] === 'F' ? 'selected' : '' ?>>
                                                        Féminin</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Lieu de Naissance</label>
                                            <input type="text" name="lieu_naissance" class="form-control"
                                                value="<?= htmlspecialchars($detenu['lieu_naissance'] ?? '') ?>">
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Nationalité</label>
                                            <input type="text" name="nationalite" class="form-control"
                                                value="<?= htmlspecialchars($detenu['nationalite']) ?>">
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Situation Matrimoniale</label>
                                                <select name="situation_matrimoniale" class="form-select">
                                                    <option value="">Sélectionner</option>
                                                    <option value="CELIBATAIRE"
                                                        <?= $detenu['situation_matrimoniale'] === 'CELIBATAIRE' ? 'selected' : '' ?>>
                                                        Célibataire
                                                    </option>
                                                    <option value="MARIE"
                                                        <?= $detenu['situation_matrimoniale'] === 'MARIE' ? 'selected' : '' ?>>
                                                        Marié(e)
                                                    </option>
                                                    <option value="DIVORCE"
                                                        <?= $detenu['situation_matrimoniale'] === 'DIVORCE' ? 'selected' : '' ?>>
                                                        Divorcé(e)
                                                    </option>
                                                    <option value="VEUF"
                                                        <?= $detenu['situation_matrimoniale'] === 'VEUF' ? 'selected' : '' ?>>
                                                        Veuf(ve)
                                                    </option>
                                                </select>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Nombre d'Enfants</label>
                                                <input type="number" name="nombre_enfants" class="form-control" min="0"
                                                    value="<?= (int)$detenu['nombre_enfants'] ?>">
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">
                                                Changer la Photo
                                                <?php if (!empty($detenu['photo_path'])): ?>
                                                    <span class="text-muted">(Photo actuelle affichée ci-dessus)</span>
                                                <?php endif; ?>
                                            </label>
                                            <input type="file" name="photo" class="form-control" accept="image/*">
                                            <small class="text-muted">
                                                Format : JPG, PNG (max 5MB). Laissez vide pour conserver la photo
                                                actuelle.
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Informations militaires & Contact -->
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
                                                        <?= $detenu['grade_id'] == $grade['id'] ? 'selected' : '' ?>>
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
                                                        <?= $detenu['unite_id'] == $unite['id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($unite['code'] . ' - ' . $unite['nom']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Matricule Militaire</label>
                                            <input type="text" name="matricule_militaire" class="form-control"
                                                value="<?= htmlspecialchars($detenu['matricule_militaire'] ?? '') ?>">
                                            <small class="text-muted">
                                                Matricule détenu: <?= htmlspecialchars($detenu['matricule']) ?> (non
                                                modifiable)
                                            </small>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Date d'Incorporation</label>
                                            <input type="date" name="date_incorporation" class="form-control"
                                                value="<?= htmlspecialchars($detenu['date_incorporation'] ?? '') ?>">
                                        </div>
                                    </div>
                                </div>

                                <!-- Contact -->
                                <div class="card mt-3">
                                    <div class="card-header">
                                        <h4 class="card-title">
                                            <i class="fas fa-phone me-2"></i>Coordonnées
                                        </h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">Téléphone</label>
                                            <input type="tel" name="telephone" class="form-control"
                                                value="<?= htmlspecialchars($detenu['telephone'] ?? '') ?>">
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Email</label>
                                            <input type="email" name="email" class="form-control"
                                                value="<?= htmlspecialchars($detenu['email'] ?? '') ?>">
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Adresse</label>
                                            <textarea name="adresse" class="form-control"
                                                rows="2"><?= htmlspecialchars($detenu['adresse'] ?? '') ?></textarea>
                                        </div>

                                        <hr>
                                        <h5 class="mb-3">Personne à Contacter</h5>

                                        <div class="mb-3">
                                            <label class="form-label">Nom</label>
                                            <input type="text" name="personne_contact_nom" class="form-control"
                                                value="<?= htmlspecialchars($detenu['personne_contact_nom'] ?? '') ?>">
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Téléphone</label>
                                                <input type="tel" name="personne_contact_telephone" class="form-control"
                                                    value="<?= htmlspecialchars($detenu['personne_contact_telephone'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Relation</label>
                                                <input type="text" name="personne_contact_relation" class="form-control"
                                                    placeholder="Ex: Épouse, Père..."
                                                    value="<?= htmlspecialchars($detenu['personne_contact_relation'] ?? '') ?>">
                                            </div>
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
                                                    Les champs marqués d'un <span class="text-danger">*</span> sont
                                                    obligatoires
                                                </small>
                                            </div>
                                            <div>
                                                <a href="voir_detenu.php?id=<?= $detenu['id'] ?>"
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

                    <!-- Changement de statut (Admin uniquement) -->
                    <?php if ($user['role'] === 'ADMIN'): ?>
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="card border-warning">
                                    <div class="card-header bg-warning text-white">
                                        <h4 class="card-title mb-0">
                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                            Zone Administrateur
                                        </h4>
                                    </div>
                                    <div class="card-body">
                                        <p class="text-muted">
                                            Le changement de statut est une opération sensible.
                                            Pour modifier le statut, utilisez le module de gestion des condamnations.
                                        </p>
                                        <p class="mb-0">
                                            <strong>Statut actuel:</strong>
                                            <span class="badge badge-<?= $color ?> ms-2">
                                                <?= str_replace('_', ' ', $detenu['statut_actuel']) ?>
                                            </span>
                                        </p>
                                        <p class="text-muted small mb-0">
                                            Dernière modification:
                                            <?= date('d/m/Y H:i', strtotime($detenu['date_changement_statut'])) ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php include '../../requires/script.php'; ?>
</body>

</html>