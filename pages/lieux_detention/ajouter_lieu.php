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
    $required = ['code', 'nom', 'type'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            $errors[] = "Le champ " . ucfirst($field) . " est obligatoire.";
        }
    }

    // Vérifier code unique
    if (!empty($_POST['code'])) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as nb FROM lieux_detention WHERE code = ?");
        $stmt->execute([$_POST['code']]);
        if ((int)$stmt->fetch()['nb'] > 0) {
            $errors[] = "Ce code existe déjà.";
        }
    }

    if (empty($errors)) {
        $data = [
            'code' => strtoupper(trim($_POST['code'])),
            'nom' => trim($_POST['nom']),
            'type' => $_POST['type'],
            'capacite' => !empty($_POST['capacite']) ? (int)$_POST['capacite'] : null,
            'adresse' => trim($_POST['adresse']) ?: null,
            'ville' => trim($_POST['ville']) ?: null,
            'telephone' => trim($_POST['telephone']) ?: null
        ];

        $lieuId = $refMgr->createLieuDetention($data);

        if ($lieuId) {
            log_activity($pdo, $_SESSION['user_id'], 'Ajout lieu détention', "Nouveau lieu: " . $data['nom']);
            $success = "Lieu de détention ajouté avec succès !";
            header("refresh:2;url=lieux_detention.php");
        } else {
            $errors[] = "Erreur lors de l'ajout du lieu.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Ajouter un Lieu - Système Militaire</title>
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
                            <i class="fas fa-plus me-2"></i>Ajouter un Lieu de Détention
                        </h3>
                        <ul class="breadcrumbs mb-3">
                            <li class="nav-home"><a href="../dash/dashboard.php"><i class="fas fa-home"></i></a></li>
                            <li class="separator"><i class="fas fa-arrow-right"></i></li>
                            <li class="nav-item"><a href="lieux_detention.php">Lieux de Détention</a></li>
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
                                            Lieu</h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Code <span
                                                        class="text-danger">*</span></label>
                                                <input type="text" name="code" class="form-control"
                                                    placeholder="Ex: MACA, CAP" required
                                                    style="text-transform: uppercase;"
                                                    value="<?= htmlspecialchars($_POST['code'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Type <span
                                                        class="text-danger">*</span></label>
                                                <select name="type" class="form-select" required>
                                                    <option value="">Sélectionner</option>
                                                    <option value="PRISON_MILITAIRE">Prison Militaire</option>
                                                    <option value="PRISON_CIVILE">Prison Civile</option>
                                                    <option value="MAISON_ARRET">Maison d'Arrêt</option>
                                                    <option value="AUTRES">Autres</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Nom <span class="text-danger">*</span></label>
                                            <input type="text" name="nom" class="form-control"
                                                placeholder="Ex: Maison d'Arrêt et de Correction d'Abidjan" required
                                                value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>">
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Capacité d'Accueil</label>
                                                <input type="number" name="capacite" class="form-control" min="0"
                                                    placeholder="Ex: 500"
                                                    value="<?= htmlspecialchars($_POST['capacite'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Ville</label>
                                                <input type="text" name="ville" class="form-control"
                                                    placeholder="Ex: Abidjan"
                                                    value="<?= htmlspecialchars($_POST['ville'] ?? '') ?>">
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Adresse Complète</label>
                                            <textarea name="adresse" class="form-control" rows="2"
                                                placeholder="Adresse, localisation..."><?= htmlspecialchars($_POST['adresse'] ?? '') ?></textarea>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Téléphone</label>
                                            <input type="tel" name="telephone" class="form-control"
                                                placeholder="+225 XX XX XX XX XX"
                                                value="<?= htmlspecialchars($_POST['telephone'] ?? '') ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-4">
                                <div class="card">
                                    <div class="card-header bg-primary text-white">
                                        <h4 class="card-title mb-0"><i class="fas fa-info-circle me-2"></i>Types de
                                            Lieux</h4>
                                    </div>
                                    <div class="card-body">
                                        <h6 class="text-primary"><i class="fas fa-shield-alt me-2"></i>Prison Militaire
                                        </h6>
                                        <p class="small mb-3">Établissements pénitentiaires gérés par l'armée.</p>

                                        <h6 class="text-info"><i class="fas fa-building me-2"></i>Prison Civile</h6>
                                        <p class="small mb-3">Prisons civiles pouvant accueillir des militaires.</p>

                                        <h6 class="text-warning"><i class="fas fa-home me-2"></i>Maison d'Arrêt</h6>
                                        <p class="small mb-3">Pour détention provisoire et courtes peines.</p>

                                        <h6 class="text-secondary"><i class="fas fa-question me-2"></i>Autres</h6>
                                        <p class="small mb-0">Centres spécialisés, camps, etc.</p>
                                    </div>
                                </div>

                                <div class="card mt-3">
                                    <div class="card-header bg-success text-white">
                                        <h4 class="card-title mb-0"><i class="fas fa-lightbulb me-2"></i>Exemples</h4>
                                    </div>
                                    <div class="card-body">
                                        <ul class="list-unstyled mb-0">
                                            <li class="mb-2"><strong>MACA</strong><br><small>Maison d'Arrêt et de
                                                    Correction d'Abidjan</small></li>
                                            <li class="mb-2"><strong>CAP</strong><br><small>Camp Pénal d'Akouédo</small>
                                            </li>
                                            <li class="mb-0"><strong>PMBA</strong><br><small>Prison Militaire de
                                                    Bouaké</small></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-body text-end">
                                        <a href="lieux_detention.php" class="btn btn-secondary me-2">
                                            <i class="fas fa-times me-2"></i>Annuler
                                        </a>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>Enregistrer
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
    document.querySelector('input[name="code"]').addEventListener('input', function() {
        this.value = this.value.toUpperCase().replace(/[^A-Z0-9_]/g, '');
    });
    </script>
</body>

</html>